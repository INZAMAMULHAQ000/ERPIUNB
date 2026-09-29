<?php
$pageTitle = 'Create Account Type';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';

if (!hasPermission('finance.manage')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $type_name = trim($_POST['type_name'] ?? '');
    $group_id = $_POST['group_id'] ?? '';
    $description = trim($_POST['description'] ?? '');

    $data = [
        'type_name' => $type_name,
        'group_id' => $group_id,
        'description' => $description
    ];

    $errors = validate($data, [
        'type_name' => 'required|max:100',
        'group_id' => 'required'
    ]);

    /*
     * Additional server-side validation
     * Type name should contain letters and spaces only.
     */
    if (empty($errors['type_name'])) {

        if (!preg_match('/^[A-Za-z ]+$/', $type_name)) {
            $errors['type_name'] = 'Type name can contain letters and spaces only.';
        }

        if (preg_match('/\s{2,}/', $type_name)) {
            $errors['type_name'] = 'Type name cannot contain multiple spaces.';
        }
    }

    /*
     * Validate group ID
     */
    if (empty($errors['group_id'])) {

        if (!ctype_digit((string)$group_id) || (int)$group_id <= 0) {
            $errors['group_id'] = 'Please select a valid group.';
        }
    }

    /*
     * Validate description length
     */
    if (empty($errors['description'])) {

        if (strlen($description) > 500) {
            $errors['description'] = 'Description cannot exceed 500 characters.';
        }
    }

    if (!empty($errors)) {

        saveFormState($errors);

        to('modules/finance/account_types/create.php');
    }

    /*
     * Insert Account Type
     */
    $stmt = $db->prepare("
        INSERT INTO account_types
        (
            type_name,
            group_id,
            description
        )
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $type_name,
        (int)$group_id,
        $description !== '' ? $description : null
    ]);

    /*
     * Audit Log
     */
    logAudit(
        $_SESSION['user_id'],
        'Created account type: ' . $type_name,
        'finance'
    );

    /*
     * Success Message
     */
    flash(
        'success',
        'Account type created successfully'
    );

    to('modules/finance/account_types/index.php');
}


/*
 * Get Account Groups
 */
