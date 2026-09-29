<?php
session_start();
$basePath = dirname(__DIR__, 2);
require_once $basePath . '/config/database.php';
require_once $basePath . '/helpers/security.php';
require_once $basePath . '/helpers/rbac.php';
require_once $basePath . '/middleware/auth.php';
require_once $basePath . '/helpers/report_engine.php';

requireAuth();

if (!hasPermission('reports.export')) {
    http_response_code(403);
    die('Access Denied');
}

$type   = $_GET['type']   ?? '';
$format = $_GET['format'] ?? 'csv';

if (!$type) { header('Location: index.php'); exit; }

$filters = [
    'date_from'     => $_GET['date_from']     ?? date('Y-m-01'),
    'date_to'       => $_GET['date_to']       ?? date('Y-m-d'),
    'department_id' => $_GET['department_id'] ?? '',
    'employee_id'   => $_GET['employee_id']   ?? '',
    'project_id'    => $_GET['project_id']    ?? '',
    'client_id'     => $_GET['client_id']     ?? '',
    'status'        => $_GET['status']        ?? '',
    'category_id'   => $_GET['category_id']   ?? '',
    'priority'      => $_GET['priority']      ?? '',
];

$db      = getDB();
$data    = getReportData($db, $type, $filters);
$columns = getReportColumns($type);
$title   = getReportTitle($type);

// Log export
$stmt = $db->prepare("INSERT INTO report_exports (report_type, export_format, filters, exported_by) VALUES (?,?,?,?)");
$stmt->execute([$type, $format, json_encode($filters), $_SESSION['user_id']]);
logAudit($_SESSION['user_id'], 'exported_report_' . $type . '_' . $format, 'reports');

if ($format === 'csv') {
    $filename = sanitize($type) . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fputs($out, "\xEF\xBB\xBF");

    // Header row
    fputcsv($out, array_column($columns, 'label'));

    foreach ($data as $row) {
        $line = [];
        foreach ($columns as $col) {
            $line[] = $row[$col['key']] ?? '';
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

if ($format === 'pdf') {
    // Try template system first
    require_once $basePath . '/helpers/template_renderer.php';

    // Build template data from report data
    $summary = getReportSummary($type, $data);
    $templateData = [
        'report_title'   => $title,
        'report_type'    => $type,
        'date_from'      => $filters['date_from'],
        'date_to'        => $filters['date_to'],
        'generated_by'   => $_SESSION['user_name'] ?? 'User',
        'generated_date' => date('Y-m-d H:i:s'),
        'generated_time' => date('H:i:s'),
        'record_count'   => count($data),
        'report_data'    => $data,
        'report_columns' => $columns,
    ];
    // Flatten summary into placeholders
    if (!empty($summary)) {
        foreach ($summary as $i => $card) {
            $templateData['summary_' . $i . '_label'] = $card['label'];
            $templateData['summary_' . $i . '_value'] = $card['value'];
        }
    }

    $filename = sanitize($type) . '_' . date('Ymd_His');

    if (hasTemplateForModule('reports', $type)) {
        streamTemplateForModule('reports', $type, $templateData, $filename);
        // streamTemplateForModule calls exit() — fallback below if no template
    }

    // Fallback: hardcoded HTML-to-print PDF
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($title) ?> Report</title>
<style>
  body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; }
  h1 { font-size: 16px; margin-bottom: 4px; }
  .meta { color: #666; font-size: 10px; margin-bottom: 12px; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #1e40af; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; }
  td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; }
  tr:nth-child(even) td { background: #f9fafb; }
  .footer { margin-top: 16px; font-size: 9px; color: #999; text-align: right; }
  @media print { @page { margin: 1cm; } }
</style>
</head>
<body onload="window.print()">
<h1><?= htmlspecialchars($title) ?> Report</h1>
<div class="meta">
  Generated: <?= date('Y-m-d H:i:s') ?> &nbsp;|&nbsp;
  Period: <?= htmlspecialchars($filters['date_from']) ?> to <?= htmlspecialchars($filters['date_to']) ?> &nbsp;|&nbsp;
  Records: <?= count($data) ?>
</div>
<table>
  <thead>
    <tr><?php foreach ($columns as $col): ?><th><?= htmlspecialchars($col['label']) ?></th><?php endforeach; ?></tr>
  </thead>
  <tbody>
    <?php foreach ($data as $row): ?>
    <tr><?php foreach ($columns as $col): ?><td><?= htmlspecialchars((string)($row[$col['key']] ?? '-')) ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
    <?php if (empty($data)): ?>
    <tr><td colspan="<?= count($columns) ?>" style="text-align:center;padding:20px;color:#999;">No data found</td></tr>
    <?php endif; ?>
  </tbody>
</table>
<div class="footer">ERP System &mdash; Confidential</div>
</body>
</html>
    <?php
    exit;
}
