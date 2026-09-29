<?php
/**
 * AI Workspaces — F19
 * 
 * Shared AI Workspaces: conversations, findings, research, context, members.
 */

require_once dirname(__DIR__) . '/bootstrap.php';

$db = getDB();
AIKillSwitchService::initialize($db);

// Auth check
if (function_exists('requirePermission') && !hasPermission('ai.workspaces.view')) {
    http_response_code(403);
    die('Access denied');
}
if (!AIKillSwitchService::isEnabled($db, 'ai_workspaces')) {
    http_response_code(403);
    die('AI Workspaces are currently disabled.');
}

$companyId = $_SESSION['company_id'] ?? 1;
$userId    = $_SESSION['user_id'] ?? 0;
$tab       = $_GET['tab'] ?? 'list';
$wsId      = isset($_GET['workspace_id']) ? (int)$_GET['workspace_id'] : null;
$currentTheme = $_SESSION['theme'] ?? 'ibm-light';

// Handle API actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    switch ($action) {
        case 'create_workspace':
            $result = AIWorkspaceService::create($db, [
                'company_id'    => $companyId,
                'owner_user_id' => $userId,
                'name'          => $_POST['name'] ?? '',
                'description'   => $_POST['description'] ?? '',
                'visibility'    => $_POST['visibility'] ?? 'restricted',
            ]);
            echo json_encode($result);
            exit;

        case 'add_member':
            $result = AIWorkspaceService::addMember($db, (int)$_POST['workspace_id'], (int)$_POST['user_id'], $_POST['role'] ?? 'member', $userId);
            echo json_encode($result);
            exit;

        case 'remove_member':
            $result = AIWorkspaceService::removeMember($db, (int)$_POST['workspace_id'], (int)$_POST['user_id'], $userId);
            echo json_encode($result);
            exit;

        case 'archive_workspace':
            $result = AIWorkspaceService::archive($db, (int)$_POST['workspace_id'], $userId);
            echo json_encode($result);
            exit;

        case 'create_conversation':
            $result = AISharedConversationService::create($db, (int)$_POST['workspace_id'], $userId, $_POST['title'] ?? null);
            echo json_encode($result);
            exit;

        case 'share_conversation':
            $result = AISharedConversationService::shareToWorkspace($db, (int)$_POST['conversation_id'], (int)$_POST['workspace_id'], $userId, true);
            echo json_encode($result);
            exit;

        case 'add_message':
            $result = AISharedConversationService::addMessage($db, (int)$_POST['conversation_id'], $userId, $_POST['content'] ?? '', $_POST['type'] ?? 'user');
            echo json_encode($result);
            exit;

        case 'create_finding':
            $result = AIFindingService::create($db, [
                'workspace_id'    => (int)$_POST['workspace_id'],
                'title'           => $_POST['title'] ?? '',
                'summary'         => $_POST['summary'] ?? '',
                'confidence'      => $_POST['confidence'] ?? null,
                'created_by_user_id' => $userId,
            ]);
            echo json_encode($result);
            exit;

        case 'review_finding':
            $result = AIFindingService::updateStatus($db, (int)$_POST['finding_id'], $_POST['status'] ?? 'reviewed', $userId);
            echo json_encode($result);
            exit;

        case 'create_research':
            $result = AIResearchService::create($db, [
                'workspace_id' => (int)$_POST['workspace_id'],
                'title'        => $_POST['title'] ?? '',
                'objective'    => $_POST['objective'] ?? '',
                'created_by'   => $userId,
            ]);
            echo json_encode($result);
            exit;

        case 'add_research_note':
            $result = AIResearchService::addNote($db, (int)$_POST['session_id'], $userId, $_POST['content'] ?? '', $_POST['type'] ?? 'note');
            echo json_encode($result);
            exit;

        case 'pin_context':
            $result = AIWorkspaceContextService::pin($db, (int)$_POST['workspace_id'], $userId, $_POST['reference_type'] ?? '', (int)($_POST['reference_id'] ?? 0), $_POST['title'] ?? null, $_POST['note'] ?? null);
            echo json_encode($result);
            exit;

        case 'unpin_context':
            $result = AIWorkspaceContextService::unpin($db, (int)$_POST['pin_id']);
            echo json_encode($result);
            exit;

        case 'set_instruction':
            $result = AIWorkspaceContextService::setInstruction($db, (int)$_POST['workspace_id'], $userId, $_POST['text'] ?? '', (int)($_POST['priority'] ?? 50));
            echo json_encode($result);
            exit;

        case 'create_goal':
            $result = AIWorkspaceContextService::createGoal($db, (int)$_POST['workspace_id'], $userId, $_POST['title'] ?? '', $_POST['description'] ?? null, $_POST['success_criteria'] ?? null);
            echo json_encode($result);
            exit;

        case 'add_knowledge_ref':
            $result = AIWorkspaceContextService::addKnowledgeRef($db, (int)$_POST['workspace_id'], (int)$_POST['collection_id'], $userId);
            echo json_encode($result);
            exit;

        case 'share_agent_result':
            $result = AIWorkspaceContextService::shareAgentResult($db, (int)$_POST['workspace_id'], (int)$_POST['agent_run_id'], $userId, $_POST['summary'] ?? null);
            echo json_encode($result);
            exit;

        case 'get_workspace_full':
            $ws = AIWorkspaceService::getFull($db, (int)$_POST['workspace_id'], $companyId);
            echo json_encode(['success' => true, 'workspace' => $ws]);
            exit;

        case 'get_activity':
            $activity = AIWorkspaceActivityService::getRecent($db, (int)$_POST['workspace_id'], (int)($_POST['limit'] ?? 20));
            echo json_encode(['success' => true, 'activity' => $activity]);
            exit;

        case 'get_notifications':
            $notifs = AIWorkspaceService::getNotifications($db, (int)$_POST['workspace_id'], $userId);
            echo json_encode(['success' => true, 'notifications' => $notifs]);
            exit;

        case 'mark_notifications_read':
            AIWorkspaceService::markRead($db, (int)$_POST['workspace_id'], $userId);
            echo json_encode(['success' => true]);
            exit;

        case 'get_analytics':
            $analytics = AIWorkspaceActivityService::getAnalytics($db, $companyId);
            echo json_encode(['success' => true, 'analytics' => $analytics]);
            exit;

        case 'get_members':
            $members = AIWorkspaceService::getMembers($db, (int)$_POST['workspace_id']);
            echo json_encode(['success' => true, 'members' => $members]);
            exit;

        case 'list_conversations':
            $convs = AISharedConversationService::listByWorkspace($db, (int)$_POST['workspace_id']);
            echo json_encode(['success' => true, 'conversations' => $convs]);
            exit;

        case 'get_conversation':
            $conv = AISharedConversationService::getById($db, (int)$_POST['conversation_id']);
            echo json_encode(['success' => (bool)$conv, 'conversation' => $conv]);
            exit;

        case 'list_findings':
            $findings = AIFindingService::listByWorkspace($db, (int)$_POST['workspace_id'], $_POST['status'] ?? null);
            echo json_encode(['success' => true, 'findings' => $findings]);
            exit;

        case 'get_finding':
            $f = AIFindingService::getById($db, (int)$_POST['finding_id']);
            echo json_encode(['success' => (bool)$f, 'finding' => $f]);
            exit;

        case 'add_evidence':
            $ev = AIFindingService::addEvidence($db, (int)$_POST['finding_id'], $_POST['reference_type'] ?? '', (int)($_POST['reference_id'] ?? 0), $_POST['description'] ?? null);
            echo json_encode($ev);
            exit;

        case 'get_research':
            $s = AIResearchService::getById($db, (int)$_POST['session_id']);
            echo json_encode(['success' => (bool)$s, 'session' => $s]);
            exit;

        case 'list_research':
            $sessions = AIResearchService::listByWorkspace($db, (int)$_POST['workspace_id']);
            echo json_encode(['success' => true, 'sessions' => $sessions]);
            exit;

        case 'get_research_summary':
            $sum = AIResearchService::generateSummary($db, (int)$_POST['session_id']);
            echo json_encode($sum);
            exit;

        case 'get_pins':
            $pins = AIWorkspaceContextService::getPins($db, (int)$_POST['workspace_id']);
            echo json_encode(['success' => true, 'pins' => $pins]);
            exit;

        case 'get_instructions':
            $insts = AIWorkspaceContextService::getInstructions($db, (int)$_POST['workspace_id']);
            echo json_encode(['success' => true, 'instructions' => $insts]);
            exit;

        case 'get_goals':
            $goals = AIWorkspaceContextService::getGoals($db, (int)$_POST['workspace_id']);
            echo json_encode(['success' => true, 'goals' => $goals]);
            exit;

        case 'get_knowledge_refs':
            $krefs = AIWorkspaceContextService::getKnowledgeRefs($db, (int)$_POST['workspace_id']);
            echo json_encode(['success' => true, 'knowledge_refs' => $krefs]);
            exit;

        case 'get_shared_agent_runs':
            $runs = AIWorkspaceContextService::getSharedAgentRuns($db, (int)$_POST['workspace_id']);
            echo json_encode(['success' => true, 'agent_runs' => $runs]);
            exit;

        case 'get_workspace_full':
            $ws = AIWorkspaceService::getFull($db, (int)$_POST['workspace_id'], $companyId);
            echo json_encode(['success' => (bool)$ws, 'workspace' => $ws]);
            exit;

        case 'build_context':
            $ctx = AIWorkspaceContextService::buildContext($db, (int)$_POST['workspace_id']);
            echo json_encode(['success' => true, 'context' => $ctx]);
            exit;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            exit;
    }
}

