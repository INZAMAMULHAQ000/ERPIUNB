<?php
$pageTitle = 'Edit Cash Account';
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

$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM cash_accounts WHERE id = ?");
$stmt->execute([$id]);

$ca = $stmt->fetch();

if (!$ca) {
    flash('error', 'Cash account not found.');
    to('modules/finance/cash_accounts/index.php');
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
        to('modules/finance/cash_accounts/edit.php?id=' . $id);
    }

    $stmt = $db->prepare("
        UPDATE cash_accounts
        SET
            cash_name = ?,
            location = ?,
            custodian_employee_id = ?,
            chart_account_id = ?,
            status = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $cash_name,
        trim($_POST['location'] ?? ''),
        $_POST['custodian_employee_id']
            ? (int)$_POST['custodian_employee_id']
            : null,
        $chart_account_id,
        $_POST['status'] ?? 'active',
        $id,
    ]);

    logAudit(
        $_SESSION['user_id'],
        'Updated cash account: ' . $cash_name,
        'finance'
    );

    flash('success', 'Cash account updated successfully.');

    to('modules/finance/cash_accounts/index.php');
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Cash Accounts
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">Edit Cash Account</h2>

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
        id="editCashAccountForm"
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
                    value="<?= old('cash_name', $ca['cash_name']) ?>"
                    maxlength="200"
                    class="w-full px-3 py-2 border rounded">
            </div>

            <!-- Location -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Location
                </label>

                <input
                    type="text"
                    name="location"
                    value="<?= old('location', $ca['location']) ?>"
                    maxlength="200"
                    class="w-full px-3 py-2 border rounded">
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

                        <?php
                        $selectedEmployee =
                            (int)($_POST['custodian_employee_id'] ?? $ca['custodian_employee_id'])
                            === (int)$e['id'];
                        ?>

                        <option
                            value="<?= $e['id'] ?>"
                            <?= $selectedEmployee ? 'selected' : '' ?>>
                            <?= sanitize($e['name']) ?>
                        </option>

                    <?php endforeach; ?>

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
                            (int)($_POST['chart_account_id'] ?? $ca['chart_account_id'])
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
                $selectedStatus = $_POST['status'] ?? $ca['status'];
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
                Update Cash Account
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

        // jQuery Validate
        $('#editCashAccountForm').validate({

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