<?php
/**
 * AI F32 — Communication Context Builder
 * 
 * Controlled context assembly with F24 permission enforcement.
 * Assembles authorized ERP data before it reaches the AI provider.
 */

class AICommunicationContextService {

    private $companyId;
    private $userId;

    public function __construct($companyId, $userId) {
        $this->companyId = $companyId;
        $this->userId = $userId;
    }

    /**
     * Build complete communication context from an ERP record and related data.
     * F24 enforcement: data is filtered BEFORE reaching the AI provider.
     *
     * @param string $contextType customer,supplier,employee,invoice,po,quotation,ticket,document
     * @param int    $contextId   Record ID
     * @param array  $options     Additional options (maxHistory, includeDocs, etc.)
     * @return array ['context' => [...], 'sources' => [...], 'classification' => string]
     */
    public function buildContext($contextType, $contextId, $options = []) {
        $context = [
            'context_type'  => $contextType,
            'context_id'    => $contextId,
            'assembled_at'  => date('Y-m-d H:i:s'),
            'assembled_by'  => $this->userId,
            'sections'      => [],
            'sources'       => [],
            'classification'=> 'internal',
            'redactions'    => [],
        ];

        // 1. Primary ERP record
        $primaryRecord = $this->getAuthorizedRecord($contextType, $contextId);
        if ($primaryRecord) {
            $context['sections']['primary'] = $this->filterSensitiveFields($primaryRecord, $contextType);
            $context['sources'][] = [
                'type' => $contextType,
                'id'   => $contextId,
                'name' => $primaryRecord['name'] ?? $primaryRecord['title'] ?? "#{$contextId}",
            ];
        }

        // 2. Related records
        $relatedRecords = $this->getRelatedRecords($contextType, $contextId);
        if (!empty($relatedRecords)) {
            $context['sections']['related'] = $relatedRecords;
        }

        // 3. Communication history (bounded)
        $maxHistory = $options['max_history'] ?? 20;
        $history = $this->getCommunicationHistory($contextType, $contextId, $maxHistory);
        if (!empty($history)) {
            $context['sections']['history'] = $history;
            $context['sources'][] = [
                'type' => 'communication_history',
                'count'=> count($history),
            ];
        }

        // 4. Relevant documents (if authorized)
        if (!empty($options['include_documents'])) {
            $documents = $this->getRelevantDocuments($contextType, $contextId);
            if (!empty($documents)) {
                $context['sections']['documents'] = $documents;
            }
        }

        // 5. Workflow status
        $workflow = $this->getWorkflowStatus($contextType, $contextId);
        if ($workflow) {
            $context['sections']['workflow'] = $workflow;
        }

        // 6. Relevant F26 intelligence
        $intelligence = $this->getRelevantIntelligence($contextType, $contextId);
        if (!empty($intelligence)) {
            $context['sections']['intelligence'] = $intelligence;
            $context['sources'][] = [
                'type' => 'intelligence',
                'count'=> count($intelligence),
            ];
        }

        // 7. Financial/account summary (F24 classified)
        $financial = $this->getFinancialSummary($contextType, $contextId);
        if ($financial) {
            $context['sections']['financial'] = $this->filterSensitiveFields($financial, 'financial');
        }

        // 8. F24 classification of the assembled context
        $context['classification'] = $this->classifyContext($context);

        // 9. Apply F24 data classification filtering
        $context = $this->applyGovernanceFiltering($context);

        return $context;
    }

