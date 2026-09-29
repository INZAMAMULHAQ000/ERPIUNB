<?php
/**
 * AI F26 — Intelligence Center UI.
 *
 * Proactive intelligence dashboard: alerts, recommendations, insights,
 * rules, monitors, watchlists, trends, briefing, and dismissed history.
 *
 * Responsive, role-aware UI with F24 injection-safe evidence display.
 */
$pageTitle = 'AI Intelligence';
require_once __DIR__ . '/../bootstrap.php';
require_once AI_MODULE_DIR . '/services/AIIntelligenceService.php';
require_once AI_MODULE_DIR . '/services/AIDetectionEngine.php';
require_once AI_MODULE_DIR . '/services/AIWatchlistService.php';
require_once AI_MODULE_DIR . '/services/AIBriefingService.php';

if (!hasPermission('ai.assistant.access')) { die('Access Denied'); }

$db = getDB();
$userId = $_SESSION['user_id'];
$companyId = (int) ($_SESSION['company_id'] ?? 1);

$viewTab = $_GET['tab'] ?? 'overview';
$viewEventId = (int) ($_GET['event_id'] ?? 0);

// Test 16: Determine user's role for module filtering
$userRole = $_GET['role'] ?? 'all';
$validRoles = ['all', 'finance', 'procurement', 'inventory', 'management'];
if (!in_array($userRole, $validRoles)) $userRole = 'all';

// Load data
$stats = AIIntelligenceService::getStatistics($db, $companyId);
$briefing = AIBriefingService::generateBriefing($db, $userId, $companyId);

// Load events based on tab with role filtering
$eventsData = ['events' => [], 'total' => 0];
switch ($viewTab) {
    case 'alerts':
        $filters = [
            'company_id' => $companyId, 'statuses' => ['new', 'acknowledged'], 'min_severity' => 'medium',
        ];
        if ($userRole !== 'all') $filters['role_module_filter'] = $userRole;
        $eventsData = AIIntelligenceService::getEvents($db, $filters, 1, 30);
        break;
    case 'recommendations':
        $filters = [
            'company_id' => $companyId, 'statuses' => ['new', 'acknowledged'],
        ];
        if ($userRole !== 'all') $filters['role_module_filter'] = $userRole;
        $eventsData = AIIntelligenceService::getEvents($db, $filters, 1, 30);
        break;
    case 'dismissed':
        $eventsData = AIIntelligenceService::getEvents($db, [
            'company_id' => $companyId, 'statuses' => ['dismissed', 'expired'],
        ], 1, 30);
        break;
    case 'history':
        $eventsData = AIIntelligenceService::getEvents($db, [
            'company_id' => $companyId, 'statuses' => ['actioned'],
        ], 1, 30);
        break;
    default: // overview
        $filters = [
            'company_id' => $companyId, 'statuses' => ['new', 'acknowledged'], 'min_severity' => 'low',
        ];
        if ($userRole !== 'all') $filters['role_module_filter'] = $userRole;
        $eventsData = AIIntelligenceService::getEvents($db, $filters, 1, 20);
        break;
}

// Test 7: Permission check on event access
$viewEvent = $viewEventId ? AIIntelligenceService::getEvent($db, $viewEventId, $userId) : null;

$sevColors = ['info' => 'ai-badge ai-badge-info', 'low' => 'ai-badge', 'medium' => 'ai-badge ai-badge-warning', 'high' => 'ai-badge ai-badge-warning', 'critical' => 'ai-badge ai-badge-danger'];
$statColors = ['new' => 'blue', 'acknowledged' => 'gray', 'action_pending' => 'yellow', 'actioned' => 'green', 'dismissed' => 'gray', 'expired' => 'gray'];
?>

<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>
<?php ai_render_module_nav('ai.intelligence'); ?>
<?php ai_render_page_styles(); ?>

