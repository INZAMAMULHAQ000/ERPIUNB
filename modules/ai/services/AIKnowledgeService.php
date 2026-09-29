<?php
/**
 * AI F11 — Knowledge Base Service.
 *
 * Manages knowledge collections, sources, chunks, embeddings, and retrieval.
 * Uses configurable embedding providers and vector storage abstraction.
 */

class AIKnowledgeService
{
    const MAX_UPLOAD_SIZE = 10 * 1024 * 1024; // 10MB
    const MAX_EXTRACTED_TEXT = 500000; // 500KB
    const MAX_CHUNKS_PER_SOURCE = 500;
    const CHUNK_SIZE = 1000; // characters
    const CHUNK_OVERLAP = 200; // characters
    const MAX_RETRIEVAL_RESULTS = 10;
    const MAX_CONTEXT_CHARS = 8000;
    const MIN_SIMILARITY_THRESHOLD = 0.15;
    const MAX_EMBEDDING_BATCH = 50;

    const ALLOWED_MIME_TYPES = [
        'text/plain',
        'text/markdown',
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    const ALLOWED_EXTENSIONS = ['txt', 'md', 'pdf', 'docx'];

    private static function getCurrentCompanyId(): int
    {
        return (int) ($_SESSION['company_id'] ?? 1);
    }

    private static function fetchCollectionForCompany(PDO $db, int $collectionId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_knowledge_collections WHERE id = ? AND company_id = ? LIMIT 1");
        $stmt->execute([$collectionId, self::getCurrentCompanyId()]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function fetchSourceForCompany(PDO $db, int $sourceId): ?array
    {
        $stmt = $db->prepare("
            SELECT s.*
            FROM ai_knowledge_sources s
            JOIN ai_knowledge_collections c ON c.id = s.collection_id
            WHERE s.id = ? AND c.company_id = ?
            LIMIT 1
        ");
        $stmt->execute([$sourceId, self::getCurrentCompanyId()]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ═══ Collection Operations ═══

    public static function createCollection(PDO $db, int $userId, string $name, ?string $description, ?string $module, string $visibility = 'company'): array
    {
        $name = trim($name);
        if ($name === '') return ['success' => false, 'error' => 'Collection name is required.'];

        try {
            $companyId = self::getCurrentCompanyId();
            $stmt = $db->prepare("
                INSERT INTO ai_knowledge_collections (company_id, name, description, module, visibility, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, 'active', ?, NOW())
            ");
            $stmt->execute([$companyId, $name, $description, $module, $visibility, $userId]);
            $id = (int) $db->lastInsertId();
            self::log($db, $userId, 'collection_created', $id);
            return ['success' => true, 'id' => $id];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to create collection.'];
        }
    }

    public static function getCollections(PDO $db, int $userId, ?string $status = 'active'): array
    {
        $companyId = self::getCurrentCompanyId();
        $sql = "SELECT * FROM ai_knowledge_collections WHERE company_id = ?";
        $params = [$companyId];
        if ($status) { $sql .= " AND status = ?"; $params[] = $status; }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getCollectionById(PDO $db, int $id): ?array
    {
        return self::fetchCollectionForCompany($db, $id);
    }

    public static function updateCollection(PDO $db, int $id, array $updates): array
    {
        if (!self::fetchCollectionForCompany($db, $id)) {
            return ['success' => false, 'error' => 'Collection not found or access denied.'];
        }

        $fields = [];
        $params = [];
        foreach (['name', 'description', 'module', 'visibility', 'status'] as $f) {
            if (array_key_exists($f, $updates)) {
                $fields[] = "{$f} = ?";
                $params[] = $updates[$f];
            }
        }
        if (empty($fields)) return ['success' => false, 'error' => 'Nothing to update.'];
        $fields[] = 'updated_at = NOW()';
        $params[] = $id;
        $params[] = self::getCurrentCompanyId();
        $db->prepare("UPDATE ai_knowledge_collections SET " . implode(', ', $fields) . " WHERE id = ? AND company_id = ?")->execute($params);
        return ['success' => true];
    }

    // ═══ Restricted Collection Permissions ═══

    /**
     * Grant access to a restricted collection for specific roles/users.
     */
    public static function grantAccess(PDO $db, int $collectionId, ?string $role, ?int $userId): array
    {
        if (empty($role) && empty($userId)) {
            return ['success' => false, 'error' => 'Role or user ID required.'];
        }
        if (!self::fetchCollectionForCompany($db, $collectionId)) {
            return ['success' => false, 'error' => 'Collection not found or access denied.'];
        }
        try {
            $stmt = $db->prepare("
                INSERT IGNORE INTO ai_knowledge_collection_access (collection_id, role, user_id, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$collectionId, $role ?: null, $userId ?: null]);
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to grant access.'];
        }
    }

    /**
     * Revoke access from a restricted collection.
     */
    public static function revokeAccess(PDO $db, int $collectionId, ?string $role, ?int $userId): array
    {
        if (!self::fetchCollectionForCompany($db, $collectionId)) {
            return ['success' => false, 'error' => 'Collection not found or access denied.'];
        }
        try {
            $conditions = ['collection_id = ?'];
            $params = [$collectionId];
            if ($role) { $conditions[] = 'role = ?'; $params[] = $role; }
            if ($userId) { $conditions[] = 'user_id = ?'; $params[] = $userId; }
            $db->prepare("DELETE FROM ai_knowledge_collection_access WHERE " . implode(' AND ', $conditions))->execute($params);
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to revoke access.'];
        }
    }

    /**
     * Get access list for a restricted collection.
     */
    public static function getAccessList(PDO $db, int $collectionId): array
    {
        if (!self::fetchCollectionForCompany($db, $collectionId)) {
            return [];
        }
        $stmt = $db->prepare("SELECT * FROM ai_knowledge_collection_access WHERE collection_id = ?");
        $stmt->execute([$collectionId]);
        return $stmt->fetchAll();
    }

    // ═══ Source Operations ═══

    public static function createTextSource(PDO $db, int $userId, int $collectionId, string $title, string $content): array
    {
        $title = trim($title);
        $content = trim($content);
        if ($title === '' || $content === '') return ['success' => false, 'error' => 'Title and content are required.'];
        if (!self::fetchCollectionForCompany($db, $collectionId)) {
            return ['success' => false, 'error' => 'Collection not found or access denied.'];
        }

        if (strlen($content) > self::MAX_EXTRACTED_TEXT) {
            return ['success' => false, 'error' => 'Content too large (max ' . (self::MAX_EXTRACTED_TEXT / 1024) . 'KB).'];
        }

        try {
            $checksum = hash('sha256', $content);
            $stmt = $db->prepare("
                INSERT INTO ai_knowledge_sources (collection_id, title, source_type, checksum, status, created_by, created_at)
                VALUES (?, ?, 'text', ?, 'uploaded', ?, NOW())
            ");
            $stmt->execute([$collectionId, $title, $checksum, $userId]);
            $sourceId = (int) $db->lastInsertId();

            // Process immediately
            $result = self::processSource($db, $sourceId, $content);
            return $result['success']
                ? ['success' => true, 'id' => $sourceId, 'chunks' => $result['chunks']]
                : ['success' => false, 'error' => $result['error']];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to create source.'];
        }
    }

    public static function createFileSource(PDO $db, int $userId, int $collectionId, string $title, array $file): array
    {
        $title = trim($title);
        if ($title === '') return ['success' => false, 'error' => 'Title is required.'];
        if (!self::fetchCollectionForCompany($db, $collectionId)) {
            return ['success' => false, 'error' => 'Collection not found or access denied.'];
        }

        // Validate file
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS)) {
            return ['success' => false, 'error' => 'Unsupported file type. Allowed: ' . implode(', ', self::ALLOWED_EXTENSIONS)];
        }

        if ($file['size'] > self::MAX_UPLOAD_SIZE) {
            return ['success' => false, 'error' => 'File too large (max ' . (self::MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB).'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Upload error: ' . $file['error']];
        }

        // Store file safely
        $storageDir = AI_APP_ROOT . '/storage/knowledge';
        if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);

        $safeFilename = bin2hex(random_bytes(16)) . '.' . $ext;
        $storagePath = $storageDir . '/' . $safeFilename;

        if (!move_uploaded_file($file['tmp_name'], $storagePath)) {
            return ['success' => false, 'error' => 'Failed to store file.'];
        }

        $checksum = hash_file('sha256', $storagePath);

        // Check for duplicate
        $dupStmt = $db->prepare("SELECT id FROM ai_knowledge_sources WHERE checksum = ? AND collection_id = ? AND status = 'indexed'");
        $dupStmt->execute([$checksum, $collectionId]);
        if ($dupStmt->fetch()) {
            unlink($storagePath);
            return ['success' => false, 'error' => 'This document already exists in this collection.'];
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO ai_knowledge_sources (collection_id, title, source_type, original_filename, storage_path, mime_type, file_size, checksum, status, created_by, created_at)
                VALUES (?, ?, 'file', ?, ?, ?, ?, ?, 'uploaded', ?, NOW())
            ");
            $stmt->execute([$collectionId, $title, $file['name'], $storagePath, $file['type'], $file['size'], $checksum, $userId]);
            $sourceId = (int) $db->lastInsertId();

            // Extract and process
            $content = AITextExtractor::extract($storagePath, $ext);
            if ($content === null) {
                $db->prepare("UPDATE ai_knowledge_sources SET status = 'failed', extraction_status = 'Extraction failed' WHERE id = ?")->execute([$sourceId]);
                return ['success' => false, 'error' => 'Failed to extract text from file.'];
            }

            $result = self::processSource($db, $sourceId, $content);
            return $result['success']
                ? ['success' => true, 'id' => $sourceId, 'chunks' => $result['chunks']]
                : ['success' => false, 'error' => $result['error']];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to create source.'];
        }
    }

    public static function getSourcesByCollection(PDO $db, int $collectionId): array
    {
        if (!self::fetchCollectionForCompany($db, $collectionId)) {
            return [];
        }
        $stmt = $db->prepare("SELECT * FROM ai_knowledge_sources WHERE collection_id = ? ORDER BY created_at DESC");
        $stmt->execute([$collectionId]);
        return $stmt->fetchAll();
    }

    public static function deleteSource(PDO $db, int $sourceId): array
    {
        $source = self::fetchSourceForCompany($db, $sourceId);
        if (!$source) return ['success' => false, 'error' => 'Source not found.'];

        // Clear vectors through abstraction
        $vectorStore = AIVectorStoreFactory::create($db);
        $vectorStore->deleteBySource($sourceId);

        // Delete chunks
        $db->prepare("DELETE FROM ai_knowledge_chunks WHERE source_id = ?")->execute([$sourceId]);
        // Delete source
        $db->prepare("DELETE FROM ai_knowledge_sources WHERE id = ?")->execute([$sourceId]);
        // Delete file if exists
        if ($source['storage_path'] && file_exists($source['storage_path'])) {
            unlink($source['storage_path']);
        }

        return ['success' => true];
    }

    // ═══ Reprocessing ═══

    /**
     * Reprocess a knowledge source: re-extract, re-chunk, re-embed.
     */
    public static function reprocessSource(PDO $db, int $userId, int $sourceId): array
    {
        $source = self::fetchSourceForCompany($db, $sourceId);
        if (!$source) return ['success' => false, 'error' => 'Source not found.'];

        try {
            $content = null;

            if ($source['source_type'] === 'file') {
                if (!$source['storage_path'] || !file_exists($source['storage_path'])) {
                    return ['success' => false, 'error' => 'Source file not found on disk.'];
                }
                $ext = strtolower(pathinfo($source['original_filename'], PATHINFO_EXTENSION));
                $content = AITextExtractor::extract($source['storage_path'], $ext);
                if ($content === null) {
                    $db->prepare("UPDATE ai_knowledge_sources SET status = 'failed', extraction_status = 'Re-extraction failed' WHERE id = ?")->execute([$sourceId]);
                    return ['success' => false, 'error' => 'Failed to re-extract text from file.'];
                }
            } else {
                // For text sources, get content from the most recent chunk
                $chunkStmt = $db->prepare("SELECT content FROM ai_knowledge_chunks WHERE source_id = ? ORDER BY chunk_index ASC");
                $chunkStmt->execute([$sourceId]);
                $chunks = $chunkStmt->fetchAll();
                if (!empty($chunks)) {
                    $content = implode("\n\n", array_column($chunks, 'content'));
                }
                // If no chunks exist, the text source is empty
                if (empty($content)) {
                    return ['success' => false, 'error' => 'No content found to reprocess.'];
                }
            }

            // Mark old vectors as stale
            $vectorStore = AIVectorStoreFactory::create($db);
            $vectorStore->deleteBySource($sourceId);

            // Re-process through the full pipeline
            $result = self::processSource($db, $sourceId, $content);

            self::log($db, $userId, 'source_reprocessed', $sourceId);

            return $result['success']
                ? ['success' => true, 'chunks' => $result['chunks']]
                : ['success' => false, 'error' => $result['error']];
        } catch (\Throwable $e) {
            $db->prepare("UPDATE ai_knowledge_sources SET status = 'failed', extraction_status = 'Reprocess error' WHERE id = ?")->execute([$sourceId]);
            return ['success' => false, 'error' => 'Reprocessing failed.'];
        }
    }

    // ═══ Processing Pipeline ═══

    private static function processSource(PDO $db, int $sourceId, string $content): array
    {
        // Step 1: Normalize
        $db->prepare("UPDATE ai_knowledge_sources SET status = 'extracting' WHERE id = ?")->execute([$sourceId]);
        $content = self::normalizeText($content);
        if (strlen($content) > self::MAX_EXTRACTED_TEXT) {
            $content = substr($content, 0, self::MAX_EXTRACTED_TEXT);
        }

        // Step 2: Chunk
        $db->prepare("UPDATE ai_knowledge_sources SET status = 'chunking' WHERE id = ?")->execute([$sourceId]);
        $chunks = AIChunker::chunk($content, self::CHUNK_SIZE, self::CHUNK_OVERLAP);

        if (count($chunks) > self::MAX_CHUNKS_PER_SOURCE) {
            $chunks = array_slice($chunks, 0, self::MAX_CHUNKS_PER_SOURCE);
        }

        // Get collection_id
        $srcStmt = $db->prepare("SELECT collection_id FROM ai_knowledge_sources WHERE id = ?");
        $srcStmt->execute([$sourceId]);
        $collectionId = (int) $srcStmt->fetchColumn();

        // Store chunks (clear old ones first)
        $db->prepare("DELETE FROM ai_knowledge_chunks WHERE source_id = ?")->execute([$sourceId]);

        $stmt = $db->prepare("
            INSERT INTO ai_knowledge_chunks (source_id, collection_id, chunk_index, content, content_hash, metadata_json, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($chunks as $i => $chunk) {
            $hash = hash('sha256', $chunk);
            $metadata = json_encode(['chunk_index' => $i, 'length' => strlen($chunk)]);
            $stmt->execute([$sourceId, $collectionId, $i, $chunk, $hash, $metadata]);
        }

        // Step 3: Generate embeddings
        $db->prepare("UPDATE ai_knowledge_sources SET status = 'embedding' WHERE id = ?")->execute([$sourceId]);
        $embeddingResult = self::generateEmbeddings($db, $sourceId, $chunks);

        // Step 4: Store vectors
        if ($embeddingResult['success'] && !empty($embeddingResult['embeddings'])) {
            $vectorStore = AIVectorStoreFactory::create($db);
            $chunkData = array_map(fn($c) => ['content' => $c, 'metadata' => []], $chunks);
            $vectorStore->store($sourceId, $collectionId, $chunkData, $embeddingResult['embeddings']);
        }

        // Step 5: Mark indexed
        $extractionStatus = $embeddingResult['success']
            ? 'OK (' . $embeddingResult['provider'] . ')'
            : 'OK (no embeddings: ' . ($embeddingResult['error'] ?? 'fallback') . ')';

        $db->prepare("
            UPDATE ai_knowledge_sources SET status = 'indexed', chunk_count = ?, extraction_status = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([count($chunks), $extractionStatus, $sourceId]);

        self::log($db, 0, 'source_indexed', $sourceId, count($chunks) . ' chunks, embeddings: ' . ($embeddingResult['success'] ? 'yes' : 'no'));

        return ['success' => true, 'chunks' => count($chunks)];
    }

    /**
     * Generate embeddings for chunks using the configured embedding provider.
     */
    private static function generateEmbeddings(PDO $db, int $sourceId, array $chunks): array
    {
        if (empty($chunks)) {
            return ['success' => true, 'embeddings' => [], 'provider' => 'none', 'error' => null];
        }

        try {
            $provider = AIEmbeddingProviderFactory::create($db);

            if (!$provider->isAvailable()) {
                return ['success' => false, 'embeddings' => [], 'provider' => $provider->getModelId(), 'error' => 'Provider not available'];
            }

            // Process in batches
            $allEmbeddings = [];
            $batches = array_chunk($chunks, self::MAX_EMBEDDING_BATCH);

            foreach ($batches as $batch) {
                $result = $provider->embed($batch);
                if (!$result['success']) {
                    return ['success' => false, 'embeddings' => [], 'provider' => $provider->getModelId(), 'error' => $result['error']];
                }
                $allEmbeddings = array_merge($allEmbeddings, $result['embeddings']);
            }

            return ['success' => true, 'embeddings' => $allEmbeddings, 'provider' => $provider->getModelId(), 'error' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'embeddings' => [], 'provider' => 'unknown', 'error' => 'Embedding generation failed'];
        }
    }

    private static function normalizeText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        // Remove control characters except newlines
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        return trim($text);
    }

    // ═══ Retrieval ═══

    public static function searchKnowledge(PDO $db, int $userId, string $query, ?int $collectionId = null, int $topK = 5): array
    {
        $query = trim($query);
        if ($query === '') return ['success' => false, 'error' => 'Query is required.', 'results' => []];

        // Get accessible collection IDs (with permission filtering)
        $accessibleCollections = self::getAccessibleCollections($db, $userId);
        if (empty($accessibleCollections)) {
            return ['success' => true, 'results' => [], 'message' => 'No accessible knowledge collections.'];
        }

        // Filter by specific collection if requested
        if ($collectionId) {
            if (!in_array($collectionId, $accessibleCollections)) {
                return ['success' => false, 'error' => 'Access denied to this collection.', 'results' => []];
            }
            $accessibleCollections = [$collectionId];
        }

        // Try embedding-based search first
        $vectorStore = AIVectorStoreFactory::create($db);
        $embeddingProvider = AIEmbeddingProviderFactory::create($db);

        if ($embeddingProvider->isAvailable()) {
            $queryResult = $embeddingProvider->embed([$query]);
            if ($queryResult['success'] && !empty($queryResult['embeddings'][0])) {
                $searchResult = $vectorStore->search(
                    $queryResult['embeddings'][0],
                    ['collection_ids' => $accessibleCollections],
                    min($topK, self::MAX_RETRIEVAL_RESULTS),
                    self::MIN_SIMILARITY_THRESHOLD
                );

                if ($searchResult['success'] && !empty($searchResult['results'])) {
                    self::logRetrieval($db, $userId, $collectionId, $searchResult['results'], $query);
                    return ['success' => true, 'results' => $searchResult['results']];
                }
            }
        }

        // Fallback: keyword-based search (when no embeddings available)
        return self::keywordSearch($db, $userId, $query, $accessibleCollections, $topK, $collectionId);
    }

    /**
     * Fallback keyword search when embeddings are not available.
     */
    private static function keywordSearch(PDO $db, int $userId, string $query, array $accessibleCollections, int $topK, ?int $collectionId): array
    {
        $placeholders = implode(',', array_fill(0, count($accessibleCollections), '?'));

        $queryWords = array_filter(explode(' ', strtolower($query)), fn($w) => strlen($w) >= 2);

        if (empty($queryWords)) {
            return ['success' => true, 'results' => [], 'message' => 'Query too short for meaningful search.'];
        }

        // Build LIKE conditions for each word
        $likeConditions = [];
        $params = [];
        foreach ($queryWords as $word) {
            $likeConditions[] = "LOWER(kc.content) LIKE ?";
            $params[] = '%' . addcslashes($word, '%_\\') . '%';
        }

        $safeLimit = (int) $topK;
        $sql = "
            SELECT kc.id, kc.content, kc.chunk_index, kc.collection_id, kc.source_id,
                   ks.title as source_title, kcol.name as collection_name
            FROM ai_knowledge_chunks kc
            JOIN ai_knowledge_sources ks ON kc.source_id = ks.id
            JOIN ai_knowledge_collections kcol ON kc.collection_id = kcol.id
            WHERE kc.collection_id IN ({$placeholders})
            AND ks.status = 'indexed'
            AND (" . implode(' OR ', $likeConditions) . ")
            ORDER BY kc.id ASC
            LIMIT {$safeLimit}
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge($accessibleCollections, $params));
        $results = $stmt->fetchAll();

        self::logRetrieval($db, $userId, $collectionId, $results, $query);

        return ['success' => true, 'results' => $results];
    }

    /**
     * Log retrieval event.
     */
    private static function logRetrieval(PDO $db, int $userId, ?int $collectionId, array $results, string $query): void
    {
        try {
            $sourceIds = array_unique(array_column($results, 'source_id'));
            $db->prepare("
                INSERT INTO ai_knowledge_retrieval_logs (user_id, collection_id, source_ids_json, query_summary, chunks_used, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ")->execute([
                $userId,
                $collectionId,
                json_encode($sourceIds),
                substr($query, 0, 255),
                count($results),
            ]);
        } catch (\Throwable $e) {}
    }

    /**
     * Format retrieval results for AI context.
     */
    public static function formatForAI(array $results): string
    {
        if (empty($results)) return '';

        $lines = ['[COMPANY KNOWLEDGE - Retrieved from internal documents]'];
        $lines[] = '';

        foreach ($results as $i => $r) {
            $title = $r['source_title'] ?? 'Unknown Source';
            $collection = $r['collection_name'] ?? 'Unknown Collection';
            $relevance = isset($r['relevance']) ? ' (relevance: ' . round($r['relevance'] * 100) . '%)' : '';
            $lines[] = "--- Source: {$title} ({$collection}){$relevance} ---";
            $lines[] = $r['content'];
            $lines[] = '';
        }

        $lines[] = '[End of company knowledge]';
        $lines[] = '';
        $lines[] = 'IMPORTANT: When citing this knowledge, always attribute the source by name.';
        $lines[] = 'Never fabricate citations. Only cite sources shown above.';

        $text = implode("\n", $lines);
        return substr($text, 0, self::MAX_CONTEXT_CHARS);
    }

    /**
     * Get collection IDs accessible to a user.
     *
     * Enforces:
     * - Company/tenant scope
     * - Collection visibility (company, private, restricted)
     * - Explicit permissions for restricted collections
     */
    public static function getAccessibleCollections(PDO $db, int $userId): array
    {
        // Get user's company for tenant isolation
        $companyId = self::getCurrentCompanyId();

        // Company collections (visible to all authenticated users IN THIS COMPANY)
        $stmt = $db->prepare("
            SELECT id FROM ai_knowledge_collections
            WHERE status = 'active' AND visibility = 'company'
            AND company_id = ?
        ");
        $stmt->execute([$companyId]);
        $accessible = array_column($stmt->fetchAll(), 'id');

        // Private collections owned by the user (within this company)
        $stmt = $db->prepare("
            SELECT id FROM ai_knowledge_collections
            WHERE status = 'active' AND visibility = 'private' AND created_by = ? AND company_id = ?
        ");
        $stmt->execute([$userId, $companyId]);
        $accessible = array_merge($accessible, array_column($stmt->fetchAll(), 'id'));

        // Restricted collections: owned by user (within this company)
        $stmt = $db->prepare("
            SELECT id FROM ai_knowledge_collections
            WHERE status = 'active' AND visibility = 'restricted' AND created_by = ? AND company_id = ?
        ");
        $stmt->execute([$userId, $companyId]);
        $accessible = array_merge($accessible, array_column($stmt->fetchAll(), 'id'));

        // Restricted collections: granted via access table (within this company)
        $stmt = $db->prepare("
            SELECT DISTINCT kac.collection_id
            FROM ai_knowledge_collection_access kac
            JOIN ai_knowledge_collections kcol ON kac.collection_id = kcol.id
            WHERE kcol.status = 'active' AND kcol.visibility = 'restricted'
            AND kcol.company_id = ?
            AND (
                kac.user_id = ?
                OR kac.role IN (
                    SELECT CONVERT(r.name USING utf8mb4) COLLATE utf8mb4_general_ci FROM roles r
                    JOIN user_roles ur ON r.id = ur.role_id
                    WHERE ur.user_id = ?
                )
            )
        ");
        $stmt->execute([$companyId, $userId, $userId]);
        $accessible = array_merge($accessible, array_column($stmt->fetchAll(), 'collection_id'));

        return array_unique($accessible);
    }

    // ═══ Audit ═══

    private static function log(PDO $db, int $userId, string $event, int $entityId, ?string $details = null): void
    {
        try {
            $db->prepare("
                INSERT INTO ai_audit_log (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, ?, 'knowledge', ?, ?, NOW())
            ")->execute([$userId, $event, $entityId, $details]);
        } catch (\Throwable $e) {}
    }
}
