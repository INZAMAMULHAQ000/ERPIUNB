<?php
$pageTitle = 'Leave Reports';
require_once '../../includes/header.php';

if (!hasPermission('leave_management.report') && !hasPermission('leaves.view')) {
    die('Access Denied');
}

$db = getDB();

// Filters
$reportType = $_GET['report_type'] ?? 'employee';
$employeeId = $_GET['employee_id'] ?? '';
$departmentId = $_GET['department_id'] ?? '';
$leaveTypeId = $_GET['leave_type_id'] ?? '';
$status = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-01-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');
$year = $_GET['year'] ?? date('Y');

$employees = $db->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();
$departments = $db->query("SELECT * FROM departments WHERE status = 'active' ORDER BY department_name")->fetchAll();
$leaveTypes = $db->query("SELECT * FROM leave_types WHERE status = 'active' ORDER BY leave_name")->fetchAll();
?>
<div class="mb-6">
    <a href="dashboard.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Dashboard
    </a>
    <h1 class="text-2xl font-bold text-gray-800 mt-2">Leave Reports</h1>
    <p class="text-gray-600">View and export leave data</p>
</div>

<!-- Report Type Tabs -->
<div class="mb-6 border-b">
    <nav class="flex gap-6">
        <?php
        $tabs = [
            'employee' => 'By Employee',
            'department' => 'By Department',
            'type' => 'By Leave Type',
            'detail' => 'Detailed Log',
        ];
        foreach ($tabs as $key => $label): ?>
        <a href="?report_type=<?= $key ?>" class="pb-3 px-1 text-sm font-medium border-b-2 transition
            <?= $reportType === $key ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </nav>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <input type="hidden" name="report_type" value="<?= $reportType ?>">

        <?php if ($reportType === 'employee'): ?>
        <div class="w-56">
            <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
            <select name="employee_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Employees</option>
                <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>" <?= $employeeId == $emp['id'] ? 'selected' : '' ?>>
                    <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php if ($reportType === 'department'): ?>
        <div class="w-56">
            <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
            <select name="department_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                <option value="<?= $dept['id'] ?>" <?= $departmentId == $dept['id'] ? 'selected' : '' ?>>
                    <?= sanitize($dept['department_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php if ($reportType === 'type'): ?>
        <div class="w-56">
            <label class="block text-sm font-medium text-gray-700 mb-1">Leave Type</label>
            <select name="leave_type_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Types</option>
                <?php foreach ($leaveTypes as $lt): ?>
                <option value="<?= $lt['id'] ?>" <?= $leaveTypeId == $lt['id'] ? 'selected' : '' ?>>
                    <?= sanitize($lt['leave_name']) ?> (<?= $lt['leave_code'] ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php if ($reportType === 'detail'): ?>
        <div class="w-48">
            <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
            <select name="employee_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>" <?= $employeeId == $emp['id'] ? 'selected' : '' ?>>
                    <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <?php endif; ?>

        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">From</label>
            <input type="date" name="date_from" value="<?= $dateFrom ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">To</label>
            <input type="date" name="date_to" value="<?= $dateTo ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
            <i class="fas fa-search"></i> Generate
        </button>
        <a href="report_pdf.php?<?= http_build_query($_GET) ?>"
            class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition inline-flex items-center">
            <i class="fas fa-file-pdf mr-2"></i> Download PDF
        </a>
        <button type="button" onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-print"></i> Print
        </button>
    </form>
</div>

<!-- Report Content -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <?php if ($reportType === 'employee'): ?>
    <?php
        $sql = "SELECT e.id as emp_id, e.first_name, e.last_name, e.employee_code,
                       d.department_name,
                       COUNT(lr.id) as total_leaves,
                       COALESCE(SUM(lr.total_days), 0) as total_days_used,
                       COALESCE(SUM(CASE WHEN lr.is_lop = 1 THEN lr.total_days ELSE 0 END), 0) as lop_days
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN leave_requests lr ON e.id = lr.employee_id
                    AND lr.status = 'approved'
                    AND lr.from_date >= " . $db->quote($dateFrom) . "
                    AND lr.from_date <= " . $db->quote($dateTo) . "
                WHERE e.status = 'active'";

        if ($employeeId) {
            $sql .= " AND e.id = " . (int)$employeeId;
        }
        $sql .= " GROUP BY e.id ORDER BY e.first_name";

        $reportData = $db->query($sql)->fetchAll();
    ?>
    <div class="p-6 border-b bg-gray-50">
        <h3 class="text-lg font-semibold text-gray-800">Leave Summary by Employee</h3>
        <p class="text-sm text-gray-500"><?= date('M d, Y', strtotime($dateFrom)) ?> — <?= date('M d, Y', strtotime($dateTo)) ?></p>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Total Leaves</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Days Used</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">LOP Days</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <?php foreach ($reportData as $row): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <div class="font-medium text-sm"><?= sanitize($row['first_name'] . ' ' . $row['last_name']) ?></div>
                    <div class="text-xs text-gray-500"><?= sanitize($row['employee_code']) ?></div>
                </td>
                <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($row['department_name'] ?? '-') ?></td>
                <td class="px-4 py-3 text-center font-medium"><?= $row['total_leaves'] ?></td>
                <td class="px-4 py-3 text-center font-medium"><?= $row['total_days_used'] ?></td>
                <td class="px-4 py-3 text-center <?= $row['lop_days'] > 0 ? 'text-red-600 font-medium' : 'text-gray-600' ?>">
                    <?= $row['lop_days'] ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($reportData)): ?>
            <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No data found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php elseif ($reportType === 'department'): ?>
    <?php
        $deptSql = "SELECT d.id, d.department_name,
                           COUNT(DISTINCT e.id) as emp_count,
                           COUNT(lr.id) as total_leaves,
                           COALESCE(SUM(lr.total_days), 0) as total_days_used
                    FROM departments d
                    LEFT JOIN employees e ON e.department_id = d.id AND e.status = 'active'
                    LEFT JOIN leave_requests lr ON e.id = lr.employee_id
                        AND lr.status = 'approved'
                        AND lr.from_date >= " . $db->quote($dateFrom) . "
                        AND lr.from_date <= " . $db->quote($dateTo) . "
                    WHERE d.status = 'active'";
        if ($departmentId) {
            $deptSql .= " AND d.id = " . (int)$departmentId;
        }
        $deptSql .= " GROUP BY d.id ORDER BY d.department_name";
        $deptData = $db->query($deptSql)->fetchAll();
    ?>
    <div class="p-6 border-b bg-gray-50">
        <h3 class="text-lg font-semibold text-gray-800">Leave Summary by Department</h3>
        <p class="text-sm text-gray-500"><?= date('M d, Y', strtotime($dateFrom)) ?> — <?= date('M d, Y', strtotime($dateTo)) ?></p>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Employees</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Total Leaves</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Days Used</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Avg/Employee</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <?php foreach ($deptData as $row): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-sm"><?= sanitize($row['department_name']) ?></td>
                <td class="px-4 py-3 text-center"><?= $row['emp_count'] ?></td>
                <td class="px-4 py-3 text-center"><?= $row['total_leaves'] ?></td>
                <td class="px-4 py-3 text-center"><?= $row['total_days_used'] ?></td>
                <td class="px-4 py-3 text-center"><?= $row['emp_count'] > 0 ? round($row['total_days_used'] / $row['emp_count'], 1) : 0 ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($deptData)): ?>
            <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No data found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php elseif ($reportType === 'type'): ?>
    <?php
        $typeSql = "SELECT lt.id, lt.leave_name, lt.leave_code, lt.total_days as default_days,
                           COUNT(lr.id) as times_used,
                           COALESCE(SUM(lr.total_days), 0) as total_days_used,
                           COALESCE(SUM(CASE WHEN lr.is_lop = 1 THEN lr.total_days ELSE 0 END), 0) as lop_days
                    FROM leave_types lt
                    LEFT JOIN leave_requests lr ON lt.id = lr.leave_type_id
                        AND lr.status = 'approved'
                        AND lr.from_date >= " . $db->quote($dateFrom) . "
                        AND lr.from_date <= " . $db->quote($dateTo) . "
                    WHERE lt.status = 'active'";
        if ($leaveTypeId) {
            $typeSql .= " AND lt.id = " . (int)$leaveTypeId;
        }
        $typeSql .= " GROUP BY lt.id ORDER BY lt.leave_name";
        $typeData = $db->query($typeSql)->fetchAll();
    ?>
    <div class="p-6 border-b bg-gray-50">
        <h3 class="text-lg font-semibold text-gray-800">Leave Summary by Type</h3>
        <p class="text-sm text-gray-500"><?= date('M d, Y', strtotime($dateFrom)) ?> — <?= date('M d, Y', strtotime($dateTo)) ?></p>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Leave Type</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Code</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Default Days</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Times Used</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Days Used</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">LOP Days</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <?php foreach ($typeData as $row): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-sm"><?= sanitize($row['leave_name']) ?></td>
                <td class="px-4 py-3 text-center text-sm text-gray-600"><?= sanitize($row['leave_code']) ?></td>
                <td class="px-4 py-3 text-center"><?= $row['default_days'] ?></td>
                <td class="px-4 py-3 text-center"><?= $row['times_used'] ?></td>
                <td class="px-4 py-3 text-center font-medium"><?= $row['total_days_used'] ?></td>
                <td class="px-4 py-3 text-center <?= $row['lop_days'] > 0 ? 'text-red-600' : 'text-gray-600' ?>"><?= $row['lop_days'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php elseif ($reportType === 'detail'): ?>
    <?php
        $detailWhere = "WHERE 1=1";
        $detailParams = [];
        if ($employeeId) { $detailWhere .= " AND lr.employee_id = ?"; $detailParams[] = $employeeId; }
        if ($status) { $detailWhere .= " AND lr.status = ?"; $detailParams[] = $status; }
        $detailWhere .= " AND lr.from_date >= ? AND lr.from_date <= ?";
        $detailParams[] = $dateFrom;
        $detailParams[] = $dateTo;

        $detailStmt = $db->prepare("
            SELECT lr.*, e.first_name, e.last_name, e.employee_code, lt.leave_name, lt.leave_code,
                   u.name as approver_name
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            LEFT JOIN users u ON lr.approved_by = u.id
            $detailWhere
            ORDER BY lr.created_at DESC
        ");
        $detailStmt->execute($detailParams);
        $detailData = $detailStmt->fetchAll();
    ?>
    <div class="p-6 border-b bg-gray-50">
        <h3 class="text-lg font-semibold text-gray-800">Detailed Leave Log</h3>
        <p class="text-sm text-gray-500"><?= date('M d, Y', strtotime($dateFrom)) ?> — <?= date('M d, Y', strtotime($dateTo)) ?></p>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dates</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Days</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">LOP</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Approved By</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <?php foreach ($detailData as $row): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <div class="text-sm font-medium"><?= sanitize($row['first_name'] . ' ' . $row['last_name']) ?></div>
                    <div class="text-xs text-gray-500"><?= sanitize($row['employee_code']) ?></div>
                </td>
                <td class="px-4 py-3 text-sm">
                    <span class="font-medium"><?= sanitize($row['leave_name']) ?></span>
                    <span class="text-gray-500 text-xs">(<?= $row['leave_code'] ?>)</span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-600">
                    <?= date('M d', strtotime($row['from_date'])) ?> - <?= date('M d, Y', strtotime($row['to_date'])) ?>
                </td>
                <td class="px-4 py-3 text-center font-medium"><?= $row['total_days'] ?></td>
                <td class="px-4 py-3 text-xs text-gray-600 max-w-[200px] truncate"><?= sanitize($row['reason']) ?></td>
                <td class="px-4 py-3 text-center">
                    <?php if ($row['is_lop']): ?>
                    <span class="text-xs text-red-600 font-medium">LOP</span>
                    <?php else: ?>
                    <span class="text-xs text-gray-400">-</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-center">
                    <?php
                    $sc = ['pending' => 'bg-yellow-100 text-yellow-800', 'approved' => 'bg-green-100 text-green-800', 'rejected' => 'bg-red-100 text-red-800', 'cancelled' => 'bg-gray-100 text-gray-800'];
                    ?>
                    <span class="px-2 py-0.5 text-xs rounded <?= $sc[$row['status']] ?? 'bg-gray-100' ?>"><?= ucfirst($row['status']) ?></span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-600"><?= sanitize($row['approver_name'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($detailData)): ?>
            <tr><td colspan="8" class="px-6 py-8 text-center text-gray-500">No leave records found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="mt-6 text-center text-xs text-gray-400">
    Report generated: <?= date('M d, Y h:i A') ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
