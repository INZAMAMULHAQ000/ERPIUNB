<?php
/**
 * AI F9 — Action Framework.
 *
 * Provides the action registry, base contract, executor, and confirmation system.
 * All actions require explicit human confirmation before execution.
 */

// ── Action Risk Levels ──
define('AI_ACTION_RISK_LOW', 'low');
define('AI_ACTION_RISK_MEDIUM', 'medium');
define('AI_ACTION_RISK_HIGH', 'high');
define('AI_ACTION_RISK_CRITICAL', 'critical');

// ── Action Statuses ──
define('AI_ACTION_STATUS_PENDING', 'pending');
define('AI_ACTION_STATUS_CONFIRMED', 'confirmed');
define('AI_ACTION_STATUS_EXECUTING', 'executing');
define('AI_ACTION_STATUS_COMPLETED', 'completed');
define('AI_ACTION_STATUS_CANCELLED', 'cancelled');
define('AI_ACTION_STATUS_EXPIRED', 'expired');
define('AI_ACTION_STATUS_FAILED', 'failed');

/**
 * Action Registry — central catalog of all registered AI actions.
 */
class AIActionRegistry
{
    private static array $actions = [];
    private static bool $initialized = false;

    public static function register(array $definition): void
    {
        $required = ['id', 'name', 'description', 'module', 'risk_level', 'required_permissions', 'handler_class'];
        foreach ($required as $field) {
            if (empty($definition[$field])) {
                throw new \InvalidArgumentException("Action definition missing required field: {$field}");
            }
        }

        self::$actions[$definition['id']] = [
            'id'                    => $definition['id'],
            'name'                  => $definition['name'],
            'description'           => $definition['description'],
            'module'                => $definition['module'],
            'category'              => $definition['category'] ?? 'action',
            'risk_level'            => $definition['risk_level'],
            'required_permissions'  => $definition['required_permissions'],
            'input_schema'          => $definition['input_schema'] ?? [],
            'required_fields'       => $definition['required_fields'] ?? [],
            'handler_class'         => $definition['handler_class'],
            'enabled'               => $definition['enabled'] ?? true,
            'requires_confirmation' => $definition['requires_confirmation'] ?? true,
        ];
    }

    public static function getAll(): array
    {
        self::init();
        return self::$actions;
    }

    public static function get(string $id): ?array
    {
        self::init();
        return self::$actions[$id] ?? null;
    }

    public static function getAccessible(array $userPermissions): array
    {
        self::init();
        $accessible = [];

        foreach (self::$actions as $action) {
            if (!$action['enabled']) continue;

            if (!empty($action['required_permissions'])) {
                $hasAccess = false;
                foreach ($action['required_permissions'] as $perm) {
                    if (in_array($perm, $userPermissions)) {
                        $hasAccess = true;
                        break;
                    }
                }
                if (!$hasAccess) continue;
            }

            $accessible[] = $action;
        }

        return $accessible;
    }

    /**
     * Format actions for AI provider consumption.
     */
    public static function formatForAI(array $actions): array
    {
        $formatted = [];
        foreach ($actions as $action) {
            $formatted[] = [
                'type' => 'function',
                'function' => [
                    'name'        => $action['id'],
                    'description' => $action['description'] . ' [ACTION: requires user confirmation before execution]',
                    'parameters'  => $action['input_schema'] ?? [
                        'type'       => 'object',
                        'properties' => [],
                        'required'   => [],
                    ],
                ],
            ];
        }
        return $formatted;
    }

    private static function init(): void
    {
        if (self::$initialized) return;
        self::$initialized = true;
    }
}

/**
 * Action Handler Interface.
 */
interface AIActionHandlerInterface
{
    public function __construct(PDO $db, int $userId, array $userPermissions);

    /**
     * Validate input parameters.
     * @return array{valid: bool, error: string|null, cleaned: array}
     */
    public function validateInput(array $params): array;

    /**
     * Build a human-readable preview of what the action will do.
     * @return array{title: string, fields: array, warnings: array}
     */
    public function buildPreview(array $params): array;

    /**
     * Execute the action (only called after confirmation).
     * @return array{success: bool, data: string|null, error: string|null, record_id: int|null}
     */
    public function execute(array $params): array;
}

/**
 * Base class for action handlers.
 */
