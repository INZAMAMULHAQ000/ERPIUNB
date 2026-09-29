<?php
$pageTitle = 'Asset Assignments';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('asset_assignments.view')) { die('Access Denied'); }

$db = getDB();
$status = $_GET['status'] ?? '';
$employee_id = $_GET['employee_id'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($status) { $where .= " AND aa.status = ?"; $params[] = $status; }
if ($employee_id) { $where .= " AND aa.employee_id = ?"; $params[] = $employee_id; }

$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM asset_assignments aa $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("SELECT aa.*, a.asset_code, a.asset_name, e.first_name, e.last_name, e.employee_code as emp_code 
    FROM asset_assignments aa 
    JOIN assets a ON aa.asset_id = a.id 
    JOIN employees e ON aa.employee_id = e.id 
    $where 
    ORDER BY aa.assigned_date DESC 
    LIMIT ? OFFSET ?");
$stmt->execute($queryParams);
$assignments = $stmt->fetchAll();

$employees = $db->query("SELECT id, first_name, last_name, employee_code FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Asset Assignments</h1>
        <p class="text-gray-600">Track asset allocations to employees</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <?php
    $activeCount = $db->query("SELECT COUNT(*) as c FROM asset_assignments WHERE status = 'active'")->fetch()['c'];
    $returnedCount = $db->query("SELECT COUNT(*) as c FROM asset_assignments WHERE status = 'returned'")->fetch()['c'];
    $overdueCount = $db->query("SELECT COUNT(*) as c FROM asset_assignments WHERE status = 'overdue'")->fetch()['c'];
    ?>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Active Assignments</div>
        <div class="text-2xl font-bold text-blue-600"><?= $activeCount ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Returned</div>
        <div class="text-2xl font-bold text-green-600"><?= $returnedCount ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Overdue</div>
        <div class="text-2xl font-bold text-red-600"><?= $overdueCount ?></div>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="returned" <?= $status === 'returned' ? 'selected' : '' ?>>Returned</option>
                <option value="overdue" <?= $status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
            </select>
        </div>
        <div class="w-48">
            <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
            <select name="employee_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach ($employees as $emp): ?><option value="<?= $emp['id'] ?>" <?= $employee_id == $emp['id'] ? 'selected' : '' ?>><?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-search"></i></button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Assigned Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expected Return</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($assignments as $a): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= sanitize($a['asset_code']) ?></div>
                    <div class="text-sm text-gray-500"><?= sanitize($a['asset_name']) ?></div>
                </td>
                <td class="px-6 py-4">
                    <div class="font-medium"><?= sanitize($a['first_name'] . ' ' . $a['last_name']) ?></div>
                    <div class="text-sm text-gray-500"><?= sanitize($a['emp_code']) ?></div>
                </td>
                <td class="px-6 py-4 text-gray-600"><?= date('M d, Y', strtotime($a['assigned_date'])) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= $a['expected_return_date'] ? date('M d, Y', strtotime($a['expected_return_date'])) : '-' ?></td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs rounded <?= $a['status'] == 'active' ? 'bg-green-100 text-green-800' : ($a['status'] == 'overdue' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') ?>"><?= ucfirst($a['status']) ?></span>
                </td>
                <td class="px-6 py-4 text-right">
                    <a href="<?= url('modules/assets/view.php?id=' . $a['asset_id'] . '') ?>" class="text-blue-600 hover:text-blue-900 mr-2" title="View Asset"><i class="fas fa-eye"></i></a>
                    <?php if (hasPermission('assets.assign') && $a['status'] == 'active'): ?>
                    <a href="<?= url('modules/asset_returns/create.php?assignment_id=' . $a['id'] . '') ?>" class="text-red-600 hover:text-red-900" title="Process Return"><i class="fas fa-undo"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($assignments)): ?>
            <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No assignments found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../includes/footer.php'; ?>

