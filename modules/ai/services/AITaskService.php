<?php
/**
 * AI F10 — Task Service.
 *
 * Manages AI tasks: creation, planning, execution, progress tracking.
 */

class AITaskService
{
    const MAX_STEPS_PER_TASK = 20;
    const MAX_CONCURRENT_TASKS = 5;
    const MAX_EXECUTION_TIME = 60; // seconds

    // ── Task Operations ──

    public static function createTask(PDO $db, int $userId, string $title, ?string $description, ?string $originalRequest, ?int $conversationId = null): array
    {
        // Check concurrent task limit
        $stmt = $db->prepare("SELECT COUNT(*) FROM ai_tasks WHERE user_id = ? AND status IN ('draft','planned','running','awaiting_confirmation','paused')");
        $stmt->execute([$userId]);
        if ((int) $stmt->fetchColumn() >= self::MAX_CONCURRENT_TASKS) {
            return ['success' => false, 'error' => 'Too many active tasks. Complete or cancel existing tasks first.'];
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO ai_tasks (user_id, conversation_id, title, description, original_request, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'draft', NOW())
            ");
            $stmt->execute([$userId, $conversationId, trim($title), $description, $originalRequest]);
            $taskId = (int) $db->lastInsertId();

            self::logTask($db, $userId, $taskId, 'created');
            return ['success' => true, 'id' => $taskId];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to create task.'];
        }
    }

