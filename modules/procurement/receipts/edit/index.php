<?php
/** P4 — Edit Goods Receipt (draft only) */
$pageTitle = 'Edit Goods Receipt';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../helpers/validation.php';
require_once '../../../../finance/helpers/finance_helpers.php';
require_once __DIR__ . '/../../ProcurementService.php';
require_once __DIR__ . '/../../ReceiptService.php';

if (!hasPermission('procurement.receipt.edit')) die('Access Denied');
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$gr = ReceiptService::getGR($db, $id);
if (!$gr) { flash('error', 'Receipt not found.'); to('modules/procurement/receipts/list/index.php'); }
if (!in_array($gr['status'], ['draft'], true)) { flash('error', 'Only draft receipts can be edited.'); to('modules/procurement/receipts/view/index.php?id=' . $id); }

$errors = restoreFormErrors();
if (empty($errors)) { clearOldInput(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid request token.'; }

    $header = [
        'po_id' => (int)$_POST['po_id'],
        'vendor_id' => (int)$_POST['vendor_id'],
        'company_id' => (int)$_POST['company_id'],
        'warehouse_id' => !empty($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : null,
        'location_id' => !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null,
        'receipt_date' => trim($_POST['receipt_date'] ?? ''),
        'delivery_note_number' => trim($_POST['delivery_note_number'] ?? ''),
        'vendor_delivery_date' => trim($_POST['vendor_delivery_date'] ?? ''),
        'received_by' => (int)$_POST['received_by'],
        'inspection_required' => !empty($_POST['inspection_required']) ? 1 : 0,
        'notes' => trim($_POST['notes'] ?? ''),
    ];
    $lines = $_POST['items'] ?? [];

    if ((int)$header['po_id'] <= 0) $errors[] = 'Purchase Order is required.';
    if (empty($header['receipt_date'])) $errors[] = 'Receipt date is required.';
    $errs = ReceiptService::validateGRLines($db, $lines, $header['po_id']);
    $errors = array_merge($errors, $errs);

    if (!empty($errors)) { saveFormState($errors); to('modules/procurement/receipts/edit/index.php?id=' . $id); }

    try {
        $db->beginTransaction();
        $db->prepare("UPDATE goods_receipts SET po_id=?, vendor_id=?, company_id=?, warehouse_id=?, location_id=?,
            receipt_date=?, delivery_note_number=?, vendor_delivery_date=?, received_by=?,
            inspection_required=?, notes=?, updated_at=NOW() WHERE id=?")
           ->execute([
               $header['po_id'], $header['vendor_id'], $header['company_id'],
               $header['warehouse_id'], $header['location_id'], $header['receipt_date'],
               $header['delivery_note_number'] ?: null, $header['vendor_delivery_date'] ?: null,
               $header['received_by'], $header['inspection_required'] ? 1 : 0,
               $header['notes'] ?: null, $id,
           ]);

        // Replace items
        $db->prepare("DELETE FROM goods_receipt_items WHERE gr_id=?")->execute([$id]);
        ReceiptService::saveGRLines($db, $id, $header['po_id'], $lines);

        ReceiptService::log($db, 'receipt_edited', $id, null, null, $_SESSION['user_id']);
        logAudit($_SESSION['user_id'], 'Edited goods receipt ' . $gr['gr_number'], 'procurement');
        $db->commit();
        flash('success', 'Receipt updated.');
        to('modules/procurement/receipts/view/index.php?id=' . $id);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $errors[] = 'Update failed: ' . $e->getMessage();
        saveFormState($errors);
    }
}

// Load existing items for form
$existingItems = ReceiptService::getGRItems($db, $id);
$eligiblePOs = $db->prepare("
    SELECT po.id, po.po_number, v.vendor_name
    FROM purchase_orders po
    JOIN vendors v ON v.id=po.vendor_id
    WHERE po.status IN ('approved','sent','acknowledged','partially_received')
    ORDER BY po.po_number DESC
");
$eligiblePOs->execute();
$eligiblePOs = $eligiblePOs->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Edit Goods Receipt</h1>
        <p class="text-gray-500 text-sm"><?= sanitize($gr['gr_number']) ?></p>
    </div>
    <a href="../view/index.php?id=<?= $id ?>" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">← Back</a>
</div>

<form method="post" class="space-y-5">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold mb-3">Receipt Details</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="text-xs text-gray-500 block mb-1">Purchase Order <span class="text-red-600">*</span></labe>
                <select name="po_id" id="poSelect" required class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">— Select PO —</option>
                    <?php foreach ($eligiblePOs as $po): ?>
                    <option value="<?= $po['id'] ?>" <?= $gr['po_id'] == $po['id'] ? 'selected' : '' ?>>
                        <?= sanitize($po['po_number']) ?> · <?= sanitize($po['vendor_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Receipt Date <span class="text-red-600">*</span></labe>
                <input type="date" name="receipt_date" value="<?= $gr['receipt_date'] ?>" required class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Delivery Note #</label>
                <input type="text" name="delivery_note_number" value="<?= sanitize($gr['delivery_note_number'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Vendor Delivery Date</label>
                <input type="date" name="vendor_delivery_date" value="<?= $gr['vendor_delivery_date'] ?>" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Received By</label>
                <select name="received_by" class="w-full border rounded px-3 py-2 text-sm">
                    <?php foreach ($db->query("SELECT id, name FROM users ORDER BY name")->fetchAll() as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $u['id'] == $gr['received_by'] ? 'selected' : '' ?>><?= sanitize($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-center">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="inspection_required" value="1" class="rounded" <?= $gr['inspection_required'] ? 'checked' : '' ?>>
                    Inspection Required
                </label>
            </div>
            <div class="md:col-span-2">
                <label class="text-xs text-gray-500 block mb-1">Notes</label>
                <textarea name="notes" rows="2" class="w-full border rounded px-3 py-2 text-sm"><?= sanitize($gr['notes'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <div class="flex gap-2">
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Update Receipt</button>
        <a href="../view/index.php?id=<?= $id ?>" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Cancel</a>
    </div>
</form>
