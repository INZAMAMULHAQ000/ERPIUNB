<?php
$pageTitle = 'Edit Vendor Bill';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../tax/helpers/tax_ui.php';

if (!hasPermission('vendor_bills.edit')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('error', 'Invalid bill ID.'); redirect('index.php'); }

// Load bill
$stmt = $db->prepare("SELECT * FROM vendor_bills WHERE id = ?");
$stmt->execute([$id]);
$bill = $stmt->fetch();
if (!$bill) { flash('error', 'Vendor bill not found.'); redirect('index.php'); }
if ($bill['status'] !== 'draft') { flash('error', 'Only draft bills can be edited.'); redirect('view.php?id=' . $id); }

$errors = restoreFormErrors();
if (empty($errors)) { clearOldInput(); }

$vendors = $db->query("SELECT id, vendor_code, vendor_name, default_expense_account_id, default_payable_account_id FROM vendors WHERE status = 'active' ORDER BY vendor_name")->fetchAll();

// Load existing items
$existingItems = $db->prepare("SELECT * FROM vendor_bill_items WHERE bill_id = ? ORDER BY sort_order");
$existingItems->execute([$id]);
$existingItems = $existingItems->fetchAll();

// Chart of accounts for expense dropdown
$expenseAccounts = $db->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE type_id = (SELECT id FROM account_types WHERE type_name = 'Expense' LIMIT 1) AND status = 'active' ORDER BY account_code")->fetchAll();
if (empty($expenseAccounts)) {
    $expenseAccounts = $db->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE status = 'active' LIMIT 50 ORDER BY account_code")->fetchAll();
}

