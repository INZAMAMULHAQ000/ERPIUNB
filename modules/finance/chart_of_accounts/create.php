<?php
$pageTitle = 'Create Account';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';

if (!hasPermission('finance.account.create')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

// Pre-populate account_code from settings
$prefixStmt = $db->prepare("
    SELECT setting_value
    FROM settings
    WHERE setting_key = 'finance_account_code_prefix'
");
$prefixStmt->execute();

$codePrefix = $prefixStmt->fetch()['setting_value'] ?? 'ACC-';

// Generate next code
$countStmt = $db->query("
    SELECT COUNT(*) as cnt
    FROM chart_of_accounts
");

$nextNum = $countStmt->fetch()['cnt'] + 1;

$suggestedCode = $codePrefix . str_pad(
    $nextNum,
    4,
    '0',
    STR_PAD_LEFT
);

// Fetch all groups for hierarchical display
$allGroups = $db->query("
    SELECT id, group_name, parent_group_id, nature
    FROM account_groups
    WHERE status = 'active'
    ORDER BY nature, display_order, group_name
")->fetchAll();

// Fetch all types with group name
$types = $db->query("
    SELECT
        at.id,
        at.type_name,
        ag.group_name
    FROM account_types at
    JOIN account_groups ag
        ON at.group_id = ag.id
    ORDER BY
        ag.display_order,
        ag.group_name,
        at.type_name
")->fetchAll();


function buildGroupOptions(
    $allGroups,
    $selectedId = null,
    $parentId = 0,
    $depth = 0
) {
    $html = '';

    foreach ($allGroups as $g) {

        if (($g['parent_group_id'] ?? 0) == $parentId) {

            $prefix = str_repeat('&nbsp;&nbsp;', $depth);

            $sel = ($selectedId == $g['id'])
                ? ' selected'
                : '';

            $html .= '<option value="' . $g['id'] . '"' . $sel . '>'
                . $prefix
                . sanitize($g['group_name'])
                . '</option>';

            $html .= buildGroupOptions(
                $allGroups,
                $selectedId,
                $g['id'],
                $depth + 1
            );
        }
    }

    return $html;
}


// ==========================================
// POST - Create Account
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = [
        'account_code' => trim($_POST['account_code'] ?? ''),
        'account_name' => trim($_POST['account_name'] ?? ''),
        'group_id' => $_POST['group_id'] ?? '',
        'type_id' => $_POST['type_id'] ?? '',
    ];

    // Server-side validation
    $errors = validate($data, [
        'account_code' => 'required',
        'account_name' => 'required',
        'group_id' => 'required',
        'type_id' => 'required',
    ]);

    // Check unique account code
    if (empty($errors)) {

        $checkStmt = $db->prepare("
            SELECT id
            FROM chart_of_accounts
            WHERE account_code = ?
        ");

        $checkStmt->execute([
            $data['account_code']
        ]);

        if ($checkStmt->fetch()) {

            $errors['account_code'] =
                'Account code already exists';
        }
    }

    if (!empty($errors)) {

        saveFormState($errors);

        to(
            'modules/finance/chart_of_accounts/create.php'
        );
    }

    // Insert account
    $stmt = $db->prepare("
        INSERT INTO chart_of_accounts
        (
            account_code,
            account_name,
            group_id,
            type_id,
            opening_balance,
            opening_balance_type,
            allow_manual_entry,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data['account_code'],
        $data['account_name'],
        $data['group_id'],
        $data['type_id'],
        $_POST['opening_balance'] ?? 0,
        $_POST['opening_balance_type'] ?? 'debit',
        $_POST['allow_manual_entry'] ?? 0,
        $_POST['status'] ?? 'active',
    ]);

    logAudit(
        $_SESSION['user_id'],
        'Created account: '
            . $data['account_code']
            . ' - '
            . $data['account_name'],
        'finance'
    );

    flash(
        'success',
        'Account created successfully'
    );

    to(
        'modules/finance/chart_of_accounts/index.php'
    );
}
?>

<div class="mb-6">

    <a href="<?= url('modules/finance/chart_of_accounts/index.php') ?>"
        class="text-blue-600 hover:text-blue-900">

        <i class="fas fa-arrow-left mr-1"></i>
        Back to Chart of Accounts

    </a>

</div>


<div class="bg-white rounded-lg shadow p-6 w-full">

    <h2 class="text-xl font-semibold mb-6">
        Add New Account
    </h2>


    <?php if ($errors): ?>

        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">

            <ul class="list-disc list-inside">

                <?php foreach ($errors as $e): ?>

                    <li><?= sanitize($e) ?></li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <form method="POST"
        id="createAccountForm"
        class="space-y-4"
        novalidate>

        <input type="hidden"
            name="csrf_token"
            value="<?= csrfToken() ?>">


        <!-- Account Code + Account Name -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Account Code -->
            <div>

                <label
                    for="account_code"
                    class="block text-sm font-medium text-gray-700 mb-1">

                    Account Code <span class="text-red-600">*</span>

                </label>

                <input
                    type="text"
                    id="account_code"
                    name="account_code"
                    value="<?= sanitize(old('account_code', $suggestedCode)) ?>"
                    maxlength="50"
                    autocomplete="off"
                    placeholder="e.g. ACC-0001"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


            <!-- Account Name -->
            <div>

                <label
                    for="account_name"
                    class="block text-sm font-medium text-gray-700 mb-1">

                    Account Name <span class="text-red-600">*</span>

                </label>

                <input
                    type="text"
                    id="account_name"
                    name="account_name"
                    value="<?= sanitize(old('account_name')) ?>"
                    maxlength="150"
                    autocomplete="off"
                    placeholder="e.g. Petty Cash"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

        </div>


        <!-- Group -->
        <div>

            <label
                for="group_id"
                class="block text-sm font-medium text-gray-700 mb-1">

                Group <span class="text-red-600">*</span>

            </label>

            <select
                id="group_id"
                name="group_id"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <option value="">
                    Select Group
                </option>

                <?= buildGroupOptions(
                    $allGroups,
                    (int) old('group_id')
                ) ?>

            </select>

        </div>


        <!-- Type -->
        <div>

            <label
                for="type_id"
                class="block text-sm font-medium text-gray-700 mb-1">

                Type <span class="text-red-600">*</span>

            </label>

            <select
                id="type_id"
                name="type_id"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <option value="">
                    Select Type
                </option>

                <?php foreach ($types as $t): ?>

                    <option
                        value="<?= $t['id'] ?>"
                        <?= old('type_id') == $t['id']
                            ? 'selected'
                            : '' ?>>

                        <?= sanitize(
                            $t['group_name']
                                . ' - '
                                . $t['type_name']
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- Opening Balance + Balance Type -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Opening Balance -->
            <div>

                <label
                    for="opening_balance"
                    class="block text-sm font-medium text-gray-700 mb-1">

                    Opening Balance

                </label>

                <input
                    type="number"
                    id="opening_balance"
                    name="opening_balance"
                    value="<?= old('opening_balance', '0') ?>"
                    min="0"
                    max="999999999999999"
                    step="0.01"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


            <!-- Balance Type -->
            <div>

                <label
                    for="opening_balance_type"
                    class="block text-sm font-medium text-gray-700 mb-1">

                    Balance Type

                </label>

                <select
                    id="opening_balance_type"
                    name="opening_balance_type"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option
                        value="debit"
                        <?= old(
                            'opening_balance_type',
                            'debit'
                        ) === 'debit'
                            ? 'selected'
                            : '' ?>>

                        Debit

                    </option>

                    <option
                        value="credit"
                        <?= old('opening_balance_type') === 'credit'
                            ? 'selected'
                            : '' ?>>

                        Credit

                    </option>

                </select>

            </div>

        </div>


        <!-- Manual Entry + Status -->
        <div class="flex items-center gap-6">

            <!-- Allow Manual Entry -->
            <div>

                <label class="inline-flex items-center">

                    <input
                        type="hidden"
                        name="allow_manual_entry"
                        value="0">

                    <input
                        type="checkbox"
                        id="allow_manual_entry"
                        name="allow_manual_entry"
                        value="1"
                        <?= old(
                            'allow_manual_entry',
                            1
                        )
                            ? 'checked'
                            : '' ?>
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">

                    <span class="ml-2 text-sm font-medium text-gray-700">
                        Allow Manual Entry
                    </span>

                </label>

            </div>


            <!-- Status -->
            <div>

                <label
                    for="status"
                    class="block text-sm font-medium text-gray-700 mb-1">

                    Status

                </label>

                <select
                    id="status"
                    name="status"
                    class="px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 w-full">

                    <option
                        value="active"
                        <?= old('status', 'active') === 'active'
                            ? 'selected'
                            : '' ?>>

                        Active

                    </option>

                    <option
                        value="inactive"
                        <?= old('status') === 'inactive'
                            ? 'selected'
                            : '' ?>>

                        Inactive

                    </option>

                </select>

            </div>

        </div>


        <!-- Buttons -->
        <div class="pt-4">

            <button
                type="submit"
                class="bg-red-600 text-white px-6 py-2 rounded hover:bg-red-700 transition">

                Create Account

            </button>

            <a href="<?= url('modules/finance/chart_of_accounts/index.php') ?>"
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

        // ==========================================
        // Account Code - Input Filter
        // ==========================================
        $("#account_code").on("input", function() {

            let value = $(this).val();

            // Allow only letters, numbers and hyphen
            value = value.replace(/[^A-Za-z0-9-]/g, "");

            $(this).val(value);

        });


        // ==========================================
        // Account Name - Input Filter
        // ==========================================
        $("#account_name").on("input", function() {

            let value = $(this).val();

            // Allow letters, numbers and common characters
            value = value.replace(
                /[^A-Za-z0-9 .,&()_/@#+'-]/g,
                ""
            );

            // Remove multiple spaces
            value = value.replace(/\s{2,}/g, " ");

            $(this).val(value);

        });


        // ==========================================
        // Opening Balance - Input Filter
        // ==========================================
        $("#opening_balance").on("input", function() {

            let value = $(this).val();

            // Allow only numbers and decimal point
            value = value.replace(/[^0-9.]/g, "");

            // Allow only one decimal point
            const parts = value.split(".");

            if (parts.length > 2) {

                value =
                    parts[0] +
                    "." +
                    parts.slice(1).join("");

            }

            // Maximum 2 decimal places
            if (value.includes(".")) {

                const decimalParts = value.split(".");

                value =
                    decimalParts[0] +
                    "." +
                    decimalParts[1].substring(0, 2);
            }

            $(this).val(value);

        });


        // ==========================================
        // Custom Amount Validation
        // ==========================================
        $.validator.addMethod(
            "validAmount",
            function(value, element) {

                if (this.optional(element)) {
                    return true;
                }

                return /^\d+(?:\.\d{1,2})?$/.test(value);

            },
            "Please enter a valid amount"
        );


        // ==========================================
        // jQuery Validation
        // ==========================================
        $("#createAccountForm").validate({

            errorElement: "span",

            errorClass: "text-red-500 text-sm mt-1 block",

            rules: {

                account_code: {
                    required: true,
                    minlength: 2,
                    maxlength: 50
                },

                account_name: {
                    required: true,
                    minlength: 2,
                    maxlength: 150
                },

                group_id: {
                    required: true
                },

                type_id: {
                    required: true
                },

                opening_balance: {
                    number: true,
                    min: 0,
                    validAmount: true
                },

                opening_balance_type: {
                    required: true
                },

                status: {
                    required: true
                }
            },


            messages: {

                account_code: {
                    required: "Account code is required",
                    minlength: "Account code must be at least 2 characters",
                    maxlength: "Account code must not exceed 50 characters"
                },

                account_name: {
                    required: "Account name is required",
                    minlength: "Account name must be at least 2 characters",
                    maxlength: "Account name must not exceed 150 characters"
                },

                group_id: {
                    required: "Please select a group"
                },

                type_id: {
                    required: "Please select an account type"
                },

                opening_balance: {
                    number: "Please enter a valid amount",
                    min: "Opening balance cannot be negative",
                    validAmount: "Please enter a valid amount with maximum 2 decimal places"
                },

                opening_balance_type: {
                    required: "Please select balance type"
                },

                status: {
                    required: "Please select status"
                }
            },


            // ==========================================
            // Error Message Placement
            // ==========================================
            errorPlacement: function(error, element) {

                if (element.is("select")) {

                    // Dropdown parent container
                    // Error will appear BELOW dropdown
                    element.parent().append(error);

                } else {

                    // Normal input
                    error.insertAfter(element);

                }
            },


            // ==========================================
            // Highlight Invalid Fields
            // ==========================================
            highlight: function(element) {

                $(element).addClass("border-red-500");

            },


            // ==========================================
            // Remove Error Highlight
            // ==========================================
            unhighlight: function(element) {

                $(element).removeClass("border-red-500");

            }

        });

    });
</script>


<?php require_once '../../../includes/footer.php'; ?>