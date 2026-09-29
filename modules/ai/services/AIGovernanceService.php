<?php
/**
 * AI F16 - Governance Service.
 *
 * Centralizes server-side AI safety, limits, masking, usage, errors,
 * retention, scheduler heartbeat, and configuration audit helpers.
 */
class AIGovernanceService
{
    private const DEFAULT_COMPANY_ID = 1;

    private const ERROR_CATEGORIES = [
        'provider_error',
        'timeout',
        'permission_denied',
        'validation_error',
        'limit_reached',
        'configuration_error',
        'tool_error',
        'scheduler_error',
        'unknown',
    ];

    private const FEATURE_SWITCHES = [
        'chat' => 'ai_chat',
        'ai_chat' => 'ai_chat',
        'command_center' => 'command_center',
        'tool_call' => 'tool_calling',
        'tool_calling' => 'tool_calling',
        'action' => 'write_actions',
        'write_action' => 'write_actions',
        'write_actions' => 'write_actions',
        'agent' => 'agents',
        'agents' => 'agents',
        'automation' => 'automations',
        'automations' => 'automations',
        'scheduled_automation' => 'scheduled_automations',
        'scheduled_automations' => 'scheduled_automations',
        'event_automation' => 'event_automations',
        'event_automations' => 'event_automations',
        'knowledge_search' => 'knowledge_retrieval',
        'knowledge_retrieval' => 'knowledge_retrieval',
        'knowledge_embedding' => 'knowledge_retrieval',
        'insight' => 'ai_insights',
        'ai_insights' => 'ai_insights',
    ];

    private const SYSTEM_HARD_LIMITS = [
        'max_tool_calls' => 5,
        'max_agent_steps' => 20,
        'max_automation_runtime' => 300,
        'max_knowledge_context' => 8000,
        'max_upload_size_mb' => 10,
        'max_retrieval_results' => 10,
        'max_nested_execution' => 1,
        'max_concurrent_runs' => 3,
        'write_confirmation_required' => 1,
    ];

    public static function tableExists(PDO $db, string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            // Use information_schema — reliable across all MariaDB/MySQL versions
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
            );
            $stmt->execute([$table]);
            $cache[$table] = ((int) $stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            // Fallback: try a direct query
            try {
                $db->query("SELECT 1 FROM `{$table}` LIMIT 0");
                $cache[$table] = true;
            } catch (\Throwable $e2) {
                $cache[$table] = false;
            }
        }

