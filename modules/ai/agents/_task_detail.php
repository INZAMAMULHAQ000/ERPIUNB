<?php
/**
 * AI F25 — Task Detail View (partial).
 * Included by agents/index.php when viewing a specific task.
 */
if (!$viewTask) { echo '<div class="text-center text-gray-500 py-8">Task not found.</div>'; return; }

$task = $viewTask;
$steps = AIAgentRuntimeService::getTaskSteps($db, $task['id']);
$events = AIAgentRuntimeService::getTaskEvents($db, $task['id']);
$approvals = AIAgentApprovalService::getTaskApprovals($db, $task['id']);
$artifacts = AIAgentArtifactService::getTaskArtifacts($db, $task['id']);
$checkpoints = AIAgentCheckpointService::getTaskCheckpoints($db, $task['id']);
$agent = AIAgentRuntimeService::getAgentDefinition($db, $task['agent_id']);
?>

<div class="mb-4">
    <a href="?tab=tasks" class="text-blue-600 hover:text-blue-800 text-sm">
        <i class="fas fa-arrow-left mr-1"></i>Back to Tasks
    </a>
</div>

<!-- Task Header -->
<div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-6">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
        <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
                <h2 class="text-lg font-bold text-gray-800"><?= sanitize($agent['name'] ?? 'Agent') ?></h2>
                <span class="px-2 py-0.5 text-xs rounded-full <?= $statusColors[$task['status']] ?? 'bg-gray-100' ?>">
                    <?= ucfirst(str_replace('_', ' ', $task['status'])) ?>
                </span>
            </div>
            <p class="text-sm text-gray-600"><?= sanitize(substr($task['original_request'], 0, 300)) ?></p>
        </div>
        <div class="flex gap-2 flex-shrink-0">
            <?php if (in_array($task['status'], ['running', 'planning'])): ?>
            <button onclick="pauseRun(<?= $task['id'] ?>)" class="bg-yellow-50 text-yellow-700 border border-yellow-200 px-3 py-1.5 rounded-lg text-xs hover:bg-yellow-100">
                <i class="fas fa-pause mr-1"></i>Pause
            </button>
            <?php endif; ?>
            <?php if ($task['status'] === 'paused'): ?>
            <button onclick="resumeRun(<?= $task['id'] ?>)" class="bg-green-600 text-white px-3 py-1.5 rounded-lg text-xs hover:bg-green-700">
                <i class="fas fa-play mr-1"></i>Resume
            </button>
            <?php endif; ?>
            <?php if (!in_array($task['status'], ['completed', 'cancelled'])): ?>
            <button onclick="cancelTask(<?= $task['id'] ?>)" class="bg-red-50 text-red-600 border border-red-200 px-3 py-1.5 rounded-lg text-xs hover:bg-red-100">
                <i class="fas fa-stop mr-1"></i>Cancel
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="mb-4">
        <div class="flex justify-between text-xs text-gray-500 mb-1">
            <span>Progress</span>
            <span><?= $task['progress_pct'] ?>% · Step <?= $task['current_step'] ?> / <?= $task['total_steps'] ?></span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2.5">
            <div class="bg-blue-600 h-2.5 rounded-full transition-all" style="width: <?= $task['progress_pct'] ?>%"></div>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
        <div>
            <span class="text-gray-500 text-xs">Source</span>
            <div class="font-semibold capitalize"><?= $task['source'] ?></div>
        </div>
        <div>
            <span class="text-gray-500 text-xs">Priority</span>
            <div class="font-semibold capitalize"><?= $task['priority'] ?></div>
        </div>
        <div>
            <span class="text-gray-500 text-xs">Created</span>
            <div class="font-semibold"><?= date('M j H:i', strtotime($task['created_at'])) ?></div>
        </div>
        <div>
            <span class="text-gray-500 text-xs">Duration</span>
            <div class="font-semibold">
                <?= $task['started_at'] ? round((strtotime($task['completed_at'] ?? date('Y-m-d H:i:s')) - strtotime($task['started_at']))) . 's' : '—' ?>
            </div>
        </div>
    </div>

    <?php if ($task['result_summary']): ?>
    <div class="mt-4 bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="text-sm font-medium text-green-800 mb-1"><i class="fas fa-check-circle mr-1"></i>Result</div>
        <div class="text-green-700 text-sm"><?= nl2br(sanitize($task['result_summary'])) ?></div>
    </div>
    <?php endif; ?>

    <?php if ($task['error_message']): ?>
    <div class="mt-4 bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="text-sm font-medium text-red-800 mb-1"><i class="fas fa-exclamation-circle mr-1"></i>Error</div>
        <div class="text-red-700 text-sm"><?= nl2br(sanitize($task['error_message'])) ?></div>
    </div>
    <?php endif; ?>
