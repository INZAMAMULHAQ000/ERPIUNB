<?php
/**
 * AI Tasks — Task Management UI.
 */
$pageTitle = 'AI Tasks';
require_once __DIR__ . '/../bootstrap.php';
require_once AI_MODULE_DIR . '/services/AITaskService.php';
require_once AI_MODULE_DIR . '/services/AITaskPlanner.php';

if (!hasPermission('ai.assistant.access')) { die('Access Denied'); }

$db = getDB();
$userId = $_SESSION['user_id'];
$tasks = AITaskService::getUserTasks($db, $userId);

$statusColors = [
    'draft' => 'ai-badge',
    'planned' => 'ai-badge-info',
    'awaiting_confirmation' => 'ai-badge-warning',
    'running' => 'ai-badge-info',
    'paused' => 'ai-badge-warning',
    'completed' => 'ai-badge-success',
    'failed' => 'ai-badge-danger',
    'cancelled' => 'ai-badge',
];
$statusColorsCustom = [
    'draft' => 'background:rgba(148,163,184,0.15);color:var(--ai-text-muted)',
    'cancelled' => 'background:rgba(148,163,184,0.15);color:var(--ai-text-muted)',
];
?>

<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>
<?php ai_render_page_styles(); ?>
<?php ai_render_module_nav('ai.tasks'); ?>

