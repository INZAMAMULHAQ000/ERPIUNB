<?php
/**
 * AI F16 — AI Management Center.
 *
 * Centralized governance, observability, safety, usage tracking,
 * cost controls, kill switches, diagnostics, and health monitoring.
 */
$pageTitle = 'AI Management Center';
require_once __DIR__ . '/../bootstrap.php';
require_once AI_MODULE_DIR . '/includes/ai_ui.php';

if (!hasPermission('ai.management.view') && !hasPermission('ai.settings.manage') && ($_SESSION['role'] ?? '') !== 'superadmin') {
    die('Access Denied');
}

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'overview';

AIGovernanceService::ensureDefaults($db);

$governanceHealth = [
    'active_policies' => 0,
    'draft_policies' => 0,
    'active_risk_rules' => 0,
    'pending_reviews' => 0,
    'open_security_events' => 0,
    'policy_engine_status' => 'unavailable',
    'autonomy_level' => 'suggest',
];
if (class_exists('AIPolicyEngine')) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM ai_policies WHERE status = 'active'");
        $governanceHealth['active_policies'] = (int) $stmt->fetchColumn();
        $stmt = $db->query("SELECT COUNT(*) FROM ai_policies WHERE status = 'draft'");
        $governanceHealth['draft_policies'] = (int) $stmt->fetchColumn();
        $stmt = $db->query("SELECT COUNT(*) FROM ai_risk_rules WHERE is_active = 1");
        $governanceHealth['active_risk_rules'] = (int) $stmt->fetchColumn();
        $stmt = $db->query("SELECT COUNT(*) FROM ai_review_queue WHERE status = 'pending'");
        $governanceHealth['pending_reviews'] = (int) $stmt->fetchColumn();
        $stmt = $db->query("SELECT COUNT(*) FROM ai_security_events WHERE status = 'open'");
        $governanceHealth['open_security_events'] = (int) $stmt->fetchColumn();

        $policyResult = AIPolicyEngine::evaluate($db, 'management_access', [
            'user_id' => $userId,
            'company_id' => $_SESSION['company_id'] ?? 1,
        ]);
        $governanceHealth['policy_engine_status'] = $policyResult['decision'] === 'deny' ? 'restricted' : 'active';

        $governanceHealth['autonomy_level'] = AIPolicyEngine::getAutonomyLevel($db, $_SESSION['company_id'] ?? null, null, $userId);
    } catch (\Throwable $e) {
    }
}

$dashboardData = AIManagementService::getDashboardData($db);
$overallStatus = $dashboardData['system_status'] ?? 'unknown';
$healthStatus = $dashboardData['health'] ?? [];
$providerHealth = $dashboardData['provider_health'] ?? [];
$killSwitches = AIKillSwitchService::getAll($db);
$killSwitchSummary = AIKillSwitchService::getStatusSummary($db);
$alerts = $dashboardData['alerts'] ?? [];
$todayUsage = $dashboardData['usage_today'] ?? [];
$schedulerStatus = $dashboardData['scheduler'] ?? [];
$capabilityMatrix = $dashboardData['capability_matrix'] ?? [];
$modelCapabilityMatrix = $dashboardData['model_capability_matrix'] ?? [];
$featureDependencies = $dashboardData['feature_dependencies'] ?? [];
$permissionMatrix = $dashboardData['permission_matrix'] ?? [];
$safetyPolicies = $dashboardData['safety_policies'] ?? [];
$costControls = $dashboardData['cost_controls'] ?? [];
$rateLimits = $dashboardData['rate_limits'] ?? [];
$retentionPolicies = $dashboardData['retention_policies'] ?? [];
$maskingPolicies = $dashboardData['masking_policies'] ?? [];
$failedOps = AIManagementService::getFailedOperations($db, ['limit' => 20]);
$auditLogs = AIManagementService::getAuditTimeline($db, ['limit' => 25]);
$usageStats = AIUsageService::getStats($db, ['date_from' => date('Y-m-d 00:00:00', strtotime('-30 days'))]);

$killSwitchMap = [];
foreach ($killSwitches as $ks) {
    $killSwitchMap[$ks['switch_key']] = (bool)$ks['enabled'];
}

$allKillSwitchFeatures = AIKillSwitchService::getDefaultSwitches();
?>
<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>
<?php ai_render_module_nav('ai.management'); ?>
<?php ai_render_page_styles(); ?>

<div class="ai-page-container">

<!-- Page Header -->
<div class="ai-page-header mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-white">🛡️ AI Management Center</h1>
            <span id="overallStatusBadge" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold <?= $overallStatus === 'healthy' ? 'bg-emerald-500/20 text-emerald-400' : ($overallStatus === 'warning' ? 'bg-amber-500/20 text-amber-400' : 'bg-red-500/20 text-red-400') ?>">
                <span class="w-2 h-2 rounded-full mr-1.5 <?= $overallStatus === 'healthy' ? 'bg-emerald-400' : ($overallStatus === 'warning' ? 'bg-amber-400' : 'bg-red-400') ?>"></span>
                <?= ucfirst(htmlspecialchars($overallStatus)) ?>
            </span>
        </div>
        <p class="text-gray-400 text-sm mt-1">Centralized AI governance, observability, cost controls, safety policies, and system health</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <button onclick="runHealthCheckAll()" class="bg-sky-600 hover:bg-sky-700 text-white px-3.5 py-2 rounded-lg text-sm font-medium transition shadow-sm flex items-center gap-1.5">
            <i class="fas fa-heartbeat"></i> Health Check
        </button>
        <button onclick="runDiagnosticsModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3.5 py-2 rounded-lg text-sm font-medium transition shadow-sm flex items-center gap-1.5">
            <i class="fas fa-stethoscope"></i> Diagnostics
        </button>
        <button onclick="runRetentionCleanup()" class="bg-gray-700 hover:bg-gray-800 text-white px-3.5 py-2 rounded-lg text-sm font-medium transition shadow-sm flex items-center gap-1.5">
            <i class="fas fa-broom"></i> Retention Cleanup
        </button>
    </div>
</div>

<!-- System Alerts (if any) -->
<?php if (!empty($alerts)): ?>
<div class="mb-6 space-y-2">
    <?php foreach (array_slice($alerts, 0, 3) as $alert): ?>
    <div class="flex items-center justify-between p-3.5 rounded-lg border text-sm <?= $alert['severity'] === 'critical' ? 'border-red-500/30 bg-red-500/10 text-red-400' : ($alert['severity'] === 'warning' ? 'border-amber-500/30 bg-amber-500/10 text-amber-400' : 'border-sky-500/30 bg-sky-500/10 text-sky-400') ?>">
        <div class="flex items-center gap-2.5">
            <i class="fas <?= $alert['severity'] === 'critical' ? 'fa-exclamation-circle text-red-400' : 'fa-exclamation-triangle text-amber-400' ?>"></i>
            <div>
                <strong class="text-white"><?= htmlspecialchars($alert['title']) ?>:</strong> <?= htmlspecialchars($alert['message']) ?>
            </div>
        </div>
        <?php if (!empty($alert['created_at'])): ?>
        <span class="text-xs text-gray-400"><?= htmlspecialchars($alert['created_at']) ?></span>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Management Center Tabs -->
<div class="ai-tabs mb-6">
    <a href="?tab=overview" class="ai-tab <?= $tab === 'overview' ? 'active' : '' ?>">
        <i class="fas fa-tachometer-alt"></i> Overview
    </a>
    <a href="?tab=providers" class="ai-tab <?= $tab === 'providers' ? 'active' : '' ?>">
        <i class="fas fa-server"></i> Providers & Models
    </a>
    <a href="?tab=capabilities" class="ai-tab <?= $tab === 'capabilities' ? 'active' : '' ?>">
        <i class="fas fa-puzzle-piece"></i> AI Capabilities
    </a>
    <a href="?tab=usage" class="ai-tab <?= $tab === 'usage' ? 'active' : '' ?>">
        <i class="fas fa-chart-pie"></i> Usage & Cost Controls
    </a>
    <a href="?tab=safety" class="ai-tab <?= $tab === 'safety' ? 'active' : '' ?>">
        <i class="fas fa-shield-alt"></i> Safety & Kill Switches
    </a>
    <a href="?tab=operations" class="ai-tab <?= $tab === 'operations' ? 'active' : '' ?>">
        <i class="fas fa-exclamation-triangle"></i> Failed Operations
    </a>
    <a href="?tab=privacy" class="ai-tab <?= $tab === 'privacy' ? 'active' : '' ?>">
        <i class="fas fa-user-shield"></i> Privacy, Retention & Audit
    </a>
    <a href="?tab=diagnostics" class="ai-tab <?= $tab === 'diagnostics' ? 'active' : '' ?>">
        <i class="fas fa-microchip"></i> Diagnostics & Scheduler
    </a>
