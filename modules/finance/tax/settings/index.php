<?php
$pageTitle = 'Tax Settings';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.configure')) { die('Access Denied'); }

$db = getDB();

// —— Handle POST ——
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid or expired form token.', 'error');
        redirect('index.php');
    }

    $settings = $_POST;

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");

        // Only persist whitelisted tax_* keys (prevents mass-assignment of arbitrary settings)
        $allowedKeys = [
            'tax_default_tax_type_id', 'tax_default_tax_rate_id', 'tax_default_tax_group_id',
            'tax_pricing_method', 'tax_rounding_method', 'tax_decimal_precision',
            'tax_output_tax_account_id', 'tax_input_tax_account_id', 'tax_adjustment_account_id',
            'tax_invoice_prefix', 'tax_credit_note_prefix', 'tax_auto_period',
        ];

        // Normalize the checkbox so an unchecked toggle actually persists '0'
        $settings['tax_auto_period'] = isset($settings['tax_auto_period']) ? '1' : '0';
        // Clamp decimal precision to the supported 0..4 range
        $settings['tax_decimal_precision'] = max(0, min(4, (int)($settings['tax_decimal_precision'] ?? 2)));

        foreach ($allowedKeys as $key) {
            if (!array_key_exists($key, $settings)) { continue; }
            $stmt->execute([$key, (string)$settings[$key]]);
        }

        $db->commit();
        logAudit($_SESSION['user_id'], 'Updated tax settings', 'tax');
        flash('success', 'Tax settings saved.');
    } catch (Exception $e) {
        $db->rollBack();
        flash('error', 'Failed to save settings: ' . $e->getMessage());
    }

    redirect('index.php');
}

// —— Load current settings (map of tax_* keys) ——
$rows = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'tax\\_%'")->fetchAll();
$currentSettings = [];
foreach ($rows as $row) {
    $currentSettings[$row['setting_key']] = $row['setting_value'];
}

$defaults = [
    'tax_default_tax_type_id'    => '',
    'tax_default_tax_rate_id'    => '',
    'tax_default_tax_group_id'   => '',
    'tax_pricing_method'         => 'exclusive',
    'tax_rounding_method'        => 'half_up',
    'tax_decimal_precision'      => '2',
    'tax_invoice_prefix'         => 'INV',
    'tax_credit_note_prefix'     => 'CN',
    'tax_auto_period'            => '1',
];
foreach ($defaults as $key => $default) {
    if (!isset($currentSettings[$key])) {
        $currentSettings[$key] = $default;
    }
}

