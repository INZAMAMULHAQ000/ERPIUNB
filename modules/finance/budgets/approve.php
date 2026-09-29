<?php
$pageTitle = 'Approve Budget';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.approve')) { die('Access Denied'); }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('index.php'); }
if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid or expired form token.', 'error'); redirect('index.php'); }

$id = (int)($_POST['budget_id'] ?? 0);
$action = $_POST['action'] ?? '';
$budget = budget_find($db, $id);
if (!$budget) { flash('error', 'Budget not found.', 'error'); redirect('index.php'); }
if ($budget['status'] !== 'pending_approval') {
    flash('error', 'Budget is not awaiting approval.', 'error');
    redirect('view.php?id=' . $id);
}

if ($action === 'approve') {
    $db->prepare("UPDATE budgets SET status = 'approved', approved_by = ? WHERE id = ?")->execute([$_SESSION['user_id'], $id]);
    logAudit($_SESSION['user_id'], 'Approved budget: ' . $budget['budget_code'], 'budget');
    flash('success', 'Budget approved. Activate it to start monitoring.', 'success');
} elseif ($action === 'reject') {
    $reason = trim($_POST['rejection_reason'] ?? '');
    if ($reason === '') {
        flash('error', 'A rejection reason is required.', 'error');
        redirect('view.php?id=' . $id);
    }
    $db->prepare("UPDATE budgets SET status = 'rejected' WHERE id = ?")->execute([$id]);
    logAudit($_SESSION['user_id'], 'Rejected budget: ' . $budget['budget_code'], 'budget');
    flash('success', 'Budget rejected.', 'success');
} else {
    flash('error', 'Invalid action.', 'error');
}
redirect('view.php?id=' . $id);