</div>

<!-- ========================================================================= -->
<!-- TAB 1: OVERVIEW DASHBOARD                                                -->
<!-- ========================================================================= -->
<?php if ($tab === 'overview'): ?>

<!-- 10 Real Data Stat Cards -->
<div class="ai-stat-grid mb-6">
    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-heartbeat"></i></div>
        <div class="ai-stat-value <?= $overallStatus === 'healthy' ? 'text-emerald-400' : ($overallStatus === 'warning' ? 'text-amber-400' : 'text-red-400') ?>">
            <?= ucfirst(htmlspecialchars($overallStatus)) ?>
        </div>
        <div class="ai-stat-label">System Status</div>
        <div class="text-xs text-gray-400 mt-1">All subsystems checked</div>
    </div>

    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-shield-alt"></i></div>
        <div class="ai-stat-value text-white"><?= $governanceHealth['active_policies'] ?></div>
        <div class="ai-stat-label">Active Policies</div>
        <div class="text-xs text-gray-400 mt-1"><?= $governanceHealth['draft_policies'] ?> drafts</div>
    </div>

    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="ai-stat-value text-white"><?= $governanceHealth['active_risk_rules'] ?></div>
        <div class="ai-stat-label">Risk Rules</div>
        <div class="text-xs text-gray-400 mt-1">Active classification rules</div>
    </div>

    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-search"></i></div>
        <div class="ai-stat-value text-white"><?= $governanceHealth['pending_reviews'] ?></div>
        <div class="ai-stat-label">Pending Reviews</div>
        <div class="text-xs text-gray-400 mt-1">Awaiting human review</div>
    </div>

    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-bell"></i></div>
        <div class="ai-stat-value text-white"><?= $governanceHealth['open_security_events'] ?></div>
        <div class="ai-stat-label">Open Security Events</div>
        <div class="text-xs text-gray-400 mt-1">Requires attention</div>
    </div>

    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-server"></i></div>
        <div class="ai-stat-value text-white"><?= (int) ($dashboardData['active_providers'] ?? 0) ?></div>
        <div class="ai-stat-label">Active Providers</div>
        <div class="text-xs text-gray-400 mt-1">Configured & active</div>
    </div>

    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-cube"></i></div>
        <div class="ai-stat-value text-white"><?= (int) ($dashboardData['available_models'] ?? 0) ?></div>
        <div class="ai-stat-label">Available Models</div>
        <div class="text-xs text-gray-400 mt-1">Chat & embedding models</div>
    </div>

    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-tools"></i></div>
        <div class="ai-stat-value text-white"><?= (int) ($dashboardData['tool_calls_today'] ?? 0) ?></div>
        <div class="ai-stat-label">Tool Calls Today</div>
        <div class="text-xs text-gray-400 mt-1">ERP function executions</div>
    </div>

    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-robot"></i></div>
        <div class="ai-stat-value text-white"><?= (int) ($dashboardData['agent_runs'] ?? 0) ?></div>
        <div class="ai-stat-label">Agent Runs</div>
        <div class="text-xs text-gray-400 mt-1">Autonomous workflows</div>
    </div>

    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-bolt"></i></div>
        <div class="ai-stat-value text-white"><?= (int) ($dashboardData['automation_runs'] ?? 0) ?></div>
        <div class="ai-stat-label">Automation Runs</div>
        <div class="text-xs text-gray-400 mt-1">Scheduled & event runs</div>
    </div>

    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-wave-square"></i></div>
        <div class="ai-stat-value text-white"><?= (int) ($dashboardData['observability_requests_30d'] ?? 0) ?></div>
        <div class="ai-stat-label">Observable Requests</div>
        <div class="text-xs text-gray-400 mt-1">30d tracked · <?= (int) ($dashboardData['observability_failed_30d'] ?? 0) ?> failed/blocked</div>
    </div>

    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-lightbulb"></i></div>
        <div class="ai-stat-value text-white"><?= (int) ($dashboardData['active_insights'] ?? 0) ?></div>
        <div class="ai-stat-label">Active Insights</div>
        <div class="text-xs text-gray-400 mt-1">Proactive suggestions</div>
    </div>

    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-book"></i></div>
        <div class="ai-stat-value text-white"><?= (int) ($dashboardData['knowledge_sources'] ?? 0) ?></div>
        <div class="ai-stat-label">Knowledge Sources</div>
        <div class="text-xs text-gray-400 mt-1">Indexed ERP documents</div>
    </div>

    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="ai-stat-value <?= (int)($dashboardData['open_failed_operations'] ?? 0) > 0 ? 'text-red-400' : 'text-white' ?>">
            <?= (int) ($dashboardData['open_failed_operations'] ?? 0) ?>
        </div>
        <div class="ai-stat-label">Failed Operations</div>
        <div class="text-xs text-gray-400 mt-1"><?= (int)($dashboardData['failed_operations_today'] ?? 0) ?> today</div>
    </div>

    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-dollar-sign"></i></div>
        <div class="ai-stat-value text-white">$<?= number_format((float) ($todayUsage['total_cost'] ?? 0), 4) ?></div>
        <div class="ai-stat-label">Usage Today</div>
        <div class="text-xs text-gray-400 mt-1"><?= number_format((int) ($todayUsage['requests'] ?? 0)) ?> reqs · <?= number_format((int) ($todayUsage['total_tokens'] ?? 0)) ?> tok</div>
    </div>

    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-file-alt"></i></div>
        <div class="ai-stat-value text-white"><?= number_format((int) ($dashboardData['document_total'] ?? 0)) ?></div>
        <div class="ai-stat-label">Documents</div>
        <div class="text-xs text-gray-400 mt-1"><?= number_format((int) ($dashboardData['document_ready'] ?? 0)) ?> ready · <?= number_format((int) ($dashboardData['document_needs_review'] ?? 0)) ?> review</div>
    </div>

    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-microphone"></i></div>
        <div class="ai-stat-value text-white"><?= number_format((int) ($dashboardData['voice_transcriptions'] ?? 0)) ?></div>
        <div class="ai-stat-label">Voice</div>
        <div class="text-xs text-gray-400 mt-1"><?= number_format((int) ($dashboardData['voice_usage_30d'] ?? 0)) ?> usage events · 30d</div>
    </div>

    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-plug"></i></div>
        <div class="ai-stat-value text-white"><?= number_format((int) ($dashboardData['integration_connections'] ?? 0)) ?></div>
        <div class="ai-stat-label">Integrations</div>
        <div class="text-xs text-gray-400 mt-1"><?= number_format((int) ($dashboardData['integration_failed_connections'] ?? 0)) ?> failed · <?= number_format((int) ($dashboardData['integration_sync_jobs'] ?? 0)) ?> sync jobs</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Component Health Status -->
    <div class="ai-content-card lg:col-span-2">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-white text-base flex items-center gap-2">
                <i class="fas fa-heartbeat text-sky-400"></i> System Subsystems Health
            </h3>
            <button onclick="runHealthCheckAll()" class="text-xs text-sky-400 hover:text-sky-300 font-medium">
                <i class="fas fa-sync-alt mr-1"></i> Refresh
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <?php foreach ($healthStatus as $h): ?>
            <div class="flex items-center justify-between p-3 rounded-lg bg-white/5 border border-white/10 text-sm">
                <div>
                    <span class="font-semibold text-gray-200"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $h['component']))) ?></span>
                    <p class="text-xs text-gray-400 truncate max-w-[200px]"><?= htmlspecialchars($h['message'] ?: 'Operational') ?></p>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $h['status'] === 'healthy' ? 'bg-emerald-500/20 text-emerald-400' : ($h['status'] === 'warning' ? 'bg-amber-500/20 text-amber-400' : ($h['status'] === 'degraded' ? 'bg-orange-500/20 text-orange-400' : 'bg-red-500/20 text-red-400')) ?>">
                    <?= ucfirst(htmlspecialchars($h['status'])) ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Kill Switch Quick Overview -->
    <div class="ai-content-card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-white text-base flex items-center gap-2">
                <i class="fas fa-power-off text-red-400"></i> Kill Switches
            </h3>
            <a href="?tab=safety" class="text-xs text-sky-400 hover:text-sky-300 font-medium">Manage All</a>
        </div>
        <div class="space-y-2.5">
            <?php 
            $quickSwitches = ['ai_chat', 'command_center', 'agents', 'automations', 'write_actions'];
            foreach ($quickSwitches as $qKey): 
                $isEn = $killSwitchMap[$qKey] ?? true;
                $label = $allKillSwitchFeatures[$qKey]['name'] ?? $qKey;
            ?>
            <div class="flex items-center justify-between p-2.5 rounded-lg bg-white/5 border border-white/10 text-sm">
                <span class="font-medium text-gray-200"><?= htmlspecialchars($label) ?></span>
                <button onclick="toggleKillSwitch('<?= $qKey ?>', <?= $isEn ? 'false' : 'true' ?>)" class="px-3 py-1 rounded-full text-xs font-bold transition <?= $isEn ? 'bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30' : 'bg-red-500/20 text-red-400 hover:bg-red-500/30' ?>">
                    <?= $isEn ? '✓ Active' : '🛑 Disabled' ?>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-4 pt-3 border-t border-white/10 text-xs text-gray-400 flex justify-between">
            <span>Active: <?= (int)($killSwitchSummary['enabled'] ?? 0) ?></span>
            <span>Disabled: <?= (int)($killSwitchSummary['disabled'] ?? 0) ?></span>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- ========================================================================= -->
