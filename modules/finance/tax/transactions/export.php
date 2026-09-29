<?php
session_start();
$basePath = dirname(__DIR__, 4);
require_once $basePath . '/config/database.php';
require_once $basePath . '/helpers/security.php';
require_once $basePath . '/helpers/rbac.php';
require_once $basePath . '/middleware/auth.php';

requireAuth();
if (!(hasPermission('tax.export') || hasPermission('tax.view'))) { die('Access Denied'); }

$db = getDB();
require_once $basePath . '/modules/finance/tax/helpers/tax_ui.php';

// ---- Filters (GET) — same WHERE builder + params as index.php ----
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');
$taxTypeId  = (int)($_GET['tax_type_id'] ?? 0);
$direction  = trim($_GET['direction'] ?? '');
$txStatus   = trim($_GET['status'] ?? '');
$sourceMod  = trim($_GET['source_module'] ?? '');

$validDirections = ['output', 'input', 'adjustment'];
$validStatuses   = ['active', 'reversed'];
$validModules    = ['customer_invoice', 'vendor_bill', 'company_expense', 'credit_note', 'debit_note', 'tax_adjustment'];

$where  = [];
$params = [];

if ($dateFrom && strtotime($dateFrom) !== false) { $where[] = 'transaction_date >= ?'; $params[] = $dateFrom; }
if ($dateTo   && strtotime($dateTo)   !== false) { $where[] = 'transaction_date <= ?'; $params[] = $dateTo; }
if ($taxTypeId)                                  { $where[] = 'tax_type_id = ?';      $params[] = $taxTypeId; }
if ($direction !== '' && in_array($direction, $validDirections, true)) { $where[] = 'tax_direction = ?'; $params[] = $direction; }
if ($txStatus  !== '' && in_array($txStatus, $validStatuses, true))    { $where[] = 'status = ?';        $params[] = $txStatus; }
if ($sourceMod !== '' && in_array($sourceMod, $validModules, true))    { $where[] = 'source_module = ?'; $params[] = $sourceMod; }

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT x.*, t.name AS type_name, t.code AS type_code
    FROM tax_transactions x
    JOIN tax_types t ON t.id = x.tax_type_id
    $whereSql
    ORDER BY x.transaction_date DESC, x.id DESC
");
$stmt->execute($params);
$txns = $stmt->fetchAll();

$directionLabels = ['output' => 'Output Tax', 'input' => 'Input Tax', 'adjustment' => 'Adjustment'];

logAudit($_SESSION['user_id'] ?? 0, 'Exported tax transactions CSV', 'tax');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=tax_transactions_export.csv');

$out = fopen('php://output', 'w');
fputcsv($out, ['ID', 'Source Module', 'Document No', 'Date', 'Tax Type', 'Direction', 'Rate', 'Taxable', 'Tax', 'Tax Status', 'Status', 'Reference']);
foreach ($txns as $x) {
    fputcsv($out, [
        $x['id'],
        taxui_source_label($x['source_module']),
        $x['source_document_no'] ?? '',
        $x['transaction_date'],
        $x['type_name'] . ($x['type_code'] ? ' (' . $x['type_code'] . ')' : ''),
        $directionLabels[$x['tax_direction']] ?? $x['tax_direction'],
        (string)(float)$x['tax_rate'],
        $x['taxable_amount'],
        $x['tax_amount'],
        $x['tax_status'],
        $x['status'],
        $x['reference'] ?? '',
    ]);
}
fclose($out);
exit;