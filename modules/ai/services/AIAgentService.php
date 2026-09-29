<?php
/**
 * AI F12 — Agent Service.
 *
 * Manages agent runs, steps, and integration with the AI conversation system.
 * Provides the high-level API for starting agents, tracking progress, and
 * handling the multi-step execution lifecycle.
 */
class AIAgentService
{
    const MAX_OBJECTIVE_LENGTH = 2000;
    const WORKING_MEMORY_MAX_ENTRIES = 20;
    const WORKING_MEMORY_MAX_CHARS = 8000;

    // ═══ Run Management ═══

    /**
     * Start a new agent run from a user objective.
     */
    public static function startAgent(PDO $db, int $userId, array $userPermissions, string $agentId, string $objective, ?int $conversationId = null): array
    {
        $objective = trim($objective);
        if ($objective === '') return ['success' => false, 'error' => 'Objective is required.'];
        if (strlen($objective) > self::MAX_OBJECTIVE_LENGTH) {
            return ['success' => false, 'error' => 'Objective too long (max ' . self::MAX_OBJECTIVE_LENGTH . ' characters).'];
        }

        $executor = new AIAgentExecutor($db, $userId, $userPermissions);
        return $executor->startRun($agentId, $objective, $conversationId);
    }

    /**
     * Execute the next step of an agent run.
     */
    public static function executeNextStep(PDO $db, int $userId, array $userPermissions, int $runId): array
    {
        $executor = new AIAgentExecutor($db, $userId, $userPermissions);
        $run = $executor->getRun($runId);

        if (!$run) return ['success' => false, 'error' => 'Run not found.'];
        if ($run['user_id'] != $userId) return ['success' => false, 'error' => 'Access denied.'];

        return $executor->executeStep($runId);
    }

    /**
     * Execute multiple steps in sequence (for autonomous operation).
     * Stops when: limit reached, awaiting confirmation, or needs AI input.
     */
    public static function executeAutonomously(PDO $db, int $userId, array $userPermissions, int $runId, int $maxIterations = 10): array
    {
        $executor = new AIAgentExecutor($db, $userId, $userPermissions);
        $results = [];

        for ($i = 0; $i < $maxIterations; $i++) {
            $run = $executor->getRun($runId);
            if (!$run) break;

            // Stop if not in an executable state
            if (!in_array($run['status'], ['queued', 'running'])) {
                break;
            }

            $result = $executor->executeStep($runId);
            $results[] = $result;

            // Stop on failure, limit reached, or needs AI input
            if (!$result['success'] || !empty($result['needs_ai_input']) || !empty($result['limit_reached'])) {
                break;
            }

            // Stop if awaiting confirmation
            if (!empty($result['result']['paused'])) {
                break;
            }
        }

        return ['success' => true, 'steps_executed' => count($results), 'results' => $results];
    }

    /**
     * Cancel an agent run.
     */
    public static function cancelRun(PDO $db, int $userId, int $runId): array
    {
        $executor = new AIAgentExecutor($db, $userId, []);
        $run = $executor->getRun($runId);
        if (!$run) return ['success' => false, 'error' => 'Run not found.'];
        if ($run['user_id'] != $userId) return ['success' => false, 'error' => 'Access denied.'];

        return $executor->cancelRun($runId);
    }

    // ═══ Query Operations ═══

    /**
     * Get a run with all its steps.
     */
    public static function getRunDetails(PDO $db, int $userId, int $runId): ?array
    {
        $executor = new AIAgentExecutor($db, $userId, []);
        $run = $executor->getRun($runId);
        if (!$run || $run['user_id'] != $userId) return null;

        $run['steps'] = $executor->getRunSteps($runId);
        $run['agent_def'] = AIAgentRegistry::get($run['agent_id']);

        return $run;
    }

    /**
     * Get all runs for a user.
     */
    public static function getUserRuns(PDO $db, int $userId, ?string $status = null, int $limit = 20): array
    {
        $sql = "SELECT r.*, a.name as agent_name, a.description as agent_description
                FROM ai_agent_runs r
                LEFT JOIN ai_agents a ON r.agent_id = a.agent_id
                WHERE r.user_id = ?";
        $params = [$userId];

        if ($status) {
            $sql .= " AND r.status = ?";
            $params[] = $status;
        }

        $safeLimit = (int) $limit;
        $sql .= " ORDER BY r.created_at DESC LIMIT {$safeLimit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get available agents for a user.
     */
    public static function getAvailableAgents(array $userPermissions): array
    {
        return AIAgentRegistry::getAccessible($userPermissions);
    }

    // ═══ Working Memory ═══

    /**
     * Add an observation to the run's working memory.
     */
    public static function addObservation(PDO $db, int $runId, string $type, string $content): void
    {
        $stmt = $db->prepare("SELECT working_memory_json FROM ai_agent_runs WHERE id = ?");
        $stmt->execute([$runId]);
        $memory = json_decode($stmt->fetchColumn() ?? '[]', true) ?? [];

        $memory[] = [
            'type'    => $type,
            'content' => substr($content, 0, 2000),
            'time'    => date('Y-m-d H:i:s'),
        ];

        // Enforce limits
        if (count($memory) > self::WORKING_MEMORY_MAX_ENTRIES) {
            $memory = array_slice($memory, -self::WORKING_MEMORY_MAX_ENTRIES);
        }

        $totalChars = 0;
        foreach ($memory as &$entry) {
            $totalChars += strlen($entry['content']);
            if ($totalChars > self::WORKING_MEMORY_MAX_CHARS) {
                $entry['content'] = substr($entry['content'], 0, 500) . '...[truncated]';
            }
        }
        unset($entry);

        $db->prepare("UPDATE ai_agent_runs SET working_memory_json = ?, updated_at = NOW() WHERE id = ?")
           ->execute([json_encode($memory), $runId]);
    }

    /**
     * Get formatted working memory for AI context.
     */
    public static function getWorkingMemoryForAI(PDO $db, int $runId): string
    {
        $stmt = $db->prepare("SELECT working_memory_json FROM ai_agent_runs WHERE id = ?");
        $stmt->execute([$runId]);
        $memory = json_decode($stmt->fetchColumn() ?? '[]', true) ?? [];

        if (empty($memory)) return '';

        $lines = ['[AGENT WORKING MEMORY]'];
        foreach ($memory as $entry) {
            $lines[] = "[{$entry['type']}] {$entry['content']}";
        }
        $lines[] = '[End of working memory]';

        return implode("\n", $lines);
    }

    // ═══ Audit ═══

    public static function logAgentEvent(PDO $db, int $userId, string $event, int $runId, ?array $extra = null): void
    {
        try {
            $details = array_merge(['run_id' => $runId, 'event' => $event], $extra ?? []);
            $db->prepare("
                INSERT INTO ai_audit_log (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, ?, 'agent_run', ?, ?, NOW())
            ")->execute([$userId, "agent_{$event}", $runId, json_encode($details)]);
        } catch (\Throwable $e) {}
    }
}
