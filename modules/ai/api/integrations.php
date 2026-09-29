<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once AI_MODULE_DIR . '/services/AIIntegrationService.php';

while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$companyId = isset($_SESSION['company_id']) ? (int) $_SESSION['company_id'] : 1;
$action = $_GET['action'] ?? $_POST['action'] ?? 'meta';
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$input = is_array($jsonInput) ? $jsonInput : $_POST;
$userPermissions = function_exists('getUserPermissions') ? getUserPermissions($userId) : [];

AIIntegrationService::ensureDefaults($db);

if (!hasPermission('ai.integrations.view') && !hasPermission('ai.settings.manage')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied.']);
    exit;
}

switch ($action) {
    case 'meta':
        echo json_encode([
            'success' => true,
            'categories' => AIIntegrationService::getConnectorCategories(),
            'connectors' => AIIntegrationService::listConnectors($db),
            'capabilities' => AIIntegrationService::listCapabilities($db),
            'policies' => AIIntegrationService::getPolicies($db, $companyId),
            'summary' => AIIntegrationService::getDashboardSummary($db, $companyId),
        ]);
        break;

    case 'connections':
        echo json_encode(['success' => true, 'connections' => AIIntegrationService::listConnections($db, $companyId)]);
        break;

    case 'save_connection':
        if (!hasPermission('ai.integrations.manage') && !hasPermission('ai.integrations.connect')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIIntegrationService::saveConnection($db, $companyId, $userId, $input));
        break;

    case 'save_credential':
        if (!hasPermission('ai.integrations.manage') && !hasPermission('ai.integrations.connect')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIIntegrationService::saveCredential($db, (int) ($input['connection_id'] ?? 0), $companyId, $userId, $input));
        break;

    case 'revoke_connection':
        if (!hasPermission('ai.integrations.disconnect') && !hasPermission('ai.integrations.manage')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIIntegrationService::revokeConnection($db, (int) ($input['connection_id'] ?? 0), $companyId, $userId));
        break;

    case 'save_endpoint':
        if (!hasPermission('ai.integrations.manage')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIIntegrationService::saveRestEndpoint($db, (int) ($input['connection_id'] ?? 0), $companyId, $userId, $input));
        break;

    case 'webhooks':
        echo json_encode(['success' => true, 'webhooks' => AIIntegrationService::listWebhooks($db, $companyId)]);
        break;

    case 'save_webhook':
        if (!hasPermission('ai.integrations.webhooks.manage') && !hasPermission('ai.integrations.manage')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIIntegrationService::saveWebhook($db, $companyId, $userId, $input));
        break;

    case 'deliver_webhook':
        if (!hasPermission('ai.integration.webhook.send') && !hasPermission('ai.integrations.manage')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIIntegrationService::deliverWebhook(
            $db,
            $companyId,
            $userId,
            (string) ($input['webhook_key'] ?? ''),
            (string) ($input['event_name'] ?? 'workflow.completed'),
            is_array($input['payload'] ?? null) ? $input['payload'] : [],
            ['trust_level' => 'validated']
        ));
        break;

    case 'sync_mappings':
        echo json_encode(['success' => true, 'mappings' => AIIntegrationService::listSyncMappings($db, $companyId)]);
        break;

    case 'save_sync_mapping':
        if (!hasPermission('ai.integrations.sync') && !hasPermission('ai.integrations.manage')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIIntegrationService::saveSyncMapping($db, $companyId, $userId, $input));
        break;

    case 'sync_preview':
        if (!hasPermission('ai.integrations.sync') && !hasPermission('ai.integrations.manage')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIIntegrationService::createSyncPreview($db, $companyId, $userId, $input));
        break;

    case 'apply_sync':
        if (!hasPermission('ai.integrations.sync') && !hasPermission('ai.integrations.manage')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIIntegrationService::applySyncJob($db, $companyId, $userId, (int) ($input['job_id'] ?? 0), !empty($input['confirmed'])));
        break;

    case 'sync_jobs':
        echo json_encode(['success' => true, 'jobs' => AIIntegrationService::listSyncJobs($db, $companyId)]);
        break;

    case 'activity':
        echo json_encode(['success' => true, 'activity' => AIIntegrationService::getActivity($db, $companyId, 100)]);
        break;

    case 'health':
        echo json_encode(['success' => true, 'health' => AIIntegrationService::getHealth($db, $companyId)]);
        break;

    case 'update_policy':
        if (!hasPermission('ai.integrations.manage') && !hasPermission('ai.settings.manage')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIIntegrationService::updatePolicy($db, $companyId, $userId, (string) ($input['policy_key'] ?? ''), !empty($input['enabled'])));
        break;

    case 'execute_capability':
        echo json_encode(AIIntegrationService::executeCapability(
            $db,
            $userId,
            $companyId,
            (string) ($input['capability_key'] ?? ''),
            is_array($input['input'] ?? null) ? $input['input'] : [],
            $userPermissions,
            ['connection_id' => (int) ($input['connection_id'] ?? 0), 'confirmed' => !empty($input['confirmed'])]
        ));
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
        break;
}
