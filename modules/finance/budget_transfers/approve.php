<?php
$pageTitle = 'Approve/Reject Transfer';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.approve')) { die('Access Denied'); }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('index.php'); }
if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid or expired form token.', 'error'); redirect('index.php'); }

$id     = (int)($_POST['transfer_id'] ?? 0);
$action = $_POST['action'] ?? '';

$transfer = $db->prepare("SELECT t.*, b.budget_code, b.financial_year_id,
                                 fy.start_date, fy.end_date
                          FROM budget_transfers t
                          JOIN budgets b ON b.id = t.budget_id
                          JOIN financial_years fy ON fy.id = b.financial_year_id
                          WHERE t.id = ?");
$transfer->execute([$id]);
$transfer = $transfer->fetch();
if (!$transfer) { flash('error', 'Transfer not found.', 'error'); redirect('index.php'); }
if ($transfer['status'] !== 'pending') { flash('error', 'Only pending transfers can be actioned.', 'error'); redirect('view.php?id=' . $id); }

if ($action === 'approve') {
    $fy = [
        'id'         => $transfer['financial_year_id'],
        'start_date' => $transfer['start_date'],
        'end_date'   => $transfer['end_date'],
    ];
    $fromLine = $db->prepare("SELECT * FROM budget_lines WHERE id = ?");
    $fromLine->execute([$transfer['from_line_id']]);
    $fromLine = $fromLine->fetch();

    $toLine = $db->prepare("SELECT * FROM budget_lines WHERE id = ?");
    $toLine->execute([$transfer['to_line_id']]);
    $toLine = $toLine->fetch();

    if (!$fromLine || !$toLine) { flash('error', 'Transfer lines not found.', 'error'); redirect('view.php?id=' . $id); }

    // Final available check
    $available = line_available($db, $fromLine, $fy, $id);
    if ((float)$transfer['amount'] > $available + 0.005) {
        flash('error', 'Source line no longer has enough available budget.', 'error');
        redirect('view.php?id=' . $id);
    }

    try {
        $db->beginTransaction();
        // Decrement from_line
        $db->prepare("UPDATE budget_lines SET budget_amount = budget_amount - ? WHERE id = ?")->execute([$transfer['amount'], $transfer['from_line_id']]);
        // Increment to_line
        $db->prepare("UPDATE budget_lines SET budget_amount = budget_amount + ? WHERE id = ?")->execute([$transfer['amount'], $transfer['to_line_id']]);
        // Update transfer status
        $db->prepare("UPDATE budget_transfers SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?")->execute([$_SESSION['user_id'], $id]);
        // Recompute version total
        recompute_version_total($db, $transfer['budget_version_id']);
        $db->commit();
        logAudit($_SESSION['user_id'], "Approved budget transfer {$transfer['transfer_no']}: " . formatMoney((float)$transfer['amount']), 'budget');
        flash('success', 'Transfer approved and applied.', 'success');
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('Transfer approve error: ' . $e->getMessage());
        flash('error', 'Could not approve transfer: ' . $e->getMessage(), 'error');
    }
} elseif ($action === 'reject') {
    $reason = trim($_POST['rejection_reason'] ?? '');
    if ($reason === '') { flash('error', 'Rejection reason is required.', 'error'); redirect('view.php?id=' . $id); }

    $db->prepare("UPDATE budget_transfers SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?")
        ->execute([$_SESSION['user_id'], $reason, $id]);
    logAudit($_SESSION['user_id'], "Rejected budget transfer {$transfer['transfer_no']}: $reason", 'budget');
    flash('success', 'Transfer rejected.', 'success');
} else {
    flash('error', 'Invalid action.', 'error');
}
redirect('view.php?id=' . $id);