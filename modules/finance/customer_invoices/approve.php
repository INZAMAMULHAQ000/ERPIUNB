<?php
$pageTitle = 'Approve Invoice';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../tax/helpers/tax_ui.php';

if (!hasPermission('customer_invoices.approve')) { die('Access Denied'); }

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

if ($invoice['status'] !== 'draft') {
    flash('error', 'Only draft invoices can be approved.');
    redirect('view.php?id=' . $invoice_id);
}

try {
    $db->beginTransaction();

    // Load AR account ID and revenue account ID from settings
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('finance_ar_account_id', 'finance_revenue_account_id')");
    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['setting_key']] = (int)$row['setting_value'];
    }

    $arAccountId = $settings['finance_ar_account_id'] ?? 0;
    $revenueAccountId = $settings['finance_revenue_account_id'] ?? 0;

    if ($arAccountId <= 0 || $revenueAccountId <= 0) {
        throw new Exception('AR account or Revenue account not configured in Finance Settings.');
    }

    // Get Sales Invoice voucher type
    $stmt = $db->prepare("SELECT id, voucher_prefix FROM voucher_types WHERE voucher_name = 'Sales Invoice' LIMIT 1");
    $stmt->execute();
    $vt = $stmt->fetch();
    if (!$vt) {
        throw new Exception('Sales Invoice voucher type not found. Please ensure it exists in voucher types.');
    }
    $voucherTypeId = (int)$vt['id'];

    // Generate voucher number
    require_once '../helpers/finance_helpers.php';
    $voucherNo = generateVoucherNo($voucherTypeId, $db);
    if (!$voucherNo) {
        throw new Exception('Failed to generate voucher number.');
    }

    // Create journal entry (draft)
    $stmt = $db->prepare("
        INSERT INTO journal_entries (voucher_no, voucher_type_id, voucher_date, description, status, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'draft', ?, NOW(), NOW())
    ");
    $stmt->execute([
        $voucherNo,
        $voucherTypeId,
        $invoice['invoice_date'],
        'Invoice #' . $invoice['invoice_number'] . ' - ' . $invoice['total_amount'],
        $_SESSION['user_id'],
    ]);
    $journalId = (int)$db->lastInsertId();

    $totalAmount = (float)$invoice['total_amount'];
    $taxAmount   = (float)$invoice['tax_amount'];
    $netAmount   = $totalAmount - $taxAmount;

    // Add journal lines: DR AR (total), CR Revenue (net), CR Output Tax (tax)
    $stmt = $db->prepare("
        INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description)
        VALUES (?, ?, ?, ?, ?)
    ");
    // DR: AR Account
    $stmt->execute([$journalId, $arAccountId, $totalAmount, 0, 'Invoice #' . $invoice['invoice_number']]);
    // CR: Revenue Account (tax-exclusive amount)
    $stmt->execute([$journalId, $revenueAccountId, 0, $netAmount, 'Invoice #' . $invoice['invoice_number']]);
    // CR: Output Tax Account (if any tax on the document)
    if ($taxAmount > 0) {
        $outputTaxAccountId = (int)TaxService::config('output_tax_account_id', 0);
        if ($outputTaxAccountId <= 0) {
            throw new Exception('Output Tax account not configured in Tax Settings.');
        }
        $stmt->execute([$journalId, $outputTaxAccountId, 0, $taxAmount, 'Output Tax #' . $invoice['invoice_number']]);
    }

    // Register the output tax transactions in the tax register (grouped by type).
    $stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order");
    $stmt->execute([$invoice_id]);
    $typeGroups = [];
    foreach ($stmt->fetchAll() as $it) {
        $tid = (int)($it['tax_type_id'] ?? 0);
        if (!$tid) continue;
        $typeGroups[$tid][] = [
            'taxable_amount' => (float)$it['taxable_amount'],
            'tax_amount'     => (float)$it['tax_amount'],
            'tax_rate'       => (float)($it['tax_percent'] ?: $it['tax_percentage'] ?: 0),
            'tax_rate_id'    => $it['tax_rate_id'] ? (int)$it['tax_rate_id'] : null,
            'tax_group_id'   => $it['tax_group_id'] ? (int)$it['tax_group_id'] : null,
            'tax_status'     => $it['tax_status'] ?? 'taxable',
            'exemption_id'   => $it['tax_exemption_id'] ? (int)$it['tax_exemption_id'] : null,
        ];
    }
    foreach ($typeGroups as $tid => $lines) {
        TaxService::recordDocumentTax([
            'source_module'      => 'customer_invoice',
            'source_record_id'   => $invoice_id,
            'source_document_no' => $invoice['invoice_number'],
            'transaction_date'   => $invoice['invoice_date'],
            'tax_type_id'        => $tid,
            'direction'          => 'output',
            'counterparty_type'  => 'customer',
            'counterparty_id'    => (int)$invoice['client_id'],
            'created_by'         => $_SESSION['user_id'],
            'lines'              => $lines,
        ]);
    }

    // Post the journal entry
    require_once __DIR__ . '/../posting_engine/PostingEngine.php';
    $result = PostingEngine::post($journalId, $_SESSION['user_id']);

    if (!$result['success']) {
        throw new Exception($result['message']);
    }

    // Update invoice status
    $stmt = $db->prepare("UPDATE customer_invoices SET status = 'approved', journal_entry_id = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$journalId, $invoice_id]);

    // Record status history
    $stmt = $db->prepare("INSERT INTO invoice_status_history (invoice_id, from_status, to_status, changed_by, change_note) VALUES (?, 'draft', 'approved', ?, ?)");
    $stmt->execute([$invoice_id, $_SESSION['user_id'], 'Invoice approved and posted to general ledger. Voucher: ' . $voucherNo]);

    $db->commit();

    logAudit($_SESSION['user_id'], 'Approved invoice: ' . $invoice['invoice_number'] . ' (Voucher: ' . $voucherNo . ')', 'customer_invoices');
    flash('success', 'Invoice approved and posted to general ledger successfully. Voucher #' . $voucherNo, 'success');
} catch (Exception $e) {
    $db->rollBack();
    flash('error', 'Failed to approve invoice: ' . $e->getMessage());
}

redirect('view.php?id=' . $invoice_id);