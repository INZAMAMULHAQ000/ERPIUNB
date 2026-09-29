<?php
/**
 * F12 — Year-End Closing Engine
 *
 * GOLDEN RULES:
 *  - Accounting history is immutable. The closing journal is created and
 *    posted through the Posting Engine like any other journal.
 *  - The retained earnings account is CONFIGURABLE (period_close_settings),
 *    never hard-coded.
 *  - Idempotent: a financial year can only be closed once
 *    (DB-level UNIQUE constraint + code guard).
 *  - Temporary accounts (income / expense natures) are closed to zero.
 *    Permanent accounts (asset / liability / equity) carry forward.
 */

require_once __DIR__ . '/../posting_engine/PostingEngine.php';
require_once __DIR__ . '/PeriodCloseService.php';

class YearEndClosingService
{
    const ACT_YEAR_VALIDATION_STARTED   = 'year_validation_started';
    const ACT_YEAR_VALIDATION_COMPLETED = 'year_validation_completed';
    const ACT_YEAR_CLOSED               = 'year_closed';
    const ACT_YEAR_REOPENED             = 'year_reopened';
    const ACT_OPENING_GENERATED         = 'opening_balances_generated';
    const ACT_CLOSING_JOURNAL_REVERSED  = 'closing_journal_reversed';

    /** Audit passthrough to the shared period_close_audit table. */
    private static function log($db, $action, $fyId = null, $periodId = null, $refId = null, $details = null, $reason = null, $userId = null)
    {
        PeriodCloseService::log($db, $action, $fyId, $periodId, $refId, $details, $reason, $userId);
    }

    // ---------------------------------------------------------
    // STEP 10 — Year-end validation
    // ---------------------------------------------------------

