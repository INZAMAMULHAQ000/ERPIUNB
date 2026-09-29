<?php
/**
 * AI F22 - External Connector Tools
 */

AIToolRegistry::register([
    'id' => 'external.read_capability',
    'name' => 'external_read_capability',
    'description' => 'Execute a registered read-only external connector capability through the approved integration framework.',
    'module' => 'ai_integrations',
    'risk_level' => AI_TOOL_RISK_READ_ONLY,
    'required_permissions' => ['ai.integrations.use'],
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'capability_key' => ['type' => 'string', 'description' => 'Registered capability key such as external.rest.read_inventory_status'],
            'connection_id' => ['type' => 'integer', 'description' => 'Optional company connection to use'],
            'input' => ['type' => 'object', 'description' => 'Capability input payload'],
        ],
        'required' => ['capability_key'],
    ],
    'handler_class' => 'AIToolExternalReadCapability',
]);

class AIToolExternalReadCapability extends AIToolHandlerBase
{
    public function execute(array $params): array
    {
        if (!class_exists('AIIntegrationService')) {
            return ['success' => false, 'error' => 'Integration service is not available.'];
        }

        $companyId = isset($_SESSION['company_id']) ? (int) $_SESSION['company_id'] : 1;
        $result = AIIntegrationService::executeCapability(
            $this->db,
            $this->userId,
            $companyId,
            (string) ($params['capability_key'] ?? ''),
            is_array($params['input'] ?? null) ? $params['input'] : [],
            $this->userPermissions,
            ['connection_id' => (int) ($params['connection_id'] ?? 0)]
        );

        if (empty($result['success'])) {
            return ['success' => false, 'error' => $result['error'] ?? 'External capability execution failed.'];
        }
        if (!empty($result['requires_confirmation'])) {
            return ['success' => false, 'error' => $result['message'] ?? 'This external action requires confirmation in the Integration Center.'];
        }

        return ['success' => true, 'data' => json_encode($result['normalized'] ?? $result['data'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];
    }
}
