<?php
/**
 * AI F14 — AI Insights Center.
 *
 * Manage and view AI-generated proactive insights.
 */
$pageTitle = 'AI Insights';
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('ai.insights.view') && !hasPermission('ai.assistant.access')) {
    die('Access Denied');
}

$db = getDB();
$userId = $_SESSION['user_id'] ?? 0;

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $insightId = (int) ($_POST['insight_id'] ?? 0);

    switch ($_POST['action']) {
        case 'mark_seen':
            echo json_encode(AIInsightService::markSeen($db, $insightId, $userId));
            break;
        case 'dismiss':
            echo json_encode(AIInsightService::dismiss($db, $insightId, $userId));
            break;
        case 'feedback':
            echo json_encode(AIInsightService::submitFeedback($db, $insightId, $userId, $_POST['feedback_type'] ?? 'helpful', $_POST['reason'] ?? null));
            break;
        case 'save_prefs':
            $prefs = [
                'insights_enabled' => isset($_POST['insights_enabled']),
                'info_enabled' => isset($_POST['info_enabled']),
                'notice_enabled' => isset($_POST['notice_enabled']),
                'warning_enabled' => isset($_POST['warning_enabled']),
                'critical_enabled' => isset($_POST['critical_enabled']),
                'notification_frequency' => $_POST['notification_frequency'] ?? 'immediate',
            ];
            echo json_encode(AIInsightService::updatePreferences($db, $userId, null, $prefs));
            break;
        default:
            echo json_encode(['success' => false]);
    }
    exit;
}

// Get filter params
$filterStatus = $_GET['status'] ?? 'active';
$filterSeverity = $_GET['severity'] ?? '';
$filterModule = $_GET['module'] ?? '';
$showHistory = isset($_GET['history']);

// Build filters
$filters = [];
if ($filterStatus && !$showHistory) $filters['status'] = $filterStatus;
if ($filterSeverity) $filters['severity'] = $filterSeverity;
if ($filterModule) $filters['module'] = $filterModule;

// Get data
$insights = $showHistory
    ? AIInsightService::getHistory($db, $userId, 100)
    : AIInsightService::getUserInsights($db, $userId, $filters);
$insights = is_array($insights) ? $insights : [];
$counts = AIInsightService::getUserInsightCounts($db, $userId);
$counts = is_array($counts) ? $counts : [];
$counts = array_merge(['critical' => 0, 'warning' => 0, 'notice' => 0, 'info' => 0, 'total' => 0], $counts);
$preferences = AIInsightService::getPreferences($db, $userId);
$preferences = is_array($preferences) ? $preferences : [];
$preferences = array_merge([
    'insights_enabled' => true,
    'info_enabled' => true,
    'notice_enabled' => true,
    'warning_enabled' => true,
    'critical_enabled' => true,
    'notification_frequency' => 'immediate',
], $preferences);

// Severity icons
$severityIcons = ['critical' => '🔴', 'warning' => '⚠️', 'notice' => 'ℹ️', 'info' => '💡'];
$severityColors = [
    'critical' => 'bg-red-500/20 text-red-300 border border-red-500/30',
    'warning' => 'bg-yellow-500/20 text-yellow-300 border border-yellow-500/30',
    'notice' => 'bg-sky-500/20 text-sky-300 border border-sky-500/30',
    'info' => 'bg-slate-500/20 text-slate-300 border border-slate-500/30',
];
?>

<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>
<?php ai_render_module_nav('ai.insights'); ?>
<?php ai_render_page_styles(); ?>

<div class="ai-page-header">
    <div class="flex justify-between items-start flex-wrap gap-4">
        <div>
            <h1 class="ai-page-title">
                <span class="ai-page-icon"><i class="fas fa-lightbulb"></i></span>
                AI Insights
            </h1>
            <p class="ai-page-subtitle">Proactive AI-powered notifications and intelligence</p>
        </div>
        <div class="flex gap-2">
            <button onclick="document.getElementById('prefsModal').classList.remove('hidden')" class="bg-slate-700 border border-slate-600 text-slate-200 px-3 py-2 rounded-lg hover:bg-slate-600 text-sm transition-colors">
                <i class="fas fa-cog mr-1"></i>Preferences
            </button>
            <a href="?history=1" class="bg-slate-700 border border-slate-600 text-slate-200 px-3 py-2 rounded-lg hover:bg-slate-600 text-sm transition-colors <?= $showHistory ? 'bg-slate-600' : '' ?>">
                <i class="fas fa-history mr-1"></i>History
            </a>
        </div>
    </div>
