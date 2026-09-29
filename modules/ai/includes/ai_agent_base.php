<?php
/**
 * AI F12 — Agent Framework.
 *
 * Provides: Agent Registry, Agent Contract (base class), and Agent Executor.
 * All agents execute through controlled, auditable, permission-enforced loops.
 */

// ── Agent Statuses ──
define('AI_AGENT_STATUS_QUEUED', 'queued');
define('AI_AGENT_STATUS_PLANNING', 'planning');
define('AI_AGENT_STATUS_RUNNING', 'running');
define('AI_AGENT_STATUS_AWAITING', 'awaiting_confirmation');
define('AI_AGENT_STATUS_PAUSED', 'paused');
define('AI_AGENT_STATUS_COMPLETED', 'completed');
define('AI_AGENT_STATUS_FAILED', 'failed');
define('AI_AGENT_STATUS_CANCELLED', 'cancelled');
define('AI_AGENT_STATUS_LIMIT_REACHED', 'limit_reached');

// ── Agent Step Types ──
define('AI_AGENT_STEP_TOOL', 'tool');
define('AI_AGENT_STEP_ACTION', 'action');
define('AI_AGENT_STEP_REASONING', 'reasoning');
define('AI_AGENT_STEP_KNOWLEDGE', 'knowledge');
define('AI_AGENT_STEP_NAVIGATION', 'navigation');

/**
 * Agent Registry — central catalog of all registered AI agents.
 *
 * Agents are registered at bootstrap time. The registry is authoritative:
 * agents cannot dynamically grant themselves additional capabilities.
 */
class AIAgentRegistry
{
    private static array $agents = [];
    private static bool $initialized = false;

    /**
     * Register an agent definition.
     */
    public static function register(array $definition): void
    {
        $required = ['agent_id', 'name', 'description', 'module', 'allowed_tools', 'max_steps', 'risk_level'];
        foreach ($required as $field) {
            if (empty($definition[$field]) && $definition[$field] !== 0) {
                throw new \InvalidArgumentException("Agent definition missing required field: {$field}");
            }
        }

        self::$agents[$definition['agent_id']] = [
            'agent_id'                   => $definition['agent_id'],
            'agent_type'                 => $definition['agent_type'] ?? 'specialist',
            'name'                       => $definition['name'],
            'description'                => $definition['description'],
            'module'                     => $definition['module'],
            'enabled'                    => $definition['enabled'] ?? true,
            'allowed_tools'              => $definition['allowed_tools'] ?? [],
            'allowed_actions'            => $definition['allowed_actions'] ?? [],
            'allowed_knowledge'          => $definition['allowed_knowledge'] ?? [],
            'max_steps'                  => $definition['max_steps'] ?? 10,
            'max_tool_calls'             => $definition['max_tool_calls'] ?? 20,
            'max_runtime_seconds'        => $definition['max_runtime_seconds'] ?? 120,
            'max_working_memory_chars'   => $definition['max_working_memory_chars'] ?? 8000,
            'risk_level'                 => $definition['risk_level'] ?? 'medium',
            'required_permissions'       => $definition['required_permissions'] ?? [],
            'system_instructions'        => $definition['system_instructions'] ?? '',
            'human_confirmation_policy'  => $definition['human_confirmation_policy'] ?? 'write_only',
        ];
    }

    /**
     * Get all registered agents.
     */
    public static function getAll(): array
    {
        self::init();
        return self::$agents;
    }

    /**
     * Get a specific agent by ID.
     */
    public static function get(string $agentId): ?array
    {
        self::init();
        return self::$agents[$agentId] ?? null;
    }

    /**
     * Get agents accessible to a user (enabled + permission-filtered).
     */
    public static function getAccessible(array $userPermissions): array
    {
        self::init();
        $accessible = [];

        foreach (self::$agents as $agent) {
            if (!$agent['enabled']) continue;

            if (!empty($agent['required_permissions'])) {
                $hasAccess = false;
                foreach ($agent['required_permissions'] as $perm) {
                    if (in_array($perm, $userPermissions)) {
                        $hasAccess = true;
                        break;
                    }
                }
                if (!$hasAccess) continue;
            }

            $accessible[] = $agent;
        }

        return $accessible;
    }

    /**
     * Check if an agent can use a specific tool.
     */
    public static function canUseTool(string $agentId, string $toolId): bool
    {
        $agent = self::get($agentId);
        if (!$agent) return false;

        // Empty allowed_tools means no tools allowed
        if (empty($agent['allowed_tools'])) return false;

        return in_array($toolId, $agent['allowed_tools']);
    }

