<?php
/**
 * AJAX endpoint: budget lines for a given active budget (used by
 * budget_transfers/create.php to populate From/To line dropdowns).
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['user_id'])) { header('Content-Type: application/json'); echo json_encode([]); exit; }

require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/helpers/security.php';
require_once dirname(__DIR__, 3) . '/helpers/rbac.php';
require_once dirname(__DIR__, 3) . '/helpers/currency.php';
require_once dirname(__DIR__) . '/helpers/budget_helpers.php';

header('Content-Type: application/json');

if (!hasPermission('budget.transfer')) { echo json_encode([]); exit; }

$db = getDB();
$budgetId = (int)($_GET['budget_id'] ?? 0);
if (!$budgetId) { echo json_encode([]); exit; }

$budget = budget_find($db, $budgetId);
if (!$budget || $budget['status'] !== 'active') { echo json_encode([]); exit; }

$version = $db->prepare("SELECT * FROM budget_versions WHERE id = ?");
$version->execute([$budget['current_version_id']]);
$version = $version->fetch();
if (!$version) { echo json_encode([]); exit; }

$lines = $db->prepare("SELECT bl.*, acct.account_code, acct.account_name, d.department_name, p.project_name
                       FROM budget_lines bl
                       JOIN chart_of_accounts acct ON acct.id = bl.chart_of_accounts_id
                       LEFT JOIN departments d ON d.id = bl.department_id
                       LEFT JOIN projects p ON p.id = bl.project_id
                       WHERE bl.budget_version_id = ?
                       ORDER BY acct.account_code, bl.month");
$lines->execute([$version['id']]);
$lines = $lines->fetchAll();

$out = array_map(function($l) {
    return [
        'id'      => (int)$l['id'],
        'display' => ($l['department_name'] ?? '—') . ' / ' . ($l['project_name'] ?? '—') . ' / ' . $l['account_code'] . ' - ' . $l['account_name'] . ' / ' . budget_month_name($l['month']),
        'dept'    => $l['department_id'],
        'proj'    => $l['project_id'],
        'acct'    => $l['chart_of_accounts_id'],
        'month'   => $l['month'],
        'amount'  => (float)$l['budget_amount'],
    ];
}, $lines);

echo json_encode($out);