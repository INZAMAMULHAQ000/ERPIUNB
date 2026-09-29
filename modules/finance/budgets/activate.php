<?php
$pageTitle = 'Activate Budget';
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
if (!in_array($budget['status'], ['approved', 'active'])) {
    flash('error', 'Budget must be approved before activation.', 'error');
    redirect('view.php?id=' . $id);
}

$versionId = $budget['current_version_id'];

try {
    $db->beginTransaction();
    // Lock any other active versions of this budget
    $db->prepare("UPDATE budget_versions SET status = 'locked' WHERE budget_id = ? AND status = 'active'")->execute([$id]);
    if ($versionId) {
        $db->prepare("UPDATE budget_versions SET status = 'active' WHERE id = ?")->execute([$versionId]);
    }
    $db->prepare("UPDATE budgets SET status = 'active' WHERE id = ?")->execute([$id]);
    $db->commit();
    logAudit($_SESSION['user_id'], 'Activated budget: ' . $budget['budget_code'], 'budget');
    flash('success', 'Budget activated. Monitoring is now live.', 'success');
    redirect('view.php?id=' . $id);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('Budget activate error: ' . $e->getMessage());
    flash('error', 'Could not activate budget: ' . $e->getMessage(), 'error');
    redirect('view.php?id=' . $id);
}