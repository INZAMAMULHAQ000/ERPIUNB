<?php
/**
 * F7 Integration Test (CLI) — drives the 6 scenarios in FINANCE/F7-testing.
 *
 * Every POST runs through FINANCE/F7-post-runner.php in a fresh PHP process
 * with a super-admin session (user_id=1, role 1) and a fixed CSRF token, exactly
 * like a browser form submit. After each POST the DB state is asserted here.
 *
 * Budgets are PLANNING TOOLS ONLY — the suite also proves actuals flow from the
 * General Ledger (via company_expenses) and that budgets never write to the GL.
 *
 * Test data is tagged "F7 TEST" and wiped at the start so the suite is
 * repeatable; records are left in place afterwards for UI inspection.
 *
 * Run:  php F7-integration.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$ROOT   = realpath(__DIR__ . '/..');
$FIN    = $ROOT . '/modules/finance';
$PHP    = PHP_BINARY;
$RUNNER = __DIR__ . '/F7-post-runner.php';
$CSRF   = 'f7-test-token-1234567890';
$LOG    = __DIR__ . '/F7-post-runner.log';

/* ---------- tiny test harness ---------- */
$pass = 0; $fail = 0;
function ok($cond, $label) {
    global $pass, $fail;
    $pass++;
    if ($cond) { echo "      \u{2705} $label\n"; return true; }
    $fail++;
    echo "      \u{274C} FAIL: $label\n";
    return false;
}
function sec($label) { echo "\n  \u{25B8} $label\n"; }

/* ---------- DB ---------- */
function db() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=localhost;dbname=erp_db;charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
function q($sql, $params = []) { $s = db()->prepare($sql); $s->execute($params); return $s; }
function qv($sql, $params = []) { return q($sql, $params)->fetchColumn(); }
function qr($sql, $params = []) { return q($sql, $params)->fetch(); }
function qq($sql, $params = []) { return q($sql, $params)->fetchAll(); }

/* ---------- drive a real handler POST in a fresh process ---------- */
function runPost($target, array $post) {
    global $PHP, $RUNNER, $CSRF, $LOG;
    if (is_file($LOG)) file_put_contents($LOG, '');   // fresh log per POST
    $post['csrf_token'] = $CSRF;
    $tmp = __DIR__ . '/tmp_payload.json';
    file_put_contents($tmp, json_encode(['post' => $post, 'get' => []]));
    $cmd = escapeshellarg($PHP) . ' ' . escapeshellarg($RUNNER) . ' ' . escapeshellarg($target) . ' ' . escapeshellarg($tmp);
    $out = trim((string)shell_exec($cmd . ' 2>&1'));
    $log = is_file($LOG) ? trim((string)file_get_contents($LOG)) : '';
    // Filter the benign double-session_start notice from header.php (runner
    // pre-starts a session; header.php's bare session_start() then no-ops).
    $log = trim(preg_replace('/^.*session_start\(\): Ignoring session_start\(\).*$/m', '', (string)$log));
    if ($log !== '') {
        echo "         \u{2514} runtime log:\n" . preg_replace('/^/m', '            ', $log) . "\n";
    }
    return $out;
}

/* ---------- boot budget helpers for direct computations (Tests 5) ---------- */
$_SESSION = ['user_id' => 1];
require_once $ROOT . '/config/database.php';
require_once $ROOT . '/helpers/currency.php';
require_once $ROOT . '/helpers/security.php';
require_once $ROOT . '/helpers/rbac.php';
require_once $FIN . '/helpers/finance_helpers.php';
require_once $FIN . '/helpers/budget_helpers.php';

echo "F7 Integration Test — 6 scenarios from FINANCE/F7-testing\n";
echo "===========================================================\n";

/* =====================================================================
 * Cleanup: remove any prior "F7 TEST" run so the suite is repeatable.
 * (budget_versions/lines cascade on budget delete; transfers/revisions
 *  must go first because they FK-restrict on budget_lines.)
 * ===================================================================== */