</div>

<div class="ai-page-container">
<nav class="flex flex-wrap gap-2 mb-5" aria-label="AI navigation">
    <a href="<?= url('modules/ai/assistant/index.php') ?>" class="ai-content-card px-3 py-2 text-sm text-slate-200 transition-colors">
        <i class="fas fa-robot mr-1 text-blue-400"></i>AI Assistant
    </a>
    <a href="<?= url('modules/ai/insights/index.php') ?>" class="ai-content-card px-3 py-2 text-sm text-sky-300 font-medium transition-colors">
        <i class="fas fa-lightbulb mr-1"></i>Insights
    </a>
    <a href="<?= url('modules/ai/automation/index.php') ?>" class="ai-content-card px-3 py-2 text-sm text-slate-200 transition-colors">
        <i class="fas fa-bolt mr-1 text-yellow-400"></i>Automation
    </a>
    <a href="<?= url('modules/ai/agents/index.php') ?>" class="ai-content-card px-3 py-2 text-sm text-slate-200 transition-colors">
        <i class="fas fa-user-cog mr-1 text-green-400"></i>Agents
    </a>
    <a href="<?= url('modules/ai/knowledge/index.php') ?>" class="ai-content-card px-3 py-2 text-sm text-slate-200 transition-colors">
        <i class="fas fa-book mr-1 text-orange-400"></i>Knowledge
    </a>
</nav>

<!-- Summary Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <div class="ai-content-card border-l-4 border-l-red-500 text-center">
        <div class="text-3xl font-bold text-red-400"><?= $counts['critical'] ?></div>
        <div class="text-sm text-red-300">Critical</div>
    </div>
    <div class="ai-content-card border-l-4 border-l-yellow-500 text-center">
        <div class="text-3xl font-bold text-yellow-400"><?= $counts['warning'] ?></div>
        <div class="text-sm text-yellow-300">Warnings</div>
    </div>
    <div class="ai-content-card border-l-4 border-l-blue-500 text-center">
        <div class="text-3xl font-bold text-blue-400"><?= $counts['notice'] ?></div>
        <div class="text-sm text-blue-300">Notices</div>
    </div>
    <div class="ai-content-card border-l-4 border-l-emerald-500 text-center">
        <div class="text-3xl font-bold text-emerald-400"><?= $counts['total'] ?></div>
        <div class="text-sm text-emerald-300">Total Active</div>
    </div>
</div>

<!-- Filters -->
<div class="ai-content-card p-3 mb-4 flex flex-wrap gap-3 items-center">
    <span class="text-sm font-medium text-slate-300">Filter:</span>
    <a href="?" class="px-3 py-1 rounded text-sm <?= !$filterSeverity && !$showHistory ? 'bg-sky-600 text-white' : 'bg-slate-700 hover:bg-slate-600 text-slate-200' ?>">All</a>
    <a href="?severity=critical" class="px-3 py-1 rounded text-sm <?= $filterSeverity === 'critical' ? 'bg-red-600 text-white' : 'bg-slate-700 hover:bg-slate-600 text-slate-200' ?>">🔴 Critical</a>
    <a href="?severity=warning" class="px-3 py-1 rounded text-sm <?= $filterSeverity === 'warning' ? 'bg-yellow-600 text-white' : 'bg-slate-700 hover:bg-slate-600 text-slate-200' ?>">⚠️ Warning</a>
    <a href="?severity=notice" class="px-3 py-1 rounded text-sm <?= $filterSeverity === 'notice' ? 'bg-sky-600 text-white' : 'bg-slate-700 hover:bg-slate-600 text-slate-200' ?>">ℹ️ Notice</a>
    <a href="?severity=info" class="px-3 py-1 rounded text-sm <?= $filterSeverity === 'info' ? 'bg-slate-600 text-white' : 'bg-slate-700 hover:bg-slate-600 text-slate-200' ?>">💡 Info</a>
    <span class="text-slate-600">|</span>
    <a href="?history=1" class="px-3 py-1 rounded text-sm <?= $showHistory ? 'bg-purple-600 text-white' : 'bg-slate-700 hover:bg-slate-600 text-slate-200' ?>">📋 History</a>
