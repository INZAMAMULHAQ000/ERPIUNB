<?php
$pageTitle = 'Create Expense Category';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';

if (!hasPermission('expense_categories.create')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

/*
|--------------------------------------------------------------------------
| Field Errors
|--------------------------------------------------------------------------
*/
$fieldErrors = [];

/*
|--------------------------------------------------------------------------
| Load Expense Accounts
|--------------------------------------------------------------------------
*/
$expenseAccounts = $db->query("
    SELECT 
        coa.id,
        coa.account_code,
        coa.account_name
    FROM chart_of_accounts coa
    JOIN account_types at 
        ON coa.type_id = at.id
    WHERE at.type_name = 'Expense'
      AND coa.status = 'active'
    ORDER BY coa.account_code
")->fetchAll();

/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid or expired form token.';
    }

    /*
    |--------------------------------------------------------------------------
    | Get Form Values
    |--------------------------------------------------------------------------
    */
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $default_expense_account_id = !empty($_POST['default_expense_account_id'])
        ? (int) $_POST['default_expense_account_id']
        : null;

    $is_active = isset($_POST['is_active']) ? 1 : 0;

    /*
    |--------------------------------------------------------------------------
    | Category Name Validation
    |--------------------------------------------------------------------------
    */

    if ($name === '') {

        $fieldErrors['name'] = 'Category name is required.';
        $errors[] = 'Category name is required.';
    } elseif (mb_strlen($name) < 2) {

        $fieldErrors['name'] = 'Category name must be at least 2 characters.';
        $errors[] = 'Category name must be at least 2 characters.';
    } elseif (mb_strlen($name) > 100) {

        $fieldErrors['name'] = 'Category name cannot exceed 100 characters.';
        $errors[] = 'Category name cannot exceed 100 characters.';
    } else {

        /*
        |--------------------------------------------------------------------------
        | Duplicate Category Name Check
        |--------------------------------------------------------------------------
        | TRIM + case-insensitive comparison
        |
        | Example:
        | Office Supplies
        | office supplies
        | OFFICE SUPPLIES
        |
        | All are treated as duplicate.
        |--------------------------------------------------------------------------
        */

        $duplicateStmt = $db->prepare("
            SELECT id
            FROM expense_categories
            WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
            LIMIT 1
        ");

        $duplicateStmt->execute([$name]);

        $existingCategory = $duplicateStmt->fetch();

        if ($existingCategory) {

            $fieldErrors['name'] = 'This expense category already exists.';
            $errors[] = 'This expense category already exists. Please enter a different category name.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Description Validation
    |--------------------------------------------------------------------------
    */
    if (mb_strlen($description) > 500) {

        $fieldErrors['description'] = 'Description cannot exceed 500 characters.';
        $errors[] = 'Description cannot exceed 500 characters.';
    }

    /*
    |--------------------------------------------------------------------------
    | Validate GL Account
    |--------------------------------------------------------------------------
    */
    if ($default_expense_account_id !== null) {

        $accountStmt = $db->prepare("
            SELECT coa.id
            FROM chart_of_accounts coa
            JOIN account_types at
                ON coa.type_id = at.id
            WHERE coa.id = ?
              AND at.type_name = 'Expense'
              AND coa.status = 'active'
            LIMIT 1
        ");

        $accountStmt->execute([
            $default_expense_account_id
        ]);

        if (!$accountStmt->fetch()) {

            $fieldErrors['default_expense_account_id'] =
                'Please select a valid active expense GL account.';

            $errors[] =
                'Please select a valid active expense GL account.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | If Validation Failed
    |--------------------------------------------------------------------------
    */
    if (!empty($errors)) {

        saveFormState($errors);

        /*
         * Do NOT redirect here.
         * Keep the form on the same page so inline validation
         * messages can be displayed under the fields.
         */
    } else {

        /*
        |--------------------------------------------------------------------------
        | Insert
        |--------------------------------------------------------------------------
        */
        try {

            $stmt = $db->prepare("
                INSERT INTO expense_categories
                (
                    name,
                    description,
                    default_expense_account_id,
                    is_active,
                    created_by
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $name,
                $description !== '' ? $description : null,
                $default_expense_account_id,
                $is_active,
                $_SESSION['user_id']
            ]);

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */
            logAudit(
                $_SESSION['user_id'],
                'Created expense category: ' . $name,
                'expense_categories'
            );

            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */
            flash(
                'success',
                'Expense category "' . sanitize($name) . '" created successfully.',
                'success'
            );

            redirect('index.php');
        } catch (PDOException $e) {

            /*
            |--------------------------------------------------------------------------
            | Duplicate Key Protection
            |--------------------------------------------------------------------------
            */
            if ((int) $e->errorInfo[1] === 1062) {

                $fieldErrors['name'] =
                    'This expense category already exists.';

                $errors[] =
                    'This expense category already exists. Please enter a different category name.';
            } else {

                $errors[] =
                    'Database error: ' . $e->getMessage();
            }
        } catch (Exception $e) {

            $errors[] =
                'Error: ' . $e->getMessage();
        }
    }
}
?>

<div class="mb-6 flex justify-between items-center">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            Create Expense Category
        </h1>

        <p class="text-gray-600">
            Add a new expense category
        </p>
    </div>

    <a
        href="index.php"
        class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">

        <i class="fas fa-arrow-left mr-2"></i>

        Back

    </a>

</div>


<?php if (!empty($errors)): ?>

    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">

        <?php foreach ($errors as $e): ?>

            <p>
                <?= sanitize($e) ?>
            </p>

        <?php endforeach; ?>

    </div>

<?php endif; ?>


<form
    method="POST"
    id="expenseCategoryForm"
    class="max-w-2xl"
    novalidate>

    <input
        type="hidden"
        name="csrf_token"
        value="<?= csrfToken() ?>">


    <div class="bg-white rounded-lg shadow p-6 space-y-6">


        <!-- Category Name -->
        <div>

            <label
                for="name"
                class="block text-sm font-medium text-gray-700 mb-1">

                Category Name

                <span class="text-red-500">*</span>

            </label>

            <input
                type="text"
                name="name"
                id="name"
                value="<?= sanitize($_POST['name'] ?? old('name')) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 <?= !empty($fieldErrors['name']) ? 'border-red-500' : 'border-gray-300' ?>"
                maxlength="100"
                placeholder="e.g. Office Supplies"
                autocomplete="off">

            <?php if (!empty($fieldErrors['name'])): ?>

                <p
                    id="name-error-server"
                    class="text-red-500 text-sm mt-1">

                    <?= sanitize($fieldErrors['name']) ?>

                </p>

            <?php endif; ?>

        </div>


        <!-- Description -->
        <div>

            <label
                for="description"
                class="block text-sm font-medium text-gray-700 mb-1">

                Description

            </label>

            <textarea
                name="description"
                id="description"
                rows="2"
                maxlength="500"
                class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500"
                placeholder="Optional description"><?= sanitize($_POST['description'] ?? old('description')) ?></textarea>

            <?php if (!empty($fieldErrors['description'])): ?>

                <p class="text-red-500 text-sm mt-1">
                    <?= sanitize($fieldErrors['description']) ?>
                </p>

            <?php endif; ?>

        </div>


        <!-- Default Expense GL Account -->
        <div>

            <label
                for="default_expense_account_id"
                class="block text-sm font-medium text-gray-700 mb-1">

                Default Expense GL Account

            </label>

            <select
                name="default_expense_account_id"
                id="default_expense_account_id"
                class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">

                <option value="">
                    -- Select GL Account --
                </option>

                <?php foreach ($expenseAccounts as $a): ?>

                    <?php
                    $selectedAccount =
                        $_POST['default_expense_account_id']
                        ?? old('default_expense_account_id');
                    ?>

                    <option
                        value="<?= (int) $a['id'] ?>"
                        <?= $selectedAccount == $a['id'] ? 'selected' : '' ?>>

                        <?= sanitize(
                            $a['account_code'] . ' - ' . $a['account_name']
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

            <?php if (!empty($fieldErrors['default_expense_account_id'])): ?>

                <p class="text-red-500 text-sm mt-1">
                    <?= sanitize($fieldErrors['default_expense_account_id']) ?>
                </p>

            <?php else: ?>

                <p class="text-xs text-gray-500 mt-1">

                    This GL account will be used as the default debit account
                    when this category is selected in expenses.

                </p>

            <?php endif; ?>

        </div>


        <!-- Active -->
        <div class="flex items-center gap-2">

            <?php
            $activeValue = $_POST['is_active']
                ?? old('is_active', '1');
            ?>

            <input
                type="checkbox"
                name="is_active"
                id="is_active"
                value="1"
                <?= $activeValue ? 'checked' : '' ?>
                class="rounded border-gray-300">

            <label
                for="is_active"
                class="text-sm font-medium text-gray-700">

                Active

            </label>

        </div>

    </div>


    <!-- Buttons -->
    <div class="flex justify-end gap-4 mt-6">

        <a
            href="index.php"
            class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400 transition">

            Cancel

        </a>

        <button
            type="submit"
            class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">

            <i class="fas fa-save mr-2"></i>

            Create Category

        </button>

    </div>

</form>


<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>


<script>
    $(document).ready(function() {

        $("#expenseCategoryForm").validate({

            rules: {

                name: {
                    required: true,
                    minlength: 2,
                    maxlength: 100
                },

                description: {
                    maxlength: 500
                }

            },

            messages: {

                name: {
                    required: "Category name is required.",
                    minlength: "Category name must be at least 2 characters.",
                    maxlength: "Category name cannot exceed 100 characters."
                },

                description: {
                    maxlength: "Description cannot exceed 500 characters."
                }

            },

            errorElement: "p",

            errorClass: "text-red-500 text-sm mt-1",

            /*
            |--------------------------------------------------------------------------
            | Error Placement
            |--------------------------------------------------------------------------
            */
            errorPlacement: function(error, element) {

                error.insertAfter(element);

            },

            /*
            |--------------------------------------------------------------------------
            | Highlight
            |--------------------------------------------------------------------------
            */
            highlight: function(element) {

                $(element)
                    .addClass("border-red-500")
                    .removeClass("border-gray-300");

            },

            /*
            |--------------------------------------------------------------------------
            | Unhighlight
            |--------------------------------------------------------------------------
            */
            unhighlight: function(element) {

                $(element)
                    .removeClass("border-red-500")
                    .addClass("border-gray-300");

            },

            /*
            |--------------------------------------------------------------------------
            | When User Starts Typing
            |--------------------------------------------------------------------------
            */
            onkeyup: function(element) {

                $(element).valid();

            },

            /*
            |--------------------------------------------------------------------------
            | Remove Server Duplicate Error When User Changes Name
            |--------------------------------------------------------------------------
            */
            onfocusout: function(element) {

                if ($(element).attr("name") === "name") {

                    $("#name-error-server").remove();

                }

                $(element).valid();

            }

        });

    });
</script>


<?php require_once '../../../includes/footer.php'; ?>