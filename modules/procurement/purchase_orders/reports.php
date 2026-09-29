<?php
/** P3 — Purchase Order Reports */
$pageTitle = 'PO Reports';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../finance/helpers/finance_helpers.php';
require_once __DIR__ . '/../ProcurementService.php';
require_once __DIR__ . '/../PoService.php';

if (!hasPermission('procurement.po.report')) die('Access Denied');
$db = getDB();

// PO Register (all POs)
$pos = $db->query("SELECT po.*, v.vendor_name, c.company_name, d.department_name
    FROM purchase_orders po
    LEFT JOIN vendors v ON v.id=po.vendor_id
    LEFT JOIN companies c ON c.id=po.company_id
    LEFT JOIN departments d ON d.id=po.department_id
    ORDER BY po.id DESC LIMIT 200")->fetchAll();

// Summary stats
$stats = $db->query("SELECT
    COUNT(*) total,
    SUM(status='draft') draft,
    SUM(status='pending_approval') pending,
    SUM(status='approved') approved,
    SUM(status='sent') sent,
    SUM(status='acknowledged') ack,
    SUM(status='cancelled') cancelled,
    SUM(status='closed') closed,
    COALESCE(SUM(grand_total),0) total_value,
    COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','closed') THEN grand_total END),0) open_value
FROM purchase_orders")->fetch();
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">PO Reports</h1>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
    <div class="bg-white rounded-lg shadow p-3 text-center border-t-4 border-blue-500">
        <p class="text-[11px] text-gray-500 uppercase">Total POs</p>
        <p class="font-bold text-xl"><?= (int)$stats['total'] ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-3 text-center border-t-4 border-green-500">
        <p class="text-[11px] text-gray-500 uppercase">Approved</p>
        <p class="font-bold text-xl"><?= (int)($stats['approved'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-3 text-center border-t-4 border-yellow-500">
        <p class="text-[11px] text-gray-500 uppercase">Pending</p>
        <p class="font-bold text-xl"><?= (int)($stats['pending'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-3 text-center border-t-4 border-purple-500">
        <p class="text-[11px] text-gray-500 uppercase">Open Value</p>
        <p class="font-bold text-lg"><?= formatMoney((float)($stats['open_value'] ?? 0)) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-3 text-center border-t-4 border-gray-500">
        <p class="text-[11px] text-gray-500 uppercase">Total Value</p>
        <p class="font-bold text-lg"><?= formatMoney((float)($stats['total_value'] ?? 0)) ?></p>
    </div>
</div>

<!-- PO Register Table -->
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <div class="px-5 pt-4"><h3 class="font-semibold">Purchase Order Register</h3></div>
    <table class="w-full text-sm mt-2">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="px-4 py-2 text-left">PO Number</th>
                <th class="px-4 py-2 text-left">Date</th>
                <th class="px-4 py-2 text-left">Vendor</th>
                <th class="px-4 py-2 text-left">Company</th>
                <th class="px-4 py-2 text-left">Department</th>
                <th class="px-4 py-2 text-right">Total</th>
                <th class="px-4 py-2 text-left">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y">
        <?php foreach ($pos as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-mono text-xs"><?= sanitize($r['po_number']) ?></td>
                <td class="px-4 py-2 text-xs"><?= $r['po_date'] ? date('d M Y', strtotime($r['po_date'])) : '—' ?></td>
                <td class="px-4 py-2"><?= sanitize($r['vendor_name']) ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($r['company_name']) ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($r['department_name'] ?? '—') ?></td>
                <td class="px-4 py-2 text-right font-mono"><?= formatMoney((float)$r['grand_total']) ?></td>
                <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs font-semibold bg-gray-100"><?= strtoupper(str_replace('_', ' ', $r['status'])) ?></span></td>
            </tr>
        <?php endforeach; if (empty($pos)): ?>
            <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No POs found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../includes/footer.php'; ?>
