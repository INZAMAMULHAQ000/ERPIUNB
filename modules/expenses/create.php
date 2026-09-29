<?php
$pageTitle = 'Add Expense';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('expenses.create')) { die('Access Denied'); }

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

$categories = $db->query("SELECT id, category_name FROM expense_categories WHERE status = 'active' ORDER BY category_name")->fetchAll();

function generateExpenseCode($db) {
    $year = date('Y');
    $month = date('m');
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM expenses WHERE expense_code LIKE 'EXP$year$month%'");
    return "EXP" . $year . $month . str_pad($stmt->fetch()['cnt'] + 1, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'category_id' => $_POST['category_id'] ?? '',
        'expense_date' => $_POST['expense_date'] ?? '',
        'amount' => $_POST['amount'] ?? '',
        'vendor_name' => $_POST['vendor_name'] ?? ''
    ];
    
    $errors = validate($data, ['category_id' => 'required', 'expense_date' => 'required', 'amount' => 'required']);
    
    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/expenses/create.php');
    }

    
        $expenseCode = generateExpenseCode($db);
        
        $stmt = $db->prepare("INSERT INTO expenses (expense_code, category_id, expense_date, amount, payment_method, vendor_name, description, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->execute([
            $expenseCode,
            $data['category_id'],
            $data['expense_date'],
            $data['amount'],
            $_POST['payment_method'],
            $data['vendor_name'],
            $_POST['description'],
            $_SESSION['user_id']
        ]);
        
        logAudit($_SESSION['user_id'], 'create_expense', 'expenses');
        flash('success', 'Expense added successfully');
        to('http://localhost/ERP/modules/expenses/index.php');
    

}
?>

<div class="mb-6">
    <a href="<?= url('modules/expenses/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Expenses</a>
</div>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <h2 class="text-xl font-semibold mb-6">Add New Expense</h2>
    
    <?php if ($errors): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
    
    <form method="POST" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category <span class="text-red-600">*</span></labe>
                <select name="category_id" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?><option value="' . $cat['id'] . '" <?= old('category_id') == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['category_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Expense Date <span class="text-red-600">*</span></labe>
                <input type="date" name="expense_date" value="<?= old('expense_date', date('Y-m-d')) ?>" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Amount <span class="text-red-600">*</span></labe>
                <input type="number" name="amount" value="<?= old('amount') ?>" min="0.01" step="0.01" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                <select name="payment_method" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="cash">Cash</option>
                    <option value="upi">UPI</option>
                    <option value="credit_card">Credit Card</option>
                </select>
            </div>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Vendor Name</label>
            <input type="text" name="vendor_name" value="<?= old('vendor_name') ?>" placeholder="Vendor or supplier name" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="3" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('description') ?></textarea>
        </div>
        
        <div class="pt-4">
            <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded hover:bg-red-700 transition">Add Expense</button>
            <a href="<?= url('modules/expenses/index.php') ?>" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>