// Load workspace data if viewing a specific workspace
$workspace = null;
$workspaces = [];
if ($wsId) {
    $workspace = AIWorkspaceService::getFull($db, $wsId, $companyId);
    if (!$workspace) {
        $tab = 'list';
        $wsId = null;
    }
}
if (!$wsId || $tab === 'list') {
    $workspaces = AIWorkspaceService::listByUser($db, $userId, $companyId);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Workspaces — Collaboration Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .status-badge { padding: 2px 10px; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
        .ws-card { transition: all 0.2s; cursor: pointer; }
        .ws-card:hover { box-shadow: 0 4px 20px rgba(99,102,241,0.15); transform: translateY(-1px); }
        .fade-in { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .chat-bubble { max-width: 75%; padding: 10px 14px; border-radius: 16px; margin-bottom: 8px; }
        .chat-user { background: #eef2ff; border-bottom-right-radius: 4px; margin-left: auto; }
        .chat-ai { background: #f0fdf4; border-bottom-left-radius: 4px; }
        .chat-system { background: #fefce8; text-align: center; font-size: 0.8rem; }
    </style>
    <?php ai_render_shell_styles(); ?>
</head>
<body class="ai-module-page min-h-screen" data-theme="<?= $currentTheme ?>">
    <?php ai_render_module_nav('ai.workspaces'); ?><?php ai_render_page_styles(); ?>
    <div class="ai-page-header">
        <div class="flex justify-between items-start flex-wrap gap-4">
            <div>
                <h1 class="ai-page-title">
                    <span class="ai-page-icon"><i class="fas fa-users"></i></span>
                    AI Workspaces
                </h1>
                <p class="ai-page-subtitle">Collaborative AI-powered team environments</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button onclick="showCreateModal()" class="bg-sky-500 hover:bg-sky-400 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                    <i class="fas fa-plus mr-1"></i> New Workspace
                </button>
            </div>
        </div>
    </div>

    <?php if ($wsId && $workspace): ?>
    <!-- Workspace Detail View -->
        <div class="ai-page-container">
        <!-- Workspace Header -->
            <div class="ai-content-card mb-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a href="?tab=list" class="text-slate-500 hover:text-slate-300"><i class="fas fa-arrow-left"></i></a>
                    <div>
                        <h2 class="text-xl font-bold text-slate-100"><?= htmlspecialchars($workspace['name']) ?></h2>
                        <p class="text-sm text-slate-400"><?= htmlspecialchars($workspace['description'] ?? '') ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="status-badge <?= $workspace['status'] === 'active' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-700/50 text-slate-400' ?>">
                        <?= ucfirst($workspace['status']) ?>
                    </span>
                    <span class="status-badge bg-sky-500/20 text-sky-400"><?= ucfirst($workspace['visibility']) ?></span>
                    <span class="text-sm text-slate-400"><i class="fas fa-users mr-1"></i><?= count($workspace['members']) ?> members</span>
                </div>
            </div>
        </div>

        <div class="ai-tabs">
            <?php
            $tabs = [
                'conversations' => 'Conversations',
                'findings' => 'Findings',
                'research' => 'Research',
                'context' => 'Context',
                'members' => 'Members',
                'activity' => 'Activity',
                'settings' => 'Settings',
            ];
            foreach ($tabs as $k => $label): ?>
                <a href="?workspace_id=<?= $wsId ?>&tab=<?= $k ?>" class="ai-tab <?= $tab === $k ? 'active' : '' ?>"><i class="fas fa-<?= match($k) { 'conversations' => 'comments', 'findings' => 'lightbulb', 'research' => 'flask', 'context' => 'thumbtack', 'members' => 'users', 'activity' => 'clock', 'settings' => 'cog', default => 'circle' } ?>"></i><?= $label ?></a>
            <?php endforeach; ?>
        </div>

        <!-- Tab Content -->
        <div class="fade-in">
        <?php if ($tab === 'conversations'): ?>
            <!-- Shared Conversations -->
            <?php $conversations = AISharedConversationService::listByWorkspace($db, $wsId); ?>
            <div class="ai-content-card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-slate-100"><i class="fas fa-comments text-sky-400 mr-2"></i>Shared Conversations</h3>
                    <button onclick="showNewConversationModal()" class="bg-sky-500/20 text-sky-400 px-3 py-1.5 rounded-lg text-sm font-semibold hover:bg-sky-500/30">
                        <i class="fas fa-plus mr-1"></i>New
                    </button>
                </div>
                <?php if (empty($conversations)): ?>
                    <p class="text-slate-500 text-sm text-center py-8">No shared conversations yet. Start one or share a private conversation.</p>
                <?php else: ?>
                    <div class="space-y-2">
                    <?php foreach ($conversations as $conv): ?>
                        <div class="flex items-center justify-between p-3 rounded-lg border border-slate-700/50 hover:bg-slate-800/50 cursor-pointer" onclick="openConversation(<?= $conv['conversation_id'] ?>)">
                            <div>
                                <div class="font-semibold text-slate-100 text-sm"><?= htmlspecialchars($conv['title'] ?? 'Untitled') ?></div>
                                <div class="text-xs text-slate-500"><?= $conv['message_count'] ?> messages · Created by <?= htmlspecialchars($conv['creator_name'] ?? 'Unknown') ?></div>
                            </div>
                            <span class="text-xs text-slate-500"><?= date('M j', strtotime($conv['updated_at'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'findings'): ?>
            <!-- AI Findings -->
            <?php $findings = AIFindingService::listByWorkspace($db, $wsId); ?>
            <div class="ai-content-card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-slate-100"><i class="fas fa-lightbulb text-amber-500 mr-2"></i>AI Findings</h3>
                    <button onclick="showNewFindingModal()" class="bg-amber-500/20 text-amber-400 px-3 py-1.5 rounded-lg text-sm font-semibold hover:bg-amber-500/30">
                        <i class="fas fa-plus mr-1"></i>New Finding
                    </button>
                </div>
                <?php if (empty($findings)): ?>
                    <p class="text-slate-500 text-sm text-center py-8">No findings yet. AI findings will appear here for team review.</p>
                <?php else: ?>
                    <div class="space-y-3">
                    <?php foreach ($findings as $f): ?>
                        <div class="p-4 rounded-lg border border-slate-700/50">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-bold text-slate-100 text-sm"><?= htmlspecialchars($f['title']) ?></h4>
                                <span class="status-badge <?= match($f['status']) { 'draft' => 'bg-amber-500/20 text-amber-400', 'accepted' => 'bg-emerald-500/20 text-emerald-400', 'dismissed' => 'bg-red-500/20 text-red-400', default => 'bg-slate-700/50 text-slate-400' } ?>"><?= ucfirst($f['status']) ?></span>
                            </div>
                            <p class="text-sm text-slate-300 mb-2"><?= htmlspecialchars($f['summary']) ?></p>
                            <div class="flex items-center gap-3 text-xs text-slate-500">
                                <span><i class="fas fa-user mr-1"></i><?= htmlspecialchars($f['creator_name'] ?? ($f['created_by_ai'] ? 'AI' : 'Unknown')) ?></span>
                                <span><i class="fas fa-clock mr-1"></i><?= date('M j, Y', strtotime($f['created_at'])) ?></span>
                                <?php if ($f['confidence']): ?>
                                    <span><i class="fas fa-chart-line mr-1"></i><?= round($f['confidence'] * 100) ?>% confidence</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($f['status'] === 'draft'): ?>
                            <div class="mt-3 flex gap-2">
                                <button onclick="reviewFinding(<?= $f['finding_id'] ?>, 'accepted')" class="bg-emerald-500/20 text-emerald-400 px-3 py-1 rounded text-xs font-semibold hover:bg-emerald-500/30">Accept</button>
                                <button onclick="reviewFinding(<?= $f['finding_id'] ?>, 'dismissed')" class="bg-red-500/20 text-red-400 px-3 py-1 rounded text-xs font-semibold hover:bg-red-500/30">Dismiss</button>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'research'): ?>
            <!-- Research Sessions -->
            <?php $sessions = AIResearchService::listByWorkspace($db, $wsId); ?>
            <div class="ai-content-card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-slate-100"><i class="fas fa-flask text-purple-600 mr-2"></i>Research Sessions</h3>
                    <button onclick="showNewResearchModal()" class="bg-purple-500/20 text-purple-400 px-3 py-1.5 rounded-lg text-sm font-semibold hover:bg-purple-500/30">
                        <i class="fas fa-plus mr-1"></i>New Session
                    </button>
                </div>
                <?php if (empty($sessions)): ?>
                    <p class="text-slate-500 text-sm text-center py-8">No research sessions yet. Create one to start a collaborative investigation.</p>
                <?php else: ?>
                    <div class="space-y-3">
                    <?php foreach ($sessions as $s): ?>
                        <div class="p-4 rounded-lg border border-slate-700/50">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-bold text-slate-100 text-sm"><?= htmlspecialchars($s['title']) ?></h4>
                                <span class="status-badge <?= $s['status'] === 'active' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-700/50 text-slate-400' ?>"><?= ucfirst($s['status']) ?></span>
                            </div>
                            <?php if ($s['objective']): ?>
                                <p class="text-sm text-slate-300 mb-2"><?= htmlspecialchars($s['objective']) ?></p>
                            <?php endif; ?>
                            <div class="text-xs text-slate-500">
                                <span><i class="fas fa-sticky-note mr-1"></i><?= $s['note_count'] ?? 0 ?> notes</span>
                                <span class="ml-3"><i class="fas fa-user mr-1"></i><?= htmlspecialchars($s['creator_name'] ?? 'Unknown') ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'context'): ?>
            <!-- Workspace Context: Pins, Instructions, Goals, Knowledge -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Pinned Context -->
                <div class="ai-content-card">
                    <h3 class="font-bold text-slate-100 text-sm mb-3"><i class="fas fa-thumbtack text-red-500 mr-2"></i>Pinned Context</h3>
                    <?php if (empty($workspace['pins'])): ?>
                        <p class="text-slate-500 text-xs">No pinned context.</p>
                    <?php else: ?>
                        <div class="space-y-2">
                        <?php foreach ($workspace['pins'] as $pin): ?>
                            <div class="flex items-center justify-between p-2 rounded bg-slate-800/40 text-xs">
                                <span><span class="font-semibold"><?= htmlspecialchars($pin['reference_type']) ?></span>: <?= htmlspecialchars($pin['title'] ?? 'ID ' . $pin['reference_id']) ?></span>
                                <button onclick="unpin(<?= $pin['pin_id'] ?>)" class="text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <button onclick="showPinModal()" class="mt-3 text-xs text-sky-400 hover:text-sky-300 font-semibold"><i class="fas fa-plus mr-1"></i>Pin Context</button>
                </div>

                <!-- Instructions -->
                <div class="ai-content-card">
                    <h3 class="font-bold text-slate-100 text-sm mb-3"><i class="fas fa-list-check text-blue-500 mr-2"></i>Workspace Instructions</h3>
                    <?php if (empty($workspace['instructions'])): ?>
                        <p class="text-slate-500 text-xs">No instructions set.</p>
                    <?php else: ?>
                        <div class="space-y-2">
                        <?php foreach ($workspace['instructions'] as $inst): ?>
                            <div class="p-2 rounded bg-blue-50 text-xs text-blue-800"><?= htmlspecialchars($inst['instruction_text']) ?></div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <button onclick="showInstructionModal()" class="mt-3 text-xs text-sky-400 hover:text-sky-300 font-semibold"><i class="fas fa-plus mr-1"></i>Add Instruction</button>
                </div>

                <!-- Goals -->
                <div class="ai-content-card">
                    <h3 class="font-bold text-slate-100 text-sm mb-3"><i class="fas fa-bullseye text-green-500 mr-2"></i>Goals</h3>
                    <?php if (empty($workspace['goals'])): ?>
                        <p class="text-slate-500 text-xs">No goals defined.</p>
                    <?php else: ?>
                        <div class="space-y-2">
                        <?php foreach ($workspace['goals'] as $goal): ?>
                            <div class="p-2 rounded bg-green-50 text-xs">
                                <span class="font-semibold text-emerald-300"><?= htmlspecialchars($goal['title']) ?></span>
                                <?php if ($goal['success_criteria']): ?>
                                    <p class="text-emerald-300 mt-1"><?= htmlspecialchars($goal['success_criteria']) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <button onclick="showGoalModal()" class="mt-3 text-xs text-sky-400 hover:text-sky-300 font-semibold"><i class="fas fa-plus mr-1"></i>Add Goal</button>
                </div>

                <!-- Knowledge References -->
                <div class="ai-content-card">
                    <h3 class="font-bold text-slate-100 text-sm mb-3"><i class="fas fa-book text-amber-500 mr-2"></i>Knowledge References</h3>
                    <?php if (empty($workspace['knowledge'])): ?>
                        <p class="text-slate-500 text-xs">No knowledge collections linked.</p>
                    <?php else: ?>
                        <div class="space-y-2">
                        <?php foreach ($workspace['knowledge'] as $k): ?>
                            <div class="p-2 rounded bg-amber-50 text-xs text-amber-800">
                                <i class="fas fa-book mr-1"></i><?= htmlspecialchars($k['collection_title'] ?? 'Collection #' . $k['knowledge_collection_id']) ?>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($tab === 'members'): ?>
            <!-- Members -->
            <div class="ai-content-card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-slate-100"><i class="fas fa-users text-sky-400 mr-2"></i>Members</h3>
                    <button onclick="showAddMemberModal()" class="bg-sky-500/20 text-sky-400 px-3 py-1.5 rounded-lg text-sm font-semibold hover:bg-sky-500/30">
                        <i class="fas fa-plus mr-1"></i>Add Member
                    </button>
                </div>
                <table class="w-full text-sm">
                    <thead class="text-xs text-slate-400 uppercase bg-slate-800/40">
                        <tr><th class="py-2 px-3 text-left">User</th><th class="py-2 px-3 text-left">Role</th><th class="py-2 px-3 text-left">Joined</th><th class="py-2 px-3 text-right">Actions</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                    <?php foreach ($workspace['members'] as $m): ?>
                        <tr>
                            <td class="py-2 px-3 font-semibold"><?= htmlspecialchars($m['username'] ?? $m['full_name'] ?? 'User #' . $m['user_id']) ?></td>
                            <td class="py-2 px-3">
                                <span class="status-badge <?= $m['role'] === 'owner' ? 'bg-purple-500/20 text-purple-400' : ($m['role'] === 'manager' ? 'bg-sky-500/20 text-sky-400' : 'bg-slate-700/50 text-slate-300') ?>"><?= ucfirst($m['role']) ?></span>
                            </td>
                            <td class="py-2 px-3 text-slate-500 text-xs"><?= date('M j, Y', strtotime($m['joined_at'])) ?></td>
                            <td class="py-2 px-3 text-right">
                                <?php if ($m['role'] !== 'owner'): ?>
                                    <button onclick="removeMember(<?= $m['user_id'] ?>)" class="text-red-400 hover:text-red-600 text-xs"><i class="fas fa-times"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($tab === 'activity'): ?>
            <!-- Activity Timeline -->
            <?php $activities = $workspace['recent_activity'] ?? []; ?>
            <div class="ai-content-card">
                <h3 class="font-bold text-slate-100 mb-4"><i class="fas fa-clock text-slate-400 mr-2"></i>Activity Timeline</h3>
                <?php if (empty($activities)): ?>
                    <p class="text-slate-500 text-sm text-center py-8">No activity yet.</p>
                <?php else: ?>
                    <div class="space-y-3">
                    <?php foreach ($activities as $act): ?>
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 bg-slate-700/50 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fas <?= match($act['action_type']) { 'workspace_created' => 'fa-plus text-green-500', 'member_added' => 'fa-user-plus text-blue-500', 'member_removed' => 'fa-user-minus text-red-500', 'conversation_shared' => 'fa-comments text-sky-400', 'finding_created' => 'fa-lightbulb text-amber-500', 'finding_reviewed' => 'fa-check text-green-500', 'context_pinned' => 'fa-thumbtack text-red-500', 'agent_result_shared' => 'fa-robot text-purple-500', default => 'fa-circle text-slate-500' } ?> text-xs"></i>
                            </div>
                            <div>
                                <div class="text-sm text-slate-100">
                                    <span class="font-semibold"><?= htmlspecialchars($act['actor_name'] ?? 'System') ?></span>
                                    <span class="text-slate-400"><?= str_replace('_', ' ', $act['action_type']) ?></span>
                                </div>
                                <div class="text-xs text-slate-500"><?= date('M j, Y g:i A', strtotime($act['created_at'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'settings'): ?>
            <!-- Workspace Settings -->
            <div class="ai-content-card">
                <h3 class="font-bold text-slate-100 mb-4"><i class="fas fa-cog text-slate-400 mr-2"></i>Workspace Settings</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Name</label>
                        <input type="text" id="ws-name" value="<?= htmlspecialchars($workspace['name']) ?>" class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Description</label>
                        <textarea id="ws-desc" class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm" rows="3"><?= htmlspecialchars($workspace['description'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Visibility</label>
                        <select id="ws-visibility" class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm">
                            <option value="private" <?= $workspace['visibility'] === 'private' ? 'selected' : '' ?>>Private</option>
                            <option value="restricted" <?= $workspace['visibility'] === 'restricted' ? 'selected' : '' ?>>Restricted</option>
                            <option value="company" <?= $workspace['visibility'] === 'company' ? 'selected' : '' ?>>Company</option>
                        </select>
                    </div>
                    <div class="pt-4 border-t border-slate-700/50">
                        <button onclick="archiveWorkspace()" class="bg-red-500/20 text-red-400 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-red-500/30">
                            <i class="fas fa-archive mr-1"></i>Archive Workspace
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <?php else: ?>
    <!-- Workspaces List View -->
        <div class="ai-page-container">
        <?php if (empty($workspaces)): ?>
            <div class="ai-content-card text-center p-12">
                <i class="fas fa-users text-4xl text-slate-500 mb-4"></i>
                <h3 class="text-lg font-bold text-slate-300 mb-2">No Workspaces Yet</h3>
                <p class="text-sm text-slate-500 mb-4">Create a workspace to start collaborating with your team and AI.</p>
                <button onclick="showCreateModal()" class="bg-sky-500 text-white px-6 py-2 rounded-lg text-sm font-semibold hover:bg-sky-400">
                    <i class="fas fa-plus mr-1"></i>Create First Workspace
                </button>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($workspaces as $ws): ?>
                <a href="?workspace_id=<?= $ws['workspace_id'] ?>&tab=conversations" class="ws-card ai-content-card block">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-sky-500/20 rounded-xl flex items-center justify-center">
                            <i class="fas fa-users text-sky-400"></i>
                        </div>
                        <span class="status-badge <?= $ws['status'] === 'active' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-700/50 text-slate-400' ?>"><?= ucfirst($ws['status']) ?></span>
                    </div>
                    <h3 class="font-bold text-slate-100 mb-1"><?= htmlspecialchars($ws['name']) ?></h3>
                    <p class="text-xs text-slate-400 mb-3 line-clamp-2"><?= htmlspecialchars($ws['description'] ?? 'No description') ?></p>
                    <div class="flex items-center justify-between text-xs text-slate-500">
                        <span><i class="fas fa-users mr-1"></i><?= $ws['member_count'] ?? 0 ?> members</span>
                        <span class="status-badge bg-sky-500/20 text-sky-400"><?= ucfirst($ws['visibility']) ?></span>
                    </div>
                    <?php if (isset($ws['my_role'])): ?>
                        <div class="mt-2 text-xs text-sky-400 font-semibold">Your role: <?= ucfirst($ws['my_role']) ?></div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Modals -->
    <!-- Create Workspace Modal -->
    <div id="createModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center">
        <div class="ai-content-card w-full max-w-md">
            <h3 class="font-bold text-slate-100 mb-4">Create Workspace</h3>
            <form id="createForm" onsubmit="return createWorkspace(event)">
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Name <span class="text-red-600">*</span></labe>
                        <input type="text" name="name" required class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm" placeholder="e.g., Procurement Operations">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Description</label>
                        <textarea name="description" class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm" rows="2" placeholder="What is this workspace for?"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Visibility</label>
                        <select name="visibility" class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm">
                            <option value="private">Private — Only you</option>
                            <option value="restricted" selected>Restricted — Invited members</option>
                            <option value="company">Company — All company users</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeModal('createModal')" class="px-4 py-2 text-sm text-slate-400 hover:text-slate-200">Cancel</button>
                    <button type="submit" class="bg-sky-500 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-sky-400">Create</button>
                </div>
            </form>
        </div>
    </div>

    <!-- New Conversation Modal -->
    <div id="newConvModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center">
        <div class="ai-content-card w-full max-w-md">
            <h3 class="font-bold text-slate-100 mb-4">New Shared Conversation</h3>
            <form onsubmit="return createConversation(event)">
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Title</label>
                        <input type="text" name="title" class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm" placeholder="e.g., Requisition Review Discussion">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeModal('newConvModal')" class="px-4 py-2 text-sm text-slate-400 hover:text-slate-200">Cancel</button>
                    <button type="submit" class="bg-sky-500 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-sky-400">Create</button>
                </div>
            </form>
        </div>
    </div>

    <!-- New Finding Modal -->
    <div id="newFindingModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center">
        <div class="ai-content-card w-full max-w-md">
            <h3 class="font-bold text-slate-100 mb-4">Create AI Finding</h3>
            <form onsubmit="return createFinding(event)">
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Title <span class="text-red-600">*</span></labe>
                        <input type="text" name="title" required class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm" placeholder="e.g., Delayed Requisitions Pattern">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Summary <span class="text-red-600">*</span></labe>
                        <textarea name="summary" required class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm" rows="3" placeholder="Describe the finding..."></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Confidence (0-1)</label>
                        <input type="number" name="confidence" step="0.01" min="0" max="1" class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm" placeholder="0.85">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeModal('newFindingModal')" class="px-4 py-2 text-sm text-slate-400 hover:text-slate-200">Cancel</button>
                    <button type="submit" class="bg-amber-500 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-amber-600">Create</button>
                </div>
            </form>
        </div>
    </div>

    <!-- New Research Modal -->
    <div id="newResearchModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center">
        <div class="ai-content-card w-full max-w-md">
            <h3 class="font-bold text-slate-100 mb-4">New Research Session</h3>
            <form onsubmit="return createResearch(event)">
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Title <span class="text-red-600">*</span></labe>
                        <input type="text" name="title" required class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm" placeholder="e.g., Evaluate Supplier Delivery Delays">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Objective</label>
                        <textarea name="objective" class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm" rows="2" placeholder="What are we investigating?"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeModal('newResearchModal')" class="px-4 py-2 text-sm text-slate-400 hover:text-slate-200">Cancel</button>
                    <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-purple-700">Create</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pin Context Modal -->
    <div id="pinModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center">
        <div class="ai-content-card w-full max-w-md">
            <h3 class="font-bold text-slate-100 mb-4">Pin Context</h3>
            <form onsubmit="return pinContext(event)">
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Reference Type</label>
                        <select name="reference_type" class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm">
                            <option value="erp_record">ERP Record</option>
                            <option value="knowledge">Knowledge</option>
                            <option value="conversation">Conversation</option>
                            <option value="task">Task</option>
                            <option value="insight">Insight</option>
                            <option value="finding">Finding</option>
                            <option value="agent_result">Agent Result</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Reference ID</label>
                        <input type="number" name="reference_id" required class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm" placeholder="ID of the item to pin">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Title</label>
                        <input type="text" name="title" class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm" placeholder="Optional title">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeModal('pinModal')" class="px-4 py-2 text-sm text-slate-400 hover:text-slate-200">Cancel</button>
                    <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-red-600">Pin</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Instruction Modal -->
    <div id="instructionModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center">
        <div class="ai-content-card w-full max-w-md">
            <h3 class="font-bold text-slate-100 mb-4">Add Workspace Instruction</h3>
            <form onsubmit="return setInstruction(event)">
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Instruction</label>
                        <textarea name="text" required class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm" rows="3" placeholder="e.g., Keep all procurement summaries concise."></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Priority (1-100)</label>
                        <input type="number" name="priority" value="50" min="1" max="100" class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeModal('instructionModal')" class="px-4 py-2 text-sm text-slate-400 hover:text-slate-200">Cancel</button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-600">Add</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Goal Modal -->
    <div id="goalModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center">
        <div class="ai-content-card w-full max-w-md">
            <h3 class="font-bold text-slate-100 mb-4">Add Workspace Goal</h3>
            <form onsubmit="return createGoal(event)">
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Goal Title <span class="text-red-600">*</span></labe>
                        <input type="text" name="title" required class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm" placeholder="e.g., Reduce overdue requisitions">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Description</label>
                        <textarea name="description" class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm" rows="2"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Success Criteria</label>
                        <textarea name="success_criteria" class="w-full border border-slate-700 rounded-lg px-3 py-2 text-sm" rows="2" placeholder="e.g., All pending PRs older than 14 days reviewed"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeModal('goalModal')" class="px-4 py-2 text-sm text-slate-400 hover:text-slate-200">Cancel</button>
                    <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-green-600">Create</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const WS_ID = <?= json_encode($wsId) ?>;
    const BASE = '/modules/ai/workspaces/index.php';

    function showCreateModal() { document.getElementById('createModal').classList.remove('hidden'); }
    function showNewConversationModal() { document.getElementById('newConvModal').classList.remove('hidden'); }
    function showNewFindingModal() { document.getElementById('newFindingModal').classList.remove('hidden'); }
    function showNewResearchModal() { document.getElementById('newResearchModal').classList.remove('hidden'); }
    function showPinModal() { document.getElementById('pinModal').classList.remove('hidden'); }
    function showInstructionModal() { document.getElementById('instructionModal').classList.remove('hidden'); }
    function showGoalModal() { document.getElementById('goalModal').classList.remove('hidden'); }
    function showAddMemberModal() { prompt('Enter user ID to add:'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    async function apiPost(action, data = {}) {
        const form = new URLSearchParams({ action, ...data });
        const resp = await fetch(BASE, { method: 'POST', body: form, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
        return resp.json();
    }

    async function createWorkspace(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('action', 'create_workspace');
        const resp = await fetch(BASE, { method: 'POST', body: fd });
        const r = await resp.json();
        if (r.success) location.href = '?workspace_id=' + r.workspace_id + '&tab=conversations';
        else alert(r.error || 'Failed');
    }

    async function createConversation(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('action', 'create_conversation');
        fd.append('workspace_id', WS_ID);
        const resp = await fetch(BASE, { method: 'POST', body: fd });
        const r = await resp.json();
        if (r.success) location.reload();
        else alert(r.error || 'Failed');
    }

    async function createFinding(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('action', 'create_finding');
        fd.append('workspace_id', WS_ID);
        const resp = await fetch(BASE, { method: 'POST', body: fd });
        const r = await resp.json();
        if (r.success) location.reload();
        else alert(r.error || 'Failed');
    }

    async function createResearch(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('action', 'create_research');
        fd.append('workspace_id', WS_ID);
        const resp = await fetch(BASE, { method: 'POST', body: fd });
        const r = await resp.json();
        if (r.success) location.reload();
        else alert(r.error || 'Failed');
    }

    async function reviewFinding(findingId, status) {
        const r = await apiPost('review_finding', { finding_id: findingId, status });
        if (r.success) location.reload();
        else alert(r.error || 'Failed');
    }

    async function pinContext(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('action', 'pin_context');
        fd.append('workspace_id', WS_ID);
        const resp = await fetch(BASE, { method: 'POST', body: fd });
        const r = await resp.json();
        if (r.success) location.reload();
        else alert(r.error || 'Failed');
    }

    async function unpin(pinId) {
        if (!confirm('Unpin this context?')) return;
        const r = await apiPost('unpin_context', { pin_id: pinId });
        if (r.success) location.reload();
    }

    async function setInstruction(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('action', 'set_instruction');
        fd.append('workspace_id', WS_ID);
        const resp = await fetch(BASE, { method: 'POST', body: fd });
        const r = await resp.json();
        if (r.success) location.reload();
        else alert(r.error || 'Failed');
    }

    async function createGoal(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('action', 'create_goal');
        fd.append('workspace_id', WS_ID);
        const resp = await fetch(BASE, { method: 'POST', body: fd });
        const r = await resp.json();
        if (r.success) location.reload();
        else alert(r.error || 'Failed');
    }

    async function archiveWorkspace() {
        if (!confirm('Archive this workspace? Members will lose access.')) return;
        const r = await apiPost('archive_workspace', { workspace_id: WS_ID });
        if (r.success) location.href = '?tab=list';
        else alert(r.error || 'Failed');
    }

    async function removeMember(userId) {
        if (!confirm('Remove this member?')) return;
        const r = await apiPost('remove_member', { workspace_id: WS_ID, user_id: userId });
        if (r.success) location.reload();
        else alert(r.error || 'Failed');
    }

    function openConversation(convId) {
        // TODO: Open conversation detail view
        alert('Conversation detail view coming soon');
    }
    </script>
    <?php ai_render_chat_launcher(); ?>
</body>
</html>




