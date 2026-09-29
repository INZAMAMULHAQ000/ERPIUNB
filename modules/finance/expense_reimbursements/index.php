<?php
$pageTitle = 'Expense Reimbursements';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';

if (!hasPermission('expense_reimbursements.view')) { die('Access Denied'); }

$db = getDB();

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (er.reimbursement_number LIKE ? OR CONCAT(e.first_name, ' ', e.last_name) LIKE ? OR ec.claim_number LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($status !== '') {
    $where .= " AND er.status = ?";
    $params[] = $status;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM expense_reimbursements er $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT er.*, e.first_name, e.last_name, e.employee_code,
        ec.claim_number, ec.total_amount AS claim_amount
    FROM expense_reimbursements er
    LEFT JOIN employees e ON er.employee_id = e.id
    LEFT JOIN employee_expense_claims ec ON er.claim_id = ec.id
    $where ORDER BY er.created_at DESC LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$reimbursements = $stmt->fetchAll();

$statusBadges = [
    'draft' => 'bg-gray-100 text-gray-800',
    'paid' => 'bg-green-100 text-green-800',
    'cancelled' => 'bg-gray-200 text-gray-600',
];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Expense Reimbursements</h1>
        <p class="text-gray-600">Payments to employees for approved expense claims</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
        <?php if (hasPermission('expense_reimbursements.create')): ?>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New Reimbursement</a>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Reimbursement #, employee, claim..." class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-search"></i></button>
        <?php if ($search || $status !== ''): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reimbursement #</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Claim #</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment Method</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($reimbursements as $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-mono font-medium text-gray-900"><?= sanitize($r['reimbursement_number']) ?></td>
                    <td class="px-4 py-3 text-sm font-mono text-gray-600"><?= sanitize($r['claim_number']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($r['first_name'] . ' ' . $r['last_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= ucwords(str_replace('_', ' ', $r['payment_method'])) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($r['payment_date'])) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-1 text-xs rounded font-medium <?= $statusBadges[$r['status']] ?? 'bg-gray-100' ?>">
                            <?= ucfirst($r['status']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney((float)$r['amount']) ?></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="view.php?id=<?= $r['id'] ?>" class="text-blue-600 hover:text-blue-900" title="View"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($reimbursements)): ?>
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-hand-holding-usd text-4xl mb-3 text-gray-300"></i>
                    <p>No reimbursements found</p>
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../../includes/footer.php'; ?>
