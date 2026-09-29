<?php
$pageTitle = 'Close Tax Period';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.period.close')) { die('Access Denied'); }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('index.php'); }

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('error', 'Invalid or expired form token.', 'error');
    redirect('index.php');
}

$id = (int)($_POST['period_id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM tax_periods WHERE id = ?");
$stmt->execute([$id]);
$period = $stmt->fetch();

if (!$period || $period['status'] !== 'submitted') {
    flash('error', 'Only submitted periods can be closed.', 'error');
    redirect('index.php');
}

$db->prepare("UPDATE tax_periods SET status = 'closed', closed_by = ?, closed_at = NOW() WHERE id = ?")
   ->execute([$_SESSION['user_id'], $id]);
logAudit($_SESSION['user_id'], 'Closed tax period #' . $id, 'tax');
flash('success', 'Tax period "' . $period['period_name'] . '" closed. No further tax transactions are allowed.');
redirect('index.php');

require_once '../../../../includes/footer.php'; ?>