</div>

<!-- Insights List -->
<?php if (empty($insights)): ?>
<div class="ai-content-card p-8 text-center">
    <div class="bg-emerald-500/20 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-check text-emerald-400 text-2xl"></i>
    </div>
    <h2 class="text-xl font-semibold text-slate-100 mb-2">
        <?= $showHistory ? 'No History Yet' : 'All Clear!' ?>
    </h2>
    <p class="text-slate-400">
        <?= $showHistory ? 'No insights have been generated yet.' : 'No active insights. The system is monitoring your ERP data proactively.' ?>
    </p>
</div>
<?php else: ?>
<div class="space-y-3">
    <?php foreach ($insights as $insight):
        $sev = $insight['severity'] ?? 'info';
        $nav = json_decode($insight['navigation_config'] ?? '{}', true);
        $evidence = json_decode($insight['evidence_json'] ?? '{}', true);
        $isSeen = ($insight['recipient_status'] ?? '') !== 'pending';
    ?>
    <div class="ai-content-card p-4 insight-card <?= !$isSeen ? 'border-l-4 border-l-sky-500' : '' ?>" data-id="<?= $insight['id'] ?>">
        <div class="flex justify-between items-start mb-2">
            <div class="flex items-center gap-2">
                <span class="text-lg"><?= $severityIcons[$sev] ?? '📋' ?></span>
                <h3 class="font-semibold text-slate-100"><?= htmlspecialchars($insight['title']) ?></h3>
                <?php if (!$isSeen): ?>
                    <span class="px-2 py-0.5 rounded-full text-xs bg-sky-500/20 text-sky-300 font-medium border border-sky-500/30">New</span>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-400">
                <span class="px-2 py-0.5 rounded <?= $severityColors[$sev] ?? 'bg-slate-500/20 text-slate-300 border border-slate-500/30' ?>"><?= ucfirst($sev) ?></span>
                <span><?= htmlspecialchars($insight['def_name'] ?? '') ?></span>
                <span><?= date('M j, H:i', strtotime($insight['created_at'])) ?></span>
            </div>
        </div>

        <p class="text-sm text-slate-300 mb-3"><?= htmlspecialchars($insight['summary'] ?? '') ?></p>

        <?php if (!empty($insight['ai_narrative'])): ?>
        <div class="bg-purple-500/10 border border-purple-500/30 rounded p-2 mb-3 text-sm text-purple-300">
            <i class="fas fa-robot mr-1"></i><?= htmlspecialchars($insight['ai_narrative']) ?>
        </div>
        <?php endif; ?>

        <!-- Evidence -->
        <?php if (!empty($evidence)): ?>
        <div class="bg-slate-700/50 rounded p-2 mb-3 text-xs text-slate-400">
            <strong>Evidence:</strong>
            <?php if (isset($evidence['records_evaluated'])): ?> <?= $evidence['records_evaluated'] ?> records evaluated.<?php endif; ?>
            <?php if (isset($evidence['details']['count'])): ?> Count: <?= $evidence['details']['count'] ?>.<?php endif; ?>
            <?php if (isset($evidence['details']['old_count'])): ?> <?= $evidence['details']['old_count'] ?> items older than <?= $evidence['details']['threshold_days'] ?? '' ?> days.<?php endif; ?>
            <?php if (isset($evidence['details']['concentration'])): ?> <?= $evidence['details']['concentration']['percentage'] ?>% in <?= htmlspecialchars($evidence['details']['concentration']['group'] ?? '') ?>.<?php endif; ?>
            <?php if (isset($evidence['evaluated_at'])): ?> Evaluated: <?= $evidence['evaluated_at'] ?>.<?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="flex justify-between items-center pt-2 border-t border-slate-700">
            <div class="flex gap-2">
                <?php if (!empty($nav['route'])): ?>
                <a href="<?= url($nav['route']) ?>" class="text-sm text-sky-400 hover:text-sky-300 hover:underline">
                    <i class="fas fa-external-link-alt mr-1"></i><?= htmlspecialchars($nav['label'] ?? 'View Details') ?>
                </a>
                <?php endif; ?>
                <?php if (!$isSeen): ?>
                <button onclick="markSeen(<?= $insight['id'] ?>)" class="text-sm text-slate-400 hover:text-slate-200">
                    <i class="fas fa-eye mr-1"></i>Mark as Seen
                </button>
                <?php endif; ?>
            </div>
            <div class="flex gap-2">
                <button onclick="submitFeedback(<?= $insight['id'] ?>, 'helpful')" class="text-xs text-emerald-400 hover:text-emerald-300" title="Helpful">👍</button>
                <button onclick="submitFeedback(<?= $insight['id'] ?>, 'not_helpful')" class="text-xs text-red-400 hover:text-red-300" title="Not Helpful">👎</button>
                <button onclick="dismissInsight(<?= $insight['id'] ?>)" class="text-xs text-slate-400 hover:text-slate-200" title="Dismiss">✕</button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Preferences Modal -->
