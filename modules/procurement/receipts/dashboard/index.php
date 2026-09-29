<?php
/** P4 — Receiving Dashboard */
$pageTitle = 'Receiving Dashboard';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../finance/helpers/finance_helpers.php';
require_once __DIR__ . '/../../ProcurementService.php';
require_once __DIR__ . '/../../ReceiptService.php';

if (!hasPermission('procurement.receipt.view')) die('Access Denied');
$db = getDB();
$stats = ReceiptService::dashboardStats($db);
$poStats = $db->query("SELECT
    SUM(status='partially_received') as partially_received,
    SUM(status='fully_received') as fully_received,
    SUM(status IN ('approved','sent','acknowledged') AND status NOT IN ('partially_received','fully_received')) as awaiting_receipt
 FROM purchase_orders")->fetch();
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Receiving Dashboard</h1>
    <p class="text-gray-500 text-sm">Goods and service receipt overview</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase">Draft Receipts</div>
        <div class="text-2xl font-bold mt-1"><?= number_format($stats['draft'] ?? 0) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase">Pending Inspection</div>
        <div class="text-2xl font-bold mt-1 text-yellow-600"><?= number_format($stats['pending_inspection'] ?? 0) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase">Partially Accepted</div>
        <div class="text-2xl font-bold mt-1 text-orange-600"><?= number_format($stats['partially_accepted'] ?? 0) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase">Accepted</div>
        <div class="text-2xl font-bold mt-1 text-green-600"><?= number_format($stats['accepted'] ?? 0) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase">Rejected</div>
        <div class="text-2xl font-bold mt-1 text-red-600"><?= number_format($stats['rejected'] ?? 0) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase">Total Received Qty</div>
        <div class="text-2xl font-bold mt-1"><?= number_format((float)($stats['total_received'] ?? 0), 1) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase">Total Accepted Qty</div>
        <div class="text-2xl font-bold mt-1 text-green-700"><?= number_format((float)($stats['total_accepted'] ?? 0), 1) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase">Total Rejected Qty</div>
        <div class="text-2xl font-bold mt-1 text-red-700"><?= number_format((float)($stats['total_rejected'] ?? 0), 1) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase">Total Damaged Qty</div>
        <div class="text-2xl font-bold mt-1 text-orange-700"><?= number_format((float)($stats['total_damaged'] ?? 0), 1) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase">POs Partially Received</div>
        <div class="text-2xl font-bold mt-1 text-cyan-600"><?= number_format($poStats['partially_received'] ?? 0) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase">POs Fully Received</div>
        <div class="text-2xl font-bold mt-1 text-emerald-600"><?= number_format($poStats['fully_received'] ?? 0) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase">POs Awaiting Receipt</div>
        <div class="text-2xl font-bold mt-1 text-gray-600"><?= number_format($poStats['awaiting_receipt'] ?? 0) ?></div>
    </div>
</div>

<div class="bg-white rounded-lg shadow p-4">
    <div class="flex justify-between items-center mb-3">
        <h3 class="font-semibold">Recent Receipts</h3>
        <a href="list/index.php" class="text-blue-600 hover:underline text-sm">View All</a>
    </div>
    <?php
    $recent = ReceiptService::listReceipts($db, [], 10, 0);
    $rows = $recent['rows'];
    if (empty($rows)): ?>
        <p class="text-gray-400 text-sm text-center py-6">No receipts found.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                <tr>
                    <th class="px-4 py-2 text-left">GR Number</th>
                    <th class="px-4 py-2 text-left">Date</th>
                    <th class="px-4 py-2 text-left">PO</th>
                    <th class="px-4 py-2 text-left">Vendor</th>
                    <th class="px-4 py-2 text-left">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($rows as $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 font-mono text-xs"><?= sanitize($r['gr_number']) ?></td>
                    <td class="px-4 py-2 text-xs"><?= $r['receipt_date'] ? date('d M Y', strtotime($r['receipt_date'])) : '—' ?></td>
                    <td class="px-4 py-2 text-xs"><?= sanitize($r['po_number']) ?></td>
                    <td class="px-4 py-2"><?= sanitize($r['vendor_name']) ?></td>
                    <td class="px-4 py-2">
                        <span class="px-2 py-0.5 rounded text-xs font-semibold bg-gray-100">
                            <?= strtoupper(str_replace('_', ' ', $r['status'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-2 text-right">
                        <a href="../view/index.php?id=<?= $r['id'] ?>" class="text-blue-600 hover:underline text-xs">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
