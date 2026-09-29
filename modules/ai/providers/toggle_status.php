<?php
/**
 * Toggle AI Provider Status.
 *
 * AJAX endpoint to enable/disable a provider.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

// Discard buffered HTML output
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');

// Authentication check
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'errors' => ['Not authenticated.']]);
    exit;
}

// Permission check
if (!hasPermission('ai.providers.update')) {
    echo json_encode(['success' => false, 'errors' => ['Permission denied.']]);
    exit;
}

// CSRF check
$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!empty($token) && !verifyCsrf($token)) {
    echo json_encode(['success' => false, 'errors' => ['Invalid CSRF token.']]);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$activate = !empty($_POST['activate']);

if (!$id) {
    echo json_encode(['success' => false, 'errors' => ['Invalid provider ID.']]);
    exit;
}

$db = getDB();
$result = AIProviderService::toggleProviderStatus($db, $id, $activate, $_SESSION['user_id']);

echo json_encode($result);