        return $cache[$table];
    }

    public static function columnExists(PDO $db, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
            ");
            $stmt->execute([$table, $column]);
            $cache[$key] = ((int) $stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }

    /**
     * Seed default governance rows without overwriting admin changes.
     */
    public static function ensureDefaults(PDO $db): void
    {
        self::seedPermissions($db);
        self::seedSidebar($db);

        if (class_exists('AIKillSwitchService') && self::tableExists($db, 'ai_kill_switches')) {
            AIKillSwitchService::initialize($db);
        }

        self::seedRateLimits($db);
        self::seedCostControls($db);
        self::seedSafetyPolicies($db);
        self::seedRetentionPolicies($db);
        self::seedMaskingPolicies($db);
    }

    private static function seedPermissions(PDO $db): void
    {
        if (!self::tableExists($db, 'permissions') || !self::tableExists($db, 'role_permissions') || !self::tableExists($db, 'roles')) {
            return;
        }

        $permissions = [
            'management.view',
            'management.admin',
            'command_center.access',
            'tools.use',
            'actions.request',
            'knowledge.search',
            'automation.execute',
            'insights.dismiss',
            'insights.configure',
            'integrations.view',
            'integrations.manage',
            'integrations.connect',
            'integrations.disconnect',
            'integrations.use',
            'integrations.webhooks.manage',
            'integrations.sync',
            'integration.rest.read',
            'integration.rest.write',
            'integration.webhook.send',
            'observability.view',
            'observability.traces',
            'observability.evaluations',
            'observability.feedback.review',
            'observability.experiments',
            'observability.manage',
        ];

        try {
            $stmt = $db->prepare("INSERT IGNORE INTO permissions (module, name, created_at) VALUES ('ai', ?, NOW())");
            foreach ($permissions as $permission) {
                $stmt->execute([$permission]);
            }

            $db->exec("
                INSERT IGNORE INTO role_permissions (role_id, permission_id)
                SELECT r.id, p.id
                FROM roles r
                JOIN permissions p ON p.module = 'ai'
                WHERE r.name IN ('Super Admin', 'Admin')
            ");
        } catch (\Throwable $e) {
            // Permission seeding must not block management views.
        }
    }

    private static function seedSidebar(PDO $db): void
    {
        if (!self::tableExists($db, 'sidebar_menu')) {
            return;
        }

        $updates = [
            'ai-assistant' => ['modules/ai/assistant/index.php', 'ai.assistant.access'],
            'ai-management' => ['modules/ai/management/index.php', 'ai.management.view'],
            'ai-providers' => ['modules/ai/providers/index.php', 'ai.providers.view'],
            'ai-models' => ['modules/ai/models/index.php', 'ai.models.view'],
            'ai-agents' => ['modules/ai/agents/index.php', 'ai.agents.view'],
            'ai-knowledge' => ['modules/ai/knowledge/index.php', 'ai.knowledge.view'],
            'ai-tools' => ['modules/ai/tools/index.php', 'ai.tools.view'],
            'ai-automation' => ['modules/ai/automation/index.php', 'ai.automation.view'],
            'ai-integrations' => ['modules/ai/integrations/index.php', 'ai.integrations.view'],
            'ai-observability' => ['modules/ai/observability/index.php', 'ai.observability.view'],
            'ai-monitoring' => ['modules/ai/monitoring/index.php', 'ai.monitoring.view'],
            'ai-settings' => ['modules/ai/settings/index.php', 'ai.settings.manage'],
        ];

        try {
            $stmt = $db->prepare('UPDATE sidebar_menu SET route = ?, permission = ? WHERE route = ?');
            foreach ($updates as $oldRoute => [$newRoute, $permission]) {
                $stmt->execute([$newRoute, $permission, $oldRoute]);
            }
        } catch (\Throwable $e) {
            // Non-critical.
        }
    }

    private static function seedRateLimits(PDO $db): void
    {
        if (!self::tableExists($db, 'ai_rate_limits')) {
            return;
        }

        $hasScope = self::columnExists($db, 'ai_rate_limits', 'scope');
        try {
            foreach (self::defaultRateLimits() as $limit) {
                if ($hasScope) {
                    $stmt = $db->prepare("
                        INSERT IGNORE INTO ai_rate_limits
                            (limit_key, limit_type, scope, max_requests, feature, enabled, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $limit['limit_key'],
                        $limit['limit_type'],
                        $limit['scope'],
                        $limit['max_requests'],
                        $limit['feature'],
                    ]);
                } else {
                    $stmt = $db->prepare("
                        INSERT IGNORE INTO ai_rate_limits
                            (limit_key, limit_type, max_requests, feature, enabled, created_at, updated_at)
                        VALUES (?, ?, ?, ?, 1, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $limit['limit_key'],
                        $limit['limit_type'],
                        $limit['max_requests'],
                        $limit['feature'],
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Defaults are advisory if the table is not writable.
        }
    }

    private static function seedCostControls(PDO $db): void
    {
        if (!self::tableExists($db, 'ai_cost_controls')) {
            return;
        }

        $config = function_exists('getAIConfig') ? getAIConfig() : [];
        $dailyLimit = (float) ($config['management']['max_cost_per_day'] ?? 10.0);
        if ($dailyLimit <= 0) {
            return;
        }

        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM ai_cost_controls
                WHERE control_type = 'per_company_daily'
                AND target_id IS NULL
                AND enabled = 1
            ");
            $stmt->execute();
            if ((int) $stmt->fetchColumn() === 0) {
                $db->prepare("
                    INSERT INTO ai_cost_controls
                        (control_type, target_id, max_cost, current_cost, period_start, period_end, enabled, created_at, updated_at)
                    VALUES ('per_company_daily', NULL, ?, 0, '1970-01-01', '2999-12-31', 1, NOW(), NOW())
                ")->execute([$dailyLimit]);
            }
        } catch (\Throwable $e) {
            // Non-critical.
        }
    }

    private static function seedSafetyPolicies(PDO $db): void
    {
        if (!self::tableExists($db, 'ai_safety_policies')) {
            return;
        }

        try {
            $stmt = $db->prepare("
                INSERT IGNORE INTO ai_safety_policies
                    (policy_key, policy_name, description, max_value, scope, enabled, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'system', 1, NOW(), NOW())
            ");
            foreach (self::defaultSafetyPolicies() as $policy) {
                $stmt->execute([
                    $policy['policy_key'],
                    $policy['policy_name'],
                    $policy['description'],
                    $policy['max_value'],
                ]);
            }
        } catch (\Throwable $e) {
            // Non-critical.
        }
    }

    private static function seedRetentionPolicies(PDO $db): void
    {
        if (!self::tableExists($db, 'ai_retention_policies')) {
            return;
        }

        $defaults = [
            'conversation_history' => 180,
            'command_history' => 180,
            'agent_working_memory' => 30,
            'usage_metadata' => 90,
            'error_logs' => 30,
            'knowledge_processing_logs' => 90,
            'config_audit' => 365,
        ];

        try {
            $stmt = $db->prepare("
                INSERT IGNORE INTO ai_retention_policies (data_type, retention_days, enabled, created_at, updated_at)
                VALUES (?, ?, 1, NOW(), NOW())
            ");
            foreach ($defaults as $type => $days) {
                $stmt->execute([$type, $days]);
            }
        } catch (\Throwable $e) {
            // Non-critical.
        }
    }

    private static function seedMaskingPolicies(PDO $db): void
    {
        if (!self::tableExists($db, 'ai_masking_policies')) {
            return;
        }

        $defaults = [
            ['api_key', 'full', 'Mask API key fields before AI context use.'],
            ['secret', 'full', 'Mask secret fields before AI context use.'],
            ['password', 'full', 'Mask password fields before AI context use.'],
            ['token', 'partial', 'Partially mask token fields before AI context use.'],
        ];

        try {
            $exists = $db->prepare("SELECT COUNT(*) FROM ai_masking_policies WHERE field_pattern = ? AND module IS NULL");
            $insert = $db->prepare("
                INSERT INTO ai_masking_policies (field_pattern, masking_type, module, description, enabled, created_at, updated_at)
                VALUES (?, ?, NULL, ?, 1, NOW(), NOW())
            ");
            foreach ($defaults as [$field, $type, $description]) {
                $exists->execute([$field]);
                if ((int) $exists->fetchColumn() === 0) {
                    $insert->execute([$field, $type, $description]);
                }
            }
        } catch (\Throwable $e) {
            // Non-critical.
        }
    }

    private static function defaultRateLimits(): array
    {
        return [
            ['limit_key' => 'ai_chat_user_minute', 'limit_type' => 'per_minute', 'scope' => 'user', 'max_requests' => 30, 'feature' => 'ai_chat'],
            ['limit_key' => 'command_center_user_minute', 'limit_type' => 'per_minute', 'scope' => 'user', 'max_requests' => 30, 'feature' => 'command_center'],
            ['limit_key' => 'tool_call_user_minute', 'limit_type' => 'per_minute', 'scope' => 'user', 'max_requests' => 60, 'feature' => 'tool_call'],
            ['limit_key' => 'agent_runs_user_hour', 'limit_type' => 'per_hour', 'scope' => 'user', 'max_requests' => 10, 'feature' => 'agent'],
            ['limit_key' => 'automation_runs_user_hour', 'limit_type' => 'per_hour', 'scope' => 'user', 'max_requests' => 20, 'feature' => 'automation'],
            ['limit_key' => 'embedding_user_minute', 'limit_type' => 'per_minute', 'scope' => 'user', 'max_requests' => 30, 'feature' => 'knowledge_embedding'],
        ];
    }

    private static function defaultSafetyPolicies(): array
    {
        return [
            ['policy_key' => 'max_tool_calls', 'policy_name' => 'Maximum Tool Calls', 'description' => 'Maximum tool calls allowed in one AI request.', 'max_value' => 5],
            ['policy_key' => 'max_agent_steps', 'policy_name' => 'Maximum Agent Steps', 'description' => 'Maximum steps in an agent run.', 'max_value' => 20],
            ['policy_key' => 'max_automation_runtime', 'policy_name' => 'Maximum Automation Runtime', 'description' => 'Maximum automation runtime in seconds.', 'max_value' => 300],
            ['policy_key' => 'max_knowledge_context', 'policy_name' => 'Maximum Knowledge Context', 'description' => 'Maximum knowledge context characters sent to AI.', 'max_value' => 8000],
            ['policy_key' => 'max_upload_size_mb', 'policy_name' => 'Maximum Upload Size', 'description' => 'Maximum AI knowledge upload size in megabytes.', 'max_value' => 10],
            ['policy_key' => 'max_retrieval_results', 'policy_name' => 'Maximum Retrieval Results', 'description' => 'Maximum knowledge retrieval results.', 'max_value' => 10],
            ['policy_key' => 'max_nested_execution', 'policy_name' => 'Maximum Nested Execution', 'description' => 'Maximum nested AI execution depth.', 'max_value' => 1],
            ['policy_key' => 'max_concurrent_runs', 'policy_name' => 'Maximum Concurrent Runs', 'description' => 'Maximum concurrent AI runs.', 'max_value' => 3],
            ['policy_key' => 'write_confirmation_required', 'policy_name' => 'Write Confirmation Required', 'description' => 'Require human confirmation for AI write actions.', 'max_value' => 1],
        ];
    }

    public static function guardRequest(PDO $db, string $feature, int $userId, ?int $companyId = null, array $options = []): array
    {
        $companyId = $companyId ?: (int) ($_SESSION['company_id'] ?? self::DEFAULT_COMPANY_ID);
        $providerId = isset($options['provider_id']) ? (int) $options['provider_id'] : null;
        $modelId = isset($options['model_id']) ? (int) $options['model_id'] : null;

        if (!self::aiModuleEnabled()) {
            return self::deny($db, $feature, 'configuration_error', 'AI is currently disabled.', $userId, $companyId, $providerId, $modelId, $options);
        }

        $switchKey = self::switchForFeature($feature);
        if ($switchKey && class_exists('AIKillSwitchService') && !AIKillSwitchService::isEnabled($db, $switchKey)) {
            return self::deny($db, $feature, 'configuration_error', self::disabledMessage($switchKey), $userId, $companyId, $providerId, $modelId, $options);
        }

        $rate = self::consumeRateLimit($db, $feature, $userId, $companyId);
        if (!$rate['allowed']) {
            return self::deny($db, $feature, 'limit_reached', 'AI request limit reached. Please try again shortly.', $userId, $companyId, $providerId, $modelId, array_merge($options, ['rate_limit' => $rate]));
        }

        $estimatedCost = isset($options['estimated_cost']) ? (float) $options['estimated_cost'] : 0.0;
        $budget = self::checkCostControls($db, $feature, $userId, $companyId, $modelId, $estimatedCost);
        if (!$budget['allowed']) {
            return self::deny($db, $feature, 'limit_reached', 'Budget Limit Reached', $userId, $companyId, $providerId, $modelId, array_merge($options, ['budget' => $budget]));
        }

        return ['allowed' => true, 'message' => null];
    }

    private static function aiModuleEnabled(): bool
    {
        if (!function_exists('getAIConfig')) {
            return true;
        }
        $config = getAIConfig();
        return (bool) ($config['enabled'] ?? true);
    }

    public static function switchForFeature(string $feature): ?string
    {
        return self::FEATURE_SWITCHES[$feature] ?? $feature;
    }

    private static function disabledMessage(string $switchKey): string
    {
        return match ($switchKey) {
            'ai_chat' => 'AI conversation unavailable.',
            'command_center' => 'AI Command Center is currently disabled.',
            'tool_calling' => 'AI tool calling is currently disabled.',
            'write_actions' => 'AI write action requests are currently disabled.',
            'agents' => 'Agent cannot start.',
            'automations', 'scheduled_automations', 'event_automations' => 'AI automation is currently disabled.',
            'knowledge_retrieval' => 'Knowledge retrieval is currently unavailable.',
            'ai_insights' => 'AI insights are currently disabled.',
            default => 'AI capability is currently disabled.',
        };
    }

    private static function deny(
        PDO $db,
        string $feature,
        string $category,
        string $message,
        int $userId,
        int $companyId,
        ?int $providerId,
        ?int $modelId,
        array $options
    ): array {
        self::logError($db, [
            'feature' => $feature,
            'operation_type' => $options['request_type'] ?? 'request',
            'error_category' => $category,
            'error_message' => $message,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'company_id' => $companyId,
            'user_id' => $userId,
            'details' => $options,
        ]);

        if (class_exists('AIUsageService') && self::tableExists($db, 'ai_usage_logs')) {
            AIUsageService::log($db, [
                'provider_id' => $providerId,
                'model_id' => $modelId,
                'feature' => $feature,
                'user_id' => $userId,
                'company_id' => $companyId,
                'request_type' => $options['request_type'] ?? 'request',
                'status' => 'failed',
                'error_category' => $category,
                'metadata' => ['blocked' => true, 'reason' => $message],
            ]);
        }

        return ['allowed' => false, 'message' => $message, 'error_category' => $category];
    }

    public static function consumeRateLimit(PDO $db, string $feature, int $userId, int $companyId): array
    {
        $limits = self::getApplicableRateLimits($db, $feature, $userId, $companyId);
        if (empty($limits) || !self::tableExists($db, 'ai_rate_limit_counters')) {
            return ['allowed' => true];
        }

        try {
            if (random_int(1, 100) === 1) {
                $db->exec("DELETE FROM ai_rate_limit_counters WHERE window_end < DATE_SUB(NOW(), INTERVAL 1 DAY)");
            }
        } catch (\Throwable $e) {
            // Ignore cleanup failures.
        }

        foreach ($limits as $limit) {
            $max = (int) ($limit['max_requests'] ?? 0);
            if ($max <= 0) {
                continue;
            }

            [$windowStart, $windowEnd] = self::windowForLimit($limit['limit_type'] ?? 'per_minute');
            $scope = $limit['scope'] ?? 'user';
            $key = (string) $limit['limit_key'];
            $counterUserId = $scope === 'user' ? $userId : 0;
            $counterCompanyId = in_array($scope, ['user', 'company'], true) ? $companyId : 0;
            $counterFeature = !empty($limit['feature']) ? (string) $limit['feature'] : ($feature ?: '*');

            try {
                $db->prepare("
                    INSERT INTO ai_rate_limit_counters
                        (limit_key, scope, user_id, company_id, feature, window_start, window_end, request_count, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE request_count = request_count + 1, updated_at = NOW()
                ")->execute([$key, $scope, $counterUserId, $counterCompanyId, $counterFeature, $windowStart, $windowEnd]);

                $stmt = $db->prepare("
                    SELECT request_count
                    FROM ai_rate_limit_counters
                    WHERE limit_key = ? AND scope = ? AND user_id = ? AND company_id = ?
                    AND feature = ? AND window_start = ?
                ");
                $stmt->execute([$key, $scope, $counterUserId, $counterCompanyId, $counterFeature, $windowStart]);
                $count = (int) $stmt->fetchColumn();

                if ($count > $max) {
                    return [
                        'allowed' => false,
                        'limit_key' => $key,
                        'limit' => $max,
                        'count' => $count,
                        'window_end' => $windowEnd,
                    ];
                }
            } catch (\Throwable $e) {
                return ['allowed' => true, 'warning' => 'rate_limit_counter_unavailable'];
            }
        }

        return ['allowed' => true];
    }

    private static function getApplicableRateLimits(PDO $db, string $feature, int $userId, int $companyId): array
    {
        if (!self::tableExists($db, 'ai_rate_limits')) {
            return self::fallbackRateLimits($feature);
        }

        $hasScope = self::columnExists($db, 'ai_rate_limits', 'scope');
        $scopeSql = $hasScope ? 'scope' : "'user' AS scope";

        try {
            $stmt = $db->prepare("
                SELECT limit_key, limit_type, {$scopeSql}, max_requests, user_id, company_id, feature
                FROM ai_rate_limits
                WHERE enabled = 1
                AND (feature IS NULL OR feature = ?)
                AND (user_id IS NULL OR user_id = ?)
                AND (company_id IS NULL OR company_id = ?)
                ORDER BY
                    CASE WHEN user_id IS NULL THEN 1 ELSE 0 END,
                    CASE WHEN company_id IS NULL THEN 1 ELSE 0 END
            ");
            $stmt->execute([$feature, $userId, $companyId]);
            $rows = $stmt->fetchAll();
            return $rows ?: self::fallbackRateLimits($feature);
        } catch (\Throwable $e) {
            return self::fallbackRateLimits($feature);
        }
    }

    private static function fallbackRateLimits(string $feature): array
    {
        return array_values(array_filter(self::defaultRateLimits(), function (array $limit) use ($feature): bool {
            return $limit['feature'] === $feature;
        }));
    }

    private static function windowForLimit(string $limitType): array
    {
        $seconds = match ($limitType) {
            'per_hour' => 3600,
            'per_day' => 86400,
            default => 60,
        };
        $start = (int) (floor(time() / $seconds) * $seconds);
        $end = $start + $seconds;
        return [date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $end)];
    }

    public static function checkCostControls(PDO $db, string $feature, int $userId, int $companyId, ?int $modelId = null, float $estimatedCost = 0.0): array
    {
        if (!self::tableExists($db, 'ai_cost_controls') || !self::tableExists($db, 'ai_usage_logs')) {
            return ['allowed' => true, 'controls' => []];
        }

        try {
            $stmt = $db->query("
                SELECT *
                FROM ai_cost_controls
                WHERE enabled = 1
                AND period_start <= CURDATE()
                AND period_end >= CURDATE()
            ");
            $controls = $stmt->fetchAll();
        } catch (\Throwable $e) {
            return ['allowed' => true, 'controls' => []];
        }

        foreach ($controls as $control) {
            if (!self::costControlApplies($control, $feature, $userId, $companyId, $modelId)) {
                continue;
            }

            [$start, $end] = self::costControlWindow($control['control_type']);
            $used = self::sumUsageCost($db, $control, $start, $end, $userId, $companyId, $modelId);
            $max = (float) $control['max_cost'];

            if (($used + $estimatedCost) > $max) {
                return [
                    'allowed' => false,
                    'control_type' => $control['control_type'],
                    'target_id' => $control['target_id'],
                    'used' => $used,
                    'estimated_cost' => $estimatedCost,
                    'limit' => $max,
                ];
            }
        }

        return ['allowed' => true, 'controls' => $controls];
    }

    private static function costControlApplies(array $control, string $feature, int $userId, int $companyId, ?int $modelId): bool
    {
        $target = $control['target_id'] !== null ? (int) $control['target_id'] : null;
        return match ($control['control_type']) {
            'per_user_daily' => $target === null || $target === $userId,
            'per_company_daily', 'monthly_company' => $target === null || $target === $companyId,
            'per_model_daily' => $modelId !== null && ($target === null || $target === $modelId),
            'per_agent_run' => in_array($feature, ['agent', 'agents'], true),
            'per_automation_run' => in_array($feature, ['automation', 'automations', 'scheduled_automation', 'event_automation'], true),
            default => false,
        };
    }

    private static function costControlWindow(string $controlType): array
    {
        if ($controlType === 'monthly_company') {
            $start = date('Y-m-01 00:00:00');
            $end = date('Y-m-01 00:00:00', strtotime('+1 month'));
            return [$start, $end];
        }

        $start = date('Y-m-d 00:00:00');
        $end = date('Y-m-d 00:00:00', strtotime('+1 day'));
        return [$start, $end];
    }

    private static function sumUsageCost(PDO $db, array $control, string $start, string $end, int $userId, int $companyId, ?int $modelId): float
    {
        $where = ['status = ?', 'created_at >= ?', 'created_at < ?'];
        $params = ['success', $start, $end];
        $target = $control['target_id'] !== null ? (int) $control['target_id'] : null;

        switch ($control['control_type']) {
            case 'per_user_daily':
                $where[] = 'user_id = ?';
                $params[] = $target ?: $userId;
                break;
            case 'per_company_daily':
            case 'monthly_company':
                $where[] = 'company_id = ?';
                $params[] = $target ?: $companyId;
                break;
            case 'per_model_daily':
                if (!$modelId && !$target) {
                    return 0.0;
                }
                $where[] = 'model_id = ?';
                $params[] = $target ?: $modelId;
                break;
            case 'per_agent_run':
                $where[] = "feature IN ('agent','agents')";
                break;
            case 'per_automation_run':
                $where[] = "feature IN ('automation','automations','scheduled_automation','event_automation')";
                break;
        }

        try {
            $stmt = $db->prepare('SELECT COALESCE(SUM(estimated_cost), 0) FROM ai_usage_logs WHERE ' . implode(' AND ', $where));
            $stmt->execute($params);
            return (float) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    public static function estimateCostForModelId(PDO $db, ?int $modelId, ?int $inputTokens, ?int $outputTokens): array
    {
        if (!$modelId || !self::tableExists($db, 'ai_models')) {
            return ['available' => false, 'estimated_cost' => 0.0, 'basis' => 'unavailable'];
        }

        try {
            $stmt = $db->prepare('SELECT * FROM ai_models WHERE id = ?');
            $stmt->execute([$modelId]);
            $model = $stmt->fetch();
            if (!$model) {
                return ['available' => false, 'estimated_cost' => 0.0, 'basis' => 'unavailable'];
            }
            return self::estimateCost($model, $inputTokens, $outputTokens);
        } catch (\Throwable $e) {
            return ['available' => false, 'estimated_cost' => 0.0, 'basis' => 'unavailable'];
        }
    }

    public static function estimateCost(array $model, ?int $inputTokens, ?int $outputTokens): array
    {
        $config = json_decode($model['configuration_json'] ?? '{}', true);
        $config = is_array($config) ? $config : [];

        $inputRate = self::pricingValue($config, [
            'input_cost_per_1k',
            'cost_per_1k_input',
            'cost_per_1k_input_tokens',
            'prompt_cost_per_1k',
            'pricing.prompt',
            'pricing.input',
        ]);
        $outputRate = self::pricingValue($config, [
            'output_cost_per_1k',
            'cost_per_1k_output',
            'cost_per_1k_output_tokens',
            'completion_cost_per_1k',
            'pricing.completion',
            'pricing.output',
        ]);

        if ($inputRate === null && $outputRate === null) {
            return ['available' => false, 'estimated_cost' => 0.0, 'basis' => 'unavailable'];
        }

        $inputRate = $inputRate ?? 0.0;
        $outputRate = $outputRate ?? 0.0;
        $inputTokens = max(0, (int) ($inputTokens ?? 0));
        $outputTokens = max(0, (int) ($outputTokens ?? 0));

        $looksPerToken = ($inputRate > 0 && $inputRate < 0.001) || ($outputRate > 0 && $outputRate < 0.001);
        if ($looksPerToken) {
            $cost = ($inputTokens * $inputRate) + ($outputTokens * $outputRate);
            $basis = 'configured_per_token';
        } else {
            $cost = (($inputTokens / 1000) * $inputRate) + (($outputTokens / 1000) * $outputRate);
            $basis = 'configured_per_1k_tokens';
        }

        return [
            'available' => true,
            'estimated_cost' => round($cost, 6),
            'basis' => $basis,
        ];
    }

    private static function pricingValue(array $config, array $paths): ?float
    {
        foreach ($paths as $path) {
            $value = self::arrayPath($config, $path);
            if ($value !== null && is_numeric($value)) {
                return (float) $value;
            }
        }
        return null;
    }

    private static function arrayPath(array $data, string $path)
    {
        $cursor = $data;
        foreach (explode('.', $path) as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
                return null;
            }
            $cursor = $cursor[$part];
        }
        return $cursor;
    }

    public static function logError(PDO $db, array $data): int
    {
        if (!self::tableExists($db, 'ai_error_registry')) {
            return 0;
        }

        $category = $data['error_category'] ?? 'unknown';
        if (!in_array($category, self::ERROR_CATEGORIES, true)) {
            $category = 'unknown';
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO ai_error_registry
                    (feature, operation_type, error_category, error_message, provider_id, model_id,
                     company_id, user_id, retry_count, resolution_status, sanitized_details, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, NOW())
            ");
            $stmt->execute([
                $data['feature'] ?? 'unknown',
                $data['operation_type'] ?? null,
                $category,
                self::sanitizeText((string) ($data['error_message'] ?? 'Unknown AI error'), 1000),
                $data['provider_id'] ?? null,
                $data['model_id'] ?? null,
                $data['company_id'] ?? ($_SESSION['company_id'] ?? self::DEFAULT_COMPANY_ID),
                $data['user_id'] ?? ($_SESSION['user_id'] ?? null),
                (int) ($data['retry_count'] ?? 0),
                json_encode(self::sanitizeValue($data['details'] ?? [])),
            ]);
            return (int) $db->lastInsertId();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function sanitizeText(string $text, int $maxLength = 2000): string
    {
        $patterns = [
            '/Bearer\s+[A-Za-z0-9._\-]+/i' => 'Bearer [masked]',
            '/sk-[A-Za-z0-9_\-]{8,}/i' => 'sk-[masked]',
            '/(api[_-]?key|secret|password|token)\s*[:=]\s*["\']?[^"\'\s,&}]+/i' => '$1=[masked]',
            '/Authorization:\s*[^\r\n]+/i' => 'Authorization: [masked]',
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i' => '[masked-email]',
            '/\b\d{12,19}\b/' => '[masked-number]',
        ];

        $text = preg_replace(array_keys($patterns), array_values($patterns), $text) ?? $text;
        return mb_substr($text, 0, $maxLength);
    }

    public static function sanitizeValue($value)
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                if (self::looksSensitive((string) $key)) {
                    $clean[$key] = '[masked]';
                } else {
                    $clean[$key] = self::sanitizeValue($item);
                }
            }
            return $clean;
        }

        if (is_string($value)) {
            return self::sanitizeText($value, 1000);
        }

        return $value;
    }

    private static function looksSensitive(string $field): bool
    {
        return preg_match('/api[_-]?key|secret|password|token|authorization|credential/i', $field) === 1;
    }

    public static function maskData(PDO $db, $value, ?string $module = null)
    {
        $policies = self::getMaskingPolicies($db, $module);
        return self::maskValue($value, $policies);
    }

    private static function getMaskingPolicies(PDO $db, ?string $module = null): array
    {
        if (!self::tableExists($db, 'ai_masking_policies')) {
            return [];
        }

        try {
            $stmt = $db->prepare("
                SELECT field_pattern, masking_type, module
                FROM ai_masking_policies
                WHERE enabled = 1
                AND (module IS NULL OR module = ?)
                ORDER BY CASE WHEN module IS NULL THEN 1 ELSE 0 END, id ASC
            ");
            $stmt->execute([$module]);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function maskValue($value, array $policies, ?string $fieldName = null)
    {
        $policy = $fieldName !== null ? self::matchingMaskPolicy($fieldName, $policies) : null;
        if ($policy) {
            if ($policy['masking_type'] === 'exclude') {
                return ['__ai_excluded__' => true];
            }
            if (!is_array($value)) {
                return self::applyMask($value, $policy['masking_type']);
            }
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $masked = self::maskValue($item, $policies, is_string($key) ? $key : null);
                if (is_array($masked) && count($masked) === 1 && isset($masked['__ai_excluded__'])) {
                    continue;
                }
                $out[$key] = $masked;
            }
            return $out;
        }

        if (is_string($value)) {
            return self::sanitizeText($value, 1000);
        }

        return $value;
    }

    private static function matchingMaskPolicy(string $fieldName, array $policies): ?array
    {
        $field = strtolower($fieldName);
        foreach ($policies as $policy) {
            $pattern = strtolower((string) $policy['field_pattern']);
            $wildcard = str_replace('%', '*', $pattern);
            if (function_exists('fnmatch') && fnmatch($wildcard, $field)) {
                return $policy;
            }
            if ($pattern !== '' && str_contains($field, $pattern)) {
                return $policy;
            }
        }
        return null;
    }

    private static function applyMask($value, string $type)
    {
        if ($type === 'none') {
            return $value;
        }

        $text = is_scalar($value) ? (string) $value : json_encode($value);
        if ($type === 'partial') {
            if (strlen($text) <= 6) {
                return '[masked]';
            }
            return substr($text, 0, 2) . str_repeat('*', min(8, max(3, strlen($text) - 4))) . substr($text, -2);
        }

        return '[masked]';
    }

    public static function getEffectiveLimit(PDO $db, string $policyKey, ?int $companyId = null, ?string $feature = null, ?int $requestedValue = null): int
    {
        $hard = self::SYSTEM_HARD_LIMITS[$policyKey] ?? PHP_INT_MAX;
        $values = [$hard];

        if (self::tableExists($db, 'ai_safety_policies')) {
            try {
                $stmt = $db->prepare("
                    SELECT max_value, scope, target_id
                    FROM ai_safety_policies
                    WHERE policy_key = ? AND enabled = 1
                ");
                $stmt->execute([$policyKey]);
                foreach ($stmt->fetchAll() as $row) {
                    $scope = $row['scope'] ?? 'system';
                    $target = $row['target_id'] !== null ? (int) $row['target_id'] : null;
                    if ($scope === 'system') {
                        $values[] = (int) $row['max_value'];
                    } elseif ($scope === 'company' && ($target === null || $target === (int) $companyId)) {
                        $values[] = (int) $row['max_value'];
                    } elseif ($scope === 'feature') {
                        $values[] = (int) $row['max_value'];
                    }
                }
            } catch (\Throwable $e) {
                // Fall back to hard limit.
            }
        }

        if ($requestedValue !== null) {
            $values[] = max(0, $requestedValue);
        }

        return max(0, min($values));
    }

    public static function getSafetyPolicies(PDO $db): array
    {
        if (!self::tableExists($db, 'ai_safety_policies')) {
            return self::defaultSafetyPolicies();
        }

        try {
            $stmt = $db->query("SELECT * FROM ai_safety_policies ORDER BY scope, policy_key");
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return self::defaultSafetyPolicies();
        }
    }

    public static function getRateLimitStatuses(PDO $db): array
    {
        if (!self::tableExists($db, 'ai_rate_limits')) {
            return self::defaultRateLimits();
        }

        $hasScope = self::columnExists($db, 'ai_rate_limits', 'scope');
        $scopeSql = $hasScope ? 'scope' : "'user' AS scope";
        try {
            $stmt = $db->query("
                SELECT id, limit_key, limit_type, {$scopeSql}, max_requests, user_id, company_id, feature, enabled, updated_at
                FROM ai_rate_limits
                ORDER BY enabled DESC, limit_key
            ");
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return self::defaultRateLimits();
        }
    }

    public static function getCostControlStatuses(PDO $db, ?int $companyId = null): array
    {
        if (!self::tableExists($db, 'ai_cost_controls')) {
            return [];
        }

        $companyId = $companyId ?: (int) ($_SESSION['company_id'] ?? self::DEFAULT_COMPANY_ID);
        try {
            $rows = $db->query("SELECT * FROM ai_cost_controls ORDER BY enabled DESC, control_type")->fetchAll();
            foreach ($rows as &$row) {
                [$start, $end] = self::costControlWindow($row['control_type']);
                $row['used_cost'] = self::sumUsageCost($db, $row, $start, $end, (int) ($_SESSION['user_id'] ?? 0), $companyId, null);
                $row['percent_used'] = ((float) $row['max_cost'] > 0)
                    ? min(999, round(($row['used_cost'] / (float) $row['max_cost']) * 100, 1))
                    : 0;
            }
            unset($row);
            return $rows;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function auditConfigChange(PDO $db, string $configType, ?string $configId, string $action, $previousValue, $newValue, int $userId): void
    {
        if (!self::tableExists($db, 'ai_config_audit')) {
            return;
        }

        try {
            $db->prepare("
                INSERT INTO ai_config_audit
                    (config_type, config_id, action, previous_value, new_value, user_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $configType,
                $configId,
                $action,
                self::auditValue($previousValue),
                self::auditValue($newValue),
                $userId,
            ]);
        } catch (\Throwable $e) {
            // Auditing must not break the primary operation.
        }
    }

    private static function auditValue($value): string
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode(self::sanitizeValue((array) $value));
        }
        return self::sanitizeText((string) $value, 1200);
    }

    public static function cleanupRetention(PDO $db, int $userId): array
    {
        if (!self::tableExists($db, 'ai_retention_policies')) {
            return ['success' => false, 'error' => 'Retention policies are not configured.', 'deleted' => []];
        }

        $deleted = [];
        $policies = $db->query("SELECT * FROM ai_retention_policies WHERE enabled = 1")->fetchAll();

        foreach ($policies as $policy) {
            $days = max(1, (int) $policy['retention_days']);
            $type = $policy['data_type'];

            try {
                $count = match ($type) {
                    'conversation_history' => self::cleanupConversationHistory($db, $days),
                    'command_history' => self::cleanupCommandHistory($db, $days),
                    'agent_working_memory' => self::cleanupAgentMemory($db, $days),
                    'usage_metadata' => self::deleteOlderThan($db, 'ai_usage_logs', 'created_at', $days),
                    'error_logs' => self::cleanupErrors($db, $days),
                    'knowledge_processing_logs' => self::deleteOlderThan($db, 'ai_knowledge_retrieval_logs', 'created_at', $days),
                    'config_audit' => self::deleteOlderThan($db, 'ai_config_audit', 'created_at', $days),
                    'observability_request_metadata' => self::cleanupObservabilityRequests($db, $days),
                    'observability_trace_metadata' => self::cleanupObservabilitySpans($db, $days),
                    'observability_prompt_content' => self::cleanupObservabilityRequestContent($db, $days, 'prompt_summary'),
                    'observability_response_content' => self::cleanupObservabilityRequestContent($db, $days, 'response_summary'),
                    'observability_tool_summaries' => self::cleanupObservabilitySpanSummaries($db, $days),
                    'observability_evaluation_results' => self::deleteOlderThan($db, 'ai_observability_eval_results', 'created_at', $days),
                    'observability_feedback' => self::deleteOlderThan($db, 'ai_observability_feedback', 'created_at', $days),
                    default => 0,
                };
                $deleted[$type] = $count;
                $db->prepare('UPDATE ai_retention_policies SET last_cleanup_at = NOW(), updated_at = NOW() WHERE id = ?')
                   ->execute([$policy['id']]);
            } catch (\Throwable $e) {
                $deleted[$type] = 'failed';
            }
        }

        self::auditConfigChange($db, 'retention', null, 'cleanup_run', null, $deleted, $userId);
        return ['success' => true, 'deleted' => $deleted];
    }

    private static function cleanupConversationHistory(PDO $db, int $days): int
    {
        if (!self::tableExists($db, 'ai_conversations')) {
            return 0;
        }

        $count = 0;
        if (self::tableExists($db, 'ai_messages')) {
            $stmt = $db->prepare("
                DELETE m FROM ai_messages m
                JOIN ai_conversations c ON c.id = m.conversation_id
                WHERE c.status <> 'active'
                AND c.updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $count += $stmt->rowCount();
        }

        $stmt = $db->prepare("DELETE FROM ai_conversations WHERE status <> 'active' AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
        return $count + $stmt->rowCount();
    }

    private static function cleanupCommandHistory(PDO $db, int $days): int
    {
        $count = 0;
        if (self::tableExists($db, 'ai_command_executions')) {
            $count += self::deleteOlderThan($db, 'ai_command_executions', 'created_at', $days);
        }
        if (self::tableExists($db, 'ai_command_conversations')) {
            $stmt = $db->prepare("
                DELETE FROM ai_command_conversations
                WHERE status <> 'active'
                AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $count += $stmt->rowCount();
        }
        return $count;
    }

    private static function cleanupAgentMemory(PDO $db, int $days): int
    {
        if (!self::tableExists($db, 'ai_agent_runs')) {
            return 0;
        }

        $stmt = $db->prepare("
            UPDATE ai_agent_runs
            SET working_memory_json = NULL, updated_at = NOW()
            WHERE working_memory_json IS NOT NULL
            AND status IN ('completed','failed','cancelled','limit_reached')
            AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }

    private static function cleanupErrors(PDO $db, int $days): int
    {
        if (!self::tableExists($db, 'ai_error_registry')) {
            return 0;
        }

        $stmt = $db->prepare("
            DELETE FROM ai_error_registry
            WHERE resolution_status IN ('resolved','ignored')
            AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }

    private static function cleanupObservabilityRequests(PDO $db, int $days): int
    {
        if (!self::tableExists($db, 'ai_observability_requests')) {
            return 0;
        }

        $stmt = $db->prepare("
            UPDATE ai_observability_requests
            SET user_id = NULL,
                session_reference = NULL,
                metadata_json = NULL,
                updated_at = NOW()
            WHERE started_at < DATE_SUB(NOW(), INTERVAL ? DAY)
              AND (user_id IS NOT NULL OR session_reference IS NOT NULL OR metadata_json IS NOT NULL)
        ");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }

    private static function cleanupObservabilitySpans(PDO $db, int $days): int
    {
        if (!self::tableExists($db, 'ai_observability_spans')) {
            return 0;
        }

        $stmt = $db->prepare("
            UPDATE ai_observability_spans
            SET safe_metadata_json = NULL,
                updated_at = NOW()
            WHERE started_at < DATE_SUB(NOW(), INTERVAL ? DAY)
              AND safe_metadata_json IS NOT NULL
        ");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }

    private static function cleanupObservabilityRequestContent(PDO $db, int $days, string $column): int
    {
        if (!self::tableExists($db, 'ai_observability_requests')) {
            return 0;
        }

        $stmt = $db->prepare("
            UPDATE ai_observability_requests
            SET {$column} = NULL,
                updated_at = NOW()
            WHERE started_at < DATE_SUB(NOW(), INTERVAL ? DAY)
              AND {$column} IS NOT NULL
        ");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }

    private static function cleanupObservabilitySpanSummaries(PDO $db, int $days): int
    {
        if (!self::tableExists($db, 'ai_observability_spans')) {
            return 0;
        }

        $stmt = $db->prepare("
            UPDATE ai_observability_spans
            SET safe_input_summary = NULL,
                safe_output_summary = NULL,
                updated_at = NOW()
            WHERE started_at < DATE_SUB(NOW(), INTERVAL ? DAY)
              AND (safe_input_summary IS NOT NULL OR safe_output_summary IS NOT NULL)
        ");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }

    private static function deleteOlderThan(PDO $db, string $table, string $column, int $days): int
    {
        if (!self::tableExists($db, $table)) {
            return 0;
        }

        $stmt = $db->prepare("DELETE FROM {$table} WHERE {$column} < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }

    public static function recordSchedulerHeartbeat(PDO $db, string $status = 'healthy', array $result = []): void
    {
        if (!self::tableExists($db, 'ai_scheduler_heartbeats')) {
            return;
        }

        $status = in_array($status, ['healthy', 'warning', 'degraded', 'unavailable', 'unknown'], true) ? $status : 'unknown';
        $counts = self::getSchedulerQueueCounts($db);
        $payload = array_merge($counts, $result);

        try {
            $db->prepare("
                INSERT INTO ai_scheduler_heartbeats
                    (scheduler_key, status, last_heartbeat_at, last_successful_cycle_at, last_failure_at,
                     expected_interval_seconds, due_jobs, delayed_jobs, last_result_json, created_at, updated_at)
                VALUES ('ai_automation_scheduler', ?, NOW(), IF(? = 'healthy', NOW(), NULL), IF(? IN ('degraded','unavailable'), NOW(), NULL),
                        60, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    last_heartbeat_at = NOW(),
                    last_successful_cycle_at = IF(VALUES(status) = 'healthy', NOW(), last_successful_cycle_at),
                    last_failure_at = IF(VALUES(status) IN ('degraded','unavailable'), NOW(), last_failure_at),
                    due_jobs = VALUES(due_jobs),
                    delayed_jobs = VALUES(delayed_jobs),
                    last_result_json = VALUES(last_result_json),
                    updated_at = NOW()
            ")->execute([
                $status,
                $status,
                $status,
                $counts['due_jobs'],
                $counts['delayed_jobs'],
                json_encode(self::sanitizeValue($payload)),
            ]);
        } catch (\Throwable $e) {
            // Non-critical.
        }
    }

    public static function getSchedulerStatus(PDO $db): array
    {
        if (!self::tableExists($db, 'ai_scheduler_heartbeats')) {
            return self::legacySchedulerStatus($db);
        }

        try {
            $stmt = $db->prepare("SELECT * FROM ai_scheduler_heartbeats WHERE scheduler_key = 'ai_automation_scheduler' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
            if (!$row) {
                return array_merge([
                    'status' => 'unknown',
                    'message' => 'No scheduler heartbeat recorded.',
                    'last_heartbeat_at' => null,
                    'last_successful_cycle_at' => null,
                    'last_failure_at' => null,
                    'expected_interval_seconds' => 60,
                ], self::getSchedulerQueueCounts($db));
            }

            $lastHeartbeat = $row['last_heartbeat_at'] ? strtotime($row['last_heartbeat_at']) : null;
            $expected = max(60, (int) ($row['expected_interval_seconds'] ?? 60));
            $computedStatus = $row['status'] ?: 'unknown';
            $message = 'Scheduler heartbeat recorded.';

            if (!$lastHeartbeat) {
                $computedStatus = 'unknown';
                $message = 'No scheduler heartbeat recorded.';
            } else {
                $age = time() - $lastHeartbeat;
                if ($age > ($expected * 15)) {
                    $computedStatus = 'unavailable';
                    $message = 'Scheduler heartbeat is stale.';
                } elseif ($age > ($expected * 5)) {
                    $computedStatus = 'warning';
                    $message = 'Scheduler heartbeat is delayed.';
                }
            }

            return array_merge($row, [
                'status' => $computedStatus,
                'message' => $message,
                'minutes_since_heartbeat' => $lastHeartbeat ? round((time() - $lastHeartbeat) / 60) : null,
            ], self::getSchedulerQueueCounts($db));
        } catch (\Throwable $e) {
            return self::legacySchedulerStatus($db);
        }
    }

    private static function legacySchedulerStatus(PDO $db): array
    {
        if (!self::tableExists($db, 'ai_automation_runs')) {
            return ['status' => 'unknown', 'message' => 'Automation run table not found.', 'due_jobs' => 0, 'delayed_jobs' => 0];
        }

        try {
            $lastRun = $db->query('SELECT MAX(created_at) FROM ai_automation_runs')->fetchColumn();
            if (!$lastRun) {
                return ['status' => 'unknown', 'message' => 'No scheduler runs recorded.', 'due_jobs' => 0, 'delayed_jobs' => 0];
            }
            $minutes = round((time() - strtotime($lastRun)) / 60);
            return [
                'status' => $minutes > 30 ? 'warning' : 'healthy',
                'message' => "Last automation run {$minutes} minutes ago.",
                'last_heartbeat_at' => $lastRun,
                'minutes_since_heartbeat' => $minutes,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'unknown', 'message' => 'Scheduler status unavailable.', 'due_jobs' => 0, 'delayed_jobs' => 0];
        }
    }

    public static function getSchedulerQueueCounts(PDO $db): array
    {
        if (!self::tableExists($db, 'ai_automations') || !self::tableExists($db, 'ai_automation_triggers')) {
            return ['due_jobs' => 0, 'delayed_jobs' => 0];
        }

        try {
            $due = $db->query("
                SELECT COUNT(*)
                FROM ai_automations a
                JOIN ai_automation_triggers t ON a.id = t.automation_id
                WHERE t.trigger_type = 'scheduled'
                AND t.enabled = 1
                AND a.is_active = 1
                AND a.next_run_at IS NOT NULL
                AND a.next_run_at <= NOW()
            ")->fetchColumn();

            $delayed = $db->query("
                SELECT COUNT(*)
                FROM ai_automations a
                JOIN ai_automation_triggers t ON a.id = t.automation_id
                WHERE t.trigger_type = 'scheduled'
                AND t.enabled = 1
                AND a.is_active = 1
                AND a.next_run_at IS NOT NULL
                AND a.next_run_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ")->fetchColumn();

            return ['due_jobs' => (int) $due, 'delayed_jobs' => (int) $delayed];
        } catch (\Throwable $e) {
            return ['due_jobs' => 0, 'delayed_jobs' => 0];
        }
    }

    public static function canRetryOperation(array $operation): bool
    {
        $type = $operation['operation_type'] ?? '';
        $details = json_decode($operation['sanitized_details'] ?? '{}', true);
        $details = is_array($details) ? $details : [];

        if ($type === 'provider_health_check' && !empty($operation['provider_id'])) {
            return true;
        }
        if (in_array($type, ['knowledge_reprocess', 'knowledge_processing', 'knowledge_embedding'], true) && !empty($details['source_id'])) {
            return true;
        }
        if ($type === 'diagnostics') {
            return true;
        }

        return false;
    }

    public static function retryFailedOperation(PDO $db, int $errorId, int $userId): array
    {
        if (!self::tableExists($db, 'ai_error_registry')) {
            return ['success' => false, 'error' => 'Error registry unavailable.'];
        }

        $stmt = $db->prepare("SELECT * FROM ai_error_registry WHERE id = ?");
        $stmt->execute([$errorId]);
        $operation = $stmt->fetch();
        if (!$operation) {
            return ['success' => false, 'error' => 'Failed operation not found.'];
        }
        if (!self::canRetryOperation($operation)) {
            return ['success' => false, 'error' => 'This operation is not safe to retry automatically.'];
        }

        $db->prepare("UPDATE ai_error_registry SET retry_count = retry_count + 1, resolution_status = 'investigating' WHERE id = ?")
           ->execute([$errorId]);

        $type = $operation['operation_type'] ?? '';
        $details = json_decode($operation['sanitized_details'] ?? '{}', true);
        $details = is_array($details) ? $details : [];
        $result = ['success' => false, 'error' => 'No retry handler is available for this operation.'];

        try {
            if ($type === 'provider_health_check' && class_exists('AIHealthService')) {
                $result = AIHealthService::checkProviderHealth($db, (int) $operation['provider_id'], true);
            } elseif (in_array($type, ['knowledge_reprocess', 'knowledge_processing', 'knowledge_embedding'], true) && class_exists('AIKnowledgeService')) {
                $result = AIKnowledgeService::reprocessSource($db, $userId, (int) $details['source_id']);
            } elseif ($type === 'diagnostics' && class_exists('AIManagementService')) {
                $checks = AIManagementService::runDiagnostics($db);
                $failed = array_filter($checks, fn($c) => !in_array($c['status'], ['healthy'], true));
                $result = ['success' => empty($failed), 'checks' => $checks];
            }
        } catch (\Throwable $e) {
            $result = ['success' => false, 'error' => 'Retry failed safely.'];
        }

        if (!empty($result['success'])) {
            $db->prepare("UPDATE ai_error_registry SET resolution_status = 'resolved', resolved_at = NOW() WHERE id = ?")
               ->execute([$errorId]);
            self::auditConfigChange($db, 'failed_operation', (string) $errorId, 'retry_success', null, $result, $userId);
        } else {
            self::auditConfigChange($db, 'failed_operation', (string) $errorId, 'retry_failed', null, $result, $userId);
        }

        return $result;
    }
}
