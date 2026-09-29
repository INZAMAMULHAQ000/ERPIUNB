<?php
/** P4 � Create Goods Receipt from PO */
$pageTitle = 'Create Goods Receipt';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../helpers/validation.php';
require_once '../../../../finance/helpers/finance_helpers.php';
require_once __DIR__ . '/../../ProcurementService.php';
require_once __DIR__ . '/../../ReceiptService.php';

if (!hasPermission('procurement.receipt.create')) die('Access Denied');
$db = getDB();
$UID = $_SESSION['user_id'];
$errors = restoreFormErrors();
if (empty($errors)) { clearOldInput(); }

$pre_po_id = (int)($_GET['po_id'] ?? 0);

// Get POs eligible for receiving
$eligiblePOs = $db->prepare("
    SELECT po.id, po.po_number, v.vendor_name, po.status,
           (SELECT COUNT(*) FROM purchase_order_items WHERE po_id=po.id AND quantity > received_quantity) as items_pending
    FROM purchase_orders po
    JOIN vendors v ON v.id=po.vendor_id
    WHERE po.status IN ('approved','sent','acknowledged','partially_received')
    ORDER BY po.po_number DESC
");
$eligiblePOs->execute();
$eligiblePOs = $eligiblePOs->fetchAll();

// Generate GR number
$number = ReceiptService::nextGRNumber($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid request token.'; }

    $header = [
        'po_id' => (int)($_POST['po_id'] ?? 0),
        'vendor_id' => (int)($_POST['vendor_id'] ?? 0),
        'company_id' => (int)($_POST['company_id'] ?? 0),
        'warehouse_id' => !empty($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : null,
        'location_id' => !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null,
        'receipt_date' => trim($_POST['receipt_date'] ?? ''),
        'delivery_note_number' => trim($_POST['delivery_note_number'] ?? ''),
        'vendor_delivery_date' => trim($_POST['vendor_delivery_date'] ?? ''),
        'received_by' => (int)($_POST['received_by'] ?? $UID),
        'inspection_required' => !empty($_POST['inspection_required']) ? 1 : 0,
        'notes' => trim($_POST['notes'] ?? ''),
    ];
    $lines = $_POST['items'] ?? [];

    if ((int)$header['po_id'] <= 0) $errors[] = 'Please select a Purchase Order.';
    if (empty($header['receipt_date'])) $errors[] = 'Receipt date is required.';

    $errs = ReceiptService::validateGRLines($db, $lines, $header['po_id']);
    $errors = array_merge($errors, $errs);

    if (!empty($errors)) { saveFormState($errors); to('modules/procurement/receipts/create/index.php' . ($pre_po_id ? '?po_id=' . $pre_po_id : '')); }

    $res = ReceiptService::createGR($db, $header, $lines, $UID);
    if (!empty($res['success'])) {
        flash('success', 'Goods Receipt ' . $res['number'] . ' created.');
        to('modules/procurement/receipts/view/index.php?id=' . $res['id']);
    }
    foreach (($res['errors'] ?? [$res['message'] ?? 'Create failed.']) as $e) flash('error', $e);
}
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Create Goods Receipt</h1>
        <p class="text-gray-500 text-sm">Record goods received against a purchase order</p>
    </div>
    <a href="../list/index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">? Back</a>
</div>

<?php if (empty($eligiblePOs)): ?>
<div class="bg-white rounded-lg shadow p-10 text-center text-gray-400">
    <i class="fas fa-inbox text-4xl mb-3"></i>
    <p>No purchase orders available for receiving.<br>Approved or sent POs will appear here.</p>
</div>
<?php else: ?>

<form method="post" class="space-y-5">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <!-- PO Selection -->
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold mb-3">Select Purchase Order</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <select name="po_id" id="poSelect" required class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">� Select PO �</option>
                    <?php foreach ($eligiblePOs as $po): ?>
                    <option value="<?= $po['id'] ?>" <?= $pre_po_id == $po['id'] ? 'selected' : '' ?>>
                        <?= sanitize($po['po_number']) ?> � <?= sanitize($po['vendor_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Receipt Date <span class="text-red-600">*</span></labe>
                <input type="date" name="receipt_date" value="<?= date('Y-m-d') ?>" required class="w-full border rounded px-3 py-2 text-sm">
            </div>
        </div>
    </div>

    <!-- Receipt Header Details -->
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold mb-3">Receipt Details</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="text-xs text-gray-500 block mb-1">Delivery Note #</label>
                <input type="text" name="delivery_note_number" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Vendor Delivery Date</label>
                <input type="date" name="vendor_delivery_date" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Received By</label>
                <select name="received_by" class="w-full border rounded px-3 py-2 text-sm">
                    <?php
                    $users = $db->query("SELECT id, name FROM users WHERE status='active' ORDER BY name")->fetchAll();
                    foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $u['id'] == $UID ? 'selected' : '' ?>><?= sanitize($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-center">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="inspection_required" value="1" class="rounded">
                    Inspection Required
                </label>
            </div>
            <div class="md:col-span-4">
                <label class="text-xs text-gray-500 block mb-1">Notes</label>
                <textarea name="notes" rows="2" class="w-full border rounded px-3 py-2 text-sm"></textarea>
            </div>
        </div>
    </div>

    <!-- Items -->
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold mb-3">Items</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="itemsTable">
                <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                    <tr>
                        <th class="px-3 py-2 text-left">Item</th>
                        <th class="px-3 py-2 text-right">Ordered</th>
                        <th class="px-3 py-2 text-right">Previously Received</th>
                        <th class="px-3 py-2 text-right">Remaining</th>
                        <th class="px-3 py-2 text-right">Received</th>
                        <th class="px-3 py-2 text-right">Accepted</th>
                        <th class="px-3 py-2 text-right">Rejected</th>
                        <th class="px-3 py-2 text-right">Damaged</th>
                        <th class="px-3 py-2 text-right">Short</th>
                        <th class="px-3 py-2 text-right">Backorder</th>
                        <th class="px-3 py-2 text-left">Batch</th>
                        <th class="px-3 py-2 text-left">Serial</th>
                        <th class="px-3 py-2 text-left">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y" id="itemsBody">
                    <tr><td colspan="13" class="px-4 py-6 text-center text-gray-400">Select a PO to load items.</td></tr>
                </tbody>
            </table>
        </div>
        <input type="hidden" name="items" id="itemsData">
    </div>

    <div class="flex gap-2">
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Create Goods Receipt</button>
        <a href="../list/index.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Cancel</a>
    </div>
</form>

<script>
document.getElementById('poSelect').addEventListener('change', function() {
    const poId = this.value;
    const tbody = document.getElementById('itemsBody');
    if (!poId) { tbody.innerHTML = '<tr><td colspan="13" class="px-4 py-6 text-center text-gray-400">Select a PO to load items.</td></tr>'; return; }
    fetch('../../../../modules/finance/purchase_orders/ajax_get_items.php?po_id=' + poId)
        .then(r => r.json())
        .then(items => {
            if (!items.length) { tbody.innerHTML = '<tr><td colspan="13" class="px-4 py-6 text-center text-gray-400">No items found.</td></tr>'; return; }
            let html = '';
            items.forEach((it, i) => {
                html += `<tr>
                    <td class="px-3 py-2">${sanitize(it.item_name)}</td>
                    <td class="px-3 py-2 text-right text-xs">${it.quantity}</td>
                    <td class="px-3 py-2 text-right text-xs">${it.received_quantity || 0}</td>
                    <td class="px-3 py-2 text-right text-xs">${(parseFloat(it.quantity) - parseFloat(it.received_quantity || 0)).toFixed(3)}</td>
                    <td><input type="number" step="0.001" name="items[${i}][received_quantity]" value="0" class="w-20 border rounded px-2 py-1 text-xs text-right item-qty"></td>
                    <td><input type="number" step="0.001" name="items[${i}][accepted_quantity]" value="0" class="w-20 border rounded px-2 py-1 text-xs text-right item-qty"></td>
                    <td><input type="number" step="0.001" name="items[${i}][rejected_quantity]" value="0" class="w-20 border rounded px-2 py-1 text-xs text-right item-qty"></td>
                    <td><input type="number" step="0.001" name="items[${i}][damaged_quantity]" value="0" class="w-20 border rounded px-2 py-1 text-xs text-right item-qty"></td>
                    <td><input type="number" step="0.001" name="items[${i}][short_quantity]" value="0" class="w-20 border rounded px-2 py-1 text-xs text-right item-qty"></td>
                    <td><input type="number" step="0.001" name="items[${i}][backorder_quantity]" value="0" class="w-20 border rounded px-2 py-1 text-xs text-right item-qty"></td>
                    <td><input type="text" name="items[${i}][batch_number]" class="w-24 border rounded px-2 py-1 text-xs"></td>
                    <td><input type="text" name="items[${i}][serial_number]" class="w-24 border rounded px-2 py-1 text-xs"></td>
                    <td><input type="text" name="items[${i}][notes]" class="w-32 border rounded px-2 py-1 text-xs"></td>
                    <input type="hidden" name="items[${i}][po_item_id]" value="${it.id}">
                    <input type="hidden" name="items[${i}][item_id]" value="${it.item_id || ''}">
                    <input type="hidden" name="items[${i}][item_name]" value="${sanitize(it.item_name)}">
                    <input type="hidden" name="items[${i}][item_type]" value="${it.item_type || 'non_stock'}">
                </tr>`;
            });
            tbody.innerHTML = html;
        });
});
</script>
<?php endif; ?>
