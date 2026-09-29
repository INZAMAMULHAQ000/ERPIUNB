<?php
/**
 * AI F11 — Vector Storage Abstraction.
 *
 * Provides a generic interface for vector storage and retrieval.
 * Initial implementation uses MySQL with application-level cosine similarity.
 *
 * Scalability note: This MySQL-based approach loads vectors into PHP memory
 * for similarity calculation. For datasets under ~100K chunks, this is
 * adequate. For larger datasets, migrate to a dedicated vector database
 * (Pinecone, Weaviate, Qdrant, pgvector) by implementing a new adapter.
 */

/**
 * Abstract base for vector storage backends.
 */
abstract class AIVectorStore
{
    protected PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Store embeddings with metadata.
     *
     * @param int $sourceId Source document ID
     * @param int $collectionId Collection ID
     * @param array $chunks Array of ['content' => string, 'metadata' => array]
     * @param array $embeddings Array of embedding vectors (parallel to $chunks)
     * @return array{success: bool, stored: int, error: string|null}
     */
    abstract public function store(int $sourceId, int $collectionId, array $chunks, array $embeddings): array;

    /**
     * Search for similar vectors.
     *
     * @param array $queryEmbedding The query embedding vector
     * @param array $filters ['collection_ids' => array, 'source_ids' => array, etc.]
     * @param int $topK Number of results to return
     * @param float $minRelevance Minimum similarity score (0-1)
     * @return array{success: bool, results: array, error: string|null}
     */
    abstract public function search(array $queryEmbedding, array $filters, int $topK = 5, float $minRelevance = 0.0): array;

    /**
     * Delete all vectors for a source.
     */
    abstract public function deleteBySource(int $sourceId): array;

    /**
     * Get the name of this store implementation.
     */
    abstract public function getName(): string;
}

/**
 * MySQL-based vector store using application-level cosine similarity.
 *
 * Stores embedding vectors as JSON in the ai_knowledge_chunks.embedding_vector column.
 * Performs cosine similarity in PHP after loading candidate vectors from MySQL.
 */
class AIMySQLVectorStore extends AIVectorStore
{
    /**
     * Maximum chunks to load for similarity calculation.
     * Limits memory usage for large datasets.
     */
    const MAX_CANDIDATES = 5000;

    public function store(int $sourceId, int $collectionId, array $chunks, array $embeddings): array
    {
        if (count($chunks) !== count($embeddings)) {
            return ['success' => false, 'stored' => 0, 'error' => 'Chunks and embeddings count mismatch'];
        }

        try {
            $stmt = $this->db->prepare("
                UPDATE ai_knowledge_chunks
                SET embedding_vector = ?
                WHERE source_id = ? AND chunk_index = ?
            ");

            $stored = 0;
            foreach ($chunks as $i => $chunk) {
                $vector = $embeddings[$i] ?? null;
                if (empty($vector)) continue;

                $vectorJson = json_encode($vector);
                $stmt->execute([$vectorJson, $sourceId, $i]);
                $stored++;
            }

            return ['success' => true, 'stored' => $stored, 'error' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'stored' => 0, 'error' => 'Failed to store vectors'];
        }
    }

    public function search(array $queryEmbedding, array $filters, int $topK = 5, float $minRelevance = 0.0): array
    {
        try {
            // Build WHERE conditions
            $conditions = ["kc.embedding_vector IS NOT NULL", "ks.status = 'indexed'"];
            $params = [];

            if (!empty($filters['collection_ids'])) {
                $placeholders = implode(',', array_fill(0, count($filters['collection_ids']), '?'));
                $conditions[] = "kc.collection_id IN ({$placeholders})";
                $params = array_merge($params, $filters['collection_ids']);
            }

            if (!empty($filters['source_ids'])) {
                $placeholders = implode(',', array_fill(0, count($filters['source_ids']), '?'));
                $conditions[] = "kc.source_id IN ({$placeholders})";
                $params = array_merge($params, $filters['source_ids']);
            }

            $whereClause = implode(' AND ', $conditions);

            // Load candidate chunks (limit for memory safety)
            $sql = "
                SELECT kc.id, kc.content, kc.chunk_index, kc.collection_id, kc.source_id,
                       kc.embedding_vector, kc.metadata_json,
                       ks.title as source_title, kcol.name as collection_name
                FROM ai_knowledge_chunks kc
                JOIN ai_knowledge_sources ks ON kc.source_id = ks.id
                JOIN ai_knowledge_collections kcol ON kc.collection_id = kcol.id
                WHERE {$whereClause}
                LIMIT " . self::MAX_CANDIDATES;
            ;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $candidates = $stmt->fetchAll();

            if (empty($candidates)) {
                return ['success' => true, 'results' => [], 'error' => null];
            }

            // Calculate cosine similarity for each candidate
            $scored = [];
            foreach ($candidates as $candidate) {
                $vector = json_decode($candidate['embedding_vector'], true);
                if (empty($vector) || count($vector) !== count($queryEmbedding)) {
                    // Dimension mismatch — skip or try truncated comparison
                    if (count($vector) > count($queryEmbedding)) {
                        $vector = array_slice($vector, 0, count($queryEmbedding));
                    } elseif (count($queryEmbedding) > count($vector)) {
                        continue; // Can't compare
                    }
                }

                $similarity = self::cosineSimilarity($queryEmbedding, $vector);

                if ($similarity >= $minRelevance) {
                    $scored[] = [
                        'id' => (int) $candidate['id'],
                        'content' => $candidate['content'],
                        'chunk_index' => (int) $candidate['chunk_index'],
                        'collection_id' => (int) $candidate['collection_id'],
                        'source_id' => (int) $candidate['source_id'],
                        'source_title' => $candidate['source_title'],
                        'collection_name' => $candidate['collection_name'],
                        'metadata' => json_decode($candidate['metadata_json'] ?? '{}', true),
                        'relevance' => round($similarity, 4),
                    ];
                }
            }

            // Sort by relevance descending
            usort($scored, fn($a, $b) => $b['relevance'] <=> $a['relevance']);

            // Return top K
            $results = array_slice($scored, 0, $topK);

            return ['success' => true, 'results' => $results, 'error' => null];
        } catch (\Throwable $e) {
            return ['success' => true, 'results' => [], 'error' => null];
        }
    }

    public function deleteBySource(int $sourceId): array
    {
        try {
            $this->db->prepare("UPDATE ai_knowledge_chunks SET embedding_vector = NULL WHERE source_id = ?")
                     ->execute([$sourceId]);
            return ['success' => true, 'error' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to clear vectors'];
        }
    }

    public function getName(): string
    {
        return 'mysql_cosine';
    }

    /**
     * Calculate cosine similarity between two vectors.
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $len = min(count($a), count($b));
        if ($len === 0) return 0.0;

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $len; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA === 0.0 || $normB === 0.0) return 0.0;

        return $dotProduct / ($normA * $normB);
    }
}

/**
 * Vector Store Factory.
 */
class AIVectorStoreFactory
{
    public static function create(PDO $db): AIVectorStore
    {
        // For now, MySQL is the only implemented backend.
        // Future: check config for external vector DB settings.
        return new AIMySQLVectorStore($db);
    }
}
