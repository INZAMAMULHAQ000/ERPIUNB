<?php
/** P4 — Create Service Receipt */
$pageTitle = 'Create Service Receipt';
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

// Get POs with service-type items eligible for receiving
$eligiblePOs = $db->prepare("
    SELECT DISTINCT po.id, po.po_number, v.vendor_name, po.status
    FROM purchase_orders po
    JOIN vendors v ON v.id=po.vendor_id
    JOIN purchase_order_items poi ON poi.po_id=po.id
    WHERE po.status IN ('approved','sent','acknowledged','partially_received')
    AND poi.item_type IN ('service','expense','asset')
    ORDER BY po.po_number DESC
");
$eligiblePOs->execute();
$eligiblePOs = $eligiblePOs->fetchAll();

$number = ReceiptService::nextSRNumber($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid request token.'; }

    $header = [
        'po_id' => (int)($_POST['po_id'] ?? 0),
        'vendor_id' => (int)($_POST['vendor_id'] ?? 0),
        'company_id' => (int)($_POST['company_id'] ?? 0),
        'receipt_date' => trim($_POST['receipt_date'] ?? ''),
        'service_description' => trim($_POST['service_description'] ?? ''),
        'service_period_start' => trim($_POST['service_period_start'] ?? ''),
        'service_period_end' => trim($_POST['service_period_end'] ?? ''),
        'completion_date' => trim($_POST['completion_date'] ?? ''),
        'ordered_quantity' => (float)($_POST['ordered_quantity'] ?? 0),
        'completed_quantity' => (float)($_POST['completed_quantity'] ?? 0),
        'accepted_quantity' => (float)($_POST['accepted_quantity'] ?? 0),
        'rejected_quantity' => (float)($_POST['rejected_quantity'] ?? 0),
        'project_id' => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
        'cost_center_id' => !empty($_POST['cost_center_id']) ? (int)$_POST['cost_center_id'] : null,
        'received_by' => (int)($_POST['received_by'] ?? $UID),
        'notes' => trim($_POST['notes'] ?? ''),
    ];
    $lines = $_POST['items'] ?? [];

    if ((int)$header['po_id'] <= 0) $errors[] = 'Please select a Purchase Order.';
    if (empty($header['receipt_date'])) $errors[] = 'Receipt date is required.';
    if (!count($lines)) $errors[] = 'At least one item is required.';

    if (!empty($errors)) { saveFormState($errors); to('modules/procurement/receipts/create/service.php' . ($pre_po_id ? '?po_id=' . $pre_po_id : '')); }

    $res = ReceiptService::createSR($db, $header, $lines, $UID);
    if (!empty($res['success'])) {
        flash('success', 'Service Receipt ' . $res['number'] . ' created.');
        to('modules/procurement/receipts/view/index.php?id=' . $res['id']); // reuse view or create sr view
    }
    foreach (($res['errors'] ?? [$res['message'] ?? 'Create failed.']) as $e) flash('error', $e);
}
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Create Service Receipt</h1>
        <p class="text-gray-500 text-sm">Record service completion against a purchase order</p>
    </div>
    <a href="../list/index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">← Back</a>
</div>

<?php if (empty($eligiblePOs)): ?>
<div class="bg-white rounded-lg shadow p-10 text-center text-gray-400">
    <i class="fas fa-inbox text-4xl mb-3"></i>
    <p>No service-type purchase orders available.</p>
</div>
<?php else: ?>
<form method="post" class="space-y-5">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold mb-3">Select Purchase Order</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <select name="po_id" id="poSelect" required class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">— Select PO —</option>
                    <?php foreach ($eligiblePOs as $po): ?>
                    <option value="<?= $po['id'] ?>" <?= $pre_po_id == $po['id'] ? 'selected' : '' ?>>
                        <?= sanitize($po['po_number']) ?> · <?= sanitize($po['vendor_name']) ?>
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

    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold mb-3">Service Details</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="text-xs text-gray-500 block mb-1">Service Description</label>
                <textarea name="service_description" rows="2" class="w-full border rounded px-3 py-2 text-sm"></textarea>
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Service Period Start</label>
                <input type="date" name="service_period_start" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Service Period End</label>
                <input type="date" name="service_period_end" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Completion Date</label>
                <input type="date" name="completion_date" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Ordered Qty</label>
                <input type="number" step="0.001" name="ordered_quantity" value="1" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Completed Qty</label>
                <input type="number" step="0.001" name="completed_quantity" value="1" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Accepted Qty</label>
                <input type="number" step="0.001" name="accepted_quantity" value="1" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Rejected Qty</label>
                <input type="number" step="0.001" name="rejected_quantity" value="0" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Project</label>
                <select name="project_id" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">— None —</option>
                    <?php foreach ($db->query("SELECT id, project_name FROM projects ORDER BY project_name")->fetchAll() as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= sanitize($p['project_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Cost Center</label>
                <select name="cost_center_id" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">— None —</option>
                    <?php foreach ($db->query("SELECT id, cost_center_name FROM cost_centers ORDER BY cost_center_name")->fetchAll() as $cc): ?>
                    <option value="<?= $cc['id'] ?>"><?= sanitize($cc['cost_center_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Received By</label>
                <select name="received_by" class="w-full border rounded px-3 py-2 text-sm">
                    <?php foreach ($db->query("SELECT id, name FROM users ORDER BY name")->fetchAll() as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $u['id'] == $UID ? 'selected' : '' ?>><?= sanitize($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-4">
                <label class="text-xs text-gray-500 block mb-1">Notes</label>
                <textarea name="notes" rows="2" class="w-full border rounded px-3 py-2 text-sm"></textarea>
            </div>
        </div>
    </div>

    <div class="flex gap-2">
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Create Service Receipt</button>
        <a href="../list/index.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Cancel</a>
    </div>
</form>
<?php endif; ?>
