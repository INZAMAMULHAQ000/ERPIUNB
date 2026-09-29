<?php
/**
 * AI F32 — Communication Classification & Follow-Up Service
 * 
 * Classification, priority detection, follow-up detection.
 * Sections 11-13 of F32 specification.
 */

class AICommunicationClassificationService {

    private $companyId;
    private $userId;

    private $validClassifications = [
        'inquiry', 'complaint', 'payment', 'delivery', 'quotation',
        'support', 'sales', 'procurement', 'approval', 'urgent',
        'informational', 'spam',
    ];

    private $validPriorities = ['low', 'normal', 'high', 'urgent'];

    public function __construct($companyId, $userId) {
        $this->companyId = $companyId;
        $this->userId = $userId;
    }

    /**
     * Classify a communication message.
     *
     * @param string $text Message text
     * @param string $contextType Context type (customer, supplier, etc.)
     * @param int    $contextId
     * @return array Classification with priority, confidence, evidence
     */
    public function classify($text, $contextType = null, $contextId = null) {
        // 1. Try deterministic classification first
        $deterministic = $this->deterministicClassify($text);

        // 2. Use AI-assisted classification for nuance
        $aiResult = $this->aiClassify($text, $contextType, $deterministic);

        // 3. Merge results — deterministic takes precedence for obvious cases
        $classification = $this->mergeClassifications($deterministic, $aiResult);

        // 4. Detect priority
        $priority = $this->detectPriority($text, $classification, $contextType, $contextId);

        // 5. Store classification
        $classId = $this->storeClassification([
            'context_type'    => $contextType,
            'context_id'      => $contextId,
            'message_text'    => mb_substr($text, 0, 5000),
            'classification'  => $classification['type'],
            'confidence'      => $classification['confidence'],
            'sub_classification' => $classification['sub_type'] ?? null,
            'priority'        => $priority['level'],
            'priority_reason' => $priority['reason'],
            'priority_evidence' => $priority['evidence'],
            'auto_classified' => 1,
            'provider'        => $aiResult['provider'] ?? null,
        ]);

        // 6. Record F23 event
        $this->recordEvent('classify', $classId, [
            'classification' => $classification['type'],
            'confidence' => $classification['confidence'],
            'priority' => $priority['level'],
        ]);

        return [
            'classification_id' => $classId,
            'classification'    => $classification['type'],
            'confidence'        => $classification['confidence'],
            'sub_classification'=> $classification['sub_type'] ?? null,
            'priority'          => $priority['level'],
            'priority_reason'   => $priority['reason'],
            'priority_evidence' => $priority['evidence'],
            'deterministic'     => $deterministic['type'] !== null,
        ];
    }

