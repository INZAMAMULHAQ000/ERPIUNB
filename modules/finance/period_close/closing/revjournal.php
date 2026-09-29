<?php
/**
 * F12 — Reverse the year-end closing journal (explicit corrective action).
 * Available only after an authorized year REOPEN. History preserved.
 */
$pageTitle = 'Reverse Closing Journal';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../PeriodCloseService.php';
require_once __DIR__ . '/../YearEndClosingService.php';

if (!hasPermission('year.reopen')) { die('Access Denied'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('error', 'Invalid request.');
    to('modules/finance/period_close/history/index.php');
}

$db = getDB();
$fyId = (int)($_POST['fy_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

if ($reason === '') {
    flash('error', 'A reason is required to reverse a closing journal.');
    to('modules/finance/period_close/history/index.php?fy_id=' . $fyId);
}

$res = YearEndClosingService::reverseClosingJournal($db, $fyId, $_SESSION['user_id'], $reason);
flash(!empty($res['success']) ? 'success' : 'error', $res['message'] ?? 'Reversal failed.');
to('modules/finance/period_close/history/index.php?fy_id=' . $fyId);
