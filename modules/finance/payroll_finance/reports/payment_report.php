<?php
/**
 * Payroll Payment Report
 * Phase F10 - Payroll & Finance Integration
 */
$pageTitle = 'Payment Report';
require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

if (!hasPermission('payroll_finance.report')) {
    redirect_to('no-access.php');
}

$db = getDB();

$search   = $_GET['search'] ?? '';
$status   = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to'] ?? date('Y-m-t');

// Get all batches with payment batches and linked postings
$where = "WHERE 1=1";
$params = [];
if ($search !== '') {
    $where .= " AND (CAST(pb.id AS CHAR) LIKE ? OR CAST(pb.payroll_run_id AS CHAR) LIKE ? OR pb.reference_number LIKE ?)";
    $s = "%$search%";
    $params[] = $s; $params[] = $s; $params[] = $s;
}
if ($status !== '') {
    $where .= " AND pb.status = ?";
    $params[] = $status;
}
if ($dateFrom) {
    $where .= " AND pb.payment_date >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where .= " AND pb.payment_date <= ?";
    $params[] = $dateTo;
}

$summaryStmt = $db->prepare("
    SELECT
        SUM(pb.total_amount) as total_amount,
        COUNT(*) as batch_count,
        COUNT(DISTINCT pb.payroll_run_id) as run_count
    FROM payroll_payment_batches pb
    $where
");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

// Count by status
$statusStmt = $db->prepare("SELECT status, COUNT(*) as cnt, SUM(total_amount) as total FROM payroll_payment_batches $where GROUP BY status");
$statusStmt->execute($params);
$byStatus = $statusStmt->fetchAll();

$detailStmt = $db->prepare("
    SELECT
        pb.id as batch_id,
        pb.payroll_run_id,
        pb.payment_date,
        pb.total_amount,
        pb.reference_number,
        pb.status,
        pb.journal_entry_id,
        ba.account_name as bank_account,
        pfp.total_net as payroll_net,
        pfp.status as posting_status
    FROM payroll_payment_batches pb
    LEFT JOIN bank_accounts ba ON pb.bank_account_id = ba.id
    LEFT JOIN payroll_finance_postings pfp ON pb.payroll_run_id = pfp.payroll_run_id
    $where
    ORDER BY pb.payment_date DESC, pb.id DESC
");
$paramsWithLimit = array_merge($params, [100]);
$detailStmt->execute($paramsWithLimit);
$rows = $detailStmt->fetchAll();
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="index.php" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-2"></i>Back to Reports</a>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold">Payment Report</h1>
        <p class="text-gray-600">Salary payment batches and transactions</p>
    </div>

    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Batch ID, Run ID, Reference" class="px-3 py-2 border rounded w-48">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="px-3 py-2 border rounded">
                    <option value="">All</option>
                    <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Approved" <?= $status === 'Approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="Paid" <?= $status === 'Paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="Failed" <?= $status === 'Failed' ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>
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
                <i class="fas fa-print"></i>
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Total Payment Amount</p>
            <p class="text-2xl font-bold text-blue-600">AED <?= formatNumber($summary['total_amount'] ?? 0) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Total Batches</p>
            <p class="text-2xl font-bold"><?= (int)($summary['batch_count'] ?? 0) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Payroll Runs Covered</p>
            <p class="text-2xl font-bold"><?= (int)($summary['run_count'] ?? 0) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-500">Period</p>
            <p class="text-sm font-bold"><?= formatDate($dateFrom) ?> — <?= formatDate($dateTo) ?></p>
        </div>
    </div>

    <!-- Status breakdown -->
    <?php if (!empty($byStatus)): ?>
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <h3 class="font-semibold text-gray-700 mb-3">By Status</h3>
        <div class="flex flex-wrap gap-3">
            <?php foreach ($byStatus as $s): ?>
                <span class="px-3 py-1 rounded-full text-sm font-medium <?= getStatusBadge($s['status']) ?>">
                    <?= sanitize($s['status']) ?>: (<?= (int)$s['cnt'] ?>) - AED <?= formatNumber($s['total']) ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Batch</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Run</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bank Account</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment Date</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Journal Entry</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">No payment records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium">Batch #<?= (int)$r['batch_id'] ?></td>
                        <td class="px-4 py-3 text-sm text-center">Run #<?= (int)$r['payroll_run_id'] ?></td>
                        <td class="px-4 py-3 text-sm"><?= sanitize($r['bank_account'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm"><?= formatDate($r['payment_date']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-mono font-bold">AED <?= formatNumber($r['total_amount']) ?></td>
                        <td class="px-4 py-3 text-sm"><?= sanitize($r['reference_number']) ?></td>
                        <td class="px-4 py-3 text-sm"><?= $r['journal_entry_id'] ? 'JE #' . (int)$r['journal_entry_id'] : '-' ?></td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-1 text-xs rounded font-medium <?= getStatusBadge($r['status']) ?>">
                                <?= sanitize($r['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>