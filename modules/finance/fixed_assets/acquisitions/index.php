<?php
$pageTitle = 'Asset Capitalization';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../helpers/pagination.php';
require_once '../../../../helpers/currency.php';
require_once '../depreciationservice.php';

if (!hasPermission('fixedasset.capitalize')) { die('Access Denied'); }

$db = getDB();
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (f.id LIKE ? OR a.asset_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status) {
    $where .= " AND f.status = ?";
    $params[] = $status;
}

$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM fixed_asset_financials f $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("SELECT f.*, a.asset_code, a.asset_name 
    FROM fixed_asset_financials f 
    JOIN assets a ON f.asset_id = a.id 
    $where 
    ORDER BY f.id DESC 
    LIMIT ? OFFSET ?");
$stmt->execute($queryParams);
$capitalizations = $stmt->fetchAll();

$statusColors = [
    'active' => 'bg-green-100 text-green-800',
    'disposed' => 'bg-red-100 text-red-800',
    'impaired' => 'bg-yellow-100 text-yellow-800',
];
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Asset Capitalization</h1>
        <p class="text-gray-600">Manage asset capitalization and financial records</p>
    </div>
    <?php if (hasPermission('fixedasset.create')): ?>
    <a href="<?= url('modules/finance/fixed_assets/acquisitions/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>Capitalize Asset</a>
    <?php endif; ?>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cost</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Capitalization Date</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($capitalizations as $c): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= sanitize($c['asset_code']) ?> - <?= sanitize($c['asset_name']) ?></div>
                </td>
                <td class="px-6 py-4 text-right text-gray-600"><?= formatMoney($c['capitalized_cost']) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= date('Y-m-d', strtotime($c['capitalization_date'])) ?></td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2 py-1 text-xs rounded <?= $statusColors[$c['status']] ?? 'bg-gray-100 text-gray-800' ?>"><?= ucfirst($c['status']) ?></span>
                </td>
                <td class="px-6 py-4 text-right">
                    <a href="<?= url('modules/finance/fixed_assets/acquisitions/view.php?id=' . $c['id'] . '') ?>" class="text-blue-600 hover:text-blue-900 mr-2"><i class="fas fa-eye"></i></a>
                    <?php if (hasPermission('fixedasset.edit')): ?>
                    <a href="<?= url('modules/finance/fixed_assets/acquisitions/edit.php?id=' . $c['id'] . '') ?>" class="text-yellow-600 hover:text-yellow-900 mr-2"><i class="fas fa-edit"></i></a>
                    <?php endif; ?>
                    <a href="?delete=<?= $c['id'] ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this capitalization?');"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($capitalizations)): ?>
            <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No capitalizations found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../../../includes/footer.php'; ?>