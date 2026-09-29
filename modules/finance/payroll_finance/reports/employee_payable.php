<?php
/**
 * Employee Payable Report
 * Phase F10 - Payroll & Finance Integration
 */
$pageTitle = 'Employee Payable Report';
require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

if (!hasPermission('payroll_finance.report')) {
    redirect_to('no-access.php');
}

$db = getDB();

$month = $_GET['month'] ?? date('m');
$year  = $_GET['year'] ?? date('Y');

// Get liability totals by payroll run (what we owe employees)
$stmt = $db->prepare("
    SELECT
        pfp.payroll_run_id,
        pfp.posting_date,
        pfp.total_net,
        COALESCE(SUM(ppb.total_amount), 0) as amount_paid,
        (pfp.total_net - COALESCE(SUM(ppb.total_amount), 0)) as balance_due,
        pfp.status as posting_status,
        prr.status as recon_status
    FROM payroll_finance_postings pfp
    LEFT JOIN payroll_payment_batches ppb ON pfp.payroll_run_id = ppb.payroll_run_id
        AND ppb.status NOT IN ('Failed', 'Cancelled')
    LEFT JOIN payroll_reconciliation prr ON pfp.payroll_run_id = prr.payroll_run_id
    WHERE MONTH(pfp.posting_date) = ?
      AND YEAR(pfp.posting_date) = ?
      AND pfp.status = 'Posted'
    GROUP BY pfp.payroll_run_id
    ORDER BY pfp.posting_date DESC
");
$stmt->execute([$month, $year]);
$rows = $stmt->fetchAll();

$totalPayable = $totalPaid = $totalBalance = 0;
foreach ($rows as $r) {
    $totalPayable  += (float)$r['total_net'];
    $totalPaid     += (float)$r['amount_paid'];
    $totalBalance  += (float)$r['balance_due'];
}

$monthLabel = getMonthName((int)$month) . ' ' . $year;
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="index.php" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-2"></i>Back to Reports</a>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold">Employee Payable Report</h1>
        <p class="text-gray-600">Salary payable age analysis for <?= sanitize($monthLabel) ?></p>
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
            <p class="text-sm text-gray-500">Total Payable</p>
            <p class="text-2xl font-bold text-blue-600">AED <?= formatNumber($totalPayable) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Total Paid</p>
            <p class="text-2xl font-bold text-green-600">AED <?= formatNumber($totalPaid) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Outstanding Balance</p>
            <p class="text-2xl font-bold <?= $totalBalance > 0 ? 'text-red-600' : 'text-green-600' ?>">AED <?= formatNumber($totalBalance) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Payroll Runs</p>
            <p class="text-2xl font-bold"><?= count($rows) ?></p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Run #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Posting Date</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Payable (AED)</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paid (AED)</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance Due (AED)</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Progress</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No payable records for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r):
                        $payable = (float)$r['total_net'];
                        $paid    = (float)$r['amount_paid'];
                        $balance = (float)$r['balance_due'];
                        $pct     = $payable > 0 ? min(100, round(($paid / $payable) * 100)) : 0;
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium">Run #<?= (int)$r['payroll_run_id'] ?></td>
                        <td class="px-4 py-3 text-sm"><?= formatDate($r['posting_date']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($payable) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono text-green-600">AED <?= formatNumber($paid) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono font-bold <?= $balance > 0 ? 'text-red-600' : 'text-green-600' ?>">AED <?= formatNumber($balance) ?></td>
                        <td class="px-4 py-3">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="h-2 rounded-full <?= $pct >= 100 ? 'bg-green-500' : 'bg-yellow-500' ?>" style="width: <?= $pct ?>%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1"><?= $pct ?>%</p>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <?php if ($balance <= 0.01): ?>
                                <span class="px-2 py-1 text-xs rounded font-medium bg-green-100 text-green-800">Paid</span>
                            <?php elseif ($paid > 0): ?>
                                <span class="px-2 py-1 text-xs rounded font-medium bg-yellow-100 text-yellow-800">Partial</span>
                            <?php else: ?>
                                <span class="px-2 py-1 text-xs rounded font-medium bg-red-100 text-red-800">Outstanding</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                <tfoot class="bg-gray-50">
                    <tr class="font-bold">
                        <td colspan="2" class="px-4 py-3 text-sm">TOTAL</td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($totalPayable) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($totalPaid) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($totalBalance) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>