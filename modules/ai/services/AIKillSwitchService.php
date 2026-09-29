<?php
/**
 * AI F16 — Kill Switch Service.
 *
 * Emergency disable capabilities, server-side enforced.
 */
class AIKillSwitchService
{
    // Default kill switches
    private static array $defaultSwitches = [
        'ai_chat' => ['name' => 'AI Chat', 'description' => 'Disable AI chat functionality'],
        'command_center' => ['name' => 'Command Center', 'description' => 'Disable the AI Command Center'],
        'tool_calling' => ['name' => 'Tool Calling', 'description' => 'Disable AI tool execution'],
        'write_actions' => ['name' => 'Write Actions', 'description' => 'Disable all AI write action requests'],
        'agents' => ['name' => 'Agents', 'description' => 'Disable AI agent execution'],
        'automations' => ['name' => 'Automations', 'description' => 'Disable all automation triggers'],
        'scheduled_automations' => ['name' => 'Scheduled Automations', 'description' => 'Disable scheduled automation execution'],
        'event_automations' => ['name' => 'Event Automations', 'description' => 'Disable event-triggered automations'],
        'workflow_studio' => ['name' => 'Workflow Studio', 'description' => 'Disable the AI Workflow Studio UI'],
        'workflow_execution' => ['name' => 'Workflow Execution', 'description' => 'Disable workflow runtime execution'],
        'workflow_generation' => ['name' => 'Workflow Generation', 'description' => 'Disable AI workflow draft generation'],
        'process_intelligence' => ['name' => 'Process Intelligence', 'description' => 'Disable AI process intelligence analysis, simulations, and proposals'],
        'scheduled_workflows' => ['name' => 'Scheduled Workflows', 'description' => 'Disable scheduled workflow execution'],
        'event_workflows' => ['name' => 'Event Workflows', 'description' => 'Disable event-triggered workflow execution'],
        'ai_workspaces' => ['name' => 'AI Workspaces', 'description' => 'Disable AI shared workspaces'],
        'shared_conversations' => ['name' => 'Shared Conversations', 'description' => 'Disable shared workspace conversation operations'],
        'collaborative_findings' => ['name' => 'Collaborative Findings', 'description' => 'Disable shared finding creation and review'],
        'shared_agent_results' => ['name' => 'Shared Agent Results', 'description' => 'Disable sharing agent results into workspaces'],
        'workspace_ai_context' => ['name' => 'Workspace AI Context', 'description' => 'Disable workspace context building and usage'],
        'knowledge_retrieval' => ['name' => 'Knowledge Retrieval', 'description' => 'Disable knowledge base search'],
        'ai_insights' => ['name' => 'AI Insights', 'description' => 'Disable insight generation'],
        'document_processing' => ['name' => 'Document Processing', 'description' => 'Disable document ingestion and queue processing'],
        'document_ocr' => ['name' => 'Document OCR', 'description' => 'Disable OCR-based document extraction'],
        'external_ai_document_analysis' => ['name' => 'External AI Document Analysis', 'description' => 'Disable external AI processing for documents'],
        'document_qna' => ['name' => 'Document Q&A', 'description' => 'Disable document question answering'],
        'document_comparison' => ['name' => 'Document Comparison', 'description' => 'Disable document comparison workflows'],
        'document_to_erp_actions' => ['name' => 'Document to ERP Actions', 'description' => 'Disable draft actions proposed from documents'],
        'voice_input' => ['name' => 'Voice Input', 'description' => 'Disable push-to-talk voice input'],
        'speech_to_text' => ['name' => 'Speech-to-Text', 'description' => 'Disable speech transcription'],
        'text_to_speech' => ['name' => 'Text-to-Speech', 'description' => 'Disable spoken AI responses'],
        'voice_dictation' => ['name' => 'Voice Dictation', 'description' => 'Disable voice dictation into fields'],
        'voice_commands' => ['name' => 'Voice Commands', 'description' => 'Disable voice command execution'],
        'external_integrations' => ['name' => 'External Integrations', 'description' => 'Disable all external connector execution'],
        'ai_connector_tools' => ['name' => 'AI Connector Tools', 'description' => 'Disable AI access to connector capabilities'],
        'external_writes' => ['name' => 'External Writes', 'description' => 'Disable connector write and destructive operations'],
        'webhooks' => ['name' => 'Webhooks', 'description' => 'Disable outbound webhook delivery'],
        'sync_jobs' => ['name' => 'Sync Jobs', 'description' => 'Disable integration sync preview and apply operations'],
        'generic_rest_connector' => ['name' => 'Generic REST Connector', 'description' => 'Disable restricted generic REST connector execution'],
        'ai_evaluations' => ['name' => 'AI Evaluations', 'description' => 'Disable evaluation dataset execution and regression runs'],
        'ai_experiments' => ['name' => 'AI Experiments', 'description' => 'Disable controlled experiment creation and activation'],
        'content_sampling' => ['name' => 'Content Sampling', 'description' => 'Disable sampled masked prompt/response capture in observability'],
        'ai_feedback_collection' => ['name' => 'AI Feedback Collection', 'description' => 'Disable end-user helpful or problem feedback collection'],
    ];

