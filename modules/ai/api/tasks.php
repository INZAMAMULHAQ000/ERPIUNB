<?php
/**
 * AI Tasks API Endpoint.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
require_once AI_MODULE_DIR . '/tools/procurement_tools.php';
require_once AI_MODULE_DIR . '/tools/procurement_actions.php';
require_once AI_MODULE_DIR . '/services/AITaskService.php';
require_once AI_MODULE_DIR . '/services/AITaskPlanner.php';

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) { echo json_encode(['success' => false, 'error' => 'Not authenticated.']); exit; }
if (!hasPermission('ai.assistant.access')) { echo json_encode(['success' => false, 'error' => 'Permission denied.']); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();
$userId = $_SESSION['user_id'];

$permStmt = $db->prepare("SELECT DISTINCT CONCAT(p.module, '.', p.name) as perm FROM permissions p JOIN role_permissions rp ON p.id = rp.permission_id JOIN user_roles ur ON rp.role_id = ur.role_id WHERE ur.user_id = ?");
$permStmt->execute([$userId]);
$userPermissions = array_column($permStmt->fetchAll(), 'perm');

switch ($action) {
    case 'create':
        $input = json_decode(file_get_contents('php://input'), true);
        $result = AITaskService::createTask($db, $userId, $input['title'] ?? '', $input['description'] ?? null, $input['original_request'] ?? null, $input['conversation_id'] ?? null);
        echo json_encode($result);
        break;

    case 'list':
        $status = $_GET['status'] ?? null;
        $tasks = AITaskService::getUserTasks($db, $userId, $status);
        echo json_encode(['success' => true, 'tasks' => $tasks]);
        break;

    case 'get':
        $taskId = (int) ($_GET['task_id'] ?? 0);
        $task = AITaskService::getTaskById($db, $taskId, $userId);
        if (!$task) { echo json_encode(['success' => false, 'error' => 'Task not found.']); exit; }
        $steps = AITaskService::getTaskSteps($db, $taskId);
        echo json_encode(['success' => true, 'task' => $task, 'steps' => $steps]);
        break;

    case 'plan':
        $input = json_decode(file_get_contents('php://input'), true);
        $taskId = (int) ($input['task_id'] ?? 0);
        $plan = $input['plan'] ?? null;

        if (!$plan) {
            // Auto-generate plan from objective
            $objective = $input['objective'] ?? '';
            $toolDefs = AIToolRegistry::getAccessible($userPermissions);
            $actionDefs = AIActionRegistry::getAccessible($userPermissions);
            $planResult = AITaskPlanner::buildPlan($objective, $toolDefs, $actionDefs);
            if (!$planResult['success']) { echo json_encode($planResult); exit; }
            $plan = $planResult['plan'];
        }

        $result = AITaskService::setPlan($db, $taskId, $userId, $plan);
        echo json_encode($result);
        break;

    case 'start':
        $input = json_decode(file_get_contents('php://input'), true);
        $taskId = (int) ($input['task_id'] ?? 0);
        $result = AITaskService::startTask($db, $taskId, $userId, $userPermissions);
        echo json_encode($result);
        break;

    case 'confirm_step':
        $input = json_decode(file_get_contents('php://input'), true);
        $taskId = (int) ($input['task_id'] ?? 0);
        $pendingActionId = (int) ($input['pending_action_id'] ?? 0);
        $token = trim($input['token'] ?? '');
        $result = AITaskService::confirmStep($db, $taskId, $userId, $pendingActionId, $token, $userPermissions);
        echo json_encode($result);
        break;

    case 'cancel':
        $input = json_decode(file_get_contents('php://input'), true);
        $taskId = (int) ($input['task_id'] ?? 0);
        $result = AITaskService::cancelTask($db, $taskId, $userId);
        echo json_encode($result);
        break;

    case 'build_plan':
        $input = json_decode(file_get_contents('php://input'), true);
        $objective = trim($input['objective'] ?? '');
        $toolDefs = AIToolRegistry::getAccessible($userPermissions);
        $actionDefs = AIActionRegistry::getAccessible($userPermissions);
        $result = AITaskPlanner::buildPlan($objective, $toolDefs, $actionDefs);
        echo json_encode($result);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        break;
}
