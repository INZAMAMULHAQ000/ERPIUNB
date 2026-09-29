<?php
$pageTitle = 'Edit Bank Account';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/banking_helpers.php';

if (!hasPermission('finance.bank.edit')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM bank_accounts WHERE id = ?");
$stmt->execute([$id]);

$ba = $stmt->fetch();

if (!$ba) {
    flash('error', 'Bank account not found.');
    to('modules/finance/bank_accounts/index.php');
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

    if (empty($errors)) {
        $stmt = $db->prepare("
            SELECT id
            FROM bank_accounts
            WHERE account_number = ?
            AND id != ?
        ");

        $stmt->execute([
            $data['account_number'],
            $id
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'Account number already exists.';
        }
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/finance/bank_accounts/edit.php?id=' . $id);
    }

    $stmt = $db->prepare("
        UPDATE bank_accounts
        SET
            account_name = ?,
            bank_name = ?,
            account_number = ?,
            iban = ?,
            swift_code = ?,
            branch_name = ?,
            currency = ?,
            chart_account_id = ?,
            status = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $data['account_name'],
        $data['bank_name'],
        $data['account_number'],
        trim($_POST['iban'] ?? ''),
        trim($_POST['swift_code'] ?? ''),
        trim($_POST['branch_name'] ?? ''),
        $_POST['currency'] ?? 'AED',
        $chart_account_id,
        $_POST['status'] ?? 'active',
        $id,
    ]);

    logAudit(
        $_SESSION['user_id'],
        'Updated bank account: ' . $data['account_name'],
        'finance'
    );

    flash('success', 'Bank account updated successfully.');

    to('modules/finance/bank_accounts/index.php');
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Bank Accounts
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">Edit Bank Account</h2>

    <?php if ($errors): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $e): ?>
                    <li><?= sanitize($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form
        method="POST"
        id="editBankAccountForm"
        class="space-y-4"
        novalidate>

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
                    value="<?= old('account_name', $ba['account_name']) ?>"
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
                    value="<?= old('bank_name', $ba['bank_name']) ?>"
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
                    value="<?= old('account_number', $ba['account_number']) ?>"
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
                    value="<?= old('iban', $ba['iban']) ?>"
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
                    value="<?= old('swift_code', $ba['swift_code']) ?>"
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
                    value="<?= old('branch_name', $ba['branch_name']) ?>"
                    maxlength="200"
                    class="w-full px-3 py-2 border rounded">
            </div>

            <!-- Currency -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Currency
                </label>

                <?php
                $selectedCurrency = $_POST['currency'] ?? $ba['currency'];
                ?>

                <select
                    name="currency"
                    class="w-full px-3 py-2 border rounded">
                    <option value="AED" <?= $selectedCurrency === 'AED' ? 'selected' : '' ?>>
                        AED - UAE Dirham
                    </option>

                    <option value="USD" <?= $selectedCurrency === 'USD' ? 'selected' : '' ?>>
                        USD - US Dollar
                    </option>

                    <option value="EUR" <?= $selectedCurrency === 'EUR' ? 'selected' : '' ?>>
                        EUR - Euro
                    </option>

                    <option value="GBP" <?= $selectedCurrency === 'GBP' ? 'selected' : '' ?>>
                        GBP - British Pound
                    </option>

                    <option value="SAR" <?= $selectedCurrency === 'SAR' ? 'selected' : '' ?>>
                        SAR - Saudi Riyal
                    </option>
                </select>
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

                        <?php
                        $selectedChartAccount =
                            (int)($_POST['chart_account_id'] ?? $ba['chart_account_id'])
                            === (int)$a['id'];
                        ?>

                        <option
                            value="<?= $a['id'] ?>"
                            <?= $selectedChartAccount ? 'selected' : '' ?>>
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

                <?php
                $selectedStatus = $_POST['status'] ?? $ba['status'];
                ?>

                <select
                    name="status"
                    class="w-full px-3 py-2 border rounded">
                    <option
                        value="active"
                        <?= $selectedStatus === 'active' ? 'selected' : '' ?>>
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= $selectedStatus === 'inactive' ? 'selected' : '' ?>>
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
                Update Bank Account
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

        // Account Name
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

        // jQuery Validate
        $('#editBankAccountForm').validate({

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