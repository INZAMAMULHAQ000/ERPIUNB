<?php
/**
 * AI F32 — Communication F25 Agent Integration
 * 
 * Routes communication tasks through F25 agent runtime with governance.
 * Section 27 of F32 specification.
 */

class AICommunicationF25Integration {

    private $companyId;
    private $userId;

    public function __construct($companyId, $userId) {
        $this->companyId = $companyId;
        $this->userId = $userId;
    }

    /**
     * Create an F25 agent task for batch communication preparation.
     *
     * @param array $params Task parameters
     * @return array Created task info
     */
    public function createCommunicationTask($params) {
        $taskType = $params['task_type'] ?? 'draft_followups';
        $criteria = $params['criteria'] ?? [];
        $priority = $params['priority'] ?? 'normal';

        // 1. Check F24 governance — emergency controls
        if (class_exists('AIEmergencyControlsService')) {
            if (method_exists('AIEmergencyControlsService', 'areAgentsDisabled')) {
                if (AIEmergencyControlsService::areAgentsDisabled()) {
                    return ['error' => 'Autonomous agents are currently disabled'];
                }
            }
        }

        // 2. Classify risk for the task
        $riskLevel = $this->classifyTaskRisk($taskType, $criteria);

        // 3. Create the F25 task
        $taskData = [
            'task_type'   => 'communication_' . $taskType,
            'title'       => $this->getTaskTitle($taskType, $criteria),
            'description' => $this->getTaskDescription($taskType, $criteria),
            'priority'    => $priority,
            'risk_level'  => $riskLevel,
            'source'      => 'f32_communication',
            'criteria'    => $criteria,
            'context'     => [
                'module' => 'communications',
                'action' => $taskType,
                'criteria' => $criteria,
            ],
        ];

        // Route through F25
        if (class_exists('AIAgentRuntimeService')) {
            $runtime = new AIAgentRuntimeService($this->companyId, $this->userId);
            if (method_exists($runtime, 'createTask')) {
                $task = $runtime->createTask($taskData);
                if (!empty($task['task_id'])) {
                    $this->recordEvent('task_created', $task['task_id'], [
                        'task_type' => $taskType,
                        'risk_level' => $riskLevel,
                    ]);
                    return $task;
                }
            }
        }

        // Fallback: create task directly
        return $this->createDirectTask($taskData);
    }

    /**
     * Execute a communication task step (called by F25 runtime).
     */
    public function executeStep($taskId, $step, $context) {
        $action = $step['action'] ?? '';

        switch ($action) {
            case 'find_eligible_records':
                return $this->findEligibleRecords($step['params'] ?? []);

            case 'generate_drafts':
                return $this->generateBatchDrafts($step['params'] ?? [], $context);

            case 'request_approval':
                return $this->requestApproval($taskId, $step['params'] ?? []);

            default:
                return ['error' => "Unknown step action: {$action}"];
        }
    }

