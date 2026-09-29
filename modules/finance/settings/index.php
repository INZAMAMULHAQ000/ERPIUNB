<?php
$pageTitle = 'Finance Settings';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('finance.settings.manage')) { die('Access Denied'); }

$db = getDB();

// ——— Handle POST ———
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid or expired form token. Please try again.');
        to('modules/finance/settings/index.php');
    }

    $settings = $_POST;

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");

        // Only persist whitelisted setting keys (prevents mass-assignment of arbitrary settings)
        $allowedKeys = [
            'finance_company_tax_number', 'finance_base_currency', 'finance_currency_symbol',
            'finance_decimal_places', 'finance_fy_format', 'finance_account_code_prefix',
            'finance_voucher_prefix', 'finance_ar_account_id', 'finance_revenue_account_id',
            'finance_sales_return_account_id', 'finance_ap_account_id',
            'finance_expense_account_id', 'finance_expense_payable_account_id',
        ];
        foreach ($allowedKeys as $key) {
            if (!array_key_exists($key, $settings)) { continue; }
            $stmt->execute([$key, (string)$settings[$key]]);
        }

        $db->commit();
        logAudit($_SESSION['user_id'], 'Updated finance settings', 'finance');
        flash('success', 'Finance settings saved successfully.');
    } catch (Exception $e) {
        $db->rollBack();
        flash('error', 'Failed to save settings: ' . $e->getMessage());
    }

    to('modules/finance/settings/index.php');
}

// ——— Load current settings ———
$rows = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'finance_%'")->fetchAll();
$currentSettings = [];
foreach ($rows as $row) {
    $currentSettings[$row['setting_key']] = $row['setting_value'];
}

// Default values
$defaults = [
    'finance_base_currency'     => 'USD',
    'finance_currency_symbol'   => '$',
    'finance_decimal_places'    => '2',
    'finance_fy_format'         => 'FY {Y-1}-{Y}',
    'finance_account_code_prefix' => 'ACC-',
    'finance_voucher_prefix'    => 'V-',
    'finance_company_tax_number'=> '',
];

foreach ($defaults as $key => $default) {
    if (!isset($currentSettings[$key])) {
        $currentSettings[$key] = $default;
    }
}
    // Load chart of accounts for account mapping dropdowns
    $chartAccounts = $db->query("SELECT coa.id, coa.account_code, coa.account_name, ag.group_name FROM chart_of_accounts coa LEFT JOIN account_groups ag ON coa.group_id = ag.id WHERE coa.status = 'active' ORDER BY coa.account_code")->fetchAll();

$currencies = [
    'USD' => '$ - US Dollar',
    'INR' => '&#8377; - Indian Rupee',
    'EUR' => '&#8364; - Euro',
    'GBP' => '&#163; - British Pound',
    'AED' => '&#1583;.&#1573; - UAE Dirham',
    'SAR' => '&#65020; - Saudi Riyal',
    'CAD' => 'CA$ - Canadian Dollar',
    'AUD' => 'AU$ - Australian Dollar',
    'JPY' => '&#165; - Japanese Yen',
    'CNY' => '&#20803; - Chinese Yuan',
    'MYR' => 'RM - Malaysian Ringgit',
    'QAR' => '&#65020; - Qatari Riyal',
];
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Finance Settings</h1>
    <p class="text-gray-600">Configure finance module preferences</p>
</div>

