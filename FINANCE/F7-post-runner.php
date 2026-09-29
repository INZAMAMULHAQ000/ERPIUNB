<?php
/**
 * F7 POST runner — drives a real module handler with POST data in a
 * fresh process. Each handler calls redirect()/exit, so the parent
 * integration script runs each POST here and verifies DB state after.
 *
 * Usage: php F7-post-runner.php <target_rel_path> <json_payload_file>
 *   json_payload = { "post": {...}, "get": {...} }
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');   // headers_already_sent noise is harness-only
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/F7-post-runner.log');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['user_id']  = 1;                    // Super Admin (role 1)
$_SESSION['csrf_token'] = 'f7-test-token-1234567890';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'localhost';

$payload = json_decode(file_get_contents($argv[2]), true);
$_POST = $payload['post'] ?? [];
$_GET  = $payload['get']  ?? [];

$target = $argv[1];
chdir(dirname($target)); // replicate Apache cwd so ../../../includes resolves
require $target;

// If the handler returns (no redirect), we still report success;
// the parent decides via DB assertions.
echo "POST_RUN_OK\n";
