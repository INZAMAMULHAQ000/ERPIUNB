<?php
$pageTitle = 'Add Bank Account';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/banking_helpers.php';

if (!hasPermission('finance.bank.create')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$accounts = $db->query("
    SELECT id, account_code, account_name
    FROM chart_of_accounts
    WHERE status = 'active'
    ORDER BY account_code
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    }

    $data = [
        'account_name' => trim($_POST['account_name'] ?? ''),
        'bank_name' => trim($_POST['bank_name'] ?? ''),
        'account_number' => trim($_POST['account_number'] ?? ''),
    ];

    $errors = validate($data, [
        'account_name' => 'required|max:200',
        'bank_name' => 'required|max:200',
        'account_number' => 'required|max:50',
    ]);

    $chart_account_id = (int)($_POST['chart_account_id'] ?? 0);

    if ($chart_account_id <= 0) {
        $errors[] = 'Please select a linked Chart of Account.';
    }

    // Prevent duplicate account numbers
    if (empty($errors)) {
        $stmt = $db->prepare("
            SELECT id
            FROM bank_accounts
            WHERE account_number = ?
        ");
        $stmt->execute([$data['account_number']]);

        if ($stmt->fetch()) {
            $errors[] = 'Account number already exists.';
        }
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/finance/bank_accounts/create.php');
    }

    $stmt = $db->prepare("
        INSERT INTO bank_accounts
        (
            account_name,
            bank_name,
            account_number,
            iban,
            swift_code,
            branch_name,
            currency,
            opening_balance,
            chart_account_id,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data['account_name'],
        $data['bank_name'],
        $data['account_number'],
        trim($_POST['iban'] ?? ''),
        trim($_POST['swift_code'] ?? ''),
        trim($_POST['branch_name'] ?? ''),
        $_POST['currency'] ?? 'AED',
        (float)str_replace(',', '', $_POST['opening_balance'] ?? 0),
        $chart_account_id,
        $_POST['status'] ?? 'active',
    ]);

    logAudit(
        $_SESSION['user_id'],
        'Created bank account: ' . $data['account_name'],
        'finance'
    );

    flash('success', 'Bank account created successfully.');
    to('modules/finance/bank_accounts/index.php');
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Bank Accounts
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">Add New Bank Account</h2>

    <?php if ($errors): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $e): ?>
                    <li><?= sanitize($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="createBankAccountForm" class="space-y-4" novalidate>

         <input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Account Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Account Name <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="account_name"
                    value="<?= old('account_name') ?>"
                    maxlength="200"
                    class="w-full px-3 py-2 border rounded">
            </div>

            <!-- Bank Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Bank Name <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="bank_name"
                    value="<?= old('bank_name') ?>"
                    maxlength="200"
                    class="w-full px-3 py-2 border rounded">
            </div>

            <!-- Account Number -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Account Number <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="account_number"
                    value="<?= old('account_number') ?>"
                    maxlength="50"
                    class="w-full px-3 py-2 border rounded">
            </div>

            <!-- IBAN -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    IBAN
                </label>

                <input
                    type="text"
                    name="iban"
                    value="<?= old('iban') ?>"
                    maxlength="50"
                    class="w-full px-3 py-2 border rounded">
            </div>

            <!-- SWIFT Code -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    SWIFT Code
                </label>

                <input
                    type="text"
                    name="swift_code"
                    value="<?= old('swift_code') ?>"
                    maxlength="20"
                    class="w-full px-3 py-2 border rounded">
            </div>

            <!-- Branch Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Branch Name
                </label>

                <input
                    type="text"
                    name="branch_name"
                    value="<?= old('branch_name') ?>"
                    maxlength="200"
                    class="w-full px-3 py-2 border rounded">
            </div>

            <!-- Currency -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Currency <span class="text-red-600">*</span>
                </label>

                <select
                    name="currency"
                    class="w-full px-3 py-2 border rounded">
                    <option value="AED" <?= old('currency', 'AED') === 'AED' ? 'selected' : '' ?>>
                        AED - UAE Dirham
                    </option>

                    <option value="USD" <?= old('currency') === 'USD' ? 'selected' : '' ?>>
                        USD - US Dollar
                    </option>

                    <option value="EUR" <?= old('currency') === 'EUR' ? 'selected' : '' ?>>
                        EUR - Euro
                    </option>

                    <option value="GBP" <?= old('currency') === 'GBP' ? 'selected' : '' ?>>
                        GBP - British Pound
                    </option>

                    <option value="SAR" <?= old('currency') === 'SAR' ? 'selected' : '' ?>>
                        SAR - Saudi Riyal
                    </option>
                </select>
            </div>

            <!-- Opening Balance -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Opening Balance (<?= old('currency', 'AED') ?>)
                </label>

                <input
                    type="text"
                    name="opening_balance"
                    value="<?= old('opening_balance', '0.00') ?>"
                    maxlength="15"
                    class="w-full px-3 py-2 border rounded">
            </div>

            <!-- Chart Account -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Linked Chart of Account <span class="text-red-600">*</span>
                </label>

                <select
                    name="chart_account_id"
                    class="w-full px-3 py-2 border rounded">
                    <option value="">Select Account</option>

                    <?php foreach ($accounts as $a): ?>
                        <option
                            value="<?= $a['id'] ?>"
                            <?= old('chart_account_id') == $a['id'] ? 'selected' : '' ?>>
                            <?= sanitize($a['account_code'] . ' - ' . $a['account_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <!-- Status -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Status
                </label>

                <select
                    name="status"
                    class="w-full px-3 py-2 border rounded">
                    <option value="active" <?= old('status', 'active') === 'active' ? 'selected' : '' ?>>
                        Active
                    </option>

                    <option value="inactive" <?= old('status') === 'inactive' ? 'selected' : '' ?>>
                        Inactive
                    </option>
                </select>
            </div>

        </div>

        <div class="pt-4">

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                <i class="fas fa-save mr-2"></i>
                Save Bank Account
            </button>

            <a
                href="index.php"
                class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">
                Cancel
            </a>

        </div>

    </form>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>
<script>
    $(document).ready(function() {

        // Account Name - letters, numbers, spaces and common symbols
        $('input[name="account_name"]').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z0-9 .,&()_/@#+'-]/g, '')
                .replace(/\s{2,}/g, ' ');
        });

        // Bank Name
        $('input[name="bank_name"]').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z0-9 .,&()_/@#+'-]/g, '')
                .replace(/\s{2,}/g, ' ');
        });

        // Account Number
        $('input[name="account_number"]').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z0-9\-]/g, '');
        });

        // IBAN
        $('input[name="iban"]').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z0-9]/g, '')
                .toUpperCase();
        });

        // SWIFT Code
        $('input[name="swift_code"]').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z0-9]/g, '')
                .toUpperCase();
        });

        // Branch Name
        $('input[name="branch_name"]').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z0-9 .,&()_/@#+'-]/g, '')
                .replace(/\s{2,}/g, ' ');
        });

        // Opening Balance
        $('input[name="opening_balance"]').on('input', function() {
            let value = this.value
                .replace(/[^0-9.]/g, '');

            const parts = value.split('.');

            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }

            if (value.includes('.')) {
                const decimalParts = value.split('.');
                value =
                    decimalParts[0] +
                    '.' +
                    decimalParts[1].substring(0, 2);
            }

            this.value = value;
        });

        // jQuery Validate
        $('#createBankAccountForm').validate({

            errorElement: 'span',

            errorClass: 'text-red-500 text-sm mt-1 block',

            errorPlacement: function(error, element) {

                if (element.is('select')) {
                    element.parent().append(error);
                } else {
                    error.insertAfter(element);
                }
            },

            highlight: function(element) {
                $(element).addClass('border-red-500');
            },

            unhighlight: function(element) {
                $(element).removeClass('border-red-500');
            },

            rules: {

                account_name: {
                    required: true,
                    minlength: 2,
                    maxlength: 200
                },

                bank_name: {
                    required: true,
                    minlength: 2,
                    maxlength: 200
                },

                account_number: {
                    required: true,
                    minlength: 3,
                    maxlength: 50
                },

                iban: {
                    maxlength: 50
                },

                swift_code: {
                    maxlength: 20
                },

                branch_name: {
                    maxlength: 200
                },

                currency: {
                    required: true
                },

                opening_balance: {
                    number: true,
                    min: 0
                },

                chart_account_id: {
                    required: true
                },

                status: {
                    required: true
                }
            },

            messages: {

                account_name: {
                    required: 'Account name is required',
                    minlength: 'Account name must be at least 2 characters',
                    maxlength: 'Account name cannot exceed 200 characters'
                },

                bank_name: {
                    required: 'Bank name is required',
                    minlength: 'Bank name must be at least 2 characters',
                    maxlength: 'Bank name cannot exceed 200 characters'
                },

                account_number: {
                    required: 'Account number is required',
                    minlength: 'Account number must be at least 3 characters',
                    maxlength: 'Account number cannot exceed 50 characters'
                },

                iban: {
                    maxlength: 'IBAN cannot exceed 50 characters'
                },

                swift_code: {
                    maxlength: 'SWIFT code cannot exceed 20 characters'
                },

                branch_name: {
                    maxlength: 'Branch name cannot exceed 200 characters'
                },

                currency: {
                    required: 'Please select a currency'
                },

                opening_balance: {
                    number: 'Please enter a valid amount',
                    min: 'Opening balance cannot be negative'
                },

                chart_account_id: {
                    required: 'Please select a linked Chart of Account'
                },

                status: {
                    required: 'Please select status'
                }
            }
        });

    });
</script>

<?php require_once '../../../includes/footer.php'; ?>