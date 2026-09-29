<?php
$pageTitle = 'Task Details';
require_once '../../includes/header.php';

if (!hasPermission('tasks.view')) { die('Access Denied'); }

$db = getDB();
$taskId = (int)($_GET['id'] ?? 0);

// Fetch task with project and assigned employee
$stmt = $db->prepare("SELECT t.*, p.project_name, p.project_code, p.id as project_id FROM tasks t JOIN projects p ON t.project_id = p.id WHERE t.id = ?");
$stmt->execute([$taskId]);
$task = $stmt->fetch();

if (!$task) { flash('error', 'Task not found'); to('index.php'); }

// Fetch assignees
$assignees = $db->prepare("SELECT e.id, e.first_name, e.last_name FROM task_assignees ta JOIN employees e ON ta.employee_id = e.id WHERE ta.task_id = ? ORDER BY e.first_name");
$assignees->execute([$taskId]);
$taskAssignees = $assignees->fetchAll();

// Fetch tags
$tags = $db->prepare("SELECT * FROM task_tags WHERE task_id = ? ORDER BY tag_name");
$tags->execute([$taskId]);
$taskTags = $tags->fetchAll();

// Fetch subtasks
$subtasks = $db->prepare("SELECT * FROM task_subtasks WHERE task_id = ? ORDER BY position");
$subtasks->execute([$taskId]);
$taskSubtasks = $subtasks->fetchAll();

// Fetch comments with user names
$comments = $db->prepare("SELECT tc.*, u.name as user_name FROM task_comments tc JOIN users u ON tc.user_id = u.id WHERE tc.task_id = ? ORDER BY tc.created_at ASC");
$comments->execute([$taskId]);
$taskComments = $comments->fetchAll();

// Fetch activity log
$activities = $db->prepare("SELECT ta.*, u.name as user_name FROM task_activities ta JOIN users u ON ta.user_id = u.id WHERE ta.task_id = ? ORDER BY ta.created_at DESC LIMIT 30");
$activities->execute([$taskId]);
$taskActivities = $activities->fetchAll();

// Fetch projects for the edit dropdown
$projects = $db->query("SELECT id, project_name FROM projects ORDER BY project_name")->fetchAll();

