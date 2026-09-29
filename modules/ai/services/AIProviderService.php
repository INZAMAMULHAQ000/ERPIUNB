<?php
/**
 * AI Provider Service.
 *
 * Handles all database operations for AI provider management.
 * Uses prepared statements for all queries.
 */

require_once AI_MODULE_DIR . '/includes/ai_credential.php';
require_once AI_MODULE_DIR . '/includes/ai_provider_adapter_base.php';

class AIProviderService
{
    /**
     * Send a plain chat request through the configured provider adapter.
     *
     * This is a compatibility wrapper used by older AI flows that only need
     * the final assistant text. The tool-calling API continues to use the
     * provider adapter directly.
     */
    public static function chat(PDO $db, int $providerId, string $input, array $options = []): string
    {
        $provider = self::resolveChatProvider($db, $providerId);
        if (!$provider) {
            throw new RuntimeException('No active AI provider is available.');
        }

        $adapter = AIProviderAdapterFactory::create($db, $provider);
        if (!$adapter) {
            throw new RuntimeException('The selected AI provider type is not supported.');
        }

        $model = self::resolveChatModel($db, (int) $provider['id'], $options);
        if (!$model) {
            throw new RuntimeException('No active AI model is available for the selected provider.');
        }

        $messages = self::buildChatMessages($input, $options);
        $chatOptions = [];
        if (isset($options['temperature'])) {
            $chatOptions['temperature'] = (float) $options['temperature'];
        }
        if (isset($options['max_tokens'])) {
            $chatOptions['max_tokens'] = (int) $options['max_tokens'];
        }
        if (!empty($options['tools']) && is_array($options['tools'])) {
            $chatOptions['tools'] = $options['tools'];
            if (isset($options['tool_choice'])) {
                $chatOptions['tool_choice'] = $options['tool_choice'];
            }
        }

        $responseFormat = $options['response_format'] ?? null;
        if ($responseFormat === 'json' || (is_array($responseFormat) && ($responseFormat['type'] ?? '') === 'json_object')) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => 'Return valid JSON only. Do not include markdown fences, comments, or extra prose.',
            ]);
        }

        $result = $adapter->chat((string) ($model['model_id'] ?? ''), $messages, $chatOptions);
        if (empty($result['success'])) {
            throw new RuntimeException($result['error'] ?? 'AI provider request failed.');
        }

        return trim((string) ($result['content'] ?? ''));
    }

    private static function resolveChatProvider(PDO $db, int $providerId): ?array
    {
        if ($providerId > 0) {
            $provider = self::getProviderById($db, $providerId);
            if ($provider && !empty($provider['is_active'])) {
                return $provider;
            }
        }

        $default = self::getDefaultProvider($db);
        if ($default && !empty($default['is_active'])) {
            return $default;
        }

        return null;
    }

    private static function resolveChatModel(PDO $db, int $providerId, array $options = []): ?array
    {
        $modelId = $options['model_id'] ?? $options['model_db_id'] ?? null;
        if (is_numeric($modelId) && (int) $modelId > 0 && class_exists('AIModelService')) {
            $model = AIModelService::getModelById($db, (int) $modelId);
            if ($model && (int) ($model['provider_id'] ?? 0) === $providerId && !empty($model['is_active'])) {
                return $model;
            }
        }

        $modelIdentifier = trim((string) ($options['model_identifier'] ?? ''));
        if ($modelIdentifier !== '') {
            $stmt = $db->prepare("
                SELECT m.*, p.name as provider_name, p.provider_type, p.is_active as provider_active
                FROM ai_models m
                JOIN ai_providers p ON p.id = m.provider_id
                WHERE m.provider_id = ? AND m.model_id = ? AND m.is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$providerId, $modelIdentifier]);
            $model = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($model) {
                return $model;
            }
        }

        if (class_exists('AIModelService')) {
            $defaultModel = AIModelService::getDefaultModel($db);
            if ($defaultModel && (int) ($defaultModel['provider_id'] ?? 0) === $providerId) {
                return $defaultModel;
            }

            $providerModels = AIModelService::getActiveModelsByProvider($db, $providerId);
            if (!empty($providerModels)) {
                return $providerModels[0];
            }
        }

        return null;
    }

    private static function buildChatMessages(string $input, array $options = array()): array
    {
        $messages = [];
        $providedMessages = $options['messages'] ?? null;
        $systemPrompt = trim((string) ($options['system_prompt'] ?? $options['systemPrompt'] ?? ''));

        if (is_array($providedMessages) && !empty($providedMessages)) {
            foreach ($providedMessages as $message) {
                if (!is_array($message)) {
                    continue;
                }
                $role = trim((string) ($message['role'] ?? 'user'));
                $content = (string) ($message['content'] ?? '');
                if ($content === '') {
                    continue;
                }
                $messages[] = [
                    'role' => in_array($role, ['system', 'assistant', 'user', 'tool'], true) ? $role : 'user',
                    'content' => $content,
                ];
            }
            if ($systemPrompt !== '' && (empty($messages) || ($messages[0]['role'] ?? '') !== 'system')) {
                array_unshift($messages, ['role' => 'system', 'content' => $systemPrompt]);
            }
            if (empty($messages)) {
                $messages[] = ['role' => 'user', 'content' => $input];
            }
            return $messages;
        }

        if ($systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        if (!empty($options['history']) && is_array($options['history'])) {
            foreach ($options['history'] as $message) {
                if (!is_array($message)) {
                    continue;
                }
                $role = trim((string) ($message['role'] ?? 'user'));
                $content = (string) ($message['content'] ?? '');
                if ($content === '') {
                    continue;
                }
                $messages[] = [
                    'role' => in_array($role, ['system', 'assistant', 'user', 'tool'], true) ? $role : 'user',
                    'content' => $content,
                ];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $input];
        return $messages;
    }

    // ---------------------------------------------------------
    // Read Operations
    // ---------------------------------------------------------

    /**
     * Get all AI providers.
     *
     * @param PDO $db Database connection
     * @param bool $activeOnly If true, return only active providers
     * @return array List of providers
     */
    public static function getProviders(PDO $db, bool $activeOnly = false): array
    {
        $sql = "SELECT id, name, slug, provider_type, base_url,
                       is_active, is_default, last_tested_at, last_test_status,
                       created_at, updated_at, created_by, updated_by
                FROM ai_providers";

        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }

        $sql .= " ORDER BY is_default DESC, name ASC";

        $stmt = $db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get a single AI provider by ID.
     *
     * @param PDO $db Database connection
     * @param int $id Provider ID
     * @return array|null Provider data, or null if not found
     */
    public static function getProviderById(PDO $db, int $id): ?array
    {
        $stmt = $db->prepare("
            SELECT id, name, slug, provider_type, base_url,
                   api_key_encrypted, api_key_iv,
                   configuration_json,
                   is_active, is_default, last_tested_at, last_test_status,
                   created_at, updated_at, created_by, updated_by
            FROM ai_providers
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get provider by slug.
     *
     * @param PDO $db Database connection
     * @param string $slug Provider slug
     * @return array|null Provider data, or null if not found
     */
    public static function getProviderBySlug(PDO $db, string $slug): ?array
    {
        $stmt = $db->prepare("SELECT id FROM ai_providers WHERE slug = ?");
        $stmt->execute([$slug]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get the default active provider.
     *
     * @param PDO $db Database connection
     * @return array|null Default provider, or null if none set
     */
    public static function getDefaultProvider(PDO $db): ?array
    {
        $stmt = $db->query("
            SELECT id, name, slug, provider_type, base_url,
                   api_key_encrypted, api_key_iv, configuration_json,
                   is_active, is_default
            FROM ai_providers
            WHERE is_default = 1 AND is_active = 1
            LIMIT 1
        ");
        $result = $stmt->fetch();
        return $result ?: null;
    }

    // ---------------------------------------------------------
    // Create Operations
    // ---------------------------------------------------------

    /**
     * Create a new AI provider.
     *
     * @param PDO $db Database connection
     * @param array $data Provider data
     * @param int $userId User creating the provider
     * @return array{success: bool, id?: int, errors?: string[]}
     */
    public static function createProvider(PDO $db, array $data, int $userId): array
    {
        // Validate
        $errors = self::validate($db, $data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Generate slug
        $slug = self::generateSlug($db, $data['name']);

        // Encrypt API key if provided
        $apiKeyEncrypted = null;
        $apiKeyIv = null;
        if (!empty($data['api_key'])) {
            try {
                $encrypted = aiEncryptCredential($data['api_key']);
                $apiKeyEncrypted = $encrypted['encrypted'];
                $apiKeyIv = $encrypted['iv'];
            } catch (Exception $e) {
                return ['success' => false, 'errors' => ['Failed to encrypt API key: ' . $e->getMessage()]];
            }
        }

        // Prepare configuration JSON
        $configJson = null;
        if (!empty($data['configuration_json'])) {
            $configJson = is_string($data['configuration_json'])
                ? $data['configuration_json']
                : json_encode($data['configuration_json']);
            // Validate JSON
            if (json_decode($configJson) === null && json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'errors' => ['Invalid configuration JSON.']];
            }
        }

        try {
            $db->beginTransaction();

            // If this is the first provider or marked as default, handle default logic
            $isDefault = !empty($data['is_default']) ? 1 : 0;
            if ($isDefault) {
                // Clear existing default
                $db->exec("UPDATE ai_providers SET is_default = 0 WHERE is_default = 1");
            }

            $stmt = $db->prepare("
                INSERT INTO ai_providers (name, slug, provider_type, base_url,
                    api_key_encrypted, api_key_iv, configuration_json,
                    is_active, is_default, created_by, updated_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                trim($data['name']),
                $slug,
                $data['provider_type'],
                !empty($data['base_url']) ? trim($data['base_url']) : null,
                $apiKeyEncrypted,
                $apiKeyIv,
                $configJson,
                !empty($data['is_active']) ? 1 : 0,
                $isDefault,
                $userId,
                $userId,
            ]);

            $providerId = (int) $db->lastInsertId();

            // Audit log
            aiAuditLog($db, 'provider_created', 'provider', $providerId,
                'Provider "' . trim($data['name']) . '" created', $userId);
            logAudit($userId, 'Created AI provider "' . trim($data['name']) . '"', 'ai');

            $db->commit();
            return ['success' => true, 'id' => $providerId];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
        }
    }

    // ---------------------------------------------------------
    // Update Operations
    // ---------------------------------------------------------

    /**
     * Update an existing AI provider.
     *
     * @param PDO $db Database connection
     * @param int $id Provider ID
     * @param array $data Provider data to update
     * @param int $userId User updating the provider
     * @return array{success: bool, errors?: string[]}
     */
    public static function updateProvider(PDO $db, int $id, array $data, int $userId): array
    {
        $existing = self::getProviderById($db, $id);
        if (!$existing) {
            return ['success' => false, 'errors' => ['Provider not found.']];
        }

        // Validate
        $errors = self::validate($db, $data, $id);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Build update fields
        $updates = [];
        $params = [];

        if (isset($data['name'])) {
            $updates[] = "name = ?";
            $params[] = trim($data['name']);
        }

        if (isset($data['provider_type'])) {
            $updates[] = "provider_type = ?";
            $params[] = $data['provider_type'];
        }

        if (array_key_exists('base_url', $data)) {
            $updates[] = "base_url = ?";
            $params[] = !empty($data['base_url']) ? trim($data['base_url']) : null;
        }

        // API key replacement (only if a new value is provided)
        if (!empty($data['api_key'])) {
            try {
                $encrypted = aiEncryptCredential($data['api_key']);
                $updates[] = "api_key_encrypted = ?";
                $params[] = $encrypted['encrypted'];
                $updates[] = "api_key_iv = ?";
                $params[] = $encrypted['iv'];
            } catch (Exception $e) {
                return ['success' => false, 'errors' => ['Failed to encrypt API key: ' . $e->getMessage()]];
            }
        }

        if (isset($data['configuration_json'])) {
            $updates[] = "configuration_json = ?";
            $configJson = !empty($data['configuration_json'])
                ? (is_string($data['configuration_json']) ? $data['configuration_json'] : json_encode($data['configuration_json']))
                : null;
            $params[] = $configJson;
        }

        if (isset($data['is_active'])) {
            $updates[] = "is_active = ?";
            $params[] = !empty($data['is_active']) ? 1 : 0;

            // If disabling, check if it's the default
            if (empty($data['is_active']) && $existing['is_default']) {
                $updates[] = "is_default = 0";
            }
        }

        if (isset($data['is_default']) && !empty($data['is_default'])) {
            // Clear existing default first
            $db->exec("UPDATE ai_providers SET is_default = 0 WHERE is_default = 1");
            $updates[] = "is_default = 1";
            $updates[] = "is_active = 1"; // Default must be active
        }

        $updates[] = "updated_by = ?";
        $params[] = $userId;
        $updates[] = "updated_at = NOW()";

        $params[] = $id;

        try {
            $db->beginTransaction();

            $sql = "UPDATE ai_providers SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            // Audit log
            aiAuditLog($db, 'provider_updated', 'provider', $id,
                'Provider updated', $userId);
            logAudit($userId, 'Updated AI provider #' . $id, 'ai');

            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
        }
    }

    // ---------------------------------------------------------
    // Status Operations
    // ---------------------------------------------------------

    /**
     * Toggle provider active/inactive status.
     *
     * @param PDO $db Database connection
     * @param int $id Provider ID
     * @param bool $activate True to activate, false to deactivate
     * @param int $userId User performing the action
     * @return array{success: bool, errors?: string[]}
     */
    public static function toggleProviderStatus(PDO $db, int $id, bool $activate, int $userId): array
    {
        $existing = self::getProviderById($db, $id);
        if (!$existing) {
            return ['success' => false, 'errors' => ['Provider not found.']];
        }

        // Cannot deactivate the default provider
        if (!$activate && $existing['is_default']) {
            return ['success' => false, 'errors' => ['Cannot disable the default provider. Please set another provider as default first.']];
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("UPDATE ai_providers SET is_active = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$activate ? 1 : 0, $userId, $id]);

            $action = $activate ? 'provider_activated' : 'provider_deactivated';
            $logMsg = $activate ? 'activated' : 'deactivated';

            aiAuditLog($db, $action, 'provider', $id,
                'Provider "' . $existing['name'] . '" ' . $logMsg, $userId);
            logAudit($userId, ucfirst($logMsg) . ' AI provider "' . $existing['name'] . '"', 'ai');

            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
        }
    }

    /**
     * Set a provider as the default.
     *
     * @param PDO $db Database connection
     * @param int $id Provider ID to set as default
     * @param int $userId User performing the action
     * @return array{success: bool, errors?: string[]}
     */
    public static function setDefaultProvider(PDO $db, int $id, int $userId): array
    {
        $existing = self::getProviderById($db, $id);
        if (!$existing) {
            return ['success' => false, 'errors' => ['Provider not found.']];
        }

        if (!$existing['is_active']) {
            return ['success' => false, 'errors' => ['Cannot set an inactive provider as default.']];
        }

        try {
            $db->beginTransaction();

            // Clear all defaults
            $db->exec("UPDATE ai_providers SET is_default = 0 WHERE is_default = 1");

            // Set new default
            $stmt = $db->prepare("UPDATE ai_providers SET is_default = 1, updated_by = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$userId, $id]);

            aiAuditLog($db, 'default_provider_changed', 'provider', $id,
                'Default provider changed to "' . $existing['name'] . '"', $userId);
            logAudit($userId, 'Changed default AI provider to "' . $existing['name'] . '"', 'ai');

            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
        }
    }

    // ---------------------------------------------------------
    // Delete Operations
    // ---------------------------------------------------------

    /**
     * Delete an AI provider.
     *
     * @param PDO $db Database connection
     * @param int $id Provider ID
     * @param int $userId User deleting the provider
     * @return array{success: bool, errors?: string[]}
     */
    public static function deleteProvider(PDO $db, int $id, int $userId): array
    {
        $existing = self::getProviderById($db, $id);
        if (!$existing) {
            return ['success' => false, 'errors' => ['Provider not found.']];
        }

        // Check for references (future: models, agents, etc.)
        // For now, allow deletion since no references exist yet

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("DELETE FROM ai_providers WHERE id = ?");
            $stmt->execute([$id]);

            aiAuditLog($db, 'provider_deleted', 'provider', $id,
                'Provider "' . $existing['name'] . '" deleted', $userId);
            logAudit($userId, 'Deleted AI provider "' . $existing['name'] . '"', 'ai');

            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
        }
    }

    // ---------------------------------------------------------
    // Credential Operations
    // ---------------------------------------------------------

    /**
     * Get masked API key status for display.
     *
     * @param PDO $db Database connection
     * @param int $id Provider ID
     * @return array{configured: bool, masked: string}
     */
    public static function getCredentialStatus(PDO $db, int $id): array
    {
        $stmt = $db->prepare("SELECT api_key_encrypted, api_key_iv FROM ai_providers WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row || empty($row['api_key_encrypted'])) {
            return ['configured' => false, 'masked' => 'Not configured'];
        }

        // Decrypt to get last 4 chars for masking
        $decrypted = aiDecryptCredential($row['api_key_encrypted'], $row['api_key_iv'] ?? '');
        if ($decrypted !== null) {
            return ['configured' => true, 'masked' => aiMaskApiKey($decrypted)];
        }

        return ['configured' => true, 'masked' => '••••••••'];
    }

    // ---------------------------------------------------------
    // Validation
    // ---------------------------------------------------------

    /**
     * Validate provider data.
     *
     * @param PDO $db Database connection
     * @param array $data Provider data to validate
     * @param int|null $excludeId Provider ID to exclude from uniqueness checks
     * @return string[] List of validation errors (empty if valid)
     */
    private static function validate(PDO $db, array $data, ?int $excludeId = null): array
    {
        $errors = [];

        // Name required
        if (empty($data['name']) || trim($data['name']) === '') {
            $errors[] = 'Provider name is required.';
        }

        // Name length
        if (!empty($data['name']) && strlen(trim($data['name'])) > 150) {
            $errors[] = 'Provider name must be 150 characters or less.';
        }

        // Provider type required
        if (empty($data['provider_type'])) {
            $errors[] = 'Provider type is required.';
        } elseif (!aiIsValidProviderType($data['provider_type'])) {
            $errors[] = 'Invalid provider type.';
        }

        // Base URL validation for types that require it
        if (!empty($data['provider_type'])) {
            $typeMeta = aiGetProviderType($data['provider_type']);
            if ($typeMeta && $typeMeta['base_url_required'] && empty($data['base_url'])) {
                $errors[] = 'Base URL is required for ' . $typeMeta['name'] . ' provider type.';
            }
        }

        // URL format validation
        if (!empty($data['base_url']) && !filter_var(trim($data['base_url']), FILTER_VALIDATE_URL)) {
            $errors[] = 'Invalid base URL format.';
        }

        // Name uniqueness
        if (!empty($data['name'])) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM ai_providers WHERE name = ? AND id != ?");
            $stmt->execute([trim($data['name']), $excludeId ?? 0]);
            if ((int) $stmt->fetchColumn() > 0) {
                $errors[] = 'A provider with this name already exists.';
            }
        }

        return $errors;
    }

    /**
     * Generate a unique slug from a name.
     *
     * @param PDO $db Database connection
     * @param string $name Provider name
     * @return string Unique slug
     */
    private static function generateSlug(PDO $db, string $name): string
    {
        $base = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($name)));
        $base = trim($base, '-');
        $slug = $base;
        $counter = 1;

        while (true) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM ai_providers WHERE slug = ?");
            $stmt->execute([$slug]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $slug;
            }
            $slug = $base . '-' . $counter;
            $counter++;
        }
    }
}
