<?php
$pageTitle = 'Fixed Asset Register Report';
require_once '../../../../includes/header.php';

if (!hasPermission('fixedasset.report')) { die('Access Denied'); }

$db = getDB();

$search = $_GET['search'] ?? '';
$category_id = $_GET['category_id'] ?? '';
$status = $_GET['status'] ?? '';
$method_id = $_GET['method_id'] ?? '';
$year = $_GET['year'] ?? date('Y');

$where = "WHERE 1=1";
$params = [];

if ($search) { 
    $where .= " AND (a.asset_code LIKE ? OR a.asset_name LIKE ? OR f.asset_id = ?)";
    $params[] = "%$search%"; 
    $params[] = "%$search%"; 
    $params[] = $search;
}
if ($category_id) { 
    $where .= " AND f.asset_category_id = ?"; 
    $params[] = $category_id; 
}
if ($status) { 
    $where .= " AND f.status = ?"; 
    $params[] = $status; 
}
if ($method_id) { 
    $where .= " AND f.depreciation_method_id = ?"; 
    $params[] = $method_id; 
}

// Get asset categories for filter
$categories = $db->query("SELECT fc.id, ac.category_name FROM fixed_asset_categories fc LEFT JOIN asset_categories ac ON fc.asset_category_id = ac.id WHERE fc.status = 'active' ORDER BY ac.category_name")->fetchAll();

// Get depreciation methods for filter
$methods = $db->query("SELECT * FROM depreciation_methods WHERE status = 'active' ORDER BY method_name")->fetchAll();

// Get assets
$assets = $db->prepare("
    SELECT 
        f.id as financial_id,
        f.asset_id,
        a.asset_code,
        a.asset_name,
        ac.category_name,
        dm.method_name,
        dm.method_code,
        f.capitalization_date,
        f.acquisition_cost,
        f.residual_value,
        f.useful_life_months,
        f.accumulated_depreciation,
        f.status,
        COALESCE(ai.impairment_loss, 0) as impairment_amount
    FROM fixed_asset_financials f
    LEFT JOIN fixed_asset_categories fac ON f.asset_category_id = fac.id
    LEFT JOIN asset_categories ac ON fac.asset_category_id = ac.id
    LEFT JOIN assets a ON f.asset_id = a.id
    LEFT JOIN depreciation_methods dm ON f.depreciation_method_id = dm.id
    LEFT JOIN asset_impairment ai ON f.asset_id = ai.asset_id AND ai.approval_status = 'approved'
    $where
    ORDER BY a.asset_code
");
$assets->execute($params);
$assets = $assets->fetchAll();

// Calculate totals
$totalCost = array_sum(array_column($assets, 'acquisition_cost'));
$totalDepreciation = array_sum(array_column($assets, 'accumulated_depreciation'));
$totalImpairment = array_sum(array_column($assets, 'impairment_amount'));
$totalNBV = $totalCost - $totalDepreciation - $totalImpairment;
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Fixed Asset Register</h1>
        <p class="text-gray-600">Complete list of all fixed assets with financial details</p>
    </div>
    <div class="flex gap-2">
        <form method="GET" class="flex gap-2">
            <input type="hidden" name="report" value="register">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                <i class="fas fa-file-export mr-2"></i>Export CSV
            </button>
        </form>
        <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
            <i class="fas fa-print mr-2"></i>Print
        </button>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <input type="hidden" name="report" value="register">
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
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Depreciation Method</label>
            <select name="method_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Methods</option>
                <?php foreach ($methods as $method): ?>
                <option value="<?= $method['id'] ?>" <?= $method_id == $method['id'] ? 'selected' : '' ?>>
                    <?= sanitize($method['method_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-search"></i></button>
    </form>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
        <div class="text-sm text-gray-500">Total Assets</div>
        <div class="text-2xl font-bold text-gray-800"><?= count($assets) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
        <div class="text-sm text-gray-500">Total Cost</div>
        <div class="text-2xl font-bold text-gray-800"><?= formatAmount($totalCost) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-indigo-500">
        <div class="text-sm text-gray-500">Accumulated Depreciation</div>
        <div class="text-2xl font-bold text-gray-800"><?= formatAmount($totalDepreciation) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
        <div class="text-sm text-gray-500">Net Book Value</div>
        <div class="text-2xl font-bold text-gray-800"><?= formatAmount($totalNBV) ?></div>
    </div>
</div>

<!-- Report Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="bg-gray-50 p-4 border-b">
        <h3 class="text-lg font-semibold text-gray-800">Fixed Asset Register Report</h3>
        <p class="text-sm text-gray-500">Generated on: <?= date('M d, Y H:i:s') ?></p>
    </div>
    
    <table class="w-full text-sm">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-4 py-3 text-left">Asset Code</th>
                <th class="px-4 py-3 text-left">Asset Name</th>
                <th class="px-4 py-3 text-left">Category</th>
                <th class="px-4 py-3 text-left">Method</th>
                <th class="px-4 py-3 text-right">Cost</th>
                <th class="px-4 py-3 text-right">Depreciation</th>
                <th class="px-4 py-3 text-right">Impairment</th>
                <th class="px-4 py-3 text-right">Net Book Value</th>
                <th class="px-4 py-3 text-center">Status</th>
                <th class="px-4 py-3 text-left">Capitalized On</th>
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
                <td class="px-4 py-3"><?= sanitize($asset['method_name']) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($asset['acquisition_cost']) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($asset['accumulated_depreciation']) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($asset['impairment_amount']) ?></td>
                <td class="px-4 py-3 text-right font-medium"><?= formatAmount($nbv) ?></td>
                <td class="px-4 py-3 text-center">
                    <span class="px-2 py-1 text-xs rounded
                        <?= $asset['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                        <?= ucfirst($asset['status']) ?>
                    </span>
                </td>
                <td class="px-4 py-3"><?= date('M d, Y', strtotime($asset['capitalization_date'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-gray-100 font-bold">
            <tr>
                <td colspan="4" class="px-4 py-3">Total</td>
                <td class="px-4 py-3 text-right"><?= formatAmount($totalCost) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($totalDepreciation) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($totalImpairment) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($totalNBV) ?></td>
                <td colspan="2" class="px-4 py-3"></td>
            </tr>
        </tfoot>
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
