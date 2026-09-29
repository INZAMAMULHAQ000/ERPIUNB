<?php
$pageTitle = 'Depreciation Processing';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/finance_helpers.php';

if (!hasPermission('fixedasset.depreciate')) { die('Access Denied'); }

require_once __DIR__ . '/../DepreciationService.php';

$db = getDB();

$asset_id = $_GET['asset_id'] ?? '';
$process_all = $_GET['process_all'] ?? 0;
$action = $_GET['action'] ?? '';

// Get selected asset or all active assets
if ($asset_id) {
    $assets = $db->prepare("
        SELECT f.*, a.asset_code, a.asset_name, ac.category_name, dm.method_name, dm.method_code,
               COALESCE(ai.impairment_loss, 0) as impairment_amount
        FROM fixed_asset_financials f
        LEFT JOIN assets a ON f.asset_id = a.id
        LEFT JOIN fixed_asset_categories fac ON f.asset_category_id = fac.id
        LEFT JOIN asset_categories ac ON fac.asset_category_id = ac.id
        LEFT JOIN depreciation_methods dm ON f.depreciation_method_id = dm.id
        LEFT JOIN asset_impairment ai ON f.asset_id = ai.asset_id AND ai.approval_status = 'approved'
        WHERE f.id = ? AND f.status = 'active'
    ")->execute([$asset_id])->fetchAll();
} else {
    $assets = $db->prepare("
        SELECT f.*, a.asset_code, a.asset_name, ac.category_name, dm.method_name, dm.method_code,
               COALESCE(ai.impairment_loss, 0) as impairment_amount
        FROM fixed_asset_financials f
        LEFT JOIN assets a ON f.asset_id = a.id
        LEFT JOIN fixed_asset_categories fac ON f.asset_category_id = fac.id
        LEFT JOIN asset_categories ac ON fac.asset_category_id = ac.id
        LEFT JOIN depreciation_methods dm ON f.depreciation_method_id = dm.id
        LEFT JOIN asset_impairment ai ON f.asset_id = ai.asset_id AND ai.approval_status = 'approved'
        WHERE f.status = 'active'
        ORDER BY a.asset_code
    ")->fetchAll();
}

if ($action === 'preview') {
    $periodDate = $_POST['period_date'] ?? date('Y-m-01');
    $previewResults = [];
    
    foreach ($assets as $asset) {
        $category = $db->prepare("SELECT * FROM fixed_asset_categories WHERE id = ?")->execute([$asset['asset_category_id']])->fetch();
        
        $openingNBV = $asset['acquisition_cost'] - $asset['accumulated_depreciation'] - $asset['impairment_amount'];
        
        // Calculate monthly depreciation
        $depreciableAmount = $asset['acquisition_cost'] - $asset['residual_value'];
        $monthlyDep = $depreciableAmount / $asset['useful_life_months'];
        
        $closingNBV = $openingNBV - $monthlyDep;
        
        $previewResults[] = [
            'financial_asset_id' => $asset['id'],
            'asset_code' => $asset['asset_code'],
            'asset_name' => $asset['asset_name'],
            'opening_nbv' => $openingNBV,
            'depreciation' => $monthlyDep,
            'closing_nbv' => $closingNBV,
            'accumulated_dep' => $asset['accumulated_depreciation'] + $monthlyDep
        ];
    }
}

if ($action === 'post') {
    $periodDate = $_POST['period_date'] ?? date('Y-m-01');
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    foreach ($assets as $asset) {
        $deprService = new DepreciationService();
        $result = $deprService->postDepreciation($asset['id'], $periodDate, $_SESSION['user_id']);
        
        if ($result['success']) {
            $successCount++;
        } else {
            $errorCount++;
            $errors[] = $asset['asset_code'] . ': ' . $result['error'];
        }
    }
    
    if ($successCount > 0) {
        flash('success', "Successfully posted depreciation for $successCount asset(s)");
    }
    if ($errorCount > 0) {
        flash('error', "Failed to post depreciation for $errorCount asset(s): " . implode(', ', $errors));
    }
    
    to('modules/finance/fixed_assets/depreciation/process.php' . ($asset_id ? '?asset_id=' . $asset_id : ''));
}

// Get period date
$periodDate = $_GET['period_date'] ?? date('Y-m-01');
?>

<div class="mb-6">
    <a href="<?= url('modules/finance/fixed_assets/financial_records/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Register</a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold text-gray-800 mb-6">
        <i class="fas fa-calculator mr-2 text-blue-500"></i>
        Depreciation Processing
    </h2>

    <!-- Filter -->
    <div class="flex gap-4 mb-6">
        <a href="<?= url('modules/finance/fixed_assets/depreciation/process.php') ?>" class="px-4 py-2 rounded <?= $asset_id ? '' : 'bg-blue-600 text-white' ?>">All Active Assets</a>
        <?php foreach ($assets as $asset): ?>
        <a href="<?= url('modules/finance/fixed_assets/depreciation/process.php?asset_id=' . $asset['id'] . '') ?>" class="px-4 py-2 rounded <?= $asset_id && $asset_id == $asset['id'] ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' ?>">
            <?= sanitize($asset['asset_code']) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Period Selection -->
    <div class="bg-gray-50 rounded-lg p-4 mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-2">Select Depreciation Period</label>
        <form method="GET" class="flex gap-4 items-center">
            <input type="date" name="period_date" value="<?= $periodDate ?>" class="px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            <button type="submit" name="action" value="preview" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                <i class="fas fa-eye mr-2"></i>Preview
            </button>
        </form>
    </div>

    <?php if ($action === 'preview' && isset($previewResults)): ?>
    <!-- Preview Results -->
    <div class="mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Preview Results for <?= date('F Y', strtotime($periodDate)) ?></h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left">Asset</th>
                        <th class="px-4 py-2 text-right">Opening NBV</th>
                        <th class="px-4 py-2 text-right">Monthly Depreciation</th>
                        <th class="px-4 py-2 text-right">Closing NBV</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($previewResults as $result): ?>
                    <tr>
                        <td class="px-4 py-2 font-medium text-gray-900">
                            <?= sanitize($result['asset_code']) ?> - <?= sanitize($result['asset_name']) ?>
                        </td>
                        <td class="px-4 py-2 text-right"><?= formatNumber($result['opening_nbv']) ?></td>
                        <td class="px-4 py-2 text-right text-red-600">-<?= formatNumber($result['depreciation']) ?></td>
                        <td class="px-4 py-2 text-right font-medium text-gray-800"><?= formatNumber($result['closing_nbv']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50 font-bold">
                    <tr>
                        <td class="px-4 py-2">Total</td>
                        <td class="px-4 py-2 text-right"><?= formatNumber(array_sum(array_column($previewResults, 'opening_nbv'))) ?></td>
                        <td class="px-4 py-2 text-right text-red-600">-<?= formatNumber(array_sum(array_column($previewResults, 'depreciation'))) ?></td>
                        <td class="px-4 py-2 text-right"><?= formatNumber(array_sum(array_column($previewResults, 'closing_nbv'))) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class="mt-6 flex gap-4">
            <form method="POST">
                <input type="hidden" name="period_date" value="<?= $periodDate ?>">
                <button type="submit" name="action" value="post" class="bg-green-600 text-white px-6 py-3 rounded hover:bg-green-700 text-lg">
                    <i class="fas fa-check-circle mr-2"></i>Post Depreciation for <?= count($previewResults) ?> Asset(s)
                </button>
            </form>
            <a href="<?= url('modules/finance/fixed_assets/depreciation/process.php?asset_id=' . ($asset_id ?? '') . '') ?>" class="px-6 py-3 rounded bg-gray-600 text-white hover:bg-gray-700 text-lg">
                Cancel
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Instructions -->
    <div class="bg-blue-50 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-blue-800 mb-4">How It Works</h3>
        <div class="space-y-2 text-sm text-blue-700">
            <p><i class="fas fa-check-circle mr-2"></i>Select a financial period for depreciation calculation</p>
            <p><i class="fas fa-check-circle mr-2"></i>Click <strong>Preview</strong> to see depreciation amounts</p>
            <p><i class="fas fa-check-circle mr-2"></i>Review the summary and click <strong>Post Depreciation</strong></p>
            <p><i class="fas fa-check-circle mr-2"></i>Journal entries will be created automatically</p>
        </div>
    </div>
</div>

<?php require_once '../../../../includes/footer.php'; ?>

