<?php
$pageTitle = 'View Invoice';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('invoices.view')) { die('Access Denied'); }

$db = getDB();
$id = $_GET['id'] ?? 0;

$invoice = $db->prepare("SELECT i.*, c.company_name, c.client_code, c.email as client_email, c.phone as client_phone, c.address as client_address 
    FROM invoices i 
    LEFT JOIN clients c ON i.client_id = c.id 
    WHERE i.id = ?");
$invoice->execute([$id]);
$invoice = $invoice->fetch();

if (!$invoice) { die('Invoice not found'); }

$items = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$items->execute([$id]);
$items = $items->fetchAll();

$payments = $db->prepare("SELECT p.*, u.name as received_by_name FROM payments p LEFT JOIN users u ON p.received_by = u.id WHERE p.invoice_id = ? ORDER BY p.payment_date DESC");
$payments->execute([$id]);
$payments = $payments->fetchAll();

$statusColors = ['draft' => 'bg-gray-100 text-gray-800', 'sent' => 'bg-blue-100 text-blue-800', 'partially_paid' => 'bg-yellow-100 text-yellow-800', 'paid' => 'bg-green-100 text-green-800', 'overdue' => 'bg-red-100 text-red-800', 'cancelled' => 'bg-red-200 text-red-800'];
?>

<div class="mb-6 flex justify-between items-center">
    <a href="<?= url('modules/invoices/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Invoices</a>
    <?php if (hasPermission('invoices.edit')): ?>
    <a href="<?= url('modules/invoices/edit.php?id=' . $id . '') ?>" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600"><i class="fas fa-edit mr-2"></i>Edit</a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Invoice Details</h2>
        <div class="space-y-3">
            <div class="flex justify-between"><span class="text-gray-600">Invoice #:</span><span class="font-medium"><?= sanitize($invoice['invoice_number']) ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Status:</span><span class="px-2 py-1 text-xs rounded <?= $statusColors[$invoice['status']] ?? 'bg-gray-100' ?>"><?= ucwords(str_replace('_', ' ', $invoice['status'])) ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Invoice Date:</span><span><?= date('M d, Y', strtotime($invoice['invoice_date'])) ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Due Date:</span><span><?= $invoice['due_date'] ? date('M d, Y', strtotime($invoice['due_date'])) : '-' ?></span></div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Client Information</h2>
        <div class="space-y-3">
            <div class="flex justify-between"><span class="text-gray-600">Company:</span><span class="font-medium"><?= sanitize($invoice['company_name'] ?? '-') ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Email:</span><span><?= sanitize($invoice['client_email'] ?? '-') ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Phone:</span><span><?= sanitize($invoice['client_phone'] ?? '-') ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Address:</span><span><?= sanitize($invoice['client_address'] ?? '-') ?></span></div>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Invoice Items</h2>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Item</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Description</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Qty</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Price</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Tax %</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="px-4 py-3 font-medium"><?= sanitize($item['item_name']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= sanitize($item['description'] ?? '-') ?></td>
                    <td class="px-4 py-3 text-right"><?= $item['quantity'] ?></td>
                    <td class="px-4 py-3 text-right"><?= formatMoney($item['unit_price']) ?></td>
                    <td class="px-4 py-3 text-right"><?= $item['tax_percentage'] ?>%</td>
                    <td class="px-4 py-3 text-right font-medium"><?= formatMoney($item['total']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="mt-4 flex justify-end">
            <div class="w-64 space-y-2">
                <div class="flex justify-between"><span class="text-gray-600">Subtotal:</span><span><?= formatMoney($invoice['subtotal']) ?></span></div>
                <div class="flex justify-between"><span class="text-gray-600">Tax:</span><span><?= formatMoney($invoice['tax_amount']) ?></span></div>
                <div class="flex justify-between"><span class="text-gray-600">Discount:</span><span>-<?= formatMoney($invoice['discount_amount']) ?></span></div>
                <div class="flex justify-between text-lg font-bold border-t pt-2">
                    <span>Total:</span><span><?= formatMoney($invoice['total_amount']) ?></span>
                </div>
                <div class="flex justify-between text-green-600"><span>Paid:</span><span><?= formatMoney($invoice['paid_amount']) ?></span></div>
                <div class="flex justify-between text-red-600 font-bold"><span>Balance:</span><span><?= formatMoney($invoice['balance_amount']) ?></span></div>
            </div>
        </div>
    </div>
</div>

<?php if ($invoice['notes']): ?>
<div class="bg-white rounded-lg shadow mb-6 p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-2">Notes</h2>
    <p class="text-gray-600"><?= nl2br(sanitize($invoice['notes'])) ?></p>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold text-gray-800">Payment History</h2>
        <?php if (hasPermission('payments.create') && $invoice['balance_amount'] > 0): ?>
        <a href="<?= url('modules/payments/create.php?invoice_id=' . $id . '') ?>" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 text-sm"><i class="fas fa-plus mr-1"></i>Add Payment</a>
        <?php endif; ?>
    </div>
    
    <?php if ($payments): ?>
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Date</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Amount</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Method</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Reference</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Received By</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($payments as $p): ?>
            <tr>
                <td class="px-4 py-3"><?= date('M d, Y', strtotime($p['payment_date'])) ?></td>
                <td class="px-4 py-3 font-medium text-green-600"><?= formatMoney($p['amount']) ?></td>
                <td class="px-4 py-3"><?= ucwords(str_replace('_', ' ', $p['payment_method'])) ?></td>
                <td class="px-4 py-3 text-gray-600"><?= sanitize($p['reference_number'] ?? '-') ?></td>
                <td class="px-4 py-3"><?= sanitize($p['received_by_name'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="text-gray-500 text-center py-4">No payments recorded yet</p>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>

