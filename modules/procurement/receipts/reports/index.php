<?php
/** P4 — Receiving Reports */
$pageTitle = 'Receiving Reports';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../finance/helpers/finance_helpers.php';
require_once __DIR__ . '/../../ProcurementService.php';
require_once __DIR__ . '/../../ReceiptService.php';

if (!hasPermission('procurement.receipt.report')) die('Access Denied');
$db = getDB();

$report = $_GET['report'] ?? 'gr_register';
$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-t');
$status = $_GET['status'] ?? '';
$poId = !empty($_GET['po_id']) ? (int)$_GET['po_id'] : 0;

function badge($s) {
    $map = [
        'draft'=>'bg-gray-100 text-gray-600','pending_inspection'=>'bg-yellow-100 text-yellow-700',
        'partially_accepted'=>'bg-orange-100 text-orange-700','accepted'=>'bg-green-100 text-green-700',
        'rejected'=>'bg-red-100 text-red-700','cancelled'=>'bg-purple-100 text-purple-700',
    ];
    return '<span class="px-2 py-0.5 rounded text-xs font-semibold ' . ($map[$s] ?? 'bg-gray-100') . '">' . strtoupper(str_replace('_', ' ', $s)) . '</span>';
}
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Receiving Reports</h1>
    <p class="text-gray-500 text-sm">Goods and service receipt reporting</p>
</div>

<div class="bg-white rounded-lg shadow p-5 mb-6">
    <form method="get" class="flex flex-wrap gap-3 items-end">
        <input type="hidden" name="report" value="<?= sanitize($report) ?>">
        <div>
            <label class="text-xs text-gray-500 block mb-1">From Date</label>
            <input type="date" name="from_date" value="<?= sanitize($fromDate) ?>" class="border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">To Date</label>
            <input type="date" name="to_date" value="<?= sanitize($toDate) ?>" class="border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Status</label>
            <select name="status" class="border rounded px-3 py-2 text-sm">
                <option value="">All</option>
                <?php foreach (ReceiptService::GR_STATUSES as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= strtoupper(str_replace('_', ' ', $s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">PO ID</label>
            <input type="number" name="po_id" value="<?= $poId ?>" class="border rounded px-3 py-2 text-sm">
        </div>
        <button class="bg-gray-800 text-white px-4 py-2 rounded text-sm">Run</button>
    </form>
</div>

<?php
$where = ["gr.receipt_date BETWEEN ? AND ?"];
$params = [$fromDate, $toDate];
if ($status) { $where[] = "gr.status=?"; $params[] = $status; }
if ($poId) { $where[] = "gr.po_id=?"; $params[] = $poId; }

$sql = "SELECT gr.*, v.vendor_name, po.po_number, u.name received_by_name
        FROM goods_receipts gr
        LEFT JOIN vendors v ON v.id=gr.vendor_id
        LEFT JOIN purchase_orders po ON po.id=gr.po_id
        LEFT JOIN users u ON u.id=gr.received_by
        WHERE " . implode(' AND ', $where) . "
        ORDER BY gr.receipt_date DESC, gr.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="px-4 py-3 text-left">GR Number</th>
                <th class="px-4 py-3 text-left">Date</th>
                <th class="px-4 py-3 text-left">PO</th>
                <th class="px-4 py-3 text-left">Vendor</th>
                <th class="px-4 py-3 text-right">Received</th>
                <th class="px-4 py-3 text-right">Accepted</th>
                <th class="px-4 py-3 text-right">Rejected</th>
                <th class="px-4 py-3 text-right">Damaged</th>
                <th class="px-4 py-3 text-left">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y">
        <?php foreach ($rows as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-mono text-xs"><?= sanitize($r['gr_number']) ?></td>
                <td class="px-4 py-2 text-xs"><?= $r['receipt_date'] ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($r['po_number']) ?></td>
                <td class="px-4 py-2"><?= sanitize($r['vendor_name']) ?></td>
                <td class="px-4 py-2 text-right"><?= $r['received_quantity_total'] ?></td>
                <td class="px-4 py-2 text-right text-green-600"><?= $r['accepted_quantity_total'] ?></td>
                <td class="px-4 py-2 text-right text-red-600"><?= $r['rejected_quantity_total'] ?></td>
                <td class="px-4 py-2 text-right text-orange-600"><?= $r['damaged_quantity_total'] ?></td>
                <td class="px-4 py-2"><?= badge($r['status']) ?></td>
            </tr>
        <?php endforeach; if (empty($rows)) { ?>
            <tr><td colspan="9" class="px-4 py-10 text-center text-gray-400">No receipts found for the selected criteria.</td></tr>
        <?php } ?>
        </tbody>
    </table>
</div>
