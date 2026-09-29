<?php
/**
 * Payroll Cost Report
 * Phase F10 - Payroll & Finance Integration
 */
$pageTitle = 'Payroll Cost Report';
require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

if (!hasPermission('payroll_finance.report')) {
    redirect_to('no-access.php');
}

$db = getDB();

// Date range filters
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to'] ?? date('Y-m-t');

// Summary totals
$summary = $db->prepare("
    SELECT
        COUNT(DISTINCT pfp.payroll_run_id) as total_runs,
        COALESCE(SUM(pfp.total_gross), 0) as total_gross,
        COALESCE(SUM(pfp.total_deductions), 0) as total_deductions,
        COALESCE(SUM(pfp.total_net), 0) as total_net,
        COALESCE(SUM(ppb.total_amount), 0) as total_paid
    FROM payroll_finance_postings pfp
    LEFT JOIN payroll_payment_batches ppb ON pfp.payroll_run_id = ppb.payroll_run_id
        AND ppb.status IN ('Paid', 'Approved')
    WHERE pfp.posting_date BETWEEN ? AND ?
");
$summary->execute([$dateFrom, $dateTo]);
$stats = $summary->fetch();

// Detail: per payroll run cost breakdown
$detailStmt = $db->prepare("
    SELECT
        pfp.payroll_run_id,
        pfp.posting_date,
        pfp.total_gross,
        pfp.total_deductions,
        pfp.total_net,
        pfp.status,
        COALESCE(SUM(ppb.total_amount), 0) as amount_paid,
        prr.status as recon_status
    FROM payroll_finance_postings pfp
    LEFT JOIN payroll_payment_batches ppb ON pfp.payroll_run_id = ppb.payroll_run_id
        AND ppb.status IN ('Paid', 'Approved')
    LEFT JOIN payroll_reconciliation prr ON pfp.payroll_run_id = prr.payroll_run_id
    WHERE pfp.posting_date BETWEEN ? AND ?
    GROUP BY pfp.id, pfp.payroll_run_id, pfp.posting_date, pfp.total_gross, pfp.total_deductions, pfp.total_net, pfp.status, prr.status
    ORDER BY pfp.posting_date DESC
");
$detailStmt->execute([$dateFrom, $dateTo]);
$rows = $detailStmt->fetchAll();
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="index.php" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-2"></i>Back to Reports</a>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold">Payroll Cost Report</h1>
        <p class="text-gray-600">Detailed breakdown of total payroll costs by run</p>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" name="date_from" value="<?= sanitize($dateFrom) ?>" class="px-3 py-2 border rounded">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" name="date_to" value="<?= sanitize($dateTo) ?>" class="px-3 py-2 border rounded">
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                <i class="fas fa-search"></i>
            </button>
            <button type="button" onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                <i class="fas fa-print"></i> Print
            </button>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Total Payroll Runs</p>
            <p class="text-2xl font-bold"><?= (int)$stats['total_runs'] ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Total Gross</p>
            <p class="text-2xl font-bold text-blue-600">AED <?= formatNumber($stats['total_gross']) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Total Deductions</p>
            <p class="text-2xl font-bold text-orange-600">AED <?= formatNumber($stats['total_deductions']) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Total Net Paid</p>
            <p class="text-2xl font-bold text-green-600">AED <?= formatNumber($stats['total_net']) ?></p>
        </div>
    </div>

    <!-- Detail Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Run #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Posting Date</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Gross</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Deductions</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Net</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paid</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Payment Status</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Recon Status</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Posting Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">No payroll cost records found for the selected period.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium">Run #<?= (int)$r['payroll_run_id'] ?></td>
                        <td class="px-4 py-3 text-sm"><?= formatDate($r['posting_date']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($r['total_gross']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($r['total_deductions']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono font-bold">AED <?= formatNumber($r['total_net']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($r['amount_paid']) ?></td>
                        <td class="px-4 py-3 text-center">
                            <?php if ($r['amount_paid'] >= $r['total_net'] - 0.01): ?>
                                <span class="px-2 py-1 text-xs rounded font-medium bg-green-100 text-green-800">Paid</span>
                            <?php elseif ($r['amount_paid'] > 0): ?>
                                <span class="px-2 py-1 text-xs rounded font-medium bg-yellow-100 text-yellow-800">Partial</span>
                            <?php else: ?>
                                <span class="px-2 py-1 text-xs rounded font-medium bg-red-100 text-red-800">Unpaid</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-1 text-xs rounded font-medium <?= getStatusBadge($r['recon_status'] ?? 'Unreconciled') ?>">
                                <?= sanitize($r['recon_status'] ?? '-') ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-1 text-xs rounded font-medium <?= getStatusBadge($r['status']) ?>">
                                <?= sanitize($r['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                <tfoot class="bg-gray-50">
                    <tr class="font-bold">
                        <td colspan="2" class="px-4 py-3 text-sm">TOTAL</td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($stats['total_gross']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($stats['total_deductions']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($stats['total_net']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono">AED <?= formatNumber($stats['total_paid']) ?></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>