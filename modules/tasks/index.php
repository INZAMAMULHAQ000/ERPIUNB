<?php
$pageTitle = 'Tasks';
require_once '../../includes/header.php';

if (!hasPermission('tasks.view')) {
    die('Access Denied');
}

$db = getDB();

// ——— Filters ———
$project_id  = $_GET['project_id'] ?? '';
$assigned_to = $_GET['assigned_to'] ?? '';
$priority    = $_GET['priority'] ?? '';
$search      = $_GET['search'] ?? '';
$viewMode    = $_GET['view'] ?? 'board';

// ——— Fetch all tasks with related data ———
$where  = "WHERE 1=1";
$params = [];

if ($project_id) {
    $where .= " AND t.project_id = ?";
    $params[] = (int)$project_id;
}
if ($assigned_to) {
    $where .= " AND (t.assigned_to = ? OR ta2.employee_id = ?)";
    $params[] = (int)$assigned_to;
    $params[] = (int)$assigned_to;
}
if ($priority) {
    $where .= " AND t.priority = ?";
    $params[] = $priority;
}
if ($search) {
    $where .= " AND (t.task_title LIKE ? OR t.task_description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = "SELECT DISTINCT t.*, p.project_name,
        (SELECT COUNT(*) FROM task_subtasks WHERE task_id = t.id) as subtotal,
        (SELECT COUNT(*) FROM task_subtasks WHERE task_id = t.id AND is_completed = 1) as subdone,
        (SELECT COUNT(*) FROM task_comments WHERE task_id = t.id) as comment_count
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        LEFT JOIN task_assignees ta2 ON t.id = ta2.task_id
        $where
        ORDER BY t.created_at DESC";

$allTasks = $db->prepare($sql);
$allTasks->execute($params);
$tasks = $allTasks->fetchAll();

// ——— Group for board view ———
$statuses = ['todo', 'in_progress', 'review', 'testing', 'completed', 'blocked'];
$statusLabels = [
    'todo'        => 'To Do',
    'in_progress' => 'In Progress',
    'review'      => 'Review',
    'testing'     => 'Testing',
    'completed'   => 'Completed',
    'blocked'     => 'Blocked'
];
$statusColors = [
    'todo'        => '#94a3b8',
    'in_progress' => '#3b82f6',
    'review'      => '#f59e0b',
    'testing'     => '#8b5cf6',
    'completed'   => '#22c55e',
    'blocked'     => '#ef4444'
];
$priorityColors = [
    'urgent' => '#ef4444',
    'high'   => '#f97316',
    'medium' => '#eab308',
    'low'    => '#94a3b8'
];

$board = [];
foreach ($statuses as $s) {
    $board[$s] = [];
}
foreach ($tasks as $t) {
    $board[$t['status']][] = $t;
}

// ——— Filter dropdowns ———
$projects = $db->query("SELECT id, project_name FROM projects ORDER BY project_name")->fetchAll();
$employees = $db->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.css">
<style>
    /* ═══════════════════════════════════════════════
       LIGHT MODE (default) — values in CSS variables
       ═══════════════════════════════════════════════ */
    :root {
        --col-bg: #f1f5f9;
        --card-bg: #fff;
        --card-border: #e2e8f0;
        --card-shadow: 0 1px 3px rgba(0,0,0,0.06);
        --card-shadow-hover: 0 4px 12px rgba(0,0,0,0.08);
        --card-progress-bg: #e2e8f0;
        --table-header-bg: #f8fafc;
        --table-header-border: #e2e8f0;
        --table-row-border: #f1f5f9;
        --table-row-hover: #f8fafc;
        --table-title-color: #1e293b;
        --tab-hover-bg: #f1f5f9;
        --tab-hover-color: #334155;
        --avatar-border: #fff;
        --ghost-bg: #bfdbfe;
        --ghost-border: #4f46e5;
        --drag-over-bg: #e2e8f0;
    }

    .task-board { display: flex; gap: 16px; overflow-x: auto; padding-bottom: 12px; min-height: 70vh; }
    .task-col { min-width: 280px; max-width: 280px; flex-shrink: 0; }
    .task-col-header { padding: 12px 14px; border-radius: 10px 10px 0 0; font-weight: 600; font-size: 0.85rem; display: flex; align-items: center; justify-content: space-between; }
    .task-col-body { background: var(--col-bg); border-radius: 0 0 10px 10px; padding: 10px; min-height: 120px; transition: background 0.15s; }
    .task-card {
        background: var(--card-bg); border-radius: 8px; padding: 12px; margin-bottom: 10px;
        box-shadow: var(--card-shadow); cursor: grab; transition: box-shadow 0.15s, transform 0.15s;
        border: 1px solid var(--card-border); position: relative;
    }
    .task-card:hover { box-shadow: var(--card-shadow-hover); transform: translateY(-1px); }
    .task-card:active { cursor: grabbing; }
    .task-card .priority-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
    .task-card .task-tag { display: inline-block; font-size: 0.65rem; padding: 1px 8px; border-radius: 999px; margin-right: 4px; margin-bottom: 4px; font-weight: 500; color: #fff; }
    .task-card .assignee-avatars { display: flex; }
    .task-card .assignee-avatars .avatar { width: 24px; height: 24px; border-radius: 50%; border: 2px solid var(--avatar-border); margin-left: -6px; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; font-weight: 600; color: #fff; }
    .task-card .assignee-avatars .avatar:first-child { margin-left: 0; }
    .task-col.drag-over .task-col-body { background: var(--drag-over-bg); }

    /* Drag-active column highlight (during drag) */
    .drag-active .task-col-body { background: var(--drag-over-bg); transition: background 0.2s; }
    .drag-active .task-col-header { opacity: 0.7; }

    /* Toast animation */
    .kanban-toast { transform: translateY(0); opacity: 1; }

    /* List view */
    .task-list-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .task-list-table th { padding: 10px 14px; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; text-align: left; background: var(--table-header-bg); border-bottom: 2px solid var(--table-header-border); }
    .task-list-table td { padding: 12px 14px; font-size: 0.875rem; border-bottom: 1px solid var(--table-row-border); vertical-align: middle; }
    .task-list-table tr:hover td { background: var(--table-row-hover); }
    .task-list-table .task-title-cell { font-weight: 500; color: var(--table-title-color); }
    .task-list-table .task-title-cell:hover { color: #4f46e5; }

    /* View tabs */
    .view-tab { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; font-size: 0.85rem; font-weight: 500; border-radius: 8px; cursor: pointer; transition: all 0.15s; color: #64748b; }
    .view-tab:hover { background: var(--tab-hover-bg); color: var(--tab-hover-color); }
    .view-tab.active { background: #4f46e5; color: #fff; }

    /* SortableJS ghost */
    .task-card.sortable-ghost { opacity: 0.4; background: var(--ghost-bg); border: 2px dashed var(--ghost-border); }
    .task-card.sortable-chosen { box-shadow: 0 8px 24px rgba(0,0,0,0.12); }

    /* Tooltip for assignees */
    .avatar-tooltip { position: relative; }
    .avatar-tooltip:hover .tooltip-text { visibility: visible; opacity: 1; }
    .tooltip-text { visibility: hidden; opacity: 0; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; font-size: 0.65rem; padding: 2px 8px; border-radius: 4px; white-space: nowrap; z-index: 10; transition: opacity 0.15s; margin-bottom: 4px; }

    /* ═══════════════════════════════════════════════
       DARK MODE — override ONLY the CSS variables
       ═══════════════════════════════════════════════ */
    [data-theme="dark"] {
        --col-bg: #0f172a;
        --card-bg: #1e293b;
        --card-border: #334155;
        --card-shadow: 0 1px 3px rgba(0,0,0,0.3);
        --card-shadow-hover: 0 4px 12px rgba(0,0,0,0.4);
        --card-progress-bg: #334155;
        --table-header-bg: #0f172a;
        --table-header-border: #334155;
        --table-row-border: #1e293b;
        --table-row-hover: #1e293b;
        --table-title-color: #f1f5f9;
        --tab-hover-bg: #1e293b;
        --tab-hover-color: #e2e8f0;
        --avatar-border: #1e293b;
        --ghost-bg: rgba(79,70,229,0.2);
        --ghost-border: #6366f1;
        --drag-over-bg: #334155;
    }

    /* These need explicit dark override since they're fixed Tailwind colors, not vars */
    [data-theme="dark"] .task-card .text-slate-800 { color: #f1f5f9 !important; }
    [data-theme="dark"] .task-card .text-slate-400 { color: #64748b !important; }
    [data-theme="dark"] .task-card .text-red-500 { color: #f87171 !important; }
    [data-theme="dark"] .task-card .bg-slate-100 { background-color: #334155 !important; }
    [data-theme="dark"] .task-list-table th { color: #94a3b8 !important; }
    [data-theme="dark"] .task-list-table .task-title-cell { color: #f1f5f9 !important; }
    [data-theme="dark"] .task-list-table .task-title-cell:hover { color: #818cf8 !important; }
    [data-theme="dark"] .view-tab { color: #64748b !important; }
    [data-theme="dark"] .view-tab:hover { background: #1e293b !important; color: #e2e8f0 !important; }
    [data-theme="dark"] .view-tab.active { background: #4f46e5 !important; color: #fff !important; }
</style>

<div class="mb-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Tasks</h1>
            <p class="text-gray-500 text-sm mt-0.5">Manage and track your team's work</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="?view=board<?= $project_id ? '&project_id='.$project_id : '' ?>" class="view-tab <?= $viewMode === 'board' ? 'active' : '' ?>">
                <i class="fas fa-columns"></i> Board
            </a>
            <a href="?view=list<?= $project_id ? '&project_id='.$project_id : '' ?>" class="view-tab <?= $viewMode === 'list' ? 'active' : '' ?>">
                <i class="fas fa-list"></i> List
            </a>
            <?php if (hasPermission('tasks.create')): ?>
            <a href="<?= url('modules/tasks/create.php' . ($project_id ? '?project_id=' . $project_id : '')) ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition flex items-center gap-2 text-sm font-medium shadow-sm">
                <i class="fas fa-plus"></i> New Task
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="mt-4 bg-white border border-slate-200 rounded-xl p-4 flex flex-wrap gap-3 items-center">
        <select name="project_id" onchange="filterChange(this)" style="width: 150px;" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
            <option value="">All Projects</option>
            <?php foreach ($projects as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $project_id == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['project_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="assigned_to" onchange="filterChange(this)" style="width: 150px;" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
            <option value="">All Assignees</option>
            <?php foreach ($employees as $e): ?>
            <option value="<?= $e['id'] ?>" <?= $assigned_to == $e['id'] ? 'selected' : '' ?>><?= sanitize($e['first_name'] . ' ' . $e['last_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="priority" onchange="filterChange(this)" style="width: 150px;" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
            <option value="">All Priorities</option>
            <option value="urgent" <?= $priority === 'urgent' ? 'selected' : '' ?>>Urgent</option>
            <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>High</option>
            <option value="medium" <?= $priority === 'medium' ? 'selected' : '' ?>>Medium</option>
            <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Low</option>
        </select>
        <div class="relative flex-1 min-w-[180px]">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text" id="searchInput" placeholder="Search tasks..." value="<?= sanitize($search) ?>"
                   onkeydown="if(event.key==='Enter')filterChange(this)"
                   class="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
        </div>
        <a href="<?= url('modules/tasks/index.php') ?>" class="text-sm text-slate-500 hover:text-slate-700 transition">
            <i class="fas fa-undo"></i> Reset
        </a>
    </div>
</div>

<?php if ($viewMode === 'board'): ?>

<!-- ═══ BOARD VIEW ═══ -->
<div class="task-board" id="taskBoard">
    <?php foreach ($statuses as $st):
        $colTasks = $board[$st];
        $count = count($colTasks);
        $overdue = array_filter($colTasks, fn($t) => $t['due_date'] && strtotime($t['due_date']) < strtotime(date('Y-m-d')) && $t['status'] !== 'completed');
    ?>
    <div class="task-col" data-status="<?= $st ?>">
        <div class="task-col-header" style="background:<?= $statusColors[$st] ?>18; color:<?= $statusColors[$st] ?>">
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $statusColors[$st] ?>;margin-right:8px"></span><?= $statusLabels[$st] ?></span>
            <span style="background:<?= $statusColors[$st] ?>22;padding:2px 10px;border-radius:999px;font-size:0.75rem"><?= $count ?></span>
        </div>
        <div class="task-col-body" id="col-<?= $st ?>">
            <?php foreach ($colTasks as $task):
                $assignees = $db->prepare("SELECT e.id, e.first_name, e.last_name FROM task_assignees ta JOIN employees e ON ta.employee_id = e.id WHERE ta.task_id = ?");
                $assignees->execute([$task['id']]);
                $taskAssignees = $assignees->fetchAll();
                $tags = $db->prepare("SELECT * FROM task_tags WHERE task_id = ?");
                $tags->execute([$task['id']]);
                $taskTags = $tags->fetchAll();
                $isOverdue = $task['due_date'] && strtotime($task['due_date']) < strtotime(date('Y-m-d')) && $task['status'] !== 'completed';
                $progress = $task['subtotal'] > 0 ? round(($task['subdone'] / $task['subtotal']) * 100) : -1;
            ?>
            <div class="task-card" data-task-id="<?= $task['id'] ?>">
                <!-- Top row: project + priority -->
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[0.65rem] font-medium text-slate-400 uppercase tracking-wide"><?= sanitize($task['project_name']) ?></span>
                    <span class="priority-dot" style="background:<?= $priorityColors[$task['priority']] ?? '#94a3b8' ?>"></span>
                </div>

                <!-- Title -->
                <a href="<?= url('modules/tasks/view.php?id=' . $task['id']) ?>" class="block text-sm font-semibold text-slate-800 mb-2 hover:text-indigo-600 transition leading-snug">
                    <?= sanitize($task['task_title']) ?>
                </a>

                <!-- Tags -->
                <?php if ($taskTags): ?>
                <div class="flex flex-wrap gap-1 mb-2">
                    <?php foreach ($taskTags as $tag): ?>
                    <span class="task-tag" style="background:<?= $tag['tag_color'] ?>"><?= sanitize($tag['tag_name']) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Meta row: due date, subtasks, comments -->
                <div class="flex items-center gap-3 text-[0.7rem] text-slate-400 mb-2">
                    <?php if ($task['due_date']): ?>
                    <span class="flex items-center gap-1 <?= $isOverdue ? 'text-red-500 font-medium' : '' ?>">
                        <i class="far fa-calendar-alt"></i> <?= date('M d', strtotime($task['due_date'])) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($task['subtotal'] > 0): ?>
                    <span class="flex items-center gap-1">
                        <i class="far fa-check-circle"></i> <?= $task['subdone'] ?>/<?= $task['subtotal'] ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($task['comment_count'] > 0): ?>
                    <span class="flex items-center gap-1">
                        <i class="far fa-comment"></i> <?= $task['comment_count'] ?>
                    </span>
                    <?php endif; ?>
                </div>

                <!-- Progress bar -->
                <?php if ($progress >= 0): ?>
                <div class="w-full h-1 bg-slate-100 rounded-full mb-2 overflow-hidden">
                    <div class="h-full rounded-full transition-all" style="width:<?= $progress ?>%;background:<?= $progress === 100 ? '#22c55e' : '#4f46e5' ?>"></div>
                </div>
                <?php endif; ?>

                <!-- Assignees -->
                <?php if ($taskAssignees): ?>
                <div class="assignee-avatars">
                    <?php foreach ($taskAssignees as $i => $a): ?>
                    <div class="avatar avatar-tooltip" style="background:hsl(<?= ($i * 47) % 360 ?>, 55%, 50%);z-index:<?= count($taskAssignees) - $i ?>">
                        <?= strtoupper(substr($a['first_name'], 0, 1)) ?>
                        <span class="tooltip-text"><?= sanitize($a['first_name'] . ' ' . $a['last_name']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php if ($count === 0): ?>
            <div class="flex flex-col items-center justify-center py-8 text-slate-400">
                <i class="far fa-file-alt text-2xl mb-2"></i>
                <span class="text-xs">No tasks</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php else: ?>

<!-- ═══ LIST VIEW ═══ -->
<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="task-list-table w-full">
        <thead>
            <tr>
                <th style="width:40%">Task</th>
                <th>Project</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Assignees</th>
                <th>Due Date</th>
                <th style="width:50px"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($tasks) === 0): ?>
            <tr><td colspan="7" class="text-center py-12 text-slate-400">
                <i class="far fa-file-alt text-3xl mb-2 block"></i>
                No tasks found matching your filters
            </td></tr>
            <?php endif; ?>
            <?php foreach ($tasks as $task):
                $assignees = $db->prepare("SELECT e.id, e.first_name, e.last_name FROM task_assignees ta JOIN employees e ON ta.employee_id = e.id WHERE ta.task_id = ?");
                $assignees->execute([$task['id']]);
                $taskAssignees = $assignees->fetchAll();
                $isOverdue = $task['due_date'] && strtotime($task['due_date']) < strtotime(date('Y-m-d')) && $task['status'] !== 'completed';
            ?>
            <tr onclick="window.location.href='<?= url('modules/tasks/view.php?id=' . $task['id']) ?>'" style="cursor:pointer">
                <td>
                    <div class="task-title-cell"><?= sanitize($task['task_title']) ?></div>
                    <?php if ($task['task_description']): ?>
                    <div class="text-xs text-slate-400 mt-0.5 line-clamp-1"><?= sanitize(substr($task['task_description'], 0, 120)) ?></div>
                    <?php endif; ?>
                </td>
                <td><span class="text-sm text-slate-600"><?= sanitize($task['project_name']) ?></span></td>
                <td>
                    <span class="inline-flex items-center gap-1.5 text-xs font-medium">
                        <span class="w-2 h-2 rounded-full" style="background:<?= $priorityColors[$task['priority']] ?? '#94a3b8' ?>"></span>
                        <?= ucfirst($task['priority']) ?>
                    </span>
                </td>
                <td>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium"
                          style="background:<?= $statusColors[$task['status']] ?>18;color:<?= $statusColors[$task['status']] ?>">
                        <span style="width:6px;height:6px;border-radius:50%;background:<?= $statusColors[$task['status']] ?>;display:inline-block"></span>
                        <?= $statusLabels[$task['status']] ?>
                    </span>
                </td>
                <td>
                    <div class="assignee-avatars">
                        <?php if (!$taskAssignees): ?>
                        <span class="text-xs text-slate-400">—</span>
                        <?php else: foreach ($taskAssignees as $i => $a): ?>
                        <div class="avatar" style="background:hsl(<?= ($i * 47) % 360 ?>, 55%, 50%)">
                            <?= strtoupper(substr($a['first_name'], 0, 1)) ?>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </td>
                <td class="<?= $isOverdue ? 'text-red-500 font-medium' : 'text-slate-500' ?> text-sm">
                    <?= $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : '—' ?>
                </td>
                <td>
                    <a href="<?= url('modules/tasks/view.php?id=' . $task['id']) ?>" class="text-slate-400 hover:text-indigo-600 transition">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<style>
select option {
    padding: 8px 10px;
    border-bottom: 1px solid #e2e8f0;
}
</style>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
// ——— Filter helpers ———
function filterChange(el) {
    const params = new URLSearchParams(window.location.search);
    const name = el.getAttribute('name') || el.id;
    if (el.value) params.set(name, el.value);
    else params.delete(name);
    window.location.search = params.toString();
}

// ——— Drag & Drop (Board view only) ———
<?php if ($viewMode === 'board'): ?>

const SORTABLES = [];

function initSortable() {
    document.querySelectorAll('.task-col-body').forEach(col => {
        const sort = new Sortable(col, {
            group: 'tasks',
            animation: 200,
            easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            delay: 150,
            delayOnTouchOnly: true,
            touchStartThreshold: 5,
            scroll: true,
            scrollSensitivity: 80,
            scrollSpeed: 12,

            onStart: function() {
                document.querySelectorAll('.task-col-body').forEach(b => {
                    b.closest('.task-col').classList.add('drag-active');
                });
            },

            onEnd: function(evt) {
                document.querySelectorAll('.task-col-body').forEach(b => {
                    b.closest('.task-col').classList.remove('drag-active');
                });

                const taskId = evt.item.dataset.taskId;
                const newStatus = evt.to.closest('.task-col').dataset.status;
                const oldStatus = evt.from.closest('.task-col').dataset.status;

                if (newStatus === oldStatus) return;

                // Show loading state on the card
                evt.item.style.opacity = '0.5';
                evt.item.style.pointerEvents = 'none';

                fetch('<?= url('modules/tasks/ajax/update_status.php') ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'task_id=' + taskId + '&field=status&value=' + encodeURIComponent(newStatus)
                })
                .then(r => r.json())
                .then(data => {
                    evt.item.style.opacity = '1';
                    evt.item.style.pointerEvents = '';
                    if (!data.success) {
                        showToast('Failed to update status', 'error');
                        location.reload();
                    } else {
                        updateColumnUI();
                        showToast('Moved to ' + newStatus.replace('_', ' '), 'success');
                    }
                })
                .catch(() => {
                    evt.item.style.opacity = '1';
                    evt.item.style.pointerEvents = '';
                    showToast('Network error — reverting', 'error');
                    location.reload();
                });
            }
        });
        SORTABLES.push(sort);
    });
}

function updateColumnUI() {
    document.querySelectorAll('.task-col').forEach(col => {
        const body = col.querySelector('.task-col-body');
        const cards = body.querySelectorAll('.task-card');
        const countEl = col.querySelector('.task-col-header span:last-child');
        if (countEl) countEl.textContent = cards.length;

        // Show/hide empty state
        let emptyEl = body.querySelector('.col-empty-state');
        if (cards.length === 0) {
            if (!emptyEl) {
                emptyEl = document.createElement('div');
                emptyEl.className = 'col-empty-state flex flex-col items-center justify-center py-8 text-slate-400';
                emptyEl.innerHTML = '<i class="far fa-file-alt text-2xl mb-2"></i><span class="text-xs">No tasks</span>';
                body.appendChild(emptyEl);
            }
            emptyEl.style.display = '';
        } else if (emptyEl) {
            emptyEl.style.display = 'none';
        }
    });
}

// ——— Toast notification ———
function showToast(msg, type) {
    const existing = document.querySelector('.kanban-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'kanban-toast fixed bottom-6 right-6 z-50 px-4 py-2.5 rounded-lg text-sm font-medium shadow-lg transition-all duration-300 ' +
        (type === 'success'
            ? 'bg-green-600 text-white'
            : 'bg-red-600 text-white');
    toast.textContent = msg;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(8px)';
        setTimeout(() => toast.remove(), 300);
    }, 2000);
}

// ——— Auto-scroll on drag near edges ———
document.querySelectorAll('.task-board').forEach(board => {
    board.addEventListener('dragover', function(e) {
        const rect = this.getBoundingClientRect();
        const threshold = 60;
        if (e.clientX - rect.left < threshold) this.scrollLeft -= 8;
        if (rect.right - e.clientX < threshold) this.scrollLeft += 8;
    });
});

// Init on load
initSortable();
<?php endif; ?>
</script>

<?php require_once '../../includes/footer.php'; ?>
