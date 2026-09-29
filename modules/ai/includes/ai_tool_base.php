<?php
/**
 * AI Tool Framework.
 *
 * Provides the tool registry, base contract, and executor for AI tools.
 * All tools are strictly read-only in AI F8.
 */

// ── Tool Risk Levels ──
define('AI_TOOL_RISK_READ_ONLY', 'read_only');
define('AI_TOOL_RISK_WRITE', 'write');
define('AI_TOOL_RISK_ACTION', 'action');
define('AI_TOOL_RISK_EXTERNAL', 'external');
define('AI_TOOL_RISK_SYSTEM', 'system');

/**
 * Tool Registry — central catalog of all registered AI tools.
 */
class AIToolRegistry
{
    private static array $tools = [];
    private static bool $initialized = false;

    /**
     * Register a tool definition.
     */
    public static function register(array $definition): void
    {
        $required = ['id', 'name', 'description', 'module', 'risk_level', 'required_permissions'];
        foreach ($required as $field) {
            if (empty($definition[$field])) {
                throw new \InvalidArgumentException("Tool definition missing required field: {$field}");
            }
        }

        self::$tools[$definition['id']] = [
            'id'                  => $definition['id'],
            'name'                => $definition['name'],
            'description'         => $definition['description'],
            'module'              => $definition['module'],
            'risk_level'          => $definition['risk_level'],
            'action_type'         => $definition['action_type'] ?? 'function',
            'required_permissions'=> $definition['required_permissions'],
            'input_schema'        => $definition['input_schema'] ?? [],
            'output_fields'       => $definition['output_fields'] ?? [],
            'enabled'             => $definition['enabled'] ?? true,
            'handler_class'       => $definition['handler_class'] ?? null,
        ];
    }

    /**
     * Get all registered tools.
     */
    public static function getAll(): array
    {
        self::init();
        return self::$tools;
    }

