<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
/**
 * Payroll Payables List
 *
 * @package XXX
 * @submodule Finance.Payroll.Payables
 */

require_once __DIR__ . '/../../../../helpers/rbac.php';

// Check permission
if (!hasPermission('payrollfinance.view')) {
    redirect_to('no-access.php');
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$payrollRunId = isset($_GET['payroll_run_id']) ? (int)$_GET['payroll_run_id'] : 0;

// Fetch data
$payables = getPayrollPayables($search, $statusFilter, $payrollRunId);

// Get filter options
$payableStatuses = getPayableStatuses();
$postedPayrollRuns = getPostedPayrollRuns($payrollRunId);

// Include header
include_once __DIR__ . '/../../../../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center flex-wrap">
        <h1 class="text-2xl font-bold">Payroll Payables</h1>
        <?php if (hasPermission('payrollfinance.pay')): ?>
            <a href="create_payment_batch.php" class="bg-blue-600 text-white px-4 py-2 hover:bg-blue-700 transition">Create Payment Batch</a>
        <?php endif; ?>
    </div>

    <!-- Search and Filters -->
    <form class="mb-6 flex flex-wrap items-end gap-4" method="GET">
        <div>
            <label class="block mb-1 font-medium" for="search">Search Employee or Description</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" id="search" class="w-64">
        </div>

        <div>
            <label class="block mb-1 font-medium" for="status">Status</label>
            <select name="status" id="status" class="w-48">
                <option value="">All Status</option>
                <?php foreach ($payableStatuses as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block mb-1 font-medium" for="payroll_run_id">Payroll Run</label>
            <select name="payroll_run_id" id="payroll_run_id" class="w-64">
                <option value="">All Payroll Runs</option>
                <?php foreach ($postedPayrollRuns as $run): ?>
                    <option value="<?= $run['id'] ?>" <?= $payrollRunId === $run['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($run['payroll_run_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex items-end">
            <button type="submit" class="btn-secondary px-4 py-2 border border-blue-600 text-blue-600 hover:bg-blue-50 transition">Filter</button>
            <a href="index.php" class="btn-outline px-4 py-2 border border-blue-600 text-blue-600 hover:bg-blue-50 transition">Reset</a>
        </div>
    </form>

    <!-- Summary Cards -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-blue-50 p-4 rounded-lg">
            <h3 class="text-sm font-medium text-blue-600">Total Payables</h3>
            <p class="text-2xl font-bold text-blue-800">AED <?= number_format(array_sum(array_column($payables, 'amount')) ?? 0, 2) ?></p>
        </div>
        <div class="bg-green-50 p-4 rounded-lg">
            <h3 class="text-sm font-medium text-green-600">This Month</h3>
            <p class="text-2xl font-bold text-green-800">AED <?= number_format(0, 2) ?></p>
        </div>
        <div class="bg-yellow-50 p-4 rounded-lg">
            <h3 class="text-sm font-medium text-yellow-600">Overdue</h3>
            <p class="text-2xl font-bold text-yellow-800">AED <?= number_format(0, 2) ?></p>
        </div>
        <div class="bg-purple-50 p-4 rounded-lg">
            <h3 class="text-sm font-medium text-purple-600">Paid Today</h3>
            <p class="text-2xl font-bold text-purple-800">AED <?= number_format(0, 2) ?></p>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Payroll Run</th>
                    <th>Payable Type</th>
                    <th>Amount Due</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payables)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">No payables found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payables as $payable): ?>
                        <tr>
                            <td><?= htmlspecialchars($payable['employee_name']) ?></td>
                            <td><?= htmlspecialchars($payable['payroll_run_name']) ?></td>
                            <td><?= htmlspecialchars($payable['payable_type']) ?></td>
                            <td class="text-right">AED <?= number_format($payable['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($payable['due_date']) ?></td>
                            <td>
                                <span class="text-xs px-2 py-1 rounded-full font-medium <?= $payable['status'] === 'Paid' ? 'bg-green-100 text-green-800' : ($payable['status'] === 'Overdue' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                    <?= htmlspecialchars($payable['status']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="flex justify-center space-x-2">
                                    <?php if (hasPermission('payrollfinance.view')): ?>
                                        <a href="view.php?id=<?= $payable['id'] ?>" class="btn-xs px-2 py-1 bg-blue-600 text-white border border-blue-600 hover:bg-blue-700 transition text-sm">View</a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('payrollfinance.pay') && $payable['status'] !== 'Paid'): ?>
                                        <a href="edit.php?id=<?= $payable['id'] ?>" class="btn-xs px-2 py-1 border border-blue-600 text-blue-600 hover:bg-blue-50 transition text-sm">Adjust</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Include footer
include_once __DIR__ . '/../../../../includes/footer.php';
?>