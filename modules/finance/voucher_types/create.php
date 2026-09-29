<?php
$pageTitle = 'Create Voucher Type';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';

if (!hasPermission('finance.voucher.manage')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $voucher_name = $_POST['voucher_name'] ?? '';
    $voucher_prefix = $_POST['voucher_prefix'] ?? '';
    $auto_numbering = isset($_POST['auto_numbering']) ? 1 : 0;
    $status = $_POST['status'] ?? 'active';

    $data = [
        'voucher_name' => $voucher_name,
        'voucher_prefix' => $voucher_prefix,
        'status' => $status
    ];

    $errors = validate($data, [
        'voucher_name' => 'required|max:100',
        'voucher_prefix' => 'required|max:5',
        'status' => 'required'
    ]);

    if (strlen($voucher_prefix) < 2) {
        $errors[] = 'Voucher prefix must be at least 2 characters';
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/finance/voucher_types/create.php');
    }

    $stmt = $db->prepare("
        INSERT INTO voucher_types
        (voucher_name, voucher_prefix, auto_numbering, status)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $voucher_name,
        strtoupper($voucher_prefix),
        $auto_numbering,
        $status
    ]);

    logAudit(
        $_SESSION['user_id'],
        'Created voucher type: ' . $voucher_name,
        'finance'
    );

    flash('success', 'Voucher type created successfully');
    to('modules/finance/voucher_types/index.php');
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Voucher Types
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Create New Voucher Type</h2>

    <?php if ($errors): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?= sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="createVoucherTypeForm" class="space-y-4" novalidate>

        <!-- Voucher Name -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Voucher Name *
            </label>

            <input
                type="text"
                name="voucher_name"
                value="<?= old('voucher_name') ?>"
                maxlength="100"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                placeholder="e.g. Journal Voucher">
        </div>

        <!-- Voucher Prefix -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Voucher Prefix *
            </label>

            <input
                type="text"
                name="voucher_prefix"
                value="<?= old('voucher_prefix') ?>"
                maxlength="5"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 uppercase"
                placeholder="e.g. JV, PV, RV">

            <p class="text-xs text-gray-500 mt-1">
                2-5 characters, will be stored in uppercase
            </p>
        </div>

        <!-- Auto Numbering -->
        <div class="flex items-center">
            <input
                type="checkbox"
                name="auto_numbering"
                id="auto_numbering"
                value="1"
                <?= old('auto_numbering') !== '0' ? 'checked' : '' ?>
                class="h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">

            <label
                for="auto_numbering"
                class="ml-2 text-sm font-medium text-gray-700">
                Enable Auto Numbering
            </label>
        </div>

        <!-- Status -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Status *
            </label>

            <select
                name="status"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">Select Status</option>

                <option
                    value="active"
                    <?= old('status') === 'active' ? 'selected' : '' ?>>
                    Active
                </option>

                <option
                    value="inactive"
                    <?= old('status') === 'inactive' ? 'selected' : '' ?>>
                    Inactive
                </option>
            </select>
        </div>

        <!-- Buttons -->
        <div class="pt-4">
            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Create Voucher Type
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

        /*
         * Voucher Name
         * Allow letters, numbers, spaces and common business characters.
         */
        $('input[name="voucher_name"]').on('input', function() {
            let value = $(this).val();

            value = value.replace(/[^A-Za-z0-9 .,&()_/@#+'-]/g, '');
            value = value.replace(/\s{2,}/g, ' ');

            $(this).val(value);
        });

        /*
         * Voucher Prefix
         * Allow only letters and numbers.
         * Automatically convert to uppercase.
         */
        $('input[name="voucher_prefix"]').on('input', function() {
            let value = $(this).val();

            value = value.replace(/[^A-Za-z0-9]/g, '');
            value = value.toUpperCase();

            $(this).val(value);
        });

        /*
         * jQuery Validate
         */
        $('#createVoucherTypeForm').validate({

            errorElement: 'span',

            errorClass: 'text-red-500 text-sm mt-1 block',

            rules: {
                voucher_name: {
                    required: true,
                    minlength: 2,
                    maxlength: 100
                },

                voucher_prefix: {
                    required: true,
                    minlength: 2,
                    maxlength: 5,
                    alphanumericPrefix: true
                },

                status: {
                    required: true
                }
            },

            messages: {
                voucher_name: {
                    required: 'Voucher name is required',
                    minlength: 'Voucher name must be at least 2 characters',
                    maxlength: 'Voucher name must not exceed 100 characters'
                },

                voucher_prefix: {
                    required: 'Voucher prefix is required',
                    minlength: 'Voucher prefix must be at least 2 characters',
                    maxlength: 'Voucher prefix must not exceed 5 characters',
                    alphanumericPrefix: 'Only letters and numbers are allowed'
                },

                status: {
                    required: 'Please select status'
                }
            },

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
            }
        });

        /*
         * Custom Voucher Prefix validation
         */
        $.validator.addMethod(
            'alphanumericPrefix',
            function(value, element) {
                return this.optional(element) || /^[A-Z0-9]+$/.test(value);
            },
            'Only letters and numbers are allowed'
        );

    });
</script>

<?php require_once '../../../includes/footer.php'; ?>