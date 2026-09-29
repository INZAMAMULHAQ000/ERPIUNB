<?php
$pageTitle = 'Projects';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('projects.view')) {
    die('Access Denied');
}

$db = getDB();
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (p.project_name LIKE ? OR p.project_code LIKE ? OR p.client_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $where .= " AND p.status = ?";
    $params[] = $status;
}

if ($priority) {
    $where .= " AND p.priority = ?";
    $params[] = $priority;
}

$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM projects p $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];

$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT p.*, e.first_name as manager_first_name, e.last_name as manager_last_name
    FROM projects p
    LEFT JOIN employees e ON p.project_manager_id = e.id
    $where ORDER BY p.created_at DESC LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$projects = $stmt->fetchAll();

$statusColors = ['planning' => 'bg-blue-100 text-blue-800', 'active' => 'bg-green-100 text-green-800', 'on_hold' => 'bg-yellow-100 text-yellow-800', 'completed' => 'bg-purple-100 text-purple-800', 'cancelled' => 'bg-red-100 text-red-800'];
$priorityColors = ['low' => 'bg-gray-100 text-gray-800', 'medium' => 'bg-blue-100 text-blue-800', 'high' => 'bg-orange-100 text-orange-800', 'critical' => 'bg-red-100 text-red-800'];
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Projects</h1>
        <p class="text-gray-600">Manage all projects</p>
    </div>
    <?php if (hasPermission('projects.create')): ?>
    <a href="<?= url('modules/projects/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>New Project
    </a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <?php
    $stats = [
        ['label' => 'Total Projects', 'value' => $total, 'color' => 'blue'],
        ['label' => 'Active', 'value' => $db->query("SELECT COUNT(*) as c FROM projects WHERE status = 'active'")->fetch()['c'], 'color' => 'green'],
        ['label' => 'Planning', 'value' => $db->query("SELECT COUNT(*) as c FROM projects WHERE status = 'planning'")->fetch()['c'], 'color' => 'yellow'],
        ['label' => 'Completed', 'value' => $db->query("SELECT COUNT(*) as c FROM projects WHERE status = 'completed'")->fetch()['c'], 'color' => 'purple']
    ];
    foreach ($stats as $stat): ?>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500"><?= $stat['label'] ?></div>
        <div class="text-2xl font-bold text-gray-800"><?= $stat['value'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Project name, code..." 
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="planning" <?= $status === 'planning' ? 'selected' : '' ?>>Planning</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="on_hold" <?= $status === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
            <select name="priority" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Low</option>
                <option value="medium" <?= $priority === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>High</option>
                <option value="critical" <?= $priority === 'critical' ? 'selected' : '' ?>>Critical</option>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-search"></i>
        </button>
    </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($projects as $proj): 
    $taskCount = $db->prepare("SELECT COUNT(*) as c FROM tasks WHERE project_id = ?")->execute([$proj['id']]) ? $db->query("SELECT COUNT(*) as c FROM tasks WHERE project_id = {$proj['id']}")->fetch()['c'] : 0;
    $completedTasks = $db->query("SELECT COUNT(*) as c FROM tasks WHERE project_id = {$proj['id']} AND status = 'completed'")->fetch()['c'];
    $memberCount = $db->query("SELECT COUNT(*) as c FROM project_members WHERE project_id = {$proj['id']}")->fetch()['c'];
    ?>
    <div class="bg-white rounded-lg shadow hover:shadow-lg transition">
        <div class="p-6">
            <div class="flex justify-between items-start mb-3">
                <h3 class="text-lg font-semibold text-gray-800"><?= sanitize($proj['project_name']) ?></h3>
                <span class="px-2 py-1 text-xs rounded <?= $priorityColors[$proj['priority']] ?? 'bg-gray-100' ?>">
                    <?= ucfirst($proj['priority']) ?>
                </span>
            </div>
            <p class="text-sm text-gray-500 mb-3"><?= sanitize($proj['project_code']) ?></p>
            <p class="text-sm text-gray-600 mb-4"><?= sanitize($proj['client_name'] ?? 'No client') ?></p>
            
            <div class="flex items-center justify-between text-sm text-gray-500 mb-4">
                <span><i class="fas fa-user mr-1"></i> <?= sanitize($proj['manager_first_name'] ?? '-') ?></span>
                <span><i class="fas fa-users mr-1"></i> <?= $memberCount ?> members</span>
            </div>
            
            <div class="mb-4">
                <div class="flex justify-between text-sm mb-1">
                    <span>Progress</span>
                    <span><?= $taskCount > 0 ? round(($completedTasks / $taskCount) * 100) : 0 ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $taskCount > 0 ? round(($completedTasks / $taskCount) * 100) : 0 ?>%"></div>
                </div>
            </div>
            
            <div class="flex justify-between items-center">
                <span class="px-2 py-1 text-xs rounded <?= $statusColors[$proj['status']] ?? 'bg-gray-100' ?>">
                    <?= ucwords(str_replace('_', ' ', $proj['status'])) ?>
                </span>
                <a href="<?= url('modules/projects/view.php?id=' . $proj['id'] . '') ?>" class="text-blue-600 hover:text-blue-900 text-sm">View Details <i class="fas fa-arrow-right ml-1"></i></a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($projects)): ?>
    <div class="col-span-full text-center py-8 text-gray-500">No projects found</div>
    <?php endif; ?>
</div>

<?= paginationLinks($pagination, '?') ?>

<?php require_once '../../includes/footer.php'; ?>

