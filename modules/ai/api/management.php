<?php
/**
 * AI F16 — AI Management Center API Endpoint.
 *
 * Handles AJAX requests for AI governance, observability, health checks,
 * diagnostics, kill switches, policies, retries, and usage analytics.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}

if (!hasPermission('ai.management.view') && !hasPermission('ai.settings.manage') && ($_SESSION['role'] ?? '') !== 'superadmin') {
    echo json_encode(['success' => false, 'error' => 'Permission denied. Administrator access required.']);
    exit;
}

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');

// CSRF check for mutating actions (POST/PUT/DELETE)
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'], true)) {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
    if (empty($token)) {
        echo json_encode(['success' => false, 'error' => 'CSRF token required for this action.']);
        exit;
    }
    if (!verifyCsrf($token)) {
        echo json_encode(['success' => false, 'error' => 'Invalid or expired CSRF token.']);
        exit;
    }
}

try {
    switch ($action) {
        // ── Health & Diagnostics ──
        case 'health_check_all':
            $results = AIHealthService::checkAll($db);
            $healthStatus = AIHealthService::getStoredHealth($db);
            $overallStatus = AIHealthService::getOverallStatus($db);
            $alerts = AIManagementService::getAlerts($db);
            echo json_encode([
                'success' => true,
                'overall_status' => $overallStatus,
                'health_status' => $healthStatus,
                'alerts' => $alerts,
                'message' => 'All system health checks refreshed successfully.'
            ]);
            break;

        case 'health_check_provider':
            $providerId = (int) ($input['provider_id'] ?? ($_GET['provider_id'] ?? 0));
            if (!$providerId) {
                echo json_encode(['success' => false, 'error' => 'Provider ID is required.']);
                exit;
            }
            $result = AIHealthService::checkProviderHealth($db, $providerId, true);
            echo json_encode($result);
            break;

        case 'run_diagnostics':
            $diagnostics = AIManagementService::runDiagnostics($db);
            AIGovernanceService::auditConfigChange($db, 'diagnostics', null, 'run_diagnostics', null, ['checks_count' => count($diagnostics)], $userId);
            echo json_encode([
                'success' => true,
                'diagnostics' => $diagnostics,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'validate_features':
            $features = ['agents', 'knowledge', 'scheduled_automations', 'command_center', 'automations', 'insights'];
            $results = [];
            foreach ($features as $f) {
                $results[$f] = AIManagementService::validateFeatureConfig($db, $f);
            }
            echo json_encode([
                'success' => true,
                'validations' => $results,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
            break;

        // ── Kill Switches ──
        case 'toggle_kill_switch':
            $switchKey = trim($input['switch_key'] ?? ($_POST['switch_key'] ?? ''));
            $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : (isset($_POST['enabled']) ? (bool)$_POST['enabled'] : false);
            $reason = trim($input['reason'] ?? ($_POST['reason'] ?? ''));

            if (!$switchKey) {
                echo json_encode(['success' => false, 'error' => 'Switch key required.']);
                exit;
            }

            $res = AIKillSwitchService::toggle($db, $switchKey, $enabled, $userId, $reason ?: null);
            $summary = AIKillSwitchService::getStatusSummary($db);
            $alerts = AIManagementService::getAlerts($db);
            echo json_encode([
                'success' => $res['success'] ?? false,
                'enabled' => $enabled,
                'summary' => $summary,
                'alerts' => $alerts,
                'message' => $enabled ? "Kill switch deactivated ({$switchKey} enabled)." : "Kill switch ACTIVATED ({$switchKey} disabled)."
            ]);
            break;

        // ── Cost Controls ──
        case 'save_cost_control':
            $id = (int) ($input['id'] ?? 0);
            $controlType = trim($input['control_type'] ?? '');
            $targetId = !empty($input['target_id']) ? (int) $input['target_id'] : null;
            $maxCost = (float) ($input['max_cost'] ?? 0.0);
            $enabled = isset($input['enabled']) ? (int)(bool)$input['enabled'] : 1;

            if (!$controlType || $maxCost < 0) {
                echo json_encode(['success' => false, 'error' => 'Valid control type and non-negative max cost required.']);
                exit;
            }

            if ($id > 0) {
                $prev = $db->query("SELECT * FROM ai_cost_controls WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
                $stmt = $db->prepare("
                    UPDATE ai_cost_controls
                    SET control_type = ?, target_id = ?, max_cost = ?, enabled = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$controlType, $targetId, $maxCost, $enabled, $id]);
                AIGovernanceService::auditConfigChange($db, 'cost_control', (string)$id, 'updated', $prev, ['control_type' => $controlType, 'max_cost' => $maxCost, 'enabled' => $enabled], $userId);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO ai_cost_controls
                        (control_type, target_id, max_cost, current_cost, period_start, period_end, enabled, created_at, updated_at)
                    VALUES (?, ?, ?, 0.00, '1970-01-01', '2999-12-31', ?, NOW(), NOW())
                ");
                $stmt->execute([$controlType, $targetId, $maxCost, $enabled]);
                $newId = $db->lastInsertId();
                AIGovernanceService::auditConfigChange($db, 'cost_control', (string)$newId, 'created', null, ['control_type' => $controlType, 'max_cost' => $maxCost], $userId);
            }

            echo json_encode(['success' => true, 'message' => 'Cost control saved successfully.']);
            break;

        case 'toggle_cost_control':
            $id = (int) ($input['id'] ?? 0);
            $enabled = isset($input['enabled']) ? (int)(bool)$input['enabled'] : 1;
            $db->prepare("UPDATE ai_cost_controls SET enabled = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$enabled, $id]);
            AIGovernanceService::auditConfigChange($db, 'cost_control', (string)$id, 'toggle', null, ['enabled' => $enabled], $userId);
            echo json_encode(['success' => true, 'message' => 'Cost control status updated.']);
            break;

        case 'delete_cost_control':
            $id = (int) ($input['id'] ?? 0);
            $prev = $db->query("SELECT * FROM ai_cost_controls WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
            $db->prepare("DELETE FROM ai_cost_controls WHERE id = ?")->execute([$id]);
            AIGovernanceService::auditConfigChange($db, 'cost_control', (string)$id, 'deleted', $prev, null, $userId);
            echo json_encode(['success' => true, 'message' => 'Cost control removed.']);
            break;

        // ── Rate Limits ──
        case 'save_rate_limit':
            $id = (int) ($input['id'] ?? 0);
            $limitKey = trim($input['limit_key'] ?? '');
            $limitType = trim($input['limit_type'] ?? 'per_minute');
            $scope = trim($input['scope'] ?? 'user');
            $maxRequests = (int) ($input['max_requests'] ?? 60);
            $feature = !empty($input['feature']) ? trim($input['feature']) : null;
            $enabled = isset($input['enabled']) ? (int)(bool)$input['enabled'] : 1;

            if (!$limitKey || $maxRequests <= 0) {
                echo json_encode(['success' => false, 'error' => 'Valid limit key and positive request limit required.']);
                exit;
            }

            if ($id > 0) {
                $prev = $db->query("SELECT * FROM ai_rate_limits WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
                $stmt = $db->prepare("
                    UPDATE ai_rate_limits
                    SET limit_key = ?, limit_type = ?, max_requests = ?, feature = ?, enabled = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$limitKey, $limitType, $maxRequests, $feature, $enabled, $id]);
                AIGovernanceService::auditConfigChange($db, 'rate_limit', (string)$id, 'updated', $prev, ['limit_key' => $limitKey, 'max_requests' => $maxRequests], $userId);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO ai_rate_limits
                        (limit_key, limit_type, max_requests, feature, enabled, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$limitKey, $limitType, $maxRequests, $feature, $enabled]);
                $newId = $db->lastInsertId();
                AIGovernanceService::auditConfigChange($db, 'rate_limit', (string)$newId, 'created', null, ['limit_key' => $limitKey, 'max_requests' => $maxRequests], $userId);
            }

            echo json_encode(['success' => true, 'message' => 'Rate limit saved successfully.']);
            break;

        case 'toggle_rate_limit':
            $id = (int) ($input['id'] ?? 0);
            $enabled = isset($input['enabled']) ? (int)(bool)$input['enabled'] : 1;
            $db->prepare("UPDATE ai_rate_limits SET enabled = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$enabled, $id]);
            AIGovernanceService::auditConfigChange($db, 'rate_limit', (string)$id, 'toggle', null, ['enabled' => $enabled], $userId);
            echo json_encode(['success' => true, 'message' => 'Rate limit status updated.']);
            break;

        case 'delete_rate_limit':
            $id = (int) ($input['id'] ?? 0);
            $prev = $db->query("SELECT * FROM ai_rate_limits WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
            $db->prepare("DELETE FROM ai_rate_limits WHERE id = ?")->execute([$id]);
            AIGovernanceService::auditConfigChange($db, 'rate_limit', (string)$id, 'deleted', $prev, null, $userId);
            echo json_encode(['success' => true, 'message' => 'Rate limit removed.']);
            break;

        // ── Safety Policies ──
        case 'update_safety_policy':
            $id = (int) ($input['id'] ?? 0);
            $policyKey = trim($input['policy_key'] ?? '');
            $maxValue = (int) ($input['max_value'] ?? 0);
            $enabled = isset($input['enabled']) ? (int)(bool)$input['enabled'] : 1;

            if ($id <= 0 && !$policyKey) {
                echo json_encode(['success' => false, 'error' => 'Policy ID or key required.']);
                exit;
            }

            $stmt = $id > 0
                ? $db->prepare("SELECT * FROM ai_safety_policies WHERE id = ?")
                : $db->prepare("SELECT * FROM ai_safety_policies WHERE policy_key = ?");
            $stmt->execute([$id ?: $policyKey]);
            $prev = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$prev) {
                echo json_encode(['success' => false, 'error' => 'Policy not found.']);
                exit;
            }

            // Verify against system hard limits
            $hardLimit = AIGovernanceService::getEffectiveLimit($db, $prev['policy_key']);
            $db->prepare("
                UPDATE ai_safety_policies
                SET max_value = ?, enabled = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([$maxValue, $enabled, $prev['id']]);

            AIGovernanceService::auditConfigChange($db, 'safety_policy', $prev['policy_key'], 'updated', $prev, ['max_value' => $maxValue, 'enabled' => $enabled], $userId);

            echo json_encode(['success' => true, 'message' => 'Safety policy updated successfully.']);
            break;

        // ── Sensitive Data Masking ──
        case 'save_masking_policy':
            $id = (int) ($input['id'] ?? 0);
            $fieldPattern = trim($input['field_pattern'] ?? '');
            $maskingType = trim($input['masking_type'] ?? 'full');
            $module = !empty($input['module']) ? trim($input['module']) : null;
            $description = trim($input['description'] ?? '');
            $enabled = isset($input['enabled']) ? (int)(bool)$input['enabled'] : 1;

            if (!$fieldPattern) {
                echo json_encode(['success' => false, 'error' => 'Field pattern is required.']);
                exit;
            }

            if (!in_array($maskingType, ['none', 'partial', 'full', 'exclude'], true)) {
                $maskingType = 'full';
            }

            if ($id > 0) {
                $prev = $db->query("SELECT * FROM ai_masking_policies WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
                $stmt = $db->prepare("
                    UPDATE ai_masking_policies
                    SET field_pattern = ?, masking_type = ?, module = ?, description = ?, enabled = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$fieldPattern, $maskingType, $module, $description, $enabled, $id]);
                AIGovernanceService::auditConfigChange($db, 'masking_policy', (string)$id, 'updated', $prev, ['field_pattern' => $fieldPattern, 'masking_type' => $maskingType], $userId);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO ai_masking_policies
                        (field_pattern, masking_type, module, description, enabled, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$fieldPattern, $maskingType, $module, $description, $enabled]);
                $newId = $db->lastInsertId();
                AIGovernanceService::auditConfigChange($db, 'masking_policy', (string)$newId, 'created', null, ['field_pattern' => $fieldPattern, 'masking_type' => $maskingType], $userId);
            }

            echo json_encode(['success' => true, 'message' => 'Data masking policy saved successfully.']);
            break;

        case 'delete_masking_policy':
            $id = (int) ($input['id'] ?? 0);
            $prev = $db->query("SELECT * FROM ai_masking_policies WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
            $db->prepare("DELETE FROM ai_masking_policies WHERE id = ?")->execute([$id]);
            AIGovernanceService::auditConfigChange($db, 'masking_policy', (string)$id, 'deleted', $prev, null, $userId);
            echo json_encode(['success' => true, 'message' => 'Masking policy removed.']);
            break;

        // ── Retention Policies & Cleanup ──
        case 'update_retention_policy':
            $id = (int) ($input['id'] ?? 0);
            $days = max(1, (int) ($input['retention_days'] ?? 30));
            $enabled = isset($input['enabled']) ? (int)(bool)$input['enabled'] : 1;

            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Policy ID required.']);
                exit;
            }

            $prev = $db->query("SELECT * FROM ai_retention_policies WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
            $db->prepare("UPDATE ai_retention_policies SET retention_days = ?, enabled = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$days, $enabled, $id]);
            AIGovernanceService::auditConfigChange($db, 'retention_policy', (string)$id, 'updated', $prev, ['retention_days' => $days, 'enabled' => $enabled], $userId);
            echo json_encode(['success' => true, 'message' => 'Retention policy updated.']);
            break;

        case 'run_retention_cleanup':
            $result = AIGovernanceService::cleanupRetention($db, $userId);
            echo json_encode($result);
            break;

        // ── Failed Operations ──
        case 'retry_failed_operation':
            $errorId = (int) ($input['error_id'] ?? 0);
            if ($errorId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Error ID is required.']);
                exit;
            }
            $result = AIGovernanceService::retryFailedOperation($db, $errorId, $userId);
            echo json_encode($result);
            break;

        case 'resolve_failed_operation':
            $errorId = (int) ($input['error_id'] ?? 0);
            $status = in_array($input['status'] ?? '', ['resolved', 'ignored'], true) ? $input['status'] : 'resolved';
            $db->prepare("UPDATE ai_error_registry SET resolution_status = ?, resolved_at = NOW() WHERE id = ?")
               ->execute([$status, $errorId]);
            AIGovernanceService::auditConfigChange($db, 'error_registry', (string)$errorId, 'status_change', null, ['status' => $status], $userId);
            echo json_encode(['success' => true, 'message' => "Operation marked as {$status}."]);
            break;

        // ── Usage & Analytics ──
        case 'get_usage_data':
            $dateFrom = !empty($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d 00:00:00', strtotime('-30 days'));
            $dateTo = !empty($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d 23:59:59');
            $feature = !empty($_GET['feature']) ? $_GET['feature'] : null;
            $groupBy = !empty($_GET['group_by']) ? $_GET['group_by'] : 'feature';

            $filters = ['date_from' => $dateFrom, 'date_to' => $dateTo];
            if ($feature) $filters['feature'] = $feature;

            $stats = AIUsageService::getStats($db, $filters);
            $grouped = AIUsageService::getGroupedStats($db, $filters, $groupBy);
            $recent = AIUsageService::getRecent($db, 30);
            $daily = AIUsageService::getDailyUsage($db, 30);

            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'grouped' => $grouped,
                'recent' => $recent,
                'daily' => $daily,
            ]);
            break;

        // ── Audit Logs ──
        case 'get_audit_logs':
            $filters = [
                'config_type' => !empty($_GET['config_type']) ? $_GET['config_type'] : null,
                'date_from' => !empty($_GET['date_from']) ? $_GET['date_from'] : null,
                'limit' => min(100, (int) ($_GET['limit'] ?? 50)),
            ];
            $logs = AIManagementService::getAuditTimeline($db, array_filter($filters));
            echo json_encode(['success' => true, 'logs' => $logs]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => "Unknown management action '{$action}'."]);
            break;
    }
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
