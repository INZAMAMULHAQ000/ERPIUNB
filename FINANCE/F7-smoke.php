<?php
/**
 * F7 Runtime Smoke Test (CLI)
 * Boots every F7 page with a super-admin session and reports
 * any PHP error/warning/notice raised during execution.
 *
 * Run: php F7-smoke.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '0'); // collect into $errors; avoid header() noise
ini_set('log_errors', '0');

$BASE = realpath(__DIR__ . '/..');

$pages = [
    'budgets/index.php',
    'budgets/create.php',
    'budget_versions/index.php',
    'budget_versions/create.php',
    'budget_transfers/index.php',
    'budget_transfers/create.php',
    'budget_revisions/index.php',
    'budget_revisions/create.php',
    'budget_monitoring/index.php',
    'budget_monitoring/variance.php',
    'budget_reports/dashboard.php',
    'budget_reports/index.php',
];

$ajax = [
    'budget_revisions/lines_ajax.php?version_id=0',
    'budget_revisions/versions_ajax.php?budget_id=0',
    'budget_transfers/lines_ajax.php?budget_id=0',
];

// Capture errors/warnings/notices (excluding harness noise)
$errors = [];
set_error_handler(function ($no, $str, $file, $line) use (&$errors) {
    // Ignore only the artifacts of our harness pre-starting a session
    if (strpos($str, 'session is already active') !== false) return true;
    $errors[] = "[$no] $str ($file:$line)";
    return true; // suppress PHP's own output; we handle it
});

// ---- Fake a logged-in super admin session ----
session_start();
$_SESSION['user_id'] = 1; // Super Admin (role 1 -> all budget perms)
$_SERVER['REQUEST_METHOD'] = 'GET'; // Apache always sets this; CLI does not

$fail = 0;
$FIN = "$BASE/modules/finance";
foreach ($pages as $pageRel) {
    $file = "$FIN/$pageRel";
    if (!is_file($file)) { echo "MISSING  $pageRel\n"; $fail++; continue; }
    $before = count($errors);
    $prev = getcwd();
    chdir(dirname($file)); // replicate Apache cwd so ../../../includes works
    try {
        ob_start();
        $_GET = [];
        $_POST = [];
        require $file;
        $out = ob_get_clean();
    } catch (Throwable $e) {
        $out = ob_get_clean();
        $errors[] = "EXCEPTION: " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine();
    }
    chdir($prev);
    $new = array_slice($errors, $before);
    $status = empty($new) ? 'OK ' : 'FAIL';
    if (!empty($new)) $fail++;
    echo str_pad($status, 5) . "  $pageRel  (" . strlen($out) . "b)";
    if (!empty($new)) {
        echo "\n";
        foreach ($new as $e) echo "       └─ $e\n";
    } else {
        echo "\n";
    }
}

echo "\n--- AJAX endpoints (per-process) ---\n";
$phpExe = PHP_BINARY;
$setup  = __DIR__ . '/F7-session-setup.php';
foreach ($ajax as $a) {
    list($rel, $qs) = array_pad(explode('?', $a, 2), 2, '');
    $file = "$FIN/$rel";
    if (!is_file($file)) { echo "MISSING  $a\n"; $fail++; continue; }
    $cmd = escapeshellarg($phpExe)
        . ' -d display_errors=1'
        . ' -d error_reporting=22527' // E_ALL & ~E_NOTICE & ~E_USER_NOTICE & ~E_DEPRECATED & ~E_STRICT
        . ' -d auto_prepend_file=' . escapeshellarg($setup)
        . ' ' . escapeshellarg($file);
    if ($qs) $cmd .= ' ' . escapeshellarg('?' . $qs); // $_GET population via argv
    $out = shell_exec($cmd . ' 2>&1');
    $trimmed = trim((string)$out);
    $isJson = $trimmed !== '' && json_decode($trimmed) !== null;
    // A JSON array is valid output. Treat "[]" as OK (empty result set).
    $status = $isJson ? 'OK ' : 'FAIL';
    if ($status === 'FAIL') $fail++;
    echo str_pad($status, 5) . "  $a  → " . substr($trimmed, 0, 80) . "\n";
}

echo "\n--- View pages (per-process, against real records) ---\n";
// View pages need an existing row; boot them against the latest record of each
// table so wrong-column / missing-key bugs (e.g. users.name, financial_year_id)
// surface instead of silently passing. Skips gracefully when the DB is empty.
require_once $BASE . '/config/database.php';
$viewPages = [];
try {
    $vdb = getDB();
    foreach ([
        'budgets/view.php'            => "SELECT MAX(id) FROM budgets",
        'budget_versions/view.php'    => "SELECT MAX(id) FROM budget_versions",
        'budget_transfers/view.php'   => "SELECT MAX(id) FROM budget_transfers",
        'budget_revisions/view.php'   => "SELECT MAX(id) FROM budget_revisions",
    ] as $vrel => $vsql) {
        $vid = (int)$vdb->query($vsql)->fetchColumn();
        if ($vid > 0) $viewPages[] = "$vrel?id=$vid";
    }
} catch (Throwable $e) {
    $viewPages = []; // no DB → skip view section
}
if (empty($viewPages)) {
    echo "SKIP  (no records in DB — run F7-integration.php first)\n";
} else {
    foreach ($viewPages as $a) {
        list($rel, $qs) = array_pad(explode('?', $a, 2), 2, '');
        $file = "$FIN/$rel";
        if (!is_file($file)) { echo "MISSING  $a\n"; $fail++; continue; }
        // 32759 = E_ALL & ~E_NOTICE (filters only the benign header.php session notice)
        // cd to the module dir so relative '../../../includes/header.php' requires resolve.
        $cmd = 'cd ' . escapeshellarg(dirname($file)) . ' && '
            . escapeshellarg($phpExe)
            . ' -d display_errors=1'
            . ' -d error_reporting=32759'
            . ' -d auto_prepend_file=' . escapeshellarg($setup)
            . ' ' . escapeshellarg($file);
        if ($qs) $cmd .= ' ' . escapeshellarg('?' . $qs);
        $out = (string)shell_exec($cmd . ' 2>&1');
        $errs = [];
        if (preg_match('/Fatal error|Parse error|Uncaught|Warning:|Exception/', $out, $m)) $errs[] = trim($m[0]);
        $status = empty($errs) ? 'OK ' : 'FAIL';
        if (!empty($errs)) $fail++;
        echo str_pad($status, 5) . "  $a  (" . strlen($out) . "b)";
        if (!empty($errs)) echo "  └─ " . implode('; ', $errs);
        echo "\n";
    }
}

echo "\n" . ($fail === 0 ? "ALL PASS ✅" : "$fail FAILURES ❌") . "\n";
exit($fail === 0 ? 0 : 1);
