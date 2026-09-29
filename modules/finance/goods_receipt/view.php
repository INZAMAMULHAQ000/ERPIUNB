<?php
$pageTitle = 'View Goods Receipt Note';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('goods_receipt.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('error', 'Invalid GRN ID.'); redirect('index.php'); }

$stmt = $db->prepare("
    SELECT grn.*, po.po_number, po.status as po_status, v.vendor_name, v.vendor_code,
        u.full_name AS created_by_name
    FROM goods_receipt_notes grn
    JOIN purchase_orders po ON grn.po_id = po.id
    JOIN vendors v ON grn.vendor_id = v.id
    LEFT JOIN users u ON grn.created_by = u.id
    WHERE grn.id = ?
");
$stmt->execute([$id]);
$grn = $stmt->fetch();
if (!$grn) { flash('error', 'Goods receipt note not found.'); redirect('index.php'); }

$items = $db->prepare("SELECT gri.*, poi.description as poi_description FROM finance_goods_receipt_items gri JOIN purchase_order_items poi ON gri.po_item_id = poi.id WHERE gri.grn_id = ? ORDER BY gri.sort_order");
$items->execute([$id]);
$items = $items->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">GRN: <?= sanitize($grn['grn_number']) ?></h1>
        <p class="text-gray-600">Goods receipt for <a href="../purchase_orders/view.php?id=<?= $grn['po_id'] ?>" class="text-blue-600 hover:underline"><?= sanitize($grn['po_number']) ?></a> from <?= sanitize($grn['vendor_name']) ?></p>
    </div>
    <div class="flex gap-2">
        <a href="index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back</a>
        <?php if ($grn['status'] === 'completed' && hasPermission('goods_receipt.cancel')): ?>
        <form method="POST" action="cancel.php" style="display:inline" onsubmit="return confirm('Cancel this GRN? Received quantities will be rolled back on the PO.')">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="grn_id" value="<?= $id ?>">
            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition"><i class="fas fa-ban mr-2"></i>Cancel GRN</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-lg font-semibold text-gray-800">GRN Details</h3>
                <span class="px-3 py-1 text-sm rounded font-medium <?= $grn['status'] === 'completed' ? 'bg-green-100 text-green-800' : ($grn['status'] === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') ?>">
                    <?= ucfirst($grn['status']) ?>
                </span>
            </div>
            <dl class="space-y-3">
                <div><dt class="text-sm text-gray-500">Vendor</dt><dd class="text-sm font-medium"><a href="../vendor_master/view.php?id=<?= $grn['vendor_id'] ?>" class="text-blue-600 hover:underline"><?= sanitize($grn['vendor_name']) ?></a></dd></div>
                <div><dt class="text-sm text-gray-500">Purchase Order</dt><dd class="text-sm font-medium"><a href="../purchase_orders/view.php?id=<?= $grn['po_id'] ?>" class="text-blue-600 hover:underline"><?= sanitize($grn['po_number']) ?></a></dd></div>
                <div><dt class="text-sm text-gray-500">Receipt Date</dt><dd class="text-sm font-medium"><?= date('M d, Y', strtotime($grn['receipt_date'])) ?></dd></div>
                <div><dt class="text-sm text-gray-500">Delivery Note #</dt><dd class="text-sm font-medium"><?= sanitize($grn['delivery_note_number'] ?? '--') ?></dd></div>
                <div><dt class="text-sm text-gray-500">Created By</dt><dd class="text-sm font-medium"><?= sanitize($grn['created_by_name'] ?? '--') ?></dd></div>
            </dl>
            <?php if ($grn['notes']): ?>
            <div class="mt-4"><h4 class="text-sm font-medium text-gray-700 mb-1">Notes</h4><p class="text-sm text-gray-600 whitespace-pre-line"><?= sanitize($grn['notes']) ?></p></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800">Items Received</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qty Received</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($items as $i => $item): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 text-sm text-gray-500"><?= $i + 1 ?></td>
                            <td class="px-3 py-2 text-sm text-gray-900"><?= sanitize($item['description'] ?: $item['poi_description']) ?></td>
                            <td class="px-3 py-2 text-sm text-right font-mono"><?= (float)$item['quantity_received'] ?></td>
                            <td class="px-3 py-2 text-sm text-gray-600"><?= sanitize($item['unit'] ?? '--') ?></td>
                            <td class="px-3 py-2 text-sm text-right font-mono"><?= formatMoney((float)$item['unit_price']) ?></td>
                            <td class="px-3 py-2 text-sm text-right font-mono font-medium"><?= formatMoney((float)$item['line_total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>
