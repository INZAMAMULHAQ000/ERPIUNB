<?php
$pageTitle = 'Financial Assets';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../helpers/pagination.php';
require_once '../../../../helpers/currency.php';
require_once '../depreciationservice.php';

if (!hasPermission('fixedasset.view')) { die('Access Denied'); }

$db = getDB();
$search = $_GET['search'] ?? '';
$category_id = $_GET['category_id'] ?? '';
$status = $_GET['status'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (a.asset_code LIKE ? OR a.asset_name LIKE ? OR f.id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($category_id) {
    $where .= " AND f.asset_category_id = ?";
    $params[] = $category_id;
}
if ($status) {
    $where .= " AND f.status = ?";
    $params[] = $status;
}

$countStmt = $db->prepare("
    SELECT COUNT(*) as cnt
    FROM fixed_asset_financials f
    JOIN assets a ON f.asset_id = a.id
    $where
");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT f.*, a.asset_code, a.asset_name, a.serial_number, c.category_name
    FROM fixed_asset_financials f
    JOIN assets a ON f.asset_id = a.id
    LEFT JOIN fixed_asset_categories fc ON f.asset_category_id = fc.id
    LEFT JOIN asset_categories c ON fc.asset_category_id = c.id
    $where
    ORDER BY f.id DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$assets = $stmt->fetchAll();

$categories = $db->query("SELECT id, category_name FROM asset_categories WHERE status = 'active' ORDER BY category_name")->fetchAll();

$statusColors = [
    'active'    => 'bg-green-100 text-green-800',
    'disposed'  => 'bg-red-100 text-red-800',
    'impaired'  => 'bg-yellow-100 text-yellow-800',
];
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Financial Asset Register</h1>
        <p class="text-gray-600">Financial records for all capitalized assets</p>
    </div>
    <?php if (hasPermission('fixedasset.capitalize')): ?>
    <a href="<?= url('modules/finance/fixed_assets/acquisitions/index.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>Capitalize Asset</a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
    <?php
    $totalCost = $db->query("SELECT COALESCE(SUM(capitalized_cost), 0) as val FROM fixed_asset_financials")->fetch()['val'] ?? 0;
    $totalAccum = $db->query("SELECT COALESCE(SUM(accumulated_depreciation), 0) as val FROM fixed_asset_financials")->fetch()['val'] ?? 0;
    $totalNBV = $db->query("SELECT COALESCE(SUM(net_book_value), 0) as val FROM fixed_asset_financials")->fetch()['val'] ?? 0;
    $totalActive = $db->query("SELECT COUNT(*) as c FROM fixed_asset_financials WHERE status = 'active'")->fetch()['c'];
    $totalDisposed = $db->query("SELECT COUNT(*) as c FROM fixed_asset_financials WHERE status = 'disposed'")->fetch()['c'];
    ?>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Total Asset Cost</div>
        <div class="text-2xl font-bold text-gray-800"><?= formatMoney($totalCost) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Accumulated Depreciation</div>
        <div class="text-2xl font-bold text-orange-600"><?= formatMoney($totalAccum) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Net Book Value</div>
        <div class="text-2xl font-bold text-green-600"><?= formatMoney($totalNBV) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Active Assets</div>
        <div class="text-2xl font-bold text-gray-600"><?= $totalActive ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Disposed Assets</div>
        <div class="text-2xl font-bold text-red-600"><?= $totalDisposed ?></div>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Asset code, name..." class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-48">
            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
            <select name="category_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-32">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="disposed" <?= $status === 'disposed' ? 'selected' : '' ?>>Disposed</option>
                <option value="impaired" <?= $status === 'impaired' ? 'selected' : '' ?>>Impaired</option>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-search"></i></button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cost</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Accum Deprec</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">NBV</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($assets as $asset): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= sanitize($asset['asset_code']) ?> - <?= sanitize($asset['asset_name']) ?></div>
                    <div class="text-sm text-gray-500"><?= sanitize($asset['serial_number'] ?? '') ?></div>
                </td>
                <td class="px-6 py-4 text-gray-600"><?= sanitize($asset['category_name'] ?? '-') ?></td>
                <td class="px-6 py-4 text-right text-gray-600"><?= formatMoney($asset['capitalized_cost']) ?></td>
                <td class="px-6 py-4 text-right text-gray-600"><?= formatMoney($asset['accumulated_depreciation']) ?></td>
                <td class="px-6 py-4 text-right text-gray-600"><?= formatMoney($asset['net_book_value']) ?></td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2 py-1 text-xs rounded <?= $statusColors[$asset['status']] ?? 'bg-gray-100 text-gray-800' ?>"><?= ucfirst($asset['status']) ?></span>
                </td>
                <td class="px-6 py-4 text-right">
                    <a href="<?= url('modules/finance/fixed_assets/financial_records/view.php?id=' . $asset['id'] . '') ?>" class="text-blue-600 hover:text-blue-900 mr-2"><i class="fas fa-eye"></i></a>
                    <a href="<?= url('modules/finance/fixed_assets/financial_records/edit.php?id=' . $asset['id'] . '') ?>" class="text-yellow-600 hover:text-yellow-900 mr-2"><i class="fas fa-edit"></i></a>
                    <a href="<?= url('modules/finance/fixed_assets/disposal/index.php?asset_id=' . $asset['asset_id'] . '') ?>" class="text-red-600 hover:text-red-900" title="Dispose"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($assets)): ?>
            <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No financial assets found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../../../includes/footer.php'; ?>