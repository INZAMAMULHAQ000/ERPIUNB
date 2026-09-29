<?php
/** P4 � Receipt Return / Rejection */
$pageTitle = 'Receipt Return';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../helpers/validation.php';
require_once '../../../../finance/helpers/finance_helpers.php';
require_once __DIR__ . '/../../ProcurementService.php';
require_once __DIR__ . '/../../ReceiptService.php';

if (!hasPermission('procurement.receipt.reject')) die('Access Denied');
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$gr = ReceiptService::getGR($db, $id);
if (!$gr) { flash('error', 'Receipt not found.'); to('modules/procurement/receipts/list/index.php'); }

$items = ReceiptService::getGRItems($db, $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid request.'); to("return/index.php?id=$id"); }

    $reason = trim($_POST['reason'] ?? '');
    if ($reason === '') { flash('error', 'Rejection reason is REQUIRED.'); to("return/index.php?id=$id"); }

    $rejectionData = [
        'gr_id' => $id,
        'gr_item_id' => null,
        'quantity' => (float)($_POST['quantity'] ?? 0),
        'reason' => $reason,
        'inspection_result' => $_POST['inspection_result'] ?? 'failed',
        'notes' => trim($_POST['notes'] ?? ''),
        'supporting_document' => trim($_POST['supporting_document'] ?? ''),
    ];

    try {
        $db->beginTransaction();
        $ins = $db->prepare("INSERT INTO goods_receipt_rejections
            (gr_id, gr_item_id, quantity, reason, inspection_result, notes, supporting_document, created_by)
            VALUES (?,?,?,?,?,?,?,?)");
        $ins->execute([
            $id, null, $rejectionData['quantity'], $rejectionData['reason'],
            $rejectionData['inspection_result'], $rejectionData['notes'],
            $rejectionData['supporting_document'], $_SESSION['user_id'],
        ]);

        ReceiptService::log($db, 'receipt_rejected', $id, "Returned qty: " . $rejectionData['quantity'], $reason, $_SESSION['user_id']);
        logAudit($_SESSION['user_id'], 'Returned goods from receipt ' . $gr['gr_number'], 'procurement');
        $db->commit();
        flash('success', 'Return recorded successfully.');
        to('modules/procurement/receipts/view/index.php?id=' . $id);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        flash('error', 'Failed: ' . $e->getMessage());
    }
}
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Receipt Return / Rejection</h1>
        <p class="text-gray-500 text-sm"><?= sanitize($gr['gr_number']) ?> � <?= sanitize($gr['vendor_name']) ?></p>
    </div>
    <a href="../view/index.php?id=<?= $id ?>" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">? Back</a>
</div>

<div class="bg-white rounded-lg shadow p-5">
    <form method="post" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-xs text-gray-500 block mb-1">Quantity to Return <span class="text-red-600">*</span></labe>
                <input type="number" step="0.001" name="quantity" required class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Inspection Result</label>
                <select name="inspection_result" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="failed">Failed</option>
                    <option value="partially_passed">Partially Passed</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
        </div>

        <div>
            <label class="text-xs text-gray-500 block mb-1">Rejection Reason <span class="text-red-600">*</span></labe>
            <textarea name="reason" rows="3" required class="w-full border rounded px-3 py-2 text-sm"></textarea>
        </div>

        <div>
            <label class="text-xs text-gray-500 block mb-1">Notes</label>
            <textarea name="notes" rows="2" class="w-full border rounded px-3 py-2 text-sm"></textarea>
        </div>

        <div>
            <label class="text-xs text-gray-500 block mb-1">Supporting Document (path/URL)</label>
            <input type="text" name="supporting_document" class="w-full border rounded px-3 py-2 text-sm">
        </div>

        <div class="flex gap-2">
            <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded hover:bg-red-700">Record Return</button>
            <a href="../view/index.php?id=<?= $id ?>" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Cancel</a>
        </div>
    </form>
</div>