// Active chart of accounts for the GL mapping dropdowns
$chartAccounts = $db->query("
    SELECT coa.id, coa.account_code, coa.account_name, ag.group_name
    FROM chart_of_accounts coa
    LEFT JOIN account_groups ag ON coa.group_id = ag.id
    WHERE coa.status = 'active'
    ORDER BY coa.account_code
")->fetchAll();

// Currently mapped tax GL accounts (authoritative read via TaxService)
$outputTaxAccount  = TaxService::config('output_tax_account_id');
$inputTaxAccount   = TaxService::config('input_tax_account_id');
$adjustmentAccount = TaxService::config('adjustment_account_id');

/** Render an account <select> (mirrors finance settings account select markup). */
$renderAccountSelect = function($name, $selected) use ($chartAccounts) {
    $html = '<select id="' . $name . '" name="' . $name . '" class="w-full max-w-md px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">';
    $html .= '<option value="">-- Select Account --</option>';
    foreach ($chartAccounts as $acct) {
        $sel = ((string)$selected === (string)$acct['id']) ? ' selected' : '';
        $html .= '<option value="' . (int)$acct['id'] . '"' . $sel . '>'
              . sanitize($acct['account_code']) . ' - ' . sanitize($acct['account_name'])
              . ' (' . sanitize($acct['group_name'] ?? '') . ')</option>';
    }
    $html .= '</select>';
    return $html;
};
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Tax Settings</h1>
    <p class="text-gray-600">Configure tax engine defaults, pricing, rounding and GL mappings</p>
</div>

<?= taxui_tabs('settings') ?>

<form method="POST" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <!-- Section: Defaults -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-bookmark text-blue-600 mr-2"></i>Defaults
            </h2>
        </div>
        <div class="px-6 py-4 space-y-4">
            <div>
                <label for="tax_default_tax_type_id" class="block text-sm font-medium text-gray-700 mb-1">Default Tax Type</label>
                <?= taxui_types_select($currentSettings['tax_default_tax_type_id'] ?? '', 'tax_default_tax_type_id') ?>
                <p class="text-xs text-gray-500 mt-1">The tax regime applied when a document does not specify one.</p>
            </div>
            <div>
                <label for="tax_default_tax_rate_id" class="block text-sm font-medium text-gray-700 mb-1">Default Tax Rate</label>
                <?= taxui_rates_all($currentSettings['tax_default_tax_rate_id'] ?? '', 'tax_default_tax_rate_id') ?>
                <p class="text-xs text-gray-500 mt-1">Standard rate used when no rate is chosen (resolved by effective dates).</p>
            </div>
            <div>
                <label for="tax_default_tax_group_id" class="block text-sm font-medium text-gray-700 mb-1">Default Tax Group</label>
                <?= taxui_groups_select($currentSettings['tax_default_tax_group_id'] ?? '', 'tax_default_tax_group_id') ?>
                <p class="text-xs text-gray-500 mt-1">Default group applied to taxable lines when no group is selected.</p>
            </div>
        </div>
    </div>

    <!-- Section: Pricing -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-tags text-green-600 mr-2"></i>Pricing
            </h2>
        </div>
        <div class="px-6 py-4 space-y-4">
            <div>
                <label for="tax_pricing_method" class="block text-sm font-medium text-gray-700 mb-1">Pricing Method</label>
                <select id="tax_pricing_method" name="tax_pricing_method"
                        class="w-full max-w-md px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="exclusive" <?= ($currentSettings['tax_pricing_method'] ?? 'exclusive') === 'exclusive' ? 'selected' : '' ?>>Exclusive (tax shown separately, added on top)</option>
                    <option value="inclusive" <?= ($currentSettings['tax_pricing_method'] ?? 'exclusive') === 'inclusive' ? 'selected' : '' ?>>Inclusive (prices include tax, tax split out)</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Whether line prices are entered excluding or including tax.</p>
            </div>
        </div>
    </div>

    <!-- Section: Rounding -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-sort-amount-up text-purple-600 mr-2"></i>Rounding
            </h2>
        </div>
        <div class="px-6 py-4 space-y-4">
            <div>
                <label for="tax_rounding_method" class="block text-sm font-medium text-gray-700 mb-1">Rounding Method</label>
                <select id="tax_rounding_method" name="tax_rounding_method"
                        class="w-full max-w-md px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="half_up" <?= ($currentSettings['tax_rounding_method'] ?? 'half_up') === 'half_up' ? 'selected' : '' ?>>Round Half Up</option>
                    <option value="half_down" <?= ($currentSettings['tax_rounding_method'] ?? 'half_up') === 'half_down' ? 'selected' : '' ?>>Round Half Down</option>
                    <option value="round_up" <?= ($currentSettings['tax_rounding_method'] ?? 'half_up') === 'round_up' ? 'selected' : '' ?>>Round Up</option>
                    <option value="round_down" <?= ($currentSettings['tax_rounding_method'] ?? 'half_up') === 'round_down' ? 'selected' : '' ?>>Round Down</option>
                    <option value="nearest" <?= ($currentSettings['tax_rounding_method'] ?? 'half_up') === 'nearest' ? 'selected' : '' ?>>Nearest</option>
                    <option value="free" <?= ($currentSettings['tax_rounding_method'] ?? 'half_up') === 'free' ? 'selected' : '' ?>>Free</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">How computed tax and totals are rounded.</p>
            </div>
            <div>
                <label for="tax_decimal_precision" class="block text-sm font-medium text-gray-700 mb-1">Decimal Precision</label>
                <input type="number" id="tax_decimal_precision" name="tax_decimal_precision"
                       value="<?= sanitize($currentSettings['tax_decimal_precision'] ?? '2') ?>"
                       min="0" max="4" step="1"
                       class="w-full max-w-xs px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-500 mt-1">Number of decimal places (0-4) used when rounding tax amounts.</p>
            </div>
        </div>
    </div>

    <!-- Section: GL Account Mapping -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-book text-orange-600 mr-2"></i>GL Account Mapping
            </h2>
        </div>
        <div class="px-6 py-4 space-y-4">
            <div>
                <label for="tax_output_tax_account_id" class="block text-sm font-medium text-gray-700 mb-1">
                    Output Tax Payable Account <span class="text-red-500">*</span>
                </label>
                <?= $renderAccountSelect('tax_output_tax_account_id', $outputTaxAccount) ?>
                <p class="text-xs text-gray-500 mt-1">Liability account credited when output tax is charged on sales (typically under Tax Payable).</p>
                <?php if (!$outputTaxAccount): ?>
                <p class="text-xs text-amber-600 mt-1">Not configured — postings will fail.</p>
                <?php endif; ?>
            </div>
            <div>
                <label for="tax_input_tax_account_id" class="block text-sm font-medium text-gray-700 mb-1">
                    Input Tax Recoverable Account <span class="text-red-500">*</span>
                </label>
                <?= $renderAccountSelect('tax_input_tax_account_id', $inputTaxAccount) ?>
                <p class="text-xs text-gray-500 mt-1">Asset account debited for recoverable input tax on purchases (typically under Current Assets).</p>
                <?php if (!$inputTaxAccount): ?>
                <p class="text-xs text-amber-600 mt-1">Not configured — postings will fail.</p>
                <?php endif; ?>
            </div>
            <div>
                <label for="tax_adjustment_account_id" class="block text-sm font-medium text-gray-700 mb-1">
                    Tax Adjustment Clearing Account <span class="text-red-500">*</span>
                </label>
                <?= $renderAccountSelect('tax_adjustment_account_id', $adjustmentAccount) ?>
                <p class="text-xs text-gray-500 mt-1">Clearing account that balances tax adjustments (typically under Tax Payable).</p>
                <?php if (!$adjustmentAccount): ?>
                <p class="text-xs text-amber-600 mt-1">Not configured — postings will fail.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Section: Documents -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-file-invoice text-cyan-600 mr-2"></i>Documents
            </h2>
        </div>
        <div class="px-6 py-4 space-y-4">
            <div>
                <label for="tax_invoice_prefix" class="block text-sm font-medium text-gray-700 mb-1">Invoice Prefix</label>
                <input type="text" id="tax_invoice_prefix" name="tax_invoice_prefix"
                       value="<?= sanitize($currentSettings['tax_invoice_prefix'] ?? 'INV') ?>"
                       class="w-full max-w-xs px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="e.g. INV">
                <p class="text-xs text-gray-500 mt-1">Prefix prepended to auto-generated tax invoice numbers.</p>
            </div>
            <div>
                <label for="tax_credit_note_prefix" class="block text-sm font-medium text-gray-700 mb-1">Credit Note Prefix</label>
                <input type="text" id="tax_credit_note_prefix" name="tax_credit_note_prefix"
                       value="<?= sanitize($currentSettings['tax_credit_note_prefix'] ?? 'CN') ?>"
                       class="w-full max-w-xs px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="e.g. CN">
                <p class="text-xs text-gray-500 mt-1">Prefix prepended to auto-generated tax credit note numbers.</p>
            </div>
            <div>
                <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                    <input type="checkbox" name="tax_auto_period" value="1" <?= ($currentSettings['tax_auto_period'] ?? '1') === '1' ? 'checked' : '' ?> class="rounded">
                    Auto-create tax periods for new dates
                </label>
                <p class="text-xs text-gray-500 mt-1">When enabled, posting on a date without an open period automatically creates one.</p>
            </div>
        </div>
    </div>

    <!-- Submit -->
    <div class="flex justify-end">
        <button type="submit" name="submit" value="1"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
            <i class="fas fa-save"></i> Save Tax Settings
        </button>
    </div>
</form>

<?php require_once '../../../../includes/footer.php'; ?>