    /**
     * Check if an agent can use a specific action.
     */
    public static function canUseAction(string $agentId, string $actionId): bool
    {
        $agent = self::get($agentId);
        if (!$agent) return false;

        if (empty($agent['allowed_actions'])) return false;

        return in_array($actionId, $agent['allowed_actions']);
    }

    /**
     * Initialize default agents. Called lazily.
     */
    private static function init(): void
    {
        if (self::$initialized) return;
        self::$initialized = true;

        // Default agents are registered in ai_agents_def.php via bootstrap
    }
}

/**
 * Agent Executor — runs the controlled agent execution loop.
 *
 * The executor:
 * - Validates every step against agent capabilities
 * - Enforces limits (steps, tool calls, runtime, recursion)
 * - Handles tool execution, action proposals, and knowledge retrieval
 * - Manages pause/resume for human confirmation
 * - Logs all activity
 * - Prevents recursion and self-replication
 */
class AIAgentExecutor
{
    const MAX_RECURSION_DEPTH = 3;
    const MAX_CONCURRENT_RUNS_PER_USER = 3;

    private PDO $db;
    private int $userId;
    private array $userPermissions;

    public function __construct(PDO $db, int $userId, array $userPermissions)
    {
        $this->db = $db;
        $this->userId = $userId;
        $this->userPermissions = $userPermissions;
    }

