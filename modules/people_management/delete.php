<?php
require_once '../../helpers/redirect.php';
require_once '../../config/database.php';
require_once '../../helpers/security.php';
require_once '../../helpers/rbac.php';

session_start();

if (!isset($_SESSION['user_id']) || !hasPermission('people_management.delete')) {
    die('Access Denied');
}

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM people WHERE id = ?");
$stmt->execute([$id]);
$person = $stmt->fetch();

if (!$person) {
    flash('error', 'Person not found', 'error');
    to('modules/people_management/index.php');
}

try {
    $db->beginTransaction();

    // Delete the person record (the FK ON DELETE SET NULL will nullify user_id/employee_id in people)
    $delStmt = $db->prepare("DELETE FROM people WHERE id = ?");
    $delStmt->execute([$id]);

    // Note: We do NOT delete the linked user or employee records,
    // as those may have independent data (employee has attendance, leaves, etc.)
    // They remain orphaned but accessible from their respective modules.

    $db->commit();

    logAudit($_SESSION['user_id'], 'delete', 'people_management');
    flash('success', 'Person deleted successfully');

} catch (Exception $e) {
    $db->rollBack();
    flash('error', 'Failed to delete person: ' . $e->getMessage(), 'error');
}

to('modules/people_management/index.php');