<!-- TAB 2: PROVIDERS & MODELS                                                 -->
<!-- ========================================================================= -->
<?php if ($tab === 'providers'): ?>

<div class="space-y-6">
    <!-- Providers Section -->
    <div class="ai-content-card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold text-white text-base flex items-center gap-2">
                    <i class="fas fa-server text-indigo-400"></i> AI Providers & Health
                </h3>
                <p class="text-xs text-gray-400">Configured provider connections, authentication status, and recent failure rates</p>
            </div>
            <a href="<?= url('modules/ai/providers/create.php') ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">
                <i class="fas fa-plus mr-1"></i> Add Provider
            </a>
        </div>

        <div class="ai-table-wrapper">
            <table class="ai-data-table">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Active Models</th>
                        <th>Last Check / Auth</th>
                        <th>Recent Failures</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($providerHealth)): ?>
                    <tr><td colspan="7" class="py-6 text-center text-gray-400">No providers configured yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($providerHealth as $p): ?>
                    <tr>
                        <td class="font-semibold text-white">
                            <?= htmlspecialchars($p['provider_name']) ?>
                            <?php if (!empty($p['metadata']['is_default'])): ?>
                            <span class="ml-1 px-1.5 py-0.5 bg-sky-500/20 text-sky-400 text-xs rounded font-medium">Default</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-gray-300 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $p['provider_type'] ?? 'unknown')) ?></td>
                        <td>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $p['status'] === 'healthy' ? 'bg-emerald-500/20 text-emerald-400' : ($p['status'] === 'warning' ? 'bg-amber-500/20 text-amber-400' : 'bg-red-500/20 text-red-400') ?>">
                                <?= ucfirst(htmlspecialchars($p['status'])) ?>
                            </span>
                        </td>
                        <td class="text-gray-300">
                            <span class="font-bold"><?= (int)($p['metadata']['active_models'] ?? 0) ?></span> models
                            <span class="text-xs text-gray-400">(<?= (int)($p['metadata']['chat_models'] ?? 0) ?> chat, <?= (int)($p['metadata']['tool_models'] ?? 0) ?> tools)</span>
                        </td>
                        <td class="text-xs text-gray-400">
                            <?= htmlspecialchars($p['metadata']['last_connection_at'] ?? 'Never tested') ?>
                        </td>
                        <td>
                            <span class="font-medium <?= (int)($p['metadata']['recent_failures'] ?? 0) > 0 ? 'text-red-400 font-bold' : 'text-gray-300' ?>">
                                <?= (int)($p['metadata']['recent_failures'] ?? 0) ?>
                            </span>
                        </td>
                        <td class="text-right space-x-2">
                            <button onclick="checkProviderHealth(<?= $p['provider_id'] ?>)" class="text-sky-400 hover:text-sky-300 text-xs font-medium" title="Test Connection">
                                <i class="fas fa-vial"></i> Test
                            </button>
                            <a href="<?= url('modules/ai/providers/edit.php?id=' . $p['provider_id']) ?>" class="text-gray-400 hover:text-gray-300 text-xs" title="Edit Provider">
                                <i class="fas fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Model Capability Matrix -->
    <div class="ai-content-card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold text-white text-base flex items-center gap-2">
                    <i class="fas fa-cube text-purple-400"></i> Model Capability Matrix
                </h3>
                <p class="text-xs text-gray-400">Real verified capabilities from model configuration (Chat, Tool Calling, Structured Output, Embeddings, Agent Capable)</p>
            </div>
            <a href="<?= url('modules/ai/models/create.php') ?>" class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">
                <i class="fas fa-plus mr-1"></i> Add Model
            </a>
        </div>

        <div class="ai-table-wrapper">
            <table class="ai-data-table">
                <thead>
                    <tr>
                        <th>Model</th>
                        <th>Provider</th>
                        <th class="text-center">Chat</th>
                        <th class="text-center">Tools</th>
                        <th class="text-center">Structured</th>
                        <th class="text-center">Embedding</th>
                        <th class="text-center">Agent Capable</th>
                        <th>Context Window</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($modelCapabilityMatrix)): ?>
                    <tr><td colspan="8" class="py-6 text-center text-gray-400">No models active.</td></tr>
                    <?php else: ?>
                    <?php foreach ($modelCapabilityMatrix as $m): ?>
                    <tr>
                        <td class="font-semibold text-white">
                            <?= htmlspecialchars($m['model']) ?>
                            <?php if (!empty($m['default'])): ?>
                            <span class="ml-1 px-1.5 py-0.5 bg-sky-500/20 text-sky-400 text-xs rounded font-medium">Default</span>
                            <?php endif; ?>
                            <div class="text-xs text-gray-400 font-mono"><?= htmlspecialchars($m['model_id']) ?></div>
                        </td>
                        <td class="text-gray-300"><?= htmlspecialchars($m['provider']) ?></td>
                        <td class="text-center"><?= $m['chat'] ? '<span class="text-emerald-400 font-bold">✓</span>' : '<span class="text-gray-500">✗</span>' ?></td>
                        <td class="text-center"><?= $m['tools'] ? '<span class="text-emerald-400 font-bold">✓</span>' : '<span class="text-gray-500">✗</span>' ?></td>
                        <td class="text-center"><?= $m['structured'] ? '<span class="text-emerald-400 font-bold">✓</span>' : '<span class="text-gray-500">✗</span>' ?></td>
                        <td class="text-center"><?= $m['embedding'] ? '<span class="text-emerald-400 font-bold">✓</span>' : '<span class="text-gray-500">✗</span>' ?></td>
                        <td class="text-center"><?= $m['agent'] ? '<span class="text-emerald-400 font-bold">✓</span>' : '<span class="text-gray-500">✗</span>' ?></td>
                        <td class="text-gray-300 font-mono text-xs"><?= $m['context_window'] ? number_format($m['context_window']) . ' tok' : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Feature Dependency Mapping -->
    <div class="ai-content-card">
        <h3 class="font-bold text-white text-base mb-3 flex items-center gap-2">
            <i class="fas fa-project-diagram text-teal-400"></i> AI Feature Dependency Mapping
        </h3>
        <p class="text-xs text-gray-400 mb-4">Explains why an AI feature is available or unavailable based on underlying model capabilities and services</p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($featureDependencies as $fKey => $fDep): ?>
            <div class="p-4 rounded-lg border <?= $fDep['status'] === 'ready' ? 'border-emerald-500/30 bg-emerald-500/10' : 'border-amber-500/30 bg-amber-500/10' ?>">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-bold text-white"><?= htmlspecialchars($fDep['name']) ?></span>
                    <span class="px-2 py-0.5 rounded text-xs font-bold <?= $fDep['status'] === 'ready' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400' ?>">
                        <?= $fDep['status'] === 'ready' ? 'Ready' : 'Dependency Missing' ?>
                    </span>
                </div>
                <div class="text-xs text-gray-300 mb-2">
                    <strong>Requires:</strong> <?= htmlspecialchars(implode(', ', $fDep['requires'])) ?>
                </div>
                <?php if ($fDep['status'] !== 'ready'): ?>
                <div class="text-xs text-amber-400 font-medium">
                    <i class="fas fa-info-circle mr-1"></i> <?= htmlspecialchars(ucfirst($fDep['status'])) ?>
                </div>
                <?php else: ?>
                <div class="text-xs text-emerald-400">
                    <i class="fas fa-check-circle mr-1"></i> All prerequisites active
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- ========================================================================= -->
<!-- TAB 3: AI CAPABILITIES                                                   -->
<!-- ========================================================================= -->
<?php if ($tab === 'capabilities'): ?>

