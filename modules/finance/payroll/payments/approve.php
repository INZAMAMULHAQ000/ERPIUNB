<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$pageTitle = 'Approve Payroll Payment Batch';
require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../../../helpers/redirect.php';
require_once __DIR__ . '/../../../../helpers/validation.php';
require_once __DIR__ . '/../../../../helpers/finance_helpers.php';

// if (!hasPermission('payrollfinance.pay')) { die('Access Denied'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Invalid request method.');
    redirect('index.php');
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('error', 'Invalid or expired form token. Please try again.');
    redirect('index.php');
}

$db = getDB();
$paymentBatchId = (int)($_POST['payment_batch_id'] ?? 0);
if ($paymentBatchId <= 0) {
    flash('error', 'Invalid payment batch ID.');
    redirect('index.php');
}

// Load payment batch
$stmt = $db->prepare('SELECT * FROM payroll_payment_batches WHERE id = ?');
$stmt->execute([$paymentBatchId]);
$paymentBatch = $stmt->fetch();

if (!$paymentBatch) {
    flash('error', 'Payment batch not found.');
    redirect('index.php');
}

if ($paymentBatch['status'] !== 'draft') {
    flash('error', 'Only draft payment batches can be approved.');
    redirect('view.php?id=' . $paymentBatchId);
}

try {
    $db->beginTransaction();

    // Get bank account ID from payment batch
    $bankAccountId = (int)$paymentBatch['bank_account_id'];
    if ($bankAccountId <= 0) {
        throw new Exception('Invalid bank account.');
    }

    // Get salary payable account from payroll_finance_mappings for Salary Payable liability
    $stmt = $db->prepare('SELECT chart_account_id FROM payroll_finance_mappings WHERE payroll_component = ? AND mapping_type = ? AND status = ''active''');
    $stmt->execute(['Salary Payable', 'liability']);
    $salaryPayableAccountId = (int)$stmt->fetchColumn();
    if ($salaryPayableAccountId <= 0) {
        throw new Exception('Salary payable account not configured. Check payroll finance mappings for Net Salary liability.');
    }

    // Get voucher type for Payroll Payment Voucher
    $stmt = $db->prepare('SELECT id, voucher_prefix FROM voucher_types WHERE voucher_name = ? LIMIT 1');
    $stmt->execute(['Payroll Payment Voucher']);
    $vt = $stmt->fetch();
    if (!$vt) {
        throw new Exception('Payroll Payment Voucher voucher type not found. Please ensure it exists in voucher types.');
    }
    $voucherTypeId = (int)$vt['id'];

    // Generate voucher number
    $voucherNo = generateVoucherNo($voucherTypeId, $db);
    if (!$voucherNo) {
        throw new Exception('Failed to generate voucher number.');
    }

    // Create journal entry (draft)
    $stmt = $db->prepare('
        INSERT INTO journal_entries (voucher_no, voucher_type_id, voucher_date, description, status, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, \'draft\', ?, NOW(), NOW())
    ');
    $stmt->execute([
        $voucherNo,
        $voucherTypeId,
        $paymentBatch['payment_date'],
        'Payroll Payment Batch #' . $paymentBatch['reference_number'] . ' - Payroll Run ID: ' . $paymentBatch['payroll_run_id'],
        $_SESSION['user_id']
    ]);
    $journalId = (int)$db->lastInsertId();

    $amount = (float)$paymentBatch['amount'];

    // Add journal lines: DR Salary Payable, CR Bank
    $detailStmt = $db->prepare('
        INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description)
        VALUES (?, ?, ?, ?, ?)
    ');

    // DR: Salary Payable Account
    $detailStmt->execute([
        $journalId,
        $salaryPayableAccountId,
        $amount,
        0,
        'Payroll Payment Batch #' . $paymentBatch['reference_number']
    ]);

    // CR: Bank Account
    $detailStmt->execute([
        $journalId,
        $bankAccountId,
        0,
        $amount,
        'Payroll Payment Batch #' . $paymentBatch['reference_number']
    ]);

    // Post the journal entry
    require_once __DIR__ . '/../../posting_engine/PostingEngine.php';
    $result = PostingEngine::post($journalId, $_SESSION['user_id']);

    if (!$result['success']) {
        throw new Exception($result['message']);
    }

    // Update payment batch status
    $stmt = $db->prepare('UPDATE payroll_payment_batches SET status = \'posted\', journal_entry_id = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$journalId, $paymentBatchId]);

    // Record payment in payroll_payments table for tracking
    $stmt = $db->prepare('
        INSERT INTO payroll_payments (payroll_run_id, amount, payment_date, reference_number, journal_entry_id, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ');
    $stmt->execute([
        $paymentBatch['payroll_run_id'],
        $amount,
        $paymentBatch['payment_date'],
        $paymentBatch['reference_number'],
        $journalId,
        $_SESSION['user_id']
    ]);

    $db->commit();

    logAudit($_SESSION['user_id'], 'Approved payroll payment batch: ' . $paymentBatch['reference_number'] . ' (Voucher: ' . $voucherNo . ')', 'payroll');
    flash('success', 'Payroll payment batch approved and posted to general ledger successfully. Voucher #' . $voucherNo, 'success');
} catch (Exception $e) {
    $db->rollBack();
    flash('error', 'Failed to approve payroll payment batch: ' . $e->getMessage());
}

redirect('view.php?id=' . $paymentBatchId);
?>
