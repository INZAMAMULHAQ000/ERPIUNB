<?php
/**
 * AI F18 — Workflow Studio API.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
require_once AI_MODULE_DIR . '/services/AIWorkflowService.php';

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
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$input = json_decode(file_get_contents('php://input'), true) ?: [];

if (!hasPermission('ai.workflows.view') && !hasPermission('ai.assistant.access')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied.']);
    exit;
}
if (in_array($action, ['meta', 'list', 'get'], true) && !AIKillSwitchService::isEnabled($db, 'workflow_studio')) {
    echo json_encode(['success' => false, 'error' => 'Workflow Studio is disabled by kill switch.']);
    exit;
}

switch ($action) {
    case 'meta':
        echo json_encode([
            'success' => true,
            'registry' => AIWorkflowService::getNodeRegistry(),
            'templates' => AIWorkflowService::getTemplates(),
        ]);
        break;

    case 'list':
        echo json_encode([
            'success' => true,
            'workflows' => AIWorkflowService::getWorkflows($db, $userId, ['company_id' => $companyId]),
            'runs' => AIWorkflowService::getRuns($db, null, 15),
        ]);
        break;

    case 'get':
        $workflowId = (int) ($_GET['id'] ?? $input['id'] ?? 0);
        $workflow = AIWorkflowService::getWorkflow($db, $workflowId);
        echo json_encode(['success' => $workflow !== null, 'workflow' => $workflow]);
        break;

    case 'create':
        if (!hasPermission('ai.workflows.create')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        $payload = $input;
        $payload['company_id'] = $payload['company_id'] ?? $companyId;
        echo json_encode(AIWorkflowService::createWorkflow($db, $userId, $payload));
        break;

    case 'update':
        if (!hasPermission('ai.workflows.edit')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        $workflowId = (int) ($input['id'] ?? 0);
        $payload = $input;
        unset($payload['id']);
        echo json_encode(AIWorkflowService::updateWorkflow($db, $workflowId, $userId, $payload));
        break;

    case 'generate':
        if (!hasPermission('ai.workflows.create')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        if (!AIKillSwitchService::isEnabled($db, 'workflow_generation')) {
            echo json_encode(['success' => false, 'error' => 'Workflow generation is disabled by kill switch.']);
            exit;
        }
        echo json_encode(AIWorkflowService::generateDraft($db, $userId, (string) ($input['prompt'] ?? ''), [
            'company_id' => $companyId,
            'module' => $input['module'] ?? null,
        ], !isset($input['persist']) || (bool) $input['persist']));
        break;

    case 'validate':
        if (!hasPermission('ai.workflows.edit') && !hasPermission('ai.workflows.create')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        $triggerType = (string) ($input['trigger_type'] ?? 'manual');
        $triggerConfig = is_array($input['trigger_config'] ?? null) ? $input['trigger_config'] : [];
        $graph = is_array($input['graph'] ?? null) ? $input['graph'] : [];
        echo json_encode([
            'success' => true,
            'validation' => AIWorkflowService::validateGraph($db, $graph, $triggerType, $triggerConfig),
        ]);
        break;

    case 'test':
        if (!hasPermission('ai.workflows.test')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        $workflowId = (int) ($input['id'] ?? 0);
        echo json_encode(AIWorkflowService::testWorkflow($db, $workflowId, $userId, is_array($input['trigger_context'] ?? null) ? $input['trigger_context'] : []));
        break;

    case 'run':
        if (!hasPermission('ai.workflows.run')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        $workflowId = (int) ($input['id'] ?? 0);
        echo json_encode(AIWorkflowService::runManualWorkflow($db, $workflowId, $userId, is_array($input['trigger_context'] ?? null) ? $input['trigger_context'] : []));
        break;

    case 'status':
        $workflowId = (int) ($input['id'] ?? 0);
        $statusAction = (string) ($input['workflow_action'] ?? '');
        $permMap = [
            'mark_testing' => 'ai.workflows.test',
            'approve' => 'ai.workflows.approve',
            'publish' => 'ai.workflows.publish',
            'enable' => 'ai.workflows.enable',
            'disable' => 'ai.workflows.enable',
            'archive' => 'ai.workflows.edit',
        ];
        $required = $permMap[$statusAction] ?? 'ai.workflows.edit';
        if (!hasPermission($required)) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIWorkflowService::changeStatus($db, $workflowId, $userId, $statusAction));
        break;

    case 'run_get':
        $runId = (int) ($_GET['run_id'] ?? $input['run_id'] ?? 0);
        $run = AIWorkflowService::getRun($db, $runId);
        echo json_encode(['success' => $run !== null, 'run' => $run]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
        break;
}