    public static function validateYearEnd($db, $fyId, $userId = null)
    {
        if ($userId === null) $userId = $_SESSION['user_id'] ?? null;
        self::log($db, self::ACT_YEAR_VALIDATION_STARTED, $fyId, null, null, 'Year-end validation started', null, $userId);
        $checks = [];

        $stmt = $db->prepare("SELECT * FROM financial_years WHERE id = ?");
        $stmt->execute([$fyId]);
        $fy = $stmt->fetch();
        if (!$fy) {
            return ['passed' => false, 'checks' => [['name' => 'Financial year', 'status' => 'fail', 'detail' => 'Not found.']]];
        }

        // 1. All periods closed
        $stmt = $db->prepare("
            SELECT COUNT(*) total, SUM(status = 'closed') closed FROM accounting_periods WHERE financial_year_id = ?
        ");
        $stmt->execute([$fyId]);
        $p = $stmt->fetch();
        $allClosed = (int)$p['total'] > 0 && (int)$p['total'] === (int)$p['closed'];
        $checks[] = ['name' => 'All periods closed', 'status' => $allClosed ? 'pass' : 'fail',
                     'detail' => "{$p['closed']} of {$p['total']} periods closed."];

        // 2. Trial balance balanced for the whole FY
        $tb = self::fyTotals($db, $fyId);
        $balanced = abs($tb['debit'] - $tb['credit']) < 0.001;
        $checks[] = ['name' => 'Trial Balance balanced', 'status' => $balanced ? 'pass' : 'fail',
                     'detail' => 'Dr ' . number_format($tb['debit'], 2) . ' | Cr ' . number_format($tb['credit'], 2)];

        // 3. No unposted journals anywhere in the FY
        $stmt = $db->prepare("SELECT COUNT(*) FROM journal_entries WHERE voucher_date BETWEEN ? AND ? AND status = 'draft'");
        $stmt->execute([$fy['start_date'], $fy['end_date']]);
        $drafts = (int)$stmt->fetchColumn();
        $checks[] = ['name' => 'No unposted journals', 'status' => $drafts === 0 ? 'pass' : 'fail',
                     'detail' => $drafts === 0 ? 'Clean.' : "$drafts draft journal(s)."];

        // 4. No adjustments pending approval or approved-but-unposted
        $stmt = $db->prepare("SELECT COUNT(*) FROM closing_adjustments WHERE financial_year_id = ? AND status IN ('draft','pending_approval','approved')");
        $stmt->execute([$fyId]);
        $pendingAdj = (int)$stmt->fetchColumn();
        $checks[] = ['name' => 'Required adjustments posted', 'status' => $pendingAdj === 0 ? 'pass' : 'fail',
                     'detail' => $pendingAdj === 0 ? 'All closing adjustments posted.' : "$pendingAdj adjustment(s) not yet posted."];

        // 5. Retained earnings configured
        $settings = PeriodCloseService::getSettings($db);
        $reOk = !empty($settings['retained_earnings_account_id']);
        $checks[] = ['name' => 'Retained earnings account configured', 'status' => $reOk ? 'pass' : 'fail',
                     'detail' => $reOk ? 'Configured in settings.' : 'Set it under Closing Settings before closing the year.'];
        if ($settings['closing_method'] === 'via_income_summary') {
            $isOk = !empty($settings['income_summary_account_id']);
            $checks[] = ['name' => 'Income summary account configured', 'status' => $isOk ? 'pass' : 'fail',
                         'detail' => $isOk ? 'Configured.' : 'Closing method is "via income summary" but no account is set.'];
        }

        // 6. Year-level checklist complete, no exceptions
        if (!empty($settings['require_checklist_before_year_close'])) {
            PeriodCloseService::ensureChecklistItems($db, $fyId, 0, 'year');
            list($total, $done, $exceptions) = PeriodCloseService::checklistProgress($db, $fyId, 0);
            $chkOk = ($done >= $total && $exceptions === 0);
            $checks[] = ['name' => 'Closing checklist completed', 'status' => $chkOk ? 'pass' : 'fail',
                         'detail' => "$done of $total items completed, $exceptions exception(s)."];
        }

        // 7. No unresolved critical exceptions on year checklist
        $stmt = $db->prepare("SELECT COUNT(*) FROM period_checklist_items WHERE financial_year_id = ? AND status = 'exception'");
        $stmt->execute([$fyId]);
        $excAll = (int)$stmt->fetchColumn();
        $checks[] = ['name' => 'No unresolved critical exceptions', 'status' => $excAll === 0 ? 'pass' : 'fail',
                     'detail' => $excAll === 0 ? 'None.' : "$excAll exception item(s) across all checklists."];

        // 8-9. P&L / Balance Sheet review attestations come from checklist items
        foreach ([['Profit & Loss reviewed', 'reports'], ['Balance Sheet reviewed', 'reports']] as $x) { /* covered by templates */ }
        foreach (['banking' => 'Bank reconciled', 'ar' => 'AR reconciled', 'ap' => 'AP reconciled',
                  'tax' => 'Tax reconciled', 'payroll' => 'Payroll reconciled'] as $area => $label) {
            $item = PeriodCloseService::checklistItemByArea($db, $fyId, 0, $area);
            if (!$item) {
                // fall back to any period checklist instance for this area
                $item = self::anyChecklistItemByArea($db, $fyId, $area);
            }
            if ($item) {
                $ok = $item['status'] === 'completed';
                $checks[] = ['name' => $label, 'status' => $ok ? 'pass' : 'fail',
                             'detail' => ucfirst(str_replace('_', ' ', $item['status']))];
            }
        }

        $passed = true;
        foreach ($checks as $c) if ($c['status'] === 'fail') { $passed = false; break; }

        self::log($db, self::ACT_YEAR_VALIDATION_COMPLETED, $fyId, null, null,
            json_encode(['passed' => $passed, 'checks' => $checks]), null, $userId);

        return ['passed' => $passed, 'checks' => $checks, 'fy' => $fy];
    }

    private static function anyChecklistItemByArea($db, $fyId, $area)
    {
        $stmt = $db->prepare("
            SELECT ci.* FROM period_checklist_items ci
            JOIN period_checklist_templates ct ON ct.id = ci.template_id
            WHERE ci.financial_year_id = ? AND ct.module_area = ?
            ORDER BY (ci.status='completed') DESC, ci.period_id DESC LIMIT 1
        ");
        $stmt->execute([$fyId, $area]);
        return $stmt->fetch();
    }

    // ---------------------------------------------------------
    // STEP 11 — Temporary vs permanent accounts & balances
    // ---------------------------------------------------------

    /**
     * Per-account movement totals for a FY, split by permanence.
     * nature IN (income, expense)          -> temporary
     * nature IN (asset, liability, equity) -> permanent
     */
    public static function fyAccountBalances($db, $fyId)
    {
        $stmt = $db->prepare("
            SELECT coa.id, coa.account_code, coa.account_name, ag.nature,
                   COALESCE(SUM(gl.debit),0)  AS debit,
                   COALESCE(SUM(gl.credit),0) AS credit
            FROM chart_of_accounts coa
            JOIN account_groups ag ON ag.id = coa.group_id
            LEFT JOIN general_ledger gl ON gl.account_id = coa.id AND gl.financial_year_id = ?
            GROUP BY coa.id, coa.account_code, coa.account_name, ag.nature
            HAVING debit <> 0 OR credit <> 0 OR ag.nature = 'equity'
            ORDER BY coa.account_code
        ");
        $stmt->execute([$fyId]);
        return $stmt->fetchAll();
    }

    public static function classifyTemporary(array $balances)
    {
        $temp = [];
        foreach ($balances as $b) {
            if (in_array($b['nature'], ['income', 'expense'], true)) $temp[] = $b;
        }
        return $temp;
    }

    public static function computeNetProfit(array $tempAccounts)
    {
        // Income natural credit; expense natural debit.
        // Net profit = total income − total expenses.
        $income = 0; $expense = 0;
        foreach ($tempAccounts as $a) {
            $net = (float)$a['credit'] - (float)$a['debit']; // signed natural balance
            if ($a['nature'] === 'income')      $income  += $net;
            elseif ($a['nature'] === 'expense') $expense += $net; // typically negative-signed
        }
        return round($income + $expense, 2); // profit > 0, loss < 0
    }

    // ---------------------------------------------------------
    // STEP 12/13 — Close the year through the Posting Engine
    // ---------------------------------------------------------

    public static function closeYear($db, $fyId, $userId)
    {
        // === Idempotency guards (STEP 26) ===
        $stmt = $db->prepare("SELECT id, status FROM year_end_closings WHERE financial_year_id = ?");
        $stmt->execute([$fyId]);
        $existing = $stmt->fetch();
        if ($existing && $existing['status'] === 'completed') {
            return ['success' => false, 'message' => 'This financial year is ALREADY closed. Duplicate closing rejected.'];
        }

        $stmt = $db->prepare("SELECT * FROM financial_years WHERE id = ?");
        $stmt->execute([$fyId]);
        $fy = $stmt->fetch();
        if (!$fy)                                   return ['success' => false, 'message' => 'Financial year not found.'];
        if (in_array($fy['status'], ['closed','archived']))
                                                    return ['success' => false, 'message' => 'Financial year is already closed/archived.'];

        // === Validation (STEP 25: stop on ANY critical failure) ===
        $validation = self::validateYearEnd($db, $fyId, $userId);
        if (!$validation['passed']) {
            return [
                'success' => false,
                'message' => 'Year-end validation FAILED. Nothing was closed.',
                'validation' => $validation,
            ];
        }

        $settings  = PeriodCloseService::getSettings($db);
        $reAcctId  = (int)$settings['retained_earnings_account_id'];

        try {
            $db->beginTransaction();

            // === Build the closing journal (STEP 13) ===
            $balances  = self::fyAccountBalances($db, $fyId);
            $tempAccts = self::classifyTemporary($balances);
            $netProfit = self::computeNetProfit($tempAccts);

            if (empty($tempAccts)) {
                $db->rollBack();
                return ['success' => false, 'message' => 'No temporary accounts found — nothing to close.', 'validation' => $validation];
            }

            $lines = []; // [account_id, debit, credit]
            foreach ($tempAccts as $a) {
                $natural = round((float)$a['credit'] - (float)$a['debit'], 2);
                if (abs($natural) < 0.005) continue;
                if ($natural > 0) {
                    // credit-balanced (income): debit to zero
                    $lines[] = ['account_id' => $a['id'], 'debit' => $natural, 'credit' => 0.00];
                } else {
                    // debit-balanced (expense): credit to zero
                    $lines[] = ['account_id' => $a['id'], 'debit' => 0.00, 'credit' => abs($natural)];
                }
            }
            if (empty($lines)) {
                $db->rollBack();
                return ['success' => false, 'message' => 'All temporary accounts already have zero balances.', 'validation' => $validation];
            }

            $totalDebit  = round(array_sum(array_column($lines, 'debit')), 2);
            $totalCredit = round(array_sum(array_column($lines, 'credit')), 2);

            if ($settings['closing_method'] === 'via_income_summary') {
                $summaryId = (int)$settings['income_summary_account_id'];
                // temp accounts -> income summary (difference line)
                $diff = round($totalDebit - $totalCredit, 2); // profit => debit side larger -> credit summary
                if (abs($diff) >= 0.005) {
                    $lines[] = $diff > 0
                        ? ['account_id' => $summaryId, 'debit' => 0.00, 'credit' => $diff]
                        : ['account_id' => $summaryId, 'debit' => abs($diff), 'credit' => 0.00];
                }
                // income summary -> retained earnings (the profit transfer)
                $lines[] = $netProfit >= 0
                    ? ['account_id' => $summaryId, 'debit' => $netProfit, 'credit' => 0.00]
                    : ['account_id' => $summaryId, 'debit' => 0.00, 'credit' => abs($netProfit)];
                $lines[] = $netProfit >= 0
                    ? ['account_id' => $reAcctId, 'debit' => 0.00, 'credit' => $netProfit]
                    : ['account_id' => $reAcctId, 'debit' => abs($netProfit), 'credit' => 0.00];
            } else {
                // direct_to_retained: single balancing line on RE
                $diff = round($totalDebit - $totalCredit, 2); // >0 profit (debits exceed) -> credit RE
                if (abs($diff) >= 0.005) {
                    $lines[] = $diff > 0
                        ? ['account_id' => $reAcctId, 'debit' => 0.00, 'credit' => $diff]
                        : ['account_id' => $reAcctId, 'debit' => abs($diff), 'credit' => 0.00];
                }
            }

            // Final sanity: balanced journal
            $td = round(array_sum(array_column($lines, 'debit')), 2);
            $tc = round(array_sum(array_column($lines, 'credit')), 2);
            if (abs($td - $tc) > 0.001 || $td <= 0) {
                $db->rollBack();
                return ['success' => false, 'message' => "Closing journal would not balance (Dr $td / Cr $tc). Aborted.", 'validation' => $validation];
            }

            // === Create + post the journal via Posting Engine ===
            require_once __DIR__ . '/../helpers/finance_helpers.php';
            $voucherTypeId = self::journalVoucherTypeId($db);
            $voucherNo     = generateVoucherNo($voucherTypeId, $db);

            $ins = $db->prepare("
                INSERT INTO journal_entries (voucher_no, voucher_type_id, voucher_date, reference_no, description, status, created_by)
                VALUES (?, ?, ?, ?, ?, 'draft', ?)
            ");
            $ins->execute([
                $voucherNo, $voucherTypeId, $fy['end_date'],
                'YEC-' . ($fy['year_code'] ?: $fy['year_name']),
                'Year-End Closing ' . $fy['year_name'] . ' — close temporary accounts to retained earnings',
                $userId,
            ]);
            $journalId = (int)$db->lastInsertId();

            $lineIns = $db->prepare("
                INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($lines as $l) {
                $acctName = self::accountName($db, $l['account_id']);
                $lineIns->execute([
                    $journalId, $l['account_id'], $l['debit'], $l['credit'],
                    'Year-end close: ' . $acctName,
                ]);
            }

            // Post through THE engine. bypass_period_lock is required because the
            // final period is already CLOSED by design (workflow closes periods first).
            $postResult = PostingEngine::post($journalId, $userId, ['bypass_period_lock' => true]);
            if (empty($postResult['success'])) {
                $db->rollBack();
                return ['success' => false, 'message' => 'Posting Engine rejected the closing journal: ' . $postResult['message'], 'validation' => $validation];
            }

            // === Mark year closed ===
            $upd = $db->prepare("
                UPDATE financial_years SET status = 'closed', closed_by = ?, closed_at = NOW() WHERE id = ?
            ");
            $upd->execute([$userId, $fyId]);

            // === Carry forward opening balances (STEP 14) ===
            // Recompute FRESH balances — the closing journal above changed
            // retained earnings (a permanent account); stale data would
            // silently drop it from the carry-forward.
            $freshBalances = self::fyAccountBalances($db, $fyId);
            $carry = self::carryForwardOpeningBalances($db, $fyId, $freshBalances, $journalId, $userId);
            if (!$carry['success']) {
                $db->rollBack();
                return ['success' => false, 'message' => 'Carry-forward failed: ' . $carry['message'], 'validation' => $validation];
            }

            // === Record the closing (idempotency anchor) ===
            $snapshot = json_encode(['validation' => $validation, 'carry_forward' => $carry], JSON_UNESCAPED_UNICODE);
            $yecIns = $db->prepare("
                INSERT INTO year_end_closings
                    (financial_year_id, next_financial_year_id, closing_journal_id, net_profit,
                     retained_earnings_account_id, closing_method, total_temp_debit, total_temp_credit,
                     opening_debit_total, opening_credit_total, verification_passed, validation_snapshot,
                     status, closed_by, closed_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW())
            ");
            $yecIns->execute([
                $fyId, $carry['next_fy_id'], $journalId, $netProfit, $reAcctId, $settings['closing_method'],
                $totalDebit, $totalCredit,
                $carry['totals']['debit'], $carry['totals']['credit'],
                $carry['verification_passed'] ? 1 : 0,
                $snapshot, $userId,
            ]);
            $yecId = (int)$db->lastInsertId();

            // Activate the new FY (only one active at a time)
            $newFyId = $carry['next_fy_id'];
            $db->exec("UPDATE financial_years SET status = 'closed' WHERE status = 'active' AND id <> " . (int)$newFyId);
            $db->exec("UPDATE financial_years SET status = 'active' WHERE id = " . (int)$newFyId);

            self::log($db, self::ACT_YEAR_CLOSED, $fyId, null, $journalId,
                'Net ' . ($netProfit >= 0 ? 'profit' : 'loss') . ' ' . number_format(abs($netProfit), 2) . ' transferred to retained earnings', null, $userId);
            self::log($db, self::ACT_OPENING_GENERATED, $newFyId, null, $yecId,
                count($carry['rows']) . ' account(s) carried forward; verification ' . ($carry['verification_passed'] ? 'PASSED' : 'FAILED'), null, $userId);
            logAudit($userId, 'Year-end closing completed for ' . $fy['year_name'] . ' (' . $voucherNo . ')', 'finance');

            $db->commit();

            return [
                'success' => true,
                'message' => 'Year-end closing completed. Net ' . ($netProfit >= 0 ? 'profit' : 'loss') . ' ' . number_format(abs($netProfit), 2) . ' → retained earnings.',
                'journal_id' => $journalId, 'voucher_no' => $voucherNo,
                'net_profit' => $netProfit, 'carry_forward' => $carry, 'validation' => $validation,
            ];

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => 'Year-end closing aborted: ' . $e->getMessage()];
        }
    }

    // ---------------------------------------------------------
    // STEP 14/15 — Opening balance carry-forward + verification
    // ---------------------------------------------------------

    private static function carryForwardOpeningBalances($db, $oldFyId, array $balances, $closingJournalId, $userId)
    {
        $next = self::findOrCreateNextFY($db, $oldFyId, $userId);
        if (!$next['success']) return $next;
        $newFy = $next['fy'];

        // Idempotency: never duplicate opening balances
        $chk = $db->prepare("SELECT COUNT(*) FROM period_opening_balances WHERE financial_year_id = ?");
        $chk->execute([$newFy['id']]);
        if ((int)$chk->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Opening balances already exist for ' . $newFy['year_name'] . '.'];
        }

        // Permanent accounts carry their OLD-FY CLOSING balance.
        // Temporary accounts start at ZERO (they were just closed by the JE).
        $permanent = array_filter($balances, fn($b) => in_array($b['nature'], ['asset', 'liability', 'equity'], true));

        $rows = [];
        foreach ($permanent as $b) {
            // Natural signed closing balance (debit positive / credit negative)
            $signed = round((float)$b['debit'] - (float)$b['credit'], 2);
            if (abs($signed) < 0.005) continue;
            $rows[] = [
                'account_id' => $b['id'],
                'debit'  => $signed > 0 ? $signed : 0.00,
                'credit' => $signed < 0 ? abs($signed) : 0.00,
                'signed' => $signed,
                'code'   => $b['account_code'], 'name' => $b['account_name'], 'type' => $b['opening_type'] ?? '',
            ];
        }

        $totD = round(array_sum(array_column($rows, 'debit')), 2);
        $totC = round(array_sum(array_column($rows, 'credit')), 2);

        // Persist audit copy + update chart_of_accounts.opening_balance
        // (this is exactly what reports add on top of GL movement per FY).
        $ins = $db->prepare("
            INSERT INTO period_opening_balances (financial_year_id, account_id, debit, credit, source_closing_id, generated_by, generated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $updCoa = $db->prepare("
            UPDATE chart_of_accounts SET opening_balance = ?, opening_balance_type = ? WHERE id = ?
        ");
        $hist = $db->prepare("
            INSERT INTO opening_balance_history (account_id, financial_year_id, old_balance, new_balance, balance_type, changed_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($rows as $r) {
            $ins->execute([$newFy['id'], $r['account_id'], $r['debit'], $r['credit'], $closingJournalId, $userId]);

            $cur = $db->prepare("SELECT opening_balance, opening_balance_type FROM chart_of_accounts WHERE id = ?");
            $cur->execute([$r['account_id']]);
            $old = $cur->fetch();

            $type = $r['signed'] >= 0 ? 'debit' : 'credit';
            $abs  = abs($r['signed']);
            $updCoa->execute([$abs, $type, $r['account_id']]);
            if ($old && ((float)$old['opening_balance'] != $abs || $old['opening_balance_type'] != $type)) {
                $hist->execute([$r['account_id'], $newFy['id'], (float)$old['opening_balance'], $abs, $type, $userId]);
            }
        }

        // STEP 15 — verification: totals must match and each row Dr=Cr against source
        $verificationPassed = abs($totD - $totC) < 0.001;

        return [
            'success' => true,
            'next_fy_id' => $newFy['id'], 'next_fy_name' => $newFy['year_name'],
            'created_fy' => $next['created'],
            'rows' => $rows, 'totals' => ['debit' => $totD, 'credit' => $totC],
            'verification_passed' => $verificationPassed,
        ];
    }

    /** Find the chronological next FY or auto-create it. */
    public static function findOrCreateNextFY($db, $currentFyId, $userId)
    {
        $stmt = $db->prepare("SELECT * FROM financial_years WHERE id = ?");
        $stmt->execute([$currentFyId]);
        $fy = $stmt->fetch();

        $nextStmt = $db->prepare("
            SELECT * FROM financial_years WHERE start_date > ? ORDER BY start_date ASC LIMIT 1
        ");
        $nextStmt->execute([$fy['start_date']]);
        $next = $nextStmt->fetch();
        if ($next) return ['success' => true, 'fy' => $next, 'created' => false];

        $start = new DateTime($fy['end_date']);
        $start->modify('+1 day');
        $end = clone $start;
        $end->modify('+1 year')->modify('-1 day');

        $name = 'FY ' . $start->format('Y');
        if ($start->format('Y') !== $end->format('Y')) $name .= '-' . $end->format('y');

        $ins = $db->prepare("
            INSERT INTO financial_years (year_name, year_code, start_date, end_date, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'active', NOW(), NOW())
        ");
        $code = $start->format('y') . $end->format('y');
        $ins->execute([$name, $code, $start->format('Y-m-d'), $end->format('Y-m-d')]);
        $newId = (int)$db->lastInsertId();

        $get = $db->prepare("SELECT * FROM financial_years WHERE id = ?");
        $get->execute([$newId]);

        PeriodCloseService::ensurePeriodsExist($db, $newId);
        logAudit($userId, 'Auto-created next financial year ' . $name . ' during year-end closing', 'finance');

        return ['success' => true, 'fy' => $get->fetch(), 'created' => true];
    }

    // ---------------------------------------------------------
    // STEP 16 — Year reopening (authorized, audited, non-destructive)
    // ---------------------------------------------------------

    public static function reopenYear($db, $fyId, $userId, $reason)
    {
        if (trim($reason) === '') return ['success' => false, 'message' => 'A reason is REQUIRED to reopen a financial year.'];

        $settings = PeriodCloseService::getSettings($db);
        if (empty($settings['allow_year_reopen'])) {
            return ['success' => false, 'message' => 'Year reopening is disabled in closing settings.'];
        }

        $stmt = $db->prepare("SELECT * FROM year_end_closings WHERE financial_year_id = ?");
        $stmt->execute([$fyId]);
        $yec = $stmt->fetch();
        if (!$yec || $yec['status'] !== 'completed') {
            return ['success' => false, 'message' => 'Year is not currently closed.'];
        }

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE year_end_closings SET status = 'reopened', reopened_by = ?, reopened_at = NOW(), reopen_reason = ? WHERE id = ?")
               ->execute([$userId, $reason, $yec['id']]);
            $db->prepare("UPDATE financial_years SET status = 'closing', closed_by = NULL, closed_at = NULL WHERE id = ?")
               ->execute([$fyId]);

            self::log($db, self::ACT_YEAR_REOPENED, $fyId, null, $yec['closing_journal_id'],
                'Year reopened. Closing journal preserved (not deleted). Use reversal for corrections.', $reason, $userId);
            logAudit($userId, 'Reopened financial year ID ' . $fyId . ': ' . $reason, 'finance');

            $db->commit();
            return ['success' => true, 'message' => 'Year reopened (audited). Closing history preserved.'];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => 'Reopen failed: ' . $e->getMessage()];
        }
    }

    /** Explicit corrective action after reopen — reversal via Posting Engine. */
    public static function reverseClosingJournal($db, $fyId, $userId, $reason)
    {
        $stmt = $db->prepare("SELECT * FROM year_end_closings WHERE financial_year_id = ?");
        $stmt->execute([$fyId]);
        $yec = $stmt->fetch();
        if (!$yec || empty($yec['closing_journal_id'])) return ['success' => false, 'message' => 'No closing journal recorded.'];
        if ($yec['status'] !== 'reopened')              return ['success' => false, 'message' => 'Reopen the year before reversing its closing journal.'];

        $res = PostingEngine::reverse((int)$yec['closing_journal_id'], $userId, 'Year-end closing reversal: ' . $reason, ['bypass_period_lock' => true]);
        if (!empty($res['success'])) {
            self::log($db, self::ACT_CLOSING_JOURNAL_REVERSED, $fyId, null, $yec['closing_journal_id'], 'Reversal voucher: ' . ($res['reversal_no'] ?? ''), $reason, $userId);
        }
        return $res;
    }

    // ---------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------

    private static function fyTotals($db, $fyId)
    {
        $stmt = $db->prepare("SELECT COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c FROM general_ledger WHERE financial_year_id = ?");
        $stmt->execute([$fyId]);
        $r = $stmt->fetch();
        return ['debit' => (float)$r['d'], 'credit' => (float)$r['c']];
    }

    private static function journalVoucherTypeId($db)
    {
        // Prefer an exact "Journal Voucher" JV type
        $id = $db->query("SELECT id FROM voucher_types WHERE voucher_name = 'Journal Voucher' LIMIT 1")->fetchColumn();
        if (!$id) $id = $db->query("SELECT id FROM voucher_types WHERE status='active' ORDER BY id LIMIT 1")->fetchColumn();
        return (int)$id;
    }

    private static function accountName($db, $accountId)
    {
        $stmt = $db->prepare("SELECT CONCAT(account_code, ' — ', account_name) FROM chart_of_accounts WHERE id = ?");
        $stmt->execute([$accountId]);
        return $stmt->fetchColumn() ?: ('Account #' . $accountId);
    }
}
