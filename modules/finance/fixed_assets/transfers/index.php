<?php
$pageTitle = 'Asset Transfers';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../helpers/pagination.php';

if (!hasPermission('fixedasset.transfer')) { die('Access Denied'); }

$db = getDB();
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (a.asset_code LIKE ? OR a.asset_name LIKE ? OR t.id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status) {
    $where .= " AND t.status = ?";
    $params[] = $status;
}

$countStmt = $db->prepare("
    SELECT COUNT(*) as cnt
    FROM asset_transfer_history t
    JOIN assets a ON t.asset_id = a.id
    $where
");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT t.*, a.asset_code, a.asset_name
    FROM asset_transfer_history t
    JOIN assets a ON t.asset_id = a.id
    $where
    ORDER BY t.transfer_date DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$transfers = $stmt->fetchAll();

?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Asset Transfers</h1>
        <p class="text-gray-600">Track financial transfers of assets between departments, locations, etc.</p>
    </div>
    <?php if (hasPermission('fixedasset.create')): ?>
    <a href="<?= url('modules/finance/fixed_assets/transfers/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New Transfer</a>
    <?php endif; ?>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transfer Date</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($transfers as $t): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= sanitize($t['asset_code']) ?> - <?= sanitize($t['asset_name']) ?></div>
                </td>
                <td class="px-6 py-4 text-center text-gray-600"><?= formatDate($t['transfer_date']) ?></td>
                <td class="px-6 py-4 text-center text-gray-600">
                    <?php
                    $transferTypes = [];
                    if ($t['from_department_id'] || $t['to_department_id']) $transferTypes[] = 'Department';
                    if ($t['from_location_id'] || $t['to_location_id']) $transferTypes[] = 'Location';
                    if ($t['from_project_id'] || $t['to_project_id']) $transferTypes[] = 'Project';
                    if ($t['from_cost_center_id'] || $t['to_cost_center_id']) $transferTypes[] = 'Cost Center';
                    echo implode(', ', $transferTypes) ?: 'Financial';
                    ?>
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2 py-1 text-xs rounded <?= $t['status'] === 'completed' ? 'bg-green-100 text-green-800' : ($t['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>"><?= ucfirst($t['status']) ?></span>
                </td>
                <td class="px-6 py-4 text-right">
                    <a href="<?= url('modules/finance/fixed_assets/transfers/view.php?id=' . $t['id'] . '') ?>" class="text-blue-600 hover:text-blue-900 mr-2"><i class="fas fa-eye"></i></a>
                    <a href="?delete=<?= $t['id'] ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this transfer?');"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($transfers)): ?>
            <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No transfers found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../../../includes/footer.php'; ?>