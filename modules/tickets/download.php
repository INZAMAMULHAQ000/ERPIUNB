<?php
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('tickets.view')) { die('Access Denied'); }

$db = getDB();
$fileId = (int) ($_GET['file'] ?? 0);

$stmt = $db->prepare("SELECT * FROM ticket_attachments WHERE id = ?");
$stmt->execute([$fileId]);
$file = $stmt->fetch();
if (!$file) { flash('error', 'File not found'); back(); }

$filePath = dirname(dirname(__DIR__)) . '/' . $file['file_path'];
if (!file_exists($filePath)) { flash('error', 'File not found on server'); back(); }

$stmt = $db->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?");
$stmt->execute([$file['ticket_id']]);

header('Content-Type: ' . $file['mime_type']);
header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
header('Content-Length: ' . $file['file_size']);
header('Cache-Control: private');
readfile($filePath);
exit;
?>
