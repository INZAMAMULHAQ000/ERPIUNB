<?php
$pageTitle = 'Edit Account Group';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';

if (!hasPermission('finance.view')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM account_groups WHERE id = ?");
$stmt->execute([$id]);
$group = $stmt->fetch();

if (!$group) {
    flash('error', 'Account group not found');
    to('modules/finance/account_groups/index.php');
}

$old = $_POST;

// Fetch all groups and compute descendant IDs for circular reference prevention
$allGroups = $db->query(
    "SELECT * FROM account_groups ORDER BY display_order ASC"
)->fetchAll();

function getDescendantIds($allGroups, $groupId)
{
    $ids = [$groupId];

    foreach ($allGroups as $g) {
        if ($g['parent_group_id'] == $groupId) {
            $ids = array_merge(
                $ids,
                getDescendantIds($allGroups, $g['id'])
            );
        }
    }

    return $ids;
}

$excludeIds = getDescendantIds($allGroups, $id);

function buildGroupOptions(
    $allGroups,
    $excludeIds = [],
    $parentId = 0,
    $depth = 0,
    $selectedParentId = null
) {
    $html = '';

    foreach ($allGroups as $g) {

        if (in_array($g['id'], $excludeIds)) {
            continue;
        }

        if (($g['parent_group_id'] ?? 0) == $parentId) {

            $prefix = str_repeat('&nbsp;&nbsp;', $depth * 2);

            $selected = (
                $selectedParentId !== null &&
                $selectedParentId == $g['id']
            )
                ? 'selected'
                : '';

            $html .= '<option value="' . $g['id'] . '" ' . $selected . '>'
                . $prefix
                . sanitize($g['group_name'])
                . '</option>';

            $html .= buildGroupOptions(
                $allGroups,
                $excludeIds,
                $g['id'],
                $depth + 1,
                $selectedParentId
            );
        }
    }

    return $html;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = [
        'group_name'      => trim($_POST['group_name'] ?? ''),
        'parent_group_id' => ($_POST['parent_group_id'] ?? '') !== ''
            ? (int)$_POST['parent_group_id']
            : null,
        'nature'          => $_POST['nature'] ?? '',
        'display_order'   => (int)($_POST['display_order'] ?? 0),
        'status'          => $_POST['status'] ?? 'active',
    ];

    $errors = validate($data, [
        'group_name' => 'required|min:2|max:100',
        'nature'     => 'required',
    ]);

    // Validate nature enum value
    if (empty($errors['nature'])) {

        $validNatures = [
            'asset',
            'liability',
            'equity',
            'income',
            'expense'
        ];

        if (!in_array($data['nature'], $validNatures)) {
            $errors['nature'] = 'Invalid nature type selected';
        }
    }

    // Prevent circular parent reference on the server side
    if (
        empty($errors) &&
        $data['parent_group_id'] !== null
    ) {

        if (in_array($data['parent_group_id'], $excludeIds)) {

            $errors['parent_group_id'] =
                'Cannot set this group or one of its descendants as parent (circular reference)';
        }
    }

    if (!empty($errors)) {

        saveFormState($errors);

        to(
            'modules/finance/account_groups/edit.php?id=' . $id
        );
    }

    $stmt = $db->prepare("
        UPDATE account_groups
        SET
            group_name = ?,
            parent_group_id = ?,
            nature = ?,
            display_order = ?,
            status = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $data['group_name'],
        $data['parent_group_id'],
        $data['nature'],
        $data['display_order'],
        $data['status'],
        $id
    ]);

    logAudit(
        $_SESSION['user_id'],
        'update',
        'account_groups'
    );

    flash(
        'success',
        'Account group updated successfully'
    );

    to('modules/finance/account_groups/index.php');

    $group = array_merge($group, $data);
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Account Groups
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">
        Edit Account Group
    </h2>

    <?php if ($errors): ?>

        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">

            <ul class="list-disc list-inside">

                <?php foreach ($errors as $error): ?>

                    <li><?= sanitize($error) ?></li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>

    <form
        method="POST"
        id="editAccountGroupForm"
        class="space-y-4"
        novalidate>

        <!-- Group Name + Nature -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Group Name -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Group Name <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="group_name"
                    value="<?= old('group_name', $group['group_name']) ?>"
                    maxlength="100"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

            <!-- Nature -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Nature <span class="text-red-600">*</span>
                </label>

                <select
                    name="nature"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        Select Nature
                    </option>

                    <option
                        value="asset"
                        <?= ($group['nature'] ?? '') === 'asset' ? 'selected' : '' ?>>
                        Asset
                    </option>

                    <option
                        value="liability"
                        <?= ($group['nature'] ?? '') === 'liability' ? 'selected' : '' ?>>
                        Liability
                    </option>

                    <option
                        value="equity"
                        <?= ($group['nature'] ?? '') === 'equity' ? 'selected' : '' ?>>
                        Equity
                    </option>

                    <option
                        value="income"
                        <?= ($group['nature'] ?? '') === 'income' ? 'selected' : '' ?>>
                        Income
                    </option>

                    <option
                        value="expense"
                        <?= ($group['nature'] ?? '') === 'expense' ? 'selected' : '' ?>>
                        Expense
                    </option>

                </select>

            </div>

        </div>

        <!-- Parent Group + Display Order -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Parent Group -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Parent Group
                </label>

                <select
                    name="parent_group_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        None (Root Group)
                    </option>

                    <?php

                    $selectedParentId = old(
                        'parent_group_id',
                        $group['parent_group_id'] ?? ''
                    );

                    echo buildGroupOptions(
                        $allGroups,
                        $excludeIds,
                        0,
                        0,
                        $selectedParentId
                    );

                    ?>

                </select>

                <p class="text-xs text-gray-500 mt-1">
                    Leave empty for a top-level group.
                    Current group and descendants are excluded.
                </p>

            </div>

            <!-- Display Order -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Display Order
                </label>

                <input
                    type="number"
                    name="display_order"
                    value="<?= old(
                                'display_order',
                                $group['display_order'] ?? '0'
                            ) ?>"
                    min="0"
                    step="1"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <p class="text-xs text-gray-500 mt-1">
                    Lower numbers appear first
                </p>

            </div>

        </div>

        <!-- Status -->
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Status
            </label>

            <select
                name="status"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <option
                    value="active"
                    <?= ($group['status'] ?? 'active') === 'active'
                        ? 'selected'
                        : '' ?>>
                    Active
                </option>

                <option
                    value="inactive"
                    <?= ($group['status'] ?? 'active') === 'inactive'
                        ? 'selected'
                        : '' ?>>
                    Inactive
                </option>

            </select>

        </div>

        <!-- Buttons -->
        <div class="pt-4">

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Update Account Group
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
         * Group Name
         * Allow letters, numbers, spaces and common characters.
         */
        $('input[name="group_name"]').on('input', function() {

            let value = $(this).val();

            value = value.replace(
                /[^A-Za-z0-9 .,&()_/@#+'-]/g,
                ''
            );

            // Prevent multiple spaces
            value = value.replace(/\s{2,}/g, ' ');

            $(this).val(value);
        });


        /*
         * Display Order
         * Numbers only.
         */
        $('input[name="display_order"]').on('input', function() {

            let value = $(this).val();

            value = value.replace(/[^0-9]/g, '');

            $(this).val(value);
        });


        /*
         * Custom Nature Validation
         */
        $.validator.addMethod(
            "validNature",
            function(value, element) {

                return this.optional(element) || [
                    "asset",
                    "liability",
                    "equity",
                    "income",
                    "expense"
                ].includes(value);

            },
            "Please select a valid nature"
        );


        /*
         * Custom Display Order Validation
         */
        $.validator.addMethod(
            "validDisplayOrder",
            function(value, element) {

                if (this.optional(element)) {
                    return true;
                }

                return /^\d+$/.test(value);

            },
            "Display order must be a valid number"
        );


        /*
         * Account Group Edit Form Validation
         */
        $("#editAccountGroupForm").validate({

            errorElement: "span",

            errorClass: "text-red-500 text-sm mt-1 block",

            rules: {

                group_name: {
                    required: true,
                    minlength: 2,
                    maxlength: 100
                },

                nature: {
                    required: true,
                    validNature: true
                },

                parent_group_id: {
                    digits: true
                },

                display_order: {
                    digits: true,
                    min: 0,
                    validDisplayOrder: true
                },

                status: {
                    required: true
                }
            },

            messages: {

                group_name: {
                    required: "Group name is required",
                    minlength: "Group name must be at least 2 characters",
                    maxlength: "Group name cannot exceed 100 characters"
                },

                nature: {
                    required: "Please select a nature",
                    validNature: "Please select a valid nature"
                },

                parent_group_id: {
                    digits: "Please select a valid parent group"
                },

                display_order: {
                    digits: "Display order must contain numbers only",
                    min: "Display order cannot be negative",
                    validDisplayOrder: "Please enter a valid display order"
                },

                status: {
                    required: "Please select a status"
                }
            },

            /*
             * Dropdown error message appears below dropdown
             */
            errorPlacement: function(error, element) {

                if (element.is("select")) {

                    element.parent().append(error);

                } else {

                    error.insertAfter(element);

                }
            },

            highlight: function(element) {

                $(element).addClass("border-red-500");

            },

            unhighlight: function(element) {

                $(element).removeClass("border-red-500");

            }

        });

    });
</script>

<?php require_once '../../../includes/footer.php'; ?>