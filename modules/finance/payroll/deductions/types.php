<?php
/**
 * Payroll Deduction Types Management
 * 
 * @package XXX
 * @submodule Finance.Payroll.Deductions.Types
 */

// Check permission
if (!hasPermission('payrollfinance.configure')) {
    redirect_to('no-access.php');
}

// Get search and filter parameters
$ = isset($['search']) ? trim($['search']) : '';
$ = isset($['status']) ? trim($['status']) : '';

// Fetch data - placeholder for actual implementation
// In a real implementation, this would get deduction types from database
$ = getPayrollDeductionTypes($); // This gets active ones, we might need a different function for all

// Include header
include_once __DIR__ . '/../../../../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center flex-wrap">
        <h1 class="text-2xl font-bold">Payroll Deduction Types</h1>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 hover:bg-blue-700 transition">Add Deduction Type</a>
    </div>

    <!-- Search and Filters -->
    <form class="mb-6 flex flex-wrap items-end gap-4" method="GET">
        <div>
            <label class="block mb-1 font-medium" for="search">Search Deduction Type</label>
            <input type="text" name="search" value="<?= htmlspecialchars($) ?>" id="search" class="w-64">
        </div>

        <div>
            <label class="block mb-1 font-medium" for="status">Status</label>
            <select name="status" id="status" class="w-48">
                <option value="">All Status</option>
                <?php foreach (['Active', 'Inactive'] as $): ?>
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

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr>
                    <th>Deduction Type</th>
                    <th>Description</th>
                    <th>Is Configurable</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">No deduction types found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($ as $): ?>
                        <tr>
                            <td><?= htmlspecialchars($['deduction_type']) ?></td>
                            <td><?= htmlspecialchars($['description']) ?></td>
                            <td><?= $['is_configurable'] ? 'Yes' : 'No' ?></td>
                            <td>
                                <span class="text-xs px-2 py-1 rounded-full font-medium <?= $['status'] === 'Active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= htmlspecialchars($['status']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="flex justify-center space-x-2">
                                    <?php if (hasPermission('payrollfinance.view')): ?>
                                        <a href="view.php?id=<?= $['id'] ?>" class="btn-xs px-2 py-1 bg-blue-600 text-white border border-blue-600 hover:bg-blue-700 transition text-sm">View</a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('payrollfinance.configure')): ?>
                                        <a href="edit.php?id=<?= $['id'] ?>" class="btn-xs px-2 py-1 border border-blue-600 text-blue-600 hover:bg-blue-50 transition text-sm">Edit</a>
                                        <a href="delete.php?id=<?= $['id'] ?>" class="btn-xs px-2 py-1 border border-red-600 text-red-600 hover:bg-red-50 transition text-sm">Delete</a>
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
include_once __DIR__ . '/../../../../includes/footer.php';
?>
