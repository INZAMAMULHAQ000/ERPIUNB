<?php
$pageTitle = 'Employee Expense Claims';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';

if (!hasPermission('employee_claims.view')) { die('Access Denied'); }

$db = getDB();

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$employee_id = $_GET['employee_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (ec.claim_number LIKE ? OR ec.description LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($status !== '') {
    $where .= " AND ec.status = ?";
    $params[] = $status;
}
if ($employee_id) {
    $where .= " AND ec.employee_id = ?";
    $params[] = (int)$employee_id;
}
if ($date_from) { $where .= " AND ec.expense_date >= ?"; $params[] = $date_from; }
if ($date_to) { $where .= " AND ec.expense_date <= ?"; $params[] = $date_to; }

$countStmt = $db->prepare("SELECT COUNT(*) FROM employee_expense_claims ec $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT ec.*, e.first_name, e.last_name, e.employee_code,
        cat.name AS category_name, p.project_name, u.name AS created_by_name,
        je.voucher_no
    FROM employee_expense_claims ec
    LEFT JOIN employees e ON ec.employee_id = e.id
    LEFT JOIN expense_categories cat ON ec.category_id = cat.id
    LEFT JOIN projects p ON ec.project_id = p.id
    LEFT JOIN users u ON ec.created_by = u.id
    LEFT JOIN journal_entries je ON ec.journal_entry_id = je.id
    $where ORDER BY ec.created_at DESC LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$claims = $stmt->fetchAll();

$employees = $db->query("SELECT id, first_name, last_name, employee_code FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();

$statusBadges = [
    'draft' => 'bg-gray-100 text-gray-800',
    'pending_manager' => 'bg-yellow-100 text-yellow-800',
    'pending_finance' => 'bg-orange-100 text-orange-800',
    'approved' => 'bg-blue-100 text-blue-800',
    'rejected' => 'bg-red-100 text-red-800',
    'reimbursed' => 'bg-green-100 text-green-800',
    'cancelled' => 'bg-gray-200 text-gray-600',
];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Employee Expense Claims</h1>
        <p class="text-gray-600">Employee reimbursement requests and claims</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
        <?php if (hasPermission('employee_claims.create')): ?>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New Claim</a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Claim # or description..." class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-44">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="pending_manager" <?= $status === 'pending_manager' ? 'selected' : '' ?>>Pending Manager</option>
                <option value="pending_finance" <?= $status === 'pending_finance' ? 'selected' : '' ?>>Pending Finance</option>
                <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                <option value="reimbursed" <?= $status === 'reimbursed' ? 'selected' : '' ?>>Reimbursed</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <div class="w-44">
            <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
            <select name="employee_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>" <?= $employee_id == $emp['id'] ? 'selected' : '' ?>><?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input type="date" id="date_from" name="date_from" value="<?= sanitize($date_from) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input type="date" id="date_to" name="date_to" value="<?= sanitize($date_to) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-search"></i></button>
        <?php if ($search || $status !== '' || $employee_id || $date_from || $date_to): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Claim #</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($claims as $c): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-mono font-medium text-gray-900"><?= sanitize($c['claim_number']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($c['first_name'] . ' ' . $c['last_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($c['expense_date'])) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($c['category_name'] ?? '--') ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate"><?= sanitize($c['description'] ?? '--') ?></td>
                    <td class="px-4 py-3 text-center whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded font-medium <?= $statusBadges[$c['status']] ?? 'bg-gray-100' ?>">
                            <?= ucwords(str_replace('_', ' ', $c['status'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney((float)$c['total_amount']) ?></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="view.php?id=<?= $c['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3" title="View"><i class="fas fa-eye"></i></a>
                        <?php if ($c['status'] === 'draft' && hasPermission('employee_claims.edit')): ?>
                        <a href="edit.php?id=<?= $c['id'] ?>" class="text-yellow-600 hover:text-yellow-900 mr-3" title="Edit"><i class="fas fa-pencil"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($claims)): ?>
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-receipt text-4xl mb-3 text-gray-300"></i>
                    <p>No expense claims found</p>
                    <?php if (hasPermission('employee_claims.create')): ?>
                    <a href="create.php" class="text-blue-600 hover:underline mt-2 inline-block">Submit your first claim</a>
                    <?php endif; ?>
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= paginationLinks($pagination, '?') ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>
$(document).ready(function () {

    function updateToDate() {

        const fromDate = $('#date_from').val();
        const $toDate = $('#date_to');

        if (fromDate) {

            $toDate.attr('min', fromDate);

            if ($toDate.val() && $toDate.val() < fromDate) {
                $toDate.val('');
            }

        } else {

            $toDate.removeAttr('min');
            $toDate.val('');
        }
    }

    // Page load
    updateToDate();

    // From Date change
    $('#date_from').on('change', function () {

        updateToDate();

        // To Date validation refresh
        $('#date_to').valid();
    });

    // To Date change
    $('#date_to').on('change', function () {

        const fromDate = $('#date_from').val();
        const toDate = $(this).val();

        if (!fromDate) {
            $(this).val('');
            return;
        }

        if (toDate < fromDate) {
            $(this).val('');
        }
    });

});
</script>

<?php require_once '../../../includes/footer.php'; ?>
