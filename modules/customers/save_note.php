<?php
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('customers.view')) { die('Access Denied'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('csrf', 'Invalid request.', 'error'); to('modules/customers/index.php');
}

$db = getDB();
$clientId = (int) ($_POST['client_id'] ?? 0);
$text = trim($_POST['note_text'] ?? '');
if ($clientId <= 0 || $text === '') {
    flash('note', 'Note text is required.', 'error');
    to('modules/customers/view.php?id=' . $clientId);
}

$db->prepare("INSERT INTO client_notes (client_id, user_id, note_text, created_at) VALUES (?, ?, ?, NOW())")
   ->execute([$clientId, $_SESSION['user_id'], $text]);

flash('note', 'Note saved.', 'success');
to('modules/customers/view.php?id=' . $clientId . '#notes');