$statusLabels = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'review' => 'Review', 'testing' => 'Testing', 'completed' => 'Completed', 'blocked' => 'Blocked'];
$statusColors = ['todo' => '#94a3b8', 'in_progress' => '#3b82f6', 'review' => '#f59e0b', 'testing' => '#8b5cf6', 'completed' => '#22c55e', 'blocked' => '#ef4444'];
$priorityLabels = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'];
$priorityColors = ['urgent' => '#ef4444', 'high' => '#f97316', 'medium' => '#eab308', 'low' => '#94a3b8'];
?>
<div class="mb-4 flex items-center justify-between">
    <a href="index.php" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
        <i class="fas fa-arrow-left mr-1"></i> Back to Tasks
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main -->
    <div class="lg:col-span-2 space-y-5">

        <!-- Title -->
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h1 class="text-2xl font-bold text-slate-800"><?= sanitize($task['task_title']) ?></h1>
        </div>

        <!-- Tags -->
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Labels</h3>
            <div class="flex flex-wrap gap-2 items-center" id="tagsContainer">
                <?php foreach ($taskTags as $tag): ?>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium text-white" style="background:<?= $tag['tag_color'] ?>">
                    <?= sanitize($tag['tag_name']) ?>
                    <button onclick="deleteTag(<?= $tag['id'] ?>)" class="hover:text-white/70">&times;</button>
                </span>
                <?php endforeach; ?>
                <button onclick="showAddTag()" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                    <i class="fas fa-plus mr-0.5"></i> Add label
                </button>
            </div>
            <div id="addTagForm" class="hidden mt-2 flex gap-2">
                <input type="text" id="newTagName" placeholder="Label name" class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm flex-1">
                <input type="color" id="newTagColor" value="#6366f1" class="w-9 h-9 rounded border border-slate-200 cursor-pointer">
                <button onclick="addTag(<?= $taskId ?>)" class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">Add</button>
            </div>
        </div>

        <!-- Description -->
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Description</h3>
            <div id="descDisplay" class="<?= $task['task_description'] ? '' : 'hidden' ?>">
                <p class="text-sm text-slate-700 whitespace-pre-wrap"><?= sanitize($task['task_description']) ?></p>
                <button onclick="editDesc()" class="text-xs text-indigo-600 hover:text-indigo-800 mt-2 font-medium"><i class="fas fa-edit mr-0.5"></i> Edit</button>
            </div>
            <div id="descEmpty" class="<?= $task['task_description'] ? 'hidden' : '' ?>">
                <button onclick="editDesc()" class="text-sm text-slate-400 hover:text-indigo-600 transition"><i class="fas fa-plus mr-1"></i> Add description...</button>
            </div>
            <div id="descForm" class="hidden">
                <textarea id="descText" rows="3" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20"><?= sanitize($task['task_description']) ?></textarea>
                <div class="flex gap-2 mt-2">
                    <button onclick="saveDesc(<?= $taskId ?>)" class="px-4 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">Save</button>
                    <button onclick="cancelEditDesc()" class="px-4 py-1.5 border border-slate-200 text-sm rounded-lg hover:bg-slate-50">Cancel</button>
                </div>
            </div>
        </div>

        <!-- Subtasks -->
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Checklist</h3>
            <div class="mb-3">
                <div class="flex items-center gap-2 text-sm text-slate-500 mb-2">
                    <span id="subDone"><?= count(array_filter($taskSubtasks, fn($s) => $s['is_completed'])) ?></span>/<span id="subTotal"><?= count($taskSubtasks) ?></span> done
                </div>
                <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div id="subProgress" class="h-full rounded-full transition-all" style="width:<?= count($taskSubtasks) > 0 ? round(count(array_filter($taskSubtasks, fn($s) => $s['is_completed'])) / count($taskSubtasks) * 100) : 0 ?>%;background:<?= count($taskSubtasks) > 0 && count(array_filter($taskSubtasks, fn($s) => $s['is_completed'])) === count($taskSubtasks) ? '#22c55e' : '#4f46e5' ?>"></div>
                </div>
            </div>
            <div id="subtaskList" class="space-y-1">
                <?php foreach ($taskSubtasks as $sub): ?>
                <div class="flex items-center gap-2 py-1 group" data-sub-id="<?= $sub['id'] ?>">
                    <input type="checkbox" <?= $sub['is_completed'] ? 'checked' : '' ?>
                           onchange="toggleSubtask(<?= $sub['id'] ?>, this.checked)"
                           class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                    <span class="text-sm flex-1 <?= $sub['is_completed'] ? 'line-through text-slate-400' : 'text-slate-700' ?>"><?= sanitize($sub['title']) ?></span>
                    <button onclick="deleteSubtask(<?= $sub['id'] ?>)" class="text-slate-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition text-xs px-1">&times;</button>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-3 flex gap-2">
                <input type="text" id="newSubtask" placeholder="Add a subtask..."
                       onkeydown="if(event.key==='Enter')addSubtask(<?= $taskId ?>)"
                       class="flex-1 px-3 py-1.5 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20">
                <button onclick="addSubtask(<?= $taskId ?>)" class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">Add</button>
            </div>
        </div>

        <!-- Comments -->
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">
                Comments (<span id="commentCount"><?= count($taskComments) ?></span>)
            </h3>
            <div id="commentList" class="space-y-4 mb-4">
                <?php foreach ($taskComments as $c): ?>
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 text-xs font-bold flex-shrink-0">
                        <?= strtoupper(substr($c['user_name'], 0, 1)) ?>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-sm font-semibold text-slate-700"><?= sanitize($c['user_name']) ?></span>
                            <span class="text-xs text-slate-400"><?= timeAgo($c['created_at']) ?></span>
                        </div>
                        <p class="text-sm text-slate-600 whitespace-pre-wrap"><?= sanitize($c['comment']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="flex gap-3">
                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 text-xs font-bold flex-shrink-0">
                    <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="flex-1">
                    <textarea id="newComment" rows="2" placeholder="Write a comment..."
                              class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20"></textarea>
                    <button onclick="addComment(<?= $taskId ?>)" class="mt-2 px-4 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 font-medium">Comment</button>
                </div>
            </div>
        </div>

        <!-- Activity -->
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <details>
                <summary class="text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer">Activity Log</summary>
                <div class="mt-3 space-y-3">
                    <?php foreach ($taskActivities as $a): ?>
                    <div class="flex gap-3 text-sm">
                        <span class="text-xs text-slate-400 w-16 flex-shrink-0"><?= date('M d', strtotime($a['created_at'])) ?></span>
                        <span class="text-slate-600">
                            <strong><?= sanitize($a['user_name']) ?></strong>
                            <?= formatActivity($a) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$taskActivities): ?>
                    <p class="text-sm text-slate-400">No activity recorded yet.</p>
                    <?php endif; ?>
                </div>
            </details>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-4">

        <!-- Status -->
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-2">Status</label>
            <select onchange="updateField(<?= $taskId ?>, 'status', this.value)" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20">
                <?php foreach ($statusLabels as $val => $label): ?>
                <option value="<?= $val ?>" <?= $task['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Priority -->
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-2">Priority</label>
            <select onchange="updateField(<?= $taskId ?>, 'priority', this.value)" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20">
                <?php foreach ($priorityLabels as $val => $label): ?>
                <option value="<?= $val ?>" <?= $task['priority'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Project (read-only) -->
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-2">Project</label>
            <a href="<?= url('modules/projects/view.php?id=' . $task['project_id']) ?>" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                <?= sanitize($task['project_name']) ?>
            </a>
        </div>

        <!-- Assignees -->
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-2">Assignees</label>
            <div id="assigneeList" class="space-y-2 mb-3">
                <?php foreach ($taskAssignees as $a): ?>
                <div class="flex items-center justify-between group" data-emp-id="<?= $a['id'] ?>">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 text-xs font-bold">
                            <?= strtoupper(substr($a['first_name'], 0, 1)) ?>
                        </div>
                        <span class="text-sm text-slate-700"><?= sanitize($a['first_name'] . ' ' . $a['last_name']) ?></span>
                    </div>
                    <button onclick="removeAssignee(<?= $taskId ?>, <?= $a['id'] ?>)" class="text-slate-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition text-xs">&#10005;</button>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="flex gap-2">
                <select id="assigneeSelect" class="flex-1 px-2 py-1.5 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20">
                    <option value="">Add person...</option>
                    <?php
                    $allEmps = $db->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();
                    foreach ($allEmps as $emp):
                        $isAssigned = false;
                        foreach ($taskAssignees as $a) { if ($a['id'] == $emp['id']) { $isAssigned = true; break; } }
                        if (!$isAssigned):
                    ?>
                    <option value="<?= $emp['id'] ?>"><?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?></option>
                    <?php endif; endforeach; ?>
                </select>
                <button onclick="addAssignee(<?= $taskId ?>)" class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">Add</button>
            </div>
        </div>

        <!-- Dates -->
        <div class="bg-white rounded-xl border border-slate-200 p-4 space-y-3">
            <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-2">Dates</label>
            <div>
                <label class="text-xs text-slate-400 block mb-1">Start Date</label>
                <input type="date" value="<?= $task['start_date'] ?>" onchange="updateField(<?= $taskId ?>, 'start_date', this.value)"
                       class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20">
            </div>
            <div>
                <label class="text-xs text-slate-400 block mb-1">Due Date</label>
                <input type="date" value="<?= $task['due_date'] ?>" onchange="updateField(<?= $taskId ?>, 'due_date', this.value)"
                       class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20">
            </div>
        </div>

        <!-- Task Info -->
        <div class="bg-white rounded-xl border border-slate-200 p-4 space-y-2 text-sm">
            <p class="text-xs text-slate-400">Created: <?= date('M j, Y g:i a', strtotime($task['created_at'])) ?></p>
            <p class="text-xs text-slate-400">Updated: <?= date('M j, Y g:i a', strtotime($task['updated_at'])) ?></p>
            <?php if (hasPermission('tasks.edit')): ?>
            <a href="<?= url('modules/tasks/edit.php?id=' . $taskId) ?>" class="block mt-2 text-center px-3 py-1.5 border border-slate-200 text-sm rounded-lg hover:bg-slate-50 text-slate-600 transition font-medium">
                <i class="fas fa-edit mr-1"></i> Edit Task
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function updateField(taskId, field, value) {
    fetch('ajax/update_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'task_id=' + taskId + '&field=' + field + '&value=' + encodeURIComponent(value)
    });
}

function addSubtask(taskId) {
    const input = document.getElementById('newSubtask');
    if (!input.value.trim()) return;
    fetch('ajax/subtask.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=add&task_id=' + taskId + '&title=' + encodeURIComponent(input.value.trim())
    }).then(r => r.json()).then(data => {
        if (data.success) { location.reload(); }
    });
}

