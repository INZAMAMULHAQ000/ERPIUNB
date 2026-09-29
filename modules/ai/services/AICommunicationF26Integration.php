<?php
/**
 * AI F32 — Communication F26 Intelligence Integration
 * 
 * Creates communication-related intelligence events and integrates with F26.
 * Section 28 of F32 specification.
 */

class AICommunicationF26Integration {

    private $companyId;
    private $userId;

    public function __construct($companyId, $userId) {
        $this->companyId = $companyId;
        $this->userId = $userId;
    }

    /**
     * Detect communication-related intelligence events.
     *
     * @return array Intelligence events with suggested communications
     */
    public function detectCommunicationEvents() {
        $events = [];

        // 1. Customer follow-up overdue
        $events = array_merge($events, $this->detectCustomerFollowUpOverdue());

        // 2. Supplier delivery delay
        $events = array_merge($events, $this->detectSupplierDeliveryDelay());

        // 3. Invoice approaching overdue
        $events = array_merge($events, $this->detectInvoiceApproachingOverdue());

        // 4. Quotation unanswered
        $events = array_merge($events, $this->detectQuotationUnanswered());

        // 5. Support issue unresolved
        $events = array_merge($events, $this->detectSupportIssueUnresolved());

        // Store events through F26
        foreach ($events as &$event) {
            $event['intelligence_id'] = $this->createIntelligenceEvent($event);
        }

        return $events;
    }

    /**
     * Generate suggested communication + draft for an intelligence event.
     */
    public function generateSuggestedCommunication($eventId, $eventData) {
        $commType = $eventData['suggested_communication_type'] ?? 'email';
        $tone = $eventData['suggested_tone'] ?? 'professional';
        $contextType = $eventData['context_type'] ?? 'customer';
        $contextId = $eventData['context_id'] ?? null;

        $draftService = new AICommunicationDraftService($this->companyId, $this->userId);

        $result = $draftService->createDraft([
            'context_type'       => $contextType,
            'context_id'         => $contextId,
            'communication_type' => $commType,
            'tone'               => $tone,
            'instructions'       => $eventData['ai_instructions'] ?? '',
        ]);

        return [
            'event_id' => $eventId,
            'draft'    => $result,
        ];
    }

    // ============================================================
    // DETECTION METHODS
    // ============================================================

    private function detectCustomerFollowUpOverdue() {
        $db = $this->getDb();
        if (!$db) return [];

        $sql = "SELECT d.id, d.context_id, d.subject, d.created_at, c.name as customer_name
                FROM ai_communication_drafts d
                LEFT JOIN customers c ON d.context_id = c.id AND c.company_id = d.company_id
                WHERE d.company_id = ? AND d.status = 'sent' AND d.context_type = 'customer'
                AND d.created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
                AND d.context_id NOT IN (
                    SELECT context_id FROM ai_communication_drafts 
                    WHERE context_type = 'customer' AND status = 'draft' AND created_at > d.created_at
                )
                ORDER BY d.created_at ASC LIMIT 10";

        $stmt = $db->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('i', $this->companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $events = [];
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'event_type'  => 'customer_followup_overdue',
                'title'       => "Customer {$row['customer_name']} has not responded to follow-up",
                'severity'    => 'medium',
                'priority'    => 'normal',
                'context_type'=> 'customer',
                'context_id'  => $row['context_id'],
                'evidence'    => ['draft_id' => $row['id'], 'sent_date' => $row['created_at']],
                'suggested_communication_type' => 'email',
                'suggested_tone' => 'professional',
                'ai_instructions' => "Write a polite follow-up asking if the customer has had a chance to review the previous communication.",
            ];
        }
        $stmt->close();

