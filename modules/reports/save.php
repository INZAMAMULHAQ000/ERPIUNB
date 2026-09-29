<?php
session_start();
$basePath = dirname(__DIR__, 2);
require_once $basePath . '/config/database.php';
require_once $basePath . '/helpers/security.php';
require_once $basePath . '/helpers/rbac.php';
require_once $basePath . '/middleware/auth.php';

requireAuth();
if (!hasPermission('reports.view')) { die('Access Denied'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    header('Location: index.php'); exit;
}

$name        = trim($_POST['name'] ?? '');
$report_type = trim($_POST['report_type'] ?? '');
$filters     = $_POST['filters'] ?? '{}';

if (!$name || !$report_type) {
    header('Location: index.php'); exit;
}

$db = getDB();
$stmt = $db->prepare("INSERT INTO saved_reports (name, report_type, filters, created_by) VALUES (?,?,?,?)");
$stmt->execute([$name, $report_type, $filters, $_SESSION['user_id']]);

logAudit($_SESSION['user_id'], 'saved_report_' . $report_type, 'reports');

header('Location: saved.php?saved=1'); exit;