    public static function getDefaultSwitches(): array
    {
        return self::$defaultSwitches;
    }

    /**
     * Initialize default kill switches.
     */
    public static function initialize(PDO $db): int
    {
        $count = 0;
        foreach (self::$defaultSwitches as $key => $config) {
            try {
                $db->prepare("
                    INSERT IGNORE INTO ai_kill_switches (switch_key, name, description, enabled)
                    VALUES (?, ?, ?, 1)
                ")->execute([$key, $config['name'], $config['description']]);
                $count++;
            } catch (\Throwable $e) {}
        }
        return $count;
    }

    /**
     * Check if a feature is enabled (not killed).
     */
    public static function isEnabled(PDO $db, string $switchKey): bool
    {
        try {
            $stmt = $db->prepare("SELECT enabled FROM ai_kill_switches WHERE switch_key = ?");
            $stmt->execute([$switchKey]);
            $result = $stmt->fetch();
            return $result ? (bool) $result['enabled'] : true; // Default: enabled
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Toggle a kill switch.
     */
    public static function toggle(PDO $db, string $switchKey, bool $enabled, int $userId, ?string $reason = null): array
    {
        self::initialize($db);

        if (!isset(self::$defaultSwitches[$switchKey])) {
            return ['success' => false, 'error' => 'Unknown AI feature switch.'];
        }

        $stmt = $db->prepare("SELECT * FROM ai_kill_switches WHERE switch_key = ?");
        $stmt->execute([$switchKey]);
        $previous = $stmt->fetch();

        $reason = $reason !== null ? trim($reason) : null;
        if ($reason !== null && strlen($reason) > 500) {
            $reason = substr($reason, 0, 500);
        }

        $db->prepare("
            UPDATE ai_kill_switches
            SET enabled = ?,
                disabled_by = ?,
                disabled_at = IF(? = 0, NOW(), NULL),
                reason = ?,
                updated_at = NOW()
            WHERE switch_key = ?
        ")->execute([$enabled ? 1 : 0, $enabled ? null : $userId, $enabled ? 1 : 0, $reason, $switchKey]);

        // Audit
        self::audit($db, $userId, $switchKey, $enabled ? 'enabled' : 'disabled', $reason, $previous, [
            'switch_key' => $switchKey,
            'enabled' => $enabled,
            'reason' => $reason,
        ]);

        return ['success' => true, 'enabled' => $enabled];
    }

    /**
     * Get all kill switches.
     */
    public static function getAll(PDO $db): array
    {
        $stmt = $db->query("SELECT * FROM ai_kill_switches ORDER BY switch_key");
        return $stmt->fetchAll();
    }

    /**
     * Get status summary.
     */
    public static function getStatusSummary(PDO $db): array
    {
        $switches = self::getAll($db);
        $disabled = array_filter($switches, fn($s) => !$s['enabled']);
        return [
            'total' => count($switches),
            'enabled' => count($switches) - count($disabled),
            'disabled' => count($disabled),
            'disabled_names' => array_column($disabled, 'name'),
        ];
    }

    /**
     * Audit kill switch changes.
     */
    private static function audit(PDO $db, int $userId, string $switchKey, string $action, ?string $reason, ?array $previous = null, ?array $new = null): void
    {
        try {
            if (class_exists('AIGovernanceService')) {
                AIGovernanceService::auditConfigChange($db, 'kill_switch', $switchKey, $action, $previous, $new, $userId);
            } else {
                $db->prepare("
                    INSERT INTO ai_config_audit (config_type, config_id, action, new_value, user_id, created_at)
                    VALUES ('kill_switch', ?, ?, ?, ?, NOW())
                ")->execute([$switchKey, $action, $reason, $userId]);
            }
        } catch (\Throwable $e) {}
    }
}
