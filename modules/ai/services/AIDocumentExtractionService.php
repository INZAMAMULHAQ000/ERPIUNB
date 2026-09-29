<?php
/**
 * AI Document Extraction Service
 * 
 * F20/F27: Text Extraction, OCR, Classification, Structured Extraction, Validation.
 * All methods are static and take $db as first parameter.
 *
 * Test 2: Per-field confidence and source references
 * Test 4: Human verification recorded per-field
 * Test 5: Low-confidence fields enter review queue
 * Test 23: Human-verified values protected from AI overwrite
 */
class AIDocumentExtractionService {

    // ── Text Extraction ──────────────────────────────────────

    public static function extractText($db, $documentId) {
        $doc = AIDocumentService::getById($db, $documentId);
        if (!$doc) return ['success' => false, 'error' => 'Document not found'];

        // Test 13: Scan extracted text for injection before processing
        if (!empty($doc['extracted_text']) && class_exists('AIDocumentSecurityService')) {
            $injectionCheck = AIDocumentSecurityService::scanForInjection($db, $documentId, $doc['extracted_text']);
            if ($injectionCheck['action'] === 'block') {
                return ['success' => false, 'error' => 'Document content blocked by security policy — potential injection detected'];
            }
        }

        $mimeType = $doc['mime_type'] ?? '';
        $method = self::resolveExtractionMethod($mimeType);

        $result = AIDocumentPipelineService::enqueue($db, $doc['company_id'], $documentId, 'extract_text');
        if (!$result['success']) return $result;

        return ['success' => true, 'method' => $method, 'queue_id' => $result['queue_id']];
    }

    public static function resolveExtractionMethod($mimeType) {
        $mimeTypeMap = [
            'application/pdf'              => 'pdf_text',
            'application/msword'           => 'docx_text',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx_text',
            'text/plain'                   => 'plain_text',
            'text/csv'                     => 'csv_text',
            'text/html'                    => 'html_text',
            'application/vnd.ms-excel'     => 'spreadsheet_text',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'spreadsheet_text',
            'image/jpeg'                   => 'ocr',
            'image/png'                    => 'ocr',
            'image/tiff'                   => 'ocr',
            'image/bmp'                    => 'ocr',
            'image/webp'                   => 'ocr',
        ];

        return $mimeTypeMap[$mimeType] ?? 'ocr';
    }

    public static function getProviders() {
        return [
            'pdf_text'     => ['name' => 'Native PDF Text', 'description' => 'Extract text from native PDF files', 'requires_external' => false],
            'docx_text'    => ['name' => 'DOCX Text', 'description' => 'Extract text from Word documents', 'requires_external' => false],
            'plain_text'   => ['name' => 'Plain Text', 'description' => 'Direct text file reading', 'requires_external' => false],
            'csv_text'     => ['name' => 'CSV Text', 'description' => 'Extract text from CSV files', 'requires_external' => false],
            'html_text'    => ['name' => 'HTML Text', 'description' => 'Extract text from HTML files', 'requires_external' => false],
            'spreadsheet_text' => ['name' => 'Spreadsheet Text', 'description' => 'Extract text from spreadsheet files', 'requires_external' => false],
            'ocr'          => ['name' => 'OCR', 'description' => 'Optical Character Recognition for images', 'requires_external' => true],
            'ai_vision'    => ['name' => 'AI Vision', 'description' => 'AI-powered document understanding', 'requires_external' => true],
        ];
    }

    // ── OCR Support ──────────────────────────────────────────

    public static function processOCR($db, $documentId) {
        $doc = AIDocumentService::getById($db, $documentId);
        if (!$doc) return ['success' => false, 'error' => 'Document not found'];

        if (class_exists('AIKillSwitchService') && !AIKillSwitchService::isEnabled($db, 'document_ocr')) {
            return ['success' => false, 'error' => 'Document OCR is currently disabled.'];
        }

        // Test 18: Check if external processing is allowed for sensitive documents
        $extCheck = AIDocumentService::canProcessExternally($db, $doc['company_id'], $documentId);
        if (!$extCheck['allowed']) {
            return ['success' => false, 'error' => $extCheck['reason']];
        }

        $result = AIDocumentPipelineService::enqueue($db, $doc['company_id'], $documentId, 'ocr', 8);
        return $result;
    }

