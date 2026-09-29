<?php
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('employee_claims.create')) { die('Access Denied'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { flash('error', 'Invalid request method.'); redirect('index.php'); }
if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid or expired form token.'); redirect('index.php'); }

$db = getDB();
$claim_id = (int)($_POST['claim_id'] ?? 0);
if ($claim_id <= 0) { flash('error', 'Invalid claim ID.'); redirect('index.php'); }

$stmt = $db->prepare("SELECT * FROM employee_expense_claims WHERE id = ?");
$stmt->execute([$claim_id]);
$claim = $stmt->fetch();
if (!$claim) { flash('error', 'Claim not found.'); redirect('index.php'); }
if ($claim['status'] !== 'draft') { flash('error', 'Only draft claims can be submitted.'); redirect('view.php?id=' . $claim_id); }

try {
    $db->beginTransaction();
    $stmt = $db->prepare("UPDATE employee_expense_claims SET status = 'pending_manager', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$claim_id]);
    $stmt = $db->prepare("INSERT INTO expense_approval_history (expense_type, expense_id, approval_level, action, action_by) VALUES ('employee_claim', ?, 'submission', 'submitted', ?)");
    $stmt->execute([$claim_id, $_SESSION['user_id']]);
    $db->commit();
    logAudit($_SESSION['user_id'], 'Submitted claim for approval: ' . $claim['claim_number'], 'employee_claims');
    flash('success', 'Claim ' . $claim['claim_number'] . ' submitted for manager approval.', 'success');
} catch (Exception $e) { $db->rollBack(); flash('error', 'Error: ' . $e->getMessage()); }

redirect('view.php?id=' . $claim_id);
