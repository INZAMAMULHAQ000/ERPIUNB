<?php
/**
 * AJAX endpoint: budget versions for a given budget (used by
 * budget_revisions/create.php to populate the Version dropdown).
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
$budgetId = (int)($_GET['budget_id'] ?? 0);
if (!$budgetId) { echo json_encode([]); exit; }

$versions = $db->prepare("SELECT id, version_label, version_no FROM budget_versions WHERE budget_id = ? ORDER BY version_no DESC");
$versions->execute([$budgetId]);
$versions = $versions->fetchAll();

echo json_encode($versions);