    public static function storeOCRResult($db, $documentId, $extractedText, $confidence = null, $language = null) {
        // Test 13: Scan for injection in OCR output
        if (class_exists('AIDocumentSecurityService')) {
            $injectionCheck = AIDocumentSecurityService::scanForInjection($db, $documentId, $extractedText);
            if ($injectionCheck['action'] === 'block') {
                $extractedText = '[BLOCKED: Content contains injection attempt]';
            } elseif (!$injectionCheck['safe']) {
                $extractedText = $injectionCheck['sanitized_text'];
            }
        }

        // Test 18: Classify sensitivity of extracted text
        if (class_exists('AIDocumentSecurityService')) {
            AIDocumentSecurityService::classifyDocument($db, $documentId, $extractedText);
        }

        // Test 21: Check for secrets in extracted text
        if (class_exists('AIDocumentSecurityService')) {
            $secretCheck = AIDocumentSecurityService::scanForSecrets($extractedText);
            if ($secretCheck['has_secrets']) {
                if (class_exists('AISecretProtectionService')) {
                    $extractedText = AISecretProtectionService::redactSecrets($extractedText);
                }
            }
        }

        AIDocumentService::update($db, $documentId, [
            'extracted_text' => $extractedText,
            'ocr_confidence' => $confidence,
            'ocr_language' => $language,
        ]);

        // Chunk the text for search/retrieval
        self::chunkText($db, $documentId, $extractedText);

        return ['success' => true];
    }

    public static function chunkText($db, $documentId, $text, $chunkSize = 500, $overlap = 50) {
        if (empty($text)) return ['success' => true, 'chunks' => 0];

        $db->prepare("DELETE FROM ai_document_chunks WHERE document_id = ?")->execute([(int)$documentId]);

        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $chunks = [];
        $currentChunk = '';
        $chunkIndex = 0;

        foreach ($sentences as $sentence) {
            if (strlen($currentChunk) + strlen($sentence) > $chunkSize && !empty($currentChunk)) {
                $chunks[] = $currentChunk;
                $currentChunk = mb_substr($currentChunk, -$overlap) . ' ' . $sentence;
                $chunkIndex++;
            } else {
                $currentChunk .= ($currentChunk ? ' ' : '') . $sentence;
            }
        }

        if (!empty($currentChunk)) {
            $chunks[] = $currentChunk;
        }

        $ins = $db->prepare("INSERT INTO ai_document_chunks (document_id, chunk_index, content) VALUES (?, ?, ?)");
        foreach ($chunks as $i => $chunk) {
            $ins->execute([(int)$documentId, $i, trim($chunk)]);
        }

        return ['success' => true, 'chunks' => count($chunks)];
    }

    // ── Document Classification ──────────────────────────────

    public static function classify($db, $documentId, $classificationData) {
        $validTypes = ['unknown','quotation','invoice','purchase_order','contract','policy','procedure','report','supplier_document','identity_document','other',
                       'delivery_note','receipt','employee_document','bank_document','expense_document','customer_document'];

        $type = $classificationData['document_type'] ?? 'unknown';
        if (!in_array($type, $validTypes)) $type = 'unknown';

        $confidence = isset($classificationData['confidence']) ? (float)$classificationData['confidence'] : 0;
        $reason = $classificationData['reason_summary'] ?? null;
        $method = $classificationData['method'] ?? 'ai';

        $db->prepare("INSERT INTO ai_document_classifications (document_id, suggested_type, confidence, reason_summary, method) VALUES (?, ?, ?, ?, ?)")
            ->execute([(int)$documentId, $type, $confidence, $reason, $method]);

        AIDocumentService::update($db, $documentId, [
            'document_type' => $type,
            'classification_confidence' => $confidence,
            'classification_reason' => $reason,
        ]);

        // Test 5: Low confidence → needs review
        if ($confidence < 0.7) {
            AIDocumentService::update($db, $documentId, ['processing_status' => 'needs_review']);
        }

        return ['success' => true, 'type' => $type, 'confidence' => $confidence];
    }

