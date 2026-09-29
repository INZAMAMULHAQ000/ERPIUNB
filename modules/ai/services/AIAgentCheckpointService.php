<?php
/**
 * AI F25 — Agent Checkpoint Service.
 *
 * Manages durable checkpoints that allow agents to safely resume
 * after interruptions, approvals, or recoverable errors.
 *
 * Covers Section 8: Checkpoints.
 */
class AIAgentCheckpointService
{
    /**
     * Save a checkpoint for a running agent.
     */
    public static function saveCheckpoint(
        PDO $db,
        int $runId,
        int $taskId,
        int $stepNumber,
        string $type = 'auto',
        array $state = []
    ): array {
        try {
            // Get current working memory
            $stmt = $db->prepare("SELECT working_memory_json FROM ai_agent_task_runs WHERE id = ?");
            $stmt->execute([$runId]);
            $workingMemory = $stmt->fetchColumn();

            // Get completed steps
            $stmt = $db->prepare("
                SELECT step_number, status, operation_id, step_type
                FROM ai_agent_step_executions
                WHERE run_id = ? AND status = 'completed'
                ORDER BY step_number ASC
            ");
            $stmt->execute([$runId]);
            $completedSteps = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get pending approvals
            $stmt = $db->prepare("
                SELECT id, action_type, status FROM ai_agent_approvals
                WHERE run_id = ? AND status = 'pending'
            ");
            $stmt->execute([$runId]);
            $pendingApprovals = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("
                INSERT INTO ai_agent_checkpoints
                    (run_id, task_id, step_number, checkpoint_type, state_json,
                     completed_steps_json, working_memory_snapshot,
                     pending_approvals_json, resume_from_step)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $runId,
                $taskId,
                $stepNumber,
                $type,
                json_encode($state),
                json_encode($completedSteps),
                $workingMemory,
                json_encode($pendingApprovals),
                $stepNumber + 1, // Resume from the NEXT step
            ]);

            return ['success' => true, 'checkpoint_id' => (int) $db->lastInsertId()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get the latest valid checkpoint for a run.
     */
    public static function getLatestCheckpoint(PDO $db, int $runId): ?array
    {
        $stmt = $db->prepare("
            SELECT * FROM ai_agent_checkpoints
            WHERE run_id = ? AND can_resume = 1
            ORDER BY step_number DESC
            LIMIT 1
        ");
        $stmt->execute([$runId]);
        $checkpoint = $stmt->fetch();
        return $checkpoint ?: null;
    }

    /**
     * Get the latest checkpoint for a task.
     */
    public static function getLatestTaskCheckpoint(PDO $db, int $taskId): ?array
    {
        $stmt = $db->prepare("
            SELECT c.* FROM ai_agent_checkpoints c
            JOIN ai_agent_task_runs r ON c.run_id = r.id
            WHERE c.task_id = ? AND c.can_resume = 1
            ORDER BY c.step_number DESC
            LIMIT 1
        ");
        $stmt->execute([$taskId]);
        $checkpoint = $stmt->fetch();
        return $checkpoint ?: null;
    }

    /**
     * Resume execution from a checkpoint.
     * Returns the state needed to continue.
     */
    public static function resumeFromCheckpoint(PDO $db, int $checkpointId): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_agent_checkpoints WHERE id = ?");
        $stmt->execute([$checkpointId]);
        $checkpoint = $stmt->fetch();

        if (!$checkpoint) {
            return ['success' => false, 'error' => 'Checkpoint not found'];
        }

        if (!$checkpoint['can_resume']) {
            return ['success' => false, 'error' => 'Checkpoint is not resumable'];
        }

        return [
            'success' => true,
            'resume_from_step' => (int) $checkpoint['resume_from_step'],
            'state' => json_decode($checkpoint['state_json'] ?? '{}', true),
            'completed_steps' => json_decode($checkpoint['completed_steps_json'] ?? '[]', true),
            'working_memory' => json_decode($checkpoint['working_memory_snapshot'] ?? '[]', true),
            'pending_approvals' => json_decode($checkpoint['pending_approvals_json'] ?? '[]', true),
        ];
    }

    /**
     * Invalidate a checkpoint (e.g., after a critical failure).
     */
    public static function invalidateCheckpoint(PDO $db, int $checkpointId): bool
    {
        try {
            $db->prepare("UPDATE ai_agent_checkpoints SET can_resume = 0 WHERE id = ?")
               ->execute([$checkpointId]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get all checkpoints for a task (for UI display).
     */
    public static function getTaskCheckpoints(PDO $db, int $taskId): array
    {
        $stmt = $db->prepare("
            SELECT c.*, r.user_id
            FROM ai_agent_checkpoints c
            JOIN ai_agent_task_runs r ON c.run_id = r.id
            WHERE c.task_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$taskId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
