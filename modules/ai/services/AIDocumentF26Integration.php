<?php
/**
 * AI F27 — Document → F26 Intelligence Integration.
 *
 * Creates F26 intelligence events from document analysis.
 * Covers Section 17: Document Intelligence → F26.
 *
 * Intelligence events from documents:
 * - Contract expiring soon
 * - Invoice total differs from PO
 * - Supplier quotation significantly above previous
 * - Important payment term detected
 * - Duplicate document detected
 * - Sensitive document uploaded
 */
class AIDocumentF26Integration
{
    /**
     * Analyze a document and create F26 intelligence events where appropriate.
     */
    public static function analyzeDocument(PDO $db, int $companyId, int $documentId): array
    {
        $eventsCreated = 0;
        $doc = AIDocumentService::getById($db, $documentId, $companyId);
        if (!$doc) {
            return ['success' => false, 'error' => 'Document not found'];
        }

        // Get latest extraction
        $stmt = $db->prepare("
            SELECT extracted_data FROM ai_document_extractions
            WHERE document_id = ? ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$documentId]);
        $ext = $stmt->fetch(PDO::FETCH_ASSOC);
        $data = $ext ? json_decode($ext['extracted_data'], true) : [];

        // 1. Contract expiry intelligence
        if (($doc['document_type'] ?? '') === 'contract') {
            $result = self::checkContractExpiry($db, $companyId, $documentId, $data);
            if ($result) $eventsCreated++;
        }

        // 2. Invoice vs PO mismatch
        if (($doc['document_type'] ?? '') === 'invoice' && !empty($data['po_number'])) {
            $result = self::checkInvoicePOMismatch($db, $companyId, $documentId, $data);
            if ($result) $eventsCreated++;
        }

        // 3. Quotation vs previous quotation comparison
        if (($doc['document_type'] ?? '') === 'quotation') {
            $result = self::checkQuotationComparison($db, $companyId, $documentId, $data);
            if ($result) $eventsCreated++;
        }

        // 4. Important payment terms
        $result = self::checkPaymentTerms($db, $companyId, $documentId, $doc, $data);
        if ($result) $eventsCreated++;

        // 5. Duplicate document detected
        if (($doc['duplicate_status'] ?? '') === 'duplicate' || ($doc['duplicate_status'] ?? '') === 'possible_duplicate') {
            $result = self::checkDuplicateDocument($db, $companyId, $documentId, $doc);
            if ($result) $eventsCreated++;
        }

        // 6. Sensitive document uploaded
        if (!empty($doc['is_sensitive'])) {
            $result = self::checkSensitiveDocument($db, $companyId, $documentId, $doc);
            if ($result) $eventsCreated++;
        }