    /**
     * Get an authorized ERP record with permission check.
     */
    private function getAuthorizedRecord($type, $id) {
        $db = $this->getDb();
        if (!$db) return null;

        $tableMap = [
            'customer'    => ['table' => 'customers',    'name_col' => 'name'],
            'supplier'    => ['table' => 'suppliers',    'name_col' => 'name'],
            'employee'    => ['table' => 'employees',    'name_col' => 'CONCAT(first_name, " ", last_name)'],
            'invoice'     => ['table' => 'invoices',     'name_col' => 'invoice_number'],
            'po'          => ['table' => 'purchase_orders', 'name_col' => 'po_number'],
            'quotation'   => ['table' => 'quotations',   'name_col' => 'quotation_number'],
            'ticket'      => ['table' => 'support_tickets', 'name_col' => 'ticket_number'],
            'product'     => ['table' => 'products',     'name_col' => 'name'],
            'order'       => ['table' => 'sales_orders', 'name_col' => 'order_number'],
        ];

        if (!isset($tableMap[$type])) return null;

        $table = $tableMap[$type]['table'];

        // Tenant isolation: always filter by company_id
        $sql = "SELECT * FROM {$table} WHERE id = ? AND company_id = ? LIMIT 1";
        $stmt = $db->prepare($sql);
        if (!$stmt) return null;

        $stmt->bind_param('ii', $id, $this->companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $record = $result->fetch_assoc();
        $stmt->close();

        return $record;
    }

    /**
     * Get related records (e.g., invoice → customer, PO → supplier).
     */
    private function getRelatedRecords($type, $id) {
        $db = $this->getDb();
        if (!$db) return [];

        $relations = [
            'invoice' => [
                ['sql' => "SELECT id, name, email, phone FROM customers WHERE id = (SELECT customer_id FROM invoices WHERE id = ? AND company_id = ?) LIMIT 1", 'label' => 'Customer'],
            ],
            'po' => [
                ['sql' => "SELECT id, name, email, phone FROM suppliers WHERE id = (SELECT supplier_id FROM purchase_orders WHERE id = ? AND company_id = ?) LIMIT 1", 'label' => 'Supplier'],
            ],
            'quotation' => [
                ['sql' => "SELECT id, name, email, phone FROM customers WHERE id = (SELECT customer_id FROM quotations WHERE id = ? AND company_id = ?) LIMIT 1", 'label' => 'Customer'],
            ],
            'ticket' => [
                ['sql' => "SELECT id, name, email FROM customers WHERE id = (SELECT customer_id FROM support_tickets WHERE id = ? AND company_id = ?) LIMIT 1", 'label' => 'Customer'],
            ],
            'order' => [
                ['sql' => "SELECT id, name, email FROM customers WHERE id = (SELECT customer_id FROM sales_orders WHERE id = ? AND company_id = ?) LIMIT 1", 'label' => 'Customer'],
            ],
            'customer' => [
                ['sql' => "SELECT COUNT(*) as open_invoices FROM invoices WHERE customer_id = ? AND company_id = ? AND status != 'paid'", 'label' => 'Invoice Summary'],
            ],
            'supplier' => [
                ['sql' => "SELECT COUNT(*) as open_pos FROM purchase_orders WHERE supplier_id = ? AND company_id = ? AND status NOT IN ('received','cancelled')", 'label' => 'PO Summary'],
            ],
        ];

        $related = [];
        if (!isset($relations[$type])) return $related;

        foreach ($relations[$type] as $rel) {
            $stmt = $db->prepare($rel['sql']);
            if (!$stmt) continue;
            $stmt->bind_param('ii', $id, $this->companyId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            if ($row) {
                $related[$rel['label']] = $this->filterSensitiveFields($row, 'related');
            }
        }

        return $related;
    }

    /**
     * Get bounded communication history for a record.
     */
    private function getCommunicationHistory($type, $id, $limit = 20) {
        $db = $this->getDb();
        if (!$db) return [];

        // Try ai_communication_drafts first, then ERP communications table
        $sql = "SELECT id, communication_type, subject, body, tone, status, created_at 
                FROM ai_communication_drafts 
                WHERE context_type = ? AND context_id = ? AND company_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('siii', $type, $id, $this->companyId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = [
                'type'    => $row['communication_type'],
                'subject' => $row['subject'],
                'preview' => mb_substr($row['body'], 0, 200),
                'tone'    => $row['tone'],
                'status'  => $row['status'],
                'date'    => $row['created_at'],
            ];
        }
        $stmt->close();

        return array_reverse($history); // chronological order
    }

    /**
     * Get relevant documents (F27 integration).
     */
    private function getRelevantDocuments($type, $id) {
        $db = $this->getDb();
        if (!$db) return [];

        $recordType = $type;
        if ($type === 'po') {
            $recordType = 'purchase_order';
        } elseif ($type === 'order') {
            $recordType = 'sales_order';
        }

        $sql = "SELECT document_id AS id, file_name AS original_filename, document_type, status, created_at 
                FROM ai_documents 
                WHERE company_id = ? 
                AND related_record_type = ? AND related_record_id = ?
                ORDER BY created_at DESC 
                LIMIT 5";
        $stmt = $db->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('isi', $this->companyId, $recordType, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $docs = [];
        while ($row = $result->fetch_assoc()) {
            $docs[] = [
                'id'       => $row['id'],
                'filename' => $row['original_filename'],
                'type'     => $row['document_type'],
                'status'   => $row['status'],
                'date'     => $row['created_at'],
            ];
        }
        $stmt->close();

        return $docs;
    }

    /**
     * Get workflow status for a record.
     */
    private function getWorkflowStatus($type, $id) {
        $db = $this->getDb();
        if (!$db) return null;

        // Check if there's a workflow status on the record itself
        $tableMap = [
            'invoice' => ['table' => 'invoices',     'status_col' => 'status'],
            'po'      => ['table' => 'purchase_orders', 'status_col' => 'status'],
            'quotation' => ['table' => 'quotations', 'status_col' => 'status'],
            'ticket'  => ['table' => 'support_tickets', 'status_col' => 'status'],
            'order'   => ['table' => 'sales_orders', 'status_col' => 'status'],
        ];

        if (!isset($tableMap[$type])) return null;

        $table = $tableMap[$type]['table'];
        $sql = "SELECT {$tableMap[$type]['status_col']} as status FROM {$table} WHERE id = ? AND company_id = ? LIMIT 1";
        $stmt = $db->prepare($sql);
        if (!$stmt) return null;

        $stmt->bind_param('ii', $id, $this->companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row ? ['status' => $row['status']] : null;
    }

    /**
     * Get relevant F26 intelligence events for a record.
     */
    private function getRelevantIntelligence($type, $id) {
        $db = $this->getDb();
        if (!$db) return [];

        $sql = "SELECT id, event_type, title, severity, priority, status 
                FROM ai_intelligence_events 
                WHERE company_id = ? 
                AND entity_type = ? AND entity_id = ? 
                AND status IN ('new','acknowledged') 
                ORDER BY created_at DESC 
                LIMIT 5";
        $stmt = $db->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('isi', $this->companyId, $type, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $events = [];
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'id'       => $row['id'],
                'type'     => $row['event_type'],
                'title'    => $row['title'],
                'severity' => $row['severity'],
                'priority' => $row['priority'],
            ];
        }
        $stmt->close();

        return $events;
    }

    /**
     * Get financial summary (F24 classified — masked for external use).
     */
    private function getFinancialSummary($type, $id) {
        $db = $this->getDb();
        if (!$db) return null;

        if ($type === 'customer') {
            $sql = "SELECT 
                        COUNT(*) as total_invoices,
                        SUM(CASE WHEN status != 'paid' THEN total_amount ELSE 0 END) as outstanding_amount,
                        SUM(CASE WHEN status != 'paid' AND due_date < CURDATE() THEN total_amount ELSE 0 END) as overdue_amount,
                        MAX(CASE WHEN status != 'paid' THEN due_date END) as next_due_date
                    FROM invoices 
                    WHERE customer_id = ? AND company_id = ?";
            $stmt = $db->prepare($sql);
            if (!$stmt) return null;
            $stmt->bind_param('ii', $id, $this->companyId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row;
        }

        if ($type === 'supplier') {
            $sql = "SELECT 
                        COUNT(*) as total_pos,
                        SUM(CASE WHEN status NOT IN ('received','cancelled') THEN total_amount ELSE 0 END) as outstanding_amount,
                        SUM(CASE WHEN status NOT IN ('received','cancelled') AND delivery_date < CURDATE() THEN total_amount ELSE 0 END) as overdue_amount
                    FROM purchase_orders 
                    WHERE supplier_id = ? AND company_id = ?";
            $stmt = $db->prepare($sql);
            if (!$stmt) return null;
            $stmt->bind_param('ii', $id, $this->companyId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row;
        }

        return null;
    }

    /**
     * Filter sensitive fields based on context type and F24 classification.
     */
    private function filterSensitiveFields($data, $contextType) {
        if (!is_array($data)) return $data;

        $sensitiveFields = [
            'password', 'password_hash', 'api_key', 'secret', 'token',
            'access_token', 'refresh_token', 'private_key', 'credit_card',
            'ssn', 'tax_id', 'bank_account', 'routing_number',
        ];

        $filtered = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitiveFields)) {
                $filtered[$key] = '***REDACTED***';
                continue;
            }
            // Mask email bodies for external transmission classification
            if ($contextType === 'external' && $lowerKey === 'body' && strlen($value) > 500) {
                $filtered[$key] = mb_substr($value, 0, 500) . '...[truncated for context]';
                continue;
            }
            $filtered[$key] = $value;
        }

        return $filtered;
    }

    /**
     * Classify assembled context data sensitivity.
     */
    private function classifyContext($context) {
        $classifications = ['public', 'internal', 'confidential', 'restricted'];
        $highestIndex = 1; // default: internal

        foreach ($context['sections'] as $section) {
            if (is_array($section)) {
                $json = json_encode($section);
                if (preg_match('/\b(password|api_key|secret|token|credit_card|ssn)\b/i', $json)) {
                    $highestIndex = 3; // restricted
                    break;
                }
                if (preg_match('/\b(financial|invoice|payment|amount|balance)\b/i', $json)) {
                    $highestIndex = max($highestIndex, 2); // confidential
                }
            }
        }

        return $classifications[$highestIndex];
    }

    /**
     * Apply F24 governance filtering — remove data that should not reach the AI provider.
     */
    private function applyGovernanceFiltering($context) {
        // Check F24 emergency controls
        if (class_exists('AIEmergencyControlsService')) {
            $db = $this->getDb();
            if ($db && AIEmergencyControlsService::isControlActive($db, 'disable_all_ai')) {
                return [
                    'error' => 'AI is currently disabled by emergency controls',
                    'sections' => [],
                    'sources' => [],
                ];
            }
        }

        if (function_exists('AIEmergencyControlsService::isAiDisabled') && AIEmergencyControlsService::isAiDisabled()) {
            return [
                'error' => 'AI is currently disabled by emergency controls',
                'sections' => [],
                'sources' => [],
            ];
        }

        // Apply secret redaction
        if (class_exists('AISecretProtectionService')) {
            $secretService = new AISecretProtectionService();
            $contextJson = json_encode($context);
            $redacted = $secretService->redactSecrets($contextJson);
            if ($redacted !== $contextJson) {
                $context = json_decode($redacted, true);
                $context['redactions'][] = 'secrets_redacted';
            }
        }

        // Apply data classification masking
        if (class_exists('AIDataClassificationService')) {
            $classificationService = new AIDataClassificationService();
            if (method_exists($classificationService, 'maskDataForExternal')) {
                $contextJson = json_encode($context);
                $masked = $classificationService->maskDataForExternal($contextJson, $context['classification']);
                if ($masked !== $contextJson) {
                    $context = json_decode($masked, true);
                    $context['redactions'][] = 'classification_masking_applied';
                }
            }
        }

        return $context;
    }

    /**
     * Get database connection.
     */
    private function getDb() {
        if (function_exists('getDB')) {
            return getDB();
        }
        global $db;
        return $db ?? null;
    }
}
