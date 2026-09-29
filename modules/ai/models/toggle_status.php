<?php
/**
 * Toggle AI Model Status.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) { echo json_encode(['success' => false, 'errors' => ['Not authenticated.']]); exit; }
if (!hasPermission('ai.models.manage')) { echo json_encode(['success' => false, 'errors' => ['Permission denied.']]); exit; }

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!empty($token) && !verifyCsrf($token)) { echo json_encode(['success' => false, 'errors' => ['Invalid CSRF token.']]); exit; }

$id = (int) ($_POST['id'] ?? 0);
$activate = !empty($_POST['activate']);

if (!$id) { echo json_encode(['success' => false, 'errors' => ['Invalid model ID.']]); exit; }

$db = getDB();
$result = AIModelService::toggleModelStatus($db, $id, $activate, $_SESSION['user_id']);
echo json_encode($result);