        return ['success' => true, 'events_created' => $eventsCreated];
    }

    /**
     * Check contract expiry and create intelligence event.
     */
    private static function checkContractExpiry(PDO $db, int $companyId, int $documentId, array $data): bool
    {
        $expiryDate = $data['expiry_date'] ?? $data['end_date'] ?? $data['valid_until'] ?? null;
        if (!$expiryDate) return false;

        $daysUntil = (strtotime($expiryDate) - time()) / 86400;
        if ($daysUntil < 0 || $daysUntil > 90) return false;

        $severity = 'medium';
        if ($daysUntil <= 7) $severity = 'critical';
        elseif ($daysUntil <= 30) $severity = 'high';

        AIIntelligenceService::createEvent($db, [
            'company_id' => $companyId,
            'source_module' => 'documents',
            'source_record_type' => 'document',
            'source_record_id' => $documentId,
            'detection_type' => 'rule',
            'detector_key' => 'document_contract_expiry',
            'severity' => $severity,
            'confidence' => 0.9,
            'title' => 'Contract expiring in ' . round($daysUntil) . ' days',
            'explanation' => 'Document ' . ($data['contract_number'] ?? '') . ' expires on ' . $expiryDate . '. Days remaining: ' . round($daysUntil),
            'recommendation' => 'Review contract and initiate renewal discussions.',
            'suggested_action' => 'Review contract renewal',
            'suggested_action_type' => 'review',
            'urgency' => $daysUntil <= 7 ? 'immediate' : 'high',
            'evidence' => ['expiry_date' => $expiryDate, 'days_remaining' => round($daysUntil), 'document_id' => $documentId],
            'evidence_records' => [
                ['type' => 'document', 'id' => $documentId, 'label' => 'Contract Document', 'status' => 'expiring'],
            ],
        ]);

        return true;
    }

    /**
     * Check invoice vs PO mismatch.
     */
    private static function checkInvoicePOMismatch(PDO $db, int $companyId, int $documentId, array $data): bool
    {
        if (empty($data['po_number']) || empty($data['total'])) return false;

        if (!self::tableExists($db, 'purchase_orders')) return false;

        $stmt = $db->prepare("
            SELECT id, po_number, total FROM purchase_orders
            WHERE company_id = ? AND po_number = ? LIMIT 1
        ");
        $stmt->execute([$companyId, $data['po_number']]);
        $po = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$po) return false;

        $invTotal = (float) $data['total'];
        $poTotal = (float) $po['total'];
        $diff = abs($invTotal - $poTotal);

        if ($diff < 0.01) return false;

        $severity = 'medium';
        if ($diff > 1000) $severity = 'high';
        if ($diff > 10000) $severity = 'critical';

        AIIntelligenceService::createEvent($db, [
            'company_id' => $companyId,
            'source_module' => 'documents',
            'source_record_type' => 'document',
            'source_record_id' => $documentId,
            'detection_type' => 'rule',
            'detector_key' => 'document_invoice_po_mismatch',
            'severity' => $severity,
            'confidence' => 0.95,
            'title' => 'Invoice total differs from PO by $' . number_format($diff, 2),
            'explanation' => 'Invoice total: $' . number_format($invTotal, 2) . ' vs PO total: $' . number_format($poTotal, 2) . '. Difference: $' . number_format($diff, 2),
            'recommendation' => 'Review the discrepancy between invoice and PO amounts.',
            'suggested_action' => 'Review invoice/PO discrepancy',
            'suggested_action_type' => 'review',
            'urgency' => 'high',
            'evidence' => ['invoice_total' => $invTotal, 'po_total' => $poTotal, 'difference' => $diff, 'po_number' => $data['po_number']],
            'evidence_records' => [
                ['type' => 'document', 'id' => $documentId, 'label' => 'Invoice Document', 'amount' => $invTotal],
                ['type' => 'purchase_order', 'id' => (int) $po['id'], 'label' => 'PO ' . $data['po_number'], 'amount' => $poTotal],
            ],
        ]);

        return true;
    }

    /**
     * Check if quotation is significantly above previous quotation.
     */
    private static function checkQuotationComparison(PDO $db, int $companyId, int $documentId, array $data): bool
    {
        if (empty($data['supplier_name']) || empty($data['total_amount'] ?? $data['total'])) return false;

        if (!self::tableExists($db, 'ai_document_extractions')) return false;

        $currentTotal = (float) ($data['total_amount'] ?? $data['total'] ?? 0);

        // Find previous quotation from same supplier
        $stmt = $db->prepare("
            SELECT e.extracted_data, e.document_id
            FROM ai_document_extractions e
            JOIN ai_documents d ON d.document_id = e.document_id
            WHERE d.company_id = ? AND d.document_type = 'quotation'
            AND d.document_id != ? AND d.status = 'active'
            AND JSON_EXTRACT(e.extracted_data, '$.supplier_name') = ?
            ORDER BY d.created_at DESC LIMIT 1
        ");
        $stmt->execute([$companyId, $documentId, $data['supplier_name']]);
        $prev = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prev) return false;

        $prevData = json_decode($prev['extracted_data'], true) ?: [];
        $prevTotal = (float) ($prevData['total_amount'] ?? $prevData['total'] ?? 0);

        if ($prevTotal <= 0) return false;

        $changePercent = (($currentTotal - $prevTotal) / $prevTotal) * 100;

        if (abs($changePercent) < 20) return false; // Only flag significant changes

        $severity = 'medium';
        if ($changePercent > 50) $severity = 'high';

        AIIntelligenceService::createEvent($db, [
            'company_id' => $companyId,
            'source_module' => 'documents',
            'source_record_type' => 'document',
            'source_record_id' => $documentId,
            'detection_type' => 'rule',
            'detector_key' => 'document_quotation_change',
            'severity' => $severity,
            'confidence' => 0.85,
            'title' => 'Supplier quotation ' . ($changePercent > 0 ? 'increased' : 'decreased') . ' by ' . abs(round($changePercent)) . '%',
            'explanation' => 'Current quotation: $' . number_format($currentTotal, 2) . ' vs previous: $' . number_format($prevTotal, 2) . ' (' . ($changePercent > 0 ? '+' : '') . round($changePercent) . '%)',
            'recommendation' => 'Review the significant price change from this supplier.',
            'suggested_action' => 'Review quotation change',
            'suggested_action_type' => 'review',
            'urgency' => 'medium',
            'evidence' => ['current_total' => $currentTotal, 'previous_total' => $prevTotal, 'change_percent' => round($changePercent, 1)],
            'evidence_records' => [
                ['type' => 'document', 'id' => $documentId, 'label' => 'Current Quotation', 'amount' => $currentTotal],
                ['type' => 'document', 'id' => (int) $prev['document_id'], 'label' => 'Previous Quotation', 'amount' => $prevTotal],
            ],
        ]);

        return true;
    }

    /**
     * Check for important payment terms.
     */
    private static function checkPaymentTerms(PDO $db, int $companyId, int $documentId, array $doc, array $data): bool
    {
        $paymentTerms = strtolower($data['payment_terms'] ?? '');
        if (empty($paymentTerms)) return false;

        // Flag unusual payment terms
        $flags = [
            'prepayment' => ['severity' => 'high', 'message' => 'Prepayment required — financial risk'],
            'advance' => ['severity' => 'high', 'message' => 'Advance payment required'],
            'net 90' => ['severity' => 'medium', 'message' => 'Extended payment terms (90 days)'],
            'net 120' => ['severity' => 'high', 'message' => 'Very extended payment terms (120 days)'],
            'cash on delivery' => ['severity' => 'medium', 'message' => 'COD terms detected'],
            'cod' => ['severity' => 'medium', 'message' => 'COD terms detected'],
        ];

        foreach ($flags as $pattern => $flag) {
            if (str_contains($paymentTerms, $pattern)) {
                AIIntelligenceService::createEvent($db, [
                    'company_id' => $companyId,
                    'source_module' => 'documents',
                    'source_record_type' => 'document',
                    'source_record_id' => $documentId,
                    'detection_type' => 'rule',
                    'detector_key' => 'document_payment_terms',
                    'severity' => $flag['severity'],
                    'confidence' => 0.9,
                    'title' => 'Important payment term detected: ' . ucfirst($pattern),
                    'explanation' => $flag['message'] . '. Payment terms: ' . ($data['payment_terms'] ?? ''),
                    'recommendation' => 'Review payment terms carefully before proceeding.',
                    'suggested_action' => 'Review payment terms',
                    'suggested_action_type' => 'review',
                    'urgency' => 'medium',
                    'evidence' => ['payment_terms' => $data['payment_terms'], 'pattern' => $pattern],
                    'evidence_records' => [
                        ['type' => 'document', 'id' => $documentId, 'label' => $doc['file_name'] ?? 'Document'],
                    ],
                ]);
                return true;
            }
        }

        return false;
    }

    /**
     * Check duplicate document.
     */
    private static function checkDuplicateDocument(PDO $db, int $companyId, int $documentId, array $doc): bool
    {
        $severity = ($doc['duplicate_status'] ?? '') === 'duplicate' ? 'high' : 'medium';
        $originalId = $doc['duplicate_of_id'] ?? null;

        AIIntelligenceService::createEvent($db, [
            'company_id' => $companyId,
            'source_module' => 'documents',
            'source_record_type' => 'document',
            'source_record_id' => $documentId,
            'detection_type' => 'rule',
            'detector_key' => 'document_duplicate',
            'severity' => $severity,
            'confidence' => 0.9,
            'title' => 'Possible duplicate document detected',
            'explanation' => 'This document may be a duplicate of document #' . ($originalId ?? 'unknown'),
            'recommendation' => 'Verify this is not a duplicate upload.',
            'suggested_action' => 'Verify document uniqueness',
            'suggested_action_type' => 'review',
            'urgency' => 'medium',
            'evidence' => ['duplicate_status' => $doc['duplicate_status'], 'original_id' => $originalId],
            'evidence_records' => [
                ['type' => 'document', 'id' => $documentId, 'label' => $doc['file_name'] ?? 'Document'],
            ],
        ]);

        return true;
    }

    /**
     * Check sensitive document upload.
     */
    private static function checkSensitiveDocument(PDO $db, int $companyId, int $documentId, array $doc): bool
    {
        AIIntelligenceService::createEvent($db, [
            'company_id' => $companyId,
            'source_module' => 'documents',
            'source_record_type' => 'document',
            'source_record_id' => $documentId,
            'detection_type' => 'rule',
            'detector_key' => 'document_sensitive_upload',
            'severity' => 'medium',
            'confidence' => 1.0,
            'title' => 'Sensitive document uploaded: ' . ($doc['sensitive_category'] ?? 'unknown category'),
            'explanation' => 'A document classified as "' . ($doc['sensitive_category'] ?? 'sensitive') . '" has been uploaded. Ensure proper access controls are in place.',
            'recommendation' => 'Verify document access permissions match its classification.',
            'suggested_action' => 'Review document access',
            'suggested_action_type' => 'review',
            'urgency' => 'medium',
            'evidence' => ['sensitive_category' => $doc['sensitive_category'], 'document_id' => $documentId],
            'evidence_records' => [
                ['type' => 'document', 'id' => $documentId, 'label' => $doc['file_name'] ?? 'Document', 'classification' => $doc['sensitive_category'] ?? 'sensitive'],
            ],
        ]);

        return true;
    }

    /**
     * Check if table exists.
     */
    private static function tableExists(PDO $db, string $table): bool
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
