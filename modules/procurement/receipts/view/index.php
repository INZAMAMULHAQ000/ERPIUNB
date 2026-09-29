<?php
/** P4 — Receipt Detail View (Goods Receipt + Service Receipt) */
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../finance/helpers/finance_helpers.php';
require_once __DIR__ . '/../../ProcurementService.php';
require_once __DIR__ . '/../../ReceiptService.php';

if (!hasPermission('procurement.receipt.view')) die('Access Denied');
$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$gr = ReceiptService::getGR($db, $id);
$isServiceReceipt = false;

if (!$gr) {
    $sr = ReceiptService::getSR($db, $id);
    if (!$sr) {
        flash('error', 'Receipt not found.');
        to('modules/procurement/receipts/list/index.php');
    }
    $isServiceReceipt = true;
    $pageTitle = 'Service Receipt';
} else {
    $pageTitle = 'Goods Receipt';
}

if ($isServiceReceipt) {
    $items = ReceiptService::getSRItems($db, $id);
    $rejections = [];
    $damages = [];
    $inspections = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finalize_sr') {
        if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
            flash('error', 'Invalid request token.');
            to('modules/procurement/receipts/view/index.php?id=' . $id);
        }
        $res = ReceiptService::finalizeSR($db, $id, $_SESSION['user_id']);
        if (!empty($res['success'])) {
            flash('success', 'Service Receipt ' . $sr['sr_number'] . ' submitted.');
        } else {
            flash('error', $res['message'] ?? 'Finalize failed.');
        }
        to('modules/procurement/receipts/view/index.php?id=' . $id);
    }
} else {
    $items = ReceiptService::getGRItems($db, $id);
    $inspections = $db->prepare("SELECT * FROM goods_receipt_inspections WHERE gr_id=? ORDER BY id");
    $inspections->execute([$id]);
    $inspections = $inspections->fetchAll();
    $rejections = $db->prepare("SELECT * FROM goods_receipt_rejections WHERE gr_id=? ORDER BY id");
    $rejections->execute([$id]);
    $rejections = $rejections->fetchAll();
    $damages = $db->prepare("SELECT * FROM goods_receipt_damages WHERE gr_id=? ORDER BY id");
    $damages->execute([$id]);
    $damages = $damages->fetchAll();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finalize') {
        if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
            flash('error', 'Invalid request token.');
            to('modules/procurement/receipts/view/index.php?id=' . $id);
        }
        $res = ReceiptService::finalizeGR($db, $id, $_SESSION['user_id']);
        if (!empty($res['success'])) {
            flash('success', 'Goods Receipt ' . $gr['gr_number'] . ' finalized as ' . ($res['status'] ?? 'accepted') . '.');
        } else {
            flash('error', $res['message'] ?? 'Finalize failed.');
        }
        to('modules/procurement/receipts/view/index.php?id=' . $id);
    }
}

$audit = $db->prepare("SELECT a.*, u.name user_name FROM procurement_audit a LEFT JOIN users u ON u.id=a.user_id WHERE pr_id=? ORDER BY a.created_at DESC, a.id DESC");
$audit->execute([$id]);
$audit = $audit->fetchAll();

$projects = array_column($db->query("SELECT id, project_name FROM projects ORDER BY project_name")->fetchAll(PDO::FETCH_UNIQUE), null, 'id') ?: [];
$costCenters = array_column($db->query("SELECT id, cost_center_name FROM cost_centers ORDER BY cost_center_name")->fetchAll(PDO::FETCH_UNIQUE), null, 'id') ?: [];