sec("Cleanup of previous 'F7 TEST' data");
q("DELETE FROM budget_revisions WHERE budget_id IN (SELECT id FROM budgets WHERE budget_name LIKE 'F7 TEST%')");
q("DELETE FROM budget_transfers WHERE budget_id IN (SELECT id FROM budgets WHERE budget_name LIKE 'F7 TEST%')");
q("DELETE FROM budgets WHERE budget_name LIKE 'F7 TEST%'");   // cascades versions -> lines
q("DELETE FROM general_ledger     WHERE journal_entry_id IN (SELECT journal_entry_id FROM company_expenses WHERE description LIKE 'F7 TEST%' AND journal_entry_id IS NOT NULL)");
q("DELETE FROM journal_entry_details WHERE journal_entry_id IN (SELECT journal_entry_id FROM company_expenses WHERE description LIKE 'F7 TEST%' AND journal_entry_id IS NOT NULL)");
q("DELETE FROM journal_entries    WHERE id IN (SELECT journal_entry_id FROM company_expenses WHERE description LIKE 'F7 TEST%' AND journal_entry_id IS NOT NULL)");
q("DELETE FROM expense_items          WHERE expense_type='company_expense' AND expense_id IN (SELECT id FROM company_expenses WHERE description LIKE 'F7 TEST%')");
q("DELETE FROM expense_approval_history WHERE expense_type='company_expense' AND expense_id IN (SELECT id FROM company_expenses WHERE description LIKE 'F7 TEST%')");
q("DELETE FROM company_expenses WHERE description LIKE 'F7 TEST%'");
ok(true, 'prior test data removed');

/* =====================================================================
 * TEST 1 — Annual Budget (FY 2026-2027, department + account lines)
 * ===================================================================== */
sec("TEST 1 — Create Annual Budget FY 2026-2027");
$r = runPost("$FIN/budgets/create.php", [
    'budget_name'      => 'F7 TEST Annual Budget',
    'budget_type'      => 'annual',
    'financial_year_id'=> 1,
    'description'      => 'F7 integration test annual budget',
    'items' => [
        ['department_id' => 4, 'account_id' => 25, 'amount' => 15000, 'month' => '', 'notes' => 'Marketing advertising budget'],
        ['department_id' => 2, 'account_id' => 27, 'amount' => 10000, 'month' => '', 'notes' => 'IT software budget'],
        ['department_id' => 4, 'account_id' => 21, 'amount' => 5000,  'month' => '', 'notes' => 'Marketing travel budget'],
        ['department_id' => 2, 'account_id' => 21, 'amount' => 2000,  'month' => '', 'notes' => 'IT travel budget'],
    ],
]);

$budget = qr("SELECT * FROM budgets WHERE budget_name = 'F7 TEST Annual Budget'");
ok($budget !== false, 'budget row created');
$budgetId = (int)($budget['id'] ?? 0);
if (!$budgetId) { echo "      Stopping: budget not created.\n"; exit(1); }
ok($budget['status'] === 'draft', 'status = draft');
ok((int)$budget['financial_year_id'] === 1, 'linked to FY 2026-2027 (id=1)');
ok($budget['budget_type'] === 'annual', 'budget_type = annual');

$ver1 = qr("SELECT * FROM budget_versions WHERE budget_id = ? AND version_no = 1", [$budgetId]);
ok($ver1 !== false, 'version 1 created');
$ver1Id = (int)$ver1['id'];
ok($ver1['version_type'] === 'original' && $ver1['version_label'] === 'Original', 'version 1 = Original');
ok($ver1['status'] === 'active', 'version 1 active');
ok((int)$budget['current_version_id'] === $ver1Id, 'current_version_id -> version 1');

$lines1 = qq("SELECT * FROM budget_lines WHERE budget_version_id = ? ORDER BY chart_of_accounts_id, department_id", [$ver1Id]);
ok(count($lines1) === 4, '4 budget lines created');
$total = (float)qv("SELECT total_amount FROM budget_versions WHERE id = ?", [$ver1Id]);
ok(abs($total - 32000) < 0.005, 'version total recomputed = 32,000.00 (got ' . number_format($total, 2) . ')');

