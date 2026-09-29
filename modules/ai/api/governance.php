<?php
/**
 * AI F24 — Governance API.
 *
 * Centralized API endpoints for policy engine, risk classification,
 * data classification, and governance operations.
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Check API access
if (!hasPermission('ai.management.admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'evaluate':
            evaluatePolicy($db);
            break;
        case 'classify_risk':
            classifyRisk($db);
            break;
        case 'classify_data':
            classifyData($db);
            break;
        case 'check_provider':
            checkProvider($db);
            break;
        case 'get_autonomy':
            getAutonomy($db);
            break;
        case 'create_policy':
            createPolicy($db);
            break;
        case 'update_policy':
            updatePolicy($db);
            break;
        case 'delete_policy':
            deletePolicy($db);
            break;
        case 'list_policies':
            listPolicies($db);
            break;
        case 'get_policy_history':
            getPolicyHistory($db);
            break;
        case 'rollback_policy':
            rollbackPolicy($db);
            break;
        case 'get_risk_rules':
            getRiskRules($db);
            break;
        case 'add_risk_rule':
            addRiskRule($db);
            break;
        case 'get_classifications':
            getClassifications($db);
            break;
        case 'get_masking_rules':
            getMaskingRules($db);
            break;
        case 'simulate':
            simulatePolicy($db);
            break;
        case 'get_governance_stats':
            getGovernanceStats($db);
            break;
        case 'get_emergency_controls':
            getEmergencyControls($db);
            break;
        case 'toggle_emergency_control':
            toggleEmergencyControl($db);
            break;
        case 'activate_all_emergency':
            activateAllEmergency($db);
            break;
        case 'deactivate_all_emergency':
            deactivateAllEmergency($db);
            break;
        case 'simulate_policy':
            simulatePolicyEnhanced($db);
            break;
        case 'get_injection_stats':
            getInjectionStats($db);
            break;
        case 'check_tool_authorization':
            checkToolAuthorization($db);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Evaluate policy for an action.
 */
function evaluatePolicy(PDO $db): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? '';
    $context = $input['context'] ?? [];

    if (!$action) {
        echo json_encode(['success' => false, 'error' => 'Action is required']);
        return;
    }

    if (!class_exists('AIPolicyEngine')) {
        echo json_encode(['success' => false, 'error' => 'Policy engine not available']);
        return;
    }

    $result = AIPolicyEngine::evaluate($db, $action, $context);
    echo json_encode(['success' => true, 'data' => $result]);
}

/**
 * Classify risk for an action.
 */
function classifyRisk(PDO $db): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? '';
    $context = $input['context'] ?? [];

    if (!class_exists('AIRiskClassifier')) {
        echo json_encode(['success' => false, 'error' => 'Risk classifier not available']);
        return;
    }

    $result = AIRiskClassifier::classify($db, $action, $context);
    echo json_encode(['success' => true, 'data' => $result]);
}

/**
 * Classify data based on content.
 */
function classifyData(PDO $db): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $content = $input['content'] ?? '';
    $context = $input['context'] ?? [];

    if (!class_exists('AIDataClassificationService')) {
        echo json_encode(['success' => false, 'error' => 'Data classification service not available']);
        return;
    }

    $result = AIDataClassificationService::classifyContent($content, $context);
    echo json_encode(['success' => true, 'data' => $result]);
}

/**
 * Check provider eligibility.
 */
function checkProvider(PDO $db): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $providerId = $input['provider_id'] ?? 0;
    $dataClassification = $input['data_classification'] ?? 'internal';
    $companyId = $input['company_id'] ?? null;

    if (!class_exists('AIPolicyEngine')) {
        echo json_encode(['success' => false, 'error' => 'Policy engine not available']);
        return;
    }

    $result = AIPolicyEngine::evaluateProvider($db, $providerId, $dataClassification, $companyId);
    echo json_encode(['success' => true, 'data' => $result]);
}

/**
 * Get autonomy level for a user/context.
 */
function getAutonomy(PDO $db): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $companyId = $input['company_id'] ?? null;
    $roleId = $input['role_id'] ?? null;
    $userId = $input['user_id'] ?? null;

    if (!class_exists('AIPolicyEngine')) {
        echo json_encode(['success' => false, 'error' => 'Policy engine not available']);
        return;
    }

    $autonomy = AIPolicyEngine::getAutonomyLevel($db, $companyId, $roleId, $userId);
    echo json_encode(['success' => true, 'data' => ['autonomy_level' => $autonomy]]);
}

/**
 * Create a new policy.
 */
function createPolicy(PDO $db): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if (!class_exists('AIPolicyEngine')) {
        echo json_encode(['success' => false, 'error' => 'Policy engine not available']);
        return;
    }

    $result = AIPolicyEngine::createPolicy($db, $input, $_SESSION['user_id'] ?? 0);
    echo json_encode($result);
}

/**
 * Update a policy.
 */
function updatePolicy(PDO $db): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $policyId = $input['id'] ?? 0;

    if (!class_exists('AIPolicyEngine')) {
        echo json_encode(['success' => false, 'error' => 'Policy engine not available']);
        return;
    }

    $result = AIPolicyEngine::updatePolicy($db, $policyId, $input, $_SESSION['user_id'] ?? 0);
    echo json_encode($result);
}

