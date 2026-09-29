<?php
/**
 * AI F16 — Health Check Service.
 *
 * Monitors all AI components and reports their status.
 */
class AIHealthService
{
    private static function hasTable(PDO $db, string $table): bool
    {
        if (class_exists('AIGovernanceService')) {
            return AIGovernanceService::tableExists($db, $table);
        }

        try {
            $stmt = $db->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function hasColumn(PDO $db, string $table, string $column): bool
    {
        if (class_exists('AIGovernanceService')) {
            return AIGovernanceService::columnExists($db, $table, $column);
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
            return ((int) $stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Run all health checks and return results.
     */
    public static function checkAll(PDO $db): array
    {
        $checks = [
            'providers' => self::checkProviders($db),
            'models' => self::checkModels($db),
            'tool_registry' => self::checkToolRegistry($db),
            'action_registry' => self::checkActionRegistry($db),
            'knowledge' => self::checkKnowledge($db),
            'embedding' => self::checkEmbedding($db),
            'agent_engine' => self::checkAgentEngine($db),
            'scheduler' => self::checkScheduler($db),
            'event_system' => self::checkEventSystem($db),
            'insight_engine' => self::checkInsightEngine($db),
            'command_router' => self::checkCommandRouter($db),
        ];

        // Store results
        foreach ($checks as $component => $check) {
            self::storeCheck($db, $component, $check['type'], $check['status'], $check['message'] ?? null, $check['metadata'] ?? null);
        }

        foreach (self::getProviderHealth($db, false) as $providerCheck) {
            self::storeCheck(
                $db,
                'provider_' . (int) $providerCheck['provider_id'],
                'provider',
                $providerCheck['status'],
                $providerCheck['message'],
                $providerCheck['metadata'] ?? null
            );
        }

        return $checks;
    }

    /**
     * Check AI providers.
     */
    private static function checkProviders(PDO $db): array
    {
        if (!self::hasTable($db, 'ai_providers')) {
            return ['type' => 'provider', 'status' => 'unavailable', 'message' => 'Provider table is not installed.'];
        }

        $stmt = $db->query("SELECT COUNT(*) FROM ai_providers WHERE is_active = 1");
        $active = (int) $stmt->fetchColumn();
        $stmt2 = $db->query("SELECT COUNT(*) FROM ai_providers");
        $total = (int) $stmt2->fetchColumn();

        if ($active === 0) {
            return ['type' => 'provider', 'status' => 'unavailable', 'message' => 'No active providers configured.'];
        }

        $providerHealth = self::getProviderHealth($db, false);
        $statuses = array_column($providerHealth, 'status');

        $recentFailures = 0;
        if (self::hasTable($db, 'ai_usage_logs')) {
            $failures = $db->prepare("SELECT COUNT(*) FROM ai_usage_logs WHERE status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $failures->execute();
            $recentFailures = (int) $failures->fetchColumn();
        }

        if ($recentFailures > 10) {
            return ['type' => 'provider', 'status' => 'warning', 'message' => "{$recentFailures} failures in the last hour."];
        }
        if (in_array('unavailable', $statuses, true)) {
            return ['type' => 'provider', 'status' => 'degraded', 'message' => 'One or more active providers are unavailable.'];
        }
        if (in_array('warning', $statuses, true) || in_array('degraded', $statuses, true)) {
            return ['type' => 'provider', 'status' => 'warning', 'message' => 'One or more providers need attention.'];
        }

        return ['type' => 'provider', 'status' => 'healthy', 'message' => "{$active} active provider(s) of {$total} total."];
    }

    /**
     * Return cached or on-demand health for each configured provider.
     */
    public static function getProviderHealth(PDO $db, bool $force = false): array
    {
        if (!self::hasTable($db, 'ai_providers')) {
            return [];
        }

        $providers = $db->query("
            SELECT p.*
            FROM ai_providers p
            ORDER BY p.is_default DESC, p.is_active DESC, p.name ASC
        ")->fetchAll();

        $checks = [];
        foreach ($providers as $provider) {
            $checks[] = self::buildProviderHealth($db, $provider, $force);
        }

        return $checks;
    }

    /**
     * Run a single provider health check. $force=true performs the adapter auth test.
     */
    public static function checkProviderHealth(PDO $db, int $providerId, bool $force = true): array
    {
        if (!self::hasTable($db, 'ai_providers')) {
            return ['success' => false, 'status' => 'unavailable', 'message' => 'Provider table is not installed.'];
        }

        $stmt = $db->prepare("SELECT * FROM ai_providers WHERE id = ?");
        $stmt->execute([$providerId]);
        $provider = $stmt->fetch();
        if (!$provider) {
            return ['success' => false, 'status' => 'unavailable', 'message' => 'Provider not found.'];
        }

        $check = self::buildProviderHealth($db, $provider, $force);
        self::storeCheck(
            $db,
            'provider_' . $providerId,
            'provider',
            $check['status'],
            $check['message'],
            $check['metadata'] ?? null
        );

        return array_merge(['success' => $check['status'] === 'healthy'], $check);
    }

    private static function buildProviderHealth(PDO $db, array $provider, bool $force): array
    {
        $providerId = (int) $provider['id'];
        $metadata = [
            'provider_type' => $provider['provider_type'] ?? null,
            'is_active' => (bool) ($provider['is_active'] ?? false),
            'is_default' => (bool) ($provider['is_default'] ?? false),
            'credential_configured' => !empty($provider['api_key_encrypted']),
            'base_url_configured' => !empty($provider['base_url']),
            'active_models' => 0,
            'chat_models' => 0,
            'tool_models' => 0,
            'structured_models' => 0,
            'embedding_models' => 0,
            'recent_failures' => 0,
            'last_success_at' => null,
            'last_connection_status' => $provider['last_connection_status'] ?? ($provider['last_test_status'] ?? 'never_tested'),
            'last_connection_at' => $provider['last_connection_at'] ?? ($provider['last_tested_at'] ?? null),
            'last_connection_message' => $provider['last_connection_message'] ?? null,
            'checked_mode' => $force ? 'on_demand' : 'cached',
        ];

        if (self::hasTable($db, 'ai_models')) {
            $stmt = $db->prepare("
                SELECT COUNT(*) active_models,
                       SUM(CASE WHEN supports_chat = 1 THEN 1 ELSE 0 END) chat_models,
                       SUM(CASE WHEN supports_tool_calling = 1 THEN 1 ELSE 0 END) tool_models,
                       SUM(CASE WHEN supports_structured_output = 1 THEN 1 ELSE 0 END) structured_models,
                       SUM(CASE WHEN supports_embeddings = 1 OR model_type = 'embedding' THEN 1 ELSE 0 END) embedding_models
                FROM ai_models
                WHERE provider_id = ? AND is_active = 1
            ");
            $stmt->execute([$providerId]);
            $modelStats = $stmt->fetch() ?: [];
            foreach (['active_models', 'chat_models', 'tool_models', 'structured_models', 'embedding_models'] as $key) {
                $metadata[$key] = (int) ($modelStats[$key] ?? 0);
            }
        }

        if (self::hasTable($db, 'ai_usage_logs')) {
            $stmt = $db->prepare("
                SELECT
                    SUM(CASE WHEN status <> 'success' THEN 1 ELSE 0 END) recent_failures,
                    MAX(CASE WHEN status = 'success' THEN created_at ELSE NULL END) last_success_at
                FROM ai_usage_logs
                WHERE provider_id = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute([$providerId]);
            $usage = $stmt->fetch() ?: [];
            $metadata['recent_failures'] = (int) ($usage['recent_failures'] ?? 0);
            $metadata['last_success_at'] = $usage['last_success_at'] ?? null;
        }

        if ($force && class_exists('AIProviderAdapterFactory')) {
            try {
                $adapter = AIProviderAdapterFactory::create($db, $provider);
                if (!$adapter) {
                    $metadata['adapter_test'] = ['success' => false, 'message' => 'Unsupported provider type.'];
                } else {
                    $adapterResult = $adapter->testConnection();
                    $metadata['adapter_test'] = [
                        'success' => !empty($adapterResult['success']),
                        'message' => $adapterResult['message'] ?? null,
                    ];
                    self::updateProviderConnection($db, $providerId, !empty($adapterResult['success']), $adapterResult['message'] ?? '');
                    $metadata['last_connection_status'] = !empty($adapterResult['success']) ? 'success' : 'failed';
                    $metadata['last_connection_at'] = date('Y-m-d H:i:s');
                    $metadata['last_connection_message'] = $adapterResult['message'] ?? null;
                }
            } catch (\Throwable $e) {
                $metadata['adapter_test'] = ['success' => false, 'message' => 'Provider test failed safely.'];
                self::updateProviderConnection($db, $providerId, false, 'Provider test failed safely.');
                if (class_exists('AIGovernanceService')) {
                    AIGovernanceService::logError($db, [
                        'feature' => 'provider',
                        'operation_type' => 'provider_health_check',
                        'error_category' => 'provider_error',
                        'error_message' => $e->getMessage(),
                        'provider_id' => $providerId,
                        'details' => ['provider' => $provider['name'] ?? $providerId],
                    ]);
                }
            }
        }

        $issues = [];
        if (empty($metadata['is_active'])) {
            $issues[] = 'inactive';
        }
        if (empty($metadata['credential_configured'])) {
            $issues[] = 'missing credential';
        }
        if ((int) $metadata['active_models'] === 0) {
            $issues[] = 'no active models';
        }
        if (($metadata['last_connection_status'] ?? '') === 'failed') {
            $issues[] = 'last auth test failed';
        }
        if ((int) $metadata['recent_failures'] > 5) {
            $issues[] = $metadata['recent_failures'] . ' recent failures';
        }

        $status = 'healthy';
        if (empty($metadata['is_active'])) {
            $status = 'unknown';
        } elseif (empty($metadata['credential_configured']) || ($metadata['last_connection_status'] ?? '') === 'failed') {
            $status = 'unavailable';
        } elseif ((int) $metadata['active_models'] === 0 || (int) $metadata['recent_failures'] > 0) {
            $status = 'warning';
        }

        if (!empty($metadata['adapter_test']) && empty($metadata['adapter_test']['success'])) {
            $status = 'unavailable';
        }

        $message = empty($issues)
            ? 'Provider configured and no recent failures detected.'
            : 'Needs attention: ' . implode(', ', $issues) . '.';

        return [
            'provider_id' => $providerId,
            'provider_name' => $provider['name'] ?? ('Provider #' . $providerId),
            'provider_type' => $provider['provider_type'] ?? null,
            'status' => $status,
            'message' => $message,
            'metadata' => $metadata,
        ];
    }

    private static function updateProviderConnection(PDO $db, int $providerId, bool $success, string $message): void
    {
        if (!self::hasColumn($db, 'ai_providers', 'last_connection_status')) {
            return;
        }

        try {
            $db->prepare("
                UPDATE ai_providers
                SET last_connection_status = ?,
                    last_connection_at = NOW(),
                    last_connection_message = ?,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$success ? 'success' : 'failed', substr($message, 0, 500), $providerId]);
        } catch (\Throwable $e) {
            // Connection status is advisory.
        }
    }

    /**
     * Check models.
     */
    private static function checkModels(PDO $db): array
    {
        if (!self::hasTable($db, 'ai_models')) {
            return ['type' => 'model', 'status' => 'unavailable', 'message' => 'Model table is not installed.'];
        }

        $stmt = $db->query("SELECT COUNT(*) FROM ai_models WHERE is_active = 1");
        $active = (int) $stmt->fetchColumn();

        if ($active === 0) {
            return ['type' => 'model', 'status' => 'unavailable', 'message' => 'No active models configured.'];
        }

        return ['type' => 'model', 'status' => 'healthy', 'message' => "{$active} active model(s)."];
    }

    /**
     * Check tool registry.
     */
    private static function checkToolRegistry(PDO $db): array
    {
        if (!class_exists('AIToolRegistry')) {
            return ['type' => 'registry', 'status' => 'unavailable', 'message' => 'Tool registry is not loaded.'];
        }

        $tools = AIToolRegistry::getAll();
        $count = count($tools);

        if ($count === 0) {
            return ['type' => 'registry', 'status' => 'warning', 'message' => 'No tools registered.'];
        }

        return ['type' => 'registry', 'status' => 'healthy', 'message' => "{$count} tool(s) registered."];
    }

    /**
     * Check action registry.
     */
    private static function checkActionRegistry(PDO $db): array
    {
        if (!class_exists('AIActionRegistry')) {
            return ['type' => 'registry', 'status' => 'unavailable', 'message' => 'Action registry is not loaded.'];
        }

        $actions = AIActionRegistry::getAll();
        $count = count($actions);

        return ['type' => 'registry', 'status' => 'healthy', 'message' => "{$count} action(s) registered."];
    }

    /**
     * Check knowledge system.
     */
    private static function checkKnowledge(PDO $db): array
    {
        if (!self::hasTable($db, 'ai_knowledge_sources')) {
            return ['type' => 'knowledge', 'status' => 'unavailable', 'message' => 'Knowledge source table is not installed.'];
        }

        $stmt = $db->query("SELECT COUNT(*) FROM ai_knowledge_sources WHERE status = 'indexed'");
        $indexed = (int) $stmt->fetchColumn();
        $stmt2 = $db->query("SELECT COUNT(*) FROM ai_knowledge_sources WHERE status = 'failed'");
        $failed = (int) $stmt2->fetchColumn();

        if ($failed > 0) {
            return ['type' => 'knowledge', 'status' => 'warning', 'message' => "{$indexed} indexed, {$failed} failed."];
        }

        return ['type' => 'knowledge', 'status' => 'healthy', 'message' => "{$indexed} source(s) indexed."];
    }

    /**
     * Check embedding provider.
     */
    private static function checkEmbedding(PDO $db): array
    {
        if (!self::hasTable($db, 'ai_models')) {
            return ['type' => 'embedding', 'status' => 'unavailable', 'message' => 'Model table is not installed.'];
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM ai_models WHERE model_type = 'embedding' AND is_active = 1");
        $stmt->execute();
        $active = (int) $stmt->fetchColumn();

        if ($active === 0) {
            return ['type' => 'embedding', 'status' => 'degraded', 'message' => 'No active embedding provider. New indexing unavailable.'];
        }

        return ['type' => 'embedding', 'status' => 'healthy', 'message' => "{$active} embedding model(s) active."];
    }

    /**
     * Check agent engine.
     */
    private static function checkAgentEngine(PDO $db): array
    {
        if (!self::hasTable($db, 'ai_agents') || !self::hasTable($db, 'ai_agent_runs')) {
            return ['type' => 'agent', 'status' => 'unavailable', 'message' => 'Agent tables are not installed.'];
        }

        $stmt = $db->query("SELECT COUNT(*) FROM ai_agents WHERE enabled = 1");
        $active = (int) $stmt->fetchColumn();
        $stmt2 = $db->query("SELECT COUNT(*) FROM ai_agent_runs WHERE status = 'running'");
        $running = (int) $stmt2->fetchColumn();

        return ['type' => 'agent', 'status' => 'healthy', 'message' => "{$active} agent(s), {$running} running."];
    }

    /**
     * Check scheduler.
     */
    private static function checkScheduler(PDO $db): array
    {
        if (class_exists('AIGovernanceService')) {
            $status = AIGovernanceService::getSchedulerStatus($db);
            return [
                'type' => 'scheduler',
                'status' => $status['status'] ?? 'unknown',
                'message' => $status['message'] ?? 'Scheduler status unavailable.',
                'metadata' => $status,
            ];
        }

        if (!self::hasTable($db, 'ai_automation_runs')) {
            return ['type' => 'scheduler', 'status' => 'unknown', 'message' => 'Automation run table not found.'];
        }

        // Check if scheduler has run recently
        $stmt = $db->prepare("SELECT MAX(created_at) FROM ai_automation_runs");
        $stmt->execute();
        $lastRun = $stmt->fetchColumn();

        if (!$lastRun) {
            return ['type' => 'scheduler', 'status' => 'unknown', 'message' => 'No scheduler runs recorded.'];
        }

        $minutesAgo = (time() - strtotime($lastRun)) / 60;
        if ($minutesAgo > 30) {
            return ['type' => 'scheduler', 'status' => 'warning', 'message' => 'Last run was ' . round($minutesAgo) . ' minutes ago.'];
        }

        return ['type' => 'scheduler', 'status' => 'healthy', 'message' => 'Scheduler active. Last run ' . round($minutesAgo) . ' min ago.'];
    }

    /**
     * Check event system.
     */
    private static function checkEventSystem(PDO $db): array
    {
        if (!self::hasTable($db, 'ai_event_registry') || !self::hasTable($db, 'ai_event_subscriptions')) {
            return ['type' => 'event', 'status' => 'unavailable', 'message' => 'Event automation tables are not installed.'];
        }

        $stmt = $db->query("SELECT COUNT(*) FROM ai_event_registry WHERE enabled = 1");
        $events = (int) $stmt->fetchColumn();
        $stmt2 = $db->query("SELECT COUNT(*) FROM ai_event_subscriptions WHERE enabled = 1");
        $subs = (int) $stmt2->fetchColumn();

        return ['type' => 'event', 'status' => 'healthy', 'message' => "{$events} event(s), {$subs} subscription(s)."];
    }

    /**
     * Check insight engine.
     */
    private static function checkInsightEngine(PDO $db): array
    {
        if (!self::hasTable($db, 'ai_insight_definitions') || !self::hasTable($db, 'ai_insight_records')) {
            return ['type' => 'insight', 'status' => 'unavailable', 'message' => 'Insight tables are not installed.'];
        }

        $stmt = $db->query("SELECT COUNT(*) FROM ai_insight_definitions WHERE enabled = 1");
        $defs = (int) $stmt->fetchColumn();
        $stmt2 = $db->query("SELECT COUNT(*) FROM ai_insight_records WHERE status = 'active'");
        $active = (int) $stmt2->fetchColumn();

        return ['type' => 'insight', 'status' => 'healthy', 'message' => "{$defs} definition(s), {$active} active insight(s)."];
    }

    /**
     * Check command router.
     */
    private static function checkCommandRouter(PDO $db): array
    {
        if (!class_exists('AICapabilityRouter')) {
            return ['type' => 'command', 'status' => 'unavailable', 'message' => 'Command router is not loaded.'];
        }

        $handlers = AICapabilityRouter::getHandlers();
        $count = count($handlers);

        if ($count === 0) {
            return ['type' => 'command', 'status' => 'degraded', 'message' => 'No capability handlers registered.'];
        }

        return ['type' => 'command', 'status' => 'healthy', 'message' => "{$count} capability handler(s) registered."];
    }

    /**
     * Store a health check result.
     */
    private static function storeCheck(PDO $db, string $component, string $type, string $status, ?string $message, ?array $metadata): void
    {
        if (!self::hasTable($db, 'ai_health_checks')) {
            return;
        }

        try {
            $db->prepare("
                INSERT INTO ai_health_checks (component, component_type, status, message, last_checked_at, metadata_json)
                VALUES (?, ?, ?, ?, NOW(), ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), message = VALUES(message), last_checked_at = NOW(),
                failure_count = IF(VALUES(status) = 'healthy', 0, failure_count + 1),
                last_healthy_at = IF(VALUES(status) = 'healthy', NOW(), last_healthy_at),
                metadata_json = VALUES(metadata_json)
            ")->execute([
                $component,
                $type,
                $status,
                $message,
                $metadata ? json_encode(class_exists('AIGovernanceService') ? AIGovernanceService::sanitizeValue($metadata) : $metadata) : null,
            ]);
        } catch (\Throwable $e) {}
    }

    /**
     * Get stored health status.
     */
    public static function getStoredHealth(PDO $db): array
    {
        if (!self::hasTable($db, 'ai_health_checks')) {
            return [];
        }

        $stmt = $db->query("SELECT * FROM ai_health_checks ORDER BY component_type, component");
        return $stmt->fetchAll();
    }

    /**
     * Get overall system status.
     */
    public static function getOverallStatus(PDO $db): string
    {
        $checks = self::getStoredHealth($db);
        $statuses = array_column($checks, 'status');

        if (in_array('unavailable', $statuses)) return 'degraded';
        if (in_array('warning', $statuses)) return 'warning';
        if (in_array('degraded', $statuses)) return 'degraded';
        if (in_array('unknown', $statuses)) return 'unknown';
        return 'healthy';
    }
}
