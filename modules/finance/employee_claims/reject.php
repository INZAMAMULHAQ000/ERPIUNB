<?php
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('employee_claims.approve')) { die('Access Denied'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { flash('error', 'Invalid request method.'); redirect('index.php'); }
if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid or expired form token.'); redirect('index.php'); }

$db = getDB();
$claim_id = (int)($_POST['claim_id'] ?? 0);
if ($claim_id <= 0) { flash('error', 'Invalid claim ID.'); redirect('index.php'); }

$stmt = $db->prepare("SELECT * FROM employee_expense_claims WHERE id = ?");
$stmt->execute([$claim_id]);
$claim = $stmt->fetch();
if (!$claim) { flash('error', 'Claim not found.'); redirect('index.php'); }
if (!in_array($claim['status'], ['pending_manager', 'pending_finance'])) { flash('error', 'Claim cannot be rejected in its current state.'); redirect('view.php?id=' . $claim_id); }

$rejection_reason = trim($_POST['rejection_reason'] ?? '');
if (empty($rejection_reason)) { flash('error', 'Rejection reason is required.'); redirect('view.php?id=' . $claim_id); }

try {
    $db->beginTransaction();
    $stmt = $db->prepare("UPDATE employee_expense_claims SET status = 'rejected', rejection_reason = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$rejection_reason, $claim_id]);
    $stmt = $db->prepare("INSERT INTO expense_approval_history (expense_type, expense_id, approval_level, action, comments, action_by) VALUES ('employee_claim', ?, 'rejection', 'rejected', ?, ?)");
    $stmt->execute([$claim_id, $rejection_reason, $_SESSION['user_id']]);
    $db->commit();
    logAudit($_SESSION['user_id'], 'Rejected claim: ' . $claim['claim_number'], 'employee_claims');
    flash('success', 'Claim rejected.', 'success');
} catch (Exception $e) { $db->rollBack(); flash('error', 'Error: ' . $e->getMessage()); }

redirect('view.php?id=' . $claim_id);
