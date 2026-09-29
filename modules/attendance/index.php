<?php
$pageTitle = 'Attendance';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('attendance.view')) {
    die('Access Denied');
}

$db = getDB();
$search = $_GET['search'] ?? '';
$employee_id = $_GET['employee_id'] ?? '';
$status = $_GET['status'] ?? '';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$page = getPageNumber();
$perPage = 20;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($employee_id) {
    $where .= " AND a.employee_id = ?";
    $params[] = $employee_id;
}

if ($status) {
    $where .= " AND a.status = ?";
    $params[] = $status;
}

$dateFrom = "$year-$month-01";
$dateTo = date('Y-m-t', strtotime($dateFrom));
$where .= " AND a.attendance_date BETWEEN ? AND ?";
$params[] = $dateFrom;
$params[] = $dateTo;

$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM attendance a JOIN employees e ON a.employee_id = e.id $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];

$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT a.*, e.first_name, e.last_name, e.employee_code 
    FROM attendance a 
    JOIN employees e ON a.employee_id = e.id
    $where ORDER BY a.attendance_date DESC, e.first_name ASC LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$records = $stmt->fetchAll();

$employees = $db->query("SELECT id, first_name, last_name, employee_code FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();

$statusColors = ['present' => 'bg-green-100 text-green-800', 'absent' => 'bg-red-100 text-red-800', 'half_day' => 'bg-yellow-100 text-yellow-800', 'leave' => 'bg-blue-100 text-blue-800', 'holiday' => 'bg-purple-100 text-purple-800', 'weekend' => 'bg-gray-100 text-gray-800'];
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Attendance</h1>
        <p class="text-gray-600">Manage employee attendance</p>
    </div>
    <?php if (hasPermission('attendance.create')): ?>
    <a href="<?= url('modules/attendance/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>Mark Attendance
    </a>
    <?php endif; ?>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
            <select name="employee_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Employees</option>
                <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>" <?= $employee_id == $emp['id'] ? 'selected' : '' ?>><?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= $emp['employee_code'] ?>)</option>
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
                <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="w-32">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="present" <?= $status === 'present' ? 'selected' : '' ?>>Present</option>
                <option value="absent" <?= $status === 'absent' ? 'selected' : '' ?>>Absent</option>
                <option value="half_day" <?= $status === 'half_day' ? 'selected' : '' ?>>Half Day</option>
                <option value="leave" <?= $status === 'leave' ? 'selected' : '' ?>>Leave</option>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-search"></i>
        </button>
    </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <?php
    $stats = [
        ['label' => 'Total Records', 'value' => $total, 'color' => 'blue'],
        ['label' => 'Present', 'value' => $db->prepare("SELECT COUNT(*) as c FROM attendance WHERE status = 'present' AND attendance_date BETWEEN ? AND ?")->execute([$dateFrom, $dateTo]) ? $db->query("SELECT COUNT(*) as c FROM attendance WHERE status = 'present' AND attendance_date BETWEEN '$dateFrom' AND '$dateTo'")->fetch()['c'] : 0, 'color' => 'green'],
        ['label' => 'Absent', 'value' => $db->query("SELECT COUNT(*) as c FROM attendance WHERE status = 'absent' AND attendance_date BETWEEN '$dateFrom' AND '$dateTo'")->fetch()['c'], 'color' => 'red'],
        ['label' => 'Half Day', 'value' => $db->query("SELECT COUNT(*) as c FROM attendance WHERE status = 'half_day' AND attendance_date BETWEEN '$dateFrom' AND '$dateTo'")->fetch()['c'], 'color' => 'yellow']
    ];
    foreach ($stats as $stat): ?>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500"><?= $stat['label'] ?></div>
        <div class="text-2xl font-bold text-gray-800"><?= $stat['value'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Check In</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Check Out</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Late (min)</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($records as $rec): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 text-gray-600"><?= date('M d, Y', strtotime($rec['attendance_date'])) ?></td>
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= sanitize($rec['first_name'] . ' ' . $rec['last_name']) ?></div>
                    <div class="text-sm text-gray-500"><?= sanitize($rec['employee_code']) ?></div>
                </td>
                <td class="px-6 py-4 text-gray-600"><?= $rec['check_in'] ? date('H:i', strtotime($rec['check_in'])) : '-' ?></td>
                <td class="px-6 py-4 text-gray-600"><?= $rec['check_out'] ? date('H:i', strtotime($rec['check_out'])) : '-' ?></td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs rounded <?= $statusColors[$rec['status']] ?? 'bg-gray-100' ?>">
                        <?= ucwords(str_replace('_', ' ', $rec['status'])) ?>
                    </span>
                </td>
                <td class="px-6 py-4 text-gray-600"><?= $rec['late_minutes'] > 0 ? $rec['late_minutes'] . ' min' : '-' ?></td>
                <td class="px-6 py-4 text-right">
                    <?php if (hasPermission('attendance.edit')): ?>
                    <a href="<?= url('modules/attendance/edit.php?id=' . $rec['id'] . '') ?>" class="text-yellow-600 hover:text-yellow-900 mr-2" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($records)): ?>
            <tr>
                <td colspan="7" class="px-6 py-8 text-center text-gray-500">No attendance records found</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginationLinks($pagination, '?') ?>

<?php require_once '../../includes/footer.php'; ?>

