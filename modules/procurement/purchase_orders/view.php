<?php
/** P3 — Purchase Order Detail View */
$pageTitle = 'Purchase Order';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../finance/helpers/finance_helpers.php';
require_once __DIR__ . '/../ProcurementService.php';
require_once __DIR__ . '/../RfqService.php';
require_once __DIR__ . '/../PoService.php';

if (!hasPermission('procurement.po.view')) die('Access Denied');
$db = getDB();
$UID = $_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);
$po = PoService::getPO($db, $id);
if (!$po) { flash('error', 'PO not found.'); to('modules/procurement/purchase_orders/list.php'); }

$items = PoService::getItems($db, $id);
$charges = PoService::getCharges($db, $id);
$approvals = PoService::getApprovals($db, $id);
$revisions = PoService::getRevisions($db, $id);
$comms = PoService::getCommunications($db, $id);
$commitment = PoService::commitmentSummary($db, $id);

$statusStyles = [
    'draft'=>'bg-gray-100 text-gray-600','pending_approval'=>'bg-yellow-100 text-yellow-700',
    'approved'=>'bg-green-100 text-green-700','rejected'=>'bg-red-100 text-red-700',
    'sent'=>'bg-blue-100 text-blue-700','acknowledged'=>'bg-indigo-100 text-indigo-700',
    'closed'=>'bg-gray-200 text-gray-700','cancelled'=>'bg-purple-100 text-purple-700',
];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid request.'); to("view.php?id=$id"); }
    $action = $_POST['action'] ?? '';

    if ($action === 'submit') {
        $res = PoService::submit($db, $id, $UID);
        flash($res['success'] ? 'success' : 'error', $res['message'] ?? 'Submitted.');
    } elseif ($action === 'approve' && hasPermission('procurement.po.approve')) {
        $res = PoService::approve($db, $id, $UID, $_POST['comments'] ?? null);
        flash($res['success'] ? 'success' : 'error', $res['message'] ?? 'Approved.');
    } elseif ($action === 'reject' && hasPermission('procurement.po.reject')) {
        $res = PoService::reject($db, $id, $UID, $_POST['reason'] ?? '');
        flash($res['success'] ? 'success' : 'error', $res['message'] ?? 'Rejected.');
    } elseif ($action === 'send' && hasPermission('procurement.po.send')) {
        $res = PoService::send($db, $id, $UID, $_POST['method'] ?? 'email');
        flash($res['success'] ? 'success' : 'error', $res['message'] ?? 'Sent.');
    } elseif ($action === 'acknowledge') {
        $res = PoService::acknowledge($db, $id, $UID, $_POST['vendor_reference'] ?? null,
               $_POST['expected_delivery_date_ack'] ?: null, $_POST['vendor_comments'] ?? null);
        flash($res['success'] ? 'success' : 'error', $res['message'] ?? 'Acknowledged.');
    } elseif ($action === 'cancel' && hasPermission('procurement.po.cancel')) {
        $res = PoService::cancel($db, $id, $UID, $_POST['reason'] ?? '');
        flash($res['success'] ? 'success' : 'error', $res['message'] ?? 'Cancelled.');
    } elseif ($action === 'close') {
        $res = PoService::close($db, $id, $UID, $_POST['reason'] ?? null);
        flash($res['success'] ? 'success' : 'error', $res['message'] ?? 'Closed.');
    }
    to("view.php?id=$id");
}

$po = PoService::getPO($db, $id); // refresh
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= sanitize($po['po_number']) ?></h1>
        <p class="font-mono text-sm text-gray-500">
            <?= sanitize($po['vendor_name']) ?> · <?= formatMoney((float)$po['grand_total']) ?>
            <span class="ml-2 px-2 py-0.5 rounded text-xs font-semibold <?= $statusStyles[$po['status']] ?? '' ?>">
                <?= strtoupper(str_replace('_', ' ', $po['status'])) ?>
            </span>
            <?php if ($po['revision_number'] > 0): ?>
            <span class="text-xs text-orange-500">Rev. <?= $po['revision_number'] ?></span>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex gap-2">
        <a href="list.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">← Back</a>
        <?php if (in_array($po['status'], ['draft','rejected']) && hasPermission('procurement.po.edit')): ?>
        <a href="edit.php?id=<?= $id ?>" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Edit</a>
        <?php endif; ?>
    </div>
