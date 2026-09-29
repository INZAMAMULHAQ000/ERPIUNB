<?php
$pageTitle = 'Mark Invoice as Sent';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('customer_invoices.edit')) { die('Access Denied'); }

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

// Load invoice
$stmt = $db->prepare("SELECT * FROM customer_invoices WHERE id = ?");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    flash('error', 'Invoice not found.');
    redirect('index.php');
}

if ($invoice['status'] !== 'approved') {
    flash('error', 'Only approved invoices can be marked as sent.');
    redirect('view.php?id=' . $invoice_id);
}

try {
    $db->beginTransaction();

    // Update status
    $stmt = $db->prepare("UPDATE customer_invoices SET status = 'sent', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$invoice_id]);

    // Record status history
    $stmt = $db->prepare("INSERT INTO invoice_status_history (invoice_id, from_status, to_status, changed_by, change_note) VALUES (?, 'approved', 'sent', ?, 'Invoice marked as sent')");
    $stmt->execute([$invoice_id, $_SESSION['user_id']]);

    $db->commit();

    logAudit($_SESSION['user_id'], 'Marked invoice as sent: ' . $invoice['invoice_number'], 'customer_invoices');
    flash('success', 'Invoice #' . $invoice['invoice_number'] . ' marked as sent.', 'success');
} catch (Exception $e) {
    $db->rollBack();
    flash('error', 'Database error: ' . $e->getMessage());
}

redirect('view.php?id=' . $invoice_id);