<div class="ai-page-container">
    <div class="ai-page-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="ai-page-title">
                <span class="ai-page-icon"><i class="fas fa-list-check"></i></span>
                AI Tasks
            </h1>
            <p class="ai-page-subtitle">Create and manage multi-step AI tasks</p>
        </div>
        <button onclick="showCreateModal()" style="background:var(--ai-accent);color:#0f172a;border:none;cursor:pointer;padding:0.5rem 1rem;border-radius:0.5rem;font-size:0.875rem;font-weight:600;">
            <i class="fas fa-plus mr-1"></i>New Task
        </button>
    </div>

    <!-- Task List -->
    <?php if (empty($tasks)): ?>
    <div class="ai-content-card text-center">
        <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background:rgba(148,163,184,0.12)">
            <i class="fas fa-list-check text-2xl" style="color:var(--ai-text-muted)"></i>
        </div>
        <h3 class="text-lg font-semibold mb-2" style="color:var(--ai-text-primary)">No Tasks Yet</h3>
        <p class="mb-4" style="color:var(--ai-text-secondary)">Create a task to let AI help with multi-step operations.</p>
        <button onclick="showCreateModal()" style="background:var(--ai-accent);color:#0f172a;border:none;cursor:pointer;padding:0.5rem 1rem;border-radius:0.5rem;font-size:0.875rem;font-weight:600;">
            <i class="fas fa-plus mr-1"></i>Create Task
        </button>
    </div>
    <?php else: ?>
    <div class="ai-table-wrapper">
        <table class="ai-data-table">
            <thead>
                <tr>
                    <th>Task</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task): ?>
                <tr>
                    <td>
                        <div class="font-medium" style="color:var(--ai-text-primary)"><?= sanitize($task['title']) ?></div>
                        <?php if ($task['description']): ?>
                        <div class="text-xs mt-1" style="color:var(--ai-text-muted)"><?= sanitize(substr($task['description'], 0, 80)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="ai-badge <?= $statusColors[$task['status']] ?? 'ai-badge' ?>"
                            <?php if (isset($statusColorsCustom[$task['status']])): ?>
                            style="<?= $statusColorsCustom[$task['status']] ?>"
                            <?php endif; ?>
                        >
                            <?= ucfirst(str_replace('_', ' ', $task['status'])) ?>
                        </span>
                    </td>
                    <td>
                        <div style="color:var(--ai-text-secondary)">
                            <?php if ($task['total_steps'] > 0): ?>
                                <?= $task['current_step'] ?> / <?= $task['total_steps'] ?> steps
                            <?php else: ?>
                                No plan
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="color:var(--ai-text-muted)">
                        <?= date('M j, g:i A', strtotime($task['created_at'])) ?>
                    </td>
                    <td>
                        <a href="<?= url('modules/ai/tasks/view.php?id=' . $task['id']) ?>" class="mr-3" style="color:var(--ai-accent);text-decoration:none">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if (in_array($task['status'], ['draft', 'planned'])): ?>
                        <button onclick="deleteTask(<?= $task['id'] ?>)" style="color:var(--ai-danger);background:none;border:none;cursor:pointer">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Create Task Modal -->
    <div id="createModal" class="hidden fixed inset-0 flex items-center justify-center z-50" style="background:rgba(0,0,0,0.6)">
        <div class="ai-content-card max-w-lg w-full mx-4">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4" style="color:var(--ai-text-primary)">Create New Task</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color:var(--ai-text-secondary)">Task Title</label>
                        <input type="text" id="taskTitle" class="w-full rounded-lg px-3 py-2 text-sm" style="background:var(--ai-input-bg);border:1px solid var(--ai-input-border);color:var(--ai-text-primary)" placeholder="e.g., Review pending purchase requisitions">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color:var(--ai-text-secondary)">Description (optional)</label>
                        <textarea id="taskDesc" rows="2" class="w-full rounded-lg px-3 py-2 text-sm" style="background:var(--ai-input-bg);border:1px solid var(--ai-input-border);color:var(--ai-text-primary)" placeholder="What should this task accomplish?"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color:var(--ai-text-secondary)">Or describe in natural language</label>
                        <textarea id="taskObjective" rows="3" class="w-full rounded-lg px-3 py-2 text-sm" style="background:var(--ai-input-bg);border:1px solid var(--ai-input-border);color:var(--ai-text-primary)" placeholder="e.g., Find all pending purchase requisitions from this month and prepare a summary"></textarea>
                        <p class="text-xs mt-1" style="color:var(--ai-text-muted)">AI will generate a task plan from your description.</p>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 flex justify-end gap-3" style="border-top:1px solid var(--ai-border)">
                <button onclick="hideCreateModal()" style="background:var(--ai-input-bg);color:var(--ai-text-secondary);border:1px solid var(--ai-input-border);cursor:pointer;padding:0.5rem 1rem;border-radius:0.5rem;font-size:0.875rem;">Cancel</button>
                <button onclick="createTask()" id="createBtn" style="background:var(--ai-accent);color:#0f172a;border:none;cursor:pointer;padding:0.5rem 1rem;border-radius:0.5rem;font-size:0.875rem;font-weight:600;">Create Task</button>
            </div>
        </div>
    </div>

    <script>
function showCreateModal() {
    document.getElementById("createModal").classList.remove("hidden");
}
function hideCreateModal() {
    document.getElementById("createModal").classList.add("hidden");
    document.getElementById("taskTitle").value = "";
    document.getElementById("taskDesc").value = "";
    document.getElementById("taskObjective").value = "";
}

async function createTask() {
    const title = document.getElementById("taskTitle").value.trim();
    const desc = document.getElementById("taskDesc").value.trim();
    const objective = document.getElementById("taskObjective").value.trim();

    if (!title && !objective) { alert("Please enter a title or description."); return; }

    const btn = document.getElementById("createBtn");
    btn.disabled = true;
    btn.textContent = "Creating...";

    try {
        // Create task
        const createResp = await fetch("<?= url("modules/ai/api/tasks.php?action=create") ?>", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ title: title || objective.substring(0, 100), description: desc, original_request: objective })
        });
        const createData = await createResp.json();

        if (!createData.success) { alert(createData.error || "Failed to create task."); return; }

        // Build plan
        const planResp = await fetch("<?= url("modules/ai/api/tasks.php?action=plan") ?>", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ task_id: createData.id, objective: objective || title })
        });
        const planData = await planResp.json();

        if (planData.success) {
            window.location.href = "<?= url("modules/ai/tasks/view.php?id=") ?>" + createData.id;
        } else {
            alert(planData.error || "Failed to generate plan.");
            window.location.href = "<?= url("modules/ai/tasks/view.php?id=") ?>" + createData.id;
        }
    } catch (e) {
        alert("Error creating task.");
    }

    btn.disabled = false;
    btn.textContent = "Create Task";
}

async function deleteTask(id) {
    if (!confirm("Delete this task?")) return;
    await fetch("<?= url("modules/ai/api/tasks.php?action=cancel") ?>", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ task_id: id })
    });
    location.reload();
}
</script>

<?php require_once AI_APP_ROOT . '/templates/footer.php'; ?>