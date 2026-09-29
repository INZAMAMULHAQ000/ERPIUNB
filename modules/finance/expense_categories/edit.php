<?php
$pageTitle = 'Edit Expense Category';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';

if (!hasPermission('expense_categories.edit')) {
    die('Access Denied');
}

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Invalid category ID.');
    redirect('index.php');
}

$stmt = $db->prepare("SELECT * FROM expense_categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    flash('error', 'Category not found.');
    redirect('index.php');
}

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$expenseAccounts = $db->query("
    SELECT coa.id, coa.account_code, coa.account_name
    FROM chart_of_accounts coa
    JOIN account_types at ON coa.type_id = at.id
    WHERE at.type_name = 'Expense' AND coa.status = 'active'
    ORDER BY coa.account_code
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid or expired form token.';
    }

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $default_expense_account_id = !empty($_POST['default_expense_account_id'])
        ? (int)$_POST['default_expense_account_id']
        : null;

    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name)) {
        $errors[] = 'Category name is required.';
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/finance/expense_categories/edit.php?id=' . $id);
    }

    try {

        $stmt = $db->prepare("
            UPDATE expense_categories 
            SET 
                name = ?, 
                description = ?, 
                default_expense_account_id = ?, 
                is_active = ? 
            WHERE id = ?
        ");

        $stmt->execute([
            $name,
            $description ?: null,
            $default_expense_account_id,
            $is_active,
            $id
        ]);

        logAudit(
            $_SESSION['user_id'],
            'Updated expense category: ' . $name,
            'expense_categories'
        );

        flash(
            'success',
            'Expense category updated successfully.',
            'success'
        );

        redirect('index.php');
    } catch (Exception $e) {

        flash('error', 'Error: ' . $e->getMessage());

        to('modules/finance/expense_categories/edit.php?id=' . $id);
    }
}
?>

<div class="mb-6 flex justify-between items-center">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            Edit Expense Category
        </h1>

        <p class="text-gray-600">
            Update category details
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

            <p><?= sanitize($e) ?></p>

        <?php endforeach; ?>

    </div>

<?php endif; ?>


<form
    method="POST"
    id="expenseCategoryForm"
    class="max-w-2xl">

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
                value="<?= sanitize(old('name', $category['name'])) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                maxlength="100"
                required>

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
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= sanitize(old('description', $category['description'] ?? '')) ?></textarea>

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
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <option value="">
                    -- Select GL Account --
                </option>

                <?php foreach ($expenseAccounts as $a): ?>

                    <option
                        value="<?= $a['id'] ?>"
                        <?= (
                            old(
                                'default_expense_account_id',
                                $category['default_expense_account_id']
                            ) == $a['id']
                        ) ? 'selected' : '' ?>>
                        <?= sanitize(
                            $a['account_code'] . ' - ' . $a['account_name']
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- Active -->
        <div class="flex items-center gap-2">

            <input
                type="checkbox"
                name="is_active"
                id="is_active"
                value="1"
                <?= old('is_active', $category['is_active']) ? 'checked' : '' ?>
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
            Update Category
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

            highlight: function(element) {

                $(element)
                    .addClass("border-red-500")
                    .removeClass("border-gray-300");

            },

            unhighlight: function(element) {

                $(element)
                    .removeClass("border-red-500")
                    .addClass("border-gray-300");

            },

            errorPlacement: function(error, element) {

                error.insertAfter(element);

            }

        });

    });
</script>


<?php require_once '../../../includes/footer.php'; ?>