<?php
/**
 * Yearly Payroll Summary Report
 * Phase F10 - Payroll & Finance Integration
 */
$pageTitle = 'Yearly Payroll Summary';
require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

if (!hasPermission('payroll_finance.report')) {
    redirect_to('no-access.php');
}

$db = getDB();

// Get all available years
$yearsStmt = $db->prepare("
    SELECT DISTINCT YEAR(posting_date) as yr FROM payroll_finance_postings
    WHERE status = 'Posted'
    UNION
    SELECT DISTINCT YEAR(payment_date) as yr FROM payroll_payment_batches
    ORDER BY yr DESC
");
$yearsStmt->execute();
$availableYears = array_column($yearsStmt->fetchAll(), 'yr');

$year = $_GET['year'] ?? ($availableYears[0] ?? date('Y'));

// Yearly aggregation
$stmt = $db->prepare("
    SELECT
        COUNT(DISTINCT payroll_run_id) as total_runs,
        SUM(total_gross) as total_gross,
        SUM(total_deductions) as total_deductions,
        SUM(total_net) as total_net,
        COUNT(*) as total_postings,
        MIN(posting_date) as first_posting,
        MAX(posting_date) as last_posting
    FROM payroll_finance_postings
    WHERE status = 'Posted' AND YEAR(posting_date) = ?
");
$stmt->execute([$year]);
$postingSummary = $stmt->fetch();

// Monthly breakdown for the year
$monthlyStmt = $db->prepare("
    SELECT
        MONTH(posting_date) as month,
        COUNT(DISTINCT payroll_run_id) as runs,
        SUM(total_gross) as gross,
        SUM(total_deductions) as deductions,
        SUM(total_net) as net
    FROM payroll_finance_postings
    WHERE status = 'Posted' AND YEAR(posting_date) = ?
    GROUP BY MONTH(posting_date)
    ORDER BY month ASC
");
$monthlyStmt->execute([$year]);
$monthlyData = [];
while ($row = $monthlyStmt->fetch()) {
    $monthlyData[(int)$row['month']] = $row;
}

// Payment totals
$payStmt = $db->prepare("
    SELECT
        SUM(total_amount) as total_paid,
        COUNT(*) as batch_count,
        COUNT(DISTINCT payroll_run_id) as runs_paid
    FROM payroll_payment_batches
    WHERE YEAR(payment_date) = ? AND status NOT IN ('Failed', 'Cancelled')
");
$payStmt->execute([$year]);
$paymentSummary = $payStmt->fetch();

// Reconciliation status
$reconStmt = $db->prepare("
    SELECT status, COUNT(*) as cnt FROM payroll_reconciliation
    WHERE YEAR(checked_at) = ? OR payroll_run_id IN (
        SELECT payroll_run_id FROM payroll_finance_postings WHERE YEAR(posting_date) = ?
    )
    GROUP BY status
");
$reconStmt->execute([$year, $year]);
$reconStatus = $reconStmt->fetchAll();
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="index.php" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-2"></i>Back to Reports</a>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold">Yearly Payroll Summary</h1>
        <p class="text-gray-600">Annual payroll financial summary for <?= sanitize($year) ?></p>
    </div>

    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form method="GET" class="flex items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                <select name="year" class="px-3 py-2 border rounded">
                    <?php foreach ($availableYears as $y): ?>
                        <option value="<?= $y ?>" <?= (int)$year === (int)$y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                <i class="fas fa-search"></i>
            </button>
            <button type="button" onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                <i class="fas fa-print"></i> Print Full Report
            </button>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Total Payroll Runs</p>
            <p class="text-2xl font-bold"><?= (int)($postingSummary['total_runs'] ?? 0) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Total Gross</p>
            <p class="text-2xl font-bold text-blue-600">AED <?= formatNumber($postingSummary['total_gross'] ?? 0) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Total Net</p>
            <p class="text-2xl font-bold text-green-600">AED <?= formatNumber($postingSummary['total_net'] ?? 0) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Total Paid</p>
            <p class="text-2xl font-bold text-purple-600">AED <?= formatNumber($paymentSummary['total_paid'] ?? 0) ?></p>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Total Deductions</p>
            <p class="text-2xl font-bold text-orange-600">AED <?= formatNumber($postingSummary['total_deductions'] ?? 0) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Payment Batches</p>
            <p class="text-2xl font-bold"><?= (int)($paymentSummary['batch_count'] ?? 0) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Runs Paid</p>
            <p class="text-2xl font-bold"><?= (int)($paymentSummary['runs_paid'] ?? 0) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Period</p>
            <p class="text-sm font-bold">
                <?= $postingSummary['first_posting'] ? formatDate($postingSummary['first_posting']) : '-' ?>
                to
                <?= $postingSummary['last_posting'] ? formatDate($postingSummary['last_posting']) : '-' ?>
            </p>
        </div>
    </div>

    <!-- Monthly Breakdown Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="px-4 py-3 border-b font-semibold text-gray-700">Monthly Breakdown</div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Runs</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Gross</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Deductions</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Net</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">% of Yearly Net</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                <?php for ($m = 1; $m <= 12; $m++):
                    $d = $monthlyData[$m] ?? null;
                    $has = $d && ((int)$d['runs'] > 0);
                ?>
                    <tr class="hover:bg-gray-50 <?= !$has ? 'text-gray-400' : '' ?>">
                        <td class="px-4 py-3 text-sm font-semibold"><?= getMonthName($m) ?></td>
                        <td class="px-4 py-3 text-sm text-center"><?= $has ? (int)$d['runs'] : '-' ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= $has ? formatNumber($d['gross']) : '-' ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= $has ? formatNumber($d['deductions']) : '-' ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono font-bold">AED <?= $has ? formatNumber($d['net']) : '-' ?></td>
                        <td class="px-4 py-3 text-sm text-right">
                            <?= $has && ($postingSummary['total_net'] ?? 0) > 0
                                ? formatPercent($d['net'], $postingSummary['total_net'])
                                : '-' ?>
                        </td>
                    </tr>
                <?php endfor; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr class="font-bold">
                        <td class="px-4 py-3 text-sm">YEAR TOTAL</td>
                        <td class="px-4 py-3 text-sm text-center"><?= (int)($postingSummary['total_runs'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($postingSummary['total_gross'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($postingSummary['total_deductions'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($postingSummary['total_net'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-sm text-right">100.00%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Reconciliation Status -->
    <?php if (!empty($reconStatus)): ?>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-4 py-3 border-b font-semibold text-gray-700">Reconciliation Status</div>
        <div class="flex flex-wrap gap-3 p-4">
            <?php foreach ($reconStatus as $r): ?>
                <span class="px-3 py-2 rounded font-medium <?= getStatusBadge($r['status']) ?>">
                    <?= sanitize($r['status']) ?>: <?= (int)$r['cnt'] ?> runs
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>