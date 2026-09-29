<?php
/**
 * AI F33 — Knowledge RAG API
 * 
 * API endpoints for enterprise knowledge and RAG intelligence.
 */

$aiModuleDir = dirname(__DIR__);
require_once dirname($aiModuleDir) . '/ai/bootstrap.php';
require_once AI_MODULE_DIR . '/includes/ai_text_extractor.php';
require_once AI_MODULE_DIR . '/includes/ai_embedding_provider.php';
require_once AI_MODULE_DIR . '/includes/ai_vector_store.php';
require_once AI_MODULE_DIR . '/services/AIKnowledgeService.php';
require_once AI_MODULE_DIR . '/services/AIKnowledgeVersionService.php';
require_once AI_MODULE_DIR . '/services/AIKnowledgeQAService.php';
require_once AI_MODULE_DIR . '/services/AIKnowledgeFreshnessService.php';
require_once AI_MODULE_DIR . '/services/AIKnowledgeReviewService.php';
require_once AI_MODULE_DIR . '/services/AIKnowledgeGapService.php';
require_once AI_MODULE_DIR . '/services/AIKnowledgeF33Integration.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
$companyId = $_SESSION['company_id'] ?? 0;

if (!$userId || !$companyId) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

if (function_exists('hasPermission') && !hasPermission('ai.knowledge.view', $userId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

$db = getDB();
$action = $_REQUEST['action'] ?? '';

// Helper: require specific permission for sensitive actions
function requireKnowledgePermission(string $perm): void {
    global $userId;
    if (function_exists('hasPermission') && !hasPermission($perm, $userId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied: ' . $perm]);
        exit;
    }
}

// Actions requiring elevated permissions
$adminActions = ['approve_version', 'approve_draft', 'publish_draft', 'create_version', 'resolve_review', 'create_review'];
$reviewActions = ['approve_version', 'approve_draft', 'resolve_review', 'create_review'];

if (in_array($action, $adminActions)) {
    requireKnowledgePermission('ai.knowledge.admin');
} elseif (in_array($action, $reviewActions)) {
    requireKnowledgePermission('ai.knowledge.review');
}

switch ($action) {

    // ============================================================
    // ASK & SEARCH
    // ============================================================

    case 'ask':
        $query = trim($_POST['query'] ?? '');
        if (!$query) { echo json_encode(['error' => 'Query required']); break; }

        $qaService = new AIKnowledgeQAService($db, $userId);
        $result = $qaService->askQuestion($query, [
            'query_type' => $_POST['query_type'] ?? 'ask',
            'context_type' => $_POST['context_type'] ?? null,
            'context_id' => intval($_POST['context_id'] ?? 0) ?: null,
            'collection_id' => intval($_POST['collection_id'] ?? 0) ?: null,
            'historical_date' => $_POST['historical_date'] ?? null,
        ]);
        echo json_encode($result);
        break;

    case 'search':
        $query = trim($_GET['q'] ?? $_POST['q'] ?? '');
        if (!$query) { echo json_encode(['error' => 'Query required']); break; }

        $qaService = new AIKnowledgeQAService($db, $userId);
        $result = $qaService->searchKnowledge($query, [
            'collection_id' => intval($_GET['collection_id'] ?? 0) ?: null,
            'limit' => intval($_GET['limit'] ?? 10),
        ]);
        echo json_encode($result);
        break;

    case 'feedback':
        $queryId = intval($_POST['query_id'] ?? 0);
        $rating = $_POST['rating'] ?? 'helpful';
        $type = $_POST['feedback_type'] ?? null;
        $text = $_POST['feedback_text'] ?? null;

        $qaService = new AIKnowledgeQAService($db, $userId);
        $result = $qaService->submitFeedback($queryId, $rating, $type, $text);
        echo json_encode($result);
        break;

    // ============================================================
    // VERSIONS
    // ============================================================

    case 'create_version':
        $sourceId = intval($_POST['source_id'] ?? 0);
        $content = $_POST['content'] ?? '';
        $options = [
            'effective_date' => $_POST['effective_date'] ?? null,
            'expiration_date' => $_POST['expiration_date'] ?? null,
            'change_summary' => $_POST['change_summary'] ?? null,
        ];

        $versionService = new AIKnowledgeVersionService($db, $userId);
        $result = $versionService->createVersion($sourceId, $content, $options);
        echo json_encode($result);
        break;

    case 'approve_version':
        $versionId = intval($_POST['version_id'] ?? 0);
        $versionService = new AIKnowledgeVersionService($db, $userId);
        $result = $versionService->approveVersion($versionId);
        echo json_encode($result);
        break;

    case 'get_versions':
        $sourceId = intval($_GET['source_id'] ?? 0);
        $versionService = new AIKnowledgeVersionService($db, $userId);
        $versions = $versionService->getVersionHistory($sourceId);
        echo json_encode(['versions' => $versions]);
        break;

    case 'get_effective_version':
        $sourceId = intval($_GET['source_id'] ?? 0);
        $versionService = new AIKnowledgeVersionService($db, $userId);
        $version = $versionService->getEffectiveVersion($sourceId);
        echo json_encode($version ?: ['error' => 'No effective version']);
        break;

    case 'detect_version_conflicts':
        $collectionId = intval($_GET['collection_id'] ?? 0);
        $versionService = new AIKnowledgeVersionService($db, $userId);
        $conflicts = $versionService->detectConflicts($collectionId);
        echo json_encode(['conflicts' => $conflicts]);
        break;

    // ============================================================
    // FRESHNESS & QUALITY
    // ============================================================

    case 'check_freshness':
        $freshService = new AIKnowledgeFreshnessService($db, $userId);
        $alerts = $freshService->checkFreshness();
        echo json_encode(['alerts' => $alerts, 'count' => count($alerts)]);
        break;

    case 'get_quality':
        $sourceId = intval($_GET['source_id'] ?? 0);
        $freshService = new AIKnowledgeFreshnessService($db, $userId);
        $quality = $freshService->calculateQuality($sourceId);
        echo json_encode($quality);
        break;

    case 'get_collection_freshness':
        $collectionId = intval($_GET['collection_id'] ?? 0);
        $freshService = new AIKnowledgeFreshnessService($db, $userId);
        $freshness = $freshService->getCollectionFreshness($collectionId);
        echo json_encode($freshness);
        break;

    // ============================================================
    // REVIEWS
    // ============================================================

    case 'create_review':
        $params = [
            'review_type' => $_POST['review_type'] ?? '',
            'title' => $_POST['title'] ?? '',
            'severity' => $_POST['severity'] ?? 'normal',
            'source_id' => intval($_POST['source_id'] ?? 0) ?: null,
            'collection_id' => intval($_POST['collection_id'] ?? 0) ?: null,
            'description' => $_POST['description'] ?? null,
            'assigned_to' => intval($_POST['assigned_to'] ?? 0) ?: null,
        ];

        $reviewService = new AIKnowledgeReviewService($db, $userId);
        $result = $reviewService->createReview($params);
        echo json_encode($result);
        break;

    case 'get_reviews':
        $type = $_GET['type'] ?? null;
        $assignedTo = intval($_GET['assigned_to'] ?? 0) ?: null;

        $reviewService = new AIKnowledgeReviewService($db, $userId);
        $reviews = $reviewService->getPendingReviews($type, $assignedTo);
        echo json_encode(['reviews' => $reviews]);
        break;

    case 'resolve_review':
        $reviewId = intval($_POST['review_id'] ?? 0);
        $resolution = $_POST['resolution'] ?? '';
        $notes = $_POST['notes'] ?? null;

        $reviewService = new AIKnowledgeReviewService($db, $userId);
        $result = $reviewService->resolveReview($reviewId, $resolution, $notes);
        echo json_encode($result);
        break;

    case 'dismiss_review':
        $reviewId = intval($_POST['review_id'] ?? 0);
        $reason = $_POST['reason'] ?? null;

        $reviewService = new AIKnowledgeReviewService($db, $userId);
        $result = $reviewService->dismissReview($reviewId, $reason);
        echo json_encode($result);
        break;

    case 'get_review_stats':
        $reviewService = new AIKnowledgeReviewService($db, $userId);
        $stats = $reviewService->getReviewStats();
        echo json_encode($stats);
        break;

    case 'get_lifecycle':
        $reviewService = new AIKnowledgeReviewService($db, $userId);
        $lifecycle = $reviewService->getLifecycleStatus();
        echo json_encode($lifecycle);
        break;

    // ============================================================
    // GAPS
    // ============================================================

    case 'detect_gaps':
        $gapService = new AIKnowledgeGapService($db, $userId);
        $gaps = $gapService->detectGaps();
        echo json_encode(['gaps' => $gaps, 'count' => count($gaps)]);
        break;

    case 'get_gaps':
        $status = $_GET['status'] ?? null;
        $gapService = new AIKnowledgeGapService($db, $userId);
        $gaps = $gapService->getGaps($status);
        echo json_encode(['gaps' => $gaps]);
        break;

    // ============================================================
    // AI DRAFTS
    // ============================================================

    case 'generate_draft':
        $topic = $_POST['topic'] ?? '';
        $draftType = $_POST['draft_type'] ?? 'article';
        $options = [
            'title' => $_POST['title'] ?? null,
            'collection_id' => intval($_POST['collection_id'] ?? 0) ?: null,
        ];

        $gapService = new AIKnowledgeGapService($db, $userId);
        $result = $gapService->generateDraft($topic, $draftType, $options);
        echo json_encode($result);
        break;

    case 'approve_draft':
        $draftId = intval($_POST['draft_id'] ?? 0);
        $gapService = new AIKnowledgeGapService($db, $userId);
        $result = $gapService->approveDraft($draftId);
        echo json_encode($result);
        break;

    case 'reject_draft':
        $draftId = intval($_POST['draft_id'] ?? 0);
        $reason = $_POST['reason'] ?? null;
        $gapService = new AIKnowledgeGapService($db, $userId);
        $result = $gapService->rejectDraft($draftId, $reason);
        echo json_encode($result);
        break;

    case 'publish_draft':
        $draftId = intval($_POST['draft_id'] ?? 0);
        $gapService = new AIKnowledgeGapService($db, $userId);
        $result = $gapService->publishDraft($draftId);
        echo json_encode($result);
        break;

    case 'list_drafts':
        $status = $_GET['status'] ?? null;
        $gapService = new AIKnowledgeGapService($db, $userId);
        $drafts = $gapService->listDrafts($status);
        echo json_encode(['drafts' => $drafts]);
        break;

    // ============================================================
    // INTEGRATIONS
    // ============================================================

    case 'copilot_route':
        $query = $_POST['query'] ?? '';
        $context = json_decode($_POST['context'] ?? '{}', true);

        $integration = new AIKnowledgeF33Integration($db, $userId);
        $result = $integration->copilotKnowledgeRoute($query, $context);
        echo json_encode($result);
        break;

    case 'record_knowledge':
        $recordType = $_POST['record_type'] ?? '';
        $recordId = intval($_POST['record_id'] ?? 0);
        $question = $_POST['question'] ?? '';

        $integration = new AIKnowledgeF33Integration($db, $userId);
        $result = $integration->recordKnowledge($recordType, $recordId, $question);
        echo json_encode($result);
        break;

    case 'check_process_compliance':
        $process = $_POST['process'] ?? '';
        $sopTopic = $_POST['sop_topic'] ?? '';

        $integration = new AIKnowledgeF33Integration($db, $userId);
        $result = $integration->checkProcessCompliance($process, $sopTopic);
        echo json_encode($result);
        break;

    case 'agent_lookup':
        $topic = $_POST['topic'] ?? '';
        $context = json_decode($_POST['context'] ?? '{}', true);

        $integration = new AIKnowledgeF33Integration($db, $userId);
        $result = $integration->agentKnowledgeLookup($topic, $context);
        echo json_encode($result);
        break;

    case 'page_knowledge':
        $module = $_GET['module'] ?? 'general';
        $recordId = intval($_GET['record_id'] ?? 0) ?: null;

        $integration = new AIKnowledgeF33Integration($db, $userId);
        $result = $integration->getPageKnowledge($module, $recordId);
        echo json_encode($result);
        break;

    // ============================================================
    // OBSERVABILITY
    // ============================================================

    case 'get_queries':
        $limit = min(intval($_GET['limit'] ?? 50), 200);
        $stmt = $db->prepare("
            SELECT * FROM ai_knowledge_queries WHERE company_id = ? ORDER BY created_at DESC LIMIT ?
        ");
        $stmt->execute([$companyId, $limit]);
        $queries = $stmt->fetchAll();
        echo json_encode(['queries' => $queries]);
        break;

    case 'get_events':
        $limit = min(intval($_GET['limit'] ?? 50), 200);
        $eventType = $_GET['event_type'] ?? null;

        $sql = "SELECT * FROM ai_knowledge_events WHERE company_id = ?";
        $params = [$companyId];
        if ($eventType) { $sql .= " AND event_type = ?"; $params[] = $eventType; }
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $events = $stmt->fetchAll();
        echo json_encode(['events' => $events]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . $action]);
        break;
}