$l1 = qr("SELECT * FROM budget_lines WHERE budget_version_id = ? AND department_id = 4 AND chart_of_accounts_id = 25", [$ver1Id]);
$l2 = qr("SELECT * FROM budget_lines WHERE budget_version_id = ? AND department_id = 2 AND chart_of_accounts_id = 27", [$ver1Id]);
$l3 = qr("SELECT * FROM budget_lines WHERE budget_version_id = ? AND department_id = 4 AND chart_of_accounts_id = 21", [$ver1Id]);
$l4 = qr("SELECT * FROM budget_lines WHERE budget_version_id = ? AND department_id = 2 AND chart_of_accounts_id = 21", [$ver1Id]);
ok($l1 && $l2 && $l3 && $l4, 'department + account-wise lines present (Marketing/600-MKT, IT/600-SOFT, Travel lines)');

/* ---- workflow to activate (required before transfers/revisions) ---- */
sec("TEST 1 — Submit / approve / activate budget");
$glBefore = (int)qv("SELECT COUNT(*) FROM general_ledger");
runPost("$FIN/budgets/submit.php",  ['budget_id' => $budgetId]);
ok(qv("SELECT status FROM budgets WHERE id = ?", [$budgetId]) === 'pending_approval', 'submit -> pending_approval');

runPost("$FIN/budgets/approve.php", ['budget_id' => $budgetId, 'action' => 'approve']);
ok(qv("SELECT status FROM budgets WHERE id = ?", [$budgetId]) === 'approved', 'approve -> approved');

runPost("$FIN/budgets/activate.php",['budget_id' => $budgetId]);
ok(qv("SELECT status FROM budgets WHERE id = ?", [$budgetId]) === 'active', 'activate -> active');
ok(qv("SELECT status FROM budget_versions WHERE id = ?", [$ver1Id]) === 'active', 'version 1 remains active');

/* =====================================================================
 * TEST 2 — Budget Monitoring: record expense, actual auto-updates from GL
 * ===================================================================== */
sec("TEST 2 — Record expense through Expense module (Marketing 8,000)");
$r = runPost("$FIN/company_expenses/create.php", [
    'category_id'    => 15,        // Marketing -> default expense account 600-MKT (25)
    'department_id'  => 4,
    'project_id'     => '',
    'expense_date'   => '2026-08-05',
    'description'    => 'F7 TEST marketing expense',
    'payment_method' => 'cash',
    'cash_account_id'=> 5,
    'amount'         => 8000,
    'tax_amount'     => 0,
    'items'          => [['description' => '', 'amount' => '', 'tax_amount' => '']],
]);
$exp = qr("SELECT * FROM company_expenses WHERE description = 'F7 TEST marketing expense'");
ok($exp !== false, 'expense created');
$expId = (int)($exp['id'] ?? 0);
if (!$expId) { echo "      Stopping: expense not created.\n"; exit(1); }
ok($exp['status'] === 'draft', 'expense status = draft');
ok(abs((float)$exp['total_amount'] - 8000) < 0.005, 'total_amount = 8,000.00');

runPost("$FIN/company_expenses/submit.php", ['expense_id' => $expId]);
ok(qv("SELECT status FROM company_expenses WHERE id = ?", [$expId]) === 'pending_approval', 'submit -> pending_approval');

runPost("$FIN/company_expenses/approve.php", ['expense_id' => $expId]);
$exp2 = qr("SELECT * FROM company_expenses WHERE id = ?", [$expId]);
ok($exp2['status'] === 'approved', 'approve -> approved + posted to GL');
$journalId = (int)($exp2['journal_entry_id'] ?? 0);
ok($journalId > 0, 'journal_entry_id linked');
ok(qv("SELECT status FROM journal_entries WHERE id = ?", [$journalId]) === 'posted', 'journal posted by PostingEngine');
$glDebit = (float)qv("SELECT COALESCE(SUM(debit)-SUM(credit),0) FROM general_ledger WHERE journal_entry_id = ? AND account_id = 25", [$journalId]);
ok(abs($glDebit - 8000) < 0.005, 'GL carries 8,000 debit on 600-MKT');

