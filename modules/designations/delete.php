<?php
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('designations.delete')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$empCount = $db->prepare("SELECT COUNT(*) as cnt FROM employees WHERE designation_id = ? AND status = 'active'");
$empCount->execute([$id]);
if ($empCount->fetch()['cnt'] > 0) {
    flash('error', 'Cannot delete designation with active employees');
    to('modules/designations/index.php');
}

$stmt = $db->prepare("DELETE FROM designations WHERE id = ?");
$stmt->execute([$id]);

if ($stmt->rowCount()) {
    logAudit($_SESSION['user_id'], 'delete', 'designations');
    flash('success', 'Designation deleted successfully');
} else {
    flash('error', 'Designation not found');
}

to('modules/designations/index.php');