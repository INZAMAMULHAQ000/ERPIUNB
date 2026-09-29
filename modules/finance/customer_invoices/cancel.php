<?php
$pageTitle = 'Cancel Invoice';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('customer_invoices.cancel')) { die('Access Denied'); }

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Invalid request method.');
    redirect('index.php');
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('error', 'Invalid or expired form token. Please try again.');
    redirect('index.php');
}

$db = getDB();
$invoice_id = (int)($_POST['invoice_id'] ?? 0);
if ($invoice_id <= 0) {
    flash('error', 'Invalid invoice ID.');
    redirect('index.php');
}

$remarks = trim($_POST['remarks'] ?? '');
if (empty($remarks)) {
    flash('error', 'Cancellation reason is required.');
    redirect('view.php?id=' . $invoice_id);
}

// Load invoice
$stmt = $db->prepare("SELECT * FROM customer_invoices WHERE id = ?");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    flash('error', 'Invoice not found.');
    redirect('index.php');
}

// Only allow cancellation of draft, approved, sent, partially_paid, overdue (not paid or cancelled)
$cancellableStatuses = ['draft', 'approved', 'sent', 'partially_paid', 'overdue'];
if (!in_array($invoice['status'], $cancellableStatuses)) {
    flash('error', 'Invoice cannot be cancelled in its current status: ' . ucwords(str_replace('_', ' ', $invoice['status'])));
    redirect('view.php?id=' . $invoice_id);
}

try {
    $db->beginTransaction();

    $fromStatus = $invoice['status'];

    // If invoice was approved/posted (has journal_entry_id), reverse the journal entry
    if (!empty($invoice['journal_entry_id'])) {
        require_once __DIR__ . '/../posting_engine/PostingEngine.php';
        $result = PostingEngine::reverse($invoice['journal_entry_id'], $_SESSION['user_id'], 'Invoice cancelled: ' . $remarks);

        if (!$result['success']) {
            throw new Exception('Failed to reverse journal entry: ' . $result['message']);
        }
    }

    // Reverse every active output tax transaction for this invoice
    // (writes balanced negative mirror rows; originals stay for audit).
    require_once '../tax/helpers/tax_ui.php';
    try {
        TaxService::reverseDocumentTax('customer_invoice', $invoice_id, $invoice['invoice_date'], $_SESSION['user_id'], 'Invoice cancelled: ' . $remarks);
    } catch (Exception $e) {
        throw new Exception('Failed to reverse tax transactions: ' . $e->getMessage());
    }

    // Update invoice status
    $stmt = $db->prepare("UPDATE customer_invoices SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$invoice_id]);

    // Record status history
    $stmt = $db->prepare("INSERT INTO invoice_status_history (invoice_id, from_status, to_status, changed_by, change_note) VALUES (?, ?, 'cancelled', ?, ?)");
    $stmt->execute([$invoice_id, $fromStatus, $_SESSION['user_id'], $remarks]);

    $db->commit();

    logAudit($_SESSION['user_id'], 'Cancelled invoice: ' . $invoice['invoice_number'] . ' - ' . $remarks, 'customer_invoices');
    flash('success', 'Invoice #' . $invoice['invoice_number'] . ' cancelled successfully.', 'success');
} catch (Exception $e) {
    $db->rollBack();
    flash('error', 'Failed to cancel invoice: ' . $e->getMessage());
}

redirect('view.php?id=' . $invoice_id);
