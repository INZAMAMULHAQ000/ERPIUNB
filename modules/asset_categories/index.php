<?php
$pageTitle = 'Asset Categories';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('assets.view')) {
    die('Access Denied');
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('assets.create')) {
    $category_name = $_POST['category_name'] ?? '';
    $category_code = $_POST['category_code'] ?? '';
    $description = $_POST['description'] ?? '';

    if ($category_name) {
        try {
            $stmt = $db->prepare("
                INSERT INTO asset_categories
                (category_name, category_code, description)
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $category_name,
                $category_code,
                $description
            ]);

            flash('success', 'Category added successfully');
        } catch (Exception $e) {
            flash('error', 'Category already exists or invalid');
        }

        to('http://localhost/ERP/modules/asset_categories/index.php');
    }
}

if (isset($_GET['toggle']) && hasPermission('assets.edit')) {

    $id = $_GET['toggle'];

    $cat = $db->prepare("
        SELECT status
        FROM asset_categories
        WHERE id = ?
    ");

    $cat->execute([$id]);
    $cat = $cat->fetch();

    if ($cat) {

        $newStatus = $cat['status'] == 'active'
            ? 'inactive'
            : 'active';

        $db->prepare("
            UPDATE asset_categories
            SET status = ?
            WHERE id = ?
        ")->execute([
            $newStatus,
            $id
        ]);

        to('http://localhost/ERP/modules/asset_categories/index.php');
    }
}

$categories = $db->query("
    SELECT *
    FROM asset_categories
    ORDER BY category_name
")->fetchAll();
?>

<style>
    /* Validation message */
    #categoryForm p.error {
        display: block !important;
        width: 100% !important;
        margin: 4px 0 0 0 !important;
        padding: 0 !important;
        clear: both !important;
        color: #dc2626 !important;
        font-size: 12px !important;
        line-height: 16px !important;
        position: static !important;
    }

    /* Invalid fields */
    #categoryForm input.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    /* Valid fields */
    #categoryForm input.valid {
        border-color: #22c55e !important;
    }
</style>

<div class="mb-6 flex justify-between items-center">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            Asset Categories
        </h1>

        <p class="text-gray-600">
            Manage asset categories
        </p>
    </div>

</div>

<?php if (hasPermission('assets.create')): ?>

    <div class="bg-white rounded-lg shadow mb-6 p-6">

        <h2 class="text-lg font-semibold mb-4">
            Add New Category
        </h2>

        <form
            method="POST"
            class="flex flex-wrap gap-4 items-end"
            id="categoryForm"
            novalidate>

            <!-- Category Name -->
            <div class="flex-1 min-w-[200px]">

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Category Name
                    <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="category_name"
                    maxlength="255"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

            <!-- Category Code -->
            <div class="w-32">

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Code
                </label>

                <input
                    type="text"
                    name="category_code"
                    maxlength="20"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

            <!-- Description -->
            <div class="flex-1 min-w-[200px]">

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Description
                </label>

                <input
                    type="text"
                    name="description"
                    maxlength="500"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

            <!-- Add Button -->
            <div>

                <button
                    type="submit"
                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Add
                </button>

            </div>

        </form>

    </div>

<?php endif; ?>


<div class="bg-white rounded-lg shadow overflow-hidden">

    <table class="w-full">

        <thead class="bg-gray-50">

            <tr>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Code
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Category Name
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Description
                </th>

                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Status
                </th>

                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                    Actions
                </th>

            </tr>

        </thead>

        <tbody class="divide-y divide-gray-200">

            <?php foreach ($categories as $cat): ?>

                <tr class="hover:bg-gray-50">

                    <td class="px-6 py-4 font-medium text-gray-900">
                        <?= sanitize($cat['category_code']) ?>
                    </td>

                    <td class="px-6 py-4 text-gray-600">
                        <?= sanitize($cat['category_name']) ?>
                    </td>

                    <td class="px-6 py-4 text-gray-600">
                        <?= sanitize($cat['description'] ?? '-') ?>
                    </td>

                    <td class="px-6 py-4">

                        <span class="px-2 py-1 text-xs rounded
                        <?= $cat['status'] == 'active'
                            ? 'bg-green-100 text-green-800'
                            : 'bg-red-100 text-red-800'
                        ?>">

                            <?= ucfirst($cat['status']) ?>

                        </span>

                    </td>

                    <td class="px-6 py-4 text-right">

                        <?php if (hasPermission('assets.edit')): ?>

                            <a
                                href="<?= url('modules/asset_categories/index.php?toggle=' . $cat['id']) ?>"
                                class="text-gray-600 hover:text-gray-900 mr-2"
                                title="<?= $cat['status'] == 'active' ? 'Deactivate' : 'Activate' ?>">

                                <i class="fas fa-toggle-<?= $cat['status'] == 'active' ? 'on' : 'off' ?>"></i>

                            </a>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>

        </tbody>

    </table>

</div>


<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- jQuery Validation -->
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function() {

        $("#categoryForm").validate({

            rules: {

                category_name: {
                    required: true,
                    minlength: 2,
                    maxlength: 255
                },

                category_code: {
                    maxlength: 20
                },

                description: {
                    maxlength: 500
                }

            },

            messages: {

                category_name: {
                    required: "Please enter category name.",
                    minlength: "Category name must be at least 2 characters.",
                    maxlength: "Category name cannot exceed 255 characters."
                },

                category_code: {
                    maxlength: "Category code cannot exceed 20 characters."
                },

                description: {
                    maxlength: "Description cannot exceed 500 characters."
                }

            },

            errorPlacement: function(error, element) {

                error.insertAfter(element);

            },

            errorElement: "p",

            errorClass: "error",

            highlight: function(element) {

                $(element)
                    .addClass("error")
                    .removeClass("valid");

            },

            unhighlight: function(element) {

                $(element)
                    .removeClass("error")
                    .addClass("valid");

            },

            submitHandler: function(form) {

                form.submit();

            }

        });

    });
</script>


<?php require_once '../../includes/footer.php'; ?>