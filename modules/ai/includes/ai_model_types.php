<?php
/**
 * AI Model Type & Capability Registry.
 *
 * Centralized definitions for model types and capabilities.
 */

/**
 * Get all supported model types.
 *
 * @return array<string, array{identifier: string, name: string, description: string}>
 */
function aiGetModelTypes(): array
{
    return [
        'chat'           => ['identifier' => 'chat',           'name' => 'Chat',           'description' => 'Conversational AI model'],
        'reasoning'      => ['identifier' => 'reasoning',      'name' => 'Reasoning',      'description' => 'Advanced reasoning and analysis'],
        'embedding'      => ['identifier' => 'embedding',      'name' => 'Embedding',      'description' => 'Text embedding model'],
        'vision'         => ['identifier' => 'vision',         'name' => 'Vision',         'description' => 'Image understanding and analysis'],
        'multimodal'     => ['identifier' => 'multimodal',     'name' => 'Multimodal',     'description' => 'Supports multiple input types'],
        'agent_capable'  => ['identifier' => 'agent_capable',  'name' => 'Agent Capable',  'description' => 'Supports multi-step tool use for agents'],
    ];
}

/**
 * Get all model capabilities.
 *
 * @return array<string, array{key: string, label: string, description: string}>
 */
function aiGetModelCapabilities(): array
{
    return [
        'chat'                 => ['key' => 'chat',                 'label' => 'Chat',                 'description' => 'Conversational responses'],
        'tool_calling'         => ['key' => 'tool_calling',         'label' => 'Tool Calling',         'description' => 'Function/tool invocation'],
        'vision'               => ['key' => 'vision',               'label' => 'Vision',               'description' => 'Image understanding'],
        'embeddings'           => ['key' => 'embeddings',           'label' => 'Embeddings',           'description' => 'Text embedding generation'],
        'structured_output'    => ['key' => 'structured_output',    'label' => 'Structured Output',    'description' => 'JSON/structured responses'],
    ];
}

/**
 * Get a model type by identifier.
 */
function aiGetModelType(string $identifier): ?array
{
    return aiGetModelTypes()[$identifier] ?? null;
}

/**
 * Get all model type identifiers.
 *
 * @return string[]
 */
function aiGetModelTypeIdentifiers(): array
{
    return array_keys(aiGetModelTypes());
}