    /**
     * Correct a classification (human feedback for F23).
     */
    public function correctClassification($classId, $newClassification, $reason = null) {
        $db = $this->getDb();
        if (!$db) return ['error' => 'Database not available'];

        if (!in_array($newClassification, $this->validClassifications)) {
            return ['error' => 'Invalid classification'];
        }

        $stmt = $db->prepare("UPDATE ai_communication_classifications 
            SET classification = ?, corrected_by = ?, corrected_at = NOW(), correction_reason = ?
            WHERE id = ? AND company_id = ?");
        if (!$stmt) return ['error' => 'Query failed'];

        $stmt->bind_param('siisi', $newClassification, $this->userId, $reason, $classId, $this->companyId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            // Record correction for F23 evaluation
            $this->recordEvent('classify', $classId, [
                'corrected_to' => $newClassification,
                'reason' => $reason,
                'original_classified_by' => 'ai',
            ]);
            return ['success' => true];
        }

        return ['error' => 'Classification not found'];
    }

    /**
     * Detect follow-ups needed based on ERP conditions.
     *
     * @return array List of follow-up suggestions with evidence
     */
    public function detectFollowUps() {
        $followUps = [];

        // 1. Unanswered messages
        $followUps = array_merge($followUps, $this->detectUnansweredMessages());

        // 2. Overdue commitments
        $followUps = array_merge($followUps, $this->detectOverdueCommitments());

        // 3. Overdue invoices
        $followUps = array_merge($followUps, $this->detectOverdueInvoices());

        // 4. Pending quotations
        $followUps = array_merge($followUps, $this->detectPendingQuotations());

        // 5. Unresolved support tickets
        $followUps = array_merge($followUps, $this->detectUnresolvedTickets());

        // 6. Supplier delivery delays
        $followUps = array_merge($followUps, $this->detectSupplierDelays());

        // Store each follow-up
        foreach ($followUps as &$fu) {
            $fu['followup_id'] = $this->storeFollowUp($fu);
        }

        // Record F23 event
        $this->recordEvent('followup_detected', 0, [
            'count' => count($followUps),
            'types' => array_unique(array_column($followUps, 'context_type')),
        ]);

        return $followUps;
    }

    /**
     * Update follow-up status.
     */
    public function updateFollowUp($followupId, $action, $options = []) {
        $db = $this->getDb();
        if (!$db) return ['error' => 'Database not available'];

        $statusMap = [
            'draft'     => 'drafting',
            'schedule'  => 'scheduled',
            'task'      => 'tasked',
            'ignore'    => 'ignored',
            'snooze'    => 'snoozed',
            'complete'  => 'completed',
        ];

        $newStatus = $statusMap[$action] ?? $action;
        $snoozeUntil = $options['snooze_until'] ?? null;

        $sql = "UPDATE ai_communication_followups SET status = ?";
        $params = [$newStatus];
        $types = 's';

        if ($newStatus === 'snoozed' && $snoozeUntil) {
            $sql .= ", snoozed_until = ?";
            $params[] = $snoozeUntil;
            $types .= 's';
        }

        $sql .= " WHERE id = ? AND company_id = ?";
        $params[] = $followupId;
        $params[] = $this->companyId;
        $types .= 'ii';

        $stmt = $db->prepare($sql);
        if (!$stmt) return ['error' => 'Query failed'];

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();

        return ['success' => true, 'status' => $newStatus];
    }

    // ============================================================
    // DETERMINISTIC CLASSIFICATION
    // ============================================================

    private function deterministicClassify($text) {
        $lower = strtolower($text);

        $patterns = [
            'complaint'   => '/\b(complain|complaint|dissatisfied|unhappy|poor quality|terrible|awful|unacceptable|refund|disappointed)\b/i',
            'payment'     => '/\b(payment|invoice|bill|amount due|overdue|paid|receipt|remittance|bank transfer)\b/i',
            'delivery'    => '/\b(delivery|shipping|shipped|dispatch|tracking|late|delayed|eta|estimated arrival)\b/i',
            'quotation'   => '/\b(quote|quotation|estimate|proposal|pricing|price list|bid)\b/i',
            'support'     => '/\b(support|help|issue|problem|error|bug|not working|broken|fix)\b/i',
            'inquiry'     => '/\b(inquiry|enquiry|question|information|details|asking|wondering|could you)\b/i',
            'sales'       => '/\b(sale|discount|offer|promotion|deal|special price|bulk order)\b/i',
            'procurement' => '/\b(purchase order|procurement|supplier|vendor|rfq|request for quote)\b/i',
            'approval'    => '/\b(approval|approve|authorized|sign off|confirm|pending approval)\b/i',
            'urgent'      => '/\b(urgent|asap|immediately|emergency|critical|time.sensitive|deadline)\b/i',
        ];

        $matches = [];
        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $lower)) {
                $matches[$type] = 1;
            }
        }

        if (empty($matches)) {
            return ['type' => 'informational', 'confidence' => 0.4];
        }

        // Most specific match wins
        $priority = ['urgent', 'complaint', 'payment', 'delivery', 'approval', 'procurement', 'quotation', 'support', 'sales', 'inquiry', 'informational'];
        foreach ($priority as $p) {
            if (isset($matches[$p])) {
                return ['type' => $p, 'confidence' => 0.7];
            }
        }

        return ['type' => 'informational', 'confidence' => 0.4];
    }

    private function aiClassify($text, $contextType, $deterministic) {
        $prompt = "Classify this business communication message.\n\n";
        $prompt .= "Message: \"{$text}\"\n\n";
        if ($contextType) {
            $prompt .= "Context: {$contextType}\n";
        }
        if ($deterministic['type']) {
            $prompt .= "Preliminary classification: {$deterministic['type']}\n";
        }
        $prompt .= "Valid classifications: " . implode(', ', $this->validClassifications) . "\n\n";
        $prompt .= "Return JSON: {\"type\": \"...\", \"confidence\": 0.0-1.0, \"sub_type\": \"...\", \"reasoning\": \"...\"}\n";

        $aiResult = $this->callAIProvider($prompt);
        $data = json_decode($aiResult['text'] ?? '{}', true);

        if (!is_array($data) || empty($data['type'])) {
            return ['type' => null, 'confidence' => 0, 'provider' => $aiResult['provider'] ?? null];
        }

        if (!in_array($data['type'], $this->validClassifications)) {
            $data['type'] = 'informational';
        }

        return [
            'type'       => $data['type'],
            'confidence' => max(0, min(1, floatval($data['confidence'] ?? 0.5))),
            'sub_type'   => $data['sub_type'] ?? null,
            'reasoning'  => $data['reasoning'] ?? null,
            'provider'   => $aiResult['provider'] ?? null,
        ];
    }

    private function mergeClassifications($deterministic, $ai) {
        // If deterministic is confident, use it
        if ($deterministic['confidence'] >= 0.7 && $deterministic['type']) {
            return [
                'type'       => $deterministic['type'],
                'confidence' => $deterministic['confidence'],
                'sub_type'   => $ai['sub_type'] ?? null,
            ];
        }

        // If AI is confident, use it
        if ($ai['confidence'] >= 0.6 && $ai['type']) {
            return $ai;
        }

        // Default to deterministic or informational
        return [
            'type'       => $deterministic['type'] ?? ($ai['type'] ?? 'informational'),
            'confidence' => max($deterministic['confidence'], $ai['confidence'] ?? 0),
            'sub_type'   => $ai['sub_type'] ?? null,
        ];
    }

    // ============================================================
    // PRIORITY DETECTION
    // ============================================================

    private function detectPriority($text, $classification, $contextType, $contextId) {
        $lower = strtolower($text);
        $level = 'normal';
        $reason = '';
        $evidence = [];

        // Explicit urgency keywords
        if (preg_match('/\b(urgent|asap|immediately|emergency|critical|time.sensitive)\b/i', $text, $m)) {
            $level = 'urgent';
            $reason = 'Explicit urgency language detected';
            $evidence[] = $m[0];
        }

        // Escalation language
        if (preg_match('/\b(escalat|supervisor|manager|complaint|legal|lawyer|threaten)\b/i', $text, $m)) {
            $level = max($level, 'high', SORT_REGULAR);
            $reason = 'Escalation language detected';
            $evidence[] = $m[0];
        }

        // Deadline language
        if (preg_match('/\b(deadline|by\s+(monday|tuesday|wednesday|thursday|friday|tomorrow|today|end of (week|month|day)))\b/i', $text, $m)) {
            $level = max($level, 'high', SORT_REGULAR);
            $reason = 'Deadline language detected';
            $evidence[] = $m[0];
        }

        // Business rules: overdue context
        if ($contextType && $contextId) {
            $overdue = $this->checkOverdueStatus($contextType, $contextId);
            if ($overdue) {
                $level = max($level, 'high', SORT_REGULAR);
                $reason = 'Overdue ERP record';
                $evidence[] = "{$contextType} #{$contextId} is overdue";
            }
        }

        // Classification-based priority
        if ($classification['type'] === 'complaint') {
            $level = max($level, 'high', SORT_REGULAR);
            $reason = $reason ?: 'Complaint classification';
        }
        if ($classification['type'] === 'urgent') {
            $level = 'urgent';
            $reason = $reason ?: 'Urgent classification';
        }

        // Do NOT infer emergencies solely from emotional language
        // (per F32 Section 13)

        return [
            'level'    => $level,
            'reason'   => $reason ?: 'Standard priority',
            'evidence' => $evidence,
        ];
    }

    private function checkOverdueStatus($contextType, $contextId) {
        $db = $this->getDb();
        if (!$db) return false;

        $tableMap = [
            'invoice'   => ['table' => 'invoices',       'date_col' => 'due_date',      'status_col' => 'status'],
            'po'        => ['table' => 'purchase_orders', 'date_col' => 'delivery_date', 'status_col' => 'status'],
            'quotation' => ['table' => 'quotations',     'date_col' => 'valid_until',   'status_col' => 'status'],
            'ticket'    => ['table' => 'support_tickets','date_col' => 'updated_at',    'status_col' => 'status'],
        ];

        if (!isset($tableMap[$contextType])) return false;
        $t = $tableMap[$contextType];

        $sql = "SELECT {$t['date_col']} as date_val, {$t['status_col']} as status_val 
                FROM {$t['table']} WHERE id = ? AND company_id = ? LIMIT 1";
        $stmt = $db->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param('ii', $contextId, $this->companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) return false;

        $dateVal = $row['date_val'];
        $status = strtolower($row['status_val']);

        if ($dateVal && strtotime($dateVal) < time() && !in_array($status, ['paid', 'received', 'closed', 'resolved', 'cancelled'])) {
            return true;
        }

        return false;
    }

    // ============================================================
    // FOLLOW-UP DETECTION
    // ============================================================

    private function detectUnansweredMessages() {
        $db = $this->getDb();
        if (!$db) return [];

        $sql = "SELECT id, context_type, context_id, subject, created_at
                FROM ai_communication_drafts
                WHERE company_id = ? AND status = 'sent' AND recipient IS NOT NULL
                AND id NOT IN (
                    SELECT draft_id FROM ai_communication_drafts 
                    WHERE context_type IS NOT NULL AND status = 'draft'
                )
                AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
                AND deleted_at IS NULL
                ORDER BY created_at DESC LIMIT 20";

        $stmt = $db->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('i', $this->companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'context_type' => $row['context_type'],
                'context_id'   => $row['context_id'],
                'reason'       => "Message '{$row['subject']}' sent on {$row['created_at']} has no response",
                'evidence'     => ['draft_id' => $row['id'], 'sent_date' => $row['created_at']],
                'priority'     => 'normal',
                'suggested_action' => 'draft',
            ];
        }
        $stmt->close();

        return $items;
    }

    private function detectOverdueCommitments() {
        // Simplified: check for follow-ups with overdue status
        return [];
    }

    private function detectOverdueInvoices() {
        $db = $this->getDb();
        if (!$db) return [];

        $sql = "SELECT i.id, i.invoice_number, i.total_amount, i.due_date, c.name as customer_name
                FROM invoices i
                LEFT JOIN customers c ON i.customer_id = c.id AND c.company_id = i.company_id
                WHERE i.company_id = ? AND i.status != 'paid' AND i.due_date < CURDATE()
                ORDER BY i.due_date ASC LIMIT 10";

        $stmt = $db->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('i', $this->companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $daysOverdue = floor((time() - strtotime($row['due_date'])) / 86400);
            $items[] = [
                'context_type' => 'invoice',
                'context_id'   => $row['id'],
                'reason'       => "Invoice {$row['invoice_number']} for {$row['customer_name']} is {$daysOverdue} days overdue ({$row['total_amount']})",
                'evidence'     => [
                    'invoice_number' => $row['invoice_number'],
                    'amount' => $row['total_amount'],
                    'due_date' => $row['due_date'],
                    'days_overdue' => $daysOverdue,
                ],
                'priority'     => $daysOverdue > 30 ? 'high' : 'normal',
                'suggested_action' => 'draft',
            ];
        }
        $stmt->close();

        return $items;
    }

    private function detectPendingQuotations() {
        $db = $this->getDb();
        if (!$db) return [];

        $sql = "SELECT q.id, q.quotation_number, q.valid_until, c.name as customer_name
                FROM quotations q
                LEFT JOIN customers c ON q.customer_id = c.id AND c.company_id = q.company_id
                WHERE q.company_id = ? AND q.status = 'sent' 
                AND q.valid_until IS NOT NULL AND q.valid_until > CURDATE()
                AND q.created_at < DATE_SUB(NOW(), INTERVAL 5 DAY)
                ORDER BY q.valid_until ASC LIMIT 10";

        $stmt = $db->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('i', $this->companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'context_type' => 'quotation',
                'context_id'   => $row['id'],
                'reason'       => "Quotation {$row['quotation_number']} to {$row['customer_name']} has no response (valid until {$row['valid_until']})",
                'evidence'     => [
                    'quotation_number' => $row['quotation_number'],
                    'valid_until' => $row['valid_until'],
                    'customer_name' => $row['customer_name'],
                ],
                'priority'     => 'normal',
                'suggested_action' => 'draft',
            ];
        }
        $stmt->close();

        return $items;
    }

    private function detectUnresolvedTickets() {
        $db = $this->getDb();
        if (!$db) return [];

        $sql = "SELECT id, ticket_number, subject, priority, created_at
                FROM support_tickets
                WHERE company_id = ? AND status NOT IN ('resolved','closed')
                AND created_at < DATE_SUB(NOW(), INTERVAL 5 DAY)
                ORDER BY created_at ASC LIMIT 10";

        $stmt = $db->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('i', $this->companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $daysOpen = floor((time() - strtotime($row['created_at'])) / 86400);
            $items[] = [
                'context_type' => 'ticket',
                'context_id'   => $row['id'],
                'reason'       => "Support ticket {$row['ticket_number']} has been open for {$daysOpen} days without resolution",
                'evidence'     => [
                    'ticket_number' => $row['ticket_number'],
                    'subject' => $row['subject'],
                    'days_open' => $daysOpen,
                ],
                'priority'     => $row['priority'] === 'high' ? 'high' : 'normal',
                'suggested_action' => 'draft',
            ];
        }
        $stmt->close();

        return $items;
    }

    private function detectSupplierDelays() {
        $db = $this->getDb();
        if (!$db) return [];

        $sql = "SELECT po.id, po.po_number, po.delivery_date, s.name as supplier_name
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.id AND s.company_id = po.company_id
                WHERE po.company_id = ? AND po.status NOT IN ('received','cancelled')
                AND po.delivery_date < CURDATE()
                ORDER BY po.delivery_date ASC LIMIT 10";

        $stmt = $db->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('i', $this->companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $daysLate = floor((time() - strtotime($row['delivery_date'])) / 86400);
            $items[] = [
                'context_type' => 'supplier',
                'context_id'   => $row['id'],
                'reason'       => "PO {$row['po_number']} from {$row['supplier_name']} is {$daysLate} days late",
                'evidence'     => [
                    'po_number' => $row['po_number'],
                    'delivery_date' => $row['delivery_date'],
                    'supplier_name' => $row['supplier_name'],
                    'days_late' => $daysLate,
                ],
                'priority'     => $daysLate > 7 ? 'high' : 'normal',
                'suggested_action' => 'draft',
            ];
        }
        $stmt->close();

        return $items;
    }

    // ============================================================
    // STORE / RETRIEVE
    // ============================================================

    private function storeClassification($data) {
        $db = $this->getDb();
        if (!$db) return 0;

        $sql = "INSERT INTO ai_communication_classifications
                (company_id, context_type, context_id, message_text, classification, confidence,
                 sub_classification, priority, priority_reason, priority_evidence, auto_classified, provider)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $db->prepare($sql);
        if (!$stmt) return 0;

        $priorityEvidenceJson = json_encode($data['priority_evidence'] ?? []);
        $stmt->bind_param('iisssdssssis',
            $this->companyId, $data['context_type'], $data['context_id'],
            $data['message_text'], $data['classification'], $data['confidence'],
            $data['sub_classification'], $data['priority'], $data['priority_reason'],
            $priorityEvidenceJson, $data['auto_classified'], $data['provider']
        );

        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    private function storeFollowUp($data) {
        $db = $this->getDb();
        if (!$db) return 0;

        $key = bin2hex(random_bytes(16));

        $sql = "INSERT INTO ai_communication_followups
                (company_id, user_id, followup_key, context_type, context_id, context_data,
                 reason, evidence, suggested_action, status, priority)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'detected', ?)";

        $stmt = $db->prepare($sql);
        if (!$stmt) return 0;

        $contextDataJson = json_encode($data['context_data'] ?? null);
        $evidenceJson = json_encode($data['evidence'] ?? []);

        $stmt->bind_param('iisssssssi',
            $this->companyId, $this->userId, $key,
            $data['context_type'], $data['context_id'], $contextDataJson,
            $data['reason'], $evidenceJson, $data['suggested_action'], $data['priority']
        );

        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    private function callAIProvider($prompt) {
        if (class_exists('AIProviderService')) {
            $providerService = new AIProviderService();
            if (method_exists($providerService, 'complete')) {
                $result = $providerService->complete($prompt);
                if ($result) return $result;
            }
        }
        return ['text' => '{}', 'provider' => 'none', 'model' => 'none', 'tokens_used' => 0, 'cost_usd' => 0];
    }

    private function recordEvent($eventType, $entityId, $metadata = []) {
        $db = $this->getDb();
        if (!$db) return;
        $sql = "INSERT INTO ai_communication_events (company_id, event_type, entity_type, entity_id, user_id, metadata)
                VALUES (?, ?, 'classification', ?, ?, ?)";
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
