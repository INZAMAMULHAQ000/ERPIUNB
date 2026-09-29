<?php
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('employee_claims.edit')) { die('Access Denied'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { flash('error', 'Invalid request method.'); redirect('index.php'); }
if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid or expired form token.'); redirect('index.php'); }

$db = getDB();
$claim_id = (int)($_POST['claim_id'] ?? 0);
if ($claim_id <= 0) { flash('error', 'Invalid claim ID.'); redirect('index.php'); }

$stmt = $db->prepare("SELECT * FROM employee_expense_claims WHERE id = ?");
$stmt->execute([$claim_id]);
$claim = $stmt->fetch();
if (!$claim) { flash('error', 'Claim not found.'); redirect('index.php'); }
if (in_array($claim['status'], ['reimbursed', 'cancelled'])) { flash('error', 'Claim cannot be cancelled.'); redirect('view.php?id=' . $claim_id); }

try {
    $db->beginTransaction();

    // If the claim was approved and posted to GL, reverse the journal entry first
    if (!empty($claim['journal_entry_id'])) {
        require_once __DIR__ . '/../posting_engine/PostingEngine.php';
        $result = PostingEngine::reverse($claim['journal_entry_id'], $_SESSION['user_id'], 'Claim cancelled: ' . $claim['claim_number']);
        if (!$result['success']) {
            throw new Exception('Cannot cancel: ' . $result['message']);
        }
    }

    $stmt = $db->prepare("UPDATE employee_expense_claims SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND status <> 'cancelled'");
    $stmt->execute([$claim_id]);
    if ($stmt->rowCount() === 0) {
        throw new Exception('Claim was already cancelled or changed by another user.');
    }
    $stmt = $db->prepare("INSERT INTO expense_approval_history (expense_type, expense_id, approval_level, action, action_by) VALUES ('employee_claim', ?, 'cancellation', 'cancelled', ?)");
    $stmt->execute([$claim_id, $_SESSION['user_id']]);
    $db->commit();
    logAudit($_SESSION['user_id'], 'Cancelled claim: ' . $claim['claim_number'], 'employee_claims');
    flash('success', 'Claim cancelled.' . (!empty($claim['journal_entry_id']) ? ' Journal entry reversed.' : ''), 'success');
} catch (Exception $e) { $db->rollBack(); flash('error', 'Error: ' . $e->getMessage()); }

redirect('view.php?id=' . $claim_id);
