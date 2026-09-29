<?php
$pageTitle = 'Cash Accounts';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';
require_once '../helpers/finance_helpers.php';
require_once '../helpers/banking_helpers.php';

if (!hasPermission('finance.cash.view')) { die('Access Denied'); }

$db = getDB();
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];
if ($search) { $where .= " AND (ca.cash_name LIKE ? OR ca.location LIKE ?)";
    $s = "%$search%"; $params[] = $s; $params[] = $s; }
if ($statusFilter !== '') { $where .= " AND ca.status = ?"; $params[] = $statusFilter; }

$countStmt = $db->prepare("SELECT COUNT(*) FROM cash_accounts ca $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT ca.*, coa.account_code, coa.account_name AS coa_name, u.name AS custodian_name
    FROM cash_accounts ca
    LEFT JOIN chart_of_accounts coa ON ca.chart_account_id = coa.id
    LEFT JOIN users u ON ca.custodian_employee_id = u.id
    $where ORDER BY ca.cash_name LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$accounts = $stmt->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Cash Accounts</h1>
        <p class="text-gray-600">Manage cash accounts (Main Cash, Petty Cash, etc.)</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>Add Cash Account</a>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Name, Location..." class="w-full px-3 py-2 border rounded">
        </div>
        <div class="w-36">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded">
                <option value="">All</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-search"></i></button>
        <?php if ($search || $statusFilter !== ''): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cash Name</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Custodian</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Linked CoA</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($accounts as $a): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= sanitize($a['cash_name']) ?></td>
                <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($a['location'] ?? '-') ?></td>
                <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($a['custodian_name'] ?? '-') ?></td>
                <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($a['coa_name'] ?? '-') ?></td>
                <td class="px-4 py-3 text-center"><?= formatBankStatus($a['status']) ?></td>
                <td class="px-4 py-3 text-right">
                    <a href="edit.php?id=<?= $a['id'] ?>" class="text-yellow-600 hover:text-yellow-900" title="Edit"><i class="fas fa-edit"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($accounts)): ?>
            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-money-bill-wave text-4xl mb-3 text-gray-300"></i><p>No cash accounts found</p></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../../includes/footer.php'; ?>