    public static function getTaskById(PDO $db, int $taskId, int $userId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$taskId, $userId]);
        $task = $stmt->fetch();
        return $task ?: null;
    }

    public static function getUserTasks(PDO $db, int $userId, ?string $status = null, int $limit = 20): array
    {
        $sql = "SELECT * FROM ai_tasks WHERE user_id = ?";
        $params = [$userId];

        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ── Plan Operations ──

    public static function setPlan(PDO $db, int $taskId, int $userId, array $plan): array
    {
        $task = self::getTaskById($db, $taskId, $userId);
        if (!$task) return ['success' => false, 'error' => 'Task not found.'];
        if ($task['status'] !== 'draft') return ['success' => false, 'error' => 'Task is not in draft status.'];

        // Validate plan
        $validation = self::validatePlan($plan);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => 'Invalid plan: ' . $validation['error']];
        }

        try {
            // Save plan
            $stmt = $db->prepare("
                UPDATE ai_tasks SET plan_json = ?, total_steps = ?, status = 'planned', updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([json_encode($plan), count($plan['steps'] ?? []), $taskId, $userId]);

            // Create step records
            foreach (($plan['steps'] ?? []) as $step) {
                $db->prepare("
                    INSERT INTO ai_task_steps (task_id, step_number, title, description, step_type, tool_id, action_id, input_json, requires_confirmation, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ")->execute([
                    $taskId,
                    $step['step'],
                    $step['title'] ?? 'Step ' . $step['step'],
                    $step['purpose'] ?? $step['description'] ?? '',
                    $step['type'] ?? 'read',
                    $step['tool_id'] ?? null,
                    $step['action_id'] ?? null,
                    isset($step['input']) ? json_encode($step['input']) : null,
                    ($step['type'] === 'action') ? 1 : 0,
                ]);
            }

            self::logTask($db, $userId, $taskId, 'planned');
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to save plan.'];
        }
    }

    public static function getTaskSteps(PDO $db, int $taskId): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_task_steps WHERE task_id = ? ORDER BY step_number ASC");
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }

    // ── Task Execution ──

    public static function startTask(PDO $db, int $taskId, int $userId, array $userPermissions): array
    {
        $task = self::getTaskById($db, $taskId, $userId);
        if (!$task) return ['success' => false, 'error' => 'Task not found.'];
        if (!in_array($task['status'], ['planned', 'paused'])) {
            return ['success' => false, 'error' => 'Task cannot be started in current status.'];
        }

        try {
            $db->prepare("
                UPDATE ai_tasks SET status = 'running', started_at = NOW(), updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ")->execute([$taskId, $userId]);

            self::logTask($db, $userId, $taskId, 'started');

            // Execute steps
            return self::executeNextStep($db, $taskId, $userId, $userPermissions);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to start task.'];
        }
    }

    public static function executeNextStep(PDO $db, int $taskId, int $userId, array $userPermissions): array
    {
        $task = self::getTaskById($db, $taskId, $userId);
        if (!$task || $task['status'] !== 'running') {
            return ['success' => false, 'error' => 'Task is not running.'];
        }

        // Get next pending step
        $steps = self::getTaskSteps($db, $taskId);
        $nextStep = null;
        foreach ($steps as $step) {
            if ($step['status'] === 'pending') {
                $nextStep = $step;
                break;
            }
        }

        if (!$nextStep) {
            // All steps completed
            $db->prepare("
                UPDATE ai_tasks SET status = 'completed', completed_at = NOW(), current_step = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([count($steps), $taskId]);

            self::logTask($db, $userId, $taskId, 'completed');
            return ['success' => true, 'completed' => true, 'message' => 'Task completed successfully.'];
        }

        // Update current step
        $db->prepare("UPDATE ai_tasks SET current_step = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$nextStep['step_number'], $taskId]);

        // Mark step as running
        $db->prepare("UPDATE ai_task_steps SET status = 'running', started_at = NOW() WHERE id = ?")
           ->execute([$nextStep['id']]);

        self::logTask($db, $userId, $taskId, 'step_started', ['step' => $nextStep['step_number'], 'title' => $nextStep['title']]);

        // Execute based on step type
        if ($nextStep['step_type'] === 'action') {
            // Action step — pause and require confirmation
            $db->prepare("UPDATE ai_task_steps SET status = 'awaiting_confirmation' WHERE id = ?")
               ->execute([$nextStep['id']]);

            $db->prepare("UPDATE ai_tasks SET status = 'awaiting_confirmation', updated_at = NOW() WHERE id = ?")
               ->execute([$taskId]);

            // Create pending action
            $actionExecutor = new AIActionExecutor($db, $userId, $userPermissions);
            $actionResult = $actionExecutor->propose(
                $nextStep['action_id'],
                json_decode($nextStep['input_json'] ?? '{}', true),
                $task['conversation_id'] ?? 0
            );

            self::logTask($db, $userId, $taskId, 'paused', ['reason' => 'awaiting_confirmation', 'step' => $nextStep['step_number']]);

            return [
                'success' => true,
                'paused' => true,
                'reason' => 'awaiting_confirmation',
                'step' => $nextStep['step_number'],
                'action_result' => $actionResult,
            ];
        }

        if ($nextStep['step_type'] === 'read') {
            // Read step — execute tool
            $toolExecutor = new AIToolExecutor($db, $userId, $userPermissions);
            $toolResult = $toolExecutor->execute(
                $nextStep['tool_id'],
                json_decode($nextStep['input_json'] ?? '{}', true),
                $task['conversation_id'] ?? 0
            );

            // Store result
            $db->prepare("
                UPDATE ai_task_steps SET status = ?, output_json = ?, completed_at = NOW(), error_message = ?
                WHERE id = ?
            ")->execute([
                $toolResult['success'] ? 'completed' : 'failed',
                $toolResult['success'] ? json_encode(['result' => $toolResult['result']]) : null,
                $toolResult['success'] ? null : $toolResult['error'],
                $nextStep['id'],
            ]);

            if (!$toolResult['success']) {
                $db->prepare("UPDATE ai_tasks SET status = 'failed', failed_at = NOW(), result_summary = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$toolResult['error'], $taskId]);
                self::logTask($db, $userId, $taskId, 'failed', ['error' => $toolResult['error']]);
                return ['success' => false, 'error' => 'Step failed: ' . $toolResult['error']];
            }

            self::logTask($db, $userId, $taskId, 'step_completed', ['step' => $nextStep['step_number']]);

            // Continue to next step
            return self::executeNextStep($db, $taskId, $userId, $userPermissions);
        }

        if ($nextStep['step_type'] === 'reasoning') {
            // Reasoning step — mark as completed (AI handles reasoning in chat)
            $db->prepare("UPDATE ai_task_steps SET status = 'completed', completed_at = NOW() WHERE id = ?")
               ->execute([$nextStep['id']]);

            self::logTask($db, $userId, $taskId, 'step_completed', ['step' => $nextStep['step_number']]);

            // Continue to next step
            return self::executeNextStep($db, $taskId, $userId, $userPermissions);
        }

        return ['success' => false, 'error' => 'Unknown step type.'];
    }

    public static function confirmStep(PDO $db, int $taskId, int $userId, int $pendingActionId, string $token, array $userPermissions): array
    {
        $task = self::getTaskById($db, $taskId, $userId);
        if (!$task || $task['status'] !== 'awaiting_confirmation') {
            return ['success' => false, 'error' => 'Task is not awaiting confirmation.'];
        }

        // Execute the pending action
        $actionExecutor = new AIActionExecutor($db, $userId, $userPermissions);
        $result = $actionExecutor->confirm($pendingActionId, $token);

        if ($result['success']) {
            // Mark step as completed
            $stmt = $db->prepare("UPDATE ai_task_steps SET status = 'completed', output_json = ?, completed_at = NOW() WHERE task_id = ? AND status = 'awaiting_confirmation' LIMIT 1");
            $stmt->execute([json_encode($result), $taskId]);

            // Resume task
            $db->prepare("UPDATE ai_tasks SET status = 'running', updated_at = NOW() WHERE id = ?")->execute([$taskId]);
            self::logTask($db, $userId, $taskId, 'step_completed', ['step' => 'action']);

            // Continue to next step
            return self::executeNextStep($db, $taskId, $userId, $userPermissions);
        } else {
            // Action failed
            $db->prepare("UPDATE ai_task_steps SET status = 'failed', error_message = ? WHERE task_id = ? AND status = 'awaiting_confirmation' LIMIT 1")
               ->execute([$result['error'], $taskId]);
            $db->prepare("UPDATE ai_tasks SET status = 'failed', failed_at = NOW(), result_summary = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$result['error'], $taskId]);
            self::logTask($db, $userId, $taskId, 'failed', ['error' => $result['error']]);
            return ['success' => false, 'error' => $result['error']];
        }
    }

    public static function cancelTask(PDO $db, int $taskId, int $userId): array
    {
        $task = self::getTaskById($db, $taskId, $userId);
        if (!$task) return ['success' => false, 'error' => 'Task not found.'];
        if (in_array($task['status'], ['completed', 'cancelled'])) {
            return ['success' => false, 'error' => 'Task already finished.'];
        }

        // Cancel pending steps
        $db->prepare("UPDATE ai_task_steps SET status = 'cancelled' WHERE task_id = ? AND status IN ('pending', 'awaiting_confirmation')")
           ->execute([$taskId]);

        // Cancel any pending actions
        $pendingSteps = $db->prepare("SELECT id FROM ai_task_steps WHERE task_id = ? AND status = 'awaiting_confirmation'");
        $pendingSteps->execute([$taskId]);
        // Pending actions are invalidated by task cancellation

        $db->prepare("UPDATE ai_tasks SET status = 'cancelled', updated_at = NOW() WHERE id = ?")
           ->execute([$taskId]);

        self::logTask($db, $userId, $taskId, 'cancelled');
        return ['success' => true];
    }

    // ── Plan Validation ──

    public static function validatePlan(array $plan): array
    {
        $steps = $plan['steps'] ?? [];
        if (empty($steps)) {
            return ['valid' => false, 'error' => 'Plan must have at least one step.'];
        }

        if (count($steps) > self::MAX_STEPS_PER_TASK) {
            return ['valid' => false, 'error' => 'Too many steps (max ' . self::MAX_STEPS_PER_TASK . ').'];
        }

        foreach ($steps as $i => $step) {
            $stepNum = $step['step'] ?? ($i + 1);
            $type = $step['type'] ?? 'read';

            if (!in_array($type, ['read', 'action', 'reasoning'])) {
                return ['valid' => false, 'error' => "Invalid step type: {$type}"];
            }

            if ($type === 'read' && empty($step['tool_id'])) {
                return ['valid' => false, 'error' => "Read step {$stepNum} must have a tool_id."];
            }

            if ($type === 'action' && empty($step['action_id'])) {
                return ['valid' => false, 'error' => "Action step {$stepNum} must have an action_id."];
            }

            // Validate tool/action exists
            if ($type === 'read' && !AIToolRegistry::get($step['tool_id'])) {
                return ['valid' => false, 'error' => "Unknown tool: {$step['tool_id']}"];
            }

            if ($type === 'action' && !AIActionRegistry::get($step['action_id'])) {
                return ['valid' => false, 'error' => "Unknown action: {$step['action_id']}"];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    // ── Audit ──

    private static function logTask(PDO $db, int $userId, int $taskId, string $event, ?array $extra = null): void
    {
        try {
            $details = array_merge(['task_id' => $taskId, 'event' => $event], $extra ?? []);
            $db->prepare("
                INSERT INTO ai_audit_log (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, ?, 'task', ?, ?, NOW())
            ")->execute([$userId, "task_{$event}", $taskId, json_encode($details)]);
        } catch (\Throwable $e) {}
    }
}