<form method="POST" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <!-- Section: General -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-info-circle text-blue-600 mr-2"></i>General
            </h2>
        </div>
        <div class="px-6 py-4 space-y-4">
            <div>
                <label for="finance_company_tax_number" class="block text-sm font-medium text-gray-700 mb-1">Company Tax Number</label>
                <input type="text" id="finance_company_tax_number" name="finance_company_tax_number"
                       value="<?= sanitize($currentSettings['finance_company_tax_number']) ?>"
                       class="w-full max-w-md px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="e.g. GSTIN1234567890">
                <p class="text-xs text-gray-500 mt-1">Tax registration number for your company (e.g. GST, VAT, PAN)</p>
            </div>
        </div>
    </div>

    <!-- Section: Currency -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-money-bill-wave text-green-600 mr-2"></i>Currency
            </h2>
        </div>
        <div class="px-6 py-4 space-y-4">
            <div>
                <label for="finance_base_currency" class="block text-sm font-medium text-gray-700 mb-1">Base Currency</label>
                <select id="finance_base_currency" name="finance_base_currency"
                        class="w-full max-w-md px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($currencies as $code => $label): ?>
                        <option value="<?= $code ?>" <?= $currentSettings['finance_base_currency'] === $code ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">The base currency used for all financial transactions</p>
            </div>
            <div>
                <label for="finance_currency_symbol" class="block text-sm font-medium text-gray-700 mb-1">Currency Symbol</label>
                <input type="text" id="finance_currency_symbol" name="finance_currency_symbol"
                       value="<?= sanitize($currentSettings['finance_currency_symbol']) ?>"
                       class="w-full max-w-xs px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="e.g. $, &#8364;, &#163;">
                <p class="text-xs text-gray-500 mt-1">Display symbol used when showing currency amounts</p>
            </div>
            <div>
                <label for="finance_decimal_places" class="block text-sm font-medium text-gray-700 mb-1">Decimal Places</label>
                <input type="number" id="finance_decimal_places" name="finance_decimal_places"
                       value="<?= sanitize($currentSettings['finance_decimal_places']) ?>"
                       min="0" max="4" step="1"
                       class="w-full max-w-xs px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-500 mt-1">Number of decimal places (0-4) for financial values</p>
            </div>
        </div>
    </div>

    <!-- Section: Formatting -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-paint-roller text-purple-600 mr-2"></i>Formatting
            </h2>
        </div>
        <div class="px-6 py-4 space-y-4">
            <div>
                <label for="finance_fy_format" class="block text-sm font-medium text-gray-700 mb-1">Financial Year Format</label>
                <input type="text" id="finance_fy_format" name="finance_fy_format"
                       value="<?= sanitize($currentSettings['finance_fy_format']) ?>"
                       class="w-full max-w-md px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="e.g. FY {Y-1}-{Y}">
                <p class="text-xs text-gray-500 mt-1">Format pattern for financial year display. Use {Y} for current year, {Y-1} for previous year</p>
            </div>
            <div>
                <label for="finance_account_code_prefix" class="block text-sm font-medium text-gray-700 mb-1">Account Code Prefix</label>
                <input type="text" id="finance_account_code_prefix" name="finance_account_code_prefix"
                       value="<?= sanitize($currentSettings['finance_account_code_prefix']) ?>"
                       class="w-full max-w-xs px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="e.g. ACC-">
                <p class="text-xs text-gray-500 mt-1">Prefix prepended to auto-generated account codes</p>
            </div>
            <div>
                <label for="finance_voucher_prefix" class="block text-sm font-medium text-gray-700 mb-1">Voucher Prefix</label>
                <input type="text" id="finance_voucher_prefix" name="finance_voucher_prefix"
                       value="<?= sanitize($currentSettings['finance_voucher_prefix']) ?>"
                       class="w-full max-w-xs px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="e.g. V-">
                <p class="text-xs text-gray-500 mt-1">Prefix prepended to auto-generated voucher numbers</p>
            </div>
        </div>
    </div>

    <!-- Section: Account Mapping -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-book text-orange-600 mr-2"></i>Account Mapping
            </h2>
        </div>
        <div class="px-6 py-4 space-y-4">
            <div>
                <label for="finance_ar_account_id" class="block text-sm font-medium text-gray-700 mb-1">
                    Accounts Receivable Account <span class="text-red-500">*</span>
                </label>
                <select id="finance_ar_account_id" name="finance_ar_account_id"
                        class="w-full max-w-md px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Select AR Account --</option>
                    <?php foreach ($chartAccounts as $acct): ?>
                    <option value="<?= $acct['id'] ?>" <?= ($currentSettings['finance_ar_account_id'] ?? '') == $acct['id'] ? 'selected' : '' ?>>
                        <?= sanitize($acct['account_code']) ?> - <?= sanitize($acct['account_name']) ?> (<?= sanitize($acct['group_name'] ?? '') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">The account used to track customer receivables (typically grouped under Current Assets)</p>
            </div>
            <div>
                <label for="finance_revenue_account_id" class="block text-sm font-medium text-gray-700 mb-1">
                    Revenue / Sales Account <span class="text-red-500">*</span>
                </label>
                <select id="finance_revenue_account_id" name="finance_revenue_account_id"
                        class="w-full max-w-md px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Select Revenue Account --</option>
                    <?php foreach ($chartAccounts as $acct): ?>
                    <option value="<?= $acct['id'] ?>" <?= ($currentSettings['finance_revenue_account_id'] ?? '') == $acct['id'] ? 'selected' : '' ?>>
                        <?= sanitize($acct['account_code']) ?> - <?= sanitize($acct['account_name']) ?> (<?= sanitize($acct['group_name'] ?? '') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">The account used to recognize revenue from sales (typically grouped under Income/Revenue)</p>
            </div>
            <div>
                <label for="finance_sales_return_account_id" class="block text-sm font-medium text-gray-700 mb-1">
                    Sales Return Account <span class="text-red-500">*</span>
                </label>
                <select id="finance_sales_return_account_id" name="finance_sales_return_account_id"
                        class="w-full max-w-md px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Select Sales Return Account --</option>
                    <?php foreach ($chartAccounts as $acct): ?>
                    <option value="<?= $acct['id'] ?>" <?= ($currentSettings['finance_sales_return_account_id'] ?? '') == $acct['id'] ? 'selected' : '' ?>>
                        <?= sanitize($acct['account_code']) ?> - <?= sanitize($acct['account_name']) ?> (<?= sanitize($acct['group_name'] ?? '') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">The account used to record sales returns and credit notes (typically a contra-revenue account grouped under Income)</p>
            </div>
            <div>
                <label for="finance_ap_account_id" class="block text-sm font-medium text-gray-700 mb-1">
                    Accounts Payable (AP) Account <span class="text-red-500">*</span>
                </label>
                <select id="finance_ap_account_id" name="finance_ap_account_id"
                        class="w-full max-w-md px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Select AP Account --</option>
                    <?php foreach ($chartAccounts as $acct): ?>
                    <option value="<?= $acct['id'] ?>" <?= ($currentSettings['finance_ap_account_id'] ?? '') == $acct['id'] ? 'selected' : '' ?>>
                        <?= sanitize($acct['account_code']) ?> - <?= sanitize($acct['account_name']) ?> (<?= sanitize($acct['group_name'] ?? '') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">The account used to track vendor payables (typically grouped under Current Liabilities). Required for vendor bill approval.</p>
            </div>
            <div>
                <label for="finance_expense_account_id" class="block text-sm font-medium text-gray-700 mb-1">
                    Default Expense Account <span class="text-red-500">*</span>
                </label>
                <select id="finance_expense_account_id" name="finance_expense_account_id"
                        class="w-full max-w-md px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Select Default Expense Account --</option>
                    <?php foreach ($chartAccounts as $acct): ?>
                    <option value="<?= $acct['id'] ?>" <?= ($currentSettings['finance_expense_account_id'] ?? '') == $acct['id'] ? 'selected' : '' ?>>
                        <?= sanitize($acct['account_code']) ?> - <?= sanitize($acct['account_name']) ?> (<?= sanitize($acct['group_name'] ?? '') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Default expense account used for bill line items when no specific expense account is assigned. Required for vendor bill approval.</p>
            </div>
            <div>
                <label for="finance_expense_payable_account_id" class="block text-sm font-medium text-gray-700 mb-1">
                    Expense Payable Account <span class="text-red-500">*</span>
                </label>
                <select id="finance_expense_payable_account_id" name="finance_expense_payable_account_id"
                        class="w-full max-w-md px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Select Expense Payable Account --</option>
                    <?php foreach ($chartAccounts as $acct): ?>
                    <option value="<?= $acct['id'] ?>" <?= ($currentSettings['finance_expense_payable_account_id'] ?? '') == $acct['id'] ? 'selected' : '' ?>>
                        <?= sanitize($acct['account_code']) ?> - <?= sanitize($acct['account_name']) ?> (<?= sanitize($acct['group_name'] ?? '') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Liability account credited when expenses/claims are approved (typically "Expense Payable" under Current Liabilities). Required for expense management approval.</p>
            </div>
        </div>
    </div>

    <!-- Submit -->
    <div class="flex justify-end">
        <button type="submit" name="submit" value="1"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
            <i class="fas fa-save"></i> Save Settings
        </button>
    </div>
</form>

<?php require_once '../../../includes/footer.php'; ?>
