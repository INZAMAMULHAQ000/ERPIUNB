<?php
$pageTitle = 'Reject Tax Adjustment';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.adjust')) { die('Access Denied'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('index.php'); }

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('error', 'Invalid or expired form token.', 'error');
    redirect('view.php?id=' . (int)($_POST['id'] ?? 0));
}

$db = getDB();
$id = (int)($_POST['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM tax_adjustments WHERE id = ?");
$stmt->execute([$id]);
$adj = $stmt->fetch();
if (!$adj) { flash('error', 'Tax adjustment not found.', 'error'); redirect('index.php'); }
if ($adj['status'] !== 'draft') {
    flash('error', 'Only draft adjustments can be rejected.', 'error');
    redirect('view.php?id=' . $id);
}

$approvalNotes = trim($_POST['approval_notes'] ?? '');

$upd = $db->prepare("UPDATE tax_adjustments SET status = 'rejected', approval_notes = ? WHERE id = ? AND status = 'draft'");
$upd->execute([$approvalNotes !== '' ? $approvalNotes : null, $id]);
if ($upd->rowCount() === 0) {
    flash('error', 'Adjustment was already processed.', 'error');
} else {
    logAudit($_SESSION['user_id'], 'Rejected tax adjustment #' . $id, 'tax');
    flash('success', 'Adjustment rejected.');
}
redirect('view.php?id=' . $id);