<!-- Feature Dependency Map -->
<div class="ai-content-card mb-6">
    <h3 class="font-bold text-white text-base mb-2 flex items-center gap-2">
        <i class="fas fa-project-diagram text-orange-400"></i> Feature Dependencies & Status
    </h3>
    <p class="text-xs text-gray-400 mb-4">Shows why a feature is available or unavailable, with actionable guidance</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach ($featureDependencies as $fKey => $fDep): ?>
        <div class="p-4 rounded-xl border <?= $fDep['enabled'] && $fDep['status'] === 'ready' ? 'border-emerald-500/30 bg-emerald-500/10' : 'border-white/10 bg-white/5' ?>">
            <div class="flex items-center justify-between mb-2">
                <span class="font-bold text-white text-sm"><?= htmlspecialchars($fDep['name']) ?></span>
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $fDep['enabled'] && $fDep['status'] === 'ready' ? 'bg-emerald-500/20 text-emerald-400' : ($fDep['enabled'] ? 'bg-amber-500/20 text-amber-400' : 'bg-red-500/20 text-red-400') ?>">
                    <?= $fDep['enabled'] && $fDep['status'] === 'ready' ? '✓ Ready' : ($fDep['enabled'] ? '⚠ Incomplete' : '🛑 Disabled') ?>
                </span>
            </div>
            <div class="text-xs text-gray-400 mb-1">Requires: <?= htmlspecialchars(implode(', ', $fDep['requires'])) ?></div>
            <div class="text-xs <?= $fDep['enabled'] && $fDep['status'] === 'ready' ? 'text-emerald-400' : 'text-gray-400' ?>">
                <?= htmlspecialchars($fDep['explanation'] ?? '') ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Permission Governance -->
<div class="ai-content-card mb-6">
    <h3 class="font-bold text-white text-base mb-4 flex items-center gap-2">
        <i class="fas fa-user-lock text-sky-400"></i> Permission Governance
    </h3>

    <div class="ai-table-wrapper">
        <table class="ai-data-table">
            <thead>
                <tr>
                    <th>Capability</th>
                    <th>Required Permission</th>
                    <th>Seeded</th>
                    <th class="text-center">Roles</th>
                    <th class="text-center">Users</th>
                    <th class="text-center">Kill Switch</th>
                    <th class="text-center">Ready</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($permissionMatrix as $pm): ?>
                <tr>
                    <td class="font-semibold text-white"><?= htmlspecialchars($pm['capability']) ?></td>
                    <td class="font-mono text-xs text-sky-400"><?= htmlspecialchars($pm['permission']) ?></td>
                    <td>
                        <span class="px-2 py-0.5 rounded text-xs font-semibold <?= !empty($pm['seeded']) ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400' ?>">
                            <?= !empty($pm['seeded']) ? '✓ Yes' : '⚠ No' ?>
                        </span>
                    </td>
                    <td class="text-center text-gray-300"><?= (int)$pm['assigned_roles'] ?></td>
                    <td class="text-center text-gray-300"><?= (int)$pm['assigned_users'] ?></td>
                    <td class="text-center">
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= !empty($pm['enabled']) ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400' ?>">
                            <?= !empty($pm['enabled']) ? 'Active' : 'Disabled' ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= !empty($pm['ready']) ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400' ?>">
                            <?= !empty($pm['ready']) ? '✓' : '⚠' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Capability Access Matrix -->
<div class="ai-content-card mb-6">
    <h3 class="font-bold text-white text-base mb-2 flex items-center gap-2">
        <i class="fas fa-puzzle-piece text-purple-400"></i> AI Capability Access & Readiness Matrix
    </h3>
    <p class="text-xs text-gray-400 mb-4">Complete breakdown of all AI capabilities, required permissions, underlying providers, and current status</p>

    <div class="ai-table-wrapper">
        <table class="ai-data-table">
            <thead>
                <tr>
                    <th>Capability</th>
                    <th>Required Permission</th>
                    <th>Provider / Model Requirement</th>
                    <th class="text-center">Kill Switch</th>
                    <th class="text-center">System Readiness</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($capabilityMatrix as $cap): ?>
                <tr>
                    <td class="font-semibold text-white"><?= htmlspecialchars($cap['capability']) ?></td>
                    <td class="font-mono text-xs text-sky-400"><?= htmlspecialchars($cap['permission']) ?></td>
                    <td class="text-gray-300 text-xs"><?= htmlspecialchars($cap['provider']) ?></td>
                    <td class="text-center">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $cap['enabled'] ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400' ?>">
                            <?= $cap['enabled'] ? 'Active' : 'Disabled' ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $cap['ready'] ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400' ?>">
                            <?= $cap['ready'] ? '✓ Configured' : '⚠ Missing Config' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<!-- ========================================================================= -->
<!-- TAB 4: USAGE & COST CONTROLS                                             -->
<!-- ========================================================================= -->
<?php if ($tab === 'usage'): ?>

