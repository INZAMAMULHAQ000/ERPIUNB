<?php
/** P3 — Create Purchase Order from Award */
$pageTitle = 'Create Purchase Order';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../finance/helpers/finance_helpers.php';
require_once __DIR__ . '/../ProcurementService.php';
require_once __DIR__ . '/../RfqService.php';
require_once __DIR__ . '/../PoService.php';

if (!hasPermission('procurement.po.create')) die('Access Denied');
$db = getDB();
$UID = $_SESSION['user_id'];

// Find available awards (awarded quotations without a PO yet)
$awards = $db->query("SELECT qs.id, qs.award_level, qs.rfq_item_id, qs.vendor_id,
    q.quotation_number, q.grand_total, q.rfq_id,
    r.rfq_number, r.pr_id, pr.pr_number,
    v.vendor_name
    FROM quotation_selection qs
    JOIN vendor_quotations q ON q.id=qs.quotation_id
    JOIN request_for_quotations r ON r.id=qs.rfq_id
    JOIN vendors v ON v.id=qs.vendor_id
    LEFT JOIN purchase_requisitions pr ON pr.id=r.pr_id
    WHERE qs.award_level='rfq'
    AND q.status='selected'
    AND NOT EXISTS (SELECT 1 FROM purchase_orders po WHERE po.award_id=qs.id)
    ORDER BY qs.id DESC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid request.'); to('modules/procurement/purchase_orders/list.php'); }

    $awardId = (int)($_POST['award_id'] ?? 0);
    if (!$awardId) { flash('error', 'Please select an award.'); to('modules/procurement/purchase_orders/create.php'); }

    $overrides = [
        'po_date' => $_POST['po_date'] ?? date('Y-m-d'),
        'expected_delivery_date' => $_POST['expected_delivery_date'] ?: null,
        'currency' => $_POST['currency'] ?? 'AED',
        'payment_terms' => $_POST['payment_terms'] ?? null,
        'delivery_terms' => $_POST['delivery_terms'] ?? null,
        'delivery_location' => $_POST['delivery_location'] ?? null,
        'incoterms' => $_POST['incoterms'] ?? null,
        'warranty_terms' => $_POST['warranty_terms'] ?? null,
        'shipping_method' => $_POST['shipping_method'] ?? null,
        'special_instructions' => $_POST['special_instructions'] ?? null,
        'vendor_notes' => $_POST['vendor_notes'] ?? null,
        'internal_notes' => $_POST['internal_notes'] ?? null,
    ];

    $res = PoService::createFromAward($db, $awardId, $overrides, $UID);
    if (!empty($res['success'])) {
        flash('success', 'PO ' . $res['number'] . ' created from award.');
        to('modules/procurement/purchase_orders/view.php?id=' . $res['id']);
    }
    foreach (($res['errors'] ?? [$res['message'] ?? 'Create failed.']) as $e) flash('error', $e);
}
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Create Purchase Order</h1>
        <p class="text-gray-500 text-sm">Select an awarded quotation to generate a PO</p>
    </div>
    <a href="list.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back to List</a>
</div>

<?php if (empty($awards)): ?>
<div class="bg-white rounded-lg shadow p-10 text-center text-gray-400">
    <i class="fas fa-inbox text-4xl mb-3"></i>
    <p>No awards available to create POs from.<br>Complete the RFQ evaluation and award process first.</p>
</div>
<?php else: ?>

<form method="post" class="space-y-5">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <!-- Award Selection -->
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold mb-3">Select Award</h3>
        <select name="award_id" required class="w-full border rounded px-3 py-2 text-sm">
            <option value="">— Select an awarded quotation —</option>
            <?php foreach ($awards as $a): ?>
            <option value="<?= $a['id'] ?>">
                <?= sanitize($a['rfq_number']) ?> · <?= sanitize($a['vendor_name']) ?>
                — <?= formatMoney((float)$a['grand_total']) ?>
                <?= $a['pr_number'] ? '(PR: ' . sanitize($a['pr_number']) . ')' : '' ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- PO Details -->
    <div class="bg-white rounded-lg shadow p-5 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="text-xs text-gray-500 block mb-1">PO Date <span class="text-red-600">*</span></labe>
            <input type="date" name="po_date" value="<?= date('Y-m-d') ?>" required class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Expected Delivery</label>
            <input type="date" name="expected_delivery_date" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Currency</label>
            <input name="currency" value="AED" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Payment Terms</label>
            <input name="payment_terms" value="Net 30" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Delivery Terms</label>
            <input name="delivery_terms" value="Delivered to site" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Delivery Location</label>
            <input name="delivery_location" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Incoterms</label>
            <input name="incoterms" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Shipping Method</label>
            <input name="shipping_method" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div class="md:col-span-2">
            <label class="text-xs text-gray-500 block mb-1">Warranty Terms</label>
            <input name="warranty_terms" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div class="md:col-span-2">
            <label class="text-xs text-gray-500 block mb-1">Special Instructions</label>
            <textarea name="special_instructions" rows="2" class="w-full border rounded px-3 py-2 text-sm"></textarea>
        </div>
        <div class="md:col-span-2">
            <label class="text-xs text-gray-500 block mb-1">Vendor Notes</label>
            <textarea name="vendor_notes" rows="2" class="w-full border rounded px-3 py-2 text-sm"></textarea>
        </div>
        <div class="md:col-span-2">
            <label class="text-xs text-gray-500 block mb-1">Internal Notes</label>
            <textarea name="internal_notes" rows="2" class="w-full border rounded px-3 py-2 text-sm"></textarea>
        </div>
    </div>

    <button class="bg-blue-600 text-white px-6 py-2.5 rounded hover:bg-blue-700 text-sm">
        <i class="fas fa-check mr-1"></i>Create Purchase Order
    </button>
</form>
<?php endif; ?>

<?php require_once '../../../includes/footer.php'; ?>
