<?php
/**
 * AI F16 — Management Center Service.
 *
 * Centralized administration, diagnostics, and configuration validation.
 */
class AIManagementService
{
    private static function hasTable(PDO $db, string $table): bool
    {
        if (class_exists('AIGovernanceService')) {
            return AIGovernanceService::tableExists($db, $table);
        }

        try {
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
            );
            $stmt->execute([$table]);
            return ((int) $stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            try {
                $db->query("SELECT 1 FROM `{$table}` LIMIT 0");
                return true;
            } catch (\Throwable $e2) {
                return false;
            }
        }
    }

    private static function count(PDO $db, string $table, string $where = '1=1', array $params = []): int
    {
        if (!self::hasTable($db, $table)) {
            return 0;
        }

        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get dashboard overview data.
     */
    public static function getDashboardData(PDO $db): array
    {
        $data = [];

        if (class_exists('AIGovernanceService')) {
            AIGovernanceService::ensureDefaults($db);
        }

        // AI System Status
        $data['system_status'] = class_exists('AIHealthService') ? AIHealthService::getOverallStatus($db) : 'unknown';

        // Active Providers
        $data['active_providers'] = self::count($db, 'ai_providers', 'is_active = 1');

        // Available Models
        $data['available_models'] = self::count($db, 'ai_models', 'is_active = 1');

        // Tool Calls (today)
        $data['tool_calls_today'] = self::count($db, 'ai_usage_logs', "feature = 'tool_call' AND DATE(created_at) = CURDATE()");

        // Agent Runs
        $data['agent_runs'] = self::count($db, 'ai_agent_runs', "status IN ('running', 'completed')");

        // Automation Runs
        $data['automation_runs'] = self::count($db, 'ai_automation_runs');

        // Active Insights
        $data['active_insights'] = self::count($db, 'ai_insight_records', "status = 'active'");

        // Knowledge Sources
        $data['knowledge_sources'] = self::count($db, 'ai_knowledge_sources');

        // Document Intelligence
        $data['document_total'] = self::count($db, 'ai_documents', "status = 'active'");
        $data['document_ready'] = self::count($db, 'ai_documents', "status = 'active' AND processing_status = 'ready'");
        $data['document_needs_review'] = self::count($db, 'ai_documents', "status = 'active' AND processing_status = 'needs_review'");
        $data['document_qna'] = self::count($db, 'ai_document_qna_sessions');
        $data['document_actions'] = self::count($db, 'ai_document_action_requests');

        // Voice Interface
        $data['voice_transcriptions'] = self::count($db, 'ai_voice_transcriptions');
        $data['voice_usage_30d'] = self::count($db, 'ai_voice_usage', "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");

        // Integrations
        $data['integration_connectors'] = self::count($db, 'ai_connectors');
        $data['integration_connections'] = self::count($db, 'ai_integration_connections', "status = 'active'");
        $data['integration_failed_connections'] = self::count($db, 'ai_integration_connections', "status IN ('failed','expired')");
        $data['integration_sync_jobs'] = self::count($db, 'ai_sync_jobs');
        $data['integration_webhook_failures'] = self::count($db, 'ai_webhook_deliveries', "status = 'failed'");
        $data['integration_activity_30d'] = self::count($db, 'ai_integration_activity', "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");

        // Observability
        $data['observability_requests_30d'] = self::count($db, 'ai_observability_requests', "DATE(started_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $data['observability_failed_30d'] = self::count($db, 'ai_observability_requests', "DATE(started_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND status IN ('failed','blocked','timeout')");
        $data['observability_feedback_open'] = self::count($db, 'ai_observability_feedback', "status IN ('new','reviewing')");
        $data['observability_findings_open'] = self::count($db, 'ai_observability_findings', "status IN ('open','investigating')");
        $data['observability_eval_runs'] = self::count($db, 'ai_observability_eval_runs', "DATE(started_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");

        // Failed Operations (today)
        $data['failed_operations_today'] = self::count($db, 'ai_error_registry', "DATE(created_at) = CURDATE()");
        $data['open_failed_operations'] = self::count($db, 'ai_error_registry', "resolution_status IN ('open','investigating')");

        // Usage Today
        $data['usage_today'] = class_exists('AIUsageService') ? AIUsageService::getTodaySummary($db) : [
            'requests' => 0,
            'successful' => 0,
            'failed' => 0,
            'total_tokens' => 0,
            'total_cost' => 0,
        ];

        // Kill Switch Status
        $data['kill_switches'] = class_exists('AIKillSwitchService') ? AIKillSwitchService::getStatusSummary($db) : [
            'total' => 0,
            'enabled' => 0,
            'disabled' => 0,
            'disabled_switches' => [],
        ];

        // Health Status
        $data['health'] = class_exists('AIHealthService') ? AIHealthService::getStoredHealth($db) : [];
        $data['provider_health'] = class_exists('AIHealthService') ? AIHealthService::getProviderHealth($db, false) : [];
        $data['scheduler'] = class_exists('AIGovernanceService') ? AIGovernanceService::getSchedulerStatus($db) : [];
        $data['rate_limits'] = class_exists('AIGovernanceService') ? AIGovernanceService::getRateLimitStatuses($db) : [];
        $data['cost_controls'] = class_exists('AIGovernanceService') ? AIGovernanceService::getCostControlStatuses($db) : [];
        $data['safety_policies'] = class_exists('AIGovernanceService') ? AIGovernanceService::getSafetyPolicies($db) : [];
        $data['retention_policies'] = self::getRetentionPolicies($db);
        $data['masking_policies'] = self::getMaskingPolicies($db);
        $data['capability_matrix'] = self::getCapabilityMatrix($db);
        $data['model_capability_matrix'] = self::getModelCapabilityMatrix($db);
        $data['feature_dependencies'] = self::getFeatureDependencies($db);
        $data['permission_matrix'] = self::getPermissionMatrix($db);
        $data['alerts'] = self::getAlerts($db);

        return $data;
    }

    /**
     * Run diagnostic checks.
     */
    public static function runDiagnostics(PDO $db): array
    {
        $checks = [];

        // Database connectivity
        try {
            $db->query("SELECT 1");
            $checks[] = ['name' => 'Database Connectivity', 'status' => 'healthy', 'message' => 'Connected'];
        } catch (\Throwable $e) {
            $checks[] = ['name' => 'Database Connectivity', 'status' => 'unavailable', 'message' => 'Connection failed'];
        }

        // Provider configuration
        $count = self::count($db, 'ai_providers');
        $checks[] = ['name' => 'Provider Configuration', 'status' => $count > 0 ? 'healthy' : 'warning', 'message' => "{$count} provider(s) configured"];

        $missingCredentials = 0;
        if (self::hasTable($db, 'ai_providers')) {
            $missingCredentials = self::count($db, 'ai_providers', 'is_active = 1 AND api_key_encrypted IS NULL');
        }
        $checks[] = [
            'name' => 'Provider Credentials',
            'status' => $missingCredentials === 0 ? 'healthy' : 'warning',
            'message' => $missingCredentials === 0 ? 'Active providers have credentials configured' : "{$missingCredentials} active provider(s) missing credentials",
        ];

        // Enabled models
        $count = self::count($db, 'ai_models', 'is_active = 1');
        $checks[] = ['name' => 'Enabled Models', 'status' => $count > 0 ? 'healthy' : 'warning', 'message' => "{$count} model(s) enabled"];

        // Tool registry
        $tools = class_exists('AIToolRegistry') ? AIToolRegistry::getAll() : [];
        $checks[] = ['name' => 'Tool Registry', 'status' => count($tools) > 0 ? 'healthy' : 'warning', 'message' => count($tools) . ' tool(s) registered'];

        // Action registry
        $actions = class_exists('AIActionRegistry') ? AIActionRegistry::getAll() : [];
        $checks[] = ['name' => 'Action Registry', 'status' => count($actions) > 0 ? 'healthy' : 'warning', 'message' => count($actions) . ' action(s) registered'];

        // Agent registry
        $count = self::count($db, 'ai_agents');
        $checks[] = ['name' => 'Agent Registry', 'status' => $count > 0 ? 'healthy' : 'warning', 'message' => "{$count} agent(s) configured"];

        // Knowledge collections
        $count = self::count($db, 'ai_knowledge_collections');
        $checks[] = ['name' => 'Knowledge Collections', 'status' => $count > 0 ? 'healthy' : 'info', 'message' => "{$count} collection(s)"];

        // Vector storage
        $hasVectorStorage = self::hasTable($db, 'ai_knowledge_chunks');
        $checks[] = ['name' => 'Vector Storage', 'status' => $hasVectorStorage ? 'healthy' : 'warning', 'message' => $hasVectorStorage ? 'Available' : 'Not found'];

        // Scheduler status
        if (class_exists('AIGovernanceService')) {
            $scheduler = AIGovernanceService::getSchedulerStatus($db);
            $checks[] = [
                'name' => 'Scheduler Status',
                'status' => $scheduler['status'] ?? 'unknown',
                'message' => $scheduler['message'] ?? 'Scheduler status unavailable',
            ];
        } elseif (self::hasTable($db, 'ai_automation_runs')) {
            $stmt = $db->prepare("SELECT MAX(created_at) FROM ai_automation_runs");
            $stmt->execute();
            $lastRun = $stmt->fetchColumn();
            if ($lastRun) {
                $minsAgo = round((time() - strtotime($lastRun)) / 60);
                $checks[] = ['name' => 'Scheduler Status', 'status' => $minsAgo < 30 ? 'healthy' : 'warning', 'message' => "Last run {$minsAgo} min ago"];
            } else {
                $checks[] = ['name' => 'Scheduler Status', 'status' => 'unknown', 'message' => 'No runs recorded'];
            }
        } else {
            $checks[] = ['name' => 'Scheduler Status', 'status' => 'unknown', 'message' => 'Automation run table not installed'];
        }

        // Event system
        $count = self::count($db, 'ai_event_registry', 'enabled = 1');
        $checks[] = ['name' => 'Event System', 'status' => $count > 0 ? 'healthy' : 'info', 'message' => "{$count} event(s) registered"];

        // Insight engine
        $count = self::count($db, 'ai_insight_definitions', 'enabled = 1');
        $checks[] = ['name' => 'Insight Engine', 'status' => $count > 0 ? 'healthy' : 'info', 'message' => "{$count} definition(s)"];

        // Permissions
        $managementPermission = self::permissionExists($db, 'ai.management.view');
        $checks[] = [
            'name' => 'Permissions',
            'status' => function_exists('hasPermission') && $managementPermission ? 'healthy' : 'warning',
            'message' => $managementPermission ? 'Permission system loaded' : 'Management permissions are not fully seeded',
        ];

        // Governance tables
        foreach (['ai_usage_logs', 'ai_health_checks', 'ai_kill_switches', 'ai_rate_limits', 'ai_cost_controls', 'ai_safety_policies', 'ai_error_registry', 'ai_config_audit', 'ai_documents', 'ai_document_qna_sessions', 'ai_document_action_requests', 'ai_voice_transcriptions', 'ai_voice_usage', 'ai_connectors', 'ai_connector_capabilities', 'ai_integration_connections', 'ai_integration_credentials', 'ai_connector_rest_endpoints', 'ai_webhook_definitions', 'ai_webhook_deliveries', 'ai_sync_jobs', 'ai_sync_mappings', 'ai_integration_activity', 'ai_integration_health', 'ai_integration_policies', 'ai_observability_requests', 'ai_observability_spans', 'ai_observability_feedback', 'ai_observability_eval_runs', 'ai_observability_findings', 'ai_observability_reports'] as $table) {
            $checks[] = [
                'name' => 'Table: ' . $table,
                'status' => self::hasTable($db, $table) ? 'healthy' : 'warning',
                'message' => self::hasTable($db, $table) ? 'Installed' : 'Missing',
            ];
        }

        return $checks;
    }

    /**
     * Validate configuration for enabling a feature.
     */
    public static function validateFeatureConfig(PDO $db, string $feature): array
    {
        $issues = [];

        switch ($feature) {
            case 'agents':
                if (!self::hasTable($db, 'ai_models')) {
                    $issues[] = 'AI model table is missing.';
                    break;
                }
                $stmt = $db->prepare("SELECT COUNT(*) FROM ai_models WHERE is_active = 1 AND supports_tool_calling = 1");
                $stmt->execute();
                if ((int) $stmt->fetchColumn() === 0) {
                    $issues[] = 'No model with Tool Calling capability is enabled.';
                }
                $stmt2 = $db->prepare("SELECT COUNT(*) FROM ai_models WHERE is_active = 1 AND supports_structured_output = 1");
                $stmt2->execute();
                if ((int) $stmt2->fetchColumn() === 0) {
                    $issues[] = 'No model with Structured Output capability is enabled.';
                }
                break;

            case 'knowledge':
                if (!self::hasTable($db, 'ai_models')) {
                    $issues[] = 'AI model table is missing.';
                    break;
                }
                $stmt = $db->prepare("SELECT COUNT(*) FROM ai_models WHERE model_type = 'embedding' AND is_active = 1");
                $stmt->execute();
                if ((int) $stmt->fetchColumn() === 0) {
                    $issues[] = 'No active embedding provider configured.';
                }
                break;

            case 'scheduled_automations':
                // Check scheduler exists
                $scheduler = class_exists('AIGovernanceService') ? AIGovernanceService::getSchedulerStatus($db) : null;
                if ($scheduler && !in_array($scheduler['status'] ?? 'unknown', ['healthy', 'warning'], true)) {
                    $issues[] = 'Scheduler is not in a healthy state.';
                }
                break;
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
        ];
    }

    /**
     * Get capability access matrix.
     */
    public static function getCapabilityMatrix(PDO $db): array
    {
        return [
            ['capability' => 'AI Chat', 'permission' => 'ai.assistant.access', 'enabled' => self::switchEnabled($db, 'ai_chat'), 'provider' => 'Chat model', 'ready' => self::validateFeatureConfig($db, 'chat')['valid']],
            ['capability' => 'Command Center', 'permission' => 'ai.command_center.access', 'enabled' => self::switchEnabled($db, 'command_center'), 'provider' => 'Chat model', 'ready' => self::validateFeatureConfig($db, 'command_center')['valid']],
            ['capability' => 'Agents', 'permission' => 'ai.agents.execute', 'enabled' => self::switchEnabled($db, 'agents'), 'provider' => 'Tool calling + structured output', 'ready' => self::validateFeatureConfig($db, 'agents')['valid']],
            ['capability' => 'Knowledge', 'permission' => 'ai.knowledge.search', 'enabled' => self::switchEnabled($db, 'knowledge_retrieval'), 'provider' => 'Embedding model', 'ready' => self::validateFeatureConfig($db, 'knowledge')['valid']],
            ['capability' => 'Automations', 'permission' => 'ai.automation.execute', 'enabled' => self::switchEnabled($db, 'automations'), 'provider' => 'Task/automation runtime', 'ready' => self::validateFeatureConfig($db, 'automations')['valid']],
            ['capability' => 'Insights', 'permission' => 'ai.insights.view', 'enabled' => self::switchEnabled($db, 'ai_insights'), 'provider' => 'Insight engine', 'ready' => self::validateFeatureConfig($db, 'insights')['valid']],
            ['capability' => 'Write Actions', 'permission' => 'ai.actions.request', 'enabled' => self::switchEnabled($db, 'write_actions'), 'provider' => 'F9 pending actions', 'ready' => class_exists('AIActionRegistry')],
            ['capability' => 'Tool Calling', 'permission' => 'ai.tools.use', 'enabled' => self::switchEnabled($db, 'tool_calling'), 'provider' => 'Tool registry', 'ready' => class_exists('AIToolRegistry') && count(AIToolRegistry::getAll()) > 0],
            ['capability' => 'External Integrations', 'permission' => 'ai.integrations.use', 'enabled' => self::switchEnabled($db, 'external_integrations'), 'provider' => 'Connector registry', 'ready' => self::hasTable($db, 'ai_connectors') && self::count($db, 'ai_connectors') > 0],
        ];
    }

    private static function switchEnabled(PDO $db, string $switch): bool
    {
        return class_exists('AIKillSwitchService') ? AIKillSwitchService::isEnabled($db, $switch) : true;
    }

    /**
     * Get model capability matrix.
     */
    public static function getModelCapabilityMatrix(PDO $db): array
    {
        if (!self::hasTable($db, 'ai_models')) {
            return [];
        }

        $joinProvider = self::hasTable($db, 'ai_providers');
        $providerSelect = $joinProvider ? ', p.name as provider_name, p.is_active as provider_active' : ', NULL as provider_name, 1 as provider_active';
        $providerJoin = $joinProvider ? 'LEFT JOIN ai_providers p ON p.id = m.provider_id' : '';
        $stmt = $db->query("
            SELECT m.id, m.provider_id, m.model_id, m.display_name, m.model_type,
                   m.context_window, m.max_output_tokens, m.supports_chat, m.supports_tool_calling,
                   m.supports_structured_output, m.supports_embeddings, m.supports_vision,
                   m.is_default
                   {$providerSelect}
            FROM ai_models m
            {$providerJoin}
            WHERE m.is_active = 1
            ORDER BY m.is_default DESC, m.display_name
        ");
        $models = $stmt->fetchAll();

        $matrix = [];
        foreach ($models as $model) {
            $matrix[] = [
                'id' => (int) $model['id'],
                'model' => $model['display_name'] ?? $model['model_id'],
                'model_id' => $model['model_id'],
                'provider' => $model['provider_name'] ?? 'Unknown provider',
                'type' => $model['model_type'],
                'chat' => (bool) $model['supports_chat'],
                'tools' => (bool) $model['supports_tool_calling'],
                'structured' => (bool) $model['supports_structured_output'],
                'vision' => (bool) ($model['supports_vision'] ?? false),
                'embedding' => (bool) $model['supports_embeddings'] || $model['model_type'] === 'embedding',
                'agent' => (bool) $model['supports_tool_calling'] && (bool) $model['supports_structured_output'],
                'context_window' => $model['context_window'] ?? null,
                'max_output_tokens' => $model['max_output_tokens'] ?? null,
                'default' => (bool) ($model['is_default'] ?? false),
            ];
        }

        return $matrix;
    }

    /**
     * Get feature dependency map.
     */
    public static function getFeatureDependencies(PDO $db): array
    {
        $deps = [
            'command_center' => [
                'name' => 'Command Center',
                'requires' => ['Chat Model'],
                'status' => self::checkFeatureReady($db, 'command_center'),
                'enabled' => self::switchEnabled($db, 'command_center'),
            ],
            'agents' => [
                'name' => 'Agents',
                'requires' => ['Tool Calling', 'Structured Output', 'Agent Engine'],
                'status' => self::checkFeatureReady($db, 'agents'),
                'enabled' => self::switchEnabled($db, 'agents'),
            ],
            'knowledge' => [
                'name' => 'Knowledge',
                'requires' => ['Embedding Provider', 'Vector Storage', 'Knowledge Tool'],
                'status' => self::checkFeatureReady($db, 'knowledge'),
                'enabled' => self::switchEnabled($db, 'knowledge_retrieval'),
            ],
            'automations' => [
                'name' => 'Automations',
                'requires' => ['Scheduler'],
                'status' => self::checkFeatureReady($db, 'automations'),
                'enabled' => self::switchEnabled($db, 'automations'),
            ],
            'insights' => [
                'name' => 'Insights',
                'requires' => ['Read Tools'],
                'status' => self::checkFeatureReady($db, 'insights'),
                'enabled' => self::switchEnabled($db, 'ai_insights'),
            ],
        ];

        // Add user-friendly explanation for each feature
        foreach ($deps as $key => &$dep) {
            if (!$dep['enabled']) {
                $dep['explanation'] = self::dependencyExplanation($key, 'disabled');
            } elseif (str_starts_with($dep['status'], 'missing:')) {
                $dep['explanation'] = self::dependencyExplanation($key, 'missing', $dep['status']);
            } else {
                $dep['explanation'] = self::dependencyExplanation($key, 'ready');
            }
        }
        unset($dep);

        return $deps;
    }

    /**
     * Generate user-friendly explanation for feature availability.
     */
    private static function dependencyExplanation(string $feature, string $state, ?string $detail = null): string
    {
        $explanations = [
            'command_center' => [
                'ready' => 'Command Center is fully configured and operational.',
                'disabled' => 'Command Center is disabled by an administrator via kill switch. Re-enable it in Safety & Kill Switches.',
                'missing' => 'Command Center requires at least one active Chat model. Configure a provider and enable a chat-capable model.',
            ],
            'agents' => [
                'ready' => 'Agent Engine is fully configured with tool calling and structured output capabilities.',
                'disabled' => 'Agent capability is disabled by an administrator via kill switch. Re-enable it in Safety & Kill Switches.',
                'missing' => 'Agents require at least one model supporting Tool Calling + Structured Output. Enable a compatible model in Models.',
            ],
            'knowledge' => [
                'ready' => 'Knowledge system is configured with embedding provider and vector storage.',
                'disabled' => 'Knowledge Retrieval is disabled by an administrator via kill switch. Re-enable it in Safety & Kill Switches.',
                'missing' => 'Knowledge requires an active embedding model and vector storage. Configure an embedding provider in Models.',
            ],
            'automations' => [
                'ready' => 'Automation scheduler and engine are operational.',
                'disabled' => 'Automation capability is disabled by an administrator via kill switch. Re-enable it in Safety & Kill Switches.',
                'missing' => 'Automations require the AI scheduler to be running. Ensure the automation scheduler is configured and healthy.',
            ],
            'insights' => [
                'ready' => 'Insight Engine is configured and can evaluate insights using read-only tools.',
                'disabled' => 'AI Insights are disabled by an administrator via kill switch. Re-enable them in Safety & Kill Switches.',
                'missing' => 'Insights require read-only tools to be registered. Ensure the Tool Registry has read tools available.',
            ],
        ];

        $featureExplanations = $explanations[$feature] ?? [];
        return $featureExplanations[$state] ?? 'Unknown status for ' . $feature . '.';
    }

    private static function checkFeatureReady(PDO $db, string $feature): string
    {
        $validation = self::validateFeatureConfig($db, $feature);
        if ($validation['valid']) return 'ready';
        return 'missing: ' . implode(', ', $validation['issues']);
    }

    /**
     * Get failed operations.
     */
    public static function getFailedOperations(PDO $db, array $filters = []): array
    {
        if (!self::hasTable($db, 'ai_error_registry')) {
            return [];
        }

        $where = ["1=1"];
        $params = [];

        if (!empty($filters['feature'])) {
            $where[] = "feature = ?";
            $params[] = $filters['feature'];
        }
        if (!empty($filters['error_category'])) {
            $where[] = "error_category = ?";
            $params[] = $filters['error_category'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }

        $whereClause = implode(' AND ', $where);
        $limit = min(100, (int) ($filters['limit'] ?? 50));

        $stmt = $db->prepare("SELECT * FROM ai_error_registry WHERE {$whereClause} ORDER BY created_at DESC LIMIT {$limit}");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['can_retry'] = class_exists('AIGovernanceService') ? AIGovernanceService::canRetryOperation($row) : false;
        }
        unset($row);
        return $rows;
    }

    /**
     * Get audit timeline.
     */
    public static function getAuditTimeline(PDO $db, array $filters = []): array
    {
        if (!self::hasTable($db, 'ai_config_audit')) {
            return [];
        }

        $where = ["1=1"];
        $params = [];

        if (!empty($filters['config_type'])) {
            $where[] = "config_type = ?";
            $params[] = $filters['config_type'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }

        $whereClause = implode(' AND ', $where);
        $limit = min(100, $filters['limit'] ?? 50);

        $stmt = $db->prepare("SELECT ca.*, u.name as user_name FROM ai_config_audit ca LEFT JOIN users u ON ca.user_id = u.id WHERE {$whereClause} ORDER BY ca.created_at DESC LIMIT {$limit}");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getPermissionMatrix(PDO $db): array
    {
        $rows = self::getCapabilityMatrix($db);

        if (!self::hasTable($db, 'permissions')) {
            foreach ($rows as &$row) {
                $row['seeded'] = false;
                $row['assigned_roles'] = 0;
                $row['assigned_users'] = 0;
            }
            unset($row);
            return $rows;
        }

        foreach ($rows as &$row) {
            $permission = $row['permission'];
            $row['seeded'] = self::permissionExists($db, $permission);
            $row['assigned_roles'] = 0;
            $row['assigned_users'] = 0;

            if ($row['seeded'] && self::hasTable($db, 'role_permissions')) {
                $parts = explode('.', $permission, 2);
                $stmt = $db->prepare("
                    SELECT COUNT(DISTINCT rp.role_id)
                    FROM role_permissions rp
                    JOIN permissions p ON p.id = rp.permission_id
                    WHERE p.module = ? AND p.name = ?
                ");
                $stmt->execute([$parts[0] ?? '', $parts[1] ?? '']);
                $row['assigned_roles'] = (int) $stmt->fetchColumn();
            }

            if ($row['seeded'] && self::hasTable($db, 'user_roles') && self::hasTable($db, 'role_permissions')) {
                $parts = explode('.', $permission, 2);
                $stmt = $db->prepare("
                    SELECT COUNT(DISTINCT ur.user_id)
                    FROM user_roles ur
                    JOIN role_permissions rp ON rp.role_id = ur.role_id
                    JOIN permissions p ON p.id = rp.permission_id
                    WHERE p.module = ? AND p.name = ?
                ");
                $stmt->execute([$parts[0] ?? '', $parts[1] ?? '']);
                $row['assigned_users'] = (int) $stmt->fetchColumn();
            }
        }
        unset($row);

        return $rows;
    }

    public static function getAlerts(PDO $db): array
    {
        $alerts = [];

        foreach (class_exists('AIHealthService') ? AIHealthService::getStoredHealth($db) : [] as $health) {
            if (in_array($health['status'], ['warning', 'degraded', 'unavailable'], true)) {
                $alerts[] = [
                    'severity' => $health['status'] === 'unavailable' ? 'critical' : 'warning',
                    'title' => 'Component health: ' . $health['component'],
                    'message' => $health['message'] ?: 'Component needs attention.',
                    'created_at' => $health['last_checked_at'] ?? null,
                ];
            }
        }

        if (class_exists('AIKillSwitchService')) {
            foreach (AIKillSwitchService::getAll($db) as $switch) {
                if (empty($switch['enabled'])) {
                    $alerts[] = [
                        'severity' => 'warning',
                        'title' => 'Kill switch disabled: ' . ($switch['name'] ?? $switch['switch_key']),
                        'message' => $switch['reason'] ?: 'Feature is disabled by an administrator.',
                        'created_at' => $switch['disabled_at'] ?? $switch['updated_at'] ?? null,
                    ];
                }
            }
        }

        if (class_exists('AIGovernanceService')) {
            foreach (AIGovernanceService::getCostControlStatuses($db) as $control) {
                if (!empty($control['enabled']) && (float) ($control['percent_used'] ?? 0) >= 80) {
                    $alerts[] = [
                        'severity' => (float) $control['percent_used'] >= 100 ? 'critical' : 'warning',
                        'title' => 'AI budget threshold',
                        'message' => $control['control_type'] . ' is at ' . $control['percent_used'] . '% of configured budget.',
                        'created_at' => $control['updated_at'] ?? null,
                    ];
                }
            }

            $scheduler = AIGovernanceService::getSchedulerStatus($db);
            if (!in_array($scheduler['status'] ?? 'unknown', ['healthy'], true)) {
                $alerts[] = [
                    'severity' => ($scheduler['status'] ?? '') === 'unavailable' ? 'critical' : 'warning',
                    'title' => 'Scheduler heartbeat',
                    'message' => $scheduler['message'] ?? 'Scheduler status is not healthy.',
                    'created_at' => $scheduler['last_heartbeat_at'] ?? null,
                ];
            }
        }

        foreach (self::getFailedOperations($db, ['limit' => 10]) as $failure) {
            if (in_array($failure['resolution_status'] ?? 'open', ['open', 'investigating'], true)) {
                $alerts[] = [
                    'severity' => 'warning',
                    'title' => 'Failed AI operation: ' . ($failure['feature'] ?? 'unknown'),
                    'message' => $failure['error_message'] ?? 'Failed operation requires review.',
                    'created_at' => $failure['created_at'] ?? null,
                ];
            }
        }

        usort($alerts, function ($a, $b) {
            $severity = ['critical' => 0, 'warning' => 1, 'info' => 2];
            $aRank = $severity[$a['severity']] ?? 3;
            $bRank = $severity[$b['severity']] ?? 3;
            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        return array_slice($alerts, 0, 25);
    }

    public static function getRetentionPolicies(PDO $db): array
    {
        if (!self::hasTable($db, 'ai_retention_policies')) {
            return [];
        }

        return $db->query("SELECT * FROM ai_retention_policies ORDER BY data_type")->fetchAll();
    }

    public static function getMaskingPolicies(PDO $db): array
    {
        if (!self::hasTable($db, 'ai_masking_policies')) {
            return [];
        }

        return $db->query("SELECT * FROM ai_masking_policies ORDER BY enabled DESC, module, field_pattern")->fetchAll();
    }

    private static function permissionExists(PDO $db, string $permission): bool
    {
        if (!self::hasTable($db, 'permissions')) {
            return false;
        }

        $parts = explode('.', $permission, 2);
        if (count($parts) !== 2) {
            return false;
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM permissions WHERE module = ? AND name = ?");
        $stmt->execute([$parts[0], $parts[1]]);
        return ((int) $stmt->fetchColumn()) > 0;
    }
}