<div class="space-y-6">
    <!-- Usage Metrics Overview -->
    <div class="ai-stat-grid">
        <div class="ai-stat-card">
            <div class="ai-stat-icon-wrap"><i class="fas fa-chart-bar"></i></div>
            <div class="ai-stat-value text-white"><?= number_format((int) ($usageStats['total'] ?? 0)) ?></div>
            <div class="ai-stat-label">Total Requests</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-icon-wrap"><i class="fas fa-check"></i></div>
            <div class="ai-stat-value text-emerald-400"><?= number_format((int) ($usageStats['successful'] ?? 0)) ?></div>
            <div class="ai-stat-label">Successful</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-icon-wrap"><i class="fas fa-times"></i></div>
            <div class="ai-stat-value text-red-400"><?= number_format((int) ($usageStats['failed'] ?? 0)) ?></div>
            <div class="ai-stat-label">Failed</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-icon-wrap"><i class="fas fa-coins"></i></div>
            <div class="ai-stat-value text-purple-400"><?= number_format((int)(($usageStats['total_input_tokens'] ?? 0) + ($usageStats['total_output_tokens'] ?? 0))) ?></div>
            <div class="ai-stat-label">Total Tokens</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-icon-wrap"><i class="fas fa-dollar-sign"></i></div>
            <div class="ai-stat-value text-emerald-400">$<?= number_format((float) ($usageStats['total_cost'] ?? 0), 4) ?></div>
            <div class="ai-stat-label">Total Cost</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-icon-wrap"><i class="fas fa-clock"></i></div>
            <div class="ai-stat-value text-sky-400"><?= number_format((float) ($usageStats['avg_duration'] ?? 0), 0) ?> ms</div>
            <div class="ai-stat-label">Avg Latency</div>
        </div>
    </div>

    <!-- Cost Controls & Budgets -->
    <div class="ai-content-card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold text-white text-base flex items-center gap-2">
                    <i class="fas fa-hand-holding-usd text-emerald-400"></i> AI Cost Controls & Budgets
                </h3>
                <p class="text-xs text-gray-400">Configurable budget limits enforced server-side. Requests are blocked when budget is reached.</p>
            </div>
            <button onclick="showAddCostControlModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">
                <i class="fas fa-plus mr-1"></i> Add Cost Limit
            </button>
        </div>

        <div class="ai-table-wrapper">
            <table class="ai-data-table">
                <thead>
                    <tr>
                        <th>Control Type</th>
                        <th>Target</th>
                        <th>Max Budget</th>
                        <th>Current Usage</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($costControls)): ?>
                    <tr><td colspan="7" class="py-6 text-center text-gray-400">No cost limits configured. Default limits apply.</td></tr>
                    <?php else: ?>
                    <?php foreach ($costControls as $cc): ?>
                    <tr>
                        <td class="font-semibold text-white capitalize"><?= htmlspecialchars(str_replace('_', ' ', $cc['control_type'])) ?></td>
                        <td class="text-gray-300 text-xs"><?= $cc['target_id'] ? '#' . (int)$cc['target_id'] : 'Global / Company' ?></td>
                        <td class="font-bold text-white">$<?= number_format((float)$cc['max_cost'], 2) ?></td>
                        <td class="text-gray-300">$<?= number_format((float)($cc['used_cost'] ?? 0), 4) ?></td>
                        <td class="w-48">
                            <div class="w-full bg-white/10 rounded-full h-2">
                                <div class="h-2 rounded-full <?= (float)($cc['percent_used'] ?? 0) > 90 ? 'bg-red-500' : ((float)($cc['percent_used'] ?? 0) > 70 ? 'bg-amber-500' : 'bg-emerald-500') ?>" style="width: <?= min(100, (float)($cc['percent_used'] ?? 0)) ?>%"></div>
                            </div>
                            <span class="text-xs text-gray-400 mt-0.5"><?= number_format((float)($cc['percent_used'] ?? 0), 1) ?>% used</span>
                        </td>
                        <td>
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= !empty($cc['enabled']) ? 'bg-emerald-500/20 text-emerald-400' : 'bg-white/10 text-gray-400' ?>">
                                <?= !empty($cc['enabled']) ? 'Active' : 'Disabled' ?>
                            </span>
                        </td>
                        <td class="text-right space-x-2">
                            <button onclick="toggleCostControl(<?= $cc['id'] ?>, <?= !empty($cc['enabled']) ? 'false' : 'true' ?>)" class="text-xs text-gray-300 hover:text-sky-400">
                                <?= !empty($cc['enabled']) ? 'Disable' : 'Enable' ?>
                            </button>
                            <button onclick="deleteCostControl(<?= $cc['id'] ?>)" class="text-xs text-red-400 hover:text-red-300">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Rate Limits Table -->
    <div class="ai-content-card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold text-white text-base flex items-center gap-2">
                    <i class="fas fa-tachometer-alt text-sky-400"></i> AI Rate Limits
                </h3>
                <p class="text-xs text-gray-400">Server-side rate limits per minute / per hour to protect upstream APIs and prevent abuse</p>
            </div>
            <button onclick="showAddRateLimitModal()" class="bg-sky-600 hover:bg-sky-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">
                <i class="fas fa-plus mr-1"></i> Add Rate Limit
            </button>
        </div>

        <div class="ai-table-wrapper">
            <table class="ai-data-table">
                <thead>
                    <tr>
                        <th>Limit Key</th>
                        <th>Window</th>
                        <th>Feature</th>
                        <th>Max Requests</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rateLimits as $rl): ?>
                    <tr>
                        <td class="font-mono text-xs text-white"><?= htmlspecialchars($rl['limit_key']) ?></td>
                        <td class="text-gray-300 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $rl['limit_type'])) ?></td>
                        <td class="text-gray-300"><?= htmlspecialchars($rl['feature'] ?: 'All Features (*)') ?></td>
                        <td class="font-bold text-white"><?= number_format((int)$rl['max_requests']) ?></td>
                        <td>
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= !empty($rl['enabled']) ? 'bg-emerald-500/20 text-emerald-400' : 'bg-white/10 text-gray-400' ?>">
                                <?= !empty($rl['enabled']) ? 'Active' : 'Disabled' ?>
                            </span>
                        </td>
                        <td class="text-right space-x-2">
                            <button onclick="toggleRateLimit(<?= $rl['id'] ?>, <?= !empty($rl['enabled']) ? 'false' : 'true' ?>)" class="text-xs text-gray-300 hover:text-sky-400">
                                <?= !empty($rl['enabled']) ? 'Disable' : 'Enable' ?>
                            </button>
                            <button onclick="deleteRateLimit(<?= $rl['id'] ?>)" class="text-xs text-red-400 hover:text-red-300">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- ========================================================================= -->
<!-- TAB 5: SAFETY & KILL SWITCHES                                             -->
<!-- ========================================================================= -->
<?php if ($tab === 'safety'): ?>

<div class="space-y-6">
    <!-- Emergency Kill Switches -->
    <div class="ai-content-card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold text-white text-base flex items-center gap-2">
                    <i class="fas fa-power-off text-red-400"></i> Emergency Kill Switches
                </h3>
                <p class="text-xs text-gray-400">Instantly shut down individual AI capabilities server-side in emergency situations. Audited with reason.</p>
            </div>
            <span class="text-xs text-gray-400">Total: <?= count($allKillSwitchFeatures) ?> features</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($allKillSwitchFeatures as $switchKey => $sw): 
                $isEn = $killSwitchMap[$switchKey] ?? true;
            ?>
            <div class="p-4 rounded-xl border <?= $isEn ? 'border-white/10 bg-white/5' : 'border-red-500/30 bg-red-500/10' ?> flex items-center justify-between shadow-sm">
                <div>
                    <span class="font-bold text-white text-sm block"><?= htmlspecialchars($sw['name']) ?></span>
                    <span class="text-xs text-gray-400"><?= htmlspecialchars($sw['description']) ?></span>
                    <?php if (!$isEn): ?>
                    <span class="block text-xs font-semibold text-red-400 mt-1">🛑 EMERGENCY DISABLED</span>
                    <?php endif; ?>
                </div>
                <div class="ml-3">
                    <button onclick="promptKillSwitchToggle('<?= $switchKey ?>', '<?= htmlspecialchars($sw['name']) ?>', <?= $isEn ? 'false' : 'true' ?>)" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition <?= $isEn ? 'bg-red-500/20 text-red-400 hover:bg-red-500/30 border border-red-500/30' : 'bg-emerald-600 text-white hover:bg-emerald-700' ?>">
                        <?= $isEn ? 'Shut Down' : 'Restore' ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Safety Policy Hierarchy -->
    <div class="ai-content-card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold text-white text-base flex items-center gap-2">
                    <i class="fas fa-shield-alt text-sky-400"></i> Safety Policy Hierarchy
                </h3>
                <p class="text-xs text-gray-400">Precedence: System Hard Limit &gt; Company Policy &gt; Feature Policy &gt; User Request</p>
            </div>
        </div>

        <div class="ai-table-wrapper">
            <table class="ai-data-table">
                <thead>
                    <tr>
                        <th>Policy Name</th>
                        <th>Key</th>
                        <th>Configured Limit</th>
                        <th>Effective Limit</th>
                        <th>Scope</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($safetyPolicies as $sp): 
                        $effective = AIGovernanceService::getEffectiveLimit($db, $sp['policy_key']);
                    ?>
                    <tr>
                        <td class="font-semibold text-white">
                            <?= htmlspecialchars($sp['policy_name']) ?>
                            <div class="text-xs text-gray-400 font-normal"><?= htmlspecialchars($sp['description']) ?></div>
                        </td>
                        <td class="font-mono text-xs text-gray-300"><?= htmlspecialchars($sp['policy_key']) ?></td>
                        <td class="font-bold text-white"><?= number_format((int)$sp['max_value']) ?></td>
                        <td class="font-bold text-emerald-400"><?= number_format((int)$effective) ?></td>
                        <td class="text-xs text-gray-300 uppercase"><?= htmlspecialchars($sp['scope'] ?? 'system') ?></td>
                        <td class="text-right">
                            <button onclick="editSafetyPolicy(<?= $sp['id'] ?>, '<?= htmlspecialchars($sp['policy_name']) ?>', <?= (int)$sp['max_value'] ?>)" class="text-xs text-sky-400 hover:text-sky-300 font-medium">
                                Edit Limit
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Graceful Degradation Guide -->
    <div class="ai-content-card">
        <h3 class="font-bold text-white text-base mb-4 flex items-center gap-2">
            <i class="fas fa-life-ring text-teal-400"></i> Graceful Degradation Guide
        </h3>
        <p class="text-xs text-gray-400 mb-4">Expected behavior when AI components fail or are disabled</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 rounded-lg bg-white/5 border border-white/10">
                <div class="font-semibold text-sm text-white mb-1">🤖 Chat Provider Unavailable</div>
                <div class="text-xs text-gray-400">AI conversation unavailable. Normal ERP operations continue unaffected.</div>
            </div>
            <div class="p-4 rounded-lg bg-white/5 border border-white/10">
                <div class="font-semibold text-sm text-white mb-1">📊 Embedding Provider Unavailable</div>
                <div class="text-xs text-gray-400">Existing indexed knowledge may still be searchable if query embeddings are available. New indexing is unavailable.</div>
            </div>
            <div class="p-4 rounded-lg bg-white/5 border border-white/10">
                <div class="font-semibold text-sm text-white mb-1">💡 AI Narrative Unavailable</div>
                <div class="text-xs text-gray-400">Rule-based Insights continue using deterministic summaries without AI-generated narratives.</div>
            </div>
            <div class="p-4 rounded-lg bg-white/5 border border-white/10">
                <div class="font-semibold text-sm text-white mb-1">🤖 Agent Model Unavailable</div>
                <div class="text-xs text-gray-400">Agent cannot start. Normal read-only ERP functions and AI chat remain available.</div>
            </div>
            <div class="p-4 rounded-lg bg-white/5 border border-white/10">
                <div class="font-semibold text-sm text-white mb-1">⏰ Scheduler Unavailable</div>
                <div class="text-xs text-gray-400">Scheduled automations pause. Event-triggered automations and manual operations continue.</div>
            </div>
            <div class="p-4 rounded-lg bg-white/5 border border-white/10">
                <div class="font-semibold text-sm text-white mb-1">🔌 AI Module Fully Disabled</div>
                <div class="text-xs text-gray-400">The ERP itself remains fully operational. All non-AI functionality is unaffected.</div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- ========================================================================= -->