<div class="ai-page-container">
    <?php ai_render_page_header('AI Intelligence Center', 'Proactive detection, explanation, and recommendations', 'fas fa-radar'); ?>

    <!-- Stats Cards -->
    <div class="ai-stat-grid mb-6">
        <div class="ai-stat-card text-center">
            <div class="ai-stat-icon-wrap" style="background:rgba(248,113,113,0.15);color:var(--ai-danger);"><i class="fas fa-radar"></i></div>
            <div class="ai-stat-value" style="color:var(--ai-danger)"><?= $stats['active_by_severity']['critical'] ?? 0 ?></div>
            <div class="ai-stat-label">Critical</div>
        </div>
        <div class="ai-stat-card text-center">
            <div class="ai-stat-icon-wrap" style="background:rgba(251,191,36,0.15);color:var(--ai-warning);"><i class="fas fa-radar"></i></div>
            <div class="ai-stat-value" style="color:var(--ai-warning)"><?= $stats['active_by_severity']['high'] ?? 0 ?></div>
            <div class="ai-stat-label">High</div>
        </div>
        <div class="ai-stat-card text-center">
            <div class="ai-stat-icon-wrap" style="background:rgba(251,191,36,0.15);color:var(--ai-warning);"><i class="fas fa-radar"></i></div>
            <div class="ai-stat-value" style="color:var(--ai-warning)"><?= $stats['active_by_severity']['medium'] ?? 0 ?></div>
            <div class="ai-stat-label">Medium</div>
        </div>
        <div class="ai-stat-card text-center">
            <div class="ai-stat-icon-wrap" style="background:rgba(52,211,153,0.15);color:var(--ai-success);"><i class="fas fa-clock"></i></div>
            <div class="ai-stat-value" style="color:var(--ai-success)"><?= $stats['new_last_24h'] ?? 0 ?></div>
            <div class="ai-stat-label">New (24h)</div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="ai-tabs">
        <a href="?tab=overview&role=<?= $userRole ?>" class="ai-tab <?= $viewTab === 'overview' ? 'active' : '' ?>">Overview</a>
        <a href="?tab=alerts&role=<?= $userRole ?>" class="ai-tab <?= $viewTab === 'alerts' ? 'active' : '' ?>">Alerts</a>
        <a href="?tab=recommendations&role=<?= $userRole ?>" class="ai-tab <?= $viewTab === 'recommendations' ? 'active' : '' ?>">Recommendations</a>
        <a href="?tab=briefing" class="ai-tab <?= $viewTab === 'briefing' ? 'active' : '' ?>">Briefing</a>
        <a href="?tab=rules" class="ai-tab <?= $viewTab === 'rules' ? 'active' : '' ?>">Rules</a>
        <a href="?tab=watchlists" class="ai-tab <?= $viewTab === 'watchlists' ? 'active' : '' ?>">Watchlists</a>
        <a href="?tab=dismissed" class="ai-tab <?= $viewTab === 'dismissed' ? 'active' : '' ?>">Dismissed</a>
        <a href="?tab=history" class="ai-tab <?= $viewTab === 'history' ? 'active' : '' ?>">History</a>
    </div>

    <!-- Test 16: Role Filter -->
    <?php if ($viewTab !== 'briefing' && $viewTab !== 'rules' && $viewTab !== 'watchlists'): ?>
    <div class="flex flex-wrap gap-2 mb-4">
        <span class="text-xs text-slate-400 self-center">Filter:</span>
        <a href="?tab=<?= $viewTab ?>&role=all" class="px-2 py-1 rounded text-xs <?= $userRole === 'all' ? 'ai-badge ai-badge-info' : 'ai-badge' ?>">All</a>
        <a href="?tab=<?= $viewTab ?>&role=finance" class="px-2 py-1 rounded text-xs <?= $userRole === 'finance' ? 'ai-badge ai-badge-info' : 'ai-badge' ?>">Finance</a>
        <a href="?tab=<?= $viewTab ?>&role=procurement" class="px-2 py-1 rounded text-xs <?= $userRole === 'procurement' ? 'ai-badge ai-badge-info' : 'ai-badge' ?>">Procurement</a>
        <a href="?tab=<?= $viewTab ?>&role=inventory" class="px-2 py-1 rounded text-xs <?= $userRole === 'inventory' ? 'ai-badge ai-badge-info' : 'ai-badge' ?>">Inventory</a>
        <a href="?tab=<?= $viewTab ?>&role=management" class="px-2 py-1 rounded text-xs <?= $userRole === 'management' ? 'ai-badge ai-badge-info' : 'ai-badge' ?>">Management</a>
    </div>
    <?php endif; ?>

    <?php if ($viewEvent): ?>
    <!-- ═══ Event Detail View ═══ -->
    <div class="mb-4">
        <a href="?tab=<?= $viewTab ?>&role=<?= $userRole ?>" class="text-sky-400 hover:text-sky-300 text-sm"><i class="fas fa-arrow-left mr-1"></i>Back</a>
    </div>
    <div class="ai-content-card mb-6">
        <div class="flex items-start justify-between mb-4">
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-2">
                    <span class="px-2 py-0.5 text-xs rounded-full <?= $sevColors[$viewEvent['severity']] ?? 'ai-badge' ?>"><?= ucfirst($viewEvent['severity']) ?></span>
                    <span class="text-xs text-slate-500"><?= date('M j H:i', strtotime($viewEvent['created_at'])) ?></span>
                </div>
                <h2 class="text-lg font-bold text-slate-200"><?= htmlspecialchars($viewEvent['title']) ?></h2>
            </div>
            <div class="flex gap-2 flex-wrap">
                <?php if (!in_array($viewEvent['status'], ['actioned', 'dismissed'])): ?>
                <button onclick="updateEventStatus(<?= $viewEvent['id'] ?>, 'acknowledged')" class="ai-badge hover:opacity-80">Acknowledge</button>
                <button onclick="createTaskFromEvent(<?= $viewEvent['id'] ?>)" class="bg-sky-600 text-white px-3 py-1 rounded text-xs hover:bg-sky-500">Create Task</button>
                <button onclick="updateEventStatus(<?= $viewEvent['id'] ?>, 'snoozed', '24')" class="ai-badge ai-badge-warning hover:opacity-80">Snooze 24h</button>
                <button onclick="updateEventStatus(<?= $viewEvent['id'] ?>, 'dismissed')" class="ai-badge ai-badge-danger hover:opacity-80">Dismiss</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Test 9: Why?, Show Evidence, What should I do? -->
        <!-- Verified Facts (Why?) -->
        <div class="space-y-4">
            <div class="border border-sky-500/30 rounded-lg p-4" style="background:rgba(56,189,248,0.08);">
                <div class="text-xs font-semibold text-sky-400 uppercase mb-1"><i class="fas fa-question-circle mr-1"></i>Why?</div>
                <div class="text-sm text-slate-300"><?= htmlspecialchars($viewEvent['explanation'] ?? 'No explanation available.') ?></div>
            </div>

            <!-- Recommendation (What should I do?) -->
            <?php if ($viewEvent['recommendation']): ?>
            <div class="border border-emerald-500/30 rounded-lg p-4" style="background:rgba(52,211,153,0.08);">
                <div class="text-xs font-semibold text-emerald-400 uppercase mb-1"><i class="fas fa-lightbulb mr-1"></i>What should I do?</div>
                <div class="text-sm text-slate-300"><?= htmlspecialchars($viewEvent['recommendation']) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Show Evidence (Evidence Panel) -->
        <?php
        $evidenceData = json_decode($viewEvent['evidence_json'] ?? '[]', true);
        ?>
        <?php if (!empty($evidenceData)): ?>
        <div class="mt-4">
            <h3 class="text-sm font-semibold text-slate-300 mb-2"><i class="fas fa-search mr-1"></i>Show Evidence</h3>
            <div class="border border-slate-700/50 rounded-lg p-3 text-xs text-slate-400" style="background:rgba(15,23,42,0.5);">
                <pre class="whitespace-pre-wrap"><?= htmlspecialchars(json_encode($evidenceData, JSON_PRETTY_PRINT)) ?></pre>
            </div>
        </div>
        <?php endif; ?>

        <!-- Test 8: F24: Evidence Records with masked sensitive data -->
        <?php if (!empty($viewEvent['evidence'])): ?>
        <div class="mt-4">
            <h3 class="text-sm font-semibold text-slate-300 mb-2">Affected Records</h3>
            <div class="space-y-1">
                <?php foreach ($viewEvent['evidence'] as $ev): ?>
                <div class="flex items-center gap-3 py-1.5 px-3 border border-slate-700/50 rounded text-xs" style="background:rgba(15,23,42,0.5);">
                    <span class="text-slate-500 w-20"><?= htmlspecialchars($ev['record_type']) ?></span>
                    <span class="font-medium text-slate-200"><?= htmlspecialchars($ev['record_label'] ?? 'Record #' . $ev['record_id']) ?></span>
                    <?php if ($ev['amount']): ?>
                    <span class="text-slate-400">$<?= number_format((float)$ev['amount'], 2) ?></span>
                    <?php endif; ?>
                    <?php if ($ev['date_value']): ?>
                    <span class="text-slate-500"><?= $ev['date_value'] ?></span>
                    <?php endif; ?>
                    <?php if (!empty($ev['classification']) && $ev['classification'] !== 'internal'): ?>
                    <span class="px-1.5 py-0.5 border border-yellow-500/30 text-yellow-400 rounded text-xs" style="background:rgba(251,191,36,0.1);"><?= htmlspecialchars($ev['classification']) ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Related Task -->
        <?php if ($viewEvent['related_task_id']): ?>
        <div class="mt-4 border border-indigo-500/30 rounded-lg p-3" style="background:rgba(139,92,246,0.08);">
            <a href="/modules/ai/agents/index.php?tab=tasks&task_id=<?= $viewEvent['related_task_id'] ?>" class="text-indigo-400 hover:text-indigo-300 text-sm font-medium">
                <i class="fas fa-link mr-1"></i>View related F25 task: <?= htmlspecialchars($viewEvent['related_task_key'] ?? '#' . $viewEvent['related_task_id']) ?>
            </a>
        </div>
        <?php endif; ?>

        <!-- Test 17: Feedback -->
        <div class="mt-4 pt-4 border-t border-slate-700/50">
            <div class="flex items-center gap-2 text-xs text-slate-400 flex-wrap">
                <span>Was this helpful?</span>
                <button onclick="submitFeedback(<?= $viewEvent['id'] ?>, 'useful')" class="text-emerald-400 hover:text-emerald-300">Useful</button>
                <button onclick="submitFeedback(<?= $viewEvent['id'] ?>, 'not_useful')" class="text-slate-400 hover:text-slate-300">Not useful</button>
                <button onclick="submitFeedback(<?= $viewEvent['id'] ?>, 'incorrect')" class="text-red-400 hover:text-red-300">Incorrect</button>
                <button onclick="submitFeedback(<?= $viewEvent['id'] ?>, 'duplicate')" class="text-purple-400 hover:text-purple-300">Duplicate</button>
                <button onclick="submitFeedback(<?= $viewEvent['id'] ?>, 'already_handled')" class="text-sky-400 hover:text-sky-300">Already handled</button>
            </div>
        </div>
    </div>

    <?php elseif ($viewTab === 'briefing'): ?>
    <!-- ═══ Daily Briefing with Real ERP Data ═══ -->
    <div class="ai-content-card mb-6">
        <h2 class="text-lg font-bold text-slate-200 mb-4">AI Daily Briefing — <?= date('F j, Y') ?></h2>

        <!-- ERP Metrics from actual database -->
        <?php if (!empty($briefing['erp_metrics'])): ?>
        <div class="mb-6">
            <h3 class="text-sm font-semibold text-slate-300 mb-3">ERP Data Summary</h3>
            <div class="ai-grid ai-grid-cols-4">
                <?php if (!empty($briefing['erp_metrics']['finance'])): ?>
                <div class="border border-sky-500/30 rounded-lg p-3" style="background:rgba(56,189,248,0.08);">
                    <div class="text-xs font-semibold text-sky-400 mb-2">Finance</div>
                    <div class="text-xs text-slate-400 space-y-1">
                        <div>Total: $<?= number_format((float)$briefing['erp_metrics']['finance']['total_amount'] ?? 0, 2) ?></div>
                        <div>Outstanding: $<?= number_format((float)$briefing['erp_metrics']['finance']['outstanding_amount'] ?? 0, 2) ?></div>
                        <div class="text-red-400">Overdue: <?= $briefing['erp_metrics']['finance']['overdue_count'] ?? 0 ?> ($<?= number_format((float)$briefing['erp_metrics']['finance']['overdue_amount'] ?? 0, 2) ?>)</div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($briefing['erp_metrics']['procurement'])): ?>
                <div class="border border-emerald-500/30 rounded-lg p-3" style="background:rgba(52,211,153,0.08);">
                    <div class="text-xs font-semibold text-emerald-400 mb-2">Procurement</div>
                    <div class="text-xs text-slate-400 space-y-1">
                        <div>Total POs: <?= $briefing['erp_metrics']['procurement']['total_pos'] ?? 0 ?></div>
                        <div>Pending: $<?= number_format((float)$briefing['erp_metrics']['procurement']['pending_amount'] ?? 0, 2) ?></div>
                        <div class="text-orange-400">Overdue: <?= $briefing['erp_metrics']['procurement']['overdue_count'] ?? 0 ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($briefing['erp_metrics']['inventory'])): ?>
                <div class="border border-amber-500/30 rounded-lg p-3" style="background:rgba(251,191,36,0.08);">
                    <div class="text-xs font-semibold text-amber-400 mb-2">Inventory</div>
                    <div class="text-xs text-slate-400 space-y-1">
                        <div>Products: <?= $briefing['erp_metrics']['inventory']['total_products'] ?? 0 ?></div>
                        <div class="text-red-400">Out of stock: <?= $briefing['erp_metrics']['inventory']['out_of_stock'] ?? 0 ?></div>
                        <div class="text-orange-400">Low stock: <?= $briefing['erp_metrics']['inventory']['low_stock'] ?? 0 ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($briefing['erp_metrics']['contracts'])): ?>
                <div class="border border-purple-500/30 rounded-lg p-3" style="background:rgba(139,92,246,0.08);">
                    <div class="text-xs font-semibold text-purple-400 mb-2">Contracts</div>
                    <div class="text-xs text-slate-400 space-y-1">
                        <div>Active: <?= $briefing['erp_metrics']['contracts']['active'] ?? 0 ?></div>
                        <div class="text-orange-400">Expiring soon: <?= $briefing['erp_metrics']['contracts']['expiring_soon'] ?? 0 ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Priorities -->
        <div class="mb-6">
            <h3 class="text-sm font-semibold text-slate-300 mb-3">Priorities</h3>
            <?php if (empty($briefing['priorities'])): ?>
            <p class="text-sm text-slate-400">No critical or high-priority issues.</p>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($briefing['priorities'] as $p): ?>
                <div class="flex items-center gap-2 p-2 rounded border <?= $p['severity'] === 'critical' ? 'border-red-500/30' : 'border-orange-500/30' ?>" style="background:rgba(15,23,42,0.5);">
                    <span class="px-1.5 py-0.5 text-xs rounded-full <?= $sevColors[$p['severity']] ?? '' ?>"><?= strtoupper(substr($p['severity'], 0, 1)) ?></span>
                    <span class="text-sm text-slate-300"><?= htmlspecialchars($p['title']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recommended Actions -->
        <div class="mb-6">
            <h3 class="text-sm font-semibold text-slate-300 mb-3">Recommended Actions</h3>
            <?php if (empty($briefing['recommended_actions'])): ?>
            <p class="text-sm text-slate-400">No recommended actions at this time.</p>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach (array_slice($briefing['recommended_actions'], 0, 5) as $i => $action): ?>
                <div class="flex items-start gap-2 p-2 border border-emerald-500/30 rounded text-sm" style="background:rgba(52,211,153,0.08);">
                    <span class="text-emerald-400 font-semibold"><?= ($i + 1) ?>.</span>
                    <span class="text-slate-300"><?= htmlspecialchars($action['recommendation']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Role-specific sections -->
        <?php if (!empty($briefing['role_data'])): ?>
        <div class="mb-6">
            <h3 class="text-sm font-semibold text-slate-300 mb-3">Role-Specific Overview</h3>
            <div class="space-y-3">
                <?php foreach ($briefing['role_data'] as $role => $data): ?>
                <div class="border border-slate-700/50 rounded-lg p-3" style="background:rgba(15,23,42,0.5);">
                    <div class="text-xs font-semibold text-slate-400 uppercase mb-2"><?= htmlspecialchars($data['title'] ?? $role) ?></div>
                    <?php if (!empty($data['top_overdue'])): ?>
                    <div class="text-xs text-slate-400">
                        <strong>Top overdue:</strong>
                        <?php foreach (array_slice($data['top_overdue'], 0, 3) as $item): ?>
                        <span class="inline-block border border-red-500/30 text-red-400 px-1.5 py-0.5 rounded mr-1 mb-1" style="background:rgba(248,113,113,0.1);">
                            <?= htmlspecialchars($item['invoice_number'] ?? $item['po_number'] ?? '#') ?>
                            — $<?= number_format((float)($item['amount'] ?? $item['total'] ?? 0), 2) ?>
                            (<?= $item['days_overdue'] ?? '' ?> days)
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($data['top_overdue_pos'])): ?>
                    <div class="text-xs text-slate-400">
                        <strong>Top overdue POs:</strong>
                        <?php foreach (array_slice($data['top_overdue_pos'], 0, 3) as $item): ?>
                        <span class="inline-block border border-orange-500/30 text-orange-400 px-1.5 py-0.5 rounded mr-1 mb-1" style="background:rgba(251,191,36,0.1);">
                            <?= htmlspecialchars($item['po_number'] ?? '#') ?>
                            — $<?= number_format((float)($item['total'] ?? 0), 2) ?>
                            (<?= $item['days_overdue'] ?? '' ?> days)
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($data['low_stock_items'])): ?>
                    <div class="text-xs text-slate-400">
                        <strong>Low stock:</strong>
                        <?php foreach (array_slice($data['low_stock_items'], 0, 3) as $item): ?>
                        <span class="inline-block border border-amber-500/30 text-amber-400 px-1.5 py-0.5 rounded mr-1 mb-1" style="background:rgba(251,191,36,0.1);">
                            <?= htmlspecialchars($item['name'] ?? '') ?> (<?= $item['quantity_on_hand'] ?? 0 ?>/<?= $item['reorder_level'] ?? 0 ?>)
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Full formatted briefing -->
        <div class="mt-4 pt-4 border-t border-slate-700/50">
            <details>
                <summary class="text-sm font-semibold text-slate-300 cursor-pointer">Full Briefing (Plain Text)</summary>
                <pre class="mt-2 whitespace-pre-wrap text-xs text-slate-400 border border-slate-700/50 rounded-lg p-3" style="background:rgba(15,23,42,0.5);"><?= htmlspecialchars(AIBriefingService::formatBriefing($briefing)) ?></pre>
            </details>
        </div>
    </div>

    <?php elseif ($viewTab === 'rules'): ?>
    <!-- ═══ Detection Rules ═══ -->
    <?php if (!hasPermission('ai.intelligence.manage')): ?>
    <div class="ai-alert ai-alert-danger text-center text-sm">
        <i class="fas fa-lock mr-1"></i>Manage intelligence permission required.
    </div>
    <?php else: ?>
    <?php
    $stmt = $db->query("SELECT * FROM ai_intelligence_rules ORDER BY module, name");
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="ai-table-wrapper">
        <table class="ai-data-table">
            <thead>
                <tr>
                    <th>Rule</th>
                    <th>Module</th>
                    <th>Type</th>
                    <th>Severity</th>
                    <th>Last Run</th>
                    <th>Events</th>
                    <th>Enabled</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rules as $rule): ?>
                <tr>
                    <td class="font-medium"><?= htmlspecialchars($rule['name']) ?></td>
                    <td class="text-slate-400"><?= htmlspecialchars($rule['module']) ?></td>
                    <td class="text-slate-400"><?= htmlspecialchars($rule['detection_type']) ?></td>
                    <td><span class="px-2 py-0.5 text-xs rounded-full <?= $sevColors[$rule['severity_default']] ?? '' ?>"><?= ucfirst($rule['severity_default']) ?></span></td>
                    <td class="text-slate-500 text-xs"><?= $rule['last_run_at'] ? date('M j H:i', strtotime($rule['last_run_at'])) : 'Never' ?></td>
                    <td><?= $rule['last_run_events'] ?></td>
                    <td><?= $rule['enabled'] ? 'Yes' : 'No' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php elseif ($viewTab === 'watchlists'): ?>
    <!-- ═══ Watchlists ═══ -->
    <?php $watchlists = AIWatchlistService::getUserWatchlists($db, $userId, $companyId); ?>
    <div class="ai-content-card">
        <h3 class="font-semibold text-slate-200 mb-3">Your Watchlists</h3>
        <?php if (empty($watchlists)): ?>
        <p class="text-slate-400 text-sm">No watchlists yet. Add entities from the ERP to monitor them.</p>
        <?php else: ?>
        <div class="space-y-2">
            <?php foreach ($watchlists as $wl): ?>
            <div class="flex items-center justify-between border border-slate-700/50 rounded-lg p-3">
                <div>
                    <span class="font-medium text-sm text-slate-200"><?= htmlspecialchars($wl['entity_type']) ?></span>
                    <span class="text-slate-400 text-xs ml-2"><?= htmlspecialchars($wl['entity_label'] ?? '#' . $wl['entity_id']) ?></span>
                    <?php if ($wl['last_checked_at']): ?>
                    <span class="text-slate-500 text-xs ml-2">Last checked: <?= date('M j H:i', strtotime($wl['last_checked_at'])) ?></span>
                    <?php endif; ?>
                </div>
                <button onclick="removeWatchlist(<?= $wl['id'] ?>)" class="text-red-400 text-xs hover:text-red-300">Remove</button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- ═══ Event List (Overview / Alerts / Recommendations / Dismissed / History) ═══ -->
    <?php if (empty($eventsData['events'])): ?>
    <div class="ai-content-card text-center py-8">
        <i class="fas fa-check-circle text-4xl text-emerald-500/50 mb-3 block"></i>
        <p class="text-slate-400">No events to display.</p>
    </div>
    <?php else: ?>
    <div class="space-y-3">
        <?php foreach ($eventsData['events'] as $event): ?>
        <a href="?event_id=<?= $event['id'] ?>&tab=<?= $viewTab ?>&role=<?= $userRole ?>" class="block ai-content-card hover:border-sky-500/30 transition-colors">
            <div class="flex items-start gap-3">
                <div class="mt-0.5">
                    <span class="px-2 py-0.5 text-xs rounded-full <?= $sevColors[$event['severity']] ?? 'ai-badge' ?>"><?= strtoupper(substr($event['severity'], 0, 1)) ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="font-medium text-slate-200 text-sm"><?= htmlspecialchars($event['title']) ?></h4>
                    <p class="text-xs text-slate-400 mt-1 line-clamp-1"><?= htmlspecialchars($event['explanation'] ?? '') ?></p>
                    <div class="flex items-center gap-3 mt-1.5 text-xs text-slate-500 flex-wrap">
                        <span><?= date('M j H:i', strtotime($event['created_at'])) ?></span>
                        <?php if ($event['source_module']): ?>
                        <span class="px-1.5 py-0.5 border border-slate-700/50 rounded text-slate-400" style="background:rgba(15,23,42,0.5);"><?= htmlspecialchars($event['source_module']) ?></span>
                        <?php endif; ?>
                        <?php if ($event['recommendation']): ?>
                        <span class="text-emerald-400"><i class="fas fa-lightbulb mr-1"></i>Has recommendation</span>
                        <?php endif; ?>
                        <?php if ($event['related_task_id']): ?>
                        <span class="text-indigo-400"><i class="fas fa-link mr-1"></i>Task linked</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex-shrink-0 text-xs text-slate-500">
                    <?= ucfirst($event['status']) ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
