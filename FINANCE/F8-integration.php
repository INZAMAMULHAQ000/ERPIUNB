<?php
/**
 * F8 Integration Test — all 10 minimum scenarios from FINANCE/F8-Testing.txt
 *
 * Drives the real module POST handlers through F8-post-runner.php and verifies
 * tax math, GL posting, and register/ledger consistency after each.
 *
 * Run: php F8-integration.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$ROOT   = realpath(__DIR__ . '/..');
$FIN    = $ROOT . '/modules/finance';
$TAX    = $FIN . '/tax';
$PHP    = PHP_BINARY;
$RUNNER = __DIR__ . '/F8-post-runner.php';
$CSRF   = 'f8-test-token-1234567890';
$LOG    = __DIR__ . '/F8-post-runner.log';

/* ---------- tiny test harness ---------- */
$pass = 0; $fail = 0;
$GLOBALS['_failures'] = [];
function ok($cond, $label) {
    global $pass, $fail;
    $pass++;
    if ($cond) { echo "      \u{2705} $label\n"; return true; }
    $fail++;
    echo "      \u{274C} FAIL: $label\n";
    $GLOBALS['_failures'][] = $label;
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
function q($sql, $params = [])  { $s = db()->prepare($sql); $s->execute($params); return $s; }
function qv($sql, $params = []) { return q($sql, $params)->fetchColumn(); }
function qr($sql, $params = []) { return q($sql, $params)->fetch(); }
function qq($sql, $params = []) { return q($sql, $params)->fetchAll(); }

/* ---------- drive a real handler POST in a fresh process ---------- */
function runPost($target, array $post) {
    global $PHP, $RUNNER, $CSRF, $LOG;
    if (is_file($LOG)) file_put_contents($LOG, '');
    $post['csrf_token'] = $CSRF;
    $tmp = __DIR__ . '/tmp_payload.json';
    file_put_contents($tmp, json_encode(['post' => $post, 'get' => []]));
    $cmd = escapeshellarg($PHP) . ' ' . escapeshellarg($RUNNER) . ' '
         . escapeshellarg($target) . ' ' . escapeshellarg($tmp);
    $out = trim((string)shell_exec($cmd . ' 2>&1'));
    $log = is_file($LOG) ? trim((string)file_get_contents($LOG)) : '';
    $log = trim(preg_replace('/^.*session_start\(\): Ignoring session_start\(\).*$/m', '', (string)$log));
    if ($log !== '' && stripos($log, 'POST_RUN_OK') === false && strlen($log) > 5) {
        echo "         \u{2514} runtime log:\n" . preg_replace('/^/m', '            ', $log) . "\n";
    }
    return $out;
}

/* ---------- boot helpers for direct computations ---------- */
$_SESSION = ['user_id' => 1];
require_once $ROOT . '/config/database.php';
require_once $ROOT . '/helpers/currency.php';
require_once $ROOT . '/helpers/security.php';
require_once $ROOT . '/helpers/rbac.php';
require_once $FIN . '/helpers/finance_helpers.php';
require_once $TAX . '/TaxService.php';

echo "F8 Integration Test — 10 minimum scenarios from FINANCE/F8-Testing.txt\n";
echo "=======================================================================\n";

/* =====================================================================
 * Bootstrap & Settings
 * ===================================================================== */
sec("Bootstrap — ensure finance/tax settings are configured");

// Ensure AR/AP/Revenue/SalesReturn accounts exist (idempotent).
function ensureAccount($code, $name, $groupId, $typeId, $nature = 'credit') {
    $r = qr("SELECT id FROM chart_of_accounts WHERE account_code = ?", [$code]);
    if ($r) return (int)$r['id'];
    $opBalType = in_array($typeId, [20, 18], true) ? 'credit' : 'debit';
    q("INSERT INTO chart_of_accounts (account_code, account_name, group_id, type_id, opening_balance, opening_balance_type, allow_manual_entry, status)
      VALUES (?, ?, ?, ?, 0, ?, 1, 'active')", [$code, $name, $groupId, $typeId, $opBalType]);
    return (int)qv("SELECT id FROM chart_of_accounts WHERE account_code = ?", [$code]);
}
$arId   = ensureAccount('1200-AR', 'Accounts Receivable', 14, 21);
$apId   = ensureAccount('2000-AP', 'Accounts Payable', 21, 20);
$revId  = 11; // existing seed
$expId  = qr("SELECT id FROM chart_of_accounts WHERE account_code = '600-MKT'")['id'] ?? 25;
$srId   = ensureAccount('4000-SR', 'Sales Returns & Allowances', 40, 18);

function setSetting($k, $v) {
    $r = qr("SELECT id FROM settings WHERE setting_key = ?", [$k]);
    if ($r) {
        q("UPDATE settings SET setting_value = ? WHERE id = ?", [$v, $r['id']]);
    } else {
        q("INSERT INTO settings (setting_key, setting_value, created_at, updated_at) VALUES (?, ?, NOW(), NOW())", [$k, $v]);
    }
}
setSetting('finance_ar_account_id', $arId);
setSetting('finance_ap_account_id', $apId);
setSetting('finance_revenue_account_id', $revId);
setSetting('finance_expense_account_id', $expId);
setSetting('finance_sales_return_account_id', $srId);
setSetting('tax_pricing_method', 'exclusive');
setSetting('tax_output_tax_account_id', 38);
setSetting('tax_input_tax_account_id', 39);
setSetting('tax_adjustment_account_id', 40);
ok(true, "finance_ar=$arId  finance_ap=$apId  finance_revenue=$revId  finance_expense=$expId  finance_sales_return=$srId");

// vendor_invoice_number (migration 009) may never have been applied to this DB.
$hasVin = (int)qv("SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA='erp_db' AND TABLE_NAME='vendor_bills' AND COLUMN_NAME='vendor_invoice_number'");
if (!$hasVin) {
    q("ALTER TABLE vendor_bills
       ADD COLUMN vendor_invoice_number VARCHAR(100) DEFAULT NULL AFTER bill_number,
       ADD INDEX idx_vendor_invoice_v (vendor_invoice_number)");
    ok(true, 'migration 009 applied at bootstrap: vendor_bills.vendor_invoice_number added');
} else {
    ok(true, 'vendor_bills.vendor_invoice_number column already present');
}

/* =====================================================================
 * Cleanup of prior "F8" test data
 * ===================================================================== */
sec("Cleanup of previous 'F8' run data");

// Collect journal ids first for safe cascade deletion.
$invIds  = qq("SELECT id FROM customer_invoices WHERE notes LIKE 'F8 %'");
$invJournals = qq("SELECT journal_entry_id FROM customer_invoices WHERE notes LIKE 'F8 %' AND journal_entry_id IS NOT NULL");
$billJournals = qq("SELECT journal_entry_id FROM vendor_bills WHERE notes LIKE 'F8 %' AND journal_entry_id IS NOT NULL");
$cnJournals   = qq("SELECT journal_entry_id FROM credit_notes WHERE reason LIKE 'F8 %' AND journal_entry_id IS NOT NULL");
$allJournals  = array_merge(array_filter(array_column($invJournals,'journal_entry_id')),
                            array_filter(array_column($billJournals,'journal_entry_id')),
                            array_filter(array_column($cnJournals,'journal_entry_id')));
$allJournals  = array_unique($allJournals);

q("DELETE FROM general_ledger WHERE journal_entry_id IN (" . implode(',', array_map('intval', $allJournals ?: [0])) . ")");
q("DELETE FROM journal_entry_details WHERE journal_entry_id IN (" . implode(',', array_map('intval', $allJournals ?: [0])) . ")");
q("DELETE FROM journal_entries WHERE id IN (" . implode(',', array_map('intval', $allJournals ?: [0])) . ")");

q("DELETE FROM tax_transactions WHERE source_module='customer_invoice' AND source_record_id IN (SELECT id FROM customer_invoices WHERE notes LIKE 'F8 %')");
q("DELETE FROM tax_transactions WHERE source_module='vendor_bill' AND source_record_id IN (SELECT id FROM vendor_bills WHERE notes LIKE 'F8 %')");
q("DELETE FROM tax_transactions WHERE source_module='credit_note' AND source_record_id IN (SELECT id FROM credit_notes WHERE reason LIKE 'F8 %')");
// Tax adj rows for the closed-period test
q("DELETE FROM tax_transactions WHERE source_module='tax_adjustment' AND reference LIKE 'F8 %'");

q("DELETE FROM invoice_status_history WHERE invoice_id IN (SELECT id FROM customer_invoices WHERE notes LIKE 'F8 %')");
q("DELETE FROM invoice_items WHERE invoice_id IN (SELECT id FROM customer_invoices WHERE notes LIKE 'F8 %')");
q("DELETE FROM customer_invoices WHERE notes LIKE 'F8 %'");

q("DELETE FROM bill_status_history WHERE bill_id IN (SELECT id FROM vendor_bills WHERE notes LIKE 'F8 %')");
q("DELETE FROM vendor_bill_items WHERE bill_id IN (SELECT id FROM vendor_bills WHERE notes LIKE 'F8 %')");
q("DELETE FROM vendor_bills WHERE notes LIKE 'F8 %'");

q("DELETE FROM credit_notes WHERE reason LIKE 'F8 %'");

// Test-only tax periods (e.g. "January 2027") with no surviving transactions.
q("DELETE FROM tax_periods WHERE period_name='January 2027' AND tax_type_id=1");
q("DELETE FROM tax_periods WHERE period_name='January 2027' AND tax_type_id=5");

// Stale seed items (invoice/bill ids 1-7 vanished in earlier runs) collide with
// re-created F8 test ids — sweep orphaned detail rows so item lookups are exact.
q("DELETE FROM invoice_items WHERE invoice_id NOT IN (SELECT id FROM customer_invoices)");
q("DELETE FROM invoice_status_history WHERE invoice_id NOT IN (SELECT id FROM customer_invoices)");
q("DELETE FROM vendor_bill_items WHERE bill_id NOT IN (SELECT id FROM vendor_bills)");
q("DELETE FROM bill_status_history WHERE bill_id NOT IN (SELECT id FROM vendor_bills)");

// Restore rate 1 to 5% / is_default=1 (in case a prior run changed it).
q("UPDATE tax_rates SET rate_percentage=5.0000, is_default=1 WHERE id=1");
// Remove temporary test rate(s) we create below.
q("DELETE FROM tax_rates WHERE rate_name LIKE 'F8 %'");

ok(true, 'prior F8 data removed');

/* =====================================================================
 * TEST 1 — 5% exclusive (net 1000 → tax 50 → total 1050)
 *            + Tax posting: DR AR(total) + CR Revenue(net) + CR Output(tax)
 *            + tax_transactions register row (output, 50, rate 5)
 * ===================================================================== */
sec("TEST 1 — 5% exclusive invoice (net 1000 → tax 50 → total 1050)");

runPost("$FIN/customer_invoices/create.php", [
    'client_id'   => 6,
    'project_id'  => '',
    'invoice_date' => '2026-08-01',
    'due_date'    => '2026-08-31',
    'currency'    => 'AED',
    'payment_terms' => 'Net 30',
    'notes'       => 'F8 5pct exclusive 1000',
    'items' => [[
        'description'  => 'Consulting',
        'quantity'     => '1',
        'unit'         => 'svc',
        'unit_price'   => '1000.00',
        'discount_percent' => '0',
        'tax_rate_id'  => '1',
        'tax_status'   => 'taxable',
        'exemption_id' => '',
    ]],
]);
$inv1 = qr("SELECT * FROM customer_invoices WHERE notes = 'F8 5pct exclusive 1000'");
ok($inv1 !== false, 'invoice 1 created');
$inv1Id = (int)($inv1['id'] ?? 0);
ok($inv1['status'] === 'draft', 'status = draft before approve');
ok(abs((float)$inv1['tax_amount'] - 50) < 0.005, "inv1.tax_amount = 50.00 (got {$inv1['tax_amount']})");
ok(abs((float)$inv1['total_amount'] - 1050) < 0.005, "inv1.total_amount = 1050.00 (got {$inv1['total_amount']})");

$item1 = qr("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id DESC LIMIT 1", [$inv1Id]);
ok(abs((float)$item1['taxable_amount'] - 1000) < 0.005, 'item1.taxable_amount = 1000');
ok(abs((float)$item1['tax_amount'] - 50) < 0.005, 'item1.tax_amount = 50');
ok((float)$item1['tax_percent'] === 5.0, 'item1.tax_percent = 5');
ok((int)$item1['tax_status'] === 0 || $item1['tax_status'] === 'taxable', 'item1.tax_status = taxable');

runPost("$FIN/customer_invoices/approve.php", ['invoice_id' => $inv1Id]);
$inv1b = qr("SELECT * FROM customer_invoices WHERE id = ?", [$inv1Id]);
ok($inv1b['status'] === 'approved', 'after approve status = approved');
$j1 = (int)($inv1b['journal_entry_id'] ?? 0);
ok($j1 > 0, 'journal_entry_id linked');
ok(qv("SELECT status FROM journal_entries WHERE id = ?", [$j1]) === 'posted', 'journal 1 posted');

$jl1 = qq("SELECT * FROM journal_entry_details WHERE journal_entry_id = ? ORDER BY id", [$j1]);
$ar_d = $rev_c = $out_c = 0;
foreach ($jl1 as $l) {
    if ((int)$l['account_id'] === $arId)  $ar_d = (float)$l['debit_amount'];
    if ((int)$l['account_id'] === $revId) $rev_c = (float)$l['credit_amount'];
    if ((int)$l['account_id'] === 38)     $out_c = (float)$l['credit_amount'];
}
ok(abs($ar_d - 1050) < 0.005, "DR AR = 1050.00 (got $ar_d)");
ok(abs($rev_c - 1000) < 0.005, "CR Revenue = 1000.00 (got $rev_c)");
ok(abs($out_c - 50) < 0.005, "CR Output Tax = 50.00 (got $out_c)");

$reg1 = qr("SELECT * FROM tax_transactions WHERE source_module='customer_invoice' AND source_record_id=? AND status='active'", [$inv1Id]);
ok($reg1 !== false, 'tax_transactions register row created (active)');
ok((float)$reg1['taxable_amount'] === 1000.0, 'register.taxable = 1000');
ok((float)$reg1['tax_amount'] === 50.0, 'register.tax = 50');
ok((int)$reg1['tax_rate_id'] === 1, 'register.tax_rate_id = 1');
ok((float)$reg1['tax_rate'] === 5.0, 'register.tax_rate = 5');
ok($reg1['tax_direction'] === 'output', 'register.direction = output');
ok((int)$reg1['tax_period_id'] === 8, 'register linked to period August 2026 (id=8)');

/* =====================================================================
 * TEST 2 — 5% INCLUSIVE pricing (total 1050 → tax 50 → net 1000)
 * ===================================================================== */
sec("TEST 2 — 5% inclusive pricing (total 1050 → tax 50 → net 1000)");

setSetting('tax_pricing_method', 'inclusive');

runPost("$FIN/customer_invoices/create.php", [
    'client_id'   => 6,
    'project_id'  => '',
    'invoice_date' => '2026-08-02',
    'due_date'    => '2026-09-01',
    'currency'    => 'AED',
    'payment_terms' => 'Net 30',
    'notes'       => 'F8 5pct inclusive 1050',
    'items' => [[
        'description'  => 'Consulting inclusive',
        'quantity'     => '1',
        'unit'         => 'svc',
        'unit_price'   => '1050.00',
        'discount_percent' => '0',
        'tax_rate_id'  => '1',
        'tax_status'   => 'taxable',
        'exemption_id' => '',
    ]],
]);
$inv2 = qr("SELECT * FROM customer_invoices WHERE notes = 'F8 5pct inclusive 1050'");
$inv2Id = (int)$inv2['id'];
$item2 = qr("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id DESC LIMIT 1", [$inv2Id]);

ok(abs((float)$item2['taxable_amount'] - 1000) < 0.005, "item2.taxable (net) extracted = 1000.00 (got {$item2['taxable_amount']})");
ok(abs((float)$item2['tax_amount'] - 50) < 0.005, "item2.tax = 50.00 (got {$item2['tax_amount']})");
ok(abs((float)$item2['line_total'] - 1050) < 0.005, "item2.line_total (gross) = 1050.00 (got {$item2['line_total']})");

runPost("$FIN/customer_invoices/approve.php", ['invoice_id' => $inv2Id]);
$j2 = (int)(qr("SELECT journal_entry_id FROM customer_invoices WHERE id=?",[$inv2Id])['journal_entry_id']);
$jl2 = qq("SELECT * FROM journal_entry_details WHERE journal_entry_id = ?", [$j2]);
$ar_d2=$rev_c2=$out_c2=0;
foreach($jl2 as $l){
    if((int)$l['account_id']===$arId)$ar_d2=(float)$l['debit_amount'];
    if((int)$l['account_id']===$revId)$rev_c2=(float)$l['credit_amount'];
    if((int)$l['account_id']===38)$out_c2=(float)$l['credit_amount'];
}
ok(abs($ar_d2 - 1050) < 0.005, "DR AR = 1050 (gross)");
ok(abs($rev_c2 - 1000) < 0.005, "CR Revenue = 1000 (net extracted from gross)");
ok(abs($out_c2 - 50) < 0.005, "CR Output Tax = 50");

$reg2 = qr("SELECT * FROM tax_transactions WHERE source_module='customer_invoice' AND source_record_id=? AND status='active'", [$inv2Id]);
ok((float)$reg2['taxable_amount']===1000.0 && (float)$reg2['tax_amount']===50.0, 'register inclusive: taxable 1000, tax 50');

setSetting('tax_pricing_method', 'exclusive');
ok(qv("SELECT setting_value FROM settings WHERE setting_key='tax_pricing_method'") === 'exclusive', 'pricing_method restored to exclusive');

/* =====================================================================
 * TEST 3 — Zero-rated: tax 0 while taxable_amount preserved
 * ===================================================================== */
sec("TEST 3 — Zero-rated (tax 0, taxable preserved, status zero_rated)");

runPost("$FIN/customer_invoices/create.php", [
    'client_id'   => 6,
    'project_id'  => '',
    'invoice_date' => '2026-08-02',
    'due_date'    => '2026-09-01',
    'currency'    => 'AED',
    'payment_terms' => 'Net 30',
    'notes'       => 'F8 zero-rated 500',
    'items' => [[
        'description'  => 'Export supply',
        'quantity'     => '1',
        'unit'         => 'svc',
        'unit_price'   => '500.00',
        'discount_percent' => '0',
        'tax_rate_id'  => '2',     // Zero-Rated 0%
        'tax_status'   => 'zero_rated',
        'exemption_id' => '',
    ]],
]);
$inv3 = qr("SELECT * FROM customer_invoices WHERE notes = 'F8 zero-rated 500'");
$inv3Id = (int)$inv3['id'];
$item3 = qr("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id DESC LIMIT 1", [$inv3Id]);
ok(abs((float)$inv3['tax_amount']) < 0.005, 'inv3.tax_amount = 0');
ok(abs((float)$inv3['total_amount'] - 500) < 0.005, 'inv3.total_amount = 500');
ok(abs((float)$item3['taxable_amount'] - 500) < 0.005, 'item3.taxable preserved at 500');
ok(abs((float)$item3['tax_amount']) < 0.005, 'item3.tax = 0');

runPost("$FIN/customer_invoices/approve.php", ['invoice_id' => $inv3Id]);
$reg3 = qr("SELECT * FROM tax_transactions WHERE source_module='customer_invoice' AND source_record_id=?", [$inv3Id]);
ok($reg3['tax_status'] === 'zero_rated', 'register tax_status = zero_rated');
ok(abs((float)$reg3['tax_amount']) < 0.005, 'register tax = 0');

/* =====================================================================
 * TEST 4 — Exempt: reason/code (exemption_id) retained; tax 0
 * ===================================================================== */
sec("TEST 4 — Exempt line (EX-MEDICAL retained on register row, tax = 0)");

runPost("$FIN/customer_invoices/create.php", [
    'client_id'   => 6,
    'project_id'  => '',
    'invoice_date' => '2026-08-03',
    'due_date'    => '2026-09-02',
    'currency'    => 'AED',
    'payment_terms' => 'Net 30',
    'notes'       => 'F8 exempt 300 EX-MEDICAL',
    'items' => [[
        'description'  => 'Medical service',
        'quantity'     => '1',
        'unit'         => 'svc',
        'unit_price'   => '300.00',
        'discount_percent' => '0',
        'tax_rate_id'  => '3',     // Exempt 0% (rate id 3)
        'tax_status'   => 'exempt',
        'exemption_id' => '2',     // EX-MEDICAL
    ]],
]);
$inv4 = qr("SELECT * FROM customer_invoices WHERE notes = 'F8 exempt 300 EX-MEDICAL'");
$inv4Id = (int)$inv4['id'];
$item4 = qr("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id DESC LIMIT 1", [$inv4Id]);
ok(abs((float)$item4['tax_amount']) < 0.005, 'item4.tax = 0');
ok((int)$item4['tax_exemption_id'] === 2 || $item4['tax_exemption_id'] === '2', "item4.tax_exemption_id = 2 (got {$item4['tax_exemption_id']})");

runPost("$FIN/customer_invoices/approve.php", ['invoice_id' => $inv4Id]);
$reg4 = qr("SELECT * FROM tax_transactions WHERE source_module='customer_invoice' AND source_record_id=?", [$inv4Id]);
ok($reg4['tax_status'] === 'exempt', 'register tax_status = exempt');
ok((int)$reg4['exemption_id'] === 2, 'register.exemption_id = 2 (EX-MEDICAL retained)');
ok(abs((float)$reg4['tax_amount']) < 0.005, 'register tax = 0');

/* =====================================================================
 * TEST 5 — Vendor bill posting: DR Expense(net) + DR Input + CR AP(total)
 * ===================================================================== */
sec("TEST 5 — Vendor bill (DR Expense + DR Input Tax + CR AP)");

runPost("$FIN/vendor_bills/create.php", [
    'vendor_id'   => 1,
    'po_id'       => '',
    'grn_id'      => '',
    'bill_date'   => '2026-08-04',
    'due_date'    => '2026-09-03',
    'currency'    => 'AED',
    'vendor_invoice_number' => 'VINV-F8-001',
    'notes'       => 'F8 vendor bill 1000 net 5pct',
    'items' => [[
        'description'  => 'Software license',
        'quantity'     => '1',
        'unit'         => 'ea',
        'unit_price'   => '1000.00',
        'discount_percent' => '0',
        'tax_rate_id'  => '1',
        'tax_status'   => 'taxable',
        'exemption_id' => '',
        'expense_account_id' => (string)$expId,
    ]],
]);
$bill1 = qr("SELECT * FROM vendor_bills WHERE notes = 'F8 vendor bill 1000 net 5pct'");
$bill1Id = (int)$bill1['id'];
ok(abs((float)$bill1['tax_amount'] - 50) < 0.005, "bill1.tax = 50 (got {$bill1['tax_amount']})");
ok(abs((float)$bill1['total_amount'] - 1050) < 0.005, "bill1.total = 1050 (got {$bill1['total_amount']})");

runPost("$FIN/vendor_bills/approve.php", ['bill_id' => $bill1Id]);
$bill1b = qr("SELECT * FROM vendor_bills WHERE id = ?", [$bill1Id]);
$jB = (int)($bill1b['journal_entry_id'] ?? 0);
ok($bill1b['status'] === 'approved', 'bill status = approved');
ok($jB > 0, 'bill journal linked');
ok(qv("SELECT status FROM journal_entries WHERE id = ?", [$jB]) === 'posted', 'bill journal posted');

$jlB = qq("SELECT * FROM journal_entry_details WHERE journal_entry_id = ?", [$jB]);
$exp_dr=$in_dr=$ap_cr=0;
foreach ($jlB as $l){
    if ((int)$l['account_id']===$expId) $exp_dr=(float)$l['debit_amount'];
    if ((int)$l['account_id']===39)    $in_dr=(float)$l['debit_amount'];
    if ((int)$l['account_id']===$apId) $ap_cr=(float)$l['credit_amount'];
}
ok(abs($exp_dr - 1000) < 0.005, "DR Expense (net) = 1000 (got $exp_dr)");
ok(abs($in_dr - 50) < 0.005, "DR Input Tax = 50 (got $in_dr)");
ok(abs($ap_cr - 1050) < 0.005, "CR AP = 1050 (got $ap_cr)");

$regB = qr("SELECT * FROM tax_transactions WHERE source_module='vendor_bill' AND source_record_id=? AND status='active'", [$bill1Id]);
ok((float)$regB['taxable_amount']===1000.0 && (float)$regB['tax_amount']===50.0, 'register input row: taxable 1000, tax 50');
ok($regB['tax_direction'] === 'input', 'register.direction = input');

/* =====================================================================
 * TEST 6 — Historical rate preservation
 *   - Rate 1 is currently 5% (already used for previous tests).
 *   - Update rate 1 to 6% (the rate change).
 *   - New invoice pinned to rate 1 — gets 6% now.
 *   - Old invoices still show 5% where stored.
 * ===================================================================== */
sec("TEST 6 — Historical rate (rate1 flips 5% → 6%, new calc uses 6%, old register still 5/50)");

q("UPDATE tax_rates SET rate_percentage = 6.0000 WHERE id = 1");

runPost("$FIN/customer_invoices/create.php", [
    'client_id'   => 6,
    'project_id'  => '',
    'invoice_date' => '2026-08-20',
    'due_date'    => '2026-09-19',
    'currency'    => 'AED',
    'payment_terms' => 'Net 30',
    'notes'       => 'F8 historical new rate 6pct',
    'items' => [[
        'description'  => 'New-rate service',
        'quantity'     => '1',
        'unit'         => 'svc',
        'unit_price'   => '1000.00',
        'discount_percent' => '0',
        'tax_rate_id'  => '1',     // pinned; the rate row is now 6%
        'tax_status'   => 'taxable',
        'exemption_id' => '',
    ]],
]);
$inv6 = qr("SELECT * FROM customer_invoices WHERE notes = 'F8 historical new rate 6pct'");
$inv6Id = (int)$inv6['id'];
ok(abs((float)$inv6['tax_amount'] - 60) < 0.005, "after rate change, new invoice tax = 60 (got {$inv6['tax_amount']})");
runPost("$FIN/customer_invoices/approve.php", ['invoice_id' => $inv6Id]);
$reg6 = qr("SELECT * FROM tax_transactions WHERE source_module='customer_invoice' AND source_record_id=?", [$inv6Id]);
ok(abs((float)$reg6['tax_amount'] - 60) < 0.005, 'historical test: register new = 60');

// Old registers/totals still reflect 5%
$reg1now = qr("SELECT * FROM tax_transactions WHERE source_module='customer_invoice' AND source_record_id=? AND status='active'", [$inv1Id]);
ok((float)$reg1now['tax_amount'] === 50.0, 'HISTORICAL PRESERVATION: inv1 register still 50');
ok((int)$reg1now['tax_rate_id'] === 1, 'inv1 register still linked to rate id=1');
ok((float)$reg1now['tax_rate'] === 5.0, 'inv1 tax_rate stored as 5 (unchanged by rate1 update)');

// Restore rate1.
q("UPDATE tax_rates SET rate_percentage = 5.0000, is_default = 1 WHERE id = 1");
ok(true, 'rate 1 restored to 5% / is_default=1');

/* =====================================================================
 * TEST 7 — Credit note (return) reversing AR/Revenue/Tax treatment
 *   DR Sales Return (net), DR Output Tax (reduces liability), CR AR (total).
 *   tax register: direction=output, SIGN-FLIPPED amounts.
 *   Linked invoice balance reduced; invoice status flips to paid if zero.
 * ===================================================================== */
sec("TEST 7 — Credit note (return): DR Sales Return + DR Output Tax + CR AR; register = negative");

runPost("$FIN/credit_notes/create.php", [
    'client_id'        => 6,
    'invoice_id'       => (string)$inv1Id,
    'credit_note_date' => '2026-08-05',
    'credit_note_type' => 'return',
    'reason'           => 'F8 Customer return of inv1',
    'subtotal'         => '1000.00',
    'tax' => [
        'tax_rate_id'      => '1',
        'tax_status'       => 'taxable',
        'tax_exemption_id' => '',
    ],
]);
$cn1 = qr("SELECT * FROM credit_notes WHERE reason = 'F8 Customer return of inv1'");
$cn1Id = (int)$cn1['id'];
ok($cn1 !== false, 'credit note created');
ok($cn1['status'] === 'issued', 'credit note status = issued');
ok(abs((float)$cn1['tax_amount'] - 50) < 0.005, 'CN tax = 50');
ok(abs((float)$cn1['total_amount'] - 1050) < 0.005, 'CN total = 1050');

$jCN = (int)($cn1['journal_entry_id'] ?? 0);
ok($jCN > 0, 'CN journal linked');
ok(qv("SELECT status FROM journal_entries WHERE id=?",[$jCN]) === 'posted', 'CN journal posted');

$jlCN = qq("SELECT * FROM journal_entry_details WHERE journal_entry_id = ?", [$jCN]);
$sr_dr = $out_dr = $ar_cr = 0;
foreach ($jlCN as $l) {
    if ((int)$l['account_id'] === $srId) $sr_dr = (float)$l['debit_amount'];
    if ((int)$l['account_id'] === 38)   $out_dr = (float)$l['debit_amount'];
    if ((int)$l['account_id'] === $arId) $ar_cr = (float)$l['credit_amount'];
}
ok(abs($sr_dr - 1000) < 0.005, "DR Sales Return = 1000 (got $sr_dr)");
ok(abs($out_dr - 50) < 0.005, "DR Output Tax = 50 (reduces liability)");
ok(abs($ar_cr - 1050) < 0.005, "CR AR = 1050");

$regCN = qr("SELECT * FROM tax_transactions WHERE source_module='credit_note' AND source_record_id=?", [$cn1Id]);
ok((float)$regCN['taxable_amount'] === -1000.0, 'CN register.taxable = -1000 (reversal)');
ok((float)$regCN['tax_amount'] === -50.0, 'CN register.tax = -50 (reversal)');
ok($regCN['tax_direction'] === 'output', 'CN register.direction = output');

// Linked invoice balance reduced.
$inv1after = qr("SELECT * FROM customer_invoices WHERE id = ?", [$inv1Id]);
ok(abs((float)$inv1after['balance_amount']) < 0.005, 'linked invoice balance reduced to 0');
ok($inv1after['status'] === 'paid', 'linked invoice status = paid');

/* =====================================================================
 * TEST 8 — Closed tax period blocks normal modifications
 *   - Approve a "January 2027" invoice (auto-create period, open)
 *   - Submit + Close that period
 *   - Another January-2027 invoice should be BLOCKED at approve:
 *       status stays draft, no journal, no register rows.
 * ===================================================================== */
sec("TEST 8 — Closed period blocks modifications");

runPost("$FIN/customer_invoices/create.php", [
    'client_id'   => 6,
    'project_id'  => '',
    'invoice_date' => '2027-01-15',
    'due_date'    => '2027-02-14',
    'currency'    => 'AED',
    'payment_terms' => 'Net 30',
    'notes'       => 'F8 closed-period baseline 250',
    'items' => [[
        'description'  => 'Baseline Jan2027',
        'quantity'     => '1',
        'unit'         => 'svc',
        'unit_price'   => '250.00',
        'discount_percent' => '0',
        'tax_rate_id'  => '1',
        'tax_status'   => 'taxable',
        'exemption_id' => '',
    ]],
]);
$invCpOpen = qr("SELECT * FROM customer_invoices WHERE notes = 'F8 closed-period baseline 250'");
$invCpOpenId = (int)$invCpOpen['id'];
runPost("$FIN/customer_invoices/approve.php", ['invoice_id' => $invCpOpenId]);
$invCpOpenAfter = qr("SELECT * FROM customer_invoices WHERE id = ?", [$invCpOpenId]);
ok($invCpOpenAfter['status'] === 'approved', 'baseline Jan-2027 invoice approved (period auto-created open)');

// Find the period (January 2027, tax_type 1, status open).
$period = qr("SELECT * FROM tax_periods WHERE tax_type_id=1 AND period_name='January 2027'");
$cpid = (int)($period['id'] ?? 0);
ok($cpid > 0, 'auto-created January 2027 period found');
ok($period['status'] === 'open', 'Jan 2027 period status = open baseline');

// Submit then close.
runPost("$TAX/periods/submit.php", ['period_id' => $cpid]);
$p2 = qr("SELECT status FROM tax_periods WHERE id=?",[$cpid]);
ok($p2['status'] === 'submitted', 'Jan-2027 period submitted');

runPost("$TAX/periods/close.php", ['period_id' => $cpid]);
$p3 = qr("SELECT status FROM tax_periods WHERE id=?",[$cpid]);
ok($p3['status'] === 'closed', 'Jan-2027 period closed');

// Now try to record another Jan-2027 invoice — should be BLOCKED.
runPost("$FIN/customer_invoices/create.php", [
    'client_id'   => 6,
    'project_id'  => '',
    'invoice_date' => '2027-01-20',
    'due_date'    => '2027-02-19',
    'currency'    => 'AED',
    'payment_terms' => 'Net 30',
    'notes'       => 'F8 closed-period blocked 300',
    'items' => [[
        'description'  => 'Block attempt Jan2027',
        'quantity'     => '1',
        'unit'         => 'svc',
        'unit_price'   => '300.00',
        'discount_percent' => '0',
        'tax_rate_id'  => '1',
        'tax_status'   => 'taxable',
        'exemption_id' => '',
    ]],
]);
$invCpBlo = qr("SELECT * FROM customer_invoices WHERE notes = 'F8 closed-period blocked 300'");
$invCpBloId = (int)$invCpBlo['id'];
runPost("$FIN/customer_invoices/approve.php", ['invoice_id' => $invCpBloId]);
$invCpBloAfter = qr("SELECT * FROM customer_invoices WHERE id = ?", [$invCpBloId]);
ok($invCpBloAfter['status'] === 'draft', 'closed period: invoice stays draft');
ok(empty($invCpBloAfter['journal_entry_id']), 'closed period: NO journal_entry_id attached');
$regBlocked = qq("SELECT id FROM tax_transactions WHERE source_module='customer_invoice' AND source_record_id=? AND status='active'", [$invCpBloId]);
ok(count($regBlocked) === 0, 'closed period: NO tax register rows');

ok($p3['status'] === 'closed', 'period remains closed (test left it in that state)');

/* =====================================================================
 * TEST 9 — Ledger reconciliation
 *   Σ tax_transactions(active,output)  ==  Σ GL(38) (credit - debit)
 *   Σ tax_transactions(active,input)   ==  Σ GL(39) (debit  - credit)
 * ===================================================================== */
sec("TEST 9 — Ledger reconciliation: register totals vs GL tax accounts");

// Sum ACTIVE register rows for all test docs (notes/reason LIKE 'F8 %').
$regOut = (float)qv("
    SELECT COALESCE(SUM(x.tax_amount),0)
    FROM tax_transactions x
    WHERE x.status='active' AND x.tax_direction='output'
      AND (
        (x.source_module='customer_invoice' AND x.source_record_id IN (SELECT id FROM customer_invoices WHERE notes LIKE 'F8 %'))
     OR (x.source_module='credit_note'      AND x.source_record_id IN (SELECT id FROM credit_notes      WHERE reason  LIKE 'F8 %'))
      )
");
$regIn = (float)qv("
    SELECT COALESCE(SUM(x.tax_amount),0)
    FROM tax_transactions x
    WHERE x.status='active' AND x.tax_direction='input'
      AND x.source_module='vendor_bill'
      AND x.source_record_id IN (SELECT id FROM vendor_bills WHERE notes LIKE 'F8 %')
");

// GL: net = credit - debit for the same set of test journals.
$jnIdsHead = array_unique(array_filter(array_merge(
    array_column(qq("SELECT journal_entry_id AS j FROM customer_invoices WHERE notes LIKE 'F8 %' AND journal_entry_id IS NOT NULL"), 'j'),
    array_column(qq("SELECT journal_entry_id AS j FROM vendor_bills      WHERE notes LIKE 'F8 %' AND journal_entry_id IS NOT NULL"), 'j'),
    array_column(qq("SELECT journal_entry_id AS j FROM credit_notes      WHERE reason         LIKE 'F8 %' AND journal_entry_id IS NOT NULL"), 'j')
)));
$jnList = implode(',', array_map('intval', $jnIdsHead ?: [0]));

$glOutDr = (float)qv("SELECT COALESCE(SUM(debit),0)  FROM general_ledger WHERE journal_entry_id IN ($jnList) AND account_id = 38");
$glOutCr = (float)qv("SELECT COALESCE(SUM(credit),0) FROM general_ledger WHERE journal_entry_id IN ($jnList) AND account_id = 38");
$glInDr  = (float)qv("SELECT COALESCE(SUM(debit),0)  FROM general_ledger WHERE journal_entry_id IN ($jnList) AND account_id = 39");
$glInCr  = (float)qv("SELECT COALESCE(SUM(credit),0) FROM general_ledger WHERE journal_entry_id IN ($jnList) AND account_id = 39");

$glOut = $glOutCr - $glOutDr;
$glIn  = $glInDr  - $glInCr;

ok(abs($regOut - $glOut) < 0.005, "register output  vs GL(38) credit-debit  : reg=$regOut  gl=$glOut");
ok(abs($regIn  - $glIn ) < 0.005, "register input   vs GL(39) debit-credit  : reg=$regIn   gl=$glIn");
ok(abs($regOut - 122.5) < 0.005, "register output expected 122.5 (50 inv1 + 50 incl + 60 hist - 50 CN + 12.5 baseline) (got $regOut)");
ok(abs($regIn  - 50)  < 0.005, "register input  expected  50 (50 bill) (got $regIn)");

/* ---------- summary ---------- */
echo "\n==================================================\n";
if ($fail === 0) {
    echo "ALL $pass CHECKS PASS \u{2705}\n";
} else {
    echo count($GLOBALS['_failures']) . " of $pass checks FAILED \u{274C}\n";
    foreach ($GLOBALS['_failures'] as $f) echo "    - $f\n";
}
echo "\nTest data left in DB for UI inspection (tagged 'F8 '):\n";
echo "  Invoice 1 (5% excl):     $FIN/customer_invoices/view.php?id=$inv1Id\n";
echo "  Invoice 2 (5% incl):     $FIN/customer_invoices/view.php?id=$inv2Id\n";
echo "  Invoice 3 (zero-rated):  $FIN/customer_invoices/view.php?id=$inv3Id\n";
echo "  Invoice 4 (exempt):      $FIN/customer_invoices/view.php?id=$inv4Id\n";
echo "  Bill 1 (input tax):      $FIN/vendor_bills/view.php?id=$bill1Id\n";
echo "  Invoice 6 (hist 6%):     $FIN/customer_invoices/view.php?id=$inv6Id\n";
echo "  Credit Note:             $FIN/credit_notes/view.php?id=$cn1Id\n";
echo "  Baseline Jan-2027:       $FIN/customer_invoices/view.php?id=$invCpOpenId\n";
echo "  Blocked Jan-2027:        $FIN/customer_invoices/view.php?id=$invCpBloId\n";
echo "  Tax Register:            $TAX/transactions/index.php\n";
echo "  Reports:                 $TAX/reports/index.php\n";
echo "  Tax Periods:             $TAX/periods/index.php\n";
exit($fail === 0 ? 0 : 1);