<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

$db = getDB();
$companyId = (int) ($_SESSION['company_id'] ?? 1);
$userId = (int) ($_SESSION['user_id'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

if (function_exists('hasPermission') && !hasPermission('ai.process_intelligence.view')) {
    echo json_encode(['success' => false, 'error' => 'No permission.']);
    exit;
}

if (class_exists('AIKillSwitchService') && !AIKillSwitchService::isEnabled($db, 'process_intelligence')) {
    echo json_encode(['success' => false, 'error' => 'Process Intelligence is currently disabled.']);
    exit;
}
if (class_exists('AIEmergencyControlsService') && AIEmergencyControlsService::isControlActive($db, 'disable_all_ai')) {
    echo json_encode(['success' => false, 'error' => 'AI is currently disabled by emergency control.']);
    exit;
}

$service = new AIProcessIntelligenceService($db, $companyId, $userId);
$action = trim((string) ($_POST['action'] ?? ''));

switch ($action) {
    case 'overview':
        echo json_encode(['success' => true, 'overview' => $service->getOverview()]);
        break;

    case 'refresh':
        echo json_encode($service->refreshProcessData());
        break;

    case 'definitions':
        echo json_encode(['success' => true, 'definitions' => $service->getDefinitions()]);
        break;

    case 'explorer':
        echo json_encode([
            'success' => true,
            'runs' => $service->getProcessExplorer($_POST['process_key'] ?? null, (int) ($_POST['limit'] ?? 25)),
        ]);
        break;

    case 'map':
        echo json_encode([
            'success' => true,
            'map' => $service->getProcessMap($_POST['process_key'] ?? null),
        ]);
        break;

    case 'bottlenecks':
        echo json_encode([
            'success' => true,
            'findings' => $service->getBottlenecks($_POST['process_key'] ?? null),
        ]);
        break;

    case 'compare':
        echo json_encode([
            'success' => true,
            'comparison' => $service->compareProcess($_POST['process_key'] ?? null, $_POST['dimension'] ?? 'department'),
        ]);
        break;

    case 'recommendations':
        echo json_encode([
            'success' => true,
            'recommendations' => $service->getRecommendations($_POST['process_key'] ?? null),
        ]);
        break;

    case 'metrics':
        echo json_encode([
            'success' => true,
            'metrics' => $service->getMetrics($_POST['process_key'] ?? null),
        ]);
        break;

    case 'sla':
        echo json_encode([
            'success' => true,
            'sla' => $service->getSlaSummary($_POST['process_key'] ?? null),
        ]);
        break;

    case 'rework':
        echo json_encode([
            'success' => true,
            'rework' => $service->getReworkSummary($_POST['process_key'] ?? null),
        ]);
        break;

    case 'simulate':
        $variables = json_decode((string) ($_POST['variables'] ?? '{}'), true) ?: [];
        echo json_encode($service->runSimulation($_POST['process_key'] ?? null, $variables, $_POST['name'] ?? 'Ad hoc simulation'));
        break;

    case 'proposal':
        echo json_encode($service->createProposalFromRecommendation((int) ($_POST['recommendation_id'] ?? 0)));
        break;

    case 'task':
        echo json_encode($service->createTaskFromRecommendation((int) ($_POST['recommendation_id'] ?? 0)));
        break;

    case 'schedule':
        $schedule = json_decode((string) ($_POST['schedule'] ?? '{}'), true) ?: [];
        echo json_encode($service->scheduleAnalysis((string) ($_POST['process_key'] ?? ''), $schedule));
        break;

    case 'run_scheduled':
        echo json_encode($service->runDueScheduledAnalyses());
        break;

    case 'history':
        echo json_encode(['success' => true, 'history' => $service->getHistory((int) ($_POST['limit'] ?? 25))]);
        break;

    case 'query':
        $context = json_decode((string) ($_POST['context'] ?? '{}'), true) ?: [];
        echo json_encode($service->processRequest((string) ($_POST['text'] ?? ''), $context));
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
        break;
}
