<?php
$pageTitle = 'Asset Disposal';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../helpers/pagination.php';

if (!hasPermission('fixedasset.dispose')) { die('Access Denied'); }

$db = getDB();
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (a.asset_code LIKE ? OR a.asset_name LIKE ? OR d.id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
// Removed status filter since asset_disposal doesn't have status column

$countStmt = $db->prepare("
    SELECT COUNT(*) as cnt
    FROM asset_disposal d
    JOIN assets a ON d.asset_id = a.id
    $where
");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT d.*, a.asset_code, a.asset_name, f.acquisition_cost, f.accumulated_depreciation,
           (f.acquisition_cost - f.accumulated_depreciation) as net_book_value
    FROM asset_disposal d
    JOIN assets a ON d.asset_id = a.id
    JOIN fixed_asset_financials f ON a.id = f.asset_id
    $where
    ORDER BY d.id DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$disposals = $stmt->fetchAll();

?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Asset Disposal</h1>
        <p class="text-gray-600">Manage asset disposals and calculate gains/losses</p>
    </div>
    <?php if (hasPermission('fixedasset.create')): ?>
    <a href="<?= url('modules/finance/fixed_assets/disposal/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New Disposal</a>
    <?php endif; ?>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Disposal Date</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Proceeds</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">NBV</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Gain/Loss</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($disposals as $d): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= sanitize($d['asset_code']) ?> - <?= sanitize($d['asset_name']) ?></div>
                </td>
                <td class="px-6 py-4 text-center text-gray-600"><?= formatDate($d['disposal_date']) ?></td>
                <td class="px-6 py-4 text-center text-gray-600"><?= ucfirst($d['disposal_type']) ?></td>
                <td class="px-6 py-4 text-right text-gray-600"><?= formatAmount($d['disposal_proceeds']) ?></td>
                <td class="px-6 py-4 text-right text-gray-600"><?= formatAmount($d['net_book_value']) ?></td>
                <td class="px-6 py-4 text-right">
                    <?php if ($d['gain_loss'] >= 0): ?>
                        <span class="px-2 py-1 text-xs rounded bg-green-100 text-green-800">Gain: <?= formatAmount($d['gain_loss']) ?></span>
                    <?php else: ?>
                        <span class="px-2 py-1 text-xs rounded bg-red-100 text-red-800">Loss: <?= formatAmount(abs($d['gain_loss'])) ?></span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-right">
                    <a href="<?= url('modules/finance/fixed_assets/disposal/view.php?id=' . $d['id'] . '') ?>" class="text-blue-600 hover:text-blue-900 mr-2"><i class="fas fa-eye"></i></a>
                    <a href="<?= url('modules/finance/fixed_assets/disposal/edit.php?id=' . $d['id'] . '') ?>" class="text-yellow-600 hover:text-yellow-900 mr-2"><i class="fas fa-edit"></i></a>
                    <a href="?delete=<?= $d['id'] ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this disposal?');"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($disposals)): ?>
            <tr><td colspan="8" class="px-6 py-8 text-center text-gray-500">No disposals found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../../../includes/footer.php'; ?>