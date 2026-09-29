<?php
$pageTitle = 'Generate Monitoring Snapshot';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.report')) { die('Access Denied'); }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../budget_monitoring/index.php'); }
if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid or expired form token.', 'error'); redirect('../budget_monitoring/index.php'); }

$budget_id  = (int)($_POST['budget_id'] ?? 0);
$version_id = (int)($_POST['version_id'] ?? 0);

$budget = $budget_id ? budget_find($db, $budget_id) : null;
$version = $db->prepare("SELECT * FROM budget_versions WHERE id = ?");
$version->execute([$version_id ?: ($budget ? $budget['current_version_id'] : 0)]);
$version = $version->fetch();

if (!$budget || !$version) { flash('error', 'Budget or version not found.', 'error'); redirect('../budget_monitoring/index.php'); }

$fy = [
    'id'         => $budget['financial_year_id'],
    'start_date' => $budget['fy_start'],
    'end_date'   => $budget['fy_end'],
];

// Generate snapshots for each month (1-12).
// Replace this version's rows for today's date so re-running the snapshot
// is idempotent (the table has no unique key for ON DUPLICATE KEY).
try {
    $db->beginTransaction();
    $db->prepare("DELETE FROM budget_monitoring_snapshots WHERE budget_version_id = ? AND snapshot_date = CURDATE()")
        ->execute([$version['id']]);
    for ($m = 1; $m <= 12; $m++) {
        $lines = budget_lines_for_version($db, $version['id'], $fy, $m);
        if (empty($lines)) continue;
        $totals = budget_lines_totals($lines);

        $stmt = $db->prepare("INSERT INTO budget_monitoring_snapshots
            (budget_id, budget_version_id, financial_year_id, snapshot_date, month, allocated, spent, committed, available, utilization_pct, generated_by, generated_at)
            VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $budget['id'], $version['id'], $budget['financial_year_id'], $m,
            $totals['allocated'], $totals['spent'], $totals['committed'], $totals['available'], $totals['pct'],
            $_SESSION['user_id']
        ]);
    }
    $db->commit();
    logAudit($_SESSION['user_id'], "Generated monitoring snapshot for budget: {$budget['budget_code']} version {$version['version_label']}", 'budget');
    flash('success', 'Monitoring snapshot generated for all 12 months.', 'success');
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('Snapshot error: ' . $e->getMessage());
    flash('error', 'Could not generate snapshot: ' . $e->getMessage(), 'error');
}

redirect('../budget_monitoring/index.php');