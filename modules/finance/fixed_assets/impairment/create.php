<?php
$pageTitle = 'Record Asset Impairment';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';

if (!hasPermission('fixedasset.impair')) { die('Access Denied'); }

$db = getDB();
$asset_id = $_GET['asset_id'] ?? '';

if (!$asset_id) {
    flash('error', 'Asset ID required');
    to('modules/finance/fixed_assets/financial_records/index.php');
}

// Get financial asset
$stmt = $db->prepare("
    SELECT f.*, a.asset_code, a.asset_name, ac.category_name,
           COALESCE(ai.impairment_loss, 0) as impairment_amount
    FROM fixed_asset_financials f
    LEFT JOIN assets a ON f.asset_id = a.id
    LEFT JOIN fixed_asset_categories fac ON f.asset_category_id = fac.id
    LEFT JOIN asset_categories ac ON fac.asset_category_id = ac.id
    LEFT JOIN asset_impairment ai ON f.asset_id = ai.asset_id AND ai.approval_status = 'approved'
    WHERE f.id = ? AND f.status = 'active'
");
$stmt->execute([$asset_id]);
$asset = $stmt->fetch();

if (!$asset) {
    flash('error', 'Asset not found or already disposed');
    to('modules/finance/fixed_assets/financial_records/index.php');
}

$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'financial_asset_id' => $asset_id,
        'impairment_date' => $_POST['impairment_date'] ?? date('Y-m-d'),
        'recoverable_amount' => $_POST['recoverable_amount'] ?? '',
        'reason' => $_POST['reason'] ?? '',
    ];

    $errors = validate($data, [
        'financial_asset_id' => 'required',
        'impairment_date' => 'required',
        'recoverable_amount' => 'required|numeric',
    ]);

    if (empty($errors)) {
        // Calculate carrying amount
        $carryingAmount = $asset['acquisition_cost'] - $asset['accumulated_depreciation'] - $asset['impairment_amount'];
        
        if ($data['recoverable_amount'] >= $carryingAmount) {
            $errors['recoverable_amount'] = 'Recoverable amount must be less than carrying amount for impairment';
        }
    }

    if (empty($errors)) {
        require_once __DIR__ . '/../DepreciationService.php';
        $deprService = new DepreciationService();
        $result = $deprService->recordImpairment($data, $_SESSION['user_id']);
        
        if ($result['success']) {
            logAudit($_SESSION['user_id'], 'Recorded impairment for asset: ' . $asset['asset_code'], 'fixed_assets');
            flash('success', 'Impairment recorded successfully. Amount: ' . formatAmount($result['impairment_loss']));
            to('modules/finance/fixed_assets/financial_records/view.php?id=' . $asset_id);
        } else {
            $errors['general'] = $result['error'];
        }
    }
}

// Calculate carrying amount
$carryingAmount = $asset['acquisition_cost'] - $asset['accumulated_depreciation'] - $asset['impairment_amount'];
?>

<div class="mb-6">
    <a href="<?= url('modules/finance/fixed_assets/financial_records/view.php?id=' . $asset_id . '') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Asset</a>
</div>

