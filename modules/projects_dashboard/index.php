<?php
$pageTitle = 'Projects Dashboard';
$dashboardKey = 'projects';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('projects.view') && !hasPermission('tasks.view') && !hasPermission('timesheets.view')) { die('Access Denied'); }

$db = getDB();

$totalProjects = $db->query("SELECT COUNT(*) FROM projects")->fetchColumn();
$activeProjects = $db->query("SELECT COUNT(*) FROM projects WHERE status = 'active'")->fetchColumn();
$planningProjects = $db->query("SELECT COUNT(*) FROM projects WHERE status = 'planning'")->fetchColumn();
$completedProjects = $db->query("SELECT COUNT(*) FROM projects WHERE status = 'completed'")->fetchColumn();
$onHoldProjects = $db->query("SELECT COUNT(*) FROM projects WHERE status = 'on_hold'")->fetchColumn();
$cancelledProjects = $db->query("SELECT COUNT(*) FROM projects WHERE status = 'cancelled'")->fetchColumn();
$overdueProjects = $db->query("SELECT COUNT(*) FROM projects WHERE end_date < CURDATE() AND status IN ('active','planning')")->fetchColumn();

$totalTasks = $db->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$todoTasks = $db->query("SELECT COUNT(*) FROM tasks WHERE status = 'todo'")->fetchColumn();
$inProgressTasks = $db->query("SELECT COUNT(*) FROM tasks WHERE status = 'in_progress'")->fetchColumn();
$reviewTasks = $db->query("SELECT COUNT(*) FROM tasks WHERE status = 'review'")->fetchColumn();
$completedTasks = $db->query("SELECT COUNT(*) FROM tasks WHERE status = 'completed'")->fetchColumn();
$blockedTasks = $db->query("SELECT COUNT(*) FROM tasks WHERE status = 'blocked'")->fetchColumn();
$overdueTasks = $db->query("SELECT COUNT(*) FROM tasks WHERE due_date < CURDATE() AND status NOT IN ('completed','blocked')")->fetchColumn();

$totalHoursLogged = $db->query("SELECT COALESCE(SUM(hours_logged),0) FROM timesheets")->fetchColumn();
$hoursThisMonth = $db->query("SELECT COALESCE(SUM(hours_logged),0) FROM timesheets WHERE MONTH(work_date) = MONTH(NOW()) AND YEAR(work_date) = YEAR(NOW())")->fetchColumn();

$projectsByStatus = $db->query("SELECT status, COUNT(*) as cnt FROM projects GROUP BY status ORDER BY FIELD(status,'planning','active','on_hold','completed','cancelled')")->fetchAll();
$tasksByStatus = $db->query("SELECT status, COUNT(*) as cnt FROM tasks GROUP BY status ORDER BY FIELD(status,'todo','in_progress','review','testing','completed','blocked')")->fetchAll();
$tasksByPriority = $db->query("SELECT priority, COUNT(*) as cnt FROM tasks WHERE status NOT IN ('completed','blocked') GROUP BY priority ORDER BY FIELD(priority,'urgent','high','medium','low')")->fetchAll();

