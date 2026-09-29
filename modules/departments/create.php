<?php
$pageTitle = 'Create Department';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('departments.create')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'department_name' => $_POST['department_name'] ?? '',
        'department_code' => strtoupper($_POST['department_code'] ?? ''),
        'description' => $_POST['description'] ?? '',
        'status' => $_POST['status'] ?? 'active'
    ];

    $errors = validate($data, [
        'department_name' => 'required|min:2|max:100',
        'department_code' => 'required|min:2|max:20'
    ]);

    if (empty($errors)) {
        $checkStmt = $db->prepare("SELECT id FROM departments WHERE department_code = ?");
        $checkStmt->execute([$data['department_code']]);
        if ($checkStmt->fetch()) {
            $errors['department_code'] = 'Department code already exists';
        }
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/departments/create.php');
    }


    $stmt = $db->prepare("INSERT INTO departments (department_name, department_code, description, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$data['department_name'], $data['department_code'], $data['description'], $data['status']]);

    logAudit($_SESSION['user_id'], 'create', 'departments');
    flash('success', 'Department created successfully');
    to('modules/departments/index.php');
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Departments
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Create New Department</h2>

    <?php if ($errors): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?= sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="departmentForm" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Department Name <span class="text-red-600">*</span></labe>
                <input type="text" id="department_name" name="department_name" value="<?= old('department_name') ?>" required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Department Code <span class="text-red-600">*</span></labe>
                <input type="text" id="department_code" name="department_code" value="<?= old('department_code') ?>" required placeholder="e.g., HR, IT, FIN"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 uppercase">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" id="description" rows="3" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('description') ?></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="active" <?= old('status') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= old('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <div class="pt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Create Department
            </button>
            <a href="index.php" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>
<script>
    $(document).ready(function() {

        // Department Name
        $("#department_name").on("input", function() {
            this.value = this.value.replace(/[^A-Za-z ]/g, "");
        });

        // Department Code
        $("#department_code").on("input", function() {
            this.value = this.value
                .replace(/[^A-Za-z0-9]/g, "")
                .toUpperCase();
        });

        // Department Form Validation
        $("#departmentForm").validate({

            rules: {
                department_name: {
                    required: true,
                    minlength: 2,
                    maxlength: 100,
                    pattern: /^[A-Za-z]+(?: [A-Za-z]+)*$/
                },

                department_code: {
                    required: true,
                    minlength: 2,
                    maxlength: 20,
                    pattern: /^[A-Z0-9]+$/
                },

                description: {
                    maxlength: 500
                },

                status: {
                    required: true
                }
            },

            messages: {
                department_name: {
                    required: "Department Name is required",
                    minlength: "Department Name must be at least 2 characters",
                    maxlength: "Department Name must not exceed 100 characters",
                    pattern: "Department Name can contain letters and spaces only"
                },

                department_code: {
                    required: "Department Code is required",
                    minlength: "Department Code must be at least 2 characters",
                    maxlength: "Department Code must not exceed 20 characters",
                    pattern: "Department Code can contain uppercase letters and numbers only"
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