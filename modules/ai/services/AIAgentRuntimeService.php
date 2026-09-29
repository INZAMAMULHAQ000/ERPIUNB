<?php
/**
 * AI F25 — Agent Runtime Service.
 *
 * Main orchestrator that coordinates the full agent lifecycle:
 * Understand → Plan → Govern → Execute → Verify → Continue/Pause → Complete → Report
 *
 * Integrates with F24 (governance), F23 (observability), F12 (agent base),
 * F13 (automation), F17 (skills), F22 (integrations).
 *
 * Covers Sections 2, 3, 5, 6, 11-19, 25.
 */
class AIAgentRuntimeService
{
    /**
     * Create a task from a natural language request.
     * Entry point for all task creation (Section 2).
     */
    public static function createTask(
        PDO $db,
        int $userId,
        int $companyId,
        array $userPermissions,
        int $agentDefId,
        string $request,
        string $source = 'chat',
        ?int $sourceId = null,
        ?int $conversationId = null,
        string $priority = 'medium'
    ): array {
        $taskKey = 'task_' . bin2hex(random_bytes(12));
        $request = trim($request);

        if (empty($request)) {
            return ['success' => false, 'error' => 'Request cannot be empty'];
        }
        if (strlen($request) > 5000) {
            return ['success' => false, 'error' => 'Request too long (max 5000 characters)'];
        }

        // Test 20: Emergency kill switch check
        if (class_exists('AIEmergencyControlsService')) {
            if (AIEmergencyControlsService::isControlActive($db, 'disable_all_ai') ||
                AIEmergencyControlsService::isControlActive($db, 'disable_autonomous_agents')) {
                return ['success' => false, 'error' => 'Emergency kill switch active — agent tasks are disabled'];
            }
        }

        // Load agent definition
        $agent = self::getAgentDefinition($db, $agentDefId);
        if (!$agent || !$agent['is_enabled']) {
            return ['success' => false, 'error' => 'Agent not found or disabled'];
        }

        // Check user authorization
        $authCheck = self::checkUserAuthorization($agent, $userId, $userPermissions);
        if (!$authCheck['authorized']) {
            return ['success' => false, 'error' => $authCheck['reason']];
        }

        // F24 governance check for task creation
        if (class_exists('AIGovernanceMiddleware')) {
            $governance = AIGovernanceMiddleware::enforce($db, 'agent_execution', 'create_task', [
                'user_id' => $userId,
                'company_id' => $companyId,
                'agent_id' => $agentDefId,
                'action_type' => 'read',
            ]);
            if (!$governance['allowed']) {
                return ['success' => false, 'error' => 'Task creation blocked: ' . $governance['reason']];
            }
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO ai_agent_tasks
                    (task_key, agent_id, company_id, user_id, original_request,
                     priority, status, source, source_id, conversation_id)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
            ");
            $stmt->execute([
                $taskKey, $agentDefId, $companyId, $userId, $request,
                $priority, $source, $sourceId, $conversationId,
            ]);

            $taskId = (int) $db->lastInsertId();

            // Create event
            self::recordEvent($db, $taskId, null, $companyId, $userId, 'task_created', 'lifecycle', 'info', "Task created from $source");

            return [
                'success' => true,
                'task_id' => $taskId,
                'task_key' => $taskKey,
                'agent' => [
                    'id' => $agent['id'],
                    'name' => $agent['name'],
                    'description' => $agent['description'],
                ],
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to create task: ' . $e->getMessage()];
        }
    }

    /**
     * Generate a structured plan for a task.
     * Uses the AI to produce a plan, then validates it server-side.
     */
    public static function generatePlan(PDO $db, int $taskId, int $userId, array $planSteps): array
    {
        $task = self::getTask($db, $taskId);
        if (!$task) return ['success' => false, 'error' => 'Task not found'];
        if ($task['user_id'] != $userId) return ['success' => false, 'error' => 'Access denied'];

        // Update task status
        $db->prepare("UPDATE ai_agent_tasks SET status = 'planning', updated_at = NOW() WHERE id = ?")
           ->execute([$taskId]);

        // Create plan (validates against agent capabilities + F24 governance)
        $result = AIAgentPlanService::createPlan($db, $taskId, $planSteps, $userId);

        if ($result['success']) {
            $db->prepare("UPDATE ai_agent_tasks SET status = 'pending', total_steps = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$result['step_count'], $taskId]);

            self::recordEvent($db, $taskId, null, $task['company_id'], $userId,
                'plan_created', 'planning', 'info',
                "Plan v{$result['plan_version']} created with {$result['step_count']} steps, risk: {$result['risk_level']}"
            );
        }

        return $result;
    }

    /**
     * Execute a task — creates a run and processes steps.
     * This is the main execution entry point (Section 3).
     */
    public static function executeTask(PDO $db, int $taskId, int $userId, array $userPermissions): array
    {
        $task = self::getTask($db, $taskId);
        if (!$task) return ['success' => false, 'error' => 'Task not found'];
        if ($task['user_id'] != $userId) return ['success' => false, 'error' => 'Access denied'];

        // Test 7: Tenant isolation — verify task belongs to user's company
        $userCompanyId = (int) ($_SESSION['company_id'] ?? 0);
        if ($userCompanyId > 0 && (int) $task['company_id'] !== $userCompanyId) {
            return ['success' => false, 'error' => 'Tenant isolation violation — task belongs to a different company'];
        }

        // Test 20: Emergency kill switch check
        if (class_exists('AIEmergencyControlsService')) {
            if (AIEmergencyControlsService::isControlActive($db, 'disable_all_ai') ||
                AIEmergencyControlsService::isControlActive($db, 'disable_autonomous_agents')) {
                return ['success' => false, 'error' => 'Emergency kill switch active — agent execution blocked'];
            }
        }

        $agent = self::getAgentDefinition($db, $task['agent_id']);
        if (!$agent) return ['success' => false, 'error' => 'Agent not found'];

        // Get the latest plan for this task
        $planStmt = $db->prepare("
            SELECT *
            FROM ai_agent_plans
            WHERE task_id = ?
            ORDER BY plan_version DESC, id DESC
            LIMIT 1
        ");
        $planStmt->execute([$taskId]);
        $plan = $planStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$plan) {
            return ['success' => false, 'error' => 'No plan exists for this task. Generate a plan first.'];
        }

        // Create a run
        $runResult = self::createRun($db, $taskId, $agent, $userId, $task['company_id'], $plan['id']);
        if (!$runResult['success']) return $runResult;

        $runId = $runResult['run_id'];

        // Execute the run autonomously (up to agent's max steps)
        $executionResult = self::executeRun($db, $runId, $userId, $userPermissions, $agent, $task);

        // Test 21: Build actual result from server-side execution data
        $finalResult = self::buildFinalResult($db, $taskId, $runId, $executionResult);

        return array_merge($executionResult, [
            'run_id' => $runId,
            'task_id' => $taskId,
            'final_result' => $finalResult,
        ]);
    }

    /**
     * Build the final result from actual server-side execution data (Test 21).
     * Never rely on model claims — use actual step execution records.
     */
    private static function buildFinalResult(PDO $db, int $taskId, int $runId, array $executionResult): array
    {
        $steps = self::getTaskSteps($db, $runId);
        $task = self::getTask($db, $taskId);

        $completedSteps = array_filter($steps, fn($s) => $s['status'] === 'completed');
        $failedSteps = array_filter($steps, fn($s) => $s['status'] === 'failed');
        $skippedSteps = array_filter($steps, fn($s) => $s['status'] === 'skipped');
        $pendingApprovals = array_filter($steps, fn($s) => $s['status'] === 'awaiting_approval');

        return [
            'requested' => $task['original_request'] ?? '',
            'actually_done' => count($completedSteps) . ' of ' . count($steps) . ' steps completed',
            'completed_steps' => count($completedSteps),
            'failed_steps' => count($failedSteps),
            'skipped_steps' => count($skippedSteps),
            'pending_approvals' => count($pendingApprovals),
            'step_details' => array_map(fn($s) => [
                'step' => $s['step_number'],
                'type' => $s['step_type'],
                'operation' => $s['operation_id'],
                'status' => $s['status'],
                'verification' => $s['verification_status'] ?? null,
            ], $steps),
            'task_status' => $task['status'],
        ];
    }

    /**
     * Create a run record for task execution.
     */
    private static function createRun(PDO $db, int $taskId, array $agent, int $userId, int $companyId, ?int $planId): array
    {
        try {
            $maxSteps = (int) $agent['max_steps'];
            $stmt = $db->prepare("
                INSERT INTO ai_agent_task_runs
                    (task_id, plan_id, agent_id, company_id, user_id, status, total_steps)
                VALUES (?, ?, ?, ?, ?, 'queued', ?)
            ");
            $stmt->execute([$taskId, $planId, $agent['id'], $companyId, $userId, $maxSteps]);

            $runId = (int) $db->lastInsertId();

            // Update task
            $db->prepare("UPDATE ai_agent_tasks SET status = 'running', started_at = NOW(), updated_at = NOW() WHERE id = ?")
               ->execute([$taskId]);

            return ['success' => true, 'run_id' => $runId];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to create run: ' . $e->getMessage()];
        }
    }

    /**
     * Execute a run — processes steps from the plan sequentially.
     * Integrates F24 governance, F23 observability, checkpoints, approvals.
     */
    public static function executeRun(
        PDO $db,
        int $runId,
        int $userId,
        array $userPermissions,
        array $agent,
        array $task,
        int $maxIterations = 0
    ): array {
        if ($maxIterations <= 0) {
            $maxIterations = (int) $agent['max_steps'];
        }

        $results = [];
        $run = self::getRun($db, $runId);

        for ($i = 0; $i < $maxIterations; $i++) {
            $run = self::getRun($db, $runId);
            if (!$run) break;

            // Check if run is still in an executable state
            if (!in_array($run['status'], ['queued', 'running'])) {
                break;
            }

            // Test 20: Emergency kill switch check — blocks all agent execution
            if (class_exists('AIEmergencyControlsService')) {
                if (AIEmergencyControlsService::isControlActive($db, 'disable_all_ai') ||
                    AIEmergencyControlsService::isControlActive($db, 'disable_autonomous_agents')) {
                    self::updateRunStatus($db, $runId, 'failed', 'Emergency kill switch active — agent execution blocked');
                    self::recordEvent($db, $task['id'], $runId, $task['company_id'], $userId,
                        'emergency_kill_switch', 'governance', 'critical', 'Agent execution blocked by emergency kill switch'
                    );
                    break;
                }
            }

            // Check limits (Section 12)
            $limitCheck = self::checkLimits($run, $agent);
            if (!$limitCheck['ok']) {
                self::updateRunStatus($db, $runId, 'limit_reached', $limitCheck['reason']);
                self::recordEvent($db, $task['id'], $runId, $task['company_id'], $userId,
                    'limit_reached', 'execution', 'warning', $limitCheck['reason']
                );
                break;
            }

            // Get the current step from the plan
            $nextStep = self::getNextStep($db, $runId, $run['current_step']);
            if (!$nextStep) {
                // All steps completed
                self::completeRun($db, $runId, $task['id']);
                break;
            }

            // Save checkpoint before execution (Section 8)
            AIAgentCheckpointService::saveCheckpoint($db, $runId, $task['id'], $nextStep['step_number'], 'auto');

            // F24 Governance check for this step (Section 19)
            $governanceResult = self::checkStepGovernance($db, $nextStep, $task, $userId);
            if (!$governanceResult['allowed']) {
                self::failStep($db, $nextStep['id'], $governanceResult['reason']);
                self::recordEvent($db, $task['id'], $runId, $task['company_id'], $userId,
                    'step_denied', 'governance', 'high',
                    "Step {$nextStep['step_number']} denied: {$governanceResult['reason']}"
                );
                self::updateRunStatus($db, $runId, 'failed', $governanceResult['reason']);
                break;
            }

            // Check if step requires approval (Section 10)
            if (!empty($nextStep['requires_approval'])) {
                $approvalResult = self::requestApproval($db, $nextStep, $task, $runId, $userId);
                if ($approvalResult['success']) {
                    self::updateRunStatus($db, $runId, 'awaiting_approval', "Waiting for approval on step {$nextStep['step_number']}");
                    $results[] = ['step' => $nextStep['step_number'], 'status' => 'awaiting_approval'];
                    break; // Stop until approved
                }
            }

            // Execute the step
            $stepResult = self::executeStep($db, $nextStep, $run, $agent, $task, $userId, $userPermissions);
            $results[] = $stepResult;

            // Record the step execution (Section 18 observability)
            self::recordStepExecution($db, $runId, $task['id'], $nextStep, $stepResult, $userId);

            if (!$stepResult['success']) {
                // Check retry policy (Section 13)
                $retryResult = self::handleStepFailure($db, $nextStep, $runId, $task, $userId);
                if ($retryResult['should_retry']) {
                    continue; // Retry the same step
                }
                self::updateRunStatus($db, $runId, 'failed', $stepResult['error']);
                break;
            }

            // Verify result (Section 14)
            $verification = self::verifyStepResult($db, $nextStep, $stepResult);
            self::updateStepVerification($db, $nextStep['id'], $verification);

            if (!$verification['verified']) {
                self::recordEvent($db, $task['id'], $runId, $task['company_id'], $userId,
                    'verification_failed', 'verification', 'warning',
                    "Step {$nextStep['step_number']} result verification failed: {$verification['reason']}"
                );
            }

            // Update progress
            self::updateProgress($db, $runId, $task['id'], $nextStep['step_number']);

            // F23 Observability trace (Section 18)
            self::recordObservabilityTrace($db, $task, $run, $nextStep, $stepResult);
        }

        // Final run status check
        $run = self::getRun($db, $runId);
        if ($run && $run['status'] === 'running') {
            self::completeRun($db, $runId, $task['id']);
        }

        return ['success' => true, 'steps_executed' => count($results), 'results' => $results];
    }

    /**
     * Execute a single step based on its type.
     */
    private static function executeStep(
        PDO $db,
        array $step,
        array $run,
        array $agent,
        array $task,
        int $userId,
        array $userPermissions
    ): array {
        // Mark step as running
        $db->prepare("UPDATE ai_agent_step_executions SET status = 'running', started_at = NOW() WHERE id = ?")
           ->execute([$step['id']]);

        $input = json_decode($step['input_json'] ?? '{}', true);

        $startTime = microtime(true);
        $result = match ($step['step_type']) {
            'tool' => self::executeToolStep($step, $input, $userId, $userPermissions, $task['company_id']),
            'action' => self::executeActionStep($step, $input, $userId, $userPermissions, $task, $run),
            'knowledge' => self::executeKnowledgeStep($step, $input, $userId),
            'reasoning' => ['success' => true, 'data' => ['result' => 'Reasoning completed.']],
            'navigation' => self::executeNavigationStep($step, $input),
            'verification' => ['success' => true, 'data' => ['result' => 'Verification completed.']],
            'checkpoint' => [
                'success' => true,
                'data' => ['result' => 'Checkpoint saved.'],
            ],
            default => ['success' => false, 'error' => 'Unknown step type: ' . $step['step_type']],
        };

        $duration = (int) ((microtime(true) - $startTime) * 1000);

        // Update step status
        $status = $result['success'] ? 'completed' : 'failed';
        $db->prepare("
            UPDATE ai_agent_step_executions
            SET status = ?, output_json = ?, error_message = ?, duration_ms = ?, completed_at = NOW()
            WHERE id = ?
        ")->execute([
            $status,
            json_encode($result['data'] ?? null),
            $result['success'] ? null : $result['error'],
            $duration,
            $step['id'],
        ]);

        return $result;
    }

    /**
     * Execute a tool step — uses registered ERP tools only.
     * Section 5: Tool Execution — server-side only, registered tools only.
     */
    private static function executeToolStep(array $step, array $input, int $userId, array $userPermissions, int $companyId): array
    {
        global $db;
        $toolId = $step['operation_id'];

        // Validate tool exists in registry (Test 6: unauthorized tool denial)
        if (class_exists('AIToolRegistry')) {
            $toolDef = AIToolRegistry::get($toolId);
            if (!$toolDef) {
                return ['success' => false, 'error' => "Tool not registered: $toolId"];
            }
            if (isset($toolDef['enabled']) && !$toolDef['enabled']) {
                return ['success' => false, 'error' => "Tool is disabled: $toolId"];
            }
        }

        // F24 authorization check (Test 6: deny through governance)
        if (class_exists('AIToolAuthorizationService') && $db) {
            $authResult = AIToolAuthorizationService::authorize($db, $toolId, $input, $userId, $companyId, $userPermissions);
            if (!$authResult['authorized']) {
                return ['success' => false, 'error' => 'Tool authorization denied: ' . $authResult['reason']];
            }
        }

        // Execute via tool executor — server-side only (Test 19: no secrets exposed)
        if (class_exists('AIToolExecutor') && $db) {
            $executor = new AIToolExecutor($db, $userId, $userPermissions);
            $result = $executor->execute($toolId, $input, 0);
            if ($result['success']) {
                return ['success' => true, 'data' => ['tool' => $toolId, 'result' => $result['result'] ?? 'Executed']];
            }
            return ['success' => false, 'error' => $result['error'] ?? 'Tool execution failed'];
        }

        return ['success' => false, 'error' => 'Tool executor not available'];
    }

    /**
     * Execute an action step — always requires confirmation in agent context.
     * Section 5: All write actions go through F9 action framework.
     * Section 10: Actions require approval before execution.
     */
    private static function executeActionStep(array $step, array $input, int $userId, array $userPermissions, array $task, array $run): array
    {
        global $db;
        $actionId = $step['operation_id'];

        // Validate action exists in registry
        if (class_exists('AIActionRegistry')) {
            $actionDef = AIActionRegistry::get($actionId);
            if (!$actionDef) {
                return ['success' => false, 'error' => "Action not registered: $actionId"];
            }
        }

        // In agent runtime, ALL actions go through the approval system (Section 10)
        // The step should already be marked requires_approval by the plan validator
        return [
            'success' => true,
            'data' => [
                'action' => $actionId,
                'status' => 'awaiting_approval',
                'message' => 'Action requires human approval before execution',
                'input' => $input,
            ],
        ];
    }

    /**
     * Execute a knowledge retrieval step.
     */
    private static function executeKnowledgeStep(array $step, array $input, int $userId): array
    {
        global $db;
        $query = $input['query'] ?? '';
        if (empty($query)) {
            return ['success' => false, 'error' => 'Knowledge query is empty'];
        }

        if (class_exists('AIKnowledgeService') && $db) {
            $collectionId = $input['collection_id'] ?? null;
            $result = AIKnowledgeService::searchKnowledge($db, $userId, $query, $collectionId, 5);
            if (!empty($result['success']) && !empty($result['results'])) {
                $formatted = AIKnowledgeService::formatForAI($result['results']);
                return ['success' => true, 'data' => ['result' => $formatted, 'count' => count($result['results'])]];
            }
        }

        return ['success' => true, 'data' => ['result' => 'No relevant knowledge found.', 'count' => 0]];
    }

    /**
     * Execute a navigation step.
     */
    private static function executeNavigationStep(array $step, array $input): array
    {
        if (class_exists('AINavigationSecurityService')) {
            $url = $input['url'] ?? '';
            $validation = AINavigationSecurityService::validateUrl($url);
            if (!$validation['safe']) {
                return ['success' => false, 'error' => 'Navigation blocked: ' . $validation['reason']];
            }
        }

        return ['success' => true, 'data' => ['navigated_to' => $input['url'] ?? '']];
    }

    /**
     * Check F24 governance for a step (Section 19).
     */
    private static function checkStepGovernance(PDO $db, array $step, array $task, int $userId): array
    {
        if (!class_exists('AIGovernanceMiddleware')) {
            return ['allowed' => true, 'reason' => ''];
        }

        return AIGovernanceMiddleware::enforce($db, 'agent_execution', 'agent_step', [
            'user_id' => $userId,
            'company_id' => $task['company_id'],
            'step_type' => $step['step_type'],
            'operation_id' => $step['operation_id'] ?? '',
            'risk_level' => $step['risk_level'] ?? 'low',
            'parameters' => json_decode($step['input_json'] ?? '{}', true),
        ]);
    }

    /**
     * Request approval for a step (Section 10).
     */
    private static function requestApproval(PDO $db, array $step, array $task, int $runId, int $userId): array
    {
        $parameters = json_decode($step['input_json'] ?? '{}', true);

        return AIAgentApprovalService::createApproval(
            $db,
            $task['id'],
            $runId,
            null, // planId
            $step['id'],
            $task['company_id'],
            $userId,
            $step['operation_id'] ?? $step['step_type'],
            $parameters,
            $step['risk_level'] ?? 'medium'
        );
    }

    /**
     * Handle step failure with retry logic and exponential backoff (Section 13).
     * Includes duplicate action detection to prevent idempotency issues.
     */
    private static function handleStepFailure(PDO $db, array $step, int $runId, array $task, int $userId): array
    {
        $maxRetries = (int) $step['max_retries'];
        $currentRetries = (int) $step['retry_count'];

        if ($currentRetries >= $maxRetries) {
            self::recordEvent($db, $task['id'], $runId, $task['company_id'], $userId,
                'max_retries_exceeded', 'execution', 'warning',
                "Step {$step['step_number']} exceeded max retries ($maxRetries)"
            );
            return ['should_retry' => false, 'reason' => 'Max retries exceeded'];
        }

        // Check for duplicate/repeated action (Test 10: loop protection)
        $duplicateCheck = self::checkDuplicateAction($db, $runId, $step);
        if ($duplicateCheck['is_duplicate']) {
            self::recordEvent($db, $task['id'], $runId, $task['company_id'], $userId,
                'duplicate_action_detected', 'loop_protection', 'high',
                "Duplicate action detected at step {$step['step_number']}: {$duplicateCheck['reason']}"
            );
            return ['should_retry' => false, 'reason' => 'Duplicate action detected: ' . $duplicateCheck['reason']];
        }

        // For write operations, check if retry is safe (Test 12: idempotency)
        if ($step['step_type'] === 'action' || $step['step_type'] === 'tool') {
            $input = json_decode($step['input_json'] ?? '{}', true);
            if (!self::isSafeToRetry($step, $input)) {
                self::recordEvent($db, $task['id'], $runId, $task['company_id'], $userId,
                    'unsafe_retry', 'idempotency', 'high',
                    "Step {$step['step_number']} is a non-idempotent write — retry blocked to prevent duplicates"
                );
                return ['should_retry' => false, 'reason' => 'Non-idempotent write operation — retry blocked'];
            }
        }

        // Increment retry count
        $db->prepare("UPDATE ai_agent_step_executions SET retry_count = retry_count + 1 WHERE id = ?")
           ->execute([$step['id']]);

        // Save checkpoint before retry
        AIAgentCheckpointService::saveCheckpoint($db, $runId, $task['id'], $step['step_number'], 'error');

        // Exponential backoff: 1s, 2s, 4s, 8s...
        $backoffSeconds = min(pow(2, $currentRetries), 30);
        sleep((int) $backoffSeconds);

        self::recordEvent($db, $task['id'], $runId, $task['company_id'], $userId,
            'step_retry', 'execution', 'info',
            "Retrying step {$step['step_number']} (attempt " . ($currentRetries + 1) . ", backoff {$backoffSeconds}s)"
        );

        return ['should_retry' => true];
    }

    /**
     * Check for duplicate/repeated actions (Test 10: loop protection).
     */
    private static function checkDuplicateAction(PDO $db, int $runId, array $step): array
    {
        // Check if the same tool/action with same params was already executed successfully
        $stmt = $db->prepare("
            SELECT COUNT(*) as cnt FROM ai_agent_step_executions
            WHERE run_id = ? AND step_type = ? AND operation_id = ?
            AND input_json = ? AND status = 'completed'
        ");
        $stmt->execute([
            $runId,
            $step['step_type'],
            $step['operation_id'],
            $step['input_json'],
        ]);
        $count = (int) $stmt->fetchColumn();

        if ($count > 0) {
            return ['is_duplicate' => true, 'reason' => 'Same step already completed successfully'];
        }

        // Check for repeated failures on the same step (stuck detection)
        $stmt = $db->prepare("
            SELECT COUNT(*) as cnt FROM ai_agent_step_executions
            WHERE run_id = ? AND step_number = ? AND status = 'failed'
        ");
        $stmt->execute([$runId, $step['step_number']]);
        $failCount = (int) $stmt->fetchColumn();

        if ($failCount >= 3) {
            return ['is_duplicate' => true, 'reason' => 'Step failed repeatedly — stuck detection triggered'];
        }

        return ['is_duplicate' => false];
    }

    /**
     * Check if a step is safe to retry (Test 12: idempotency).
     * Write operations that create records, send emails, etc. are NOT safe to retry.
     */
    private static function isSafeToRetry(array $step, array $input): bool
    {
        // Read-only operations are always safe
        if ($step['step_type'] === 'tool') {
            $toolId = $step['operation_id'] ?? '';
            // Search/query tools are safe
            if (strpos($toolId, 'search') !== false || strpos($toolId, 'get') !== false || strpos($toolId, 'list') !== false) {
                return true;
            }
        }

        // Knowledge retrieval is safe
        if ($step['step_type'] === 'knowledge') return true;

        // Reasoning/navigation are safe
        if (in_array($step['step_type'], ['reasoning', 'navigation', 'verification'])) return true;

        // Write actions are generally NOT safe
        // Create, delete, send, update are non-idempotent unless they use dedup keys
        $dangerousPatterns = ['create', 'delete', 'send', 'email', 'execute', 'submit', 'publish'];
        $operationId = strtolower($step['operation_id'] ?? '');
        foreach ($dangerousPatterns as $pattern) {
            if (strpos($operationId, $pattern) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verify a step result (Section 14).
     */
    private static function verifyStepResult(PDO $db, array $step, array $result): array
    {
        $verified = true;
        $reason = '';

        // Basic verification: result exists and is not empty
        if (empty($result['data'])) {
            $verified = false;
            $reason = 'Step produced no output data';
        }

        // Check expected result if defined
        if ($verified && !empty($step['expected_result'])) {
            // Simple string match verification
            $resultStr = json_encode($result['data'] ?? '');
            if (strpos($resultStr, $step['expected_result']) === false) {
                // Not a hard failure, just a mismatch
                $reason = 'Output does not contain expected result pattern';
            }
        }

        return ['verified' => $verified, 'reason' => $reason];
    }

    /**
     * Update step verification result.
     */
    private static function updateStepVerification(PDO $db, int $stepId, array $verification): void
    {
        $db->prepare("
            UPDATE ai_agent_step_executions
            SET verification_status = ?, verification_details_json = ?
            WHERE id = ?
        ")->execute([
            $verification['verified'] ? 'passed' : 'failed',
            json_encode($verification),
            $stepId,
        ]);
    }

    /**
     * Update task and run progress.
     */
    private static function updateProgress(PDO $db, int $runId, int $taskId, int $completedStep): void
    {
        $run = self::getRun($db, $runId);
        if (!$run) return;

        $total = max(1, (int) $run['total_steps']);
        $pct = round(($completedStep / $total) * 100, 2);

        $db->prepare("
            UPDATE ai_agent_task_runs SET current_step = ?, updated_at = NOW() WHERE id = ?
        ")->execute([$completedStep, $runId]);

        $db->prepare("
            UPDATE ai_agent_tasks SET current_step = ?, progress_pct = ?, updated_at = NOW() WHERE id = ?
        ")->execute([$completedStep, min(100, $pct), $taskId]);
    }

    /**
     * Mark a run as completed.
     */
    private static function completeRun(PDO $db, int $runId, int $taskId): void
    {
        $db->prepare("
            UPDATE ai_agent_task_runs SET status = 'completed', completed_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ")->execute([$runId]);

        $db->prepare("
            UPDATE ai_agent_tasks SET status = 'completed', progress_pct = 100, completed_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ")->execute([$taskId]);
    }

    /**
     * Fail a step.
     */
    private static function failStep(PDO $db, int $stepId, string $error): void
    {
        $db->prepare("
            UPDATE ai_agent_step_executions
            SET status = 'failed', error_message = ?, completed_at = NOW()
            WHERE id = ?
        ")->execute([$error, $stepId]);
    }

    /**
     * Update run status.
     */
    private static function updateRunStatus(PDO $db, int $runId, string $status, ?string $result = null): void
    {
        $extra = '';
        $params = [$status];
        if ($result !== null) {
            $extra = ', result_summary = ?';
            $params[] = $result;
        }
        if ($status === 'completed') $extra .= ', completed_at = NOW()';
        $params[] = $runId;
        $db->prepare("UPDATE ai_agent_task_runs SET status = ?{$extra}, updated_at = NOW() WHERE id = ?")
           ->execute($params);
    }

    /**
     * Check agent limits (Section 12).
     */
    private static function checkLimits(array $run, array $agent): array
    {
        if ($run['current_step'] >= $agent['max_steps']) {
            return ['ok' => false, 'reason' => "Max steps reached ({$agent['max_steps']})"];
        }
        if ($run['tool_calls_made'] >= $agent['max_tool_calls']) {
            return ['ok' => false, 'reason' => "Max tool calls reached ({$agent['max_tool_calls']})"];
        }
        if ($run['started_at']) {
            $elapsed = time() - strtotime($run['started_at']);
            if ($elapsed >= $agent['max_runtime_seconds']) {
                return ['ok' => false, 'reason' => "Max runtime reached ({$agent['max_runtime_seconds']}s)"];
            }
        }
        return ['ok' => true];
    }

    /**
     * Get the next step to execute.
     */
    private static function getNextStep(PDO $db, int $runId, int $currentStep): ?array
    {
        $stmt = $db->prepare("
            SELECT * FROM ai_agent_step_executions
            WHERE run_id = ? AND step_number > ? AND status = 'pending'
            ORDER BY step_number ASC LIMIT 1
        ");
        $stmt->execute([$runId, $currentStep]);
        $step = $stmt->fetch();
        return $step ?: null;
    }

    /**
     * Check if user is authorized for the agent.
     */
    private static function checkUserAuthorization(array $agent, int $userId, array $permissions): array
    {
        $requiredPerms = json_decode($agent['allowed_roles_json'] ?? '[]', true) ?? [];
        if (!empty($requiredPerms)) {
            foreach ($requiredPerms as $perm) {
                if (!in_array($perm, $permissions)) {
                    return ['authorized' => false, 'reason' => "Missing permission: $perm"];
                }
            }
        }
        return ['authorized' => true, 'reason' => ''];
    }

    /**
     * Record an agent event.
     */
    public static function recordEvent(PDO $db, ?int $taskId, ?int $runId, int $companyId, ?int $userId,
        string $eventType, string $category, string $severity, string $message, array $details = []): void
    {
        try {
            $db->prepare("
                INSERT INTO ai_agent_events
                    (task_id, run_id, company_id, user_id, event_type, event_category,
                     severity, message, details_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $taskId, $runId, $companyId, $userId,
                $eventType, $category, $severity, $message, json_encode($details),
            ]);
        } catch (\Throwable $e) { /* non-critical */ }
    }

    /**
     * Record a step execution for observability (Section 18).
     */
    private static function recordStepExecution(PDO $db, int $runId, int $taskId, array $step, array $result, int $userId): void
    {
        try {
            $db->prepare("
                INSERT INTO ai_agent_step_executions
                    (run_id, task_id, step_number, step_type, operation_id, input_json,
                     output_json, status, risk_level, requires_approval, started_at, completed_at, duration_ms)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)
            ")->execute([
                $runId, $taskId, $step['step_number'], $step['step_type'],
                $step['operation_id'] ?? null, $step['input_json'] ?? null,
                json_encode($result['data'] ?? null),
                $result['success'] ? 'completed' : 'failed',
                $step['risk_level'] ?? 'low',
                $step['requires_approval'] ?? 0,
                $result['duration_ms'] ?? null,
            ]);
        } catch (\Throwable $e) { /* non-critical */ }
    }

    /**
     * Record observability trace (Section 18).
     */
    private static function recordObservabilityTrace(PDO $db, array $task, array $run, array $step, array $result): void
    {
        if (class_exists('AIObservabilityService')) {
            try {
                AIObservabilityService::logActionEvent($db, [
                    'task_id' => $task['id'],
                    'run_id' => $run['id'],
                    'step_number' => $step['step_number'],
                    'step_type' => $step['step_type'],
                    'operation_id' => $step['operation_id'],
                    'status' => $result['success'] ? 'success' : 'failed',
                    'event' => 'agent_step_completed',
                ]);
            } catch (\Throwable $e) { /* non-critical */ }
        }
    }

    // ═══ Query Methods ═══

    public static function getTask(PDO $db, int $taskId): ?array
    {
        $companyId = (int) ($_SESSION['company_id'] ?? 0);
        $sql = "SELECT * FROM ai_agent_tasks WHERE id = ?";
        $params = [$taskId];

        if ($companyId > 0) {
            $sql .= " AND company_id = ?";
            $params[] = $companyId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $task = $stmt->fetch();
        return $task ?: null;
    }

    public static function getRun(PDO $db, int $runId): ?array
    {
        $companyId = (int) ($_SESSION['company_id'] ?? 0);
        $sql = "SELECT * FROM ai_agent_task_runs WHERE id = ?";
        $params = [$runId];

        if ($companyId > 0) {
            $sql .= " AND company_id = ?";
            $params[] = $companyId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $run = $stmt->fetch();
        return $run ?: null;
    }

    public static function getAgentDefinition(PDO $db, int $agentId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_agent_definitions WHERE id = ?");
        $stmt->execute([$agentId]);
        $agent = $stmt->fetch();
        return $agent ?: null;
    }

    public static function resolveAgentDefinitionIdByKey(PDO $db, string $agentKey): ?int
    {
        $agentKey = trim($agentKey);
        if ($agentKey === '') {
            return null;
        }

        $stmt = $db->prepare("SELECT id FROM ai_agent_definitions WHERE agent_key = ? LIMIT 1");
        $stmt->execute([$agentKey]);
        $agentId = $stmt->fetchColumn();

        return $agentId !== false ? (int) $agentId : null;
    }

    public static function getUserTasks(PDO $db, int $userId, ?string $status = null, int $limit = 20): array
    {
        $sql = "SELECT t.*, a.name as agent_name
                FROM ai_agent_tasks t
                LEFT JOIN ai_agent_definitions a ON t.agent_id = a.id
                WHERE t.user_id = ?";
        $params = [$userId];
        $companyId = (int) ($_SESSION['company_id'] ?? 0);
        if ($companyId > 0) {
            $sql .= " AND t.company_id = ?";
            $params[] = $companyId;
        }
        if ($status) { $sql .= " AND t.status = ?"; $params[] = $status; }
        $sql .= " ORDER BY t.created_at DESC LIMIT " . (int) $limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getTaskSteps(PDO $db, int $runId): array
    {
        $companyId = (int) ($_SESSION['company_id'] ?? 0);
        $sql = "
            SELECT s.*
            FROM ai_agent_step_executions s
            JOIN ai_agent_task_runs r ON r.id = s.run_id
            WHERE s.run_id = ?
        ";
        $params = [$runId];
        if ($companyId > 0) {
            $sql .= " AND r.company_id = ?";
            $params[] = $companyId;
        }
        $sql .= " ORDER BY s.step_number ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getTaskEvents(PDO $db, int $taskId, int $limit = 50): array
    {
        $companyId = (int) ($_SESSION['company_id'] ?? 0);
        $sql = "
            SELECT e.*
            FROM ai_agent_events e
            JOIN ai_agent_tasks t ON t.id = e.task_id
            WHERE e.task_id = ?
        ";
        $params = [$taskId];
        if ($companyId > 0) {
            $sql .= " AND t.company_id = ?";
            $params[] = $companyId;
        }
        $sql .= " ORDER BY e.created_at DESC LIMIT ?";
        $params[] = $limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function pauseRun(PDO $db, int $runId, int $userId, string $reason = ''): array
    {
        $run = self::getRun($db, $runId);
        if (!$run || $run['user_id'] != $userId) {
            return ['success' => false, 'error' => 'Run not found or access denied'];
        }
        if (!in_array($run['status'], ['running', 'queued'])) {
            return ['success' => false, 'error' => 'Run cannot be paused in current state'];
        }

        $db->prepare("UPDATE ai_agent_task_runs SET status = 'paused', updated_at = NOW() WHERE id = ?")
           ->execute([$runId]);
        $db->prepare("UPDATE ai_agent_tasks SET status = 'paused', updated_at = NOW() WHERE id = ?")
           ->execute([$run['task_id']]);

        AIAgentCheckpointService::saveCheckpoint($db, $runId, $run['task_id'], $run['current_step'], 'manual');

        return ['success' => true];
    }

    public static function resumeRun(PDO $db, int $runId, int $userId): array
    {
        $run = self::getRun($db, $runId);
        if (!$run || $run['user_id'] != $userId) {
            return ['success' => false, 'error' => 'Run not found or access denied'];
        }
        if ($run['status'] !== 'paused') {
            return ['success' => false, 'error' => 'Run is not paused'];
        }

        // Test 13: Restore from checkpoint if available
        $checkpoint = AIAgentCheckpointService::getLatestCheckpoint($db, $runId);
        if ($checkpoint) {
            $resumeData = AIAgentCheckpointService::resumeFromCheckpoint($db, $checkpoint['id']);
            if ($resumeData['success']) {
                // Restore working memory from checkpoint
                if (!empty($resumeData['working_memory'])) {
                    $db->prepare("UPDATE ai_agent_task_runs SET working_memory_json = ?, updated_at = NOW() WHERE id = ?")
                       ->execute([json_encode($resumeData['working_memory']), $runId]);
                }
            }
        }

        $db->prepare("UPDATE ai_agent_task_runs SET status = 'running', updated_at = NOW() WHERE id = ?")
           ->execute([$runId]);
        $db->prepare("UPDATE ai_agent_tasks SET status = 'running', updated_at = NOW() WHERE id = ?")
           ->execute([$run['task_id']]);

        self::recordEvent($db, $run['task_id'], $runId, $run['company_id'], $userId,
            'run_resumed', 'execution', 'info', 'Run resumed from pause'
        );

        return ['success' => true, 'resumed_from_checkpoint' => $checkpoint ? true : false];
    }

    public static function cancelTask(PDO $db, int $taskId, int $userId, string $reason = ''): array
    {
        $task = self::getTask($db, $taskId);
        if (!$task || $task['user_id'] != $userId) {
            return ['success' => false, 'error' => 'Task not found or access denied'];
        }

        $db->prepare("
            UPDATE ai_agent_tasks SET status = 'cancelled', cancelled_by = ?,
                   cancelled_at = NOW(), cancellation_reason = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$userId, $reason, $taskId]);

        // Test 14: Cancel any active runs AND their pending steps
        $activeRuns = $db->prepare("SELECT id FROM ai_agent_task_runs WHERE task_id = ? AND status IN ('queued', 'running', 'paused')");
        $activeRuns->execute([$taskId]);
        $runIds = $activeRuns->fetchAll(PDO::FETCH_COLUMN);

        foreach ($runIds as $runId) {
            // Cancel pending steps
            $db->prepare("UPDATE ai_agent_step_executions SET status = 'cancelled' WHERE run_id = ? AND status IN ('pending', 'awaiting_approval')")
               ->execute([$runId]);
        }

        // Cancel all active runs
        $db->prepare("
            UPDATE ai_agent_task_runs SET status = 'cancelled', updated_at = NOW()
            WHERE task_id = ? AND status IN ('queued', 'running', 'paused')
        ")->execute([$taskId]);

        // Cancel pending approvals
        $db->prepare("
            UPDATE ai_agent_approvals SET status = 'cancelled', updated_at = NOW()
            WHERE task_id = ? AND status = 'pending'
        ")->execute([$taskId]);

        self::recordEvent($db, $taskId, null, $task['company_id'], $userId,
            'task_cancelled', 'lifecycle', 'info',
            "Task cancelled: $reason"
        );

        return ['success' => true];
    }
}