/**
 * Delete a policy.
 */
function deletePolicy(PDO $db): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $policyId = $input['id'] ?? 0;

    try {
        $stmt = $db->prepare("UPDATE ai_policies SET status = 'archived' WHERE id = ?");
        $stmt->execute([$policyId]);

        if (class_exists('AIGovernanceAudit')) {
            AIGovernanceAudit::log($db, 'policy_deleted', [
                'actor_id' => $_SESSION['user_id'] ?? 0,
                'target_type' => 'policy',
                'target_id' => $policyId,
                'change_reason' => 'Policy archived',
            ]);
        }

        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * List policies.
 */
function listPolicies(PDO $db): void
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(100, (int) ($_GET['per_page'] ?? 20));
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];

    if (!empty($_GET['type'])) {
        $where[] = 'type = ?';
        $params[] = $_GET['type'];
    }
    if (!empty($_GET['scope'])) {
        $where[] = 'scope = ?';
        $params[] = $_GET['scope'];
    }
    if (!empty($_GET['status'])) {
        $where[] = 'status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['effect'])) {
        $where[] = 'effect = ?';
        $params[] = $_GET['effect'];
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM ai_policies $whereClause");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT * FROM ai_policies
            $whereClause
            ORDER BY priority DESC, created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $policies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $policies,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ],
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get policy version history.
 */
function getPolicyHistory(PDO $db): void
{
    $policyId = (int) ($_GET['id'] ?? 0);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(50, (int) ($_GET['per_page'] ?? 20));

    if (!class_exists('AIPolicyVersionService')) {
        echo json_encode(['success' => false, 'error' => 'Version service not available']);
        return;
    }

    $result = AIPolicyVersionService::getVersionHistory($db, $policyId, $page, $perPage);
    echo json_encode(['success' => true, 'data' => $result]);
}

/**
 * Rollback a policy version.
 */
function rollbackPolicy(PDO $db): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $policyId = $input['policy_id'] ?? 0;
    $targetVersion = $input['target_version'] ?? 0;

    if (!class_exists('AIPolicyVersionService')) {
        echo json_encode(['success' => false, 'error' => 'Version service not available']);
        return;
    }

    $result = AIPolicyVersionService::rollback($db, $policyId, $targetVersion, $_SESSION['user_id'] ?? 0);
    echo json_encode($result);
}

/**
 * Get risk classification rules.
 */
function getRiskRules(PDO $db): void
{
    try {
        $stmt = $db->query("SELECT * FROM ai_risk_rules ORDER BY priority ASC");
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rules]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Add a risk rule.
 */
function addRiskRule(PDO $db): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if (!class_exists('AIRiskClassifier')) {
        echo json_encode(['success' => false, 'error' => 'Risk classifier not available']);
        return;
    }

    $result = AIRiskClassifier::registerRule($db, $input);
    echo json_encode(['success' => $result]);
}

/**
 * Get data classifications.
 */
