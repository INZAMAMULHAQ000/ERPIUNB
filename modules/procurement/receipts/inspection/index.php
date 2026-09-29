<?php
/** P4 — Goods Receipt Inspection */
$pageTitle = 'Receipt Inspection';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../finance/helpers/finance_helpers.php';
require_once __DIR__ . '/../../ProcurementService.php';
require_once __DIR__ . '/../../ReceiptService.php';

if (!hasPermission('procurement.receipt.inspect')) die('Access Denied');
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$gr = ReceiptService::getGR($db, $id);
if (!$gr) { flash('error', 'Receipt not found.'); to('modules/procurement/receipts/list/index.php'); }

$items = ReceiptService::getGRItems($db, $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid request.'); to("inspection/index.php?id=$id"); }

    $data = [
        'inspection_status' => $_POST['inspection_status'] ?? 'pending',
        'inspections' => [],
    ];
    foreach ($items as $it) {
        $prefix = 'item_' . $it['id'] . '_';
        $data['inspections'][] = [
            'gr_item_id' => $it['id'],
            'inspection_status' => $_POST[$prefix . 'status'] ?? 'pending',
            'accepted_quantity' => $_POST[$prefix . 'accepted'] ?? 0,
            'rejected_quantity' => $_POST[$prefix . 'rejected'] ?? 0,
            'reason' => $_POST[$prefix . 'reason'] ?? '',
            'comments' => $_POST[$prefix . 'comments'] ?? '',
        ];
    }

    $res = ReceiptService::updateInspection($db, $id, $data, $_SESSION['user_id']);
    flash($res['success'] ? 'success' : 'error', $res['message'] ?? 'Inspection saved.');
    to('modules/procurement/receipts/view/index.php?id=' . $id);
}

$existingInspections = $db->prepare("SELECT * FROM goods_receipt_inspections WHERE gr_id=?");
$existingInspections->execute([$id]);
$existingInspections = $existingInspections->fetchAll();
$inspMap = [];
foreach ($existingInspections as $ins) { $inspMap[$ins['gr_item_id']] = $ins; }
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Inspection: <?= sanitize($gr['gr_number']) ?></h1>
        <p class="text-gray-500 text-sm"><?= sanitize($gr['vendor_name']) ?> · <?= sanitize($gr['po_number']) ?></p>
    </div>
    <a href="../view/index.php?id=<?= $id ?>" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">← Back</a>
</div>

<form method="post" class="space-y-5">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold mb-3">Overall Inspection Status</h3>
        <select name="inspection_status" class="w-full border rounded px-3 py-2 text-sm">
            <?php foreach (ReceiptService::INSPECTION_STATUSES as $s): ?>
            <option value="<?= $s ?>" <?= ($gr['inspection_status'] ?? 'not_required') === $s ? 'selected' : '' ?>><?= strtoupper(str_replace('_', ' ', $s)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <div class="px-5 pt-4"><h3 class="font-semibold">Item Inspections</h3></div>
        <table class="w-full text-sm mt-2">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                <tr>
                    <th class="px-4 py-2 text-left">Item</th>
                    <th class="px-4 py-2 text-right">Received</th>
                    <th class="px-4 py-2 text-left">Status</th>
                    <th class="px-4 py-2 text-right">Accepted</th>
                    <th class="px-4 py-2 text-right">Rejected</th>
                    <th class="px-4 py-2 text-left">Reason</th>
                    <th class="px-4 py-2 text-left">Comments</th>
                </tr>
            </thead>
            <tbody class="divide-y">
            <?php foreach ($items as $it):
                $ins = $inspMap[$it['id']] ?? [];
            ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2"><?= sanitize($it['item_name']) ?></td>
                    <td class="px-4 py-2 text-right text-xs"><?= $it['received_quantity'] ?></td>
                    <td class="px-4 py-2">
                        <select name="item_<?= $it['id'] ?>_status" class="border rounded px-2 py-1 text-xs">
                            <?php foreach (ReceiptService::INSPECTION_STATUSES as $s): ?>
                            <option value="<?= $s ?>" <?= ($ins['inspection_status'] ?? 'pending') === $s ? 'selected' : '' ?>><?= strtoupper(str_replace('_', ' ', $s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="px-4 py-2"><input type="number" step="0.001" name="item_<?= $it['id'] ?>_accepted" value="<?= $ins['accepted_quantity'] ?? $it['accepted_quantity'] ?>" class="w-20 border rounded px-2 py-1 text-xs text-right"></td>
                    <td class="px-4 py-2"><input type="number" step="0.001" name="item_<?= $it['id'] ?>_rejected" value="<?= $ins['rejected_quantity'] ?? $it['rejected_quantity'] ?>" class="w-20 border rounded px-2 py-1 text-xs text-right"></td>
                    <td class="px-4 py-2"><input type="text" name="item_<?= $it['id'] ?>_reason" value="<?= sanitize($ins['reason'] ?? '') ?>" class="w-full border rounded px-2 py-1 text-xs"></td>
                    <td class="px-4 py-2"><input type="text" name="item_<?= $it['id'] ?>_comments" value="<?= sanitize($ins['comments'] ?? '') ?>" class="w-full border rounded px-2 py-1 text-xs"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="flex gap-2">
        <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">Save Inspection</button>
        <a href="../view/index.php?id=<?= $id ?>" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Cancel</a>
    </div>
</form>