$statusStyles = [
    'draft'=>'bg-gray-100 text-gray-600','pending_inspection'=>'bg-yellow-100 text-yellow-700',
    'pending_acceptance'=>'bg-blue-100 text-blue-700','partially_accepted'=>'bg-orange-100 text-orange-700',
    'accepted'=>'bg-green-100 text-green-700','rejected'=>'bg-red-100 text-red-700','cancelled'=>'bg-purple-100 text-purple-700',
];
?>
<?php if ($isServiceReceipt): ?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= sanitize($sr['sr_number']) ?></h1>
        <p class="font-mono text-sm text-gray-500">
            <?= sanitize($sr['vendor_name']) ?> · <?= sanitize($sr['po_number']) ?>
            <span class="ml-2 px-2 py-0.5 rounded text-xs font-semibold <?= $statusStyles[$sr['status']] ?? '' ?>">
                <?= strtoupper(str_replace('_', ' ', $sr['status'])) ?>
            </span>
        </p>
    </div>
    <div class="flex gap-2">
        <a href="../list/index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">&larr; Back</a>
        <?php if (in_array($sr['status'], ['draft'], true) && hasPermission('procurement.receipt.submit')): ?>
        <form method="post" class="inline" onsubmit="return confirm('Submit this service receipt?');">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="finalize_sr">
            <button type="submit" class="bg-green-600 text-white px-3 py-2 rounded hover:bg-green-700 text-sm">Finalize</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Header Info -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 text-sm">
        <h3 class="font-semibold mb-2">Receipt Details</h3>
        <p><span class="text-gray-500">Date:</span> <?= $sr['receipt_date'] ? date('d M Y', strtotime($sr['receipt_date'])) : '—' ?></p>
        <p><span class="text-gray-500">Received By:</span> <?= sanitize($sr['received_by_name'] ?? '—') ?></p>
        <p><span class="text-gray-500">PO Number:</span> <?= sanitize($sr['po_number'] ?? '—') ?></p>
        <p><span class="text-gray-500">Vendor:</span> <?= sanitize($sr['vendor_name'] ?? '—') ?></p>
        <p><span class="text-gray-500">Company:</span> <?= sanitize($sr['company_name'] ?? '—') ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-sm">
        <h3 class="font-semibold mb-2">Service Details</h3>
        <p><span class="text-gray-500">Description:</span> <?= sanitize($sr['service_description'] ?? '—') ?></p>
        <p><span class="text-gray-500">Period:</span> <?= $sr['service_period_start'] ? date('d M Y', strtotime($sr['service_period_start'])) : '—' ?> — <?= $sr['service_period_end'] ? date('d M Y', strtotime($sr['service_period_end'])) : '—' ?></p>
        <p><span class="text-gray-500">Completion Date:</span> <?= $sr['completion_date'] ? date('d M Y', strtotime($sr['completion_date'])) : '—' ?></p>
        <p><span class="text-gray-500">Project:</span> <?= sanitize($projects[$sr['project_id']]['project_name'] ?? ($sr['project_id'] ?? '—')) ?></p>
        <p><span class="text-gray-500">Cost Center:</span> <?= sanitize($costCenters[$sr['cost_center_id']]['cost_center_name'] ?? ($sr['cost_center_id'] ?? '—')) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-sm">
        <h3 class="font-semibold mb-2">Quantities</h3>
        <p><span class="text-gray-500">Ordered:</span> <?= number_format((float)$sr['ordered_quantity'], 3) ?></p>
        <p><span class="text-gray-500">Completed:</span> <?= number_format((float)$sr['completed_quantity'], 3) ?></p>
        <p><span class="text-gray-500">Accepted:</span> <?= number_format((float)$sr['accepted_quantity'], 3) ?></p>
        <p><span class="text-gray-500">Rejected:</span> <?= number_format((float)$sr['rejected_quantity'], 3) ?></p>
    </div>
</div>

