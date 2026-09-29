<?php
/**
 * AI F13 — Automation UI.
 *
 * Full management interface for:
 * - Automation list with safety badges
 * - Schedule configuration (interval, daily, weekly)
 * - Event trigger configuration
 * - Run history with step timeline
 * - Notifications panel
 * - Event log viewer
 */
$pageTitle = 'AI Automation';
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('ai.automation.view') && !hasPermission('ai.monitoring.view')) {
    die('Access Denied');
}

$db = getDB();
$userId = $_SESSION['user_id'] ?? 0;
$action = $_GET['action'] ?? 'list';
$automationId = isset($_GET['id']) ? (int) $_GET['id'] : null;

// Handle API actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_action'])) {
    header('Content-Type: application/json');
    $apiAction = $_POST['api_action'];

    switch ($apiAction) {
        case 'set_trigger':
            $result = AIAutomationService::setTrigger(
                $db, $automationId, $userId,
                $_POST['trigger_type'],
                json_decode($_POST['config_json'] ?? '{}', true)
            );
            echo json_encode($result);
            break;

        case 'toggle_trigger':
            $result = AIAutomationService::toggleTrigger(
                $db, $automationId, $userId,
                $_POST['trigger_type'],
                ($_POST['enabled'] ?? '0') === '1'
            );
            echo json_encode($result);
            break;

        case 'manual_run':
            $result = AIAutomationService::manualRun($db, $automationId, $userId, []);
            echo json_encode($result);
            break;

        case 'get_run_steps':
            $runId = (int) ($_POST['run_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM ai_automation_steps WHERE run_id = ? ORDER BY step_number");
            $stmt->execute([$runId]);
            echo json_encode($stmt->fetchAll());
            break;

        case 'mark_notification_read':
            AIAutomationSchedulerService::markRead($db, (int) $_POST['notification_id'], $userId);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
    exit;
}

// Get notifications
$notifications = AIAutomationSchedulerService::getNotifications($db, $userId);
$notifications = is_array($notifications) ? $notifications : [];
$unreadCount = count($notifications);
?>

<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>
<?php ai_render_module_nav('ai.automation'); ?>
<?php ai_render_page_styles(); ?>

<div class="ai-page-header">
    <div class="flex justify-between items-start flex-wrap gap-4">
        <div>
            <h1 class="ai-page-title">
                <span class="ai-page-icon"><i class="fas fa-bolt"></i></span>
                AI Automation
            </h1>
            <p class="ai-page-subtitle">Manage workflows, triggers, schedules, and event-driven automations</p>
        </div>
        <div class="flex gap-2">
            <?php if ($unreadCount > 0): ?>
            <button onclick="toggleNotifications()" class="bg-slate-700 border border-slate-600 text-slate-200 px-3 py-2 rounded-lg hover:bg-slate-600 text-sm relative transition-colors">
                <i class="fas fa-bell text-yellow-400"></i>
                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center"><?= $unreadCount ?></span>
            </button>
            <?php endif; ?>
            <?php if (hasPermission('ai.automation.create')): ?>
            <a href="?action=create" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-500 text-sm transition-colors">
                <i class="fas fa-plus mr-1"></i>New Automation
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="ai-page-container">

<nav class="flex flex-wrap gap-2 mb-5" aria-label="AI navigation">

<!-- Notifications Panel (hidden by default) -->
<div id="notificationsPanel" class="hidden fixed top-16 right-4 bg-slate-800 border border-slate-600 rounded-lg shadow-xl z-50 w-80 max-h-96 overflow-y-auto">
    <div class="p-3 border-b bg-slate-700/40 rounded-t-lg flex justify-between items-center">
        <h3 class="font-semibold text-sm">Notifications</h3>
        <button onclick="toggleNotifications()" class="text-slate-500 hover:text-slate-400"><i class="fas fa-times"></i></button>
    </div>
    <div id="notificationsList">
        <?php if (empty($notifications)): ?>
            <div class="p-4 text-center text-slate-500 text-sm">No new notifications</div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
            <div class="p-3 border-b hover:bg-slate-700/40 notification-item" data-id="<?= $notif['id'] ?>">
                <div class="text-sm font-medium text-slate-100"><?= htmlspecialchars($notif['title']) ?></div>
                <div class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($notif['message'] ?? '') ?></div>
                <div class="text-xs text-slate-500 mt-1"><?= date('M j, H:i', strtotime($notif['created_at'])) ?></div>
                <button onclick="markRead(<?= $notif['id'] ?>)" class="text-xs text-blue-400 hover:underline mt-1">Mark read</button>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'list'): ?>
<!-- Automation List -->
<?php
$userPerms = [];
$automations = AIAutomationService::getUserAutomations($db, $userId);
?>

<?php if (empty($automations)): ?>
<div class="ai-content-card text-center">
    <div class="bg-yellow-500/15 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-bolt text-yellow-400 text-2xl"></i>
    </div>
    <h2 class="text-xl font-semibold text-slate-100 mb-2">No Automations Yet</h2>
    <p class="text-slate-400 mb-6 max-w-md mx-auto">Create your first automation to schedule tasks, react to ERP events, or build workflows.</p>
    <?php if (hasPermission('ai.automation.create')): ?>
    <a href="?action=create" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-500 inline-block transition-colors">
        <i class="fas fa-plus mr-2"></i>Create Automation
    </a>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($automations as $auto):
        $triggers = AIAutomationService::getTriggers($db, $auto['id']);
        $safety = $auto['safety_classification'] ?? 'read_only';
        $stats = AIAutomationService::getRunStats($db, $auto['id']);
        $totalRuns = array_sum($stats);
    ?>
    <div class="ai-content-card hover:shadow-lg transition-shadow">
        <div class="flex justify-between items-start mb-3">
            <h3 class="font-semibold text-slate-100 truncate"><?= htmlspecialchars($auto['name']) ?></h3>
            <span class="px-2 py-1 rounded text-xs font-medium <?= $auto['is_active'] ? 'bg-emerald-500/15 text-emerald-400' : 'bg-slate-700/50 text-slate-500' ?>">
                <?= $auto['is_active'] ? 'Active' : 'Inactive' ?>
            </span>
        </div>
        <?php if ($auto['description']): ?>
            <p class="text-sm text-slate-400 mb-3 line-clamp-2"><?= htmlspecialchars($auto['description']) ?></p>
        <?php endif; ?>

        <!-- Triggers -->
        <div class="flex flex-wrap gap-1 mb-3">
            <?php foreach ($triggers as $trigger): ?>
            <span class="px-2 py-0.5 rounded text-xs <?= $trigger['enabled'] ? 'bg-blue-500/15 text-blue-400' : 'bg-slate-700/50 text-slate-500' ?>">
                <i class="fas fa-<?= $trigger['trigger_type'] === 'scheduled' ? 'clock' : ($trigger['trigger_type'] === 'event' ? 'zap' : 'play') ?> mr-1"></i>
                <?= ucfirst($trigger['trigger_type']) ?>
            </span>
            <?php endforeach; ?>
            <?php if (empty($triggers)): ?>
            <span class="px-2 py-0.5 rounded text-xs bg-slate-700/50 text-slate-500">Manual only</span>
            <?php endif; ?>
        </div>

        <!-- Safety Badge -->
        <div class="flex items-center gap-2 mb-3">
            <span class="px-2 py-0.5 rounded text-xs font-medium <?= $safety === 'read_only' ? 'bg-emerald-500/15 text-emerald-400' : ($safety === 'confirmation_required' ? 'bg-yellow-500/15 text-yellow-400' : 'bg-red-500/15 text-red-400') ?>">
                <i class="fas fa-<?= $safety === 'read_only' ? 'lock-open' : ($safety === 'confirmation_required' ? 'shield-alt' : 'ban') ?> mr-1"></i>
                <?= ucfirst(str_replace('_', ' ', $safety)) ?>
            </span>
        </div>

        <!-- Stats -->
        <div class="text-xs text-slate-500 flex justify-between">
            <span><?= $totalRuns ?> run<?= $totalRuns !== 1 ? 's' : '' ?></span>
            <span>Last: <?= $auto['last_run_at'] ? date('M j, H:i', strtotime($auto['last_run_at'])) : 'Never' ?></span>
        </div>

        <!-- Actions -->
        <div class="flex gap-2 mt-3 pt-3 border-t">
            <a href="?action=view&id=<?= $auto['id'] ?>" class="flex-1 text-center bg-slate-700/50 text-slate-200 px-3 py-1.5 rounded text-xs hover:bg-slate-600">
                <i class="fas fa-eye mr-1"></i>View
            </a>
            <?php if (hasPermission('ai.automation.execute')): ?>
            <form method="post" class="flex-1">
                <input type="hidden" name="api_action" value="manual_run">
                <button type="submit" class="w-full bg-blue-500/15 text-blue-400 px-3 py-1.5 rounded text-xs hover:bg-blue-500/25">
                    <i class="fas fa-play mr-1"></i>Run
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($action === 'view' && $automationId): ?>
<!-- Automation Detail / Config -->
<?php
$auto = AIAutomationService::getAutomationById($db, $automationId, $userId);
if (!$auto) { echo '<div class="ai-content-card text-center"><p class="text-red-400">Automation not found.</p></div>'; require_once AI_APP_ROOT . '/templates/footer.php'; exit; }
$triggers = AIAutomationService::getTriggers($db, $automationId);
$safety = $auto['safety_classification'] ?? 'read_only';
$workflow = json_decode($auto['workflow_json'] ?? '{}', true);
$runHistory = AIAutomationService::getRunHistory($db, $automationId, 20);
$events = AIEventBusService::getEventsWithSubscriptions($db);
$runningEvents = array_filter($events, fn($e) => in_array($e['module'], ['procurement', 'finance', 'hr', 'inventory']));
?>

<div class="mb-4">
    <a href="?" class="text-blue-400 hover:underline text-sm"><i class="fas fa-arrow-left mr-1"></i>Back to Automations</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left: Config -->
    <div class="lg:col-span-2 space-y-4">
        <!-- Header -->
        <div class="ai-content-card">
            <div class="flex justify-between items-start">
                <div>
                    <h2 class="text-xl font-bold text-slate-100"><?= htmlspecialchars($auto['name']) ?></h2>
                    <?php if ($auto['description']): ?>
                        <p class="text-slate-400 text-sm mt-1"><?= htmlspecialchars($auto['description']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="flex gap-2">
                    <span class="px-3 py-1 rounded-full text-sm font-medium <?= $safety === 'read_only' ? 'bg-emerald-500/15 text-emerald-400' : ($safety === 'confirmation_required' ? 'bg-yellow-500/15 text-yellow-400' : 'bg-red-500/15 text-red-400') ?>">
                        <?= ucfirst(str_replace('_', ' ', $safety)) ?>
                    </span>
                    <span class="px-3 py-1 rounded-full text-sm <?= $auto['is_active'] ? 'bg-emerald-500/15 text-emerald-400' : 'bg-slate-700/50 text-slate-500' ?>">
                        <?= $auto['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Schedule Configuration -->
        <div class="ai-content-card">
            <h3 class="font-semibold text-slate-100 mb-3"><i class="fas fa-clock text-blue-500 mr-2"></i>Schedule Configuration</h3>

            <?php
            $scheduleTrigger = null;
            foreach ($triggers as $t) { if ($t['trigger_type'] === 'scheduled') { $scheduleTrigger = $t; break; } }
            $scheduleConfig = $scheduleTrigger ? json_decode($scheduleTrigger['config_json'] ?? '{}', true) : [];
            ?>

            <div id="scheduleConfig">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-200 mb-1">Schedule Type</label>
                        <select id="scheduleType" class="w-full border border-slate-600 bg-slate-700/60 text-slate-200 rounded-lg px-3 py-2 text-sm" onchange="updateScheduleUI()">
                            <option value="none" <?= ($scheduleConfig['schedule_type'] ?? 'none') === 'none' ? 'selected' : '' ?>>No Schedule</option>
                            <option value="interval" <?= ($scheduleConfig['schedule_type'] ?? '') === 'interval' ? 'selected' : '' ?>>Interval</option>
                            <option value="daily" <?= ($scheduleConfig['schedule_type'] ?? '') === 'daily' ? 'selected' : '' ?>>Daily</option>
                            <option value="weekly" <?= ($scheduleConfig['schedule_type'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                        </select>
                    </div>

                    <div id="intervalConfig" class="<?= ($scheduleConfig['schedule_type'] ?? '') !== 'interval' ? 'hidden' : '' ?>">
                        <label class="block text-xs font-medium text-slate-200 mb-1">Interval (minutes)</label>
                        <input type="number" id="intervalMinutes" value="<?= $scheduleConfig['interval_minutes'] ?? 60 ?>" min="5" max="1440" class="w-full border border-slate-600 bg-slate-700/60 text-slate-200 rounded-lg px-3 py-2 text-sm">
                    </div>

                    <div id="timeConfig" class="<?= !in_array($scheduleConfig['schedule_type'] ?? '', ['daily', 'weekly']) ? 'hidden' : '' ?>">
                        <label class="block text-xs font-medium text-slate-200 mb-1">Time</label>
                        <input type="time" id="scheduleTime" value="<?= $scheduleConfig['time'] ?? '09:00' ?>" class="w-full border border-slate-600 bg-slate-700/60 text-slate-200 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                    <div id="dayOfWeekConfig" class="<?= ($scheduleConfig['schedule_type'] ?? '') !== 'weekly' ? 'hidden' : '' ?>">
                        <label class="block text-xs font-medium text-slate-200 mb-1">Day of Week</label>
                        <select id="dayOfWeek" class="w-full border border-slate-600 bg-slate-700/60 text-slate-200 rounded-lg px-3 py-2 text-sm">
                            <?php foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day): ?>
                            <option value="<?= $day ?>" <?= ($scheduleConfig['day_of_week'] ?? '') === $day ? 'selected' : '' ?>><?= ucfirst($day) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-200 mb-1">Timezone</label>
                        <select id="scheduleTimezone" class="w-full border border-slate-600 bg-slate-700/60 text-slate-200 rounded-lg px-3 py-2 text-sm">
                            <?php foreach (['UTC', 'Asia/Dubai', 'America/New_York', 'Europe/London', 'Asia/Tokyo'] as $tz): ?>
                            <option value="<?= $tz ?>" <?= ($scheduleConfig['timezone'] ?? 'UTC') === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <div class="text-xs text-slate-500">
                        <?php if ($auto['next_run_at']): ?>
                        <i class="fas fa-calendar mr-1"></i>Next run: <?= date('M j, Y H:i', strtotime($auto['next_run_at'])) ?>
                        <?php endif; ?>
                    </div>
                    <button onclick="saveSchedule(<?= $automationId ?>)" class="bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm hover:bg-blue-500">
                        <i class="fas fa-save mr-1"></i>Save Schedule
                    </button>
                </div>
            </div>
        </div>

        <!-- Event Trigger Configuration -->
        <div class="ai-content-card">
            <h3 class="font-semibold text-slate-100 mb-3"><i class="fas fa-zap text-yellow-400 mr-2"></i>Event Triggers</h3>

            <?php
            $eventTrigger = null;
            foreach ($triggers as $t) { if ($t['trigger_type'] === 'event') { $eventTrigger = $t; break; } }
            $eventConfig = $eventTrigger ? json_decode($eventTrigger['config_json'] ?? '{}', true) : [];
            ?>

            <div class="mb-3">
                <label class="block text-xs font-medium text-slate-200 mb-1">Trigger on Event</label>
                <select id="eventName" class="w-full border border-slate-600 bg-slate-700/60 text-slate-200 rounded-lg px-3 py-2 text-sm">
                    <option value="">No event trigger</option>
                    <?php foreach ($runningEvents as $evt): ?>
                    <option value="<?= $evt['event_name'] ?>" <?= ($eventConfig['event_name'] ?? '') === $evt['event_name'] ? 'selected' : '' ?>>
                        <?= $evt['event_name'] ?> (<?= $evt['module'] ?>) — <?= $evt['subscription_count'] ?> subs
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="block text-xs font-medium text-slate-200 mb-1">Filter (JSON, optional)</label>
                <input type="text" id="eventFilter" value="<?= htmlspecialchars($eventConfig['filter'] ? json_encode($eventConfig['filter']) : '') ?>" placeholder='{"status": "pending"}' class="w-full border border-slate-600 bg-slate-700/60 text-slate-200 rounded-lg px-3 py-2 text-sm font-mono">
            </div>

            <div class="flex justify-between items-center">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" id="eventEnabled" <?= $eventTrigger && $eventTrigger['enabled'] ? 'checked' : '' ?>>
                    <span>Enable event trigger</span>
                </label>
                <button onclick="saveEventTrigger(<?= $automationId ?>)" class="bg-yellow-500 text-white px-4 py-1.5 rounded-lg text-sm hover:bg-yellow-400">
                    <i class="fas fa-save mr-1"></i>Save Event Trigger
                </button>
            </div>
        </div>

        <!-- Workflow Steps -->
        <div class="ai-content-card">
            <h3 class="font-semibold text-slate-100 mb-3"><i class="fas fa-project-diagram text-purple-400 mr-2"></i>Workflow Steps</h3>
            <?php $steps = $workflow['steps'] ?? []; ?>
            <?php if (empty($steps)): ?>
                <p class="text-slate-500 text-sm">No workflow steps defined.</p>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($steps as $i => $step): ?>
                <div class="flex items-center gap-3 p-2 bg-slate-700/40 rounded">
                    <span class="w-6 h-6 bg-purple-500/15 text-purple-400 rounded-full flex items-center justify-center text-xs font-bold"><?= $i + 1 ?></span>
                    <span class="px-2 py-0.5 rounded text-xs <?= $step['type'] === 'read' ? 'bg-blue-500/15 text-blue-400' : ($step['type'] === 'action' ? 'bg-yellow-500/15 text-yellow-400' : 'bg-slate-700/50 text-slate-200') ?>">
                        <?= ucfirst($step['type']) ?>
                    </span>
                    <span class="text-sm text-slate-200">
                        <?= htmlspecialchars($step[$step['type'] . '_id'] ?? $step['tool_id'] ?? $step['action_id'] ?? 'N/A') ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: Runs + Notifications -->
    <div class="space-y-4">
        <!-- Run History -->
        <div class="ai-content-card">
            <h3 class="font-semibold text-slate-100 mb-3"><i class="fas fa-history text-emerald-400 mr-2"></i>Run History</h3>
            <?php if (empty($runHistory)): ?>
                <p class="text-slate-500 text-sm">No runs yet.</p>
            <?php else: ?>
            <div class="space-y-2 max-h-96 overflow-y-auto">
                <?php foreach ($runHistory as $run): ?>
                <div class="p-2 border border-slate-600/40 rounded-lg cursor-pointer hover:bg-slate-700/40 run-item" data-run-id="<?= $run['id'] ?>">
                    <div class="flex justify-between items-center">
                        <span class="px-2 py-0.5 rounded text-xs <?= $run['status'] === 'completed' ? 'bg-emerald-500/15 text-emerald-400' : ($run['status'] === 'running' ? 'bg-blue-500/15 text-blue-400' : ($run['status'] === 'failed' ? 'bg-red-500/15 text-red-400' : 'bg-slate-700/50 text-slate-500')) ?>">
                            <?= ucfirst($run['status']) ?>
                        </span>
                        <span class="text-xs text-slate-500"><?= date('M j H:i', strtotime($run['created_at'])) ?></span>
                    </div>
                    <div class="text-xs text-slate-500 mt-1">
                        <i class="fas fa-<?= $run['trigger_type'] === 'scheduled' ? 'clock' : ($run['trigger_type'] === 'event' ? 'zap' : 'play') ?> mr-1"></i>
                        <?= ucfirst($run['trigger_type'] ?? 'manual') ?>
                        <?php if ($run['event_name']): ?>
                            — <?= $run['event_name'] ?>
                        <?php endif; ?>
                    </div>
                    <!-- Steps will be loaded here -->
                    <div id="run-steps-<?= $run['id'] ?>" class="mt-2 hidden"></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Notifications -->
        <div class="ai-content-card">
            <h3 class="font-semibold text-slate-100 mb-3"><i class="fas fa-bell text-yellow-400 mr-2"></i>Recent Notifications</h3>
            <?php if (empty($notifications)): ?>
                <p class="text-slate-500 text-sm">No notifications.</p>
            <?php else: ?>
            <div class="space-y-2 max-h-64 overflow-y-auto">
                <?php foreach (array_slice($notifications, 0, 10) as $notif): ?>
                <div class="p-2 border border-slate-600/40 rounded-lg text-sm">
                    <div class="font-medium text-slate-100"><?= htmlspecialchars($notif['title']) ?></div>
                    <div class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($notif['message'] ?? '') ?></div>
                    <div class="text-xs text-slate-500 mt-1"><?= date('M j H:i', strtotime($notif['created_at'])) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php elseif ($action === 'events'): ?>
<!-- Event Log Viewer -->
<?php
$recentEvents = AIEventBusService::getRecentEvents($db, 100);
$stats = AIEventBusService::getEventStats($db, 24);
?>

<div class="mb-4">
    <a href="?" class="text-blue-400 hover:underline text-sm"><i class="fas fa-arrow-left mr-1"></i>Back to Automations</a>
</div>

<div class="ai-content-card mb-4">
    <h2 class="text-lg font-bold text-slate-100 mb-3"><i class="fas fa-stream text-blue-500 mr-2"></i>Event Log</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
        <div class="bg-blue-500/10 border border-blue-400/20 rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-blue-400"><?= $stats['total_events'] ?></div>
            <div class="text-xs text-slate-400">Events (24h)</div>
        </div>
        <div class="bg-emerald-500/10 border border-emerald-400/20 rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-emerald-400"><?= $stats['triggered'] ?></div>
            <div class="text-xs text-slate-400">Triggered</div>
        </div>
        <div class="bg-red-500/10 border border-red-400/20 rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-red-400"><?= $stats['failed'] ?></div>
            <div class="text-xs text-slate-400">Failed</div>
        </div>
        <div class="bg-slate-700/40 rounded p-3 text-center">
            <div class="text-2xl font-bold text-slate-400"><?= count($stats['by_module']) ?></div>
            <div class="text-xs text-slate-400">Modules</div>
        </div>
    </div>
</div>

<div class="ai-content-card overflow-hidden">
    <table class="min-w-full divide-y divide-slate-600/40 text-sm">
        <thead class="bg-slate-700/40">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Event</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Record</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Processed</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Time</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-600/40">
            <?php foreach ($recentEvents as $evt): ?>
            <tr class="hover:bg-slate-700/40">
                <td class="px-4 py-2">
                    <span class="font-mono text-xs"><?= htmlspecialchars($evt['event_name']) ?></span>
                </td>
                <td class="px-4 py-2 text-xs text-slate-400">
                    <?php if ($evt['record_id']): ?>
                        <?= $evt['record_type'] ?? '' ?> #<?= $evt['record_id'] ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td class="px-4 py-2">
                    <?php if ($evt['processed']): ?>
                        <span class="text-emerald-400"><i class="fas fa-check"></i></span>
                    <?php else: ?>
                        <span class="text-red-400"><i class="fas fa-times"></i></span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-2 text-xs text-slate-500"><?= date('M j H:i:s', strtotime($evt['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentEvents)): ?>
            <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No events recorded yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php else: ?>
<div class="ai-content-card text-center">
    <p class="text-slate-500">Unknown action.</p>
</div>
<?php endif; ?>

<script>
function toggleNotifications() {
    const panel = document.getElementById('notificationsPanel');
    panel.classList.toggle('hidden');
}

function markRead(id) {
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'api_action=mark_notification_read&notification_id=' + id
    }).then(() => {
        const item = document.querySelector(`.notification-item[data-id="${id}"]`);
        if (item) item.remove();
    });
}

function updateScheduleUI() {
    const type = document.getElementById('scheduleType').value;
    document.getElementById('intervalConfig').classList.toggle('hidden', type !== 'interval');
    document.getElementById('timeConfig').classList.toggle('hidden', !['daily', 'weekly'].includes(type));
    document.getElementById('dayOfWeekConfig').classList.toggle('hidden', type !== 'weekly');
}

function saveSchedule(id) {
    const type = document.getElementById('scheduleType').value;
    if (type === 'none') {
        // Toggle off
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'api_action=toggle_trigger&trigger_type=scheduled&enabled=0&id=' + id
        }).then(r => r.json()).then(d => alert(d.success ? 'Schedule disabled.' : d.error));
        return;
    }

    const config = {
        schedule_type: type,
        timezone: document.getElementById('scheduleTimezone').value
    };
    if (type === 'interval') config.interval_minutes = parseInt(document.getElementById('intervalMinutes').value);
    if (['daily', 'weekly'].includes(type)) config.time = document.getElementById('scheduleTime').value;
    if (type === 'weekly') config.day_of_week = document.getElementById('dayOfWeek').value;

    const form = new FormData();
    form.append('api_action', 'set_trigger');
    form.append('trigger_type', 'scheduled');
    form.append('config_json', JSON.stringify(config));
    fetch('', { method: 'POST', body: form })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert('Schedule saved! Safety: ' + (d.safety_classification || 'unknown'));
                location.reload();
            } else {
                alert('Error: ' + d.error);
            }
        });
}

