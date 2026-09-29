<?php
$pageTitle = 'Edit Vendor Bill';
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('procurement.bills.edit')) { die('Access Denied'); }

$db = getDB();
$billId = (int)($_GET['id'] ?? 0);
if ($billId <= 0) { flash('error', 'Invalid bill ID.'); redirect('index.php'); }

$bill = BillService::getBill($db, $billId);
if (!$bill) { flash('error', 'Bill not found.'); redirect('index.php'); }
if (!BillService::canEdit($bill)) { flash('error', 'Bill cannot be edited in its current status.'); redirect('view.php?id=' . $billId); }

$items = BillService::getItems($db, $billId);
$vendors = $db->query("SELECT id, vendor_name FROM vendors WHERE status='active' ORDER BY vendor_name")->fetchAll();
$companies = $db->query("SELECT id, company_name FROM companies WHERE status='active' ORDER BY company_name")->fetchAll();

$errors = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Edit Vendor Bill: <?= sanitize($bill['bill_number']) ?></h1>

    <?php if ($errors): foreach ($errors as $e): if ($e['type'] === 'error'): ?>
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded"><?= sanitize($e['message']) ?></div>
    <?php endif; endforeach; endif; ?>

    <form method="POST" action="update.php" id="billForm" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="id" value="<?= $bill['id'] ?>">

        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Bill Header</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Vendor Invoice Number</label>
                    <input type="text" name="vendor_invoice_number" value="<?= sanitize($bill['vendor_invoice_number']) ?>"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Vendor <span class="text-red-600">*</span></labe>
                    <select name="vendor_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" required>
                        <option value="">Select Vendor</option>
                        <?php foreach ($vendors as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= (int)$bill['vendor_id'] === (int)$v['id'] ? 'selected' : '' ?>><?= sanitize($v['vendor_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Company <span class="text-red-600">*</span></labe>
                    <select name="company_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" required>
                        <option value="">Select Company</option>
                        <?php foreach ($companies as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (int)$bill['company_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= sanitize($c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Bill Date <span class="text-red-600">*</span></labe>
                    <input type="date" name="bill_date" value="<?= sanitize($bill['bill_date']) ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Due Date <span class="text-red-600">*</span></labe>
                    <input type="date" name="due_date" value="<?= sanitize($bill['due_date']) ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Currency</label>
                    <select name="currency" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="AED" <?= $bill['currency'] === 'AED' ? 'selected' : '' ?>>AED</option>
                        <option value="USD" <?= $bill['currency'] === 'USD' ? 'selected' : '' ?>>USD</option>
                        <option value="EUR" <?= $bill['currency'] === 'EUR' ? 'selected' : '' ?>>EUR</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Payment Terms</label>
                    <input type="text" name="payment_terms" value="<?= sanitize($bill['payment_terms']) ?>"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"><?= sanitize($bill['notes']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Bill Items</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="linesTable">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Discount</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tax %</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Line Total</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="linesBody">
                        <?php foreach ($items as $i => $it): ?>
                        <tr>
                            <td class="px-3 py-2"><input type="text" name="lines[<?= $i ?>][item_name]" value="<?= sanitize($it['description']) ?>" class="w-full rounded border-gray-300 text-sm" required><input type="hidden" name="lines[<?= $i ?>][procurement_po_item_id]" value="<?= (int)($it['procurement_po_item_id'] ?? 0) ?>"><input type="hidden" name="lines[<?= $i ?>][procurement_gr_item_id]" value="<?= (int)($it['procurement_gr_item_id'] ?? 0) ?>"><input type="hidden" name="lines[<?= $i ?>][procurement_sr_item_id]" value="<?= (int)($it['procurement_sr_item_id'] ?? 0) ?>"></td>
                            <td class="px-3 py-2"><input type="number" step="0.001" name="lines[<?= $i ?>][quantity]" value="<?= number_format((float)$it['quantity'], 3) ?>" class="w-20 rounded border-gray-300 text-sm qty-input" onchange="recalcLine(this)"></td>
                            <td class="px-3 py-2"><input type="number" step="0.0001" name="lines[<?= $i ?>][unit_price]" value="<?= number_format((float)$it['unit_price'], 4) ?>" class="w-24 rounded border-gray-300 text-sm price-input" onchange="recalcLine(this)"></td>
                            <td class="px-3 py-2"><input type="number" step="0.01" name="lines[<?= $i ?>][discount_amount]" value="<?= number_format((float)$it['discount_amount'], 2) ?>" class="w-20 rounded border-gray-300 text-sm disc-input" onchange="recalcLine(this)"></td>
                            <td class="px-3 py-2"><input type="number" step="0.001" name="lines[<?= $i ?>][tax_percent]" value="<?= number_format((float)$it['tax_percent'], 3) ?>" class="w-20 rounded border-gray-300 text-sm tax-input" onchange="recalcLine(this)"></td>
                            <td class="px-3 py-2 text-sm text-gray-900 line-total"><?= number_format((float)$it['line_total'], 2) ?></td>
                            <td class="px-3 py-2"><button type="button" onclick="removeLine(this)" class="text-red-600 hover:text-red-900 text-sm">Remove</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-end">
                <div class="w-64 space-y-1 text-sm">
                    <div class="flex justify-between"><span class="text-gray-600">Subtotal:</span><span id="subtotal"><?= number_format((float)$bill['subtotal'], 2) ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-600">Discount:</span><span id="discount"><?= number_format((float)$bill['discount_amount'], 2) ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-600">Tax:</span><span id="tax"><?= number_format((float)$bill['tax_amount'], 2) ?></span></div>
                    <div class="flex justify-between font-semibold text-base border-t pt-1"><span class="text-gray-900">Grand Total:</span><span id="grandTotal"><?= number_format((float)$bill['total_amount'], 2) ?></span></div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="view.php?id=<?= $bill['id'] ?>" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg text-sm font-medium">Cancel</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg text-sm font-medium">Update Bill</button>
        </div>
    </form>
</div>

<script>
let lineIndex = <?= count($items) ?>;

document.getElementById('addLineBtn')?.addEventListener('click', function() {
    const tbody = document.getElementById('linesBody');
    const row = document.createElement('tr');
    row.innerHTML = `<td class="px-3 py-2"><input type="text" name="lines[${lineIndex}][item_name]" class="w-full rounded border-gray-300 text-sm" required></td>
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

function removeLine(btn) { btn.closest('tr').remove(); recalcAll(); }

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

recalcAll();
</script>