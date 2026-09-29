<?php
$pageTitle = 'Dispose Asset';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';

if (!hasPermission('fixedasset.dispose')) { die('Access Denied'); }

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
    WHERE f.id = ? AND f.status != 'disposed'
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
        'disposal_date' => $_POST['disposal_date'] ?? date('Y-m-d'),
        'disposal_type' => $_POST['disposal_type'] ?? 'scrapped',
        'proceeds' => $_POST['proceeds'] ?? '0',
        'cash_account_id' => $_POST['cash_account_id'] ?? '',
        'notes' => $_POST['notes'] ?? '',
    ];

    $errors = validate($data, [
        'financial_asset_id' => 'required',
        'disposal_date' => 'required',
        'disposal_type' => 'required',
        'proceeds' => 'required|numeric',
    ]);

    if (empty($errors)) {
        require_once __DIR__ . '/../DepreciationService.php';
        $deprService = new DepreciationService();
        $result = $deprService->recordDisposal($data, $_SESSION['user_id']);
        
        if ($result['success']) {
            logAudit($_SESSION['user_id'], 'Disposed asset: ' . $asset['asset_code'], 'fixed_assets');
            flash('success', 'Asset disposal recorded successfully. Gain/Loss: ' . formatAmount($result['gain_loss']));
            to('modules/finance/fixed_assets/financial_records/index.php');
        } else {
            $errors['general'] = $result['error'];
        }
    }
}

// Calculate current book value
$bookValue = $asset['acquisition_cost'] - $asset['accumulated_depreciation'] - $asset['impairment_amount'];

// Get bank/cash accounts
$bankAccounts = $db->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE account_type IN ('asset_current', 'asset_noncurrent') AND status = 'active' ORDER BY account_code")->fetchAll();
?>

<div class="mb-6">
    <a href="<?= url('modules/finance/fixed_assets/financial_records/view.php?id=' . $asset_id . '') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Asset</a>
</div>

<div class="bg-white rounded-lg shadow p-6 max-w-3xl">
    <h2 class="text-xl font-semibold mb-6">
        <i class="fas fa-trash-alt mr-2 text-red-500"></i>
        Dispose Asset
    </h2>

    <!-- Asset Summary -->
    <div class="bg-red-50 border border-red-200 p-4 rounded mb-6">
        <h3 class="font-semibold text-red-800">Asset Details</h3>
        <div class="grid grid-cols-2 gap-2 mt-2">
            <div><span class="font-medium">Asset Code:</span> <?= sanitize($asset['asset_code']) ?></div>
            <div><span class="font-medium">Name:</span> <?= sanitize($asset['asset_name']) ?></div>
            <div><span class="font-medium">Category:</span> <?= sanitize($asset['category_name']) ?></div>
            <div><span class="font-medium">Original Cost:</span> <?= formatAmount($asset['acquisition_cost']) ?></div>
            <div><span class="font-medium">Accumulated Depreciation:</span> <?= formatAmount($asset['accumulated_depreciation']) ?></div>
            <div><span class="font-medium">Impairment:</span> <?= formatAmount($asset['impairment_amount']) ?></div>
        </div>
        <div class="mt-3 pt-3 border-t border-red-200">
            <div class="flex justify-between items-center">
                <span class="font-medium text-red-900">Current Book Value:</span>
                <span class="font-bold text-xl text-red-900"><?= formatAmount($bookValue) ?></span>
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

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Disposal Date <span class="text-red-600">*</span></labe>
                <input type="date" name="disposal_date" value="<?= old('disposal_date', date('Y-m-d')) ?>" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Disposal Type <span class="text-red-600">*</span></labe>
                <select name="disposal_type" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                    <option value="sale" <?= old('disposal_type') == 'sale' ? 'selected' : '' ?>>Sale</option>
                    <option value="scrapped" <?= old('disposal_type') == 'scrapped' ? 'selected' : '' ?>>Scrapped</option>
                    <option value="written_off" <?= old('disposal_type') == 'written_off' ? 'selected' : '' ?>>Written Off</option>
                    <option value="donated" <?= old('disposal_type') == 'donated' ? 'selected' : '' ?>>Donated</option>
                    <option value="other" <?= old('disposal_type') == 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Proceeds (Sale Price) <span class="text-red-600">*</span></labe>
                <input type="number" name="proceeds" value="<?= old('proceeds', '0') ?>" min="0" step="0.01" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                <p class="text-xs text-gray-500 mt-1">Enter 0 for scrap, donation, or write-off</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cash/Bank Account <span class="text-red-600">*</span></labe>
                <select name="cash_account_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                    <option value="">Select Account</option>
                    <?php foreach ($bankAccounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>" <?= old('cash_account_id') == $acc['id'] ? 'selected' : '' ?>>
                        <?= sanitize($acc['account_code']) ?> - <?= sanitize($acc['account_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Gain/Loss Calculator -->
        <div class="bg-gray-50 rounded-lg p-4">
            <h3 class="font-semibold text-gray-800 mb-3">Gain/Loss Calculation</h3>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <p class="text-xs text-gray-500 uppercase">Book Value</p>
                    <p class="font-medium text-gray-800"><?= formatAmount($bookValue) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Proceeds</p>
                    <p class="font-medium text-gray-800" id="proceedsDisplay">- <?= formatAmount(old('proceeds', 0)) ?></p>
                </div>
                <div class="col-span-2 pt-2 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <p class="text-sm font-medium text-gray-700">Gain/Loss on Disposal:</p>
                        <p class="text-xl font-bold" id="gainLossDisplay">
                            <?= old('proceeds') ? formatAmount(old('proceeds', 0) - $bookValue) : '-' ?>
                        </p>
                    </div>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">
                <span class="text-green-600 font-medium">✓</span> Gain: Positive amount (Credit to P&L)<br>
                <span class="text-red-600 font-medium">✗</span> Loss: Negative amount (Debit to P&L)
            </p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea name="notes" rows="3" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="Add any notes about this disposal (buyer, condition, etc.)"><?= old('notes') ?></textarea>
        </div>

        <div class="pt-4 flex gap-4">
            <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded hover:bg-red-700 transition">
                <i class="fas fa-check-circle mr-2"></i>Record Disposal
            </button>
            <a href="<?= url('modules/finance/fixed_assets/financial_records/view.php?id=' . $asset_id . '') ?>" class="text-gray-600 hover:text-gray-900 px-6 py-2 rounded">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const proceedsInput = document.querySelector('input[name="proceeds"]');
    const gainLossDisplay = document.getElementById('gainLossDisplay');
    const proceedsDisplay = document.getElementById('proceedsDisplay');
    
    let bookValue = <?= $bookValue ?>;
    
    proceedsInput.addEventListener('input', function() {
        let proceeds = parseFloat(this.value) || 0;
        
        let gainLoss = proceeds - bookValue;
        
        let formatted = new Intl.NumberFormat('en-AE', {
            style: 'currency',
            currency: 'AED'
        }).format(gainLoss);
        
        gainLossDisplay.textContent = formatted;
        gainLossDisplay.className = 'text-xl font-bold ' + (gainLoss > 0 ? 'text-green-600' : (gainLoss < 0 ? 'text-red-600' : 'text-gray-600'));
        
        proceedsDisplay.textContent = '- ' + new Intl.NumberFormat('en-AE', {
            style: 'currency',
            currency: 'AED'
        }).format(proceeds);
    });
});
</script>

<?php require_once '../../../../includes/footer.php'; ?>
