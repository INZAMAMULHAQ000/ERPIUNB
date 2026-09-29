<?php
/**
 * Payroll Posting Process
 * Handle payroll posting - generate journal entries and post to GL
 * Phase F10 - Payroll & Finance Integration
 */

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../helpers/security.php';
require_once __DIR__ . '/../../../../helpers/rbac.php';
require_once __DIR__ . '/../../../../helpers/redirect.php';
require_once __DIR__ . '/../../../../middleware/auth.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

requireAuth();

if (!hasPermission('payroll_finance.post')) {
    redirect_to('no-access.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('index.php');
}

$db = getDB();
$action = $_POST['action'] ?? 'create';

switch ($action) {
    case 'create_posting':
        $payrollRunId = (int)($_POST['payroll_run_id'] ?? 0);
        $postingDate = $_POST['posting_date'] ?? date('Y-m-d');

        if ($payrollRunId <= 0 || $postingDate === '') {
            $_SESSION['flash']['error'] = 'Invalid payroll run or posting date.';
            redirect_to('create.php');
        }

        // Check if already posted
        $existing = $db->prepare("SELECT id FROM payroll_finance_postings WHERE payroll_run_id = ? AND status = 'Posted'");
        $existing->execute([$payrollRunId]);
        if ($existing->fetch()) {
            $_SESSION['flash']['error'] = 'This payroll run has already been posted to finance.';
            redirect_to('index.php');
        }

        // Get payroll data from reconciliation table (source of truth for payroll totals)
        $runStmt = $db->prepare("SELECT payroll_total, posted_total, status FROM payroll_reconciliation WHERE payroll_run_id = ? ORDER BY id DESC LIMIT 1");
        $runStmt->execute([$payrollRunId]);
        $run = $runStmt->fetch();

        if (!$run) {
            $_SESSION['flash']['error'] = 'No reconciliation record found for this payroll run. Please run payroll reconciliation first.';
            redirect_to('../reconciliation/');
        }

        $payrollTotal = (float)$run['payroll_total'];
        $postedTotal = (float)$run['posted_total'];
        $net = $payrollTotal;
        $totalDeductions = $payrollTotal - $net;

        if ($net <= 0) {
            $_SESSION['flash']['error'] = 'Payroll run has no calculated total to post.';
            redirect_to('create.php');
        }

        // Resolve debit account (expense) from active mappings
        $debitStmt = $db->prepare("
            SELECT pfm.chart_account_id, coa.account_code, coa.account_name
            FROM payroll_finance_mappings pfm
            JOIN chart_of_accounts coa ON pfm.chart_account_id = coa.id
            WHERE pfm.mapping_type = 'expense' AND pfm.status = 'Active'
            ORDER BY pfm.id ASC LIMIT 1
        ");
        $debitStmt->execute();
        $debit = $debitStmt->fetch();

        // Resolve credit account (liability) from active mappings
        $creditStmt = $db->prepare("
            SELECT pfm.chart_account_id, coa.account_code, coa.account_name
            FROM payroll_finance_mappings pfm
            JOIN chart_of_accounts coa ON pfm.chart_account_id = coa.id
            WHERE pfm.mapping_type = 'liability' AND pfm.status = 'Active'
            ORDER BY pfm.id ASC LIMIT 1
        ");
        $creditStmt->execute();
        $credit = $creditStmt->fetch();

        // Fallback to chart of accounts search if no mapping configured
        if (!$debit) {
            $debit = $db->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE account_name LIKE '%Salary%Expense%' AND status = 'active' ORDER BY id LIMIT 1")->fetch();
        }
        if (!$credit) {
            $credit = $db->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE account_name LIKE '%Salary%Payable%' AND status = 'active' ORDER BY id LIMIT 1")->fetch();
            if (!$credit) {
                $credit = $db->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE account_name LIKE '%Payable%' AND status = 'active' ORDER BY id LIMIT 1")->fetch();
            }
        }

        if (!$debit || !$credit) {
            $_SESSION['flash']['error'] = 'No GL accounts configured. Please add at least one expense and one liability mapping under GL Mappings first.';
            redirect_to('../mappings/');
        }

        $voucherTypeId = (int)$db->query("SELECT id FROM voucher_types ORDER BY id LIMIT 1")->fetchColumn();
        $voucherNo = 'JV-' . date('YmdHis') . '-' . $payrollRunId;
        $description = 'Payroll posting for run #' . $payrollRunId . ' | Net: AED ' . number_format($net, 2);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $clientIP = getClientIP();

        $db->beginTransaction();

        try {
            // Create journal entry
            $db->prepare("
                INSERT INTO journal_entries (voucher_no, voucher_type_id, voucher_date, reference_no, description, status, created_by)
                VALUES (?, ?, ?, ?, ?, 'posted', ?)
            ")->execute([$voucherNo, $voucherTypeId, $postingDate, 'PAYROLL-RUN-' . $payrollRunId, $description, $userId]);

            $journalId = (int)$db->lastInsertId();

            // Debit line - Salary Expense
            $db->prepare("
                INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description)
                VALUES (?, ?, ?, 0, ?)
            ")->execute([$journalId, $debit['id'], $net, 'Salary expense - payroll run #' . $payrollRunId]);

            // Credit line - Salary Payable (Liability)
            $db->prepare("
                INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description)
                VALUES (?, ?, 0, ?, ?)
            ")->execute([$journalId, $credit['id'], $net, 'Salary payable - payroll run #' . $payrollRunId]);

            // Create finance posting record with all required fields
            $db->prepare("
                INSERT INTO payroll_finance_postings
                    (payroll_run_id, journal_entry_id, posting_date, total_gross, total_deductions, total_net, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 'Posted', ?)
            ")->execute([
                $payrollRunId,
                $journalId,
                $postingDate,
                $net,           // total_gross = net amount (gross = net when no employee deductions breakdown)
                0,              // total_deductions handled at payroll level
                $net,           // total_net
                $userId
            ]);

            // Update reconciliation: mark posted_total
            $db->prepare("
                UPDATE payroll_reconciliation
                SET posted_total = ?
                WHERE payroll_run_id = ? AND status != 'Reconciled'
            ")->execute([$net, $payrollRunId]);

            // Audit log
            logAudit($userId, 'Payroll Posted (Run #' . $payrollRunId . ', Journal #' . $journalId . ')', 'payroll_finance');

            $db->commit();
            $_SESSION['flash']['success'] = 'Payroll posted successfully to General Ledger. Journal Entry #' . $journalId . ' created.';
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Payroll posting failed (run #$payrollRunId): " . $e->getMessage());
            $_SESSION['flash']['error'] = 'Failed to post payroll: ' . $e->getMessage();
        }
        break;
}

redirect_to('index.php');