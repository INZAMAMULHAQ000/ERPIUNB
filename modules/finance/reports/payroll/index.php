<?php
/**
 * F11 — Payroll Reports
 * Payroll Cost, Salary Expense, Employee Payable, Department-wise
 * Integrates with F10 - Payroll Finance Integration
 */

$pageTitle = 'Payroll Reports';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once '../FinancialReportService.php';

if (!hasPermission('report.payroll.view')) { die('Access Denied'); }

$db = getDB();

// Get parameters
$reportType = $_GET['report_type'] ?? 'summary';
$departmentId = (int)($_GET['department_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');

// Get lookup data
$departments = $db->query("SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name")->fetchAll();

// Generate report based on type
$report = null;
$dateColumn = 'pay_date';
$deductionColumn = 'employee_deductions';

if ($reportType === 'summary') {
    $report = FinancialReportService::generatePayrollSummary([
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'department_id' => $departmentId,
    ]);
} elseif ($reportType === 'detail') {
    $where = "WHERE pr.$dateColumn BETWEEN ? AND ?";
    $p = [$dateFrom, $dateTo];
    
    $stmt = $db->prepare("
        SELECT pr.*,
               pr.run_code AS employee_code,
               pr.period_name AS employee_name,
               'All Departments' AS department_name,
               pr.$dateColumn AS run_date,
               pr.$deductionColumn AS total_deductions
        FROM payroll_runs pr
        $where
        ORDER BY pr.$dateColumn DESC, pr.run_code
    ");
    $stmt->execute($p);
    $report = ['success' => true, 'payroll_runs' => $stmt->fetchAll()];
} elseif ($reportType === 'monthly') {
    // Monthly payroll trend
    $stmt = $db->prepare("
        SELECT DATE_FORMAT(pr.$dateColumn, '%Y-%m') as month,
               DATE_FORMAT(pr.$dateColumn, '%b %Y') as month_name,
               COUNT(*) as employee_count,
               SUM(pr.gross_salary) as gross,
               SUM(pr.$deductionColumn) as deductions,
               SUM(pr.net_salary) as net
        FROM payroll_runs pr
        WHERE pr.$dateColumn BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(pr.$dateColumn, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([date('Y-01-01', strtotime($dateFrom)), $dateTo]);
    $report = ['success' => true, 'months' => $stmt->fetchAll()];
} elseif ($reportType === 'cost') {
    // Total payroll cost breakdown
    $where = "WHERE pr.$dateColumn BETWEEN ? AND ?";
    $p = [$dateFrom, $dateTo];
    
    $stmt = $db->prepare("
        SELECT 
            SUM(pr.gross_salary) as total_gross,
            SUM(pr.basic_salary) as total_basic,
            SUM(pr.allowances) as total_allowances,
            SUM(pr.overtime) as total_overtime,
            SUM(pr.$deductionColumn) as total_deductions,
            SUM(pr.net_salary) as total_net,
            SUM(pr.employer_contributions) as employer_contributions
        FROM payroll_runs pr
        $where
    ");
    $stmt->execute($p);
    $report = ['success' => true, 'cost' => $stmt->fetch(), 'date_from' => $dateFrom, 'date_to' => $dateTo];
} elseif ($reportType === 'liability') {
    // Payroll payable
    $stmt = $db->prepare("
        SELECT pr.$dateColumn AS run_date,
               pr.gross_salary,
               pr.$deductionColumn AS total_deductions,
               pr.net_salary,
               pr.run_code AS employee_code,
               pr.period_name AS employee_name
        FROM payroll_runs pr
        WHERE pr.$dateColumn BETWEEN ? AND ?
        AND pr.status IN ('Approved','Posted','approved','posted')
        ORDER BY pr.$dateColumn DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $report = ['success' => true, 'payroll_runs' => $stmt->fetchAll()];
}

// Calculate totals
$totalPayrollStmt = $db->prepare("
    SELECT COALESCE(SUM(gross_salary), 0) as gross, 
           COALESCE(SUM($deductionColumn), 0) as deductions,
           COALESCE(SUM(net_salary), 0) as net
    FROM payroll_runs 
    WHERE $dateColumn BETWEEN ? AND ?
");
$totalPayrollStmt->execute([$dateFrom, $dateTo]);
$totalPayroll = $totalPayrollStmt->fetch();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Payroll Reports</h1>
        <p class="text-gray-600">Period: <?= date('M d, Y', strtotime($dateFrom)) ?> to <?= date('M d, Y', strtotime($dateTo)) ?></p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"><i class="fas fa-print mr-2"></i>Print</button>
        <a href="../dashboard/index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-arrow-left mr-2"></i>Dashboard</a>
    </div>
</div>

<!-- Report Type Tabs -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="flex border-b overflow-x-auto">
        <a href="?report_type=summary&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="px-6 py-3 font-medium <?= $reportType === 'summary' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Summary
        </a>
        <a href="?report_type=cost&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="px-6 py-3 font-medium <?= $reportType === 'cost' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Cost Breakdown
        </a>
        <a href="?report_type=detail&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="px-6 py-3 font-medium <?= $reportType === 'detail' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Employee Detail
        </a>
        <a href="?report_type=monthly&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="px-6 py-3 font-medium <?= $reportType === 'monthly' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Monthly Trend
        </a>
        <a href="?report_type=liability&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="px-6 py-3 font-medium <?= $reportType === 'liability' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Payroll Payable
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow mb-6 p-4">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <input type="hidden" name="report_type" value="<?= $reportType ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input type="date" name="date_from" value="<?= sanitize($dateFrom) ?>" class="px-3 py-2 border rounded">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input type="date" name="date_to" value="<?= sanitize($dateTo) ?>" class="px-3 py-2 border rounded">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
            <select name="department_id" class="px-3 py-2 border rounded">
                <option value="">All Departments</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $departmentId == $d['id'] ? 'selected' : '' ?>><?= sanitize($d['department_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-search mr-2"></i>Generate</button>
    </form>
</div>

<!-- Total Payroll Summary Card -->
<div class="bg-gradient-to-r from-indigo-600 to-indigo-500 rounded-lg shadow mb-6 p-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <p class="text-indigo-100 text-sm">Gross Salary</p>
            <p class="text-white text-2xl font-bold"><?= formatMoney($totalPayroll['gross']) ?></p>
        </div>
        <div>
            <p class="text-indigo-100 text-sm">Total Deductions</p>
            <p class="text-white text-2xl font-bold"><?= formatMoney($totalPayroll['deductions']) ?></p>
        </div>
        <div>
            <p class="text-indigo-100 text-sm">Net Payroll</p>
            <p class="text-white text-2xl font-bold"><?= formatMoney($totalPayroll['net']) ?></p>
        </div>
    </div>
</div>

<?php if ($reportType === 'summary' && $report && $report['success']): ?>

<!-- Payroll Summary by Department -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-800">Payroll Summary by Department</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Department</th>
                    <th class="px-4 py-3 text-right">Pay Runs</th>
                    <th class="px-4 py-3 text-right">Gross Salary</th>
                    <th class="px-4 py-3 text-right">Deductions</th>
                    <th class="px-4 py-3 text-right">Net Salary</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['departments'])): ?>
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">No payroll data found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['departments'] as $dept): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($dept['department_name'] ?? 'Unassigned') ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= $dept['runs'] ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold"><?= formatMoney($dept['gross']) ?></td>
                    <td class="px-4 py-3 text-right font-mono text-red-600"><?= formatMoney($dept['deductions']) ?></td>
                    <td class="px-4 py-3 text-right font-mono text-green-600 font-bold"><?= formatMoney($dept['net']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tfoot class="bg-gray-100 font-bold">
                    <tr>
                        <td class="px-4 py-3">TOTAL</td>
                        <td class="px-4 py-3 text-right">-</td>
                        <td class="px-4 py-3 text-right font-mono"><?= formatMoney($report['totals']['gross']) ?></td>
                        <td class="px-4 py-3 text-right font-mono"><?= formatMoney($report['totals']['deductions']) ?></td>
                        <td class="px-4 py-3 text-right font-mono"><?= formatMoney($report['totals']['net']) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'cost' && $report && $report['success']): ?>

<!-- Payroll Cost Breakdown -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="px-6 py-4 border-b bg-indigo-50">
        <h3 class="font-semibold text-indigo-800">Payroll Cost Breakdown</h3>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h4 class="font-medium text-gray-700 mb-4">Employee Cost</h4>
                <table class="w-full text-sm">
                    <tr class="border-b">
                        <td class="py-2 text-gray-600">Basic Salary</td>
                        <td class="py-2 text-right font-mono"><?= formatMoney($report['cost']['total_basic'] ?? 0) ?></td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-2 text-gray-600">Allowances</td>
                        <td class="py-2 text-right font-mono"><?= formatMoney($report['cost']['total_allowances'] ?? 0) ?></td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-2 text-gray-600">Overtime Pay</td>
                        <td class="py-2 text-right font-mono"><?= formatMoney($report['cost']['total_overtime'] ?? 0) ?></td>
                    </tr>
                    <tr class="border-b bg-gray-50 font-semibold">
                        <td class="py-2">Gross Salary</td>
                        <td class="py-2 text-right font-mono"><?= formatMoney($report['cost']['total_gross'] ?? 0) ?></td>
                    </tr>
                    <tr>
                        <td class="py-2 text-gray-600">Less: Deductions</td>
                        <td class="py-2 text-right font-mono text-red-600">- <?= formatMoney($report['cost']['total_deductions'] ?? 0) ?></td>
                    </tr>
                    <tr class="bg-indigo-50 font-bold">
                        <td class="py-2">Net Salary (Employee Take-home)</td>
                        <td class="py-2 text-right font-mono text-indigo-700"><?= formatMoney($report['cost']['total_net'] ?? 0) ?></td>
                    </tr>
                </table>
            </div>
            <div>
                <h4 class="font-medium text-gray-700 mb-4">Employer Cost</h4>
                <table class="w-full text-sm">
                    <tr class="border-b">
                        <td class="py-2 text-gray-600">Employer Contributions (PF, etc.)</td>
                        <td class="py-2 text-right font-mono"><?= formatMoney($report['cost']['employer_contributions'] ?? 0) ?></td>
                    </tr>
                </table>
                <div class="mt-4 p-4 bg-indigo-50 rounded-lg">
                    <p class="text-sm text-indigo-600 mb-2">Total Payroll Cost to Employer</p>
                    <p class="text-3xl font-bold text-indigo-700">
                        <?= formatMoney(($report['cost']['total_gross'] ?? 0) + ($report['cost']['employer_contributions'] ?? 0)) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($reportType === 'detail' && $report && $report['success']): ?>

<!-- Employee Payroll Detail -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-800">Employee Payroll Detail</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-left">Department</th>
                    <th class="px-4 py-3 text-left">Run Date</th>
                    <th class="px-4 py-3 text-right">Gross</th>
                    <th class="px-4 py-3 text-right">Deductions</th>
                    <th class="px-4 py-3 text-right">Net</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['payroll_runs'])): ?>
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No payroll data found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['payroll_runs'] as $pr): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center">
                            <span class="font-mono text-gray-500 text-xs mr-2"><?= sanitize($pr['employee_code']) ?></span>
                            <span class="font-medium text-gray-800"><?= sanitize($pr['employee_name']) ?></span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-gray-600"><?= sanitize($pr['department_name'] ?? '-') ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= date('M d, Y', strtotime($pr['run_date'])) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($pr['gross_salary']) ?></td>
                    <td class="px-4 py-3 text-right font-mono text-red-600"><?= formatMoney($pr['total_deductions']) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold text-green-600"><?= formatMoney($pr['net_salary']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'monthly' && $report && $report['success']): ?>

<!-- Monthly Trend -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-800">Monthly Payroll Trend</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Month</th>
                    <th class="px-4 py-3 text-right">Employees</th>
                    <th class="px-4 py-3 text-right">Gross</th>
                    <th class="px-4 py-3 text-right">Deductions</th>
                    <th class="px-4 py-3 text-right">Net</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['months'])): ?>
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">No payroll data found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['months'] as $month): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($month['month_name']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= $month['employee_count'] ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($month['gross']) ?></td>
                    <td class="px-4 py-3 text-right font-mono text-red-600"><?= formatMoney($month['deductions']) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold text-green-600"><?= formatMoney($month['net']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'liability' && $report && $report['success']): ?>

<!-- Payroll Payable -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b bg-amber-50">
        <h3 class="font-semibold text-amber-800">Payroll Payable (Outstanding)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Run Date</th>
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-right">Gross</th>
                    <th class="px-4 py-3 text-right">Deductions</th>
                    <th class="px-4 py-3 text-right">Net Payable</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['payroll_runs'])): ?>
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">No outstanding payroll</td>
                </tr>
                <?php else: 
                    $totalPayable = 0;
                    foreach ($report['payroll_runs'] as $pr) $totalPayable += (float)$pr['net_salary'];
                ?>
                <?php foreach ($report['payroll_runs'] as $pr): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-600"><?= date('M d, Y', strtotime($pr['run_date'])) ?></td>
                    <td class="px-4 py-3">
                        <span class="font-mono text-gray-500 text-xs mr-2"><?= sanitize($pr['employee_code']) ?></span>
                        <span class="font-medium text-gray-800"><?= sanitize($pr['employee_name']) ?></span>
                    </td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($pr['gross_salary']) ?></td>
                    <td class="px-4 py-3 text-right font-mono text-red-600"><?= formatMoney($pr['total_deductions']) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold text-amber-600"><?= formatMoney($pr['net_salary']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tfoot class="bg-amber-50 font-bold">
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-right">TOTAL PAYABLE</td>
                        <td class="px-4 py-3 text-right font-mono text-amber-700"><?= formatMoney($totalPayable) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<style media="print">
    .erp-layout, nav, .sidebar, .navbar, form, button { display: none !important; }
    body { background: white; }
    @page { margin: 10mm; }
</style>

<?php require_once '../../../../includes/footer.php'; ?>