const CSRF = '<?= csrfToken() ?>';
const BASE_URL = '<?= url('') ?>';

async function runDetectors() {
    const btn = document.getElementById('runBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Running...';
    try {
        const resp = await fetch(`${BASE_URL}/modules/ai/api/intelligence.php?action=run_detectors`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF }
        });
        const data = await resp.json();
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play mr-1"></i>Run Detectors';
        if (data.success) {
            const msg = `Detectors complete: ${data.data.events_created} events created, ${data.data.success} succeeded, ${data.data.failed} failed`;
            alert(msg);
            location.reload();
        } else {
            alert(data.error || 'Failed to run detectors');
        }
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play mr-1"></i>Run Detectors';
        alert('Error running detectors: ' + e.message);
    }
}

async function updateEventStatus(eventId, status, reason) {
    await fetch(`${BASE_URL}/modules/ai/api/intelligence.php?action=update_status`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ event_id: eventId, status, reason })
    });
    location.reload();
}

async function createTaskFromEvent(eventId) {
    const resp = await fetch(`${BASE_URL}/modules/ai/api/intelligence.php?action=create_task_from_event`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ event_id: eventId })
    });
    const data = await resp.json();
    if (data.success) {
        window.location.href = `/modules/ai/agents/index.php?tab=tasks&task_id=${data.task_id}`;
    } else {
        alert(data.error || 'Failed to create task');
    }
}

async function submitFeedback(eventId, feedbackType) {
    await fetch(`${BASE_URL}/modules/ai/api/intelligence.php?action=submit_feedback`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ event_id: eventId, feedback_type: feedbackType })
    });
    alert('Feedback submitted. Thank you!');
}

async function removeWatchlist(wlId) {
    if (!confirm('Remove this watchlist?')) return;
    await fetch(`${BASE_URL}/modules/ai/api/intelligence.php?action=remove_watchlist`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ watchlist_id: wlId })
    });
    location.reload();
}
</script>

<?php require_once AI_APP_ROOT . '/includes/footer.php'; ?>