<!-- TAB 6: FAILED OPERATIONS & RETRIES                                       -->
<!-- ========================================================================= -->
<?php if ($tab === 'operations'): ?>

<div class="ai-content-card">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="font-bold text-white text-base flex items-center gap-2">
                <i class="fas fa-exclamation-triangle text-red-400"></i> Failed Operations & Error Registry
            </h3>
            <p class="text-xs text-gray-400">Review AI errors, diagnostic details, and trigger safe retries for idempotent operations</p>
        </div>
    </div>

    <div class="ai-table-wrapper">
        <table class="ai-data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Feature / Operation</th>
                    <th>Category</th>
                    <th>Error Message</th>
                    <th>Retries</th>
                    <th>Status</th>
                    <th>Time</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($failedOps)): ?>
                <tr><td colspan="8" class="py-8 text-center text-gray-400"><i class="fas fa-check-circle text-emerald-400 text-2xl block mb-2"></i>No failed operations recorded. All operations healthy.</td></tr>
                <?php else: ?>
                <?php foreach ($failedOps as $fo): ?>
                <tr>
                    <td class="font-mono text-xs text-gray-400">#<?= $fo['id'] ?></td>
                    <td class="font-semibold text-white">
                        <?= htmlspecialchars($fo['feature'] ?? 'unknown') ?>
                        <div class="text-xs text-gray-400"><?= htmlspecialchars($fo['operation_type'] ?? '-') ?></div>
                    </td>
                    <td>
                        <span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-500/20 text-red-400 font-mono">
                            <?= htmlspecialchars($fo['error_category'] ?? 'unknown') ?>
                        </span>
                    </td>
                    <td class="text-xs text-gray-300 max-w-xs truncate" title="<?= htmlspecialchars($fo['error_message']) ?>">
                        <?= htmlspecialchars($fo['error_message']) ?>
                    </td>
                    <td class="text-gray-300 font-mono"><?= (int)($fo['retry_count'] ?? 0) ?></td>
                    <td>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $fo['resolution_status'] === 'resolved' ? 'bg-emerald-500/20 text-emerald-400' : ($fo['resolution_status'] === 'investigating' ? 'bg-amber-500/20 text-amber-400' : 'bg-red-500/20 text-red-400') ?>">
                            <?= ucfirst(htmlspecialchars($fo['resolution_status'] ?? 'open')) ?>
                        </span>
                    </td>
                    <td class="text-xs text-gray-400 whitespace-nowrap"><?= htmlspecialchars($fo['created_at']) ?></td>
                    <td class="text-right space-x-2 whitespace-nowrap">
                        <?php if (!empty($fo['can_retry'])): ?>
                        <button onclick="retryOperation(<?= $fo['id'] ?>)" class="text-xs bg-sky-600 hover:bg-sky-700 text-white px-2.5 py-1 rounded font-medium shadow-sm">
                            <i class="fas fa-redo mr-1"></i> Retry
                        </button>
                        <?php endif; ?>
                        <?php if ($fo['resolution_status'] !== 'resolved'): ?>
                        <button onclick="resolveOperation(<?= $fo['id'] ?>)" class="text-xs text-gray-300 hover:text-emerald-400">
                            <i class="fas fa-check"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<!-- ========================================================================= -->
<!-- TAB 7: PRIVACY, RETENTION & AUDIT                                         -->
<!-- ========================================================================= -->
<?php if ($tab === 'privacy'): ?>

<div class="space-y-6">
    <!-- Sensitive Field Masking Policies -->
    <div class="ai-content-card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold text-white text-base flex items-center gap-2">
                    <i class="fas fa-user-secret text-indigo-400"></i> Sensitive Data Masking Policies
                </h3>
                <p class="text-xs text-gray-400">Protects sensitive ERP data by masking passwords, credentials, tokens, and PII before passing to AI providers</p>
            </div>
            <button onclick="showAddMaskingModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">
                <i class="fas fa-plus mr-1"></i> Add Masking Rule
            </button>
        </div>

        <div class="ai-table-wrapper">
            <table class="ai-data-table">
                <thead>
                    <tr>
                        <th>Field Pattern</th>
                        <th>Masking Type</th>
                        <th>Module Scope</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($maskingPolicies as $mp): ?>
                    <tr>
                        <td class="font-mono text-xs text-white font-bold"><?= htmlspecialchars($mp['field_pattern']) ?></td>
                        <td>
                            <span class="px-2 py-0.5 rounded text-xs font-semibold <?= $mp['masking_type'] === 'full' ? 'bg-red-500/20 text-red-400' : ($mp['masking_type'] === 'partial' ? 'bg-amber-500/20 text-amber-400' : 'bg-white/10 text-gray-300') ?>">
                                <?= strtoupper(htmlspecialchars($mp['masking_type'])) ?>
                            </span>
                        </td>
                        <td class="text-gray-300 text-xs"><?= htmlspecialchars($mp['module'] ?: 'Global (All Modules)') ?></td>
                        <td class="text-xs text-gray-400"><?= htmlspecialchars($mp['description']) ?></td>
                        <td>
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= !empty($mp['enabled']) ? 'bg-emerald-500/20 text-emerald-400' : 'bg-white/10 text-gray-400' ?>">
                                <?= !empty($mp['enabled']) ? 'Active' : 'Disabled' ?>
                            </span>
                        </td>
                        <td class="text-right">
                            <button onclick="deleteMaskingPolicy(<?= $mp['id'] ?>)" class="text-xs text-red-400 hover:text-red-300">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Retention Policies -->
    <div class="ai-content-card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold text-white text-base flex items-center gap-2">
                    <i class="fas fa-calendar-times text-amber-400"></i> AI Data Retention Policies
                </h3>
                <p class="text-xs text-gray-400">Configures retention days for conversations, commands, memory, usage, and error logs</p>
            </div>
            <button onclick="runRetentionCleanup()" class="bg-amber-600 hover:bg-amber-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">
                <i class="fas fa-broom mr-1"></i> Run Cleanup Now
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($retentionPolicies as $rp): ?>
            <div class="p-4 rounded-xl border border-white/10 bg-white/5 shadow-sm flex flex-col justify-between">
                <div>
                    <span class="font-bold text-white text-sm capitalize block mb-1">
                        <?= htmlspecialchars(str_replace('_', ' ', $rp['data_type'])) ?>
                    </span>
                    <span class="text-2xl font-bold text-indigo-400"><?= (int)$rp['retention_days'] ?></span>
                    <span class="text-xs text-gray-400">days retention</span>
                </div>
                <div class="mt-3 pt-3 border-t border-white/10 flex items-center justify-between text-xs text-gray-400">
                    <span>Last: <?= htmlspecialchars($rp['last_cleanup_at'] ? date('M j, H:i', strtotime($rp['last_cleanup_at'])) : 'Never') ?></span>
                    <button onclick="editRetentionPolicy(<?= $rp['id'] ?>, '<?= htmlspecialchars($rp['data_type']) ?>', <?= (int)$rp['retention_days'] ?>)" class="text-sky-400 hover:text-sky-300 font-medium">
                        Edit
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- AI Configuration Audit Timeline -->
    <div class="ai-content-card">
        <h3 class="font-bold text-white text-base mb-3 flex items-center gap-2">
            <i class="fas fa-history text-purple-400"></i> AI Configuration Audit Timeline
        </h3>
        <div class="ai-table-wrapper">
            <table class="ai-data-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Config Type</th>
                        <th>Action</th>
                        <th>User</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($auditLogs)): ?>
                    <tr><td colspan="5" class="py-6 text-center text-gray-400">No configuration changes audited yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($auditLogs as $al): ?>
                    <tr>
                        <td class="py-2.5 text-xs text-gray-400 whitespace-nowrap"><?= htmlspecialchars($al['created_at']) ?></td>
                        <td class="py-2.5 font-semibold text-white text-xs capitalize"><?= htmlspecialchars($al['config_type']) ?></td>
                        <td class="py-2.5">
                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-white/10 text-gray-300">
                                <?= htmlspecialchars($al['action']) ?>
                            </span>
                        </td>
                        <td class="py-2.5 text-xs text-gray-300"><?= htmlspecialchars($al['user_name'] ?? 'System') ?></td>
                        <td class="py-2.5 text-xs text-gray-300 font-mono truncate max-w-sm" title="<?= htmlspecialchars($al['new_value']) ?>">
                            <?= htmlspecialchars(substr($al['new_value'], 0, 100)) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- ========================================================================= -->
