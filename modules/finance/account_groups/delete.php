<?php
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('finance.view')) {
    die('Access Denied');
}

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

// Check if group has child groups
$childStmt = $db->prepare("SELECT COUNT(*) as cnt FROM account_groups WHERE parent_group_id = ?");
$childStmt->execute([$id]);
if ($childStmt->fetch()['cnt'] > 0) {
    flash('error', 'Cannot delete group with child groups. Move or delete children first.');
    to('modules/finance/account_groups/index.php');
}

// Check if group is referenced by account_types
$typeStmt = $db->prepare("SELECT COUNT(*) as cnt FROM account_types WHERE group_id = ?");
$typeStmt->execute([$id]);
if ($typeStmt->fetch()['cnt'] > 0) {
    flash('error', 'Cannot delete group linked to account types');
    to('modules/finance/account_groups/index.php');
}

// Check if group is referenced by chart_of_accounts
$coaStmt = $db->prepare("SELECT COUNT(*) as cnt FROM chart_of_accounts WHERE group_id = ?");
$coaStmt->execute([$id]);
if ($coaStmt->fetch()['cnt'] > 0) {
    flash('error', 'Cannot delete group linked to chart of accounts');
    to('modules/finance/account_groups/index.php');
}

$stmt = $db->prepare("DELETE FROM account_groups WHERE id = ?");
$stmt->execute([$id]);

if ($stmt->rowCount()) {
    logAudit($_SESSION['user_id'], 'delete', 'account_groups');
    flash('success', 'Account group deleted successfully');
} else {
    flash('error', 'Account group not found');
}

to('modules/finance/account_groups/index.php');