function saveEventTrigger(id) {
    const eventName = document.getElementById('eventName').value;
    if (!eventName) {
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'api_action=toggle_trigger&trigger_type=event&enabled=0&id=' + id
        }).then(r => r.json()).then(d => alert(d.success ? 'Event trigger disabled.' : d.error));
        return;
    }

    let filter = {};
    const filterVal = document.getElementById('eventFilter').value.trim();
    if (filterVal) {
        try { filter = JSON.parse(filterVal); } catch(e) { alert('Invalid JSON filter.'); return; }
    }

    const form = new FormData();
    form.append('api_action', 'set_trigger');
    form.append('trigger_type', 'event');
    form.append('config_json', JSON.stringify({ event_name: eventName, filter: filter }));
    fetch('', { method: 'POST', body: form })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert('Event trigger saved!');
                location.reload();
            } else {
                alert('Error: ' + d.error);
            }
        });
}

// Click to expand run steps
document.querySelectorAll('.run-item').forEach(el => {
    el.addEventListener('click', function() {
        const runId = this.dataset.runId;
        const container = document.getElementById('run-steps-' + runId);
        if (container.innerHTML.trim()) {
            container.classList.toggle('hidden');
            return;
        }

        const form = new FormData();
        form.append('api_action', 'get_run_steps');
        form.append('run_id', runId);
        fetch('', { method: 'POST', body: form })
            .then(r => r.json())
            .then(steps => {
                if (!steps.length) {
                    container.innerHTML = '<div class="text-xs text-slate-500">No steps recorded.</div>';
                } else {
                    container.innerHTML = steps.map(s => `
                        <div class="text-xs p-1 mb-1 rounded bg-slate-700/40 flex justify-between">
                            <span>Step ${s.step_number}: ${s.step_type} — ${s.tool_id || s.action_id || ''}</span>
                            <span class="${s.status === 'completed' ? 'text-green-600' : 'text-red-600'}">${s.status}</span>
                        </div>
                    `).join('');
                }
                container.classList.remove('hidden');
            });
    });
});
</script>

<?php require_once AI_APP_ROOT . '/templates/footer.php'; ?>