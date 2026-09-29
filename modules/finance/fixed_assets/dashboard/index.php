<?php
$pageTitle = 'Fixed Asset Dashboard';
$dashboardKey = 'finance_fixed_assets';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../helpers/pagination.php';
require_once '../../../../helpers/currency.php';
require_once '../depreciationservice.php';

if (!hasPermission('fixedasset.view')) { die('Access Denied'); }

$db = getDB();
$search = $_GET['search'] ?? '';
$perPage = 15;
$page = getPageNumber();

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (a.asset_code LIKE ? OR a.asset_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM fixed_asset_financials f $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("SELECT f.*, a.asset_code, a.asset_name, ac.category_name, d.method_name FROM fixed_asset_financials f JOIN assets a ON f.asset_id = a.id LEFT JOIN fixed_asset_categories fc ON f.asset_category_id = fc.id LEFT JOIN asset_categories ac ON fc.asset_category_id = ac.id LEFT JOIN depreciation_methods d ON f.depreciation_method_id = d.id $where ORDER BY f.id DESC LIMIT ? OFFSET ?");
$stmt->execute($queryParams);
$assets = $stmt->fetchAll();

$deprService = new DepreciationService();

$statusColors = ['active' => 'bg-green-100 text-green-800', 'disposed' => 'bg-red-100 text-red-800', 'impaired' => 'bg-yellow-100 text-yellow-800'];

$stats = [
    'totalCost' => $db->query("SELECT COALESCE(SUM(capitalized_cost), 0) as val FROM fixed_asset_financials WHERE status = 'active'")->fetch()['val'] ?? 0,
    'totalAccum' => $db->query("SELECT COALESCE(SUM(accumulated_depreciation), 0) as val FROM fixed_asset_financials WHERE status = 'active'")->fetch()['val'] ?? 0,
    'totalNBV' => $db->query("SELECT COALESCE(SUM(net_book_value), 0) as val FROM fixed_asset_financials WHERE status = 'active'")->fetch()['val'] ?? 0,
    'active' => $db->query("SELECT COUNT(*) as c FROM fixed_asset_financials WHERE status = 'active'")->fetch()['c'] ?? 0,
    'pendingDep' => $db->query("SELECT COUNT(*) as c FROM fixed_asset_financials WHERE status = 'active'")->fetch()['c'] ?? 0,
    'impairedTotal' => $db->query("SELECT COALESCE(SUM(impairment_loss), 0) as val FROM asset_impairment WHERE approval_status = 'approved'")->fetch()['val'] ?? 0,
];

// Generate mini chart data for JavaScript
$categoryData = $db->query("SELECT c.category_name, COALESCE(SUM(f.capitalized_cost), 0) as val FROM fixed_asset_categories fc JOIN asset_categories c ON fc.asset_category_id = c.id JOIN fixed_asset_financials f ON fc.id = f.asset_category_id GROUP BY c.category_name")->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Fixed Asset Dashboard</h1>
        <p class="text-gray-600">Overview of fixed asset financial status and performance</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Total Asset Cost</div>
        <div class="text-2xl font-bold text-gray-800"><?= formatMoney($stats['totalCost']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Accumulated Depreciation</div>
        <div class="text-2xl font-bold text-orange-600"><?= formatMoney($stats['totalAccum']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Net Book Value</div>
        <div class="text-2xl font-bold text-green-600"><?= formatMoney($stats['totalNBV']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Active Assets</div>
        <div class="text-2xl font-bold text-blue-600"><?= $stats['active'] ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Impairment</div>
        <div class="text-2xl font-bold text-red-600"><?= formatMoney($stats['impairedTotal']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Pending Depreciation</div>
        <div class="text-2xl font-bold text-yellow-600"><?= $stats['pendingDep'] ?></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <h2 class="text-lg font-semibold mb-4">Asset Cost by Category</h2>
        <div style="height: 300px;"><canvas id="assetChart"></canvas></div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-4">
        <h2 class="text-lg font-semibold mb-4">Monthly Depreciation Trend</h2>
        <div style="height: 300px;"><canvas id="depreciationChart"></canvas></div>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <div class="p-4">
        <h2 class="text-lg font-semibold mb-4">Recent Assets</h2>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cost</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Depreciation Method</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($assets as $asset): 
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
                        <td class="px-6 py-4 text-gray-600"><?= sanitize($asset['category_name'] ?? '-') ?></td>
                        <td class="px-6 py-4 text-right text-gray-600"><?= formatMoney($asset['capitalized_cost']) ?></td>
                        <td class="px-6 py-4 text-right text-gray-600"><?= sanitize($asset['method_name']) ?></td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-2 py-1 text-xs rounded <?= $statusColors[$asset['status']] ?? 'bg-gray-100 text-gray-800' ?>"><?= ucfirst($asset['status']) ?></span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="<?= url('modules/finance/fixed_assets/financial_records/view.php?id=' . $asset['id'] . '') ?>" class="text-blue-600 hover:text-blue-900 mr-2"><i class="fas fa-eye"></i></a>
                            <a href="<?= url('modules/finance/fixed_assets/acquisitions/view.php?id=' . $asset['id'] . '') ?>" class="text-yellow-600 hover:text-yellow-900 mr-2"><i class="fas fa-edit"></i></a>
                            <a href="<?= url('modules/finance/fixed_assets/disposal/index.php?asset_id=' . $asset['asset_id'] . '') ?>" class="text-red-600 hover:text-red-900" title="Dispose"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const assetChart = new Chart(document.getElementById('assetChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: ['<?= str_replace("'", "\\'", implode("', '", array_column($categoryData, 'category_name'))) ?>'],
        datasets: [{
            data: [<?= implode(',', array_column($categoryData, 'val')) ?>],
            backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#6366F1', '#14B8A6', '#F97316'],
            borderWidth: 1
        }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});

// Initialize depreciation chart (placeholder for demo)
const depreciationChart = new Chart(document.getElementById('depreciationChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
            label: 'Depreciation Expense',
            data: [1667, 1667, 1667, 1667, 1667, 1667, 1667, 1667, 1667, 1667, 1667, 1667],
            borderColor: '#F59E0B',
            backgroundColor: 'rgba(245, 158, 11, 0.1)',
            tension: 0.1
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
});
</script>

<?php require_once '../../../../includes/footer.php'; ?>