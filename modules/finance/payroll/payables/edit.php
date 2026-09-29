<?php
/**
 * Edit Payroll Payable
 * 
 * @package XXX
 * @submodule Finance.Payroll.Payables
 */

// Check permission
if (!hasPermission('payrollfinance.pay')) {
    redirect_to('no-access.php');
}

// Get payable ID from URL
$ = isset($['id']) ? (int)$['id'] : 0;
if ($ <= 0) {
    redirect_to('index.php');
}

// Handle form submission
if ($['REQUEST_METHOD'] === 'POST') {
    // Process form data and update payable
    // This would validate input, update payable record, then redirect
    header('Location: view.php?id=' . $ . '&success=1');
    exit;
}

// Fetch payable data
$ = getPayrollPayableById($, $);
if (!$) {
    redirect_to('index.php');
}

// Get data for form dropdowns
$ = getActiveEmployees($);
$ = getPostedPayrollRuns($);
$ = getPayrollPayableTypes($);

// Include header
include_once __DIR__ . '/../../../../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center flex-wrap">
        <h1 class="text-2xl font-bold">Edit Payroll Payable</h1>
        <a href="index.php" class="btn-outline px-4 py-2 border border-blue-600 text-blue-600 hover:bg-blue-50 transition">Back to List</a>
    </div>

    <?php if (isset($['success']) && $['success'] == 1): ?>
        <div class="bg-green-50 border-l-4 border-green-600 p-4 text-green-800 mb-6">
            Payroll payable updated successfully!
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="POST" action="">
            <input type="hidden" name="id" value="<?= $ ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block mb-2 font-medium">Employee</label>
                    <select name="employee_id" class="select w-full" required>
                        <option value="">Select Employee</option>
                        <?php foreach ($ as $): ?>
                            <option value="<?= $['id'] ?>" <?= $['id'] == $['employee_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($['employee_name']) ?> 
                                (<?= htmlspecialchars($['employee_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block mb-2 font-medium">Payroll Run</label>
                    <select name="payroll_run_id" class="select w-full">
                        <option value="">No Specific Payroll Run (Manual Adjustment)</option>
                        <?php foreach ($ as $): ?>
                            <option value="<?= $['id'] ?>" <?= !empty($['payroll_run_id']) && $['id'] == $['payroll_run_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($['payroll_run_name']) ?> 
                                (<?= htmlspecialchars($['payroll_run_date']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                <div>
                    <label class="block mb-2 font-medium">Payable Type</label>
                    <select name="payable_type" class="select w-full" required>
                        <option value="">Select Payable Type</option>
                        <?php foreach ($ as $): ?>
                            <option value="<?= $['type'] ?>" <?= $['type'] == $['payable_type'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($['description']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block mb-2 font-medium">Amount Due (AED)</label>
                    <input type="number" name="amount" class="input w-full" step="0.01" min="0" value="<?= number_format($['amount'], 2) ?>" required>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                <div>
                    <label class="block mb-2 font-medium">Due Date</label>
                    <input type="date" name="due_date" class="input w-full" value="<?= htmlspecialchars($['due_date']) ?>" required>
                </div>
                
                <div>
                    <label class="block mb-2 font-medium">Description</label>
                    <textarea name="description" class="textarea w-full" rows="3"><?= htmlspecialchars($['description']) ?></textarea>
                </div>
            </div>
            
            <div class="mt-6">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 hover:bg-blue-700 transition w-full">
                    Update Payable
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../../../../includes/footer.php';
?>
