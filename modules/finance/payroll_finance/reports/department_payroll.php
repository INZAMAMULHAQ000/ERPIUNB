<?php
/**
 * Department Payroll Report
 * Phase F10 - Payroll & Finance Integration
 */
$pageTitle = 'Department Payroll Report';
require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

if (!hasPermission('payroll_finance.report')) {
    redirect_to('no-access.php');
}

$db = getDB();

$month = $_GET['month'] ?? date('m');
$year  = $_GET['year'] ?? date('Y');

// If there's a payroll_runs table with department breakdown, use it.
// Since payroll_runs may not exist yet, we'll aggregate from postings + GL mappings
// NOTE: This report shows total payroll cost. Department breakdown requires payroll_run department data.

// Get all GL mappings with their components
$mappingsStmt = $db->prepare("
    SELECT pfm.id, pfm.payroll_component, pfm.chart_account_id, pfm.mapping_type,
           coa.account_code, coa.account_name
    FROM payroll_finance_mappings pfm
    JOIN chart_of_accounts coa ON pfm.chart_account_id = coa.id
    WHERE pfm.status = 'Active'
    ORDER BY pfm.mapping_type, pfm.payroll_component
");
$mappingsStmt->execute();
$mappings = $mappingsStmt->fetchAll();

// Summary by mapping type
$summaryStmt = $db->prepare("
    SELECT
        pfm.mapping_type,
        COALESCE(SUM(jed.debit_amount), 0) as total_debit,
        COALESCE(SUM(jed.credit_amount), 0) as total_credit,
        COUNT(DISTINCT pfp.payroll_run_id) as run_count
    FROM payroll_finance_postings pfp
    JOIN journal_entries je ON pfp.journal_entry_id = je.id
    JOIN journal_entry_details jed ON je.id = jed.journal_entry_id
    JOIN payroll_finance_mappings pfm ON jed.account_id = pfm.chart_account_id
    WHERE pfp.status = 'Posted'
      AND MONTH(pfp.posting_date) = ?
      AND YEAR(pfp.posting_date) = ?
    GROUP BY pfm.mapping_type
    ORDER BY pfm.mapping_type
");
$summaryStmt->execute([$month, $year]);
$summary = $summaryStmt->fetchAll();

$grandTotal = 0;
foreach ($summary as $s) {
    $grandTotal += max((float)$s['total_debit'], (float)$s['total_credit']);
}

// Full detail
$detailStmt = $db->prepare("
    SELECT
        pfm.payroll_component,
        pfm.mapping_type,
        coa.account_code,
        coa.account_name,
        pfp.posting_date,
        pfp.payroll_run_id,
        COALESCE(SUM(jed.debit_amount), 0) as debit,
        COALESCE(SUM(jed.credit_amount), 0) as credit
    FROM payroll_finance_postings pfp
    JOIN journal_entries je ON pfp.journal_entry_id = je.id
    JOIN journal_entry_details jed ON je.id = jed.journal_entry_id
    JOIN payroll_finance_mappings pfm ON jed.account_id = pfm.chart_account_id
    JOIN chart_of_accounts coa ON jed.account_id = coa.id
    WHERE pfp.status = 'Posted'
      AND MONTH(pfp.posting_date) = ?
      AND YEAR(pfp.posting_date) = ?
    GROUP BY pfm.id, pfp.payroll_run_id, pfp.posting_date
    ORDER BY pfm.mapping_type, pfm.payroll_component, pfp.posting_date DESC
");
$detailStmt->execute([$month, $year]);
$details = $detailStmt->fetchAll();

$monthLabel = getMonthName((int)$month) . ' ' . $year;
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="index.php" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-2"></i>Back to Reports</a>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold">Department Payroll Report</h1>
        <p class="text-gray-600">Payroll expense by category/account for <?= sanitize($monthLabel) ?></p>
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

    <!-- Summary Cards by Type -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <?php foreach ($summary as $s): $amt = max((float)$s['total_debit'], (float)$s['total_credit']); ?>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500 capitalize"><?= sanitize($s['mapping_type']) ?> - <?= $s['run_count'] ?> runs</p>
                <p class="text-2xl font-bold text-blue-600">AED <?= formatNumber($amt) ?></p>
            </div>
        <?php endforeach; ?>
        <?php if (empty($summary)): ?>
            <div class="bg-white rounded-lg shadow p-4 col-span-3">
                <p class="text-gray-500">No data for selected period.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Detail Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Component</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">GL Account</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Runs</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                <?php if (empty($details)): ?>
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No department payroll data found.</td></tr>
                <?php else: ?>
                    <?php foreach ($details as $d): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium"><?= sanitize($d['payroll_component']) ?></td>
                        <td class="px-4 py-3 text-sm"><span class="px-2 py-1 text-xs rounded <?= getStatusBadge($d['mapping_type']) ?>"><?= sanitize($d['mapping_type']) ?></span></td>
                        <td class="px-4 py-3 text-sm"><?= sanitize($d['account_code']) ?> - <?= sanitize($d['account_name']) ?></td>
                        <td class="px-4 py-3 text-sm text-center">Run #<?= (int)$d['payroll_run_id'] ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono <?= $d['debit'] > 0 ? 'font-bold text-green-700' : '' ?>">AED <?= formatNumber($d['debit']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono <?= $d['credit'] > 0 ? 'font-bold text-red-700' : '' ?>">AED <?= formatNumber($d['credit']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>