$groups = $db->query("
    SELECT
        id,
        group_name,
        nature,
        parent_group_id,
        display_order
    FROM account_groups
    WHERE status = 'active'
    ORDER BY nature, display_order
")->fetchAll();


/*
 * Account Natures
 */
$natures = [
    'asset',
    'liability',
    'equity',
    'income',
    'expense'
];
?>

<!-- Back -->
<div class="mb-6">

    <a
        href="index.php"
        class="text-blue-600 hover:text-blue-900">

        <i class="fas fa-arrow-left mr-1"></i>

        Back to Account Types

    </a>

</div>


<!-- Main Card -->
<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">
        Create New Account Type
    </h2>


    <!-- Server Validation Errors -->
    <?php if (!empty($errors)): ?>

        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">

            <ul class="list-disc list-inside">

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= sanitize($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <!-- Form -->
    <form
        method="POST"
        id="createAccountTypeForm"
        class="space-y-4"
        novalidate
        onsubmit="return showAccountTypeConfirm(event)">


        <!-- Type Name -->
        <div>

            <label
                class="block text-sm font-medium text-gray-700 mb-1">

                Type Name *

            </label>

            <input
                type="text"
                name="type_name"
                value="<?= sanitize(old('type_name')) ?>"
                maxlength="100"
                autocomplete="off"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

        </div>


        <!-- Group -->
        <div>

            <label
                class="block text-sm font-medium text-gray-700 mb-1">

                Group *

            </label>

            <select
                name="group_id"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <option value="">
                    -- Select Group --
                </option>

                <?php foreach ($natures as $nature): ?>

                    <?php

                    $natureGroups = array_filter(
                        $groups,
                        function ($g) use ($nature) {
                            return $g['nature'] === $nature;
                        }
                    );

                    ?>

                    <?php if (!empty($natureGroups)): ?>

                        <optgroup
                            label="<?= ucfirst($nature) ?>">

                            <?php foreach ($natureGroups as $g): ?>

                                <option
                                    value="<?= $g['id'] ?>"
                                    <?= old('group_id') == $g['id']
                                        ? 'selected'
                                        : '' ?>>

                                    <?= sanitize($g['group_name']) ?>

                                </option>

                            <?php endforeach; ?>

                        </optgroup>

                    <?php endif; ?>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- Description -->
        <div>

            <label
                class="block text-sm font-medium text-gray-700 mb-1">

                Description

            </label>

            <textarea
                name="description"
                rows="3"
                maxlength="500"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= sanitize(old('description')) ?></textarea>

        </div>


        <!-- Buttons -->
        <div class="pt-4">

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">

                Create Account Type

            </button>


            <a
                href="index.php"
                class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 rounded hover:text-gray-900">

                Cancel

            </a>

        </div>

    </form>

</div>


<!-- ========================================================= -->
<!-- CONFIRMATION MODAL -->
<!-- ========================================================= -->

<div
    id="accountTypeConfirmModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">

    <div
        class="bg-white rounded-lg shadow-xl w-full max-w-md">

        <!-- Modal Header -->
        <div class="px-6 py-4 border-b">

            <div class="flex items-center gap-3">

                <div
                    class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">

                    <i class="fas fa-question text-blue-600"></i>

                </div>

                <div>

                    <h3 class="text-lg font-semibold text-gray-800">

                        Confirm Create

                    </h3>

                    <p class="text-sm text-gray-500">

                        Please confirm your action

                    </p>

                </div>

            </div>

        </div>


        <!-- Modal Body -->
        <div class="px-6 py-5">

            <p class="text-gray-600">

                Are you sure you want to create this account type?

            </p>


            <div
                id="confirmAccountTypeName"
                class="mt-3 px-3 py-2 bg-gray-50 border rounded text-sm font-medium text-gray-800">

            </div>

        </div>


        <!-- Modal Footer -->
        <div
            class="px-6 py-4 bg-gray-50 border-t flex justify-end gap-3">

            <button
                type="button"
                onclick="closeAccountTypeConfirm()"
                class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-100 transition">

                Cancel

            </button>


            <button
                type="button"
                onclick="confirmCreateAccountType()"
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">

                Confirm

            </button>

        </div>

    </div>

</div>


<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- jQuery Validation -->
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>


<script>

$(document).ready(function () {


    /*
     * =========================================================
     * TYPE NAME
     * =========================================================
     *
     * Allow letters and spaces only.
     */

    $('input[name="type_name"]').on('input', function () {

        let value = $(this).val();

        // Remove numbers and special characters
        value = value.replace(/[^A-Za-z ]/g, '');

        // Prevent multiple spaces
        value = value.replace(/\s{2,}/g, ' ');

        // Prevent leading space
        value = value.replace(/^\s+/, '');

        $(this).val(value);

    });


    /*
     * =========================================================
     * DESCRIPTION
     * =========================================================
     */

    $('textarea[name="description"]').on('input', function () {

        let value = $(this).val();

        /*
         * Allow normal ASCII characters,
         * spaces and line breaks.
         */
        value = value.replace(
            /[^\x20-\x7E\r\n]/g,
            ''
        );

        /*
         * Maximum 500 characters
         */
        if (value.length > 500) {

            value = value.substring(0, 500);

        }

        $(this).val(value);

    });


    /*
     * =========================================================
     * VALID GROUP ID
     * =========================================================
     */

    $.validator.addMethod(
        "validGroupId",

        function (value, element) {

            if (value === '') {
                return false;
            }

            return /^\d+$/.test(value)
                && parseInt(value, 10) > 0;

        },

        "Please select a valid group"
    );


    /*
     * =========================================================
     * FORM VALIDATION
     * =========================================================
     */

    $("#createAccountTypeForm").validate({

        errorElement: "span",

        errorClass: "text-red-500 text-sm mt-1 block",

        rules: {

            type_name: {

                required: true,

                minlength: 2,

                maxlength: 100

            },

            group_id: {

                required: true,

                validGroupId: true

            },

            description: {

                maxlength: 500

            }

        },


        messages: {

            type_name: {

                required: "Type name is required",

                minlength:
                    "Type name must be at least 2 characters",

                maxlength:
                    "Type name cannot exceed 100 characters"

            },

            group_id: {

                required:
                    "Please select a group",

                validGroupId:
                    "Please select a valid group"

            },

            description: {

                maxlength:
                    "Description cannot exceed 500 characters"

            }

        },


        /*
         * Error message placement
         */
        errorPlacement: function (error, element) {

            if (element.is("select")) {

                error.insertAfter(element);

            } else {

                error.insertAfter(element);

            }

        },


        /*
         * Highlight invalid fields
         */
        highlight: function (element) {

            $(element).addClass(
                "border-red-500"
            );

        },


        /*
         * Remove error border
         */
        unhighlight: function (element) {

            $(element).removeClass(
                "border-red-500"
            );

        }

    });

});


/*
 * =========================================================
 * CONFIRMATION MODAL
 * =========================================================
 */

let accountTypeForm = null;


/*
 * Open Confirmation Modal
 */
function showAccountTypeConfirm(event) {

    event.preventDefault();

    const form = $("#createAccountTypeForm");


    /*
     * Run jQuery validation first
     */
    if (!form.valid()) {

        return false;

    }


    /*
     * Store form
     */
    accountTypeForm = form[0];


    /*
     * Get Type Name
     */
    const typeName = $('input[name="type_name"]')
        .val()
        .trim();


    /*
     * Show selected type name
     */
    $('#confirmAccountTypeName').text(
        'Account Type: ' + typeName
    );


    /*
     * Open modal
     */
    $('#accountTypeConfirmModal')
        .removeClass('hidden')
        .addClass('flex');


    return false;
}


/*
 * Close Confirmation Modal
 */
function closeAccountTypeConfirm() {

    $('#accountTypeConfirmModal')
        .addClass('hidden')
        .removeClass('flex');

    accountTypeForm = null;
}


/*
 * Confirm Create
 */
function confirmCreateAccountType() {

    if (!accountTypeForm) {
        return;
    }


    /*
     * Remove onsubmit handler
     * to prevent modal opening again.
     */
    accountTypeForm.removeAttribute('onsubmit');


    /*
     * Submit form
     */
    accountTypeForm.submit();

}


/*
 * =========================================================
 * ESC KEY
 * =========================================================
 */

$(document).on('keydown', function (event) {

    if (event.key === 'Escape') {

        if (
            !$('#accountTypeConfirmModal')
                .hasClass('hidden')
        ) {

            closeAccountTypeConfirm();

        }

    }

});


/*
 * =========================================================
 * CLICK OUTSIDE MODAL
 * =========================================================
 */

$('#accountTypeConfirmModal').on(
    'click',
    function (event) {

        if (event.target === this) {

            closeAccountTypeConfirm();

        }

    }
);

</script>


<?php require_once '../../../includes/footer.php'; ?>