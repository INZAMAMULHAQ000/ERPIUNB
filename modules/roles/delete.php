<?php
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('roles.delete')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 1) {
    flash('error', 'Cannot delete default roles');
    to('modules/roles/index.php');
}

$userCount = $db->prepare("SELECT COUNT(*) as cnt FROM user_roles WHERE role_id = ?");
$userCount->execute([$id]);
if ($userCount->fetch()['cnt'] > 0) {
    flash('error', 'Cannot delete role with assigned users');
    to('modules/roles/index.php');
}

$stmt = $db->prepare("UPDATE roles SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);

if ($stmt->rowCount()) {
    $db->exec("DELETE FROM role_permissions WHERE role_id = $id");
    logAudit($_SESSION['user_id'], 'delete', 'roles');
    flash('success', 'Role deleted successfully');
} else {
    flash('error', 'Role not found');
}

to('modules/roles/index.php');