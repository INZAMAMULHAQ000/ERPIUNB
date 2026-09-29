<?php
/**
 * AI Provider Adapter Abstraction.
 *
 * Generic provider-agnostic architecture for future AI providers.
 * Each provider will implement this interface to integrate with the ERP.
 *
 * IMPORTANT: This is a foundation only. No real provider implementation exists yet.
 */

/**
 * AI Provider Adapter Interface
 *
 * All AI providers must implement this interface to ensure consistent
 * integration with the ERP system.
 */
interface AIProviderAdapter
{
    /**
     * Test the connection to the AI provider.
     *
     * @return AIConnectionResult
     */
    public function testConnection(): AIConnectionResult;

    /**
     * List available models from the provider.
     *
     * @return array<AIModelInfo>
     */
    public function listModels(): array;

    /**
     * Send a chat request to the provider.
     *
     * @param AIChatRequest $request
     * @return AIChatResponse
     */
    public function chat(AIChatRequest $request): AIChatResponse;
}

/**
 * AI Connection Result
 *
 * Returned by testConnection() to indicate connection status.
 */
class AIConnectionResult
{
    public bool $success;
    public string $message;
    public ?string $providerName;
    public ?string $error;
    public ?float $latencyMs;

    public function __construct(
        bool $success,
        string $message,
        ?string $providerName = null,
        ?string $error = null,
        ?float $latencyMs = null
    ) {
        $this->success      = $success;
        $this->message      = $message;
        $this->providerName = $providerName;
        $this->error        = $error;
        $this->latencyMs    = $latencyMs;
    }

    public static function success(string $message, ?string $providerName = null, ?float $latencyMs = null): self
    {
        return new self(true, $message, $providerName, null, $latencyMs);
    }

    public static function failure(string $error, ?string $providerName = null): self
    {
        return new self(false, $error, $providerName, $error);
    }
}

/**
 * AI Model Info
 *
 * Represents a model available from a provider.
 */
class AIModelInfo
{
    public string $id;
    public string $name;
    public ?string $description;
    public ?string $providerId;
    public int $maxTokens;
    public float $costPer1kInputTokens;
    public float $costPer1kOutputTokens;

    public function __construct(
        string $id,
        string $name,
        ?string $description = null,
        ?string $providerId = null,
        int $maxTokens = 4096,
        float $costPer1kInputTokens = 0.0,
        float $costPer1kOutputTokens = 0.0
    ) {
        $this->id                     = $id;
        $this->name                   = $name;
        $this->description            = $description;
        $this->providerId             = $providerId;
        $this->maxTokens              = $maxTokens;
        $this->costPer1kInputTokens   = $costPer1kInputTokens;
        $this->costPer1kOutputTokens  = $costPer1kOutputTokens;
    }
}

/**
 * AI Chat Request
 *
 * Represents a request to send to an AI provider.
 */
class AIChatRequest
{
    public string $model;
    public array $messages;
    public ?float $temperature;
    public ?int $maxTokens;
    public ?string $systemPrompt;

    public function __construct(
        string $model,
        array $messages = [],
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?string $systemPrompt = null
    ) {
        $this->model        = $model;
        $this->messages     = $messages;
        $this->temperature  = $temperature;
        $this->maxTokens    = $maxTokens;
        $this->systemPrompt = $systemPrompt;
    }
}

/**
 * AI Chat Response
 *
 * Represents a response from an AI provider.
 */
class AIChatResponse
{
    public bool $success;
    public ?string $content;
    public ?string $model;
    public ?int $promptTokens;
    public ?int $completionTokens;
    public ?int $totalTokens;
    public ?float $cost;
    public ?string $error;
    public ?string $finishReason;

    public function __construct(
        bool $success,
        ?string $content = null,
        ?string $model = null,
        ?int $promptTokens = null,
        ?int $completionTokens = null,
        ?int $totalTokens = null,
        ?float $cost = null,
        ?string $error = null,
        ?string $finishReason = null
    ) {
        $this->success          = $success;
        $this->content          = $content;
        $this->model            = $model;
        $this->promptTokens     = $promptTokens;
        $this->completionTokens = $completionTokens;
        $this->totalTokens      = $totalTokens;
        $this->cost             = $cost;
        $this->error            = $error;
        $this->finishReason     = $finishReason;
    }

    public static function success(string $content, string $model, int $promptTokens = 0, int $completionTokens = 0): self
    {
        return new self(
            true,
            $content,
            $model,
            $promptTokens,
            $completionTokens,
            $promptTokens + $completionTokens,
            null,
            null,
            'stop'
        );
    }

    public static function failure(string $error): self
    {
        return new self(false, null, null, null, null, null, null, $error);
    }
}
