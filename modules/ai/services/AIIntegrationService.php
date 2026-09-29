<?php
/**
 * AI F22 - Integration & External Connectors
 */

require_once AI_MODULE_DIR . '/includes/ai_credential.php';
require_once AI_MODULE_DIR . '/includes/ai_http_client.php';

interface AIConnectorAuthStrategyInterface
{
    public function apply(array $credential, array $headers): array;
}

class AIConnectorApiKeyAuthStrategy implements AIConnectorAuthStrategyInterface
{
    public function apply(array $credential, array $headers): array
    {
        $secret = $credential['secret'] ?? '';
        $meta = is_array($credential['metadata'] ?? null) ? $credential['metadata'] : [];
        $header = trim((string) ($meta['header_name'] ?? 'X-API-Key'));
        if ($secret !== '') {
            $headers[$header] = $secret;
        }
        return $headers;
    }
}

class AIConnectorBearerTokenAuthStrategy implements AIConnectorAuthStrategyInterface
{
    public function apply(array $credential, array $headers): array
    {
        $secret = $credential['secret'] ?? '';
        if ($secret !== '') {
            $headers['Authorization'] = 'Bearer ' . $secret;
        }
        return $headers;
    }
}

class AIConnectorBasicAuthStrategy implements AIConnectorAuthStrategyInterface
{
    public function apply(array $credential, array $headers): array
    {
        $username = $credential['secret'] ?? '';
        $password = $credential['secondary_secret'] ?? '';
        if ($username !== '' || $password !== '') {
            $headers['Authorization'] = 'Basic ' . base64_encode($username . ':' . $password);
        }
        return $headers;
    }
}

class AIConnectorCustomHeaderAuthStrategy implements AIConnectorAuthStrategyInterface
{
    public function apply(array $credential, array $headers): array
    {
        $secret = $credential['secret'] ?? '';
        $meta = is_array($credential['metadata'] ?? null) ? $credential['metadata'] : [];
        $header = trim((string) ($meta['header_name'] ?? 'X-Connector-Auth'));
        $prefix = trim((string) ($meta['header_prefix'] ?? ''));
        if ($secret !== '') {
            $headers[$header] = $prefix !== '' ? $prefix . ' ' . $secret : $secret;
        }
        return $headers;
    }
}

class AIConnectorWebhookSecretAuthStrategy implements AIConnectorAuthStrategyInterface
{
    public function apply(array $credential, array $headers): array
    {
        $secret = $credential['secret'] ?? '';
        if ($secret !== '') {
            $headers['X-Webhook-Secret'] = $secret;
        }
        return $headers;
    }
}

class AIConnectorOAuth2AuthStrategy implements AIConnectorAuthStrategyInterface
{
    public function apply(array $credential, array $headers): array
    {
        $token = $credential['secret'] ?? '';
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        return $headers;
    }
}

class AIIntegrationService
{
    private const ACTIVITY_SUMMARY_LIMIT = 280;
    private const MAX_SYNC_ROWS = 100;
    private const CIRCUIT_BREAKER_THRESHOLD = 3;
    private const PRIVATE_HOST_PATTERNS = [
        '/^localhost$/i',
        '/^127\./',
        '/^10\./',
        '/^192\.168\./',
        '/^172\.(1[6-9]|2\d|3[0-1])\./',
        '/^::1$/i',
    ];

    public static function ensureDefaults(PDO $db): void
    {
        self::seedPolicies($db);
        self::seedConnectors($db);
        self::seedCapabilities($db);
        self::seedRestEndpoints($db);
        self::seedSkillTemplates($db);
    }

    public static function getConnectorCategories(): array
    {
        return ['email', 'calendar', 'storage', 'messaging', 'crm', 'accounting', 'webhook', 'rest_api', 'internal_service'];
    }

    public static function getAuthStrategies(): array
    {
        return [
            'api_key' => new AIConnectorApiKeyAuthStrategy(),
            'oauth2' => new AIConnectorOAuth2AuthStrategy(),
            'bearer_token' => new AIConnectorBearerTokenAuthStrategy(),
            'basic_auth' => new AIConnectorBasicAuthStrategy(),
            'custom_header' => new AIConnectorCustomHeaderAuthStrategy(),
            'webhook_secret' => new AIConnectorWebhookSecretAuthStrategy(),
        ];
    }

