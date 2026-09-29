<?php
$pageTitle = 'Role Permissions';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('roles.edit')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM roles WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$role = $stmt->fetch();

if (!$role) {
    flash('error', 'Role not found');
    to('modules/roles/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $permissions = $_POST['permissions'] ?? [];
    
    $db->exec("DELETE FROM role_permissions WHERE role_id = $id");
    
    if (!empty($permissions)) {
        $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($permissions as $permId) {
            $stmt->execute([$id, $permId]);
        }
    }
    
    logAudit($_SESSION['user_id'], 'update_permissions', 'roles');
    flash('success', 'Permissions updated successfully');
    to('modules/roles/index.php');
}

$permissions = $db->query("SELECT * FROM permissions WHERE deleted_at IS NULL ORDER BY module, name")->fetchAll();

$assignedStmt = $db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
$assignedStmt->execute([$id]);
$assignedPermissions = array_column($assignedStmt->fetchAll(), 'permission_id');

$modules = [];
foreach ($permissions as $perm) {
    $modules[$perm['module']][] = $perm;
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Roles
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-2">Manage Permissions: <?= sanitize($role['name']) ?></h2>
    <p class="text-gray-600 mb-6">Select permissions to assign to this role</p>
    
    <form method="POST">
        <?php foreach ($modules as $module => $perms): ?>
        <div class="mb-6">
            <h3 class="text-lg font-medium text-gray-800 mb-3 capitalize"><?= sanitize($module) ?></h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <?php foreach ($perms as $perm): ?>
                <label class="inline-flex items-center p-3 border rounded hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="permissions[]" value="<?= $perm['id'] ?>" 
                        <?= in_array($perm['id'], $assignedPermissions) ? 'checked' : '' ?>
                        class="rounded text-blue-600">
                    <span class="ml-2 text-sm"><?= sanitize($perm['name']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="pt-4 border-t">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Save Permissions
            </button>
            <a href="index.php" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>