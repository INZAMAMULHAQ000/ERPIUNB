<?php
$pageTitle = 'Edit Invoice';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('invoices.edit')) { die('Access Denied'); }

$db = getDB();
$id = $_GET['id'] ?? 0;

$invoice = $db->prepare("SELECT * FROM invoices WHERE id = ?");
$invoice->execute([$id]);
$invoice = $invoice->fetch();

if (!$invoice) { die('Invoice not found'); }

$items = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$items->execute([$id]);
$items = $items->fetchAll();

$clients = $db->query("SELECT id, company_name FROM clients WHERE status = 'active' ORDER BY company_name")->fetchAll();
$projects = $db->query("SELECT id, project_name FROM projects WHERE status IN ('active','planning') ORDER BY project_name")->fetchAll();
$taxes = $db->query("SELECT * FROM tax_settings WHERE is_active = 1")->fetchAll();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'client_id' => $_POST['client_id'] ?? '',
        'project_id' => $_POST['project_id'] ?? '',
        'invoice_date' => $_POST['invoice_date'] ?? '',
        'due_date' => $_POST['due_date'] ?? '',
        'notes' => $_POST['notes'] ?? ''
    ];
    
    $errors = validate($data, ['client_id' => 'required', 'invoice_date' => 'required']);
    
    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/invoices/edit.php?id=' . $id);
    }

    
        $subtotal = 0;
        
        $stmt = $db->prepare("UPDATE invoices SET client_id = ?, project_id = ?, invoice_date = ?, due_date = ?, notes = ?, status = ? WHERE id = ?");
        $stmt->execute([$data['client_id'], $data['project_id'] ?: null, $data['invoice_date'], $data['due_date'] ?: null, $data['notes'], $_POST['status'], $id]);
        
        $db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$id]);
        
        if (!empty($_POST['item_name'])) {
            $itemStmt = $db->prepare("INSERT INTO invoice_items (invoice_id, item_name, description, quantity, unit_price, tax_percentage, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($_POST['item_name'] as $i => $itemName) {
                if ($itemName) {
                    $qty = $_POST['quantity'][$i] ?: 1;
                    $price = $_POST['unit_price'][$i] ?: 0;
                    $tax = $_POST['tax_percentage'][$i] ?: 0;
                    $lineTotal = $qty * $price;
                    $lineTax = $lineTotal * ($tax / 100);
                    $lineTotal += $lineTax;
                    $subtotal += ($qty * $price);
                    
                    $itemStmt->execute([$id, $itemName, $_POST['description'][$i] ?? '', $qty, $price, $tax, $lineTotal]);
                }
            }
        }
        
        $taxAmount = $subtotal * 0.18;
        $totalAmount = $subtotal + $taxAmount;
        $balanceAmount = $totalAmount - $invoice['paid_amount'];
        
        $updateStmt = $db->prepare("UPDATE invoices SET subtotal = ?, tax_amount = ?, total_amount = ?, balance_amount = ? WHERE id = ?");
        $updateStmt->execute([$subtotal, $taxAmount, $totalAmount, $balanceAmount, $id]);
        
        logAudit($_SESSION['user_id'], 'update_invoice', 'invoices');
        flash('success', 'Invoice updated successfully');
        to('http://localhost/ERP/modules/invoices/index.php');
    

}
?>

