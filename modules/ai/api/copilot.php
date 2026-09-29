<?php
/**
 * AI F29 — Copilot API Endpoints.
 *
 * Unified API for all copilot operations.
 */
ob_start();
require_once __DIR__ . '/../bootstrap.php';
$db = getDB();
$companyId = $_SESSION['company_id'] ?? 1;
$userId = $_SESSION['user_id'] ?? 1;

header('Content-Type: application/json');

function copilotRespond(array $payload): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];

    // Permission check for all copilot actions
    if (function_exists('hasPermission') && !hasPermission('ai.copilot.use')) {
        copilotRespond(['success' => false, 'error' => 'You do not have permission to use the AI Copilot.']);
    }

    // Emergency kill switch check (Test 24)
    if (class_exists('AIEmergencyControlsService')) {
        if (AIEmergencyControlsService::isControlActive($db, 'disable_ai_actions')
            || AIEmergencyControlsService::isControlActive($db, 'disable_all_ai')) {
            copilotRespond(['success' => false, 'error' => 'AI is currently disabled by emergency control.']);
        }
    }

    // Session ownership validation (Test 23: tenant isolation)
    $validateSessionOwnership = function(int $sid) use ($db, $companyId): bool {
        if ($sid <= 0) return true;
        $stmt = $db->prepare('SELECT company_id FROM ai_copilot_sessions WHERE id = ?');
        $stmt->execute([$sid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && (int)$row['company_id'] === $companyId;
    };

    switch ($action) {
        // ================================================================
        // CORE: Process a copilot message
        // ================================================================
        case 'send_message':
            $text = trim($_POST['text'] ?? '');
            if (empty($text)) {
                copilotRespond(['success' => false, 'error' => 'Message cannot be empty.']);
            }

            $context = json_decode($_POST['context'] ?? '{}', true) ?: [];
            $sessionId = (int)($_POST['session_id'] ?? 0);

            $copilot = new AICopilotService($db, $companyId, $userId, $sessionId ?: null);
            $result = $copilot->processMessage($text, $context, $sessionId ?: null);

            copilotRespond($result);

        // ================================================================
        // SESSION MANAGEMENT
        // ================================================================
        case 'create_session':
            $context = json_decode($_POST['context'] ?? '{}', true) ?: [];
            $copilot = new AICopilotService($db, $companyId, $userId);
            $result = $copilot->createSession($context);
            copilotRespond($result);

        case 'end_session':
            $sessionId = (int)($_POST['session_id'] ?? 0);
            if ($sessionId && !$validateSessionOwnership($sessionId)) {
                copilotRespond(['success' => false, 'error' => 'Session not found or access denied.']);
            }
            if ($sessionId) {
                $db->prepare("UPDATE ai_copilot_sessions SET status = 'completed', ended_at = NOW() WHERE id = ? AND company_id = ?")
                    ->execute([$sessionId, $companyId]);
            }
            copilotRespond(['success' => true]);

        // ================================================================
        // HISTORY
        // ================================================================
        case 'get_history':
            $sessionId = (int)($_POST['session_id'] ?? 0);
            if (!$validateSessionOwnership($sessionId)) {
                copilotRespond(['success' => false, 'error' => 'Session not found or access denied.']);
            }
            $copilot = new AICopilotService($db, $companyId, $userId);
            $messages = $copilot->getHistory($sessionId);
            copilotRespond(['success' => true, 'messages' => $messages]);

        case 'get_recent_sessions':
            $copilot = new AICopilotService($db, $companyId, $userId);
            $sessions = $copilot->getRecentSessions();
            copilotRespond(['success' => true, 'sessions' => $sessions]);

        case 'get_session_context':
            $sessionId = (int)($_POST['session_id'] ?? 0);
            if (!$validateSessionOwnership($sessionId)) {
                copilotRespond(['success' => false, 'error' => 'Session not found or access denied.']);
            }
            $copilot = new AICopilotService($db, $companyId, $userId);
            $context = $copilot->getSessionContext($sessionId);
            copilotRespond(['success' => true, 'context' => $context]);

        // ================================================================
        // HOME DATA (Section 16: Personal AI Home)
        // ================================================================
        case 'get_home':
            $copilot = new AICopilotService($db, $companyId, $userId);
            $home = $copilot->getHomeData();
            copilotRespond(['success' => true, 'home' => $home]);

        // ================================================================
        // CAPABILITIES & ROUTING
        // ================================================================
        case 'get_capabilities':
            $router = new AICapabilityRouter($db, $companyId, $userId);
            $router->loadCapabilities();
            $caps = $router->getAvailableCapabilities();
            copilotRespond(['success' => true, 'capabilities' => $caps]);

        case 'get_quick_commands':
            $router = new AICapabilityRouter($db, $companyId, $userId);
            $router->loadCapabilities();
            $commands = $router->getQuickCommands();
            copilotRespond(['success' => true, 'commands' => $commands]);

        case 'navigate':
            $text = trim($_POST['text'] ?? '');
            $router = new AICapabilityRouter($db, $companyId, $userId);
            $result = $router->routeNavigation($text);
            copilotRespond($result);

        // ================================================================
        // UNIVERSAL SEARCH (Section 7)
        // ================================================================
        case 'search':
            $query = trim($_POST['query'] ?? '');
            if (empty($query)) {
                copilotRespond(['success' => false, 'error' => 'Search query cannot be empty.']);
            }

            $copilot = new AICopilotService($db, $companyId, $userId);
            $results = $copilot->processMessage("Search for " . $query, ['source' => 'search']);
            copilotRespond(['success' => true, 'results' => $results]);

        // ================================================================
        // RECORD ACTIONS (Section 8)
        // ================================================================
        case 'record_action':
            $recordType = $_POST['record_type'] ?? '';
            $recordId = (int)($_POST['record_id'] ?? 0);
            $actionType = $_POST['action_type'] ?? 'explain';
            $context = [
                'record_type' => $recordType,
                'record_id' => $recordId,
                'action_type' => $actionType,
            ];

            $prompts = [
                'explain' => "Explain this {$recordType}.",
                'summarize' => "Summarize this {$recordType}.",
                'find_related' => "Find records related to this {$recordType}.",
                'compare' => "Compare this {$recordType} with similar ones.",
                'check_problems' => "Check this {$recordType} for any problems.",
                'recommend' => "Recommend the next action for this {$recordType}.",
                'create_task' => "Create a task based on this {$recordType}.",
                'draft_communication' => "Draft a communication about this {$recordType}.",
            ];

            $prompt = $prompts[$actionType] ?? "Help me with this {$recordType}.";
            $copilot = new AICopilotService($db, $companyId, $userId);
            $result = $copilot->processMessage($prompt, $context);
            copilotRespond($result);

        // ================================================================
        // FEEDBACK (Section 27)
        // ================================================================
        case 'submit_feedback':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $feedbackType = $_POST['feedback_type'] ?? '';
            $feedbackText = $_POST['feedback_text'] ?? null;
            $copilot = new AICopilotService($db, $companyId, $userId);
            $result = $copilot->submitFeedback($messageId, $feedbackType, $feedbackText);
            copilotRespond($result);

        // ================================================================
        // DO IT SEMANTICS (Section 10)
        // ================================================================
        case 'do_it':
            $sessionId = (int)($_POST['session_id'] ?? 0);
            $copilot = new AICopilotService($db, $companyId, $userId, $sessionId);

            // Get the last user message to determine context
            $history = $copilot->getSessionContext($sessionId, 3);
            $contextText = '';
            foreach ($history as $msg) {
                if ($msg['role'] === 'user') {
                    $contextText = $msg['content_text'];
                    break;
                }
            }

            if (empty($contextText)) {
                copilotRespond(['success' => false, 'error' => 'No context available for "do it" command.']);
            }

            $result = $copilot->processMessage("Handle this: " . $contextText, ['do_it' => true]);
            copilotRespond($result);

        default:
            copilotRespond(['success' => false, 'error' => 'Unknown action: ' . $action]);
    }
}

copilotRespond(['success' => false, 'error' => 'Invalid request method.']);
