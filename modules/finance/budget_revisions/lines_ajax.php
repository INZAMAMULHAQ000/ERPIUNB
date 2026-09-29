<?php
/**
 * AJAX endpoint: budget lines for a given budget version (used by
 * budget_revisions/create.php to populate the Line dropdown).
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['user_id'])) { header('Content-Type: application/json'); echo json_encode([]); exit; }

require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/helpers/security.php';
require_once dirname(__DIR__, 3) . '/helpers/rbac.php';
require_once dirname(__DIR__, 3) . '/helpers/currency.php';
require_once dirname(__DIR__) . '/helpers/budget_helpers.php';

header('Content-Type: application/json');

if (!hasPermission('budget.revise')) { echo json_encode([]); exit; }

$db = getDB();
$versionId = (int)($_GET['version_id'] ?? 0);
if (!$versionId) { echo json_encode([]); exit; }

$lines = $db->prepare("SELECT bl.*, acct.account_code, acct.account_name, d.department_name, p.project_name
                       FROM budget_lines bl
                       JOIN chart_of_accounts acct ON acct.id = bl.chart_of_accounts_id
                       LEFT JOIN departments d ON d.id = bl.department_id
                       LEFT JOIN projects p ON p.id = bl.project_id
                       WHERE bl.budget_version_id = ?
                       ORDER BY acct.account_code, bl.month");
$lines->execute([$versionId]);
$lines = $lines->fetchAll();

$out = array_map(function($l) {
    return [
        'id'      => (int)$l['id'],
        'display' => ($l['department_name'] ?? '—') . ' / ' . ($l['project_name'] ?? '—') . ' / ' . $l['account_code'] . ' - ' . $l['account_name'] . ' / ' . budget_month_name($l['month']),
        'amount'  => (float)$l['budget_amount'],
    ];
}, $lines);

echo json_encode($out);