<?php
$pageTitle = 'Closing Settings';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once __DIR__ . '/PeriodCloseService.php';

if (!hasPermission('year.close')) { die('Access Denied'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('error', 'Invalid request.');
    to('modules/finance/period_close/index.php');
}

$db = getDB();
PeriodCloseService::saveSettings($db, [
    'retained_earnings_account_id' => (int)($_POST['retained_earnings_account_id'] ?? 0),
    'income_summary_account_id' => (int)($_POST['income_summary_account_id'] ?? 0),
    'closing_method' => $_POST['closing_method'] ?? 'direct_to_retained',
    'require_checklist_before_period_close' => isset($_POST['require_checklist_before_period_close']),
    'require_checklist_before_year_close' => isset($_POST['require_checklist_before_year_close']),
    'allow_year_reopen' => isset($_POST['allow_year_reopen']),
], $_SESSION['user_id']);

flash('success', 'Closing settings saved.');
to('modules/finance/period_close/index.php');
