<?php
/**
 * AI Document Service
 * 
 * F20/F27: Document Intelligence — Registry, CRUD, metadata, relationships, duplicates, expiry.
 * All methods are static and take $db as first parameter.
 *
 * Test 1: Auto-classification on register
 * Test 6: Duplicate detection on register
 * Test 19: Sanitized audit metadata
 * Test 23: Human-verified values protected
 */
class AIDocumentService {

    // ── Document Registry CRUD ───────────────────────────────

    /**
     * Register a new document in the AI document registry.
     * Test 1: Auto-classifies document type based on filename/content
     * Test 6: Checks for duplicates via file hash
     */
    public static function register($db, $data) {
        $companyId = (int)($data['company_id'] ?? 0);
        $fileName  = trim($data['file_name'] ?? '');
        $createdBy = (int)($data['created_by'] ?? 0);

        if ($companyId <= 0 || $fileName === '' || $createdBy <= 0) {
            return ['success' => false, 'error' => 'Missing required fields'];
        }

        // Test 20: Validate upload before registering
        if (class_exists('AIDocumentSecurityService') && !empty($data['tmp_name'])) {
            $securityCheck = AIDocumentSecurityService::validateUpload($db, $data);
            if (!$securityCheck['safe']) {
                return ['success' => false, 'error' => 'File failed security validation: ' . implode('; ', $securityCheck['errors'])];
            }
        }

        // Test 1: Auto-classify document type from filename if not provided
        $docType = $data['document_type'] ?? 'unknown';
        if ($docType === 'unknown' || empty($docType)) {
            $docType = self::autoClassifyFromFilename($fileName);
        }

        $stmt = $db->prepare("INSERT INTO ai_documents (
            company_id, file_reference, file_name, mime_type, file_size, file_hash,
            source_module, related_record_type, related_record_id, document_type,
            processing_status, extraction_status, is_sensitive, sensitive_category, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'uploaded', 'pending', ?, ?, ?)");

        $stmt->execute([
            $companyId,
            $data['file_reference'] ?? null,
            $fileName,
            $data['mime_type'] ?? null,
            (int)($data['file_size'] ?? 0),
            $data['file_hash'] ?? null,
            $data['source_module'] ?? null,
            $data['related_record_type'] ?? null,
            !empty($data['related_record_id']) ? (int)$data['related_record_id'] : null,
            $docType,
            (int)($data['is_sensitive'] ?? 0),
            $data['sensitive_category'] ?? null,
            $createdBy,
        ]);

        $docId = (int)$db->lastInsertId();

        // Test 19: Audit log with sanitized metadata (no secrets)
        $auditMeta = [
            'file_name' => $fileName,
            'mime_type' => $data['mime_type'] ?? null,
            'document_type' => $docType,
            'source_module' => $data['source_module'] ?? null,
        ];
        if (class_exists('AISecretProtectionService')) {
            $auditMeta = AISecretProtectionService::sanitizeLogData($auditMeta);
        }
        self::logAudit($db, $companyId, $docId, 'registered', $createdBy, $auditMeta);

        // Test 6: Check for duplicates
        if (!empty($data['file_hash'])) {
            $dupeResult = self::checkDuplicates($db, $companyId, $docId, $data['file_hash']);
        }

        // F24: Check sensitive policy
        if (!empty($data['sensitive_category'])) {
            self::applySensitivePolicy($db, $companyId, $docId, $data['sensitive_category']);
        }

        // F24: Classify document sensitivity from content if available
        if (class_exists('AIDocumentSecurityService') && !empty($data['extracted_text'])) {
            AIDocumentSecurityService::classifyDocument($db, $docId, $data['extracted_text']);
        }

        // F27: Version relationship suggestions
        if (class_exists('AIDocumentIntelligenceService')) {
            try {
                AIDocumentIntelligenceService::suggestVersionRelationships($db, $companyId, $docId);
            } catch (\Throwable $e) {
                // Version suggestions are advisory only.
            }
        }

        // F27: Trigger F26 intelligence analysis
        if (class_exists('AIDocumentF26Integration')) {
            try {
                AIDocumentF26Integration::analyzeDocument($db, $companyId, $docId);
            } catch (\Throwable $e) {
                // Intelligence analysis is advisory
            }
        }

        return ['success' => true, 'document_id' => $docId];
    }

