<?php
/**
 * Discover Models from Provider.
 *
 * AJAX endpoint to fetch available models from a provider.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) { echo json_encode(['success' => false, 'message' => 'Not authenticated.']); exit; }
if (!hasPermission('ai.models.manage')) { echo json_encode(['success' => false, 'message' => 'Permission denied.']); exit; }

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!empty($token) && !verifyCsrf($token)) { echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']); exit; }

$id = (int) ($_POST['id'] ?? 0);
if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid provider ID.']); exit; }

$db = getDB();
$provider = AIProviderService::getProviderById($db, $id);
if (!$provider) { echo json_encode(['success' => false, 'message' => 'Provider not found.']); exit; }
if (!$provider['is_active']) { echo json_encode(['success' => false, 'message' => 'Provider must be active to discover models.']); exit; }

// Resolve adapter
$adapter = AIProviderAdapterFactory::create($db, $provider);
if (!$adapter) {
    echo json_encode(['success' => false, 'message' => 'Unsupported provider type.']);
    exit;
}

// Discover models
$result = $adapter->listModels();

// Get existing models for this provider
$existingModels = AIModelService::getActiveModelsByProvider($db, $id);
$existingModelIds = array_column($existingModels, 'model_id');

// Mark discovered models as existing/new
$discovered = [];
foreach ($result['models'] as $m) {
    $m['is_existing'] = in_array($m['model_id'], $existingModelIds);
    $discovered[] = $m;
}

// Audit log
$action = $result['success'] ? 'model_discovery_succeeded' : 'model_discovery_failed';
$count = count($discovered);
$msg = $result['success'] ? "Discovered {$count} models" : ($result['error'] ?? 'Discovery failed');
aiAuditLog($db, $action, 'provider', $id, $msg, $_SESSION['user_id']);
logAudit($_SESSION['user_id'], $action . ' for "' . $provider['name'] . '": ' . $msg, 'ai');

echo json_encode([
    'success'    => $result['success'],
    'models'     => $discovered,
    'error'      => $result['error'],
    'total'      => count($discovered),
]);
