<?php
/**
 * AI F24 — Governance Audit Service.
 *
 * Tracks all governance changes for compliance and accountability.
 */
class AIGovernanceAudit
{
    const ACTION_POLICY_CREATE = 'policy_created';
    const ACTION_POLICY_UPDATE = 'policy_updated';
    const ACTION_POLICY_ACTIVATE = 'policy_activated';
    const ACTION_POLICY_DEACTIVATE = 'policy_deactivated';
    const ACTION_POLICY_DELETE = 'policy_deleted';
    const ACTION_POLICY_VERSION = 'policy_versioned';
    const ACTION_PERMISSION_CHANGE = 'permission_changed';
    const ACTION_PROVIDER_ALLOW = 'provider_allowed';
    const ACTION_PROVIDER_BLOCK = 'provider_blocked';
    const ACTION_RISK_RULE_CHANGE = 'risk_rule_changed';
    const ACTION_EMERGENCY_ACTIVATE = 'emergency_activated';
    const ACTION_GUARDRAIL_TRIGGERED = 'guardrail_triggered';
    const ACTION_KILL_SWITCH_TOGGLE = 'kill_switch_toggled';
    const ACTION_CLASSIFICATION_CHANGE = 'classification_changed';
    const ACTION_AUDIT_CONFIG = 'audit_config';

    private static array $batchBuffer = [];
    private const BATCH_SIZE = 50;

