<?php
$pageTitle = 'Create Ticket Category';

require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('ticket_categories.create')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

function generateCategoryCode($db)
{
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM ticket_categories");
    $count = $stmt->fetch()['cnt'] + 1;

    return "CAT" . str_pad($count, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $data = [
        'category_name'   => trim($_POST['category_name'] ?? ''),
        'category_code'   => trim($_POST['category_code'] ?? generateCategoryCode($db)),
        'description'     => trim($_POST['description'] ?? ''),
        'priority_default' => $_POST['priority_default'] ?? 'medium',
        'status'          => $_POST['status'] ?? 'active'
    ];

    $errors = validate($data, [
        'category_name' => 'required|min:2|max:100',
        'category_code' => 'required|min:2|max:50'
    ]);

    if (empty($errors)) {

        $checkStmt = $db->prepare(
            "SELECT id FROM ticket_categories WHERE category_name = ?"
        );

        $checkStmt->execute([$data['category_name']]);

        if ($checkStmt->fetch()) {
            $errors['category_name'] = 'Category name already exists';
        }
    }

    if (empty($errors)) {

        $checkCodeStmt = $db->prepare(
            "SELECT id FROM ticket_categories WHERE category_code = ?"
        );

        $checkCodeStmt->execute([$data['category_code']]);

        if ($checkCodeStmt->fetch()) {
            $errors['category_code'] = 'Category code already exists';
        }
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/ticket_categories/create.php');
    }

    $stmt = $db->prepare("
        INSERT INTO ticket_categories
        (
            category_name,
            category_code,
            description,
            priority_default,
            status
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data['category_name'],
        $data['category_code'],
        $data['description'],
        $data['priority_default'],
        $data['status']
    ]);

    logAudit(
        $_SESSION['user_id'],
        'create_ticket_category',
        'ticket_categories'
    );

    flash('success', 'Ticket category created successfully');

    to('http://localhost/ERP/modules/ticket_categories/index.php');
}
?>

<div class="mb-6">
    <a
        href="<?= url('modules/ticket_categories/index.php') ?>"
        class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Categories
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">
        Create New Ticket Category
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


    <form method="POST" id="ticketCategoryForm" class="space-y-4">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= csrfToken() ?>">


        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Category Name -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Category Name
                    <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="category_name"
                    value="<?= old('category_name') ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


            <!-- Category Code -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Category Code
                    <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="category_code"
                    value="<?= old('category_code', generateCategoryCode($db)) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


            <!-- Default Priority -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Default Priority
                </label>

                <select
                    name="priority_default"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option
                        value="low"
                        <?= old('priority_default') === 'low' ? 'selected' : '' ?>>
                        Low
                    </option>

                    <option
                        value="medium"
                        <?= old('priority_default', 'medium') === 'medium' ? 'selected' : '' ?>>
                        Medium
                    </option>

                    <option
                        value="high"
                        <?= old('priority_default') === 'high' ? 'selected' : '' ?>>
                        High
                    </option>

                    <option
                        value="critical"
                        <?= old('priority_default') === 'critical' ? 'selected' : '' ?>>
                        Critical
                    </option>

                </select>

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


        <!-- Description -->
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Description
            </label>

            <textarea
                name="description"
                rows="3"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('description') ?></textarea>

        </div>


        <!-- Buttons -->
        <div class="pt-4">

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Create Category
            </button>

            <a
                href="<?= url('modules/ticket_categories/index.php') ?>"
                class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">
                Cancel
            </a>

        </div>

    </form>


</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function() {

        $("#ticketCategoryForm").validate({

            rules: {

                category_name: {
                    required: true,
                    minlength: 2,
                    maxlength: 100
                },

                category_code: {
                    required: true,
                    minlength: 2,
                    maxlength: 50
                }

            },

            messages: {

                category_name: {
                    required: "Category name is required",
                    minlength: "Category name must be at least 2 characters",
                    maxlength: "Category name cannot exceed 100 characters"
                },

                category_code: {
                    required: "Category code is required",
                    minlength: "Category code must be at least 2 characters",
                    maxlength: "Category code cannot exceed 50 characters"
                }

            },

            errorElement: "p",

            errorClass: "text-red-600 text-sm mt-1",

            highlight: function(element) {
                $(element).addClass("border-red-500");
            },

            unhighlight: function(element) {
                $(element).removeClass("border-red-500");
            },

            errorPlacement: function(error, element) {
                error.insertAfter(element);
            }

        });

    });
</script>

<?php require_once '../../includes/footer.php'; ?>