<?php
$pageTitle = 'Create Designation';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('designations.create')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

$departments = $db->query("SELECT * FROM departments WHERE status = 'active' ORDER BY department_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'designation_name' => $_POST['designation_name'] ?? '',
        'department_id' => $_POST['department_id'] ?? '',
        'description' => $_POST['description'] ?? '',
        'status' => $_POST['status'] ?? 'active'
    ];

    $errors = validate($data, [
        'designation_name' => 'required|min:2|max:100',
        'department_id' => 'required'
    ]);

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/designations/create.php');
    }

    $stmt = $db->prepare("INSERT INTO designations (designation_name, department_id, description, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $data['designation_name'],
        $data['department_id'],
        $data['description'],
        $data['status']
    ]);

    logAudit($_SESSION['user_id'], 'create', 'designations');
    flash('success', 'Designation created successfully');
    to('modules/designations/index.php');
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Designations
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Create New Designation</h2>

    <?php if ($errors): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?= sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="designationForm" class="space-y-4" novalidate>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Designation Name <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    id="designation_name"
                    name="designation_name"
                    value="<?= old('designation_name') ?>"
                    required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Department <span class="text-red-600">*</span>
                </label>

                <select
                    id="department_id"
                    name="department_id"
                    required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Department</option>

                    <?php foreach ($departments as $dept): ?>
                        <option
                            value="<?= $dept['id'] ?>"
                            <?= old('department_id') == $dept['id'] ? 'selected' : '' ?>>
                            <?= sanitize($dept['department_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Description
            </label>

            <textarea
                id="description"
                name="description"
                rows="3"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('description') ?></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Status
            </label>

            <select
                id="status"
                name="status"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="active" <?= old('status', 'active') === 'active' ? 'selected' : '' ?>>
                    Active
                </option>

                <option value="inactive" <?= old('status') === 'inactive' ? 'selected' : '' ?>>
                    Inactive
                </option>
            </select>
        </div>

        <div class="pt-4">
            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Create Designation
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

        // Custom validation method
        $.validator.addMethod(
            "lettersOnly",
            function(value, element) {
                return this.optional(element) ||
                    /^[A-Za-z]+(?: [A-Za-z]+)*$/.test(value);
            },
            "Only letters and spaces are allowed"
        );

        // Designation Name - letters and spaces only
        $("#designation_name").on("input", function() {
            this.value = this.value.replace(/[^A-Za-z ]/g, "");
        });

        // Description - maximum 500 characters
        $("#description").on("input", function() {
            this.value = this.value.slice(0, 500);
        });

        // Designation Form Validation
        $("#designationForm").validate({

            rules: {
                designation_name: {
                    required: true,
                    minlength: 2,
                    maxlength: 100,
                    lettersOnly: true
                },

                department_id: {
                    required: true
                },

                description: {
                    maxlength: 500
                },

                status: {
                    required: true
                }
            },

            messages: {
                designation_name: {
                    required: "Designation Name is required",
                    minlength: "Designation Name must be at least 2 characters",
                    maxlength: "Designation Name must not exceed 100 characters",
                    lettersOnly: "Designation Name can contain letters and spaces only"
                },

                department_id: {
                    required: "Please select a department"
                },

                description: {
                    maxlength: "Description must not exceed 500 characters"
                },

                status: {
                    required: "Please select status"
                }
            },

            errorElement: "span",

            errorClass: "text-red-500 text-sm mt-1 block",

            errorPlacement: function(error, element) {

                if (element.is("select")) {
                    error.appendTo(element.closest("div"));
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

<?php require_once '../../includes/footer.php'; ?>