<?php
/**
 * Deduction Report
 * Phase F10 - Payroll & Finance Integration
 */
$pageTitle = 'Deduction Report';
require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

if (!hasPermission('payroll_finance.report')) {
    redirect_to('no-access.php');
}

$db = getDB();

$month = $_GET['month'] ?? date('m');
$year  = $_GET['year'] ?? date('y');

// Get deductions from posted payroll postings
$stmt = $db->prepare("
    SELECT
        pfp.payroll_run_id,
        pfp.posting_date,
        pfp.total_deductions,
        pfp.total_gross,
        pfp.total_net,
        pfp.status
    FROM payroll_finance_postings pfp
    WHERE pfp.status = 'Posted'
      AND MONTH(pfp.posting_date) = ?
      AND YEAR(pfp.posting_date) = ?
      AND pfp.total_deductions > 0
    ORDER BY pfp.posting_date DESC
");
$stmt->execute([$month, $year]);
$rows = $stmt->fetchAll();

$totalDeductions = $totalGross = $totalNet = 0;
foreach ($rows as $r) {
    $totalDeductions += (float)$r['total_deductions'];
    $totalGross      += (float)$r['total_gross'];
    $totalNet        += (float)$r['total_net'];
}

$monthLabel = getMonthName((int)$month) . ' ' . $year;
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="index.php" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-2"></i>Back to Reports</a>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold">Deduction Report</h1>
        <p class="text-gray-600">Payroll deductions breakdown for <?= sanitize($monthLabel) ?></p>
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

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Total Gross</p>
            <p class="text-2xl font-bold">AED <?= formatNumber($totalGross) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Total Deductions</p>
            <p class="text-2xl font-bold text-red-600">AED <?= formatNumber($totalDeductions) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Total Net Paid</p>
            <p class="text-2xl font-bold text-green-600">AED <?= formatNumber($totalNet) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Deduction %</p>
            <p class="text-2xl font-bold text-orange-600"><?= $totalGross > 0 ? formatPercent($totalDeductions, $totalGross) : '0.00%' ?></p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Run #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Gross</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Deductions</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Net</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Deduction %</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No deduction records for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-center font-medium">Run #<?= (int)$r['payroll_run_id'] ?></td>
                        <td class="px-4 py-3 text-sm"><?= formatDate($r['posting_date']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($r['total_gross']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono text-red-600 font-bold">AED <?= formatNumber($r['total_deductions']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono text-green-600 font-bold">AED <?= formatNumber($r['total_net']) ?></td>
                        <td class="px-4 py-3 text-sm text-right">
                            <?= $r['total_gross'] > 0 ? formatPercent($r['total_deductions'], $r['total_gross']) : '0.00%' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                <tfoot class="bg-gray-50">
                    <tr class="font-bold">
                        <td colspan="2" class="px-4 py-3 text-sm">TOTAL</td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($totalGross) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($totalDeductions) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($totalNet) ?></td>
                        <td class="px-4 py-3 text-sm text-right"><?= $totalGross > 0 ? formatPercent($totalDeductions, $totalGross) : '0.00%' ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>