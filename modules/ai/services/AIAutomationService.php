<?php
/**
 * AI F10 — Automation Service.
 *
 * Manages automation definitions and manual runs.
 */

class AIAutomationService
{
    const MAX_AUTOMATIONS_PER_USER = 20;
    const MAX_WORKFLOW_STEPS = 10;

    // ── Automation CRUD ──

    public static function createAutomation(PDO $db, int $userId, string $name, ?string $description, string $triggerType, array $workflow): array
    {
        // Check limit
        $stmt = $db->prepare("SELECT COUNT(*) FROM ai_automations WHERE created_by = ?");
        $stmt->execute([$userId]);
        if ((int) $stmt->fetchColumn() >= self::MAX_AUTOMATIONS_PER_USER) {
            return ['success' => false, 'error' => 'Too many automations. Max ' . self::MAX_AUTOMATIONS_PER_USER . '.'];
        }

        // Validate workflow
        $validation = self::validateWorkflow($workflow);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => 'Invalid workflow: ' . $validation['error']];
        }

        if (!in_array($triggerType, ['manual', 'scheduled', 'event'])) {
            return ['success' => false, 'error' => 'Invalid trigger type.'];
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO ai_automations (name, description, created_by, trigger_type, trigger_config_json, workflow_json, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                trim($name),
                $description,
                $userId,
                $triggerType,
                null, // trigger_config_json — not used yet
                json_encode($workflow),
            ]);

            $id = (int) $db->lastInsertId();
            self::logAutomation($db, $userId, $id, 'created');
            return ['success' => true, 'id' => $id];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to create automation.'];
        }
    }

    public static function getAutomationById(PDO $db, int $id, int $userId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_automations WHERE id = ? AND created_by = ?");
        $stmt->execute([$id, $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function getUserAutomations(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_automations WHERE created_by = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function updateAutomation(PDO $db, int $id, int $userId, array $updates): array
    {
        $auto = self::getAutomationById($db, $id, $userId);
        if (!$auto) return ['success' => false, 'error' => 'Automation not found.'];

        $fields = [];
        $params = [];

        if (isset($updates['name'])) {
            $fields[] = 'name = ?';
            $params[] = trim($updates['name']);
        }
        if (isset($updates['description'])) {
            $fields[] = 'description = ?';
            $params[] = $updates['description'];
        }
        if (isset($updates['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = (int) $updates['is_active'];
            $event = $updates['is_active'] ? 'activated' : 'deactivated';
            self::logAutomation($db, $userId, $id, $event);
        }
        if (isset($updates['workflow'])) {
            $validation = self::validateWorkflow($updates['workflow']);
            if (!$validation['valid']) {
                return ['success' => false, 'error' => 'Invalid workflow: ' . $validation['error']];
            }
            $fields[] = 'workflow_json = ?';
            $params[] = json_encode($updates['workflow']);
        }

        if (empty($fields)) {
            return ['success' => false, 'error' => 'No fields to update.'];
        }

        $fields[] = 'updated_at = NOW()';
        $params[] = $id;
        $params[] = $userId;

        $db->prepare("UPDATE ai_automations SET " . implode(', ', $fields) . " WHERE id = ? AND created_by = ?")->execute($params);
        self::logAutomation($db, $userId, $id, 'updated');
        return ['success' => true];
    }

    public static function deleteAutomation(PDO $db, int $id, int $userId): array
    {
        $auto = self::getAutomationById($db, $id, $userId);
        if (!$auto) return ['success' => false, 'error' => 'Automation not found.'];

        $db->prepare("DELETE FROM ai_automations WHERE id = ? AND created_by = ?")->execute([$id, $userId]);
        self::logAutomation($db, $userId, $id, 'deleted');
        return ['success' => true];
    }

    // ── F13: Trigger Management ──

    /**
     * Set a trigger for an automation.
     */
    public static function setTrigger(PDO $db, int $automationId, int $userId, string $triggerType, array $config): array
    {
        return AIAutomationSchedulerService::setTrigger($db, $automationId, $triggerType, $config, $userId);
    }

    /**
     * Toggle a trigger on/off.
     */
    public static function toggleTrigger(PDO $db, int $automationId, int $userId, string $triggerType, bool $enabled): array
    {
        return AIAutomationSchedulerService::toggleTrigger($db, $automationId, $triggerType, $enabled, $userId);
    }

    /**
     * Get triggers for an automation.
     */
    public static function getTriggers(PDO $db, int $automationId): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_automation_triggers WHERE automation_id = ? ORDER BY trigger_type");
        $stmt->execute([$automationId]);
        return $stmt->fetchAll();
    }

    /**
     * Get safety classification for an automation.
     */
    public static function getSafetyClassification(PDO $db, int $automationId): string
    {
        return AIAutomationSchedulerService::classifyWorkflow($db, $automationId);
    }

    /**
     * Get run statistics for an automation.
     */
    public static function getRunStats(PDO $db, int $automationId): array
    {
        $stmt = $db->prepare("
            SELECT status, COUNT(*) as count
            FROM ai_automation_runs
            WHERE automation_id = ?
            GROUP BY status
        ");
        $stmt->execute([$automationId]);
        $stats = [];
        while ($row = $stmt->fetch()) {
            $stats[$row['status']] = (int) $row['count'];
        }
        return $stats;
    }

    /**
     * Get run history for an automation.
     */
    public static function getRunHistory(PDO $db, int $automationId, int $limit = 20): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_automation_runs WHERE automation_id = ? ORDER BY created_at DESC LIMIT " . (int) $limit);
        $stmt->execute([$automationId]);
        return $stmt->fetchAll();
    }

    // ── Manual Run ──

    public static function manualRun(PDO $db, int $automationId, int $userId, array $userPermissions): array
    {
        $auto = self::getAutomationById($db, $automationId, $userId);
        if (!$auto) return ['success' => false, 'error' => 'Automation not found.'];
        if (!$auto['is_active']) return ['success' => false, 'error' => 'Automation is not active.'];

        $workflow = json_decode($auto['workflow_json'], true);
        if (!$workflow || empty($workflow['steps'])) {
            return ['success' => false, 'error' => 'Invalid workflow.'];
        }

        // Create a task from the automation workflow
        $taskResult = AITaskService::createTask(
            $db, $userId,
            "Run: {$auto['name']}",
            $auto['description'] ?? "Manual run of {$auto['name']}",
            null
        );

        if (!$taskResult['success']) {
            return ['success' => false, 'error' => $taskResult['error']];
        }

        $taskId = $taskResult['id'];

        // Set the plan from workflow
        $planResult = AITaskService::setPlan($db, $taskId, $userId, $workflow);
        if (!$planResult['success']) {
            return ['success' => false, 'error' => $planResult['error']];
        }

        // Create automation run record
        try {
            $db->prepare("
                INSERT INTO ai_automation_runs (automation_id, task_id, status, started_at, triggered_by, created_at)
                VALUES (?, ?, 'running', NOW(), ?, NOW())
            ")->execute([$automationId, $taskId, $userId]);
            $runId = (int) $db->lastInsertId();
        } catch (\Throwable $e) {
            $runId = null;
        }

        // Update last run time
        $db->prepare("UPDATE ai_automations SET last_run_at = NOW() WHERE id = ?")->execute([$automationId]);

        self::logAutomation($db, $userId, $automationId, 'manually_run', ['task_id' => $taskId]);

        return [
            'success' => true,
            'task_id' => $taskId,
            'run_id' => $runId,
        ];
    }

    // ── Workflow Validation ──

    public static function validateWorkflow(array $workflow): array
    {
        $steps = $workflow['steps'] ?? [];
        if (empty($steps)) {
            return ['valid' => false, 'error' => 'Workflow must have at least one step.'];
        }

        if (count($steps) > self::MAX_WORKFLOW_STEPS) {
            return ['valid' => false, 'error' => 'Too many steps (max ' . self::MAX_WORKFLOW_STEPS . ').'];
        }

        foreach ($steps as $i => $step) {
            $type = $step['type'] ?? 'read';
            if (!in_array($type, ['read', 'action', 'reasoning'])) {
                return ['valid' => false, 'error' => "Invalid step type: {$type}"];
            }

            if ($type === 'read' && empty($step['tool_id'])) {
                return ['valid' => false, 'error' => "Step " . ($i + 1) . " must have a tool_id."];
            }

            if ($type === 'action' && empty($step['action_id'])) {
                return ['valid' => false, 'error' => "Step " . ($i + 1) . " must have an action_id."];
            }

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

    private static function logAutomation(PDO $db, int $userId, int $automationId, string $event, ?array $extra = null): void
    {
        try {
            $details = array_merge(['automation_id' => $automationId, 'event' => $event], $extra ?? []);
            $db->prepare("
                INSERT INTO ai_audit_log (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, ?, 'automation', ?, ?, NOW())
            ")->execute([$userId, "automation_{$event}", $automationId, json_encode($details)]);
        } catch (\Throwable $e) {}
    }
}
