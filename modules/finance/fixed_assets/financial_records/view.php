<?php
$pageTitle = 'Asset Financial Profile';
require_once '../../../../includes/header.php';

if (!hasPermission('fixedasset.view')) { die('Access Denied'); }

$db = getDB();
$id = $_GET['id'] ?? '';

if (!$id) {
    flash('error', 'Asset ID required');
    to('modules/finance/fixed_assets/financial_records/index.php');
}

// Get financial asset details
$stmt = $db->prepare("
    SELECT 
        f.*,
        a.asset_code,
        a.asset_name,
        a.category_id as operational_category_id,
        a.serial_number,
        a.purchase_date,
        a.purchase_cost as operational_cost,
        ac.category_name as asset_category,
        dm.method_name,
        dm.method_code,
        COALESCE(ai.impairment_loss, 0) as impairment_amount
    FROM fixed_asset_financials f
    LEFT JOIN assets a ON f.asset_id = a.id
    LEFT JOIN fixed_asset_categories fac ON f.asset_category_id = fac.id
    LEFT JOIN asset_categories ac ON fac.asset_category_id = ac.id
    LEFT JOIN depreciation_methods dm ON f.depreciation_method_id = dm.id
    LEFT JOIN asset_impairment ai ON f.asset_id = ai.asset_id AND ai.approval_status = 'approved'
    WHERE f.id = ?
");
$stmt->execute([$id]);
$asset = $stmt->fetch();

if (!$asset) {
    flash('error', 'Asset not found');
    to('modules/finance/fixed_assets/financial_records/index.php');
}

// Get depreciation schedule
$stmt = $db->prepare("
    SELECT * FROM asset_depreciation_schedules 
    WHERE financial_asset_id = ?
    ORDER BY period_date DESC
");
$stmt->execute([$id]);
$schedule = $stmt->fetchAll();

// Get impairment records
$stmt = $db->prepare("
    SELECT * FROM asset_impairment 
    WHERE asset_id = ?
    ORDER BY impairment_date DESC
");
$stmt->execute([$asset['asset_id']]);
$impairments = $stmt->fetchAll();

// Get transfer history
$stmt = $db->prepare("
    SELECT * FROM asset_transfer_history 
    WHERE asset_id = ?
    ORDER BY transfer_date DESC
");
$stmt->execute([$asset['asset_id']]);
$transfers = $stmt->fetchAll();

// Get financial history
$stmt = $db->prepare("
    SELECT * FROM fixed_asset_history 
    WHERE financial_asset_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$id]);
$history = $stmt->fetchAll();

// Calculate totals
$totalDepreciation = $asset['accumulated_depreciation'];
$totalImpairment = $asset['impairment_amount'];
$netBookValue = $asset['acquisition_cost'] - $totalDepreciation - $totalImpairment;
?>

<div class="mb-6">
    <a href="<?= url('modules/finance/fixed_assets/financial_records/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Register</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Card -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">
                <i class="fas fa-file-invoice mr-2 text-blue-500"></i>
                Asset Financial Profile
            </h2>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase">Asset Code</p>
                    <p class="font-medium text-gray-800"><?= sanitize($asset['asset_code']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Asset Name</p>
                    <p class="font-medium text-gray-800"><?= sanitize($asset['asset_name']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Category</p>
                    <p class="font-medium text-gray-800"><?= sanitize($asset['asset_category'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Depreciation Method</p>
                    <p class="font-medium text-gray-800"><?= sanitize($asset['method_name']) ?> (<?= sanitize($asset['method_code']) ?>)</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Capitalization Date</p>
                    <p class="font-medium text-gray-800"><?= date('M d, Y', strtotime($asset['capitalization_date'])) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Status</p>
                    <span class="px-2 py-1 text-xs rounded
                        <?= $asset['status'] == 'active' ? 'bg-green-100 text-green-800' :
                           ($asset['status'] == 'disposed' ? 'bg-red-100 text-red-800' :
                           'bg-gray-100 text-gray-800') ?>">
                        <?= ucfirst($asset['status']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Cost Breakdown -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Cost Breakdown</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 rounded-lg p-4">
                    <p class="text-xs text-blue-600 uppercase font-medium">Original Cost</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?= formatAmount($asset['acquisition_cost']) ?></p>
                </div>
                <div class="bg-indigo-50 rounded-lg p-4">
                    <p class="text-xs text-indigo-600 uppercase font-medium">Residual Value</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?= formatAmount($asset['residual_value']) ?></p>
                </div>
                <div class="bg-purple-50 rounded-lg p-4">
                    <p class="text-xs text-purple-600 uppercase font-medium">Net Book Value</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?= formatAmount($netBookValue) ?></p>
                </div>
            </div>
        </div>

        <!-- Depreciation Schedule -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Depreciation Schedule</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2 text-left">Period</th>
                            <th class="px-4 py-2 text-right">Opening NBV</th>
                            <th class="px-4 py-2 text-right">Depreciation</th>
                            <th class="px-4 py-2 text-right">Accumulated</th>
                            <th class="px-4 py-2 text-right">Closing NBV</th>
                            <th class="px-4 py-2 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($schedule as $period): ?>
                        <tr>
                            <td class="px-4 py-2"><?= date('M Y', strtotime($period['period_date'])) ?></td>
                            <td class="px-4 py-2 text-right"><?= formatAmount($period['opening_book_value']) ?></td>
                            <td class="px-4 py-2 text-right"><?= formatAmount($period['depreciation_amount']) ?></td>
                            <td class="px-4 py-2 text-right"><?= formatAmount($period['accumulated_depreciation']) ?></td>
                            <td class="px-4 py-2 text-right font-medium"><?= formatAmount($period['closing_book_value']) ?></td>
                            <td class="px-4 py-2 text-center">
                                <span class="px-2 py-1 text-xs rounded
                                    <?= $period['status'] == 'posted' ? 'bg-green-100 text-green-800' :
                                       ($period['status'] == 'reversed' ? 'bg-red-100 text-red-800' :
                                       'bg-yellow-100 text-yellow-800') ?>">
                                    <?= ucfirst($period['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($schedule)): ?>
                        <tr><td colspan="6" class="px-4 py-4 text-center text-gray-500">No depreciation schedule generated</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="lg:col-span-1">
        <!-- Depreciation Details -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Depreciation Details</h3>
            <div class="space-y-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase">Useful Life</p>
                    <p class="font-medium text-gray-800"><?= $asset['useful_life_months'] ?> months (<?= $asset['useful_life_months'] / 12 ?> years)</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Monthly Depreciation</p>
                    <p class="font-medium text-gray-800"><?= formatAmount($asset['acquisition_cost'] - $asset['residual_value']) ?> / <?= $asset['useful_life_months'] ?> = 
                        <?= formatAmount(($asset['acquisition_cost'] - $asset['residual_value']) / $asset['useful_life_months']) ?>
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Depreciation Rate</p>
                    <p class="font-medium text-gray-800">
                        <?= round((1 / $asset['useful_life_months']) * 100, 2) ?>% per month (Straight Line)
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Accumulated Depreciation</p>
                    <p class="font-medium text-red-600"><?= formatAmount($totalDepreciation) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Impairment Amount</p>
                    <p class="font-medium text-gray-800"><?= formatAmount($totalImpairment) ?></p>
                </div>
            </div>
        </div>

        <!-- Impairments -->
        <?php if (!empty($impairments)): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Impairments</h3>
            <div class="space-y-4">
                <?php foreach ($impairments as $imp): ?>
                <div class="border-l-4 border-red-500 pl-3">
                    <p class="text-sm font-medium text-gray-800">
                        <?= date('M d, Y', strtotime($imp['impairment_date'])) ?>
                    </p>
                    <p class="text-xs text-gray-600 mt-1">
                        Loss: <span class="text-red-600 font-medium"><?= formatAmount($imp['impairment_loss']) ?></span>
                    </p>
                    <p class="text-xs text-gray-500 mt-1"><?= sanitize($imp['reason']) ?></p>
                    <p class="text-xs text-gray-400 mt-1">
                        Status: <?= ucfirst($imp['approval_status']) ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Transfer History -->
        <?php if (!empty($transfers)): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Transfer History</h3>
            <div class="space-y-3">
                <?php foreach ($transfers as $transfer): ?>
                <div class="border-b border-gray-100 pb-3">
                    <p class="text-xs text-gray-500">
                        <?= date('M d, Y', strtotime($transfer['transfer_date'])) ?>
                    </p>
                    <p class="text-sm text-gray-800 mt-1">
                        <i class="fas fa-exchange-alt mr-1"></i>
                        Transfer
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Actions</h3>
            <div class="space-y-2">
                <?php if (hasPermission('fixedasset.depreciate') && $asset['status'] == 'active'): ?>
                <a href="<?= url('modules/finance/fixed_assets/depreciation/process.php?asset_id=' . $asset['financial_id'] . '') ?>" class="block w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition text-center">
                    <i class="fas fa-calculator mr-2"></i>Process Depreciation
                </a>
                <?php endif; ?>
                
                <?php if (hasPermission('fixedasset.impair') && $asset['status'] == 'active'): ?>
                <a href="<?= url('modules/finance/fixed_assets/impairment/create.php?asset_id=' . $asset['financial_id'] . '') ?>" class="block w-full bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition text-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Record Impairment
                </a>
                <?php endif; ?>
                
                <?php if (hasPermission('fixedasset.dispose') && $asset['status'] != 'disposed'): ?>
                <a href="<?= url('modules/finance/fixed_assets/disposal/create.php?asset_id=' . $asset['financial_id'] . '') ?>" class="block w-full bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition text-center">
                    <i class="fas fa-trash-alt mr-2"></i>Dispose Asset
                </a>
                <?php endif; ?>
                
                <?php if (hasPermission('fixedasset.transfer')): ?>
                <a href="<?= url('modules/finance/fixed_assets/transfers/create.php?asset_id=' . $asset['financial_id'] . '') ?>" class="block w-full bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 transition text-center">
                    <i class="fas fa-random mr-2"></i>Transfer Asset
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Print button
document.addEventListener('DOMContentLoaded', function() {
    const printBtn = document.createElement('button');
    printBtn.className = 'fixed bottom-4 right-4 bg-blue-600 text-white p-3 rounded-full shadow-lg hover:bg-blue-700 transition z-50';
    printBtn.innerHTML = '<i class="fas fa-print"></i>';
    printBtn.onclick = function() { window.print(); };
    document.body.appendChild(printBtn);
});
</script>

<?php require_once '../../../../includes/footer.php'; ?>