<div class="bg-white rounded-lg shadow p-6 max-w-3xl">
    <h2 class="text-xl font-semibold mb-6">
        <i class="fas fa-exclamation-triangle mr-2 text-red-500"></i>
        Record Asset Impairment
    </h2>

    <!-- Asset Summary -->
    <div class="bg-blue-50 border border-blue-200 p-4 rounded mb-6">
        <h3 class="font-semibold text-blue-800">Asset Details</h3>
        <div class="grid grid-cols-2 gap-2 mt-2">
            <div><span class="font-medium">Asset Code:</span> <?= sanitize($asset['asset_code']) ?></div>
            <div><span class="font-medium">Name:</span> <?= sanitize($asset['asset_name']) ?></div>
            <div><span class="font-medium">Category:</span> <?= sanitize($asset['category_name']) ?></div>
            <div><span class="font-medium">Original Cost:</span> <?= formatAmount($asset['acquisition_cost']) ?></div>
            <div><span class="font-medium">Accumulated Dep:</span> <?= formatAmount($asset['accumulated_depreciation']) ?></div>
            <div><span class="font-medium">Previous Impairment:</span> <?= formatAmount($asset['impairment_amount']) ?></div>
        </div>
        <div class="mt-3 pt-3 border-t border-blue-200">
            <div class="flex justify-between">
                <span class="font-medium text-blue-900">Carrying Amount:</span>
                <span class="font-bold text-xl text-blue-900"><?= formatAmount($carryingAmount) ?></span>
            </div>
        </div>
    </div>

    <?php if ($errors): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <input type="hidden" name="financial_asset_id" value="<?= $asset_id ?>">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Impairment Date <span class="text-red-600">*</span></labe>
            <input type="date" name="impairment_date" value="<?= old('impairment_date', date('Y-m-d')) ?>" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Recoverable Amount <span class="text-red-600">*</span></labe>
            <input type="number" name="recoverable_amount" value="<?= old('recoverable_amount') ?>" min="0" step="0.01" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            <p class="text-xs text-gray-500 mt-1">Current market value or fair value less costs to sell</p>
        </div>

        <?php if ($asset['impairment_amount'] > 0): ?>
        <div class="bg-yellow-50 border border-yellow-200 p-4 rounded">
            <p class="text-sm text-yellow-800">
                <i class="fas fa-info-circle mr-2"></i>
                This asset has previous impairments totaling <?= formatAmount($asset['impairment_amount']) ?>.
            </p>
        </div>
        <?php endif; ?>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Reason / Details</label>
            <textarea name="reason" rows="3" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="Explain the reason for impairment (market decline, damage, obsolescence, etc.)"><?= old('reason') ?></textarea>
        </div>

        <!-- Impairment Calculator -->
        <div class="bg-gray-50 rounded-lg p-4">
            <h3 class="font-semibold text-gray-800 mb-3">Impairment Calculation</h3>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <p class="text-xs text-gray-500 uppercase">Carrying Amount</p>
                    <p class="font-medium text-gray-800"><?= formatAmount($carryingAmount) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Recoverable Amount</p>
                    <p class="font-medium text-gray-800" id="recoverableDisplay">- <?= formatAmount(old('recoverable_amount', 0)) ?></p>
                </div>
                <div class="col-span-2 pt-2 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <p class="text-sm font-medium text-gray-700">Impairment Loss:</p>
                        <p class="text-xl font-bold text-red-600" id="impairmentDisplay">
                            <?= old('recoverable_amount') ? formatAmount(max(0, $carryingAmount - old('recoverable_amount', 0))) : '-' ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-4 flex gap-4">
            <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded hover:bg-red-700 transition">
                <i class="fas fa-check-circle mr-2"></i>Record Impairment
            </button>
            <a href="<?= url('modules/finance/fixed_assets/financial_records/view.php?id=' . $asset_id . '') ?>" class="text-gray-600 hover:text-gray-900 px-6 py-2 rounded">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const recoverableInput = document.querySelector('input[name="recoverable_amount"]');
    const impairmentDisplay = document.getElementById('impairmentDisplay');
    const recoverableDisplay = document.getElementById('recoverableDisplay');
    
    let carryingAmount = <?= $carryingAmount ?>;
    
    recoverableInput.addEventListener('input', function() {
        let recoverable = parseFloat(this.value) || 0;
        
        if (recoverable > 0) {
            let impairment = carryingAmount - recoverable;
            
            if (impairment < 0) impairment = 0;
            
            let formatted = new Intl.NumberFormat('en-AE', {
                style: 'currency',
                currency: 'AED'
            }).format(impairment);
            
            impairmentDisplay.textContent = formatted;
            recoverableDisplay.textContent = '- ' + new Intl.NumberFormat('en-AE', {
                style: 'currency',
                currency: 'AED'
            }).format(recoverable);
        }
    });
});
</script>

<?php require_once '../../../../includes/footer.php'; ?>
