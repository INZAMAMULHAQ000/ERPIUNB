<?php
$pageTitle = 'Close Budget';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.approve')) { die('Access Denied'); }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('index.php'); }
if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid or expired form token.', 'error'); redirect('index.php'); }

$id = (int)($_POST['budget_id'] ?? 0);
$budget = budget_find($db, $id);
if (!$budget) { flash('error', 'Budget not found.', 'error'); redirect('index.php'); }
if ($budget['status'] !== 'active') {
    flash('error', 'Only active budgets can be closed.', 'error');
    redirect('view.php?id=' . $id);
}

$db->prepare("UPDATE budgets SET status = 'closed' WHERE id = ?")->execute([$id]);
logAudit($_SESSION['user_id'], 'Closed budget: ' . $budget['budget_code'], 'budget');
flash('success', 'Budget closed.', 'success');
redirect('view.php?id=' . $id);