function toggleSubtask(id, checked) {
    fetch('ajax/subtask.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=toggle&id=' + id + '&is_completed=' + (checked ? 1 : 0)
    }).then(r => r.json()).then(data => {
        if (data.success) updateSubtaskUI(data);
    });
}

function deleteSubtask(id) {
    if (!confirm('Delete this subtask?')) return;
    fetch('ajax/subtask.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=delete&id=' + id
    }).then(r => r.json()).then(data => {
        if (data.success) location.reload();
    });
}

function updateSubtaskUI(data) {
    document.getElementById('subDone').textContent = data.subdone;
    document.getElementById('subTotal').textContent = data.subtotal;
    const pct = data.subtotal > 0 ? Math.round(data.subdone / data.subtotal * 100) : 0;
    const bar = document.getElementById('subProgress');
    bar.style.width = pct + '%';
    bar.style.background = pct === 100 ? '#22c55e' : '#4f46e5';
}

function addComment(taskId) {
    const textarea = document.getElementById('newComment');
    if (!textarea.value.trim()) return;
    fetch('ajax/comment.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=add&task_id=' + taskId + '&comment=' + encodeURIComponent(textarea.value.trim())
    }).then(r => r.json()).then(data => {
        if (data.success) location.reload();
    });
}

function addAssignee(taskId) {
    const sel = document.getElementById('assigneeSelect');
    if (!sel.value) return;
    fetch('ajax/assignee.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=add&task_id=' + taskId + '&employee_id=' + sel.value
    }).then(r => r.json()).then(data => {
        if (data.success) location.reload();
    });
}