        return $events;
    }

    private function detectSupplierDeliveryDelay() {
        $db = $this->getDb();
        if (!$db) return [];

        $sql = "SELECT po.id, po.po_number, po.delivery_date, s.name as supplier_name, s.id as supplier_id
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
        $events = [];
        while ($row = $result->fetch_assoc()) {
            $daysLate = floor((time() - strtotime($row['delivery_date'])) / 86400);
            $events[] = [
                'event_type'  => 'supplier_delivery_delay',
                'title'       => "PO {$row['po_number']} from {$row['supplier_name']} is {$daysLate} days late",
                'severity'    => $daysLate > 7 ? 'high' : 'medium',
                'priority'    => $daysLate > 7 ? 'high' : 'normal',
                'context_type'=> 'supplier',
                'context_id'  => $row['supplier_id'],
                'evidence'    => ['po_id' => $row['id'], 'po_number' => $row['po_number'], 'delivery_date' => $row['delivery_date'], 'days_late' => $daysLate],
                'suggested_communication_type' => 'email',
                'suggested_tone' => 'firm',
                'ai_instructions' => "Write a firm but professional follow-up requesting an updated ETA for the delayed order.",
            ];
        }
        $stmt->close();

        return $events;
    }

    private function detectInvoiceApproachingOverdue() {
        $db = $this->getDb();
        if (!$db) return [];

        $sql = "SELECT i.id, i.invoice_number, i.total_amount, i.due_date, c.name as customer_name, c.id as customer_id
                FROM invoices i
                LEFT JOIN customers c ON i.customer_id = c.id AND c.company_id = i.company_id
                WHERE i.company_id = ? AND i.status != 'paid'
                AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                ORDER BY i.due_date ASC LIMIT 10";

        $stmt = $db->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('i', $this->companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $events = [];
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'event_type'  => 'invoice_approaching_overdue',
                'title'       => "Invoice {$row['invoice_number']} ({$row['total_amount']}) due on {$row['due_date']} for {$row['customer_name']}",
                'severity'    => 'low',
                'priority'    => 'normal',
                'context_type'=> 'customer',
                'context_id'  => $row['customer_id'],
                'evidence'    => ['invoice_id' => $row['id'], 'amount' => $row['total_amount'], 'due_date' => $row['due_date']],
                'suggested_communication_type' => 'email',
                'suggested_tone' => 'professional',
                'ai_instructions' => "Write a friendly payment reminder for the upcoming due date.",
            ];
        }
        $stmt->close();

        return $events;
    }

    private function detectQuotationUnanswered() {
        $db = $this->getDb();
        if (!$db) return [];

        $sql = "SELECT q.id, q.quotation_number, q.valid_until, c.name as customer_name, c.id as customer_id
                FROM quotations q
                LEFT JOIN customers c ON q.customer_id = c.id AND c.company_id = q.company_id
                WHERE q.company_id = ? AND q.status = 'sent'
                AND q.created_at < DATE_SUB(NOW(), INTERVAL 5 DAY)
                ORDER BY q.created_at ASC LIMIT 10";

        $stmt = $db->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('i', $this->companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $events = [];
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'event_type'  => 'quotation_unanswered',
                'title'       => "Quotation {$row['quotation_number']} to {$row['customer_name']} has no response",
                'severity'    => 'low',
                'priority'    => 'normal',
                'context_type'=> 'customer',
                'context_id'  => $row['customer_id'],
                'evidence'    => ['quotation_id' => $row['id'], 'valid_until' => $row['valid_until']],
                'suggested_communication_type' => 'email',
                'suggested_tone' => 'professional',
                'ai_instructions' => "Write a follow-up asking if the customer has had a chance to review the quotation.",
            ];
        }
        $stmt->close();

        return $events;
    }

    private function detectSupportIssueUnresolved() {
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
        $events = [];
        while ($row = $result->fetch_assoc()) {
            $daysOpen = floor((time() - strtotime($row['created_at'])) / 86400);
            $events[] = [
                'event_type'  => 'support_issue_unresolved',
                'title'       => "Ticket {$row['ticket_number']} open for {$daysOpen} days: {$row['subject']}",
                'severity'    => $row['priority'] === 'high' ? 'high' : 'medium',
                'priority'    => $row['priority'] === 'high' ? 'high' : 'normal',
                'context_type'=> 'ticket',
                'context_id'  => $row['id'],
                'evidence'    => ['ticket_id' => $row['id'], 'days_open' => $daysOpen],
                'suggested_communication_type' => 'internal_message',
                'suggested_tone' => 'professional',
                'ai_instructions' => "Draft an internal escalation message for this unresolved support ticket.",
            ];
        }
        $stmt->close();

        return $events;
    }

    private function createIntelligenceEvent($eventData) {
        if (!class_exists('AIIntelligenceService')) return 0;

        $intelService = new AIIntelligenceService($this->companyId, $this->userId);
        if (method_exists($intelService, 'createEvent')) {
            $result = $intelService->createEvent([
                'event_type' => $eventData['event_type'],
                'title'      => $eventData['title'],
                'severity'   => $eventData['severity'],
                'priority'   => $eventData['priority'],
                'entity_type'=> $eventData['context_type'],
                'entity_id'  => $eventData['context_id'],
                'evidence'   => $eventData['evidence'],
                'source'     => 'f32_communications',
            ]);

            return $result['event_id'] ?? 0;
        }

        return 0;
    }

    private function getDb() {
        if (function_exists('getDB')) return getDB();
        global $db;
        return $db ?? null;
    }
}
