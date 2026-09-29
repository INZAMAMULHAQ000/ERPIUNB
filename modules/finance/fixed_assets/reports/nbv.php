<?php
$pageTitle = 'Net Book Value Report';
require_once '../../../../includes/header.php';

if (!hasPermission('fixedasset.report')) { die('Access Denied'); }

$db = getDB();

$search = $_GET['search'] ?? '';
$category_id = $_GET['category_id'] ?? '';
$status = $_GET['status'] ?? '';

$where = "WHERE 1=1";
$params = [];

if ($search) { 
    $where .= " AND (a.asset_code LIKE ? OR a.asset_name LIKE ?)";
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

// Get asset categories
$categories = $db->query("SELECT fc.id, ac.category_name FROM fixed_asset_categories fc LEFT JOIN asset_categories ac ON fc.asset_category_id = ac.id WHERE fc.status = 'active' ORDER BY ac.category_name")->fetchAll();

// Get assets
$assets = $db->prepare("
    SELECT 
        f.id as financial_id,
        a.asset_code,
        a.asset_name,
        ac.category_name,
        f.acquisition_cost,
        f.accumulated_depreciation,
        COALESCE(ai.impairment_loss, 0) as impairment_amount,
        f.status
    FROM fixed_asset_financials f
    LEFT JOIN fixed_asset_categories fac ON f.asset_category_id = fac.id
    LEFT JOIN asset_categories ac ON fac.asset_category_id = ac.id
    LEFT JOIN assets a ON f.asset_id = a.id
    LEFT JOIN asset_impairment ai ON f.asset_id = ai.asset_id AND ai.approval_status = 'approved'
    $where
    ORDER BY a.asset_code
");
$assets->execute($params);
$assets = $assets->fetchAll();

// Calculate totals by category
$categoryTotals = [];
foreach ($assets as $asset) {
    if (!isset($categoryTotals[$asset['category_name']])) {
        $categoryTotals[$asset['category_name']] = [
            'cost' => 0,
            'depreciation' => 0,
            'impairment' => 0,
            'nbv' => 0
        ];
    }
    $categoryTotals[$asset['category_name']]['cost'] += $asset['acquisition_cost'];
    $categoryTotals[$asset['category_name']]['depreciation'] += $asset['accumulated_depreciation'];
    $categoryTotals[$asset['category_name']]['impairment'] += $asset['impairment_amount'];
    $categoryTotals[$asset['category_name']]['nbv'] += ($asset['acquisition_cost'] - $asset['accumulated_depreciation'] - $asset['impairment_amount']);
}

$totalCost = array_sum(array_column($assets, 'acquisition_cost'));
$totalDepreciation = array_sum(array_column($assets, 'accumulated_depreciation'));
$totalImpairment = array_sum(array_column($assets, 'impairment_amount'));
$totalNBV = $totalCost - $totalDepreciation - $totalImpairment;
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Net Book Value Report</h1>
        <p class="text-gray-600">Asset value breakdown by category</p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
            <i class="fas fa-print mr-2"></i>Print
        </button>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <input type="hidden" name="report" value="nbv">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Asset code or name" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
            <select name="category_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                    <?= sanitize($cat['category_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Status</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="disposed" <?= $status === 'disposed' ? 'selected' : '' ?>>Disposed</option>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-search"></i></button>
    </form>
</div>

<!-- Category Breakdown -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="bg-gray-50 p-4 border-b">
        <h3 class="text-lg font-semibold text-gray-800">Net Book Value by Category</h3>
        <p class="text-sm text-gray-500">Generated on: <?= date('M d, Y H:i:s') ?></p>
    </div>
    
    <table class="w-full text-sm">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-4 py-3 text-left">Category</th>
                <th class="px-4 py-3 text-right">Original Cost</th>
                <th class="px-4 py-3 text-right">Accumulated Depreciation</th>
                <th class="px-4 py-3 text-right">Impairment</th>
                <th class="px-4 py-3 text-right">Net Book Value</th>
                <th class="px-4 py-3 text-right">%</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($categoryTotals as $category => $totals): ?>
            <?php
            $nbv = $totals['cost'] - $totals['depreciation'] - $totals['impairment'];
            $percent = $totalCost > 0 ? round(($totals['cost'] / $totalCost) * 100) : 0;
            ?>
            <tr>
                <td class="px-4 py-3 font-medium text-blue-600"><?= sanitize($category) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($totals['cost']) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($totals['depreciation']) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($totals['impairment']) ?></td>
                <td class="px-4 py-3 text-right font-bold text-gray-800"><?= formatAmount($nbv) ?></td>
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
                <td class="px-4 py-3 text-right"><?= formatAmount($totalCost) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($totalDepreciation) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($totalImpairment) ?></td>
                <td class="px-4 py-3 text-right text-blue-600 text-xl"><?= formatAmount($totalNBV) ?></td>
                <td class="px-4 py-3"></td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- Asset Details -->
<div class="bg-white rounded-lg shadow overflow-hidden mt-6">
    <div class="bg-gray-50 p-4 border-b">
        <h3 class="text-lg font-semibold text-gray-800">Asset Details</h3>
    </div>
    
    <table class="w-full text-sm">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-4 py-3 text-left">Asset Code</th>
                <th class="px-4 py-3 text-left">Asset Name</th>
                <th class="px-4 py-3 text-left">Category</th>
                <th class="px-4 py-3 text-right">Original Cost</th>
                <th class="px-4 py-3 text-right">Accumulated Depreciation</th>
                <th class="px-4 py-3 text-right">Impairment</th>
                <th class="px-4 py-3 text-right">Net Book Value</th>
                <th class="px-4 py-3 text-center">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($assets as $asset): ?>
            <?php
            $nbv = $asset['acquisition_cost'] - $asset['accumulated_depreciation'] - $asset['impairment_amount'];
            ?>
            <tr>
                <td class="px-4 py-3 font-medium"><?= sanitize($asset['asset_code']) ?></td>
                <td class="px-4 py-3"><?= sanitize($asset['asset_name']) ?></td>
                <td class="px-4 py-3"><?= sanitize($asset['category_name']) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($asset['acquisition_cost']) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($asset['accumulated_depreciation']) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($asset['impairment_amount']) ?></td>
                <td class="px-4 py-3 text-right font-medium text-gray-800"><?= formatAmount($nbv) ?></td>
                <td class="px-4 py-3 text-center">
                    <span class="px-2 py-1 text-xs rounded
                        <?= $asset['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                        <?= ucfirst($asset['status']) ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Print Styles -->
<style media="print">
    @page { size: auto; margin: 20mm; }
    body { margin: 0; }
    .no-print { display: none; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #000; padding: 8px; }
    thead { background: #eee; font-weight: bold; }
</style>

<?php require_once '../../../../includes/footer.php'; ?>
