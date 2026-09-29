<?php
$pageTitle = 'View User';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('users.view')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    flash('error', 'User not found');
    to('modules/users/index.php');
}

$roleStmt = $db->prepare("SELECT r.* FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
$roleStmt->execute([$id]);
$roles = $roleStmt->fetchAll();

$createdByUser = null;
if ($user['created_by']) {
    $creatorStmt = $db->prepare("SELECT name FROM users WHERE id = ?");
    $creatorStmt->execute([$user['created_by']]);
    $createdByUser = $creatorStmt->fetch();
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Users
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold mb-4">User Details</h2>
        
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-500">Name</label>
                <p class="text-gray-900"><?= sanitize($user['name']) ?></p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-500">Email</label>
                <p class="text-gray-900"><?= sanitize($user['email']) ?></p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-500">Status</label>
                <?php
                $statusColors = ['active' => 'bg-green-100 text-green-800', 'inactive' => 'bg-gray-100 text-gray-800', 'suspended' => 'bg-red-100 text-red-800'];
                ?>
                <span class="px-2 py-1 text-xs rounded <?= $statusColors[$user['status']] ?? 'bg-gray-100' ?>">
                    <?= ucfirst($user['status']) ?>
                </span>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-500">Created By</label>
                <p class="text-gray-900"><?= sanitize($createdByUser['name'] ?? 'System') ?></p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-500">Created At</label>
                <p class="text-gray-900"><?= date('M d, Y H:i', strtotime($user['created_at'])) ?></p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-500">Updated At</label>
                <p class="text-gray-900"><?= date('M d, Y H:i', strtotime($user['updated_at'])) ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold mb-4">Assigned Roles</h2>
        
        <?php if ($roles): ?>
        <div class="space-y-2">
            <?php foreach ($roles as $role): ?>
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <span class="font-medium"><?= sanitize($role['name']) ?></span>
                <?php if (hasPermission('users.edit')): ?>
                <a href="../roles/edit.php?id=<?= $role['id'] ?>" class="text-blue-600 hover:text-blue-900 text-sm">Edit</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-gray-500">No roles assigned</p>
        <?php endif; ?>
        
        <?php if (hasPermission('users.edit')): ?>
        <div class="mt-4">
            <a href="edit.php?id=<?= $id ?>" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                <i class="fas fa-edit mr-2"></i>Edit User
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>