abstract class AIActionHandlerBase implements AIActionHandlerInterface
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

    protected function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->userPermissions);
    }

    protected function sanitizeInput(string $value, int $maxLen = 200): string
    {
        return trim(substr($value, 0, $maxLen));
    }
}

/**
 * Action Executor — manages the full action lifecycle.
 */
class AIActionExecutor
{
    const MAX_PENDING_ACTIONS_PER_USER = 10;
    const ACTION_EXPIRY_MINUTES = 30;

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
     * Propose an action — validates, builds preview, creates pending action.
     *
     * @return array{success: bool, pending_action_id: int|null, preview: array|null, error: string|null, requires_confirmation: bool}
     */
    public function propose(string $actionId, array $params, int $conversationId = 0): array
    {
        $this->expireStalePendingActions();

        // 1. Validate action exists
        $actionDef = AIActionRegistry::get($actionId);
        if (!$actionDef || !$actionDef['enabled']) {
            return ['success' => false, 'error' => 'Action not available.', 'pending_action_id' => null, 'preview' => null, 'requires_confirmation' => false];
        }

        // 2. Validate permissions
        if (!empty($actionDef['required_permissions'])) {
            $hasAccess = false;
            foreach ($actionDef['required_permissions'] as $perm) {
                if (in_array($perm, $this->userPermissions)) {
                    $hasAccess = true;
                    break;
                }
            }
            if (!$hasAccess) {
                return ['success' => false, 'error' => 'You do not have permission to perform this action.', 'pending_action_id' => null, 'preview' => null, 'requires_confirmation' => false];
            }
        }

        // 3. Check pending action limit
        $pendingCount = $this->countPendingActions();
        if ($pendingCount >= self::MAX_PENDING_ACTIONS_PER_USER) {
            return ['success' => false, 'error' => 'Too many pending actions. Please confirm or cancel existing ones first.', 'pending_action_id' => null, 'preview' => null, 'requires_confirmation' => false];
        }

        // 4. Validate input via handler
        $handlerClass = $actionDef['handler_class'];
        if (!class_exists($handlerClass)) {
            return ['success' => false, 'error' => 'Action implementation not available.', 'pending_action_id' => null, 'preview' => null, 'requires_confirmation' => false];
        }

        $handler = new $handlerClass($this->db, $this->userId, $this->userPermissions);
        $validation = $handler->validateInput($params);

        if (!$validation['valid']) {
            return ['success' => false, 'error' => 'Invalid input: ' . $validation['error'], 'pending_action_id' => null, 'preview' => null, 'requires_confirmation' => false];
        }

        $cleanedParams = $validation['cleaned'];

        // 5. Build preview
        $preview = $handler->buildPreview($cleanedParams);

        // 6. Generate confirmation token
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresStmt = $this->db->query(
            'SELECT DATE_ADD(NOW(), INTERVAL ' . (int) self::ACTION_EXPIRY_MINUTES . ' MINUTE) AS expires_at'
        );
        $expiresAt = (string) ($expiresStmt->fetchColumn() ?: date('Y-m-d H:i:s'));

        // 7. Create pending action
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_pending_actions
                    (user_id, conversation_id, action_id, action_input_json, action_preview_json,
                     confirmation_token, status, confirmation_token_hash, expires_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
            ");
            $stmt->execute([
                $this->userId,
                $conversationId ?: null,
                $actionId,
                json_encode($cleanedParams),
                json_encode($preview),
                $token,
                $tokenHash,
                $expiresAt,
            ]);

            $pendingId = (int) $this->db->lastInsertId();

            $this->logAction('proposed', $pendingId, $actionId);

            return [
                'success'               => true,
                'pending_action_id'     => $pendingId,
                'preview'               => $preview,
                'error'                 => null,
                'requires_confirmation' => true,
                'confirmation_token'    => $token, // Only returned once, to the user
                'action_name'           => $actionDef['name'],
                'expires_at'            => $expiresAt,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to create pending action.', 'pending_action_id' => null, 'preview' => null, 'requires_confirmation' => false];
        }
    }

    /**
     * Confirm and execute a pending action.
     *
     * @return array{success: bool, data: string|null, error: string|null, record_id: int|null}
     */
    public function confirm(int $pendingActionId, string $token): array
    {
        $this->expireStalePendingActions();

        // 1. Load pending action
        $stmt = $this->db->prepare("
            SELECT * FROM ai_pending_actions
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$pendingActionId, $this->userId]);
        $pending = $stmt->fetch();

        if (!$pending) {
            return ['success' => false, 'error' => 'Pending action not found or already processed.', 'data' => null, 'record_id' => null];
        }

        // 2. Check expiration before token validation so stale rows get a clear error.
        if ($pending['status'] === AI_ACTION_STATUS_EXPIRED) {
            $this->updateStatus($pendingActionId, AI_ACTION_STATUS_EXPIRED);
            return ['success' => false, 'error' => 'This action has expired. Please request a new one.', 'data' => null, 'record_id' => null];
        }

        if ($pending['status'] !== AI_ACTION_STATUS_PENDING) {
            return ['success' => false, 'error' => 'Action was already processed.', 'data' => null, 'record_id' => null];
        }

        // 3. Verify token
        $tokenHash = hash('sha256', $token);
        if (!hash_equals($pending['confirmation_token_hash'], $tokenHash)) {
            return ['success' => false, 'error' => 'Invalid confirmation token.', 'data' => null, 'record_id' => null];
        }

        // 4. Mark as confirmed (idempotency — only first confirmation wins)
        $stmt = $this->db->prepare("
            UPDATE ai_pending_actions SET status = 'confirmed', updated_at = NOW()
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$pendingActionId]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'error' => 'Action was already processed.', 'data' => null, 'record_id' => null];
        }

        $this->logAction('confirmed', $pendingActionId, $pending['action_id']);

        // 5. Re-authorize before execution
        $actionDef = AIActionRegistry::get($pending['action_id']);
        if (!$actionDef || !$actionDef['enabled']) {
            $this->updateStatus($pendingActionId, AI_ACTION_STATUS_FAILED, 'Action no longer available.');
            return ['success' => false, 'error' => 'Action is no longer available.', 'data' => null, 'record_id' => null];
        }

        if (!empty($actionDef['required_permissions'])) {
            // Refresh permissions
            $permStmt = $this->db->prepare("
                SELECT DISTINCT CONCAT(p.module, '.', p.name) as perm
                FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                JOIN user_roles ur ON rp.role_id = ur.role_id
                WHERE ur.user_id = ?
            ");
            $permStmt->execute([$this->userId]);
            $currentPerms = array_column($permStmt->fetchAll(), 'perm');

            foreach ($actionDef['required_permissions'] as $perm) {
                if (!in_array($perm, $currentPerms)) {
                    $this->updateStatus($pendingActionId, AI_ACTION_STATUS_FAILED, 'Permission revoked.');
                    return ['success' => false, 'error' => 'Permission denied.', 'data' => null, 'record_id' => null];
                }
            }
        }

        // 6. Execute
        $this->updateStatus($pendingActionId, AI_ACTION_STATUS_EXECUTING);

        $handlerClass = $actionDef['handler_class'];
        $handler = new $handlerClass($this->db, $this->userId, $this->userPermissions);
        $params = json_decode($pending['action_input_json'], true);

        try {
            $result = $handler->execute($params);

            if ($result['success']) {
                $this->updateStatus($pendingActionId, AI_ACTION_STATUS_COMPLETED, json_encode($result));
                $this->syncDocumentActionRequest($pendingActionId, 'executed');
                $this->logAction('completed', $pendingActionId, $pending['action_id']);
                return [
                    'success'   => true,
                    'data'      => $result['data'] ?? 'Action completed successfully.',
                    'error'     => null,
                    'record_id' => $result['record_id'] ?? null,
                ];
            } else {
                $this->updateStatus($pendingActionId, AI_ACTION_STATUS_FAILED, json_encode($result));
                $this->syncDocumentActionRequest($pendingActionId, 'failed');
                $this->logAction('failed', $pendingActionId, $pending['action_id']);
                return [
                    'success'   => false,
                    'data'      => null,
                    'error'     => $result['error'] ?? 'Action failed.',
                    'record_id' => null,
                ];
            }
        } catch (\Throwable $e) {
            $this->updateStatus($pendingActionId, AI_ACTION_STATUS_FAILED, json_encode(['error' => $e->getMessage()]));
            $this->logAction('failed', $pendingActionId, $pending['action_id']);
            return ['success' => false, 'error' => 'An error occurred during execution.', 'data' => null, 'record_id' => null];
        }
    }

    /**
     * Cancel a pending action.
     */
    public function cancel(int $pendingActionId): array
    {
        $stmt = $this->db->prepare("
            UPDATE ai_pending_actions SET status = 'cancelled', updated_at = NOW()
            WHERE id = ? AND user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$pendingActionId, $this->userId]);

        if ($stmt->rowCount() > 0) {
            $this->logAction('cancelled', $pendingActionId, '');
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Action not found or already processed.'];
    }

    /**
     * Get pending actions for the current user.
     */
    public function getPendingActions(): array
    {
        $this->expireStalePendingActions();

        $stmt = $this->db->prepare("
            SELECT id, action_id, action_preview_json, confirmation_token, status, expires_at, created_at
            FROM ai_pending_actions
            WHERE user_id = ? AND status IN ('pending', 'confirmed')
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$this->userId]);
        $actions = $stmt->fetchAll();

        $result = [];
        foreach ($actions as $a) {
            $actionDef = AIActionRegistry::get($a['action_id']);
            $result[] = [
                'id'          => (int) $a['id'],
                'action_id'   => $a['action_id'],
                'name'        => $actionDef['name'] ?? $a['action_id'],
                'preview'     => json_decode($a['action_preview_json'], true),
                'confirmation_token' => $a['status'] === AI_ACTION_STATUS_PENDING ? (string) ($a['confirmation_token'] ?? '') : null,
                'status'      => $a['status'],
                'expires_at'  => $a['expires_at'],
                'created_at'  => $a['created_at'],
            ];
        }

        return $result;
    }

    private function updateStatus(int $id, string $status, ?string $resultJson = null): void
    {
        $shouldSetExecutedAt = in_array($status, [AI_ACTION_STATUS_EXECUTING, AI_ACTION_STATUS_COMPLETED, AI_ACTION_STATUS_FAILED], true);

        if ($resultJson !== null) {
            $sql = "
                UPDATE ai_pending_actions
                SET status = ?, execution_result_json = ?," . ($shouldSetExecutedAt ? " executed_at = COALESCE(executed_at, NOW())," : "") . " updated_at = NOW()
                WHERE id = ?
            ";
            $this->db->prepare("
                " . $sql . "
            ")->execute([$status, $resultJson, $id]);
        } else {
            $sql = "
                UPDATE ai_pending_actions
                SET status = ?" . ($shouldSetExecutedAt ? ", executed_at = COALESCE(executed_at, NOW())" : "") . ", updated_at = NOW()
                WHERE id = ?
            ";
            $this->db->prepare("
                " . $sql . "
            ")->execute([$status, $id]);
        }
    }

    private function countPendingActions(): int
    {
        $this->expireStalePendingActions();

        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM ai_pending_actions
            WHERE user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$this->userId]);
        return (int) $stmt->fetchColumn();
    }

    private function expireStalePendingActions(): int
    {
        $stmt = $this->db->prepare("
            UPDATE ai_pending_actions
            SET status = 'expired', updated_at = NOW()
            WHERE user_id = ? AND status = 'pending' AND expires_at < NOW()
        ");
        $stmt->execute([$this->userId]);
        return $stmt->rowCount();
    }

    private function logAction(string $event, int $pendingId, string $actionId): void
    {
        try {
            if (class_exists('AIObservabilityService')) {
                AIObservabilityService::logActionEvent($this->db, [
                    'pending_action_id' => $pendingId,
                    'action_id' => $actionId,
                    'event' => $event,
                ]);
            }

            $this->db->prepare("
                INSERT INTO ai_audit_log (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, ?, 'pending_action', ?, ?, NOW())
            ")->execute([
                $this->userId,
                "action_{$event}",
                $pendingId,
                json_encode(['action_id' => $actionId, 'event' => $event]),
            ]);
        } catch (\Throwable $e) {
            // Silently fail
        }
    }

    private function syncDocumentActionRequest(int $pendingActionId, string $status): void
    {
        try {
            if (!class_exists('AIGovernanceService') || !AIGovernanceService::tableExists($this->db, 'ai_document_action_requests')) {
                return;
            }

            $this->db->prepare("
                UPDATE ai_document_action_requests
                SET status = ?, updated_at = NOW()
                WHERE pending_action_id = ?
            ")->execute([$status, $pendingActionId]);
        } catch (\Throwable $e) {
            // Non-critical sync
        }
    }
}