// Default tax type / rate for new lines
$defaultType = TaxService::getDefaultTaxType();
$defaultTypeId = $defaultType ? (int)$defaultType['id'] : 0;
$defRate = TaxService::getDefaultTaxRate($defaultTypeId);
$defaultRateId = $defRate ? (int)$defRate['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid or expired form token.'; }

    $vendor_id = (int)($_POST['vendor_id'] ?? 0);
    $po_id = !empty($_POST['po_id']) ? (int)$_POST['po_id'] : null;
    $grn_id = !empty($_POST['grn_id']) ? (int)$_POST['grn_id'] : null;
    $bill_date = trim($_POST['bill_date'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');
    $currency = trim($_POST['currency'] ?? 'AED');
    $vendor_invoice_number = trim($_POST['vendor_invoice_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $items = $_POST['items'] ?? [];

    if ($vendor_id <= 0) { $errors[] = 'Please select a vendor.'; }
    if (empty($bill_date)) { $errors[] = 'Bill date is required.'; }
    if (empty($due_date)) { $errors[] = 'Due date is required.'; }
    if ($due_date && $bill_date && $due_date < $bill_date) { $errors[] = 'Due date cannot be before bill date.'; }

    if (empty($items) || !is_array($items)) { $errors[] = 'At least one item is required.'; }
    else {
        $validItems = 0;
        foreach ($items as $item) {
            $desc = trim($item['description'] ?? '');
            $qty = (float)str_replace(',', '', $item['quantity'] ?? 0);
            $price = (float)str_replace(',', '', $item['unit_price'] ?? 0);
            if (empty($desc) || $qty <= 0 || $price <= 0) { continue; }
            $validItems++;
        }
        if ($validItems === 0) { $errors[] = 'At least one valid item (with description, quantity, unit price) is required.'; }
    }

    if (!empty($errors)) { saveFormState($errors); to('modules/finance/vendor_bills/edit.php?id=' . $id); }

    $subtotal = 0; $total_tax = 0; $total_discount = 0; $total_amount = 0;

    try {
        $db->beginTransaction();

        $processedItems = []; $idx = 0;
        foreach ($items as $item) {
            $desc = trim($item['description'] ?? '');
            $qty = (float)str_replace(',', '', $item['quantity'] ?? 0);
            $unit = trim($item['unit'] ?? '');
            $price = (float)str_replace(',', '', $item['unit_price'] ?? 0);
            $disc_pct = (float)str_replace(',', '', $item['discount_percent'] ?? 0);
            $tax_rate_id = (int)($item['tax_rate_id'] ?? 0);
            $tax_status = $item['tax_status'] ?? 'taxable';
            $exemption_id = (int)($item['exemption_id'] ?? 0);
            $expense_account_id = !empty($item['expense_account_id']) ? (int)$item['expense_account_id'] : null;
            if (empty($desc) || $qty <= 0 || $price <= 0) { continue; }

            $gross_base = $qty * $price;
            $disc_amt = $gross_base * ($disc_pct / 100);
            $after_disc = $gross_base - $disc_amt;

            $calc = TaxService::calculateLineTax($after_disc, [
                'tax_rate_id'  => $tax_rate_id ?: null,
                'tax_status'   => $tax_status,
                'exemption_id' => $exemption_id ?: null,
                'date'         => $bill_date,
            ]);

            $subtotal += $gross_base;
            $total_discount += $disc_amt;
            $total_tax += (float)$calc['tax_amount'];
            $total_amount += (float)$calc['total_amount'];

            $processedItems[] = [
                'description' => $desc,
                'quantity' => $qty,
                'unit' => $unit,
                'unit_price' => $price,
                'discount_percent' => $disc_pct,
                'tax_type_id' => $calc['tax_type_id'] ? (int)$calc['tax_type_id'] : null,
                'tax_rate_id' => $calc['tax_rate_id'] ? (int)$calc['tax_rate_id'] : null,
                'tax_status' => $calc['tax_status'],
                'taxable_amount' => (float)$calc['net_amount'],
                'tax_exemption_id' => $calc['exemption_id'] ? (int)$calc['exemption_id'] : null,
                'tax_percent' => (float)$calc['rate_percentage'],
                'tax_amount' => (float)$calc['tax_amount'],
                'line_total' => (float)$calc['total_amount'],
                'expense_account_id' => $expense_account_id,
                'sort_order' => $idx++,
            ];
        }

        // Quantity validation against PO/GRN
        if ($po_id) {
            $poCheck = $db->prepare("SELECT id, total_amount, status FROM purchase_orders WHERE id = ?");
            $poCheck->execute([$po_id]);
            $poData = $poCheck->fetch();
            if (!$poData) { $errors[] = 'Selected purchase order not found.'; }
            elseif (in_array($poData['status'], ['draft','cancelled','closed'])) {
                $errors[] = 'Selected purchase order is ' . $poData['status'] . ' and cannot be billed against.';
            } else {
                $billedSoFar = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM vendor_bills WHERE po_id = ? AND id != ? AND status NOT IN ('cancelled')");
                $billedSoFar->execute([$po_id, $id]);
                $alreadyBilled = (float)$billedSoFar->fetchColumn();

                $remaining = (float)$poData['total_amount'] - $alreadyBilled;
                if ($total_amount > $remaining + 0.01) {
                    $errors[] = 'Bill total (' . number_format($total_amount, 2) . ') exceeds remaining unbilled PO amount (' . number_format($remaining, 2) . ').';
                }
            }
        }
        if ($grn_id && empty($errors)) {
            $grnCheck = $db->prepare("SELECT id, status FROM goods_receipt_notes WHERE id = ?");
            $grnCheck->execute([$grn_id]);
            $grnData = $grnCheck->fetch();
            if (!$grnData) { $errors[] = 'Selected GRN not found.'; }
            elseif ($grnData['status'] !== 'completed') {
                $errors[] = 'Selected GRN is not yet completed.';
            } else {
                $grnTotal = $db->prepare("SELECT COALESCE(SUM(line_total), 0) FROM finance_goods_receipt_items WHERE grn_id = ?");
                $grnTotal->execute([$grn_id]);
                $grnTotalAmount = (float)$grnTotal->fetchColumn();

                $billedSoFar = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM vendor_bills WHERE grn_id = ? AND id != ? AND status NOT IN ('cancelled')");
                $billedSoFar->execute([$grn_id, $id]);
                $alreadyBilled = (float)$billedSoFar->fetchColumn();

                $remaining = $grnTotalAmount - $alreadyBilled;
                if ($total_amount > $remaining + 0.01) {
                    $errors[] = 'Bill total (' . number_format($total_amount, 2) . ') exceeds remaining unbilled GRN amount (' . number_format($remaining, 2) . ').';
                }
            }
        }

        if (!empty($errors)) { throw new Exception(implode(' ', $errors)); }

        // Update bill header
        $stmt = $db->prepare("
            UPDATE vendor_bills SET vendor_id = ?, po_id = ?, grn_id = ?, bill_date = ?, due_date = ?, currency = ?,
                vendor_invoice_number = ?,
                subtotal = ?, discount_amount = ?, discount_type = ?, tax_amount = ?, total_amount = ?,
                paid_amount = ?, balance_amount = ?, notes = ?, updated_at = NOW()
            WHERE id = ? AND status = 'draft'
        ");
        $stmt->execute([
            $vendor_id, $po_id, $grn_id, $bill_date, $due_date, $currency,
            $vendor_invoice_number ?: null,
            $subtotal, $total_discount, $total_discount > 0 ? 'percentage' : null,
            $total_tax, $total_amount,
            $bill['paid_amount'], $total_amount - $bill['paid_amount'],
            $notes, $id,
        ]);

        // Delete old items and re-insert
        $db->prepare("DELETE FROM vendor_bill_items WHERE bill_id = ?")->execute([$id]);

        $itemStmt = $db->prepare("
            INSERT INTO vendor_bill_items (bill_id, description, quantity, unit, unit_price, discount_percent,
                tax_type_id, tax_rate_id, tax_status, taxable_amount, tax_exemption_id,
                tax_percent, tax_amount, line_total, expense_account_id, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($processedItems as $pi) {
            $itemStmt->execute([$id, $pi['description'], $pi['quantity'], $pi['unit'], $pi['unit_price'],
                $pi['discount_percent'], $pi['tax_type_id'], $pi['tax_rate_id'], $pi['tax_status'],
                $pi['taxable_amount'], $pi['tax_exemption_id'], $pi['tax_percent'], $pi['tax_amount'], $pi['line_total'],
                $pi['expense_account_id'], $pi['sort_order']]);
        }

        $db->commit();
        logAudit($_SESSION['user_id'], 'Updated vendor bill: ' . $bill['bill_number'], 'vendor_bills');
        flash('success', 'Vendor bill ' . $bill['bill_number'] . ' updated successfully.', 'success');
        redirect('view.php?id=' . $id);
    } catch (Exception $e) {
        $db->rollBack();
        flash('error', 'Error: ' . $e->getMessage());
        to('modules/finance/vendor_bills/edit.php?id=' . $id);
    }
}
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Edit Vendor Bill</h1>
        <p class="text-gray-600"><?= sanitize($bill['bill_number']) ?></p>
    </div>
    <a href="view.php?id=<?= $id ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Bill</a>
</div>

<?php if (!empty($errors)): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6"><?php foreach ($errors as $e): ?><p><?= sanitize($e) ?></p><?php endforeach; ?></div>
<?php endif; ?>

<form method="POST" id="billForm">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Bill Number</label>
                <input type="text" value="<?= sanitize($bill['bill_number']) ?>" class="w-full px-3 py-2 border rounded bg-gray-50 text-gray-500" readonly>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Vendor <span class="text-red-500">*</span></label>
                <select name="vendor_id" id="vendorSelect" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                    <option value="">-- Select Vendor --</option>
                    <?php foreach ($vendors as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= old('vendor_id', $bill['vendor_id']) == $v['id'] ? 'selected' : '' ?>><?= sanitize($v['vendor_name'] . ' (' . $v['vendor_code'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Bill Date <span class="text-red-500">*</span></label>
                <input type="date" name="bill_date" value="<?= old('bill_date', $bill['bill_date']) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Due Date <span class="text-red-500">*</span></label>
                <input type="date" name="due_date" value="<?= old('due_date', $bill['due_date']) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                <select name="currency" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="AED" <?= (old('currency', $bill['currency']) === 'AED') ? 'selected' : '' ?>>AED</option>
                    <option value="USD" <?= old('currency', $bill['currency']) === 'USD' ? 'selected' : '' ?>>USD</option>
                    <option value="EUR" <?= old('currency', $bill['currency']) === 'EUR' ? 'selected' : '' ?>>EUR</option>
                    <option value="GBP" <?= old('currency', $bill['currency']) === 'GBP' ? 'selected' : '' ?>>GBP</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Vendor Invoice #</label>
                <input type="text" name="vendor_invoice_number" value="<?= sanitize(old('vendor_invoice_number', $bill['vendor_invoice_number'])) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="Vendor's invoice reference">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Purchase Order</label>
                <select name="po_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Optional --</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">GRN</label>
                <select name="grn_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Optional --</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Items -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Line Items</h3>
            <button type="button" onclick="addItem()" class="bg-green-600 text-white px-3 py-1.5 rounded text-sm hover:bg-green-700 transition"><i class="fas fa-plus mr-1"></i>Add Item</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full" id="itemsTable">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase" style="min-width:180px">Description</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Disc %</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Tax</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Tax Amt</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Expense Acct</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200" id="itemsBody">
                    <?php if (!empty($existingItems)): ?>
                    <?php foreach ($existingItems as $i => $item): ?>
                    <tr class="item-row">
                        <td class="px-3 py-2 text-sm text-gray-500 item-num"><?= $i + 1 ?></td>
                        <td class="px-3 py-2"><input type="text" name="items[<?= $i ?>][description]" value="<?= sanitize($item['description']) ?>" class="w-full px-2 py-2 border rounded text-sm" placeholder="Item description" required></td>
                        <td class="px-3 py-2"><input type="number" name="items[<?= $i ?>][quantity]" value="<?= (float)$item['quantity'] ?>" step="0.01" min="0.01" class="w-20 px-2 py-2 border rounded text-sm text-right qty-input" required></td>
                        <td class="px-3 py-2"><input type="text" name="items[<?= $i ?>][unit]" value="<?= sanitize($item['unit']) ?>" class="w-16 px-2 py-2 border rounded text-sm" placeholder="pcs"></td>
                        <td class="px-3 py-2"><input type="number" name="items[<?= $i ?>][unit_price]" value="<?= (float)$item['unit_price'] ?>" step="0.0001" min="0" class="w-28 px-2 py-2 border rounded text-sm text-right price-input" required></td>
                        <td class="px-3 py-2"><input type="number" name="items[<?= $i ?>][discount_percent]" value="<?= (float)$item['discount_percent'] ?>" step="0.01" min="0" max="100" class="w-16 px-2 py-2 border rounded text-sm text-right disc-input"></td>
                        <td class="px-3 py-2">
                            <input type="hidden" class="base-before-tax" value="<?= (float)$item['taxable_amount'] ?>">
                            <?= taxui_doc_line_picker(
                                    'items[' . $i . ']',
                                    $item['tax_rate_id'] ? (int)$item['tax_rate_id'] : null,
                                    $item['tax_type_id'] ? (int)$item['tax_type_id'] : $defaultTypeId,
                                    $item['tax_status'] ?? 'taxable',
                                    $item['tax_exemption_id'] ? (int)$item['tax_exemption_id'] : null
                                ) ?>
                        </td>
                        <td class="px-3 py-2"><input type="text" class="w-24 px-2 py-2 border-0 bg-transparent text-right font-mono text-sm line-tax" value="<?= number_format((float)$item['tax_amount'], 2) ?>" readonly></td>
                        <td class="px-3 py-2">
                            <select name="items[<?= $i ?>][expense_account_id]" class="w-36 px-2 py-2 border rounded text-sm">
                                <option value="">Default</option>
                                <?php foreach ($expenseAccounts as $a): ?>
                                <option value="<?= $a['id'] ?>" <?= (int)$item['expense_account_id'] === (int)$a['id'] ? 'selected' : '' ?>><?= sanitize($a['account_code']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="px-3 py-2"><input type="text" class="w-28 px-2 py-2 border-0 bg-transparent text-right font-mono font-bold text-sm line-total" value="<?= number_format((float)$item['line_total'], 2) ?>" readonly></td>
                        <td class="px-3 py-2 text-center"><button type="button" onclick="removeItem(this)" class="text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr class="item-row">
                        <td class="px-3 py-2 text-sm text-gray-500 item-num">1</td>
                        <td class="px-3 py-2"><input type="text" name="items[0][description]" class="w-full px-2 py-2 border rounded text-sm" placeholder="Item description" required></td>
                        <td class="px-3 py-2"><input type="number" name="items[0][quantity]" value="1" step="0.01" min="0.01" class="w-20 px-2 py-2 border rounded text-sm text-right qty-input" required></td>
                        <td class="px-3 py-2"><input type="text" name="items[0][unit]" class="w-16 px-2 py-2 border rounded text-sm" placeholder="pcs"></td>
                        <td class="px-3 py-2"><input type="number" name="items[0][unit_price]" step="0.0001" min="0" value="0" class="w-28 px-2 py-2 border rounded text-sm text-right price-input" required></td>
                        <td class="px-3 py-2"><input type="number" name="items[0][discount_percent]" step="0.01" min="0" max="100" value="0" class="w-16 px-2 py-2 border rounded text-sm text-right disc-input"></td>
                        <td class="px-3 py-2">
                            <input type="hidden" class="base-before-tax" value="0">
                            <?= taxui_doc_line_picker('items[0]', $defaultRateId, $defaultTypeId) ?>
                        </td>
                        <td class="px-3 py-2"><input type="text" class="w-24 px-2 py-2 border-0 bg-transparent text-right font-mono text-sm line-tax" value="0.00" readonly></td>
                        <td class="px-3 py-2">
                            <select name="items[0][expense_account_id]" class="w-36 px-2 py-2 border rounded text-sm">
                                <option value="">Default</option>
                                <?php foreach ($expenseAccounts as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= sanitize($a['account_code']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="px-3 py-2"><input type="text" class="w-28 px-2 py-2 border-0 bg-transparent text-right font-mono font-bold text-sm line-total" value="0.00" readonly></td>
                        <td class="px-3 py-2 text-center"><button type="button" onclick="removeItem(this)" class="text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr><td colspan="9" class="px-3 py-2 text-right text-sm font-medium text-gray-700">Subtotal (before discount):</td>
                        <td class="px-3 py-2 font-mono text-sm font-bold" id="displaySubtotal"><?= number_format((float)$bill['subtotal'], 2) ?></td>
                        <td></td></tr>
                    <tr><td colspan="9" class="px-3 py-2 text-right text-sm text-gray-600">Discount:</td>
                        <td class="px-3 py-2 font-mono text-sm text-gray-600" id="displayDiscount"><?= number_format((float)$bill['discount_amount'], 2) ?></td>
                        <td></td></tr>
                    <tr><td colspan="9" class="px-3 py-2 text-right text-sm text-gray-600">Tax:</td>
                        <td class="px-3 py-2 font-mono text-sm text-gray-600" id="displayTax"><?= number_format((float)$bill['tax_amount'], 2) ?></td>
                        <td></td></tr>
                    <tr><td colspan="9" class="px-3 py-2 text-right text-sm font-bold text-gray-900">Grand Total:</td>
                        <td class="px-3 py-2 font-mono text-sm font-bold text-lg text-gray-900" id="displayTotal"><?= number_format((float)$bill['total_amount'], 2) ?></td>
                        <td></td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <input type="hidden" name="total_subtotal" id="subtotal_hidden" value="<?= (float)$bill['subtotal'] ?>">
    <input type="hidden" name="total_discount" id="discount_hidden" value="<?= (float)$bill['discount_amount'] ?>">
    <input type="hidden" name="total_tax" id="tax_hidden" value="<?= (float)$bill['tax_amount'] ?>">
    <input type="hidden" name="total_amount" id="total_hidden" value="<?= (float)$bill['total_amount'] ?>">

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
        <textarea name="notes" rows="2" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('notes', $bill['notes']) ?></textarea>
    </div>

    <div class="flex justify-end gap-4 mb-8">
        <a href="view.php?id=<?= $id ?>" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400 transition">Cancel</a>
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-save mr-2"></i>Update Bill</button>
    </div>
</form>

<script src="../../../tax/helpers/tax_doc.js"></script>
<script>
window.TAX_INCLUSIVE = <?= TaxService::pricesInclusive() ? '1' : '0' ?>;
let itemIndex = <?= count($existingItems) ?>;

document.getElementById('vendorSelect').addEventListener('change', function() {
    const vendorId = this.value;
    const poSelect = document.querySelector('select[name="po_id"]');
    const grnSelect = document.querySelector('select[name="grn_id"]');
    const currentPo = '<?= $bill['po_id'] ?? '' ?>';
    const currentGrn = '<?= $bill['grn_id'] ?? '' ?>';
    poSelect.innerHTML = '<option value="">-- Optional --</option>';
    grnSelect.innerHTML = '<option value="">-- Optional --</option>';
    if (!vendorId) return;

    fetch('ajax_get_po_grn.php?vendor_id=' + vendorId)
        .then(r => r.json())
        .then(data => {
            if (data.pos && data.pos.length) {
                data.pos.forEach(po => {
                    const remaining = parseFloat(po.total_amount) - parseFloat(po.total_billed);
                    const opt = document.createElement('option');
                    opt.value = po.id;
                    opt.textContent = po.po_number + ' (' + po.status.replace('_', ' ') + ' - ' + remaining.toFixed(2) + ' unbilled)';
                    if (po.id == currentPo) opt.selected = true;
                    poSelect.appendChild(opt);
                });
            }
            if (data.grns && data.grns.length) {
                data.grns.forEach(grn => {
                    const opt = document.createElement('option');
                    opt.value = grn.id;
                    opt.textContent = grn.grn_number + ' (' + grn.receipt_date + ')';
                    if (grn.id == currentGrn) opt.selected = true;
                    grnSelect.appendChild(opt);
                });
            }
        })
        .catch(() => {});
});
// Trigger on load to populate if vendor already selected
document.addEventListener('DOMContentLoaded', function() {
    const vendorSelect = document.getElementById('vendorSelect');
    if (vendorSelect.value) vendorSelect.dispatchEvent(new Event('change'));

    document.querySelectorAll('#itemsBody .item-row').forEach(function(row) {
        window.wireTaxRow(row);
    });
    recalcDocTotals('itemsTable');
});

function addItem() {
    const tbody = document.getElementById('itemsBody');
    const first = tbody.querySelector('.item-row');
    if (!first) return;

    const row = first.cloneNode(true);
    const i = itemIndex;
    row.querySelectorAll('input, select').forEach(function(input) {
        const name = input.getAttribute('name');
        if (name && name.indexOf('[') !== -1) {
            input.setAttribute('name', name.replace(/\[\d+\]/g, '[' + i + ']'));
        }
        const tag = input.tagName.toLowerCase();
        if (tag === 'input') {
            if (input.type === 'number') {
                if (input.classList.contains('qty-input')) input.value = '1';
                else if (input.classList.contains('price-input')) input.value = '0';
                else if (input.classList.contains('disc-input')) input.value = '0';
            } else if (input.type === 'text') {
                if (input.classList.contains('line-total') || input.classList.contains('line-tax')) input.value = '0.00';
                else input.value = '';
            } else if (input.type === 'hidden') {
                input.value = '0';
            }
        } else if (tag === 'select') {
            if (input.classList.contains('tax-doc-rate')) input.selectedIndex = 0;
            else if (input.classList.contains('tax-doc-status')) input.selectedIndex = 0;
            else if (input.classList.contains('tax-doc-exemption')) input.selectedIndex = 0;
            else input.selectedIndex = 0;
        }
    });
    const expSel = row.querySelector('.tax-doc-exemption');
    if (expSel) expSel.hidden = true;

    window.wireTaxRow(row);
    tbody.appendChild(row);
    itemIndex++;
    renumberItems();
    recalcDocTotals('itemsTable');
}
function removeItem(btn) {
    const row = btn.closest('tr');
    if (document.querySelectorAll('.item-row').length <= 1) return;
    row.remove(); renumberItems(); recalcDocTotals('itemsTable');
}
function renumberItems() {
    document.querySelectorAll('.item-row').forEach((row, idx) => { row.querySelector('.item-num').textContent = idx + 1; });
}

document.getElementById('billForm').addEventListener('submit', function(e) {
    const total = parseFloat(document.getElementById('total_hidden').value) || 0;
    if (total <= 0) {
        e.preventDefault();
        alert('Bill total must be greater than zero. Please add valid items.');
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>
