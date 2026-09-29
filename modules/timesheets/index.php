<?php
$pageTitle = 'Timesheets';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('timesheets.view')) {
    die('Access Denied');
}

$db = getDB();
$employee_id = $_GET['employee_id'] ?? '';
$project_id = $_GET['project_id'] ?? '';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$page = getPageNumber();
$perPage = 20;

$where = "WHERE 1=1";
$params = [];

if ($employee_id) {
    $where .= " AND t.employee_id = ?";
    $params[] = $employee_id;
}

if ($project_id) {
    $where .= " AND t.project_id = ?";
    $params[] = $project_id;
}

if ($month && $year) {
    $where .= " AND MONTH(t.work_date) = ? AND YEAR(t.work_date) = ?";
    $params[] = $month;
    $params[] = $year;
}

$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM timesheets t $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];

$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT t.*, e.first_name, e.last_name, p.project_name
    FROM timesheets t
    JOIN employees e ON t.employee_id = e.id
    JOIN projects p ON t.project_id = p.id
    $where ORDER BY t.work_date DESC, t.created_at DESC LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$timesheets = $stmt->fetchAll();

$employees = $db->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();
$projects = $db->query("SELECT id, project_name FROM projects WHERE status IN ('active', 'planning') ORDER BY project_name")->fetchAll();

$totalHours = $db->query("SELECT SUM(hours_logged) as total FROM timesheets WHERE MONTH(work_date) = $month AND YEAR(work_date) = $year")->fetch()['total'] ?? 0;
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Timesheets</h1>
        <p class="text-gray-600">Track employee working hours</p>
    </div>
    <?php if (hasPermission('timesheets.create')): ?>
    <a href="<?= url('modules/timesheets/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>Log Hours
    </a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Total Entries</div>
        <div class="text-2xl font-bold text-gray-800"><?= $total ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Total Hours (<?= date('M Y', mktime(0, 0, 0, $month)) ?>)</div>
        <div class="text-2xl font-bold text-gray-800"><?= number_format($totalHours, 1) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">This Week</div>
        <div class="text-2xl font-bold text-gray-800"><?= number_format($db->query("SELECT SUM(hours_logged) as h FROM timesheets WHERE WEEK(work_date) = WEEK(CURDATE())")->fetch()['h'] ?? 0, 1) ?></div>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="w-48">
            <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
            <select name="employee_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>" <?= $employee_id == $emp['id'] ? 'selected' : '' ?>><?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-48">
            <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
            <select name="project_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach ($projects as $proj): ?>
                <option value="<?= $proj['id'] ?>" <?= $project_id == $proj['id'] ? 'selected' : '' ?>><?= sanitize($proj['project_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-32">
            <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
            <select name="month" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $month == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>><?= date('M', mktime(0, 0, 0, $m)) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="w-24">
            <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
            <select name="year" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <?php for ($y = date('Y') - 1; $y <= date('Y'); $y++): ?>
                <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-search"></i>
        </button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hours</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($timesheets as $ts): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 text-gray-600"><?= date('M d, Y', strtotime($ts['work_date'])) ?></td>
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= sanitize($ts['first_name'] . ' ' . $ts['last_name']) ?></div>
                </td>
                <td class="px-6 py-4 text-gray-600"><?= sanitize($ts['project_name']) ?></td>
                <td class="px-6 py-4">
                    <span class="font-medium"><?= $ts['hours_logged'] ?>h</span>
                </td>
                <td class="px-6 py-4 text-gray-500 text-sm max-w-xs truncate"><?= sanitize($ts['work_description'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($timesheets)): ?>
            <tr>
                <td colspan="5" class="px-6 py-8 text-center text-gray-500">No timesheet entries found</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginationLinks($pagination, '?') ?>

<?php require_once '../../includes/footer.php'; ?>