    // ── Structured Data Extraction ───────────────────────────

    public static function getDefaultSchema($documentType) {
        $schemas = [
            'quotation' => [
                'required_fields' => ['supplier_name', 'quotation_number', 'quotation_date', 'currency'],
                'optional_fields' => ['payment_terms', 'delivery_terms', 'valid_until', 'total_amount', 'items'],
            ],
            'invoice' => [
                'required_fields' => ['supplier_name', 'invoice_number', 'invoice_date', 'currency', 'total'],
                'optional_fields' => ['due_date', 'subtotal', 'tax', 'po_number', 'items'],
            ],
            'purchase_order' => [
                'required_fields' => ['supplier_name', 'po_number', 'po_date', 'currency'],
                'optional_fields' => ['payment_terms', 'delivery_date', 'total_amount', 'items'],
            ],
            'contract' => [
                'required_fields' => ['parties', 'effective_date'],
                'optional_fields' => ['expiry_date', 'payment_terms', 'renewal_terms', 'key_obligations', 'termination_clauses', 'notice_period_days'],
            ],
            'policy' => [
                'required_fields' => ['title'],
                'optional_fields' => ['version', 'effective_date', 'summary', 'key_sections'],
            ],
            'supplier_document' => [
                'required_fields' => ['supplier_name'],
                'optional_fields' => ['document_title', 'certifications', 'contact_info'],
            ],
        ];

        return $schemas[$documentType] ?? ['required_fields' => [], 'optional_fields' => []];
    }

