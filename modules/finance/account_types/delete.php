<?php
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('finance.manage')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT type_name FROM account_types WHERE id = ?");
$stmt->execute([$id]);
$accountType = $stmt->fetch();

if (!$accountType) {
    flash('error', 'Account type not found');
    to('modules/finance/account_types/index.php');
}

$checkStmt = $db->prepare("SELECT COUNT(*) as cnt FROM chart_of_accounts WHERE type_id = ?");
$checkStmt->execute([$id]);
$count = $checkStmt->fetch()['cnt'];

if ($count > 0) {
    flash('error', 'Cannot delete: ' . $count . ' account(s) use this type');
    to('modules/finance/account_types/index.php');
}

$stmt = $db->prepare("DELETE FROM account_types WHERE id = ?");
$stmt->execute([$id]);

if ($stmt->rowCount()) {
    logAudit($_SESSION['user_id'], 'Deleted account type: ' . $accountType['type_name'], 'finance');
    flash('success', 'Account type deleted successfully');
} else {
    flash('error', 'Account type not found');
}

to('modules/finance/account_types/index.php');