<!-- Items -->
<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <div class="px-5 pt-4"><h3 class="font-semibold">Service Receipt Items</h3></div>
    <table class="w-full text-sm mt-2">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="px-4 py-2 text-left">Item</th>
                <th class="px-4 py-2 text-left">Description</th>
                <th class="px-4 py-2 text-right">Ordered</th>
                <th class="px-4 py-2 text-right">Completed</th>
                <th class="px-4 py-2 text-right">Accepted</th>
                <th class="px-4 py-2 text-right">Rejected</th>
                <th class="px-4 py-2 text-left">Unit</th>
                <th class="px-4 py-2 text-right">Unit Price</th>
                <th class="px-4 py-2 text-left">Project</th>
                <th class="px-4 py-2 text-left">Cost Center</th>
                <th class="px-4 py-2 text-left">Notes</th>
            </tr>
        </thead>
        <tbody class="divide-y">
        <?php foreach ($items as $it): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2"><?= sanitize($it['item_name']) ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($it['description'] ?? '—') ?></td>
                <td class="px-4 py-2 text-right text-xs"><?= $it['ordered_quantity'] ?></td>
                <td class="px-4 py-2 text-right text-xs"><?= $it['completed_quantity'] ?></td>
                <td class="px-4 py-2 text-right text-green-600"><?= $it['accepted_quantity'] ?></td>
                <td class="px-4 py-2 text-right text-red-600"><?= $it['rejected_quantity'] ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($it['unit'] ?? '—') ?></td>
                <td class="px-4 py-2 text-right text-xs"><?= number_format((float)$it['unit_price'], 4) ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($projects[$it['project_id']]['project_name'] ?? ($it['project_id'] ?? '—')) ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($costCenters[$it['cost_center_id']]['cost_center_name'] ?? ($it['cost_center_id'] ?? '—')) ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($it['notes'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (!empty($sr['notes'])): ?>
<div class="bg-white rounded-lg shadow p-4 mb-6 text-sm">
    <h3 class="font-semibold mb-2">Notes</h3>
    <p class="text-gray-700 whitespace-pre-wrap"><?= sanitize($sr['notes']) ?></p>
</div>
<?php endif; ?>

<?php else: ?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= sanitize($gr['gr_number']) ?></h1>
        <p class="font-mono text-sm text-gray-500">
            <?= sanitize($gr['vendor_name']) ?> · <?= sanitize($gr['po_number']) ?>
            <span class="ml-2 px-2 py-0.5 rounded text-xs font-semibold <?= $statusStyles[$gr['status']] ?? '' ?>">
                <?= strtoupper(str_replace('_', ' ', $gr['status'])) ?>
            </span>
        </p>
    </div>
    <div class="flex gap-2">
        <a href="../list/index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">&larr; Back</a>
        <?php if (in_array($gr['status'], ['draft'], true) && hasPermission('procurement.receipt.edit')): ?>
        <a href="../edit/index.php?id=<?= $id ?>" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Edit</a>
        <?php endif; ?>
        <?php if (in_array($gr['status'], ['draft'], true) && hasPermission('procurement.receipt.submit')): ?>
        <form method="post" class="inline">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="finalize">
            <button type="submit" class="bg-green-600 text-white px-3 py-2 rounded hover:bg-green-700 text-sm">Finalize</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Header Info -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 text-sm">
        <h3 class="font-semibold mb-2">Receipt Details</h3>
        <p><span class="text-gray-500">Date:</span> <?= $gr['receipt_date'] ? date('d M Y', strtotime($gr['receipt_date'])) : '—' ?></p>
        <p><span class="text-gray-500">Received By:</span> <?= sanitize($gr['received_by_name'] ?? '—') ?></p>
        <p><span class="text-gray-500">Delivery Note:</span> <?= sanitize($gr['delivery_note_number'] ?? '—') ?></p>
        <p><span class="text-gray-500">Vendor Delivery Date:</span> <?= $gr['vendor_delivery_date'] ? date('d M Y', strtotime($gr['vendor_delivery_date'])) : '—' ?></p>
        <p><span class="text-gray-500">Inspection Required:</span> <?= $gr['inspection_required'] ? 'Yes' : 'No' ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-sm">
        <h3 class="font-semibold mb-2">Quantities</h3>
        <p><span class="text-gray-500">Received:</span> <?= number_format((float)$gr['received_quantity_total'], 3) ?></p>
        <p><span class="text-gray-500">Accepted:</span> <?= number_format((float)$gr['accepted_quantity_total'], 3) ?></p>
        <p><span class="text-gray-500">Rejected:</span> <?= number_format((float)$gr['rejected_quantity_total'], 3) ?></p>
        <p><span class="text-gray-500">Damaged:</span> <?= number_format((float)$gr['damaged_quantity_total'], 3) ?></p>
        <p><span class="text-gray-500">Short:</span> <?= number_format((float)$gr['short_quantity_total'], 3) ?></p>
        <p><span class="text-gray-500">&larr; Backorder:</span> <?= number_format((float)$gr['backorder_quantity_total'], 3) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-sm">
        <h3 class="font-semibold mb-2">Inspection</h3>
        <p><span class="text-gray-500">Status:</span> <?= strtoupper(str_replace('_', ' ', $gr['inspection_status'])) ?></p>
        <p><span class="text-gray-500">Inspected By:</span> <?= sanitize($gr['inspected_by'] ? 'User #' . $gr['inspected_by'] : '—') ?></p>
        <p><span class="text-gray-500">Date:</span> <?= $gr['inspection_date'] ? date('d M Y H:i', strtotime($gr['inspection_date'])) : '—' ?></p>
    </div>
</div>

<!-- Items -->
<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <div class="px-5 pt-4"><h3 class="font-semibold">Receipt Items</h3></div>
    <table class="w-full text-sm mt-2">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="px-4 py-2 text-left">Item</th>
                <th class="px-4 py-2 text-right">Ordered</th>
                <th class="px-4 py-2 text-right">Prev Received</th>
                <th class="px-4 py-2 text-right">Remaining</th>
                <th class="px-4 py-2 text-right">Received</th>
                <th class="px-4 py-2 text-right">Accepted</th>
                <th class="px-4 py-2 text-right">Rejected</th>
                <th class="px-4 py-2 text-right">Damaged</th>
                <th class="px-4 py-2 text-left">Batch</th>
                <th class="px-4 py-2 text-left">Serial</th>
                <th class="px-4 py-2 text-left">Notes</th>
            </tr>
        </thead>
        <tbody class="divide-y">
        <?php foreach ($items as $it): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2"><?= sanitize($it['item_name']) ?></td>
                <td class="px-4 py-2 text-right text-xs"><?= $it['ordered_quantity'] ?></td>
                <td class="px-4 py-2 text-right text-xs"><?= $it['previously_received_quantity'] ?></td>
                <td class="px-4 py-2 text-right text-xs"><?= $it['remaining_quantity'] ?></td>
                <td class="px-4 py-2 text-right font-semibold"><?= $it['received_quantity'] ?></td>
                <td class="px-4 py-2 text-right text-green-600"><?= $it['accepted_quantity'] ?></td>
                <td class="px-4 py-2 text-right text-red-600"><?= $it['rejected_quantity'] ?></td>
                <td class="px-4 py-2 text-right text-orange-600"><?= $it['damaged_quantity'] ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($it['batch_number'] ?? '—') ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($it['serial_number'] ?? '—') ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($it['notes'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Rejections -->
<?php if (!empty($rejections)): ?>
<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <div class="px-5 pt-4"><h3 class="font-semibold text-red-700">Rejections</h3></div>
    <table class="w-full text-sm mt-2">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr><th class="px-4 py-2 text-left">Qty</th><th class="px-4 py-2 text-left">Reason</th><th class="px-4 py-2 text-left">Result</th><th class="px-4 py-2 text-left">Notes</th></tr>
        </thead>
        <tbody class="divide-y">
        <?php foreach ($rejections as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2"><?= $r['quantity'] ?></td>
                <td class="px-4 py-2"><?= sanitize($r['reason']) ?></td>
                <td class="px-4 py-2"><?= strtoupper(str_replace('_', ' ', $r['inspection_result'])) ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($r['notes'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Damages -->
<?php if (!empty($damages)): ?>
<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <div class="px-5 pt-4"><h3 class="font-semibold text-orange-700">Damages</h3></div>
    <table class="w-full text-sm mt-2">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr><th class="px-4 py-2 text-left">Qty</th><th class="px-4 py-2 text-left">Type</th><th class="px-4 py-2 text-left">Reason</th><th class="px-4 py-2 text-left">Notes</th></tr>
        </thead>
        <tbody class="divide-y">
        <?php foreach ($damages as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2"><?= $r['quantity'] ?></td>
                <td class="px-4 py-2"><?= sanitize($r['damage_type'] ?? '—') ?></td>
                <td class="px-4 py-2"><?= sanitize($r['damage_reason'] ?? '—') ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($r['notes'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Audit History -->
<?php if (!empty($audit)): ?>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <div class="px-5 pt-4"><h3 class="font-semibold">Audit History</h3></div>
    <table class="w-full text-sm mt-2">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr><th class="px-4 py-2 text-left">Action</th><th class="px-4 py-2 text-left">User</th><th class="px-4 py-2 text-left">Date</th></tr>
        </thead>
        <tbody class="divide-y">
        <?php foreach ($audit as $a): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2"><?= sanitize($a['action']) ?></td>
                <td class="px-4 py-2"><?= sanitize($a['user_name'] ?? '—') ?></td>
                <td class="px-4 py-2 text-xs"><?= $a['created_at'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
