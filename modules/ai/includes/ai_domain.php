<?php
/**
 * AI Domain Structures.
 *
 * Foundational PHP classes and constants for AI domain terminology.
 * These establish consistent domain language for future development.
 */

/**
 * AI Provider Status Constants
 */
class AIProviderStatus
{
    const ACTIVE   = 'active';
    const INACTIVE = 'inactive';
    const ERROR    = 'error';
    const PENDING  = 'pending';

    public static function all(): array
    {
        return [self::ACTIVE, self::INACTIVE, self::ERROR, self::PENDING];
    }
}

/**
 * AI Model Status Constants
 */
class AIModelStatus
{
    const ACTIVE   = 'active';
    const INACTIVE = 'inactive';

    public static function all(): array
    {
        return [self::ACTIVE, self::INACTIVE];
    }
}

/**
 * AI Agent Status Constants
 */
class AIAgentStatus
{
    const ACTIVE   = 'active';
    const INACTIVE = 'inactive';

    public static function all(): array
    {
        return [self::ACTIVE, self::INACTIVE];
    }
}

/**
 * AI Conversation Status Constants
 */
class AIConversationStatus
{
    const ACTIVE   = 'active';
    const CLOSED   = 'closed';
    const ARCHIVED = 'archived';

    public static function all(): array
    {
        return [self::ACTIVE, self::CLOSED, self::ARCHIVED];
    }
}

/**
 * AI Tool Status Constants
 */
class AIToolStatus
{
    const ACTIVE   = 'active';
    const INACTIVE = 'inactive';

    public static function all(): array
    {
        return [self::ACTIVE, self::INACTIVE];
    }
}

/**
 * AI Knowledge Source Types
 */
class AIKnowledgeSourceType
{
    const DOCUMENT = 'document';
    const URL      = 'url';
    const DATABASE = 'database';
    const API      = 'api';

    public static function all(): array
    {
        return [self::DOCUMENT, self::URL, self::DATABASE, self::API];
    }
}

/**
 * AI Audit Log Action Types
 */
class AIAuditAction
{
    const PROVIDER_CREATED   = 'provider_created';
    const PROVIDER_UPDATED   = 'provider_updated';
    const PROVIDER_DELETED   = 'provider_deleted';
    const PROVIDER_TESTED    = 'provider_tested';

    const MODEL_CREATED      = 'model_created';
    const MODEL_UPDATED      = 'model_updated';
    const MODEL_DELETED      = 'model_deleted';

    const AGENT_CREATED      = 'agent_created';
    const AGENT_UPDATED      = 'agent_updated';
    const AGENT_DELETED      = 'agent_deleted';

    const KNOWLEDGE_ADDED    = 'knowledge_added';
    const KNOWLEDGE_REMOVED  = 'knowledge_removed';

    const TOOL_ENABLED       = 'tool_enabled';
    const TOOL_DISABLED      = 'tool_disabled';

    const CONVERSATION_STARTED = 'conversation_started';
    const CONVERSATION_ENDED   = 'conversation_ended';

    const EXECUTION_COMPLETED = 'execution_completed';
    const EXECUTION_FAILED    = 'execution_failed';

    public static function all(): array
    {
        return [
            self::PROVIDER_CREATED, self::PROVIDER_UPDATED, self::PROVIDER_DELETED, self::PROVIDER_TESTED,
            self::MODEL_CREATED, self::MODEL_UPDATED, self::MODEL_DELETED,
            self::AGENT_CREATED, self::AGENT_UPDATED, self::AGENT_DELETED,
            self::KNOWLEDGE_ADDED, self::KNOWLEDGE_REMOVED,
            self::TOOL_ENABLED, self::TOOL_DISABLED,
            self::CONVERSATION_STARTED, self::CONVERSATION_ENDED,
            self::EXECUTION_COMPLETED, self::EXECUTION_FAILED,
        ];
    }
}
