<?php
/**
 * AI Provider Adapter Base & Factory.
 *
 * Abstract base for provider adapters and factory to resolve the correct adapter.
 */

require_once AI_MODULE_DIR . '/includes/ai_http_client.php';

/**
 * Base adapter with shared OpenAI-compatible HTTP logic.
 */
abstract class AIProviderAdapterBase
{
    protected PDO $db;
    protected array $provider;
    protected ?string $apiKey;
    protected AIHttpClient $http;

    public function __construct(PDO $db, array $provider, ?string $apiKey)
    {
        $this->db = $db;
        $this->provider = $provider;
        $this->apiKey = $apiKey;
        $this->http = new AIHttpClient(45, true);
    }

    /**
     * Test the connection to the provider.
     *
     * @return array{success: bool, message: string, details: array|null}
     */
    abstract public function testConnection(): array;

    /**
     * List available models from the provider.
     *
     * @return array{success: bool, models: array, error: string|null}
     */
    abstract public function listModels(): array;

    /**
     * Send a chat completion request.
     *
     * @param string $model The model identifier
     * @param array $messages Array of [role => string, content => string]
     * @param array $options Optional parameters (temperature, max_tokens, tools, etc.)
     * @return array{success: bool, content: string|null, tool_calls: array|null, input_tokens: int|null, output_tokens: int|null, error: string|null}
     */
    public function chat(string $model, array $messages, array $options = []): array
    {
        $body = [
            'model'    => $model,
            'messages' => $messages,
        ];

        if (isset($options['temperature'])) $body['temperature'] = (float) $options['temperature'];
        if (isset($options['max_tokens'])) $body['max_tokens'] = (int) $options['max_tokens'];

        // Include tools if provided and supported
        if (!empty($options['tools']) && $this->supportsToolCalling()) {
            $body['tools'] = $options['tools'];
            if (isset($options['tool_choice'])) {
                $body['tool_choice'] = $options['tool_choice'];
            }
        }

        $result = $this->apiPost('chat/completions', $body);

        if (!$result['success']) {
            $errMsg = $result['error'] ?? 'Request failed';
            return [
                'success'       => false,
                'content'       => null,
                'tool_calls'    => null,
                'input_tokens'  => null,
                'output_tokens' => null,
                'error'         => $errMsg,
            ];
        }

        $data = $result['data'];
        if (!is_array($data) || empty($data['choices'])) {
            $err = $data['error']['message'] ?? $data['error'] ?? $result['error'] ?? 'Empty or invalid response from model.';
            return [
                'success'       => false,
                'content'       => null,
                'tool_calls'    => null,
                'input_tokens'  => null,
                'output_tokens' => null,
                'error'         => is_string($err) ? $err : json_encode($err),
            ];
        }

        $choice = $data['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        $content = $message['content'] ?? $message['reasoning_content'] ?? null;
        $toolCalls = $message['tool_calls'] ?? null;
        $inputTokens = $data['usage']['prompt_tokens'] ?? null;
        $outputTokens = $data['usage']['completion_tokens'] ?? null;

        // If there are tool calls, content may be null — that's OK
        if ($content === null && empty($toolCalls)) {
            return [
                'success'       => false,
                'content'       => null,
                'tool_calls'    => null,
                'input_tokens'  => $inputTokens,
                'output_tokens' => $outputTokens,
                'error'         => 'No content in model response',
            ];
        }

        return [
            'success'       => true,
            'content'       => $content,
            'tool_calls'    => $toolCalls,
            'input_tokens'  => $inputTokens,
            'output_tokens' => $outputTokens,
            'error'         => null,
        ];
    }

    /**
     * Whether this provider supports tool/function calling.
     */
    public function supportsToolCalling(): bool
    {
        return true;
    }

    /**
     * Get the base API URL for this provider.
     */
    abstract protected function getBaseUrl(): string;

    /**
     * Get default headers for API requests.
     */
    protected function getHeaders(): array
    {
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        if ($this->apiKey) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }
        return $headers;
    }

    /**
     * Make a GET request to the provider API.
     */
    protected function apiGet(string $path): array
    {
        $url = rtrim($this->getBaseUrl(), '/') . '/' . ltrim($path, '/');
        return $this->http->get($url, $this->getHeaders());
    }

    /**
     * Make a POST request to the provider API.
     */
    protected function apiPost(string $path, $body = null): array
    {
        $url = rtrim($this->getBaseUrl(), '/') . '/' . ltrim($path, '/');
        return $this->http->post($url, $body, $this->getHeaders());
    }
}

