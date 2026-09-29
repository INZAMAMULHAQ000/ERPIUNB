<?php
$pageTitle = 'Fixed Assets';
require_once '../../../includes/header.php';

if (!hasPermission('fixedasset.view')) { die('Access Denied'); }

$db = getDB();

// Quick stats
$totalCost = $db->query("SELECT COALESCE(SUM(acquisition_cost), 0) FROM fixed_asset_financials WHERE status != 'disposed'")->fetchColumn();
$totalDepreciation = $db->query("SELECT COALESCE(SUM(accumulated_depreciation), 0) FROM fixed_asset_financials WHERE status != 'disposed'")->fetchColumn();
$totalNBV = $totalCost - $totalDepreciation;
$activeAssets = $db->query("SELECT COUNT(*) FROM fixed_asset_financials WHERE status = 'active'")->fetchColumn();
$disposedAssets = $db->query("SELECT COUNT(*) FROM fixed_asset_financials WHERE status = 'disposed'")->fetchColumn();
$pendingDepreciation = $db->query("SELECT COUNT(*) FROM asset_depreciation_schedules WHERE status = 'calculated'")->fetchColumn();
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Fixed Assets & Depreciation</h1>
    <p class="text-gray-600">Manage fixed assets, depreciation, impairments, and disposals</p>
</div>

