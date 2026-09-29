<?php
$pageTitle = 'Edit Payment';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('payments.edit')) { die('Access Denied'); }

$db = getDB();
$id = $_GET['id'] ?? 0;

$payment = $db->prepare("SELECT * FROM payments WHERE id = ?");
$payment->execute([$id]);
$payment = $payment->fetch();

if (!$payment) { die('Payment not found'); }

$invoice = $db->prepare("SELECT i.*, c.company_name FROM invoices i LEFT JOIN clients c ON i.client_id = c.id WHERE i.id = ?");
$invoice->execute([$payment['invoice_id']]);
$invoice = $invoice->fetch();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'payment_date' => $_POST['payment_date'] ?? '',
        'amount' => $_POST['amount'] ?? '',
        'payment_method' => $_POST['payment_method'] ?? '',
        'reference_number' => $_POST['reference_number'] ?? ''
    ];
    
    $errors = validate($data, ['payment_date' => 'required', 'amount' => 'required']);
    
    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/payments/edit.php?id=' . $id);
    }

    
        $oldAmount = floatval($payment['amount']);
        $newAmount = floatval($data['amount']);
        
        $stmt = $db->prepare("UPDATE payments SET payment_date = ?, amount = ?, payment_method = ?, reference_number = ?, notes = ? WHERE id = ?");
        $stmt->execute([$data['payment_date'], $newAmount, $data['payment_method'], $data['reference_number'], $_POST['notes'], $id]);
        
        $invoiceAmount = floatval($invoice['total_amount']);
        $paidDiff = $newAmount - $oldAmount;
        $newPaid = floatval($invoice['paid_amount']) + $paidDiff;
        $newBalance = $invoiceAmount - $newPaid;
        $newStatus = $newBalance <= 0 ? 'paid' : ($newPaid > 0 ? 'partially_paid' : $invoice['status']);
        
        $updateStmt = $db->prepare("UPDATE invoices SET paid_amount = ?, balance_amount = ?, status = ? WHERE id = ?");
        $updateStmt->execute([max(0, $newPaid), max(0, $newBalance), $newStatus, $payment['invoice_id']]);
        
        logAudit($_SESSION['user_id'], 'update_payment', 'payments');
        flash('success', 'Payment updated successfully');
        to('http://localhost/ERP/modules/payments/index.php');
    

}
?>

<div class="mb-6">
    <a href="<?= url('modules/payments/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Payments</a>
</div>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <h2 class="text-xl font-semibold mb-6">Edit Payment</h2>
    
    <div class="bg-gray-50 p-4 rounded mb-6">
        <div class="text-sm text-gray-600">Invoice: <span class="font-medium text-gray-900"><?= sanitize($invoice['invoice_number']) ?></span></div>
        <div class="text-sm text-gray-600">Client: <span class="font-medium text-gray-900"><?= sanitize($invoice['company_name']) ?></span></div>
        <div class="text-sm text-gray-600">Invoice Total: <span class="font-medium text-gray-900"><?= formatMoney($invoice['total_amount']) ?></span></div>
    </div>
    
    <?php if ($errors): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
    
    <form method="POST" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date <span class="text-red-600">*</span></labe>
                <input type="date" name="payment_date" value="<?= old('payment_date', $payment['payment_date']) ?>" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Amount <span class="text-red-600">*</span></labe>
                <input type="number" name="amount" value="<?= old('amount', $payment['amount']) ?>" min="0.01" step="0.01" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                <select name="payment_method" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="bank_transfer" <?= $payment['payment_method'] == 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                    <option value="cash" <?= $payment['payment_method'] == 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="upi" <?= $payment['payment_method'] == 'upi' ? 'selected' : '' ?>>UPI</option>
                    <option value="credit_card" <?= $payment['payment_method'] == 'credit_card' ? 'selected' : '' ?>>Credit Card</option>
                    <option value="paypal" <?= $payment['payment_method'] == 'paypal' ? 'selected' : '' ?>>PayPal</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
                <input type="text" name="reference_number" value="<?= old('reference_number', $payment['reference_number']) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea name="notes" rows="2" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('notes', $payment['notes']) ?></textarea>
        </div>
        
        <div class="pt-4">
            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition">Update Payment</button>
            <a href="<?= url('modules/payments/index.php') ?>" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