    /**
     * Log a governance action.
     */
    public static function log(PDO $db, string $action, array $data = []): bool
    {
        try {
            $stmt = $db->prepare("
                INSERT INTO ai_governance_audit
                    (action, actor_id, actor_type, target_type, target_id,
                     previous_value_json, new_value_json, change_reason,
                     ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $action,
                $data['actor_id'] ?? ($_SESSION['user_id'] ?? 0),
                $data['actor_type'] ?? 'user',
                $data['target_type'] ?? null,
                $data['target_id'] ?? null,
                $data['previous_value'] ?? null,
                $data['new_value'] ?? null,
                $data['change_reason'] ?? null,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ]);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Log policy creation.
     */
    public static function logPolicyCreate(PDO $db, int $actorId, array $policy): bool
    {
        return self::log($db, self::ACTION_POLICY_CREATE, [
            'actor_id' => $actorId,
            'target_type' => 'policy',
            'target_id' => $policy['id'] ?? null,
            'new_value' => json_encode([
                'name' => $policy['name'],
                'type' => $policy['type'],
                'scope' => $policy['scope'],
                'effect' => $policy['effect'],
            ]),
            'change_reason' => $policy['change_reason'] ?? 'Initial creation',
        ]);
    }

    /**
     * Log policy update.
     */
    public static function logPolicyUpdate(PDO $db, int $actorId, int $policyId, array $previous, array $new): bool
    {
        return self::log($db, self::ACTION_POLICY_UPDATE, [
            'actor_id' => $actorId,
            'target_type' => 'policy',
            'target_id' => $policyId,
            'previous_value' => json_encode($previous),
            'new_value' => json_encode($new),
        ]);
    }

    /**
     * Log policy activation/deactivation.
     */
    public static function logPolicyStatus(PDO $db, int $actorId, int $policyId, string $status): bool
    {
        return self::log($db, self::ACTION_POLICY_ACTIVATE, [
            'actor_id' => $actorId,
            'target_type' => 'policy',
            'target_id' => $policyId,
            'new_value' => json_encode(['status' => $status]),
        ]);
    }

    /**
     * Log permission change.
     */
    public static function logPermissionChange(PDO $db, int $actorId, string $permission, string $action): bool
    {
        return self::log($db, self::ACTION_PERMISSION_CHANGE, [
            'actor_id' => $actorId,
            'target_type' => 'permission',
            'new_value' => json_encode(['permission' => $permission, 'action' => $action]),
        ]);
    }

    /**
     * Log provider allow/block.
     */
    public static function logProviderChange(PDO $db, int $actorId, int $providerId, bool $allowed, string $reason = ''): bool
    {
        return self::log($db, $allowed ? self::ACTION_PROVIDER_ALLOW : self::ACTION_PROVIDER_BLOCK, [
            'actor_id' => $actorId,
            'target_type' => 'provider',
            'target_id' => $providerId,
            'new_value' => json_encode(['allowed' => $allowed, 'reason' => $reason]),
        ]);
    }

    /**
     * Log risk rule change.
     */
    public static function logRiskRuleChange(PDO $db, int $actorId, string $ruleKey, array $changes): bool
    {
        return self::log($db, self::ACTION_RISK_RULE_CHANGE, [
            'actor_id' => $actorId,
            'target_type' => 'risk_rule',
            'target_id' => null,
            'new_value' => json_encode(['rule_key' => $ruleKey, 'changes' => $changes]),
        ]);
    }

    /**
     * Log emergency control activation.
     */
    public static function logEmergencyActivation(PDO $db, int $actorId, string $control, string $action): bool
    {
        return self::log($db, self::ACTION_EMERGENCY_ACTIVATE, [
            'actor_id' => $actorId,
            'target_type' => 'emergency_control',
            'new_value' => json_encode(['control' => $control, 'action' => $action]),
        ]);
    }

    /**
     * Log guardrail trigger.
     */
    public static function logGuardrailTrigger(PDO $db, int $actorId, string $guardrail, array $details = []): bool
    {
        return self::log($db, self::ACTION_GUARDRAIL_TRIGGERED, [
            'actor_id' => $actorId,
            'target_type' => 'guardrail',
            'new_value' => json_encode(['guardrail' => $guardrail] + $details),
        ]);
    }

    /**
     * Log kill switch toggle.
     */
    public static function logKillSwitchToggle(PDO $db, int $actorId, string $switchKey, bool $enabled): bool
    {
        return self::log($db, self::ACTION_KILL_SWITCH_TOGGLE, [
            'actor_id' => $actorId,
            'target_type' => 'kill_switch',
            'target_id' => null,
            'new_value' => json_encode(['switch_key' => $switchKey, 'enabled' => $enabled]),
        ]);
    }

    /**
     * Log data classification change.
     */
    public static function logClassificationChange(PDO $db, int $actorId, string $classification, array $changes): bool
    {
        return self::log($db, self::ACTION_CLASSIFICATION_CHANGE, [
            'actor_id' => $actorId,
            'target_type' => 'data_classification',
            'new_value' => json_encode(['classification' => $classification, 'changes' => $changes]),
        ]);
    }

    /**
     * Get audit log entries with pagination and filtering.
     */
    public static function getAuditLog(PDO $db, array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }
        if (!empty($filters['actor_id'])) {
            $where[] = 'actor_id = ?';
            $params[] = $filters['actor_id'];
        }
        if (!empty($filters['target_type'])) {
            $where[] = 'target_type = ?';
            $params[] = $filters['target_type'];
        }
        if (!empty($filters['target_id'])) {
            $where[] = 'target_id = ?';
            $params[] = $filters['target_id'];
        }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = '(action LIKE ? OR change_reason LIKE ?)';
            $params[] = $search;
            $params[] = $search;
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $offset = ($page - 1) * $perPage;

        try {
            // Get total count
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM ai_governance_audit $whereClause");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            // Get entries
            $stmt = $db->prepare("
                SELECT * FROM ai_governance_audit
                $whereClause
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute(array_merge($params, [$perPage, $offset]));
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'entries' => $entries,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ];
        } catch (\Throwable $e) {
            return ['entries' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 0];
        }
    }

    /**
     * Get governance statistics.
     */
    public static function getStatistics(PDO $db, int $companyId = null): array
    {
        try {
            $where = '';
            $params = [];

            if ($companyId) {
                $where = 'WHERE target_id = ?';
                $params[] = $companyId;
            }

            // Total actions by type
            $stmt = $db->prepare("
                SELECT action, COUNT(*) as count
                FROM ai_governance_audit
                $where
                GROUP BY action
                ORDER BY count DESC
                LIMIT 20
            ");
            $stmt->execute($params);
            $byAction = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Actions by actor
            $stmt = $db->prepare("
                SELECT actor_id, COUNT(*) as count
                FROM ai_governance_audit
                $where
                GROUP BY actor_id
                ORDER BY count DESC
                LIMIT 10
            ");
            $stmt->execute($params);
            $byActor = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Recent activity (last 30 days)
            $stmt = $db->prepare("
                SELECT DATE(created_at) as date, COUNT(*) as count
                FROM ai_governance_audit
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute();
            $dailyActivity = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            return [
                'total_actions' => $this->getTotalCount($db, $where, $params),
                'by_action' => $byAction,
                'by_actor' => $byActor,
                'daily_activity' => $dailyActivity,
            ];
        } catch (\Throwable $e) {
            return [
                'total_actions' => 0,
                'by_action' => [],
                'by_actor' => [],
                'daily_activity' => [],
            ];
        }
    }

    /**
     * Get total count.
     */
    private static function getTotalCount(PDO $db, string $where, array $params): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM ai_governance_audit $where";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Queue an audit entry for batch insertion.
     */
    public static function queue(PDO $db, string $action, array $data = []): void
    {
        self::$batchBuffer[] = [
            'action' => $action,
            'data' => $data,
            'timestamp' => time(),
        ];

        if (count(self::$batchBuffer) >= self::BATCH_SIZE) {
            self::flush($db);
        }
    }

    /**
     * Flush queued audit entries to database.
     */
    public static function flush(PDO $db): void
    {
        if (empty(self::$batchBuffer)) {
            return;
        }

        $buffer = self::$batchBuffer;
        self::$batchBuffer = [];

        try {
            $stmt = $db->prepare("
                INSERT INTO ai_governance_audit
                    (action, actor_id, actor_type, target_type, target_id,
                     new_value_json, change_reason, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $db->beginTransaction();
            foreach ($buffer as $entry) {
                $data = $entry['data'];
                $stmt->execute([
                    $entry['action'],
                    $data['actor_id'] ?? ($_SESSION['user_id'] ?? 0),
                    $data['actor_type'] ?? 'user',
                    $data['target_type'] ?? null,
                    $data['target_id'] ?? null,
                    $data['new_value'] ?? null,
                    $data['change_reason'] ?? null,
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                ]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            // Log failed entries individually
            foreach ($buffer as $entry) {
                self::log($db, $entry['action'], $entry['data']);
            }
        }
    }
}