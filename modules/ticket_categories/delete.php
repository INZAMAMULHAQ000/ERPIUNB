<?php
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('ticket_categories.delete')) { die('Access Denied'); }

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT id, category_name FROM ticket_categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();
if (!$category) { flash('error', 'Category not found'); to('http://localhost/ERP/modules/ticket_categories/index.php'); }

$ticketCountStmt = $db->prepare("SELECT COUNT(*) as cnt FROM tickets WHERE category_id = ?");
$ticketCountStmt->execute([$id]);
$ticketCount = $ticketCountStmt->fetch()['cnt'];

if ($ticketCount > 0) {
    flash('error', "Cannot delete: {$ticketCount} tickets are using this category");
    to('http://localhost/ERP/modules/ticket_categories/index.php');
}

$stmt = $db->prepare("DELETE FROM ticket_categories WHERE id = ?");
$stmt->execute([$id]);

logAudit($_SESSION['user_id'], 'delete_ticket_category', 'ticket_categories');
flash('success', 'Ticket category deleted successfully');
to('http://localhost/ERP/modules/ticket_categories/index.php');
?>
