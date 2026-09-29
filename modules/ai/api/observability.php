<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once AI_MODULE_DIR . '/services/AIObservabilityService.php';

while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($token) && !verifyCsrf($token)) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$companyId = isset($_SESSION['company_id']) ? (int) $_SESSION['company_id'] : 1;
$action = $_GET['action'] ?? $_POST['action'] ?? 'overview';
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$input = is_array($jsonInput) ? $jsonInput : $_POST;

AIObservabilityService::ensureDefaults($db);

$viewAllowed = hasPermission('ai.observability.view') || hasPermission('ai.observability.manage') || hasPermission('ai.settings.manage');
$manageAllowed = hasPermission('ai.observability.manage') || hasPermission('ai.settings.manage');
$evalAllowed = hasPermission('ai.observability.evaluations') || $manageAllowed;
$traceAllowed = hasPermission('ai.observability.traces') || $manageAllowed;
$feedbackReviewAllowed = hasPermission('ai.observability.feedback.review') || $manageAllowed;
$experimentAllowed = hasPermission('ai.observability.experiments') || $manageAllowed;

if (!$viewAllowed && !in_array($action, ['submit_feedback'], true)) {
    echo json_encode(['success' => false, 'error' => 'Permission denied.']);
    exit;
}

switch ($action) {
    case 'overview':
        echo json_encode(['success' => true, 'overview' => AIObservabilityService::getOverview($db, ['company_id' => $companyId])]);
        break;

    case 'requests':
        echo json_encode(['success' => true, 'requests' => AIObservabilityService::listRequests($db, ['company_id' => $companyId], (int) ($input['limit'] ?? 50), (int) ($input['offset'] ?? 0))]);
        break;

    case 'request_detail':
        if (!$traceAllowed) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        $detail = AIObservabilityService::getRequestDetail($db, (int) ($input['request_id'] ?? $_GET['request_id'] ?? 0), $companyId);
        echo json_encode(['success' => (bool) $detail, 'request' => $detail, 'error' => $detail ? null : 'Request not found.']);
        break;

    case 'submit_feedback':
        echo json_encode(AIObservabilityService::submitFeedback($db, [
            'request_id' => (int) ($input['request_id'] ?? 0) ?: null,
            'conversation_id' => (int) ($input['conversation_id'] ?? 0) ?: null,
            'company_id' => $companyId,
            'user_id' => $userId,
            'feedback_type' => $input['feedback_type'] ?? '',
            'reason_code' => $input['reason_code'] ?? null,
            'category' => $input['category'] ?? null,
            'comment' => $input['comment'] ?? null,
            'metadata' => is_array($input['metadata'] ?? null) ? $input['metadata'] : [],
        ]));
        break;

    case 'feedback':
        if (!$feedbackReviewAllowed) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(['success' => true, 'feedback' => AIObservabilityService::listFeedback($db, ['company_id' => $companyId], (int) ($input['limit'] ?? 50))]);
        break;

    case 'review_feedback':
        if (!$feedbackReviewAllowed) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIObservabilityService::reviewFeedback($db, (int) ($input['feedback_id'] ?? 0), $userId, $input));
        break;

    case 'datasets':
        if (!$evalAllowed) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(['success' => true, 'datasets' => AIObservabilityService::listDatasets($db)]);
        break;

    case 'save_dataset':
        if (!$evalAllowed) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIObservabilityService::saveDataset($db, $userId, $input));
        break;

    case 'save_eval_case':
        if (!$evalAllowed) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIObservabilityService::saveEvaluationCase($db, $userId, $input));
        break;

    case 'evaluation_runs':
        if (!$evalAllowed) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(['success' => true, 'runs' => AIObservabilityService::listEvaluationRuns($db)]);
        break;

    case 'run_evaluation':
        if (!$evalAllowed) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIObservabilityService::runEvaluation($db, $userId, $input));
        break;

    case 'experiments':
        if (!$experimentAllowed) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(['success' => true, 'experiments' => AIObservabilityService::listExperiments($db)]);
        break;

    case 'save_experiment':
        if (!$experimentAllowed) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIObservabilityService::saveExperiment($db, $userId, $input));
        break;

    case 'benchmarks':
        if (!$viewAllowed) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(['success' => true, 'benchmarks' => AIObservabilityService::listBenchmarks($db)]);
        break;

    case 'save_benchmark':
        if (!$manageAllowed) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIObservabilityService::saveBenchmark($db, $userId, $input));
        break;

    case 'prompt_versions':
        if (!$manageAllowed) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(['success' => true, 'prompts' => AIObservabilityService::listPromptVersions($db, $input['feature'] ?? null)]);
        break;

    case 'save_prompt_version':
        if (!$manageAllowed) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIObservabilityService::savePromptVersion($db, $userId, $input));
        break;

    case 'activate_prompt_version':
        if (!$manageAllowed) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIObservabilityService::activatePromptVersion($db, (int) ($input['id'] ?? 0), $userId));
        break;

    case 'findings':
        echo json_encode(['success' => true, 'findings' => AIObservabilityService::listFindings($db)]);
        break;

    case 'detect_anomalies':
        if (!$manageAllowed) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIObservabilityService::detectAnomalies($db));
        break;

    case 'failures':
        echo json_encode(['success' => true, 'failures' => AIObservabilityService::getFailures($db, ['company_id' => $companyId])]);
        break;

    case 'reports':
        echo json_encode(['success' => true, 'reports' => AIObservabilityService::listReports($db)]);
        break;

    case 'generate_report':
        if (!$manageAllowed) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIObservabilityService::generateReport($db, $userId, (string) ($input['report_type'] ?? 'daily_operations'), $companyId));
        break;

    case 'refresh_aggregates':
        if (!$manageAllowed) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }
        echo json_encode(AIObservabilityService::refreshDailyAggregates($db));
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
        break;
}