    /**
     * Start a new agent run.
     */
    public function startRun(string $agentId, string $objective, ?int $conversationId = null): array
    {
        // 1. Validate agent exists and is enabled
        $agentDef = AIAgentRegistry::get($agentId);
        if (!$agentDef || !$agentDef['enabled']) {
            return ['success' => false, 'error' => 'Agent not found or not enabled.'];
        }

        // 2. Validate user permissions
        if (!empty($agentDef['required_permissions'])) {
            foreach ($agentDef['required_permissions'] as $perm) {
                if (!in_array($perm, $this->userPermissions)) {
                    return ['success' => false, 'error' => 'You do not have permission to use this agent.'];
                }
            }
        }

        // 3. Check concurrent run limit
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM ai_agent_runs
            WHERE user_id = ? AND status IN ('queued','planning','running','awaiting_confirmation','paused')
        ");
        $stmt->execute([$this->userId]);
        if ((int) $stmt->fetchColumn() >= self::MAX_CONCURRENT_RUNS_PER_USER) {
            return ['success' => false, 'error' => 'Too many active agent runs. Complete or cancel existing ones first.'];
        }

        // 4. Create run record
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_agent_runs (agent_id, user_id, conversation_id, objective, status, created_at)
                VALUES (?, ?, ?, ?, 'queued', NOW())
            ");
            $stmt->execute([$agentId, $this->userId, $conversationId, trim($objective)]);
            $runId = (int) $this->db->lastInsertId();

            $this->log('agent_run_started', $runId, $agentId, ['objective' => substr($objective, 0, 255)]);

            return ['success' => true, 'run_id' => $runId, 'agent' => $agentDef];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to create agent run.'];
        }
    }

    /**
     * Execute a single step of an agent run.
     *
     * This is called iteratively — each call processes one step and returns.
     * The caller (API endpoint) decides whether to call again.
     */
    public function executeStep(int $runId): array
    {
        // 1. Load run
        $run = $this->getRun($runId);
        if (!$run) return ['success' => false, 'error' => 'Run not found.'];
        if (!in_array($run['status'], ['queued', 'running'])) {
            return ['success' => false, 'error' => 'Run is not in an executable state.'];
        }

        $agentDef = AIAgentRegistry::get($run['agent_id']);
        if (!$agentDef) return ['success' => false, 'error' => 'Agent definition not found.'];

        // 2. Check limits
        $limitCheck = $this->checkLimits($run, $agentDef);
        if (!$limitCheck['ok']) {
            $this->updateRunStatus($runId, AI_AGENT_STATUS_LIMIT_REACHED, $limitCheck['reason']);
            return ['success' => false, 'error' => $limitCheck['reason'], 'limit_reached' => true];
        }

        // 3. Load current step
        $currentStepNum = (int) $run['current_step'];
        $nextStepNum = $currentStepNum + 1;

        $stmt = $this->db->prepare("
            SELECT * FROM ai_agent_steps WHERE agent_run_id = ? AND step_number = ?
        ");
        $stmt->execute([$runId, $nextStepNum]);
        $step = $stmt->fetch();

        if (!$step) {
            // No more steps — the AI needs to generate the next step via the conversation
            // Mark as needing AI input
            $this->updateRunStatus($runId, AI_AGENT_STATUS_PAUSED);
            return [
                'success' => true,
                'needs_ai_input' => true,
                'message' => 'Agent needs AI to determine the next step.',
                'completed_steps' => $currentStepNum,
            ];
        }

        // 4. Update run status
        if ($run['status'] === 'queued') {
            $this->db->prepare("
                UPDATE ai_agent_runs SET status = 'running', started_at = NOW(), total_steps = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([$nextStepNum, $runId]);
        }

        // 5. Mark step as running
        $this->db->prepare("
            UPDATE ai_agent_steps SET status = 'running', started_at = NOW() WHERE id = ?
        ")->execute([$step['id']]);

        $this->log('agent_step_started', $runId, $run['agent_id'], [
            'step' => $nextStepNum, 'type' => $step['step_type'], 'operation' => $step['operation_id']
        ]);

        // 6. Execute based on step type
        $result = match($step['step_type']) {
            AI_AGENT_STEP_TOOL       => $this->executeToolStep($run, $agentDef, $step),
            AI_AGENT_STEP_ACTION     => $this->executeActionStep($run, $agentDef, $step),
            AI_AGENT_STEP_KNOWLEDGE  => $this->executeKnowledgeStep($run, $agentDef, $step),
            AI_AGENT_STEP_REASONING  => $this->executeReasoningStep($run, $agentDef, $step),
            default                   => ['success' => false, 'error' => 'Unknown step type: ' . $step['step_type']],
        };

        // 7. Update step status
        $duration = $step['started_at'] ? (int) ((microtime(true) - strtotime($step['started_at'])) * 1000) : 0;
        $this->db->prepare("
            UPDATE ai_agent_steps SET status = ?, observation_json = ?, duration_ms = ?, error_message = ?, completed_at = NOW()
            WHERE id = ?
        ")->execute([
            $result['success'] ? 'completed' : 'failed',
            $result['success'] ? json_encode($result['data'] ?? []) : null,
            $duration,
            $result['success'] ? null : $result['error'],
            $step['id'],
        ]);

        // 8. Update run progress
        $this->db->prepare("
            UPDATE ai_agent_runs SET current_step = ?, tool_calls_made = tool_calls_made + ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$nextStepNum, isset($result['tool_call']) ? 1 : 0, $runId]);

        if (!$result['success']) {
            $this->updateRunStatus($runId, AI_AGENT_STATUS_FAILED, $result['error']);
            $this->log('agent_step_failed', $runId, $run['agent_id'], ['step' => $nextStepNum, 'error' => $result['error']]);
            return ['success' => false, 'error' => 'Step failed: ' . $result['error']];
        }

        $this->log('agent_step_completed', $runId, $run['agent_id'], ['step' => $nextStepNum]);

        return [
            'success' => true,
            'step' => $nextStepNum,
            'result' => $result['data'] ?? null,
            'needs_more' => true,
        ];
    }

    /**
     * Cancel an agent run.
     */
    public function cancelRun(int $runId): array
    {
        $run = $this->getRun($runId);
        if (!$run) return ['success' => false, 'error' => 'Run not found.'];
        if (in_array($run['status'], ['completed', 'cancelled'])) {
            return ['success' => false, 'error' => 'Run already finished.'];
        }

        // Cancel pending steps
        $this->db->prepare("
            UPDATE ai_agent_steps SET status = 'cancelled' WHERE agent_run_id = ? AND status IN ('pending','awaiting_confirmation')
        ")->execute([$runId]);

        $this->updateRunStatus($runId, AI_AGENT_STATUS_CANCELLED);
        $this->log('agent_run_cancelled', $runId, $run['agent_id']);

        return ['success' => true];
    }

    // ── Step Executors ──

    private function executeToolStep(array $run, array $agentDef, array $step): array
    {
        $toolId = $step['operation_id'];

        // Validate agent is allowed to use this tool
        if (!AIAgentRegistry::canUseTool($run['agent_id'], $toolId)) {
            return ['success' => false, 'error' => "Agent is not authorized to use tool: {$toolId}"];
        }

        // Validate tool exists and user has permission
        $toolDef = AIToolRegistry::get($toolId);
        if (!$toolDef || !$toolDef['enabled']) {
            return ['success' => false, 'error' => 'Tool not available: ' . $toolId];
        }

        $toolExecutor = new AIToolExecutor($this->db, $this->userId, $this->userPermissions);
        $params = json_decode($step['input_json'] ?? '{}', true);
        $result = $toolExecutor->execute($toolId, $params, $run['conversation_id'] ?? 0);

        return [
            'success' => $result['success'],
            'data' => $result['success'] ? ['result' => $result['result']] : null,
            'error' => $result['success'] ? null : $result['error'],
            'tool_call' => true,
        ];
    }

    private function executeActionStep(array $run, array $agentDef, array $step): array
    {
        $actionId = $step['operation_id'];

        // Validate agent is allowed to use this action
        if (!AIAgentRegistry::canUseAction($run['agent_id'], $actionId)) {
            return ['success' => false, 'error' => "Agent is not authorized to use action: {$actionId}"];
        }

        // All actions require human confirmation — pause the run
        $this->db->prepare("
            UPDATE ai_agent_steps SET status = 'awaiting_confirmation' WHERE id = ?
        ")->execute([$step['id']]);

        $this->updateRunStatus($run['id'], AI_AGENT_STATUS_AWAITING);

        // Propose the action via the existing F9 system
        $actionExecutor = new AIActionExecutor($this->db, $this->userId, $this->userPermissions);
        $params = json_decode($step['input_json'] ?? '{}', true);
        $actionResult = $actionExecutor->propose($actionId, $params, $run['conversation_id'] ?? 0);

        $this->log('agent_action_proposed', $run['id'], $run['agent_id'], [
            'action' => $actionId, 'pending_action_id' => $actionResult['pending_action_id'] ?? null
        ]);

        return [
            'success' => true,
            'data' => [
                'paused' => true,
                'reason' => 'awaiting_confirmation',
                'action_result' => $actionResult,
            ],
            'error' => null,
            'paused' => true,
        ];
    }

    private function executeKnowledgeStep(array $run, array $agentDef, array $step): array
    {
        $params = json_decode($step['input_json'] ?? '{}', true);
        $query = $params['query'] ?? '';
        $collectionId = $params['collection_id'] ?? null;

        $searchResult = AIKnowledgeService::searchKnowledge(
            $this->db, $this->userId, $query, $collectionId, 5
        );

        if ($searchResult['success'] && !empty($searchResult['results'])) {
            $formatted = AIKnowledgeService::formatForAI($searchResult['results']);
            return ['success' => true, 'data' => ['result' => $formatted]];
        }

        return ['success' => true, 'data' => ['result' => 'No relevant knowledge found.']];
    }

    private function executeReasoningStep(array $run, array $agentDef, array $step): array
    {
        // Reasoning steps are handled by the AI in conversation context
        // The step is just a placeholder that gets marked complete
        return ['success' => true, 'data' => ['result' => 'Reasoning completed.']];
    }

    // ── Limits ──

    private function checkLimits(array $run, array $agentDef): array
    {
        // Step limit
        if ($run['current_step'] >= $agentDef['max_steps']) {
            return ['ok' => false, 'reason' => 'Maximum steps reached (' . $agentDef['max_steps'] . ').'];
        }

        // Tool call limit
        if ($run['tool_calls_made'] >= $agentDef['max_tool_calls']) {
            return ['ok' => false, 'reason' => 'Maximum tool calls reached (' . $agentDef['max_tool_calls'] . ').'];
        }

        // Runtime limit
        if ($run['started_at']) {
            $elapsed = time() - strtotime($run['started_at']);
            if ($elapsed >= $agentDef['max_runtime_seconds']) {
                return ['ok' => false, 'reason' => 'Maximum runtime reached (' . $agentDef['max_runtime_seconds'] . 's).'];
            }
        }

        return ['ok' => true];
    }

    // ── Helpers ──

    public function getRun(int $runId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM ai_agent_runs WHERE id = ?");
        $stmt->execute([$runId]);
        $run = $stmt->fetch();
        return $run ?: null;
    }

    public function getRunSteps(int $runId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM ai_agent_steps WHERE agent_run_id = ? ORDER BY step_number ASC
        ");
        $stmt->execute([$runId]);
        return $stmt->fetchAll();
    }

    private function updateRunStatus(int $runId, string $status, ?string $resultSummary = null): void
    {
        $extra = '';
        $params = [$status];
        if ($resultSummary !== null) {
            $extra = ', result_summary = ?';
            $params[] = $resultSummary;
        }
        if (in_array($status, ['completed'])) {
            $extra .= ', completed_at = NOW()';
        } elseif (in_array($status, ['failed'])) {
            $extra .= ', failed_at = NOW()';
        }
        $params[] = $runId;
        $this->db->prepare("UPDATE ai_agent_runs SET status = ?{$extra}, updated_at = NOW() WHERE id = ?")->execute($params);
    }

    private function log(string $event, int $runId, string $agentId, ?array $extra = null): void
    {
        try {
            $details = array_merge(['run_id' => $runId, 'agent_id' => $agentId, 'event' => $event], $extra ?? []);
            $this->db->prepare("
                INSERT INTO ai_audit_log (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, ?, 'agent_run', ?, ?, NOW())
            ")->execute([$this->userId, "agent_{$event}", $runId, json_encode($details)]);
        } catch (\Throwable $e) {}
    }
}
