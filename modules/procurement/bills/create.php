<?php
$pageTitle = 'Create Vendor Bill';
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('procurement.bills.create')) { die('Access Denied'); }

$db = getDB();
$sourceType = $_GET['source'] ?? 'po';
$sourceId = (int)($_GET['source_id'] ?? 0);
$grId = (int)($_GET['gr_id'] ?? 0);
$srId = (int)($_GET['sr_id'] ?? 0);

$po = null;
$gr = null;
$sr = null;
$lines = [];

if ($sourceType === 'po' && $sourceId) {
    $result = BillService::createFromPO($db, $sourceId, $grId ?: null, $srId ?: null);
    if (!$result['success']) {
        flash('error', $result['message']);
        redirect('index.php');
    }
    $po = $result['source'];
    $lines = $result['lines'];
    $grId = $result['gr_id'] ?? $grId;
    $srId = $result['sr_id'] ?? $srId;
} elseif ($sourceType === 'gr' && $sourceId) {
    $gr = $db->prepare("SELECT gr.*, v.vendor_name, c.company_name, c.id AS company_id
                        FROM goods_receipts gr
                        JOIN vendors v ON v.id=gr.vendor_id
                        JOIN companies c ON c.id=gr.company_id
                        WHERE gr.id=?");
    $gr->execute([$sourceId]);
    $gr = $gr->fetch();
    if (!$gr) { flash('error', 'Goods Receipt not found.'); redirect('index.php'); }

    $grItems = $db->prepare("SELECT * FROM goods_receipt_items WHERE gr_id=? ORDER BY sort_order, id");
    $grItems->execute([$sourceId]);
    foreach ($grItems->fetchAll() as $gi) {
        $lines[] = [
            'procurement_po_item_id' => (int)$gi['po_item_id'],
            'procurement_gr_item_id' => (int)$gi['id'],
            'item_name' => $gi['item_name'],
            'description' => $gi['description'],
            'quantity' => (float)$gi['accepted_quantity'],
            'unit' => $gi['unit'],
            'unit_price' => (float)$gi['unit_price'],
            'discount_amount' => 0,
            'tax_percent' => 0,
        ];
    }
} elseif ($sourceType === 'sr' && $sourceId) {
    $sr = $db->prepare("SELECT sr.*, v.vendor_name, c.company_name, c.id AS company_id
                        FROM service_receipts sr
                        JOIN vendors v ON v.id=sr.vendor_id
                        JOIN companies c ON c.id=sr.company_id
                        WHERE sr.id=?");
    $sr->execute([$sourceId]);
    $sr = $sr->fetch();
    if (!$sr) { flash('error', 'Service Receipt not found.'); redirect('index.php'); }

    $srItems = $db->prepare("SELECT * FROM service_receipt_items WHERE sr_id=? ORDER BY sort_order, id");
    $srItems->execute([$sourceId]);
    foreach ($srItems->fetchAll() as $si) {
        $lines[] = [
            'procurement_po_item_id' => (int)$si['po_item_id'],
            'procurement_sr_item_id' => (int)$si['id'],
            'item_name' => $si['item_name'],
            'description' => $si['description'],
            'quantity' => (float)$si['accepted_quantity'],
            'unit' => $si['unit'],
            'unit_price' => (float)$si['unit_price'],
            'discount_amount' => 0,
            'tax_percent' => 0,
        ];
    }
}

$vendors = $db->query("SELECT id, vendor_name FROM vendors WHERE status='active' ORDER BY vendor_name")->fetchAll();
$companies = $db->query("SELECT id, company_name FROM companies WHERE status='active' ORDER BY company_name")->fetchAll();
$poList = $db->query("SELECT id, po_number, vendor_id, company_id FROM purchase_orders WHERE status IN ('approved','sent','acknowledged','partially_received','fully_received','partially_billed') ORDER BY id DESC LIMIT 100")->fetchAll();
$grList = $db->query("SELECT id, gr_number, po_id, vendor_id, company_id FROM goods_receipts WHERE status='accepted' ORDER BY id DESC LIMIT 100")->fetchAll();
$srList = $db->query("SELECT id, sr_number, po_id, vendor_id, company_id FROM service_receipts WHERE status='accepted' ORDER BY id DESC LIMIT 100")->fetchAll();

$errors = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Create Vendor Bill</h1>

    <?php if ($errors): foreach ($errors as $e): if ($e['type'] === 'error'): ?>
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded"><?= sanitize($e['message']) ?></div>
    <?php endif; endforeach; endif; ?>

    <form method="POST" action="save.php" id="billForm" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="source_type" value="<?= sanitize($sourceType) ?>">
        <input type="hidden" name="source_id" value="<?= (int)$sourceId ?>">
        <input type="hidden" name="gr_id" value="<?= (int)$grId ?>">
        <input type="hidden" name="sr_id" value="<?= (int)$srId ?>">
        <input type="hidden" name="procurement_po_id" value="<?= (int)($po['id'] ?? $gr['po_id'] ?? $sr['po_id'] ?? 0) ?>">

        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Source Document</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Source Type</label>
                    <select name="source_type_select" id="sourceTypeSelect" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="po" <?= $sourceType === 'po' ? 'selected' : '' ?>>Purchase Order</option>
                        <option value="gr" <?= $sourceType === 'gr' ? 'selected' : '' ?>>Goods Receipt</option>
                        <option value="sr" <?= $sourceType === 'sr' ? 'selected' : '' ?>>Service Receipt</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Select Document</label>
                    <select name="source_id_select" id="sourceIdSelect" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <?php if ($sourceType === 'po'): ?>
                        <option value="">-- Select PO --</option>
                        <?php foreach ($poList as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= (int)$sourceId === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= sanitize($p['po_number']) ?> (<?= sanitize($p['vendor_id']) ?>)
                        </option>
                        <?php endforeach; ?>
                        <?php elseif ($sourceType === 'gr'): ?>
                        <option value="">-- Select Goods Receipt --</option>
                        <?php foreach ($grList as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= (int)$sourceId === (int)$g['id'] ? 'selected' : '' ?>>
                            <?= sanitize($g['gr_number']) ?> (PO: <?= sanitize($g['po_id']) ?>)
                        </option>
                        <?php endforeach; ?>
                        <?php elseif ($sourceType === 'sr'): ?>
                        <option value="">-- Select Service Receipt --</option>
                        <?php foreach ($srList as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= (int)$sourceId === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= sanitize($s['sr_number']) ?> (PO: <?= sanitize($s['po_id']) ?>)
                        </option>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div class="mt-4">
                <button type="button" id="loadSourceBtn" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">Load Source</button>
            </div>
        </div>

        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Bill Header</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Vendor Invoice Number <span class="text-red-600">*</span></labe>
                    <input type="text" name="vendor_invoice_number" value="<?= sanitize($po['vendor_invoice_number'] ?? '') ?>"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Vendor <span class="text-red-600">*</span></labe>
                    <select name="vendor_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" required>
                        <option value="">Select Vendor</option>
                        <?php foreach ($vendors as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= ($po['vendor_id'] ?? 0) == $v['id'] ? 'selected' : '' ?>><?= sanitize($v['vendor_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Company <span class="text-red-600">*</span></labe>
                    <select name="company_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" required>
                        <option value="">Select Company</option>
                        <?php foreach ($companies as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($po['company_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Bill Date <span class="text-red-600">*</span></labe>
                    <input type="date" name="bill_date" value="<?= date('Y-m-d') ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Due Date <span class="text-red-600">*</span></labe>
                    <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Currency</label>
                    <select name="currency" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="AED" <?= ($po['currency'] ?? 'AED') === 'AED' ? 'selected' : '' ?>>AED</option>
                        <option value="USD" <?= ($po['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD</option>
                        <option value="EUR" <?= ($po['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Payment Terms</label>
                    <input type="text" name="payment_terms" value="<?= sanitize($po['vendor_payment_terms'] ?? $po['payment_terms'] ?? '') ?>"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"><?= sanitize($po['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="bg-white shadow rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Bill Items</h2>
                <button type="button" id="addLineBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">Add Line</button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="linesTable">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">PO Item</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Discount</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tax %</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Line Total</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="linesBody">
                        <?php foreach ($lines as $i => $l): ?>
                        <tr>
                            <td class="px-3 py-2"><input type="text" name="lines[<?= $i ?>][item_name]" value="<?= sanitize($l['item_name']) ?>" class="w-full rounded border-gray-300 text-sm" required></td>
                            <td class="px-3 py-2"><input type="hidden" name="lines[<?= $i ?>][procurement_po_item_id]" value="<?= (int)($l['procurement_po_item_id'] ?? 0) ?>"><input type="hidden" name="lines[<?= $i ?>][procurement_gr_item_id]" value="<?= (int)($l['procurement_gr_item_id'] ?? 0) ?>"><input type="hidden" name="lines[<?= $i ?>][procurement_sr_item_id]" value="<?= (int)($l['procurement_sr_item_id'] ?? 0) ?>"><span class="text-sm text-gray-600"><?= (int)($l['procurement_po_item_id'] ?? 0) ?: '-' ?></span></td>
                            <td class="px-3 py-2"><input type="number" step="0.001" name="lines[<?= $i ?>][quantity]" value="<?= number_format((float)($l['quantity'] ?? 0), 3) ?>" class="w-20 rounded border-gray-300 text-sm qty-input" onchange="recalcLine(this)"></td>
                            <td class="px-3 py-2"><input type="number" step="0.0001" name="lines[<?= $i ?>][unit_price]" value="<?= number_format((float)($l['unit_price'] ?? 0), 4) ?>" class="w-24 rounded border-gray-300 text-sm price-input" onchange="recalcLine(this)"></td>
                            <td class="px-3 py-2"><input type="number" step="0.01" name="lines[<?= $i ?>][discount_amount]" value="<?= number_format((float)($l['discount_amount'] ?? 0), 2) ?>" class="w-20 rounded border-gray-300 text-sm disc-input" onchange="recalcLine(this)"></td>
                            <td class="px-3 py-2"><input type="number" step="0.001" name="lines[<?= $i ?>][tax_percent]" value="<?= number_format((float)($l['tax_percent'] ?? 0), 3) ?>" class="w-20 rounded border-gray-300 text-sm tax-input" onchange="recalcLine(this)"></td>
                            <td class="px-3 py-2 text-sm text-gray-900 line-total">0.00</td>
                            <td class="px-3 py-2"><button type="button" onclick="removeLine(this)" class="text-red-600 hover:text-red-900 text-sm">Remove</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-end">
                <div class="w-64 space-y-1 text-sm">
                    <div class="flex justify-between"><span class="text-gray-600">Subtotal:</span><span id="subtotal" class="text-gray-900">0.00</span></div>
                    <div class="flex justify-between"><span class="text-gray-600">Discount:</span><span id="discount" class="text-gray-900">0.00</span></div>
                    <div class="flex justify-between"><span class="text-gray-600">Tax:</span><span id="tax" class="text-gray-900">0.00</span></div>
                    <div class="flex justify-between font-semibold text-base border-t pt-1"><span class="text-gray-900">Grand Total:</span><span id="grandTotal" class="text-gray-900">0.00</span></div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="index.php" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg text-sm font-medium">Cancel</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg text-sm font-medium">Create Bill</button>
        </div>
    </form>
</div>

<script>
let lineIndex = <?= count($lines) ?>;

document.getElementById('addLineBtn').addEventListener('click', function() {
    const tbody = document.getElementById('linesBody');
    const row = document.createElement('tr');
    row.innerHTML = `<td class="px-3 py-2"><input type="text" name="lines[${lineIndex}][item_name]" class="w-full rounded border-gray-300 text-sm" required></td>
        <td class="px-3 py-2"><span class="text-sm text-gray-600">-</span></td>
        <td class="px-3 py-2"><input type="number" step="0.001" name="lines[${lineIndex}][quantity]" value="1" class="w-20 rounded border-gray-300 text-sm qty-input" onchange="recalcLine(this)"></td>
        <td class="px-3 py-2"><input type="number" step="0.0001" name="lines[${lineIndex}][unit_price]" value="0" class="w-24 rounded border-gray-300 text-sm price-input" onchange="recalcLine(this)"></td>
        <td class="px-3 py-2"><input type="number" step="0.01" name="lines[${lineIndex}][discount_amount]" value="0" class="w-20 rounded border-gray-300 text-sm disc-input" onchange="recalcLine(this)"></td>
        <td class="px-3 py-2"><input type="number" step="0.001" name="lines[${lineIndex}][tax_percent]" value="0" class="w-20 rounded border-gray-300 text-sm tax-input" onchange="recalcLine(this)"></td>
        <td class="px-3 py-2 text-sm text-gray-900 line-total">0.00</td>
        <td class="px-3 py-2"><button type="button" onclick="removeLine(this)" class="text-red-600 hover:text-red-900 text-sm">Remove</button></td>`;
    tbody.appendChild(row);
    lineIndex++;
    recalcAll();
});

function removeLine(btn) {
    btn.closest('tr').remove();
    recalcAll();
}

function recalcLine(input) {
    const row = input.closest('tr');
    const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
    const price = parseFloat(row.querySelector('.price-input').value) || 0;
    const disc = parseFloat(row.querySelector('.discount-input').value) || 0;
    const taxPct = parseFloat(row.querySelector('.tax-input').value) || 0;
    const sub = qty * price;
    const tax = (sub - disc) * taxPct / 100;
    const total = sub - disc + tax;
    row.querySelector('.line-total').textContent = total.toFixed(2);
    recalcAll();
}

function recalcAll() {
    let sub = 0, disc = 0, tax = 0;
    document.querySelectorAll('#linesBody tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty-input')?.value || 0);
        const price = parseFloat(row.querySelector('.price-input')?.value || 0);
        const d = parseFloat(row.querySelector('.disc-input')?.value || 0);
        const t = parseFloat(row.querySelector('.tax-input')?.value || 0);
        const s = qty * price;
        sub += s; disc += d; tax += (s - d) * t / 100;
    });
    document.getElementById('subtotal').textContent = sub.toFixed(2);
    document.getElementById('discount').textContent = disc.toFixed(2);
    document.getElementById('tax').textContent = tax.toFixed(2);
    document.getElementById('grandTotal').textContent = (sub - disc + tax).toFixed(2);
}

document.getElementById('sourceTypeSelect').addEventListener('change', function() {
    const val = this.value;
    const select = document.getElementById('sourceIdSelect');
    select.innerHTML = '<option value="">-- Select --</option>';
});

document.getElementById('loadSourceBtn').addEventListener('click', function() {
    const type = document.getElementById('sourceTypeSelect').value;
    const id = document.getElementById('sourceIdSelect').value;
    if (!id) { alert('Please select a document.'); return; }
    window.location.href = '?source=' + type + '&source_id=' + id;
});

recalcAll();
</script>