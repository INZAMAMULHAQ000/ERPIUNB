<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
/**
 * Payroll GL Mapping List
 *
 * @package XXX
 * @submodule Finance.Payroll.Mappings
 */

$pageTitle = 'Payroll GL Mappings';
require_once __DIR__ . '/../../../../helpers/rbac.php';
require_once __DIR__ . '/../../../../helpers/redirect.php';
require_once __DIR__ . '/../../../../helpers/finance_helpers.php';

// Check permission
if (!hasPermission('payrollfinance.view')) {
    redirect_to('no-access.php');
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$mappingType = isset($_GET['mapping_type']) ? trim($_GET['mapping_type']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Fetch data
$mappings = getPayrollGlMappings($search, $mappingType, $status);

// Get filter options
$mappingTypes = getPayrollMappingTypes();
$statuses = getMappingStatuses();

// Include header
include_once __DIR__ . '/../../../../includes/header.php';
?>
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center flex-wrap">
        <h1 class="text-2xl font-bold">Payroll GL Mappings</h1>
        <?php if (hasPermission('payrollfinance.configure')): ?>
            <a href="add.php" class="bg-blue-600 text-white px-4 py-2 hover:bg-blue-700 transition">Add New Mapping</a>
        <?php endif; ?>
    </div>

    <!-- Search and Filters -->
    <form class="mb-6 flex flex-wrap items-end gap-4" method="GET">
        <div>
            <label class="block mb-1 font-medium" for="search">Search Payroll Component</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" id="search" class="w-64">
        </div>
        <div>
            <label class="block mb-1 font-medium" for="mapping_type">Mapping Type</label>
            <select name="mapping_type" id="mapping_type" class="w-48">
                <option value="">All Types</option>
                <?php foreach ($mappingTypes as $type): ?>
                    <option value="<?= htmlspecialchars($type) ?>" <?= $mappingType === $type ? 'selected' : '' ?>>
                        <?= htmlspecialchars($type) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block mb-1 font-medium" for="status">Status</label>
            <select name="status" id="status" class="w-48">
                <option value="">All Status</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $status === $s ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
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
                    <th>Payroll Component</th>
                    <th>GL Account</th>
                    <th>Mapping Type</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($mappings)): ?>
                    <tr><td colspan="5" class="text-center py-4">No mappings found.</td></tr>
                <?php else: ?>
                    <?php foreach ($mappings as $mapping): ?>
                        <tr>
                            <td><?= htmlspecialchars($mapping['payroll_component_name'] ?? $mapping['payroll_component']) ?></td>
                            <td>
                                <?= htmlspecialchars($mapping['gl_account_code'] ?? $mapping['account_code']) ?> -
                                <?= htmlspecialchars($mapping['gl_account_name'] ?? $mapping['account_name']) ?>
                            </td>
                            <td><?= htmlspecialchars($mapping['mapping_type']) ?></td>
                            <td>
                                <span class="text-xs px-2 py-1 rounded-full font-medium <?= $mapping['status'] === 'Active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= htmlspecialchars($mapping['status']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="flex justify-center space-x-2">
                                    <?php if (hasPermission('payrollfinance.view')): ?>
                                        <a href="view.php?id=<?= $mapping['id'] ?>" class="btn-xs px-2 py-1 bg-blue-600 text-white border border-blue-600 hover:bg-blue-700 transition text-sm">View</a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('payrollfinance.configure')): ?>
                                        <a href="edit.php?id=<?= $mapping['id'] ?>" class="btn-xs px-2 py-1 border border-blue-600 text-blue-600 hover:bg-blue-50 transition text-sm">Edit</a>
                                        <button onclick="if(confirm('Delete this mapping?')) location.href='delete.php?id=<?= $mapping['id'] ?>'"
                                                class="btn-xs px-2 py-1 border border-red-600 text-red-600 hover:bg-red-50 transition text-sm">Delete</button>
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
<?php require_once __DIR__ . '/../../../../includes/footer.php'; ?>