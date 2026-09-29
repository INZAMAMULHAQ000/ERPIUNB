<?php
/**
 * Monthly Payroll Summary Report
 * Phase F10 - Payroll & Finance Integration
 */
$pageTitle = 'Monthly Payroll Summary';
require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

if (!hasPermission('payroll_finance.report')) {
    redirect_to('no-access.php');
}

$db = getDB();

$year = $_GET['year'] ?? date('Y');

// Monthly aggregation: for each month, get totals from finance postings
$stmt = $db->prepare("
    SELECT
        MONTH(posting_date) as month,
        COUNT(DISTINCT payroll_run_id) as run_count,
        SUM(total_gross) as gross,
        SUM(total_deductions) as deductions,
        SUM(total_net) as net
    FROM payroll_finance_postings
    WHERE status = 'Posted' AND YEAR(posting_date) = ?
    GROUP BY MONTH(posting_date)
    ORDER BY month ASC
");
$stmt->execute([$year]);
$monthlyData = [];
while ($row = $stmt->fetch()) {
    $monthlyData[(int)$row['month']] = $row;
}

// Grand totals for the year
$yearTotal = ['run_count' => 0, 'gross' => 0, 'deductions' => 0, 'net' => 0];
foreach ($monthlyData as $m) {
    $yearTotal['run_count'] += (int)$m['run_count'];
    $yearTotal['gross']     += (float)$m['gross'];
    $yearTotal['deductions']+= (float)$m['deductions'];
    $yearTotal['net']       += (float)$m['net'];
}

// Payment totals per month
$payStmt = $db->prepare("
    SELECT
        MONTH(payment_date) as month,
        SUM(total_amount) as paid
    FROM payroll_payment_batches
    WHERE YEAR(payment_date) = ?
      AND status NOT IN ('Failed', 'Cancelled')
    GROUP BY MONTH(payment_date)
");
$payStmt->execute([$year]);
while ($row = $payStmt->fetch()) {
    $m = (int)$row['month'];
    if (!isset($monthlyData[$m])) $monthlyData[$m] = ['run_count' => 0, 'gross' => 0, 'deductions' => 0, 'net' => 0];
    $monthlyData[$m]['paid'] = (float)$row['paid'];
}
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="index.php" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-2"></i>Back to Reports</a>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold">Monthly Payroll Summary</h1>
        <p class="text-gray-600">Month-by-month payroll overview for <?= sanitize($year) ?></p>
    </div>

    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form method="GET" class="flex items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                <input type="number" name="year" value="<?= sanitize($year) ?>" class="px-3 py-2 border rounded w-32">
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                <i class="fas fa-search"></i>
            </button>
            <button type="button" onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                <i class="fas fa-print"></i>
            </button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Runs</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Gross</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Deductions</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Net Salary</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paid</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                <?php for ($m = 1; $m <= 12; $m++):
                    $data = $monthlyData[$m] ?? null;
                    $hasData = $data && ($data['run_count'] > 0 || ($data['paid'] ?? 0) > 0);
                    if (!$hasData && $data === null && $m > date('n')) continue; // Skip future months if no data
                    $gross = (float)($data['gross'] ?? 0);
                    $ded  = (float)($data['deductions'] ?? 0);
                    $net  = (float)($data['net'] ?? 0);
                    $paid = (float)($data['paid'] ?? 0);
                    $bal  = $net - $paid;
                ?>
                    <tr class="hover:bg-gray-50 <?= !$hasData ? 'text-gray-400' : '' ?>">
                        <td class="px-4 py-3 text-sm font-semibold"><?= getMonthName($m) ?></td>
                        <td class="px-4 py-3 text-sm text-center"><?= $hasData ? (int)$data['run_count'] : '-' ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= $hasData ? formatNumber($gross) : '-' ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= $hasData ? formatNumber($ded) : '-' ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono font-bold">AED <?= $hasData ? formatNumber($net) : '-' ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono <?= $paid > 0 ? 'text-green-600' : '' ?>">AED <?= $hasData && $paid > 0 ? formatNumber($paid) : ($hasData ? '0.00' : '-') ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono <?= $bal > 0 ? 'text-red-600 font-bold' : ($bal == 0 && $hasData ? 'text-green-600' : '') ?>">AED <?= $hasData ? formatNumber($bal) : '-' ?></td>
                    </tr>
                <?php endfor; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr class="font-bold">
                        <td class="px-4 py-3 text-sm">YEAR TOTAL (<?= sanitize($year) ?>)</td>
                        <td class="px-4 py-3 text-sm text-center"><?= $yearTotal['run_count'] ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($yearTotal['gross']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($yearTotal['deductions']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($yearTotal['net']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono" colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>