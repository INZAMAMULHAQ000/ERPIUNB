<?php
$pageTitle = 'Fixed Asset Reports';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/currency.php';

if (!hasPermission('fixedasset.report')) { die('Access Denied'); }
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Fixed Asset Reports</h1>
    <p class="text-gray-600">Generate and view fixed asset reports</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <!-- Asset Register -->
    <a href="<?= url('modules/finance/fixed_assets/reports/register.php') ?>" class="block bg-white rounded-lg shadow p-6 hover:shadow-lg transition group">
        <div class="w-12 h-12 rounded-lg bg-blue-50 flex items-center justify-center mb-4 group-hover:bg-blue-600 transition">
            <i class="fas fa-book text-blue-600 text-xl group-hover:text-white"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Fixed Asset Register</h3>
        <p class="text-sm text-gray-600 mb-3">Complete list of all fixed assets with financial details, categories, and depreciation methods</p>
        <span class="text-blue-600 text-sm font-medium flex items-center group-hover:translate-x-1 transition">
            View Report <i class="fas fa-arrow-right ml-2"></i>
        </span>
    </a>

    <!-- Depreciation Schedule -->
    <a href="<?= url('modules/finance/fixed_assets/reports/depreciation.php') ?>" class="block bg-white rounded-lg shadow p-6 hover:shadow-lg transition group">
        <div class="w-12 h-12 rounded-lg bg-green-50 flex items-center justify-center mb-4 group-hover:bg-green-600 transition">
            <i class="fas fa-calculator text-green-600 text-xl group-hover:text-white"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Depreciation Schedule</h3>
        <p class="text-sm text-gray-600 mb-3">Monthly depreciation breakdown by asset with details of accumulated depreciation and current month charges</p>
        <span class="text-green-600 text-sm font-medium flex items-center group-hover:translate-x-1 transition">
            View Report <i class="fas fa-arrow-right ml-2"></i>
        </span>
    </a>

    <!-- Net Book Value -->
    <a href="<?= url('modules/finance/fixed_assets/reports/nbv.php') ?>" class="block bg-white rounded-lg shadow p-6 hover:shadow-lg transition group">
        <div class="w-12 h-12 rounded-lg bg-purple-50 flex items-center justify-center mb-4 group-hover:bg-purple-600 transition">
            <i class="fas fa-chart-pie text-purple-600 text-xl group-hover:text-white"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Net Book Value Report</h3>
        <p class="text-sm text-gray-600 mb-3">Asset value breakdown by category with percentage distribution and category totals</p>
        <span class="text-purple-600 text-sm font-medium flex items-center group-hover:translate-x-1 transition">
            View Report <i class="fas fa-arrow-right ml-2"></i>
        </span>
    </a>

    <!-- Asset Disposal -->
    <a href="<?= url('modules/finance/fixed_assets/reports/disposal.php') ?>" class="block bg-white rounded-lg shadow p-6 hover:shadow-lg transition group">
        <div class="w-12 h-12 rounded-lg bg-red-50 flex items-center justify-center mb-4 group-hover:bg-red-600 transition">
            <i class="fas fa-trash-alt text-red-600 text-xl group-hover:text-white"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Asset Disposal Report</h3>
        <p class="text-sm text-gray-600 mb-3">Record of all asset disposals with gain/loss calculations and disposal types</p>
        <span class="text-red-600 text-sm font-medium flex items-center group-hover:translate-x-1 transition">
            View Report <i class="fas fa-arrow-right ml-2"></i>
        </span>
    </a>

    <!-- Gain/Loss -->
    <a href="<?= url('modules/finance/fixed_assets/reports/gain_loss.php') ?>" class="block bg-white rounded-lg shadow p-6 hover:shadow-lg transition group">
        <div class="w-12 h-12 rounded-lg bg-amber-50 flex items-center justify-center mb-4 group-hover:bg-amber-600 transition">
            <i class="fas fa-exchange-alt text-amber-600 text-xl group-hover:text-white"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Gain/Loss Report</h3>
        <p class="text-sm text-gray-600 mb-3">Detailed gain/loss on asset disposals with breakdown by type and period</p>
        <span class="text-amber-600 text-sm font-medium flex items-center group-hover:translate-x-1 transition">
            View Report <i class="fas fa-arrow-right ml-2"></i>
        </span>
    </a>

    <!-- Category Report -->
    <a href="<?= url('modules/finance/fixed_assets/reports/category.php') ?>" class="block bg-white rounded-lg shadow p-6 hover:shadow-lg transition group">
        <div class="w-12 h-12 rounded-lg bg-indigo-50 flex items-center justify-center mb-4 group-hover:bg-indigo-600 transition">
            <i class="fas fa-folder text-indigo-600 text-xl group-hover:text-white"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Category Report</h3>
        <p class="text-sm text-gray-600 mb-3">Detailed breakdown of assets by category with cost, depreciation, and NBV</p>
        <span class="text-indigo-600 text-sm font-medium flex items-center group-hover:translate-x-1 transition">
            View Report <i class="fas fa-arrow-right ml-2"></i>
        </span>
    </a>

    <!-- Movement Report -->
    <a href="<?= url('modules/finance/fixed_assets/reports/movement.php') ?>" class="block bg-white rounded-lg shadow p-6 hover:shadow-lg transition group">
        <div class="w-12 h-12 rounded-lg bg-teal-50 flex items-center justify-center mb-4 group-hover:bg-teal-600 transition">
            <i class="fas fa-random text-teal-600 text-xl group-hover:text-white"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Asset Movement Report</h3>
        <p class="text-sm text-gray-600 mb-3">Transfers and movements of assets between departments, projects, and locations</p>
        <span class="text-teal-600 text-sm font-medium flex items-center group-hover:translate-x-1 transition">
            View Report <i class="fas fa-arrow-right ml-2"></i>
        </span>
    </a>

    <!-- Impairment Report -->
    <a href="<?= url('modules/finance/fixed_assets/reports/impairment.php') ?>" class="block bg-white rounded-lg shadow p-6 hover:shadow-lg transition group">
        <div class="w-12 h-12 rounded-lg bg-pink-50 flex items-center justify-center mb-4 group-hover:bg-pink-600 transition">
            <i class="fas fa-exclamation-triangle text-pink-600 text-xl group-hover:text-white"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Impairment Report</h3>
        <p class="text-sm text-gray-600 mb-3">Record of all asset impairments with calculations and approval status</p>
        <span class="text-pink-600 text-sm font-medium flex items-center group-hover:translate-x-1 transition">
            View Report <i class="fas fa-arrow-right ml-2"></i>
        </span>
    </a>

    <!-- Monthly Depreciation -->
    <a href="<?= url('modules/finance/fixed_assets/reports/monthly.php') ?>" class="block bg-white rounded-lg shadow p-6 hover:shadow-lg transition group">
        <div class="w-12 h-12 rounded-lg bg-cyan-50 flex items-center justify-center mb-4 group-hover:bg-cyan-600 transition">
            <i class="fas fa-calendar-alt text-cyan-600 text-xl group-hover:text-white"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Monthly Depreciation Summary</h3>
        <p class="text-sm text-gray-600 mb-3">Monthly summary of depreciation expenses with totals and averages</p>
        <span class="text-cyan-600 text-sm font-medium flex items-center group-hover:translate-x-1 transition">
            View Report <i class="fas fa-arrow-right ml-2"></i>
        </span>
    </a>
