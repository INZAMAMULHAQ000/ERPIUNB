<?php
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('customers.edit')) { die('Access Denied'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('csrf', 'Invalid request.', 'error'); to('modules/customers/index.php');
}

$db = getDB();
$kind = $_POST['kind'] ?? '';
$cid  = (int) ($_POST['cid'] ?? 0);
$clientId = (int) ($_POST['client_id'] ?? 0);

$map = [
    'contact' => ['table' => 'client_contacts',      'soft' => false],
    'address' => ['table' => 'customer_addresses',   'soft' => true],
    'note'    => ['table' => 'client_notes',         'soft' => false],
];
if (!isset($map[$kind]) || $cid <= 0) { die('Invalid request'); }

$table = $map[$kind]['table'];
if ($map[$kind]['soft']) {
    $db->prepare("UPDATE `$table` SET deleted_at = NOW() WHERE id = ? AND client_id = ?")->execute([$cid, $clientId]);
} else {
    $db->prepare("DELETE FROM `$table` WHERE id = ? AND client_id = ?")->execute([$cid, $clientId]);
}

logAudit($_SESSION['user_id'], "Removed $kind #$cid from customer #$clientId", 'customers');
flash('child', 'Removed.', 'success');
to('modules/customers/view.php?id=' . $clientId);
