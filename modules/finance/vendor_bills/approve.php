<?php
$pageTitle = 'Approve Vendor Bill';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../tax/helpers/tax_ui.php';

if (!hasPermission('vendor_bills.approve')) { die('Access Denied'); }

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

if ($bill['status'] !== 'draft') {
    flash('error', 'Only draft bills can be approved.');
    redirect('view.php?id=' . $bill_id);
}

try {
    $db->beginTransaction();

    // Load AP account and default expense account from settings
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('finance_ap_account_id', 'finance_expense_account_id')");
    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['setting_key']] = (int)$row['setting_value'];
    }

    $apAccountId = $settings['finance_ap_account_id'] ?? 0;
    $defaultExpenseAccountId = $settings['finance_expense_account_id'] ?? 0;

    if ($apAccountId <= 0) {
        throw new Exception('AP account not configured. Go to <a href="' . url('modules/finance/settings/index.php') . '" style="text-decoration:underline">Finance Settings</a> to set up the Accounts Payable account.');
    }
    if ($defaultExpenseAccountId <= 0) {
        throw new Exception('Default expense account not configured. Go to <a href="' . url('modules/finance/settings/index.php') . '" style="text-decoration:underline">Finance Settings</a> to set up the default expense account.');
    }

    // Get Purchase Voucher voucher type
    $stmt = $db->prepare("SELECT id, voucher_prefix FROM voucher_types WHERE voucher_name = 'Purchase Voucher' LIMIT 1");
    $stmt->execute();
    $vt = $stmt->fetch();
    if (!$vt) {
        throw new Exception('Purchase Voucher voucher type not found. Please ensure it exists in voucher types.');
    }
    $voucherTypeId = (int)$vt['id'];

    // Generate voucher number
    require_once '../helpers/finance_helpers.php';
    $voucherNo = generateVoucherNo($voucherTypeId, $db);
    if (!$voucherNo) {
        throw new Exception('Failed to generate voucher number.');
    }

    // Load bill items with their expense_account_id
    $items = $db->prepare("SELECT * FROM vendor_bill_items WHERE bill_id = ? ORDER BY sort_order");
    $items->execute([$bill_id]);
    $billItems = $items->fetchAll();

    if (empty($billItems)) {
        throw new Exception('Bill has no items to post.');
    }

    // Create journal entry (draft)
    $stmt = $db->prepare("
        INSERT INTO journal_entries (voucher_no, voucher_type_id, voucher_date, description, status, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'draft', ?, NOW(), NOW())
    ");
    $stmt->execute([
        $voucherNo,
        $voucherTypeId,
        $bill['bill_date'],
        'Vendor Bill #' . $bill['bill_number'] . ' - ' . $bill['vendor_id'],
        $_SESSION['user_id'],
    ]);
    $journalId = (int)$db->lastInsertId();

    $totalAmount = (float)$bill['total_amount'];
    $taxAmount   = (float)$bill['tax_amount'];
    $netAmount   = $totalAmount - $taxAmount;

    // Add journal lines: DR each item's expense account (net of tax),
    // DR Input Tax (if any), CR AP (total)
    $detailStmt = $db->prepare("
        INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description)
        VALUES (?, ?, ?, ?, ?)
    ");

    // DR: each item to its expense account (net amount = line_total - tax)
    $itemTotalDr = 0;
    foreach ($billItems as $item) {
        $expenseAccountId = (int)$item['expense_account_id'];
        if ($expenseAccountId <= 0) {
            $expenseAccountId = $defaultExpenseAccountId;
        }
        if ($expenseAccountId <= 0) {
            throw new Exception('No expense account configured for item "' . sanitize($item['description']) . '" and no default expense account set in Finance Settings.');
        }

        $lineAmount = (float)$item['line_total'];
        $lineNet = (float)$item['line_total'] - (float)$item['tax_amount'];
        $detailStmt->execute([
            $journalId,
            $expenseAccountId,
            $lineNet > 0 ? $lineNet : 0,
            0,
            'Bill #' . $bill['bill_number'] . ' - ' . $item['description'],
        ]);
        $itemTotalDr += $lineNet > 0 ? $lineNet : 0;
    }

    // DR: Input Tax Account (if any tax on the document)
    if ($taxAmount > 0) {
        $inputTaxAccountId = (int)TaxService::config('input_tax_account_id', 0);
        if ($inputTaxAccountId <= 0) {
            throw new Exception('Input Tax account not configured. Go to <a href="' . url('modules/finance/tax/settings/index.php') . '" style="text-decoration:underline">Tax Settings</a> to set it up.');
        }
        $detailStmt->execute([$journalId, $inputTaxAccountId, $taxAmount, 0, 'Input Tax #' . $bill['bill_number']]);
        $itemTotalDr += $taxAmount;
    }

    // CR: AP Account for total
    $detailStmt->execute([$journalId, $apAccountId, 0, $totalAmount, 'Bill #' . $bill['bill_number']]);

    // Register the input tax transactions in the tax register (grouped by type).
    $typeGroups = [];
    foreach ($billItems as $it) {
        $tid = (int)($it['tax_type_id'] ?? 0);
        if (!$tid) continue;
        $typeGroups[$tid][] = [
            'taxable_amount' => (float)$it['taxable_amount'],
            'tax_amount'     => (float)$it['tax_amount'],
            'tax_rate'       => (float)($it['tax_percent'] ?? 0),
            'tax_rate_id'    => $it['tax_rate_id'] ? (int)$it['tax_rate_id'] : null,
            'tax_group_id'   => $it['tax_group_id'] ? (int)$it['tax_group_id'] : null,
            'tax_status'     => $it['tax_status'] ?? 'taxable',
            'exemption_id'   => $it['tax_exemption_id'] ? (int)$it['tax_exemption_id'] : null,
        ];
    }
    foreach ($typeGroups as $tid => $lines) {
        TaxService::recordDocumentTax([
            'source_module'      => 'vendor_bill',
            'source_record_id'   => $bill_id,
            'source_document_no' => $bill['bill_number'],
            'transaction_date'   => $bill['bill_date'],
            'tax_type_id'        => $tid,
            'direction'          => 'input',
            'counterparty_type'  => 'vendor',
            'counterparty_id'    => (int)$bill['vendor_id'],
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

    // Update bill status
    $stmt = $db->prepare("UPDATE vendor_bills SET status = 'approved', journal_entry_id = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$journalId, $bill_id]);

    // Record status history
    $stmt = $db->prepare("INSERT INTO bill_status_history (bill_id, from_status, to_status, changed_by, change_note) VALUES (?, 'draft', 'approved', ?, ?)");
    $stmt->execute([$bill_id, $_SESSION['user_id'], 'Bill approved and posted to general ledger. Voucher: ' . $voucherNo]);

    $db->commit();

    logAudit($_SESSION['user_id'], 'Approved vendor bill: ' . $bill['bill_number'] . ' (Voucher: ' . $voucherNo . ')', 'vendor_bills');
    flash('success', 'Vendor bill approved and posted to general ledger successfully. Voucher #' . $voucherNo, 'success');
} catch (Exception $e) {
    $db->rollBack();
    flash('error', 'Failed to approve vendor bill: ' . $e->getMessage());
}

redirect('view.php?id=' . $bill_id);