<?php
/**
 * View Payroll Payables Details
 * 
 * @package XXX
 * @submodule Finance.Payroll.Payables
 */

// Check permission
if (!hasPermission('payrollfinance.view')) {
    redirect_to('no-access.php');
}

// Get filtering parameters
\ = isset(\['payroll_run_id']) ? (int)\['payroll_run_id'] : 0;
\ = isset(\['employee_id']) ? (int)\['employee_id'] : 0;
\ = isset(\['date_from']) ? trim(\['date_from']) : '';
\ = isset(\['date_to']) ? trim(\['date_to']) : '';

// Fetch detailed payables data
\ = getPayrollPayablesDetails(\, \, \, \, \);

// Include header
include_once __DIR__ . '/../../../../includes/header.php';
?>
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center flex-wrap">
        <h1 class="text-2xl font-bold">Payroll Payables Details</h1>
        <div class="flex space-x-3">
            <a href="index.php" class="btn-outline px-4 py-2 border border-blue-600 text-blue-600 hover:bg-blue-50 transition">Back to Payables</a>
            <a href="export.php?payroll_run_id=<?= \ ?>&employee_id=<?= \ ?>&date_from=<?= htmlspecialchars(urlencode(\)) ?>&date_to=<?= htmlspecialchars(urlencode(\)) ?>" class="btn-secondary px-4 py-2 border border-gray-500 text-gray-600 hover:bg-gray-50 transition">Export to CSV</a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6">
            <?php if (empty(\)): ?>
                <div class="text-center py-8">
                    <p class="text-gray-500">No payables found matching the selected criteria.</p>
                </div>
            <?php else: ?>
                <!-- Summary Cards -->
                <div class="mb-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-blue-600">Total Amount</h3>
                        <p class="text-2xl font-bold text-blue-800">AED <?= number_format(array_sum(array_column(\, 'amount')), 2) ?></p>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-green-600">Unique Employees</h3>
                        <p class="text-2xl font-bold text-green-800"><?= count(array_column(\, 'employee_id')) ?></p>
                    </div>
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-yellow-600">Payroll Runs</h3>
                        <p class="text-2xl font-bold text-yellow-800"><?= count(array_column(\, 'payroll_run_id')) ?></p>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-purple-600">Average per Payable</h3>
                        <p class="text-2xl font-bold text-purple-800">AED <?= number_format(array_sum(array_column(\, 'amount')) / count(\), 2) ?></p>
                    </div>
                </div>
                
                <!-- Details Table -->
                <div class="overflow-x-auto">
                    <table class="table table-sm w-full">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Payroll Run</th>
                                <th>Payable Type</th>
                                <th class="text-right">Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (\ as \): ?>
                                <tr>
                                    <td><?= htmlspecialchars(\['employee_name']) ?></td>
                                    <td><?= htmlspecialchars(\['payroll_run_name']) ?></td>
                                    <td><?= htmlspecialchars(\['payable_type']) ?></td>
                                    <td class="text-right">AED <?= number_format(\['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars(\['date']) ?></td>
                                    <td>
                                        <span class="badge <?= \['status'] === 'Paid' ? 'badge-success' : (\['status'] === 'Overdue' ? 'badge-error' : 'badge-warning') ?>">
                                            <?= htmlspecialchars(\['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../../../../includes/footer.php';
?>
