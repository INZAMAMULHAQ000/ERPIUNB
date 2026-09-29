<?php
/**
 * AI F11 — Embedding Provider Abstraction.
 *
 * Provides a generic interface for embedding generation.
 * Supports OpenAI-compatible APIs (OpenAI, OpenRouter, NVIDIA NIM, custom).
 */

/**
 * Abstract base for embedding providers.
 */
abstract class AIBaseEmbeddingProvider
{
    protected PDO $db;
    protected ?string $apiKey;
    protected string $baseUrl;

    public function __construct(PDO $db, ?string $apiKey, string $baseUrl)
    {
        $this->db = $db;
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Generate embeddings for an array of texts.
     *
     * @param string[] $texts Array of text strings to embed
     * @return array{success: bool, embeddings: array, dimensions: int|null, error: string|null}
     */
    abstract public function embed(array $texts): array;

    /**
     * Get the embedding dimensions for this model.
     */
    abstract public function getDimensions(): int;

    /**
     * Check if the provider is available and configured.
     */
    abstract public function isAvailable(): bool;

    /**
     * Get the model identifier used for embeddings.
     */
    abstract public function getModelId(): string;

    /**
     * Make a POST request to the embedding API.
     */
    protected function apiPost(string $path, $body): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $http = new AIHttpClient(45, true);
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        if ($this->apiKey) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }
        return $http->post($url, $body, $headers);
    }
}

/**
 * OpenAI-compatible embedding provider.
 * Works with OpenAI, OpenRouter, NVIDIA NIM, and any OpenAI-compatible API.
 */
class AIOpenAIEmbeddingProvider extends AIBaseEmbeddingProvider
{
    private string $modelId;
    private int $dimensions;

    public function __construct(PDO $db, ?string $apiKey, string $baseUrl, string $modelId = 'text-embedding-3-small', int $dimensions = 1536)
    {
        parent::__construct($db, $apiKey, $baseUrl);
        $this->modelId = $modelId;
        $this->dimensions = $dimensions;
    }

    public function embed(array $texts): array
    {
        if (empty($texts)) {
            return ['success' => true, 'embeddings' => [], 'dimensions' => $this->dimensions, 'error' => null];
        }

        // OpenAI API limits batch to ~2048 texts per request
        // Process in batches of 100 for safety
        $allEmbeddings = [];
        $batches = array_chunk($texts, 100);

        foreach ($batches as $batch) {
            $body = [
                'model' => $this->modelId,
                'input' => $batch,
            ];

            $result = $this->apiPost('embeddings', $body);

            if (!$result['success']) {
                return [
                    'success' => false,
                    'embeddings' => [],
                    'dimensions' => null,
                    'error' => $result['error'] ?? 'Embedding request failed',
                ];
            }

            $data = $result['data'];
            if (!is_array($data) || empty($data['data'])) {
                $errMsg = $data['error']['message'] ?? $data['error'] ?? 'Invalid embedding response';
                return [
                    'success' => false,
                    'embeddings' => [],
                    'dimensions' => null,
                    'error' => is_string($errMsg) ? $errMsg : json_encode($errMsg),
                ];
            }

            // Extract embeddings in order
            $sorted = $data['data'];
            usort($sorted, fn($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

            foreach ($sorted as $item) {
                $allEmbeddings[] = $item['embedding'] ?? [];
            }
        }

        // Detect actual dimensions from first embedding
        $actualDimensions = !empty($allEmbeddings[0]) ? count($allEmbeddings[0]) : $this->dimensions;

        return [
            'success' => true,
            'embeddings' => $allEmbeddings,
            'dimensions' => $actualDimensions,
            'error' => null,
        ];
    }

    public function getDimensions(): int
    {
        return $this->dimensions;
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey) && !empty($this->baseUrl);
    }

    public function getModelId(): string
    {
        return $this->modelId;
    }
}

/**
 * Local/fallback embedding provider using TF-IDF-like feature hashing.
 *
 * This is a lightweight fallback when no external embedding API is configured.
 * It produces consistent pseudo-embeddings using feature hashing, enabling
 * cosine similarity search without any external dependency.
 *
 * IMPORTANT: This is NOT a replacement for real embeddings. It provides basic
 * semantic similarity through term frequency hashing. For production RAG,
 * configure an external embedding provider.
 */
class AILocalEmbeddingProvider extends AIBaseEmbeddingProvider
{
    private int $dimensions;

