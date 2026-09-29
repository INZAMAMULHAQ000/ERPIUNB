<?php
$pageTitle = 'Edit User';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('users.edit')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    flash('error', 'User not found');
    to('modules/users/index.php');
}

$old = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => $_POST['name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'status' => $_POST['status'] ?? 'active'
    ];

    $errors = validate($data, [
        'name' => 'required|min:2|max:100',
        'email' => 'required|email'
    ]);

    if (empty($errors)) {
        $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL AND id != ?");
        $checkStmt->execute([$data['email'], $id]);
        if ($checkStmt->fetch()) {
            $errors['email'] = 'Email already exists';
        }
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/users/edit.php?id=' . $id);
    }

    $updateSql = "UPDATE users SET name = ?, email = ?, status = ?, updated_by = ? WHERE id = ?";
    $updateParams = [$data['name'], $data['email'], $data['status'], $_SESSION['user_id'], $id];

    if (!empty($_POST['password'])) {
        $updateSql = "UPDATE users SET name = ?, email = ?, password = ?, status = ?, updated_by = ? WHERE id = ?";
        $updateParams = [$data['name'], $data['email'], password_hash($_POST['password'], PASSWORD_DEFAULT), $data['status'], $_SESSION['user_id'], $id];
    }

    $stmt = $db->prepare($updateSql);
    $stmt->execute($updateParams);

    $db->exec("DELETE FROM user_roles WHERE user_id = $id");
    if (!empty($_POST['roles'])) {
        $roleStmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        foreach ($_POST['roles'] as $roleId) {
            $roleStmt->execute([$id, $roleId]);
        }
    }

    logAudit($_SESSION['user_id'], 'update', 'users');
    flash('success', 'User updated successfully');
    to('modules/users/index.php');



    $user = array_merge($user, $data);
}

$roles = $db->query("SELECT * FROM roles WHERE deleted_at IS NULL")->fetchAll();
$userRoles = $db->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
$userRoles->execute([$id]);
$assignedRoles = array_column($userRoles->fetchAll(), 'role_id');
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Users
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Edit User</h2>

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
            <input type="text" name="name" value="<?= old('name', $user['name']) ?>" required
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-600">*</span></labe>
            <input type="email" name="email" value="<?= old('email', $user['email']) ?>" required
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input type="password" name="password" minlength="6"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            <p class="text-xs text-gray-500 mt-1">Leave blank to keep current password</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="active" <?= ($user['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($user['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="suspended" <?= ($user['status'] ?? 'active') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Assign Roles</label>
            <div class="space-y-2">
                <?php foreach ($roles as $role): ?>
                    <label class="inline-flex items-center mr-4">
                        <input type="checkbox" name="roles[]" value="<?= $role['id'] ?>"
                            <?= in_array($role['id'], $assignedRoles) ? 'checked' : '' ?>
                            class="rounded text-blue-600">
                        <span class="ml-2"><?= sanitize($role['name']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Update User
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