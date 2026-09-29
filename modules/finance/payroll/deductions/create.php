<?php
/**
 * Create Payroll Deduction Type
 * 
 * @package XXX
 * @submodule Finance.Payroll.Deductions.Types
 */

// Check permission
if (!hasPermission('payrollfinance.configure')) {
    redirect_to('no-access.php');
}

// Handle form submission
if ($['REQUEST_METHOD'] === 'POST') {
    // Process form data and create deduction type
    // This would validate input, create deduction type record, then redirect
    header('Location: index.php?success=1');
    exit;
}

// Include header
include_once __DIR__ . '/../../../../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center flex-wrap">
        <h1 class="text-2xl font-bold">Create Deduction Type</h1>
        <a href="index.php" class="btn-outline px-4 py-2 border border-blue-600 text-blue-600 hover:bg-blue-50 transition">Back to List</a>
    </div>

    <?php if (isset($['success']) && $['success'] == 1): ?>
        <div class="bg-green-50 border-l-4 border-green-600 p-4 text-green-800 mb-6">
            Deduction type created successfully!
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="POST" action="">
            <div class="mb-4">
                <label class="block mb-2 font-medium">Deduction Type Code</label>
                <input type="text" name="deduction_type" class="input w-full" placeholder="e.g., advance, loan, unpaid_leave" required>
            </div>

            <div class="mb-4">
                <label class="block mb-2 font-medium">Description</label>
                <input type="text" name="description" class="input w-full" required>
            </div>

            <div class="mb-4">
                <label class="block mb-2 font-medium">Is Configurable?</label>
                <div class="flex items-center">
                    <input type="checkbox" name="is_configurable" value="1" checked>
                    <span class="ml-2">Allow finance department to configure GL mapping for this deduction type</span>
                </div>
                <p class="text-sm text-gray-500 mt-1">Non-configurable types are typically government-mandated deductions with fixed accounting treatment</p>
            </div>

            <div class="mb-6 pt-4 border-t">
                <h2 class="text-lg font-semibold mb-4">Common Deduction Types (for reference)</h2>
                <div class="space-y-2 text-sm">
                    <div>• advance - Employee Advance</div>
                    <div>• loan - Employee Loan</div>
                    <div>• unpaid_leave - Unpaid Leave</div>
                    <div>• other - Other Deductions</div>
                    <div>• government - Government Deductions (Tax, Social Security, etc.)</div>
                    <div>• insurance - Insurance Premiums</div>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 hover:bg-blue-700 transition w-full">
                    Create Deduction Type
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../../../../includes/footer.php';
?>
