<?php
/**
 * F11 — Budget Reports
 * Budget vs Actual, Variance Analysis, Utilization
 * Integrates with F7 - Budget Management
 */

$pageTitle = 'Budget Reports';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once '../FinancialReportService.php';

if (!hasPermission('report.budget.view')) { die('Access Denied'); }

$db = getDB();

// Get parameters
$reportType = $_GET['report_type'] ?? 'summary';
$budgetId = (int)($_GET['budget_id'] ?? 0);
$fyId = (int)($_GET['financial_year_id'] ?? 0);
$departmentId = (int)($_GET['department_id'] ?? 0);

// Default to active FY
if (!$fyId) {
    $activeFY = getActiveFinancialYear($db);
    $fyId = $activeFY ? (int)$activeFY['id'] : 0;
}

// Get lookup data
$budgets = $db->query("SELECT id, budget_name, financial_year_id FROM budgets ORDER BY budget_name DESC")->fetchAll();
$departments = $db->query("SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name")->fetchAll();
$financialYears = $db->query("SELECT id, year_name FROM financial_years ORDER BY start_date DESC")->fetchAll();

// Generate report based on type
$report = null;

if ($reportType === 'summary') {
    $report = FinancialReportService::generateBudgetVsActual([
        'budget_id' => $budgetId,
        'financial_year_id' => $fyId,
    ]);
} elseif ($reportType === 'department') {
    $where = "WHERE bl.budget_id IN (SELECT id FROM budgets WHERE financial_year_id = ?)";
    $p = [$fyId];
    if ($departmentId) { $where .= " AND bl.department_id = ?"; $p[] = $departmentId; }
    
    $stmt = $db->prepare("
        SELECT d.department_name,
               SUM(bl.budget_amount) as total_budget,
               (SELECT COALESCE(SUM(gl.debit - gl.credit), 0) FROM general_ledger gl
                JOIN chart_of_accounts coa ON coa.id = gl.account_id
                JOIN budget_lines bl2 ON bl2.chart_of_accounts_id = coa.id
                WHERE bl2.department_id = d.id AND gl.financial_year_id = ?) as total_actual
        FROM budget_lines bl
        LEFT JOIN departments d ON d.id = bl.department_id
        $where
        GROUP BY bl.department_id
        ORDER BY total_budget DESC
    ");
    $stmt->execute(array_merge([$fyId], $p));
    $report = ['success' => true, 'departments' => $stmt->fetchAll(), 'fyId' => $fyId];
} elseif ($reportType === 'account') {
    $where = "WHERE bl.budget_id = ?";
    $p = [$budgetId ?: 0];
    
    $stmt = $db->prepare("
        SELECT coa.account_code, coa.account_name, ag.group_name,
               bl.budget_amount,
               (SELECT COALESCE(SUM(gl.debit - gl.credit), 0) FROM general_ledger gl
                WHERE gl.account_id = bl.chart_of_accounts_id AND gl.financial_year_id = ?) as actual
        FROM budget_lines bl
        JOIN chart_of_accounts coa ON coa.id = bl.chart_of_accounts_id
        JOIN account_groups ag ON ag.id = coa.group_id
        $where
        ORDER BY coa.account_code
    ");
    $stmt->execute(array_merge([$fyId], $p));
    $report = ['success' => true, 'accounts' => $stmt->fetchAll(), 'fyId' => $fyId];
} elseif ($reportType === 'utilization') {
    // Budget utilization by category
    $stmt = $db->prepare("
        SELECT ag.group_name as category,
               SUM(bl.budget_amount) as budget,
               (SELECT COALESCE(SUM(gl.debit - gl.credit), 0) FROM general_ledger gl
                JOIN chart_of_accounts coa2 ON coa2.id = gl.account_id
                JOIN account_groups ag2 ON ag2.id = coa2.group_id
                WHERE ag2.group_name = ag.group_name AND gl.financial_year_id = ?) as actual
        FROM budget_lines bl
        JOIN chart_of_accounts coa ON coa.id = bl.chart_of_accounts_id
        JOIN account_groups ag ON ag.id = coa.group_id
        WHERE bl.budget_id = ?
        GROUP BY ag.id
        ORDER BY budget DESC
    ");
    $stmt->execute([$fyId, $budgetId ?: 0]);
    $report = ['success' => true, 'categories' => $stmt->fetchAll(), 'fyId' => $fyId];
}

// Get budget details for header
$budgetDetails = null;
if ($budgetId) {
    $budgetStmt = $db->prepare("SELECT * FROM budgets WHERE id = ?");
    $budgetStmt->execute([$budgetId]);
    $budgetDetails = $budgetStmt->fetch();
}
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Budget Reports</h1>
        <p class="text-gray-600"><?= $budgetDetails ? sanitize($budgetDetails['budget_name']) : 'All Budgets' ?></p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"><i class="fas fa-print mr-2"></i>Print</button>
        <a href="../dashboard/index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-arrow-left mr-2"></i>Dashboard</a>
    </div>
</div>

<!-- Report Type Tabs -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="flex border-b overflow-x-auto">
        <a href="?report_type=summary&financial_year_id=<?= $fyId ?>&budget_id=<?= $budgetId ?>" class="px-6 py-3 font-medium <?= $reportType === 'summary' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Budget vs Actual
        </a>
        <a href="?report_type=department&financial_year_id=<?= $fyId ?>" class="px-6 py-3 font-medium <?= $reportType === 'department' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            By Department
        </a>
        <a href="?report_type=account&budget_id=<?= $budgetId ?>&financial_year_id=<?= $fyId ?>" class="px-6 py-3 font-medium <?= $reportType === 'account' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            By Account
        </a>
        <a href="?report_type=utilization&budget_id=<?= $budgetId ?>&financial_year_id=<?= $fyId ?>" class="px-6 py-3 font-medium <?= $reportType === 'utilization' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Utilization
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow mb-6 p-4">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <input type="hidden" name="report_type" value="<?= $reportType ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Financial Year</label>
            <select name="financial_year_id" class="px-3 py-2 border rounded" onchange="this.form.submit()">
                <?php foreach ($financialYears as $fy): ?>
                <option value="<?= $fy['id'] ?>" <?= $fyId == $fy['id'] ? 'selected' : '' ?>><?= sanitize($fy['year_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Budget</label>
            <select name="budget_id" class="px-3 py-2 border rounded" onchange="this.form.submit()">
                <option value="">All Budgets</option>
                <?php foreach ($budgets as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $budgetId == $b['id'] ? 'selected' : '' ?>><?= sanitize($b['budget_name']) ?></option>
                <?php endforeach; ?>
            </select>
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
        <?php endif; ?>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-search mr-2"></i>Generate</button>
    </form>
</div>

<?php if ($reportType === 'summary' && $report && $report['success']): ?>

<!-- Budget vs Actual Summary -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-800">Budget vs Actual by Account</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Account Code</th>
                    <th class="px-4 py-3 text-left">Account Name</th>
                    <th class="px-4 py-3 text-left">Department</th>
                    <th class="px-4 py-3 text-right">Budget</th>
                    <th class="px-4 py-3 text-right">Actual</th>
                    <th class="px-4 py-3 text-right">Variance</th>
                    <th class="px-4 py-3 text-right">Variance %</th>
                    <th class="px-4 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['lines'])): ?>
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">No budget lines found</td>
                </tr>
                <?php else: 
                    $totalBudget = 0;
                    $totalActual = 0;
                    foreach ($report['lines'] as $l) {
                        $totalBudget += $l['budget'];
                        $totalActual += $l['actual'];
                    }
                    $totalVariance = $totalBudget - $totalActual;
                    $totalVariancePct = $totalBudget > 0 ? round(($totalVariance / $totalBudget) * 100, 1) : 0;
                ?>
                <?php foreach ($report['lines'] as $line): 
                    $variance = $line['budget'] - $line['actual'];
                    $varPct = $line['budget'] > 0 ? round(($variance / $line['budget']) * 100, 1) : 0;
                    $status = $varPct >= 0 ? 'under' : 'over';
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-gray-600"><?= sanitize($line['account_code']) ?></td>
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($line['account_name']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= sanitize($line['department'] ?? '-') ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($line['budget']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($line['actual']) ?></td>
                    <td class="px-4 py-3 text-right font-mono <?= $variance >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= formatMoney(abs($variance)) ?>
                    </td>
                    <td class="px-4 py-3 text-right <?= $variance >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= $varPct ?>%
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-1 rounded text-xs font-semibold <?= $status === 'under' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= $status === 'under' ? 'Under Budget' : 'Over Budget' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tfoot class="bg-gray-100 font-bold">
                    <tr>
                        <td colspan="3" class="px-4 py-3">TOTAL</td>
                        <td class="px-4 py-3 text-right font-mono"><?= formatMoney($totalBudget) ?></td>
                        <td class="px-4 py-3 text-right font-mono"><?= formatMoney($totalActual) ?></td>
                        <td class="px-4 py-3 text-right font-mono <?= $totalVariance >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                            <?= formatMoney(abs($totalVariance)) ?>
                        </td>
                        <td class="px-4 py-3 text-right <?= $totalVariance >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                            <?= $totalVariancePct ?>%
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'department' && $report && $report['success']): ?>

<!-- Budget by Department -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-800">Budget vs Actual by Department</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Department</th>
                    <th class="px-4 py-3 text-right">Budget</th>
                    <th class="px-4 py-3 text-right">Actual</th>
                    <th class="px-4 py-3 text-right">Variance</th>
                    <th class="px-4 py-3 text-right">Variance %</th>
                    <th class="px-4 py-3 text-center">Utilization</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['departments'])): ?>
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No budget data found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['departments'] as $dept): 
                    $variance = (float)$dept['total_budget'] - (float)$dept['total_actual'];
                    $varPct = $dept['total_budget'] > 0 ? round(($variance / $dept['total_budget']) * 100, 1) : 0;
                    $utilization = $dept['total_budget'] > 0 ? round(((float)$dept['total_actual'] / (float)$dept['total_budget']) * 100, 1) : 0;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($dept['department_name'] ?? 'Unassigned') ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($dept['total_budget']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($dept['total_actual']) ?></td>
                    <td class="px-4 py-3 text-right font-mono <?= $variance >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= formatMoney(abs($variance)) ?>
                    </td>
                    <td class="px-4 py-3 text-right <?= $variance >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= $varPct ?>%
                    </td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center">
                            <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                                <div class="h-2 rounded-full <?= $utilization > 100 ? 'bg-red-500' : ($utilization > 90 ? 'bg-yellow-500' : 'bg-green-500') ?>" style="width: <?= min($utilization, 100) ?>%"></div>
                            </div>
                            <span class="text-xs"><?= $utilization ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'account' && $report && $report['success']): ?>

<!-- Budget by Account -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-800">Budget vs Actual by Account</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Account Code</th>
                    <th class="px-4 py-3 text-left">Account Name</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-right">Budget</th>
                    <th class="px-4 py-3 text-right">Actual</th>
                    <th class="px-4 py-3 text-right">Variance</th>
                    <th class="px-4 py-3 text-right">Utilization</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['accounts'])): ?>
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">No budget data found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['accounts'] as $acct): 
                    $variance = (float)$acct['budget_amount'] - (float)$acct['actual'];
                    $utilization = $acct['budget_amount'] > 0 ? round(((float)$acct['actual'] / (float)$acct['budget_amount']) * 100, 1) : 0;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-gray-600"><?= sanitize($acct['account_code']) ?></td>
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($acct['account_name']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= sanitize($acct['group_name']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($acct['budget_amount']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($acct['actual']) ?></td>
                    <td class="px-4 py-3 text-right font-mono <?= $variance >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= formatMoney(abs($variance)) ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <span class="px-2 py-1 rounded text-xs font-semibold <?= $utilization > 100 ? 'bg-red-100 text-red-800' : ($utilization > 90 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') ?>">
                            <?= $utilization ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'utilization' && $report && $report['success']): ?>

<!-- Budget Utilization by Category -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-800">Budget Utilization by Category</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-right">Budget</th>
                    <th class="px-4 py-3 text-right">Actual</th>
                    <th class="px-4 py-3 text-right">Remaining</th>
                    <th class="px-4 py-3 text-center">Utilization</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['categories'])): ?>
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">No budget data found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['categories'] as $cat): 
                    $remaining = (float)$cat['budget'] - (float)$cat['actual'];
                    $utilization = $cat['budget'] > 0 ? round(((float)$cat['actual'] / (float)$cat['budget']) * 100, 1) : 0;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($cat['category']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($cat['budget']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($cat['actual']) ?></td>
                    <td class="px-4 py-3 text-right font-mono <?= $remaining >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= formatMoney(abs($remaining)) ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center">
                            <div class="flex-1 bg-gray-200 rounded-full h-3 mr-2">
                                <div class="h-3 rounded-full <?= $utilization > 100 ? 'bg-red-500' : ($utilization > 90 ? 'bg-yellow-500' : 'bg-green-500') ?>" style="width: <?= min($utilization, 100) ?>%"></div>
                            </div>
                            <span class="text-sm font-semibold <?= $utilization > 100 ? 'text-red-600' : ($utilization > 90 ? 'text-yellow-600' : 'text-green-600' ) ?>">
                                <?= $utilization ?>%
                            </span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
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