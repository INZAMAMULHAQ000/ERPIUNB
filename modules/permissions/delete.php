<?php
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('permissions.delete')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$roleCount = $db->prepare("SELECT COUNT(*) as cnt FROM role_permissions WHERE permission_id = ?");
$roleCount->execute([$id]);
if ($roleCount->fetch()['cnt'] > 0) {
    flash('error', 'Cannot delete permission assigned to roles');
    to('modules/permissions/index.php');
}

$stmt = $db->prepare("UPDATE permissions SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);

if ($stmt->rowCount()) {
    logAudit($_SESSION['user_id'], 'delete', 'permissions');
    flash('success', 'Permission deleted successfully');
} else {
    flash('error', 'Permission not found');
}

to('modules/permissions/index.php');