<!-- TAB 8: DIAGNOSTICS & SCHEDULER                                           -->
<!-- ========================================================================= -->
<?php if ($tab === 'diagnostics'): ?>

<div class="space-y-6">
    <!-- Scheduler Health Monitor -->
    <div class="ai-content-card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold text-white text-base flex items-center gap-2">
                    <i class="fas fa-clock text-sky-400"></i> AI Automation Scheduler Health
                </h3>
                <p class="text-xs text-gray-400">Monitors scheduled jobs, heartbeat cycle intervals, and due automation queues</p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-bold <?= ($schedulerStatus['status'] ?? '') === 'healthy' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400' ?>">
                <?= ucfirst(htmlspecialchars($schedulerStatus['status'] ?? 'unknown')) ?>
            </span>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white/5 p-4 rounded-lg border border-white/10">
                <span class="text-xs text-gray-400 uppercase font-semibold">Last Heartbeat</span>
                <div class="text-sm font-bold text-white mt-1"><?= htmlspecialchars($schedulerStatus['last_heartbeat_at'] ?? 'None') ?></div>
                <span class="text-xs text-gray-400"><?= isset($schedulerStatus['minutes_since_heartbeat']) ? $schedulerStatus['minutes_since_heartbeat'] . ' min ago' : '' ?></span>
            </div>
            <div class="bg-white/5 p-4 rounded-lg border border-white/10">
                <span class="text-xs text-gray-400 uppercase font-semibold">Last Success Cycle</span>
                <div class="text-sm font-bold text-emerald-400 mt-1"><?= htmlspecialchars($schedulerStatus['last_successful_cycle_at'] ?? 'None') ?></div>
            </div>
            <div class="bg-white/5 p-4 rounded-lg border border-white/10">
                <span class="text-xs text-gray-400 uppercase font-semibold">Due Jobs In Queue</span>
                <div class="text-xl font-bold text-white mt-1"><?= (int)($schedulerStatus['due_jobs'] ?? 0) ?></div>
            </div>
            <div class="bg-white/5 p-4 rounded-lg border border-white/10">
                <span class="text-xs text-gray-400 uppercase font-semibold">Delayed Jobs</span>
                <div class="text-xl font-bold <?= (int)($schedulerStatus['delayed_jobs'] ?? 0) > 0 ? 'text-red-400 font-bold' : 'text-white' ?> mt-1">
                    <?= (int)($schedulerStatus['delayed_jobs'] ?? 0) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Diagnostics Checklist -->
    <div class="ai-content-card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold text-white text-base flex items-center gap-2">
                    <i class="fas fa-stethoscope text-indigo-400"></i> Full System Diagnostic Checks
                </h3>
                <p class="text-xs text-gray-400">Runs non-destructive dry-run checks across all AI database tables, registries, credentials, and engines</p>
            </div>
            <button onclick="runDiagnosticsModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3.5 py-1.5 rounded-lg text-xs font-medium">
                <i class="fas fa-play mr-1"></i> Run Diagnostics
            </button>
        </div>

        <div id="diagnosticsResultsContainer">
            <p class="text-sm text-gray-400 italic">Click "Run Diagnostics" to execute a live full-system check.</p>
        </div>
    </div>

    <!-- Configuration Validation -->
    <div class="ai-content-card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold text-white text-base flex items-center gap-2">
                    <i class="fas fa-check-double text-emerald-400"></i> Configuration Validation
                </h3>
                <p class="text-xs text-gray-400">Validates that feature dependencies are met before enabling critical AI capabilities</p>
            </div>
            <button onclick="runConfigValidation()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-1.5 rounded-lg text-xs font-medium">
                <i class="fas fa-check mr-1"></i> Validate All
            </button>
        </div>

        <?php
        $featuresToValidate = ['agents', 'knowledge', 'scheduled_automations'];
        $validationResults = [];
        foreach ($featuresToValidate as $fv) {
            $validationResults[$fv] = AIManagementService::validateFeatureConfig($db, $fv);
        }
        ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($validationResults as $fvFeature => $fvResult): ?>
            <div class="p-4 rounded-lg border <?= $fvResult['valid'] ? 'border-emerald-500/30 bg-emerald-500/10' : 'border-red-500/30 bg-red-500/10' ?>">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-bold text-sm text-white capitalize"><?= htmlspecialchars(str_replace('_', ' ', $fvFeature)) ?></span>
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $fvResult['valid'] ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400' ?>">
                        <?= $fvResult['valid'] ? '✓ Ready' : '⚠ Issues' ?>
                    </span>
                </div>
                <?php if (!empty($fvResult['issues'])): ?>
                <ul class="text-xs text-red-400 list-disc list-inside space-y-0.5">
                    <?php foreach ($fvResult['issues'] as $issue): ?>
                    <li><?= htmlspecialchars($issue) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="text-xs text-emerald-400">All dependencies satisfied.</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php endif; ?>

</div>

<script>
const CSRF = '<?= csrfToken() ?>';
const BASE_URL = '<?= url('') ?>';