<div class="mb-6">
    <a href="<?= url('modules/invoices/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Invoices</a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Edit Invoice - <?= sanitize($invoice['invoice_number']) ?></h2>
    
    <?php if ($errors): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
    
    <form method="POST" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Client <span class="text-red-600">*</span></labe>
                <select name="client_id" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Client</option>
                    <?php foreach ($clients as $c): ?><option value="' . $c['id'] . '" <?= $invoice['client_id'] == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['company_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                <select name="project_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Project</option>
                    <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>" <?= $invoice['project_id'] == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['project_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Date <span class="text-red-600">*</span></labe>
                <input type="date" name="invoice_date" value="<?= $invoice['invoice_date'] ?>" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                <input type="date" name="due_date" value="<?= $invoice['due_date'] ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="draft" <?= $invoice['status'] == 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="sent" <?= $invoice['status'] == 'sent' ? 'selected' : '' ?>>Sent</option>
                    <option value="partially_paid" <?= $invoice['status'] == 'partially_paid' ? 'selected' : '' ?>>Partially Paid</option>
                    <option value="paid" <?= $invoice['status'] == 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="overdue" <?= $invoice['status'] == 'overdue' ? 'selected' : '' ?>>Overdue</option>
                    <option value="cancelled" <?= $invoice['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
        </div>
        
        <div>
            <h3 class="text-lg font-medium text-gray-800 mb-3">Invoice Items</h3>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Item</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Description</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 w-24">Qty</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 w-32">Price</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 w-24">Tax %</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 w-32">Total</th>
                        <th class="px-3 py-2 w-10"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    <?php if (empty($items)): ?>
                    <tr>
                        <td class="px-2 py-2"><input type="text" name="item_name[]" placeholder="Item name" class="w-full px-2 py-1 border rounded"></td>
                        <td class="px-2 py-2"><input type="text" name="description[]" placeholder="Description" class="w-full px-2 py-1 border rounded"></td>
                        <td class="px-2 py-2"><input type="number" name="quantity[]" value="1" min="1" class="w-full px-2 py-1 border rounded" onchange="calculateTotals()"></td>
                        <td class="px-2 py-2"><input type="number" name="unit_price[]" value="0" min="0" step="0.01" class="w-full px-2 py-1 border rounded" onchange="calculateTotals()"></td>
                        <td class="px-2 py-2"><input type="number" name="tax_percentage[]" value="18" min="0" class="w-full px-2 py-1 border rounded" onchange="calculateTotals()"></td>
                        <td class="px-2 py-2"><input type="text" name="line_total[]" value="0" readonly class="w-full px-2 py-1 border rounded bg-gray-50"></td>
                        <td class="px-2 py-2"><button type="button" onclick="removeRow(this)" class="text-red-600 hover:text-red-900"><i class="fas fa-trash"></i></button></td>
                    </tr>
                    <?php else: foreach ($items as $item): ?>
                    <tr>
                        <td class="px-2 py-2"><input type="text" name="item_name[]" value="<?= sanitize($item['item_name']) ?>" class="w-full px-2 py-1 border rounded"></td>
                        <td class="px-2 py-2"><input type="text" name="description[]" value="<?= sanitize($item['description']) ?>" class="w-full px-2 py-1 border rounded"></td>
                        <td class="px-2 py-2"><input type="number" name="quantity[]" value="<?= $item['quantity'] ?>" min="1" class="w-full px-2 py-1 border rounded" onchange="calculateTotals()"></td>
                        <td class="px-2 py-2"><input type="number" name="unit_price[]" value="<?= $item['unit_price'] ?>" min="0" step="0.01" class="w-full px-2 py-1 border rounded" onchange="calculateTotals()"></td>
                        <td class="px-2 py-2"><input type="number" name="tax_percentage[]" value="<?= $item['tax_percentage'] ?>" min="0" class="w-full px-2 py-1 border rounded" onchange="calculateTotals()"></td>
                        <td class="px-2 py-2"><input type="text" name="line_total[]" value="<?= $item['total'] ?>" readonly class="w-full px-2 py-1 border rounded bg-gray-50"></td>
                        <td class="px-2 py-2"><button type="button" onclick="removeRow(this)" class="text-red-600 hover:text-red-900"><i class="fas fa-trash"></i></button></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            <button type="button" onclick="addRow()" class="mt-2 text-blue-600 hover:text-blue-900 text-sm"><i class="fas fa-plus mr-1"></i>Add Item</button>
        </div>
        
        <div class="border-t pt-4">
            <div class="flex justify-end">
                <div class="w-64 space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subtotal:</span>
                        <span class="font-medium" id="subtotalDisplay"><?= formatMoney($invoice['subtotal']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Tax (18%):</span>
                        <span class="font-medium" id="taxDisplay"><?= formatMoney($invoice['tax_amount']) ?></span>
                    </div>
                    <div class="flex justify-between text-lg font-bold border-t pt-2">
                        <span>Total:</span>
                        <span id="totalDisplay"><?= formatMoney($invoice['total_amount']) ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea name="notes" rows="2" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= sanitize($invoice['notes']) ?></textarea>
        </div>
        
        <div class="pt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">Update Invoice</button>
            <a href="<?= url('modules/invoices/index.php') ?>" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>

<script>
function addRow() {
    const tbody = document.getElementById('itemsBody');
    const row = tbody.insertRow();
    row.innerHTML = `<td class="px-2 py-2"><input type="text" name="item_name[]" placeholder="Item name" class="w-full px-2 py-1 border rounded"></td>
        <td class="px-2 py-2"><input type="text" name="description[]" placeholder="Description" class="w-full px-2 py-1 border rounded"></td>
        <td class="px-2 py-2"><input type="number" name="quantity[]" value="1" min="1" class="w-full px-2 py-1 border rounded" onchange="calculateTotals()"></td>
        <td class="px-2 py-2"><input type="number" name="unit_price[]" value="0" min="0" step="0.01" class="w-full px-2 py-1 border rounded" onchange="calculateTotals()"></td>
        <td class="px-2 py-2"><input type="number" name="tax_percentage[]" value="18" min="0" class="w-full px-2 py-1 border rounded" onchange="calculateTotals()"></td>
        <td class="px-2 py-2"><input type="text" name="line_total[]" value="0" readonly class="w-full px-2 py-1 border rounded bg-gray-50"></td>
        <td class="px-2 py-2"><button type="button" onclick="removeRow(this)" class="text-red-600 hover:text-red-900"><i class="fas fa-trash"></i></button></td>`;
}

function removeRow(btn) {
    const row = btn.closest('tr');
    if (document.querySelectorAll('#itemsBody tr').length > 1) {
        row.remove();
        calculateTotals();
    }
}

function calculateTotals() {
    let subtotal = 0;
    const rows = document.querySelectorAll('#itemsBody tr');
    rows.forEach(row => {
        const qty = parseFloat(row.querySelector('[name="quantity[]"]').value) || 0;
        const price = parseFloat(row.querySelector('[name="unit_price[]"]').value) || 0;
        const tax = parseFloat(row.querySelector('[name="tax_percentage[]"]').value) || 0;
        const lineSubtotal = qty * price;
        const lineTax = lineSubtotal * (tax / 100);
        const lineTotal = lineSubtotal + lineTax;
        subtotal += lineSubtotal;
        row.querySelector('[name="line_total[]"]').value = lineTotal.toFixed(2);
    });
    
    const tax = subtotal * 0.18;
    const total = subtotal + tax;
    
    const currencySymbol = '<?= getCurrencies()[getActiveCurrency()]['symbol'] ?>';
    document.getElementById('subtotalDisplay').textContent = currencySymbol + subtotal.toFixed(2);
    document.getElementById('taxDisplay').textContent = currencySymbol + tax.toFixed(2);
    document.getElementById('totalDisplay').textContent = currencySymbol + total.toFixed(2);
}
</script>

<?php require_once '../../includes/footer.php'; ?>