    /**
     * Find eligible records for communication tasks.
     */
    private function findEligibleRecords($params) {
        $db = $this->getDb();
        if (!$db) return ['records' => [], 'count' => 0];

        $criteria = $params['criteria'] ?? [];
        $targetType = $criteria['target'] ?? 'invoice';

        $records = [];
        $sql = null;

        switch ($targetType) {
            case 'invoice':
                $sql = "SELECT i.id, i.invoice_number, i.total_amount, i.due_date, c.name as customer_name, c.email
                        FROM invoices i
                        LEFT JOIN customers c ON i.customer_id = c.id AND c.company_id = i.company_id
                        WHERE i.company_id = ? AND i.status != 'paid' AND i.due_date < CURDATE()
                        ORDER BY i.due_date ASC LIMIT ?";
                break;

            case 'quotation':
                $sql = "SELECT q.id, q.quotation_number, q.valid_until, c.name as customer_name, c.email
                        FROM quotations q
                        LEFT JOIN customers c ON q.customer_id = c.id AND c.company_id = q.company_id
                        WHERE q.company_id = ? AND q.status = 'sent' AND q.created_at < DATE_SUB(NOW(), INTERVAL 5 DAY)
                        ORDER BY q.created_at ASC LIMIT ?";
                break;

            case 'supplier':
                $sql = "SELECT po.id, po.po_number, po.delivery_date, s.name as supplier_name, s.email
                        FROM purchase_orders po
                        LEFT JOIN suppliers s ON po.supplier_id = s.id AND s.company_id = po.company_id
                        WHERE po.company_id = ? AND po.status NOT IN ('received','cancelled') AND po.delivery_date < CURDATE()
                        ORDER BY po.delivery_date ASC LIMIT ?";
                break;

            case 'ticket':
                $sql = "SELECT id, ticket_number, subject, created_at
                        FROM support_tickets
                        WHERE company_id = ? AND status NOT IN ('resolved','closed')
                        AND created_at < DATE_SUB(NOW(), INTERVAL 5 DAY)
                        ORDER BY created_at ASC LIMIT ?";
                break;
        }

        if (!$sql) return ['records' => [], 'count' => 0];

        $limit = min($criteria['limit'] ?? 50, 200);
        $stmt = $db->prepare($sql);
        if (!$stmt) return ['records' => [], 'count' => 0];

        $stmt->bind_param('ii', $this->companyId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        $stmt->close();

        return ['records' => $records, 'count' => count($records), 'target' => $targetType];
    }

    /**
     * Generate batch drafts for multiple records.
     */
    private function generateBatchDrafts($params, $context) {
        $records = $context['find_eligible_records']['records'] ?? [];
        $draftType = $params['communication_type'] ?? 'email';
        $tone = $params['tone'] ?? 'professional';

        $draftService = new AICommunicationDraftService($this->companyId, $this->userId);
        $drafts = [];
        $errors = [];

        foreach ($records as $record) {
            try {
                $result = $draftService->createDraft([
                    'context_type'       => $params['target'] ?? 'customer',
                    'context_id'         => $record['id'],
                    'communication_type' => $draftType,
                    'tone'               => $tone,
                    'instructions'       => $params['instructions'] ?? '',
                ]);

                if (!empty($result['error'])) {
                    $errors[] = ['record_id' => $record['id'], 'error' => $result['error']];
                } else {
                    $drafts[] = [
                        'record_id' => $record['id'],
                        'draft_id'  => $result['draft_id'],
                        'subject'   => $result['subject'],
                        'risk_level'=> $result['risk_level'],
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = ['record_id' => $record['id'], 'error' => $e->getMessage()];
            }
        }

        return [
            'drafts' => $drafts,
            'errors' => $errors,
            'total' => count($records),
            'success' => count($drafts),
            'failed' => count($errors),
        ];
    }

    /**
     * Request approval for batch sending.
     */
    private function requestApproval($taskId, $params) {
        $safetyService = new AICommunicationSafetyService($this->companyId, $this->userId);

        return [
            'approval_required' => true,
            'message' => 'Batch communication requires approval before sending',
            'draft_count' => $params['draft_count'] ?? 0,
            'risk_level' => $params['risk_level'] ?? 'medium',
        ];
    }

    // ============================================================
    // PRIVATE METHODS
    // ============================================================

    private function classifyTaskRisk($taskType, $criteria) {
        $risk = 'low';

        if (in_array($taskType, ['bulk_send', 'escalation'])) {
            $risk = 'high';
        } elseif ($taskType === 'draft_followups') {
            $risk = 'medium';
        }

        if (($criteria['limit'] ?? 0) > 50) {
            $risk = 'high';
        }

        return $risk;
    }

    private function getTaskTitle($taskType, $criteria) {
        $titles = [
            'draft_followups'  => 'Prepare follow-up communications',
            'bulk_reminders'   => 'Generate batch payment reminders',
            'escalation'       => 'Prepare escalation communications',
            'bulk_drafts'      => 'Generate batch communication drafts',
        ];

        $base = $titles[$taskType] ?? 'Communication task';
        $target = $criteria['target'] ?? '';

        return $target ? "{$base} for {$target}" : $base;
    }

    private function getTaskDescription($taskType, $criteria) {
        return "AI communication task: {$taskType}. Target: " . ($criteria['target'] ?? 'all') . ". " .
               "This task will find eligible records, generate drafts, and request approval before any sending.";
    }

    private function createDirectTask($taskData) {
        $db = $this->getDb();
        if (!$db) return ['error' => 'Database not available'];

        $taskKey = bin2hex(random_bytes(16));

        $sql = "INSERT INTO ai_agent_tasks
                (company_id, user_id, task_key, task_type, title, description, priority, status, source, metadata)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)";

        $stmt = $db->prepare($sql);
        if (!$stmt) return ['error' => 'Query failed'];

        $metadata = json_encode([
            'risk_level' => $taskData['risk_level'] ?? 'low',
            'context' => $taskData['context'] ?? [],
        ]);

        $stmt->bind_param('isssssss',
            $this->companyId, $this->userId, $taskKey,
            $taskData['task_type'], $taskData['title'], $taskData['description'],
            $taskData['priority'], $taskData['source'], $metadata
        );

        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        return ['task_id' => $id, 'task_key' => $taskKey, 'status' => 'pending'];
    }

    private function recordEvent($eventType, $entityId, $metadata = []) {
        $db = $this->getDb();
        if (!$db) return;
        $sql = "INSERT INTO ai_communication_events (company_id, event_type, entity_type, entity_id, user_id, metadata)
                VALUES (?, ?, 'task', ?, ?, ?)";
        $stmt = $db->prepare($sql);
        if (!$stmt) return;
        $stmt->bind_param('isisi', $this->companyId, $eventType, $entityId, $this->userId, json_encode($metadata));
        $stmt->execute();
        $stmt->close();
    }

    private function getDb() {
        if (function_exists('getDB')) return getDB();
        global $db;
        return $db ?? null;
    }
}