    public static function getConnectorDefinitions(): array
    {
        return [
            [
                'connector_key' => 'generic_rest',
                'name' => 'Generic REST Connector',
                'description' => 'Restricted connector for approved REST endpoints only.',
                'category' => 'rest_api',
                'version' => '1.0.0',
                'auth_types' => ['api_key', 'bearer_token', 'basic_auth', 'custom_header', 'oauth2'],
                'supported_capabilities' => ['external.rest.read_inventory_status', 'external.rest.import_supplier_preview'],
                'configuration_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'approved_base_url' => ['type' => 'string'],
                        'timeout_seconds' => ['type' => 'integer'],
                        'allow_internal_destination' => ['type' => 'boolean'],
                    ],
                    'required' => ['approved_base_url'],
                ],
                'status' => 'available',
                'safety_classification' => 'sensitive',
                'supports_webhooks' => 0,
                'supports_sync' => 1,
                'metadata' => ['registered_server_side' => true],
            ],
            [
                'connector_key' => 'outbound_webhook',
                'name' => 'Outbound Webhook Connector',
                'description' => 'Controlled outbound webhook delivery with allowlist, signing, retries, and SSRF protection.',
                'category' => 'webhook',
                'version' => '1.0.0',
                'auth_types' => ['webhook_secret', 'custom_header'],
                'supported_capabilities' => ['external.webhook.workflow_completed'],
                'configuration_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'allowed_host' => ['type' => 'string'],
                        'signature_header' => ['type' => 'string'],
                        'allow_internal_destination' => ['type' => 'boolean'],
                    ],
                    'required' => ['allowed_host'],
                ],
                'status' => 'available',
                'safety_classification' => 'restricted',
                'supports_webhooks' => 1,
                'supports_sync' => 0,
                'metadata' => ['registered_server_side' => true],
            ],
            [
                'connector_key' => 'company_calendar',
                'name' => 'Company Calendar Connector',
                'description' => 'Read-only calendar connector scaffold for approved provider integrations.',
                'category' => 'calendar',
                'version' => '1.0.0',
                'auth_types' => ['oauth2', 'bearer_token'],
                'supported_capabilities' => ['external.calendar.read_events'],
                'configuration_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'provider' => ['type' => 'string'],
                        'calendar_id' => ['type' => 'string'],
                    ],
                    'required' => ['provider'],
                ],
                'status' => 'maintenance',
                'safety_classification' => 'normal',
                'supports_webhooks' => 0,
                'supports_sync' => 0,
                'metadata' => ['scaffold_only' => true],
            ],
        ];
    }

    public static function getCapabilityDefinitions(): array
    {
        return [
            [
                'capability_key' => 'external.rest.read_inventory_status',
                'connector_key' => 'generic_rest',
                'name' => 'Read Inventory Status',
                'description' => 'Read a pre-approved inventory status endpoint from an external API.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'item_id' => ['type' => 'string'],
                        'warehouse_id' => ['type' => 'string'],
                    ],
                    'required' => ['item_id'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'records' => ['type' => 'array'],
                        'trust_level' => ['type' => 'string'],
                    ],
                ],
                'required_permission' => 'ai.integration.rest.read',
                'safety_classification' => 'normal',
                'operation_type' => 'read',
                'requires_confirmation' => 0,
                'rate_limit_per_minute' => 30,
                'rate_limit_per_hour' => 500,
                'rate_limit_per_day' => 5000,
                'concurrent_limit' => 3,
                'metadata' => ['tool_visible' => true],
            ],
            [
                'capability_key' => 'external.rest.import_supplier_preview',
                'connector_key' => 'generic_rest',
                'name' => 'Import Supplier Preview',
                'description' => 'Preview approved supplier import data from a restricted external endpoint before applying changes.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'supplier_code' => ['type' => 'string'],
                        'limit' => ['type' => 'integer'],
                    ],
                    'required' => [],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'preview' => ['type' => 'array'],
                        'trust_level' => ['type' => 'string'],
                    ],
                ],
                'required_permission' => 'ai.integration.rest.write',
                'safety_classification' => 'restricted',
                'operation_type' => 'write',
                'requires_confirmation' => 1,
                'rate_limit_per_minute' => 10,
                'rate_limit_per_hour' => 120,
                'rate_limit_per_day' => 1000,
                'concurrent_limit' => 1,
                'metadata' => ['tool_visible' => false],
            ],
            [
                'capability_key' => 'external.webhook.workflow_completed',
                'connector_key' => 'outbound_webhook',
                'name' => 'Send Workflow Completed Webhook',
                'description' => 'Deliver a controlled workflow completed event to an approved outbound webhook.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'webhook_key' => ['type' => 'string'],
                        'event_name' => ['type' => 'string'],
                        'payload' => ['type' => 'object'],
                    ],
                    'required' => ['webhook_key', 'event_name'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'delivery_id' => ['type' => 'integer'],
                        'status' => ['type' => 'string'],
                    ],
                ],
                'required_permission' => 'ai.integration.webhook.send',
                'safety_classification' => 'restricted',
                'operation_type' => 'write',
                'requires_confirmation' => 1,
                'rate_limit_per_minute' => 10,
                'rate_limit_per_hour' => 200,
                'rate_limit_per_day' => 1000,
                'concurrent_limit' => 2,
                'metadata' => ['workflow_only' => true],
            ],
            [
                'capability_key' => 'external.calendar.read_events',
                'connector_key' => 'company_calendar',
                'name' => 'Read Calendar Events',
                'description' => 'Reserved read-only calendar connector capability for future provider-backed use.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'date' => ['type' => 'string'],
                    ],
                    'required' => ['date'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'events' => ['type' => 'array'],
                    ],
                ],
                'required_permission' => 'ai.integrations.use',
                'safety_classification' => 'normal',
                'operation_type' => 'read',
                'requires_confirmation' => 0,
                'rate_limit_per_minute' => 20,
                'rate_limit_per_hour' => 200,
                'rate_limit_per_day' => 2000,
                'concurrent_limit' => 2,
                'metadata' => ['scaffold_only' => true],
            ],
        ];
    }

    public static function listConnectors(PDO $db): array
    {
        self::ensureDefaults($db);
        $stmt = $db->query("SELECT * FROM ai_connectors ORDER BY category, name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listCapabilities(PDO $db, ?string $connectorKey = null): array
    {
        self::ensureDefaults($db);
        if ($connectorKey) {
            $stmt = $db->prepare("SELECT * FROM ai_connector_capabilities WHERE connector_key = ? ORDER BY capability_key");
            $stmt->execute([$connectorKey]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $db->query("SELECT * FROM ai_connector_capabilities ORDER BY connector_key, capability_key")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getCapability(PDO $db, string $capabilityKey): ?array
    {
        self::ensureDefaults($db);
        $stmt = $db->prepare("SELECT * FROM ai_connector_capabilities WHERE capability_key = ? LIMIT 1");
        $stmt->execute([$capabilityKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function listConnections(PDO $db, int $companyId): array
    {
        self::ensureDefaults($db);
        $stmt = $db->prepare("
            SELECT c.*, h.status AS health_status, h.auth_status, h.api_status, h.circuit_state
            FROM ai_integration_connections c
            LEFT JOIN ai_integration_health h ON h.connection_id = c.id
            WHERE c.company_id = ?
            ORDER BY c.updated_at DESC
        ");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getConnection(PDO $db, int $connectionId, int $companyId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_integration_connections WHERE id = ? AND company_id = ? LIMIT 1");
        $stmt->execute([$connectionId, $companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['configuration'] = self::decodeJson($row['configuration_json'] ?? null);
        $row['enabled_capabilities'] = self::decodeJson($row['enabled_capabilities_json'] ?? null);
        $row['credentials'] = self::listCredentials($db, $connectionId);
        $row['rest_endpoints'] = self::listRestEndpoints($db, $connectionId);
        return $row;
    }

    public static function saveConnection(PDO $db, int $companyId, int $userId, array $data): array
    {
        self::ensureDefaults($db);
        $connectorKey = trim((string) ($data['connector_key'] ?? ''));
        $displayName = trim((string) ($data['display_name'] ?? ''));
        $status = self::normalizeEnum((string) ($data['status'] ?? 'draft'), ['draft','connecting','active','expired','failed','disabled','revoked'], 'draft');
        $authState = self::normalizeEnum((string) ($data['authentication_state'] ?? 'unconfigured'), ['unconfigured','configured','expired','failed','revoked'], 'unconfigured');
        $classification = self::normalizeEnum((string) ($data['connection_classification'] ?? 'normal'), ['normal','sensitive','restricted'], 'normal');
        $connectionId = (int) ($data['id'] ?? 0);
        $enabledCapabilities = array_values(array_unique(array_filter(array_map('strval', is_array($data['enabled_capabilities'] ?? null) ? $data['enabled_capabilities'] : []))));
        $config = is_array($data['configuration'] ?? null) ? $data['configuration'] : [];
        foreach (['approved_base_url', 'allowed_host', 'allow_internal_destination'] as $configKey) {
            if (array_key_exists($configKey, $data) && $data[$configKey] !== '' && $data[$configKey] !== null) {
                $config[$configKey] = $data[$configKey];
            }
        }

        if ($connectorKey === '' || $displayName === '') {
            return ['success' => false, 'error' => 'Connector and display name are required.'];
        }
        if (!self::connectorExists($db, $connectorKey)) {
            return ['success' => false, 'error' => 'Connector is not registered server-side.'];
        }

        $allowedCapabilities = array_column(self::listCapabilities($db, $connectorKey), 'capability_key');
        foreach ($enabledCapabilities as $capabilityKey) {
            if (!in_array($capabilityKey, $allowedCapabilities, true)) {
                return ['success' => false, 'error' => 'Connection contains an unregistered capability.'];
            }
        }

        if ($connectionId > 0) {
            $existing = self::getConnection($db, $connectionId, $companyId);
            if (!$existing) {
                return ['success' => false, 'error' => 'Connection not found.'];
            }
            $stmt = $db->prepare("
                UPDATE ai_integration_connections
                SET connector_key = ?, display_name = ?, status = ?, authentication_state = ?,
                    configuration_json = ?, enabled_capabilities_json = ?, connection_classification = ?,
                    updated_by = ?, updated_at = NOW()
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([
                $connectorKey,
                $displayName,
                $status,
                $authState,
                self::encodeJson($config),
                self::encodeJson($enabledCapabilities),
                $classification,
                $userId,
                $connectionId,
                $companyId,
            ]);
            self::audit($db, $userId, 'connection_updated', 'integration_connection', $connectionId, ['connector_key' => $connectorKey]);
            self::upsertHealth($db, $connectionId, $connectorKey, ['status' => $status === 'active' ? 'healthy' : 'disabled', 'auth_status' => $authState === 'configured' ? 'valid' : 'missing']);
            return ['success' => true, 'id' => $connectionId];
        }

        $stmt = $db->prepare("
            INSERT INTO ai_integration_connections
                (company_id, connector_key, display_name, status, authentication_state, configuration_json,
                 enabled_capabilities_json, connection_classification, created_by, updated_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $companyId,
            $connectorKey,
            $displayName,
            $status,
            $authState,
            self::encodeJson($config),
            self::encodeJson($enabledCapabilities),
            $classification,
            $userId,
            $userId,
        ]);
        $newId = (int) $db->lastInsertId();
        self::audit($db, $userId, 'connection_created', 'integration_connection', $newId, ['connector_key' => $connectorKey]);
        self::upsertHealth($db, $newId, $connectorKey, ['status' => $status === 'active' ? 'healthy' : 'disabled', 'auth_status' => $authState === 'configured' ? 'valid' : 'missing']);
        return ['success' => true, 'id' => $newId];
    }

    public static function listCredentials(PDO $db, int $connectionId): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_integration_credentials WHERE connection_id = ? ORDER BY created_at DESC");
        $stmt->execute([$connectionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $secret = aiDecryptCredential((string) ($row['secret_encrypted'] ?? ''), (string) ($row['secret_iv'] ?? '')) ?? '';
            $row['masked_secret'] = $secret !== '' ? aiMaskApiKey($secret) : null;
            $row['metadata'] = self::decodeJson($row['metadata_json'] ?? null);
            unset($row['secret_encrypted'], $row['secret_iv'], $row['secondary_secret_encrypted'], $row['secondary_secret_iv']);
        }
        unset($row);
        return $rows;
    }

    public static function saveCredential(PDO $db, int $connectionId, int $companyId, int $userId, array $data): array
    {
        $connection = self::getConnection($db, $connectionId, $companyId);
        if (!$connection) {
            return ['success' => false, 'error' => 'Connection not found.'];
        }
        $credentialType = trim((string) ($data['credential_type'] ?? 'connector_auth'));
        $authStrategy = trim((string) ($data['auth_strategy'] ?? 'api_key'));
        $secret = trim((string) ($data['secret'] ?? ''));
        $secondary = trim((string) ($data['secondary_secret'] ?? ''));
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];

        if ($secret === '') {
            return ['success' => false, 'error' => 'Credential secret is required.'];
        }
        if (!isset(self::getAuthStrategies()[$authStrategy])) {
            return ['success' => false, 'error' => 'Authentication strategy is not supported.'];
        }

        $encPrimary = aiEncryptCredential($secret);
        $encSecondary = $secondary !== '' ? aiEncryptCredential($secondary) : ['encrypted' => null, 'iv' => null];
        $db->prepare("UPDATE ai_integration_credentials SET status = 'rotated', rotated_at = NOW(), rotated_by = ? WHERE connection_id = ? AND status = 'active'")
            ->execute([$userId, $connectionId]);
        $db->prepare("
            INSERT INTO ai_integration_credentials
                (connection_id, credential_type, auth_strategy, secret_encrypted, secret_iv, secondary_secret_encrypted,
                 secondary_secret_iv, metadata_json, version_no, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ")->execute([
            $connectionId,
            $credentialType,
            $authStrategy,
            $encPrimary['encrypted'],
            $encPrimary['iv'],
            $encSecondary['encrypted'],
            $encSecondary['iv'],
            self::encodeJson($metadata),
            self::getNextCredentialVersion($db, $connectionId),
        ]);
        self::audit($db, $userId, 'credential_updated', 'integration_connection', $connectionId, ['auth_strategy' => $authStrategy, 'credential_type' => $credentialType]);
        self::upsertHealth($db, $connectionId, (string) $connection['connector_key'], ['auth_status' => 'valid']);
        return ['success' => true];
    }

    public static function revokeConnection(PDO $db, int $connectionId, int $companyId, int $userId): array
    {
        $connection = self::getConnection($db, $connectionId, $companyId);
        if (!$connection) {
            return ['success' => false, 'error' => 'Connection not found.'];
        }
        $db->prepare("
            UPDATE ai_integration_connections
            SET status = 'revoked', authentication_state = 'revoked', revoked_at = NOW(), updated_by = ?, updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ")->execute([$userId, $connectionId, $companyId]);
        $db->prepare("UPDATE ai_integration_credentials SET status = 'revoked', rotated_at = NOW(), rotated_by = ? WHERE connection_id = ? AND status = 'active'")
            ->execute([$userId, $connectionId]);
        self::audit($db, $userId, 'connection_revoked', 'integration_connection', $connectionId, ['connector_key' => $connection['connector_key']]);
        self::upsertHealth($db, $connectionId, (string) $connection['connector_key'], ['status' => 'disabled', 'auth_status' => 'revoked']);
        return ['success' => true];
    }

    public static function listRestEndpoints(PDO $db, ?int $connectionId = null): array
    {
        if ($connectionId) {
            $stmt = $db->prepare("SELECT * FROM ai_connector_rest_endpoints WHERE connection_id = ? ORDER BY name");
            $stmt->execute([$connectionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $db->query("SELECT * FROM ai_connector_rest_endpoints ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function saveRestEndpoint(PDO $db, int $connectionId, int $companyId, int $userId, array $data): array
    {
        $connection = self::getConnection($db, $connectionId, $companyId);
        if (!$connection || $connection['connector_key'] !== 'generic_rest') {
            return ['success' => false, 'error' => 'Generic REST connection not found.'];
        }

        $capabilityKey = trim((string) ($data['capability_key'] ?? ''));
        $endpointKey = trim((string) ($data['endpoint_key'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $baseUrl = trim((string) ($data['approved_base_url'] ?? ''));
        $path = '/' . ltrim(trim((string) ($data['approved_path'] ?? '')), '/');
        $method = strtoupper(trim((string) ($data['http_method'] ?? 'GET')));
        $authStrategy = trim((string) ($data['auth_strategy'] ?? 'api_key'));
        $allowedQuery = is_array($data['allowed_query_params'] ?? null) ? $data['allowed_query_params'] : [];
        $requestSchema = is_array($data['request_schema'] ?? null) ? $data['request_schema'] : [];
        $outputSchema = is_array($data['output_schema'] ?? null) ? $data['output_schema'] : [];

        if ($capabilityKey === '' || $endpointKey === '' || $name === '' || $baseUrl === '') {
            return ['success' => false, 'error' => 'Capability, endpoint key, name, and base URL are required.'];
        }
        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return ['success' => false, 'error' => 'HTTP method is not allowed.'];
        }
        $urlCheck = self::validateDestinationUrl(rtrim($baseUrl, '/') . $path, (bool) ($connection['configuration']['allow_internal_destination'] ?? false));
        if (!$urlCheck['valid']) {
            return ['success' => false, 'error' => $urlCheck['error']];
        }

        $stmt = $db->prepare("
            INSERT INTO ai_connector_rest_endpoints
                (connection_id, connector_key, capability_key, endpoint_key, name, approved_base_url, approved_path,
                 http_method, allowed_query_params_json, request_schema_json, output_schema_json, auth_strategy,
                 safety_classification, created_at, updated_at)
            VALUES (?, 'generic_rest', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'restricted', NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                capability_key = VALUES(capability_key),
                name = VALUES(name),
                approved_base_url = VALUES(approved_base_url),
                approved_path = VALUES(approved_path),
                http_method = VALUES(http_method),
                allowed_query_params_json = VALUES(allowed_query_params_json),
                request_schema_json = VALUES(request_schema_json),
                output_schema_json = VALUES(output_schema_json),
                auth_strategy = VALUES(auth_strategy),
                updated_at = NOW()
        ");
        $stmt->execute([
            $connectionId,
            $capabilityKey,
            $endpointKey,
            $name,
            rtrim($baseUrl, '/'),
            $path,
            $method,
            self::encodeJson(array_values(array_unique(array_map('strval', $allowedQuery)))),
            self::encodeJson($requestSchema),
            self::encodeJson($outputSchema),
            $authStrategy,
        ]);
        self::audit($db, $userId, 'rest_endpoint_saved', 'integration_connection', $connectionId, ['endpoint_key' => $endpointKey, 'capability_key' => $capabilityKey]);
        return ['success' => true];
    }

    public static function listWebhooks(PDO $db, int $companyId): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_webhook_definitions WHERE company_id = ? ORDER BY updated_at DESC");
        $stmt->execute([$companyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['payload_schema'] = self::decodeJson($row['payload_schema_json'] ?? null);
            $row['retry_policy'] = self::decodeJson($row['retry_policy_json'] ?? null);
        }
        unset($row);
        return $rows;
    }

    public static function saveWebhook(PDO $db, int $companyId, int $userId, array $data): array
    {
        $id = (int) ($data['id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $webhookKey = trim((string) ($data['webhook_key'] ?? ''));
        $destinationUrl = trim((string) ($data['destination_url'] ?? ''));
        $eventSource = trim((string) ($data['event_source'] ?? ''));
        $status = self::normalizeEnum((string) ($data['status'] ?? 'draft'), ['draft','active','disabled','failed','revoked'], 'draft');
        $signatureHeader = trim((string) ($data['signature_header'] ?? 'X-AI-Signature'));
        $payloadSchema = is_array($data['payload_schema'] ?? null) ? $data['payload_schema'] : [];
        $retryPolicy = is_array($data['retry_policy'] ?? null) ? $data['retry_policy'] : ['max_attempts' => 3, 'retry_delay_seconds' => 30];
        $allowInternal = !empty($data['allow_internal_destination']);
        $secret = trim((string) ($data['secret'] ?? ''));

        if ($name === '' || $webhookKey === '' || $destinationUrl === '' || $eventSource === '') {
            return ['success' => false, 'error' => 'Webhook name, key, destination URL, and event source are required.'];
        }
        $urlCheck = self::validateDestinationUrl($destinationUrl, $allowInternal);
        if (!$urlCheck['valid']) {
            return ['success' => false, 'error' => $urlCheck['error']];
        }
        $host = (string) parse_url($destinationUrl, PHP_URL_HOST);

        $encrypted = null;
        if ($secret !== '') {
            $encrypted = aiEncryptCredential($secret);
        }

        if ($id > 0) {
            $current = self::getWebhook($db, $companyId, $id);
            if (!$current) {
                return ['success' => false, 'error' => 'Webhook not found.'];
            }
            $db->prepare("
                UPDATE ai_webhook_definitions
                SET name = ?, webhook_key = ?, destination_url = ?, allowed_host = ?, event_source = ?,
                    payload_schema_json = ?, signature_header = ?, status = ?, retry_policy_json = ?,
                    allow_internal_destination = ?, updated_by = ?, updated_at = NOW(),
                    secret_encrypted = COALESCE(?, secret_encrypted), secret_iv = COALESCE(?, secret_iv)
                WHERE id = ? AND company_id = ?
            ")->execute([
                $name,
                $webhookKey,
                $destinationUrl,
                $host,
                $eventSource,
                self::encodeJson($payloadSchema),
                $signatureHeader,
                $status,
                self::encodeJson($retryPolicy),
                $allowInternal ? 1 : 0,
                $userId,
                $encrypted['encrypted'] ?? null,
                $encrypted['iv'] ?? null,
                $id,
                $companyId,
            ]);
            self::audit($db, $userId, 'webhook_updated', 'webhook', $id, ['webhook_key' => $webhookKey]);
            return ['success' => true, 'id' => $id];
        }

        $db->prepare("
            INSERT INTO ai_webhook_definitions
                (company_id, webhook_key, name, destination_url, allowed_host, event_source, payload_schema_json,
                 secret_encrypted, secret_iv, signature_header, status, retry_policy_json, allow_internal_destination,
                 created_by, updated_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ")->execute([
            $companyId,
            $webhookKey,
            $name,
            $destinationUrl,
            $host,
            $eventSource,
            self::encodeJson($payloadSchema),
            $encrypted['encrypted'] ?? null,
            $encrypted['iv'] ?? null,
            $signatureHeader,
            $status,
            self::encodeJson($retryPolicy),
            $allowInternal ? 1 : 0,
            $userId,
            $userId,
        ]);
        $newId = (int) $db->lastInsertId();
        self::audit($db, $userId, 'webhook_created', 'webhook', $newId, ['webhook_key' => $webhookKey]);
        return ['success' => true, 'id' => $newId];
    }

    public static function getWebhook(PDO $db, int $companyId, int $id): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_webhook_definitions WHERE company_id = ? AND id = ? LIMIT 1");
        $stmt->execute([$companyId, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function getPolicies(PDO $db, int $companyId): array
    {
        self::ensureDefaults($db);
        $stmt = $db->prepare("SELECT * FROM ai_integration_policies WHERE company_id = ? ORDER BY policy_key");
        $stmt->execute([$companyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['config'] = self::decodeJson($row['config_json'] ?? null);
        }
        unset($row);
        return $rows;
    }

    public static function updatePolicy(PDO $db, int $companyId, int $userId, string $policyKey, bool $enabled): array
    {
        $db->prepare("UPDATE ai_integration_policies SET enabled = ?, updated_by = ?, updated_at = NOW() WHERE company_id = ? AND policy_key = ?")
            ->execute([$enabled ? 1 : 0, $userId, $companyId, $policyKey]);
        self::audit($db, $userId, 'policy_updated', 'integration_policy', 0, ['policy_key' => $policyKey, 'enabled' => $enabled]);
        return ['success' => true];
    }

    public static function getPolicyMap(PDO $db, int $companyId): array
    {
        $map = [];
        foreach (self::getPolicies($db, $companyId) as $row) {
            $map[$row['policy_key']] = [
                'enabled' => (bool) $row['enabled'],
                'config' => $row['config'] ?? [],
            ];
        }
        return $map;
    }

    public static function executeCapability(PDO $db, int $userId, int $companyId, string $capabilityKey, array $input, array $userPermissions, array $options = []): array
    {
        self::ensureDefaults($db);

        if (!AIKillSwitchService::isEnabled($db, 'external_integrations')) {
            return ['success' => false, 'error' => 'External integrations are disabled by kill switch.'];
        }
        if (!AIKillSwitchService::isEnabled($db, 'ai_connector_tools') && ($options['actor_type'] ?? 'user') !== 'workflow') {
            return ['success' => false, 'error' => 'AI connector tools are disabled by kill switch.'];
        }

        $capability = self::getCapability($db, $capabilityKey);
        if (!$capability || !(int) $capability['is_active']) {
            return ['success' => false, 'error' => 'Capability is not registered or is disabled.'];
        }
        $connector = self::getConnectorDefinition($db, (string) $capability['connector_key']);
        if (!$connector) {
            return ['success' => false, 'error' => 'Connector is not registered server-side.'];
        }
        if (($connector['status'] ?? 'available') !== 'available') {
            return ['success' => false, 'error' => 'Connector is currently unavailable: ' . (string) $connector['status']];
        }
        if (!in_array('ai.integrations.use', $userPermissions, true) && !in_array('ai.settings.manage', $userPermissions, true)) {
            return ['success' => false, 'error' => 'You do not have permission to use integrations.'];
        }
        $requiredPermission = trim((string) ($capability['required_permission'] ?? ''));
        if ($requiredPermission !== '' && !in_array($requiredPermission, $userPermissions, true) && !in_array('ai.settings.manage', $userPermissions, true)) {
            return ['success' => false, 'error' => 'You do not have the required capability permission.'];
        }

        $policies = self::getPolicyMap($db, $companyId);
        $operationType = (string) ($capability['operation_type'] ?? 'read');
        if (in_array($operationType, ['write', 'destructive', 'restricted'], true) && !AIKillSwitchService::isEnabled($db, 'external_writes')) {
            return ['success' => false, 'error' => 'External write operations are disabled by kill switch.'];
        }
        if (in_array($operationType, ['write', 'destructive', 'restricted'], true) && !($policies['allow_external_writes']['enabled'] ?? false)) {
            return ['success' => false, 'error' => 'External write operations are disabled by policy.'];
        }
        if ($operationType === 'read' && !($policies['allow_external_data_access']['enabled'] ?? false)) {
            return ['success' => false, 'error' => 'External data access is disabled by policy.'];
        }
        if (str_starts_with($capabilityKey, 'external.rest.') && !AIKillSwitchService::isEnabled($db, 'generic_rest_connector')) {
            return ['success' => false, 'error' => 'Generic REST connector is disabled by kill switch.'];
        }
        if (str_starts_with($capabilityKey, 'external.webhook.') && !AIKillSwitchService::isEnabled($db, 'webhooks')) {
            return ['success' => false, 'error' => 'Webhooks are disabled by kill switch.'];
        }

        $connectionId = isset($options['connection_id']) ? (int) $options['connection_id'] : (int) ($input['connection_id'] ?? 0);
        $connection = $connectionId > 0
            ? self::getConnection($db, $connectionId, $companyId)
            : self::findConnectionForCapability($db, $companyId, $capabilityKey);
        if (!$connection) {
            return ['success' => false, 'error' => 'No active connection exists for this capability.', 'suggestion' => 'Open Integration Setup?'];
        }
        if (!in_array($capabilityKey, is_array($connection['enabled_capabilities'] ?? null) ? $connection['enabled_capabilities'] : [], true)) {
            return ['success' => false, 'error' => 'Capability is not enabled on the selected connection.'];
        }
        if (($connection['status'] ?? '') !== 'active') {
            return ['success' => false, 'error' => 'Connection is not active.'];
        }

        $health = self::getHealthForConnection($db, (int) $connection['id']);
        if (($health['circuit_state'] ?? 'healthy') === 'temporarily_disabled') {
            return ['success' => false, 'error' => 'Connector is temporarily disabled by circuit breaker.'];
        }

        $validation = self::validateInputAgainstSchema($input, self::decodeJson($capability['input_schema_json'] ?? null));
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        $rate = self::checkRateLimits($db, (int) $connection['id'], $capability);
        if (!$rate['allowed']) {
            self::recordActivity($db, [
                'connector_key' => (string) $capability['connector_key'],
                'connection_id' => (int) $connection['id'],
                'capability_key' => $capabilityKey,
                'actor_type' => (string) ($options['actor_type'] ?? 'user'),
                'actor_id' => $userId,
                'company_id' => $companyId,
                'status' => 'blocked',
                'error_category' => 'rate_limit',
                'error_message' => $rate['message'],
                'safe_input_summary' => self::safeSummary($input),
            ]);
            self::upsertHealth($db, (int) $connection['id'], (string) $connection['connector_key'], ['status' => 'degraded', 'api_status' => 'degraded', 'increment_rate_limits' => true]);
            return ['success' => false, 'error' => $rate['message']];
        }

        if ((int) ($capability['requires_confirmation'] ?? 0) === 1 && empty($options['confirmed'])) {
            $preview = [
                'connector' => $connection['display_name'],
                'capability' => $capabilityKey,
                'input' => self::safeSummary($input),
                'risk' => $operationType,
            ];
            if (!empty($options['skip_action_proposal'])) {
                return [
                    'success' => true,
                    'requires_confirmation' => true,
                    'message' => 'This external capability requires confirmation before execution.',
                    'preview' => $preview,
                ];
            }
            if (($options['actor_type'] ?? 'user') !== 'workflow' && class_exists('AIActionExecutor')) {
                $executor = new AIActionExecutor($db, $userId, $userPermissions);
                $proposal = $executor->propose('external.connector.execute', [
                    'capability_key' => $capabilityKey,
                    'connection_id' => (int) $connection['id'],
                    'input' => $input,
                ], (int) ($options['conversation_id'] ?? 0));
                if (!empty($proposal['success'])) {
                    self::recordActivity($db, [
                        'connector_key' => (string) $capability['connector_key'],
                        'connection_id' => (int) $connection['id'],
                        'capability_key' => $capabilityKey,
                        'actor_type' => (string) ($options['actor_type'] ?? 'user'),
                        'actor_id' => $userId,
                        'company_id' => $companyId,
                        'status' => 'previewed',
                        'safe_input_summary' => self::safeSummary($preview),
                        'safe_output_summary' => 'Pending action #' . (int) ($proposal['pending_action_id'] ?? 0) . ' created for confirmation.',
                    ]);
                    return $proposal + [
                        'message' => 'This external capability requires confirmation before execution.',
                        'preview' => $proposal['preview'] ?? $preview,
                    ];
                }
                return [
                    'success' => false,
                    'error' => $proposal['error'] ?? 'Unable to create a pending action for this external capability.',
                ];
            }
            self::recordActivity($db, [
                'connector_key' => (string) $capability['connector_key'],
                'connection_id' => (int) $connection['id'],
                'capability_key' => $capabilityKey,
                'actor_type' => (string) ($options['actor_type'] ?? 'user'),
                'actor_id' => $userId,
                'company_id' => $companyId,
                'status' => 'previewed',
                'safe_input_summary' => self::safeSummary($preview),
                'safe_output_summary' => 'Confirmation required before external write.',
            ]);
            return [
                'success' => true,
                'requires_confirmation' => true,
                'message' => 'This external capability requires confirmation before execution.',
                'preview' => $preview,
            ];
        }

        $started = microtime(true);
        $result = match ((string) $capability['connector_key']) {
            'generic_rest' => self::executeRestrictedRestCapability($db, $connection, $capability, $input),
            'outbound_webhook' => self::executeWebhookCapability($db, $companyId, $userId, $connection, $capability, $input),
            'company_calendar' => ['success' => false, 'error' => 'Calendar connector scaffold is not yet configured with a provider.'],
            default => ['success' => false, 'error' => 'Unsupported connector implementation.'],
        };
        $duration = (int) round((microtime(true) - $started) * 1000);

        if (!empty($result['success'])) {
            self::recordActivity($db, [
                'connector_key' => (string) $capability['connector_key'],
                'connection_id' => (int) $connection['id'],
                'capability_key' => $capabilityKey,
                'actor_type' => (string) ($options['actor_type'] ?? 'user'),
                'actor_id' => $userId,
                'company_id' => $companyId,
                'status' => 'executed',
                'duration_ms' => $duration,
                'safe_input_summary' => self::safeSummary($input),
                'safe_output_summary' => self::safeSummary($result['normalized'] ?? $result['data'] ?? []),
            ]);
            self::upsertHealth($db, (int) $connection['id'], (string) $connection['connector_key'], [
                'status' => 'healthy',
                'auth_status' => 'valid',
                'api_status' => 'available',
                'last_success_at' => date('Y-m-d H:i:s'),
                'reset_failures' => true,
            ]);
            return $result + ['duration_ms' => $duration];
        }

        self::recordActivity($db, [
            'connector_key' => (string) $capability['connector_key'],
            'connection_id' => (int) $connection['id'],
            'capability_key' => $capabilityKey,
            'actor_type' => (string) ($options['actor_type'] ?? 'user'),
            'actor_id' => $userId,
            'company_id' => $companyId,
            'status' => 'failed',
            'duration_ms' => $duration,
            'safe_input_summary' => self::safeSummary($input),
            'error_category' => 'connector_error',
            'error_message' => substr((string) ($result['error'] ?? 'Connector execution failed.'), 0, 500),
        ]);
        self::upsertHealth($db, (int) $connection['id'], (string) $connection['connector_key'], [
            'status' => 'failed',
            'api_status' => 'failed',
            'last_error_at' => date('Y-m-d H:i:s'),
            'increment_failures' => true,
        ]);
        return $result + ['duration_ms' => $duration];
    }

    public static function suggestCommandCapability(PDO $db, int $userId, int $companyId, string $input, array $userPermissions): ?array
    {
        $inputLower = strtolower($input);
        $capabilityKey = null;
        $params = [];
        if (str_contains($inputLower, 'calendar')) {
            $capabilityKey = 'external.calendar.read_events';
            $params = ['date' => date('Y-m-d', strtotime('+1 day'))];
        } elseif (str_contains($inputLower, 'inventory') || str_contains($inputLower, 'supplier')) {
            $capabilityKey = str_contains($inputLower, 'supplier') ? 'external.rest.import_supplier_preview' : 'external.rest.read_inventory_status';
            if ($capabilityKey === 'external.rest.read_inventory_status') {
                $params = ['item_id' => 'demo-item'];
            }
        }
        if (!$capabilityKey) {
            return null;
        }
        return self::executeCapability($db, $userId, $companyId, $capabilityKey, $params, $userPermissions);
    }

    public static function deliverWebhook(PDO $db, int $companyId, int $userId, string $webhookKey, string $eventName, array $payload, array $options = []): array
    {
        if (!AIKillSwitchService::isEnabled($db, 'webhooks')) {
            return ['success' => false, 'error' => 'Webhooks are disabled by kill switch.'];
        }
        $stmt = $db->prepare("SELECT * FROM ai_webhook_definitions WHERE company_id = ? AND webhook_key = ? LIMIT 1");
        $stmt->execute([$companyId, $webhookKey]);
        $webhook = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$webhook) {
            return ['success' => false, 'error' => 'Webhook is not configured.'];
        }
        if (($webhook['status'] ?? '') !== 'active') {
            return ['success' => false, 'error' => 'Webhook is not active.'];
        }
        $urlCheck = self::validateDestinationUrl((string) $webhook['destination_url'], (bool) $webhook['allow_internal_destination']);
        if (!$urlCheck['valid']) {
            return ['success' => false, 'error' => $urlCheck['error']];
        }
        $policyMap = self::getPolicyMap($db, $companyId);
        if (!($policyMap['allow_webhooks']['enabled'] ?? false)) {
            return ['success' => false, 'error' => 'Webhooks are disabled by policy.'];
        }

        $schema = self::decodeJson($webhook['payload_schema_json'] ?? null);
        $validated = self::validateInputAgainstSchema($payload, is_array($schema) ? $schema : []);
        if (!$validated['valid']) {
            return ['success' => false, 'error' => 'Webhook payload failed schema validation.'];
        }

        $secret = aiDecryptCredential((string) ($webhook['secret_encrypted'] ?? ''), (string) ($webhook['secret_iv'] ?? '')) ?? '';
        $requestId = 'wh_' . bin2hex(random_bytes(8));
        $payloadEnvelope = [
            'event_name' => $eventName,
            'generated_at' => date('c'),
            'trust_level' => $options['trust_level'] ?? 'validated',
            'payload' => self::maskOutboundPayload($db, $companyId, $payload),
        ];
        $body = self::encodeJson($payloadEnvelope);
        $signature = $secret !== '' ? hash_hmac('sha256', $body, $secret) : null;
        $headers = [
            'Content-Type' => 'application/json',
            'X-AI-Webhook-Event' => $eventName,
            'X-AI-Webhook-Request' => $requestId,
        ];
        if ($signature) {
            $headers[$webhook['signature_header'] ?: 'X-AI-Signature'] = 'sha256=' . $signature;
        }

        $retry = self::decodeJson($webhook['retry_policy_json'] ?? null);
        $maxAttempts = max(1, min(5, (int) ($retry['max_attempts'] ?? 3)));
        $http = new AIHttpClient((int) ($retry['timeout_seconds'] ?? 20), true);
        $attempt = 0;
        $response = null;
        do {
            $attempt++;
            $response = $http->post((string) $webhook['destination_url'], $payloadEnvelope, $headers);
            if (!empty($response['success'])) {
                break;
            }
            if (!self::isRetryableHttpFailure($response, $eventName)) {
                break;
            }
        } while ($attempt < $maxAttempts);

        $deliveryStatus = !empty($response['success']) ? 'sent' : 'failed';
        $duration = null;
        $db->prepare("
            INSERT INTO ai_webhook_deliveries
                (webhook_id, event_name, request_id, status, http_status, duration_ms, safe_payload_summary, response_summary, error_category, attempt_count, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            (int) $webhook['id'],
            $eventName,
            $requestId,
            $deliveryStatus,
            (int) ($response['status'] ?? 0) ?: null,
            $duration,
            self::safeSummary($payloadEnvelope),
            self::safeSummary($response['data'] ?? ['status' => $response['status'] ?? 0]),
            !empty($response['success']) ? null : 'webhook_delivery',
            $attempt,
        ]);
        $deliveryId = (int) $db->lastInsertId();
        $db->prepare("UPDATE ai_webhook_definitions SET last_sent_at = NOW(), last_error_at = IF(? = 'failed', NOW(), last_error_at), updated_at = NOW() WHERE id = ?")
            ->execute([$deliveryStatus, (int) $webhook['id']]);
        self::audit($db, $userId, !empty($response['success']) ? 'webhook_sent' : 'webhook_failed', 'webhook', (int) $webhook['id'], ['event_name' => $eventName, 'request_id' => $requestId]);

        return [
            'success' => !empty($response['success']),
            'delivery_id' => $deliveryId,
            'status' => $deliveryStatus,
            'normalized' => [
                'delivery_id' => $deliveryId,
                'event_name' => $eventName,
                'request_id' => $requestId,
                'status' => $deliveryStatus,
                'trust_level' => 'validated',
            ],
            'error' => !empty($response['success']) ? null : ($response['error'] ?? 'Webhook delivery failed.'),
        ];
    }

    public static function createSyncPreview(PDO $db, int $companyId, int $userId, array $data): array
    {
        $connectionId = (int) ($data['connection_id'] ?? 0);
        $mappingId = (int) ($data['mapping_id'] ?? 0);
        $targetEntity = trim((string) ($data['target_entity'] ?? 'vendors'));
        $rows = is_array($data['rows'] ?? null) ? array_slice($data['rows'], 0, self::MAX_SYNC_ROWS) : [];
        $strategy = self::normalizeEnum((string) ($data['conflict_strategy'] ?? 'manual_review'), ['manual_review','prefer_erp','prefer_external'], 'manual_review');

        $connection = self::getConnection($db, $connectionId, $companyId);
        if (!$connection) {
            return ['success' => false, 'error' => 'Connection not found for sync preview.'];
        }
        if (!AIKillSwitchService::isEnabled($db, 'sync_jobs')) {
            return ['success' => false, 'error' => 'Sync jobs are disabled by kill switch.'];
        }
        if (empty($rows)) {
            return ['success' => false, 'error' => 'No rows were provided for sync preview.'];
        }

        $mapping = self::getSyncMapping($db, $companyId, $mappingId);
        if (!$mapping) {
            return ['success' => false, 'error' => 'Mapping not found.'];
        }
        $rules = self::decodeJson($mapping['rules_json'] ?? null);

        $preview = ['new_records' => 0, 'updated_records' => 0, 'skipped_records' => 0, 'conflicts' => 0, 'errors' => 0, 'rows' => []];
        foreach ($rows as $row) {
            $mapped = self::applyMappingRules($rules, is_array($row) ? $row : []);
            $conflict = self::detectConflict($db, $targetEntity, $mapped);
            $validationErrors = self::validateMappedRecord($targetEntity, $mapped);
            $action = 'skip';
            if (!empty($validationErrors)) {
                $action = 'error';
                $preview['errors']++;
            } elseif (($conflict['status'] ?? 'identical') === 'conflict') {
                $action = 'conflict';
                $preview['conflicts']++;
            } elseif (($conflict['exists'] ?? false) === true) {
                $action = 'update';
                $preview['updated_records']++;
            } else {
                $action = 'create';
                $preview['new_records']++;
            }
            $preview['rows'][] = [
                'external_record_id' => (string) ($row['id'] ?? $row['code'] ?? ''),
                'preview_action' => $action,
                'conflict_status' => $conflict['status'] ?? 'identical',
                'source' => $row,
                'mapped' => $mapped,
                'validation_errors' => $validationErrors,
            ];
        }
        $jobKey = 'sync_' . bin2hex(random_bytes(8));
        $db->prepare("
            INSERT INTO ai_sync_jobs
                (company_id, connection_id, connector_key, job_key, job_type, target_entity, status, mapping_id,
                 preview_json, conflict_strategy, requested_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'manual', ?, 'preview_ready', ?, ?, ?, ?, NOW(), NOW())
        ")->execute([
            $companyId,
            $connectionId,
            $connection['connector_key'],
            $jobKey,
            $targetEntity,
            $mappingId,
            self::encodeJson($preview),
            $strategy,
            $userId,
        ]);
        $jobId = (int) $db->lastInsertId();

        foreach ($preview['rows'] as $row) {
            $db->prepare("
                INSERT INTO ai_sync_job_rows
                    (sync_job_id, external_record_id, preview_action, conflict_status, resolution_status, source_data_json,
                     mapped_data_json, validation_errors_json, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, NOW(), NOW())
            ")->execute([
                $jobId,
                $row['external_record_id'],
                $row['preview_action'],
                $row['conflict_status'],
                self::encodeJson($row['source']),
                self::encodeJson($row['mapped']),
                self::encodeJson($row['validation_errors']),
            ]);
        }

        self::audit($db, $userId, 'sync_started', 'sync_job', $jobId, ['job_key' => $jobKey, 'preview_only' => true]);
        return ['success' => true, 'job_id' => $jobId, 'preview' => $preview];
    }

    public static function applySyncJob(PDO $db, int $companyId, int $userId, int $jobId, bool $confirmed): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_sync_jobs WHERE id = ? AND company_id = ? LIMIT 1");
        $stmt->execute([$jobId, $companyId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            return ['success' => false, 'error' => 'Sync job not found.'];
        }
        if (!$confirmed) {
            return ['success' => true, 'requires_confirmation' => true, 'message' => 'Sync apply requires confirmation.', 'preview' => self::decodeJson($job['preview_json'] ?? null)];
        }
        $rowsStmt = $db->prepare("SELECT * FROM ai_sync_job_rows WHERE sync_job_id = ? ORDER BY id");
        $rowsStmt->execute([$jobId]);
        $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'conflicts' => 0, 'errors' => 0];
        foreach ($rows as $row) {
            $action = $row['preview_action'];
            if (in_array($action, ['error', 'conflict'], true)) {
                $summary[$action === 'error' ? 'errors' : 'conflicts']++;
                continue;
            }
            if ($action === 'create' || $action === 'update') {
                $summary[$action === 'create' ? 'created' : 'updated']++;
            } else {
                $summary['skipped']++;
            }
        }
        $db->prepare("
            UPDATE ai_sync_jobs
            SET status = 'completed', approved_by = ?, completed_at = NOW(), apply_summary_json = ?, updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ")->execute([$userId, self::encodeJson($summary), $jobId, $companyId]);
        self::audit($db, $userId, 'sync_applied', 'sync_job', $jobId, $summary);
        return ['success' => true, 'summary' => $summary];
    }

    public static function listSyncJobs(PDO $db, int $companyId): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_sync_jobs WHERE company_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->execute([$companyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['preview'] = self::decodeJson($row['preview_json'] ?? null);
            $row['apply_summary'] = self::decodeJson($row['apply_summary_json'] ?? null);
        }
        unset($row);
        return $rows;
    }

    public static function listSyncMappings(PDO $db, int $companyId): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_sync_mappings WHERE company_id = ? ORDER BY updated_at DESC");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function saveSyncMapping(PDO $db, int $companyId, int $userId, array $data): array
    {
        $id = (int) ($data['id'] ?? 0);
        $connectionId = (int) ($data['connection_id'] ?? 0) ?: null;
        $mappingKey = trim((string) ($data['mapping_key'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $targetEntity = trim((string) ($data['target_entity'] ?? 'vendors'));
        $rules = is_array($data['rules'] ?? null) ? $data['rules'] : [];
        $status = self::normalizeEnum((string) ($data['status'] ?? 'draft'), ['draft','active','disabled','archived'], 'draft');
        if ($mappingKey === '' || $name === '') {
            return ['success' => false, 'error' => 'Mapping key and name are required.'];
        }

        if ($id > 0) {
            $db->prepare("
                UPDATE ai_sync_mappings
                SET connection_id = ?, mapping_key = ?, name = ?, target_entity = ?, rules_json = ?, status = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ? AND company_id = ?
            ")->execute([$connectionId, $mappingKey, $name, $targetEntity, self::encodeJson($rules), $status, $userId, $id, $companyId]);
            return ['success' => true, 'id' => $id];
        }
        $db->prepare("
            INSERT INTO ai_sync_mappings
                (company_id, connection_id, mapping_key, name, target_entity, rules_json, status, created_by, updated_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ")->execute([$companyId, $connectionId, $mappingKey, $name, $targetEntity, self::encodeJson($rules), $status, $userId, $userId]);
        return ['success' => true, 'id' => (int) $db->lastInsertId()];
    }

    public static function getActivity(PDO $db, int $companyId, int $limit = 50): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_integration_activity WHERE company_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->bindValue(1, $companyId, PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getHealth(PDO $db, int $companyId): array
    {
        $stmt = $db->prepare("
            SELECT h.*, c.display_name
            FROM ai_integration_health h
            JOIN ai_integration_connections c ON c.id = h.connection_id
            WHERE c.company_id = ?
            ORDER BY c.display_name
        ");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getDashboardSummary(PDO $db, int $companyId): array
    {
        return [
            'connectors' => self::scalar($db, "SELECT COUNT(*) FROM ai_connectors"),
            'active_connections' => self::scalar($db, "SELECT COUNT(*) FROM ai_integration_connections WHERE company_id = ? AND status = 'active'", [$companyId]),
            'failed_connections' => self::scalar($db, "SELECT COUNT(*) FROM ai_integration_connections WHERE company_id = ? AND status IN ('failed','expired')", [$companyId]),
            'webhook_failures' => self::scalar($db, "SELECT COUNT(*) FROM ai_webhook_deliveries d JOIN ai_webhook_definitions w ON w.id = d.webhook_id WHERE w.company_id = ? AND d.status = 'failed' AND DATE(d.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", [$companyId]),
            'sync_jobs' => self::scalar($db, "SELECT COUNT(*) FROM ai_sync_jobs WHERE company_id = ? AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", [$companyId]),
            'activity_30d' => self::scalar($db, "SELECT COUNT(*) FROM ai_integration_activity WHERE company_id = ? AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", [$companyId]),
        ];
    }

    private static function executeRestrictedRestCapability(PDO $db, array $connection, array $capability, array $input): array
    {
        $endpoint = self::findEndpointForCapability($db, (int) $connection['id'], (string) $capability['capability_key']);
        if (!$endpoint) {
            return ['success' => false, 'error' => 'No approved endpoint is configured for this capability.'];
        }
        $credential = self::getActiveCredential($db, (int) $connection['id']);
        if (!$credential) {
            return ['success' => false, 'error' => 'No active credential is configured for this connection.'];
        }
        $url = rtrim((string) $endpoint['approved_base_url'], '/') . '/' . ltrim((string) $endpoint['approved_path'], '/');
        $urlCheck = self::validateDestinationUrl($url, (bool) ($connection['configuration']['allow_internal_destination'] ?? false));
        if (!$urlCheck['valid']) {
            return ['success' => false, 'error' => $urlCheck['error']];
        }

        $method = strtoupper((string) $endpoint['http_method']);
        $allowedParams = self::decodeJson($endpoint['allowed_query_params_json'] ?? null);
        $sanitized = self::filterAllowedParams($input, is_array($allowedParams) ? $allowedParams : []);
        $headers = ['Accept' => 'application/json'];
        $headers = self::applyCredentialHeaders($credential, $headers, (string) $endpoint['auth_strategy']);
        $http = new AIHttpClient(20, true);
        if ($method === 'GET' && !empty($sanitized)) {
            $url .= '?' . http_build_query($sanitized);
        }

        $response = null;
        $maxAttempts = $method === 'GET' ? 2 : 1;
        $attempt = 0;
        do {
            $attempt++;
            $response = match ($method) {
                'GET' => $http->get($url, $headers),
                default => $http->post($url, $method === 'POST' ? $sanitized : ['method_override' => $method, 'payload' => $sanitized], $headers),
            };
            if (!empty($response['success'])) {
                break;
            }
            if ($method !== 'GET' || !self::isRetryableReadFailure($response)) {
                break;
            }
        } while ($attempt < $maxAttempts);
        if (empty($response['success'])) {
            return ['success' => false, 'error' => $response['error'] ?? 'External API request failed.'];
        }
        $normalized = self::normalizeExternalOutput($response['data'] ?? $response['body'], 'validated', (string) $capability['capability_key']);
        return ['success' => true, 'data' => $normalized, 'normalized' => $normalized, 'attempt_count' => $attempt];
    }

    private static function executeWebhookCapability(PDO $db, int $companyId, int $userId, array $connection, array $capability, array $input): array
    {
        return self::deliverWebhook(
            $db,
            $companyId,
            $userId,
            (string) ($input['webhook_key'] ?? ''),
            (string) ($input['event_name'] ?? 'workflow.completed'),
            is_array($input['payload'] ?? null) ? $input['payload'] : [],
            ['trust_level' => 'validated']
        );
    }

    private static function findConnectionForCapability(PDO $db, int $companyId, string $capabilityKey): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_integration_connections WHERE company_id = ? AND status = 'active' ORDER BY updated_at DESC");
        $stmt->execute([$companyId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $enabled = self::decodeJson($row['enabled_capabilities_json'] ?? null);
            if (is_array($enabled) && in_array($capabilityKey, $enabled, true)) {
                $row['configuration'] = self::decodeJson($row['configuration_json'] ?? null);
                $row['enabled_capabilities'] = $enabled;
                return $row;
            }
        }
        return null;
    }

    private static function findEndpointForCapability(PDO $db, int $connectionId, string $capabilityKey): ?array
    {
        $stmt = $db->prepare("
            SELECT *
            FROM ai_connector_rest_endpoints
            WHERE connection_id = ? AND capability_key = ? AND is_active = 1
            ORDER BY updated_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$connectionId, $capabilityKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function getActiveCredential(PDO $db, int $connectionId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_integration_credentials WHERE connection_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$connectionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'auth_strategy' => $row['auth_strategy'],
            'secret' => aiDecryptCredential((string) ($row['secret_encrypted'] ?? ''), (string) ($row['secret_iv'] ?? '')) ?? '',
            'secondary_secret' => aiDecryptCredential((string) ($row['secondary_secret_encrypted'] ?? ''), (string) ($row['secondary_secret_iv'] ?? '')) ?? '',
            'metadata' => self::decodeJson($row['metadata_json'] ?? null),
        ];
    }

    private static function applyCredentialHeaders(array $credential, array $headers, string $strategyKey): array
    {
        $strategies = self::getAuthStrategies();
        $strategy = $strategies[$strategyKey] ?? $strategies[$credential['auth_strategy'] ?? 'api_key'] ?? null;
        return $strategy instanceof AIConnectorAuthStrategyInterface ? $strategy->apply($credential, $headers) : $headers;
    }

    private static function normalizeExternalOutput($payload, string $trustLevel, string $capabilityKey): array
    {
        return [
            'capability_key' => $capabilityKey,
            'trust_level' => $trustLevel,
            'normalized_at' => date('c'),
            'records' => is_array($payload) ? $payload : ['raw' => substr((string) $payload, 0, 2000)],
        ];
    }

    private static function validateInputAgainstSchema(array $input, array $schema): array
    {
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];
        foreach ($required as $field) {
            if (!array_key_exists($field, $input) || $input[$field] === '' || $input[$field] === null) {
                return ['valid' => false, 'error' => 'Missing required field: ' . $field];
            }
        }
        return ['valid' => true];
    }

    private static function filterAllowedParams(array $input, array $allowed): array
    {
        if (empty($allowed)) {
            return [];
        }
        $output = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $input)) {
                $output[$key] = is_scalar($input[$key]) ? $input[$key] : json_encode($input[$key]);
            }
        }
        return $output;
    }

    private static function validateDestinationUrl(string $url, bool $allowInternal): array
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return ['valid' => false, 'error' => 'Destination URL is invalid.'];
        }
        if (strtolower((string) $parts['scheme']) !== 'https' && !$allowInternal) {
            return ['valid' => false, 'error' => 'HTTPS is required for external destinations.'];
        }
        $host = strtolower((string) $parts['host']);
        foreach (self::PRIVATE_HOST_PATTERNS as $pattern) {
            if (preg_match($pattern, $host)) {
                return $allowInternal ? ['valid' => true] : ['valid' => false, 'error' => 'Private or internal destinations are blocked by SSRF policy.'];
            }
        }
        return ['valid' => true];
    }

    private static function maskOutboundPayload(PDO $db, int $companyId, array $payload): array
    {
        $policies = self::getPolicyMap($db, $companyId);
        $config = $policies['allow_external_data_access']['config'] ?? [];
        $maskPersonal = !empty($config['mask_personal_data']);
        $maskFinancial = !empty($config['mask_financial_data']);
        $masked = [];
        foreach ($payload as $key => $value) {
            $lower = strtolower((string) $key);
            if ($maskPersonal && preg_match('/email|phone|mobile|ssn|passport|employee/i', $lower)) {
                $masked[$key] = '[masked]';
            } elseif ($maskFinancial && preg_match('/amount|salary|cost|price|bank|iban|account/i', $lower)) {
                $masked[$key] = '[masked]';
            } else {
                $masked[$key] = $value;
            }
        }
        return $masked;
    }

    private static function isRetryableHttpFailure(array $response, string $eventName): bool
    {
        $status = (int) ($response['status'] ?? 0);
        if ($status >= 500 && $status < 600) {
            return true;
        }
        if (!empty($response['errno'])) {
            return true;
        }
        return false;
    }

    private static function isRetryableReadFailure(array $response): bool
    {
        $status = (int) ($response['status'] ?? 0);
        if (in_array($status, [408, 429], true) || ($status >= 500 && $status < 600)) {
            return true;
        }
        if (!empty($response['errno'])) {
            return true;
        }
        $error = strtolower((string) ($response['error'] ?? ''));
        return str_contains($error, 'timeout')
            || str_contains($error, 'temporar')
            || str_contains($error, 'connection reset')
            || str_contains($error, 'could not resolve');
    }

    private static function checkRateLimits(PDO $db, int $connectionId, array $capability): array
    {
        $caps = [
            'minute' => ['sql' => 'DATE_SUB(NOW(), INTERVAL 1 MINUTE)', 'max' => (int) ($capability['rate_limit_per_minute'] ?? 30)],
            'hour' => ['sql' => 'DATE_SUB(NOW(), INTERVAL 1 HOUR)', 'max' => (int) ($capability['rate_limit_per_hour'] ?? 500)],
            'day' => ['sql' => 'DATE_SUB(NOW(), INTERVAL 1 DAY)', 'max' => (int) ($capability['rate_limit_per_day'] ?? 5000)],
        ];
        foreach ($caps as $label => $cfg) {
            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM ai_integration_activity
                WHERE connection_id = ? AND capability_key = ? AND created_at >= {$cfg['sql']}
            ");
            $stmt->execute([$connectionId, $capability['capability_key']]);
            if ((int) $stmt->fetchColumn() >= $cfg['max']) {
                return ['allowed' => false, 'message' => 'Connector rate limit exceeded for the last ' . $label . '.'];
            }
        }
        return ['allowed' => true];
    }

    private static function upsertHealth(PDO $db, int $connectionId, string $connectorKey, array $changes): void
    {
        $current = self::getHealthForConnection($db, $connectionId);
        $consecutive = (int) ($current['consecutive_failures'] ?? 0);
        $rateEvents = (int) ($current['rate_limit_events'] ?? 0);
        if (!empty($changes['reset_failures'])) {
            $consecutive = 0;
        }
        if (!empty($changes['increment_failures'])) {
            $consecutive++;
        }
        if (!empty($changes['increment_rate_limits'])) {
            $rateEvents++;
        }
        $circuit = $consecutive >= self::CIRCUIT_BREAKER_THRESHOLD ? 'temporarily_disabled' : (($consecutive > 0 || $rateEvents > 0) ? 'degraded' : 'healthy');
        $status = $changes['status'] ?? ($current['status'] ?? 'healthy');
        if ($circuit === 'temporarily_disabled') {
            $status = 'disabled';
        }
        $stmt = $db->prepare("
            INSERT INTO ai_integration_health
                (connection_id, connector_key, status, auth_status, api_status, last_success_at, last_error_at, error_rate,
                 rate_limit_events, consecutive_failures, circuit_state, token_expires_at, metadata_json, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                auth_status = VALUES(auth_status),
                api_status = VALUES(api_status),
                last_success_at = COALESCE(VALUES(last_success_at), last_success_at),
                last_error_at = COALESCE(VALUES(last_error_at), last_error_at),
                rate_limit_events = VALUES(rate_limit_events),
                consecutive_failures = VALUES(consecutive_failures),
                circuit_state = VALUES(circuit_state),
                token_expires_at = COALESCE(VALUES(token_expires_at), token_expires_at),
                metadata_json = COALESCE(VALUES(metadata_json), metadata_json),
                updated_at = NOW()
        ");
        $stmt->execute([
            $connectionId,
            $connectorKey,
            $status,
            $changes['auth_status'] ?? ($current['auth_status'] ?? 'missing'),
            $changes['api_status'] ?? ($current['api_status'] ?? 'unknown'),
            $changes['last_success_at'] ?? null,
            $changes['last_error_at'] ?? null,
            $rateEvents,
            $consecutive,
            $circuit,
            $changes['token_expires_at'] ?? null,
            isset($changes['metadata']) ? self::encodeJson($changes['metadata']) : null,
        ]);
        $db->prepare("UPDATE ai_integration_connections SET last_health_status = ?, last_health_check_at = NOW(), last_success_at = COALESCE(?, last_success_at) WHERE id = ?")
            ->execute([$status, $changes['last_success_at'] ?? null, $connectionId]);
    }

    private static function getHealthForConnection(PDO $db, int $connectionId): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_integration_health WHERE connection_id = ? LIMIT 1");
        $stmt->execute([$connectionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    private static function recordActivity(PDO $db, array $data): void
    {
        $db->prepare("
            INSERT INTO ai_integration_activity
                (connector_key, connection_id, capability_key, actor_type, actor_id, company_id, status, duration_ms,
                 safe_input_summary, safe_output_summary, error_category, error_message, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $data['connector_key'] ?? '',
            $data['connection_id'] ?? null,
            $data['capability_key'] ?? null,
            $data['actor_type'] ?? 'user',
            $data['actor_id'] ?? null,
            $data['company_id'] ?? null,
            $data['status'] ?? 'executed',
            $data['duration_ms'] ?? null,
            $data['safe_input_summary'] ?? null,
            $data['safe_output_summary'] ?? null,
            $data['error_category'] ?? null,
            $data['error_message'] ?? null,
        ]);
    }

    private static function safeSummary($value): string
    {
        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return substr((string) $json, 0, self::ACTIVITY_SUMMARY_LIMIT);
        }
        return substr((string) $value, 0, self::ACTIVITY_SUMMARY_LIMIT);
    }

    private static function getNextCredentialVersion(PDO $db, int $connectionId): int
    {
        $stmt = $db->prepare("SELECT COALESCE(MAX(version_no), 0) + 1 FROM ai_integration_credentials WHERE connection_id = ?");
        $stmt->execute([$connectionId]);
        return (int) $stmt->fetchColumn();
    }

    private static function getSyncMapping(PDO $db, int $companyId, int $mappingId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_sync_mappings WHERE company_id = ? AND id = ? LIMIT 1");
        $stmt->execute([$companyId, $mappingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function applyMappingRules(array $rules, array $source): array
    {
        $output = [];
        foreach (($rules['fields'] ?? []) as $rule) {
            $from = (string) ($rule['source'] ?? '');
            $to = (string) ($rule['target'] ?? '');
            if ($to === '') {
                continue;
            }
            $value = $from !== '' && array_key_exists($from, $source) ? $source[$from] : ($rule['default'] ?? null);
            if (($rule['transform'] ?? '') === 'uppercase' && is_string($value)) {
                $value = strtoupper($value);
            } elseif (($rule['transform'] ?? '') === 'trim' && is_string($value)) {
                $value = trim($value);
            }
            $output[$to] = $value;
        }
        return $output;
    }

    private static function detectConflict(PDO $db, string $targetEntity, array $mapped): array
    {
        if ($targetEntity === 'vendors' && !empty($mapped['vendor_code']) && self::tableExists($db, 'vendors')) {
            $stmt = $db->prepare("SELECT id, updated_at FROM vendors WHERE vendor_code = ? LIMIT 1");
            $stmt->execute([$mapped['vendor_code']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return ['exists' => false, 'status' => 'external_newer'];
            }
            return ['exists' => true, 'status' => 'conflict'];
        }
        return ['exists' => false, 'status' => 'identical'];
    }

    private static function validateMappedRecord(string $targetEntity, array $mapped): array
    {
        $errors = [];
        if ($targetEntity === 'vendors') {
            if (empty($mapped['vendor_name'] ?? '')) {
                $errors[] = 'vendor_name is required';
            }
            if (empty($mapped['vendor_code'] ?? '')) {
                $errors[] = 'vendor_code is required';
            }
        }
        return $errors;
    }

    private static function seedPolicies(PDO $db): void
    {
        if (!self::tableExists($db, 'ai_integration_policies')) {
            return;
        }
        foreach ([1] as $companyId) {
            $defaults = [
                'allow_external_data_access' => ['Allow External Data Access', 1, ['mask_personal_data' => 1, 'mask_financial_data' => 0]],
                'allow_external_writes' => ['Allow External Writes', 0, ['require_confirmation' => 1]],
                'allow_webhooks' => ['Allow Webhooks', 1, ['https_only' => 1]],
                'allow_generic_rest_connectors' => ['Allow Generic REST Connectors', 1, ['approved_only' => 1]],
                'allow_oauth_connections' => ['Allow OAuth Connections', 1, ['csrf_required' => 1]],
                'allow_ai_connector_usage' => ['Allow AI Connector Usage', 1, ['read_only_auto_allowed' => 1]],
            ];
            $stmt = $db->prepare("
                INSERT IGNORE INTO ai_integration_policies (company_id, policy_key, policy_name, enabled, config_json, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            foreach ($defaults as $key => [$name, $enabled, $config]) {
                $stmt->execute([$companyId, $key, $name, $enabled, self::encodeJson($config)]);
            }
        }
    }

    private static function seedConnectors(PDO $db): void
    {
        if (!self::tableExists($db, 'ai_connectors')) {
            return;
        }
        $stmt = $db->prepare("
            INSERT INTO ai_connectors
                (connector_key, name, description, category, version, auth_types_json, supported_capabilities_json,
                 configuration_schema_json, status, safety_classification, supports_webhooks, supports_sync, metadata_json, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                category = VALUES(category),
                version = VALUES(version),
                auth_types_json = VALUES(auth_types_json),
                supported_capabilities_json = VALUES(supported_capabilities_json),
                configuration_schema_json = VALUES(configuration_schema_json),
                safety_classification = VALUES(safety_classification),
                supports_webhooks = VALUES(supports_webhooks),
                supports_sync = VALUES(supports_sync),
                metadata_json = VALUES(metadata_json),
                updated_at = NOW()
        ");
        foreach (self::getConnectorDefinitions() as $connector) {
            $stmt->execute([
                $connector['connector_key'],
                $connector['name'],
                $connector['description'],
                $connector['category'],
                $connector['version'],
                self::encodeJson($connector['auth_types']),
                self::encodeJson($connector['supported_capabilities']),
                self::encodeJson($connector['configuration_schema']),
                $connector['status'],
                $connector['safety_classification'],
                (int) $connector['supports_webhooks'],
                (int) $connector['supports_sync'],
                self::encodeJson($connector['metadata']),
            ]);
        }
    }

    private static function seedCapabilities(PDO $db): void
    {
        if (!self::tableExists($db, 'ai_connector_capabilities')) {
            return;
        }
        $stmt = $db->prepare("
            INSERT INTO ai_connector_capabilities
                (capability_key, connector_key, name, description, input_schema_json, output_schema_json, required_permission,
                 safety_classification, operation_type, requires_confirmation, rate_limit_per_minute, rate_limit_per_hour,
                 rate_limit_per_day, concurrent_limit, is_active, metadata_json, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                connector_key = VALUES(connector_key),
                name = VALUES(name),
                description = VALUES(description),
                input_schema_json = VALUES(input_schema_json),
                output_schema_json = VALUES(output_schema_json),
                required_permission = VALUES(required_permission),
                safety_classification = VALUES(safety_classification),
                operation_type = VALUES(operation_type),
                requires_confirmation = VALUES(requires_confirmation),
                rate_limit_per_minute = VALUES(rate_limit_per_minute),
                rate_limit_per_hour = VALUES(rate_limit_per_hour),
                rate_limit_per_day = VALUES(rate_limit_per_day),
                concurrent_limit = VALUES(concurrent_limit),
                metadata_json = VALUES(metadata_json),
                updated_at = NOW()
        ");
        foreach (self::getCapabilityDefinitions() as $capability) {
            $stmt->execute([
                $capability['capability_key'],
                $capability['connector_key'],
                $capability['name'],
                $capability['description'],
                self::encodeJson($capability['input_schema']),
                self::encodeJson($capability['output_schema']),
                $capability['required_permission'],
                $capability['safety_classification'],
                $capability['operation_type'],
                (int) $capability['requires_confirmation'],
                (int) $capability['rate_limit_per_minute'],
                (int) $capability['rate_limit_per_hour'],
                (int) $capability['rate_limit_per_day'],
                (int) $capability['concurrent_limit'],
                self::encodeJson($capability['metadata']),
            ]);
        }
    }

    private static function seedRestEndpoints(PDO $db): void
    {
        if (!self::tableExists($db, 'ai_connector_rest_endpoints')) {
            return;
        }
        $hasConnection = self::scalar($db, "SELECT COUNT(*) FROM ai_integration_connections WHERE connector_key = 'generic_rest'");
        if ($hasConnection <= 0) {
            return;
        }
        $stmtConn = $db->query("SELECT id, configuration_json FROM ai_integration_connections WHERE connector_key = 'generic_rest' ORDER BY id ASC LIMIT 1");
        $connection = $stmtConn->fetch(PDO::FETCH_ASSOC);
        if (!$connection) {
            return;
        }
        $config = self::decodeJson($connection['configuration_json'] ?? null);
        $baseUrl = rtrim((string) ($config['approved_base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            return;
        }
        $stmt = $db->prepare("
            INSERT IGNORE INTO ai_connector_rest_endpoints
                (connection_id, connector_key, capability_key, endpoint_key, name, approved_base_url, approved_path, http_method,
                 allowed_query_params_json, request_schema_json, output_schema_json, auth_strategy, safety_classification, created_at, updated_at)
            VALUES (?, 'generic_rest', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            (int) $connection['id'],
            'external.rest.read_inventory_status',
            'generic_rest.inventory_status',
            'Inventory Status',
            $baseUrl,
            '/inventory/status',
            'GET',
            self::encodeJson(['item_id', 'warehouse_id']),
            self::encodeJson(['type' => 'object', 'properties' => []]),
            self::encodeJson(['type' => 'object', 'properties' => ['records' => ['type' => 'array']]]),
            'api_key',
            'normal',
        ]);
        $stmt->execute([
            (int) $connection['id'],
            'external.rest.import_supplier_preview',
            'generic_rest.supplier_preview',
            'Supplier Import Preview',
            $baseUrl,
            '/suppliers/approved',
            'GET',
            self::encodeJson(['supplier_code', 'limit']),
            self::encodeJson(['type' => 'object', 'properties' => []]),
            self::encodeJson(['type' => 'object', 'properties' => ['preview' => ['type' => 'array']]]),
            'api_key',
            'restricted',
        ]);
    }

    private static function seedSkillTemplates(PDO $db): void
    {
        if (!self::tableExists($db, 'ai_skill_templates')) {
            return;
        }
        $stmt = $db->prepare("
            INSERT IGNORE INTO ai_skill_templates (template_id, name, description, template_type, steps_json, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([
            'integration_morning_briefing',
            'Integration Morning Briefing',
            'Reads approved external calendar data and summarizes open ERP work.',
            'workflow',
            self::encodeJson([
                ['type' => 'connector_capability', 'reference' => 'external.calendar.read_events', 'description' => 'Read approved calendar events'],
                ['type' => 'tool', 'reference' => 'procurement.search_purchase_requisitions', 'description' => 'Read open procurement work'],
                ['type' => 'analysis', 'description' => 'Summarize the day'],
            ]),
        ]);
    }

    private static function connectorExists(PDO $db, string $connectorKey): bool
    {
        return self::scalar($db, "SELECT COUNT(*) FROM ai_connectors WHERE connector_key = ?", [$connectorKey]) > 0;
    }

    private static function getConnectorDefinition(PDO $db, string $connectorKey): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_connectors WHERE connector_key = ? LIMIT 1");
        $stmt->execute([$connectorKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function audit(PDO $db, int $userId, string $action, string $entityType, int $entityId, array $details = []): void
    {
        aiAuditLog($db, $action, $entityType, $entityId, self::safeSummary($details), $userId);
        if (function_exists('logAudit')) {
            logAudit($userId, $action, 'ai');
        }
    }

    private static function tableExists(PDO $db, string $table): bool
    {
        return class_exists('AIGovernanceService') ? AIGovernanceService::tableExists($db, $table) : true;
    }

    private static function scalar(PDO $db, string $sql, array $params = []): int
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private static function decodeJson(?string $json): array
    {
        if (!$json) {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function encodeJson($value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function normalizeEnum(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