    /**
     * Get a tool by ID.
     */
    public static function get(string $id): ?array
    {
        self::init();
        if (isset(self::$tools[$id])) {
            return self::$tools[$id];
        }

        foreach (self::$tools as $tool) {
            if (($tool['name'] ?? null) === $id) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * Get tools accessible to a user (permission-filtered, read-only only).
     */
    public static function getAccessible(array $userPermissions, bool $readOnlyOnly = true): array
    {
        self::init();
        $accessible = [];

        foreach (self::$tools as $tool) {
            if (!$tool['enabled']) continue;

            // Only allow read-only tools in F8
            if ($readOnlyOnly && $tool['risk_level'] !== AI_TOOL_RISK_READ_ONLY) continue;

            // Check permissions
            if (!empty($tool['required_permissions'])) {
                $hasAccess = false;
                foreach ($tool['required_permissions'] as $perm) {
                    if (in_array($perm, $userPermissions)) {
                        $hasAccess = true;
                        break;
                    }
                }
                if (!$hasAccess) continue;
            }

            $accessible[] = $tool;
        }

        return $accessible;
    }

    /**
     * Format tools for AI provider consumption (OpenAI function-calling format).
     */
    public static function formatForAI(array $tools): array
    {
        $formatted = [];
        foreach ($tools as $tool) {
            $formatted[] = [
                'type' => 'function',
                'function' => [
                    'name'        => $tool['name'],
                    'description' => $tool['description'],
                    'parameters'  => $tool['input_schema'] ?? [
                        'type'       => 'object',
                        'properties' => [],
                        'required'   => [],
                    ],
                ],
            ];
        }
        return $formatted;
    }

    /**
     * Initialize default tools. Called lazily.
     */
    private static function init(): void
    {
        if (self::$initialized) return;
        self::$initialized = true;

        // Tools are registered in ai_tools.php via bootstrap
    }
}

/**
 * Tool Executor — validates and runs registered tools.
 */
class AIToolExecutor
{
    const MAX_TOOL_CALLS_PER_REQUEST = 5;
    const MAX_EXECUTION_TIME = 10; // seconds
    const MAX_RECORDS_RETURNED = 20;

    private PDO $db;
    private int $userId;
    private array $userPermissions;

    public function __construct(PDO $db, int $userId, array $userPermissions)
    {
        $this->db = $db;
        $this->userId = $userId;
        $this->userPermissions = $userPermissions;
    }

    /**
     * Execute a tool call from the AI.
     *
     * @param string $toolName The tool name requested by AI
     * @param array $arguments The arguments provided by AI
     * @param int $conversationId Current conversation ID for logging
     * @return array{success: bool, result: string|null, error: string|null}
     */
    public function execute(string $toolName, array $arguments, int $conversationId = 0): array
    {
        $startTime = microtime(true);

        // 1. Validate tool exists in registry
        $toolDef = AIToolRegistry::get($toolName);
        if (!$toolDef) {
            $this->logToolExecution($conversationId, $toolName, 'denied', 'Tool not found', 0);
            return ['success' => false, 'result' => null, 'error' => 'Tool not found: ' . $toolName];
        }

        // 2. Validate tool is enabled
        if (!$toolDef['enabled']) {
            $this->logToolExecution($conversationId, $toolName, 'denied', 'Tool disabled', 0);
            return ['success' => false, 'result' => null, 'error' => 'Tool is not available.'];
        }

        // 3. Validate risk level — ONLY read_only allowed
        if ($toolDef['risk_level'] !== AI_TOOL_RISK_READ_ONLY) {
            $this->logToolExecution($conversationId, $toolName, 'denied', 'Non-read-only tool blocked', 0);
            return ['success' => false, 'result' => null, 'error' => 'This tool is not available.'];
        }

        // 4. Validate user permissions
        if (!empty($toolDef['required_permissions'])) {
            $hasAccess = false;
            foreach ($toolDef['required_permissions'] as $perm) {
                if (in_array($perm, $this->userPermissions)) {
                    $hasAccess = true;
                    break;
                }
            }
            if (!$hasAccess) {
                $this->logToolExecution($conversationId, $toolName, 'denied', 'Permission denied', 0);
                return ['success' => false, 'result' => null, 'error' => 'You do not have permission to use this tool.'];
            }
        }

        // 5. Validate input
        $validatedInput = $this->validateInput($toolDef, $arguments);
        if (!$validatedInput['valid']) {
            $this->logToolExecution($conversationId, $toolName, 'failed', 'Invalid input: ' . $validatedInput['error'], 0);
            return ['success' => false, 'result' => null, 'error' => 'Invalid parameters: ' . $validatedInput['error']];
        }

        // 6. Execute the tool handler
        $handlerClass = $toolDef['handler_class'];
        if (!$handlerClass || !class_exists($handlerClass)) {
            $this->logToolExecution($conversationId, $toolName, 'failed', 'Handler class not found', 0);
            return ['success' => false, 'result' => null, 'error' => 'Tool implementation not available.'];
        }

        try {
            $handler = new $handlerClass($this->db, $this->userId, $this->userPermissions);
            $result = $handler->execute($validatedInput['params']);

            $duration = round((microtime(true) - $startTime) * 1000);

            if ($result['success']) {
                $this->logToolExecution($conversationId, $toolName, 'executed', null, $duration);
                return ['success' => true, 'result' => $result['data'], 'error' => null];
            } else {
                $this->logToolExecution($conversationId, $toolName, 'failed', $result['error'] ?? 'Unknown error', $duration);
                return ['success' => false, 'result' => null, 'error' => $result['error'] ?? 'Tool execution failed.'];
            }
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->logToolExecution($conversationId, $toolName, 'failed', 'Exception: ' . $e->getMessage(), $duration);
            return ['success' => false, 'result' => null, 'error' => 'An error occurred while executing the tool.'];
        }
    }

    /**
     * Validate tool input against the schema.
     */
    private function validateInput(array $toolDef, array $arguments): array
    {
        $schema = $toolDef['input_schema'] ?? [];
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];

        // Check required fields
        foreach ($required as $field) {
            if (!array_key_exists($field, $arguments) || $arguments[$field] === '' || $arguments[$field] === null) {
                return ['valid' => false, 'error' => "Missing required field: {$field}"];
            }
        }

        // Sanitize inputs
        $clean = [];
        foreach ($arguments as $key => $value) {
            if (is_string($value)) {
                $clean[$key] = trim(substr($value, 0, 500)); // Limit string length
            } elseif (is_int($value) || is_float($value)) {
                $clean[$key] = $value;
            } elseif (is_bool($value)) {
                $clean[$key] = $value;
            } elseif (is_array($value)) {
                $clean[$key] = array_slice($value, 0, 50); // Limit array size
            } else {
                $clean[$key] = (string) $value;
            }
        }

        return ['valid' => true, 'params' => $clean];
    }

    /**
     * Log tool execution to audit trail.
     */
    private function logToolExecution(int $conversationId, string $toolName, string $status, ?string $error, int $duration): void
    {
        try {
            if (class_exists('AIObservabilityService')) {
                AIObservabilityService::logToolEvent($this->db, [
                    'conversation_id' => $conversationId,
                    'tool_name' => $toolName,
                    'status' => $status,
                    'error' => $error,
                    'duration_ms' => $duration,
                    'output_summary' => $error ?: 'Tool execution completed.',
                ]);
            }

            $this->db->prepare("
                INSERT INTO ai_audit_log (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, 'tool_{$status}', 'tool', 0, ?, NOW())
            ")->execute([
                $this->userId,
                json_encode([
                    'tool'             => $toolName,
                    'conversation_id'  => $conversationId,
                    'status'           => $status,
                    'error'            => $error,
                    'duration_ms'      => $duration,
                ]),
            ]);
        } catch (\Throwable $e) {
            // Silently fail — audit logging should not break tool execution
        }
    }
}