</div>

<!-- Tabs within task detail -->
<nav class="flex gap-2 mb-4">
    <button onclick="showTaskTab('steps')" class="task-tab px-3 py-1.5 rounded-lg text-sm bg-blue-100 text-blue-700 font-medium" data-tab="steps">Steps (<?= count($steps) ?>)</button>
    <button onclick="showTaskTab('approvals')" class="task-tab px-3 py-1.5 rounded-lg text-sm bg-gray-100 text-gray-600" data-tab="approvals">Approvals (<?= count($approvals) ?>)</button>
    <button onclick="showTaskTab('artifacts')" class="task-tab px-3 py-1.5 rounded-lg text-sm bg-gray-100 text-gray-600" data-tab="artifacts">Artifacts (<?= count($artifacts) ?>)</button>
    <button onclick="showTaskTab('events')" class="task-tab px-3 py-1.5 rounded-lg text-sm bg-gray-100 text-gray-600" data-tab="events">Events (<?= count($events) ?>)</button>
</nav>

<!-- Steps Panel -->
<div id="panel-steps" class="task-panel bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <?php if (empty($steps)): ?>
    <div class="p-6 text-center text-gray-500 text-sm">No steps executed yet.</div>
    <?php else: ?>
    <div class="divide-y">
        <?php foreach ($steps as $step): ?>
        <?php
        $stepIcons = [
            'pending' => ['far fa-circle', 'text-gray-400'],
            'running' => ['fas fa-spinner fa-spin', 'text-blue-500'],
            'awaiting_approval' => ['fas fa-clock', 'text-yellow-500'],
            'completed' => ['fas fa-check-circle', 'text-green-500'],
            'failed' => ['fas fa-times-circle', 'text-red-500'],
            'skipped' => ['fas fa-forward', 'text-gray-400'],
            'cancelled' => ['fas fa-ban', 'text-gray-400'],
        ];
        $icon = $stepIcons[$step['status']] ?? $stepIcons['pending'];
        $typeIcons = ['tool' => 'fas fa-wrench', 'action' => 'fas fa-bolt', 'reasoning' => 'fas fa-brain', 'knowledge' => 'fas fa-book', 'navigation' => 'fas fa-compass', 'verification' => 'fas fa-check-double', 'checkpoint' => 'fas fa-bookmark'];
        $typeIcon = $typeIcons[$step['step_type']] ?? 'fas fa-circle';
        ?>
        <div class="px-5 py-3 flex items-start gap-3">
            <div class="mt-0.5"><i class="<?= $icon[0] ?> <?= $icon[1] ?> text-sm"></i></div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 text-sm">
                    <span class="font-medium text-gray-800">Step <?= $step['step_number'] ?></span>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">
                        <i class="<?= $typeIcon ?> mr-1"></i><?= ucfirst($step['step_type']) ?>
                    </span>
                    <?php if ($step['operation_id']): ?>
                    <span class="text-xs text-gray-400 truncate"><?= sanitize($step['operation_id']) ?></span>
                    <?php endif; ?>
                    <?php if ($step['requires_approval']): ?>
                    <span class="text-xs text-yellow-600"><i class="fas fa-lock mr-1"></i>Approval</span>
                    <?php endif; ?>
                </div>
                <?php if ($step['error_message']): ?>
                <div class="text-xs text-red-600 mt-1"><?= sanitize($step['error_message']) ?></div>
                <?php endif; ?>
                <?php if ($step['verification_status']): ?>
                <div class="text-xs mt-1 <?= $step['verification_status'] === 'passed' ? 'text-green-600' : 'text-yellow-600' ?>">
                    Verification: <?= ucfirst($step['verification_status']) ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="text-xs text-gray-400 whitespace-nowrap">
                <?php if ($step['duration_ms']): ?><?= $step['duration_ms'] ?>ms<?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Approvals Panel -->
