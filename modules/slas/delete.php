<?php
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('slas.delete')) { die('Access Denied'); }

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT id, sla_name FROM slas WHERE id = ?");
$stmt->execute([$id]);
$sla = $stmt->fetch();
if (!$sla) { flash('error', 'SLA not found'); to('http://localhost/ERP/modules/slas/index.php'); }

$ticketCountStmt = $db->prepare("SELECT COUNT(*) as cnt FROM tickets WHERE sla_id = ?");
$ticketCountStmt->execute([$id]);
$ticketCount = $ticketCountStmt->fetch()['cnt'];

if ($ticketCount > 0) {
    flash('error', "Cannot delete: {$ticketCount} tickets are using this SLA");
    to('http://localhost/ERP/modules/slas/index.php');
}

$stmt = $db->prepare("DELETE FROM slas WHERE id = ?");
$stmt->execute([$id]);

logAudit($_SESSION['user_id'], 'delete_sla', 'slas');
flash('success', 'SLA policy deleted successfully');
to('http://localhost/ERP/modules/slas/index.php');
?>
