<?php
/**
 * AI F24 — Governance Center.
 *
 * Centralized dashboard for policy engine, risk classification,
 * data classification, review queue, and security events.
 */
$pageTitle = 'AI Governance';
require_once __DIR__ . '/../bootstrap.php';
require_once AI_MODULE_DIR . '/services/AIPolicyEngine.php';
require_once AI_MODULE_DIR . '/services/AIRiskClassifier.php';
require_once AI_MODULE_DIR . '/services/AIDataClassificationService.php';
require_once AI_MODULE_DIR . '/services/AIReviewQueueService.php';
require_once AI_MODULE_DIR . '/services/AISecurityEventService.php';
require_once AI_MODULE_DIR . '/services/AIGovernanceAudit.php';
require_once AI_MODULE_DIR . '/api/governance.php';

if (!hasPermission('ai.management.admin') && !hasPermission('ai.management.view')) {
    die('Access Denied');
}

$db = getDB();
$companyId = isset($_SESSION['company_id']) ? (int) $_SESSION['company_id'] : 1;
$userId = $_SESSION['user_id'] ?? 0;

// Gather governance data
$stats = getGovernanceStats($db);
$policies = aiGovernanceListPolicies($db, $companyId);
$riskRules = getRiskRules($db);
$classifications = getClassifications($db);
$maskingRules = getMaskingRules($db);
$autonomyLevels = getAutonomyLevels($db, $companyId);

// Review queue summary
$reviewQueue = AIReviewQueueService::getStatistics($db);

// Security events summary
$securityStats = AISecurityEventService::getStatistics($db, $companyId);

// Policy categories for chart
$activePolicies = (int) ($stats['active_policies'] ?? 0);
$draftPolicies = (int) ($stats['draft_policies'] ?? 0);
$pendingReviews = (int) ($stats['pending_reviews'] ?? 0);
$openSecurityEvents = (int) ($stats['open_security_events'] ?? 0);

/**
 * Helper to get policy list with company filter.
 */