<!-- Main Navigation -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="border-b border-gray-200">
        <nav class="flex flex-wrap" aria-label="Tabs">
            <a href="<?= url('modules/finance/fixed_assets/dashboard/index.php') ?>" class="px-6 py-4 text-sm font-medium text-center border-b-2 <?= (basename($_SERVER['PHP_SELF']) == 'index.php' || basename($_SERVER['PHP_SELF']) == 'dashboard' || !isset($_GET['page'])) ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
            </a>
            <a href="<?= url('modules/finance/fixed_assets/financial_records/index.php') ?>" class="px-6 py-4 text-sm font-medium text-center border-b-2 <?= basename($_SERVER['PHP_SELF']) == 'index.php' && isset($_GET['page']) && $_GET['page'] == 'financial_records' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                <i class="fas fa-file-invoice mr-2"></i>Financial Records
                <span class="ml-2 bg-blue-100 text-blue-800 text-xs px-2 py-0.5 rounded-full"><?= $activeAssets ?></span>
            </a>
            <a href="<?= url('modules/finance/fixed_assets/acquisitions/index.php') ?>" class="px-6 py-4 text-sm font-medium text-center border-b-2 <?= basename($_SERVER['PHP_SELF']) == 'acquisitions' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                <i class="fas fa-money-bill-wave mr-2"></i>Capitalizations
            </a>
            <a href="<?= url('modules/finance/fixed_assets/depreciation/process.php') ?>" class="px-6 py-4 text-sm font-medium text-center border-b-2 <?= basename($_SERVER['PHP_SELF']) == 'depreciation' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                <i class="fas fa-calculator mr-2"></i>Depreciation
                <span class="ml-2 bg-yellow-100 text-yellow-800 text-xs px-2 py-0.5 rounded-full"><?= $pendingDepreciation ?></span>
            </a>
            <a href="<?= url('modules/finance/fixed_assets/disposal/create.php') ?>" class="px-6 py-4 text-sm font-medium text-center border-b-2 <?= basename($_SERVER['PHP_SELF']) == 'disposal' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                <i class="fas fa-trash-alt mr-2"></i>Disposal
                <span class="ml-2 bg-red-100 text-red-800 text-xs px-2 py-0.5 rounded-full"><?= $disposedAssets ?></span>
            </a>
            <a href="<?= url('modules/finance/fixed_assets/reports/index.php') ?>" class="px-6 py-4 text-sm font-medium text-center border-b-2 <?= basename($_SERVER['PHP_SELF']) == 'reports' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                <i class="fas fa-file-contract mr-2"></i>Reports
            </a>
            <a href="<?= url('modules/finance/fixed_assets/categories/index.php') ?>" class="px-6 py-4 text-sm font-medium text-center border-b-2 <?= basename($_SERVER['PHP_SELF']) == 'categories' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                <i class="fas fa-tags mr-2"></i>Categories
            </a>
        </nav>
    </div>

    <div class="p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-6">Overview</h2>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-blue-50 rounded-lg p-4 border-l-4 border-blue-500">
                <div class="text-sm text-blue-600 uppercase font-medium">Total Asset Cost</div>
                <div class="text-2xl font-bold text-gray-800 mt-1"><?= formatAmount($totalCost) ?></div>
            </div>
            <div class="bg-indigo-50 rounded-lg p-4 border-l-4 border-indigo-500">
                <div class="text-sm text-indigo-600 uppercase font-medium">Accumulated Depreciation</div>
                <div class="text-2xl font-bold text-gray-800 mt-1"><?= formatAmount($totalDepreciation) ?></div>
            </div>
            <div class="bg-purple-50 rounded-lg p-4 border-l-4 border-purple-500">
                <div class="text-sm text-purple-600 uppercase font-medium">Net Book Value</div>
                <div class="text-2xl font-bold text-gray-800 mt-1"><?= formatAmount($totalNBV) ?></div>
            </div>
            <div class="bg-green-50 rounded-lg p-4 border-l-4 border-green-500">
                <div class="text-sm text-green-600 uppercase font-medium">Active Assets</div>
                <div class="text-2xl font-bold text-gray-800 mt-1"><?= $activeAssets ?></div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Recent Asset Activity</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left">Date</th>
                            <th class="px-6 py-3 text-left">Asset</th>
                            <th class="px-6 py-3 text-left">Event</th>
                            <th class="px-6 py-3 text-right">Amount</th>
                            <th class="px-6 py-3 text-left">User</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php
                        $recentActivity = $db->query("
                            SELECT h.*, a.asset_code, u.name as user_name
                            FROM fixed_asset_history h
                            LEFT JOIN fixed_asset_financials f ON h.financial_asset_id = f.id
                            LEFT JOIN assets a ON f.asset_id = a.id
                            LEFT JOIN users u ON h.created_by = u.id
                            ORDER BY h.created_at DESC
                            LIMIT 10
                        ")->fetchAll();
                        ?>
                        <?php foreach ($recentActivity as $activity): ?>
                        <tr>
                            <td class="px-6 py-4 text-gray-600"><?= date('M d, Y H:i', strtotime($activity['created_at'])) ?></td>
                            <td class="px-6 py-4 font-medium text-gray-900"><?= sanitize($activity['asset_code'] ?? '-') ?></td>
                            <td class="px-6 py-4 text-gray-600">
                                <?= str_replace('_', ' ', ucfirst($activity['event_type'])) ?>
                            </td>
                            <td class="px-6 py-4 text-right text-gray-900">
                                <?= $activity['amount'] > 0 ? '+' . formatAmount($activity['amount']) : formatAmount($activity['amount']) ?>
                            </td>
                            <td class="px-6 py-4 text-gray-600"><?= sanitize($activity['user_name'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentActivity)): ?>
                        <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No recent activity</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php if (hasPermission('fixedasset.create')): ?>
                <a href="<?= url('modules/finance/fixed_assets/acquisitions/create.php') ?>" class="flex flex-col items-center p-4 border-2 border-blue-500 rounded-lg hover:bg-blue-50 transition">
                    <i class="fas fa-plus-circle text-2xl text-blue-600 mb-2"></i>
                    <span class="text-sm font-medium text-gray-700">Capitalize Asset</span>
                </a>
                <?php endif; ?>
                
                <?php if (hasPermission('fixedasset.depreciate')): ?>
                <a href="<?= url('modules/finance/fixed_assets/depreciation/process.php') ?>" class="flex flex-col items-center p-4 border-2 border-green-500 rounded-lg hover:bg-green-50 transition">
                    <i class="fas fa-calculator text-2xl text-green-600 mb-2"></i>
                    <span class="text-sm font-medium text-gray-700">Process Depreciation</span>
                </a>
                <?php endif; ?>
                
                <?php if (hasPermission('fixedasset.report')): ?>
                <a href="<?= url('modules/finance/fixed_assets/reports/register.php') ?>" class="flex flex-col items-center p-4 border-2 border-purple-500 rounded-lg hover:bg-purple-50 transition">
                    <i class="fas fa-file-contract text-2xl text-purple-600 mb-2"></i>
                    <span class="text-sm font-medium text-gray-700">View Register</span>
                </a>
                <?php endif; ?>
                
                <?php if (hasPermission('fixedasset.dashboard')): ?>
                <a href="<?= url('modules/finance/fixed_assets/dashboard/index.php') ?>" class="flex flex-col items-center p-4 border-2 border-orange-500 rounded-lg hover:bg-orange-50 transition">
                    <i class="fas fa-chart-bar text-2xl text-orange-600 mb-2"></i>
                    <span class="text-sm font-medium text-gray-700">View Dashboard</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>
