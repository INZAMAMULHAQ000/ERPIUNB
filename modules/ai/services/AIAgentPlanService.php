<?php
/**
 * AI F25 — Agent Plan Service.
 *
 * Manages structured agent plans: generation, validation, preview, dry-run.
 * Every plan step is validated against agent capabilities and F24 governance
 * before any execution occurs.
 *
 * Covers Sections 4 (Structured Plans), 7 (Plan Preview / Dry Run).
 */
class AIAgentPlanService
{
    /**
     * Create a structured plan for a task.
     *
     * @param PDO    $db       Database connection
     * @param int    $taskId   Agent task ID
     * @param array  $steps    Array of step definitions
     * @param int    $userId   Creating user
     * @param bool   $dryRun   Whether this is a dry-run simulation
     * @return array Creation result
     */
    public static function createPlan(PDO $db, int $taskId, array $steps, int $userId, bool $dryRun = false): array
    {
        $task = self::getTask($db, $taskId);
        if (!$task) {
            return ['success' => false, 'error' => 'Task not found'];
        }

        // Validate all steps
        $validation = self::validatePlanSteps($db, $steps, $task);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => 'Plan validation failed', 'errors' => $validation['errors']];
        }

        // Check F24 governance for the entire plan
        $governanceCheck = self::checkPlanGovernance($db, $steps, $task);
        if (!$governanceCheck['allowed']) {
            return ['success' => false, 'error' => 'Plan blocked by governance', 'reason' => $governanceCheck['reason']];
        }

        try {
            $db->beginTransaction();

            // Determine max plan version
            $stmt = $db->prepare("SELECT COALESCE(MAX(plan_version), 0) + 1 FROM ai_agent_plans WHERE task_id = ?");
            $stmt->execute([$taskId]);
            $planVersion = (int) $stmt->fetchColumn();

            // Calculate risk level from steps
            $maxRisk = 'low';
            $riskOrder = ['low' => 0, 'medium' => 1, 'high' => 2, 'critical' => 3];
            $requiresApprovals = false;
            $approvalSteps = [];

            foreach ($steps as $step) {
                $stepRisk = $step['risk_level'] ?? 'low';
                if (($riskOrder[$stepRisk] ?? 0) > ($riskOrder[$maxRisk] ?? 0)) {
                    $maxRisk = $stepRisk;
                }
                if (!empty($step['requires_approval'])) {
                    $requiresApprovals = true;
                    $approvalSteps[] = $step['step_number'];
                }
            }

            // Insert plan
            $stmt = $db->prepare("
                INSERT INTO ai_agent_plans
                    (task_id, plan_version, plan_json, status, risk_level,
                     requires_approvals, approval_steps_json, dry_run,
                     validated_by_governance, validation_result_json, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
            ");
            $stmt->execute([
                $taskId,
                $planVersion,
                json_encode(['steps' => $steps]),
                $dryRun ? 'draft' : 'validated',
                $maxRisk,
                $requiresApprovals ? 1 : 0,
                json_encode($approvalSteps),
                $dryRun ? 1 : 0,
                json_encode($governanceCheck),
                $userId,
            ]);

            $planId = (int) $db->lastInsertId();

            // Insert plan steps
            $stepStmt = $db->prepare("
                INSERT INTO ai_agent_plan_steps
                    (plan_id, step_number, step_key, objective, step_type, operation_id,
                     input_json, expected_result, dependency_step_number, risk_level,
                     requires_approval, status, max_retries)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");

            foreach ($steps as $step) {
                $stepStmt->execute([
                    $planId,
                    $step['step_number'],
                    $step['step_key'] ?? 'step_' . $step['step_number'],
                    $step['objective'],
                    $step['step_type'],
                    $step['operation_id'] ?? null,
                    json_encode($step['parameters'] ?? []),
                    $step['expected_result'] ?? null,
                    $step['dependency_step_number'] ?? null,
                    $step['risk_level'] ?? 'low',
                    !empty($step['requires_approval']) ? 1 : 0,
                    $step['max_retries'] ?? 2,
                ]);
            }

            $db->commit();

            return [
                'success' => true,
                'plan_id' => $planId,
                'plan_version' => $planVersion,
                'step_count' => count($steps),
                'risk_level' => $maxRisk,
                'requires_approvals' => $requiresApprovals,
                'dry_run' => $dryRun,
                'governance' => $governanceCheck,
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            return ['success' => false, 'error' => 'Failed to create plan: ' . $e->getMessage()];
        }
    }

    /**
     * Validate plan steps against agent capabilities.
     */
    public static function validatePlanSteps(PDO $db, array $steps, array $task): array
    {
        $errors = [];
        $agent = self::getAgentDefinition($db, $task['agent_id']);

        if (!$agent) {
            return ['valid' => false, 'errors' => ['Agent definition not found']];
        }

        $allowedTools = json_decode($agent['allowed_tools_json'] ?? '[]', true) ?? [];
        $allowedActions = json_decode($agent['allowed_actions_json'] ?? '[]', true) ?? [];
        $maxSteps = (int) $agent['max_steps'];

        if (count($steps) > $maxSteps) {
            $errors[] = "Plan has " . count($steps) . " steps but agent allows max {$maxSteps}";
        }

        $stepNumbers = [];
        foreach ($steps as $idx => $step) {
            // Required fields
            if (empty($step['step_number'])) {
                $errors[] = "Step " . ($idx + 1) . ": step_number is required";
                continue;
            }
            if (empty($step['objective'])) {
                $errors[] = "Step {$step['step_number']}: objective is required";
            }
            if (empty($step['step_type'])) {
                $errors[] = "Step {$step['step_number']}: step_type is required";
            }

            // Validate step type
            $validTypes = ['tool', 'action', 'knowledge', 'reasoning', 'navigation', 'verification', 'checkpoint'];
            if (!in_array($step['step_type'], $validTypes)) {
                $errors[] = "Step {$step['step_number']}: invalid step_type '{$step['step_type']}'";
            }

            // Validate tool/action is in agent allowlist
            if ($step['step_type'] === 'tool' && !empty($step['operation_id'])) {
                if (!empty($allowedTools) && !in_array($step['operation_id'], $allowedTools)) {
                    $errors[] = "Step {$step['step_number']}: tool '{$step['operation_id']}' is not in agent allowlist";
                }
            }
            if ($step['step_type'] === 'action' && !empty($step['operation_id'])) {
                if (!empty($allowedActions) && !in_array($step['operation_id'], $allowedActions)) {
                    $errors[] = "Step {$step['step_number']}: action '{$step['operation_id']}' is not in agent allowlist";
                }
            }

            // Validate dependency references
            if (!empty($step['dependency_step_number'])) {
                if (!in_array($step['dependency_step_number'], $stepNumbers)) {
                    $errors[] = "Step {$step['step_number']}: dependency step {$step['dependency_step_number']} not yet defined";
                }
            }

            // Validate parameters are not suspicious
            if (!empty($step['parameters'])) {
                $paramCheck = self::validateStepParameters($step['parameters']);
                if (!$paramCheck['valid']) {
                    $errors[] = "Step {$step['step_number']}: " . implode(', ', $paramCheck['errors']);
                }
            }

            $stepNumbers[] = $step['step_number'];
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validate step parameters for dangerous content.
     */
    private static function validateStepParameters(array $params): array
    {
        $errors = [];
        $dangerous = ['__proto__', 'constructor', 'prototype'];

        foreach ($params as $key => $value) {
            if (in_array(strtolower($key), $dangerous)) {
                $errors[] = "Blocked dangerous parameter: $key";
            }
            if (is_string($value)) {
                // Check for SQL injection
                if (preg_match('/;\s*(DROP|DELETE|INSERT|UPDATE|ALTER|TRUNCATE)\b/i', $value)) {
                    $errors[] = "Parameter '$key' contains SQL mutation pattern";
                }
                // Check for shell commands
                if (preg_match('/;\s*(rm|cat|wget|curl|chmod|sudo|bash)\b/i', $value)) {
                    $errors[] = "Parameter '$key' contains shell command pattern";
                }
                // Check for PHP code
                if (preg_match('/<\?php|eval\s*\(|exec\s*\(/i', $value)) {
                    $errors[] = "Parameter '$key' contains PHP code pattern";
                }
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Check F24 governance for the entire plan.
     */
    public static function checkPlanGovernance(PDO $db, array $steps, array $task): array
    {
        $result = ['allowed' => true, 'reason' => '', 'step_results' => []];

        foreach ($steps as $step) {
            if (empty($step['operation_id'])) continue;

            $context = [
                'user_id' => $task['user_id'],
                'company_id' => $task['company_id'],
                'step_type' => $step['step_type'],
                'operation_id' => $step['operation_id'],
                'risk_level' => $step['risk_level'] ?? 'low',
                'parameters' => $step['parameters'] ?? [],
            ];

            if (class_exists('AIGovernanceMiddleware')) {
                $governance = AIGovernanceMiddleware::enforce($db, 'agent_execution', 'agent_step', $context);
                $result['step_results'][$step['step_number']] = $governance;

                if (!$governance['allowed']) {
                    $result['allowed'] = false;
                    $result['reason'] = "Step {$step['step_number']} blocked: " . $governance['reason'];
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Generate a plan preview for user review (dry-run).
     * Shows what the agent intends to do without executing anything.
     */
    public static function generatePreview(PDO $db, int $planId): array
    {
        $plan = self::getPlan($db, $planId);
        if (!$plan) {
            return ['success' => false, 'error' => 'Plan not found'];
        }

        $steps = self::getPlanSteps($db, $planId);
        $task = self::getTask($db, $plan['task_id']);

        $preview = [
            'plan_id' => $planId,
            'task_id' => $plan['task_id'],
            'original_request' => $task['original_request'] ?? '',
            'total_steps' => count($steps),
            'risk_level' => $plan['risk_level'],
            'requires_approvals' => (bool) $plan['requires_approvals'],
            'estimated_cost' => $plan['estimated_cost'],
            'estimated_duration' => $plan['estimated_duration_seconds'],
            'external_systems' => json_decode($plan['external_systems_json'] ?? '[]', true),
            'dry_run' => (bool) $plan['dry_run'],
            'steps' => [],
        ];

        $actionsRequiringApproval = 0;
        $externalSystems = [];

        foreach ($steps as $step) {
            $stepPreview = [
                'number' => $step['step_number'],
                'objective' => $step['objective'],
                'type' => $step['step_type'],
                'operation' => $step['operation_id'],
                'parameters' => json_decode($step['input_json'] ?? '{}', true),
                'expected_result' => $step['expected_result'],
                'risk_level' => $step['risk_level'],
                'requires_approval' => (bool) $step['requires_approval'],
                'dependency' => $step['dependency_step_number'],
            ];

            if ($step['requires_approval']) {
                $actionsRequiringApproval++;
            }

            $preview['steps'][] = $stepPreview;
        }

        $preview['actions_requiring_approval'] = $actionsRequiringApproval;

        // Build summary
        $readSteps = count(array_filter($steps, fn($s) => $s['step_type'] === 'tool'));
        $writeSteps = count(array_filter($steps, fn($s) => $s['step_type'] === 'action'));
        $preview['summary'] = [
            'read_operations' => $readSteps,
            'write_operations' => $writeSteps,
            'approvals_needed' => $actionsRequiringApproval,
            'highest_risk' => $plan['risk_level'],
        ];

        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Execute a dry-run plan — simulate without modifying data.
     */
    public static function executeDryRun(PDO $db, int $planId, int $userId): array
    {
        $plan = self::getPlan($db, $planId);
        if (!$plan) {
            return ['success' => false, 'error' => 'Plan not found'];
        }

        $steps = self::getPlanSteps($db, $planId);
        $simResults = [];

        foreach ($steps as $step) {
            $simResult = [
                'step_number' => $step['step_number'],
                'objective' => $step['objective'],
                'type' => $step['step_type'],
                'would_execute' => true,
                'dry_run_result' => 'Simulated successfully',
            ];

            // Check governance (real check even in dry-run)
            if (class_exists('AIGovernanceMiddleware') && !empty($step['operation_id'])) {
                $task = self::getTask($db, $plan['task_id']);
                $governance = AIGovernanceMiddleware::enforce($db, 'agent_execution', 'agent_step', [
                    'user_id' => $userId,
                    'company_id' => $task['company_id'] ?? 1,
                    'step_type' => $step['step_type'],
                    'operation_id' => $step['operation_id'],
                ]);

                if (!$governance['allowed']) {
                    $simResult['would_execute'] = false;
                    $simResult['dry_run_result'] = 'WOULD BE BLOCKED: ' . $governance['reason'];
                }
            }

            $simResults[] = $simResult;
        }

        return ['success' => true, 'dry_run_results' => $simResults, 'plan_id' => $planId];
    }

    /**
     * Get a plan by ID.
     */
    public static function getPlan(PDO $db, int $planId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_agent_plans WHERE id = ?");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();
        return $plan ?: null;
    }

    /**
     * Get plan steps ordered by step number.
     */
    public static function getPlanSteps(PDO $db, int $planId): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_agent_plan_steps WHERE plan_id = ? ORDER BY step_number ASC");
        $stmt->execute([$planId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get task by ID.
     */
    private static function getTask(PDO $db, int $taskId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_agent_tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        return $task ?: null;
    }

    /**
     * Get agent definition.
     */
    private static function getAgentDefinition(PDO $db, int $agentId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_agent_definitions WHERE id = ?");
        $stmt->execute([$agentId]);
        $agent = $stmt->fetch();
        return $agent ?: null;
    }
}
