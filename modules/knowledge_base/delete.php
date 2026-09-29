<?php
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('knowledgebase.delete')) { die('Access Denied'); }

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT id, title FROM knowledge_base_articles WHERE id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch();
if (!$article) { flash('error', 'Article not found'); to('http://localhost/ERP/modules/knowledge_base/index.php'); }

$stmt = $db->prepare("DELETE FROM knowledge_base_articles WHERE id = ?");
$stmt->execute([$id]);

logAudit($_SESSION['user_id'], 'delete_kb_article', 'knowledgebase');
flash('success', 'Article deleted successfully');
to('http://localhost/ERP/modules/knowledge_base/index.php');
?>