<div id="panel-approvals" class="task-panel hidden bg-white rounded-xl border border-gray-100 shadow-sm p-4">
    <?php if (empty($approvals)): ?>
    <div class="text-center text-gray-500 text-sm py-4">No approvals for this task.</div>
    <?php else: ?>
    <div class="space-y-2">
        <?php foreach ($approvals as $a): ?>
        <div class="border rounded-lg p-3 flex items-center justify-between">
            <div>
                <span class="text-sm font-medium"><?= sanitize($a['action_type']) ?></span>
                <span class="ml-2 px-2 py-0.5 text-xs rounded-full <?= $statusColors[$a['status']] ?? 'bg-gray-100' ?>"><?= ucfirst($a['status']) ?></span>
                <span class="ml-2 text-xs text-gray-400"><?= date('M j H:i', strtotime($a['created_at'])) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Artifacts Panel -->
<div id="panel-artifacts" class="task-panel hidden bg-white rounded-xl border border-gray-100 shadow-sm p-4">
    <?php if (empty($artifacts)): ?>
    <div class="text-center text-gray-500 text-sm py-4">No artifacts produced.</div>
    <?php else: ?>
    <div class="space-y-2">
        <?php foreach ($artifacts as $art): ?>
        <div class="border rounded-lg p-3">
            <span class="text-sm font-medium"><?= sanitize($art['title']) ?></span>
            <span class="ml-2 text-xs text-gray-400"><?= ucfirst($art['artifact_type']) ?></span>
            <?php if (!empty($art['content'])): ?>
            <p class="text-xs text-gray-600 mt-1 line-clamp-2"><?= sanitize(substr($art['content'], 0, 200)) ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Events Panel -->
<div id="panel-events" class="task-panel hidden bg-white rounded-xl border border-gray-100 shadow-sm p-4">
    <?php if (empty($events)): ?>
    <div class="text-center text-gray-500 text-sm py-4">No events recorded.</div>
    <?php else: ?>
    <div class="space-y-1">
        <?php foreach ($events as $ev): ?>
        <div class="flex items-center gap-3 py-1.5 text-xs">
            <span class="text-gray-400 w-28 flex-shrink-0"><?= date('M j H:i:s', strtotime($ev['created_at'])) ?></span>
            <span class="px-1.5 py-0.5 rounded <?= $riskColors[$ev['severity']] ?? 'bg-gray-100' ?> text-xs"><?= ucfirst($ev['severity']) ?></span>
            <span class="text-gray-600 flex-1"><?= sanitize($ev['message']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function showTaskTab(tab) {
    document.querySelectorAll('.task-panel').forEach(p => p.classList.add('hidden'));
    document.getElementById('panel-' + tab)?.classList.remove('hidden');
    document.querySelectorAll('.task-tab').forEach(btn => {
        btn.classList.remove('bg-blue-100', 'text-blue-700', 'font-medium');
        btn.classList.add('bg-gray-100', 'text-gray-600');
    });
    document.querySelector(`.task-tab[data-tab="${tab}"]`)?.classList.add('bg-blue-100', 'text-blue-700', 'font-medium');
    document.querySelector(`.task-tab[data-tab="${tab}"]`)?.classList.remove('bg-gray-100', 'text-gray-600');
}
</script>