function aiGovernanceListPolicies($db, $companyId) {
    $where = 'WHERE 1=1';
    $params = [];
    if ($companyId) {
        $where .= ' AND (scope = ? OR (scope = ? AND scope_id = ?))';
        $params[] = 'global';
        $params[] = 'company';
        $params[] = $companyId;
    }
    $where .= ' ORDER BY priority DESC, created_at DESC LIMIT 20';
    $stmt = $db->prepare("SELECT * FROM ai_policies $where");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Helper to get autonomy levels.
 */
function getAutonomyLevels($db, $companyId) {
    $levels = [];
    $stmt = $db->query("SELECT * FROM ai_autonomy_settings WHERE is_active = 1 ORDER BY id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Render a simple bar chart as HTML/CSS.
 */
function renderBarChart($data, $colors = []) {
    if (empty($data)) {
        return '<div class="text-sm text-gray-400">No data available.</div>';
    }
    $max = max(array_values($data));
    if ($max === 0) $max = 1;
    $defaultColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
    $i = 0;
    $html = '<div class="space-y-2">';
    foreach ($data as $label => $value) {
        $color = $colors[$i % count($colors)] ?? $defaultColors[$i % count($defaultColors)];
        $pct = round(($value / $max) * 100, 1);
        $html .= sprintf(
            '<div class="flex items-center gap-3"><span class="w-32 text-xs text-gray-400 truncate">%s</span><div class="flex-1 bg-slate-700 rounded-full h-4"><div class="h-4 rounded-full" style="width:%s%%;background:%s"></div></div><span class="w-8 text-xs font-bold text-gray-300">%d</span></div>',
            htmlspecialchars($label), $pct, $color, $value
        );
        $i++;
    }
    $html .= '</div>';
    return $html;
}

// Handle AJAX actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($action) {
    header('Content-Type: application/json');
    try {
        switch ($action) {
            case 'evaluate_policy':
                $input = json_decode(file_get_contents('php://input'), true) ?? [];
                $actionName = $input['action'] ?? '';
                $context = $input['context'] ?? [];
                if (!$actionName) {
                    echo json_encode(['success' => false, 'error' => 'Action is required']);
                    exit;
                }
                if (!class_exists('AIPolicyEngine')) {
                    echo json_encode(['success' => false, 'error' => 'Policy engine not available']);
                    exit;
                }
                $result = AIPolicyEngine::evaluate($db, $actionName, $context);
                echo json_encode(['success' => true, 'data' => $result]);
                exit;
            case 'classify_risk':
                $input = json_decode(file_get_contents('php://input'), true) ?? [];
                $actionName = $input['action'] ?? '';
                $context = $input['context'] ?? [];
                if (!class_exists('AIRiskClassifier')) {
                    echo json_encode(['success' => false, 'error' => 'Risk classifier not available']);
                    exit;
                }
                $result = AIRiskClassifier::classify($db, $actionName, $context, $companyId);
                echo json_encode(['success' => true, 'data' => $result]);
                exit;
            case 'classify_data':
                $input = json_decode(file_get_contents('php://input'), true) ?? [];
                $content = $input['content'] ?? '';
                if (!class_exists('AIDataClassificationService')) {
                    echo json_encode(['success' => false, 'error' => 'Classification service not available']);
                    exit;
                }
                $result = AIDataClassificationService::classifyContent($content, []);
                echo json_encode(['success' => true, 'data' => $result]);
                exit;
            case 'get_review_queue':
                $page = max(1, (int) ($_GET['page'] ?? 1));
                $perPage = min(50, (int) ($_GET['per_page'] ?? 20));
                $result = AIReviewQueueService::getPendingItems($db, [], $page, $perPage);
                echo json_encode(['success' => true, 'data' => $result]);
                exit;
            case 'review_item':
                $itemId = (int) ($_POST['item_id'] ?? 0);
                $actionType = $_POST['action_type'] ?? 'approve';
                $notes = $_POST['notes'] ?? '';
                if (!$itemId) {
                    echo json_encode(['success' => false, 'error' => 'Item ID required']);
                    exit;
                }
                switch ($actionType) {
                    case 'approve':
                        AIReviewQueueService::approve($db, $itemId, $userId, $notes);
                        break;
                    case 'reject':
                        AIReviewQueueService::reject($db, $itemId, $userId, $notes);
                        break;
                    case 'escalate':
                        AIReviewQueueService::escalate($db, $itemId, $userId, $notes);
                        break;
                }
                echo json_encode(['success' => true]);
                exit;
            case 'get_security_events':
                $page = max(1, (int) ($_GET['page'] ?? 1));
                $perPage = min(50, (int) ($_GET['per_page'] ?? 20));
                $events = AISecurityEventService::getEvents($db, ['company_id' => $companyId], $page, $perPage);
                echo json_encode(['success' => true, 'data' => $events]);
                exit;
            case 'update_event_status':
                $eventId = (int) ($_POST['event_id'] ?? 0);
                $status = $_POST['status'] ?? 'resolved';
                if ($eventId) {
                    AISecurityEventService::updateStatus($db, $eventId, $status, $userId);
                }
                echo json_encode(['success' => true]);
                exit;
            case 'get_governance_stats':
                echo json_encode(['success' => true, 'data' => $stats]);
                exit;
            case 'create_policy':
                $input = json_decode(file_get_contents('php://input'), true) ?? [];
                if (!class_exists('AIPolicyEngine')) {
                    echo json_encode(['success' => false, 'error' => 'Policy engine not available']);
                    exit;
                }
                $result = AIPolicyEngine::createPolicy($db, $input, $userId);
                echo json_encode($result);
                exit;
            case 'delete_policy':
                $policyId = (int) ($_POST['policy_id'] ?? 0);
                if (!$policyId) {
                    echo json_encode(['success' => false, 'error' => 'Policy ID required']);
                    exit;
                }
                $stmt = $db->prepare("UPDATE ai_policies SET status = 'archived' WHERE id = ?");
                $stmt->execute([$policyId]);
                echo json_encode(['success' => true]);
                exit;
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
                exit;
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}
?>

<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>
<?php ai_render_module_nav('ai.management'); ?>
<?php ai_render_page_styles(); ?>

<div class="ai-page-container">

<!-- Page Header -->
<div class="ai-page-header mb-6 flex flex-wrap justify-between items-start gap-4">
    <div>
        <h1 class="text-2xl font-bold text-white">AI Governance Center</h1>
        <p class="text-gray-400 mt-1">Centralized policy engine, risk classification, data governance, review queue, and security monitoring.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <button type="button" id="refreshStats" class="bg-sky-600 hover:bg-sky-700 text-white px-3.5 py-2 rounded-lg text-sm font-medium transition shadow-sm flex items-center gap-1.5">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
        <a href="#policy-engine" class="bg-slate-700 hover:bg-slate-600 text-gray-300 px-3.5 py-2 rounded-lg text-sm font-medium transition shadow-sm flex items-center gap-1.5">
            <i class="fas fa-shield-alt"></i> Policy Engine
        </a>
        <a href="#review-queue" class="bg-slate-700 hover:bg-slate-600 text-gray-300 px-3.5 py-2 rounded-lg text-sm font-medium transition shadow-sm flex items-center gap-1.5">
            <i class="fas fa-search"></i> Review Queue
        </a>
        <a href="#security-events" class="bg-slate-700 hover:bg-slate-600 text-gray-300 px-3.5 py-2 rounded-lg text-sm font-medium transition shadow-sm flex items-center gap-1.5">
            <i class="fas fa-bell"></i> Security Events
        </a>
    </div>
</div>

<!-- Feedback banner -->
<div id="governanceFeedback" class="hidden mb-4 rounded-lg px-4 py-3 text-sm"></div>

<!-- Key Metrics -->
<div class="ai-stat-grid mb-6">
    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-file-alt text-sky-400"></i></div>
        <div class="ai-stat-value text-sky-400"><?= $activePolicies ?></div>
        <div class="ai-stat-label">Active Policies</div>
    </div>
    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-pen text-amber-400"></i></div>
        <div class="ai-stat-value text-amber-400"><?= $draftPolicies ?></div>
        <div class="ai-stat-label">Draft Policies</div>
    </div>
    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-clock text-orange-400"></i></div>
        <div class="ai-stat-value text-orange-400"><?= $pendingReviews ?></div>
        <div class="ai-stat-label">Pending Reviews</div>
    </div>
    <div class="ai-stat-card">
        <div class="ai-stat-icon-wrap"><i class="fas fa-exclamation-circle text-rose-400"></i></div>
        <div class="ai-stat-value text-rose-400"><?= $openSecurityEvents ?></div>
        <div class="ai-stat-label">Open Security Events</div>
    </div>
</div>

<!-- Section Navigation -->
<nav class="ai-tabs mb-6">
    <a href="#overview" class="ai-tab active"><i class="fas fa-chart-pie"></i> Overview</a>
    <a href="#policy-engine" class="ai-tab"><i class="fas fa-shield-alt"></i> Policy Engine</a>
    <a href="#risk-classification" class="ai-tab"><i class="fas fa-exclamation-triangle"></i> Risk Classification</a>
    <a href="#data-classification" class="ai-tab"><i class="fas fa-database"></i> Data Classification</a>
    <a href="#autonomy" class="ai-tab"><i class="fas fa-sliders-h"></i> Autonomy</a>
    <a href="#review-queue" class="ai-tab"><i class="fas fa-search"></i> Review Queue</a>
    <a href="#security-events" class="ai-tab"><i class="fas fa-bell"></i> Security Events</a>
    <a href="#masking-rules" class="ai-tab"><i class="fas fa-user-secret"></i> Masking Rules</a>
    <a href="#emergency-controls" class="ai-tab"><i class="fas fa-power-off"></i> Emergency Controls</a>
    <a href="#policy-simulator" class="ai-tab"><i class="fas fa-flask"></i> Policy Simulator</a>
    <a href="#injection-detection" class="ai-tab"><i class="fas fa-bug"></i> Injection Detection</a>
</nav>

<!-- Overview Section -->
<section id="overview" class="mb-8">
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="ai-content-card">
            <div class="px-5 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-semibold text-white">Policy Status Overview</h2>
            </div>
            <div class="p-5">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-sm text-gray-400">Active Policies</span>
                    <span class="text-sm font-bold text-sky-400"><?= $activePolicies ?></span>
                </div>
                <div class="w-full bg-slate-700 rounded-full h-4">
                    <div class="bg-sky-500 h-4 rounded-full" style="width: <?= $activePolicies > 0 ? round($activePolicies / max($activePolicies + $draftPolicies, 1) * 100, 1) : 0 ?>%"></div>
                </div>
                <div class="flex items-center justify-between mt-4">
                    <span class="text-sm text-gray-400">Draft Policies</span>
                    <span class="text-sm font-bold text-amber-400"><?= $draftPolicies ?></span>
                </div>
                <div class="w-full bg-slate-700 rounded-full h-4 mt-2">
                    <div class="bg-amber-500 h-4 rounded-full" style="width: <?= $draftPolicies > 0 ? round($draftPolicies / max($activePolicies + $draftPolicies, 1) * 100, 1) : 0 ?>%"></div>
                </div>
                <div class="mt-4 text-xs text-gray-500">
                    Total: <?= $activePolicies + $draftPolicies ?> policies across all scopes
                </div>
            </div>
        </div>

        <div class="ai-content-card">
            <div class="px-5 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-semibold text-white">Security Event Distribution</h2>
            </div>
            <div class="p-5">
                <?= renderBarChart($securityStats['by_severity'] ?? [], ['#ef4444', '#f59e0b', '#3b82f6', '#10b981', '#6b7280']) ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-6">
        <div class="ai-content-card">
            <div class="px-5 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-semibold text-white">Review Queue Status</h2>
            </div>
            <div class="p-5 space-y-3">
                <div class="flex justify-between text-sm"><span class="text-gray-400">Total Pending</span><span class="font-bold text-gray-200"><?= $reviewQueue['total_pending'] ?? 0 ?></span></div>
                <?php foreach (($reviewQueue['pending_by_risk'] ?? []) as $level => $count): ?>
                    <div class="flex justify-between text-sm"><span class="text-gray-400 capitalize"><?= htmlspecialchars($level) ?></span><span class="font-bold text-gray-200"><?= $count ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="ai-content-card">
            <div class="px-5 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-semibold text-white">Policy Governance</h2>
            </div>
            <div class="p-5 space-y-3">
                <div class="flex justify-between text-sm"><span class="text-gray-400">Active Risk Rules</span><span class="font-bold text-gray-200"><?= $stats['active_risk_rules'] ?? 0 ?></span></div>
                <div class="flex justify-between text-sm"><span class="text-gray-400">Data Classifications</span><span class="font-bold text-gray-200"><?= $stats['data_classifications'] ?? 0 ?></span></div>
                <div class="flex justify-between text-sm"><span class="text-gray-400">Active Masking Rules</span><span class="font-bold text-gray-200"><?= $stats['masking_rules'] ?? 0 ?></span></div>
            </div>
        </div>
        <div class="ai-content-card">
            <div class="px-5 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-semibold text-white">Quick Actions</h2>
            </div>
            <div class="p-5 space-y-2">
                <button onclick="showGovernanceFeedback('Policy evaluation test sent.')" class="w-full text-left px-3 py-2 text-sm bg-sky-500/10 text-sky-400 rounded-lg hover:bg-sky-500/20 transition">Test Policy Evaluation</button>
                <button onclick="showGovernanceFeedback('Risk classification test sent.')" class="w-full text-left px-3 py-2 text-sm bg-emerald-500/10 text-emerald-400 rounded-lg hover:bg-emerald-500/20 transition">Test Risk Classification</button>
                <button onclick="showGovernanceFeedback('Data classification test sent.')" class="w-full text-left px-3 py-2 text-sm bg-purple-500/10 text-purple-400 rounded-lg hover:bg-purple-500/20 transition">Test Data Classification</button>
                <a href="#policy-engine" class="block px-3 py-2 text-sm bg-slate-700/50 text-gray-300 rounded-lg hover:bg-slate-700 text-center transition">Manage Policies</a>
            </div>
        </div>
    </div>
</section>

<!-- Policy Engine Section -->
<section id="policy-engine" class="mb-8">
    <div class="ai-content-card">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h2 class="text-lg font-semibold text-white">Policy Engine</h2>
            <p class="text-sm text-gray-400 mt-1">Centralized policy evaluation for all AI operations. Every action must pass through this engine.</p>
        </div>
        <div class="p-5">
            <!-- Policy List -->
            <div class="mb-6">
                <h3 class="text-md font-semibold text-gray-300 mb-3">Policies</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-700/50 text-gray-400 uppercase text-xs">
                            <tr>
                                <th class="text-left px-4 py-2">Key</th>
                                <th class="text-left px-4 py-2">Name</th>
                                <th class="text-left px-4 py-2">Type</th>
                                <th class="text-left px-4 py-2">Scope</th>
                                <th class="text-left px-4 py-2">Effect</th>
                                <th class="text-left px-4 py-2">Status</th>
                                <th class="text-left px-4 py-2">Priority</th>
                                <th class="text-left px-4 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/50">
                            <?php foreach ($policies as $policy): ?>
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs text-gray-300"><?= htmlspecialchars($policy['policy_key'] ?? '') ?></td>
                                    <td class="px-4 py-2 text-gray-200"><?= htmlspecialchars($policy['name'] ?? '') ?></td>
                                    <td class="px-4 py-2 text-gray-300"><?= htmlspecialchars($policy['type'] ?? '') ?></td>
                                    <td class="px-4 py-2 text-gray-300"><?= htmlspecialchars($policy['scope'] ?? '') ?></td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-1 text-xs rounded <?php
                                            $effect = $policy['effect'] ?? '';
                                            $classes = ['allow' => 'bg-emerald-500/15 text-emerald-400', 'deny' => 'bg-red-500/15 text-red-400', 'require_confirmation' => 'bg-amber-500/15 text-amber-400', 'require_review' => 'bg-orange-500/15 text-orange-400', 'mask_data' => 'bg-purple-500/15 text-purple-400', 'restrict_provider' => 'bg-sky-500/15 text-sky-400', 'log_only' => 'bg-slate-500/15 text-slate-400'];
                                            echo $classes[$effect] ?? 'bg-slate-500/15 text-slate-400';
                                        ?>"><?= htmlspecialchars($effect) ?></span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-300"><?= htmlspecialchars($policy['status'] ?? '') ?></td>
                                    <td class="px-4 py-2 text-gray-300"><?= (int) ($policy['priority'] ?? 0) ?></td>
                                    <td class="px-4 py-2">
                                        <button onclick="deletePolicy(<?= (int) $policy['id'] ?>)" class="text-red-400 text-xs hover:text-red-300 transition">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Create Policy Form -->
            <div class="border border-slate-600/50 rounded-lg p-4">
                <h3 class="text-md font-semibold text-gray-300 mb-3">Create New Policy</h3>
                <form id="createPolicyForm" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <input type="text" name="policy_key" class="bg-slate-800/60 border border-slate-600/50 text-white rounded-lg px-3 py-2 text-sm placeholder-gray-500 focus:border-sky-500 focus:outline-none" placeholder="Policy Key" required>
                    <input type="text" name="name" class="bg-slate-800/60 border border-slate-600/50 text-white rounded-lg px-3 py-2 text-sm placeholder-gray-500 focus:border-sky-500 focus:outline-none" placeholder="Policy Name" required>
                    <input type="text" name="description" class="bg-slate-800/60 border border-slate-600/50 text-white rounded-lg px-3 py-2 text-sm placeholder-gray-500 focus:border-sky-500 focus:outline-none" placeholder="Description">
                    <select name="type" class="bg-slate-800/60 border border-slate-600/50 text-white rounded-lg px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                        <option value="ai_usage">AI Usage</option>
                        <option value="data_access">Data Access</option>
                        <option value="data_transmission">Data Transmission</option>
                        <option value="tool_usage">Tool Usage</option>
                        <option value="action_execution">Action Execution</option>
                        <option value="external_provider">External Provider</option>
                        <option value="document">Document</option>
                        <option value="voice">Voice</option>
                        <option value="integration">Integration</option>
                        <option value="automation">Automation</option>
                        <option value="knowledge">Knowledge</option>
                        <option value="logging">Logging</option>
                        <option value="retention">Retention</option>
                    </select>
                    <select name="scope" class="bg-slate-800/60 border border-slate-600/50 text-white rounded-lg px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                        <option value="global">Global</option>
                        <option value="company">Company</option>
                        <option value="role">Role</option>
                        <option value="user">User</option>
                        <option value="feature">Feature</option>
                        <option value="tool">Tool</option>
                        <option value="provider">Provider</option>
                    </select>
                    <input type="text" name="scope_id" class="bg-slate-800/60 border border-slate-600/50 text-white rounded-lg px-3 py-2 text-sm placeholder-gray-500 focus:border-sky-500 focus:outline-none" placeholder="Scope ID (optional)">
                    <select name="effect" class="bg-slate-800/60 border border-slate-600/50 text-white rounded-lg px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                        <option value="allow">Allow</option>
                        <option value="deny">Deny</option>
                        <option value="require_confirmation">Require Confirmation</option>
                        <option value="require_review">Require Review</option>
                        <option value="mask_data">Mask Data</option>
                        <option value="restrict_provider">Restrict Provider</option>
                        <option value="log_only">Log Only</option>
                    </select>
                    <input type="number" name="priority" class="bg-slate-800/60 border border-slate-600/50 text-white rounded-lg px-3 py-2 text-sm placeholder-gray-500 focus:border-sky-500 focus:outline-none" placeholder="Priority" value="0">
                    <select name="status" class="bg-slate-800/60 border border-slate-600/50 text-white rounded-lg px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                    </select>
                    <textarea name="conditions" class="bg-slate-800/60 border border-slate-600/50 text-white rounded-lg px-3 py-2 text-sm md:col-span-2 placeholder-gray-500 focus:border-sky-500 focus:outline-none" rows="2" placeholder='Conditions JSON (optional): {"key": "value"}'></textarea>
                    <button type="submit" class="md:col-span-2 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">Create Policy</button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Risk Classification Section -->
<section id="risk-classification" class="mb-8">
    <div class="ai-content-card">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h2 class="text-lg font-semibold text-white">Risk Classification</h2>
            <p class="text-sm text-gray-400 mt-1">Deterministic risk classification for AI actions with configurable rules.</p>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-md font-semibold text-gray-300 mb-3">Risk Rules</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-700/50 text-gray-400 uppercase text-xs">
                                <tr>
                                    <th class="text-left px-4 py-2">Rule</th>
                                    <th class="text-left px-4 py-2">Category</th>
                                    <th class="text-left px-4 py-2">Level</th>
                                    <th class="text-left px-4 py-2">Priority</th>
                                    <th class="text-left px-4 py-2">Active</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/50">
                                <?php foreach ($riskRules as $rule): ?>
                                    <tr>
                                        <td class="px-4 py-2 text-gray-300"><?= htmlspecialchars($rule['rule_name'] ?? $rule['rule_key'] ?? '') ?></td>
                                        <td class="px-4 py-2 text-gray-300"><?= htmlspecialchars($rule['category'] ?? '') ?></td>
                                        <td class="px-4 py-2">
                                            <span class="px-2 py-1 text-xs rounded <?php
                                                $level = $rule['risk_level'] ?? '';
                                                $classes = ['low' => 'bg-emerald-500/15 text-emerald-400', 'medium' => 'bg-amber-500/15 text-amber-400', 'high' => 'bg-orange-500/15 text-orange-400', 'critical' => 'bg-red-500/15 text-red-400'];
                                                echo $classes[$level] ?? 'bg-slate-500/15 text-slate-400';
                                            ?>"><?= htmlspecialchars($level) ?></span>
                                        </td>
                                        <td class="px-4 py-2 text-gray-300"><?= (int) ($rule['priority'] ?? 0) ?></td>
                                        <td class="px-4 py-2 text-gray-300"><?= $rule['is_active'] ? 'Yes' : 'No' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div>
                    <h3 class="text-md font-semibold text-gray-300 mb-3">Test Risk Classification</h3>
                    <div class="border border-slate-600/50 rounded-lg p-4">
                        <form id="testRiskForm" class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-400 mb-1">Action</label>
                                <input type="text" id="riskAction" class="w-full bg-slate-800/60 border border-slate-600/50 text-white rounded-lg px-3 py-2 text-sm placeholder-gray-500 focus:border-sky-500 focus:outline-none" placeholder="e.g., delete_record" value="delete_record">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-400 mb-1">Context (JSON)</label>
                                <textarea id="riskContext" class="w-full bg-slate-800/60 border border-slate-600/50 text-white rounded-lg px-3 py-2 text-sm placeholder-gray-500 focus:border-sky-500 focus:outline-none" rows="3">{"amount": 5000, "data_classification": "confidential", "user_role": "admin"}</textarea>
                            </div>
                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">Classify Risk</button>
                        </form>
                        <div id="riskResult" class="mt-3 hidden"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Data Classification Section -->
<section id="data-classification" class="mb-8">
    <div class="ai-content-card">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h2 class="text-lg font-semibold text-white">Data Classification</h2>
            <p class="text-sm text-gray-400 mt-1">Classify data based on content sensitivity and enforce masking rules.</p>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-md font-semibold text-gray-300 mb-3">Classifications</h3>
                    <div class="space-y-2">
                        <?php foreach ($classifications as $cls): ?>
                            <div class="border border-slate-600/50 rounded-lg p-3 flex justify-between items-center bg-slate-800/30">
                                <div>
                                    <span class="font-medium text-sm text-gray-200"><?= htmlspecialchars($cls['classification'] ?? '') ?></span>
                                    <span class="text-xs text-gray-500 ml-2"><?= htmlspecialchars($cls['description'] ?? '') ?></span>
                                </div>
                                <span class="px-2 py-1 text-xs rounded bg-sky-500/15 text-sky-400"><?= htmlspecialchars($cls['level'] ?? '') ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <h3 class="text-md font-semibold text-gray-300 mb-3">Test Data Classification</h3>
                    <div class="border border-slate-600/50 rounded-lg p-4">
                        <form id="testDataClassForm" class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-400 mb-1">Content to Classify</label>
                                <textarea id="classifyContent" class="w-full bg-slate-800/60 border border-slate-600/50 text-white rounded-lg px-3 py-2 text-sm placeholder-gray-500 focus:border-sky-500 focus:outline-none" rows="4">Customer John Doe has SSN 123-45-6789 and credit card 4111-1111-1111-1111.</textarea>
                            </div>
                            <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">Classify</button>
                        </form>
                        <div id="classifyResult" class="mt-3 hidden"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Autonomy Settings Section -->
<section id="autonomy" class="mb-8">
    <div class="ai-content-card">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h2 class="text-lg font-semibold text-white">Autonomy Levels</h2>
            <p class="text-sm text-gray-400 mt-1">Configure autonomy levels for users, roles, and companies.</p>
        </div>
        <div class="p-5">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-700/50 text-gray-400 uppercase text-xs">
                        <tr>
                            <th class="text-left px-4 py-2">Type</th>
                            <th class="text-left px-4 py-2">Name</th>
                            <th class="text-left px-4 py-2">Autonomy Level</th>
                            <th class="text-left px-4 py-2">Scope</th>
                            <th class="text-left px-4 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <?php foreach ($autonomyLevels as $aut): ?>
                            <tr>
                                <td class="px-4 py-2 text-gray-300 capitalize"><?= htmlspecialchars($aut['scope'] ?? 'unknown') ?></td>
                                <td class="px-4 py-2 text-gray-200"><?= htmlspecialchars($aut['name'] ?? '') ?></td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-1 text-xs rounded bg-indigo-500/15 text-indigo-400"><?= htmlspecialchars($aut['autonomy_level'] ?? '') ?></span>
                                </td>
                                <td class="px-4 py-2 text-gray-300"><?= htmlspecialchars($aut['scope_id'] ?? 'N/A') ?></td>
                                <td class="px-4 py-2 text-gray-300"><?= $aut['is_active'] ? 'Active' : 'Inactive' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- Review Queue Section -->
<section id="review-queue" class="mb-8">
    <div class="ai-content-card">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h2 class="text-lg font-semibold text-white">Review Queue</h2>
            <p class="text-sm text-gray-400 mt-1">Human review queue for high-risk operations requiring approval.</p>
        </div>
        <div class="p-5">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-700/50 text-gray-400 uppercase text-xs">
                        <tr>
                            <th class="text-left px-4 py-2">Item Key</th>
                            <th class="text-left px-4 py-2">Type</th>
                            <th class="text-left px-4 py-2">Risk Level</th>
                            <th class="text-left px-4 py-2">Priority</th>
                            <th class="text-left px-4 py-2">Status</th>
                            <th class="text-left px-4 py-2">User</th>
                            <th class="text-left px-4 py-2">Created</th>
                            <th class="text-left px-4 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50" id="reviewQueueBody">
                        <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">Loading review queue...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-between items-center">
                <span class="text-sm text-gray-500" id="reviewQueueInfo"></span>
                <div class="flex gap-2">
                    <button onclick="loadReviewQueue(<?= $reviewQueue['page'] ?? 1 - 1 ?>)" class="px-3 py-1 text-sm bg-slate-700 rounded hover:bg-slate-600 transition text-gray-300" id="prevPageBtn" disabled>Previous</button>
                    <button onclick="loadReviewQueue(<?= ($reviewQueue['page'] ?? 1) + 1 ?>)" class="px-3 py-1 text-sm bg-slate-700 rounded hover:bg-slate-600 transition text-gray-300" id="nextPageBtn">Next</button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Security Events Section -->
<section id="security-events" class="mb-8">
    <div class="ai-content-card">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h2 class="text-lg font-semibold text-white">Security Events</h2>
            <p class="text-sm text-gray-400 mt-1">Security event detection, tracking, and incident response.</p>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <div class="bg-slate-700/40 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-white" id="secTotal"><?= $securityStats['total_events'] ?? 0 ?></div>
                    <div class="text-xs text-gray-400">Total Events</div>
                </div>
                <div class="bg-slate-700/40 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-rose-400" id="secCritical"><?= $securityStats['open_by_severity']['critical'] ?? 0 ?></div>
                    <div class="text-xs text-gray-400">Critical Open</div>
                </div>
                <div class="bg-slate-700/40 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-orange-400" id="secHigh"><?= $securityStats['open_by_severity']['high'] ?? 0 ?></div>
                    <div class="text-xs text-gray-400">High Open</div>
                </div>
                <div class="bg-slate-700/40 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-sky-400" id="sec24h"><?= $securityStats['last_24_hours'] ?? 0 ?></div>
                    <div class="text-xs text-gray-400">Last 24h</div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-700/50 text-gray-400 uppercase text-xs">
                        <tr>
                            <th class="text-left px-4 py-2">Event Type</th>
                            <th class="text-left px-4 py-2">Category</th>
                            <th class="text-left px-4 py-2">Severity</th>
                            <th class="text-left px-4 py-2">Status</th>
                            <th class="text-left px-4 py-2">User</th>
                            <th class="text-left px-4 py-2">When</th>
                            <th class="text-left px-4 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50" id="securityEventsBody">
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Loading security events...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- Masking Rules Section -->
<section id="masking-rules" class="mb-8">
    <div class="ai-content-card">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h2 class="text-lg font-semibold text-white">Masking Rules</h2>
            <p class="text-sm text-gray-400 mt-1">Field-level data masking rules for sensitive data protection.</p>
        </div>
        <div class="p-5">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-700/50 text-gray-400 uppercase text-xs">
                        <tr>
                            <th class="text-left px-4 py-2">Field Type</th>
                            <th class="text-left px-4 py-2">Pattern</th>
                            <th class="text-left px-4 py-2">Mask Character</th>
                            <th class="text-left px-4 py-2">Active</th>
                            <th class="text-left px-4 py-2">Priority</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <?php foreach ($maskingRules as $rule): ?>
                            <tr>
                                <td class="px-4 py-2 text-gray-300"><?= htmlspecialchars($rule['field_type'] ?? '') ?></td>
                                <td class="px-4 py-2 font-mono text-xs text-gray-300"><?= htmlspecialchars($rule['pattern'] ?? '') ?></td>
                                <td class="px-4 py-2 text-gray-300"><?= htmlspecialchars($rule['mask_character'] ?? '*') ?></td>
                                <td class="px-4 py-2 text-gray-300"><?= $rule['is_active'] ? 'Yes' : 'No' ?></td>
                                <td class="px-4 py-2 text-gray-300"><?= (int) ($rule['priority'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- Emergency Controls Section -->
<section id="emergency-controls" class="mb-8">
    <div class="ai-content-card">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h2 class="text-lg font-semibold text-white">Emergency Controls</h2>
            <p class="text-sm text-gray-400 mt-1">Immediate server-side kill switches for all AI capabilities.</p>
        </div>
        <div class="p-5">
            <div id="emergencyControlsStatus" class="mb-4 text-sm text-gray-400">Loading emergency controls...</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3" id="emergencyControlsList"></div>
            <div class="mt-4 flex gap-2">
                <button onclick="activateAllEmergencyControls()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">Activate All</button>
                <button onclick="deactivateAllEmergencyControls()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">Deactivate All</button>
            </div>
        </div>
    </div>
</section>

<!-- Policy Simulator Section -->
<section id="policy-simulator" class="mb-8">
    <div class="ai-content-card">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h2 class="text-lg font-semibold text-white">Policy Simulator</h2>
            <p class="text-sm text-gray-400 mt-1">Simulate policy evaluation for hypothetical requests without making changes.</p>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <div>
                    <form id="simulateForm" class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">Action</label>
                            <input type="text" id="simAction" class="w-full bg-slate-800/60 border border-slate-600/50 text-white rounded-lg px-3 py-2 text-sm placeholder-gray-500 focus:border-sky-500 focus:outline-none" placeholder="e.g., invoice.approve" value="invoice.approve">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">User (ID or name)</label>
                            <input type="text" id="simUser" class="w-full bg-slate-800/60 border border-slate-600/50 text-white rounded-lg px-3 py-2 text-sm placeholder-gray-500 focus:border-sky-500 focus:outline-none" placeholder="User ID or name">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">Amount ($)</label>
                            <input type="number" id="simAmount" class="w-full bg-slate-800/60 border border-slate-600/50 text-white rounded-lg px-3 py-2 text-sm placeholder-gray-500 focus:border-sky-500 focus:outline-none" placeholder="e.g., 25000" value="25000">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">Context (JSON)</label>
                            <textarea id="simContext" class="w-full bg-slate-800/60 border border-slate-600/50 text-white rounded-lg px-3 py-2 text-sm placeholder-gray-500 focus:border-sky-500 focus:outline-none" rows="3">{}</textarea>
                        </div>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">Run Simulation</button>
                    </form>
                </div>
                <div>
                    <h3 class="text-md font-semibold text-gray-300 mb-3">Simulation Result</h3>
                    <div id="simResult" class="border border-slate-600/50 rounded-lg p-4 bg-slate-800/40 text-sm text-gray-400">Run a simulation to see results here.</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Injection Detection Section -->
<section id="injection-detection" class="mb-8">
    <div class="ai-content-card">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h2 class="text-lg font-semibold text-white">Injection Detection</h2>
            <p class="text-sm text-gray-400 mt-1">Monitor prompt injection attempts and external content trust boundaries.</p>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <div class="bg-slate-700/40 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-white" id="injTotal">0</div>
                    <div class="text-xs text-gray-400">Total Attempts</div>
                </div>
                <div class="bg-slate-700/40 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-red-400" id="injBlocked">0</div>
                    <div class="text-xs text-gray-400">Blocked</div>
                </div>
                <div class="bg-slate-700/40 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-orange-400" id="injLast24h">0</div>
                    <div class="text-xs text-gray-400">Last 24h</div>
                </div>
                <div class="bg-slate-700/40 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-sky-400" id="injTypes">0</div>
                    <div class="text-xs text-gray-400">Types</div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-700/50 text-gray-400 uppercase text-xs">
                        <tr>
                            <th class="text-left px-4 py-2">Type</th>
                            <th class="text-left px-4 py-2">Count</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50" id="injByTypeBody">
                        <tr><td colspan="2" class="px-4 py-4 text-center text-gray-500">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<script>
const API_BASE = '<?= url('modules/ai/api/governance.php') ?>';
const CSRF_TOKEN = '<?= csrfToken() ?>';

function showGovernanceFeedback(message, success = true) {
    const el = document.getElementById('governanceFeedback');
    el.className = (success
        ? 'mb-4 rounded-lg px-4 py-3 text-sm bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'
        : 'mb-4 rounded-lg px-4 py-3 text-sm bg-red-500/15 text-red-400 border border-red-500/30');
    el.textContent = message;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 5000);
}

async function postGovernance(action, payload = {}) {
    const resp = await fetch(API_BASE + '?action=' + encodeURIComponent(action), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
        body: JSON.stringify(payload)
    });
    return await resp.json();
}

// Load review queue
let reviewPage = 1;
async function loadReviewQueue(page) {
    reviewPage = Math.max(1, page);
    const resp = await fetch(API_BASE + '?action=get_review_queue&page=' + reviewPage + '&per_page=10');
    const result = await resp.json();
    if (!result.success) return;

    const tbody = document.getElementById('reviewQueueBody');
    const data = result.data;
    if (!data.items || data.items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">No pending items.</td></tr>';
        return;
    }

    tbody.innerHTML = data.items.map(item => `
        <tr>
            <td class="px-4 py-2 font-mono text-xs text-gray-300">${item.item_key}</td>
            <td class="px-4 py-2 text-gray-300">${item.item_type}</td>
            <td class="px-4 py-2"><span class="px-2 py-1 text-xs rounded ${getRiskClass(item.risk_level)}">${item.risk_level}</span></td>
            <td class="px-4 py-2 text-gray-300">${item.priority}</td>
            <td class="px-4 py-2 text-gray-300">${item.status}</td>
            <td class="px-4 py-2 text-gray-300">${item.user_name || 'N/A'}</td>
            <td class="px-4 py-2 text-gray-300">${item.created_at}</td>
            <td class="px-4 py-2">
                <button onclick="reviewItem(${item.id}, 'approve')" class="text-emerald-400 text-xs hover:text-emerald-300 mr-1 transition">Approve</button>
                <button onclick="reviewItem(${item.id}, 'reject')" class="text-red-400 text-xs hover:text-red-300 mr-1 transition">Reject</button>
                <button onclick="reviewItem(${item.id}, 'escalate')" class="text-orange-400 text-xs hover:text-orange-300 transition">Escalate</button>
            </td>
        </tr>
    `).join('');

    const totalPages = data.total_pages || 1;
    document.getElementById('prevPageBtn').disabled = reviewPage <= 1;
    document.getElementById('nextPageBtn').disabled = reviewPage >= totalPages;
    document.getElementById('reviewQueueInfo').textContent = `Page ${reviewPage} of ${totalPages} (${data.total} total items)`;
}

function getRiskClass(level) {
    const classes = {critical: 'bg-red-500/15 text-red-400', high: 'bg-orange-500/15 text-orange-400', medium: 'bg-amber-500/15 text-amber-400', low: 'bg-emerald-500/15 text-emerald-400'};
    return classes[level] || 'bg-slate-500/15 text-slate-400';
}

// Load security events
async function loadSecurityEvents() {
    const resp = await fetch(API_BASE + '?action=get_security_events&page=1&per_page=15');
    const result = await resp.json();
    if (!result.success) return;

    const tbody = document.getElementById('securityEventsBody');
    const events = result.data.events || [];
    if (events.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No security events found.</td></tr>';
        return;
    }

    tbody.innerHTML = events.map(ev => `
        <tr>
            <td class="px-4 py-2 text-gray-300">${ev.event_type}</td>
            <td class="px-4 py-2 text-gray-300">${ev.event_category}</td>
            <td class="px-4 py-2"><span class="px-2 py-1 text-xs rounded ${getSeverityClass(ev.severity)}">${ev.severity}</span></td>
            <td class="px-4 py-2 text-gray-300">${ev.status}</td>
            <td class="px-4 py-2 text-gray-300">${ev.user_name || 'N/A'}</td>
            <td class="px-4 py-2 text-gray-300">${ev.created_at}</td>
            <td class="px-4 py-2">
                ${ev.status !== 'resolved' ? `<button onclick="updateEventStatus(${ev.id}, 'resolved')" class="text-emerald-400 text-xs hover:text-emerald-300 transition">Resolve</button>` : ''}
            </td>
        </tr>
    `).join('');
}

function getSeverityClass(severity) {
    const classes = {critical: 'bg-red-500/15 text-red-400', high: 'bg-orange-500/15 text-orange-400', warning: 'bg-amber-500/15 text-amber-400', info: 'bg-sky-500/15 text-sky-400'};
    return classes[severity] || 'bg-slate-500/15 text-slate-400';
}

// Test risk classification
document.getElementById('testRiskForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const action = document.getElementById('riskAction').value;
    const context = document.getElementById('riskContext').value;
    try {
        const result = await postGovernance('classify_risk', {action, context: JSON.parse(context)});
        const box = document.getElementById('riskResult');
        box.classList.remove('hidden');
        box.className = 'mt-3 p-3 rounded-lg border text-sm ' + (result.success ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-red-500/10 border-red-500/30 text-red-400');
        box.innerHTML = '<strong>Result:</strong> ' + JSON.stringify(result.data || result.error, null, 2);
    } catch (err) {
        const box = document.getElementById('riskResult');
        box.classList.remove('hidden');
        box.className = 'mt-3 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm';
        box.textContent = 'Error: ' + err.message;
    }
});

// Test data classification
document.getElementById('testDataClassForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const content = document.getElementById('classifyContent').value;
    try {
        const result = await postGovernance('classify_data', {content});
        const box = document.getElementById('classifyResult');
        box.classList.remove('hidden');
        box.className = 'mt-3 p-3 rounded-lg border text-sm ' + (result.success ? 'bg-purple-500/10 border-purple-500/30 text-purple-400' : 'bg-red-500/10 border-red-500/30 text-red-400');
        box.innerHTML = '<strong>Classification:</strong> ' + JSON.stringify(result.data, null, 2);
    } catch (err) {
        const box = document.getElementById('classifyResult');
        box.classList.remove('hidden');
        box.className = 'mt-3 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm';
        box.textContent = 'Error: ' + err.message;
    }
});

// Create policy
document.getElementById('createPolicyForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const conditions = fd.get('conditions');
    const payload = {};
    for (const [key, value] of fd.entries()) {
        if (key === 'conditions') {
            if (value.trim()) {
                try { payload[key] = JSON.parse(value); } catch { payload[key] = {}; }
            }
        } else {
            payload[key] = value;
        }
    }
    try {
        const result = await postGovernance('create_policy', payload);
        if (result.success) {
            showGovernanceFeedback('Policy created successfully.');
            e.target.reset();
            location.reload();
        } else {
            showGovernanceFeedback(result.error || 'Failed to create policy.', false);
        }
    } catch (err) {
        showGovernanceFeedback('Error: ' + err.message, false);
    }
});

// Delete policy
async function deletePolicy(policyId) {
    if (!confirm('Delete this policy?')) return;
    const result = await postGovernance('delete_policy', {policy_id: policyId});
    if (result.success) {
        showGovernanceFeedback('Policy deleted.');
        location.reload();
    } else {
        showGovernanceFeedback(result.error || 'Failed to delete policy.', false);
    }
}

// Review item
async function reviewItem(itemId, actionType) {
    const notes = prompt('Enter notes (optional):', '');
    const result = await postGovernance('review_item', {item_id: itemId, action_type: actionType, notes: notes || ''});
    if (result.success) {
        showGovernanceFeedback(`Item ${actionType}ed.`);
        loadReviewQueue(reviewPage);
    } else {
        showGovernanceFeedback(result.error || 'Failed.', false);
    }
}

// Update event status
async function updateEventStatus(eventId, status) {
    const result = await postGovernance('update_event_status', {event_id: eventId, status});
    if (result.success) loadSecurityEvents();
}

// ═══ Emergency Controls ═══
async function loadEmergencyControls() {
    try {
        const result = await postGovernance('get_emergency_controls');
        if (!result.success) return;
        const controls = result.data;
        const list = document.getElementById('emergencyControlsList');
        const status = document.getElementById('emergencyControlsStatus');
        const activeCount = Object.values(controls).filter(c => c.is_active).length;
        status.textContent = activeCount > 0
            ? `${activeCount} of ${Object.keys(controls).length} controls ACTIVE — AI features restricted`
            : `All controls inactive — AI operating normally`;
        status.className = activeCount > 0 ? 'mb-4 text-sm text-red-400 font-semibold' : 'mb-4 text-sm text-emerald-400';
        list.innerHTML = Object.values(controls).map(c => `
            <div class="border rounded-lg p-3 flex justify-between items-center ${c.is_active ? 'border-red-500/30 bg-red-500/10' : 'border-slate-600/50 bg-slate-800/30'}">
                <div>
                    <div class="text-sm font-medium ${c.is_active ? 'text-red-400' : 'text-gray-300'}">${c.name}</div>
                    <div class="text-xs text-gray-500">${c.description}</div>
                </div>
                <button onclick="toggleEmergencyControl('${c.key}', ${!c.is_active})" class="px-3 py-1 text-xs rounded ${c.is_active ? 'bg-emerald-500/15 text-emerald-400 hover:bg-emerald-500/25' : 'bg-red-500/15 text-red-400 hover:bg-red-500/25'} transition">
                    ${c.is_active ? 'Reactivate' : 'Activate'}
                </button>
            </div>
        `).join('');
    } catch (err) { /* non-critical */ }
}

async function toggleEmergencyControl(key, activate) {
    const reason = prompt('Reason for this change (required):');
    if (reason === null) return;
    const result = await postGovernance('toggle_emergency_control', { control_key: key, is_active: activate, reason });
    if (result.success) { loadEmergencyControls(); showGovernanceFeedback('Control updated.'); }
}

async function activateAllEmergencyControls() {
    const reason = prompt('Reason for emergency shutdown (required):');
    if (reason === null) return;
    const result = await postGovernance('activate_all_emergency', { reason });
    if (result.success) { loadEmergencyControls(); showGovernanceFeedback('All emergency controls activated.', false); }
}

async function deactivateAllEmergencyControls() {
    const reason = prompt('Reason for lifting emergency controls (required):');
    if (reason === null) return;
    const result = await postGovernance('deactivate_all_emergency', { reason });
    if (result.success) { loadEmergencyControls(); showGovernanceFeedback('All emergency controls deactivated.'); }
}

// ═══ Policy Simulator ═══
document.getElementById('simulateForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const action = document.getElementById('simAction').value;
    const user = document.getElementById('simUser').value;
    const amount = document.getElementById('simAmount').value;
    const contextStr = document.getElementById('simContext').value;
    try {
        const context = JSON.parse(contextStr || '{}');
        if (user) context.user = user;
        const result = await postGovernance('simulate_policy', { action, context, amount: amount ? parseFloat(amount) : null });
        const box = document.getElementById('simResult');
        if (result.success && result.data && result.data.result) {
            const r = result.data.result;
            const decisionClass = {ALLOW: 'bg-emerald-500/15 text-emerald-400', CONFIRM: 'bg-amber-500/15 text-amber-400', REVIEW: 'bg-orange-500/15 text-orange-400', DENY: 'bg-red-500/15 text-red-400'};
            box.className = 'border rounded-lg p-4 text-sm border-slate-600/50 bg-slate-800/30';
            box.innerHTML = `
                <div class="mb-3"><span class="text-xs font-semibold text-gray-400">FINAL DECISION:</span> <span class="px-2 py-1 text-xs rounded font-bold ${decisionClass[r.final_decision] || 'bg-slate-500/15 text-slate-400'}">${r.final_decision}</span></div>
                ${r.risk ? `<div class="mb-2"><span class="text-xs font-semibold text-gray-400">Risk:</span> <span class="capitalize text-gray-300">${r.risk.level}</span> (confidence: ${(r.risk.confidence * 100).toFixed(0)}%)</div>` : ''}
                ${r.autonomy_level ? `<div class="mb-2"><span class="text-xs font-semibold text-gray-400">Autonomy:</span> <span class="text-gray-300">${r.autonomy_level}</span></div>` : ''}
                ${r.reasons.length ? `<div class="mt-2"><span class="text-xs font-semibold text-gray-400">Reasons:</span><ul class="mt-1 space-y-1">${r.reasons.map(r => `<li class="text-xs text-gray-400">• ${r}</li>`).join('')}</ul></div>` : ''}
            `;
        } else {
            box.innerHTML = '<pre class="text-xs text-gray-400">' + JSON.stringify(result, null, 2) + '</pre>';
        }
    } catch (err) {
        document.getElementById('simResult').innerHTML = '<div class="text-red-400 text-sm">Error: ' + err.message + '</div>';
    }
});

// ═══ Injection Detection ═══
async function loadInjectionStats() {
    try {
        const result = await postGovernance('get_injection_stats');
        if (!result.success) return;
        const d = result.data;
        document.getElementById('injTotal').textContent = d.total_attempts || 0;
        document.getElementById('injBlocked').textContent = d.blocked || 0;
        document.getElementById('injLast24h').textContent = d.last_24_hours || 0;
        document.getElementById('injTypes').textContent = Object.keys(d.by_type || {}).length;
        const tbody = document.getElementById('injByTypeBody');
        const types = d.by_type || {};
        if (Object.keys(types).length === 0) {
            tbody.innerHTML = '<tr><td colspan="2" class="px-4 py-4 text-center text-gray-500">No injection attempts recorded</td></tr>';
        } else {
            tbody.innerHTML = Object.entries(types).map(([type, count]) => `
                <tr><td class="px-4 py-2 text-gray-300 capitalize">${type.replace(/_/g, ' ')}</td><td class="px-4 py-2 font-bold text-gray-200">${count}</td></tr>
            `).join('');
        }
    } catch (err) { /* non-critical */ }
}

// Refresh
document.getElementById('refreshStats')?.addEventListener('click', () => {
    loadReviewQueue(1);
    loadSecurityEvents();
    loadEmergencyControls();
    loadInjectionStats();
    showGovernanceFeedback('Dashboard refreshed.');
});

// Initial load
loadReviewQueue(1);
loadSecurityEvents();
loadEmergencyControls();
loadInjectionStats();
</script>

<?php require_once AI_APP_ROOT . '/includes/footer.php'; ?>
