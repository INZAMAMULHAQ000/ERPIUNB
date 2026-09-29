<?php
/** P3 — Edit Purchase Order (draft/rejected only) */
$pageTitle = 'Edit Purchase Order';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../finance/helpers/finance_helpers.php';
require_once __DIR__ . '/../ProcurementService.php';
require_once __DIR__ . '/../RfqService.php';
require_once __DIR__ . '/../PoService.php';

if (!hasPermission('procurement.po.edit')) die('Access Denied');
$db = getDB();
$UID = $_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);
$po = PoService::getPO($db, $id);
if (!$po) { flash('error', 'PO not found.'); to('list.php'); }
if (!PoService::canEdit($po)) { flash('error', 'Status "' . $po['status'] . '" cannot be edited.'); to("view.php?id=$id"); }

$items = PoService::getItems($db, $id);
$charges = PoService::getCharges($db, $id);
$vendors = $db->query("SELECT id, vendor_name FROM vendors WHERE status='active' ORDER BY vendor_name")->fetchAll();
$companies = $db->query("SELECT id, company_name FROM companies ORDER BY company_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid request.'); to("edit.php?id=$id"); }

    $header = [
        'vendor_id' => (int)($_POST['vendor_id'] ?? 0),
        'company_id' => (int)($_POST['company_id'] ?? 0),
        'department_id' => $_POST['department_id'] ?: null,
        'project_id' => $_POST['project_id'] ?: null,
        'cost_center_id' => $_POST['cost_center_id'] ?: null,
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

    $lines = [];
    foreach ($_POST['lines'] ?? [] as $l) {
        if (empty($l['item_name'])) continue;
        $lines[] = $l;
    }

    $chargesArr = [];
    foreach ($_POST['charges'] ?? [] as $c) {
        if (trim($c['charge_name'] ?? '') !== '') $chargesArr[] = $c;
    }

    $res = PoService::updatePO($db, $id, $header, $lines, $chargesArr, $UID);
    if (!empty($res['success'])) {
        flash('success', 'PO updated.');
        to("view.php?id=$id");
    }
    foreach (($res['errors'] ?? []) as $e) flash('error', $e);
}
?>
<div class="mb-6 flex justify-between items-center">
    <h1 class="text-2xl font-bold text-gray-800">Edit <?= sanitize($po['po_number']) ?></h1>
    <a href="view.php?id=<?= $id ?>" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
</div>

<form method="post" class="space-y-5">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <div class="bg-white rounded-lg shadow p-5 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="text-xs text-gray-500 block mb-1">Vendor <span class="text-red-600">*</span></labe>
            <select name="vendor_id" required class="w-full border rounded px-3 py-2 text-sm">
                <?php foreach ($vendors as $v): ?>
                <option value="<?= $v['id'] ?>" <?= $po['vendor_id'] == $v['id'] ? 'selected' : '' ?>><?= sanitize($v['vendor_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Company <span class="text-red-600">*</span></labe>
            <select name="company_id" required class="w-full border rounded px-3 py-2 text-sm">
                <?php foreach ($companies as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $po['company_id'] == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">PO Date <span class="text-red-600">*</span></labe>
            <input type="date" name="po_date" value="<?= $po['po_date'] ?>" required class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Expected Delivery</label>
            <input type="date" name="expected_delivery_date" value="<?= $po['expected_delivery_date'] ?>" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Currency</label>
            <input name="currency" value="<?= sanitize($po['currency']) ?>" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Payment Terms</label>
            <input name="payment_terms" value="<?= sanitize($po['payment_terms'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Delivery Terms</label>
            <input name="delivery_terms" value="<?= sanitize($po['delivery_terms'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Delivery Location</label>
            <input name="delivery_location" value="<?= sanitize($po['delivery_location'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
        </div>
    </div>

    <!-- Items -->
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold mb-2 text-sm">PO Items</h3>
        <table class="w-full text-sm" id="linesTable">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="text-left px-2 py-2">Item</th>
                    <th class="text-right px-2">Qty</th>
                    <th class="text-right px-2">Unit Price</th>
                    <th class="text-right px-2">Tax %</th>
                    <th class="text-right px-2">Disc</th>
                    <th class="text-right px-2">Total</th>
                </tr>
            </thead>
            <tbody id="linesBody"></tbody>
        </table>
        <button type="button" onclick="addLine()" class="mt-2 text-blue-600 hover:underline text-sm"><i class="fas fa-plus mr-1"></i>Add line</button>
    </div>

    <!-- Charges -->
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold mb-2 text-sm">Other Charges</h3>
        <div id="chargesWrap"></div>
        <button type="button" onclick="addCharge()" class="text-blue-600 hover:underline text-sm mt-1"><i class="fas fa-plus mr-1"></i>Add charge</button>
    </div>

    <button class="bg-blue-600 text-white px-6 py-2.5 rounded hover:bg-blue-700 text-sm">Save Changes</button>
</form>

<script>
const existingItems = <?= json_encode($items) ?>;
const existingCharges = <?= json_encode($charges) ?>;

function addLine(data) {
    data = data || {};
    const tb = document.getElementById('linesBody');
    const tr = document.createElement('tr');
    tr.className = 'border-b';
    tr.innerHTML = `
        <td class="px-2 py-1"><input name="lines[][item_name]" value="${(data.item_name||'').replace(/"/g,'&quot;')}" required class="border rounded px-1 py-0.5 text-xs w-48"></td>
        <td class="px-2 py-1"><input type="number" step="0.001" name="lines[][quantity]" value="${data.quantity||''}" class="w-20 border rounded px-1 py-0.5 text-right text-xs"></td>
        <td class="px-2 py-1"><input type="number" step="0.01" name="lines[][unit_price]" value="${data.unit_price||''}" class="w-24 border rounded px-1 py-0.5 text-right text-xs"></td>
        <td class="px-2 py-1"><input type="number" step="0.001" name="lines[][tax_percent]" value="${data.tax_percent||0}" class="w-16 border rounded px-1 py-0.5 text-right text-xs"></td>
        <td class="px-2 py-1"><input type="number" step="0.01" name="lines[][discount_amount]" value="${data.discount_amount||0}" class="w-20 border rounded px-1 py-0.5 text-right text-xs"></td>
        <td class="px-2 py-1 text-right font-mono text-xs">—</td>
        <input type="hidden" name="lines[][item_type]" value="${data.item_type||'non_stock'}">
        <input type="hidden" name="lines[][unit]" value="${data.unit||'pcs'}">
        <input type="hidden" name="lines[][rfq_item_id]" value="${data.rfq_item_id||''}">
        <input type="hidden" name="lines[][pr_item_id]" value="${data.pr_item_id||''}">
        <input type="hidden" name="lines[][awarded_quantity]" value="${data.awarded_quantity||data.quantity||''}">
    `;
    tb.appendChild(tr);
}
function addCharge(data) {
    data = data || {};
    const w = document.getElementById('chargesWrap');
    const d = document.createElement('div');
    d.className = 'flex gap-1 mb-1';
    d.innerHTML = `<input name="charges[][charge_name]" placeholder="Freight / Insurance" value="${data.charge_name||''}" class="border rounded px-1 py-0.5 text-xs w-40">
        <input type="number" step="0.01" name="charges[][amount]" value="${data.amount||''}" class="border rounded px-1 py-0.5 text-xs w-24 text-right">
        <button type="button" onclick="this.parentElement.remove()" class="text-red-500">&times;</button>`;
    w.appendChild(d);
}
existingItems.forEach(i => addLine(i));
existingCharges.forEach(c => addCharge(c));
if (!existingCharges.length) addCharge();
</script>

<?php require_once '../../../includes/footer.php'; ?>
