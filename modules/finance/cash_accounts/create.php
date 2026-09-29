<?php
$pageTitle = 'Add Cash Account';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/banking_helpers.php';

if (!hasPermission('finance.cash.view')) {
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

$employees = $db->query("
    SELECT id, CONCAT(first_name, ' ', last_name) AS name
    FROM employees
    WHERE status = 'active'
    ORDER BY first_name
")->fetchAll();

if (empty($employees)) {
    $employees = $db->query("
        SELECT id, name
        FROM users
        ORDER BY name
    ")->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    }

    $cash_name = trim($_POST['cash_name'] ?? '');
    $chart_account_id = (int)($_POST['chart_account_id'] ?? 0);

    if (empty($cash_name)) {
        $errors[] = 'Cash account name is required.';
    }

    if ($chart_account_id <= 0) {
        $errors[] = 'Please select a linked Chart of Account.';
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/finance/cash_accounts/create.php');
    }

    $stmt = $db->prepare("
        INSERT INTO cash_accounts
        (
            cash_name,
            location,
            custodian_employee_id,
            opening_balance,
            chart_account_id,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $cash_name,
        trim($_POST['location'] ?? ''),
        $_POST['custodian_employee_id']
            ? (int)$_POST['custodian_employee_id']
            : null,
        (float)str_replace(',', '', $_POST['opening_balance'] ?? 0),
        $chart_account_id,
        $_POST['status'] ?? 'active',
    ]);

    logAudit(
        $_SESSION['user_id'],
        'Created cash account: ' . $cash_name,
        'finance'
    );

    flash('success', 'Cash account created successfully.');

    to('modules/finance/cash_accounts/index.php');
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Cash Accounts
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">Add New Cash Account</h2>

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
        id="createCashAccountForm"
        class="space-y-4"
        novalidate>

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Cash Account Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Cash Account Name <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="cash_name"
                    value="<?= old('cash_name') ?>"
                    maxlength="200"
                    class="w-full px-3 py-2 border rounded"
                    placeholder="e.g. Main Cash, Petty Cash">
            </div>

            <!-- Location -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Location
                </label>

                <input
                    type="text"
                    name="location"
                    value="<?= old('location') ?>"
                    maxlength="200"
                    class="w-full px-3 py-2 border rounded"
                    placeholder="e.g. Head Office, Reception">
            </div>

            <!-- Custodian -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Custodian
                </label>

                <select
                    name="custodian_employee_id"
                    class="w-full px-3 py-2 border rounded">
                    <option value="">None</option>

                    <?php foreach ($employees as $e): ?>
                        <option
                            value="<?= $e['id'] ?>"
                            <?= old('custodian_employee_id') == $e['id'] ? 'selected' : '' ?>>
                            <?= sanitize($e['name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <!-- Opening Balance -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Opening Balance (AED)
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
                    <option
                        value="active"
                        <?= old('status', 'active') === 'active' ? 'selected' : '' ?>>
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= old('status') === 'inactive' ? 'selected' : '' ?>>
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
                Save Cash Account
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

        // Cash Account Name
        $('input[name="cash_name"]').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z0-9 .,&()_/@#+'-]/g, '')
                .replace(/\s{2,}/g, ' ');
        });

        // Location
        $('input[name="location"]').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z0-9 .,&()_/@#+'-]/g, '')
                .replace(/\s{2,}/g, ' ');
        });

        // Opening Balance
        $('input[name="opening_balance"]').on('input', function() {

            let value = this.value
                .replace(/[^0-9.]/g, '');

            const parts = value.split('.');

            // Allow only one decimal point
            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }

            // Maximum 2 decimal places
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
        $('#createCashAccountForm').validate({

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

                cash_name: {
                    required: true,
                    minlength: 2,
                    maxlength: 200
                },

                location: {
                    maxlength: 200
                },

                custodian_employee_id: {
                    digits: true
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

                cash_name: {
                    required: 'Cash account name is required',
                    minlength: 'Cash account name must be at least 2 characters',
                    maxlength: 'Cash account name cannot exceed 200 characters'
                },

                location: {
                    maxlength: 'Location cannot exceed 200 characters'
                },

                custodian_employee_id: {
                    digits: 'Please select a valid custodian'
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