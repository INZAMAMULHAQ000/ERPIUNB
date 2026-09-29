<?php
/**
 * Budget Management (F7) — shared helpers
 *
 * Budgets are PLANNING TOOLS ONLY. They never write to general_ledger
 * and never call PostingEngine. Actual (spent) figures are read-only,
 * sourced from the General Ledger; department/project actuals are
 * attributed via the F6 company_expenses join (the only GL-linked
 * table that carries those dimensions).
 */
require_once __DIR__ . '/finance_helpers.php';

if (!defined('BUDGET_WARN_PCT'))    define('BUDGET_WARN_PCT', 80);     // % used -> yellow
if (!defined('BUDGET_EXCEED_PCT'))  define('BUDGET_EXCEED_PCT', 100);  // % used -> red

/** Month number (1=Jul..12=Jun) -> label, aligned to the July-June FY. */
function budget_month_name($m) {
    $names = [1 => 'July', 'August', 'September', 'October', 'November', 'December',
              7 => 'January', 'February', 'March', 'April', 'May', 'June'];
    return $names[(int)$m] ?? 'Annual';
}

/** Date range [start, end] for a FY month (1=Jul..12=Jun); null month = whole FY. */
function budget_month_range($fy, $month) {
    if ($month === null || $month === '') {
        return [$fy['start_date'], $fy['end_date']];
    }
    $d = new DateTime($fy['start_date']);
    $d->modify('+' . ((int)$month - 1) . ' months');
    return [$d->format('Y-m-01'), $d->format('Y-m-t')];
}

function generateBudgetCode($db, $fy) {
    $year = preg_match('/(\d{4})/', $fy['year_name'], $m) ? $m[1] : date('Y');
    $seq  = (int)$db->query("SELECT COUNT(*) FROM budgets")->fetchColumn() + 1;
    return 'BUD-' . $year . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
}

function generateTransferNo($db) {
    $seq = (int)$db->query("SELECT COUNT(*) FROM budget_transfers")->fetchColumn() + 1;
    return 'TRF-' . date('Y') . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
}

/**
 * Actual (spent) for one budget line from the General Ledger.
 * Account-level always; restricted to a department/project via the
 * F6 company_expenses join when those dimensions are set on the line.
 */
