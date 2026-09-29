<?php
$pageTitle = 'Asset Gain/Loss Report';
require_once '../../../../includes/header.php';

if (!hasPermission('fixedasset.report')) { die('Access Denied'); }

$db = getDB();

$search = $_GET['search'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$year = $_GET['year'] ?? date('Y');

$where = "WHERE f.status = 'disposed' AND f.gain_loss_amount != 0";
$params = [];

if ($search) { 
    $where .= " AND (a.asset_code LIKE ? OR a.asset_name LIKE ?)";
    $params[] = "%$search%"; 
    $params[] = "%$search%"; 
}
if ($from_date) { $where .= " AND f.disposal_date >= ?"; $params[] = $from_date; }
if ($to_date) { $where .= " AND f.disposal_date <= ?"; $params[] = $to_date; }

$assets = $db->prepare("
    SELECT 
        f.id as financial_id,
        a.asset_code,
        a.asset_name,
        ac.category_name,
        f.acquisition_cost,
        f.accumulated_depreciation,
        f.impairment_amount,
        ad.disposal_date,
        ad.disposal_type,
        ad.disposal_proceeds,
        ad.gain_loss as gain_loss_amount,
        f.status
    FROM fixed_asset_financials f
    LEFT JOIN fixed_asset_categories fac ON f.asset_category_id = fac.id
    LEFT JOIN asset_categories ac ON fac.asset_category_id = ac.id
    LEFT JOIN assets a ON f.asset_id = a.id
    LEFT JOIN asset_disposal ad ON f.asset_id = ad.asset_id
    WHERE f.status = 'disposed' AND ad.gain_loss != 0
    ORDER BY ad.disposal_date DESC
");
$assets->execute($params);
$assets = $assets->fetchAll();

$totalProceeds = array_sum(array_column($assets, 'disposal_proceeds'));
$totalGain = array_sum(array_filter(array_column($assets, 'gain_loss_amount'), function($val) { return $val > 0; }));
$totalLoss = abs(array_sum(array_filter(array_column($assets, 'gain_loss_amount'), function($val) { return $val < 0; })));
$totalNet = $totalGain - $totalLoss;
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Asset Gain/Loss Report</h1>
        <p class="text-gray-600">Detailed gain/loss on asset disposals</p>
    </div>
    <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
        <i class="fas fa-print mr-2"></i>Print
    </button>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
        <div class="text-sm text-green-600">Total Gains</div>
        <div class="text-2xl font-bold text-green-800"><?= formatAmount($totalGain) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
        <div class="text-sm text-red-600">Total Losses</div>
        <div class="text-2xl font-bold text-red-800"><?= formatAmount($totalLoss) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 <?= $totalNet >= 0 ? 'border-green-500' : 'border-red-500' ?>">
        <div class="text-sm text-gray-600">Net Gain/Loss</div>
        <div class="text-2xl font-bold <?= $totalNet >= 0 ? 'text-green-800' : 'text-red-800' ?>"><?= formatAmount($totalNet) ?></div>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left">Asset Code</th>
                <th class="px-4 py-3 text-left">Asset Name</th>
                <th class="px-4 py-3 text-left">Category</th>
                <th class="px-4 py-3 text-right">Original Cost</th>
                <th class="px-4 py-3 text-right">Book Value</th>
                <th class="px-4 py-3 text-right">Proceeds</th>
                <th class="px-4 py-3 text-right">Gain/Loss</th>
                <th class="px-4 py-3 text-center">Type</th>
                <th class="px-4 py-3 text-left">Disposal Date</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($assets as $asset): ?>
            <?php
            $bookValue = $asset['acquisition_cost'] - $asset['accumulated_depreciation'] - $asset['impairment_amount'];
            ?>
            <tr>
                <td class="px-4 py-3 font-medium"><?= sanitize($asset['asset_code']) ?></td>
                <td class="px-4 py-3"><?= sanitize($asset['asset_name']) ?></td>
                <td class="px-4 py-3"><?= sanitize($asset['category_name']) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($asset['acquisition_cost']) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($bookValue) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($asset['disposal_proceeds']) ?></td>
                <td class="px-4 py-3 text-right font-bold
                    <?= $asset['gain_loss_amount'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                    <?= formatAmount($asset['gain_loss_amount']) ?>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-800">
                        <?= str_replace('_', ' ', ucfirst($asset['disposal_type'])) ?>
                    </span>
                </td>
                <td class="px-4 py-3"><?= date('M d, Y', strtotime($asset['disposal_date'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