<div id="prefsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="ai-content-card rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-slate-100">Insight Preferences</h3>
            <button onclick="document.getElementById('prefsModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="prefsForm" onsubmit="savePrefs(event)">
            <div class="space-y-3">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="insights_enabled" <?= $preferences['insights_enabled'] ? 'checked' : '' ?>>
                    <span class="text-sm text-slate-200">Enable AI Insights</span>
                </label>
                <hr class="border-slate-700">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="info_enabled" <?= $preferences['info_enabled'] ? 'checked' : '' ?>>
                    <span class="text-sm text-slate-200">💡 Informational Insights</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="notice_enabled" <?= $preferences['notice_enabled'] ? 'checked' : '' ?>>
                    <span class="text-sm text-slate-200">ℹ️ Notice Insights</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="warning_enabled" <?= $preferences['warning_enabled'] ? 'checked' : '' ?>>
                    <span class="text-sm text-slate-200">⚠️ Warning Insights</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="critical_enabled" <?= $preferences['critical_enabled'] ? 'checked' : '' ?>>
                    <span class="text-sm text-slate-200">🔴 Critical Insights</span>
                </label>
                <hr class="border-slate-700">
                <div>
                    <label class="block text-sm font-medium mb-1 text-slate-300">Notification Frequency</label>
                    <select name="notification_frequency" class="w-full border border-slate-600 bg-slate-800 text-slate-200 rounded px-3 py-2 text-sm">
                        <option value="immediate" <?= $preferences['notification_frequency'] === 'immediate' ? 'selected' : '' ?>>Immediate</option>
                        <option value="hourly_digest" <?= $preferences['notification_frequency'] === 'hourly_digest' ? 'selected' : '' ?>>Hourly Digest</option>
                        <option value="daily_digest" <?= $preferences['notification_frequency'] === 'daily_digest' ? 'selected' : '' ?>>Daily Digest</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button type="button" onclick="document.getElementById('prefsModal').classList.add('hidden')" class="px-4 py-2 rounded border border-slate-600 text-slate-200 text-sm hover:bg-slate-700">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded bg-sky-600 text-white text-sm hover:bg-sky-500">Save Preferences</button>
            </div>
        </form>
    </div>
</div>

<script>
function markSeen(id) {
    const form = new FormData();
    form.append('action', 'mark_seen');
    form.append('insight_id', id);
    fetch('', { method: 'POST', body: form })
        .then(r => r.json())
        .then(() => location.reload());
}

function dismissInsight(id) {
    if (!confirm('Dismiss this insight?')) return;
    const form = new FormData();
    form.append('action', 'dismiss');
    form.append('insight_id', id);
    fetch('', { method: 'POST', body: form })
        .then(r => r.json())
        .then(() => location.reload());
}

function submitFeedback(id, type) {
    const form = new FormData();
    form.append('action', 'feedback');
    form.append('insight_id', id);
    form.append('feedback_type', type);
    fetch('', { method: 'POST', body: form })
        .then(r => r.json())
        .then(d => {
            if (d.success) alert('Thank you for your feedback!');
        });
}

function savePrefs(e) {
    e.preventDefault();
    const form = new FormData(document.getElementById('prefsForm'));
    form.append('action', 'save_prefs');
    fetch('', { method: 'POST', body: form })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                document.getElementById('prefsModal').classList.add('hidden');
                location.reload();
            } else {
                alert('Failed to save: ' + (d.error || 'Unknown error'));
            }
        });
}
</script>

</div>

<?php require_once AI_APP_ROOT . '/templates/footer.php'; ?>
