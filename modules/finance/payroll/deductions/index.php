<?php
/**
 * Payroll Deductions
 * 
 * @package XXX
 * @submodule Finance.Payroll.Deductions
 */

// Check permission
if (!hasPermission('payrollfinance.view')) {
    redirect_to('no-access.php');
}

// Get search and filter parameters
$ = isset($['search']) ? trim($['search']) : '';
$ = isset($['deduction_type']) ? trim($['deduction_type']) : '';

// Fetch data - placeholder for actual implementation
// In a real implementation, this would get deduction totals from payroll runs
$ = getPayrollDeductionTotals($, $);

// Get filter options
$ = getPayrollDeductionTypes();

// Include header
include_once __DIR__ . '/../../../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center flex-wrap">
        <h1 class="text-2xl font-bold">Payroll Deductions</h1>
        <?php if (hasPermission('payrollfinance.configure')): ?>
            <a href="types.php" class="bg-blue-600 text-white px-4 py-2 hover:bg-blue-700 transition">Manage Deduction Types</a>
        <?php endif; ?>
    </div>

    <!-- Search and Filters -->
    <form class="mb-6 flex flex-wrap items-end gap-4" method="GET">
        <div>
            <label class="block mb-1 font-medium" for="search">Search Deduction Description</label>
            <input type="text" name="search" value="<?= htmlspecialchars($) ?>" id="search" class="w-64">
        </div>

        <div>
            <label class="block mb-1 font-medium" for="deduction_type">Deduction Type</label>
            <select name="deduction_type" id="deduction_type" class="w-48">
                <option value="">All Types</option>
                <?php foreach ($ as $): ?>
                    <option value="<?= htmlspecialchars($) ?>" <?= $ === $ ? 'selected' : '' ?>>
                        <?= htmlspecialchars($) ?>
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
            <h3 class="text-sm font-medium text-blue-600">Total Deductions</h3>
            <p class="text-2xl font-bold text-blue-800">AED <?= number_format($ ?? 0, 2) ?></p>
        </div>
        <div class="bg-green-50 p-4 rounded-lg">
            <h3 class="text-sm font-medium text-green-600">Avg per Employee</h3>
            <p class="text-2xl font-bold text-green-800">AED <?= number_format($ ?? 0, 2) ?></p>
        </div>
        <div class="bg-yellow-50 p-4 rounded-lg">
            <h3 class="text-sm font-medium text-yellow-600">Max Deduction</h3>
            <p class="text-2xl font-bold text-yellow-800">AED <?= number_format($ ?? 0, 2) ?></p>
        </div>
        <div class="bg-purple-50 p-4 rounded-lg">
            <h3 class="text-sm font-medium text-purple-600">Unique Types</h3>
            <p class="text-2xl font-bold text-purple-800"><?= count($ ?? []) ?></p>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr>
                    <th>Payroll Run</th>
                    <th>Deduction Type</th>
                    <th>Description</th>
                    <th class="text-right">Amount (AED)</th>
                    <th class="text-center">Employees</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4">No deduction records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($ as $): ?>
                        <tr>
                            <td><?= htmlspecialchars($['payroll_run_name']) ?></td>
                            <td><?= htmlspecialchars($['deduction_type']) ?></td>
                            <td><?= htmlspecialchars($['description']) ?></td>
                            <td class="text-right">AED <?= number_format($['amount'], 2) ?></td>
                            <td class="text-center"><?= $['employee_count'] ?></td>
                            <td class="text-center">
                                <div class="flex justify-center space-x-2">
                                    <?php if (hasPermission('payrollfinance.view')): ?>
                                        <a href="view.php?id=<?= $['id'] ?>" class="btn-xs px-2 py-1 bg-blue-600 text-white border border-blue-600 hover:bg-blue-700 transition text-sm">View</a>
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
// Include footer
include_once __DIR__ . '/../../../includes/footer.php';
?>
