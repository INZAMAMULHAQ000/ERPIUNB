<?php
$pageTitle = 'Bank Accounts';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';
require_once '../helpers/finance_helpers.php';
require_once '../helpers/banking_helpers.php';

if (!hasPermission('finance.bank.view')) { die('Access Denied'); }

$db = getDB();
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];
if ($search) { $where .= " AND (ba.account_name LIKE ? OR ba.bank_name LIKE ? OR ba.account_number LIKE ?)";
    $s = "%$search%"; $params[] = $s; $params[] = $s; $params[] = $s; }
if ($statusFilter !== '') { $where .= " AND ba.status = ?"; $params[] = $statusFilter; }

$countStmt = $db->prepare("SELECT COUNT(*) FROM bank_accounts ba $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT ba.*, coa.account_code, coa.account_name AS coa_name
    FROM bank_accounts ba
    LEFT JOIN chart_of_accounts coa ON ba.chart_account_id = coa.id
    $where ORDER BY ba.account_name LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$accounts = $stmt->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Bank Accounts</h1>
        <p class="text-gray-600">Manage bank accounts</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
        <?php if (hasPermission('finance.bank.create')): ?>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>Add Bank Account</a>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Name, Bank, Account #..." class="w-full px-3 py-2 border rounded">
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
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bank</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account No</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Currency</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Linked CoA</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($accounts as $a): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= sanitize($a['account_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($a['bank_name']) ?></td>
                    <td class="px-4 py-3 text-sm font-mono text-gray-900"><?= sanitize($a['account_number']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($a['currency']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($a['coa_name'] ?? '-') ?></td>
                    <td class="px-4 py-3 text-center"><?= formatBankStatus($a['status']) ?></td>
                    <td class="px-4 py-3 text-right">
                        <?php if (hasPermission('finance.bank.edit')): ?>
                        <a href="edit.php?id=<?= $a['id'] ?>" class="text-yellow-600 hover:text-yellow-900" title="Edit"><i class="fas fa-edit"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($accounts)): ?>
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-university text-4xl mb-3 text-gray-300"></i><p>No bank accounts found</p></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../../includes/footer.php'; ?>