</div>

<!-- Header Info -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 text-sm">
        <h3 class="font-semibold mb-2">PO Details</h3>
        <p><span class="text-gray-500">Date:</span> <?= $po['po_date'] ? date('d M Y', strtotime($po['po_date'])) : '—' ?></p>
        <p><span class="text-gray-500">Buyer:</span> <?= sanitize($po['buyer_name'] ?? '—') ?></p>
        <p><span class="text-gray-500">Currency:</span> <?= sanitize($po['currency']) ?></p>
        <p><span class="text-gray-500">Expected Delivery:</span> <?= $po['expected_delivery_date'] ? date('d M Y', strtotime($po['expected_delivery_date'])) : '—' ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-sm">
        <h3 class="font-semibold mb-2">Source Documents</h3>
        <p><span class="text-gray-500">PR:</span> <?= $po['pr_number'] ? sanitize($po['pr_number']) : '—' ?></p>
        <p><span class="text-gray-500">RFQ:</span> <?= $po['rfq_number'] ? sanitize($po['rfq_number']) : '—' ?></p>
        <p><span class="text-gray-500">Quotation:</span> <?= $po['quotation_number'] ? sanitize($po['quotation_number']) : '—' ?></p>
        <p><span class="text-gray-500">Company:</span> <?= sanitize($po['company_name']) ?></p>
        <p><span class="text-gray-500">Department:</span> <?= sanitize($po['department_name'] ?? '—') ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-sm">
        <h3 class="font-semibold mb-2">Terms</h3>
        <p><span class="text-gray-500">Payment:</span> <?= sanitize($po['payment_terms'] ?? '—') ?></p>
        <p><span class="text-gray-500">Delivery:</span> <?= sanitize($po['delivery_terms'] ?? '—') ?></p>
        <p><span class="text-gray-500">Incoterms:</span> <?= sanitize($po['incoterms'] ?? '—') ?></p>
        <p><span class="text-gray-500">Warranty:</span> <?= sanitize($po['warranty_terms'] ?? '—') ?></p>
    </div>
</div>

