<?php
/**
 * Payroll Finance Postings List
 *
 * @package XXX
 * @submodule Finance.Payroll.Postings
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../../../helpers/rbac.php';
require_once __DIR__ . '/../../../../helpers/redirect.php';

// Check permission
if (!hasPermission('payrollfinance.view')) {
    redirect_to('no-access.php');
}

// Include business logic helpers (must be BEFORE usage)
require_once __DIR__ . '/../../../../helpers/finance_helpers.php';

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Fetch data
$postings = getPayrollFinancePostings($search, $status);

// Get filter options
$postingStatuses = getPostingStatuses();

// Page title MUST be set before include header.php (which uses $pageTitle ?? 'Dashboard')
$pageTitle = 'Payroll Finance Postings';

// Include header and helpers
include_once __DIR__ . '/../../../../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center flex-wrap">
        <h1 class="text-2xl font-bold">Payroll Finance Postings</h1>
        <?php if (hasPermission('payrollfinance.post')): ?>
            <a href="create.php" class="bg-blue-600 text-white px-4 py-2 hover:bg-blue-700 transition">Post New Payroll</a>
        <?php endif; ?>
    </div>

    <!-- Search and Filters -->
    <form class="mb-6 flex flex-wrap items-end gap-4" method="GET">
        <div>
            <label class="block mb-1 font-medium" for="search">Search Payroll Run</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" id="search" class="w-64">
        </div>

        <div>
            <label class="block mb-1 font-medium" for="status">Status</label>
            <select name="status" id="status" class="w-48">
                <option value="">All Status</option>
                <?php foreach ($postingStatuses as $s): ?>
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
                    <th>Payroll Run</th>
                    <th>Posting Date</th>
                    <th>Total Gross</th>
                    <th>Total Deductions</th>
                    <th>Total Net</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($postings)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">No postings found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($postings as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['payroll_run_name']) ?></td>
                            <td><?= htmlspecialchars($p['posting_date']) ?></td>
                            <td class="text-right">AED <?= number_format($p['total_gross'], 2) ?></td>
                            <td class="text-right">AED <?= number_format($p['total_deductions'], 2) ?></td>
                            <td class="text-right">AED <?= number_format($p['total_net'], 2) ?></td>
                            <td>
                                <span class="text-xs px-2 py-1 rounded-full font-medium <?= $p['status'] === 'Posted' ? 'bg-green-100 text-green-800' : ($p['status'] === 'Draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                    <?= htmlspecialchars($p['status']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="flex justify-center space-x-2">
                                    <?php if (hasPermission('payrollfinance.view')): ?>
                                        <a href="view.php?id=<?= $p['id'] ?>" class="btn-xs px-2 py-1 bg-blue-600 text-white border border-blue-600 hover:bg-blue-700 transition text-sm">View</a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('payrollfinance.post')): ?>
                                        <a href="edit.php?id=<?= $p['id'] ?>" class="btn-xs px-2 py-1 border border-blue-600 text-blue-600 hover:bg-blue-50 transition text-sm">Edit</a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('payrollfinance.post') && $p['status'] === 'Draft'): ?>
                                        <a href="post.php?id=<?= $p['id'] ?>" class="btn-xs px-2 py-1 bg-green-600 text-white border border-green-600 hover:bg-green-700 transition text-sm">Post</a>
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
