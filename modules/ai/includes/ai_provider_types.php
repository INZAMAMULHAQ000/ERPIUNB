<?php
/**
 * AI Provider Type Registry.
 *
 * Centralized definition of supported AI provider types.
 * Each type defines metadata for UI rendering and validation.
 */

/**
 * Get all registered provider types.
 *
 * @return array<string, array{
 *   identifier: string,
 *   name: string,
 *   description: string,
 *   supports_base_url: bool,
 *   base_url_required: bool,
 *   base_url_placeholder: string,
 *   expects_api_key: bool,
 *   default_base_url: string|null,
 * }>
 */
function aiGetProviderTypes(): array
{
    return [
        'openai' => [
            'identifier'        => 'openai',
            'name'              => 'OpenAI',
            'description'       => 'OpenAI GPT models (GPT-4, GPT-3.5, etc.)',
            'supports_base_url' => true,
            'base_url_required' => false,
            'base_url_placeholder' => 'https://api.openai.com/v1',
            'expects_api_key'   => true,
            'default_base_url'  => 'https://api.openai.com/v1',
        ],
        'openrouter' => [
            'identifier'        => 'openrouter',
            'name'              => 'OpenRouter',
            'description'       => 'OpenRouter unified API for multiple AI providers',
            'supports_base_url' => true,
            'base_url_required' => false,
            'base_url_placeholder' => 'https://openrouter.ai/api/v1',
            'expects_api_key'   => true,
            'default_base_url'  => 'https://openrouter.ai/api/v1',
        ],
        'nvidia_nim' => [
            'identifier'        => 'nvidia_nim',
            'name'              => 'NVIDIA NIM',
            'description'       => 'NVIDIA NIM inference microservices',
            'supports_base_url' => true,
            'base_url_required' => false,
            'base_url_placeholder' => 'https://integrate.api.nvidia.com/v1',
            'expects_api_key'   => true,
            'default_base_url'  => 'https://integrate.api.nvidia.com/v1',
        ],
        'openai_compatible' => [
            'identifier'        => 'openai_compatible',
            'name'              => 'OpenAI Compatible',
            'description'       => 'Any provider with an OpenAI-compatible API endpoint',
            'supports_base_url' => true,
            'base_url_required' => true,
            'base_url_placeholder' => 'https://your-provider.com/v1',
            'expects_api_key'   => true,
            'default_base_url'  => null,
        ],
        'custom' => [
            'identifier'        => 'custom',
            'name'              => 'Custom',
            'description'       => 'Custom provider configuration',
            'supports_base_url' => true,
            'base_url_required' => false,
            'base_url_placeholder' => '',
            'expects_api_key'   => false,
            'default_base_url'  => null,
        ],
    ];
}

/**
 * Get metadata for a specific provider type.
 *
 * @param string $identifier The provider type identifier
 * @return array|null The provider type metadata, or null if not found
 */
function aiGetProviderType(string $identifier): ?array
{
    $types = aiGetProviderTypes();
    return $types[$identifier] ?? null;
}

/**
 * Check if a provider type identifier is valid.
 *
 * @param string $identifier The provider type identifier
 * @return bool
 */
function aiIsValidProviderType(string $identifier): bool
{
    return isset(aiGetProviderTypes()[$identifier]);
}

/**
 * Get all provider type identifiers as a simple array.
 *
 * @return string[]
 */
function aiGetProviderTypeIdentifiers(): array
{
    return array_keys(aiGetProviderTypes());
}
