<?php
/**
 * AI Workspaces API — F19
 */

require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

// Auth
$companyId = $_SESSION['company_id'] ?? 1;
$userId    = $_SESSION['user_id'] ?? 0;
$action    = $_POST['action'] ?? '';

if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if (!hasPermission('ai.workspaces.view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

AIKillSwitchService::initialize($db);

if (!AIKillSwitchService::isEnabled($db, 'ai_workspaces')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'AI Workspaces are currently disabled.']);
    exit;
}

switch ($action) {
    // Workspace CRUD
    case 'create_workspace':
        $result = AIWorkspaceService::create($db, [
            'company_id'    => $companyId,
            'owner_user_id' => $userId,
            'name'          => $_POST['name'] ?? '',
            'description'   => $_POST['description'] ?? '',
            'visibility'    => $_POST['visibility'] ?? 'restricted',
        ]);
        echo json_encode($result);
        break;

    case 'get_workspace':
        $ws = AIWorkspaceService::getFull($db, (int)($_POST['workspace_id'] ?? 0), $companyId);
        echo json_encode(['success' => (bool)$ws, 'workspace' => $ws]);
        break;

    case 'list_workspaces':
        $list = AIWorkspaceService::listByUser($db, $userId, $companyId);
        echo json_encode(['success' => true, 'workspaces' => $list]);
        break;

    case 'update_workspace':
        $result = AIWorkspaceService::update($db, (int)($_POST['workspace_id'] ?? 0), $_POST, $userId);
        echo json_encode($result);
        break;

    case 'archive_workspace':
        $result = AIWorkspaceService::archive($db, (int)($_POST['workspace_id'] ?? 0), $userId);
        echo json_encode($result);
        break;

    // Members
    case 'add_member':
        $result = AIWorkspaceService::addMember($db, (int)($_POST['workspace_id'] ?? 0), (int)($_POST['user_id'] ?? 0), $_POST['role'] ?? 'member', $userId);
        echo json_encode($result);
        break;

    case 'remove_member':
        $result = AIWorkspaceService::removeMember($db, (int)($_POST['workspace_id'] ?? 0), (int)($_POST['user_id'] ?? 0), $userId);
        echo json_encode($result);
        break;

    case 'change_role':
        $result = AIWorkspaceService::changeRole($db, (int)($_POST['workspace_id'] ?? 0), (int)($_POST['user_id'] ?? 0), $_POST['role'] ?? 'member', $userId);
        echo json_encode($result);
        break;

    case 'get_members':
        $members = AIWorkspaceService::getMembers($db, (int)($_POST['workspace_id'] ?? 0));
        echo json_encode(['success' => true, 'members' => $members]);
        break;

    // Conversations
    case 'create_conversation':
        if (!AIKillSwitchService::isEnabled($db, 'shared_conversations')) {
            echo json_encode(['success' => false, 'error' => 'Shared conversations are disabled.']);
            break;
        }
        $result = AISharedConversationService::create($db, (int)($_POST['workspace_id'] ?? 0), $userId, $_POST['title'] ?? null);
        echo json_encode($result);
        break;

    case 'share_conversation':
        if (!AIKillSwitchService::isEnabled($db, 'shared_conversations')) {
            echo json_encode(['success' => false, 'error' => 'Shared conversations are disabled.']);
            break;
        }
        $result = AISharedConversationService::shareToWorkspace($db, (int)($_POST['conversation_id'] ?? 0), (int)($_POST['workspace_id'] ?? 0), $userId, (bool)($_POST['confirmed'] ?? false));
        echo json_encode($result);
        break;

    case 'list_conversations':
        $convs = AISharedConversationService::listByWorkspace($db, (int)($_POST['workspace_id'] ?? 0));
        echo json_encode(['success' => true, 'conversations' => $convs]);
        break;

    case 'get_conversation':
        $conv = AISharedConversationService::getById($db, (int)($_POST['conversation_id'] ?? 0));
        echo json_encode(['success' => (bool)$conv, 'conversation' => $conv]);
        break;

    case 'add_message':
        $result = AISharedConversationService::addMessage($db, (int)($_POST['conversation_id'] ?? 0), $userId, $_POST['content'] ?? '', $_POST['type'] ?? 'user');
        echo json_encode($result);
        break;

    // Findings
    case 'create_finding':
        if (!AIKillSwitchService::isEnabled($db, 'collaborative_findings')) {
            echo json_encode(['success' => false, 'error' => 'Collaborative findings are disabled.']);
            break;
        }
        $result = AIFindingService::create($db, [
            'workspace_id'    => (int)($_POST['workspace_id'] ?? 0),
            'title'           => $_POST['title'] ?? '',
            'summary'         => $_POST['summary'] ?? '',
            'confidence'      => $_POST['confidence'] ?? null,
            'created_by_user_id' => $userId,
        ]);
        echo json_encode($result);
        break;

    case 'get_finding':
        $f = AIFindingService::getById($db, (int)($_POST['finding_id'] ?? 0));
        echo json_encode(['success' => (bool)$f, 'finding' => $f]);
        break;

    case 'list_findings':
        $findings = AIFindingService::listByWorkspace($db, (int)($_POST['workspace_id'] ?? 0), $_POST['status'] ?? null);
        echo json_encode(['success' => true, 'findings' => $findings]);
        break;

    case 'review_finding':
        if (!AIKillSwitchService::isEnabled($db, 'collaborative_findings')) {
            echo json_encode(['success' => false, 'error' => 'Collaborative findings are disabled.']);
            break;
        }
        $result = AIFindingService::updateStatus($db, (int)($_POST['finding_id'] ?? 0), $_POST['status'] ?? 'reviewed', $userId);
        echo json_encode($result);
        break;

    case 'add_evidence':
        $result = AIFindingService::addEvidence($db, (int)($_POST['finding_id'] ?? 0), $_POST['reference_type'] ?? '', (int)($_POST['reference_id'] ?? 0), $_POST['description'] ?? null);
        echo json_encode($result);
        break;

    case 'get_finding_stats':
        $stats = AIFindingService::getStats($db, (int)($_POST['workspace_id'] ?? 0));
        echo json_encode(['success' => true, 'stats' => $stats]);
        break;

    // Research
    case 'create_research':
        $result = AIResearchService::create($db, [
            'workspace_id' => (int)($_POST['workspace_id'] ?? 0),
            'title'        => $_POST['title'] ?? '',
            'objective'    => $_POST['objective'] ?? '',
            'created_by'   => $userId,
        ]);
        echo json_encode($result);
        break;

    case 'get_research':
        $s = AIResearchService::getById($db, (int)($_POST['session_id'] ?? 0));
        echo json_encode(['success' => (bool)$s, 'session' => $s]);
        break;

    case 'list_research':
        $sessions = AIResearchService::listByWorkspace($db, (int)($_POST['workspace_id'] ?? 0));
        echo json_encode(['success' => true, 'sessions' => $sessions]);
        break;

    case 'add_research_note':
        $result = AIResearchService::addNote($db, (int)($_POST['session_id'] ?? 0), $userId, $_POST['content'] ?? '', $_POST['type'] ?? 'note');
        echo json_encode($result);
        break;

    case 'get_research_summary':
        $result = AIResearchService::generateSummary($db, (int)($_POST['session_id'] ?? 0));
        echo json_encode($result);
        break;

    // Context: Pins, Instructions, Goals, Knowledge
    case 'pin_context':
        $result = AIWorkspaceContextService::pin($db, (int)($_POST['workspace_id'] ?? 0), $userId, $_POST['reference_type'] ?? '', (int)($_POST['reference_id'] ?? 0), $_POST['title'] ?? null, $_POST['note'] ?? null);
        echo json_encode($result);
        break;

    case 'unpin_context':
        $result = AIWorkspaceContextService::unpin($db, (int)($_POST['pin_id'] ?? 0));
        echo json_encode($result);
        break;

    case 'get_pins':
        $pins = AIWorkspaceContextService::getPins($db, (int)($_POST['workspace_id'] ?? 0));
        echo json_encode(['success' => true, 'pins' => $pins]);
        break;

    case 'set_instruction':
        $result = AIWorkspaceContextService::setInstruction($db, (int)($_POST['workspace_id'] ?? 0), $userId, $_POST['text'] ?? '', (int)($_POST['priority'] ?? 50));
        echo json_encode($result);
        break;

    case 'get_instructions':
        $insts = AIWorkspaceContextService::getInstructions($db, (int)($_POST['workspace_id'] ?? 0));
        echo json_encode(['success' => true, 'instructions' => $insts]);
        break;

    case 'create_goal':
        $result = AIWorkspaceContextService::createGoal($db, (int)($_POST['workspace_id'] ?? 0), $userId, $_POST['title'] ?? '', $_POST['description'] ?? null, $_POST['success_criteria'] ?? null);
        echo json_encode($result);
        break;

    case 'get_goals':
        $goals = AIWorkspaceContextService::getGoals($db, (int)($_POST['workspace_id'] ?? 0), $_POST['status'] ?? null);
        echo json_encode(['success' => true, 'goals' => $goals]);
        break;

    case 'add_knowledge_ref':
        $result = AIWorkspaceContextService::addKnowledgeRef($db, (int)($_POST['workspace_id'] ?? 0), (int)($_POST['collection_id'] ?? 0), $userId);
        echo json_encode($result);
        break;

    case 'get_knowledge_refs':
        $refs = AIWorkspaceContextService::getKnowledgeRefs($db, (int)($_POST['workspace_id'] ?? 0));
        echo json_encode(['success' => true, 'knowledge_refs' => $refs]);
        break;

    case 'build_context':
        if (!AIKillSwitchService::isEnabled($db, 'workspace_ai_context')) {
            echo json_encode(['success' => false, 'error' => 'Workspace AI context is disabled.']);
            break;
        }
        $ctx = AIWorkspaceContextService::buildContext($db, (int)($_POST['workspace_id'] ?? 0));
        echo json_encode(['success' => true, 'context' => $ctx]);
        break;

    // Agent results
    case 'share_agent_result':
        if (!AIKillSwitchService::isEnabled($db, 'shared_agent_results')) {
            echo json_encode(['success' => false, 'error' => 'Shared agent results are disabled.']);
            break;
        }
        $result = AIWorkspaceContextService::shareAgentResult($db, (int)($_POST['workspace_id'] ?? 0), (int)($_POST['agent_run_id'] ?? 0), $userId, $_POST['summary'] ?? null);
        echo json_encode($result);
        break;

    case 'get_shared_agent_runs':
        $runs = AIWorkspaceContextService::getSharedAgentRuns($db, (int)($_POST['workspace_id'] ?? 0));
        echo json_encode(['success' => true, 'agent_runs' => $runs]);
        break;

    // Activity & Notifications
    case 'get_activity':
        $activity = AIWorkspaceActivityService::getRecent($db, (int)($_POST['workspace_id'] ?? 0), (int)($_POST['limit'] ?? 20));
        echo json_encode(['success' => true, 'activity' => $activity]);
        break;

    case 'get_notifications':
        $notifs = AIWorkspaceService::getNotifications($db, (int)($_POST['workspace_id'] ?? 0), $userId);
        echo json_encode(['success' => true, 'notifications' => $notifs]);
        break;

    case 'mark_read':
        AIWorkspaceService::markRead($db, (int)($_POST['workspace_id'] ?? 0), $userId);
        echo json_encode(['success' => true]);
        break;

    case 'get_analytics':
        $analytics = AIWorkspaceActivityService::getAnalytics($db, $companyId);
        echo json_encode(['success' => true, 'analytics' => $analytics]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
}
