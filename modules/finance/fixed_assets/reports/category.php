<?php
$pageTitle = 'Asset Category Report';
require_once '../../../../includes/header.php';

if (!hasPermission('fixedasset.report')) { die('Access Denied'); }

$db = getDB();

$assets = $db->query("
    SELECT 
        ac.category_name,
        COUNT(f.id) as asset_count,
        COALESCE(SUM(f.acquisition_cost), 0) as total_cost,
        COALESCE(SUM(f.accumulated_depreciation), 0) as total_depreciation,
        COALESCE(SUM(ai.impairment_loss), 0) as total_impairment,
        COALESCE(SUM(f.acquisition_cost), 0) - COALESCE(SUM(f.accumulated_depreciation), 0) - COALESCE(SUM(ai.impairment_loss), 0) as total_nbv
    FROM fixed_asset_financials f
    LEFT JOIN fixed_asset_categories fac ON f.asset_category_id = fac.id
    LEFT JOIN asset_categories ac ON fac.asset_category_id = ac.id
    LEFT JOIN asset_impairment ai ON f.asset_id = ai.asset_id AND ai.approval_status = 'approved'
    WHERE f.status != 'disposed'
    GROUP BY ac.category_name
    ORDER BY total_cost DESC
")->fetchAll();

$totalCost = array_sum(array_column($assets, 'total_cost'));
$totalDepreciation = array_sum(array_column($assets, 'total_depreciation'));
$totalImpairment = array_sum(array_column($assets, 'total_impairment'));
$totalNBV = $totalCost - $totalDepreciation - $totalImpairment;
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Asset Category Report</h1>
        <p class="text-gray-600">Detailed breakdown of assets by category</p>
    </div>
    <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
        <i class="fas fa-print mr-2"></i>Print
    </button>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left">Category</th>
                <th class="px-4 py-3 text-right">Asset Count</th>
                <th class="px-4 py-3 text-right">Original Cost</th>
                <th class="px-4 py-3 text-right">Accumulated Depreciation</th>
                <th class="px-4 py-3 text-right">Impairment</th>
                <th class="px-4 py-3 text-right">Net Book Value</th>
                <th class="px-4 py-3 text-right">%</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($assets as $cat): ?>
            <?php
            $percent = $totalCost > 0 ? round(($cat['total_cost'] / $totalCost) * 100) : 0;
            ?>
            <tr>
                <td class="px-4 py-3 font-medium text-blue-600"><?= sanitize($cat['category_name']) ?></td>
                <td class="px-4 py-3 text-right"><?= $cat['asset_count'] ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($cat['total_cost']) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($cat['total_depreciation']) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($cat['total_impairment']) ?></td>
                <td class="px-4 py-3 text-right font-bold text-gray-800"><?= formatAmount($cat['total_nbv']) ?></td>
                <td class="px-4 py-3 text-right">
                    <div class="flex items-center justify-end">
                        <span class="mr-2 text-xs text-gray-500"><?= $percent ?>%</span>
                        <div class="w-24 h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full" style="width: <?= $percent ?>%"></div>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-gray-100 font-bold">
            <tr>
                <td class="px-4 py-3">Total</td>
                <td class="px-4 py-3 text-right"><?= array_sum(array_column($assets, 'asset_count')) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($totalCost) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($totalDepreciation) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($totalImpairment) ?></td>
                <td class="px-4 py-3 text-right text-blue-600 text-xl"><?= formatAmount($totalNBV) ?></td>
                <td class="px-4 py-3"></td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="mt-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Top 10 Assets by Cost</h2>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Asset Code</th>
                    <th class="px-4 py-3 text-left">Asset Name</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-right">Cost</th>
                    <th class="px-4 py-3 text-right">NBV</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php
                $topAssets = $db->query("
                    SELECT a.asset_code, a.asset_name, ac.category_name, f.acquisition_cost,
                           (f.acquisition_cost - f.accumulated_depreciation - f.impairment_amount) as nbv
                    FROM fixed_asset_financials f
                    LEFT JOIN fixed_asset_categories fac ON f.asset_category_id = fac.id LEFT JOIN asset_categories ac ON fac.asset_category_id = ac.id
                    LEFT JOIN assets a ON f.asset_id = a.id
                    WHERE f.status != 'disposed'
                    ORDER BY f.acquisition_cost DESC
                    LIMIT 10
                ")->fetchAll();
                ?>
                <?php foreach ($topAssets as $asset): ?>
                <tr>
                    <td class="px-4 py-3 font-medium"><?= sanitize($asset['asset_code']) ?></td>
                    <td class="px-4 py-3"><?= sanitize($asset['asset_name']) ?></td>
                    <td class="px-4 py-3"><?= sanitize($asset['category_name']) ?></td>
                    <td class="px-4 py-3 text-right"><?= formatAmount($asset['acquisition_cost']) ?></td>
                    <td class="px-4 py-3 text-right font-medium text-gray-800"><?= formatAmount($asset['nbv']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