/**
 * OpenAI Adapter
 */
class AIOpenAIAdapter extends AIProviderAdapterBase
{
    protected function getBaseUrl(): string
    {
        return !empty($this->provider['base_url']) ? $this->provider['base_url'] : 'https://api.openai.com/v1';
    }

    public function testConnection(): array
    {
        $result = $this->apiGet('models');

        if ($result['success']) {
            $count = isset($result['data']['data']) ? count($result['data']['data']) : 0;
            return ['success' => true, 'message' => "Connected successfully ($count models available)", 'details' => null];
        }

        return ['success' => false, 'message' => $result['error'] ?? 'Connection failed', 'details' => null];
    }

    public function listModels(): array
    {
        $result = $this->apiGet('models');

        if (!$result['success']) {
            return ['success' => false, 'models' => [], 'error' => $result['error'] ?? 'Failed to list models'];
        }

        $models = [];
        if (isset($result['data']['data']) && is_array($result['data']['data'])) {
            foreach ($result['data']['data'] as $m) {
                $id = $m['id'] ?? '';
                if (empty($id)) continue;
                $models[] = [
                    'model_id'     => $id,
                    'owned_by'     => $m['owned_by'] ?? '',
                    'display_name' => $id,
                ];
            }
        }

        usort($models, fn($a, $b) => strcasecmp($a['display_name'], $b['display_name']));
        return ['success' => true, 'models' => $models, 'error' => null];
    }
}

/**
 * OpenRouter Adapter
 */
class AIOpenRouterAdapter extends AIProviderAdapterBase
{
    protected function getBaseUrl(): string
    {
        return !empty($this->provider['base_url']) ? $this->provider['base_url'] : 'https://openrouter.ai/api/v1';
    }

    protected function getHeaders(): array
    {
        $headers = parent::getHeaders();
        $headers[] = 'HTTP-Referer: http://localhost';
        $headers[] = 'X-Title: ERP AI Assistant';
        return $headers;
    }

    public function testConnection(): array
    {
        $result = $this->apiGet('models');

        if ($result['success']) {
            $count = isset($result['data']['data']) ? count($result['data']['data']) : 0;
            return ['success' => true, 'message' => "Connected successfully ($count models available)", 'details' => null];
        }

        return ['success' => false, 'message' => $result['error'] ?? 'Connection failed', 'details' => null];
    }

    public function listModels(): array
    {
        $result = $this->apiGet('models');

        if (!$result['success']) {
            return ['success' => false, 'models' => [], 'error' => $result['error'] ?? 'Failed to list models'];
        }

        $models = [];
        if (isset($result['data']['data']) && is_array($result['data']['data'])) {
            foreach ($result['data']['data'] as $m) {
                $id = $m['id'] ?? '';
                if (empty($id)) continue;

                $rawName = $m['name'] ?? $id;
                $isFree = str_contains($id, ':free') ||
                          (isset($m['pricing']) && (float)($m['pricing']['prompt'] ?? 1) == 0 && (float)($m['pricing']['completion'] ?? 1) == 0);

                $displayName = $isFree ? "[FREE] $rawName" : $rawName;

                $models[] = [
                    'model_id'       => $id,
                    'owned_by'       => $m['owned_by'] ?? '',
                    'display_name'   => $displayName,
                    'description'    => $m['description'] ?? ($isFree ? 'Free model' : ''),
                    'context_window' => $m['context_length'] ?? null,
                    'is_free'        => $isFree,
                ];
            }
        }

        // Sort free models first, then alphabetically by display name
        usort($models, function($a, $b) {
            $freeA = !empty($a['is_free']) ? 1 : 0;
            $freeB = !empty($b['is_free']) ? 1 : 0;
            if ($freeA !== $freeB) {
                return $freeB <=> $freeA; // Free first
            }
            return strcasecmp($a['display_name'], $b['display_name']);
        });

        return ['success' => true, 'models' => $models, 'error' => null];
    }
}

/**
 * NVIDIA NIM Adapter
 */
class AINvidiaNimAdapter extends AIProviderAdapterBase
{
    protected function getBaseUrl(): string
    {
        return !empty($this->provider['base_url']) ? $this->provider['base_url'] : 'https://integrate.api.nvidia.com/v1';
    }