<!-- Items -->
<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <div class="px-5 pt-4"><h3 class="font-semibold">PO Items</h3></div>
    <table class="w-full text-sm mt-2">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="px-4 py-2 text-left">#</th>
                <th class="px-4 py-2 text-left">Item</th>
                <th class="px-4 py-2 text-right">Qty</th>
                <th class="px-4 py-2 text-right">Unit Price</th>
                <th class="px-4 py-2 text-right">Tax</th>
                <th class="px-4 py-2 text-right">Total</th>
                <th class="px-4 py-2 text-right">Awarded</th>
                <th class="px-4 py-2 text-right">Remaining</th>
            </tr>
        </thead>
        <tbody class="divide-y">
        <?php foreach ($items as $it): ?>
            <tr>
                <td class="px-4 py-2"><?= (int)$it['po_item_number'] ?></td>
                <td class="px-4 py-2"><?= sanitize($it['item_name']) ?></td>
                <td class="px-4 py-2 text-right font-mono"><?= number_format((float)$it['quantity'], 2) ?></td>
                <td class="px-4 py-2 text-right font-mono"><?= number_format((float)$it['unit_price'], 2) ?></td>
                <td class="px-4 py-2 text-right font-mono"><?= number_format((float)$it['line_tax'], 2) ?></td>
                <td class="px-4 py-2 text-right font-mono font-semibold"><?= number_format((float)$it['line_total'], 2) ?></td>
                <td class="px-4 py-2 text-right font-mono text-xs"><?= number_format((float)$it['awarded_quantity'], 2) ?></td>
                <td class="px-4 py-2 text-right font-mono text-xs <?= (float)$it['remaining_quantity'] > 0 ? 'text-orange-600' : 'text-green-600' ?>">
                    <?= number_format((float)$it['remaining_quantity'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-gray-50 font-bold">
            <tr>
                <td colspan="5" class="px-4 py-2 text-right">Subtotal</td>
                <td class="px-4 py-2 text-right font-mono"><?= formatMoney((float)$po['subtotal']) ?></td>
                <td colspan="2"></td>
            </tr>
            <tr>
                <td colspan="5" class="px-4 py-2 text-right">Discount</td>
                <td class="px-4 py-2 text-right font-mono text-red-600">-<?= formatMoney((float)$po['discount_total']) ?></td>
                <td colspan="2"></td>
            </tr>
            <tr>
                <td colspan="5" class="px-4 py-2 text-right">Tax</td>
                <td class="px-4 py-2 text-right font-mono"><?= formatMoney((float)$po['tax_total']) ?></td>
                <td colspan="2"></td>
            </tr>
            <?php if ((float)$po['other_charges_total'] > 0): ?>
            <tr>
                <td colspan="5" class="px-4 py-2 text-right">Other Charges</td>
                <td class="px-4 py-2 text-right font-mono"><?= formatMoney((float)$po['other_charges_total']) ?></td>
                <td colspan="2"></td>
            </tr>
            <?php endif; ?>
            <tr class="bg-gray-100">
                <td colspan="5" class="px-4 py-2 text-right text-base">GRAND TOTAL</td>
                <td class="px-4 py-2 text-right font-mono text-lg"><?= formatMoney((float)$po['grand_total']) ?></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- Actions -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Submit / Approve / Reject -->
    <?php if (in_array($po['status'], ['draft','rejected']) && hasPermission('procurement.po.submit')): ?>
    <form method="post" class="bg-white border-2 border-blue-300 rounded-lg shadow p-5 space-y-3">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="submit">
        <h3 class="font-bold text-blue-700"><i class="fas fa-paper-plane mr-1"></i>Submit for Approval</h3>
        <button class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 text-sm">Submit PO</button>
    </form>
    <?php endif; ?>

    <?php if ($po['status'] === 'pending_approval' && hasPermission('procurement.po.approve')): ?>
    <form method="post" class="bg-white border-2 border-green-300 rounded-lg shadow p-5 space-y-3">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="approve">
        <h3 class="font-bold text-green-700"><i class="fas fa-check mr-1"></i>Approve PO</h3>
        <textarea name="comments" rows="2" placeholder="Approval comments (optional)" class="w-full border rounded px-3 py-2 text-sm"></textarea>
        <button class="w-full bg-green-600 text-white py-2 rounded hover:bg-green-700 text-sm">Approve</button>
    </form>
    <?php endif; ?>

    <?php if ($po['status'] === 'pending_approval' && hasPermission('procurement.po.reject')): ?>
    <form method="post" class="bg-white border-2 border-red-300 rounded-lg shadow p-5 space-y-3">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="reject">
        <h3 class="font-bold text-red-700"><i class="fas fa-times mr-1"></i>Reject PO</h3>
        <textarea name="reason" rows="2" required placeholder="Rejection reason (required)" class="w-full border rounded px-3 py-2 text-sm"></textarea>
        <button class="w-full bg-red-600 text-white py-2 rounded hover:bg-red-700 text-sm">Reject</button>
    </form>
    <?php endif; ?>

    <?php if ($po['status'] === 'approved' && hasPermission('procurement.po.send')): ?>
    <form method="post" class="bg-white border-2 border-indigo-300 rounded-lg shadow p-5 space-y-3">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="send">
        <h3 class="font-bold text-indigo-700"><i class="fas fa-paper-plane mr-1"></i>Send to Vendor</h3>
        <select name="method" class="w-full border rounded px-3 py-2 text-sm">
            <option value="email">Email</option><option value="pdf">PDF Download</option><option value="manual">Manual</option>
        </select>
        <button class="w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700 text-sm">Send PO</button>
    </form>
    <?php endif; ?>

    <?php if ($po['status'] === 'sent'): ?>
    <form method="post" class="bg-white border-2 border-emerald-300 rounded-lg shadow p-5 space-y-3">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="acknowledge">
        <h3 class="font-bold text-emerald-700"><i class="fas fa-handshake mr-1"></i>Vendor Acknowledgement</h3>
        <input name="vendor_reference" placeholder="Vendor reference #" class="w-full border rounded px-3 py-2 text-sm">
        <input type="date" name="expected_delivery_date_ack" class="w-full border rounded px-3 py-2 text-sm">
        <textarea name="vendor_comments" rows="2" placeholder="Vendor comments" class="w-full border rounded px-3 py-2 text-sm"></textarea>
        <button class="w-full bg-emerald-600 text-white py-2 rounded hover:bg-emerald-700 text-sm">Record Acknowledgement</button>
    </form>
    <?php endif; ?>

    <?php if (!in_array($po['status'], ['cancelled','closed']) && hasPermission('procurement.po.cancel')): ?>
    <form method="post" class="bg-white border border-red-200 rounded-lg shadow p-5 space-y-3">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="cancel">
        <h3 class="font-semibold text-red-600"><i class="fas fa-ban mr-1"></i>Cancel PO</h3>
        <textarea name="reason" rows="2" required placeholder="Cancellation reason (required)" class="w-full border rounded px-3 py-2 text-sm"></textarea>
        <button class="w-full bg-red-500 text-white py-2 rounded hover:bg-red-600 text-sm">Cancel PO</button>
    </form>
    <?php endif; ?>
</div>

<!-- Approval History -->
<?php if ($approvals): ?>
<div class="bg-white rounded-lg shadow p-5 mb-6">
    <h3 class="font-semibold mb-3">Approval History</h3>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
            <tr><th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-left">User</th><th class="px-3 py-2 text-left">Action</th><th class="px-3 py-2 text-left">Comments</th></tr>
        </thead>
        <tbody class="divide-y">
        <?php foreach ($approvals as $a): ?>
            <tr>
                <td class="px-3 py-2 text-xs"><?= date('d M Y H:i', strtotime($a['created_at'])) ?></td>
                <td class="px-3 py-2"><?= sanitize($a['approver_name']) ?></td>
                <td class="px-3 py-2 uppercase text-xs font-semibold"><?= sanitize($a['action']) ?></td>
                <td class="px-3 py-2 text-xs"><?= sanitize($a['comments'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Revision History -->
<?php if ($revisions): ?>
<div class="bg-white rounded-lg shadow p-5 mb-6">
    <h3 class="font-semibold mb-3">Revision History</h3>
    <?php foreach ($revisions as $rev): ?>
    <div class="border-l-4 border-orange-300 pl-3 mb-3">
        <p class="text-sm font-semibold">Revision #<?= $rev['revision_number'] ?> — <?= sanitize($rev['changed_by_name']) ?> — <?= date('d M Y H:i', strtotime($rev['changed_at'])) ?></p>
        <p class="text-xs text-gray-500">Reason: <?= sanitize($rev['reason']) ?></p>
        <?php if ($rev['changed_fields']): $changes = json_decode($rev['changed_fields'], true); ?>
        <div class="text-xs mt-1">
            <?php foreach ($changes as $field => $diff): ?>
            <span class="inline-block bg-orange-50 text-orange-700 px-2 py-0.5 rounded mr-1 mb-1"><?= sanitize($field) ?>: <?= sanitize($diff) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once '../../../includes/footer.php'; ?>

