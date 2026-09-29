<?php
/**
 * View Payroll Payment Details
 * 
 * @package XXX
 * @submodule Finance.Payroll.Payments
 */

// Check permission
if (!hasPermission('payrollfinance.view')) {
    redirect_to('no-access.php');
}

// Get payment ID from URL
\ = isset(\['id']) ? (int)\['id'] : 0;
if (\ <= 0) {
    redirect_to('index.php');
}

// Fetch payment data with related information
\ = getPayrollPaymentById(\, \);
if (!\) {
    redirect_to('index.php');
}

// Get related data
\ = getPayrollRunById(\, \['payroll_run_id']);
\ = getBankAccountById(\, \['bank_account_id']);

// Include header
include_once __DIR__ . '/../../../../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center flex-wrap">
        <h1 class="text-2xl font-bold">Payroll Payment Details</h1>
        <div class="flex space-x-3">
            <a href="index.php" class="btn-outline px-4 py-2 border border-blue-600 text-blue-600 hover:bg-blue-50 transition">Back to List</a>
            <?php if (hasPermission('payrollfinance.pay') && \['status'] === 'Approved'): ?>
                <a href="edit.php?id=<?= \ ?>" class="btn-secondary px-4 py-2 border border-gray-500 text-gray-600 hover:bg-gray-50 transition">Edit</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6">
            <div class="mb-4 px-4 py-2 bg-gray-50 rounded-lg">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-lg font-semibold">Payment for <?= htmlspecialchars(\['payroll_run_name'] ?? 'Unknown Payroll') ?></h2>
                        <p class="text-sm text-gray-500">Batch #: <?= htmlspecialchars(\['reference_number'] ?? 'N/A') ?></p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs px-2 py-1 rounded-full font-medium <?= \['status'] === 'Paid' ? 'bg-green-100 text-green-800' : (\['status'] === 'Approved' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                            <?= htmlspecialchars(\['status']) ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Payment Information -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <h2 class="text-xl font-semibold mb-4">Payment Information</h2>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h3 class="text-lg font-medium mb-2">Bank Account</h3>
                            <p class="text-xl font-bold"><?= htmlspecialchars(\['account_name'] ?? 'Unknown Bank') ?></p>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium mb-2">Payment Amount</h3>
                            <p class="text-2xl font-bold text-blue-600">AED <?= number_format(\['total_amount'], 2) ?></p>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium mb-2">Payment Date</h3>
                            <p class="text-2xl font-bold"><?= htmlspecialchars(\['payment_date']) ?></p>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium mb-2">Reference Number</h3>
                            <p class="text-2xl font-bold"><?= htmlspecialchars(\['reference_number']) ?></p>
                        </div>
                    </div>
                    
                    <?php if (!empty(\['description'])): ?>
                        <div class="mb-4">
                            <h3 class="text-lg font-medium mb-2">Description</h3>
                            <p class="text-gray-700"><?= nl2br(htmlspecialchars(\['description'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Payment Actions -->
            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                <h2 class="text-xl font-semibold mb-4">Actions</h2>
                <div class="flex flex-wrap space-x-3">
                    <?php if (\['status'] === 'Approved'): ?>
                        <form method="POST" action="process_payment.php" class="inline">
                            <input type="hidden" name="payment_id" value="<?= \ ?>">
                            <button type="submit" class="bg-green-600 text-white px-4 py-2 hover:bg-green-700 transition">Process Payment</button>
                        </form>
                    <?php endif; ?>
                    <?php if (\['status'] !== 'Cancelled'): ?>
                        <a href="cancel.php?id=<?= \ ?>" class="bg-yellow-500 text-white px-4 py-2 hover:bg-yellow-600 transition">Cancel Payment</a>
                    <?php endif; ?>
                    <a href="receipt.php?id=<?= \ ?>" class="btn-secondary px-4 py-2 border border-gray-500 text-gray-600 hover:bg-gray-50 transition">Print Receipt</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../../../../includes/footer.php';
?>