    public static function getExtractionSchema($db, $documentType, $templateId = null) {
        if ($templateId) {
            $stmt = $db->prepare("SELECT schema_json FROM ai_extraction_templates WHERE template_id = ? AND status = 'published'");
            $stmt->execute([(int)$templateId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return json_decode($row['schema_json'], true);
        }

        return self::getDefaultSchema($documentType);
    }

    public static function getDefaultSchemas() {
        return [
            'quotation' => self::getDefaultSchema('quotation'),
            'invoice' => self::getDefaultSchema('invoice'),
            'purchase_order' => self::getDefaultSchema('purchase_order'),
            'contract' => self::getDefaultSchema('contract'),
            'policy' => self::getDefaultSchema('policy'),
            'supplier_document' => self::getDefaultSchema('supplier_document'),
        ];
    }

    // ── Structured Extraction ────────────────────────────────

    /**
     * Test 2: Store structured extraction with per-field confidence and source references.
     * Test 5: Low-confidence fields are flagged for review.
     */
    public static function storeExtraction($db, $documentId, $data) {
        $templateId = $data['template_id'] ?? null;
        $extractionType = $data['extraction_type'] ?? 'general';
        $extractedData = $data['extracted_data'] ?? [];
        $confidence = $data['confidence'] ?? null;
        $provider = $data['provider'] ?? null;
        $method = $data['extraction_method'] ?? null;
        $evidence = $data['evidence'] ?? null;

        // Validate against schema
        $validation = self::validateExtraction($db, $documentId, $extractedData);

        $stmt = $db->prepare("INSERT INTO ai_document_extractions 
            (document_id, template_id, extraction_type, extracted_data, confidence, 
             validation_status, validation_errors, evidence_json, provider, extraction_method)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            (int)$documentId,
            $templateId ? (int)$templateId : null,
            $extractionType,
            json_encode($extractedData),
            $confidence,
            $validation['valid'] ? 'valid' : 'needs_review',
            $validation['errors'] ? json_encode($validation['errors']) : null,
            $evidence ? json_encode($evidence) : null,
            $provider,
            $method,
        ]);

        $extractionId = (int)$db->lastInsertId();

        // Test 2: Store per-field confidence and source references
        self::storeFieldConfidence($db, $documentId, $extractionId, $extractedData, $data);

        // Test 5: Check if any fields have low confidence → flag document for review
        $needsReview = self::checkLowConfidenceFields($db, $extractionId);
        if ($needsReview) {
            AIDocumentService::update($db, $documentId, [
                'extraction_status' => 'needs_review',
                'processing_status' => 'needs_review',
            ]);
        } else {
            AIDocumentService::update($db, $documentId, [
                'extraction_status' => $validation['valid'] ? 'validated' : 'needs_review',
            ]);
        }

        return ['success' => true, 'extraction_id' => $extractionId, 'validation' => $validation];
    }

    /**
     * Test 2: Store per-field confidence with source references.
     */
    private static function storeFieldConfidence(PDO $db, int $documentId, int $extractionId, array $extractedData, array $meta): void
    {
        $fieldConfidences = $meta['field_confidences'] ?? [];
        $fieldSources = $meta['field_sources'] ?? [];

        $stmt = $db->prepare("
            INSERT INTO ai_document_field_confidence
                (document_id, extraction_id, field_name, field_value, confidence,
                 source_page, source_section, source_text, extraction_method, verification_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($extractedData as $field => $value) {
            if ($value === null || $value === '') continue;

            $fieldConf = $fieldConfidences[$field] ?? ($meta['confidence'] ?? 0.8);
            $source = $fieldSources[$field] ?? [];

            $stmt->execute([
                $documentId,
                $extractionId,
                $field,
                is_array($value) ? json_encode($value) : (string) $value,
                $fieldConf,
                $source['page'] ?? null,
                $source['section'] ?? null,
                $source['text'] ?? null,
                $meta['extraction_method'] ?? 'ai',
                'ai_extracted',
            ]);

            // Test 5: Low confidence field → flag for review queue
            if ($fieldConf < 0.7) {
                // Create a review entry for this specific field
                $db->prepare("
                    INSERT INTO ai_document_field_confidence
                        (document_id, extraction_id, field_name, field_value, confidence, verification_status)
                    VALUES (?, ?, ?, ?, ?, 'ai_extracted')
                ")->execute([$documentId, $extractionId, $field . '_needs_review', is_array($value) ? json_encode($value) : (string) $value, $fieldConf]);
            }
        }
    }

    /**
     * Test 5: Check if extraction has low-confidence fields that need review.
     */
    private static function checkLowConfidenceFields(PDO $db, int $extractionId): bool
    {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM ai_document_field_confidence
            WHERE extraction_id = ? AND confidence < 0.7
        ");
        $stmt->execute([$extractionId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    // ── Extraction Validation ────────────────────────────────

    public static function validateExtraction($db, $documentId, $extractedData) {
        $doc = AIDocumentService::getById($db, $documentId);
        if (!$doc) return ['valid' => false, 'errors' => ['Document not found']];

        $schema = self::getExtractionSchema($db, $doc['document_type']);
        $errors = [];

        if (!empty($schema['required_fields'])) {
            foreach ($schema['required_fields'] as $field) {
                if (empty($extractedData[$field])) {
                    $errors[] = "Missing required field: $field";
                }
            }
        }

        foreach ($extractedData as $field => $value) {
            if ($value === null || $value === '') continue;

            if (str_contains($field, 'date') || str_contains($field, '_at')) {
                if (!strtotime($value) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    $errors[] = "Invalid date format for $field: $value";
                }
            }

            if (in_array($field, ['subtotal', 'tax', 'total', 'amount', 'price', 'cost', 'total_amount'])) {
                if (!is_numeric($value)) {
                    $errors[] = "Invalid numeric value for $field: $value";
                }
            }

            if ($field === 'currency') {
                if (!preg_match('/^[A-Z]{3}$/', $value)) {
                    $errors[] = "Invalid currency code: $value";
                }
            }
        }

        if (!empty($extractedData['total']) && !empty($extractedData['items']) && is_array($extractedData['items'])) {
            $itemsTotal = array_sum(array_column($extractedData['items'], 'total'));
            if (abs((float)$extractedData['total'] - $itemsTotal) > 0.01) {
                $errors[] = "Total (" . $extractedData['total'] . ") doesn't match items total ($itemsTotal)";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    // ── Human Review ─────────────────────────────────────────

    public static function getPendingReviews($db, $documentId) {
        $stmt = $db->prepare("SELECT * FROM ai_document_extractions 
            WHERE document_id = ? AND review_status = 'pending'
            ORDER BY created_at DESC");
        $stmt->execute([(int)$documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Test 4: Review extraction with per-field verification tracking.
     * Test 23: Human-verified values are protected from AI overwrite.
     */
    public static function reviewExtraction($db, $extractionId, $userId, $status, $editedData = null) {
        $validStatuses = ['accepted', 'partially_accepted', 'rejected'];
        if (!in_array($status, $validStatuses)) {
            return ['success' => false, 'error' => 'Invalid review status'];
        }

        $fields = ["review_status = ?", "reviewed_by = ?", "reviewed_at = NOW()"];
        $params = [$status, (int)$userId];

        if ($editedData !== null) {
            $fields[] = "extracted_data = ?";
            $params[] = json_encode($editedData);
        }

        $params[] = (int)$extractionId;
        $db->prepare("UPDATE ai_document_extractions SET " . implode(', ', $fields) . " WHERE extraction_id = ?")->execute($params);

        // Test 4: Update per-field verification status
        if ($editedData && is_array($editedData)) {
            foreach ($editedData as $field => $value) {
                $db->prepare("
                    UPDATE ai_document_field_confidence
                    SET verification_status = 'human_verified', verified_by = ?, verified_at = NOW()
                    WHERE extraction_id = ? AND field_name = ?
                ")->execute([(int)$userId, (int)$extractionId, $field]);
            }
        } elseif ($status === 'accepted') {
            // Mark all fields as verified when extraction is accepted
            $db->prepare("
                UPDATE ai_document_field_confidence
                SET verification_status = 'human_verified', verified_by = ?, verified_at = NOW()
                WHERE extraction_id = ? AND verification_status = 'ai_extracted'
            ")->execute([(int)$userId, (int)$extractionId]);
        }

        // Update document extraction status
        $ext = $db->prepare("SELECT document_id FROM ai_document_extractions WHERE extraction_id = ?");
        $ext->execute([(int)$extractionId]);
        $docId = $ext->fetchColumn();

        if ($docId) {
            AIDocumentService::update($db, $docId, [
                'extraction_status' => 'reviewed',
                'reviewed_by' => (int)$userId,
                'reviewed_at' => date('Y-m-d H:i:s'),
            ], $userId); // Pass userId to allow the update

            $doc = AIDocumentService::getById($db, $docId);
            if ($doc) {
                AIDocumentService::logAudit($db, $doc['company_id'], $docId, 'extraction_reviewed', (int)$userId, ['status' => $status]);
            }
        }

        return ['success' => true];
    }

    // ── Document Summaries ───────────────────────────────────

    public static function generateSummary($db, $documentId, $summaryType = 'short') {
        $doc = AIDocumentService::getById($db, $documentId);
        if (!$doc) return ['success' => false, 'error' => 'Document not found'];

        $validTypes = ['short','executive','key_terms','risks','dates','missing_info'];
        if (!in_array($summaryType, $validTypes)) $summaryType = 'short';

        $extStmt = $db->prepare("SELECT extracted_data FROM ai_document_extractions 
            WHERE document_id = ? AND review_status IN ('accepted','pending') ORDER BY created_at DESC LIMIT 1");
        $extStmt->execute([(int)$documentId]);
        $extraction = $extStmt->fetch(PDO::FETCH_ASSOC);

        $extractedData = $extraction ? json_decode($extraction['extracted_data'], true) : [];

        $summary = self::buildSummaryContent($doc, $extractedData, $summaryType);

        $db->prepare("INSERT INTO ai_document_summaries (document_id, summary_type, content, facts_json, interpretations_json, risks_json) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([
                (int)$documentId,
                $summaryType,
                $summary['content'],
                json_encode($summary['facts'] ?? []),
                json_encode($summary['interpretations'] ?? []),
                json_encode($summary['risks'] ?? []),
            ]);

        return ['success' => true, 'summary_id' => (int)$db->lastInsertId(), 'summary' => $summary];
    }

    private static function buildSummaryContent($doc, $data, $type) {
        $docType = $doc['document_type'] ?? 'unknown';
        $fileName = $doc['file_name'] ?? 'Unknown document';

        $facts = [];
        $interpretations = [];
        $risks = [];
        $content = '';

        switch ($docType) {
            case 'quotation':
                $facts[] = "Document: $fileName";
                if (!empty($data['supplier_name'])) $facts[] = "Supplier: " . $data['supplier_name'];
                if (!empty($data['total'])) $facts[] = "Total: " . $data['total'] . " " . ($data['currency'] ?? '');
                if (!empty($data['payment_terms'])) $facts[] = "Payment terms: " . $data['payment_terms'];
                if (!empty($data['valid_until'])) $facts[] = "Valid until: " . $data['valid_until'];
                if (!empty($data['items']) && is_array($data['items'])) {
                    $facts[] = "Items: " . count($data['items']);
                }
                $content = "Quotation from " . ($data['supplier_name'] ?? 'unknown supplier') . ". ";
                $content .= "Total: " . ($data['total'] ?? 'N/A') . ". ";
                if (!empty($data['payment_terms'])) $content .= "Payment: " . $data['payment_terms'] . ". ";
                break;

            case 'invoice':
                $facts[] = "Document: $fileName";
                if (!empty($data['supplier_name'])) $facts[] = "Supplier: " . $data['supplier_name'];
                if (!empty($data['invoice_number'])) $facts[] = "Invoice #: " . $data['invoice_number'];
                if (!empty($data['total'])) $facts[] = "Total: " . $data['total'] . " " . ($data['currency'] ?? '');
                if (!empty($data['due_date'])) $facts[] = "Due: " . $data['due_date'];
                $content = "Invoice from " . ($data['supplier_name'] ?? 'unknown') . ". ";
                $content .= "Amount: " . ($data['total'] ?? 'N/A') . ". ";
                if (!empty($data['due_date'])) $content .= "Due: " . $data['due_date'] . ". ";
                break;

            case 'contract':
                $facts[] = "Document: $fileName";
                if (!empty($data['parties'])) $facts[] = "Parties: " . implode(', ', (array)$data['parties']);
                if (!empty($data['effective_date'])) $facts[] = "Effective: " . $data['effective_date'];
                if (!empty($data['expiry_date'])) $facts[] = "Expires: " . $data['expiry_date'];
                if (!empty($data['renewal_terms'])) $facts[] = "Renewal: " . $data['renewal_terms'];
                $content = "Contract between " . implode(' and ', (array)($data['parties'] ?? ['unknown'])) . ". ";
                if (!empty($data['effective_date'])) $content .= "Effective: " . $data['effective_date'] . ". ";
                if (!empty($data['expiry_date'])) $content .= "Expires: " . $data['expiry_date'] . ". ";
                if (!empty($data['termination_clauses'])) {
                    $risks[] = "Contains termination clauses that should be reviewed";
                }
                break;

            default:
                $facts[] = "Document: $fileName";
                $facts[] = "Type: " . ucfirst(str_replace('_', ' ', $docType));
                if (!empty($doc['extracted_text'])) {
                    $content = mb_substr($doc['extracted_text'], 0, 500);
                    if (strlen($doc['extracted_text']) > 500) $content .= '...';
                } else {
                    $content = "Document registered but not yet fully processed.";
                }
        }

        return [
            'content' => $content,
            'facts' => $facts,
            'interpretations' => $interpretations,
            'risks' => $risks,
        ];
    }

    public static function getSummaries($db, $documentId) {
        $stmt = $db->prepare("SELECT * FROM ai_document_summaries WHERE document_id = ? ORDER BY created_at DESC");
        $stmt->execute([(int)$documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
