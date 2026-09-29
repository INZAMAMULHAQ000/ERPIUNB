<?php
$pageTitle = 'Edit Account Type';
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

$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM account_types WHERE id = ?");
$stmt->execute([$id]);
$accountType = $stmt->fetch();

if (!$accountType) {
    flash('error', 'Account type not found');
    to('modules/finance/account_types/index.php');
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

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/finance/account_types/edit.php?id=' . $id);
    }

    $stmt = $db->prepare("
        UPDATE account_types
        SET
            type_name = ?,
            group_id = ?,
            description = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $type_name,
        (int)$group_id,
        $description ?: null,
        $id
    ]);

    logAudit(
        $_SESSION['user_id'],
        'Updated account type: ' . $type_name,
        'finance'
    );

    flash(
        'success',
        'Account type updated successfully'
    );

    to('modules/finance/account_types/index.php');

    $accountType = array_merge($accountType, $data);
}

$groups = $db->query("
    SELECT id, group_name, nature, parent_group_id, display_order
    FROM account_groups
    WHERE status = 'active'
    ORDER BY nature, display_order
")->fetchAll();

$natures = [
    'asset',
    'liability',
    'equity',
    'income',
    'expense'
];
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Account Types
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">
        Edit Account Type
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
        id="editAccountTypeForm"
        class="space-y-4"
        novalidate>

        <!-- Type Name -->
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Type Name *
            </label>

            <input
                type="text"
                name="type_name"
                value="<?= old(
                            'type_name',
                            $accountType['type_name']
                        ) ?>"
                maxlength="100"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

        </div>

        <!-- Group -->
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-1">
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

                        <optgroup label="<?= ucfirst($nature) ?>">

                            <?php foreach ($natureGroups as $g): ?>

                                <option
                                    value="<?= $g['id'] ?>"
                                    <?= (
                                        old(
                                            'group_id',
                                            $accountType['group_id']
                                        ) == $g['id']
                                    ) ? 'selected' : '' ?>>
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

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Description
            </label>

            <textarea
                name="description"
                rows="3"
                maxlength="500"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old(
                                                                                                'description',
                                                                                                $accountType['description'] ?? ''
                                                                                            ) ?></textarea>

        </div>

        <!-- Buttons -->
        <div class="pt-4">

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Update Account Type
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
         * Type Name
         * Allow letters, numbers, spaces and common characters.
         */
        $('input[name="type_name"]').on('input', function() {

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
         * Description
         * Allow normal printable characters and new lines.
         */
        $('textarea[name="description"]').on('input', function() {

            let value = $(this).val();

            value = value.replace(
                /[^\x20-\x7E\r\n]/g,
                ''
            );

            if (value.length > 500) {
                value = value.substring(0, 500);
            }

            $(this).val(value);
        });


        /*
         * Group validation
         */
        $.validator.addMethod(
            "validGroupId",
            function(value, element) {

                if (this.optional(element)) {
                    return false;
                }

                return /^\d+$/.test(value) &&
                    parseInt(value, 10) > 0;

            },
            "Please select a valid group"
        );


        /*
         * Edit Account Type Form Validation
         */
        $("#editAccountTypeForm").validate({

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
                    minlength: "Type name must be at least 2 characters",
                    maxlength: "Type name cannot exceed 100 characters"
                },

                group_id: {
                    required: "Please select a group",
                    validGroupId: "Please select a valid group"
                },

                description: {
                    maxlength: "Description cannot exceed 500 characters"
                }
            },

            /*
             * Dropdown validation message
             * appears below the dropdown.
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