<?php
$pageTitle = 'Activate Version';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.approve')) { die('Access Denied'); }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('index.php'); }
if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid or expired form token.', 'error'); redirect('index.php'); }

$versionId = (int)($_POST['version_id'] ?? 0);
$version = $db->prepare("SELECT * FROM budget_versions WHERE id = ?");
$version->execute([$versionId]);
$version = $version->fetch();
if (!$version) { flash('error', 'Version not found.', 'error'); redirect('index.php'); }
if ($version['status'] !== 'draft') {
    flash('error', 'Only draft versions can be activated.', 'error');
    redirect('view.php?id=' . $versionId);
}

$budget = budget_find($db, $version['budget_id']);
if (!$budget || $budget['status'] !== 'active') {
    flash('error', 'Activation is only allowed while the budget is active.', 'error');
    redirect('view.php?id=' . $versionId);
}

try {
    $db->beginTransaction();
    // Lock any currently active version of this budget
    $db->prepare("UPDATE budget_versions SET status = 'locked' WHERE budget_id = ? AND status = 'active'")->execute([$version['budget_id']]);
    $db->prepare("UPDATE budget_versions SET status = 'active', approved_by = ? WHERE id = ?")->execute([$_SESSION['user_id'], $versionId]);
    $db->prepare("UPDATE budgets SET current_version_id = ? WHERE id = ?")->execute([$versionId, $version['budget_id']]);
    $db->commit();
    logAudit($_SESSION['user_id'], 'Activated ' . $version['version_label'] . ' for budget: ' . $budget['budget_code'], 'budget');
    flash('success', 'Version activated.', 'success');
    redirect('view.php?id=' . $versionId);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('Version activate error: ' . $e->getMessage());
    flash('error', 'Could not activate version: ' . $e->getMessage(), 'error');
    redirect('view.php?id=' . $versionId);
}