</div>

<!-- Quick Stats -->
<div class="bg-white rounded-lg shadow p-6 mt-8">
    <h2 class="text-xl font-semibold text-gray-800 mb-4">Quick Stats</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <?php
        $db = getDB();
        $totalAssets = $db->query("SELECT COUNT(*) FROM fixed_asset_financials WHERE status != 'disposed'")->fetchColumn();
        $totalCost = $db->query("SELECT COALESCE(SUM(acquisition_cost), 0) FROM fixed_asset_financials WHERE status != 'disposed'")->fetchColumn();
        $totalDepreciation = $db->query("SELECT COALESCE(SUM(accumulated_depreciation), 0) FROM fixed_asset_financials WHERE status != 'disposed'")->fetchColumn();
        $totalNBV = $totalCost - $totalDepreciation;
        ?>
        <div class="text-center p-4 bg-blue-50 rounded-lg">
            <div class="text-sm text-blue-600">Total Assets</div>
            <div class="text-2xl font-bold text-blue-800"><?= $totalAssets ?></div>
        </div>
        <div class="text-center p-4 bg-green-50 rounded-lg">
            <div class="text-sm text-green-600">Total Cost</div>
            <div class="text-2xl font-bold text-green-800"><?= formatMoney($totalCost) ?></div>
        </div>
        <div class="text-center p-4 bg-indigo-50 rounded-lg">
            <div class="text-sm text-indigo-600">Accumulated Depreciation</div>
            <div class="text-2xl font-bold text-indigo-800"><?= formatMoney($totalDepreciation) ?></div>
        </div>
        <div class="text-center p-4 bg-purple-50 rounded-lg">
            <div class="text-sm text-purple-600">Net Book Value</div>
            <div class="text-2xl font-bold text-purple-800"><?= formatMoney($totalNBV) ?></div>
        </div>
    </div>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
