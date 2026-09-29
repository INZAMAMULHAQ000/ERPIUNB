<?php
/**
 * AI Conversations API.
 *
 * Handles conversation CRUD operations.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) { echo json_encode(['success' => false, 'error' => 'Not authenticated.']); exit; }
if (!hasPermission('ai.assistant.access')) { echo json_encode(['success' => false, 'error' => 'Permission denied.']); exit; }

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!empty($token) && !verifyCsrf($token)) { echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']); exit; }

$db = getDB();
$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// GET: List conversations or get single conversation
if ($method === 'GET' && $action === 'list') {
    $search = $_GET['search'] ?? null;
    $conversations = AIConversationService::getUserConversations($db, $userId, $search);
    echo json_encode(['success' => true, 'conversations' => $conversations]);
    exit;
}

if ($method === 'GET' && $action === 'messages') {
    $convId = (int) ($_GET['conversation_id'] ?? 0);
    if (!$convId) { echo json_encode(['success' => false, 'error' => 'Invalid conversation ID.']); exit; }

    $conv = AIConversationService::getConversationById($db, $convId, $userId);
    if (!$conv) { echo json_encode(['success' => false, 'error' => 'Conversation not found.']); exit; }

    $messages = AIConversationService::getConversationMessages($db, $convId);
    echo json_encode(['success' => true, 'conversation' => $conv, 'messages' => $messages]);
    exit;
}

// POST actions
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

// Create conversation
if ($action === 'create') {
    $providerId = (int) ($input['provider_id'] ?? 0);
    $modelId = (int) ($input['model_id'] ?? 0);
    $title = trim($input['title'] ?? 'New Conversation');

    if (!$providerId || !$modelId) { echo json_encode(['success' => false, 'error' => 'Provider and model required.']); exit; }

    $result = AIConversationService::createConversation($db, $userId, $providerId, $modelId, $title);
    echo json_encode($result);
    exit;
}

// Rename
if ($action === 'rename') {
    $convId = (int) ($input['conversation_id'] ?? 0);
    $title = trim($input['title'] ?? '');
    if (!$convId || $title === '') { echo json_encode(['success' => false, 'error' => 'Invalid input.']); exit; }

    $result = AIConversationService::updateConversationTitle($db, $convId, $userId, $title);
    echo json_encode($result);
    exit;
}

// Archive
if ($action === 'archive') {
    $convId = (int) ($input['conversation_id'] ?? 0);
    if (!$convId) { echo json_encode(['success' => false, 'error' => 'Invalid conversation ID.']); exit; }

    $result = AIConversationService::archiveConversation($db, $convId, $userId);
    echo json_encode($result);
    exit;
}

// Delete
if ($action === 'delete') {
    $convId = (int) ($input['conversation_id'] ?? 0);
    if (!$convId) { echo json_encode(['success' => false, 'error' => 'Invalid conversation ID.']); exit; }

    $result = AIConversationService::deleteConversation($db, $convId, $userId);
    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action.']);
