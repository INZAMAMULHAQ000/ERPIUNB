<?php
$pageTitle = 'Cancel Vendor Bill';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('vendor_bills.cancel')) { die('Access Denied'); }

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
$bill_id = (int)($_POST['bill_id'] ?? 0);
if ($bill_id <= 0) {
    flash('error', 'Invalid bill ID.');
    redirect('index.php');
}

// Load bill
$stmt = $db->prepare("SELECT * FROM vendor_bills WHERE id = ?");
$stmt->execute([$bill_id]);
$bill = $stmt->fetch();

if (!$bill) {
    flash('error', 'Vendor bill not found.');
    redirect('index.php');
}

// Only allow cancellation of draft, approved, partially_paid, overdue (not paid or cancelled)
$cancellableStatuses = ['draft', 'approved', 'partially_paid', 'overdue'];
if (!in_array($bill['status'], $cancellableStatuses)) {
    flash('error', 'Vendor bill cannot be cancelled in its current status: ' . ucwords(str_replace('_', ' ', $bill['status'])));
    redirect('view.php?id=' . $bill_id);
}

try {
    $db->beginTransaction();

    $fromStatus = $bill['status'];

    // Prevent cancellation if there are payment allocations (bill has payments against it)
    if ((float)$bill['paid_amount'] > 0) {
        $allocStmt = $db->prepare("SELECT COUNT(*) FROM payment_allocations WHERE bill_id = ?");
        $allocStmt->execute([$bill_id]);
        $allocCount = (int)$allocStmt->fetchColumn();
        if ($allocCount > 0) {
            throw new Exception('Cannot cancel bill with existing payment allocations. Reverse the payments first.');
        }
    }

    // If bill was approved/posted (has journal_entry_id), reverse the journal entry
    if (!empty($bill['journal_entry_id'])) {
        require_once __DIR__ . '/../posting_engine/PostingEngine.php';
        $result = PostingEngine::reverse($bill['journal_entry_id'], $_SESSION['user_id'], 'Vendor bill cancelled: ' . $bill['bill_number']);

        if (!$result['success']) {
            throw new Exception('Failed to reverse journal entry: ' . $result['message']);
        }
    }

    // Reverse every active input tax transaction for this bill
    require_once '../tax/helpers/tax_ui.php';
    try {
        TaxService::reverseDocumentTax('vendor_bill', $bill_id, $bill['bill_date'], $_SESSION['user_id'], 'Vendor bill cancelled: ' . $bill['bill_number']);
    } catch (Exception $e) {
        throw new Exception('Failed to reverse tax transactions: ' . $e->getMessage());
    }

    // Update bill status
    $stmt = $db->prepare("UPDATE vendor_bills SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$bill_id]);

    // Record status history
    $stmt = $db->prepare("INSERT INTO bill_status_history (bill_id, from_status, to_status, changed_by, change_note) VALUES (?, ?, 'cancelled', ?, ?)");
    $stmt->execute([$bill_id, $fromStatus, $_SESSION['user_id'], 'Vendor bill cancelled']);

    $db->commit();

    logAudit($_SESSION['user_id'], 'Cancelled vendor bill: ' . $bill['bill_number'], 'vendor_bills');
    flash('success', 'Vendor bill #' . $bill['bill_number'] . ' cancelled successfully.', 'success');
} catch (Exception $e) {
    $db->rollBack();
    flash('error', 'Failed to cancel vendor bill: ' . $e->getMessage());
}

redirect('view.php?id=' . $bill_id);
