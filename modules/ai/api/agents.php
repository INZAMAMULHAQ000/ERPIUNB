<?php
/**
 * AI F12 — Agent API Endpoint.
 *
 * Handles agent operations: start, execute, cancel, list, details.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
require_once AI_MODULE_DIR . '/includes/ai_agent_base.php';
require_once AI_MODULE_DIR . '/includes/ai_agents_def.php';
require_once AI_MODULE_DIR . '/services/AIAgentService.php';

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) { echo json_encode(['success' => false, 'error' => 'Not authenticated.']); exit; }
if (!hasPermission('ai.assistant.access')) { echo json_encode(['success' => false, 'error' => 'Permission denied.']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');
$db = getDB();
$userId = $_SESSION['user_id'];

// Get user permissions
$permStmt = $db->prepare("
    SELECT DISTINCT CONCAT(p.module, '.', p.name) as perm
    FROM permissions p
    JOIN role_permissions rp ON p.id = rp.permission_id
    JOIN user_roles ur ON rp.role_id = ur.role_id
    WHERE ur.user_id = ?
");
$permStmt->execute([$userId]);
$userPermissions = array_column($permStmt->fetchAll(), 'perm');

switch ($action) {
    case 'list_agents':
        $agents = AIAgentService::getAvailableAgents($userPermissions);
        // Format for display
        $formatted = array_map(fn($a) => [
            'agent_id'     => $a['agent_id'],
            'name'         => $a['name'],
            'description'  => $a['description'],
            'module'       => $a['module'],
            'agent_type'   => $a['agent_type'],
            'risk_level'   => $a['risk_level'],
            'max_steps'    => $a['max_steps'],
            'allowed_tools' => $a['allowed_tools'],
            'allowed_actions' => $a['allowed_actions'],
        ], $agents);
        echo json_encode(['success' => true, 'agents' => $formatted]);
        break;

    case 'start':
        $guard = AIGovernanceService::guardRequest($db, 'agents', $userId, (int)($_SESSION['company_id'] ?? 1), ['request_type' => 'agent_start']);
        if (!$guard['allowed']) {
            echo json_encode(['success' => false, 'error' => $guard['message']]);
            exit;
        }

        $agentId = trim($input['agent_id'] ?? '');
        $objective = trim($input['objective'] ?? '');
        $conversationId = !empty($input['conversation_id']) ? (int) $input['conversation_id'] : null;

        $result = AIAgentService::startAgent($db, $userId, $userPermissions, $agentId, $objective, $conversationId);
        echo json_encode($result);
        break;

    case 'execute_step':
        $runId = (int) ($input['run_id'] ?? 0);
        if (!$runId) { echo json_encode(['success' => false, 'error' => 'Run ID required.']); exit; }
        $result = AIAgentService::executeNextStep($db, $userId, $userPermissions, $runId);
        echo json_encode($result);
        break;

    case 'execute_auto':
        $runId = (int) ($input['run_id'] ?? 0);
        $maxIter = (int) ($input['max_iterations'] ?? 10);
        if (!$runId) { echo json_encode(['success' => false, 'error' => 'Run ID required.']); exit; }
        $result = AIAgentService::executeAutonomously($db, $userId, $userPermissions, $runId, $maxIter);
        echo json_encode($result);
        break;

    case 'cancel':
        $runId = (int) ($input['run_id'] ?? 0);
        if (!$runId) { echo json_encode(['success' => false, 'error' => 'Run ID required.']); exit; }
        $result = AIAgentService::cancelRun($db, $userId, $runId);
        echo json_encode($result);
        break;

    case 'details':
        $runId = (int) ($_GET['run_id'] ?? $input['run_id'] ?? 0);
        if (!$runId) { echo json_encode(['success' => false, 'error' => 'Run ID required.']); exit; }
        $details = AIAgentService::getRunDetails($db, $userId, $runId);
        if (!$details) { echo json_encode(['success' => false, 'error' => 'Run not found.']); exit; }
        echo json_encode(['success' => true, 'run' => $details]);
        break;

    case 'list_runs':
        $status = !empty($input['status']) ? $input['status'] : null;
        $limit = (int) ($input['limit'] ?? 20);
        $runs = AIAgentService::getUserRuns($db, $userId, $status, $limit);
        echo json_encode(['success' => true, 'runs' => $runs]);
        break;

    // ═══ F25: Agent Runtime Endpoints ═══

    case 'create_task':
        $agentDefId = (int) ($input['agent_id'] ?? 0);
        $request = trim($input['request'] ?? '');
        $source = $input['source'] ?? 'chat';
        $priority = $input['priority'] ?? 'medium';
        $conversationId = !empty($input['conversation_id']) ? (int) $input['conversation_id'] : null;
        $companyId = (int) ($_SESSION['company_id'] ?? 1);
        $result = AIAgentRuntimeService::createTask($db, $userId, $companyId, $userPermissions, $agentDefId, $request, $source, null, $conversationId, $priority);
        echo json_encode($result);
        break;

    case 'generate_plan':
        $taskId = (int) ($input['task_id'] ?? 0);
        $planSteps = $input['plan_steps'] ?? [];
        $result = AIAgentRuntimeService::generatePlan($db, $taskId, $userId, $planSteps);
        echo json_encode($result);
        break;

    case 'preview_plan':
        $planId = (int) ($input['plan_id'] ?? $_GET['plan_id'] ?? 0);
        $result = AIAgentPlanService::generatePreview($db, $planId);
        echo json_encode($result);
        break;

    case 'dry_run_plan':
        $planId = (int) ($input['plan_id'] ?? 0);
        $result = AIAgentPlanService::executeDryRun($db, $planId, $userId);
        echo json_encode($result);
        break;

    case 'execute_task':
        $taskId = (int) ($input['task_id'] ?? 0);
        $result = AIAgentRuntimeService::executeTask($db, $taskId, $userId, $userPermissions);
        echo json_encode($result);
        break;

    case 'pause_run':
        $runId = (int) ($input['run_id'] ?? 0);
        $reason = $input['reason'] ?? '';
        $result = AIAgentRuntimeService::pauseRun($db, $runId, $userId, $reason);
        echo json_encode($result);
        break;

    case 'resume_run':
        $runId = (int) ($input['run_id'] ?? 0);
        $result = AIAgentRuntimeService::resumeRun($db, $runId, $userId);
        echo json_encode($result);
        break;

    case 'cancel_task':
        $taskId = (int) ($input['task_id'] ?? 0);
        $reason = $input['reason'] ?? '';
        $result = AIAgentRuntimeService::cancelTask($db, $taskId, $userId, $reason);
        echo json_encode($result);
        break;

    case 'task_details':
        $taskId = (int) ($input['task_id'] ?? $_GET['task_id'] ?? 0);
        $task = AIAgentRuntimeService::getTask($db, $taskId);
        if (!$task || $task['user_id'] != $userId) {
            echo json_encode(['success' => false, 'error' => 'Task not found']); exit;
        }
        $task['steps'] = AIAgentRuntimeService::getTaskSteps($db, $task['id']);
        $task['events'] = AIAgentRuntimeService::getTaskEvents($db, $task['id']);
        $task['approvals'] = AIAgentApprovalService::getTaskApprovals($db, $task['id']);
        $task['artifacts'] = AIAgentArtifactService::getTaskArtifacts($db, $task['id']);
        $task['checkpoints'] = AIAgentCheckpointService::getTaskCheckpoints($db, $task['id']);
        echo json_encode(['success' => true, 'task' => $task]);
        break;

    case 'list_tasks':
        $status = !empty($input['status']) ? $input['status'] : null;
        $limit = (int) ($input['limit'] ?? 20);
        $tasks = AIAgentRuntimeService::getUserTasks($db, $userId, $status, $limit);
        echo json_encode(['success' => true, 'tasks' => $tasks]);
        break;

    case 'pending_approvals':
        $companyId = (int) ($_SESSION['company_id'] ?? 1);
        $approvals = AIAgentApprovalService::getPendingApprovals($db, $userId, $companyId);
        echo json_encode(['success' => true, 'approvals' => $approvals]);
        break;

    case 'approve_action':
        $approvalId = (int) ($input['approval_id'] ?? 0);
        $decision = $input['decision'] ?? '';
        $result = AIAgentApprovalService::approve($db, $approvalId, $userId, $decision);
        echo json_encode($result);
        break;

    case 'reject_action':
        $approvalId = (int) ($input['approval_id'] ?? 0);
        $reason = $input['reason'] ?? '';
        $result = AIAgentApprovalService::reject($db, $approvalId, $userId, $reason);
        echo json_encode($result);
        break;

    case 'delegate_action':
        $approvalId = (int) ($input['approval_id'] ?? 0);
        $delegatedTo = (int) ($input['delegated_to'] ?? 0);
        $result = AIAgentApprovalService::delegate($db, $approvalId, $delegatedTo, $userId);
        echo json_encode($result);
        break;

    case 'task_artifacts':
        $taskId = (int) ($input['task_id'] ?? $_GET['task_id'] ?? 0);
        $artifacts = AIAgentArtifactService::getTaskArtifacts($db, $taskId);
        echo json_encode(['success' => true, 'artifacts' => $artifacts]);
        break;

    case 'my_artifacts':
        $artifacts = AIAgentArtifactService::getUserArtifacts($db, $userId);
        echo json_encode(['success' => true, 'artifacts' => $artifacts]);
        break;

    case 'list_agent_definitions':
        $stmt = $db->query("SELECT * FROM ai_agent_definitions ORDER BY name ASC");
        echo json_encode(['success' => true, 'agents' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'create_agent_definition':
        if (!hasPermission('ai.management.admin')) {
            echo json_encode(['success' => false, 'error' => 'Admin permission required']); exit;
        }
        $result = self::createAgentDefinition($db, $input, $userId);
        echo json_encode($result);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        break;
}