function removeAssignee(taskId, empId) {
    fetch('ajax/assignee.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=remove&task_id=' + taskId + '&employee_id=' + empId
    }).then(r => r.json()).then(data => {
        if (data.success) location.reload();
    });
}

function showAddTag() {
    document.getElementById('addTagForm').classList.remove('hidden');
}

function addTag(taskId) {
    const name = document.getElementById('newTagName');
    const color = document.getElementById('newTagColor');
    if (!name.value.trim()) return;
    fetch('ajax/tag.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=add&task_id=' + taskId + '&tag_name=' + encodeURIComponent(name.value.trim()) + '&tag_color=' + encodeURIComponent(color.value)
    }).then(r => r.json()).then(data => {
        if (data.success) location.reload();
    });
}

function deleteTag(id) {
    fetch('ajax/tag.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=delete&id=' + id
    }).then(r => r.json()).then(data => {
        if (data.success) location.reload();
    });
}

function editDesc() {
    document.getElementById('descDisplay').classList.add('hidden');
    document.getElementById('descEmpty').classList.add('hidden');
    document.getElementById('descForm').classList.remove('hidden');
}

function cancelEditDesc() {
    document.getElementById('descForm').classList.add('hidden');
    const hasDesc = document.getElementById('descText').value.trim().length > 0;
    document.getElementById('descDisplay').classList.toggle('hidden', !hasDesc);
    document.getElementById('descEmpty').classList.toggle('hidden', hasDesc);
}

function saveDesc(taskId) {
    const text = document.getElementById('descText').value.trim();
    fetch('ajax/update_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'task_id=' + taskId + '&field=task_description&value=' + encodeURIComponent(text)
    }).then(r => r.json()).then(data => {
        if (data.success) location.reload();
    });
}
</script>

<?php
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', $time);
}

function formatActivity($a) {
    $field = str_replace('_', ' ', $a['field_name'] ?? '');
    switch ($a['activity_type']) {
        case 'status_change': return "changed status from <strong>{$a['old_value']}</strong> to <strong>{$a['new_value']}</strong>";
        case 'priority_change': return "changed priority from <strong>{$a['old_value']}</strong> to <strong>{$a['new_value']}</strong>";
        case 'field_update': return "updated {$field}";
        case 'assignee_add': return "added <strong>{$a['new_value']}</strong> as assignee";
        case 'assignee_remove': return "removed <strong>{$a['old_value']}</strong> from assignees";
        case 'tag_add': return "added label <strong>{$a['new_value']}</strong>";
        case 'tag_remove': return "removed label <strong>{$a['old_value']}</strong>";
        case 'subtask_add': return "added subtask <strong>{$a['new_value']}</strong>";
        case 'subtask_complete': return "completed subtask <strong>{$a['new_value']}</strong>";
        case 'comment_add': return "commented on this task";
        default: return "{$a['activity_type']}";
    }
}

require_once '../../includes/footer.php';
?>
