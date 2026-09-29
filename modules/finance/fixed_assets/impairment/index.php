<?php
$pageTitle = 'Impairment';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../helpers/pagination.php';

if (!hasPermission('fixedasset.impair')) { die('Access Denied'); }

$db = getDB();
$search = $_GET['search'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (a.asset_code LIKE ? OR a.asset_name LIKE ? OR i.id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$countStmt = $db->prepare("
    SELECT COUNT(*) as cnt
    FROM asset_impairment i
    JOIN assets a ON i.asset_id = a.id
    $where
");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT i.*, a.asset_code, a.asset_name, f.capitalized_cost, f.net_book_value
    FROM asset_impairment i
    JOIN assets a ON i.asset_id = a.id
    JOIN fixed_asset_financials f ON a.id = f.asset_id
    $where
    ORDER BY i.id DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$impairments = $stmt->fetchAll();

$statusColors = [
    'pending' => 'bg-yellow-100 text-yellow-800',
    'approved' => 'bg-green-100 text-green-800',
    'rejected' => 'bg-red-100 text-red-800',
];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Asset Impairment</h1>
        <p class="text-gray-600">Track asset impairment charges</p>
    </div>
    <?php if (hasPermission('fixedasset.create')): ?>
    <a href="<?= url('modules/finance/fixed_assets/impairment/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New Impairment</a>
    <?php endif; ?>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Impairment Date</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Carrying Amount</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Recoverable Amount</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Loss</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($impairments as $i): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= sanitize($i['asset_code']) ?> - <?= sanitize($i['asset_name']) ?></div>
                </td>
                <td class="px-6 py-4 text-center text-gray-600"><?= formatDate($i['impairment_date']) ?></td>
                <td class="px-6 py-4 text-right text-gray-600"><?= formatAmount($i['carrying_amount']) ?></td>
                <td class="px-6 py-4 text-right text-gray-600"><?= formatAmount($i['recoverable_amount']) ?></td>
                <td class="px-6 py-4 text-right text-gray-600"><?= formatAmount($i['impairment_loss']) ?></td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2 py-1 text-xs rounded <?= $statusColors[$i['approval_status']] ?? 'bg-gray-100 text-gray-800' ?>"><?= ucfirst($i['approval_status']) ?></span>
                </td>
                <td class="px-6 py-4 text-right">
                    <a href="<?= url('modules/finance/fixed_assets/impairment/view.php?id=' . $i['id'] . '') ?>" class="text-blue-600 hover:text-blue-900 mr-2"><i class="fas fa-eye"></i></a>
                    <a href="<?= url('modules/finance/fixed_assets/impairment/edit.php?id=' . $i['id'] . '') ?>" class="text-yellow-600 hover:text-yellow-900 mr-2"><i class="fas fa-edit"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($impairments)): ?>
            <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No impairments found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../../../includes/footer.php'; ?>