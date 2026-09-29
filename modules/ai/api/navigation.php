<?php
/**
 * AI Navigation API Endpoint.
 *
 * Handles navigation search, validation, and URL resolution.
 * All validation occurs server-side — never trust AI-generated URLs.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) { echo json_encode(['success' => false, 'error' => 'Not authenticated.']); exit; }
if (!hasPermission('ai.assistant.access')) { echo json_encode(['success' => false, 'error' => 'Permission denied.']); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();
$userId = $_SESSION['user_id'];

// Get user permissions for navigation filtering
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
    case 'search':
        $input = json_decode(file_get_contents('php://input'), true);
        $query = trim($input['query'] ?? $_GET['query'] ?? '');
        $currentModule = $input['current_module'] ?? $_GET['current_module'] ?? null;

        if ($query === '') {
            echo json_encode(['success' => false, 'error' => 'Query is required.']);
            exit;
        }

        $results = AINavigationService::search($query, $userPermissions, $currentModule);

        $formatted = [];
        foreach ($results as $r) {
            $dest = $r['destination'];
            $formatted[] = [
                'id'          => $dest['id'],
                'name'        => $dest['name'],
                'module'      => $dest['module'],
                'description' => $dest['description'],
                'url'         => url($dest['url']),
                'icon'        => $dest['icon'] ?? 'fas fa-arrow-right',
                'score'       => $r['score'],
            ];
        }

        echo json_encode(['success' => true, 'results' => $formatted]);
        break;

    case 'validate':
        $input = json_decode(file_get_contents('php://input'), true);
        $destinationId = trim($input['destination_id'] ?? '');

        if ($destinationId === '') {
            echo json_encode(['success' => false, 'error' => 'Destination ID is required.']);
            exit;
        }

        $dest = AINavigationService::validateDestination($destinationId, $userPermissions);

        if (!$dest) {
            echo json_encode(['success' => false, 'error' => 'Destination not found or access denied.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'destination' => [
                'id'          => $dest['id'],
                'name'        => $dest['name'],
                'module'      => $dest['module'],
                'description' => $dest['description'],
                'url'         => url($dest['url']),
                'icon'        => $dest['icon'] ?? 'fas fa-arrow-right',
            ],
        ]);
        break;

    case 'resolve':
        // Resolve a list of destination IDs to validated URLs
        $input = json_decode(file_get_contents('php://input'), true);
        $destinationIds = $input['destination_ids'] ?? [];

        if (empty($destinationIds) || !is_array($destinationIds)) {
            echo json_encode(['success' => false, 'error' => 'Destination IDs array required.']);
            exit;
        }

        $resolved = [];
        foreach ($destinationIds as $id) {
            $dest = AINavigationService::validateDestination((string) $id, $userPermissions);
            if ($dest) {
                $resolved[] = [
                    'id'          => $dest['id'],
                    'name'        => $dest['name'],
                    'module'      => $dest['module'],
                    'description' => $dest['description'],
                    'url'         => url($dest['url']),
                    'icon'        => $dest['icon'] ?? 'fas fa-arrow-right',
                ];
            }
        }

        echo json_encode(['success' => true, 'destinations' => $resolved]);
        break;

    case 'list':
        // Return all accessible navigation destinations
        $accessible = AINavigationRegistry::getAccessible($userPermissions);
        $grouped = [];

        foreach ($accessible as $dest) {
            $mod = $dest['module'];
            if (!isset($grouped[$mod])) {
                $grouped[$mod] = ['name' => ucfirst(str_replace('_', ' ', $mod)), 'destinations' => []];
            }
            $grouped[$mod]['destinations'][] = [
                'id'          => $dest['id'],
                'name'        => $dest['name'],
                'description' => $dest['description'],
                'url'         => url($dest['url']),
                'icon'        => $dest['icon'] ?? 'fas fa-arrow-right',
            ];
        }

        echo json_encode(['success' => true, 'modules' => $grouped]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        break;
}
