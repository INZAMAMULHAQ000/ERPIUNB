<?php
/**
 * AI Knowledge Base API Endpoint.
 *
 * Actions: CRUD collections, sources, search, reprocess, access control.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
require_once AI_MODULE_DIR . '/includes/ai_text_extractor.php';
require_once AI_MODULE_DIR . '/includes/ai_embedding_provider.php';
require_once AI_MODULE_DIR . '/includes/ai_vector_store.php';
require_once AI_MODULE_DIR . '/services/AIKnowledgeService.php';

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) { echo json_encode(['success' => false, 'error' => 'Not authenticated.']); exit; }
if (!hasPermission('ai.assistant.access')) { echo json_encode(['success' => false, 'error' => 'Permission denied.']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');
$db = getDB();
$userId = $_SESSION['user_id'];

switch ($action) {
    // Collections
    case 'list_collections':
        $collections = AIKnowledgeService::getCollections($db, $userId);
        echo json_encode(['success' => true, 'collections' => $collections]);
        break;

    case 'create_collection':
        $input = json_decode(file_get_contents('php://input'), true);
        $result = AIKnowledgeService::createCollection($db, $userId, $input['name'] ?? '', $input['description'] ?? null, $input['module'] ?? null, $input['visibility'] ?? 'company');
        echo json_encode($result);
        break;

    case 'update_collection':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($input['id'] ?? 0);
        $result = AIKnowledgeService::updateCollection($db, $id, $input);
        echo json_encode($result);
        break;

    // Sources
    case 'list_sources':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $collectionId = (int) ($_GET['collection_id'] ?? $input['collection_id'] ?? 0);
        $sources = AIKnowledgeService::getSourcesByCollection($db, $collectionId);
        echo json_encode(['success' => true, 'sources' => $sources]);
        break;

    case 'add_text_source':
        $input = json_decode(file_get_contents('php://input'), true);
        $result = AIKnowledgeService::createTextSource($db, $userId, (int) ($input['collection_id'] ?? 0), $input['title'] ?? '', $input['content'] ?? '');
        echo json_encode($result);
        break;

    case 'upload_file':
        if (empty($_FILES['file'])) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded.']);
            exit;
        }
        $collectionId = (int) ($_POST['collection_id'] ?? 0);
        $title = trim($_POST['title'] ?? $_FILES['file']['name']);
        $result = AIKnowledgeService::createFileSource($db, $userId, $collectionId, $title, $_FILES['file']);
        echo json_encode($result);
        break;

    case 'delete_source':
        $input = json_decode(file_get_contents('php://input'), true);
        $sourceId = (int) ($input['source_id'] ?? 0);
        $result = AIKnowledgeService::deleteSource($db, $sourceId);
        echo json_encode($result);
        break;

    // Reprocessing
    case 'reprocess_source':
        $input = json_decode(file_get_contents('php://input'), true);
        $sourceId = (int) ($input['source_id'] ?? 0);
        if (!$sourceId) {
            echo json_encode(['success' => false, 'error' => 'Source ID required.']);
            exit;
        }
        $result = AIKnowledgeService::reprocessSource($db, $userId, $sourceId);
        echo json_encode($result);
        break;

    // Access Control (for restricted collections)
    case 'grant_access':
        $input = json_decode(file_get_contents('php://input'), true);
        $collectionId = (int) ($input['collection_id'] ?? 0);
        $role = !empty($input['role']) ? trim($input['role']) : null;
        $targetUserId = !empty($input['user_id']) ? (int) $input['user_id'] : null;
        if (!$collectionId) {
            echo json_encode(['success' => false, 'error' => 'Collection ID required.']);
            exit;
        }
        $result = AIKnowledgeService::grantAccess($db, $collectionId, $role, $targetUserId);
        echo json_encode($result);
        break;

    case 'revoke_access':
        $input = json_decode(file_get_contents('php://input'), true);
        $accessId = (int) ($input['access_id'] ?? 0);
        $collectionId = (int) ($input['collection_id'] ?? 0);
        if (!$accessId || !$collectionId) {
            echo json_encode(['success' => false, 'error' => 'Access ID and Collection ID required.']);
            exit;
        }
        // Look up the access record to know what to revoke
        $accessStmt = $db->prepare("SELECT * FROM ai_knowledge_collection_access WHERE id = ? AND collection_id = ?");
        $accessStmt->execute([$accessId, $collectionId]);
        $access = $accessStmt->fetch();
        if (!$access) {
            echo json_encode(['success' => false, 'error' => 'Access record not found.']);
            exit;
        }
        $result = AIKnowledgeService::revokeAccess($db, $collectionId, $access['role'], $access['user_id']);
        echo json_encode($result);
        break;

    // Search
    case 'search':
        $input = json_decode(file_get_contents('php://input'), true);
        $query = trim($input['query'] ?? '');
        $collectionId = !empty($input['collection_id']) ? (int) $input['collection_id'] : null;
        $result = AIKnowledgeService::searchKnowledge($db, $userId, $query, $collectionId);
        echo json_encode($result);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        break;
}
