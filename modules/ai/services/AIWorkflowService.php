<?php
/**
 * AI F18 — Workflow Builder & Visual Automation Studio.
 *
 * Safe workflow registry, versioning, validation, testing, and execution.
 */

class AIWorkflowService
{
    const MAX_NODES = 25;
    const MAX_GRAPH_DEPTH = 12;
    const MAX_AI_CALLS = 3;
    const MAX_TOOL_CALLS = 5;
    const MAX_WAIT_HOURS = 24 * 30;
    const MAX_CONCURRENT_RUNS = 10;

    public static function getNodeRegistry(): array
    {
        return [
            'manual_trigger' => [
                'label' => 'Manual Trigger',
                'category' => 'Triggers',
                'inputs' => ['label'],
                'outputs' => ['triggered'],
                'permissions' => ['ai.workflows.run'],
                'safety' => 'low',
            ],
            'schedule_trigger' => [
                'label' => 'Schedule Trigger',
                'category' => 'Triggers',
                'inputs' => ['schedule_type', 'time', 'day_of_week', 'interval_minutes', 'timezone'],
                'outputs' => ['triggered'],
                'permissions' => ['ai.workflows.enable'],
                'safety' => 'medium',
            ],
            'event_trigger' => [
                'label' => 'Event Trigger',
                'category' => 'Triggers',
                'inputs' => ['event_name', 'filters'],
                'outputs' => ['triggered'],
                'permissions' => ['ai.workflows.enable'],
                'safety' => 'medium',
            ],
            'field_condition' => [
                'label' => 'Field Condition',
                'category' => 'Conditions',
                'inputs' => ['field', 'operator', 'value'],
                'outputs' => ['matched'],
                'permissions' => ['ai.workflows.edit'],
                'safety' => 'low',
            ],
            'threshold_condition' => [
                'label' => 'Threshold Condition',
                'category' => 'Conditions',
                'inputs' => ['metric', 'operator', 'value'],
                'outputs' => ['matched'],
                'permissions' => ['ai.workflows.edit'],
                'safety' => 'low',
            ],
            'read_tool' => [
                'label' => 'Read Tool',
                'category' => 'Actions',
                'inputs' => ['tool_id', 'input'],
                'outputs' => ['records', 'summary'],
                'permissions' => ['ai.workflows.run'],
                'safety' => 'medium',
            ],
            'knowledge_search' => [
                'label' => 'Knowledge Search',
                'category' => 'AI',
                'inputs' => ['query', 'collection_id'],
                'outputs' => ['results'],
                'permissions' => ['ai.knowledge.search', 'ai.workflows.run'],
                'safety' => 'medium',
            ],
            'ai_analysis' => [
                'label' => 'AI Analysis',
                'category' => 'AI',
                'inputs' => ['analysis_type', 'field'],
                'outputs' => ['classification', 'summary'],
                'permissions' => ['ai.workflows.run'],
                'safety' => 'medium',
            ],
            'notification' => [
                'label' => 'Notification',
                'category' => 'Actions',
                'inputs' => ['recipient_resolver', 'title_template', 'summary_template', 'severity'],
                'outputs' => ['notification_id'],
                'permissions' => ['ai.workflows.run'],
                'safety' => 'medium',
            ],
            'create_task' => [
                'label' => 'Create Task',
                'category' => 'Actions',
                'inputs' => ['title_template', 'description_template', 'assignee_resolver'],
                'outputs' => ['task_id'],
                'permissions' => ['ai.workflows.run'],
                'safety' => 'medium',
            ],
            'connector_capability' => [
                'label' => 'Connector Capability',
                'category' => 'Integrations',
                'inputs' => ['capability_key', 'connection_id', 'input', 'confirmed'],
                'outputs' => ['result', 'trust_level'],
                'permissions' => ['ai.integrations.use', 'ai.workflows.run'],
                'safety' => 'high',
            ],
            'outbound_webhook' => [
                'label' => 'Outbound Webhook',
                'category' => 'Integrations',
                'inputs' => ['webhook_key', 'event_name', 'payload', 'confirmed'],
                'outputs' => ['delivery_id', 'status'],
                'permissions' => ['ai.integration.webhook.send', 'ai.workflows.run'],
                'safety' => 'high',
            ],
            'approval_request' => [
                'label' => 'Approval Request',
                'category' => 'Human Interaction',
                'inputs' => ['title_template', 'summary_template', 'approver_resolver'],
                'outputs' => ['approved'],
                'permissions' => ['ai.workflows.approve'],
                'safety' => 'high',
            ],
            'delay' => [
                'label' => 'Delay',
                'category' => 'Control Flow',
                'inputs' => ['hours'],
                'outputs' => ['waiting'],
                'permissions' => ['ai.workflows.run'],
                'safety' => 'low',
            ],
            'wait_until' => [
                'label' => 'Wait Until',
                'category' => 'Control Flow',
                'inputs' => ['datetime'],
                'outputs' => ['waiting'],
                'permissions' => ['ai.workflows.run'],
                'safety' => 'low',
            ],
            'if_else' => [
                'label' => 'If / Else',
                'category' => 'Control Flow',
                'inputs' => [],
                'outputs' => ['yes', 'no'],
                'permissions' => ['ai.workflows.edit'],
                'safety' => 'low',
            ],
            'end' => [
                'label' => 'End',
                'category' => 'Control Flow',
                'inputs' => [],
                'outputs' => [],
                'permissions' => ['ai.workflows.run'],
                'safety' => 'low',
            ],
        ];
    }

    public static function getTemplates(): array
    {
        return [
            [
                'template_key' => 'event_condition_notification',
                'name' => 'Event -> Condition -> Notification',
                'description' => 'React to an ERP event and notify a responsible user.',
                'trigger_type' => 'event',
                'graph' => self::buildTemplateGraph('event'),
            ],
            [
                'template_key' => 'schedule_read_ai_notification',
                'name' => 'Schedule -> Read Tool -> AI Summary -> Notification',
                'description' => 'Run on a schedule, read ERP data, summarize it, then notify users.',
                'trigger_type' => 'scheduled',
                'graph' => self::buildTemplateGraph('scheduled'),
            ],
            [
                'template_key' => 'event_condition_task',
                'name' => 'Event -> Condition -> Create Task',
                'description' => 'Create follow-up work when an event meets a threshold.',
                'trigger_type' => 'event',
                'graph' => self::buildTaskTemplateGraph(),
            ],
            [
                'template_key' => 'manual_analysis_report',
                'name' => 'Manual -> Read Tool -> Analysis -> Report',
                'description' => 'Run manually for a controlled operational review.',
                'trigger_type' => 'manual',
                'graph' => self::buildManualAnalysisTemplateGraph(),
            ],
            [
                'template_key' => 'event_human_approval_action',
                'name' => 'Event -> Human Approval -> Action',
                'description' => 'Pause for human approval before a follow-up action.',
                'trigger_type' => 'event',
                'graph' => self::buildApprovalTemplateGraph(),
            ],
        ];
    }

