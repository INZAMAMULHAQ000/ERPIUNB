<?php
$pageTitle = 'Approve Company Expense';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../helpers/finance_helpers.php';

if (!hasPermission('company_expenses.approve')) { die('Access Denied'); }

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
if ($expense['status'] !== 'pending_approval') { flash('error', 'Only expenses pending approval can be approved.'); redirect('view.php?id=' . $expense_id); }

try {
    $db->beginTransaction();

    // Load settings
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('finance_expense_payable_account_id')");
    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['setting_key']] = (int)$row['setting_value'];
    }

    $expPayableAccountId = $settings['finance_expense_payable_account_id'] ?? 0;
    if ($expPayableAccountId <= 0) {
        throw new Exception('Expense Payable account not configured. Go to Finance Settings to set it up.');
    }

    // Determine expense account: use category's default, or find one
    $expenseAccountId = (int)$expense['default_expense_account_id'];
    if ($expenseAccountId <= 0) {
        // Fallback: find any active expense account
        $stmt = $db->query("
            SELECT coa.id FROM chart_of_accounts coa
            JOIN account_types at ON coa.type_id = at.id
            WHERE at.type_name = 'Expense' AND coa.status = 'active'
            LIMIT 1
        ");
        $expenseAccountId = (int)$stmt->fetchColumn();
    }
    if ($expenseAccountId <= 0) {
        throw new Exception('No expense GL account found. Please create an expense account in Chart of Accounts and assign it to the expense category.');
    }

    // Get Expense voucher type for voucher numbering
    $stmt = $db->prepare("SELECT id, voucher_prefix FROM voucher_types WHERE voucher_name = 'Expense Voucher' LIMIT 1");
    $stmt->execute();
    $vt = $stmt->fetch();
    if (!$vt) {
        // Create Expense Voucher type on the fly
        $stmt = $db->prepare("INSERT INTO voucher_types (voucher_name, voucher_prefix) VALUES ('Expense Voucher', 'EXP')");
        $stmt->execute();
        $voucherTypeId = (int)$db->lastInsertId();
    } else {
        $voucherTypeId = (int)$vt['id'];
    }

    // Generate voucher number
    $voucherNo = generateVoucherNo($voucherTypeId, $db);
    if (!$voucherNo) {
        throw new Exception('Failed to generate voucher number.');
    }

    $totalAmount = (float)$expense['total_amount'];
    $taxAmount   = (float)$expense['tax_amount'];
    $netAmount   = $totalAmount - $taxAmount;

    // Create journal entry
    $stmt = $db->prepare("
        INSERT INTO journal_entries (voucher_no, voucher_type_id, voucher_date, description, status, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'draft', ?, NOW(), NOW())
    ");
    $stmt->execute([
        $voucherNo, $voucherTypeId, $expense['expense_date'],
        'Company Expense #' . $expense['expense_number'] . ' - ' . ($expense['description'] ?? ''),
        $_SESSION['user_id']
    ]);
    $journalId = (int)$db->lastInsertId();

    // Add journal lines
    $detailStmt = $db->prepare("
        INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description)
        VALUES (?, ?, ?, ?, ?)
    ");

    // DR: Expense Account (tax-exclusive amount)
    $detailStmt->execute([
        $journalId, $expenseAccountId, $netAmount, 0,
        'Expense #' . $expense['expense_number'] . ' - ' . ($expense['description'] ?? '')
    ]);

    // DR: Input Tax Account (if any tax on the document)
    if ($taxAmount > 0) {
        require_once '../tax/helpers/tax_ui.php';
        $inputTaxAccountId = (int)TaxService::config('input_tax_account_id', 0);
        if ($inputTaxAccountId <= 0) {
            throw new Exception('Input Tax account not configured. Go to Tax Settings to set it up.');
        }
        $detailStmt->execute([
            $journalId, $inputTaxAccountId, $taxAmount, 0,
            'Input Tax #' . $expense['expense_number']
        ]);
    }

    // CR: Expense Payable
    $detailStmt->execute([
        $journalId, $expPayableAccountId, 0, $totalAmount,
        'Expense #' . $expense['expense_number']
    ]);

    // Register the input tax transaction in the tax register.
    require_once '../tax/helpers/tax_ui.php';
    if ((int)$expense['tax_type_id'] && $expense['taxable_amount'] > 0) {
        // Resolve the stored rate's percentage for the register (historical rate preserved).
        $ratePct = 0.0;
        if ((int)$expense['tax_rate_id']) {
            $rStmt = $db->prepare("SELECT rate_percentage FROM tax_rates WHERE id = ?");
            $rStmt->execute([(int)$expense['tax_rate_id']]);
            $ratePct = (float)($rStmt->fetchColumn() ?: 0);
        }
        TaxService::recordDocumentTax([
            'source_module'      => 'company_expense',
            'source_record_id'   => $expense_id,
            'source_document_no' => $expense['expense_number'],
            'transaction_date'   => $expense['expense_date'],
            'tax_type_id'        => (int)$expense['tax_type_id'],
            'direction'          => 'input',
            'counterparty_type'  => $expense['vendor_id'] ? 'vendor' : 'none',
            'counterparty_id'    => $expense['vendor_id'] ? (int)$expense['vendor_id'] : null,
            'created_by'         => $_SESSION['user_id'],
            'lines'              => [[
                'taxable_amount' => (float)$expense['taxable_amount'],
                'tax_amount'     => (float)$expense['tax_amount'],
                'tax_rate'       => $ratePct,
                'tax_rate_id'    => $expense['tax_rate_id'] ? (int)$expense['tax_rate_id'] : null,
                'tax_group_id'   => $expense['tax_group_id'] ? (int)$expense['tax_group_id'] : null,
                'tax_status'     => $expense['tax_status'] ?? 'taxable',
                'exemption_id'   => $expense['tax_exemption_id'] ? (int)$expense['tax_exemption_id'] : null,
            ]],
        ]);
    }

    // Post through PostingEngine
    require_once __DIR__ . '/../posting_engine/PostingEngine.php';
    $result = PostingEngine::post($journalId, $_SESSION['user_id']);

    if (!$result['success']) {
        throw new Exception($result['message']);
    }

    // Update expense status (guarded — prevents double-approval under concurrent requests)
    $stmt = $db->prepare("UPDATE company_expenses SET status = 'approved', journal_entry_id = ?, updated_at = NOW() WHERE id = ? AND status = 'pending_approval'");
    $stmt->execute([$journalId, $expense_id]);
    if ($stmt->rowCount() === 0) {
        throw new Exception('Expense was already approved or changed by another user.');
    }

    // Record approval history
    $stmt = $db->prepare("INSERT INTO expense_approval_history (expense_type, expense_id, approval_level, action, comments, action_by) VALUES ('company_expense', ?, 'approval', 'approved', ?, ?)");
    $stmt->execute([$expense_id, 'Approved and posted to GL. Voucher: ' . $voucherNo, $_SESSION['user_id']]);

    $db->commit();
    logAudit($_SESSION['user_id'], 'Approved company expense: ' . $expense['expense_number'] . ' (Voucher: ' . $voucherNo . ')', 'company_expenses');
    flash('success', 'Expense approved and posted to GL. Voucher #' . $voucherNo, 'success');
} catch (Exception $e) {
    $db->rollBack();
    flash('error', 'Failed to approve expense: ' . $e->getMessage());
}

redirect('view.php?id=' . $expense_id);
