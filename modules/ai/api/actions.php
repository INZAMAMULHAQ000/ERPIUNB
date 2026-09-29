<?php
/**
 * AI Actions API Endpoint.
 *
 * Handles action proposing, confirming, cancelling, and listing.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
require_once AI_MODULE_DIR . '/includes/ai_action_base.php';
require_once AI_MODULE_DIR . '/tools/procurement_actions.php';

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) { echo json_encode(['success' => false, 'error' => 'Not authenticated.']); exit; }
if (!hasPermission('ai.assistant.access')) { echo json_encode(['success' => false, 'error' => 'Permission denied.']); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? '';
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

$executor = new AIActionExecutor($db, $userId, $userPermissions);

switch ($action) {
    case 'propose':
        $input = json_decode(file_get_contents('php://input'), true);
        $actionId = trim($input['action_id'] ?? '');
        $params = $input['params'] ?? [];
        $conversationId = (int) ($input['conversation_id'] ?? 0);

        if ($actionId === '') {
            echo json_encode(['success' => false, 'error' => 'Action ID required.']);
            exit;
        }

        $result = $executor->propose($actionId, $params, $conversationId);
        echo json_encode($result);
        break;

    case 'confirm':
        $input = json_decode(file_get_contents('php://input'), true);
        $pendingId = (int) ($input['pending_action_id'] ?? 0);
        $token = trim($input['token'] ?? '');

        if (!$pendingId || $token === '') {
            echo json_encode(['success' => false, 'error' => 'Pending action ID and token required.']);
            exit;
        }

        $result = $executor->confirm($pendingId, $token);
        echo json_encode($result);
        break;

    case 'cancel':
        $input = json_decode(file_get_contents('php://input'), true);
        $pendingId = (int) ($input['pending_action_id'] ?? 0);

        if (!$pendingId) {
            echo json_encode(['success' => false, 'error' => 'Pending action ID required.']);
            exit;
        }

        $result = $executor->cancel($pendingId);
        echo json_encode($result);
        break;

    case 'list':
        $pending = $executor->getPendingActions();
        echo json_encode(['success' => true, 'actions' => $pending]);
        break;

    case 'list_available':
        // List all available actions for the user
        $available = AIActionRegistry::getAccessible($userPermissions);
        $formatted = [];
        foreach ($available as $a) {
            $formatted[] = [
                'id'          => $a['id'],
                'name'        => $a['name'],
                'description' => $a['description'],
                'module'      => $a['module'],
                'risk_level'  => $a['risk_level'],
            ];
        }
        echo json_encode(['success' => true, 'actions' => $formatted]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        break;
}
