<?php
/**
 * AI Model Service.
 *
 * Handles all database operations for AI model management.
 */

require_once AI_MODULE_DIR . '/includes/ai_model_types.php';

class AIModelService
{
    // ---------------------------------------------------------
    // Read Operations
    // ---------------------------------------------------------

    /**
     * Get all AI models with provider info.
     */
    public static function getModels(PDO $db, ?int $providerId = null, bool $activeOnly = false): array
    {
        $sql = "SELECT m.*, p.name as provider_name, p.provider_type, p.is_active as provider_active
                FROM ai_models m
                JOIN ai_providers p ON p.id = m.provider_id";

        $conditions = [];
        $params = [];

        if ($providerId !== null) {
            $conditions[] = "m.provider_id = ?";
            $params[] = $providerId;
        }
        if ($activeOnly) {
            $conditions[] = "m.is_active = 1";
        }

        if ($conditions) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY m.is_default DESC, m.display_name ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get a single model by ID.
     */
    public static function getModelById(PDO $db, int $id): ?array
    {
        $stmt = $db->prepare("
            SELECT m.*, p.name as provider_name, p.provider_type, p.is_active as provider_active
            FROM ai_models m
            JOIN ai_providers p ON p.id = m.provider_id
            WHERE m.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get active models for a specific provider.
     */
    public static function getActiveModelsByProvider(PDO $db, int $providerId): array
    {
        $stmt = $db->prepare("
            SELECT * FROM ai_models
            WHERE provider_id = ? AND is_active = 1
            ORDER BY display_name ASC
        ");
        $stmt->execute([$providerId]);
        return $stmt->fetchAll();
    }

    /**
     * Get the default active model.
     */
    public static function getDefaultModel(PDO $db): ?array
    {
        $stmt = $db->query("
            SELECT m.*, p.name as provider_name
            FROM ai_models m
            JOIN ai_providers p ON p.id = m.provider_id
            WHERE m.is_default = 1 AND m.is_active = 1 AND p.is_active = 1
            LIMIT 1
        ");
        $result = $stmt->fetch();
        return $result ?: null;
    }

    // ---------------------------------------------------------
    // Create
    // ---------------------------------------------------------

    /**
     * Create a new AI model.
     */
    public static function createModel(PDO $db, array $data, int $userId): array
    {
        $errors = self::validate($db, $data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $db->beginTransaction();

            // Handle default logic
            $isDefault = !empty($data['is_default']) ? 1 : 0;
            if ($isDefault) {
                $db->exec("UPDATE ai_models SET is_default = 0 WHERE is_default = 1");
            }

            $stmt = $db->prepare("
                INSERT INTO ai_models (provider_id, model_id, display_name, description, model_type,
                    context_window, max_output_tokens, supports_chat, supports_tool_calling,
                    supports_vision, supports_embeddings, supports_structured_output,
                    embedding_dimensions,
                    is_active, is_default, configuration_json, created_by, updated_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                (int) $data['provider_id'],
                trim($data['model_id']),
                trim($data['display_name']),
                !empty($data['description']) ? trim($data['description']) : null,
                $data['model_type'] ?? 'chat',
                !empty($data['context_window']) ? (int) $data['context_window'] : null,
                !empty($data['max_output_tokens']) ? (int) $data['max_output_tokens'] : null,
                !empty($data['supports_chat']) ? 1 : 0,
                !empty($data['supports_tool_calling']) ? 1 : 0,
                !empty($data['supports_vision']) ? 1 : 0,
                !empty($data['supports_embeddings']) ? 1 : 0,
                !empty($data['supports_structured_output']) ? 1 : 0,
                !empty($data['embedding_dimensions']) ? (int) $data['embedding_dimensions'] : null,
                !empty($data['is_active']) ? 1 : 0,
                $isDefault,
                !empty($data['configuration_json']) ? trim($data['configuration_json']) : null,
                $userId,
                $userId,
            ]);

            $modelId = (int) $db->lastInsertId();

            aiAuditLog($db, 'model_created', 'model', $modelId,
                'Model "' . trim($data['display_name']) . '" created', $userId);
            logAudit($userId, 'Created AI model "' . trim($data['display_name']) . '"', 'ai');

            $db->commit();
            return ['success' => true, 'id' => $modelId];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
        }
    }

    // ---------------------------------------------------------
    // Update
    // ---------------------------------------------------------

    /**
     * Update an existing AI model.
     */
    public static function updateModel(PDO $db, int $id, array $data, int $userId): array
    {
        $existing = self::getModelById($db, $id);
        if (!$existing) {
            return ['success' => false, 'errors' => ['Model not found.']];
        }

        $errors = self::validate($db, $data, $id);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $updates = [];
        $params = [];

        $fields = ['provider_id', 'model_id', 'display_name', 'description', 'model_type',
                    'context_window', 'max_output_tokens', 'supports_chat', 'supports_tool_calling',
                    'supports_vision', 'supports_embeddings', 'supports_structured_output',
                    'embedding_dimensions', 'is_active', 'configuration_json'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "$field = ?";
                if (in_array($field, ['context_window', 'max_output_tokens', 'embedding_dimensions'])) {
                    $params[] = !empty($data[$field]) ? (int) $data[$field] : null;
                } elseif (in_array($field, ['supports_chat', 'supports_tool_calling', 'supports_vision',
                                              'supports_embeddings', 'supports_structured_output', 'is_active'])) {
                    $params[] = !empty($data[$field]) ? 1 : 0;
                } elseif ($field === 'provider_id') {
                    $params[] = (int) $data[$field];
                } else {
                    $params[] = !empty($data[$field]) ? trim($data[$field]) : null;
                }
            }
        }

        // Handle default logic
        if (isset($data['is_default']) && !empty($data['is_default'])) {
            $db->exec("UPDATE ai_models SET is_default = 0 WHERE is_default = 1");
            $updates[] = "is_default = 1";
            $updates[] = "is_active = 1"; // Default must be active
        } elseif (isset($data['is_default']) && empty($data['is_default'])) {
            $updates[] = "is_default = 0";
        }

        // If disabling, clear default
        if (isset($data['is_active']) && empty($data['is_active']) && $existing['is_default']) {
            $updates[] = "is_default = 0";
        }

        $updates[] = "updated_by = ?";
        $params[] = $userId;
        $updates[] = "updated_at = NOW()";
        $params[] = $id;

        try {
            $db->beginTransaction();

            $sql = "UPDATE ai_models SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            aiAuditLog($db, 'model_updated', 'model', $id, 'Model updated', $userId);
            logAudit($userId, 'Updated AI model #' . $id, 'ai');

            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
        }
    }

    // ---------------------------------------------------------
    // Status / Default / Delete
    // ---------------------------------------------------------

    public static function toggleModelStatus(PDO $db, int $id, bool $activate, int $userId): array
    {
        $existing = self::getModelById($db, $id);
        if (!$existing) return ['success' => false, 'errors' => ['Model not found.']];

        // Cannot activate if provider is inactive
        if ($activate && !$existing['provider_active']) {
            return ['success' => false, 'errors' => ['Cannot activate a model belonging to an inactive provider.']];
        }

        // Cannot deactivate default
        if (!$activate && $existing['is_default']) {
            return ['success' => false, 'errors' => ['Cannot disable the default model. Set another as default first.']];
        }

        try {
            $db->beginTransaction();
            $stmt = $db->prepare("UPDATE ai_models SET is_active = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$activate ? 1 : 0, $userId, $id]);

            $action = $activate ? 'model_activated' : 'model_deactivated';
            $logMsg = $activate ? 'activated' : 'deactivated';
            aiAuditLog($db, $action, 'model', $id, 'Model "' . $existing['display_name'] . '" ' . $logMsg, $userId);
            logAudit($userId, ucfirst($logMsg) . ' AI model "' . $existing['display_name'] . '"', 'ai');

            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
        }
    }

    public static function setDefaultModel(PDO $db, int $id, int $userId): array
    {
        $existing = self::getModelById($db, $id);
        if (!$existing) return ['success' => false, 'errors' => ['Model not found.']];

        if (!$existing['is_active']) {
            return ['success' => false, 'errors' => ['Cannot set an inactive model as default.']];
        }
        if (!$existing['provider_active']) {
            return ['success' => false, 'errors' => ['Cannot set a model from an inactive provider as default.']];
        }

        try {
            $db->beginTransaction();
            $db->exec("UPDATE ai_models SET is_default = 0 WHERE is_default = 1");
            $stmt = $db->prepare("UPDATE ai_models SET is_default = 1, updated_by = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$userId, $id]);

            aiAuditLog($db, 'default_model_changed', 'model', $id,
                'Default model changed to "' . $existing['display_name'] . '"', $userId);
            logAudit($userId, 'Changed default AI model to "' . $existing['display_name'] . '"', 'ai');

            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
        }
    }

    public static function deleteModel(PDO $db, int $id, int $userId): array
    {
        $existing = self::getModelById($db, $id);
        if (!$existing) return ['success' => false, 'errors' => ['Model not found.']];

        try {
            $db->beginTransaction();
            $stmt = $db->prepare("DELETE FROM ai_models WHERE id = ?");
            $stmt->execute([$id]);

            aiAuditLog($db, 'model_deleted', 'model', $id,
                'Model "' . $existing['display_name'] . '" deleted', $userId);
            logAudit($userId, 'Deleted AI model "' . $existing['display_name'] . '"', 'ai');

            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
        }
    }

    // ---------------------------------------------------------
    // Validation
    // ---------------------------------------------------------

    private static function validate(PDO $db, array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if (empty($data['provider_id'])) {
            $errors[] = 'Provider is required.';
        } else {
            // Check provider exists
            $stmt = $db->prepare("SELECT id, is_active FROM ai_providers WHERE id = ?");
            $stmt->execute([(int) $data['provider_id']]);
            $provider = $stmt->fetch();
            if (!$provider) {
                $errors[] = 'Selected provider does not exist.';
            }
        }

        if (empty($data['display_name']) || trim($data['display_name']) === '') {
            $errors[] = 'Display name is required.';
        }

        if (empty($data['model_id']) || trim($data['model_id']) === '') {
            $errors[] = 'Model ID is required.';
        }

        // Duplicate provider + model_id check
        if (!empty($data['provider_id']) && !empty($data['model_id'])) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM ai_models WHERE provider_id = ? AND model_id = ? AND id != ?");
            $stmt->execute([(int) $data['provider_id'], trim($data['model_id']), $excludeId ?? 0]);
            if ((int) $stmt->fetchColumn() > 0) {
                $errors[] = 'A model with this ID already exists for the selected provider.';
            }
        }

        // Positive integers for token fields
        foreach (['context_window', 'max_output_tokens'] as $field) {
            if (!empty($data[$field]) && ((int) $data[$field] <= 0 || !ctype_digit((string) $data[$field]))) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be a positive integer.';
            }
        }

        // Default model rules
        if (!empty($data['is_default']) && !empty($data['is_active'])) {
            // Check provider is active
            if (!empty($data['provider_id']) && $provider && !$provider['is_active']) {
                $errors[] = 'Cannot set a model from an inactive provider as default.';
            }
        }

        return $errors;
    }
}
