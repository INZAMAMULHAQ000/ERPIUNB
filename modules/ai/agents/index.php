<?php
/**
 * AI F25 — Agent Center UI.
 *
 * Full agent runtime interface: agents, tasks, plans, approvals,
 * checkpoints, artifacts, progress tracking, and administration.
 */
$pageTitle = 'AI Agent Center';
require_once __DIR__ . '/../bootstrap.php';
require_once AI_MODULE_DIR . '/includes/ai_agent_base.php';
require_once AI_MODULE_DIR . '/includes/ai_agents_def.php';
require_once AI_MODULE_DIR . '/services/AIAgentService.php';
require_once AI_MODULE_DIR . '/services/AIAgentPlanService.php';
require_once AI_MODULE_DIR . '/services/AIAgentCheckpointService.php';
require_once AI_MODULE_DIR . '/services/AIAgentApprovalService.php';
require_once AI_MODULE_DIR . '/services/AIAgentArtifactService.php';
require_once AI_MODULE_DIR . '/services/AIAgentRuntimeService.php';

if (!hasPermission('ai.assistant.access')) { die('Access Denied'); }

$db = getDB();
$userId = $_SESSION['user_id'];
$companyId = (int) ($_SESSION['company_id'] ?? 1);

// Get user permissions
$permStmt = $db->prepare("
    SELECT DISTINCT CONCAT(p.module, '.', p.name) as perm
    FROM permissions p JOIN role_permissions rp ON p.id = rp.permission_id
    JOIN user_roles ur ON rp.role_id = ur.role_id WHERE ur.user_id = ?
");
$permStmt->execute([$userId]);
$userPermissions = array_column($permStmt->fetchAll(), 'perm');

// Determine view mode
$viewTab = $_GET['tab'] ?? 'agents';
$viewTaskId = (int) ($_GET['task_id'] ?? 0);
$viewRunId = (int) ($_GET['run_id'] ?? 0);

// Load data
$agents = AIAgentService::getAvailableAgents($userPermissions);
$tasks = AIAgentRuntimeService::getUserTasks($db, $userId, null, 30);
$pendingApprovals = AIAgentApprovalService::getPendingApprovals($db, $userId, $companyId);
$myArtifacts = AIAgentArtifactService::getUserArtifacts($db, $userId, 20);

// View specific task
$viewTask = $viewTaskId ? AIAgentRuntimeService::getTask($db, $viewTaskId) : null;
if ($viewTask && $viewTask['user_id'] != $userId) $viewTask = null;

// View specific run
$viewRun = $viewRunId ? AIAgentService::getRunDetails($db, $userId, $viewRunId) : null;

// Status colors (preserved for backward compatibility)
$statusColors = [
    'pending' => 'bg-gray-100 text-gray-600',
    'planning' => 'bg-blue-100 text-blue-700',
    'awaiting_confirmation' => 'bg-yellow-100 text-yellow-700',
    'awaiting_review' => 'bg-orange-100 text-orange-700',
    'running' => 'bg-green-100 text-green-700',
    'paused' => 'bg-orange-100 text-orange-700',
    'completed' => 'bg-green-100 text-green-700',
    'partially_completed' => 'bg-yellow-100 text-yellow-700',
    'failed' => 'bg-red-100 text-red-700',
    'cancelled' => 'bg-gray-100 text-gray-600',
    'limit_reached' => 'bg-purple-100 text-purple-700',
    'queued' => 'bg-gray-100 text-gray-600',
];

$riskColors = [
    'low' => 'bg-green-100 text-green-700',
    'medium' => 'bg-yellow-100 text-yellow-700',
    'high' => 'bg-orange-100 text-orange-700',
    'critical' => 'bg-red-100 text-red-700',
];

// Dark theme classes for AI module
$darkStatusClasses = [
    'pending' => 'ai-badge',
    'planning' => 'ai-badge ai-badge-info',
    'awaiting_confirmation' => 'ai-badge ai-badge-warning',
    'awaiting_review' => 'ai-badge ai-badge-warning',
    'running' => 'ai-badge ai-badge-success',
    'paused' => 'ai-badge ai-badge-warning',
    'completed' => 'ai-badge ai-badge-success',
    'partially_completed' => 'ai-badge ai-badge-warning',
    'failed' => 'ai-badge ai-badge-danger',
    'cancelled' => 'ai-badge',
    'limit_reached' => 'ai-badge ai-badge-purple',
    'queued' => 'ai-badge',
];

$darkRiskClasses = [
    'low' => 'ai-badge ai-badge-success',
    'medium' => 'ai-badge ai-badge-warning',
    'high' => 'ai-badge ai-badge-warning',
    'critical' => 'ai-badge ai-badge-danger',
];
?>

<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>
<?php ai_render_module_nav('ai.agents'); ?>
<?php ai_render_page_styles(); ?>

<div class="ai-page-container">
    <?php ai_render_page_header('AI Agent Center', 'Controlled autonomous execution for multi-step ERP tasks', 'fas fa-robot'); ?>

    <?php if (!empty($pendingApprovals)): ?>
    <div class="ai-alert ai-alert-warning mb-6">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong><?= count($pendingApprovals) ?> Pending Approvals</strong>
            <span class="text-sm text-slate-300">You have approvals awaiting your review.</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div class="ai-tabs">
        <button class="ai-tab <?= $viewTab === 'agents' ? 'active' : '' ?>" onclick="location.href='?tab=agents'">
            <i class="fas fa-robot"></i>Agents
        </button>
        <button class="ai-tab <?= $viewTab === 'tasks' ? 'active' : '' ?>" onclick="location.href='?tab=tasks'">
            <i class="fas fa-list-check"></i>Tasks
            <?php $runningCount = count(array_filter($tasks, fn($t) => in_array($t['status'], ['running','planning','paused']))); ?>
            <?php if ($runningCount > 0): ?>
            <span class="ai-badge ai-badge-success ml-1"><?= $runningCount ?></span>
            <?php endif; ?>
        </button>
        <button class="ai-tab <?= $viewTab === 'approvals' ? 'active' : '' ?>" onclick="location.href='?tab=approvals'">
            <i class="fas fa-check-double"></i>Approvals
            <?php if (!empty($pendingApprovals)): ?>
            <span class="ai-badge ai-badge-warning ml-1"><?= count($pendingApprovals) ?></span>
            <?php endif; ?>
        </button>
        <button class="ai-tab <?= $viewTab === 'artifacts' ? 'active' : '' ?>" onclick="location.href='?tab=artifacts'">
            <i class="fas fa-file-alt"></i>Artifacts
        </button>
    </div>

    <?php if ($viewTask): ?>
    <!-- Task Detail View -->
    <?php require __DIR__ . '/_task_detail.php'; ?>

    <?php elseif ($viewRun): ?>
    <!-- Run Detail View (legacy) -->
    <?php require __DIR__ . '/_run_detail.php'; ?>

    <?php elseif ($viewTab === 'agents'): ?>
    <!-- Agents Tab -->
    <?php if (empty($agents)): ?>
    <div class="ai-content-card text-center">
        <div class="w-16 h-16 rounded-full bg-slate-700/50 flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-robot text-slate-400 text-2xl"></i>
        </div>
        <h3 class="text-lg font-semibold text-slate-100 mb-2">No Agents Available</h3>
        <p class="text-slate-400">No agents match your current permissions.</p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($agents as $agent): ?>
        <div class="ai-content-card flex flex-col">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="font-semibold text-slate-100"><?= sanitize($agent['name']) ?></h3>
                    <span class="ai-badge <?= $darkRiskClasses[$agent['risk_level']] ?? 'ai-badge' ?> mt-1">
                        <?= ucfirst($agent['risk_level']) ?> Risk
                    </span>
                </div>
                <span class="text-xs text-slate-500"><?= ucfirst($agent['agent_type']) ?></span>
            </div>
            <p class="text-sm text-slate-300 mb-3 flex-1"><?= sanitize($agent['description']) ?></p>
            <div class="text-xs text-slate-400 mb-3 flex flex-wrap gap-2">
                <span><i class="fas fa-layer-group mr-1"></i><?= $agent['max_steps'] ?> steps</span>
                <span><i class="fas fa-clock mr-1"></i><?= $agent['max_runtime_seconds'] ?>s</span>
                <span><i class="fas fa-wrench mr-1"></i><?= count($agent['allowed_tools']) ?> tools</span>
            </div>
            <button onclick="showStartModal('<?= $agent['agent_id'] ?>', '<?= sanitize(addslashes($agent['name'])) ?>')" class="w-full bg-sky-500 hover:bg-sky-400 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                <i class="fas fa-play mr-1"></i>Start Agent
            </button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php elseif ($viewTab === 'tasks'): ?>
    <!-- Tasks Tab -->
    <?php if (empty($tasks)): ?>
    <div class="ai-content-card text-center text-slate-400">
        No tasks yet. Start an agent to create your first task.
    </div>
    <?php else: ?>
    <div class="space-y-3">
        <?php foreach ($tasks as $task): ?>
        <a href="?tab=tasks&task_id=<?= $task['id'] ?>" class="block ai-content-card hover:border-sky-500/30 transition-colors">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="font-semibold text-slate-100 text-sm"><?= sanitize($task['agent_name'] ?? 'Agent') ?></span>
                        <span class="ai-badge <?= $darkStatusClasses[$task['status']] ?? 'ai-badge' ?>">
                            <?= ucfirst(str_replace('_', ' ', $task['status'])) ?>
                        </span>
                    </div>
                    <p class="text-sm text-slate-300 truncate"><?= sanitize(substr($task['original_request'], 0, 120)) ?></p>
                    <div class="flex items-center gap-4 mt-2 text-xs text-slate-500">
                        <span><?= date('M j H:i', strtotime($task['created_at'])) ?></span>
                        <span>Progress: <?= $task['progress_pct'] ?>%</span>
                        <span>Source: <?= ucfirst($task['source']) ?></span>
                    </div>
                </div>
                <div class="w-16">
                    <div class="w-full bg-slate-700 rounded-full h-2">
                        <div class="bg-sky-400 h-2 rounded-full transition-all" style="width: <?= $task['progress_pct'] ?>%"></div>
                    </div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php elseif ($viewTab === 'approvals'): ?>
    <!-- Approvals Tab -->
    <?php if (empty($pendingApprovals)): ?>
    <div class="ai-content-card text-center text-slate-400">
        <i class="fas fa-check-double text-3xl text-slate-500 mb-3 block"></i>
        No pending approvals.
    </div>
    <?php else: ?>
    <div class="space-y-3">
        <?php foreach ($pendingApprovals as $approval): ?>
        <div class="ai-content-card">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="font-semibold text-sm text-slate-100"><?= sanitize($approval['action_type']) ?></span>
                        <span class="ai-badge <?= $darkRiskClasses[$approval['risk_level']] ?? 'ai-badge' ?>">
                            <?= ucfirst($approval['risk_level']) ?>
                        </span>
                    </div>
                    <p class="text-sm text-slate-300 mb-1"><?= sanitize($approval['original_request'] ?? '') ?></p>
                    <p class="text-xs text-slate-500">
                        Requested by <?= sanitize($approval['requesting_user_name'] ?? 'Unknown') ?>
                        &middot; Expires <?= date('M j H:i', strtotime($approval['expires_at'])) ?>
                    </p>
                </div>
                <div class="flex gap-2">
                    <button onclick="approveAction(<?= $approval['id'] ?>)" class="bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 rounded-lg text-xs transition-colors">
                        <i class="fas fa-check mr-1"></i>Approve
                    </button>
                    <button onclick="rejectAction(<?= $approval['id'] ?>)" class="bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30 px-3 py-1.5 rounded-lg text-xs transition-colors">
                        <i class="fas fa-times mr-1"></i>Reject
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php elseif ($viewTab === 'artifacts'): ?>
    <!-- Artifacts Tab -->
    <?php if (empty($myArtifacts)): ?>
    <div class="ai-content-card text-center text-slate-400">
        <i class="fas fa-file-alt text-3xl text-slate-500 mb-3 block"></i>
        No artifacts produced yet.
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach ($myArtifacts as $artifact): ?>
        <div class="ai-content-card">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-lg bg-sky-500/10 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-file-alt text-sky-400"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="font-medium text-slate-100 text-sm"><?= sanitize($artifact['title']) ?></h4>
                    <p class="text-xs text-slate-400 mt-1"><?= ucfirst($artifact['artifact_type']) ?> &middot; <?= date('M j H:i', strtotime($artifact['created_at'])) ?></p>
                    <?php if (!empty($artifact['content'])): ?>
                    <p class="text-xs text-slate-300 mt-2 line-clamp-2"><?= sanitize(substr($artifact['content'], 0, 200)) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Start Agent Modal -->
<div id="startModal" class="hidden fixed inset-0 bg-black/70 flex items-center justify-center z-50">
    <div class="bg-slate-900 border border-slate-700 rounded-xl shadow-2xl max-w-lg w-full mx-4">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-slate-100 mb-4">Start Agent: <span id="modalAgentName" class="text-sky-400"></span></h3>
            <div class="space-y-3">
                <div>
                    <label class="ai-form-label">Request</label>
                    <textarea id="agentObjective" rows="4" class="ai-form-textarea" placeholder="Describe what you want the agent to do..."></textarea>
                </div>
                <div>
                    <label class="ai-form-label">Priority</label>
                    <select id="taskPriority" class="ai-form-select">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="bg-slate-800/50 px-6 py-4 flex justify-end gap-3 rounded-b-xl">
            <button onclick="hideStartModal()" class="px-4 py-2 border border-slate-600 rounded-lg text-slate-300 hover:bg-slate-700 text-sm transition-colors">Cancel</button>
            <button onclick="startTask()" class="px-4 py-2 bg-sky-500 hover:bg-sky-400 text-white rounded-lg text-sm transition-colors">
                <i class="fas fa-play mr-1"></i>Create & Execute
            </button>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= csrfToken() ?>';
const BASE_URL = '<?= url('') ?>';
let selectedAgentId = '';

function showStartModal(agentId, agentName) {
    selectedAgentId = agentId;
    document.getElementById('modalAgentName').textContent = agentName;
    document.getElementById('agentObjective').value = '';
    document.getElementById('startModal').classList.remove('hidden');
}

function hideStartModal() {
    document.getElementById('startModal').classList.add('hidden');
    selectedAgentId = '';
}

async function startTask() {
    const objective = document.getElementById('agentObjective').value.trim();
    const priority = document.getElementById('taskPriority').value;
    if (!objective) { alert('Please describe the objective.'); return; }

    const resp = await fetch(`${BASE_URL}/modules/ai/api/agents.php?action=create_task`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ agent_id: selectedAgentId, request: objective, priority, source: 'dashboard' })
    });
    const data = await resp.json();
    if (data.success) {
        hideStartModal();
        window.location.href = `?tab=tasks&task_id=${data.task_id}`;
    } else {
        alert(data.error || 'Failed to create task.');
    }
}

