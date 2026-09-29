<?php
$pageTitle = 'Cancel Transfer';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.transfer')) { die('Access Denied'); }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('index.php'); }
if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid or expired form token.', 'error'); redirect('index.php'); }

$id = (int)($_POST['transfer_id'] ?? 0);
$t = $db->prepare("SELECT * FROM budget_transfers WHERE id = ?");
$t->execute([$id]);
$t = $t->fetch();
if (!$t) { flash('error', 'Transfer not found.', 'error'); redirect('index.php'); }
if ($t['status'] !== 'pending') { flash('error', 'Only pending transfers can be cancelled.', 'error'); redirect('view.php?id=' . $id); }

$db->prepare("UPDATE budget_transfers SET status = 'cancelled' WHERE id = ?")->execute([$id]);
logAudit($_SESSION['user_id'], "Cancelled budget transfer {$t['transfer_no']}", 'budget');
flash('success', 'Transfer cancelled.', 'success');
redirect('view.php?id=' . $id);