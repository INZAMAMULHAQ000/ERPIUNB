<?php
/**
 * Salary Expense Report
 * Phase F10 - Payroll & Finance Integration
 */
$pageTitle = 'Salary Expense Report';
require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

if (!hasPermission('payroll_finance.report')) {
    redirect_to('no-access.php');
}

$db = getDB();

$month = $_GET['month'] ?? date('m');
$year  = $_GET['year'] ?? date('Y');

// Get posted salary expenses linked to GL expense accounts via payroll mappings
$stmt = $db->prepare("
    SELECT
        coa.account_code,
        coa.account_name,
        COALESCE(SUM(jed.debit_amount), 0) as total_expense,
        COUNT(DISTINCT pfp.payroll_run_id) as run_count
    FROM payroll_finance_postings pfp
    JOIN journal_entries je ON pfp.journal_entry_id = je.id
    JOIN journal_entry_details jed ON je.id = jed.journal_entry_id
    JOIN chart_of_accounts coa ON jed.account_id = coa.id
    JOIN payroll_finance_mappings pfm ON coa.id = pfm.chart_account_id
    WHERE pfm.mapping_type = 'expense'
      AND pfp.status = 'Posted'
      AND MONTH(pfp.posting_date) = ?
      AND YEAR(pfp.posting_date) = ?
    GROUP BY coa.id, coa.account_code, coa.account_name
    ORDER BY total_expense DESC
");
$stmt->execute([$month, $year]);
$expenses = $stmt->fetchAll();

// Grand total
$totalExpense = 0;
foreach ($expenses as $e) $totalExpense += (float)$e['total_expense'];

// Month label
$monthLabel = getMonthName((int)$month) . ' ' . $year;

// Available months for filter
$monthsStmt = $db->prepare("SELECT DISTINCT MONTH(posting_date) as m, YEAR(posting_date) as y FROM payroll_finance_postings WHERE status = 'Posted' ORDER BY y DESC, m DESC");
$monthsStmt->execute();
$availableMonths = $monthsStmt->fetchAll();
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="index.php" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-2"></i>Back to Reports</a>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold">Salary Expense Report</h1>
        <p class="text-gray-600">Salary expenses breakdown for <?= sanitize($monthLabel) ?></p>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                <select name="month" class="px-3 py-2 border rounded">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= (int)$month === $m ? 'selected' : '' ?>><?= getMonthName($m) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                <input type="number" name="year" value="<?= sanitize($year) ?>" class="px-3 py-2 border rounded w-24">
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                <i class="fas fa-search"></i>
            </button>
            <button type="button" onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                <i class="fas fa-print"></i>
            </button>
        </form>
    </div>

    <!-- Summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Total Expense Categories</p>
            <p class="text-2xl font-bold"><?= count($expenses) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Total Salary Expense</p>
            <p class="text-2xl font-bold text-blue-600">AED <?= formatNumber($totalExpense) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Period</p>
            <p class="text-2xl font-bold"><?= sanitize($monthLabel) ?></p>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Name</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Payroll Runs</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Debit (AED)</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">% of Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                <?php if (empty($expenses)): ?>
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No salary expense records found for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($expenses as $e): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono"><?= sanitize($e['account_code']) ?></td>
                        <td class="px-4 py-3 text-sm font-medium"><?= sanitize($e['account_name']) ?></td>
                        <td class="px-4 py-3 text-sm text-center"><?= (int)$e['run_count'] ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono font-bold">AED <?= formatNumber($e['total_expense']) ?></td>
                        <td class="px-4 py-3 text-sm text-right"><?= formatPercent($e['total_expense'], $totalExpense) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if (!empty($expenses)): ?>
                <tfoot class="bg-gray-50">
                    <tr class="font-bold">
                        <td colspan="3" class="px-4 py-3 text-sm">TOTAL</td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($totalExpense) ?></td>
                        <td class="px-4 py-3 text-sm text-right">100.00%</td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>