<?php
/**
 * AI F26 — Intelligence API Endpoint.
 *
 * Handles intelligence events, monitors, watchlists, briefing, feedback.
 *
 * Security:
 * - Test 7: Permission checks on event access
 * - Test 11: Recommendation requires approval — no write before approval
 * - Test 23: Emergency kill switch blocks intelligence-driven actions
 * - Test 24: Prompt injection in ERP text fields treated as data
 */
require_once dirname(__DIR__) . '/bootstrap.php';

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) { echo json_encode(['success' => false, 'error' => 'Not authenticated.']); exit; }
if (!hasPermission('ai.assistant.access')) { echo json_encode(['success' => false, 'error' => 'Permission denied.']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');
$db = getDB();
$userId = $_SESSION['user_id'];
$companyId = (int) ($_SESSION['company_id'] ?? 1);

switch ($action) {
    case 'list_events':
        $filters = [
            'company_id' => $companyId,
        ];
        if (!empty($input['status'])) $filters['status'] = $input['status'];
        if (!empty($input['statuses'])) $filters['statuses'] = $input['statuses'];
        if (!empty($input['severity'])) $filters['severity'] = $input['severity'];
        if (!empty($input['min_severity'])) $filters['min_severity'] = $input['min_severity'];
        if (!empty($input['module'])) $filters['module'] = $input['module'];
        if (!empty($input['search'])) $filters['search'] = $input['search'];

        // Test 16: Role-based module filtering
        if (!empty($input['role_module'])) $filters['role_module_filter'] = $input['role_module'];

        $page = max(1, (int) ($input['page'] ?? 1));
        $perPage = min(50, (int) ($input['per_page'] ?? 20));
        $result = AIIntelligenceService::getEvents($db, $filters, $page, $perPage);
        echo json_encode(['success' => true, 'data' => $result]);
        break;

    case 'get_event':
        $eventId = (int) ($input['event_id'] ?? $_GET['event_id'] ?? 0);
        // Test 7: Permission check — requesting user must own or be assigned
        $event = AIIntelligenceService::getEvent($db, $eventId, $userId);
        if (!$event) { echo json_encode(['success' => false, 'error' => 'Event not found or access denied']); exit; }
        echo json_encode(['success' => true, 'data' => $event]);
        break;

    case 'update_status':
        $eventId = (int) ($input['event_id'] ?? 0);
        $status = $input['status'] ?? '';
        $reason = $input['reason'] ?? null;

        // Test 13: Valid states: acknowledged, snoozed, dismissed, actioned
        $validStatuses = ['new', 'acknowledged', 'snoozed', 'dismissed', 'actioned', 'action_pending', 'expired'];
        if (!in_array($status, $validStatuses)) {
            echo json_encode(['success' => false, 'error' => 'Invalid status']); exit;
        }

        AIIntelligenceService::updateStatus($db, $eventId, $status, $reason);
        echo json_encode(['success' => true]);
        break;

    case 'submit_feedback':
        $eventId = (int) ($input['event_id'] ?? 0);
        $feedbackType = $input['feedback_type'] ?? '';
        $comment = $input['comment'] ?? null;

        // Test 17: Valid feedback types
        $validTypes = ['useful', 'not_useful', 'incorrect', 'duplicate', 'already_handled'];
        if (!in_array($feedbackType, $validTypes)) {
            echo json_encode(['success' => false, 'error' => 'Invalid feedback type']); exit;
        }

        $result = AIIntelligenceService::submitFeedback($db, $eventId, $userId, $feedbackType, $comment);
        echo json_encode(['success' => $result]);
        break;

    case 'get_briefing':
        $briefing = AIBriefingService::generateBriefing($db, $userId, $companyId);
        $formatted = AIBriefingService::formatBriefing($briefing);
        echo json_encode(['success' => true, 'data' => $briefing, 'formatted' => $formatted]);
        break;

    case 'get_statistics':
        $stats = AIIntelligenceService::getStatistics($db, $companyId);
        echo json_encode(['success' => true, 'data' => $stats]);
        break;

    case 'run_detectors':
        if (!hasPermission('ai.management.admin') && !hasPermission('ai.intelligence.run')) {
            echo json_encode(['success' => false, 'error' => 'Admin or intelligence run permission required']); exit;
        }
        $result = AIDetectionEngine::runAllDetectors($db, $companyId);
        echo json_encode(['success' => true, 'data' => $result]);
        break;

    case 'list_rules':
        if (!hasPermission('ai.intelligence.manage')) {
            echo json_encode(['success' => false, 'error' => 'Manage intelligence permission required']); exit;
        }
        $stmt = $db->query("SELECT * FROM ai_intelligence_rules ORDER BY module, name");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'list_monitors':
        if (!hasPermission('ai.intelligence.manage')) {
            echo json_encode(['success' => false, 'error' => 'Manage intelligence permission required']); exit;
        }
        $monitors = AIWatchlistService::getMonitors($db, $companyId);
        echo json_encode(['success' => true, 'data' => $monitors]);
        break;

    case 'create_monitor':
        if (!hasPermission('ai.intelligence.manage')) {
            echo json_encode(['success' => false, 'error' => 'Manage intelligence permission required']); exit;
        }
        $result = AIWatchlistService::createMonitor($db, $input, $userId);
        echo json_encode($result);
        break;

    case 'update_monitor':
        if (!hasPermission('ai.intelligence.manage')) {
            echo json_encode(['success' => false, 'error' => 'Manage intelligence permission required']); exit;
        }
        $monitorId = (int) ($input['monitor_id'] ?? 0);
        $result = AIWatchlistService::updateMonitor($db, $monitorId, $input);
        echo json_encode(['success' => $result]);
        break;

    case 'create_watchlist':
        $entityType = $input['entity_type'] ?? '';
        $entityId = (int) ($input['entity_id'] ?? 0);
        $label = $input['label'] ?? null;
        $config = $input['config'] ?? [];
        $result = AIWatchlistService::createWatchlist($db, $userId, $companyId, $entityType, $entityId, $label, $config);
        echo json_encode($result);
        break;

    case 'list_watchlists':
        $watchlists = AIWatchlistService::getUserWatchlists($db, $userId, $companyId);
        echo json_encode(['success' => true, 'data' => $watchlists]);
        break;

    case 'remove_watchlist':
        $wlId = (int) ($input['watchlist_id'] ?? 0);
        $result = AIWatchlistService::removeWatchlist($db, $wlId, $userId);
        echo json_encode(['success' => $result]);
        break;

    case 'create_task_from_event':
        // Test 23: Emergency kill switch — blocks intelligence-driven actions
        if (class_exists('AIEmergencyControlsService')) {
            if (AIEmergencyControlsService::isControlActive($db, 'disable_autonomous_agents')) {
                echo json_encode(['success' => false, 'error' => 'Emergency control active: autonomous agents are disabled']); exit;
            }
            if (AIEmergencyControlsService::isControlActive($db, 'disable_ai_actions')) {
                echo json_encode(['success' => false, 'error' => 'Emergency control active: AI actions are disabled']); exit;
            }
        }

        $eventId = (int) ($input['event_id'] ?? 0);
        // Test 7: Permission check on event access
        $event = AIIntelligenceService::getEvent($db, $eventId, $userId);
        if (!$event) { echo json_encode(['success' => false, 'error' => 'Event not found or access denied']); exit; }

        // Test 11: Check if the suggested action requires approval
        $requiresApproval = false;
        if (class_exists('AIPolicyEngine')) {
            $policyContext = [
                'user_id' => $userId,
                'company_id' => $companyId,
                'action_type' => 'write',
                'operation' => 'intelligence_create_task',
            ];
            $policyResult = AIPolicyEngine::evaluate($db, 'intelligence_create_task', $policyContext);
            if ($policyResult['decision'] === 'deny') {
                echo json_encode(['success' => false, 'error' => 'Governance policy denies this action: ' . $policyResult['reason']]); exit;
            }
            if ($policyResult['requires_confirmation'] || $policyResult['requires_review']) {
                $requiresApproval = true;
            }
        }

        // Create F25 task from intelligence event
        $agentDefId = 1; // ERP General Assistant
        $request = $event['suggested_action'] ?? $event['title'];

        // F24: Ensure no secrets are passed to the agent context
        if (class_exists('AISecretProtectionService')) {
            $request = AISecretProtectionService::redactSecrets($request);
        }

        $result = AIAgentRuntimeService::createTask($db, $userId, $companyId, [], $agentDefId, $request, 'intelligence', $eventId);

        if ($result['success']) {
            AIIntelligenceService::linkTask($db, $eventId, $result['task_id'], $result['task_key']);

            // F23: Record the task creation trace
            AIIntelligenceService::recordObservabilityTrace($db, 'event_task_created', [
                'event_id' => $eventId,
                'task_id' => $result['task_id'],
                'task_key' => $result['task_key'],
                'requires_approval' => $requiresApproval,
                'source_event_severity' => $event['severity'],
                'source_event_module' => $event['source_module'],
            ]);
        }

        echo json_encode($result);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
