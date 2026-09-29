<?php
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('expenses.approve')) { die('Access Denied'); }

$db = getDB();
$id = $_GET['id'] ?? 0;
$action = $_GET['action'] ?? '';

if (!in_array($action, ['approve', 'reject'])) { die('Invalid action'); }

$expense = $db->prepare("SELECT * FROM expenses WHERE id = ?");
$expense->execute([$id]);
$expense = $expense->fetch();

if (!$expense) { die('Expense not found'); }

$status = $action === 'approve' ? 'approved' : 'rejected';

$stmt = $db->prepare("UPDATE expenses SET status = ? WHERE id = ?");
$stmt->execute([$status, $id]);

logAudit($_SESSION['user_id'], $action . '_expense', 'expenses');
flash('success', 'Expense ' . $status . ' successfully');
to('http://localhost/ERP/modules/expenses/index.php');