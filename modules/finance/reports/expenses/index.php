<?php
/**
 * F11 — Expense Reports
 * Expense by Category, Department, Project, Employee
 */

$pageTitle = 'Expense Reports';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once '../FinancialReportService.php';

if (!hasPermission('report.expenses.view')) { die('Access Denied'); }

$db = getDB();

// Get parameters
$reportType = $_GET['report_type'] ?? 'category';
$departmentId = (int)($_GET['department_id'] ?? 0);
$projectId = (int)($_GET['project_id'] ?? 0);
$employeeId = (int)($_GET['employee_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');

// Get lookup data
$departments = $db->query("SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name")->fetchAll();
$projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();
$employees = $db->query("SELECT id, employee_code, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();
$expenseCategories = $db->query("SELECT id, name FROM expense_categories ORDER BY name")->fetchAll();

// Generate report based on type
$report = null;

if ($reportType === 'category') {
    $report = FinancialReportService::generateExpenseByCategory([
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
    ]);
} elseif ($reportType === 'department') {
    $where = "WHERE ce.expense_date BETWEEN ? AND ? AND ce.status NOT IN ('cancelled','draft')";
    $p = [$dateFrom, $dateTo];
    if ($departmentId) { $where .= " AND ce.department_id = ?"; $p[] = $departmentId; }
    
    $stmt = $db->prepare("
        SELECT d.department_name,
               COUNT(ce.id) as expense_count,
               SUM(ce.total_amount) as total
        FROM company_expenses ce
        LEFT JOIN departments d ON d.id = ce.department_id
        $where
        GROUP BY ce.department_id
        ORDER BY total DESC
    ");
    $stmt->execute($p);
    $report = ['success' => true, 'departments' => $stmt->fetchAll()];
} elseif ($reportType === 'project') {
    $where = "WHERE ce.expense_date BETWEEN ? AND ? AND ce.status NOT IN ('cancelled','draft')";
    $p = [$dateFrom, $dateTo];
    if ($projectId) { $where .= " AND ce.project_id = ?"; $p[] = $projectId; }
    
    $stmt = $db->prepare("
        SELECT p.project_name,
               COUNT(ce.id) as expense_count,
               SUM(ce.total_amount) as total
        FROM company_expenses ce
        LEFT JOIN projects p ON p.id = ce.project_id
        $where
        GROUP BY ce.project_id
        ORDER BY total DESC
    ");
    $stmt->execute($p);
    $report = ['success' => true, 'projects' => $stmt->fetchAll()];
} elseif ($reportType === 'employee') {
    $where = "WHERE eec.expense_date BETWEEN ? AND ? AND eec.status NOT IN ('cancelled','draft')";
    $p = [$dateFrom, $dateTo];
    if ($employeeId) { $where .= " AND eec.employee_id = ?"; $p[] = $employeeId; }
    
    $stmt = $db->prepare("
        SELECT e.employee_code, CONCAT(e.first_name, ' ', e.last_name) as employee_name,
               COUNT(eec.id) as claim_count,
               SUM(eec.total_amount) as total
        FROM employee_expense_claims eec
        JOIN employees e ON e.id = eec.employee_id
        $where
        GROUP BY eec.employee_id
        ORDER BY total DESC
    ");
    $stmt->execute($p);
    $report = ['success' => true, 'employees' => $stmt->fetchAll()];
} elseif ($reportType === 'monthly') {
    // Monthly trend
    $stmt = $db->prepare("
        SELECT DATE_FORMAT(ce.expense_date, '%Y-%m') as month,
               DATE_FORMAT(ce.expense_date, '%b %Y') as month_name,
               SUM(ce.total_amount) as total
        FROM company_expenses ce
        WHERE ce.expense_date BETWEEN ? AND ?
        AND ce.status NOT IN ('cancelled','draft')
        GROUP BY DATE_FORMAT(ce.expense_date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([date('Y-01-01', strtotime($dateFrom)), $dateTo]);
    $report = ['success' => true, 'months' => $stmt->fetchAll()];
} elseif ($reportType === 'top') {
    // Top expense categories
    $stmt = $db->prepare("
        SELECT ec.name as category, SUM(ce.total_amount) as total
        FROM company_expenses ce
        JOIN expense_categories ec ON ec.id = ce.category_id
        WHERE ce.expense_date BETWEEN ? AND ?
        AND ce.status NOT IN ('cancelled','draft')
        GROUP BY ce.category_id
        ORDER BY total DESC
        LIMIT 10
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $report = ['success' => true, 'top_categories' => $stmt->fetchAll()];
}

// Calculate grand total for company + employee expenses
$totalStmt = $db->prepare("
    SELECT 
        (SELECT COALESCE(SUM(total_amount), 0) FROM company_expenses 
         WHERE expense_date BETWEEN ? AND ? AND status NOT IN ('cancelled','draft')) as company_total,
        (SELECT COALESCE(SUM(total_amount), 0) FROM employee_expense_claims 
         WHERE expense_date BETWEEN ? AND ? AND status NOT IN ('cancelled','draft')) as employee_total
");
$totalStmt->execute([$dateFrom, $dateTo, $dateFrom, $dateTo]);
$totals = $totalStmt->fetch();
$grandTotal = (float)$totals['company_total'] + (float)$totals['employee_total'];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Expense Reports</h1>
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
        <a href="?report_type=category&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="px-6 py-3 font-medium <?= $reportType === 'category' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            By Category
        </a>
        <a href="?report_type=department&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="px-6 py-3 font-medium <?= $reportType === 'department' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            By Department
        </a>
        <a href="?report_type=project&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="px-6 py-3 font-medium <?= $reportType === 'project' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            By Project
        </a>
        <a href="?report_type=employee&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="px-6 py-3 font-medium <?= $reportType === 'employee' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            By Employee
        </a>
        <a href="?report_type=monthly&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="px-6 py-3 font-medium <?= $reportType === 'monthly' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Monthly Trend
        </a>
        <a href="?report_type=top&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="px-6 py-3 font-medium <?= $reportType === 'top' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Top Categories
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
        <?php if ($reportType === 'department'): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
            <select name="department_id" class="px-3 py-2 border rounded">
                <option value="">All Departments</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $departmentId == $d['id'] ? 'selected' : '' ?>><?= sanitize($d['department_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php elseif ($reportType === 'project'): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
            <select name="project_id" class="px-3 py-2 border rounded">
                <option value="">All Projects</option>
                <?php foreach ($projects as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $projectId == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['project_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php elseif ($reportType === 'employee'): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
            <select name="employee_id" class="px-3 py-2 border rounded">
                <option value="">All Employees</option>
                <?php foreach ($employees as $e): ?>
                <option value="<?= $e['id'] ?>" <?= $employeeId == $e['id'] ? 'selected' : '' ?>><?= sanitize($e['first_name'] . ' ' . $e['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-search mr-2"></i>Generate</button>
    </form>
</div>

<!-- Grand Total Card -->
<div class="bg-gradient-to-r from-blue-600 to-blue-500 rounded-lg shadow mb-6 p-4">
    <div class="flex justify-between items-center">
        <div>
            <p class="text-blue-100 text-sm">Grand Total Expenses</p>
            <p class="text-white text-3xl font-bold"><?= formatMoney($grandTotal) ?></p>
        </div>
        <div class="text-right text-blue-100 text-sm">
            <p>Company Expenses: <?= formatMoney($totals['company_total']) ?></p>
            <p>Employee Claims: <?= formatMoney($totals['employee_total']) ?></p>
        </div>
    </div>
</div>

<?php if ($reportType === 'category' && $report && $report['success']): ?>

<!-- Expense by Category -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-800">Expenses by Category</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-right">Company Expenses</th>
                    <th class="px-4 py-3 text-right">Employee Claims</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">% of Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['categories'])): ?>
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">No expense data found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['categories'] as $cat): 
                    $pct = $grandTotal > 0 ? round(($cat['total'] / $grandTotal) * 100, 1) : 0;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($cat['category']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($cat['company_expenses']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($cat['employee_claims']) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold"><?= formatMoney($cat['total']) ?></td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end">
                            <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $pct ?>%"></div>
                            </div>
                            <span class="text-xs"><?= $pct ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tfoot class="bg-gray-100 font-bold">
                    <tr>
                        <td class="px-4 py-3">TOTAL</td>
                        <td class="px-4 py-3 text-right font-mono"><?= formatMoney(array_sum(array_column($report['categories'], 'company_expenses'))) ?></td>
                        <td class="px-4 py-3 text-right font-mono"><?= formatMoney(array_sum(array_column($report['categories'], 'employee_claims'))) ?></td>
                        <td class="px-4 py-3 text-right font-mono"><?= formatMoney($report['total']) ?></td>
                        <td class="px-4 py-3 text-right">100%</td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'department' && $report && $report['success']): ?>

<!-- Expense by Department -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-800">Expenses by Department</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Department</th>
                    <th class="px-4 py-3 text-right">Expense Count</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">% of Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['departments'])): ?>
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">No expense data found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['departments'] as $dept): 
                    $pct = $grandTotal > 0 ? round(($dept['total'] / $grandTotal) * 100, 1) : 0;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($dept['department_name'] ?? 'Unassigned') ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= $dept['expense_count'] ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold"><?= formatMoney($dept['total']) ?></td>
                    <td class="px-4 py-3 text-right"><?= $pct ?>%</td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'project' && $report && $report['success']): ?>

<!-- Expense by Project -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-800">Expenses by Project</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Project</th>
                    <th class="px-4 py-3 text-right">Expense Count</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">% of Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['projects'])): ?>
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">No expense data found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['projects'] as $proj): 
                    $pct = $grandTotal > 0 ? round(($proj['total'] / $grandTotal) * 100, 1) : 0;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($proj['project_name'] ?? 'No Project') ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= $proj['expense_count'] ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold"><?= formatMoney($proj['total']) ?></td>
                    <td class="px-4 py-3 text-right"><?= $pct ?>%</td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'employee' && $report && $report['success']): ?>

<!-- Expense by Employee -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-800">Employee Expense Claims</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Employee Code</th>
                    <th class="px-4 py-3 text-left">Employee Name</th>
                    <th class="px-4 py-3 text-right">Claims</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">% of Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['employees'])): ?>
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">No expense data found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['employees'] as $emp): 
                    $pct = $grandTotal > 0 ? round(($emp['total'] / $grandTotal) * 100, 1) : 0;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-gray-600"><?= sanitize($emp['employee_code']) ?></td>
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($emp['employee_name']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= $emp['claim_count'] ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold"><?= formatMoney($emp['total']) ?></td>
                    <td class="px-4 py-3 text-right"><?= $pct ?>%</td>
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
        <h3 class="font-semibold text-gray-800">Monthly Expense Trend</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Month</th>
                    <th class="px-4 py-3 text-right">Total Expenses</th>
                    <th class="px-4 py-3 text-right">% of Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['months'])): ?>
                <tr>
                    <td colspan="3" class="px-4 py-8 text-center text-gray-500">No expense data found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['months'] as $month): 
                    $pct = $grandTotal > 0 ? round(($month['total'] / $grandTotal) * 100, 1) : 0;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($month['month_name']) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold"><?= formatMoney($month['total']) ?></td>
                    <td class="px-4 py-3 text-right"><?= $pct ?>%</td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'top' && $report && $report['success']): ?>

<!-- Top Categories -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-800">Top 10 Expense Categories</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">% of Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['top_categories'])): ?>
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">No expense data found</td>
                </tr>
                <?php else: 
                    $rank = 1;
                    foreach ($report['top_categories'] as $cat):
                        $pct = $grandTotal > 0 ? round(($cat['total'] / $grandTotal) * 100, 1) : 0;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded-full text-xs font-bold <?= $rank <= 3 ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600' ?>">
                            <?= $rank ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($cat['category']) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold"><?= formatMoney($cat['total']) ?></td>
                    <td class="px-4 py-3 text-right"><?= $pct ?>%</td>
                </tr>
                <?php $rank++; endforeach; ?>
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
