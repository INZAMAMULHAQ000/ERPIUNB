<?php
$pageTitle = 'Depreciation Schedule Report';
require_once '../../../../includes/header.php';

if (!hasPermission('fixedasset.report')) { die('Access Denied'); }

$db = getDB();

$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

// Get assets with depreciation for selected period
$assets = $db->prepare("
    SELECT 
        f.id as financial_id,
        a.asset_code,
        a.asset_name,
        ac.category_name,
        dm.method_name,
        SUM(s.depreciation_amount) as total_depreciation,
        f.acquisition_cost,
        f.accumulated_depreciation,
        f.status
    FROM fixed_asset_financials f
    LEFT JOIN assets a ON f.asset_id = a.id
    LEFT JOIN fixed_asset_categories fac ON f.asset_category_id = fac.id
    LEFT JOIN asset_categories ac ON fac.asset_category_id = ac.id
    LEFT JOIN depreciation_methods dm ON f.depreciation_method_id = dm.id
    LEFT JOIN asset_depreciation_schedules s ON f.id = s.financial_asset_id
    WHERE YEAR(s.period_date) = ? AND MONTH(s.period_date) = ?
    GROUP BY f.id, a.asset_code, a.asset_name, ac.category_name, dm.method_name, f.acquisition_cost, f.accumulated_depreciation, f.status
    ORDER BY a.asset_code
");
$assets->execute([$year, $month]);
$assets = $assets->fetchAll();

// Get all periods for the year
$periods = $db->prepare("
    SELECT DISTINCT 
        YEAR(period_date) as year,
        MONTH(period_date) as month,
        DATE_FORMAT(period_date, '%M %Y') as period_name
    FROM asset_depreciation_schedules
    WHERE YEAR(period_date) = ?
    ORDER BY period_date
");
$periods->execute([$year]);
$periods = $periods->fetchAll();

$totalDepreciation = array_sum(array_column($assets, 'total_depreciation'));
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Depreciation Schedule Report</h1>
        <p class="text-gray-600">Monthly depreciation breakdown for <?= date('F Y', strtotime("$year-$month-01")) ?></p>
    </div>
    <div class="flex gap-2">
        <form method="GET" class="flex gap-2">
            <input type="hidden" name="report" value="depreciation">
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
    <form method="GET" class="flex gap-4 items-end">
        <input type="hidden" name="report" value="depreciation">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
            <select name="year" onchange="this.form.submit()" class="w-32 px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <?php for ($y = date('Y') - 5; $y <= date('Y'); $y++): ?>
                <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
            <select name="month" onchange="this.form.submit()" class="w-32 px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $month == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                    <?= date('F', strtotime("2024-$m-01")) ?>
                </option>
                <?php endfor; ?>
            </select>
        </div>
        <a href="<?= url('modules/finance/fixed_assets/reports/depreciation.php?year=' . $year . '&month=' . $month . '&report=depreciation') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-sync-alt"></i> Refresh</a>
    </form>
</div>

<!-- Period Selector -->
<?php if (!empty($periods)): ?>
<div class="flex gap-2 mb-6 overflow-x-auto pb-2">
    <?php foreach ($periods as $period): ?>
    <a href="<?= url('modules/finance/fixed_assets/reports/depreciation.php?year=' . $period['year'] . '&month=' . str_pad($period['month'], 2, '0', STR_PAD_LEFT) . '&report=depreciation') ?>" class="px-4 py-2 rounded <?= ($year == date('Y') && $month == date('m') && $period['year'] == date('Y') && $period['month'] == date('m')) ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
        <?= date('F Y', strtotime($period['year'] . '-' . str_pad($period['month'], 2, '0', STR_PAD_LEFT) . '-01')) ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
        <div class="text-sm text-gray-500">Total Assets with Depreciation</div>
        <div class="text-2xl font-bold text-gray-800"><?= count($assets) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
        <div class="text-sm text-gray-500">Total Depreciation This Month</div>
        <div class="text-2xl font-bold text-gray-800"><?= formatAmount($totalDepreciation) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
        <div class="text-sm text-gray-500">Average Depreciation per Asset</div>
        <div class="text-2xl font-bold text-gray-800"><?= formatAmount(count($assets) > 0 ? $totalDepreciation / count($assets) : 0) ?></div>
    </div>
</div>

<!-- Report Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="bg-gray-50 p-4 border-b">
        <h3 class="text-lg font-semibold text-gray-800">Depreciation Schedule Report for <?= date('F Y', strtotime("$year-$month-01")) ?></h3>
        <p class="text-sm text-gray-500">Generated on: <?= date('M d, Y H:i:s') ?></p>
    </div>
    
    <table class="w-full text-sm">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-4 py-3 text-left">Asset Code</th>
                <th class="px-4 py-3 text-left">Asset Name</th>
                <th class="px-4 py-3 text-left">Category</th>
                <th class="px-4 py-3 text-left">Method</th>
                <th class="px-4 py-3 text-right">Original Cost</th>
                <th class="px-4 py-3 text-right">Accumulated Dep</th>
                <th class="px-4 py-3 text-right">This Month Dep</th>
                <th class="px-4 py-3 text-right">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($assets as $asset): ?>
            <tr>
                <td class="px-4 py-3 font-medium"><?= sanitize($asset['asset_code']) ?></td>
                <td class="px-4 py-3"><?= sanitize($asset['asset_name']) ?></td>
                <td class="px-4 py-3"><?= sanitize($asset['category_name']) ?></td>
                <td class="px-4 py-3"><?= sanitize($asset['method_name']) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($asset['acquisition_cost']) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($asset['accumulated_depreciation']) ?></td>
                <td class="px-4 py-3 text-right text-red-600">-<?= formatAmount($asset['total_depreciation']) ?></td>
                <td class="px-4 py-3 text-center">
                    <span class="px-2 py-1 text-xs rounded
                        <?= $asset['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                        <?= ucfirst($asset['status']) ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <?php if (!empty($assets)): ?>
        <tfoot class="bg-gray-100 font-bold">
            <tr>
                <td colspan="6" class="px-4 py-3">Total Depreciation</td>
                <td class="px-4 py-3 text-right text-red-600">-<?= formatAmount($totalDepreciation) ?></td>
                <td class="px-4 py-3"></td>
            </tr>
        </tfoot>
        <?php endif; ?>
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