    public static function ensureDefaults(PDO $db): void
    {
        if (class_exists('AIEventBusService') && self::tableExists($db, 'ai_event_registry')) {
            AIEventBusService::registerDefaultEvents($db);
        }
        foreach (self::getTemplates() as $template) {
            try {
                $db->prepare("
                    INSERT INTO ai_workflow_templates (template_key, name, description, category, trigger_type, graph_json, is_active, created_at)
                    VALUES (?, ?, ?, 'system', ?, ?, 1, NOW())
                    ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        description = VALUES(description),
                        trigger_type = VALUES(trigger_type),
                        graph_json = VALUES(graph_json),
                        is_active = 1
                ")->execute([
                    $template['template_key'],
                    $template['name'],
                    $template['description'],
                    $template['trigger_type'],
                    json_encode($template['graph']),
                ]);
            } catch (\Throwable $e) {
            }
        }
    }

    public static function getWorkflows(PDO $db, int $userId, array $filters = []): array
    {
        self::ensureDefaults($db);
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['company_id'])) {
            $where[] = '(w.company_id = ? OR w.company_id IS NULL)';
            $params[] = (int) $filters['company_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'w.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(w.name LIKE ? OR w.description LIKE ?)';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        $stmt = $db->prepare("
            SELECT w.*, v.version_status, v.ai_draft_summary, v.node_count
            FROM ai_workflows w
            LEFT JOIN ai_workflow_versions v ON v.id = w.current_version_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY w.updated_at DESC
            LIMIT 100
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['available_actions'] = self::getAllowedLifecycleActions($row);
        }
        return $rows;
    }

    public static function getWorkflow(PDO $db, int $workflowId): ?array
    {
        $stmt = $db->prepare("
            SELECT w.*, v.version_status, v.trigger_config_json, v.graph_json, v.validation_json,
                   v.ai_draft_prompt, v.ai_draft_summary, v.node_count, v.created_at AS version_created_at
            FROM ai_workflows w
            LEFT JOIN ai_workflow_versions v ON v.id = w.current_version_id
            WHERE w.id = ?
            LIMIT 1
        ");
        $stmt->execute([$workflowId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['trigger_config'] = json_decode($row['trigger_config_json'] ?? '{}', true) ?: [];
        $row['graph'] = json_decode($row['graph_json'] ?? '{}', true) ?: [];
        $row['validation'] = json_decode($row['validation_json'] ?? '{}', true) ?: [];
        $row['versions'] = self::getWorkflowVersions($db, $workflowId);
        $row['recent_runs'] = self::getRuns($db, $workflowId, 10);
        $row['available_actions'] = self::getAllowedLifecycleActions($row);
        return $row;
    }

    public static function getWorkflowVersions(PDO $db, int $workflowId): array
    {
        $stmt = $db->prepare("
            SELECT id, workflow_id, version_number, version_status, ai_draft_summary, node_count,
                   created_by, approved_by, published_at, created_at
            FROM ai_workflow_versions
            WHERE workflow_id = ?
            ORDER BY version_number DESC
        ");
        $stmt->execute([$workflowId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function createWorkflow(PDO $db, int $userId, array $payload): array
    {
        self::ensureDefaults($db);
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return ['success' => false, 'error' => 'Workflow name is required.'];
        }

        $triggerType = self::normalizeTriggerType($payload['trigger_type'] ?? 'manual');
        $triggerConfig = is_array($payload['trigger_config'] ?? null) ? $payload['trigger_config'] : [];
        $graph = self::normalizeGraph($payload['graph'] ?? []);
        $validation = self::validateGraph($db, $graph, $triggerType, $triggerConfig);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => 'Workflow validation failed.', 'validation' => $validation];
        }

        $workflowKey = self::generateWorkflowKey($db, $name);
        $companyId = isset($payload['company_id']) && $payload['company_id'] !== '' ? (int) $payload['company_id'] : null;
        $module = trim((string) ($payload['module'] ?? 'ai')) ?: 'ai';
        $description = trim((string) ($payload['description'] ?? '')) ?: null;
        $safety = self::classifySafety($graph);
        $draftPrompt = trim((string) ($payload['ai_draft_prompt'] ?? '')) ?: null;
        $draftSummary = trim((string) ($payload['ai_draft_summary'] ?? '')) ?: null;

        try {
            $db->beginTransaction();
            $db->prepare("
                INSERT INTO ai_workflows
                    (workflow_key, company_id, module, name, description, status, safety_classification,
                     trigger_type, current_version_number, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'draft', ?, ?, 1, ?, NOW(), NOW())
            ")->execute([
                $workflowKey,
                $companyId,
                $module,
                $name,
                $description,
                $safety,
                $triggerType,
                $userId,
            ]);

            $workflowId = (int) $db->lastInsertId();
            $versionId = self::insertVersion($db, $workflowId, 1, 'draft', $triggerConfig, $graph, $validation, $userId, $draftPrompt, $draftSummary);
            $db->prepare("UPDATE ai_workflows SET current_version_id = ? WHERE id = ?")->execute([$versionId, $workflowId]);
            self::audit($db, $userId, 'workflow_created', $workflowId, ['version_id' => $versionId]);
            $db->commit();
            return ['success' => true, 'workflow_id' => $workflowId, 'version_id' => $versionId];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['success' => false, 'error' => 'Failed to create workflow.'];
        }
    }

    public static function updateWorkflow(PDO $db, int $workflowId, int $userId, array $payload): array
    {
        $workflow = self::getWorkflow($db, $workflowId);
        if (!$workflow) {
            return ['success' => false, 'error' => 'Workflow not found.'];
        }

        $name = trim((string) ($payload['name'] ?? $workflow['name']));
        if ($name === '') {
            return ['success' => false, 'error' => 'Workflow name is required.'];
        }

        $triggerType = self::normalizeTriggerType($payload['trigger_type'] ?? $workflow['trigger_type']);
        $triggerConfig = is_array($payload['trigger_config'] ?? null) ? $payload['trigger_config'] : ($workflow['trigger_config'] ?? []);
        $graph = array_key_exists('graph', $payload) ? self::normalizeGraph($payload['graph']) : ($workflow['graph'] ?? []);
        $validation = self::validateGraph($db, $graph, $triggerType, $triggerConfig);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => 'Workflow validation failed.', 'validation' => $validation];
        }

        $description = trim((string) ($payload['description'] ?? $workflow['description'] ?? '')) ?: null;
        $module = trim((string) ($payload['module'] ?? $workflow['module'])) ?: 'ai';
        $companyId = isset($payload['company_id']) ? ((string) $payload['company_id'] === '' ? null : (int) $payload['company_id']) : ($workflow['company_id'] !== null ? (int) $workflow['company_id'] : null);
        $nextVersion = ((int) $workflow['current_version_number']) + 1;
        $draftPrompt = trim((string) ($payload['ai_draft_prompt'] ?? $workflow['ai_draft_prompt'] ?? '')) ?: null;
        $draftSummary = trim((string) ($payload['ai_draft_summary'] ?? $workflow['ai_draft_summary'] ?? '')) ?: null;

        try {
            $db->beginTransaction();
            $versionId = self::insertVersion($db, $workflowId, $nextVersion, 'draft', $triggerConfig, $graph, $validation, $userId, $draftPrompt, $draftSummary);
            $db->prepare("
                UPDATE ai_workflows
                SET company_id = ?, module = ?, name = ?, description = ?, status = 'draft',
                    safety_classification = ?, trigger_type = ?, current_version_number = ?, current_version_id = ?,
                    approved_by = NULL, updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $companyId,
                $module,
                $name,
                $description,
                self::classifySafety($graph),
                $triggerType,
                $nextVersion,
                $versionId,
                $workflowId,
            ]);

            self::audit($db, $userId, 'workflow_updated', $workflowId, ['version_id' => $versionId]);
            $db->commit();
            return ['success' => true, 'workflow_id' => $workflowId, 'version_id' => $versionId];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['success' => false, 'error' => 'Failed to update workflow.'];
        }
    }

    public static function changeStatus(PDO $db, int $workflowId, int $userId, string $action): array
    {
        $workflow = self::getWorkflow($db, $workflowId);
        if (!$workflow) {
            return ['success' => false, 'error' => 'Workflow not found.'];
        }

        $allowed = self::getAllowedLifecycleActions($workflow);
        if (!in_array($action, $allowed, true)) {
            return ['success' => false, 'error' => 'That workflow action is not allowed right now.'];
        }

        $statusMap = [
            'mark_testing' => 'testing',
            'approve' => 'approved',
            'publish' => 'published',
            'enable' => 'enabled',
            'disable' => 'disabled',
            'archive' => 'archived',
        ];
        $newStatus = $statusMap[$action] ?? 'draft';

        try {
            $db->beginTransaction();
            $db->prepare("UPDATE ai_workflows SET status = ?, updated_at = NOW(), approved_by = IF(? = 'approved' OR ? = 'enabled' OR ? = 'published', ?, approved_by), enabled_at = IF(? = 'enabled', NOW(), enabled_at) WHERE id = ?")
                ->execute([$newStatus, $newStatus, $newStatus, $newStatus, $userId, $newStatus, $workflowId]);
            $db->prepare("UPDATE ai_workflow_versions SET version_status = ?, approved_by = IF(? = 'approved' OR ? = 'enabled' OR ? = 'published', ?, approved_by), published_at = IF(? = 'published' OR ? = 'enabled', NOW(), published_at) WHERE id = ?")
                ->execute([$newStatus, $newStatus, $newStatus, $newStatus, $userId, $newStatus, $newStatus, $workflow['current_version_id']]);
            self::audit($db, $userId, 'workflow_' . $action, $workflowId, ['status' => $newStatus, 'version_id' => $workflow['current_version_id']]);
            $db->commit();
            return ['success' => true, 'status' => $newStatus];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['success' => false, 'error' => 'Failed to update workflow status.'];
        }
    }

    public static function generateDraft(PDO $db, int $userId, string $prompt, array $context = [], bool $persist = true): array
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return ['success' => false, 'error' => 'Prompt is required.'];
        }

        $lower = strtolower($prompt);
        $days = 7;
        if (preg_match('/(\d+)\s+day/i', $prompt, $m)) {
            $days = max(1, min(365, (int) $m[1]));
        }

        $triggerType = str_contains($lower, 'every ') || str_contains($lower, 'daily') || str_contains($lower, 'weekly') ? 'scheduled' : 'event';
        if (str_contains($lower, 'run ') || str_contains($lower, 'manually')) {
            $triggerType = 'manual';
        }

        $graph = [
            'nodes' => [
                ['id' => 'trigger_1', 'type' => $triggerType === 'scheduled' ? 'schedule_trigger' : ($triggerType === 'manual' ? 'manual_trigger' : 'event_trigger'), 'name' => 'Trigger', 'config' => self::draftTriggerConfig($prompt, $triggerType)],
                ['id' => 'condition_1', 'type' => 'field_condition', 'name' => 'Primary Condition', 'config' => self::draftPrimaryCondition($prompt, $days)],
            ],
            'edges' => [
                ['from' => 'trigger_1', 'to' => 'condition_1', 'label' => 'next'],
            ],
        ];

        $tailNode = 'condition_1';
        if (str_contains($lower, 'classify') || str_contains($lower, 'analy')) {
            $graph['nodes'][] = ['id' => 'analysis_1', 'type' => 'ai_analysis', 'name' => 'AI Analysis', 'config' => ['analysis_type' => 'classify_urgency', 'field' => 'purpose']];
            $graph['edges'][] = ['from' => $tailNode, 'to' => 'analysis_1', 'label' => 'yes'];
            $tailNode = 'analysis_1';
        }
        if (str_contains($lower, 'notify')) {
            $graph['nodes'][] = ['id' => 'notify_1', 'type' => 'notification', 'name' => 'Notify', 'config' => [
                'recipient_resolver' => str_contains($lower, 'procurement manager') ? 'procurement_manager' : 'workflow_owner',
                'title_template' => 'Workflow Alert: {{workflow.name}}',
                'summary_template' => $prompt,
                'severity' => str_contains($lower, 'urgent') ? 'high' : 'medium',
            ]];
            $graph['edges'][] = ['from' => $tailNode, 'to' => 'notify_1', 'label' => 'next'];
            $tailNode = 'notify_1';
        }
        if (str_contains($lower, 'create a review task') || str_contains($lower, 'create task')) {
            $graph['nodes'][] = ['id' => 'task_1', 'type' => 'create_task', 'name' => 'Create Task', 'config' => [
                'title_template' => 'Workflow Review: {{workflow.name}}',
                'description_template' => $prompt,
                'assignee_resolver' => str_contains($lower, 'procurement manager') ? 'procurement_manager' : 'workflow_owner',
            ]];
            $graph['edges'][] = ['from' => $tailNode, 'to' => 'task_1', 'label' => 'next'];
            $tailNode = 'task_1';
        }

        $graph['nodes'][] = ['id' => 'end_1', 'type' => 'end', 'name' => 'End', 'config' => []];
        $graph['edges'][] = ['from' => $tailNode, 'to' => 'end_1', 'label' => 'next'];

        $triggerConfig = self::draftTriggerConfig($prompt, $triggerType);
        $validation = self::validateGraph($db, $graph, $triggerType, $triggerConfig);
        $draft = [
            'name' => self::draftName($prompt),
            'description' => $prompt,
            'module' => self::inferModule($prompt, $context),
            'company_id' => $context['company_id'] ?? null,
            'trigger_type' => $triggerType,
            'trigger_config' => $triggerConfig,
            'graph' => $graph,
            'ai_draft_prompt' => $prompt,
            'ai_draft_summary' => 'AI drafted a safe workflow using registered nodes only.',
            'validation' => $validation,
        ];

        if (!$persist) {
            return ['success' => true, 'draft' => $draft];
        }

        $created = self::createWorkflow($db, $userId, $draft);
        if (!$created['success']) {
            return $created;
        }

        return [
            'success' => true,
            'workflow_id' => $created['workflow_id'],
            'version_id' => $created['version_id'],
            'draft' => $draft,
        ];
    }

    public static function testWorkflow(PDO $db, int $workflowId, int $userId, array $triggerContext = []): array
    {
        $workflow = self::getWorkflow($db, $workflowId);
        if (!$workflow) {
            return ['success' => false, 'error' => 'Workflow not found.'];
        }

        $validation = self::validateGraph($db, $workflow['graph'] ?? [], $workflow['trigger_type'], $workflow['trigger_config'] ?? []);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => 'Workflow is not valid for testing.', 'validation' => $validation];
        }

        $run = self::runWorkflow($db, $workflow, $userId, $triggerContext, true);
        if (!$run['success']) {
            return $run;
        }

        return [
            'success' => true,
            'run_id' => $run['run_id'],
            'status' => $run['status'],
            'timeline' => $run['timeline'],
            'message' => 'Dry-run completed without production writes.',
        ];
    }

    public static function runManualWorkflow(PDO $db, int $workflowId, int $userId, array $triggerContext = []): array
    {
        $workflow = self::getWorkflow($db, $workflowId);
        if (!$workflow) {
            return ['success' => false, 'error' => 'Workflow not found.'];
        }
        if (!in_array($workflow['status'], ['published', 'enabled'], true)) {
            return ['success' => false, 'error' => 'Only published or enabled workflows can run.'];
        }
        if (($workflow['trigger_type'] ?? 'manual') !== 'manual') {
            return ['success' => false, 'error' => 'This workflow is not configured for manual execution.'];
        }

        return self::runWorkflow($db, $workflow, $userId, $triggerContext, false);
    }

    public static function getRuns(PDO $db, ?int $workflowId = null, int $limit = 50): array
    {
        $sql = "
            SELECT r.*, w.name AS workflow_name
            FROM ai_workflow_runs r
            JOIN ai_workflows w ON w.id = r.workflow_id
        ";
        $params = [];
        if ($workflowId) {
            $sql .= " WHERE r.workflow_id = ?";
            $params[] = $workflowId;
        }
        $sql .= " ORDER BY r.created_at DESC LIMIT " . max(1, min(100, $limit));

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['execution_state'] = json_decode($row['execution_state_json'] ?? '{}', true) ?: [];
        }
        return $rows;
    }

    public static function getRun(PDO $db, int $runId): ?array
    {
        $stmt = $db->prepare("
            SELECT r.*, w.name AS workflow_name
            FROM ai_workflow_runs r
            JOIN ai_workflows w ON w.id = r.workflow_id
            WHERE r.id = ?
            LIMIT 1
        ");
        $stmt->execute([$runId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['execution_state'] = json_decode($row['execution_state_json'] ?? '{}', true) ?: [];
        $nodeStmt = $db->prepare("SELECT * FROM ai_workflow_run_nodes WHERE run_id = ? ORDER BY id ASC");
        $nodeStmt->execute([$runId]);
        $row['node_history'] = $nodeStmt->fetchAll(PDO::FETCH_ASSOC);
        return $row;
    }

    public static function validateGraph(PDO $db, array $graph, string $triggerType, array $triggerConfig = []): array
    {
        $registry = self::getNodeRegistry();
        $nodes = array_values($graph['nodes'] ?? []);
        $edges = array_values($graph['edges'] ?? []);
        $errors = [];
        $warnings = [];

        if (empty($nodes)) {
            return ['valid' => false, 'errors' => ['Workflow must contain at least one node.'], 'warnings' => [], 'stats' => ['nodes' => 0]];
        }
        if (count($nodes) > self::MAX_NODES) {
            $errors[] = 'Workflow exceeds the maximum node count of ' . self::MAX_NODES . '.';
        }

        $nodeIds = [];
        $triggerCount = 0;
        foreach ($nodes as $node) {
            $id = trim((string) ($node['id'] ?? ''));
            $type = trim((string) ($node['type'] ?? ''));
            if ($id === '') {
                $errors[] = 'Each node requires an id.';
                continue;
            }
            if (isset($nodeIds[$id])) {
                $errors[] = 'Duplicate node id: ' . $id;
            }
            $nodeIds[$id] = $node;

            if (!isset($registry[$type])) {
                $errors[] = 'Unregistered node type: ' . $type;
                continue;
            }

            if (str_ends_with($type, '_trigger')) {
                $triggerCount++;
            }
            $nodeErrors = self::validateNodeConfig($db, $type, $node['config'] ?? []);
            $errors = array_merge($errors, $nodeErrors);
        }

        if ($triggerCount !== 1) {
            $errors[] = 'Workflow must contain exactly one trigger node.';
        }

        foreach ($edges as $edge) {
            $from = $edge['from'] ?? '';
            $to = $edge['to'] ?? '';
            if (!isset($nodeIds[$from]) || !isset($nodeIds[$to])) {
                $errors[] = 'Workflow contains an edge that references a missing node.';
            }
        }

        $triggerValidation = self::validateTriggerConfig($db, $triggerType, $triggerConfig);
        if (!$triggerValidation['valid']) {
            $errors = array_merge($errors, $triggerValidation['errors']);
        }

        $adjacency = [];
        $incoming = [];
        foreach ($edges as $edge) {
            $adjacency[$edge['from']][] = $edge['to'];
            $incoming[$edge['to']] = ($incoming[$edge['to']] ?? 0) + 1;
        }

        $starts = [];
        foreach ($nodes as $node) {
            $type = $node['type'] ?? '';
            if (str_ends_with((string) $type, '_trigger')) {
                $starts[] = $node['id'];
            }
        }

        if (!empty($starts)) {
            $visited = [];
            $queue = [[$starts[0], 1]];
            while ($queue) {
                [$current, $depth] = array_shift($queue);
                if (isset($visited[$current])) {
                    continue;
                }
                $visited[$current] = true;
                if ($depth > self::MAX_GRAPH_DEPTH) {
                    $errors[] = 'Workflow exceeds maximum graph depth of ' . self::MAX_GRAPH_DEPTH . '.';
                    break;
                }
                foreach ($adjacency[$current] ?? [] as $next) {
                    $queue[] = [$next, $depth + 1];
                }
            }

            foreach ($nodes as $node) {
                if (!isset($visited[$node['id']])) {
                    $warnings[] = 'Node "' . ($node['name'] ?? $node['id']) . '" is disconnected from the main trigger path.';
                }
            }
        }

        if (self::hasCycle($nodes, $adjacency)) {
            $errors[] = 'Workflow contains a cycle. Recursive loops are not allowed.';
        }

        $stats = [
            'nodes' => count($nodes),
            'edges' => count($edges),
            'depth_limit' => self::MAX_GRAPH_DEPTH,
            'ai_nodes' => self::countNodesByTypePrefix($nodes, 'ai_'),
            'tool_nodes' => self::countSpecificNodeType($nodes, 'read_tool'),
        ];

        return [
            'valid' => empty($errors),
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'stats' => $stats,
        ];
    }

    private static function runWorkflow(PDO $db, array $workflow, int $userId, array $triggerContext, bool $dryRun): array
    {
        $guard = class_exists('AIGovernanceService')
            ? AIGovernanceService::guardRequest($db, 'automations', $userId, (int) ($workflow['company_id'] ?? $_SESSION['company_id'] ?? 1), ['request_type' => $dryRun ? 'workflow_test' : 'workflow_run'])
            : ['allowed' => true];
        if (isset($guard['allowed']) && !$guard['allowed']) {
            return ['success' => false, 'error' => $guard['message'] ?? 'Workflow execution is currently blocked.'];
        }

        if (!AIKillSwitchService::isEnabled($db, 'workflow_execution')) {
            return ['success' => false, 'error' => 'Workflow execution is disabled by kill switch.'];
        }
        if ($dryRun && !AIKillSwitchService::isEnabled($db, 'workflow_generation')) {
            return ['success' => false, 'error' => 'Workflow testing is unavailable while workflow generation is disabled.'];
        }

        $graph = $workflow['graph'] ?? [];
        $validation = self::validateGraph($db, $graph, $workflow['trigger_type'], $workflow['trigger_config'] ?? []);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => 'Workflow is not valid.', 'validation' => $validation];
        }

        $runningCount = $db->query("SELECT COUNT(*) FROM ai_workflow_runs WHERE status IN ('queued','running','waiting','waiting_approval')")->fetchColumn();
        if ((int) $runningCount >= self::MAX_CONCURRENT_RUNS) {
            return ['success' => false, 'error' => 'Workflow runtime is at capacity.'];
        }

        $userPermissions = self::getUserPermissions($db, $userId);
        $timeline = [];
        $state = [
            'trigger' => $triggerContext,
            'outputs' => [],
            'node_order' => [],
            'tool_calls' => 0,
            'ai_calls' => 0,
            'dry_run' => $dryRun,
        ];

        try {
            $db->beginTransaction();
            $db->prepare("
                INSERT INTO ai_workflow_runs
                    (workflow_id, workflow_version_id, workflow_version_number, company_id, trigger_type,
                     trigger_context_json, status, execution_state_json, dry_run, started_by, started_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'running', ?, ?, ?, NOW(), NOW(), NOW())
            ")->execute([
                $workflow['id'],
                $workflow['current_version_id'],
                $workflow['current_version_number'],
                $workflow['company_id'],
                $workflow['trigger_type'],
                json_encode($triggerContext),
                json_encode($state),
                $dryRun ? 1 : 0,
                $userId,
            ]);
            $runId = (int) $db->lastInsertId();

            $nodeMap = [];
            foreach (($graph['nodes'] ?? []) as $node) {
                $nodeMap[$node['id']] = $node;
            }
            $nextMap = [];
            foreach (($graph['edges'] ?? []) as $edge) {
                $nextMap[$edge['from']][] = ['to' => $edge['to'], 'label' => $edge['label'] ?? 'next'];
            }

            $currentId = self::findTriggerNodeId($graph);
            $steps = 0;
            $finalStatus = 'completed';
            $errorSummary = null;

            while ($currentId && isset($nodeMap[$currentId])) {
                $steps++;
                if ($steps > self::MAX_NODES) {
                    $finalStatus = 'failed';
                    $errorSummary = 'Execution limit reached.';
                    break;
                }

                $node = $nodeMap[$currentId];
                $result = self::executeNode($db, $runId, $node, $state, $userId, $userPermissions, $workflow, $dryRun);
                self::recordNodeExecution($db, $runId, $node, $result);
                $timeline[] = [
                    'node_id' => $node['id'],
                    'node_type' => $node['type'],
                    'status' => $result['status'],
                    'summary' => $result['summary'],
                ];
                $state['node_order'][] = $node['id'];
                $state['outputs'][$node['id']] = $result['output'] ?? [];

                if ($result['status'] === 'failed') {
                    $finalStatus = 'failed';
                    $errorSummary = $result['summary'];
                    break;
                }
                if ($result['status'] === 'waiting') {
                    $finalStatus = $result['waiting_type'] ?? 'waiting';
                    $db->prepare("UPDATE ai_workflow_runs SET status = ?, current_node_id = ?, execution_state_json = ?, resume_after = ?, updated_at = NOW() WHERE id = ?")
                        ->execute([
                            $result['waiting_type'] ?? 'waiting',
                            $node['id'],
                            json_encode($state),
                            $result['resume_after'] ?? null,
                            $runId,
                        ]);
                    $db->prepare("UPDATE ai_workflows SET last_run_at = NOW(), updated_at = NOW() WHERE id = ?")->execute([$workflow['id']]);
                    self::audit($db, $userId, 'workflow_run_started', $workflow['id'], ['run_id' => $runId, 'status' => $finalStatus]);
                    $db->commit();
                    return ['success' => true, 'run_id' => $runId, 'status' => $finalStatus, 'timeline' => $timeline];
                }

                $currentId = self::resolveNextNodeId($node, $nextMap, $result);
            }

            $db->prepare("UPDATE ai_workflow_runs SET status = ?, current_node_id = NULL, execution_state_json = ?, error_summary = ?, completed_at = IF(? IN ('completed','failed','cancelled','expired'), NOW(), completed_at), updated_at = NOW() WHERE id = ?")
                ->execute([$finalStatus, json_encode($state), $errorSummary, $finalStatus, $runId]);
            $db->prepare("UPDATE ai_workflows SET last_run_at = NOW(), updated_at = NOW() WHERE id = ?")->execute([$workflow['id']]);
            self::audit($db, $userId, 'workflow_run_' . ($finalStatus === 'completed' ? 'completed' : 'failed'), $workflow['id'], ['run_id' => $runId, 'status' => $finalStatus]);
            $db->commit();

            return ['success' => true, 'run_id' => $runId, 'status' => $finalStatus, 'timeline' => $timeline];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['success' => false, 'error' => 'Workflow execution failed.'];
        }
    }

    private static function executeNode(PDO $db, int $runId, array $node, array &$state, int $userId, array $userPermissions, array $workflow, bool $dryRun): array
    {
        $type = $node['type'] ?? '';
        $config = is_array($node['config'] ?? null) ? $node['config'] : [];
        $started = microtime(true);

        $summary = 'Node completed.';
        $output = [];
        $status = 'completed';
        $waitingType = null;
        $resumeAfter = null;

        switch ($type) {
            case 'manual_trigger':
            case 'schedule_trigger':
            case 'event_trigger':
                $summary = 'Trigger matched.';
                $output = ['matched' => true];
                break;

            case 'field_condition':
                $matched = self::evaluateCondition($config, $state['trigger']);
                $summary = $matched ? 'Condition matched.' : 'Condition did not match.';
                $output = ['matched' => $matched];
                break;

            case 'threshold_condition':
                $matched = self::evaluateCondition([
                    'field' => $config['metric'] ?? null,
                    'operator' => $config['operator'] ?? 'greater_than',
                    'value' => $config['value'] ?? null,
                ], $state['trigger']);
                $summary = $matched ? 'Threshold matched.' : 'Threshold not reached.';
                $output = ['matched' => $matched];
                break;

            case 'read_tool':
                if (++$state['tool_calls'] > self::MAX_TOOL_CALLS) {
                    return ['status' => 'failed', 'summary' => 'Workflow exceeded maximum tool calls.', 'output' => []];
                }
                if ($dryRun) {
                    $summary = 'Read tool would execute: ' . ($config['tool_id'] ?? 'unknown');
                    $output = ['summary' => $summary];
                    break;
                }
                $executor = new AIToolExecutor($db, $userId, $userPermissions);
                $toolResult = $executor->execute((string) ($config['tool_id'] ?? ''), is_array($config['input'] ?? null) ? $config['input'] : [], 0);
                if (!$toolResult['success']) {
                    return ['status' => 'failed', 'summary' => $toolResult['error'] ?? 'Tool execution failed.', 'output' => []];
                }
                $summary = 'Read tool completed.';
                $output = ['result' => $toolResult['result']];
                break;

            case 'knowledge_search':
                if (++$state['tool_calls'] > self::MAX_TOOL_CALLS) {
                    return ['status' => 'failed', 'summary' => 'Workflow exceeded maximum knowledge calls.', 'output' => []];
                }
                if ($dryRun) {
                    $summary = 'Knowledge search would run.';
                    $output = ['count' => 0];
                    break;
                }
                $search = AIKnowledgeService::searchKnowledge(
                    $db,
                    $userId,
                    (string) ($config['query'] ?? 'workflow'),
                    isset($config['collection_id']) ? (int) $config['collection_id'] : null,
                    5
                );
                $count = count($search['results'] ?? []);
                $summary = 'Knowledge search returned ' . $count . ' result(s).';
                $output = ['count' => $count];
                break;

            case 'ai_analysis':
                if (++$state['ai_calls'] > self::MAX_AI_CALLS) {
                    return ['status' => 'failed', 'summary' => 'Workflow exceeded maximum AI calls.', 'output' => []];
                }
                $field = (string) ($config['field'] ?? 'priority');
                $value = $state['trigger'][$field] ?? null;
                $classification = self::classifyValue((string) $value, $state['trigger']);
                $summary = 'AI analysis classified this item as ' . $classification . '.';
                $output = ['classification' => $classification];
                break;

            case 'notification':
                $recipientId = self::resolveUserId($db, (string) ($config['recipient_resolver'] ?? 'workflow_owner'), $workflow, $state['trigger']);
                $title = self::renderTemplate((string) ($config['title_template'] ?? 'Workflow notification'), $workflow, $state['trigger']);
                $message = self::renderTemplate((string) ($config['summary_template'] ?? 'Workflow notification'), $workflow, $state['trigger']);
                if ($dryRun) {
                    $summary = 'Notification would be sent to recipient #' . ($recipientId ?? 0) . '.';
                    $output = ['recipient_id' => $recipientId];
                    break;
                }
                if (!$recipientId) {
                    return ['status' => 'failed', 'summary' => 'Unable to resolve notification recipient.', 'output' => []];
                }
                AIAutomationSchedulerService::notify($db, $recipientId, $title, $message, null, null);
                $summary = 'Notification sent.';
                $output = ['recipient_id' => $recipientId];
                break;

            case 'create_task':
                $assigneeId = self::resolveUserId($db, (string) ($config['assignee_resolver'] ?? 'workflow_owner'), $workflow, $state['trigger']) ?: $userId;
                $title = self::renderTemplate((string) ($config['title_template'] ?? 'Workflow task'), $workflow, $state['trigger']);
                $description = self::renderTemplate((string) ($config['description_template'] ?? ''), $workflow, $state['trigger']);
                if ($dryRun) {
                    $summary = 'Task would be created for user #' . $assigneeId . '.';
                    $output = ['assignee_id' => $assigneeId];
                    break;
                }
                $task = AITaskService::createTask($db, $assigneeId, $title, $description, 'Created by workflow #' . $workflow['id']);
                if (!$task['success']) {
                    return ['status' => 'failed', 'summary' => $task['error'] ?? 'Task creation failed.', 'output' => []];
                }
                $summary = 'Task created.';
                $output = ['task_id' => $task['id']];
                break;

            case 'connector_capability':
                if (!class_exists('AIIntegrationService')) {
                    return ['status' => 'failed', 'summary' => 'Integration service is not available.', 'output' => []];
                }
                if ($dryRun) {
                    $summary = 'Connector capability would execute: ' . ($config['capability_key'] ?? 'unknown');
                    $output = ['capability_key' => $config['capability_key'] ?? null];
                    break;
                }
                $capabilityInput = is_array($config['input'] ?? null) ? $config['input'] : [];
                $connectorResult = AIIntegrationService::executeCapability(
                    $db,
                    $userId,
                    isset($workflow['company_id']) && $workflow['company_id'] !== null ? (int) $workflow['company_id'] : 1,
                    (string) ($config['capability_key'] ?? ''),
                    $capabilityInput,
                    $userPermissions,
                    [
                        'actor_type' => 'workflow',
                        'connection_id' => (int) ($config['connection_id'] ?? 0),
                        'confirmed' => !empty($config['confirmed']),
                    ]
                );
                if (!$connectorResult['success']) {
                    return ['status' => 'failed', 'summary' => $connectorResult['error'] ?? 'Connector execution failed.', 'output' => []];
                }
                if (!empty($connectorResult['requires_confirmation'])) {
                    return ['status' => 'waiting', 'summary' => $connectorResult['message'] ?? 'Connector capability awaiting confirmation.', 'output' => $connectorResult['preview'] ?? [], 'waiting_type' => 'waiting_confirmation', 'resume_after' => null];
                }
                $summary = 'Connector capability executed.';
                $output = ['result' => $connectorResult['normalized'] ?? $connectorResult['data'] ?? []];
                break;

            case 'outbound_webhook':
                if (!class_exists('AIIntegrationService')) {
                    return ['status' => 'failed', 'summary' => 'Integration service is not available.', 'output' => []];
                }
                if ($dryRun) {
                    $summary = 'Outbound webhook would be sent.';
                    $output = ['webhook_key' => $config['webhook_key'] ?? null];
                    break;
                }
                $payload = is_array($config['payload'] ?? null) ? $config['payload'] : [];
                $webhookResult = AIIntegrationService::deliverWebhook(
                    $db,
                    isset($workflow['company_id']) && $workflow['company_id'] !== null ? (int) $workflow['company_id'] : 1,
                    $userId,
                    (string) ($config['webhook_key'] ?? ''),
                    (string) ($config['event_name'] ?? 'workflow.completed'),
                    $payload,
                    ['trust_level' => 'validated']
                );
                if (!$webhookResult['success']) {
                    return ['status' => 'failed', 'summary' => $webhookResult['error'] ?? 'Webhook delivery failed.', 'output' => []];
                }
                $summary = 'Webhook delivered.';
                $output = ['delivery_id' => $webhookResult['delivery_id'] ?? null];
                break;

            case 'approval_request':
                $approverId = self::resolveUserId($db, (string) ($config['approver_resolver'] ?? 'workflow_owner'), $workflow, $state['trigger']);
                $title = self::renderTemplate((string) ($config['title_template'] ?? 'Workflow approval required'), $workflow, $state['trigger']);
                $message = self::renderTemplate((string) ($config['summary_template'] ?? 'Approval required'), $workflow, $state['trigger']);
                if ($dryRun) {
                    $summary = 'Approval would be requested from user #' . ($approverId ?? 0) . '.';
                    break;
                }
                if (!$approverId) {
                    return ['status' => 'failed', 'summary' => 'Unable to resolve workflow approver.', 'output' => []];
                }
                $db->prepare("
                    INSERT INTO ai_workflow_approvals
                        (run_id, workflow_id, workflow_version_id, node_id, requested_by, approver_user_id, status, title, summary, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
                ")->execute([
                    $runId,
                    $workflow['id'],
                    $workflow['current_version_id'],
                    $node['id'],
                    $userId,
                    $approverId,
                    $title,
                    $message,
                ]);
                $status = 'waiting';
                $waitingType = 'waiting_approval';
                $summary = 'Waiting for approval.';
                $output = ['approver_user_id' => $approverId];
                break;

            case 'delay':
                $hours = max(1, min(self::MAX_WAIT_HOURS, (int) ($config['hours'] ?? 1)));
                $status = 'waiting';
                $waitingType = 'waiting';
                $resumeAfter = date('Y-m-d H:i:s', time() + ($hours * 3600));
                $summary = 'Workflow paused for ' . $hours . ' hour(s).';
                $output = ['resume_after' => $resumeAfter];
                break;

            case 'wait_until':
                $dt = trim((string) ($config['datetime'] ?? ''));
                if ($dt === '' || strtotime($dt) === false) {
                    return ['status' => 'failed', 'summary' => 'Invalid wait-until datetime.', 'output' => []];
                }
                $resumeAfter = date('Y-m-d H:i:s', strtotime($dt));
                $status = 'waiting';
                $waitingType = 'waiting';
                $summary = 'Workflow paused until ' . $resumeAfter . '.';
                $output = ['resume_after' => $resumeAfter];
                break;

            case 'if_else':
                $conditionOutput = end($state['outputs']);
                $matched = (bool) ($conditionOutput['matched'] ?? false);
                $summary = $matched ? 'Condition path: yes.' : 'Condition path: no.';
                $output = ['matched' => $matched];
                break;

            case 'end':
                $summary = 'Workflow ended.';
                break;

            default:
                return ['status' => 'failed', 'summary' => 'Unsupported node type: ' . $type, 'output' => []];
        }

        $duration = (int) round((microtime(true) - $started) * 1000);
        return [
            'status' => $status,
            'summary' => $summary,
            'output' => $output,
            'duration_ms' => $duration,
            'waiting_type' => $waitingType,
            'resume_after' => $resumeAfter,
        ];
    }

    private static function recordNodeExecution(PDO $db, int $runId, array $node, array $result): void
    {
        $db->prepare("
            INSERT INTO ai_workflow_run_nodes
                (run_id, node_id, node_type, status, started_at, completed_at, duration_ms, safe_output_summary, error_category, created_at)
            VALUES (?, ?, ?, ?, NOW(), IF(? = 'completed' OR ? = 'failed', NOW(), NULL), ?, ?, ?, NOW())
        ")->execute([
            $runId,
            $node['id'],
            $node['type'],
            $result['status'],
            $result['status'],
            $result['status'],
            $result['duration_ms'] ?? null,
            $result['summary'] ?? null,
            $result['status'] === 'failed' ? 'node_error' : null,
        ]);
    }

    private static function validateNodeConfig(PDO $db, string $type, array $config): array
    {
        $errors = [];
        switch ($type) {
            case 'schedule_trigger':
                $schedule = self::validateTriggerConfig($db, 'scheduled', $config);
                if (!$schedule['valid']) {
                    $errors = array_merge($errors, $schedule['errors']);
                }
                break;
            case 'event_trigger':
                $event = self::validateTriggerConfig($db, 'event', $config);
                if (!$event['valid']) {
                    $errors = array_merge($errors, $event['errors']);
                }
                break;
            case 'field_condition':
                if (empty($config['field'])) {
                    $errors[] = 'Field condition requires a field.';
                }
                if (!self::isValidOperator((string) ($config['operator'] ?? ''))) {
                    $errors[] = 'Field condition uses an invalid operator.';
                }
                break;
            case 'threshold_condition':
                if (empty($config['metric'])) {
                    $errors[] = 'Threshold condition requires a metric.';
                }
                if (!self::isValidOperator((string) ($config['operator'] ?? ''))) {
                    $errors[] = 'Threshold condition uses an invalid operator.';
                }
                break;
            case 'read_tool':
                if (empty($config['tool_id']) || !AIToolRegistry::get((string) $config['tool_id'])) {
                    $errors[] = 'Read tool node references an unknown tool.';
                }
                break;
            case 'delay':
                $hours = (int) ($config['hours'] ?? 0);
                if ($hours < 1 || $hours > self::MAX_WAIT_HOURS) {
                    $errors[] = 'Delay node hours must be between 1 and ' . self::MAX_WAIT_HOURS . '.';
                }
                break;
            case 'wait_until':
                $dt = trim((string) ($config['datetime'] ?? ''));
                if ($dt === '' || strtotime($dt) === false) {
                    $errors[] = 'Wait-until node requires a valid datetime.';
                }
                break;
            case 'notification':
                if (empty($config['recipient_resolver'])) {
                    $errors[] = 'Notification node requires a recipient resolver.';
                }
                break;
            case 'create_task':
                if (empty($config['title_template'])) {
                    $errors[] = 'Task node requires a title template.';
                }
                break;
            case 'connector_capability':
                if (empty($config['capability_key']) || !class_exists('AIIntegrationService') || !AIIntegrationService::getCapability($db, (string) $config['capability_key'])) {
                    $errors[] = 'Connector capability node references an unknown integration capability.';
                }
                break;
            case 'outbound_webhook':
                if (empty($config['webhook_key'])) {
                    $errors[] = 'Outbound webhook node requires a webhook key.';
                }
                break;
        }
        return $errors;
    }

    private static function validateTriggerConfig(PDO $db, string $triggerType, array $config): array
    {
        $errors = [];
        if ($triggerType === 'manual') {
            return ['valid' => true, 'errors' => []];
        }

        if ($triggerType === 'scheduled') {
            $scheduleType = (string) ($config['schedule_type'] ?? '');
            if (!in_array($scheduleType, ['interval', 'daily', 'weekly'], true)) {
                $errors[] = 'Scheduled workflows require schedule_type: interval, daily, or weekly.';
            }
            if (in_array($scheduleType, ['daily', 'weekly'], true)) {
                $time = (string) ($config['time'] ?? '');
                if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
                    $errors[] = 'Scheduled workflows require time in HH:MM format.';
                }
            }
            if ($scheduleType === 'interval') {
                $interval = (int) ($config['interval_minutes'] ?? 0);
                if ($interval < 5) {
                    $errors[] = 'Scheduled interval must be at least 5 minutes.';
                }
            }
            if (!empty($config['timezone']) && !in_array($config['timezone'], DateTimeZone::listIdentifiers(), true)) {
                $errors[] = 'Scheduled workflow timezone is invalid.';
            }
        }

        if ($triggerType === 'event') {
            $eventName = trim((string) ($config['event_name'] ?? ''));
            if ($eventName === '') {
                $errors[] = 'Event workflows require an event name.';
            } elseif (!preg_match('/^[a-z][a-z0-9_.]+$/', $eventName)) {
                $errors[] = 'Event workflows require a registered event-style name.';
            }
            if ($eventName !== '' && self::tableExists($db, 'ai_event_registry')) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM ai_event_registry WHERE event_name = ? AND enabled = 1");
                $stmt->execute([$eventName]);
                if ((int) $stmt->fetchColumn() === 0) {
                    $errors[] = 'Event "' . $eventName . '" is not registered in the event bus.';
                }
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    private static function normalizeGraph(array $graph): array
    {
        $nodes = [];
        foreach (($graph['nodes'] ?? []) as $index => $node) {
            $nodes[] = [
                'id' => trim((string) ($node['id'] ?? ('node_' . ($index + 1)))),
                'type' => trim((string) ($node['type'] ?? 'end')),
                'name' => trim((string) ($node['name'] ?? ucfirst(str_replace('_', ' ', (string) ($node['type'] ?? 'Node'))))),
                'config' => is_array($node['config'] ?? null) ? $node['config'] : [],
            ];
        }
        $edges = [];
        foreach (($graph['edges'] ?? []) as $edge) {
            $edges[] = [
                'from' => trim((string) ($edge['from'] ?? '')),
                'to' => trim((string) ($edge['to'] ?? '')),
                'label' => trim((string) ($edge['label'] ?? 'next')),
            ];
        }
        return ['nodes' => $nodes, 'edges' => $edges];
    }

    private static function insertVersion(PDO $db, int $workflowId, int $versionNumber, string $status, array $triggerConfig, array $graph, array $validation, int $userId, ?string $draftPrompt, ?string $draftSummary): int
    {
        $db->prepare("
            INSERT INTO ai_workflow_versions
                (workflow_id, version_number, version_status, trigger_config_json, graph_json, validation_json,
                 ai_draft_prompt, ai_draft_summary, node_count, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $workflowId,
            $versionNumber,
            $status,
            json_encode($triggerConfig),
            json_encode($graph),
            json_encode($validation),
            $draftPrompt,
            $draftSummary,
            count($graph['nodes'] ?? []),
            $userId,
        ]);

        return (int) $db->lastInsertId();
    }

    private static function getAllowedLifecycleActions(array $workflow): array
    {
        $status = $workflow['status'] ?? 'draft';
        return match ($status) {
            'draft' => ['mark_testing', 'archive'],
            'testing' => ['approve', 'archive'],
            'approved' => ['publish', 'archive'],
            'published' => ['enable', 'disable', 'archive'],
            'enabled' => ['disable', 'archive'],
            'disabled' => ['enable', 'archive'],
            default => [],
        };
    }

    public static function getAvailableActions(PDO $db, int $workflowId, int $userId): array
    {
        $workflow = self::getWorkflow($db, $workflowId);
        if (!$workflow) {
            return [];
        }
        return self::getAllowedLifecycleActions($workflow);
    }

    private static function normalizeTriggerType(string $triggerType): string
    {
        return in_array($triggerType, ['manual', 'scheduled', 'event'], true) ? $triggerType : 'manual';
    }

    private static function classifySafety(array $graph): string
    {
        $hasApproval = false;
        $hasTask = false;
        $hasAI = false;
        foreach (($graph['nodes'] ?? []) as $node) {
            $type = $node['type'] ?? '';
            if ($type === 'approval_request') {
                $hasApproval = true;
            }
            if ($type === 'create_task' || $type === 'notification' || $type === 'connector_capability' || $type === 'outbound_webhook') {
                $hasTask = true;
            }
            if (str_starts_with((string) $type, 'ai_')) {
                $hasAI = true;
            }
        }
        if ($hasApproval) {
            return 'high';
        }
        if ($hasTask || $hasAI) {
            return 'medium';
        }
        return 'low';
    }

    private static function hasCycle(array $nodes, array $adjacency): bool
    {
        $state = [];
        $visit = function (string $nodeId) use (&$visit, &$state, $adjacency): bool {
            $status = $state[$nodeId] ?? 0;
            if ($status === 1) {
                return true;
            }
            if ($status === 2) {
                return false;
            }
            $state[$nodeId] = 1;
            foreach ($adjacency[$nodeId] ?? [] as $next) {
                if ($visit($next)) {
                    return true;
                }
            }
            $state[$nodeId] = 2;
            return false;
        };

        foreach ($nodes as $node) {
            if ($visit($node['id'])) {
                return true;
            }
        }
        return false;
    }

    private static function countNodesByTypePrefix(array $nodes, string $prefix): int
    {
        $count = 0;
        foreach ($nodes as $node) {
            if (str_starts_with((string) ($node['type'] ?? ''), $prefix)) {
                $count++;
            }
        }
        return $count;
    }

    private static function countSpecificNodeType(array $nodes, string $type): int
    {
        $count = 0;
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === $type) {
                $count++;
            }
        }
        return $count;
    }

    private static function isValidOperator(string $operator): bool
    {
        return in_array($operator, [
            'equals',
            'not_equals',
            'greater_than',
            'less_than',
            'greater_than_or_equal',
            'less_than_or_equal',
            'contains',
            'in',
            'not_in',
            'is_empty',
            'is_not_empty',
            'older_than',
            'newer_than',
        ], true);
    }

    private static function evaluateCondition(array $config, array $context): bool
    {
        $field = (string) ($config['field'] ?? '');
        $operator = (string) ($config['operator'] ?? 'equals');
        $expected = $config['value'] ?? null;
        $actual = $context[$field] ?? null;

        return match ($operator) {
            'equals' => (string) $actual === (string) $expected,
            'not_equals' => (string) $actual !== (string) $expected,
            'greater_than' => (float) $actual > (float) $expected,
            'less_than' => (float) $actual < (float) $expected,
            'greater_than_or_equal' => (float) $actual >= (float) $expected,
            'less_than_or_equal' => (float) $actual <= (float) $expected,
            'contains' => str_contains(strtolower((string) $actual), strtolower((string) $expected)),
            'in' => in_array($actual, is_array($expected) ? $expected : [$expected], true),
            'not_in' => !in_array($actual, is_array($expected) ? $expected : [$expected], true),
            'is_empty' => $actual === null || $actual === '',
            'is_not_empty' => !($actual === null || $actual === ''),
            'older_than' => self::daysBetween((string) $actual) > (int) $expected,
            'newer_than' => self::daysBetween((string) $actual) < (int) $expected,
            default => false,
        };
    }

    private static function daysBetween(string $date): int
    {
        $ts = strtotime($date);
        if ($ts === false) {
            return 0;
        }
        return (int) floor((time() - $ts) / 86400);
    }

    private static function classifyValue(string $value, array $context): string
    {
        $days = isset($context['age_days']) ? (int) $context['age_days'] : 0;
        $amount = isset($context['amount']) ? (float) $context['amount'] : 0.0;
        $lower = strtolower($value);
        if (str_contains($lower, 'urgent') || $days >= 14 || $amount >= 50000) {
            return 'urgent';
        }
        if ($days >= 7 || $amount >= 10000) {
            return 'needs_review';
        }
        return 'normal';
    }

    private static function resolveNextNodeId(array $node, array $nextMap, array $result): ?string
    {
        $edges = $nextMap[$node['id']] ?? [];
        if (empty($edges)) {
            return null;
        }
        if (($node['type'] ?? '') === 'if_else') {
            $label = !empty($result['output']['matched']) ? 'yes' : 'no';
            foreach ($edges as $edge) {
                if (strtolower((string) ($edge['label'] ?? '')) === $label) {
                    return $edge['to'];
                }
            }
        }
        if (in_array($node['type'] ?? '', ['field_condition', 'threshold_condition'], true)) {
            $label = !empty($result['output']['matched']) ? 'yes' : 'no';
            foreach ($edges as $edge) {
                if (strtolower((string) ($edge['label'] ?? '')) === $label || strtolower((string) ($edge['label'] ?? '')) === 'next') {
                    return $edge['to'];
                }
            }
        }
        return $edges[0]['to'] ?? null;
    }

    private static function findTriggerNodeId(array $graph): ?string
    {
        foreach (($graph['nodes'] ?? []) as $node) {
            if (str_ends_with((string) ($node['type'] ?? ''), '_trigger')) {
                return $node['id'];
            }
        }
        return null;
    }

    private static function renderTemplate(string $template, array $workflow, array $context): string
    {
        $replacements = [
            '{{workflow.name}}' => (string) ($workflow['name'] ?? 'Workflow'),
            '{{company_id}}' => (string) ($workflow['company_id'] ?? ''),
            '{{record_id}}' => (string) ($context['record_id'] ?? ''),
            '{{status}}' => (string) ($context['status'] ?? ''),
            '{{age_days}}' => (string) ($context['age_days'] ?? ''),
        ];
        return strtr($template, $replacements);
    }

    private static function resolveUserId(PDO $db, string $resolver, array $workflow, array $context): ?int
    {
        if ($resolver === 'workflow_owner') {
            return (int) ($workflow['created_by'] ?? 0) ?: null;
        }
        if ($resolver === 'current_user') {
            return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        }
        if ($resolver === 'procurement_manager') {
            $stmt = $db->query("
                SELECT u.id
                FROM users u
                LEFT JOIN user_roles ur ON ur.user_id = u.id
                LEFT JOIN roles r ON r.id = ur.role_id
                WHERE LOWER(COALESCE(r.name, '')) LIKE '%procurement%'
                ORDER BY u.id ASC
                LIMIT 1
            ");
            $id = $stmt ? $stmt->fetchColumn() : false;
            if ($id) {
                return (int) $id;
            }
        }
        if (preg_match('/^user:(\d+)$/', $resolver, $m)) {
            return (int) $m[1];
        }
        if (!empty($context['owner_user_id'])) {
            return (int) $context['owner_user_id'];
        }
        return null;
    }

    private static function getUserPermissions(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("
            SELECT DISTINCT CONCAT(p.module, '.', p.name) AS perm
            FROM permissions p
            JOIN role_permissions rp ON rp.permission_id = p.id
            JOIN user_roles ur ON ur.role_id = rp.role_id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'perm');
    }

    private static function generateWorkflowKey(PDO $db, string $name): string
    {
        $base = preg_replace('/[^a-z0-9]+/', '_', strtolower($name));
        $base = trim((string) $base, '_');
        if ($base === '') {
            $base = 'workflow';
        }
        $key = $base;
        $counter = 1;
        while (true) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM ai_workflows WHERE workflow_key = ?");
            $stmt->execute([$key]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $key;
            }
            $counter++;
            $key = $base . '_' . $counter;
        }
    }

    private static function draftName(string $prompt): string
    {
        $clean = preg_replace('/\s+/', ' ', trim($prompt));
        $clean = mb_substr($clean, 0, 80);
        return ucfirst($clean);
    }

    private static function inferModule(string $prompt, array $context): string
    {
        if (!empty($context['module'])) {
            return (string) $context['module'];
        }
        $lower = strtolower($prompt);
        if (str_contains($lower, 'requisition') || str_contains($lower, 'procurement')) {
            return 'procurement';
        }
        return 'ai';
    }

    private static function draftTriggerConfig(string $prompt, string $triggerType): array
    {
        $lower = strtolower($prompt);
        if ($triggerType === 'scheduled') {
            $config = ['schedule_type' => 'daily', 'time' => '09:00', 'timezone' => 'Asia/Dubai'];
            if (preg_match('/every\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)/i', $prompt, $m)) {
                $config['schedule_type'] = 'weekly';
                $config['day_of_week'] = strtolower($m[1]);
            }
            if (preg_match('/at\s+(\d{1,2}):(\d{2})/i', $prompt, $m)) {
                $config['time'] = str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
            }
            return $config;
        }
        if ($triggerType === 'event') {
            $eventName = 'system.user.created';
            if (str_contains($lower, 'requisition')) {
                $eventName = 'procurement.requisition.created';
            }
            return ['event_name' => $eventName, 'filters' => []];
        }
        return ['label' => 'Manual run'];
    }

    private static function draftPrimaryCondition(string $prompt, int $days): array
    {
        $lower = strtolower($prompt);
        if (str_contains($lower, 'pending')) {
            return ['field' => 'status', 'operator' => 'equals', 'value' => 'Pending'];
        }
        if (str_contains($lower, 'overdue') || str_contains($lower, 'older than')) {
            return ['field' => 'age_days', 'operator' => 'greater_than_or_equal', 'value' => $days];
        }
        return ['field' => 'status', 'operator' => 'is_not_empty', 'value' => ''];
    }

    private static function buildTemplateGraph(string $type): array
    {
        return [
            'nodes' => [
                ['id' => 'trigger_1', 'type' => $type === 'scheduled' ? 'schedule_trigger' : 'event_trigger', 'name' => 'Trigger', 'config' => $type === 'scheduled' ? ['schedule_type' => 'daily', 'time' => '09:00', 'timezone' => 'Asia/Dubai'] : ['event_name' => 'procurement.requisition.created', 'filters' => []]],
                ['id' => 'condition_1', 'type' => 'field_condition', 'name' => 'Condition', 'config' => ['field' => 'status', 'operator' => 'equals', 'value' => 'Pending']],
                ['id' => 'notify_1', 'type' => 'notification', 'name' => 'Notify', 'config' => ['recipient_resolver' => 'workflow_owner', 'title_template' => 'Workflow Alert: {{workflow.name}}', 'summary_template' => 'Condition matched for record {{record_id}}.', 'severity' => 'medium']],
                ['id' => 'end_1', 'type' => 'end', 'name' => 'End', 'config' => []],
            ],
            'edges' => [
                ['from' => 'trigger_1', 'to' => 'condition_1', 'label' => 'next'],
                ['from' => 'condition_1', 'to' => 'notify_1', 'label' => 'yes'],
                ['from' => 'notify_1', 'to' => 'end_1', 'label' => 'next'],
            ],
        ];
    }

    private static function buildTaskTemplateGraph(): array
    {
        return [
            'nodes' => [
                ['id' => 'trigger_1', 'type' => 'event_trigger', 'name' => 'Event Trigger', 'config' => ['event_name' => 'procurement.requisition.created', 'filters' => []]],
                ['id' => 'condition_1', 'type' => 'threshold_condition', 'name' => 'Age Threshold', 'config' => ['metric' => 'age_days', 'operator' => 'greater_than_or_equal', 'value' => 7]],
                ['id' => 'task_1', 'type' => 'create_task', 'name' => 'Create Task', 'config' => ['title_template' => 'Review overdue requisition', 'description_template' => 'Workflow created a follow-up for requisition {{record_id}}.', 'assignee_resolver' => 'procurement_manager']],
                ['id' => 'end_1', 'type' => 'end', 'name' => 'End', 'config' => []],
            ],
            'edges' => [
                ['from' => 'trigger_1', 'to' => 'condition_1', 'label' => 'next'],
                ['from' => 'condition_1', 'to' => 'task_1', 'label' => 'yes'],
                ['from' => 'task_1', 'to' => 'end_1', 'label' => 'next'],
            ],
        ];
    }

    private static function buildManualAnalysisTemplateGraph(): array
    {
        return [
            'nodes' => [
                ['id' => 'trigger_1', 'type' => 'manual_trigger', 'name' => 'Manual Trigger', 'config' => ['label' => 'Run manually']],
                ['id' => 'tool_1', 'type' => 'read_tool', 'name' => 'Read Tool', 'config' => ['tool_id' => 'procurement.search_purchase_requisitions', 'input' => ['status' => 'pending_approval']]],
                ['id' => 'analysis_1', 'type' => 'ai_analysis', 'name' => 'Analysis', 'config' => ['analysis_type' => 'classify_urgency', 'field' => 'status']],
                ['id' => 'notify_1', 'type' => 'notification', 'name' => 'Notify', 'config' => ['recipient_resolver' => 'workflow_owner', 'title_template' => 'Manual workflow review completed', 'summary_template' => 'Review completed for workflow {{workflow.name}}.', 'severity' => 'low']],
                ['id' => 'end_1', 'type' => 'end', 'name' => 'End', 'config' => []],
            ],
            'edges' => [
                ['from' => 'trigger_1', 'to' => 'tool_1', 'label' => 'next'],
                ['from' => 'tool_1', 'to' => 'analysis_1', 'label' => 'next'],
                ['from' => 'analysis_1', 'to' => 'notify_1', 'label' => 'next'],
                ['from' => 'notify_1', 'to' => 'end_1', 'label' => 'next'],
            ],
        ];
    }

    private static function buildApprovalTemplateGraph(): array
    {
        return [
            'nodes' => [
                ['id' => 'trigger_1', 'type' => 'event_trigger', 'name' => 'Event Trigger', 'config' => ['event_name' => 'procurement.requisition.created', 'filters' => []]],
                ['id' => 'approval_1', 'type' => 'approval_request', 'name' => 'Approval', 'config' => ['title_template' => 'Workflow approval required', 'summary_template' => 'Please review workflow {{workflow.name}} for record {{record_id}}.', 'approver_resolver' => 'procurement_manager']],
                ['id' => 'end_1', 'type' => 'end', 'name' => 'End', 'config' => []],
            ],
            'edges' => [
                ['from' => 'trigger_1', 'to' => 'approval_1', 'label' => 'next'],
                ['from' => 'approval_1', 'to' => 'end_1', 'label' => 'yes'],
            ],
        ];
    }

    private static function audit(PDO $db, int $userId, string $action, int $workflowId, array $details = []): void
    {
        try {
            $db->prepare("
                INSERT INTO ai_audit_log (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, ?, 'workflow', ?, ?, NOW())
            ")->execute([$userId, $action, $workflowId, json_encode($details)]);
        } catch (\Throwable $e) {
        }
    }

    private static function tableExists(PDO $db, string $table): bool
    {
        try {
            $stmt = $db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
