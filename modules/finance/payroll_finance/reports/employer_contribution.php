<?php
/**
 * Employer Contribution Report
 * Phase F10 - Payroll & Finance Integration
 */
$pageTitle = 'Employer Contribution Report';
require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

if (!hasPermission('payroll_finance.report')) {
    redirect_to('no-access.php');
}

$db = getDB();

$month = $_GET['month'] ?? date('m');
$year  = $_GET['year'] ?? date('y');

// Query GL entries for employer contributions (using 'employer' keyword in component or mapping type)
$stmt = $db->prepare("
    SELECT
        pfm.payroll_component,
        coa.account_code,
        coa.account_name,
        pfp.posting_date,
        pfp.payroll_run_id,
        COALESCE(SUM(jed.debit_amount), 0) as employer_contribution,
        COALESCE(SUM(jed.credit_amount), 0) as credit_total
    FROM payroll_finance_postings pfp
    JOIN journal_entries je ON pfp.journal_entry_id = je.id
    JOIN journal_entry_details jed ON je.id = jed.journal_entry_id
    JOIN payroll_finance_mappings pfm ON jed.account_id = pfm.chart_account_id
    JOIN chart_of_accounts coa ON jed.account_id = coa.id
    WHERE pfp.status = 'Posted'
      AND MONTH(pfp.posting_date) = ?
      AND YEAR(pfp.posting_date) = ?
    GROUP BY pfm.id, pfp.payroll_run_id, pfp.posting_date
    HAVING employer_contribution > 0 OR credit_total > 0
    ORDER BY pfm.payroll_component, pfp.posting_date DESC
");
$stmt->execute([$month, $year]);
$rows = $stmt->fetchAll();

$totalEmployer = 0;
foreach ($rows as $r) {
    $totalEmployer += max((float)$r['employer_contribution'], (float)$r['credit_total']);
}

$monthLabel = getMonthName((int)$month) . ' ' . $year;
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="index.php" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-2"></i>Back to Reports</a>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold">Employer Contribution Report</h1>
        <p class="text-gray-600">Employer social security, pension, and other contributions for <?= sanitize($monthLabel) ?></p>
    </div>

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

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Total Employer Contribution</p>
            <p class="text-2xl font-bold text-blue-600">AED <?= formatNumber($totalEmployer) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Period</p>
            <p class="text-2xl font-bold"><?= sanitize($monthLabel) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Unique Components</p>
            <p class="text-2xl font-bold"><?= count(array_unique(array_column($rows, 'payroll_component'))) ?></p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Run #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Component</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">GL Account</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Employer Amount (AED)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No employer contribution records found for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): $amt = max((float)$r['employer_contribution'], (float)$r['credit_total']); ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium">Run #<?= (int)$r['payroll_run_id'] ?></td>
                        <td class="px-4 py-3 text-sm"><?= sanitize($r['payroll_component']) ?></td>
                        <td class="px-4 py-3 text-sm"><?= sanitize($r['account_code']) ?> - <?= sanitize($r['account_name']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono font-bold">AED <?= formatNumber($amt) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                <tfoot class="bg-gray-50">
                    <tr class="font-bold">
                        <td colspan="3" class="px-4 py-3 text-sm">TOTAL</td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($totalEmployer) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>