<?php
// Session bootstrap for per-process AJAX smoke tests (CLI).
// auto_prepend_file => each target runs in a fresh process, so `exit`
// inside the AJAX endpoint cannot kill the harness, and no variable
// collisions with the page under test.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['user_id'] = 1; // Super Admin
$_SERVER['REQUEST_METHOD'] = 'GET';
// Populate $_GET from the trailing argv (e.g. '?budget_id=0')
if (isset($argv[1]) && strpos($argv[1], '?') === 0) {
    parse_str(ltrim($argv[1], '?'), $_GET);
    $_SERVER['QUERY_STRING'] = ltrim($argv[1], '?');
}
