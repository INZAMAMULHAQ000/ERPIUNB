<?php
$pageTitle = 'View Purchase Order';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('purchase_orders.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('error', 'Invalid PO ID.'); redirect('index.php'); }

$stmt = $db->prepare("
    SELECT po.*, v.vendor_name, v.vendor_code, v.contact_person, v.email, v.phone, v.address,
        u.full_name AS created_by_name
    FROM purchase_orders po
    JOIN vendors v ON po.vendor_id = v.id
    LEFT JOIN users u ON po.created_by = u.id
    WHERE po.id = ?
");
$stmt->execute([$id]);
$po = $stmt->fetch();
if (!$po) { flash('error', 'Purchase order not found.'); redirect('index.php'); }

// Items
$items = $db->prepare("SELECT * FROM purchase_order_items WHERE po_id = ? ORDER BY sort_order");
$items->execute([$id]);
$items = $items->fetchAll();

// Status history
$history = $db->prepare("
    SELECT psh.*, u.full_name FROM po_status_history psh
    LEFT JOIN users u ON psh.changed_by = u.id
    WHERE psh.po_id = ? ORDER BY psh.created_at ASC
");
$history->execute([$id]);
$history = $history->fetchAll();

// Linked GRNs
$grns = $db->prepare("SELECT * FROM goods_receipt_notes WHERE po_id = ? AND status IN ('completed') ORDER BY created_at DESC");
$grns->execute([$id]);
$grns = $grns->fetchAll();

// Linked Bills
$bills = $db->prepare("SELECT * FROM vendor_bills WHERE po_id = ? ORDER BY created_at DESC");
$bills->execute([$id]);
$bills = $bills->fetchAll();

$statusBadges = [
    'draft' => 'bg-gray-100 text-gray-800',
    'approved' => 'bg-blue-100 text-blue-800',
    'sent' => 'bg-indigo-100 text-indigo-800',
    'partially_received' => 'bg-yellow-100 text-yellow-800',
    'received' => 'bg-green-100 text-green-800',
    'closed' => 'bg-gray-300 text-gray-700',
    'cancelled' => 'bg-red-100 text-red-800',
];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">PO: <?= sanitize($po['po_number']) ?></h1>
        <p class="text-gray-600"><?= sanitize($po['vendor_name']) ?> · <?= date('M d, Y', strtotime($po['order_date'])) ?></p>
    </div>
    <div class="flex gap-2">
        <a href="index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back</a>
        <?php if ($po['status'] === 'draft' && hasPermission('purchase_orders.edit')): ?>
        <a href="edit.php?id=<?= $id ?>" class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700 transition"><i class="fas fa-pencil mr-2"></i>Edit</a>
        <?php endif; ?>
        <?php if ($po['status'] === 'draft' && hasPermission('purchase_orders.approve')): ?>
        <form method="POST" action="approve.php" style="display:inline" onsubmit="return confirm('Approve this PO?')">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="po_id" value="<?= $id ?>">
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition"><i class="fas fa-check mr-2"></i>Approve</button>
        </form>
        <?php endif; ?>
        <?php if (!in_array($po['status'], ['received','closed','cancelled']) && hasPermission('purchase_orders.cancel')): ?>
        <form method="POST" action="cancel.php" style="display:inline" onsubmit="return confirm('Cancel this PO?')">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="po_id" value="<?= $id ?>">
            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition"><i class="fas fa-ban mr-2"></i>Cancel</button>
        </form>
        <?php endif; ?>
        <?php if ($po['status'] === 'received' && hasPermission('purchase_orders.close')): ?>
        <form method="POST" action="close.php" style="display:inline" onsubmit="return confirm('Close this PO?')">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="po_id" value="<?= $id ?>">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-check-double mr-2"></i>Close</button>
        </form>
        <?php endif; ?>
        <?php if ($po['status'] !== 'cancelled' && hasPermission('goods_receipt.create')): ?>
        <a href="../goods_receipt/create.php?po_id=<?= $id ?>" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 transition"><i class="fas fa-clipboard-check mr-2"></i>Receive Goods</a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- PO Info -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-lg font-semibold text-gray-800">PO Details</h3>
                <span class="px-3 py-1 text-sm rounded font-medium <?= $statusBadges[$po['status']] ?? 'bg-gray-100' ?>">
                    <?= ucwords(str_replace('_', ' ', $po['status'])) ?>
                </span>
            </div>
            <dl class="space-y-3">
                <div><dt class="text-sm text-gray-500">Vendor</dt>
                    <dd class="text-sm font-medium"><a href="../vendor_master/view.php?id=<?= $po['vendor_id'] ?>" class="text-blue-600 hover:underline"><?= sanitize($po['vendor_name']) ?></a></dd></div>
                <div><dt class="text-sm text-gray-500">Order Date</dt><dd class="text-sm font-medium"><?= date('M d, Y', strtotime($po['order_date'])) ?></dd></div>
                <div><dt class="text-sm text-gray-500">Expected Delivery</dt><dd class="text-sm font-medium"><?= $po['expected_delivery_date'] ? date('M d, Y', strtotime($po['expected_delivery_date'])) : '--' ?></dd></div>
                <div><dt class="text-sm text-gray-500">Currency</dt><dd class="text-sm font-medium"><?= sanitize($po['currency']) ?></dd></div>
                <div><dt class="text-sm text-gray-500">Payment Terms</dt><dd class="text-sm font-medium"><?= sanitize($po['payment_terms'] ?? '--') ?></dd></div>
                <div><dt class="text-sm text-gray-500">Created By</dt><dd class="text-sm font-medium"><?= sanitize($po['created_by_name'] ?? '--') ?></dd></div>
            </dl>

            <?php if ($po['notes']): ?>
            <div class="mt-4">
                <h4 class="text-sm font-medium text-gray-700 mb-1">Notes</h4>
                <p class="text-sm text-gray-600 whitespace-pre-line"><?= sanitize($po['notes']) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Items -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800">Items</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Discount</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Tax</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($items as $i => $item): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 text-sm text-gray-500"><?= $i + 1 ?></td>
                            <td class="px-3 py-2 text-sm text-gray-900"><?= sanitize($item['description']) ?></td>
                            <td class="px-3 py-2 text-sm text-right font-mono"><?= (float)$item['quantity'] ?></td>
                            <td class="px-3 py-2 text-sm text-gray-600"><?= sanitize($item['unit'] ?? '--') ?></td>
                            <td class="px-3 py-2 text-sm text-right font-mono"><?= formatMoney((float)$item['unit_price']) ?></td>
                            <td class="px-3 py-2 text-sm text-right font-mono"><?= (float)$item['discount_percent'] > 0 ? (float)$item['discount_percent'] . '%' : '--' ?></td>
                            <td class="px-3 py-2 text-sm text-right font-mono"><?= (float)$item['tax_percent'] > 0 ? (float)$item['tax_percent'] . '%' : '--' ?></td>
                            <td class="px-3 py-2 text-sm text-right font-mono font-medium"><?= formatMoney((float)$item['line_total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr><td colspan="7" class="px-3 py-2 text-right text-sm font-medium">Subtotal:</td>
                            <td class="px-3 py-2 text-right font-mono text-sm"><?= formatMoney((float)$po['subtotal']) ?></td></tr>
                        <?php if ((float)$po['discount_amount'] > 0): ?>
                        <tr><td colspan="7" class="px-3 py-2 text-right text-sm font-medium">Discount:</td>
                            <td class="px-3 py-2 text-right font-mono text-sm text-red-600">-<?= formatMoney((float)$po['discount_amount']) ?></td></tr>
                        <?php endif; ?>
                        <?php if ((float)$po['tax_amount'] > 0): ?>
                        <tr><td colspan="7" class="px-3 py-2 text-right text-sm font-medium">Tax:</td>
                            <td class="px-3 py-2 text-right font-mono text-sm"><?= formatMoney((float)$po['tax_amount']) ?></td></tr>
                        <?php endif; ?>
                        <tr><td colspan="7" class="px-3 py-2 text-right text-sm font-bold">Grand Total:</td>
                            <td class="px-3 py-2 text-right font-mono font-bold text-base"><?= formatMoney((float)$po['total_amount']) ?></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Status History -->
        <div class="bg-white rounded-lg shadow mt-6">
            <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800">Status History</h3></div>
            <div class="p-4">
                <?php if (empty($history)): ?>
                <p class="text-sm text-gray-500">No history recorded.</p>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($history as $h): ?>
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-2 h-2 mt-2 rounded-full bg-blue-500"></div>
                        <div class="flex-1">
                            <p class="text-sm">
                                <span class="font-medium"><?= $h['from_status'] ? ucwords(str_replace('_', ' ', $h['from_status'])) : '--' ?></span>
                                <i class="fas fa-arrow-right text-gray-400 mx-1"></i>
                                <span class="font-medium"><?= ucwords(str_replace('_', ' ', $h['to_status'])) ?></span>
                            </p>
                            <p class="text-xs text-gray-500"><?= sanitize($h['full_name'] ?? '--') ?> · <?= date('M d, Y H:i', strtotime($h['created_at'])) ?></p>
                            <?php if ($h['change_note']): ?>
                            <p class="text-xs text-gray-600 mt-1"><?= sanitize($h['change_note']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Linked GRNs -->
<?php if (!empty($grns)): ?>
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800">Goods Received</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">GRN #</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Receipt Date</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($grns as $g): ?>
                <tr>
                    <td class="px-4 py-2 text-sm font-mono"><a href="../goods_receipt/view.php?id=<?= $g['id'] ?>" class="text-blue-600 hover:underline"><?= sanitize($g['grn_number']) ?></a></td>
                    <td class="px-4 py-2 text-sm text-gray-600"><?= date('M d, Y', strtotime($g['receipt_date'])) ?></td>
                    <td class="px-4 py-2 text-sm"><span class="px-2 py-0.5 text-xs rounded bg-green-100 text-green-800">Completed</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Linked Bills -->
<?php if (!empty($bills)): ?>
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800">Vendor Bills</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Bill #</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Due</th>
                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($bills as $b): ?>
                <tr>
                    <td class="px-4 py-2 text-sm font-mono"><a href="../vendor_bills/view.php?id=<?= $b['id'] ?>" class="text-blue-600 hover:underline"><?= sanitize($b['bill_number']) ?></a></td>
                    <td class="px-4 py-2 text-sm text-gray-600"><?= date('M d, Y', strtotime($b['bill_date'])) ?></td>
                    <td class="px-4 py-2 text-sm text-gray-600"><?= date('M d, Y', strtotime($b['due_date'])) ?></td>
                    <td class="px-4 py-2 text-center">
                        <span class="px-2 py-0.5 text-xs rounded font-medium <?= $statusBadges[$b['status']] ?? 'bg-gray-100' ?>">
                            <?= ucwords(str_replace('_', ' ', $b['status'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-2 text-sm text-right font-mono"><?= formatMoney((float)$b['total_amount']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once '../../../includes/footer.php'; ?>
