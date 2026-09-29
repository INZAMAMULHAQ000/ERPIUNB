<?php
/**
 * Payment Batch Process
 * Handle creating/processing salary payment batches
 * Phase F10 - Payroll & Finance Integration
 */

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../helpers/security.php';
require_once __DIR__ . '/../../../../helpers/rbac.php';
require_once __DIR__ . '/../../../../helpers/redirect.php';
require_once __DIR__ . '/../../../../middleware/auth.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

requireAuth();

if (!hasPermission('payroll_finance.create')) {
    redirect_to('no-access.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('create.php');
}

$db = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);

$payrollRunId   = (int)($_POST['payroll_run_id'] ?? 0);
$bankAccountId  = (int)($_POST['bank_account_id'] ?? 0);
$paymentDate    = trim($_POST['payment_date'] ?? '');
$totalAmount    = (float)($_POST['total_amount'] ?? 0);
$reference      = trim($_POST['reference_number'] ?? '');
$batchName      = trim($_POST['batch_name'] ?? '');
$description    = trim($_POST['description'] ?? '');
$paymentMethod  = trim($_POST['payment_method'] ?? 'bank_transfer');

$validMethods = ['bank_transfer', 'check', 'cash'];
if (!in_array($paymentMethod, $validMethods, true)) {
    $paymentMethod = 'bank_transfer';
}

// ---- Validation ----
if ($payrollRunId <= 0) {
    $_SESSION['flash']['error'] = 'Invalid payroll run selected.';
    redirect_to('create.php');
}
if ($bankAccountId <= 0) {
    $_SESSION['flash']['error'] = 'Please select a bank account.';
    redirect_to('create.php');
}
if ($paymentDate === '') {
    $_SESSION['flash']['error'] = 'Payment date is required.';
    redirect_to('create.php');
}
if ($totalAmount <= 0) {
    $_SESSION['flash']['error'] = 'Payment amount must be greater than zero.';
    redirect_to('create.php');
}

// Verify payroll run is posted
$postingStmt = $db->prepare("SELECT id, total_net FROM payroll_finance_postings WHERE payroll_run_id = ? AND status = 'Posted' LIMIT 1");
$postingStmt->execute([$payrollRunId]);
$posting = $postingStmt->fetch();

if (!$posting) {
    $_SESSION['flash']['error'] = 'This payroll run has not been posted to finance yet. Please post it first.';
    redirect_to('../postings/create.php');
}

$payrollNet = (float)$posting['total_net'];

