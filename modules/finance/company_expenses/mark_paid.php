<?php
$pageTitle = 'Mark Company Expense as Paid';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/finance_helpers.php';

if (!hasPermission('company_expenses.pay')) { die('Access Denied'); }

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

$stmt = $db->prepare("
    SELECT ce.*, ec.default_expense_account_id
    FROM company_expenses ce
    LEFT JOIN expense_categories ec ON ce.category_id = ec.id
    WHERE ce.id = ?
");
$stmt->execute([$expense_id]);
$expense = $stmt->fetch();

if (!$expense) { flash('error', 'Expense not found.'); redirect('index.php'); }
if ($expense['status'] !== 'approved') { flash('error', 'Only approved expenses can be marked as paid.'); redirect('view.php?id=' . $expense_id); }

try {
    $db->beginTransaction();

    // Expense Payable account (the account credited at approval is debited now)
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key = 'finance_expense_payable_account_id'");
    $expPayableAccountId = 0;
    foreach ($stmt->fetchAll() as $row) {
        if ($row['setting_key'] === 'finance_expense_payable_account_id') $expPayableAccountId = (int)$row['setting_value'];
    }
    if ($expPayableAccountId <= 0) {
        throw new Exception('Expense Payable account not configured. Go to Finance Settings.');
    }

    // Resolve the payment credit account from the payment method recorded on the expense
    $creditAccountId = 0;
    $paymentMethod = $expense['payment_method'];
    if (in_array($paymentMethod, ['bank_transfer', 'cheque', 'credit_card'])) {
        if ((int)$expense['bank_account_id'] <= 0) {
            throw new Exception('No bank account is set on this expense. Edit it and select a payment account before marking it paid.');
        }
        $stmt = $db->prepare("SELECT chart_account_id FROM bank_accounts WHERE id = ? AND status = 'active'");
        $stmt->execute([(int)$expense['bank_account_id']]);
        $creditAccountId = (int)$stmt->fetchColumn();
    } elseif ($paymentMethod === 'cash') {
        if ((int)$expense['cash_account_id'] <= 0) {
            throw new Exception('No cash account is set on this expense. Edit it and select a payment account before marking it paid.');
        }
        $stmt = $db->prepare("SELECT chart_account_id FROM cash_accounts WHERE id = ? AND status = 'active'");
        $stmt->execute([(int)$expense['cash_account_id']]);
        $creditAccountId = (int)$stmt->fetchColumn();
    } else {
        throw new Exception('This expense has no payment method set. Edit it and select a payment account before marking it paid.');
    }
    if ($creditAccountId <= 0) {
        throw new Exception('The payment account has no GL mapping. Configure its chart account link first.');
    }

    // Get/create Expense Payment voucher type for voucher numbering
    $stmt = $db->prepare("SELECT id, voucher_prefix FROM voucher_types WHERE voucher_name = 'Expense Payment' LIMIT 1");
    $stmt->execute();
    $vt = $stmt->fetch();
    if (!$vt) {
        $stmt = $db->prepare("INSERT INTO voucher_types (voucher_name, voucher_prefix) VALUES ('Expense Payment', 'EP')");
        $stmt->execute();
        $voucherTypeId = (int)$db->lastInsertId();
    } else {
        $voucherTypeId = (int)$vt['id'];
    }

    $voucherNo = generateVoucherNo($voucherTypeId, $db);
    if (!$voucherNo) {
        throw new Exception('Failed to generate voucher number.');
    }

    $totalAmount = (float)$expense['total_amount'];

    // Create payment journal entry
    $stmt = $db->prepare("
        INSERT INTO journal_entries (voucher_no, voucher_type_id, voucher_date, description, status, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'draft', ?, NOW(), NOW())
    ");
    $stmt->execute([
        $voucherNo, $voucherTypeId, $expense['expense_date'],
        'Expense Payment #' . $expense['expense_number'] . ' - ' . ($expense['description'] ?? ''),
        $_SESSION['user_id']
    ]);
    $journalId = (int)$db->lastInsertId();

    $detailStmt = $db->prepare("
        INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description)
        VALUES (?, ?, ?, ?, ?)
    ");

    // DR: Expense Payable
    $detailStmt->execute([
        $journalId, $expPayableAccountId, $totalAmount, 0,
        'Expense #' . $expense['expense_number'] . ' payment'
    ]);

    // CR: Bank / Cash
    $detailStmt->execute([
        $journalId, $creditAccountId, 0, $totalAmount,
        'Expense #' . $expense['expense_number'] . ' payment'
    ]);

    // Post through PostingEngine
    require_once __DIR__ . '/../posting_engine/PostingEngine.php';
    $result = PostingEngine::post($journalId, $_SESSION['user_id']);
    if (!$result['success']) {
        throw new Exception($result['message']);
    }

    // Update expense status (guarded — prevents double-payment under concurrent requests)
    // payment_journal_id keeps the payment posting traceable from the expense row
    // (journal_entry_id retains the approval posting).
    $stmt = $db->prepare("UPDATE company_expenses SET status = 'paid', payment_journal_id = ?, updated_at = NOW() WHERE id = ? AND status = 'approved'");
    $stmt->execute([$journalId, $expense_id]);
    if ($stmt->rowCount() === 0) {
        throw new Exception('Expense was already paid or changed by another user.');
    }

    // Record payment in approval history
    $stmt = $db->prepare("INSERT INTO expense_approval_history (expense_type, expense_id, approval_level, action, comments, action_by) VALUES ('company_expense', ?, 'payment', 'approved', ?, ?)");
    $stmt->execute([$expense_id, 'Marked as paid. Voucher: ' . $voucherNo, $_SESSION['user_id']]);

    $db->commit();
    logAudit($_SESSION['user_id'], 'Marked company expense as paid: ' . $expense['expense_number'] . ' (Voucher: ' . $voucherNo . ')', 'company_expenses');
    flash('success', 'Expense marked as paid. Voucher #' . $voucherNo, 'success');
} catch (Exception $e) {
    $db->rollBack();
    flash('error', 'Failed to mark expense as paid: ' . $e->getMessage());
}

redirect('view.php?id=' . $expense_id);
