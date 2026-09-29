<?php
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('company_expenses.approve')) { die('Access Denied'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Invalid request method.');
    redirect('index.php');
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('error', 'Invalid or expired form token.');
    redirect('index.php');
}

$db = getDB();
$expense_id = (int)($_POST['expense_id'] ?? 0);
if ($expense_id <= 0) { flash('error', 'Invalid expense ID.'); redirect('index.php'); }

$stmt = $db->prepare("SELECT * FROM company_expenses WHERE id = ?");
$stmt->execute([$expense_id]);
$expense = $stmt->fetch();

if (!$expense) { flash('error', 'Expense not found.'); redirect('index.php'); }
if ($expense['status'] !== 'pending_approval') { flash('error', 'Only pending expenses can be rejected.'); redirect('view.php?id=' . $expense_id); }

$rejection_reason = trim($_POST['rejection_reason'] ?? '');
if (empty($rejection_reason)) { flash('error', 'Rejection reason is required.'); redirect('view.php?id=' . $expense_id); }

try {
    $db->beginTransaction();

    $stmt = $db->prepare("UPDATE company_expenses SET status = 'rejected', rejection_reason = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$rejection_reason, $expense_id]);

    $stmt = $db->prepare("INSERT INTO expense_approval_history (expense_type, expense_id, approval_level, action, comments, action_by) VALUES ('company_expense', ?, 'approval', 'rejected', ?, ?)");
    $stmt->execute([$expense_id, $rejection_reason, $_SESSION['user_id']]);

    $db->commit();
    logAudit($_SESSION['user_id'], 'Rejected company expense: ' . $expense['expense_number'], 'company_expenses');
    flash('success', 'Expense rejected.', 'success');
} catch (Exception $e) {
    $db->rollBack();
    flash('error', 'Error: ' . $e->getMessage());
}

redirect('view.php?id=' . $expense_id);
