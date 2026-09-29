<?php
$pageTitle = 'Create User';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('users.create')) {
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
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? '',
        'status' => $_POST['status'] ?? 'active'
    ];

    $errors = validate($data, [
        'name' => 'required|min:2|max:100',
        'email' => 'required|email',
        'password' => 'required|min:6'
    ]);

    if (empty($errors)) {
        $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL");
        $checkStmt->execute([$data['email']]);
        if ($checkStmt->fetch()) {
            $errors['email'] = 'Email already exists';
        }
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/users/create.php');
    }

    $stmt = $db->prepare("INSERT INTO users (name, email, password, status, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['name'],
        $data['email'],
        password_hash($data['password'], PASSWORD_DEFAULT),
        $data['status'],
        $_SESSION['user_id']
    ]);

    $userId = $db->lastInsertId();

    if (!empty($_POST['roles'])) {
        $roleStmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        foreach ($_POST['roles'] as $roleId) {
            $roleStmt->execute([$userId, $roleId]);
        }
    }

    logAudit($_SESSION['user_id'], 'create', 'users');
    flash('success', 'User created successfully');
    to('modules/users/index.php');
}

$roles = $db->query("SELECT * FROM roles WHERE deleted_at IS NULL")->fetchAll();
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Users
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Create New User</h2>

    <?php if ($errors): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?= sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="userForm" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-600">*</span></labe>
            <input type="text" id="name" name="name" value="<?= old('name') ?>" required
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-600">*</span></labe>
            <input type="email" id="email" name="email" value="<?= old('email') ?>" required
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-600">*</span></labe>
            <input type="password" id="password" name="password" required minlength="6"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="active" <?= old('status') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= old('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="suspended" <?= old('status') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Assign Roles</label>
            <div class="space-y-2">
                <?php foreach ($roles as $role): ?>
                    <label class="inline-flex items-center mr-4">
                        <input type="checkbox" name="roles[]" value="<?= $role['id'] ?>" class="rounded text-blue-600">
                        <span class="ml-2"><?= sanitize($role['name']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Create User
            </button>
            <a href="index.php"
                class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 rounded hover:text-gray-900 hover:border-gray-400">
                Cancel
            </a>
        </div>
    </form>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function() {

        // Name: letters and spaces only
        $("#name").on("input", function() {
            this.value = this.value.replace(/[^A-Za-z ]/g, '');
        });

        // Email: remove spaces and invalid characters while typing
        $("#email").on("input", function() {
            this.value = this.value.replace(/[^A-Za-z0-9._%+\-@]/g, '');
        });

        $("#userForm").validate({

            rules: {

                name: {
                    required: true,
                    minlength: 2,
                    maxlength: 100,
                    pattern: /^[A-Za-z]+(?: [A-Za-z]+)*$/
                },

                email: {
                    required: true,
                    email: true
                },

                password: {
                    required: true,
                    minlength: 6
                }

            },

            messages: {

                name: {
                    required: "Name is required",
                    minlength: "Name must be at least 2 characters",
                    maxlength: "Name must not exceed 100 characters",
                    pattern: "Name can contain letters and spaces only"
                },

                email: {
                    required: "Email is required",
                    email: "Please enter a valid email address"
                },

                password: {
                    required: "Password is required",
                    minlength: "Password must be at least 6 characters"
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