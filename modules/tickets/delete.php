<?php
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('tickets.delete')) { die('Access Denied'); }

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT id, ticket_number FROM tickets WHERE id = ?");
$stmt->execute([$id]);
$ticket = $stmt->fetch();
if (!$ticket) { flash('error', 'Ticket not found'); to('http://localhost/ERP/modules/tickets/index.php'); }

$stmt = $db->prepare("DELETE FROM tickets WHERE id = ?");
$stmt->execute([$id]);

logAudit($_SESSION['user_id'], 'delete_ticket', 'tickets');
flash('success', 'Ticket deleted successfully');
to('http://localhost/ERP/modules/tickets/index.php');
?>
