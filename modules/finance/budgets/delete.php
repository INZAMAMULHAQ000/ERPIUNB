<?php
$pageTitle = 'Delete Budget';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.edit')) { die('Access Denied'); }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('index.php'); }
if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid or expired form token.', 'error'); redirect('index.php'); }

$id = (int)($_POST['budget_id'] ?? 0);
$budget = budget_find($db, $id);
if (!$budget) { flash('error', 'Budget not found.', 'error'); redirect('index.php'); }
if (!in_array($budget['status'], ['draft', 'rejected'])) {
    flash('error', 'Only draft or rejected budgets can be deleted.', 'error');
    redirect('view.php?id=' . $id);
}

try {
    $db->beginTransaction();
    $db->prepare("DELETE FROM budget_versions WHERE budget_id = ?")->execute([$id]); // cascades lines
    $db->prepare("DELETE FROM budgets WHERE id = ?")->execute([$id]);
    $db->commit();
    logAudit($_SESSION['user_id'], 'Deleted budget: ' . $budget['budget_code'], 'budget');
    flash('success', 'Budget deleted.', 'success');
    redirect('index.php');
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('Budget delete error: ' . $e->getMessage());
    flash('error', 'Could not delete budget: ' . $e->getMessage(), 'error');
    redirect('view.php?id=' . $id);
}