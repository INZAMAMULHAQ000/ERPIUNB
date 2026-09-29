<?php
/**
 * F12 — Period Close Service
 *
 * Accounting period lifecycle: open -> closing -> closed -> (authorized reopen).
 * All validations required before a period may close live here.
 * NOTHING in this class ever writes to general_ledger directly — postings
 * always go through the Posting Engine.
 */

require_once __DIR__ . '/../posting_engine/PostingEngine.php';

class PeriodCloseService
{
    /** Action names recorded in period_close_audit */
    const ACT_PERIOD_CLOSED   = 'period_closed';
    const ACT_PERIOD_REOPENED = 'period_reopened';
    const ACT_VALIDATION_RUN  = 'validation_run';
    const ACT_CHECKLIST_DONE  = 'checklist_completed';

    // ---------------------------------------------------------
    // Settings
    // ---------------------------------------------------------

    public static function getSettings($db)
    {
        $row = $db->query("SELECT * FROM period_close_settings WHERE id = 1")->fetch();
        if (!$row) {
            $db->exec("INSERT INTO period_close_settings (id) VALUES (1)");
            $row = $db->query("SELECT * FROM period_close_settings WHERE id = 1")->fetch();
        }
        return $row;
    }

    public static function saveSettings($db, array $data, $userId)
    {
        $stmt = $db->prepare("
            UPDATE period_close_settings SET
                retained_earnings_account_id = ?,
                income_summary_account_id = ?,
                closing_method = ?,
                require_checklist_before_period_close = ?,
                require_checklist_before_year_close = ?,
                allow_year_reopen = ?,
                updated_by = ?
            WHERE id = 1
        ");
        $stmt->execute([
            $data['retained_earnings_account_id'] ?: null,
            $data['income_summary_account_id'] ?: null,
            in_array($data['closing_method'], ['direct_to_retained', 'via_income_summary']) ? $data['closing_method'] : 'direct_to_retained',
            !empty($data['require_checklist_before_period_close']) ? 1 : 0,
            !empty($data['require_checklist_before_year_close']) ? 1 : 0,
            !empty($data['allow_year_reopen']) ? 1 : 0,
            $userId,
        ]);
        self::log($db, 'settings_updated', null, null, null, 'Period close settings updated', null, $userId);
    }

    // ---------------------------------------------------------
    // Period helpers
    // ---------------------------------------------------------

    /** Find the accounting period covering a posting date inside a FY. */
    public static function findPeriodForDate($db, $fyId, $date)
    {
        $stmt = $db->prepare("
            SELECT * FROM accounting_periods
            WHERE financial_year_id = ? AND start_date <= ? AND end_date >= ?
            LIMIT 1
        ");
        $stmt->execute([$fyId, $date, $date]);
        return $stmt->fetch();
    }

    /** Generate monthly periods for a FY that has none (or fill missing months). */
    public static function ensurePeriodsExist($db, $fyId)
    {
        $stmt = $db->prepare("SELECT * FROM financial_years WHERE id = ?");
        $stmt->execute([$fyId]);
        $fy = $stmt->fetch();
        if (!$fy) return ['created' => 0];

        $existing = $db->prepare("SELECT COUNT(*) FROM accounting_periods WHERE financial_year_id = ?");
        $existing->execute([$fyId]);
        if ((int)$existing->fetchColumn() > 0) return ['created' => 0];

        $start = new DateTime($fy['start_date']);
        $end   = new DateTime($fy['end_date']);
        $ins   = $db->prepare("
            INSERT IGNORE INTO accounting_periods (financial_year_id, period_name, start_date, end_date)
            VALUES (?, ?, ?, ?)
        ");
        $created = 0;
        $cursor = clone $start;
        while ($cursor <= $end) {
            $pStart = $cursor->format('Y-m-d');
            $pEnd   = $cursor->format('Y-m-t');
            if ($pStart < $fy['start_date']) $pStart = $fy['start_date'];
            if ($pEnd   > $fy['end_date'])   $pEnd   = $fy['end_date'];
            $ins->execute([$fyId, $cursor->format('M Y'), $pStart, $pEnd]);
            $created++;
            $cursor->modify('first day of next month');
        }
        return ['created' => $created];
    }

    /**
     * STEP 4 — Period validation.
     * Returns ['passed'=>bool,'checks'=>[['name','status'(pass/fail/warn),'detail'], ...]]
     */
    public static function validatePeriod($db, $fyId, $periodId, $userId = null)
    {
        if ($userId === null) $userId = $_SESSION['user_id'] ?? null;
        $checks = [];

        $stmt = $db->prepare("SELECT * FROM accounting_periods WHERE id = ? AND financial_year_id = ?");
        $stmt->execute([$periodId, $fyId]);
        $period = $stmt->fetch();
        if (!$period) {
            return ['passed' => false, 'checks' => [['name' => 'Period', 'status' => 'fail', 'detail' => 'Period not found for this financial year.']]];
        }
        list($from, $to) = [$period['start_date'], $period['end_date']];

        // 1. No draft (unposted) journals inside the period
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM journal_entries
            WHERE voucher_date BETWEEN ? AND ? AND status = 'draft'
        ");
        $stmt->execute([$from, $to]);
        $drafts = (int)$stmt->fetchColumn();
        $checks[] = ['name' => 'No invalid journal entries', 'status' => $drafts === 0 ? 'pass' : 'fail',
                     'detail' => $drafts === 0 ? 'All journals in period are posted.' : "$drafts draft journal(s) must be posted or reversed."];

        // 2. Debits = Credits inside the period
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c FROM general_ledger
            WHERE posting_date BETWEEN ? AND ?
        ");
        $stmt->execute([$from, $to]);
        $sum = $stmt->fetch();
        $balanced = abs((float)$sum['d'] - (float)$sum['c']) < 0.001;
        $checks[] = ['name' => 'Debits = Credits', 'status' => $balanced ? 'pass' : 'fail',
                     'detail' => $balanced ? 'Ledger is balanced (' . number_format((float)$sum['d'], 2) . ')' : 'Dr ' . $sum['d'] . ' ≠ Cr ' . $sum['c']];

        // 3. No adjustments awaiting approval / unposted for this period
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM closing_adjustments
            WHERE financial_year_id = ? AND status IN ('draft','pending_approval','approved')
              AND (period_id = ? OR (period_id IS NULL AND EXISTS (
                    SELECT 1 FROM accounting_periods p WHERE p.id = ?
                    AND EXISTS (SELECT 1 FROM journal_entries j WHERE j.voucher_date BETWEEN p.start_date AND p.end_date))
              ))
        ");
        $stmt->execute([$fyId, $periodId, $periodId]);
        $pendingAdj = (int)$stmt->fetchColumn();
        $checks[] = ['name' => 'No pending critical approvals', 'status' => $pendingAdj === 0 ? 'pass' : 'fail',
                     'detail' => $pendingAdj === 0 ? 'No unposted/pending closing adjustments.' : "$pendingAdj adjustment(s) still pending or unposted."];

        // 4. Checklist completion (configurable)
        $settings = self::getSettings($db);
        if (!empty($settings['require_checklist_before_period_close'])) {
            list($total, $done, $exceptions) = self::checklistProgress($db, $fyId, $periodId);
            $checks[] = ['name' => 'Closing checklist completed', 'status' => ($done >= $total && $exceptions === 0) ? 'pass' : 'fail',
                         'detail' => "$done of $total items completed, $exceptions exception(s)."];
        }

        // 5-9. Reconciliation attestations come from checklist items (bank/AR/AP/tax/payroll/FA).
        foreach (['banking' => 'Bank reconciliation status', 'ar' => 'AR reconciliation status',
                  'ap' => 'AP reconciliation status', 'tax' => 'Tax reconciliation status',
                  'payroll' => 'Payroll reconciliation status'] as $area => $label) {
            $item = self::checklistItemByArea($db, $fyId, $periodId, $area);
            if ($item) {
                $ok = $item['status'] === 'completed';
                $checks[] = ['name' => $label, 'status' => $ok ? 'pass' : 'fail',
                             'detail' => ucfirst(str_replace('_', ' ', $item['status'])) . ($item['comments'] ? ' — ' . $item['comments'] : '')];
            }
        }

        $passed = true;
        foreach ($checks as $c) if ($c['status'] === 'fail') { $passed = false; break; }

        self::log($db, self::ACT_VALIDATION_RUN, $fyId, $periodId, null,
            json_encode(['passed' => $passed, 'checks' => $checks]), null, $userId);

        return ['passed' => $passed, 'checks' => $checks];
    }

    // ---------------------------------------------------------
    // Close / reopen
    // ---------------------------------------------------------

    public static function closePeriod($db, $fyId, $periodId, $userId)
    {
        $stmt = $db->prepare("SELECT * FROM accounting_periods WHERE id = ? AND financial_year_id = ? FOR UPDATE");
        $stmt->execute([$periodId, $fyId]);
        $period = $stmt->fetch();
        if (!$period)                       return ['success' => false, 'message' => 'Period not found.'];
        if ($period['status'] === 'closed') return ['success' => false, 'message' => 'Period is already closed.'];

        $fyStmt = $db->prepare("SELECT status FROM financial_years WHERE id = ?");
        $fyStmt->execute([$fyId]);
        $fyStatus = $fyStmt->fetchColumn();
        if (in_array($fyStatus, ['closed', 'archived'])) {
            return ['success' => false, 'message' => 'Financial year is closed/archived — periods cannot change state.'];
        }

        $validation = self::validatePeriod($db, $fyId, $periodId, $userId);

        $db->beginTransaction();
        try {
            if (!$validation['passed']) {
                $db->commit(); // validation log already written
                return ['success' => false, 'message' => 'Validation failed — period NOT closed.', 'validation' => $validation];
            }
            // Mark closing (in-flight) then closed atomically
            $upd = $db->prepare("
                UPDATE accounting_periods
                SET status = 'closed', closed_by = ?, closed_at = NOW()
                WHERE id = ? AND status IN ('open','closing')
            ");
            $upd->execute([$userId, $periodId]);

            self::log($db, self::ACT_PERIOD_CLOSED, $fyId, $periodId, null,
                'Period "' . $period['period_name'] . '" closed', null, $userId);

            $db->commit();
            return ['success' => true, 'message' => 'Period closed successfully.', 'validation' => $validation];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'message' => 'Close failed: ' . $e->getMessage()];
        }
    }

    public static function reopenPeriod($db, $fyId, $periodId, $userId, $reason)
    {
        if (trim($reason) === '') return ['success' => false, 'message' => 'A reason is REQUIRED to reopen a closed period.'];

        $stmt = $db->prepare("SELECT * FROM accounting_periods WHERE id = ? AND financial_year_id = ?");
        $stmt->execute([$periodId, $fyId]);
        $period = $stmt->fetch();
        if (!$period)                        return ['success' => false, 'message' => 'Period not found.'];
        if ($period['status'] !== 'closed')  return ['success' => false, 'message' => 'Only closed periods can be reopened.'];

        $fyStmt = $db->prepare("SELECT status FROM financial_years WHERE id = ?");
        $fyStmt->execute([$fyId]);
        $fyStatus = $fyStmt->fetchColumn();
        if (in_array($fyStatus, ['closed', 'archived'])) {
            return ['success' => false, 'message' => 'Financial year is closed/archived. Reopen the year first (authorized administrators only).'];
        }

        $upd = $db->prepare("
            UPDATE accounting_periods
            SET status = 'open', closed_by = NULL, closed_at = NULL, reopen_count = reopen_count + 1
            WHERE id = ?
        ");
        $upd->execute([$periodId]);

        self::log($db, self::ACT_PERIOD_REOPENED, $fyId, $periodId, null,
            'Period "' . $period['period_name'] . '" reopened', $reason, $userId);
        logAudit($userId, 'Reopened accounting period: ' . $period['period_name'], 'finance');

        return ['success' => true, 'message' => 'Period reopened (audited).'];
    }

    // ---------------------------------------------------------
    // Checklist
    // ---------------------------------------------------------

    /** Instantiate template items for a FY+period if not present yet. */
    public static function ensureChecklistItems($db, $fyId, $periodId = 0, $appliesTo = 'both')
    {
        $tpl = $db->prepare("
            SELECT * FROM period_checklist_templates
            WHERE is_active = 1 AND applies_to IN ('both', ?) ORDER BY sort_order
        ");
        $tpl->execute([$appliesTo === 'year' ? 'year' : 'period']);
        $templates = $tpl->fetchAll();

        $ins = $db->prepare("
            INSERT IGNORE INTO period_checklist_items (financial_year_id, period_id, template_id, item_text)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($templates as $t) {
            $ins->execute([$fyId, $periodId, $t['id'], $t['item_text']]);
        }
        return count($templates);
    }

    /**
     * Update a checklist item's status with audit (used by UI and integrations).
     */
    public static function completeItem($db, $itemId, $status, $comments, $userId, $itemText = null)
    {
        if (!in_array($status, ['pending', 'in_progress', 'completed', 'exception'], true)) {
            return ['success' => false, 'message' => 'Invalid checklist status.'];
        }
        // Resolve the item's FY so the audit event is filterable by year
        $own = $db->prepare("SELECT financial_year_id, item_text FROM period_checklist_items WHERE id = ?");
        $own->execute([$itemId]);
        $meta = $own->fetch();
        if (!$meta) return ['success' => false, 'message' => 'Checklist item not found.'];

        $stmt = $db->prepare("
            UPDATE period_checklist_items
            SET status = ?, comments = ?, completed_by = ?, completed_at = ?
            WHERE id = ?
        ");
        $completed = $status === 'completed' ? date('Y-m-d H:i:s') : null;
        $stmt->execute([$status, trim((string)$comments) ?: null, $status === 'completed' ? $userId : null, $completed, $itemId]);
        if ($status === 'completed') {
            self::log($db, self::ACT_CHECKLIST_DONE, (int)$meta['financial_year_id'], null, $itemId, 'Item: ' . ($itemText ?? $meta['item_text']), null, $userId);
        }
        return ['success' => true];
    }

    public static function checklistProgress($db, $fyId, $periodId)
    {
        $stmt = $db->prepare("
            SELECT COUNT(*) total,
                   SUM(status = 'completed') done,
                   SUM(status = 'exception') exceptions
            FROM period_checklist_items WHERE financial_year_id = ? AND period_id = ?
        ");
        $stmt->execute([$fyId, $periodId]);
        $r = $stmt->fetch();
        return [(int)($r['total'] ?? 0), (int)($r['done'] ?? 0), (int)($r['exceptions'] ?? 0)];
    }

    public static function checklistItemByArea($db, $fyId, $periodId, $area)
    {
        $stmt = $db->prepare("
            SELECT ci.* FROM period_checklist_items ci
            JOIN period_checklist_templates ct ON ct.id = ci.template_id
            WHERE ci.financial_year_id = ? AND ci.period_id = ? AND ct.module_area = ?
            ORDER BY ct.sort_order LIMIT 1
        ");
        $stmt->execute([$fyId, $periodId, $area]);
        return $stmt->fetch();
    }

    // ---------------------------------------------------------
    // Audit
    // ---------------------------------------------------------

    public static function log($db, $action, $fyId = null, $periodId = null, $refId = null, $details = null, $reason = null, $userId = null)
    {
        $stmt = $db->prepare("
            INSERT INTO period_close_audit (action, financial_year_id, period_id, reference_id, details, reason, ip_address, user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt->execute([$action, $fyId, $periodId, $refId, $details, $reason, $ip, $userId]);
    }
}
