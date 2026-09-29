<?php
/**
 * AI Automations API Endpoint.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
require_once AI_MODULE_DIR . '/tools/procurement_tools.php';
require_once AI_MODULE_DIR . '/tools/procurement_actions.php';
require_once AI_MODULE_DIR . '/services/AIAutomationService.php';
require_once AI_MODULE_DIR . '/services/AITaskService.php';

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
        $result = AIAutomationService::createAutomation(
            $db, $userId,
            $input['name'] ?? '',
            $input['description'] ?? null,
            $input['trigger_type'] ?? 'manual',
            $input['workflow'] ?? ['steps' => []]
        );
        echo json_encode($result);
        break;

    case 'list':
        $automations = AIAutomationService::getUserAutomations($db, $userId);
        echo json_encode(['success' => true, 'automations' => $automations]);
        break;

    case 'get':
        $id = (int) ($_GET['id'] ?? 0);
        $auto = AIAutomationService::getAutomationById($db, $id, $userId);
        if (!$auto) { echo json_encode(['success' => false, 'error' => 'Automation not found.']); exit; }
        echo json_encode(['success' => true, 'automation' => $auto]);
        break;

    case 'update':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($input['id'] ?? 0);
        $result = AIAutomationService::updateAutomation($db, $id, $userId, $input);
        echo json_encode($result);
        break;

    case 'delete':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($input['id'] ?? 0);
        $result = AIAutomationService::deleteAutomation($db, $id, $userId);
        echo json_encode($result);
        break;

    case 'run':
        $guard = AIGovernanceService::guardRequest($db, 'automations', $userId, (int)($_SESSION['company_id'] ?? 1), ['request_type' => 'manual_run']);
        if (!$guard['allowed']) {
            echo json_encode(['success' => false, 'error' => $guard['message']]);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($input['id'] ?? 0);
        $result = AIAutomationService::manualRun($db, $id, $userId, $userPermissions);
        echo json_encode($result);
        break;

    case 'toggle':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($input['id'] ?? 0);
        $auto = AIAutomationService::getAutomationById($db, $id, $userId);
        if (!$auto) { echo json_encode(['success' => false, 'error' => 'Not found.']); exit; }
        $result = AIAutomationService::updateAutomation($db, $id, $userId, ['is_active' => !$auto['is_active']]);
        echo json_encode($result);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        break;
}
