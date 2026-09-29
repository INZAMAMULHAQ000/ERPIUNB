<?php
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('users.delete')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

if ($id === (int) $_SESSION['user_id']) {
    flash('error', 'You cannot delete your own account');
    to('modules/users/index.php');
}

$stmt = $db->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);

if ($stmt->rowCount()) {
    logAudit($_SESSION['user_id'], 'delete', 'users');
    flash('success', 'User deleted successfully');
} else {
    flash('error', 'User not found');
}

to('modules/users/index.php');