    public function testConnection(): array
    {
        $result = $this->apiGet('models');

        if ($result['success']) {
            $count = isset($result['data']['data']) ? count($result['data']['data']) : 0;
            return ['success' => true, 'message' => "Connected successfully ($count models available)", 'details' => null];
        }

        return ['success' => false, 'message' => $result['error'] ?? 'Connection failed', 'details' => null];
    }

    public function listModels(): array
    {
        $result = $this->apiGet('models');

        if (!$result['success']) {
            return ['success' => false, 'models' => [], 'error' => $result['error'] ?? 'Failed to list models'];
        }

        $models = [];
        if (isset($result['data']['data']) && is_array($result['data']['data'])) {
            foreach ($result['data']['data'] as $m) {
                $id = $m['id'] ?? '';
                if (empty($id)) continue;

                $models[] = [
                    'model_id'     => $id,
                    'owned_by'     => $m['owned_by'] ?? '',
                    'display_name' => $id,
                    'description'  => 'NVIDIA NIM Model (' . ($m['owned_by'] ?? 'NVIDIA') . ')',
                ];
            }
        }

        // Ensure a consistent NVIDIA starter set is always available, even if
        // the upstream models endpoint is unavailable or returns a reduced set.
        $fallbackModels = [
            [
                'model_id'     => 'openai/gpt-oss-20b',
                'owned_by'     => 'openai',
                'display_name' => 'openai/gpt-oss-20b',
                'description'  => 'OpenAI GPT-OSS 20B (default)',
            ],
            [
                'model_id'     => 'google/diffusiongemma-26b-a4b-it',
                'owned_by'     => 'google',
                'display_name' => 'google/diffusiongemma-26b-a4b-it',
                'description'  => 'Google DiffusionGemma 26B A4B IT',
            ],
        ];
        $existingModelIds = array_column($models, 'model_id');
        foreach ($fallbackModels as $fallbackModel) {
            if (!in_array($fallbackModel['model_id'], $existingModelIds, true)) {
                $models[] = $fallbackModel;
            }
        }

        usort($models, fn($a, $b) => strcasecmp($a['display_name'], $b['display_name']));
        return ['success' => true, 'models' => $models, 'error' => null];
    }
}

/**
 * OpenAI Compatible Adapter (reuses OpenAI logic)
 */
class AIOpenAICompatibleAdapter extends AIOpenAIAdapter
{
    // Inherits all OpenAI behavior — base URL is user-configured
}

/**
 * Custom Adapter (limited support)
 */
class AICustomAdapter extends AIProviderAdapterBase
{
    public function supportsToolCalling(): bool
    {
        return false;
    }

    protected function getBaseUrl(): string
    {
        return $this->provider['base_url'] ?? '';
    }

    public function testConnection(): array
    {
        if (empty($this->provider['base_url'])) {
            return ['success' => false, 'message' => 'No base URL configured for custom provider', 'details' => null];
        }

        $result = $this->apiGet('');
        if ($result['success'] || $result['status'] === 200) {
            return ['success' => true, 'message' => 'Endpoint reachable', 'details' => null];
        }

        return ['success' => false, 'message' => $result['error'] ?? 'Connection failed', 'details' => null];
    }

    public function listModels(): array
    {
        return ['success' => false, 'models' => [], 'error' => 'Automatic model discovery is not available for custom providers. You can add models manually.'];
    }
}

/**
 * Provider Adapter Factory.
 *
 * Resolves the correct adapter based on provider_type.
 */
class AIProviderAdapterFactory
{
    /**
     * Create an adapter for the given provider.
     *
     * @param PDO $db Database connection
     * @param array $provider Provider record from ai_providers table
     * @return AIProviderAdapterBase|null The adapter, or null if type is unsupported
     */
    public static function create(PDO $db, array $provider): ?AIProviderAdapterBase
    {
        // Decrypt API key
        $apiKey = null;
        if (!empty($provider['api_key_encrypted']) && !empty($provider['api_key_iv'])) {
            $apiKey = aiDecryptCredential($provider['api_key_encrypted'], $provider['api_key_iv']);
        }

        return match($provider['provider_type'] ?? '') {
            'openai'            => new AIOpenAIAdapter($db, $provider, $apiKey),
            'openrouter'        => new AIOpenRouterAdapter($db, $provider, $apiKey),
            'nvidia_nim'        => new AINvidiaNimAdapter($db, $provider, $apiKey),
            'openai_compatible' => new AIOpenAICompatibleAdapter($db, $provider, $apiKey),
            'custom'            => new AICustomAdapter($db, $provider, $apiKey),
            default             => null,
        };
    }
}