    public function __construct(PDO $db, int $dimensions = 384)
    {
        parent::__construct($db, null, '');
        $this->dimensions = $dimensions;
    }

    public function embed(array $texts): array
    {
        $embeddings = [];
        foreach ($texts as $text) {
            $embeddings[] = $this->hashText($text);
        }

        return [
            'success' => true,
            'embeddings' => $embeddings,
            'dimensions' => $this->dimensions,
            'error' => null,
        ];
    }

    /**
     * Generate a feature-hash vector for text.
     * Uses token n-grams hashed into a fixed-dimension vector.
     */
    private function hashText(string $text): array
    {
        $vector = array_fill(0, $this->dimensions, 0.0);

        // Normalize text
        $text = strtolower(trim($text));
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        // Tokenize
        $tokens = explode(' ', $text);
        $tokens = array_filter($tokens, fn($t) => strlen($t) >= 2);

        if (empty($tokens)) return $vector;

        // Unigrams + bigrams
        $terms = [];
        $tokenArr = array_values($tokens);

        for ($i = 0; $i < count($tokenArr); $i++) {
            $terms[] = $tokenArr[$i];
            if ($i + 1 < count($tokenArr)) {
                $terms[] = $tokenArr[$i] . ' ' . $tokenArr[$i + 1];
            }
        }

        // Hash each term into the vector
        foreach ($terms as $term) {
            $hash = crc32($term);
            $idx = abs($hash) % $this->dimensions;
            // Use sign of hash for direction
            $vector[$idx] += ($hash >= 0) ? 1.0 : -1.0;
        }

        // L2 normalize
        $norm = 0.0;
        foreach ($vector as $v) $norm += $v * $v;
        $norm = sqrt($norm);
        if ($norm > 0) {
            $vector = array_map(fn($v) => $v / $norm, $vector);
        }

        return $vector;
    }

    public function getDimensions(): int
    {
        return $this->dimensions;
    }

    public function isAvailable(): bool
    {
        return true; // Always available
    }

    public function getModelId(): string
    {
        return 'local-feature-hash';
    }
}

/**
 * Embedding Provider Factory.
 *
 * Resolves the correct embedding provider based on configuration.
 */
class AIEmbeddingProviderFactory
{
    /**
     * Create an embedding provider from configuration.
     *
     * @param PDO $db Database connection
     * @return AIBaseEmbeddingProvider
     */
    public static function create(PDO $db): AIBaseEmbeddingProvider
    {
        // Check for configured embedding model in ai_models table
        $stmt = $db->prepare("
            SELECT m.*, p.provider_type, p.base_url, p.api_key_encrypted, p.api_key_iv
            FROM ai_models m
            JOIN ai_providers p ON m.provider_id = p.id
            WHERE m.model_type = 'embedding' AND m.is_active = 1 AND p.is_active = 1
            ORDER BY m.is_default DESC, m.id ASC
            LIMIT 1
        ");
        $stmt->execute();
        $model = $stmt->fetch();

        if ($model && !empty($model['model_id'])) {
            // Decrypt API key
            $apiKey = null;
            if (!empty($model['api_key_encrypted']) && !empty($model['api_key_iv'])) {
                $apiKey = aiDecryptCredential($model['api_key_encrypted'], $model['api_key_iv']);
            }

            if ($apiKey) {
                $baseUrl = $model['base_url'] ?? '';
                if ($baseUrl === '') {
                    // Determine default base URL from provider type
                    $baseUrl = match($model['provider_type'] ?? '') {
                        'openai'            => 'https://api.openai.com/v1',
                        'openrouter'        => 'https://openrouter.ai/api/v1',
                        'nvidia_nim'        => 'https://integrate.api.nvidia.com/v1',
                        'openai_compatible' => $model['base_url'] ?? '',
                        default             => '',
                    };
                }

                if ($baseUrl !== '') {
                    $dimensions = (int) ($model['embedding_dimensions'] ?? 1536);
                    return new AIOpenAIEmbeddingProvider($db, $apiKey, $baseUrl, $model['model_id'], $dimensions);
                }
            }
        }

        // Fallback: local feature-hash embeddings (no API key needed)
        return new AILocalEmbeddingProvider($db, 384);
    }
}
