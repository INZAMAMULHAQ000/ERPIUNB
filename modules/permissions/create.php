<?php
$pageTitle = 'Create Permission';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('permissions.create')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => $_POST['name'] ?? '',
        'module' => $_POST['module'] ?? ''
    ];

    $errors = validate($data, [
        'name' => 'required|min:2|max:100',
        'module' => 'required'
    ]);

    if (empty($errors)) {
        $checkStmt = $db->prepare("SELECT id FROM permissions WHERE name = ? AND module = ? AND deleted_at IS NULL");
        $checkStmt->execute([$data['name'], $data['module']]);
        if ($checkStmt->fetch()) {
            $errors['name'] = 'Permission already exists in this module';
        }
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/permissions/create.php');
    }


    $stmt = $db->prepare("INSERT INTO permissions (name, module) VALUES (?, ?)");
    $stmt->execute([$data['name'], $data['module']]);

    logAudit($_SESSION['user_id'], 'create', 'permissions');
    flash('success', 'Permission created successfully');
    to('modules/permissions/index.php');
}

$modules = ['dashboard', 'users', 'roles', 'permissions', 'audit', 'settings'];
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Permissions
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Create New Permission</h2>

    <?php if ($errors): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?= sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="permissionForm" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Permission Name <span class="text-red-600">*</span></labe>
            <input type="text" id="permission_name" name="name" value="<?= old('name') ?>" placeholder="e.g., view, create, edit, delete" required
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Module <span class="text-red-600">*</span></labe>
            <select name="module" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">Select Module</option>
                <?php foreach ($modules as $m): ?>
                    <option value="<?= $m ?>" <?= old('module') === $m ? 'selected' : '' ?>><?= ucfirst($m) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="pt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Create Permission
            </button>
            <a href="index.php"
                class="ml-4 px-6 py-2 text-gray-600 border border-gray-200 rounded hover:bg-gray-100 hover:text-gray-900 hover:border-gray-400 transition">
                Cancel
            </a>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>
<script>
    $(document).ready(function() {

        // Permission Name - letters only
        $("#permission_name").on("input", function() {
            this.value = this.value.replace(/[^A-Za-z]/g, '');
        });

        $("#permissionForm").validate({

            rules: {

                name: {
                    required: true,
                    minlength: 2,
                    maxlength: 100,
                    pattern: /^[A-Za-z]+$/
                },

                module: {
                    required: true
                }

            },

            messages: {

                name: {
                    required: "Permission Name is required",
                    minlength: "Permission Name must be at least 2 characters",
                    maxlength: "Permission Name must not exceed 100 characters",
                    pattern: "Permission Name can contain letters only"
                },

                module: {
                    required: "Please select a module"
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