/**
 * Tool Handler Interface.
 *
 * All tool handlers must implement this interface.
 */
interface AIToolHandlerInterface
{
    public function __construct(PDO $db, int $userId, array $userPermissions);
    public function execute(array $params): array;
}

/**
 * Base class for read-only tools with common helpers.
 */
abstract class AIToolHandlerBase implements AIToolHandlerInterface
{
    protected PDO $db;
    protected int $userId;
    protected array $userPermissions;

    public function __construct(PDO $db, int $userId, array $userPermissions)
    {
        $this->db = $db;
        $this->userId = $userId;
        $this->userPermissions = $userPermissions;
    }

    /**
     * Check if user has a specific permission.
     */
    protected function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->userPermissions);
    }

    /**
     * Sanitize a search term for LIKE queries.
     */
    protected function sanitizeSearchTerm(string $term): string
    {
        return '%' . addcslashes(trim($term), '%_\\') . '%';
    }

    /**
     * Normalize a record for output (field allowlisting).
     */
    protected function normalizeRecord(array $record, array $allowedFields): array
    {
        $normalized = [];
        foreach ($allowedFields as $field => $label) {
            if (array_key_exists($field, $record)) {
                $normalized[$label] = $record[$field];
            }
        }
        return $normalized;
    }

    /**
     * Format results as a summary string for the AI.
     */
    protected function formatResults(array $records, string $entityName, int $total): string
    {
        if (empty($records)) {
            return "No {$entityName} found matching your criteria.";
        }

        $lines = ["Found {$total} {$entityName} (showing " . count($records) . "):"];
        $lines[] = '';

        foreach ($records as $i => $record) {
            $parts = [];
            foreach ($record as $key => $value) {
                if ($value !== null && $value !== '') {
                    $parts[] = "{$key}: {$value}";
                }
            }
            $lines[] = ($i + 1) . '. ' . implode(' | ', $parts);
        }

        if ($total > count($records)) {
            $lines[] = '';
            $lines[] = "Showing " . count($records) . " of {$total} results. Refine your search for more specific results.";
        }

        return implode("\n", $lines);
    }
}
