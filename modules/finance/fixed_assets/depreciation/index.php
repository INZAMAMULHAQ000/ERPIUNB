<?php
$pageTitle = 'Depreciation Processing';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../helpers/pagination.php';
require_once '../../../../helpers/currency.php';
require_once '../depreciationservice.php';

if (!hasPermission('fixedasset.depreciate')) { die('Access Denied'); }

$db = getDB();
$asset_id = $_GET['asset_id'] ?? '';
$period = $_GET['period'] ?? '';

$where = "WHERE f.status = 'active'";
$params = [];

if ($asset_id) {
    $where .= " AND f.asset_id = ?";
    $params[] = $asset_id;
}
if ($period) {
    $where .= " AND f.id = ?"; // Simplified for demo
    $params[] = $period;
}

$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM fixed_asset_financials f $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];
$pagination = paginate($total, 10, $page);

$queryParams = array_merge($params, [10, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT f.*, a.asset_code, a.asset_name, d.method_name
    FROM fixed_asset_financials f
    JOIN assets a ON f.asset_id = a.id
    JOIN depreciation_methods d ON f.depreciation_method_id = d.id
    $where
    ORDER BY f.id DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$assets = $stmt->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Depreciation Processing</h1>
        <p class="text-gray-600">Process monthly depreciation for assets</p>
    </div>
    <div>
        <a href="<?= url('modules/finance/fixed_assets/acquisitions/index.php') ?>" class="text-blue-600 hover:text-blue-900 mr-2"><i class="fas fa-arrow-left mr-1"></i>Back to Capitalizations</a>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-4">
    <form method="GET" class="p-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Asset</label>
                <select name="asset_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">All Assets</option>
                    <?php
                    $allAssets = $db->query("SELECT a.id, a.asset_code, a.asset_name FROM assets a JOIN fixed_asset_financials f ON a.id = f.asset_id WHERE f.status = 'active' ORDER BY a.asset_name")->fetchAll();
                    foreach ($allAssets as $asset): ?>
                    <option value="<?= $asset['id'] ?>" <?= $asset_id == $asset['id'] ? 'selected' : '' ?>><?= sanitize($asset['asset_code']) ?> - <?= sanitize($asset['asset_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                    <i class="fas fa-filter mr-1"></i>Filter
                </button>
            </div>
        </div>
    </form>
</div>

<?php if (!empty($assets)): ?>
<div class="bg-white rounded-lg shadow">
    <div class="p-4">
        <h2 class="text-xl font-semibold mb-4">Assets Ready for Depreciation</h2>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cost</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">NBV</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Monthly Deprec</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($assets as $asset): 
                    $deprService = new DepreciationService();
                    $monthlyDepreciation = $deprService->calculatePeriodDepreciation(
                        (float)$asset['capitalized_cost'],
                        (float)$asset['residual_value'],
                        (int)$asset['useful_life_months'],
                        $asset['method_code'],
                        0,
                        (float)$asset['accumulated_depreciation']
                    );
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900"><?= sanitize($asset['asset_code']) ?> - <?= sanitize($asset['asset_name']) ?></div>
                        </td>
                        <td class="px-6 py-4 text-right text-gray-600"><?= formatAmount($asset['capitalized_cost']) ?></td>
                        <td class="px-6 py-4 text-right text-gray-600"><?= formatAmount($asset['net_book_value']) ?></td>
                        <td class="px-6 py-4 text-right text-gray-600"><?= formatAmount($monthlyDepreciation) ?></td>
                        <td class="px-6 py-4 text-center">
                            <a href="<?= url('modules/finance/fixed_assets/depreciation/process.php?asset_id=' . $asset['id'] . '&amount=' . $monthlyDepreciation) ?>" 
                               class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                                Process Depreciation
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="bg-white rounded-lg shadow">
    <div class="p-8 text-center">
        <p class="text-gray-500">No assets found matching the criteria.</p>
    </div>
</div>
<?php endif; ?>

<?php require_once '../../../../includes/footer.php'; ?>