<?php
$pageTitle = 'Leave Management Dashboard';
$dashboardKey = 'leave';
require_once '../../includes/header.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('leave_management.view_dashboard') && !hasPermission('leaves.view')) {
    die('Access Denied');
}

$db = getDB();
$employeeId = $_GET['employee_id'] ?? '';
$page = getPageNumber();
$perPage = 15;

// Summary stats
$totalEmployees = $db->query("SELECT COUNT(*) as c FROM employees WHERE status = 'active'")->fetch()['c'];
$pendingApprovals = $db->query("SELECT COUNT(*) as c FROM leave_requests WHERE status = 'pending'")->fetch()['c'];
$approvedThisMonth = $db->query("SELECT COUNT(*) as c FROM leave_requests WHERE status = 'approved' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetch()['c'];

// Leaves in progress today
$todayLeaves = $db->query("
    SELECT COUNT(*) as c FROM leave_requests
    WHERE status = 'approved'
    AND CURDATE() BETWEEN from_date AND to_date
")->fetch()['c'];

// Employees with balance overview
$where = "WHERE e.status = 'active'";
$params = [];
if ($employeeId) {
    $where .= " AND e.id = ?";
    $params[] = $employeeId;
}

$countStmt = $db->prepare("SELECT COUNT(DISTINCT e.id) as cnt FROM employees e $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];
$pagination = paginate($total, $perPage, $page);

$offset = $pagination['offset'];
$empQuery = $db->prepare("
    SELECT e.id, e.first_name, e.last_name, e.employee_code, d.department_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    $where
    ORDER BY e.first_name ASC
    LIMIT ? OFFSET ?
");
$empQuery->execute(array_merge($params, [$perPage, $offset]));
$employees = $empQuery->fetchAll();

// Build balance data for each employee
$employeeBalances = [];
foreach ($employees as $emp) {
    $balStmt = $db->prepare("
        SELECT lb.leave_type_id, lt.leave_name, lt.leave_code, lt.total_days as default_days,
               COALESCE(lb.total_days, lt.total_days) as allocated,
               COALESCE(lb.used_days, 0) as used
        FROM leave_types lt
        LEFT JOIN leave_balances lb ON lb.leave_type_id = lt.id
            AND lb.employee_id = ? AND lb.year = YEAR(CURDATE())
        WHERE lt.status = 'active'
        ORDER BY lt.leave_name
    ");
    $balStmt->execute([$emp['id']]);
    $balances = $balStmt->fetchAll();

    $totalAllocated = 0;
    $totalUsed = 0;
    $totalRemaining = 0;
    foreach ($balances as $b) {
        $totalAllocated += $b['allocated'];
        $totalUsed += $b['used'];
        $totalRemaining += ($b['allocated'] - $b['used']);
    }

    $employeeBalances[$emp['id']] = [
        'info' => $emp,
        'balances' => $balances,
        'total_allocated' => $totalAllocated,
        'total_used' => $totalUsed,
        'total_remaining' => $totalRemaining,
    ];
}

$employeesList = $db->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();

// Aggregate balances by leave type (across the page's employees) for the bar chart
$balChart = [];
foreach ($employeeBalances as $eb) {
    foreach ($eb['balances'] as $b) {
        $key = $b['leave_name'];
        if (!isset($balChart[$key])) { $balChart[$key] = ['allocated' => 0, 'used' => 0]; }
        $balChart[$key]['allocated'] += (float)$b['allocated'];
        $balChart[$key]['used'] += (float)$b['used'];
    }
}
$balChartLabels  = array_keys($balChart);
$balAllocated   = array_column($balChart, 'allocated');
$balUsed        = array_column($balChart, 'used');
?>
<div id="dashboard-old-content">
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Leave Management</h1>
        <p class="text-gray-600">Leave balances, approvals &amp; reports</p>
    </div>
    <div class="flex gap-2">
        <?php if (hasPermission('leave_management.report') || hasPermission('leaves.view')): ?>
        <a href="report.php" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
            <i class="fas fa-chart-bar mr-2"></i>Reports
        </a>
        <?php endif; ?>
        <?php if (hasPermission('leave_management.allocate')): ?>
        <a href="allocate.php" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 transition">
            <i class="fas fa-balance-scale mr-2"></i>Allocate Balances
        </a>
        <?php endif; ?>
        <?php if (hasPermission('leaves.create')): ?>
        <a href="<?= url('modules/leave_requests/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i>New Leave Request
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Active Employees</div>
        <div class="text-2xl font-bold text-gray-800"><?= $totalEmployees ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">On Leave Today</div>
        <div class="text-2xl font-bold text-orange-600"><?= $todayLeaves ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Pending Approvals</div>
        <div class="text-2xl font-bold text-yellow-600"><?= $pendingApprovals ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Approved This Month</div>
        <div class="text-2xl font-bold text-green-600"><?= $approvedThisMonth ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">LOP Leaves (All-time)</div>
        <div class="text-2xl font-bold text-red-600"><?= $db->query("SELECT COUNT(*) as c FROM leave_requests WHERE is_lop = 1")->fetch()['c'] ?></div>
    </div>
</div>

<!-- Pending Approvals Alert -->
<?php if ($pendingApprovals > 0 && hasPermission('leaves.approve')): ?>
<div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded-r">
    <div class="flex items-center">
        <div class="flex-shrink-0"><i class="fas fa-exclamation-triangle text-yellow-500"></i></div>
        <div class="ml-3">
            <p class="text-sm text-yellow-700">
                <strong><?= $pendingApprovals ?></strong> leave request(s) pending approval.
                <a href="<?= url('modules/leave_requests/index.php?status=pending') ?>" class="font-medium text-yellow-800 underline hover:text-yellow-900 ml-1">Review now</a>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filter -->
<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="w-64">
            <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
            <select name="employee_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Employees</option>
                <?php foreach ($employeesList as $emp): ?>
                <option value="<?= $emp['id'] ?>" <?= $employeeId == $emp['id'] ? 'selected' : '' ?>>
                    <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-filter"></i> Filter
        </button>
        <a href="dashboard.php" class="text-gray-600 px-4 py-2 border border-gray-200 hover:text-gray-900">Clear</a>
    </form>
</div>

<!-- Leave Balances by Type -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Leave Balances by Type (visible page)</h3>
    <div id="balChart"></div>
</div>

<!-- Employee Leave Balances -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b bg-gray-50">
        <h3 class="text-lg font-semibold text-gray-800">Employee Leave Balances</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dept</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Used</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Remaining</th>
                    <?php
                    $allTypes = $db->query("SELECT id, leave_name, leave_code FROM leave_types WHERE status = 'active' ORDER BY leave_name")->fetchAll();
                    foreach ($allTypes as $lt): ?>
                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase"><?= sanitize($lt['leave_code']) ?></th>
                    <?php endforeach; ?>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($employeeBalances as $empId => $eb): ?>
                <?php
                    $remainingPct = $eb['total_allocated'] > 0 ? round(($eb['total_remaining'] / $eb['total_allocated']) * 100) : 0;
                    $balColor = $remainingPct > 50 ? 'text-green-600' : ($remainingPct > 25 ? 'text-yellow-600' : 'text-red-600');
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900 text-sm"><?= sanitize($eb['info']['first_name'] . ' ' . $eb['info']['last_name']) ?></div>
                        <div class="text-xs text-gray-500"><?= sanitize($eb['info']['employee_code']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600"><?= sanitize($eb['info']['department_name'] ?? '-') ?></td>
                    <td class="px-4 py-3 text-center font-medium"><?= $eb['total_allocated'] ?></td>
                    <td class="px-4 py-3 text-center font-medium"><?= $eb['total_used'] ?></td>
                    <td class="px-4 py-3 text-center font-medium <?= $balColor ?>"><?= $eb['total_remaining'] ?></td>
                    <?php foreach ($allTypes as $lt): ?>
                    <?php
                        $match = null;
                        foreach ($eb['balances'] as $b) {
                            if ($b['leave_type_id'] == $lt['id']) { $match = $b; break; }
                        }
                        $rem = $match ? ($match['allocated'] - $match['used']) : '-';
                    ?>
                    <td class="px-3 py-3 text-center text-xs <?= is_numeric($rem) && $rem <= 0 ? 'text-red-500 font-medium' : 'text-gray-600' ?>">
                        <?= is_numeric($rem) ? $rem : '-' ?>
                    </td>
                    <?php endforeach; ?>
                    <td class="px-4 py-3 text-right">
                        <a href="<?= url('modules/leave_requests/index.php?employee_id=' . $empId) ?>" class="text-blue-600 hover:text-blue-900 text-sm mr-2" title="View Leaves">
                            <i class="fas fa-list"></i>
                        </a>
                        <?php if (hasPermission('leave_management.allocate')): ?>
                        <a href="allocate.php?employee_id=<?= $empId ?>" class="text-purple-600 hover:text-purple-900 text-sm" title="Allocate Balance">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($employeeBalances)): ?>
                <tr>
                    <td colspan="<?= 7 + count($allTypes) ?>" class="px-6 py-8 text-center text-gray-500">No employees found</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= paginationLinks($pagination, '?') ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const render = window.renderCarbonChart;
    const el = document.getElementById('balChart');
    if (el && render) {
        render(el, 'grouped', {
            labels: <?= json_encode($balChartLabels) ?>,
            datasets: [
                { label: 'Allocated', data: <?= json_encode($balAllocated) ?>, backgroundColor: 'rgba(59,130,246,0.75)' },
                { label: 'Used', data: <?= json_encode($balUsed) ?>, backgroundColor: 'rgba(239,68,68,0.75)' }
            ]
        }, { horizontal: true, legend: { position: 'top' } });
    }
});
</script>

</div><!-- /dashboard-old-content -->
<?php
require_once '../../includes/dashboard_template.php';
require_once '../../includes/footer.php'; ?>
