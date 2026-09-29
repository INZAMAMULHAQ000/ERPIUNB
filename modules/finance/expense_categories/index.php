<?php
$pageTitle = 'Expense Categories';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';

if (!hasPermission('expense_categories.view')) { die('Access Denied'); }

$db = getDB();

$search = $_GET['search'] ?? '';
$page = getPageNumber();
$perPage = 20;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (ec.name LIKE ? OR ec.description LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM expense_categories ec $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT ec.*, coa.account_code, coa.account_name
    FROM expense_categories ec
    LEFT JOIN chart_of_accounts coa ON ec.default_expense_account_id = coa.id
    $where ORDER BY ec.name ASC LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$categories = $stmt->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Expense Categories</h1>
        <p class="text-gray-600">Manage expense categories and default GL account mapping</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
        <?php if (hasPermission('expense_categories.create')): ?>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New Category</a>
        <?php endif; ?>
    </div>
</div>

<!-- Search -->
<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Search categories..." class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-search"></i></button>
        <?php if ($search): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Default GL Account</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($categories as $c): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= sanitize($c['name']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($c['description'] ?? '--') ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?php if ($c['account_code']): ?>
                        <span class="font-mono"><?= sanitize($c['account_code']) ?></span> - <?= sanitize($c['account_name'] ?? '') ?>
                        <?php else: ?>
                        <span class="text-gray-400 italic">Not set</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($c['is_active']): ?>
                        <span class="px-2 py-1 text-xs rounded font-medium bg-green-100 text-green-800">Active</span>
                        <?php else: ?>
                        <span class="px-2 py-1 text-xs rounded font-medium bg-red-100 text-red-800">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?php if (hasPermission('expense_categories.edit')): ?>
                        <a href="edit.php?id=<?= $c['id'] ?>" class="text-yellow-600 hover:text-yellow-900" title="Edit"><i class="fas fa-pencil"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-tags text-4xl mb-3 text-gray-300"></i>
                    <p>No expense categories found</p>
                    <?php if (hasPermission('expense_categories.create')): ?>
                    <a href="create.php" class="text-blue-600 hover:underline mt-2 inline-block">Create your first category</a>
                    <?php endif; ?>
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../../includes/footer.php'; ?>
