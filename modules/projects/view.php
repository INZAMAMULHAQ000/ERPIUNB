<?php
$pageTitle = 'Project Details';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('projects.view')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT p.*, e.first_name as manager_first_name, e.last_name as manager_last_name FROM projects p LEFT JOIN employees e ON p.project_manager_id = e.id WHERE p.id = ?");
$stmt->execute([$id]);
$project = $stmt->fetch();

if (!$project) {
    flash('error', 'Project not found');
    to('http://localhost/ERP/modules/projects/index.php');
}

$members = $db->prepare("SELECT pm.*, e.first_name, e.last_name, e.employee_code FROM project_members pm JOIN employees e ON pm.employee_id = e.id WHERE pm.project_id = ?");
$members->execute([$id]);
$memberList = $members->fetchAll();

$milestones = $db->prepare("SELECT * FROM milestones WHERE project_id = ? ORDER BY due_date");
$milestones->execute([$id]);
$milestoneList = $milestones->fetchAll();

$tasks = $db->prepare("SELECT t.*, e.first_name, e.last_name FROM tasks t LEFT JOIN employees e ON t.assigned_to = e.id WHERE t.project_id = ? ORDER BY t.created_at DESC LIMIT 10");
$tasks->execute([$id]);
$taskList = $tasks->fetchAll();

$statusColors = ['planning' => 'bg-blue-100 text-blue-800', 'active' => 'bg-green-100 text-green-800', 'on_hold' => 'bg-yellow-100 text-yellow-800', 'completed' => 'bg-purple-100 text-purple-800', 'cancelled' => 'bg-red-100 text-red-800'];
$priorityColors = ['low' => 'bg-gray-100 text-gray-800', 'medium' => 'bg-blue-100 text-blue-800', 'high' => 'bg-orange-100 text-orange-800', 'critical' => 'bg-red-100 text-red-800'];
$taskStatusColors = ['todo' => 'bg-gray-100', 'in_progress' => 'bg-blue-100', 'review' => 'bg-yellow-100', 'testing' => 'bg-purple-100', 'completed' => 'bg-green-100', 'blocked' => 'bg-red-100'];
?>

<div class="mb-6 flex justify-between items-center">
    <a href="<?= url('modules/projects/index.php') ?>" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Projects
    </a>
    <?php if (hasPermission('projects.edit')): ?>
    <a href="<?= url('modules/projects/edit.php?id=' . $id . '') ?>" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 transition">
        <i class="fas fa-edit mr-2"></i>Edit Project
    </a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h2 class="text-xl font-semibold"><?= sanitize($project['project_name']) ?></h2>
                    <p class="text-gray-500"><?= sanitize($project['project_code']) ?></p>
                </div>
                <div class="flex gap-2">
                    <span class="px-3 py-1 text-sm rounded <?= $priorityColors[$project['priority']] ?? 'bg-gray-100' ?>"><?= ucfirst($project['priority']) ?></span>
                    <span class="px-3 py-1 text-sm rounded <?= $statusColors[$project['status']] ?? 'bg-gray-100' ?>"><?= ucwords(str_replace('_', ' ', $project['status'])) ?></span>
                </div>
            </div>
            
            <p class="text-gray-600 mb-4"><?= sanitize($project['description'] ?? 'No description') ?></p>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="text-gray-500">Client</span>
                    <p class="font-medium"><?= sanitize($project['client_name'] ?? '-') ?></p>
                </div>
                <div>
                    <span class="text-gray-500">Manager</span>
                    <p class="font-medium"><?= sanitize($project['manager_first_name'] . ' ' . $project['manager_last_name'] ?? '-') ?></p>
                </div>
                <div>
                    <span class="text-gray-500">Start Date</span>
                    <p class="font-medium"><?= $project['start_date'] ? date('M d, Y', strtotime($project['start_date'])) : '-' ?></p>
                </div>
                <div>
                    <span class="text-gray-500">End Date</span>
                    <p class="font-medium"><?= $project['end_date'] ? date('M d, Y', strtotime($project['end_date'])) : '-' ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Recent Tasks</h3>
                <a href="<?= url('modules/tasks/index.php?project_id=' . $id . '') ?>" class="text-blue-600 hover:text-blue-900 text-sm">View All Tasks</a>
            </div>
            <?php if ($taskList): ?>
            <div class="space-y-3">
                <?php foreach ($taskList as $task): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                    <div class="flex items-center">
                        <span class="w-2 h-2 rounded-full <?= str_replace('bg-', 'bg-', $taskStatusColors[$task['status']]) ?> mr-3"></span>
                        <span class="font-medium"><?= sanitize($task['task_title']) ?></span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-gray-500"><?= sanitize($task['first_name'] ?? 'Unassigned') ?></span>
                        <span class="text-xs px-2 py-1 rounded <?= $taskStatusColors[$task['status']] ?>"><?= ucwords(str_replace('_', ' ', $task['status'])) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-gray-500">No tasks yet</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Team Members</h3>
                <?php if (hasPermission('projects.edit')): ?>
                <a href="<?= url('modules/projects/members.php?id=' . $id . '') ?>" class="text-blue-600 hover:text-blue-900 text-sm">Manage</a>
                <?php endif; ?>
            </div>
            <?php if ($memberList): ?>
            <ul class="space-y-3">
                <?php foreach ($memberList as $member): ?>
                <li class="flex items-center">
                    <div class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center text-white text-sm font-bold mr-3">
                        <?= strtoupper(substr($member['first_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <p class="font-medium text-sm"><?= sanitize($member['first_name'] . ' ' . $member['last_name']) ?></p>
                        <p class="text-xs text-gray-500"><?= sanitize($member['member_role']) ?></p>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-gray-500 text-sm">No members assigned</p>
            <?php endif; ?>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Milestones</h3>
                <?php if (hasPermission('milestones.create')): ?>
                <a href="<?= url('modules/milestones/create.php?project_id=' . $id . '') ?>" class="text-blue-600 hover:text-blue-900 text-sm">Add</a>
                <?php endif; ?>
            </div>
            <?php if ($milestoneList): ?>
            <ul class="space-y-3">
                <?php foreach ($milestoneList as $ms): ?>
                <li class="p-3 bg-gray-50 rounded">
                    <div class="flex justify-between items-start">
                        <span class="font-medium text-sm"><?= sanitize($ms['milestone_name']) ?></span>
                        <span class="text-xs <?= $ms['status'] === 'completed' ? 'text-green-600' : 'text-gray-500' ?>"><?= $ms['due_date'] ? date('M d', strtotime($ms['due_date'])) : '' ?></span>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-gray-500 text-sm">No milestones</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

