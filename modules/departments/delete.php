<?php
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('departments.delete')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$desigCount = $db->prepare("SELECT COUNT(*) as cnt FROM designations WHERE department_id = ? AND status = 'active'");
$desigCount->execute([$id]);
if ($desigCount->fetch()['cnt'] > 0) {
    flash('error', 'Cannot delete department with active designations');
    to('modules/departments/index.php');
}

$stmt = $db->prepare("DELETE FROM departments WHERE id = ?");
$stmt->execute([$id]);

if ($stmt->rowCount()) {
    logAudit($_SESSION['user_id'], 'delete', 'departments');
    flash('success', 'Department deleted successfully');
} else {
    flash('error', 'Department not found');
}

to('modules/departments/index.php');