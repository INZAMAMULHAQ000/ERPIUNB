<?php
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('customers.edit')) { die('Access Denied'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('csrf', 'Invalid request.', 'error'); to('modules/customers/index.php');
}

$db = getDB();
$clientId = (int) ($_POST['client_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
if ($clientId <= 0 || $name === '') {
    flash('contact', 'A contact needs at least a name.', 'error');
    to('modules/customers/view.php?id=' . $clientId);
}

if (!empty($_POST['is_primary'])) {
    $db->prepare("UPDATE client_contacts SET is_primary = 0 WHERE client_id = ?")->execute([$clientId]);
}
$db->prepare("INSERT INTO client_contacts (client_id, name, designation, email, phone, is_primary, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())")
   ->execute([
       $clientId, $name, trim($_POST['designation'] ?? '') ?: null,
       trim($_POST['email'] ?? '') ?: null, trim($_POST['phone'] ?? '') ?: null,
       !empty($_POST['is_primary']) ? 1 : 0,
   ]);

logAudit($_SESSION['user_id'], 'Added contact to customer #' . $clientId, 'customers');
flash('contact', 'Contact added.', 'success');
to('modules/customers/view.php?id=' . $clientId . '#contacts');
