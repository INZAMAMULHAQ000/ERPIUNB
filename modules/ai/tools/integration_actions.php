<?php
/**
 * AI F22 - External Connector Action Handlers
 *
 * Routes external write/restricted connector operations through the F9
 * pending action confirmation framework.
 */

AIActionRegistry::register([
    'id'                    => 'external.connector.execute',
    'name'                  => 'External Connector Action',
    'description'           => 'Execute a registered external connector capability that requires confirmation.',
    'module'                => 'ai_integrations',
    'category'              => 'external',
    'risk_level'            => AI_ACTION_RISK_HIGH,
    'required_permissions'  => ['ai.integrations.use'],
    'requires_confirmation' => true,
    'required_fields'       => ['capability_key', 'input'],
    'input_schema'          => [
        'type'       => 'object',
        'properties' => [
            'capability_key' => ['type' => 'string', 'description' => 'Registered external capability to execute'],
            'connection_id'  => ['type' => 'integer', 'description' => 'Optional company-scoped connection override'],
            'input'          => ['type' => 'object', 'description' => 'Validated input payload for the capability'],
        ],
        'required' => ['capability_key', 'input'],
    ],
    'handler_class'         => 'AIActionExecuteExternalConnector',
]);

class AIActionExecuteExternalConnector extends AIActionHandlerBase
{
    private ?array $preparedPreview = null;
    private ?array $preparedParams = null;

    public function validateInput(array $params): array
    {
        if (!class_exists('AIIntegrationService')) {
            return ['valid' => false, 'error' => 'Integration service is not available.', 'cleaned' => []];
        }

        $capabilityKey = trim((string) ($params['capability_key'] ?? ''));
        $input = is_array($params['input'] ?? null) ? $params['input'] : [];
        $connectionId = (int) ($params['connection_id'] ?? 0);
        $companyId = isset($_SESSION['company_id']) ? (int) $_SESSION['company_id'] : 1;

        if ($capabilityKey === '') {
            return ['valid' => false, 'error' => 'Capability key is required.', 'cleaned' => []];
        }

        $capability = AIIntegrationService::getCapability($this->db, $capabilityKey);
        if (!$capability || !(int) ($capability['is_active'] ?? 0)) {
            return ['valid' => false, 'error' => 'Capability is not registered or is disabled.', 'cleaned' => []];
        }

        $operationType = (string) ($capability['operation_type'] ?? 'read');
        if (!in_array($operationType, ['write', 'destructive', 'restricted'], true)) {
            return ['valid' => false, 'error' => 'This capability does not require action confirmation.', 'cleaned' => []];
        }

        $result = AIIntegrationService::executeCapability(
            $this->db,
            $this->userId,
            $companyId,
            $capabilityKey,
            $input,
            $this->userPermissions,
            [
                'connection_id' => $connectionId,
                'actor_type' => 'action',
                'skip_action_proposal' => true,
            ]
        );

        if (empty($result['success'])) {
            return ['valid' => false, 'error' => $result['error'] ?? 'Capability validation failed.', 'cleaned' => []];
        }
        if (empty($result['requires_confirmation'])) {
            return ['valid' => false, 'error' => 'This capability no longer requires confirmation.', 'cleaned' => []];
        }

        $cleaned = [
            'capability_key' => $capabilityKey,
            'connection_id' => $connectionId,
            'input' => $input,
        ];
        $this->preparedParams = $cleaned;
        $this->preparedPreview = $result['preview'] ?? [
            'connector' => (string) ($capability['connector_key'] ?? 'external'),
            'capability' => $capabilityKey,
            'input' => '[validated]',
            'risk' => $operationType,
        ];

        return ['valid' => true, 'error' => null, 'cleaned' => $cleaned];
    }

    public function buildPreview(array $params): array
    {
        $preview = $this->preparedPreview;
        if ($preview === null && class_exists('AIIntegrationService')) {
            $companyId = isset($_SESSION['company_id']) ? (int) $_SESSION['company_id'] : 1;
            $result = AIIntegrationService::executeCapability(
                $this->db,
                $this->userId,
                $companyId,
                (string) ($params['capability_key'] ?? ''),
                is_array($params['input'] ?? null) ? $params['input'] : [],
                $this->userPermissions,
                [
                    'connection_id' => (int) ($params['connection_id'] ?? 0),
                    'actor_type' => 'action',
                    'skip_action_proposal' => true,
                ]
            );
            $preview = is_array($result['preview'] ?? null) ? $result['preview'] : [];
        }

        return [
            'title' => 'Confirm External Connector Action',
            'fields' => [
                ['label' => 'Integration capability', 'value' => (string) ($preview['capability'] ?? ($params['capability_key'] ?? ''))],
                ['label' => 'Connection', 'value' => (string) ($preview['connector'] ?? ((int) ($params['connection_id'] ?? 0) > 0 ? 'Connection #' . (int) $params['connection_id'] : 'Auto-selected active connection'))],
                ['label' => 'Input', 'value' => is_string($preview['input'] ?? null) ? $preview['input'] : json_encode($params['input'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)],
                ['label' => 'Risk level', 'value' => (string) ($preview['risk'] ?? 'restricted')],
            ],
            'warnings' => [
                'External operation will execute only after explicit confirmation.',
                'Only registered, company-scoped connector capabilities are allowed.',
            ],
        ];
    }

    public function execute(array $params): array
    {
        if (!class_exists('AIIntegrationService')) {
            return ['success' => false, 'data' => null, 'error' => 'Integration service is not available.', 'record_id' => null];
        }

        $companyId = isset($_SESSION['company_id']) ? (int) $_SESSION['company_id'] : 1;
        $result = AIIntegrationService::executeCapability(
            $this->db,
            $this->userId,
            $companyId,
            (string) ($params['capability_key'] ?? ''),
            is_array($params['input'] ?? null) ? $params['input'] : [],
            $this->userPermissions,
            [
                'connection_id' => (int) ($params['connection_id'] ?? 0),
                'confirmed' => true,
                'actor_type' => 'action',
                'skip_action_proposal' => true,
            ]
        );

        if (empty($result['success'])) {
            return ['success' => false, 'data' => null, 'error' => $result['error'] ?? 'External capability execution failed.', 'record_id' => null];
        }

        $payload = $result['normalized'] ?? $result['data'] ?? [];
        return [
            'success' => true,
            'data' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'error' => null,
            'record_id' => (int) ($params['connection_id'] ?? 0) ?: null,
        ];
    }
}
