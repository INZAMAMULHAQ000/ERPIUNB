<?php
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('employees.delete')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("DELETE FROM employees WHERE id = ?");
$stmt->execute([$id]);

if ($stmt->rowCount()) {
    logAudit($_SESSION['user_id'], 'delete', 'employees');
    flash('success', 'Employee deleted successfully');
} else {
    flash('error', 'Employee not found');
}

to('modules/employees/index.php');