$projectsByMonth = $db->query("
    SELECT DATE_FORMAT(created_at, '%b') as label, COUNT(*) as cnt
    FROM projects
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY MIN(created_at)
")->fetchAll();

$hoursByProject = $db->query("
    SELECT p.project_name, SUM(t.hours_logged) as total_hours
    FROM timesheets t
    JOIN projects p ON t.project_id = p.id
    WHERE MONTH(t.work_date) = MONTH(NOW()) AND YEAR(t.work_date) = YEAR(NOW())
    GROUP BY p.id
    ORDER BY total_hours DESC
    LIMIT 8
")->fetchAll();

$recentProjects = $db->query("
    SELECT p.*, CONCAT(e.first_name, ' ', e.last_name) as manager_name
    FROM projects p
    LEFT JOIN employees e ON p.project_manager_id = e.id
    ORDER BY p.created_at DESC LIMIT 8
")->fetchAll();

$upcomingMilestones = $db->query("
    SELECT m.*, p.project_name
    FROM milestones m
    JOIN projects p ON m.project_id = p.id
    WHERE m.due_date >= CURDATE() AND m.status NOT IN ('completed','cancelled')
    ORDER BY m.due_date ASC LIMIT 6
")->fetchAll();

$recentTasks = $db->query("
    SELECT t.*, p.project_name, CONCAT(e.first_name, ' ', e.last_name) as assignee_name
    FROM tasks t
    LEFT JOIN projects p ON t.project_id = p.id
    LEFT JOIN employees e ON t.assigned_to = e.id
    ORDER BY t.created_at DESC LIMIT 8
")->fetchAll();

$overdueTaskList = $db->query("
    SELECT t.*, p.project_name, CONCAT(e.first_name, ' ', e.last_name) as assignee_name
    FROM tasks t
    LEFT JOIN projects p ON t.project_id = p.id
    LEFT JOIN employees e ON t.assigned_to = e.id
    WHERE t.due_date < CURDATE() AND t.status NOT IN ('completed','blocked')
    ORDER BY t.due_date ASC LIMIT 6
")->fetchAll();

$projectStatusColors = [
    'planning' => 'bg-blue-100 text-blue-800',
    'active' => 'bg-green-100 text-green-800',
    'on_hold' => 'bg-yellow-100 text-yellow-800',
    'completed' => 'bg-gray-100 text-gray-800',
    'cancelled' => 'bg-red-100 text-red-800'
];
$taskStatusColors = [
    'todo' => 'bg-slate-100 text-slate-800',
    'in_progress' => 'bg-blue-100 text-blue-800',
    'review' => 'bg-purple-100 text-purple-800',
    'testing' => 'bg-amber-100 text-amber-800',
    'completed' => 'bg-green-100 text-green-800',
    'blocked' => 'bg-red-100 text-red-800'
];
$taskPriorityColors = [
    'urgent' => 'bg-red-100 text-red-800',
    'high' => 'bg-orange-100 text-orange-800',
    'medium' => 'bg-blue-100 text-blue-800',
    'low' => 'bg-gray-100 text-gray-800'
];
?>

<div id="dashboard-old-content">

<!-- Header -->
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Projects Dashboard</h1>
    <p class="text-gray-500 text-sm mt-1">Project management overview</p>
</div>

<!-- ===== KPI Stats Cards ===== -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
    <a href="<?= url('modules/projects/index.php') ?>" class="bg-white rounded-xl shadow-sm p-4 border border-slate-100 hover:shadow-md transition block">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Projects</span>
            <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center">
                <i class="fas fa-project-diagram text-blue-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-slate-800"><?= $totalProjects ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= $activeProjects ?> active</p>
    </a>
    <a href="<?= url('modules/tasks/index.php') ?>" class="bg-white rounded-xl shadow-sm p-4 border border-slate-100 hover:shadow-md transition block">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Tasks</span>
            <div class="w-9 h-9 rounded-lg bg-violet-50 flex items-center justify-center">
                <i class="fas fa-tasks text-violet-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-slate-800"><?= $totalTasks ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= $inProgressTasks ?> in progress</p>
    </a>
    <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Hours Logged</span>
            <div class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center">
                <i class="fas fa-clock text-amber-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-slate-800"><?= number_format($totalHoursLogged, 1) ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= number_format($hoursThisMonth, 1) ?> this month</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Overdue</span>
            <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-red-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-red-600"><?= $overdueProjects ?></p>
        <p class="text-xs text-slate-400 mt-1">projects behind</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Blocked</span>
            <div class="w-9 h-9 rounded-lg bg-orange-50 flex items-center justify-center">
                <i class="fas fa-ban text-orange-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-orange-600"><?= $blockedTasks ?></p>
        <p class="text-xs text-slate-400 mt-1">tasks blocked</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Completed</span>
            <div class="w-9 h-9 rounded-lg bg-green-50 flex items-center justify-center">
                <i class="fas fa-check-circle text-green-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-green-600"><?= $completedProjects ?></p>
        <p class="text-xs text-slate-400 mt-1">projects done</p>
    </div>
</div>

<!-- ===== Sub-Module Navigation Cards ===== -->
<div class="grid grid-cols-3 gap-4 mb-8">
    <?php if (hasPermission('projects.view')): ?>
    <a href="<?= url('modules/projects/index.php') ?>" class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 hover:shadow-md transition group">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-blue-500 flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-project-diagram text-white"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800">Projects</h3>
                <p class="text-xs text-slate-400">Manage projects</p>
            </div>
        </div>
        <span class="text-xl font-bold text-slate-800"><?= $totalProjects ?></span>
    </a>
    <?php endif; ?>
    <?php if (hasPermission('tasks.view')): ?>
    <a href="<?= url('modules/tasks/index.php') ?>" class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 hover:shadow-md transition group">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-violet-500 flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-tasks text-white"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800">Tasks</h3>
                <p class="text-xs text-slate-400">Task management</p>
            </div>
        </div>
        <span class="text-xl font-bold text-slate-800"><?= $totalTasks ?></span>
    </a>
    <?php endif; ?>
    <?php if (hasPermission('timesheets.view')): ?>
    <a href="<?= url('modules/timesheets/index.php') ?>" class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 hover:shadow-md transition group">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-amber-500 flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-hourglass-half text-white"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800">Timesheets</h3>
                <p class="text-xs text-slate-400">Time tracking</p>
            </div>
        </div>
        <span class="text-xl font-bold text-amber-600"><?= number_format($hoursThisMonth, 1) ?>h</span>
    </a>
    <?php endif; ?>
</div>

<!-- ===== Charts Row 1: Projects by Status + Tasks by Status ===== -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-pie text-blue-500 text-xs"></i>
            Projects by Status
        </h3>
        <div id="projectStatusChart" style="min-height: 260px"></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-bar text-violet-500 text-xs"></i>
            Tasks by Status
        </h3>
        <div id="taskStatusChart" style="min-height: 260px"></div>
    </div>
</div>

<!-- ===== Charts Row 2: Task Priority + Hours by Project ===== -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-flag text-red-500 text-xs"></i>
            Tasks by Priority
        </h3>
        <div id="taskPriorityChart" style="min-height: 260px"></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-clock text-amber-500 text-xs"></i>
            Hours by Project (This Month)
        </h3>
        <div id="hoursByProjectChart" style="min-height: 260px"></div>
    </div>
</div>

<!-- ===== Upcoming Milestones + Overdue Tasks ===== -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-flag-checkered text-indigo-500 text-xs"></i>
            Upcoming Milestones
        </h3>
        <?php if (empty($upcomingMilestones)): ?>
        <p class="text-sm text-slate-400 text-center py-6">No upcoming milestones</p>
        <?php else: ?>
        <div class="space-y-0">
            <?php foreach ($upcomingMilestones as $ms): ?>
            <div class="flex items-center justify-between py-2.5 border-b border-slate-50 last:border-0">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-800"><?= htmlspecialchars($ms['milestone_name']) ?></p>
                    <p class="text-xs text-slate-400"><?= htmlspecialchars($ms['project_name']) ?></p>
                </div>
                <span class="text-xs text-slate-500 flex-shrink-0 ml-3"><?= date('M d, Y', strtotime($ms['due_date'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-exclamation-circle text-red-500 text-xs"></i>
            Overdue Tasks
        </h3>
        <?php if (empty($overdueTaskList)): ?>
        <p class="text-sm text-green-500 text-center py-6">No overdue tasks</p>
        <?php else: ?>
        <div class="space-y-0">
            <?php foreach ($overdueTaskList as $ot): ?>
            <div class="flex items-center justify-between py-2.5 border-b border-slate-50 last:border-0">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-800 truncate"><?= htmlspecialchars($ot['task_title']) ?></p>
                    <p class="text-xs text-slate-400"><?= htmlspecialchars($ot['project_name'] ?? 'N/A') ?> &middot; <?= htmlspecialchars($ot['assignee_name'] ?? 'Unassigned') ?></p>
                </div>
                <span class="px-2 py-0.5 text-xs rounded-full <?= $taskPriorityColors[$ot['priority']] ?? '' ?> flex-shrink-0 ml-3"><?= ucfirst($ot['priority']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Recent Projects ===== -->
<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 mb-8">
    <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
        <i class="fas fa-project-diagram text-blue-500 text-xs"></i>
        Recent Projects
    </h3>
    <?php if (empty($recentProjects)): ?>
    <p class="text-sm text-slate-400 text-center py-6">No projects yet</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100">
                    <th class="text-left text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2">Project</th>
                    <th class="text-left text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2">Manager</th>
                    <th class="text-left text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2">Dates</th>
                    <th class="text-left text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2">Priority</th>
                    <th class="text-left text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($recentProjects as $rp): ?>
                <tr class="hover:bg-slate-50">
                    <td class="py-2.5">
                        <span class="font-mono text-xs text-blue-600"><?= htmlspecialchars($rp['project_code']) ?></span>
                        <span class="text-slate-800 font-medium ml-2"><?= htmlspecialchars($rp['project_name']) ?></span>
                    </td>
                    <td class="py-2.5 text-slate-600"><?= htmlspecialchars($rp['manager_name'] ?? 'N/A') ?></td>
                    <td class="py-2.5 text-slate-500 text-xs">
                        <?= $rp['start_date'] ? date('M d', strtotime($rp['start_date'])) : 'N/A' ?>
                        &rarr; <?= $rp['end_date'] ? date('M d, Y', strtotime($rp['end_date'])) : 'N/A' ?>
                    </td>
                    <td class="py-2.5"><span class="px-2 py-0.5 text-xs rounded-full <?= $taskPriorityColors[$rp['priority']] ?? '' ?>"><?= ucfirst($rp['priority']) ?></span></td>
                    <td class="py-2.5"><span class="px-2 py-0.5 text-xs rounded-full <?= $projectStatusColors[$rp['status']] ?? '' ?>"><?= str_replace('_', ' ', ucfirst($rp['status'])) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ===== Quick Actions ===== -->
<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 mb-8">
    <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
        <i class="fas fa-bolt text-indigo-500 text-xs"></i>
        Quick Actions
    </h3>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <?php if (hasPermission('projects.create')): ?>
        <a href="<?= url('modules/projects/create.php') ?>" class="flex items-center gap-3 p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
            <i class="fas fa-plus-circle text-blue-600 text-xl"></i>
            <div>
                <div class="font-medium text-sm">New Project</div>
                <div class="text-xs text-gray-500">Create project</div>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('tasks.create')): ?>
        <a href="<?= url('modules/tasks/create.php') ?>" class="flex items-center gap-3 p-4 bg-violet-50 rounded-lg hover:bg-violet-100 transition">
            <i class="fas fa-tasks text-violet-600 text-xl"></i>
            <div>
                <div class="font-medium text-sm">New Task</div>
                <div class="text-xs text-gray-500">Create task</div>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('timesheets.create')): ?>
        <a href="<?= url('modules/timesheets/create.php') ?>" class="flex items-center gap-3 p-4 bg-amber-50 rounded-lg hover:bg-amber-100 transition">
            <i class="fas fa-hourglass-half text-amber-600 text-xl"></i>
            <div>
                <div class="font-medium text-sm">Log Time</div>
                <div class="text-xs text-gray-500">Record hours</div>
            </div>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Charts JS ===== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const render = window.renderCarbonChart;

    // Projects by Status (doughnut)
    const projEl = document.getElementById('projectStatusChart');
    if (projEl && render) {
        const pLabels = <?= json_encode(array_map(function($s) { return str_replace('_', ' ', ucfirst($s['status'])); }, $projectsByStatus)) ?>;
        const pData = <?= json_encode(array_map('intval', array_column($projectsByStatus, 'cnt'))) ?>;
        render(projEl, 'doughnut', {
            labels: pLabels,
            datasets: [{ data: pData, backgroundColor: ['#3b82f6','#22c55e','#f59e0b','#94a3b8','#ef4444'], borderWidth: 0 }]
        }, { legend: { position: 'bottom' } });
    }

    // Tasks by Status (bar)
    const taskEl = document.getElementById('taskStatusChart');
    if (taskEl && render) {
        const tLabels = <?= json_encode(array_map(function($s) { return str_replace('_', ' ', ucfirst($s['status'])); }, $tasksByStatus)) ?>;
        const tData = <?= json_encode(array_map('intval', array_column($tasksByStatus, 'cnt'))) ?>;
        render(taskEl, 'bar', {
            labels: tLabels,
            datasets: [{ label: 'Tasks', data: tData, backgroundColor: ['#94a3b8','#6366f1','#a855f7','#f59e0b','#22c55e','#ef4444'] }]
        }, { scales: { y: { beginAtZero: true } }, legend: { enabled: false } });
    }

    // Tasks by Priority (doughnut)
    const priEl = document.getElementById('taskPriorityChart');
    if (priEl && render) {
        const priLabels = <?= json_encode(array_map(function($p) { return ucfirst($p['priority']); }, $tasksByPriority)) ?>;
        const priData = <?= json_encode(array_map('intval', array_column($tasksByPriority, 'cnt'))) ?>;
        render(priEl, 'doughnut', {
            labels: priLabels,
            datasets: [{ data: priData, backgroundColor: ['#ef4444','#f97316','#3b82f6','#94a3b8'], borderWidth: 0 }]
        }, { legend: { position: 'bottom' } });
    }

    // Hours by Project (horizontal bar)
    const hoursEl = document.getElementById('hoursByProjectChart');
    if (hoursEl && render) {
        const hLabels = <?= json_encode(array_column($hoursByProject, 'project_name')) ?>;
        const hData = <?= json_encode(array_map('floatval', array_column($hoursByProject, 'total_hours'))) ?>;
        render(hoursEl, 'bar', {
            labels: hLabels,
            datasets: [{ label: 'Hours', data: hData, backgroundColor: '#f59e0b' }]
        }, { scales: { x: { beginAtZero: true } }, legend: { enabled: false }, bar: { horizontal: true } });
    }
});
</script>

</div><!-- /dashboard-old-content -->

<?php
require_once '../../includes/dashboard_template.php';
require_once '../../includes/footer.php'; ?>
