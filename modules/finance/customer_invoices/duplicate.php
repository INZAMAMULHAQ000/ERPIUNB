<?php
$pageTitle = 'Duplicate Invoice';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('customer_invoices.create')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Invalid invoice ID.');
    redirect('index.php');
}

// Load original invoice
$stmt = $db->prepare("SELECT * FROM customer_invoices WHERE id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    flash('error', 'Invoice not found.');
    redirect('index.php');
}

// Load original items
$stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

try {
    $db->beginTransaction();

    // Generate new invoice number
    $prefix = 'INV' . date('Ymd');
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM customer_invoices WHERE invoice_number LIKE ?");
    $stmt->execute([$prefix . '%']);
    $count = (int)$stmt->fetch()['cnt'];
    $newInvoiceNumber = $prefix . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);

    // Insert duplicated invoice as draft
    $stmt = $db->prepare("
        INSERT INTO customer_invoices
            (invoice_number, client_id, project_id, invoice_date, due_date, currency, payment_terms, status,
             subtotal, discount_amount, discount_type, tax_amount, total_amount, paid_amount, balance_amount,
             notes, terms_conditions, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'draft',
                ?, ?, ?, ?, ?, 0, ?,
                ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([
        $newInvoiceNumber,
        $invoice['client_id'],
        $invoice['project_id'],
        date('Y-m-d'),
        date('Y-m-d', strtotime('+30 days')),
        $invoice['currency'],
        $invoice['payment_terms'],
        $invoice['subtotal'],
        $invoice['discount_amount'],
        $invoice['discount_type'],
        $invoice['tax_amount'],
        $invoice['total_amount'],
        $invoice['total_amount'],
        $invoice['notes'],
        $invoice['terms_conditions'],
        $_SESSION['user_id'],
    ]);
    $newInvoiceId = (int)$db->lastInsertId();

    // Duplicate items
    $sort = 0;
    foreach ($items as $item) {
        $stmt = $db->prepare("
            INSERT INTO invoice_items
                (invoice_id, description, quantity, unit, unit_price, discount_percent, tax_percent, tax_amount, line_total, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $newInvoiceId,
            $item['description'],
            $item['quantity'],
            $item['unit'],
            $item['unit_price'],
            $item['discount_percent'],
            $item['tax_percent'],
            $item['tax_amount'],
            $item['line_total'],
            $sort,
        ]);
        $sort++;
    }

    // Record status history
    $note = 'Duplicated from invoice #' . $invoice['invoice_number'];
    $stmt = $db->prepare("INSERT INTO invoice_status_history (invoice_id, from_status, to_status, changed_by, change_note) VALUES (?, NULL, 'draft', ?, ?)");
    $stmt->execute([$newInvoiceId, $_SESSION['user_id'], $note]);

    $db->commit();

    logAudit($_SESSION['user_id'], 'Duplicated invoice: ' . $invoice['invoice_number'] . ' -> ' . $newInvoiceNumber, 'customer_invoices');
    flash('success', 'Invoice duplicated successfully. New invoice #' . $newInvoiceNumber, 'success');
    redirect('edit.php?id=' . $newInvoiceId);
} catch (Exception $e) {
    $db->rollBack();
    flash('error', 'Failed to duplicate invoice: ' . $e->getMessage());
    redirect('view.php?id=' . $id);
}
