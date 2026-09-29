<?php
$pageTitle = 'Voucher Types';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';

if (!hasPermission('finance.voucher.manage')) {
    die('Access Denied');
}

$db = getDB();
$search = $_GET['search'] ?? '';
$page = getPageNumber();
$perPage = 10;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND vt.voucher_name LIKE ?";
    $params[] = "%$search%";
}

$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM voucher_types vt $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];

$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT *
    FROM voucher_types vt
    $where
    ORDER BY vt.id ASC
    LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$voucherTypes = $stmt->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Voucher Types</h1>
        <p class="text-gray-600">Manage voucher type classifications</p>
    </div>
    <?php if (hasPermission('finance.voucher.manage')): ?>
    <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>Add Voucher Type
    </a>
    <?php endif; ?>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Search by voucher name..."
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-search"></i>
        </button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voucher Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prefix</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Auto Numbering</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($voucherTypes as $vt): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-medium text-gray-900"><?= sanitize($vt['voucher_name']) ?></td>
                <td class="px-6 py-4 text-gray-600 font-mono"><?= sanitize($vt['voucher_prefix']) ?></td>
                <td class="px-6 py-4 text-center">
                    <?php if ($vt['auto_numbering']): ?>
                    <span class="text-green-600"><i class="fas fa-check-circle"></i></span>
                    <?php else: ?>
                    <span class="text-red-600"><i class="fas fa-times-circle"></i></span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-center">
                    <?php if ($vt['status'] === 'active'): ?>
                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                    <?php else: ?>
                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Inactive</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-right">
                    <?php if (hasPermission('finance.voucher.manage')): ?>
                    <a href="edit.php?id=<?= $vt['id'] ?>" class="text-yellow-600 hover:text-yellow-900" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>

            <?php if (empty($voucherTypes)): ?>
            <tr>
                <td colspan="5" class="px-6 py-8 text-center text-gray-500">No voucher types found</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginationLinks($pagination, '?') ?>

<?php require_once '../../../includes/footer.php'; ?>
