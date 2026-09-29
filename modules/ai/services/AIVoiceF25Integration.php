<?php
/**
 * AI F28 — Voice + F25 Agent Integration.
 *
 * Creates F25 agent tasks from voice commands.
 * Every action must pass F24 governance.
 *
 * Covers Section 17: Voice + F25 Agents.
 */
class AIVoiceF25Integration
{
    /**
     * Create an F25 agent task from a voice command.
     */
    public static function createTaskFromVoice(
        PDO $db,
        int $userId,
        int $companyId,
        string $text,
        array $context = [],
        ?int $sessionId = null,
        ?int $messageId = null
    ): array {
        // F24: Governance check
        if (class_exists('AIVoiceGovernanceService')) {
            $govCheck = AIVoiceGovernanceService::enforce($db, $userId, $companyId, $text, $context);
            if (!$govCheck['allowed']) {
                return ['success' => false, 'error' => $govCheck['reason'] ?? 'Governance denied this action'];
            }

            // High-risk: require confirmation
            if ($govCheck['requires_confirmation'] || $govCheck['risk_level'] === 'critical') {
                $confirmation = AIVoiceConfirmationService::createConfirmation(
                    $db, $userId, $companyId,
                    'f25_task_create', null,
                    ['request' => $text],
                    $govCheck['risk_level'],
                    $sessionId, $messageId
                );

                return [
                    'success' => false,
                    'requires_confirmation' => true,
                    'confirmation' => $confirmation,
                    'message' => 'This action requires confirmation. Please confirm on screen.',
                ];
            }
        }

        // Emergency kill switch
        if (class_exists('AIEmergencyControlsService')) {
            if (AIEmergencyControlsService::isControlActive($db, 'disable_autonomous_agents')) {
                return ['success' => false, 'error' => 'Autonomous agents are disabled by emergency control'];
            }
        }

        // Resolve a more suitable agent instead of hardcoding the general assistant.
        $agentKey = self::resolveAgentKey($text, $context);
        $agentDefId = class_exists('AIAgentRuntimeService')
            ? AIAgentRuntimeService::resolveAgentDefinitionIdByKey($db, $agentKey)
            : null;
        if (!$agentDefId) {
            return ['success' => false, 'error' => 'No matching AI agent is available for this request'];
        }

        $permissions = function_exists('getUserPermissions') ? getUserPermissions($userId) : [];

        // Create the F25 task
        $result = AIAgentRuntimeService::createTask(
            $db, $userId, $companyId,
            $permissions, $agentDefId,
            $text,
            'voice',
            $messageId
        );

        if ($result['success']) {
            // F23: Record observability
            if (class_exists('AIIntelligenceService')) {
                AIIntelligenceService::recordObservabilityTrace($db, 'voice_f25_task_created', [
                    'user_id' => $userId,
                    'task_id' => $result['task_id'],
                    'task_key' => $result['task_key'],
                    'risk_level' => $context['risk_level'] ?? 'low',
                ]);
            }
        }

        return $result;
    }

    private static function resolveAgentKey(string $text, array $context): string
    {
        $haystack = strtolower(trim($text . ' ' . json_encode($context)));
        if (preg_match('/\b(procurement|rfq|quotation|quote|supplier|vendor|purchase order|po)\b/i', $haystack)) {
            return 'procurement_specialist';
        }

        return 'erp_general_assistant';
    }

    /**
     * Get F25 task status for a voice-initiated task.
     */
    public static function getTaskStatus(PDO $db, int $taskId, int $companyId): array
    {
        $stmt = $db->prepare("
            SELECT t.*, 
                   (SELECT COUNT(*) FROM ai_agent_task_runs WHERE task_id = t.id) as run_count,
                   (SELECT status FROM ai_agent_task_runs WHERE task_id = t.id ORDER BY id DESC LIMIT 1) as latest_run_status
            FROM ai_agent_tasks t
            WHERE t.id = ? AND t.company_id = ?
        ");
        $stmt->execute([$taskId, $companyId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            return ['success' => false, 'error' => 'Task not found'];
        }

        return [
            'success' => true,
            'task' => $task,
            'status' => $task['status'],
            'can_pause' => in_array($task['status'], ['running']),
            'can_resume' => in_array($task['status'], ['paused']),
            'can_cancel' => in_array($task['status'], ['pending', 'running', 'paused', 'awaiting_approval']),
        ];
    }
}
