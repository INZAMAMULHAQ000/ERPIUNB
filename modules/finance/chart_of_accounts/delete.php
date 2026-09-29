<?php
$pageTitle = 'Delete Account';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('finance.manage')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$account = $db->prepare("SELECT * FROM chart_of_accounts WHERE id = ?");
$account->execute([$id]);
$account = $account->fetch();

if (!$account) {
    flash('error', 'Account not found');
    to('modules/finance/chart_of_accounts/index.php');
}

// Check if opening_balance_history exists (prevents deletion)
$histStmt = $db->prepare("SELECT COUNT(*) as cnt FROM opening_balance_history WHERE account_id = ?");
$histStmt->execute([$id]);
$historyCount = $histStmt->fetch()['cnt'];

if ($historyCount > 0) {
    flash('error', 'Cannot delete account that has transaction history');
    to('modules/finance/chart_of_accounts/index.php');
}

// Perform deletion
$stmt = $db->prepare("DELETE FROM chart_of_accounts WHERE id = ?");
$stmt->execute([$id]);

logAudit($_SESSION['user_id'], 'Deleted account: ' . $account['account_code'] . ' - ' . $account['account_name'], 'finance');
flash('success', 'Account deleted successfully');
to('modules/finance/chart_of_accounts/index.php');
