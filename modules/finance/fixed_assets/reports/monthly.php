<?php
$pageTitle = 'Monthly Depreciation Summary';
require_once '../../../../includes/header.php';

if (!hasPermission('fixedasset.report')) { die('Access Denied'); }

$db = getDB();

$year = $_GET['year'] ?? date('Y');

$monthlyData = $db->prepare("
    SELECT 
        MONTH(period_date) as month,
        MONTHNAME(period_date) as month_name,
        COUNT(DISTINCT financial_asset_id) as asset_count,
        SUM(depreciation_amount) as total_depreciation,
        AVG(depreciation_amount) as avg_depreciation,
        MAX(depreciation_amount) as max_depreciation
    FROM asset_depreciation_schedules
    WHERE YEAR(period_date) = ?
    GROUP BY MONTH(period_date), MONTHNAME(period_date)
    ORDER BY period_date
");
$monthlyData->execute([$year]);
$monthlyData = $monthlyData->fetchAll();

$totalDepreciation = array_sum(array_column($monthlyData, 'total_depreciation'));
$totalAssets = array_sum(array_column($monthlyData, 'asset_count'));
$avgMonthly = count($monthlyData) > 0 ? round($totalDepreciation / count($monthlyData)) : 0;
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Monthly Depreciation Summary</h1>
        <p class="text-gray-600">Depreciation expenses breakdown by month</p>
    </div>
    <div class="flex gap-2">
        <form method="GET" class="flex gap-2">
            <input type="hidden" name="report" value="monthly">
            <select name="year" onchange="this.form.submit()" class="px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <?php for ($y = date('Y') - 5; $y <= date('Y'); $y++): ?>
                <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                <i class="fas fa-search"></i>
            </button>
        </form>
        <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
            <i class="fas fa-print mr-2"></i>Print
        </button>
    </div>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
        <div class="text-sm text-gray-500">Total Monthly Depreciation</div>
        <div class="text-2xl font-bold text-gray-800"><?= formatAmount($totalDepreciation) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
        <div class="text-sm text-gray-500">Total Assets with Depreciation</div>
        <div class="text-2xl font-bold text-gray-800"><?= $totalAssets ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
        <div class="text-sm text-gray-500">Average Monthly Expense</div>
        <div class="text-2xl font-bold text-gray-800"><?= formatAmount($avgMonthly) ?></div>
    </div>
</div>

<!-- Monthly Breakdown -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left">Month</th>
                <th class="px-4 py-3 text-center">Assets</th>
                <th class="px-4 py-3 text-right">Total Depreciation</th>
                <th class="px-4 py-3 text-right">Average per Asset</th>
                <th class="px-4 py-3 text-right">Max Single Asset</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($monthlyData as $month): ?>
            <tr>
                <td class="px-4 py-3 font-medium text-blue-600"><?= $month['month_name'] ?></td>
                <td class="px-4 py-3 text-center"><?= $month['asset_count'] ?></td>
                <td class="px-4 py-3 text-right text-red-600">-<?= formatAmount($month['total_depreciation']) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($month['avg_depreciation']) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($month['max_depreciation']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-gray-100 font-bold">
            <tr>
                <td class="px-4 py-3">Total</td>
                <td class="px-4 py-3 text-center"><?= $totalAssets ?></td>
                <td class="px-4 py-3 text-right text-red-600">-<?= formatAmount($totalDepreciation) ?></td>
                <td class="px-4 py-3 text-right" colspan="2">Average: <?= formatAmount($avgMonthly) ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- Chart Data (for visualization) -->
<?php if (!empty($monthlyData)): ?>
<div class="mt-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Depreciation Trend (<?= $year ?>)</h2>
    <div class="bg-white rounded-lg shadow p-6">
        <div class="relative h-64">
            <canvas id="deprChart"></canvas>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($monthlyData)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('deprChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($monthlyData, 'month_name')) ?>,
                datasets: [{
                    label: 'Depreciation Amount',
                    data: <?= json_encode(array_column($monthlyData, 'total_depreciation')) ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: function(v) { return 'AED ' + v; } }
                    }
                }
            }
        });
    }
});
</script>
<?php endif; ?>

<?php require_once '../../../../includes/footer.php'; ?>