async function approveAction(approvalId) {
    if (!confirm('Approve this action?')) return;
    const resp = await fetch(`${BASE_URL}/modules/ai/api/agents.php?action=approve_action`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ approval_id: approvalId })
    });
    const data = await resp.json();
    if (data.success) location.reload();
    else alert(data.error || 'Failed to approve.');
}

async function rejectAction(approvalId) {
    const reason = prompt('Reason for rejection (optional):');
    if (reason === null) return;
    const resp = await fetch(`${BASE_URL}/modules/ai/api/agents.php?action=reject_action`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ approval_id: approvalId, reason })
    });
    const data = await resp.json();
    if (data.success) location.reload();
    else alert(data.error || 'Failed to reject.');
}

async function cancelTask(taskId) {
    if (!confirm('Cancel this task?')) return;
    const resp = await fetch(`${BASE_URL}/modules/ai/api/agents.php?action=cancel_task`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ task_id: taskId })
    });
    const data = await resp.json();
    if (data.success) location.reload();
    else alert(data.error || 'Failed to cancel.');
}

async function pauseRun(runId) {
    const resp = await fetch(`${BASE_URL}/modules/ai/api/agents.php?action=pause_run`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ run_id: runId })
    });
    const data = await resp.json();
    if (data.success) location.reload();
    else alert(data.error || 'Failed to pause.');
}

async function resumeRun(runId) {
    const resp = await fetch(`${BASE_URL}/modules/ai/api/agents.php?action=resume_run`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ run_id: runId })
    });
    const data = await resp.json();
    if (data.success) location.reload();
    else alert(data.error || 'Failed to resume.');
}
</script>

<?php require_once AI_APP_ROOT . '/includes/footer.php'; ?>