// Health check for all subsystems
async function runHealthCheckAll() {
    try {
        const resp = await fetch(`${BASE_URL}/modules/ai/api/management.php?action=health_check_all`);
        const data = await resp.json();
        if (data.success) {
            alert('✓ ' + data.message);
            location.reload();
        } else {
            alert('✗ Health check failed: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Network error executing health check.');
    }
}

// Single provider check
async function checkProviderHealth(providerId) {
    try {
        const resp = await fetch(`${BASE_URL}/modules/ai/api/management.php?action=health_check_provider&provider_id=${providerId}`);
        const data = await resp.json();
        if (data.success) {
            alert(`✓ ${data.provider_name}: ${data.message}`);
            location.reload();
        } else {
            alert(`✗ Check failed for provider #${providerId}: ${data.message || data.error}`);
        }
    } catch (e) {
        alert('Network error testing provider.');
    }
}

// Kill Switch Toggle with Reason Prompt
function promptKillSwitchToggle(switchKey, switchName, targetEnable) {
    const actionName = targetEnable ? 'RESTORE / ENABLE' : 'SHUT DOWN / DISABLE';
    const reason = prompt(`Confirm action: ${actionName} "${switchName}"?\nEnter reason for audit trail (optional):`);
    if (reason === null) return; // User cancelled

    toggleKillSwitch(switchKey, targetEnable, reason);
}

async function toggleKillSwitch(switchKey, enabled, reason = '') {
    try {
        const resp = await fetch(`${BASE_URL}/modules/ai/api/management.php?action=toggle_kill_switch`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ switch_key: switchKey, enabled: enabled, reason: reason })
        });
        const data = await resp.json();
        if (data.success) {
            alert('✓ ' + data.message);
            location.reload();
        } else {
            alert('✗ Failed: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Network error updating kill switch.');
    }
}

// Run Diagnostics
async function runDiagnosticsModal() {
    const container = document.getElementById('diagnosticsResultsContainer');
    if (container) {
        container.innerHTML = '<div class="py-6 text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i> Running comprehensive dry-run diagnostics...</div>';
    }

    try {
        const resp = await fetch(`${BASE_URL}/modules/ai/api/management.php?action=run_diagnostics`);
        const data = await resp.json();

        if (data.success && container) {
            let html = `<div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">`;
            data.diagnostics.forEach(d => {
                const badgeClass = d.status === 'healthy' ? 'bg-emerald-500/20 text-emerald-400' : (d.status === 'warning' ? 'bg-amber-500/20 text-amber-400' : 'bg-red-500/20 text-red-400');
                html += `<div class="p-3 rounded-lg border border-white/10 bg-white/5 flex items-center justify-between text-sm">
                    <div>
                        <span class="font-bold text-white">${escapeHtml(d.name)}</span>
                        <div class="text-xs text-gray-400">${escapeHtml(d.message)}</div>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold ${badgeClass}">${escapeHtml(d.status.toUpperCase())}</span>
                </div>`;
            });
            html += `</div>`;
            container.innerHTML = html;
        } else if (data.success) {
            alert('✓ Diagnostics completed with ' + data.diagnostics.length + ' checks.');
        } else {
            alert('✗ Diagnostics failed: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Network error running diagnostics.');
    }
}

// Retention Cleanup
async function runRetentionCleanup() {
    if (!confirm('Run data retention cleanup now based on configured policies?')) return;

    try {
        const resp = await fetch(`${BASE_URL}/modules/ai/api/management.php?action=run_retention_cleanup`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({})
        });
        const data = await resp.json();
        if (data.success) {
            alert('✓ Retention cleanup executed successfully.');
            location.reload();
        } else {
            alert('✗ Retention cleanup failed: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Network error executing cleanup.');
    }
}

// Retry Failed Operation
async function retryOperation(errorId) {
    if (!confirm(`Attempt safe retry for failed operation #${errorId}?`)) return;

    try {
        const resp = await fetch(`${BASE_URL}/modules/ai/api/management.php?action=retry_failed_operation`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ error_id: errorId })
        });
        const data = await resp.json();
        if (data.success) {
            alert('✓ Retry succeeded!');
            location.reload();
        } else {
            alert('✗ Retry failed: ' + (data.error || data.message || 'Error occurred'));
        }
    } catch (e) {
        alert('Network error retrying operation.');
    }
}

// Resolve Failed Operation
async function resolveOperation(errorId) {
    try {
        const resp = await fetch(`${BASE_URL}/modules/ai/api/management.php?action=resolve_failed_operation`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ error_id: errorId, status: 'resolved' })
        });
        const data = await resp.json();
        if (data.success) location.reload();
    } catch (e) {}
}

// Cost Controls Helpers
function showAddCostControlModal() {
    const type = prompt("Enter Control Type:\nper_user_daily, per_company_daily, monthly_company, per_agent_run, per_automation_run");
    if (!type) return;
    const maxCost = prompt("Enter Maximum Cost ($ USD):", "10.00");
    if (!maxCost) return;

    fetch(`${BASE_URL}/modules/ai/api/management.php?action=save_cost_control`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ control_type: type.trim(), max_cost: parseFloat(maxCost) })
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
        else alert('Error: ' + (d.error || 'Failed to save cost control'));
    });
}

function toggleCostControl(id, enabled) {
    fetch(`${BASE_URL}/modules/ai/api/management.php?action=toggle_cost_control`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ id: id, enabled: enabled })
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
    });
}

function deleteCostControl(id) {
    if (!confirm('Delete this cost control limit?')) return;
    fetch(`${BASE_URL}/modules/ai/api/management.php?action=delete_cost_control`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ id: id })
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
    });
}

// Rate Limits Helpers
function showAddRateLimitModal() {
    const key = prompt("Enter unique Limit Key (e.g., custom_feature_minute):");
    if (!key) return;
    const reqs = prompt("Enter Max Requests per window:", "60");
    if (!reqs) return;

    fetch(`${BASE_URL}/modules/ai/api/management.php?action=save_rate_limit`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ limit_key: key.trim(), max_requests: parseInt(reqs), limit_type: 'per_minute' })
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
        else alert('Error: ' + (d.error || 'Failed to save rate limit'));
    });
}

function toggleRateLimit(id, enabled) {
    fetch(`${BASE_URL}/modules/ai/api/management.php?action=toggle_rate_limit`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ id: id, enabled: enabled })
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
    });
}

function deleteRateLimit(id) {
    if (!confirm('Delete this rate limit?')) return;
    fetch(`${BASE_URL}/modules/ai/api/management.php?action=delete_rate_limit`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ id: id })
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
    });
}

// Safety Policy Helper
function editSafetyPolicy(id, name, currentMax) {
    const newMax = prompt(`Update maximum limit for "${name}":`, currentMax);
    if (newMax === null) return;

    fetch(`${BASE_URL}/modules/ai/api/management.php?action=update_safety_policy`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ id: id, max_value: parseInt(newMax) })
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
        else alert('Error: ' + (d.error || 'Failed to update policy'));
    });
}

// Masking Helpers
function showAddMaskingModal() {
    const pattern = prompt("Enter Field Pattern to mask (e.g., credit_card, tax_id, ssn, pin):");
    if (!pattern) return;
    const type = prompt("Enter Masking Type:\nfull (replace with [masked]), partial (show first/last chars), exclude (omit field)", "full");
    if (!type) return;

    fetch(`${BASE_URL}/modules/ai/api/management.php?action=save_masking_policy`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ field_pattern: pattern.trim(), masking_type: type.trim(), description: 'Custom masking rule' })
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
        else alert('Error: ' + (d.error || 'Failed to save rule'));
    });
}

function deleteMaskingPolicy(id) {
    if (!confirm('Delete this masking rule?')) return;
    fetch(`${BASE_URL}/modules/ai/api/management.php?action=delete_masking_policy`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ id: id })
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
    });
}

// Retention Policy Helper
function editRetentionPolicy(id, type, currentDays) {
    const days = prompt(`Enter retention days for ${type.replace(/_/g, ' ')}:`, currentDays);
    if (!days) return;

    fetch(`${BASE_URL}/modules/ai/api/management.php?action=update_retention_policy`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ id: id, retention_days: parseInt(days) })
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
        else alert('Error: ' + (d.error || 'Failed to update retention policy'));
    });
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Run Configuration Validation
async function runConfigValidation() {
    try {
        const resp = await fetch(`${BASE_URL}/modules/ai/api/management.php?action=validate_features`);
        const data = await resp.json();
        if (data.success) {
            let msg = 'Configuration Validation Results:\n\n';
            for (const [feature, result] of Object.entries(data.validations)) {
                const icon = result.valid ? '✓' : '⚠';
                msg += `${icon} ${feature}: ${result.valid ? 'Ready' : result.issues.join(', ')}\n`;
            }
            alert(msg);
        } else {
            alert('Validation failed: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Network error running validation.');
    }
}
</script>

<?php require_once AI_APP_ROOT . '/templates/footer.php'; ?>