function getClassifications(PDO $db): void
{
    try {
        $stmt = $db->query("SELECT * FROM ai_data_classifications ORDER BY classification ASC");
        $classifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $classifications]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get masking rules.
 */
function getMaskingRules(PDO $db): void
{
    try {
        $stmt = $db->query("SELECT * FROM ai_masking_rules WHERE is_active = 1 ORDER BY field_type ASC");
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rules]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Simulate policy evaluation.
 */
function simulatePolicy(PDO $db): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? '';
    $context = $input['context'] ?? [];

    if (!$action) {
        echo json_encode(['success' => false, 'error' => 'Action is required']);
        return;
    }

    if (!class_exists('AIPolicyEngine')) {
        echo json_encode(['success' => false, 'error' => 'Policy engine not available']);
        return;
    }

    // Simulate without executing
    $result = AIPolicyEngine::evaluate($db, $action, $context);

    echo json_encode([
        'success' => true,
        'data' => $result,
        'simulation' => true,
        'note' => 'This is a simulation - no changes were made',
    ]);
}

/**
 * Get governance statistics.
 */
function getGovernanceStats(PDO $db): void
{
    try {
        $stats = [];

        // Policy count
        $stmt = $db->query("SELECT COUNT(*) FROM ai_policies WHERE status = 'active'");
        $stats['active_policies'] = (int) $stmt->fetchColumn();

        $stmt = $db->query("SELECT COUNT(*) FROM ai_policies WHERE status = 'draft'");
        $stats['draft_policies'] = (int) $stmt->fetchColumn();

        // Risk rules count
        $stmt = $db->query("SELECT COUNT(*) FROM ai_risk_rules WHERE is_active = 1");
        $stats['active_risk_rules'] = (int) $stmt->fetchColumn();

        // Data classifications count
        $stmt = $db->query("SELECT COUNT(*) FROM ai_data_classifications");
        $stats['data_classifications'] = (int) $stmt->fetchColumn();

        // Masking rules count
        $stmt = $db->query("SELECT COUNT(*) FROM ai_masking_rules WHERE is_active = 1");
        $stats['masking_rules'] = (int) $stmt->fetchColumn();

        // Open review items
        $stmt = $db->query("SELECT COUNT(*) FROM ai_review_queue WHERE status = 'pending'");
        $stats['pending_reviews'] = (int) $stmt->fetchColumn();

        // Open security events
        $stmt = $db->query("SELECT COUNT(*) FROM ai_security_events WHERE status = 'open'");
        $stats['open_security_events'] = (int) $stmt->fetchColumn();

        echo json_encode(['success' => true, 'data' => $stats]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get emergency controls status.
 */
function getEmergencyControls(PDO $db): void
{
    if (!class_exists('AIEmergencyControlsService')) {
        echo json_encode(['success' => false, 'error' => 'Emergency controls not available']);
        return;
    }

    $controls = AIEmergencyControlsService::getAllStatus($db);
    echo json_encode(['success' => true, 'data' => $controls]);
}

/**
 * Toggle an emergency control.
 */
function toggleEmergencyControl(PDO $db): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $controlKey = $input['control_key'] ?? '';
    $active = (bool) ($input['is_active'] ?? false);
    $reason = $input['reason'] ?? '';
    $userId = $_SESSION['user_id'] ?? 0;

    if (!$controlKey) {
        echo json_encode(['success' => false, 'error' => 'Control key required']);
        return;
    }

    if (!class_exists('AIEmergencyControlsService')) {
        echo json_encode(['success' => false, 'error' => 'Emergency controls not available']);
        return;
    }

    $result = $active
        ? AIEmergencyControlsService::activateControl($db, $controlKey, $userId, $reason)
        : AIEmergencyControlsService::deactivateControl($db, $controlKey, $userId, $reason);

    echo json_encode($result);
}

/**
 * Activate all emergency controls.
 */
function activateAllEmergency(PDO $db): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $reason = $input['reason'] ?? 'Emergency shutdown';
    $userId = $_SESSION['user_id'] ?? 0;

    if (!class_exists('AIEmergencyControlsService')) {
        echo json_encode(['success' => false, 'error' => 'Emergency controls not available']);
        return;
    }

    $result = AIEmergencyControlsService::activateAll($db, $userId, $reason);
    echo json_encode($result);
}

/**
 * Deactivate all emergency controls.
 */
function deactivateAllEmergency(PDO $db): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $reason = $input['reason'] ?? 'Emergency controls lifted';
    $userId = $_SESSION['user_id'] ?? 0;

    if (!class_exists('AIEmergencyControlsService')) {
        echo json_encode(['success' => false, 'error' => 'Emergency controls not available']);
        return;
    }

    $result = AIEmergencyControlsService::deactivateAll($db, $userId, $reason);
    echo json_encode($result);
}

/**
 * Enhanced policy simulation.
 */
function simulatePolicyEnhanced(PDO $db): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if (!class_exists('AIPolicySimulator')) {
        echo json_encode(['success' => false, 'error' => 'Policy simulator not available']);
        return;
    }

    $result = AIPolicySimulator::simulate($db, $input);
    echo json_encode(['success' => true, 'data' => $result]);
}

/**
 * Get injection detection statistics.
 */
function getInjectionStats(PDO $db): void
{
    try {
        $stats = [];

        // Total injection attempts
        $stmt = $db->query("SELECT COUNT(*) FROM ai_injection_attempts");
        $stats['total_attempts'] = (int) $stmt->fetchColumn();

        // Blocked attempts
        $stmt = $db->query("SELECT COUNT(*) FROM ai_injection_attempts WHERE blocked = 1");
        $stats['blocked'] = (int) $stmt->fetchColumn();

        // By type
        $stmt = $db->query("SELECT injection_type, COUNT(*) as count FROM ai_injection_attempts GROUP BY injection_type ORDER BY count DESC");
        $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // By source
        $stmt = $db->query("SELECT source, COUNT(*) as count FROM ai_injection_attempts GROUP BY source ORDER BY count DESC");
        $stats['by_source'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Last 24 hours
        $stmt = $db->query("SELECT COUNT(*) FROM ai_injection_attempts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stats['last_24_hours'] = (int) $stmt->fetchColumn();

        echo json_encode(['success' => true, 'data' => $stats]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Check tool authorization.
 */
function checkToolAuthorization(PDO $db): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $toolName = $input['tool_name'] ?? '';
    $toolArgs = $input['tool_args'] ?? [];

    if (!$toolName) {
        echo json_encode(['success' => false, 'error' => 'Tool name required']);
        return;
    }

    if (!class_exists('AIToolAuthorizationService')) {
        echo json_encode(['success' => false, 'error' => 'Tool authorization service not available']);
        return;
    }

    $userId = $_SESSION['user_id'] ?? 0;
    $companyId = $_SESSION['company_id'] ?? 1;
    $permissions = function_exists('hasPermission') ? [/* would need permission lookup */] : [];

    $result = AIToolAuthorizationService::authorize($db, $toolName, $toolArgs, $userId, $companyId, $permissions);
    echo json_encode(['success' => true, 'data' => $result]);
}
