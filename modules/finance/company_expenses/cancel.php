<?php
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('company_expenses.cancel')) { die('Access Denied'); }

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
if (in_array($expense['status'], ['paid', 'cancelled'])) { flash('error', 'Expense cannot be cancelled in its current state.'); redirect('view.php?id=' . $expense_id); }

try {
    $db->beginTransaction();

    // If the expense was approved and posted to GL, reverse the journal entry first
    if (!empty($expense['journal_entry_id'])) {
        require_once __DIR__ . '/../posting_engine/PostingEngine.php';
        $result = PostingEngine::reverse($expense['journal_entry_id'], $_SESSION['user_id'], 'Expense cancelled: ' . $expense['expense_number']);
        if (!$result['success']) {
            throw new Exception('Cannot cancel: ' . $result['message']);
        }
    }

    // Reverse every active input tax transaction for this expense
    require_once '../tax/helpers/tax_ui.php';
    try {
        TaxService::reverseDocumentTax('company_expense', $expense_id, $expense['expense_date'], $_SESSION['user_id'], 'Expense cancelled: ' . $expense['expense_number']);
    } catch (Exception $e) {
        throw new Exception('Failed to reverse tax transactions: ' . $e->getMessage());
    }

    $stmt = $db->prepare("UPDATE company_expenses SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND status <> 'cancelled'");
    $stmt->execute([$expense_id]);
    if ($stmt->rowCount() === 0) {
        throw new Exception('Expense was already cancelled or changed by another user.');
    }

    $stmt = $db->prepare("INSERT INTO expense_approval_history (expense_type, expense_id, approval_level, action, action_by) VALUES ('company_expense', ?, 'cancellation', 'cancelled', ?)");
    $stmt->execute([$expense_id, $_SESSION['user_id']]);

    $db->commit();
    logAudit($_SESSION['user_id'], 'Cancelled company expense: ' . $expense['expense_number'], 'company_expenses');
    flash('success', 'Expense cancelled.' . (!empty($expense['journal_entry_id']) ? ' Journal entry reversed.' : ''), 'success');
} catch (Exception $e) {
    $db->rollBack();
    flash('error', 'Error: ' . $e->getMessage());
}

redirect('view.php?id=' . $expense_id);
