<?php
/**
 * AI Task View — Shows task details, plan, and execution progress.
 */
$pageTitle = 'AI Task';
require_once __DIR__ . '/../bootstrap.php';
require_once AI_MODULE_DIR . '/services/AITaskService.php';

if (!hasPermission('ai.assistant.access')) { die('Access Denied'); }

$db = getDB();
$userId = $_SESSION['user_id'];
$taskId = (int) ($_GET['id'] ?? 0);

$task = AITaskService::getTaskById($db, $taskId, $userId);
if (!$task) { die('Task not found.'); }

$steps = AITaskService::getTaskSteps($db, $taskId);
$pageTitle = $task['title'];
$plan = json_decode($task['plan_json'] ?? '{}', true);

$statusColors = [
    'draft' => 'bg-gray-100 text-gray-700',
    'planned' => 'bg-blue-100 text-blue-700',
    'awaiting_confirmation' => 'bg-yellow-100 text-yellow-700',
    'running' => 'bg-indigo-100 text-indigo-700',
    'paused' => 'bg-orange-100 text-orange-700',
    'completed' => 'bg-green-100 text-green-700',
    'failed' => 'bg-red-100 text-red-700',
    'cancelled' => 'bg-gray-100 text-gray-500',
];

$stepStatusIcons = [
    'pending' => ['icon' => 'far fa-circle', 'color' => 'text-gray-400'],
    'running' => ['icon' => 'fas fa-spinner fa-spin', 'color' => 'text-blue-500'],
    'awaiting_confirmation' => ['icon' => 'fas fa-clock', 'color' => 'text-yellow-500'],
    'completed' => ['icon' => 'fas fa-check-circle', 'color' => 'text-green-500'],
    'failed' => ['icon' => 'fas fa-times-circle', 'color' => 'text-red-500'],
    'skipped' => ['icon' => 'fas fa-forward', 'color' => 'text-gray-400'],
    'cancelled' => ['icon' => 'fas fa-ban', 'color' => 'text-gray-400'],
];
?>

<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>
<?php ai_render_module_nav('ai.tasks'); ?>

<div class="mb-6">
    <a href="<?= url('modules/ai/tasks/index.php') ?>" class="text-blue-600 hover:text-blue-800 text-sm">
        <i class="fas fa-arrow-left mr-1"></i>Back to Tasks
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Task Info -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-bold text-gray-800"><?= sanitize($task['title']) ?></h1>
                <span class="px-3 py-1 text-sm rounded-full <?= $statusColors[$task['status']] ?? '' ?>">
                    <?= ucfirst(str_replace('_', ' ', $task['status'])) ?>
                </span>
            </div>

            <?php if ($task['description']): ?>
            <p class="text-gray-600 mb-4"><?= sanitize($task['description']) ?></p>
            <?php endif; ?>

            <?php if ($task['result_summary']): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                <h3 class="text-sm font-semibold text-green-800 mb-2"><i class="fas fa-check-circle mr-1"></i>Result</h3>
                <p class="text-sm text-green-700"><?= nl2br(sanitize($task['result_summary'])) ?></p>
            </div>
            <?php endif; ?>

            <!-- Steps -->
            <?php if (!empty($steps)): ?>
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Task Steps</h3>
            <div class="space-y-3">
                <?php foreach ($steps as $step): ?>
                <div class="flex items-start gap-3 p-3 rounded-lg <?= $step['status'] === 'running' ? 'bg-blue-50 border border-blue-200' : ($step['status'] === 'awaiting_confirmation' ? 'bg-yellow-50 border border-yellow-200' : 'bg-gray-50') ?>">
                    <div class="<?= $stepStatusIcons[$step['status']]['color'] ?? 'text-gray-400' ?> mt-0.5">
                        <i class="<?= $stepStatusIcons[$step['status']]['icon'] ?? 'far fa-circle' ?>"></i>
                    </div>
                    <div class="flex-1">
                        <div class="text-sm font-medium text-gray-800">
                            Step <?= $step['step_number'] ?>: <?= sanitize($step['title']) ?>
                            <span class="text-xs text-gray-500 ml-2">(<?= ucfirst($step['step_type']) ?>)</span>
                        </div>
                        <?php if ($step['description']): ?>
                        <div class="text-xs text-gray-500 mt-1"><?= sanitize($step['description']) ?></div>
                        <?php endif; ?>
                        <?php if ($step['error_message']): ?>
                        <div class="text-xs text-red-600 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i><?= sanitize($step['error_message']) ?></div>
                        <?php endif; ?>
                        <?php if ($step['status'] === 'awaiting_confirmation'): ?>
                        <div class="mt-2">
                            <span class="text-xs text-yellow-700"><i class="fas fa-clock mr-1"></i>Waiting for user confirmation</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs text-gray-400">
                        <?= ucfirst(str_replace('_', ' ', $step['status'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sidebar -->
    <div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Task Info</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Type:</dt><dd class="font-medium"><?= ucfirst($task['task_type']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Priority:</dt><dd class="font-medium"><?= ucfirst($task['priority']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Steps:</dt><dd class="font-medium"><?= $task['current_step'] ?> / <?= $task['total_steps'] ?></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Created:</dt><dd class="font-medium"><?= date('M j, g:i A', strtotime($task['created_at'])) ?></dd></div>
                <?php if ($task['started_at']): ?>
                <div class="flex justify-between"><dt class="text-gray-500">Started:</dt><dd class="font-medium"><?= date('M j, g:i A', strtotime($task['started_at'])) ?></dd></div>
                <?php endif; ?>
                <?php if ($task['completed_at']): ?>
                <div class="flex justify-between"><dt class="text-gray-500">Completed:</dt><dd class="font-medium"><?= date('M j, g:i A', strtotime($task['completed_at'])) ?></dd></div>
                <?php endif; ?>
            </dl>

            <div class="mt-4 space-y-2">
                <?php if (in_array($task['status'], ['planned'])): ?>
                <button onclick="startTask()" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm">
                    <i class="fas fa-play mr-1"></i>Start Task
                </button>
                <?php endif; ?>

                <?php if (in_array($task['status'], ['running', 'awaiting_confirmation', 'paused'])): ?>
                <button onclick="cancelTask()" class="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm">
                    <i class="fas fa-stop mr-1"></i>Cancel Task
                </button>
                <?php endif; ?>

                <?php if ($task['status'] === 'completed'): ?>
                <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-center text-sm text-green-700">
                    <i class="fas fa-check-circle mr-1"></i>Task Completed
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const TASK_ID = <?= $taskId ?>;
const BASE_URL = '<?= url('') ?>';

async function startTask() {
    if (!confirm('Start this task? Read-only steps will execute automatically. Action steps will pause for confirmation.')) return;

    const resp = await fetch(`${BASE_URL}/modules/ai/api/tasks.php?action=start`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ task_id: TASK_ID })
    });
    const data = await resp.json();

    if (data.success) {
        location.reload();
    } else {
        alert(data.error || 'Failed to start task.');
    }
}

async function cancelTask() {
    if (!confirm('Cancel this task? Completed steps cannot be undone.')) return;

    const resp = await fetch(`${BASE_URL}/modules/ai/api/tasks.php?action=cancel`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ task_id: TASK_ID })
    });
    const data = await resp.json();

    if (data.success) {
        location.reload();
    } else {
        alert(data.error || 'Failed to cancel task.');
    }
}
</script>

<?php require_once AI_APP_ROOT . '/templates/footer.php'; ?>
