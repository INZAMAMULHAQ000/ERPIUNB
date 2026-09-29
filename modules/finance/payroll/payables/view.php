<?php
/**
 * View Payroll Payable Details
 * 
 * @package XXX
 * @submodule Finance.Payroll.Payables
 */

// Check permission
if (!hasPermission('payrollfinance.view')) {
    redirect_to('no-access.php');
}

// Get payable ID from URL
$ = isset($['id']) ? (int)$['id'] : 0;
if ($ <= 0) {
    redirect_to('index.php');
}

// Fetch payable data with related information
$ = getPayrollPayableById($, $);
if (!$) {
    redirect_to('index.php');
}

// Get employee and payroll run details
$ = getEmployeeById($, $['employee_id']);
$ = getPayrollRunById($, $['payroll_run_id']);

// Include header
include_once __DIR__ . '/../../../../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center flex-wrap">
        <h1 class="text-2xl font-bold">Payroll Payable Details</h1>
        <div class="flex space-x-3">
            <a href="index.php" class="btn-outline px-4 py-2 border border-blue-600 text-blue-600 hover:bg-blue-50 transition">Back to List</a>
            <?php if (hasPermission('payrollfinance.pay')): ?>
                <a href="edit.php?id=<?= $ ?>" class="btn-secondary px-4 py-2 border border-gray-500 text-gray-600 hover:bg-gray-50 transition">Edit</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6">
            <div class="mb-4 px-4 py-2 bg-gray-50 rounded-lg">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-lg font-semibold">Payable for <?= htmlspecialchars($['employee_name'] ?? 'Unknown Employee') ?></h2>
                        <p class="text-sm text-gray-500">Employee ID: <?= htmlspecialchars($['employee_code'] ?? 'N/A') ?></p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs px-2 py-1 rounded-full font-medium <?= $['status'] === 'Paid' ? 'bg-green-100 text-green-800' : ($['status'] === 'Overdue' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') ?>">
                            <?= htmlspecialchars($['status']) ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Payable Information -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <h2 class="text-xl font-semibold mb-4">Payable Information</h2>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h3 class="text-lg font-medium mb-2">Payable Type</h3>
                            <p class="text-xl font-bold"><?= htmlspecialchars($['payable_type_description'] ?? $['payable_type']) ?></p>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium mb-2">Amount Due</h3>
                            <p class="text-2xl font-bold text-red-600">AED <?= number_format($['amount'], 2) ?></p>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium mb-2">Due Date</h3>
                            <p class="text-2xl font-bold"><?= htmlspecialchars($['due_date']) ?></p>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium mb-2">Payroll Run</h3>
                            <p class="text-xl font-bold"><?= htmlspecialchars($['payroll_run_name'] ?? 'Manual Adjustment') ?></p>
                            <?php if (!empty($['payroll_run_date'])): ?>
                                <p class="text-sm text-gray-500">(<?= htmlspecialchars($['payroll_run_date']) ?>)</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($['description'])): ?>
                        <div class="mb-4">
                            <h3 class="text-lg font-medium mb-2">Description</h3>
                            <p class="text-gray-700"><?= nl2br(htmlspecialchars($['description'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Payment History (if any) -->
            <?php if (!empty($['payment_history']) && count($['payment_history']) > 0): ?>
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <h2 class="text-xl font-semibold mb-4">Payment History</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th>Payment Date</th>
                                    <th>Amount Paid</th>
                                    <th>Reference</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($['payment_history'] as $): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($['payment_date']) ?></td>
                                        <td class="text-right">AED <?= number_format($['amount'], 2) ?></td>
                                        <td><?= htmlspecialchars($['reference_number'] ?? 'N/A') ?></td>
                                        <td>
                                            <span class="text-xs px-2 py-1 rounded-full font-medium <?= $['status'] === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                                <?= htmlspecialchars($['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Actions -->
            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                <h2 class="text-xl font-semibold mb-4">Actions</h2>
                <div class="flex flex-wrap space-x-3">
                    <?php if ($['status'] !== 'Paid'): ?>
                        <a href="make_payment.php?id=<?= $ ?>" class="bg-blue-600 text-white px-4 py-2 hover:bg-blue-700 transition">Record Payment</a>
                    <?php endif; ?>
                    <?php if ($['status'] !== 'Voided'): ?>
                        <a href="void.php?id=<?= $ ?>" class="bg-yellow-500 text-white px-4 py-2 hover:bg-yellow-600 transition">Void Payable</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../../../../includes/footer.php';
?>
