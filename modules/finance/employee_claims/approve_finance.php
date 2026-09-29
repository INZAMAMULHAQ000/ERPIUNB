<?php
$pageTitle = 'Finance Approve Claim';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../helpers/finance_helpers.php';

if (!hasPermission('employee_claims.approve')) { die('Access Denied'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { flash('error', 'Invalid request method.'); redirect('index.php'); }
if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid or expired form token.'); redirect('index.php'); }

$db = getDB();
$claim_id = (int)($_POST['claim_id'] ?? 0);
if ($claim_id <= 0) { flash('error', 'Invalid claim ID.'); redirect('index.php'); }

$stmt = $db->prepare("
    SELECT ec.*, cat.default_expense_account_id
    FROM employee_expense_claims ec
    LEFT JOIN expense_categories cat ON ec.category_id = cat.id
    WHERE ec.id = ?
");
$stmt->execute([$claim_id]);
$claim = $stmt->fetch();
if (!$claim) { flash('error', 'Claim not found.'); redirect('index.php'); }
if ($claim['status'] !== 'pending_finance') { flash('error', 'Claim is not pending finance approval.'); redirect('view.php?id=' . $claim_id); }

try {
    $db->beginTransaction();

    // Load settings
    $settings = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('finance_expense_payable_account_id')")->fetchAll();
    $expPayableAccountId = 0;
    foreach ($settings as $s) {
        if ($s['setting_key'] === 'finance_expense_payable_account_id') $expPayableAccountId = (int)$s['setting_value'];
    }
    if ($expPayableAccountId <= 0) {
        throw new Exception('Expense Payable account not configured. Go to Finance Settings.');
    }

    // Determine expense account
    $expenseAccountId = (int)$claim['default_expense_account_id'];
    if ($expenseAccountId <= 0) {
        $tmp = $db->query("
            SELECT coa.id FROM chart_of_accounts coa
            JOIN account_types at ON coa.type_id = at.id
            WHERE at.type_name = 'Expense' AND coa.status = 'active' LIMIT 1
        ")->fetch();
        $expenseAccountId = $tmp ? (int)$tmp['id'] : 0;
    }
    if ($expenseAccountId <= 0) {
        throw new Exception('No expense GL account found. Create an expense account and assign it to the category.');
    }

    // Get / create Expense Voucher type
    $vt = $db->prepare("SELECT id, voucher_prefix FROM voucher_types WHERE voucher_name = 'Expense Voucher' LIMIT 1");
    $vt->execute();
    $vtRow = $vt->fetch();
    if (!$vtRow) {
        $db->prepare("INSERT INTO voucher_types (voucher_name, voucher_prefix) VALUES ('Expense Voucher', 'EXP')")->execute();
        $voucherTypeId = (int)$db->lastInsertId();
    } else {
        $voucherTypeId = (int)$vtRow['id'];
    }

    $voucherNo = generateVoucherNo($voucherTypeId, $db);
    if (!$voucherNo) throw new Exception('Failed to generate voucher number.');

    $totalAmount = (float)$claim['total_amount'];

    // Create journal entry
    $stmt = $db->prepare("
        INSERT INTO journal_entries (voucher_no, voucher_type_id, voucher_date, description, status, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'draft', ?, NOW(), NOW())
    ");
    $stmt->execute([
        $voucherNo, $voucherTypeId, $claim['expense_date'],
        'Employee Claim #' . $claim['claim_number'] . ' - ' . ($claim['description'] ?? ''),
        $_SESSION['user_id']
    ]);
    $journalId = (int)$db->lastInsertId();

    // Journal lines
    $detailStmt = $db->prepare("INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description) VALUES (?, ?, ?, ?, ?)");
    $detailStmt->execute([$journalId, $expenseAccountId, $totalAmount, 0, 'Claim #' . $claim['claim_number'] . ' - ' . ($claim['description'] ?? '')]);
    $detailStmt->execute([$journalId, $expPayableAccountId, 0, $totalAmount, 'Claim #' . $claim['claim_number']]);

    // Post
    require_once __DIR__ . '/../posting_engine/PostingEngine.php';
    $result = PostingEngine::post($journalId, $_SESSION['user_id']);
    if (!$result['success']) throw new Exception($result['message']);

    // Update claim (guarded — prevents double-approval under concurrent requests)
    $stmt = $db->prepare("UPDATE employee_expense_claims SET status = 'approved', journal_entry_id = ?, finance_approved_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'pending_finance'");
    $stmt->execute([$journalId, $claim_id]);
    if ($stmt->rowCount() === 0) {
        throw new Exception('Claim was already approved or changed by another user.');
    }

    $stmt = $db->prepare("INSERT INTO expense_approval_history (expense_type, expense_id, approval_level, action, comments, action_by) VALUES ('employee_claim', ?, 'finance_approval', 'approved', ?, ?)");
    $stmt->execute([$claim_id, 'Finance approved and posted. Voucher: ' . $voucherNo, $_SESSION['user_id']]);

    $db->commit();
    logAudit($_SESSION['user_id'], 'Finance approved claim: ' . $claim['claim_number'] . ' (Voucher: ' . $voucherNo . ')', 'employee_claims');
    flash('success', 'Claim approved and posted to GL. Voucher #' . $voucherNo, 'success');
} catch (Exception $e) {
    $db->rollBack();
    flash('error', 'Failed to approve claim: ' . $e->getMessage());
}

redirect('view.php?id=' . $claim_id);
