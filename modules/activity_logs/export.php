<?php
session_start();
$basePath = dirname(__DIR__, 2);
require_once $basePath . '/config/database.php';
require_once $basePath . '/helpers/security.php';
require_once $basePath . '/helpers/rbac.php';
require_once $basePath . '/middleware/auth.php';

requireAuth();
if (!hasPermission('reports.export')) { die('Access Denied'); }

$db = getDB();

$search    = trim($_GET['search']    ?? '');
$module    = trim($_GET['module']    ?? '');
$user_id   = trim($_GET['user_id']   ?? '');
$date_from = trim($_GET['date_from'] ?? date('Y-m-01'));
$date_to   = trim($_GET['date_to']   ?? date('Y-m-d'));

$where  = "WHERE al.created_at BETWEEN ? AND ?";
$params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];

if ($search) {
    $where .= " AND (al.action LIKE ? OR al.module LIKE ? OR u.name LIKE ? OR al.ip_address LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($module)  { $where .= " AND al.module=?";  $params[] = $module; }
if ($user_id) { $where .= " AND al.user_id=?"; $params[] = $user_id; }

$stmt = $db->prepare("
    SELECT al.*, u.name AS user_name
    FROM audit_logs al LEFT JOIN users u ON al.user_id=u.id
    $where ORDER BY al.created_at DESC LIMIT 10000
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

logAudit($_SESSION['user_id'], 'exported_activity_logs', 'auditlogs');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="activity_logs_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF");
fputcsv($out, ['ID','User','Action','Module','IP Address','Timestamp']);
foreach ($logs as $log) {
    fputcsv($out, [
        $log['id'],
        $log['user_name'] ?? 'System',
        $log['action'],
        $log['module'],
        $log['ip_address'] ?? '',
        $log['created_at'],
    ]);
}
fclose($out);
exit;
