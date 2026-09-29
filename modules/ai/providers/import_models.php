<?php
/**
 * Import Discovered Models.
 *
 * AJAX endpoint to import selected models from discovery results.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) { echo json_encode(['success' => false, 'message' => 'Not authenticated.']); exit; }
if (!hasPermission('ai.models.manage')) { echo json_encode(['success' => false, 'message' => 'Permission denied.']); exit; }

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!empty($token) && !verifyCsrf($token)) { echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { echo json_encode(['success' => false, 'message' => 'Invalid request body.']); exit; }

$providerId = (int) ($input['provider_id'] ?? 0);
$selectedModels = $input['models'] ?? [];

if (!$providerId) { echo json_encode(['success' => false, 'message' => 'Invalid provider ID.']); exit; }
if (empty($selectedModels)) { echo json_encode(['success' => false, 'message' => 'No models selected for import.']); exit; }

$db = getDB();
$provider = AIProviderService::getProviderById($db, $providerId);
if (!$provider) { echo json_encode(['success' => false, 'message' => 'Provider not found.']); exit; }

// Get existing model IDs for this provider
$existingModels = AIModelService::getActiveModelsByProvider($db, $providerId);
$existingModelIds = array_column($existingModels, 'model_id');

$imported = 0;
$skipped = 0;
$errors = [];

try {
    $db->beginTransaction();

    // Check if there are any existing default models
    $hasDefault = (int) $db->query("SELECT COUNT(*) FROM ai_models WHERE is_default = 1")->fetchColumn() > 0;

    foreach ($selectedModels as $model) {
        $modelId = trim($model['model_id'] ?? '');
        if (empty($modelId)) { $skipped++; continue; }

        // Skip duplicates
        if (in_array($modelId, $existingModelIds)) {
            $skipped++;
            continue;
        }

        $isDefault = (!$hasDefault && $imported === 0) ? 1 : 0;

        $stmt = $db->prepare("
            INSERT INTO ai_models (provider_id, model_id, display_name, description, model_type,
                context_window, supports_chat, supports_tool_calling, is_active, is_default, created_by, updated_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'chat', ?, 1, 0, 1, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $providerId,
            $modelId,
            trim($model['display_name'] ?? $modelId),
            trim($model['description'] ?? '') ?: null,
            !empty($model['context_window']) ? (int) $model['context_window'] : null,
            $isDefault,
            $_SESSION['user_id'],
            $_SESSION['user_id'],
        ]);

        if ($isDefault) {
            $hasDefault = true;
        }

        $imported++;
        $existingModelIds[] = $modelId; // Prevent duplicates within same batch
    }

    $db->commit();

    // Audit log
    $msg = "Imported {$imported} models, skipped {$skipped}";
    aiAuditLog($db, 'models_imported', 'provider', $providerId, $msg, $_SESSION['user_id']);
    logAudit($_SESSION['user_id'], "Imported {$imported} models from \"{$provider['name']}\"", 'ai');

    echo json_encode(['success' => true, 'imported' => $imported, 'skipped' => $skipped]);
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()]);
}
