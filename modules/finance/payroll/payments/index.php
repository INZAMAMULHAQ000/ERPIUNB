<?php
$pageTitle = 'Payroll Payment Batches';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';
require_once '../helpers/finance_helpers.php';
require_once 'helpers/payroll_helpers.php';

require_once __DIR__ . '/../../../../helpers/rbac.php';

if (!hasPermission('payrollfinance.view')) { die('Access Denied'); }

$db = getDB();
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];
if ($search) {
    $where .= " AND (pb.reference_number LIKE ? OR pr.id LIKE ?)";
    $s = "%$search%";
    $params[] = $s;
    $params[] = $s;
}
if ($statusFilter !== '') {
    $where .= " AND pb.status = ?";
    $params[] = $statusFilter;
}
if ($dateFrom) {
    $where .= " AND pb.payment_date >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where .= " AND pb.payment_date <= ?";
    $params[] = $dateTo;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM payroll_payment_batches pb JOIN payroll_runs pr ON pb.payroll_run_id = pr.id $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT pb.id, pb.payment_date, pb.amount, pb.reference_number, pb.status, pr.id AS run_id, pr.period_name, ba.account_name AS bank_account
    FROM payroll_payment_batches pb
    JOIN payroll_runs pr ON pb.payroll_run_id = pr.id
    LEFT JOIN bank_accounts ba ON pb.bank_account_id = ba.id
    $where ORDER BY pb.payment_date DESC LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$batches = $stmt->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Payroll Payment Batches</h1>
        <p class="text-gray-600">View and manage salary payment batches</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
        <?php if (hasPermission('payrollfinance.pay')): ?>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>Create Payment Batch</a>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Reference Number, Payroll Run ID" class="w-full px-3 py-2 border rounded">
        </div>
        <div class="w-36">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded">
                <option value="">All</option>
                <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="posted" <?= $statusFilter === 'posted' ? 'selected' : '' ?>>Posted</option>
                <option value="reconciled" <?= $statusFilter === 'reconciled' ? 'selected' : '' ?>>Reconciled</option>
                <option value="Approved" <?= $statusFilter === 'Approved' ? 'selected' : '' ?>>Approved</option>
            </select>
        </div>
        <div class="w-36">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input type="date" name="date_from" value="<?= sanitize($dateFrom) ?>" class="w-full px-3 py-2 border rounded">
        </div>
        <div class="w-36">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input type="date" name="date_to" value="<?= sanitize($dateTo) ?>" class="w-full px-3 py-2 border rounded">
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-search"></i></button>
        <?php if ($search || $statusFilter !== '' || $dateFrom || $dateTo): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment Batch ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payroll Run (ID/Period)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bank Account</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment Date</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference Number</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($batches as $b): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= $b['id'] ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= $b['run_id'] ?> / <?= sanitize($b['period_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($b['bank_account'] ?? '-') ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= formatDate($b['payment_date']) ?></td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900 text-right><?= formatPayrollAmount($b['amount']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600><?= sanitize($b['reference_number']) ?></td>
                    <td class="px-4 py-3 text-center><?= formatPaymentBatchStatus($b['status']) ?></td>
                    <td class="px-4 py-3 text-right">
                        <?php if (hasPermission('payrollfinance.edit')): ?>
                        <a href="edit.php?id=<?= $b['id'] ?>" class="text-yellow-600 hover:text-yellow-900" title="Edit"><i class="fas fa-edit"></i></a>
                        <?php endif; ?>
                        <?php if (hasPermission('payrollfinance.view')): ?>
                        <a href="view.php?id=<?= $b['id'] ?>" class="text-blue-600 hover:text-blue-900 ml-2" title="View"><i class="fas fa-eye"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($batches)): ?>
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-university text-4xl mb-3 text-gray-300"></i><p>No payment batches found</p></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../../includes/footer.php'; ?>
