<?php
/**
 * AI F30 — Analytics API.
 */
require_once __DIR__ . '/../bootstrap.php';
$db = getDB();
$companyId = $_SESSION['company_id'] ?? 1;
$userId = $_SESSION['user_id'] ?? 1;

header('Content-Type: application/json');

function aiAnalyticsElevatedUser(PDO $db): bool
{
    try {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        $stmt = $db->prepare("
            SELECT r.name
            FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $role = (string) ($stmt->fetchColumn() ?: '');
        return in_array($role, ['Super Admin', 'Admin'], true);
    } catch (\Throwable $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    if (function_exists('hasPermission') && !hasPermission('ai.analytics.view') && !aiAnalyticsElevatedUser($db)) {
        echo json_encode(['success' => false, 'error' => 'No permission.']);
        exit;
    }

    $analytics = new AIAnalyticsService($db, $companyId, $userId);

    switch ($_POST['action']) {
        case 'query':
            $text = trim($_POST['text'] ?? '');
            $context = json_decode($_POST['context'] ?? '{}', true) ?: [];
            echo json_encode($analytics->processRequest($text, $context));
            exit;

        case 'forecast':
            $metricKey = $_POST['metric_key'] ?? 'revenue';
            $periods = (int)($_POST['periods'] ?? 30);
            $method = $_POST['method'] ?? 'moving_average';
            $result = $analytics->getForecastService()->generateForecast($metricKey, $periods, $method);
            if (is_array($result)) {
                $analytics->recordAnalyticsRun(
                    'Forecast ' . $metricKey . ' (' . $method . ')',
                    'forecast',
                    $result,
                    (int) ($result['latency_ms'] ?? 0)
                );
            }
            echo json_encode($result);
            exit;

        case 'scenario':
            $metricKey = $_POST['metric_key'] ?? 'revenue';
            $variables = json_decode($_POST['variables'] ?? '{}', true) ?: [];
            $name = $_POST['name'] ?? '';
            $result = $analytics->getScenarioService()->runScenario($metricKey, $variables, $name);
            if (is_array($result)) {
                $analytics->recordAnalyticsRun(
                    'Scenario ' . $metricKey,
                    'scenario',
                    $result,
                    (int) ($result['latency_ms'] ?? 0)
                );
            }
            echo json_encode($result);
            exit;

        case 'briefing':
            echo json_encode($analytics->processRequest('Show executive briefing'));
            exit;

        case 'get_metrics':
            echo json_encode(['success' => true, 'metrics' => $analytics->getQueryEngine()->getRegistry()->getAvailableMetrics()]);
            exit;

        case 'get_kpis':
            $metrics = $analytics->getQueryEngine()->getRegistry()->getAvailableMetrics();
            $cards = [];
            foreach (array_slice($metrics, 0, 12) as $metric) {
                $queryText = 'Show ' . $metric['name'] . ' this quarter';
                $result = $analytics->processRequest($queryText);
                $cards[] = [
                    'metric' => $metric,
                    'result' => $result,
                ];
            }
            echo json_encode(['success' => true, 'kpis' => $cards]);
            exit;

        case 'get_dimensions':
            echo json_encode(['success' => true, 'dimensions' => ['date', 'customer', 'supplier', 'product', 'category', 'branch', 'department', 'salesperson']]);
            exit;

        case 'get_scenario_variables':
            echo json_encode(['success' => true, 'variables' => $analytics->getScenarioService()->getAvailableVariables()]);
            exit;

        case 'get_saved':
            echo json_encode(['success' => true, 'analyses' => $analytics->getSavedAnalyses()]);
            exit;

        case 'save_analysis':
            $name = $_POST['name'] ?? '';
            $metricKey = $_POST['metric_key'] ?? '';
            $options = json_decode($_POST['options'] ?? '{}', true) ?: [];
            echo json_encode($analytics->saveAnalysis($name, $metricKey, $options));
            exit;

        case 'schedule_analysis':
            $analysisId = (int) ($_POST['analysis_id'] ?? 0);
            $schedule = json_decode($_POST['schedule'] ?? '{}', true) ?: [];
            echo json_encode($analytics->scheduleAnalysis($analysisId, $schedule));
            exit;

        case 'run_scheduled':
            echo json_encode($analytics->runDueScheduledAnalyses());
            exit;

        case 'get_role_metrics':
            $role = $_POST['role'] ?? 'management';
            echo json_encode(['success' => true, 'metrics' => $analytics->getRoleAnalytics($role)]);
            exit;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            exit;
    }
}
echo json_encode(['success' => false, 'error' => 'Invalid request']);