// Calculate already paid amount for this run
$paidStmt = $db->prepare("
    SELECT COALESCE(SUM(total_amount), 0) as paid
    FROM payroll_payment_batches
    WHERE payroll_run_id = ? AND status NOT IN ('Failed', 'Cancelled')
");
$paidStmt->execute([$payrollRunId]);
$alreadyPaid = (float)$paidStmt->fetchColumn();
$remaining = $payrollNet - $alreadyPaid;

// Enforce: total payments must not exceed payroll payable
if ($totalAmount > $remaining + 0.01) {
    $_SESSION['flash']['error'] = "Payment amount (AED " . number_format($totalAmount, 2) . ") exceeds remaining balance (AED " . number_format($remaining, 2) . ").";
    redirect_to('create.php');
}

// Get GL accounts for payment journal
$bankAccount = $db->prepare("SELECT id, account_code, account_name FROM chart_of_accounts WHERE id = ? AND status = 'active' LIMIT 1");
$bankAccount->execute([$bankAccountId]);
$bankGL = $bankAccount->fetch();

// Liability account (salary payable)
$payableStmt = $db->prepare("
    SELECT pfm.chart_account_id, coa.account_code, coa.account_name
    FROM payroll_finance_mappings pfm
    JOIN chart_of_accounts coa ON pfm.chart_account_id = coa.id
    WHERE pfm.mapping_type = 'liability' AND pfm.status = 'Active'
    ORDER BY pfm.id ASC LIMIT 1
");
$payableStmt->execute();
$payableGL = $payableStmt->fetch();

if (!$payableGL) {
    $payableGL = $db->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE account_name LIKE '%Salary%Payable%' AND status = 'active' ORDER BY id LIMIT 1")->fetch();
    if (!$payableGL) {
        $payableGL = $db->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE account_name LIKE '%Payable%' AND status = 'active' ORDER BY id LIMIT 1")->fetch();
    }
}

if (!$bankGL || !$payableGL) {
    $_SESSION['flash']['error'] = 'Required GL accounts not found. Please configure bank account and liability mappings.';
    redirect_to('create.php');
}

$voucherTypeId = (int)$db->query("SELECT id FROM voucher_types ORDER BY id LIMIT 1")->fetchColumn();
$voucherNo = 'PMT-' . date('YmdHis') . '-' . $payrollRunId;
$refNo = $reference ?: $voucherNo;
$fullDescription = 'Salary payment - Run #' . $payrollRunId . ($batchName ? ' | ' . $batchName : '') . ($description ? ' | ' . $description : '');

$db->beginTransaction();
try {
    // 1. Create journal entry for payment (DR: Salary Payable, CR: Bank)
    $db->prepare("
        INSERT INTO journal_entries (voucher_no, voucher_type_id, voucher_date, reference_no, description, status, created_by)
        VALUES (?, ?, ?, ?, ?, 'posted', ?)
    ")->execute([$voucherNo, $voucherTypeId, $paymentDate, $refNo, $fullDescription, $userId]);

    $journalId = (int)$db->lastInsertId();

    // Debit: Salary Payable (reduce liability)
    $db->prepare("
        INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description)
        VALUES (?, ?, ?, 0, ?)
    ")->execute([$journalId, $payableGL['id'], $totalAmount, 'Salary paid - Run #' . $payrollRunId]);

    // Credit: Bank Account (reduce balance)
    $db->prepare("
        INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description)
        VALUES (?, ?, 0, ?, ?)
    ")->execute([$journalId, $bankGL['id'], $totalAmount, 'Salary payment from ' . ($bankGL['account_name'] ?? 'bank')]);

    // 2. Create payroll payment record
    $db->prepare("
        INSERT INTO payroll_payments (payroll_run_id, amount, payment_date, reference_number, journal_entry_id, created_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$payrollRunId, $totalAmount, $paymentDate, $refNo, $journalId, $userId]);

    // 3. Create payment batch record
    $batchStatus = 'Pending'; // Will be approved separately or auto-approved
    $db->prepare("
        INSERT INTO payroll_payment_batches
            (payroll_run_id, bank_account_id, payment_date, total_amount, reference_number, status, journal_entry_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([$payrollRunId, $bankAccountId, $paymentDate, $totalAmount, $refNo, $batchStatus, $journalId]);

    $batchId = (int)$db->lastInsertId();

    // 4. Update reconciliation paid_total
    $newPaidTotal = $alreadyPaid + $totalAmount;
    $db->prepare("
        UPDATE payroll_reconciliation
        SET paid_total = ?
        WHERE payroll_run_id = ?
    ")->execute([$newPaidTotal, $payrollRunId]);

    // 5. Audit log
    logAudit($userId, 'Payment Batch Created (Run #' . $payrollRunId . ', Batch #' . $batchId . ', AED ' . number_format($totalAmount, 2) . ')', 'payroll_finance');

    $db->commit();

    if ($newPaidTotal >= $payrollNet - 0.01) {
        // Payroll is fully paid
        $_SESSION['flash']['success'] = 'Payment batch created successfully. This completes the salary payment for Payroll Run #' . $payrollRunId . '.';
    } else {
        $remainingNow = $payrollNet - $newPaidTotal;
        $_SESSION['flash']['success'] = 'Payment batch created successfully. AED ' . number_format($remainingNow, 2) . ' remains to be paid for Run #' . $payrollRunId . '.';
    }

} catch (Exception $e) {
    $db->rollBack();
    error_log("Payment batch creation failed: " . $e->getMessage());
    $_SESSION['flash']['error'] = 'Failed to create payment batch: ' . $e->getMessage();
}

redirect_to('index.php');