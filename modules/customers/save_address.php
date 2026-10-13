<?php
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('customers.edit')) { die('Access Denied'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('csrf', 'Invalid request.', 'error'); to('modules/customers/index.php');
}

$db = getDB();
$clientId = (int) ($_POST['client_id'] ?? 0);
$label = trim($_POST['label'] ?? '');
$type = $_POST['address_type'] ?? 'shipping';
if ($clientId <= 0 || $label === '' || !in_array($type, ['billing', 'shipping', 'remittance', 'other'], true)) {
    flash('addr', 'A location needs a label and a valid type.', 'error');
    to('modules/customers/view.php?id=' . $clientId);
}

$isDefault = !empty($_POST['is_default']) ? 1 : 0;
if ($isDefault) {
    $db->prepare("UPDATE customer_addresses SET is_default = 0 WHERE client_id = ? AND address_type = ?")
       ->execute([$clientId, $type]);
}

$db->prepare("
    INSERT INTO customer_addresses
        (client_id, address_type, label, contact_name, phone, email, address_line1, address_line2,
         city, state, postal_code, country, tax_number, is_default, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
")->execute([
    $clientId, $type, $label,
    trim($_POST['contact_name'] ?? '') ?: null, trim($_POST['phone'] ?? '') ?: null,
    trim($_POST['email'] ?? '') ?: null, trim($_POST['address_line1'] ?? '') ?: null,
    trim($_POST['address_line2'] ?? '') ?: null, trim($_POST['city'] ?? '') ?: null,
    trim($_POST['state'] ?? '') ?: null, trim($_POST['postal_code'] ?? '') ?: null,
    trim($_POST['country'] ?? '') ?: null, trim($_POST['tax_number'] ?? '') ?: null,
    $isDefault,
]);

logAudit($_SESSION['user_id'], 'Added address to customer #' . $clientId, 'customers');
flash('addr', 'Location added.', 'success');
to('modules/customers/view.php?id=' . $clientId . '#addresses');