function budget_line_actual($db, $line, $fy, $from, $to) {
    $params = [
        ':fy'   => $fy['id'],
        ':acct' => $line['chart_of_accounts_id'],
        ':from' => $from,
        ':to'   => $to,
    ];
    if (!empty($line['department_id']) || !empty($line['project_id'])) {
        $sql = "SELECT COALESCE(SUM(gl.debit) - SUM(gl.credit), 0) AS spent
                FROM general_ledger gl
                JOIN journal_entries je ON je.id = gl.journal_entry_id
                JOIN company_expenses ce ON ce.journal_entry_id = je.id
                    AND ce.status NOT IN ('cancelled','draft')
                WHERE gl.financial_year_id = :fy
                  AND gl.account_id = :acct
                  AND gl.posting_date BETWEEN :from AND :to";
        if (!empty($line['department_id'])) { $sql .= " AND ce.department_id = :dept";   $params[':dept'] = $line['department_id']; }
        if (!empty($line['project_id']))    { $sql .= " AND ce.project_id = :proj";      $params[':proj'] = $line['project_id']; }
    } else {
        $sql = "SELECT COALESCE(SUM(gl.debit) - SUM(gl.credit), 0) AS spent
                FROM general_ledger gl
                WHERE gl.financial_year_id = :fy
                  AND gl.account_id = :acct
                  AND gl.posting_date BETWEEN :from AND :to";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (float)$stmt->fetchColumn();
}

/**
 * Actual spend for a whole dimension (department/project), optionally
 * restricted to an account. Unlike budget_line_actual this does NOT
 * require an account, so department/project totals aggregate correctly.
 * Dimension attribution goes through the F6 company_expenses join.
 */
function budget_dimension_actual($db, $fy, $from, $to, $departmentId = null, $projectId = null, $accountId = null) {
    // Restrict to expense-nature accounts only. A journal posts DR to the expense
    // account and CR to a payable, so netting ALL rows of the journal would zero
    // out. Restricting to expense accounts yields the true spend per dimension.
    $sql = "SELECT COALESCE(SUM(gl.debit) - SUM(gl.credit), 0) AS spent
            FROM general_ledger gl
            JOIN journal_entries je ON je.id = gl.journal_entry_id
            JOIN company_expenses ce ON ce.journal_entry_id = je.id
                AND ce.status NOT IN ('cancelled','draft')
            JOIN chart_of_accounts coa ON coa.id = gl.account_id
            JOIN account_groups ag ON ag.id = coa.group_id AND ag.nature = 'expense'
            WHERE gl.financial_year_id = :fy
              AND gl.posting_date BETWEEN :from AND :to";
    $params = [':fy' => $fy['id'], ':from' => $from, ':to' => $to];
    if ($departmentId) { $sql .= " AND ce.department_id = :dept"; $params[':dept'] = (int)$departmentId; }
    if ($projectId)    { $sql .= " AND ce.project_id = :proj";    $params[':proj'] = (int)$projectId; }
    if ($accountId)    { $sql .= " AND gl.account_id = :acct";    $params[':acct'] = (int)$accountId; }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (float)$stmt->fetchColumn();
}

/**
 * Lines of a version enriched with spent/available/variance.
 * $month (1-12) restricts to that month's lines and period; null = all lines.
 */
function budget_lines_for_version($db, $versionId, $fy, $month = null) {
    $sql = "SELECT bl.*, acct.account_code, acct.account_name,
                   d.department_name, p.project_name
            FROM budget_lines bl
            JOIN chart_of_accounts acct ON acct.id = bl.chart_of_accounts_id
            LEFT JOIN departments d ON d.id = bl.department_id
            LEFT JOIN projects p ON p.id = bl.project_id
            WHERE bl.budget_version_id = ?";
    $params = [$versionId];
    if ($month) {
        $sql .= " AND bl.month = ?";
        $params[] = (int)$month;
    }
    $sql .= " ORDER BY bl.chart_of_accounts_id, bl.month";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $lines = $stmt->fetchAll();

    foreach ($lines as &$l) {
        list($from, $to) = budget_month_range($fy, $l['month']);
        $spent = budget_line_actual($db, $l, $fy, $from, $to);
        $l['spent']      = $spent;
        $l['committed']  = 0.00; // future-ready: PO commitments plug in here
        $l['available']  = (float)$l['budget_amount'] - $spent - $l['committed'];
        $l['pct']        = (float)$l['budget_amount'] > 0 ? round($spent / (float)$l['budget_amount'] * 100, 2) : 0.0;
        $l['variance']   = (float)$l['budget_amount'] - $spent;
    }
    unset($l);
    return $lines;
}

/** Column sums over enriched lines. */
function budget_lines_totals($lines) {
    $t = ['allocated' => 0.0, 'spent' => 0.0, 'committed' => 0.0, 'available' => 0.0, 'pct' => 0.0, 'variance' => 0.0, 'count' => count($lines)];
    foreach ($lines as $l) {
        $t['allocated'] += (float)$l['budget_amount'];
        $t['spent']     += $l['spent'];
        $t['committed'] += $l['committed'];
        $t['available'] += $l['available'];
        $t['variance']  += $l['variance'];
    }
    $t['pct'] = $t['allocated'] > 0 ? round($t['spent'] / $t['allocated'] * 100, 2) : 0.0;
    return $t;
}

/**
 * Budget that can still be moved OUT of a line:
 * allocated - actual - committed - pending transfers-out.
 */
function line_available($db, $line, $fy, $excludeTransferId = null) {
    list($from, $to) = budget_month_range($fy, $line['month']);
    $spent = budget_line_actual($db, $line, $fy, $from, $to);
    $sql = "SELECT COALESCE(SUM(amount), 0) FROM budget_transfers
            WHERE from_line_id = ? AND status = 'pending'";
    $params = [$line['id']];
    if ($excludeTransferId) { $sql .= " AND id <> ?"; $params[] = $excludeTransferId; }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $pendingOut = (float)$stmt->fetchColumn();
    return (float)$line['budget_amount'] - $spent - 0.00 - $pendingOut;
}

function recompute_version_total($db, $versionId) {
    $stmt = $db->prepare("UPDATE budget_versions v
                          SET total_amount = (SELECT COALESCE(SUM(bl.budget_amount), 0)
                                              FROM budget_lines bl WHERE bl.budget_version_id = v.id)
                          WHERE v.id = ?");
    $stmt->execute([$versionId]);
}

/** Next version_no for a budget. */
function budget_next_version_no($db, $budgetId) {
    $stmt = $db->prepare("SELECT COALESCE(MAX(version_no), 0) + 1 FROM budget_versions WHERE budget_id = ?");
    $stmt->execute([$budgetId]);
    return (int)$stmt->fetchColumn();
}

function budget_version_label($db, $budgetId, $type) {
    if ($type === 'forecast') return 'Forecast';
    if ($type === 'final')    return 'Final';
    $stmt = $db->prepare("SELECT COUNT(*) FROM budget_versions WHERE budget_id = ? AND version_type <> 'original'");
    $stmt->execute([$budgetId]);
    $n = (int)$stmt->fetchColumn();
    return 'Revision ' . ($n + 1);
}

/** Clone all lines of $fromVersion into $toVersion (new version, never overwrite old). */
function copy_version_lines($db, $fromVersionId, $toBudgetId, $toVersionId) {
    $stmt = $db->prepare("INSERT INTO budget_lines
        (budget_id, budget_version_id, department_id, project_id, chart_of_accounts_id, month, budget_amount, notes)
        SELECT ?, ?, department_id, project_id, chart_of_accounts_id, month, budget_amount, notes
        FROM budget_lines WHERE budget_version_id = ?");
    $stmt->execute([$toBudgetId, $toVersionId, $fromVersionId]);
}

/** Active (current) version of a budget, else its latest version. */
function budget_active_version($db, $budgetId) {
    $stmt = $db->prepare("SELECT * FROM budget_versions WHERE budget_id = ? ORDER BY (status='active') DESC, id DESC LIMIT 1");
    $stmt->execute([$budgetId]);
    return $stmt->fetch();
}

/* ---------- color coding & status badges ---------- */

function budget_variance_color($pct) {
    if ($pct >= BUDGET_EXCEED_PCT) return 'red';
    if ($pct >= BUDGET_WARN_PCT)    return 'yellow';
    return 'green';
}

function budget_variance_badge($pct) {
    $map = [
        'red'    => 'bg-red-100 text-red-800',
        'yellow' => 'bg-yellow-100 text-yellow-800',
        'green'  => 'bg-green-100 text-green-800',
    ];
    return $map[budget_variance_color($pct)] ?? 'bg-gray-100 text-gray-800';
}

function budget_variance_label($pct) {
    $c = budget_variance_color($pct);
    return $c === 'red' ? 'Exceeded' : ($c === 'yellow' ? 'Near Limit' : 'Within Budget');
}

function budget_status_badge($status) {
    $map = [
        'draft'            => 'bg-gray-100 text-gray-800',
        'pending_approval' => 'bg-yellow-100 text-yellow-800',
        'approved'         => 'bg-blue-100 text-blue-800',
        'active'           => 'bg-green-100 text-green-800',
        'rejected'         => 'bg-red-100 text-red-800',
        'closed'           => 'bg-gray-200 text-gray-600',
    ];
    return $map[$status] ?? 'bg-gray-100 text-gray-800';
}

function version_status_badge($status) {
    $map = [
        'draft'    => 'bg-gray-100 text-gray-800',
        'approved' => 'bg-blue-100 text-blue-800',
        'active'   => 'bg-green-100 text-green-800',
        'locked'   => 'bg-gray-200 text-gray-600',
    ];
    return $map[$status] ?? 'bg-gray-100 text-gray-800';
}

function transfer_status_badge($status) {
    $map = [
        'pending'   => 'bg-yellow-100 text-yellow-800',
        'approved'  => 'bg-green-100 text-green-800',
        'rejected'  => 'bg-red-100 text-red-800',
        'cancelled' => 'bg-gray-200 text-gray-600',
    ];
    return $map[$status] ?? 'bg-gray-100 text-gray-800';
}

function revision_type_label($t) {
    return ucfirst($t);
}

/** Fetch one budget by id with FY + creator joins. */
function budget_find($db, $id) {
    $stmt = $db->prepare("SELECT b.*, fy.year_name, fy.start_date AS fy_start, fy.end_date AS fy_end, fy.status AS fy_status
                          FROM budgets b
                          JOIN financial_years fy ON fy.id = b.financial_year_id
                          WHERE b.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}
