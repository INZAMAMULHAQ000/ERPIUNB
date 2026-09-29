<?php
/**
 * F11 — Fixed Asset Reports
 * Asset Register, Depreciation, Additions, Disposals
 * Integrates with F9 - Fixed Assets
 */

$pageTitle = 'Fixed Asset Reports';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once '../FinancialReportService.php';

if (!hasPermission('report.assets.view')) { die('Access Denied'); }

$db = getDB();

// Get parameters
$reportType = $_GET['report_type'] ?? 'register';
$categoryId = (int)($_GET['category_id'] ?? 0);
$asOfDate = $_GET['as_of_date'] ?? date('Y-m-d');
$dateFrom = $_GET['date_from'] ?? date('Y-01-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Get asset categories from the base asset module. F9 financial settings are
// linked through fixed_asset_financials; there is no fixed_assets table here.
$categories = $db->query("SELECT id, category_name FROM asset_categories WHERE status = 'active' ORDER BY category_name")->fetchAll();

// Generate report based on type
$report = null;

if ($reportType === 'register') {
    $report = FinancialReportService::generateAssetRegister([
        'category_id' => $categoryId,
        'as_of_date' => $asOfDate,
    ]);
} elseif ($reportType === 'depreciation') {
    // Depreciation schedule
    $stmt = $db->prepare("
        SELECT a.asset_code, a.asset_name, ac.category_name,
               faf.capitalized_cost AS acquisition_cost,
               'Configured' AS depreciation_method,
               COALESCE((SELECT SUM(depreciation_amount) FROM asset_depreciation_schedules
                         WHERE financial_asset_id = faf.id AND period_date <= ?), faf.accumulated_depreciation, 0) AS accumulated,
               COALESCE((SELECT SUM(depreciation_amount) FROM asset_depreciation_schedules
                         WHERE financial_asset_id = faf.id AND period_date BETWEEN ? AND ?), 0) AS current_period
        FROM fixed_asset_financials faf
        JOIN assets a ON a.id = faf.asset_id
        LEFT JOIN asset_categories ac ON ac.id = a.category_id
        WHERE faf.status IN ('active','impaired')
        AND faf.capitalization_date <= ?
        ORDER BY a.asset_code
    ");
    $stmt->execute([$asOfDate, $dateFrom, $dateTo, $asOfDate]);
    $report = ['success' => true, 'assets' => $stmt->fetchAll(), 'as_of_date' => $asOfDate, 'period' => "$dateFrom to $dateTo"];
} elseif ($reportType === 'additions') {
    // Asset additions in period
    $stmt = $db->prepare("
        SELECT a.asset_code, a.asset_name, ac.category_name,
               faf.capitalization_date AS acquisition_date,
               faf.capitalized_cost AS acquisition_cost
        FROM fixed_asset_financials faf
        JOIN assets a ON a.id = faf.asset_id
        LEFT JOIN asset_categories ac ON ac.id = a.category_id
        WHERE faf.capitalization_date BETWEEN ? AND ?
        AND faf.status IN ('active','impaired')
        ORDER BY faf.capitalization_date
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $report = ['success' => true, 'assets' => $stmt->fetchAll(), 'period' => "$dateFrom to $dateTo"];
} elseif ($reportType === 'disposals') {
    // Asset disposals
    $stmt = $db->prepare("
        SELECT a.asset_code, a.asset_name, ac.category_name,
               ad.disposal_date, ad.disposal_proceeds, ad.gain_loss,
               ad.original_cost AS acquisition_cost
        FROM asset_disposal ad
        JOIN assets a ON a.id = ad.asset_id
        LEFT JOIN fixed_asset_financials faf ON faf.asset_id = a.id
        LEFT JOIN asset_categories ac ON ac.id = a.category_id
        WHERE ad.disposal_date BETWEEN ? AND ?
        ORDER BY ad.disposal_date
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $report = ['success' => true, 'disposals' => $stmt->fetchAll(), 'period' => "$dateFrom to $dateTo"];
} elseif ($reportType === 'summary') {
    // Asset summary by category
    $stmt = $db->prepare("
        SELECT ac.id, ac.category_name,
               COUNT(faf.id) as asset_count,
               COALESCE(SUM(faf.capitalized_cost), 0) as total_cost,
               COALESCE(SUM(faf.accumulated_depreciation), 0) as total_acc_dep,
               COALESCE(SUM(COALESCE(faf.net_book_value, faf.capitalized_cost - faf.accumulated_depreciation - faf.impairment_amount)), 0) as total_nbv
        FROM asset_categories ac
        LEFT JOIN assets a ON a.category_id = ac.id
        LEFT JOIN fixed_asset_financials faf ON faf.asset_id = a.id AND faf.status IN ('active','impaired')
        WHERE ac.status = 'active'
        GROUP BY ac.id, ac.category_name
        ORDER BY ac.category_name
    ");
    $stmt->execute();
    $report = ['success' => true, 'categories' => $stmt->fetchAll()];
}
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Fixed Asset Reports</h1>
        <p class="text-gray-600">As of <?= date('M d, Y', strtotime($asOfDate)) ?></p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"><i class="fas fa-print mr-2"></i>Print</button>
        <a href="../dashboard/index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-arrow-left mr-2"></i>Dashboard</a>
    </div>
</div>

<!-- Report Type Tabs -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="flex border-b overflow-x-auto">
        <a href="?report_type=register&as_of_date=<?= $asOfDate ?>" class="px-6 py-3 font-medium <?= $reportType === 'register' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Asset Register
        </a>
        <a href="?report_type=summary&as_of_date=<?= $asOfDate ?>" class="px-6 py-3 font-medium <?= $reportType === 'summary' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Category Summary
        </a>
        <a href="?report_type=depreciation&as_of_date=<?= $asOfDate ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="px-6 py-3 font-medium <?= $reportType === 'depreciation' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Depreciation
        </a>
        <a href="?report_type=additions&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="px-6 py-3 font-medium <?= $reportType === 'additions' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Additions
        </a>
        <a href="?report_type=disposals&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="px-6 py-3 font-medium <?= $reportType === 'disposals' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Disposals
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow mb-6 p-4">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <input type="hidden" name="report_type" value="<?= $reportType ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">As of Date</label>
            <input type="date" name="as_of_date" value="<?= sanitize($asOfDate) ?>" class="px-3 py-2 border rounded">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input type="date" name="date_from" value="<?= sanitize($dateFrom) ?>" class="px-3 py-2 border rounded">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input type="date" name="date_to" value="<?= sanitize($dateTo) ?>" class="px-3 py-2 border rounded">
        </div>
        <?php if ($reportType === 'register'): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
            <select name="category_id" class="px-3 py-2 border rounded">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-search mr-2"></i>Generate</button>
    </form>
</div>

<?php if ($reportType === 'register' && $report && $report['success']): ?>

<!-- Asset Register Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-blue-50 rounded-lg shadow p-4 border-l-4 border-blue-500">
        <p class="text-xs text-gray-500 uppercase">Total Assets</p>
        <p class="text-xl font-bold text-blue-700"><?= count($report['assets']) ?></p>
    </div>
    <div class="bg-green-50 rounded-lg shadow p-4 border-l-4 border-green-500">
        <p class="text-xs text-gray-500 uppercase">Total Cost</p>
        <p class="text-xl font-bold text-gray-800"><?= formatMoney($report['totals']['cost']) ?></p>
    </div>
    <div class="bg-yellow-50 rounded-lg shadow p-4 border-l-4 border-yellow-500">
        <p class="text-xs text-gray-500 uppercase">Accumulated Depreciation</p>
        <p class="text-xl font-bold text-yellow-700"><?= formatMoney($report['totals']['accumulated_depreciation']) ?></p>
    </div>
    <div class="bg-purple-50 rounded-lg shadow p-4 border-l-4 border-purple-500">
        <p class="text-xs text-gray-500 uppercase">Net Book Value</p>
        <p class="text-xl font-bold text-purple-700"><?= formatMoney($report['totals']['nbv']) ?></p>
    </div>
</div>

<!-- Asset Register Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-800">Fixed Asset Register</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset Code</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acquisition Date</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cost</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Accum Depn</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">NBV</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['assets'])): ?>
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">No fixed assets found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['assets'] as $asset): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-blue-600"><?= sanitize($asset['asset_code']) ?></td>
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($asset['asset_name']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= sanitize($asset['category_name']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= date('M d, Y', strtotime($asset['acquisition_date'])) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($asset['acquisition_cost']) ?></td>
                    <td class="px-4 py-3 text-right font-mono text-yellow-600"><?= formatMoney($asset['accumulated_depreciation']) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold text-purple-700"><?= formatMoney($asset['nbv']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="bg-gray-100 font-bold">
                <tr>
                    <td colspan="4" class="px-4 py-3">TOTALS</td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($report['totals']['cost']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($report['totals']['accumulated_depreciation']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($report['totals']['nbv']) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'summary' && $report && $report['success']): ?>

<!-- Category Summary -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-800">Asset Summary by Category</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-right">Asset Count</th>
                    <th class="px-4 py-3 text-right">Total Cost</th>
                    <th class="px-4 py-3 text-right">Accumulated Depn</th>
                    <th class="px-4 py-3 text-right">Net Book Value</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($report['categories'] as $cat): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($cat['category_name']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= $cat['asset_count'] ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($cat['total_cost'] ?? 0) ?></td>
                    <td class="px-4 py-3 text-right font-mono text-yellow-600"><?= formatMoney($cat['total_acc_dep'] ?? 0) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold text-purple-700"><?= formatMoney($cat['total_nbv'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'depreciation' && $report && $report['success']): ?>

<!-- Depreciation Schedule -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b bg-yellow-50">
        <h3 class="font-semibold text-yellow-800">Depreciation Schedule</h3>
        <p class="text-sm text-yellow-600">Period: <?= $report['period'] ?></p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Asset Code</th>
                    <th class="px-4 py-3 text-left">Asset Name</th>
                    <th class="px-4 py-3 text-left">Method</th>
                    <th class="px-4 py-3 text-right">Cost</th>
                    <th class="px-4 py-3 text-right">Period Depn</th>
                    <th class="px-4 py-3 text-right">Accum Depn</th>
                    <th class="px-4 py-3 text-right">NBV</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['assets'])): ?>
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">No assets with depreciation</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['assets'] as $asset): 
                    $nbv = (float)$asset['acquisition_cost'] - (float)$asset['accumulated'];
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-blue-600"><?= sanitize($asset['asset_code']) ?></td>
                    <td class="px-4 py-3"><?= sanitize($asset['asset_name']) ?></td>
                    <td class="px-4 py-3"><?= sanitize(ucfirst($asset['depreciation_method'])) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($asset['acquisition_cost']) ?></td>
                    <td class="px-4 py-3 text-right font-mono text-green-600"><?= formatMoney($asset['current_period'] ?? 0) ?></td>
                    <td class="px-4 py-3 text-right font-mono text-yellow-600"><?= formatMoney($asset['accumulated'] ?? 0) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold"><?= formatMoney($nbv) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'additions' && $report && $report['success']): ?>

<!-- Asset Additions -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b bg-green-50">
        <h3 class="font-semibold text-green-800">Asset Additions</h3>
        <p class="text-sm text-green-600">Period: <?= $report['period'] ?></p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Asset Code</th>
                    <th class="px-4 py-3 text-left">Asset Name</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-left">Acquisition Date</th>
                    <th class="px-4 py-3 text-right">Cost</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['assets'])): ?>
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">No asset additions in this period</td>
                </tr>
                <?php else: 
                    $totalCost = 0;
                    foreach ($report['assets'] as $a) $totalCost += (float)$a['acquisition_cost'];
                ?>
                <?php foreach ($report['assets'] as $asset): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-blue-600"><?= sanitize($asset['asset_code']) ?></td>
                    <td class="px-4 py-3"><?= sanitize($asset['asset_name']) ?></td>
                    <td class="px-4 py-3"><?= sanitize($asset['category_name']) ?></td>
                    <td class="px-4 py-3"><?= date('M d, Y', strtotime($asset['acquisition_date'])) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold"><?= formatMoney($asset['acquisition_cost']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tfoot class="bg-gray-100 font-bold">
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-right">TOTAL ADDITIONS</td>
                        <td class="px-4 py-3 text-right font-mono"><?= formatMoney($totalCost) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'disposals' && $report && $report['success']): ?>

<!-- Asset Disposals -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b bg-red-50">
        <h3 class="font-semibold text-red-800">Asset Disposals</h3>
        <p class="text-sm text-red-600">Period: <?= $report['period'] ?></p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Asset Code</th>
                    <th class="px-4 py-3 text-left">Asset Name</th>
                    <th class="px-4 py-3 text-left">Disposal Date</th>
                    <th class="px-4 py-3 text-right">Cost</th>
                    <th class="px-4 py-3 text-right">Proceeds</th>
                    <th class="px-4 py-3 text-right">Gain/(Loss)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['disposals'])): ?>
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No asset disposals in this period</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['disposals'] as $disposal): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-blue-600"><?= sanitize($disposal['asset_code']) ?></td>
                    <td class="px-4 py-3"><?= sanitize($disposal['asset_name']) ?></td>
                    <td class="px-4 py-3"><?= date('M d, Y', strtotime($disposal['disposal_date'])) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($disposal['acquisition_cost']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($disposal['disposal_proceeds'] ?? 0) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold <?= $disposal['gain_loss'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= formatMoney($disposal['gain_loss'] ?? 0) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<style media="print">
    .erp-layout, nav, .sidebar, .navbar, form, button { display: none !important; }
    body { background: white; }
    @page { margin: 10mm; }
</style>

<?php require_once '../../../../includes/footer.php'; ?>