    /**
     * Test 1: Auto-classify document type from filename patterns.
     */
    private static function autoClassifyFromFilename(string $fileName): string
    {
        $lower = strtolower($fileName);

        $patterns = [
            'invoice' => ['invoice', 'inv-', 'inv_', 'tax_invoice'],
            'quotation' => ['quotation', 'quote', 'quot-', 'rfq'],
            'purchase_order' => ['purchase_order', 'po-', 'po_', 'purchase order'],
            'contract' => ['contract', 'agreement', 'slA', 'terms'],
            'delivery_note' => ['delivery', 'dn-', 'delivery_note', 'goods_receipt'],
            'receipt' => ['receipt', 'payment_receipt', 'payment proof'],
            'policy' => ['policy', 'procedure', 'guideline'],
            'report' => ['report', 'analysis', 'summary'],
        ];

        foreach ($patterns as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return $type;
                }
            }
        }

        return 'unknown';
    }

    /**
     * Get a document by ID.
     * Test 12: Tenant isolation enforced via companyId parameter.
     */
    public static function getById($db, $documentId, $companyId = null) {
        $sql = "SELECT * FROM ai_documents WHERE document_id = ?";
        $params = [(int)$documentId];
        if ($companyId !== null) {
            $sql .= " AND company_id = ?";
            $params[] = (int)$companyId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * List documents for a company with filters.
     * Test 12: Always scoped by company_id for tenant isolation.
     */
    public static function listByCompany($db, $companyId, $filters = []) {
        $sql = "SELECT d.*, u.name as created_by_name
            FROM ai_documents d
            LEFT JOIN users u ON u.id = d.created_by
            WHERE d.company_id = ? AND d.status = 'active'";
        $params = [(int)$companyId];

        if (!empty($filters['document_type'])) {
            $sql .= " AND d.document_type = ?";
            $params[] = $filters['document_type'];
        }
        if (!empty($filters['processing_status'])) {
            $sql .= " AND d.processing_status = ?";
            $params[] = $filters['processing_status'];
        }
        if (!empty($filters['extraction_status'])) {
            $sql .= " AND d.extraction_status = ?";
            $params[] = $filters['extraction_status'];
        }
        if (!empty($filters['source_module'])) {
            $sql .= " AND d.source_module = ?";
            $params[] = $filters['source_module'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (d.file_name LIKE ? OR d.extracted_text LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }
        // Test 12: Filter by sensitivity if requested
        if (isset($filters['exclude_sensitive']) && $filters['exclude_sensitive']) {
            $sql .= " AND d.is_sensitive = 0";
        }

        $sql .= " ORDER BY d.created_at DESC";
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update document metadata.
     * Test 23: Protects human-verified values from being overwritten by AI.
     */
    public static function update($db, $documentId, $data, $actorUserId = null) {
        // Test 23: Check if this is an AI-initiated update and document has human-verified extractions
        $isAiUpdate = ($actorUserId === null);
        if ($isAiUpdate) {
            $protected = self::isHumanVerified($db, $documentId);
            if ($protected) {
                // Only allow status updates on human-verified documents, not data changes
                $allowedDuringProtection = ['processing_status', 'status'];
                $data = array_intersect_key($data, array_flip($allowedDuringProtection));
                if (empty($data)) {
                    return ['success' => false, 'error' => 'Document has human-verified extractions — AI cannot modify data'];
                }
            }
        }

        $fields = [];
        $params = [];
        $allowed = ['file_reference', 'document_type', 'processing_status', 'extraction_status',
                     'extracted_text', 'ocr_confidence', 'ocr_language', 'duplicate_status',
                     'duplicate_of_id', 'is_sensitive', 'sensitive_category', 'classification_confidence',
                     'classification_reason', 'reviewed_by', 'reviewed_at', 'status'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f = ?";
                $params[] = $data[$f];
            }
        }

        if (empty($fields)) return ['success' => false, 'error' => 'No fields to update'];

        $params[] = (int)$documentId;
        $db->prepare("UPDATE ai_documents SET " . implode(', ', $fields) . " WHERE document_id = ?")->execute($params);

        if ($actorUserId) {
            $doc = self::getById($db, $documentId);
            if ($doc) {
                // Test 19: Sanitized audit metadata
                $meta = ['updated_fields' => array_keys($data)];
                if (class_exists('AISecretProtectionService')) {
                    $meta = AISecretProtectionService::sanitizeLogData($meta);
                }
                self::logAudit($db, $doc['company_id'], $documentId, 'extraction_reviewed', $actorUserId, $meta);
            }
        }

        return ['success' => true];
    }

    /**
     * Test 23: Check if document has human-verified extractions.
     */
    private static function isHumanVerified($db, int $documentId): bool
    {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM ai_document_extractions
            WHERE document_id = ? AND review_status IN ('accepted', 'partially_accepted')
        ");
        $stmt->execute([$documentId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Archive a document.
     */
    public static function archive($db, $documentId) {
        $db->prepare("UPDATE ai_documents SET status = 'archived' WHERE document_id = ?")->execute([(int)$documentId]);
        return ['success' => true];
    }

    /**
     * Soft-delete a document.
     */
    public static function delete($db, $documentId, $actorUserId = null) {
        $db->prepare("UPDATE ai_documents SET status = 'deleted' WHERE document_id = ?")->execute([(int)$documentId]);
        if ($actorUserId) {
            $doc = self::getById($db, $documentId);
            if ($doc) self::logAudit($db, $doc['company_id'], $documentId, 'deleted', $actorUserId);
        }
        return ['success' => true];
    }

    // ── Duplicate Detection ──────────────────────────────────

    /**
     * Test 6: Check for duplicate documents based on file hash.
     * Also checks extracted data (invoice number, supplier, total) for semantic duplicates.
     */
    public static function checkDuplicates($db, $companyId, $documentId, $fileHash) {
        // Exact file hash match
        $stmt = $db->prepare("SELECT document_id, file_name FROM ai_documents 
            WHERE company_id = ? AND file_hash = ? AND document_id != ? AND status = 'active'");
        $stmt->execute([$companyId, $fileHash, (int)$documentId]);
        $dupes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($dupes)) {
            self::update($db, $documentId, [
                'duplicate_status' => 'duplicate',
                'duplicate_of_id' => $dupes[0]['document_id'],
            ]);
            return ['is_duplicate' => true, 'duplicate_of' => $dupes[0]];
        }

        // Check similar filename
        $doc = self::getById($db, $documentId);
        if ($doc) {
            $stmt2 = $db->prepare("SELECT document_id, file_name FROM ai_documents 
                WHERE company_id = ? AND file_name = ? AND document_id != ? AND status = 'active'");
            $stmt2->execute([$companyId, $doc['file_name'], (int)$documentId]);
            $nameDupes = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($nameDupes)) {
                self::update($db, $documentId, [
                    'duplicate_status' => 'possible_duplicate',
                    'duplicate_of_id' => $nameDupes[0]['document_id'],
                ]);
                return ['is_duplicate' => false, 'possible_duplicate' => true, 'similar_to' => $nameDupes[0]];
            }

            // Test 6: Semantic duplicate detection via extraction data
            $semanticDupe = self::checkSemanticDuplicate($db, $companyId, $documentId, $doc);
            if ($semanticDupe) {
                return $semanticDupe;
            }
        }

        return ['is_duplicate' => false];
    }

    /**
     * Test 6: Check for semantic duplicates using extracted data (invoice#, supplier, total).
     */
    private static function checkSemanticDuplicate($db, int $companyId, int $documentId, array $doc): ?array
    {
        if (!self::tableExists($db, 'ai_document_extractions')) return null;

        $stmt = $db->prepare("
            SELECT e.extracted_data, e.document_id
            FROM ai_document_extractions e
            JOIN ai_documents d ON d.document_id = e.document_id
            WHERE d.company_id = ? AND d.document_id != ? AND d.status = 'active'
            AND d.document_type = ?
            ORDER BY d.created_at DESC LIMIT 50
        ");
        $stmt->execute([$companyId, $documentId, $doc['document_type'] ?? 'unknown']);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get current document's extraction
        $currentExt = $db->prepare("
            SELECT extracted_data FROM ai_document_extractions
            WHERE document_id = ? ORDER BY created_at DESC LIMIT 1
        ");
        $currentExt->execute([$documentId]);
        $currentRow = $currentExt->fetch(PDO::FETCH_ASSOC);
        if (!$currentRow) return null;

        $currentData = json_decode($currentRow['extracted_data'], true) ?: [];

        foreach ($candidates as $candidate) {
            $candidateData = json_decode($candidate['extracted_data'], true) ?: [];

            // Match on invoice number + supplier + total (semantic duplicate)
            $matchScore = 0;
            $checks = 0;

            foreach (['invoice_number', 'po_number', 'quotation_number', 'contract_number'] as $numField) {
                if (!empty($currentData[$numField]) && !empty($candidateData[$numField])) {
                    $checks++;
                    if ($currentData[$numField] === $candidateData[$numField]) $matchScore++;
                }
            }

            if (!empty($currentData['supplier_name']) && !empty($candidateData['supplier_name'])) {
                $checks++;
                if (strtolower($currentData['supplier_name']) === strtolower($candidateData['supplier_name'])) $matchScore++;
            }

            if (!empty($currentData['total']) && !empty($candidateData['total'])) {
                $checks++;
                if (abs((float)$currentData['total'] - (float)$candidateData['total']) < 0.01) $matchScore++;
            }

            if ($checks >= 2 && $matchScore >= $checks) {
                self::update($db, $documentId, [
                    'duplicate_status' => 'possible_duplicate',
                    'duplicate_of_id' => $candidate['document_id'],
                ]);
                return [
                    'is_duplicate' => false,
                    'possible_duplicate' => true,
                    'semantic_match' => true,
                    'similar_to' => ['document_id' => $candidate['document_id']],
                ];
            }
        }

        return null;
    }

    // ── Document Relationships ───────────────────────────────

    public static function createRelationship($db, $companyId, $sourceDocId, $data) {
        $stmt = $db->prepare("INSERT INTO ai_document_relationships 
            (company_id, source_document_id, target_document_id, target_module, target_record_id, relationship_type, confidence, method, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $companyId,
            (int)$sourceDocId,
            !empty($data['target_document_id']) ? (int)$data['target_document_id'] : null,
            $data['target_module'] ?? null,
            $data['target_record_id'] ? (int)$data['target_record_id'] : null,
            $data['relationship_type'] ?? 'manual',
            $data['confidence'] ?? null,
            $data['method'] ?? 'manual',
            (int)($data['created_by'] ?? 0),
        ]);

        return ['success' => true, 'relationship_id' => (int)$db->lastInsertId()];
    }

    public static function getRelationships($db, $documentId) {
        $stmt = $db->prepare("SELECT r.*, 
            d.file_name as target_doc_name, d.document_type as target_doc_type
            FROM ai_document_relationships r
            LEFT JOIN ai_documents d ON d.document_id = r.target_document_id
            WHERE r.source_document_id = ?
            ORDER BY r.created_at DESC");
        $stmt->execute([(int)$documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Expiry Monitoring ────────────────────────────────────

    public static function scheduleExpiryReminders($db, $documentId, $expiryDate) {
        $expiry = new DateTime($expiryDate);
        $intervals = [
            '90_days' => 90,
            '60_days' => 60,
            '30_days' => 30,
            '7_days'  => 7,
        ];

        foreach ($intervals as $type => $days) {
            $reminderDate = clone $expiry;
            $reminderDate->modify("-{$days} days");

            if ($reminderDate > new DateTime()) {
                try {
                    $db->prepare("INSERT INTO ai_document_expiry_reminders 
                        (document_id, reminder_type, expiry_date) VALUES (?, ?, ?)")
                        ->execute([(int)$documentId, $type, $expiryDate]);
                } catch (PDOException $e) {
                    // Duplicate reminder — skip
                }
            }
        }

        return ['success' => true];
    }

    public static function getExpiringDocuments($db, $companyId, $withinDays = 90) {
        $stmt = $db->prepare("SELECT d.*, er.reminder_type, er.expiry_date
            FROM ai_documents d
            JOIN ai_document_expiry_reminders er ON er.document_id = d.document_id
            WHERE d.company_id = ? AND d.status = 'active'
            AND er.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            AND er.is_sent = 0
            ORDER BY er.expiry_date ASC");
        $stmt->execute([$companyId, (int)$withinDays]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Sensitive Document Policies ──────────────────────────

    public static function applySensitivePolicy($db, $companyId, $documentId, $category) {
        $stmt = $db->prepare("SELECT * FROM ai_sensitive_doc_policies WHERE company_id = ? AND category = ?");
        $stmt->execute([$companyId, $category]);
        $policy = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($policy) {
            self::update($db, $documentId, [
                'is_sensitive' => 1,
                'sensitive_category' => $category,
            ]);
            return ['applied' => true, 'policy' => $policy];
        }

        return ['applied' => false];
    }

    public static function canProcessExternally($db, $companyId, $documentId) {
        $doc = self::getById($db, $documentId, $companyId);
        if (!$doc) return ['allowed' => false, 'reason' => 'Document not found'];

        if (empty($doc['sensitive_category'])) {
            return ['allowed' => true];
        }

        $stmt = $db->prepare("SELECT * FROM ai_sensitive_doc_policies WHERE company_id = ? AND category = ?");
        $stmt->execute([$companyId, $doc['sensitive_category']]);
        $policy = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$policy) return ['allowed' => true];
        if (!$policy['external_provider_allowed']) {
            return ['allowed' => false, 'reason' => 'External processing prohibited for this document category'];
        }

        return ['allowed' => true];
    }

    // ── Audit Logging ────────────────────────────────────────

    public static function logAudit($db, $companyId, $documentId, $actionType, $actorUserId, $metadata = null) {
        // Test 19: Sanitize metadata before storing
        if ($metadata && class_exists('AISecretProtectionService')) {
            $metadata = AISecretProtectionService::sanitizeLogData($metadata);
        }

        $db->prepare("INSERT INTO ai_document_audit (company_id, document_id, action_type, actor_user_id, metadata_json) VALUES (?, ?, ?, ?, ?)")
            ->execute([$companyId, $documentId, $actionType, (int)$actorUserId, $metadata ? json_encode($metadata) : null]);
    }

    public static function getAuditTrail($db, $documentId, $limit = 50) {
        $stmt = $db->prepare("SELECT a.*, u.name as actor_name
            FROM ai_document_audit a
            LEFT JOIN users u ON u.id = a.actor_user_id
            WHERE a.document_id = ?
            ORDER BY a.created_at DESC
            LIMIT ?");
        $stmt->execute([(int)$documentId, (int)$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Statistics ───────────────────────────────────────────

    public static function getStats($db, $companyId) {
        $stmt = $db->prepare('SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN document_type = :t1 THEN 1 ELSE 0 END) as quotations,
            SUM(CASE WHEN document_type = :t2 THEN 1 ELSE 0 END) as invoices,
            SUM(CASE WHEN document_type = :t3 THEN 1 ELSE 0 END) as purchase_orders,
            SUM(CASE WHEN document_type = :t4 THEN 1 ELSE 0 END) as contracts,
            SUM(CASE WHEN document_type = :t5 THEN 1 ELSE 0 END) as policies,
            SUM(CASE WHEN processing_status = :s1 THEN 1 ELSE 0 END) as uploaded,
            SUM(CASE WHEN processing_status = :s2 THEN 1 ELSE 0 END) as queued,
            SUM(CASE WHEN processing_status = :s3 THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN processing_status = :s4 THEN 1 ELSE 0 END) as needs_review,
            SUM(CASE WHEN processing_status = :s5 THEN 1 ELSE 0 END) as ready,
            SUM(CASE WHEN processing_status = :s6 THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN extraction_status = :e1 THEN 1 ELSE 0 END) as extraction_pending,
            SUM(CASE WHEN extraction_status = :e2 THEN 1 ELSE 0 END) as extracted,
            SUM(CASE WHEN extraction_status = :e3 THEN 1 ELSE 0 END) as reviewed,
            SUM(CASE WHEN `is_sensitive` = 1 THEN 1 ELSE 0 END) as sensitive_docs,
            SUM(CASE WHEN duplicate_status <> :d1 THEN 1 ELSE 0 END) as duplicates,
            (SELECT COUNT(*) FROM ai_document_qna_sessions q WHERE q.company_id = ai_documents.company_id) as qna_sessions,
            (SELECT COUNT(*) FROM ai_document_action_requests a WHERE a.company_id = ai_documents.company_id) as action_requests
            FROM ai_documents WHERE company_id = :cid AND status = :st');
        $stmt->execute([':cid' => $companyId, ':st' => 'active',
            ':t1' => 'quotation', ':t2' => 'invoice', ':t3' => 'purchase_order', ':t4' => 'contract', ':t5' => 'policy',
            ':s1' => 'uploaded', ':s2' => 'queued', ':s3' => 'processing', ':s4' => 'needs_review', ':s5' => 'ready', ':s6' => 'failed',
            ':e1' => 'pending', ':e2' => 'extracted', ':e3' => 'reviewed', ':d1' => 'unique']);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getQueueStatus($db, $companyId) {
        $stmt = $db->prepare("SELECT 
            status, COUNT(*) as count
            FROM ai_document_queue
            WHERE company_id = ?
            GROUP BY status");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Check if a table exists.
     */
    private static function tableExists($db, string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) return $cache[$table];
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
            $stmt->execute([$table]);
            $cache[$table] = (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            $cache[$table] = false;
        }
        return $cache[$table];
    }
}