/* Budget actuals update automatically (read-only from GL) */
$fy = ['id' => 1, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'];
$lineActual = budget_line_actual(db(), $l1, $fy, '2026-07-01', '2027-06-30');
ok(abs($lineActual - 8000) < 0.005, "budget_line_actual(Marketing/600-MKT) = 8,000.00 (got " . number_format($lineActual, 2) . ")");
$dimActual = budget_dimension_actual(db(), $fy, '2026-07-01', '2027-06-30', 4);
ok(abs($dimActual - 8000) < 0.005, "budget_dimension_actual(department=Marketing) = 8,000.00");

/* Budget module itself must NOT write to the GL */
$glAfter = (int)qv("SELECT COUNT(*) FROM general_ledger");
$glDelta = $glAfter - $glBefore;
ok($glDelta === 2, "exactly 2 new GL rows (DR expense / CR payable), got $glDelta — budgets wrote nothing to GL");

/* =====================================================================
 * TEST 3 — Budget Transfer: AED 5,000 Marketing -> IT (dept transfer)
 * ===================================================================== */
sec("TEST 3 — Transfer AED 5,000 from Marketing to IT");
$fromId = (int)$l1['id'];
$toId   = (int)$l2['id'];
runPost("$FIN/budget_transfers/create.php", [
    'budget_id'     => $budgetId,
    'transfer_type' => 'department',
    'from_line_id'  => $fromId,
    'to_line_id'    => $toId,
    'amount'        => 5000,
    'reason'        => 'F7 TEST transfer to IT',
]);
$tr = qr("SELECT * FROM budget_transfers WHERE budget_id = ? ORDER BY id DESC LIMIT 1", [$budgetId]);
ok($tr !== false, 'transfer created');
$trId = (int)($tr['id'] ?? 0);
ok($tr['status'] === 'pending', 'transfer status = pending');
ok($tr['transfer_type'] === 'department' && (int)$tr['amount'] === 5000, 'department transfer of 5,000');
ok($tr['transfer_no'] !== '' && strpos($tr['transfer_no'], 'TRF-') === 0, 'transfer_no generated (' . $tr['transfer_no'] . ')');
ok((int)$tr['from_line_id'] === $fromId && (int)$tr['to_line_id'] === $toId, 'from Marketing line -> to IT line');
ok((int)$tr['requested_by'] === 1 && $tr['requested_at'] !== null, 'requested_by/requested_at recorded');

runPost("$FIN/budget_transfers/approve.php", ['transfer_id' => $trId, 'action' => 'approve']);
$tr2 = qr("SELECT * FROM budget_transfers WHERE id = ?", [$trId]);
ok($tr2['status'] === 'approved', 'transfer approved');
ok((int)$tr2['approved_by'] === 1 && $tr2['approved_at'] !== null, 'approval history preserved (approved_by/approved_at)');

$a1 = (float)qv("SELECT budget_amount FROM budget_lines WHERE id = ?", [$fromId]);
$a2 = (float)qv("SELECT budget_amount FROM budget_lines WHERE id = ?", [$toId]);
ok(abs($a1 - 10000) < 0.005, 'Marketing line reduced 15,000 -> 10,000');
ok(abs($a2 - 15000) < 0.005, 'IT line increased 10,000 -> 15,000');
$totalAfter = (float)qv("SELECT total_amount FROM budget_versions WHERE id = ?", [$ver1Id]);
ok(abs($totalAfter - 32000) < 0.005, 'total budget unchanged = 32,000.00');

/* =====================================================================
 * TEST 4 — Budget Revision: new version, increase Travel, original intact
 * ===================================================================== */
sec("TEST 4 — Create revision version + increase Travel budget");
runPost("$FIN/budget_versions/create.php", [
    'budget_id'    => $budgetId,
    'version_type' => 'revision',
    'notes'        => 'F7 TEST revision',
]);
$ver2 = qr("SELECT * FROM budget_versions WHERE budget_id = ? AND version_no = 2", [$budgetId]);
ok($ver2 !== false, 'revision version created');
$ver2Id = (int)$ver2['id'];
ok($ver2['version_type'] === 'revision' && $ver2['version_label'] === 'Revision 1', 'version 2 = Revision 1');
ok($ver2['status'] === 'draft', 'version 2 is a draft');
$v2Lines = qq("SELECT * FROM budget_lines WHERE budget_version_id = ?", [$ver2Id]);
ok(count($v2Lines) === 4, 'version 2 cloned all 4 lines from version 1');
$v2Total = (float)qv("SELECT total_amount FROM budget_versions WHERE id = ?", [$ver2Id]);
ok(abs($v2Total - 32000) < 0.005, 'version 2 total = 32,000.00 (copy)');

$v2Travel = qr("SELECT * FROM budget_lines WHERE budget_version_id = ? AND department_id = 4 AND chart_of_accounts_id = 21", [$ver2Id]);
runPost("$FIN/budget_revisions/create.php", [
    'budget_id'     => $budgetId,
    'version_id'    => $ver2Id,
    'line_id'       => (int)$v2Travel['id'],
    'revision_type' => 'increase',
    'new_amount'    => 7500,
    'reason'        => 'F7 TEST travel increase',
]);
$rev = qr("SELECT * FROM budget_revisions WHERE budget_id = ? ORDER BY id DESC LIMIT 1", [$budgetId]);
ok($rev !== false, 'revision recorded');
ok((int)$rev['old_amount'] === 5000 && (int)$rev['new_amount'] === 7500 && (int)$rev['change_amount'] === 2500,
   'old_amount 5,000 -> new_amount 7,500 (change +2,500)');
ok($rev['revision_type'] === 'increase', 'revision_type = increase');

$newAmt = (float)qv("SELECT budget_amount FROM budget_lines WHERE id = ?", [(int)$v2Travel['id']]);
ok(abs($newAmt - 7500) < 0.005, 'version 2 Travel line = 7,500');
$v2Total2 = (float)qv("SELECT total_amount FROM budget_versions WHERE id = ?", [$ver2Id]);
ok(abs($v2Total2 - 34500) < 0.005, 'version 2 total = 34,500.00 after revision');

/* Original version 1 must be untouched */
$l3After = (float)qv("SELECT budget_amount FROM budget_lines WHERE id = ?", [(int)$l3['id']]);
ok(abs($l3After - 5000) < 0.005, 'version 1 (Original) Travel line still 5,000');
$ver1Total = (float)qv("SELECT total_amount FROM budget_versions WHERE id = ?", [$ver1Id]);
ok(abs($ver1Total - 32000) < 0.005, 'version 1 (Original) total still 32,000.00');
ok(qv("SELECT status FROM budget_versions WHERE id = ?", [$ver1Id]) === 'active', 'version 1 remains active/current');

/* =====================================================================
 * TEST 5 — Variance Analysis: amount, %, colour indicators
 * ===================================================================== */
sec("TEST 5 — Variance Analysis (Budget vs Actual on current version)");
$lines = budget_lines_for_version(db(), $ver1Id, $fy);
$v1 = null; $v2 = null;
foreach ($lines as $l) {
    if ((int)$l['department_id'] === 4 && (int)$l['chart_of_accounts_id'] === 25) $v1 = $l;
    if ((int)$l['department_id'] === 2 && (int)$l['chart_of_accounts_id'] === 27) $v2 = $l;
}
ok(abs($v1['spent'] - 8000) < 0.005 && abs($v1['budget_amount'] - 10000) < 0.005,
   'Marketing/600-MKT: budget 10,000, actual 8,000');
ok(abs($v1['variance'] - 2000) < 0.005, 'variance amount = 2,000 remaining');
ok(abs($v1['pct'] - 80) < 0.005, 'variance % = 80%');
ok(budget_variance_color($v1['pct']) === 'yellow', '80% -> YELLOW (near limit)');
ok(budget_variance_badge($v1['pct']) === 'bg-yellow-100 text-yellow-800', 'yellow badge class applied');
ok(budget_variance_label($v1['pct']) === 'Near Limit', 'label = "Near Limit"');

ok(abs($v2['spent']) < 0.005 && abs($v2['variance'] - 15000) < 0.005,
   'IT/600-SOFT: variance = 15,000, actual 0');
ok(abs($v2['pct']) < 0.005, 'IT variance % = 0%');
ok(budget_variance_color($v2['pct']) === 'green', '0% -> GREEN (within budget)');

/* Over-budget dimension surfaces RED (prove colour logic end-to-end) */
ok(budget_variance_color(105) === 'red' && budget_variance_label(105) === 'Exceeded', '>=100% -> RED (exceeded)');

/* =====================================================================
 * TEST 6 — Dashboard widgets
 * ===================================================================== */
sec("TEST 6 — Dashboard widgets (Total / Actual / Remaining / Over-budget depts)");
/* App convention: amounts stored in USD base currency, displayed in active currency
   (default USD). Do not override active_currency here — budget figures must render
   as stored. Boot the page in a fresh subprocess (with cwd = module dir so the
   relative header/footer requires resolve) for clean output. */
$dashFile = "$FIN/budget_reports/dashboard.php";
$setupFile = __DIR__ . '/F7-session-setup.php';
$dashCmd = 'cd ' . escapeshellarg(dirname($dashFile)) . ' && ' .
    escapeshellarg($PHP) .
    ' -d display_errors=0' .
    ' -d error_reporting=22527' .
    ' -d auto_prepend_file=' . escapeshellarg($setupFile) .
    ' ' . escapeshellarg($dashFile);
$html = (string)shell_exec($dashCmd . ' 2>&1');

ok($html !== false && stripos($html, 'ERROR') === false, 'dashboard renders without error');
ok(strpos($html, '32,000.00') !== false, 'Total Budget widget = 32,000.00');
ok(strpos($html, '8,000.00') !== false, 'Actual Spend widget = 8,000.00');
ok(strpos($html, '24,000.00') !== false, 'Remaining Budget widget = 24,000.00');
ok(strpos($html, '25%') !== false, 'Budget Utilization widget = 25%');
/* Departments over budget: Marketing 8,000/15,000 (53%), IT 0/15,000 (0%) -> none over.
   Recompute the exact same aggregation the dashboard widget uses. */
$deptAgg = [];
foreach (budget_lines_for_version(db(), $ver1Id, $fy) as $l) {
    if (!empty($l['department_id'])) {
        $d = (int)$l['department_id'];
        $deptAgg[$d]['name'] = $l['department_name'] ?? '—';
        $deptAgg[$d]['alloc'] = ($deptAgg[$d]['alloc'] ?? 0) + (float)$l['budget_amount'];
        $deptAgg[$d]['spent'] = ($deptAgg[$d]['spent'] ?? 0) + (float)$l['spent'];
    }
}
$overCount = 0;
foreach ($deptAgg as $d) { if ($d['alloc'] > 0 && $d['spent'] >= $d['alloc']) $overCount++; }
ok($overCount === 0, 'no departments exceed budget (Marketing 8,000/15,000 = 53%, IT 0/15,000 = 0%)');
ok(strpos($html, 'F7 TEST Annual Budget') !== false, 'budget listed in Budget Summary');

/* ---------- summary ---------- */
echo "\n==================================================\n";
echo $fail === 0 ? "ALL " . $pass . " CHECKS PASS \u{2705}\n" : "$fail of $pass checks FAILED \u{274C}\n";
echo "Test data left in DB for UI inspection (tagged 'F7 TEST'):\n";
echo "  Budget:     modules/finance/budgets/view.php?id=$budgetId\n";
echo "  Transfer:   modules/finance/budget_transfers/view.php?id=$trId\n";
echo "  Revision:   modules/finance/budget_revisions/view.php?id=" . (int)($rev['id'] ?? 0) . "\n";
echo "  Expense:    modules/finance/company_expenses/view.php?id=$expId\n";
echo "  Dashboard:  modules/finance/budget_reports/dashboard.php\n";
exit($fail === 0 ? 0 : 1);
