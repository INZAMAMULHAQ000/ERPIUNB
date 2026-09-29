<?php
/**
 * Posting Engine — THE single accounting engine for the entire ERP.
 *
 * Every financial transaction MUST flow through this class.
 * Future modules: Invoices, Payments, Expenses, Payroll, etc.
 * must call PostingEngine::post() — NEVER write to general_ledger directly.
 */

require_once __DIR__ . '/../../../helpers/security.php';
require_once __DIR__ . '/../helpers/finance_helpers.php';

class PostingEngine {

    /**
     * Post a draft journal entry to the general ledger.
     *
     * @param int   $journalId
     * @param int   $userId
     * @param array $options Optional. ['bypass_period_lock' => true] may ONLY be
     *                       used internally by the F12 year-end closing engine
     *                       (periods are closed before the closing JE is posted).
     * @return array ['success' => bool, 'message' => string]
     */
    public static function post($journalId, $userId, $options = []) {
        $db = getDB();

        try {
            // === 1. Load journal ===
            $stmt = $db->prepare("SELECT * FROM journal_entries WHERE id = ? FOR UPDATE");
            $stmt->execute([$journalId]);
            $journal = $stmt->fetch();

            if (!$journal) {
                return ['success' => false, 'message' => 'Journal entry not found.'];
            }
            if ($journal['status'] !== 'draft') {
                return ['success' => false, 'message' => 'Only draft journals can be posted. Current status: ' . $journal['status']];
            }

            // === 2. Validate financial year ===
            $jeCompanyId = (int)($journal['company_id'] ?? 1);
            $postDate = $journal['voucher_date'];
            $fy = getFinancialYearForDate($db, $postDate, $jeCompanyId, true);
            if (!$fy) {
                $fy = getFinancialYearForDate($db, $postDate, null, true);
            }
            if (!$fy) {
                return ['success' => false, 'message' => 'No active financial year found.'];
            }
            if ($postDate < $fy['start_date'] || $postDate > $fy['end_date']) {
                return ['success' => false, 'message' => "Posting date $postDate is outside active financial year ({$fy['start_date']} to {$fy['end_date']})."];
            }
            if ((int)$fy['company_id'] !== $jeCompanyId) {
                $cFy = getFinancialYearForDate($db, $postDate, $jeCompanyId, true);
                if (!$cFy) {
                    return ['success' => false, 'message' =>
                        "Journal belongs to company #$jeCompanyId but no active financial year for that company covers $postDate."];
                }
                $fy = $cFy;
            }

            // === 2b. F12 PERIOD LOCK — block posting into closed/closing periods ===
            if (empty($options['bypass_period_lock'])) {
                require_once __DIR__ . '/../period_close/PeriodCloseService.php';
                $period = PeriodCloseService::findPeriodForDate($db, $fy['id'], $postDate);
                if ($period && in_array($period['status'], ['closing', 'closed'], true)) {
                    return ['success' => false, 'message' =>
                        "Period \"{$period['period_name']}\" is {$period['status']}. Posting into a locked period is not allowed. "
                        . 'Use an authorized closing adjustment or ask an administrator to reopen the period.'];
                }
            }

            // === 3. Load journal lines ===
            $stmt = $db->prepare("SELECT * FROM journal_entry_details WHERE journal_entry_id = ?");
            $stmt->execute([$journalId]);
            $lines = $stmt->fetchAll();

            if (empty($lines)) {
                return ['success' => false, 'message' => 'Journal has no lines.'];
            }

            // === 4. Validate debit = credit ===
            $totalDebit = 0;
            $totalCredit = 0;
            foreach ($lines as $line) {
                $totalDebit += (float)$line['debit_amount'];
                $totalCredit += (float)$line['credit_amount'];
            }
            if (abs($totalDebit - $totalCredit) > 0.001) {
                return ['success' => false, 'message' => "Debit ($totalDebit) does not equal Credit ($totalCredit)."];
            }

            // === 5. Validate all accounts are active ===
            $accountIds = array_column($lines, 'account_id');
            $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
            $stmt = $db->prepare("SELECT id, account_code, account_name, status FROM chart_of_accounts WHERE id IN ($placeholders)");
            $stmt->execute($accountIds);
            $accounts = $stmt->fetchAll();
            $accountMap = [];
            foreach ($accounts as $a) {
                $accountMap[$a['id']] = $a;
            }
            foreach ($accountIds as $aid) {
                if (!isset($accountMap[$aid])) {
                    return ['success' => false, 'message' => "Account ID $aid not found."];
                }
                if ($accountMap[$aid]['status'] !== 'active') {
                    return ['success' => false, 'message' => "Account '{$accountMap[$aid]['account_code']} - {$accountMap[$aid]['account_name']}' is not active."];
                }
            }

            // === 6. Get voucher type name for GL ===
            $stmt = $db->prepare("SELECT voucher_name FROM voucher_types WHERE id = ?");
            $stmt->execute([$journal['voucher_type_id']]);
            $voucherType = $stmt->fetch();
            $voucherTypeName = $voucherType ? $voucherType['voucher_name'] : 'Unknown';

            // === 7. BEGIN TRANSACTION (support nested calls) ===
            $inTransaction = $db->inTransaction();
            if (!$inTransaction) {
                $db->beginTransaction();
            }

            foreach ($lines as $line) {
                // Get last running balance for this account in this FY
                $stmt = $db->prepare("
                    SELECT running_balance FROM general_ledger
                    WHERE account_id = ? AND financial_year_id = ?
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt->execute([$line['account_id'], $fy['id']]);
                $lastGl = $stmt->fetch();

                $lastBalance = $lastGl ? (float)$lastGl['running_balance'] : 0.00;

                // For debit accounts (asset/expense): debit increases, credit decreases
                // For credit accounts (liability/equity/income): credit increases, debit decreases
                // We use the natural balance approach:
                $stmt = $db->prepare("SELECT nature FROM account_groups WHERE id = (SELECT group_id FROM chart_of_accounts WHERE id = ?)");
                $stmt->execute([$line['account_id']]);
                $group = $stmt->fetch();
                $nature = $group ? $group['nature'] : 'asset';

                // Calculate running balance using double-entry logic
                $debit = (float)$line['debit_amount'];
                $credit = (float)$line['credit_amount'];
                $newBalance = $lastBalance + $debit - $credit;

                $stmt = $db->prepare("
                    INSERT INTO general_ledger (posting_date, journal_entry_id, account_id, company_id, voucher_type, voucher_no, debit, credit, running_balance, financial_year_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $journal['voucher_date'],
                    $journalId,
                    $line['account_id'],
                    $jeCompanyId,
                    $voucherTypeName,
                    $journal['voucher_no'],
                    $debit,
                    $credit,
                    $newBalance,
                    $fy['id'],
                ]);
            }

            // Update journal status
            $stmt = $db->prepare("UPDATE journal_entries SET status = 'posted', posted_at = NOW() WHERE id = ?");
            $stmt->execute([$journalId]);

            if (!$inTransaction) {
                $db->commit();
            }

            // Audit log
            logAudit($userId, 'Posted journal: ' . $journal['voucher_no'], 'finance');

            return ['success' => true, 'message' => 'Journal posted successfully.', 'voucher_no' => $journal['voucher_no']];

        } catch (Exception $e) {
            if (!$inTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            return ['success' => false, 'message' => 'Posting failed: ' . $e->getMessage()];
        }
    }

    /**
     * Reverse a posted journal (creates a reversal entry).
     *
     * @param int    $journalId
     * @param int    $userId
     * @param string $reason
     * @param array  $options Optional. ['bypass_period_lock' => true] for the F12 engine only.
     * @return array ['success' => bool, 'message' => string]
     */
    public static function reverse($journalId, $userId, $reason = '', $options = []) {
        $db = getDB();

        try {
            $stmt = $db->prepare("SELECT * FROM journal_entries WHERE id = ? FOR UPDATE");
            $stmt->execute([$journalId]);
            $journal = $stmt->fetch();

            if (!$journal) {
                return ['success' => false, 'message' => 'Journal entry not found.'];
            }
            if ($journal['status'] !== 'posted') {
                return ['success' => false, 'message' => 'Only posted journals can be reversed.'];
            }

            // F12 PERIOD LOCK — reversals post into today's date; respect locks too.
            if (empty($options['bypass_period_lock'])) {
                require_once __DIR__ . '/../period_close/PeriodCloseService.php';
                $fyChk = getActiveFinancialYear($db);
                if ($fyChk) {
                    $period = PeriodCloseService::findPeriodForDate($db, $fyChk['id'], date('Y-m-d'));
                    if ($period && in_array($period['status'], ['closing', 'closed'], true)) {
                        return ['success' => false, 'message' =>
                            "Current period \"{$period['period_name']}\" is {$period['status']}. Reversal blocked."];
                    }
                }
            }

            $stmt = $db->prepare("SELECT * FROM journal_entry_details WHERE journal_entry_id = ?");
            $stmt->execute([$journalId]);
            $lines = $stmt->fetchAll();

            // F13: reversal belongs to the ORIGINAL journal's company — use that company's active FY
            $fy = getFinancialYearForDate($db, date('Y-m-d'), (int)($journal['company_id'] ?? 1), true)
               ?: getFinancialYearForDate($db, date('Y-m-d'), null, true)
               ?: getActiveFinancialYear($db);
            if (!$fy) {
                return ['success' => false, 'message' => 'No active financial year found.'];
            }

            $stmt = $db->prepare("SELECT voucher_name, voucher_prefix FROM voucher_types WHERE id = ?");
            $stmt->execute([$journal['voucher_type_id']]);
            $voucherType = $stmt->fetch();

            // BEGIN TRANSACTION (support nested calls — mirror post())
            $inTransaction = $db->inTransaction();
            if (!$inTransaction) {
                $db->beginTransaction();
            }

            // Create reversal journal
            $reversalNo = $journal['voucher_no'] . '-REV';
            $stmt = $db->prepare("
                INSERT INTO journal_entries (company_id, voucher_no, voucher_type_id, voucher_date, reference_no, description, status, created_by, posted_at)
                VALUES (?, ?, ?, ?, ?, ?, 'posted', ?, NOW())
            ");
            $stmt->execute([
                (int)($journal['company_id'] ?? 1),
                $reversalNo,
                $journal['voucher_type_id'],
                date('Y-m-d'),
                $journal['voucher_no'],
                'Reversal: ' . ($reason ?: $journal['description']),
                $userId,
            ]);
            $reversalId = (int)$db->lastInsertId();

            // Reverse each line (swap debits and credits)
            $voucherTypeName = $voucherType ? $voucherType['voucher_name'] : 'Unknown';
            foreach ($lines as $line) {
                $stmt = $db->prepare("
                    SELECT running_balance FROM general_ledger
                    WHERE account_id = ? AND financial_year_id = ?
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt->execute([$line['account_id'], $fy['id']]);
                $lastGl = $stmt->fetch();
                $lastBalance = $lastGl ? (float)$lastGl['running_balance'] : 0.00;

                $debit = (float)$line['credit_amount'];  // Swapped
                $credit = (float)$line['debit_amount'];  // Swapped
                $newBalance = $lastBalance + $debit - $credit;

                $stmt = $db->prepare("
                    INSERT INTO general_ledger (posting_date, journal_entry_id, account_id, company_id, voucher_type, voucher_no, debit, credit, running_balance, financial_year_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $revCompanyId = (int)$db->query("SELECT company_id FROM journal_entries WHERE id = " . (int)$reversalId)->fetchColumn();
                $stmt->execute([date('Y-m-d'), $reversalId, $line['account_id'], $revCompanyId ?: 1, $voucherTypeName . ' (Reversal)', $reversalNo, $debit, $credit, $newBalance, $fy['id']]);
            }

            // Mark original as reversed
            $stmt = $db->prepare("UPDATE journal_entries SET status = 'reversed' WHERE id = ?");
            $stmt->execute([$journalId]);

            if (!$inTransaction) {
                $db->commit();
            }

            logAudit($userId, 'Reversed journal: ' . $journal['voucher_no'] . ' -> ' . $reversalNo, 'finance');

            return ['success' => true, 'message' => 'Journal reversed successfully.', 'reversal_no' => $reversalNo];

        } catch (Exception $e) {
            if (!$inTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            return ['success' => false, 'message' => 'Reversal failed: ' . $e->getMessage()];
        }
    }

    /**
     * Trial posting — run all validations without writing.
     *
     * @param int $journalId
     * @return array ['valid' => bool, 'errors' => string[]]
     */
    public static function validate($journalId) {
        $db = getDB();
        $errors = [];

        $stmt = $db->prepare("SELECT * FROM journal_entries WHERE id = ?");
        $stmt->execute([$journalId]);
        $journal = $stmt->fetch();

        if (!$journal) {
            return ['valid' => false, 'errors' => ['Journal entry not found.']];
        }

        if ($journal['status'] !== 'draft') {
            $errors[] = 'Only draft journals can be posted.';
        }

        $jeCompanyId = (int)($journal['company_id'] ?? 1);
        $fy = getFinancialYearForDate($db, $journal['voucher_date'], $jeCompanyId, true)
           ?: getFinancialYearForDate($db, $journal['voucher_date'], null, true)
           ?: getActiveFinancialYear($db);
        if (!$fy) {
            $errors[] = 'No active financial year found.';
        } elseif ($journal['voucher_date'] < $fy['start_date'] || $journal['voucher_date'] > $fy['end_date']) {
            $errors[] = 'Posting date is outside active financial year.';
        } else {
            // F12 period lock preview
            require_once __DIR__ . '/../period_close/PeriodCloseService.php';
            $period = PeriodCloseService::findPeriodForDate($db, $fy['id'], $journal['voucher_date']);
            if ($period && in_array($period['status'], ['closing', 'closed'], true)) {
                $errors[] = "Period \"{$period['period_name']}\" is {$period['status']} (locked).";
            }
        }

        $stmt = $db->prepare("SELECT * FROM journal_entry_details WHERE journal_entry_id = ?");
        $stmt->execute([$journalId]);
        $lines = $stmt->fetchAll();

        if (empty($lines)) {
            $errors[] = 'Journal has no lines.';
        }

        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($lines as $line) {
            $totalDebit += (float)$line['debit_amount'];
            $totalCredit += (float)$line['credit_amount'];
        }
        if (abs($totalDebit - $totalCredit) > 0.001) {
            $errors[] = "Debit ($totalDebit) does not equal Credit ($totalCredit).";
        }

        foreach ($lines as $line) {
            $stmt = $db->prepare("SELECT status FROM chart_of_accounts WHERE id = ?");
            $stmt->execute([$line['account_id']]);
            $acct = $stmt->fetch();
            if (!$acct) {
                $errors[] = "Account ID {$line['account_id']} not found.";
            } elseif ($acct['status'] !== 'active') {
                $errors[] = "Account ID {$line['account_id']} is not active.";
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }
}
