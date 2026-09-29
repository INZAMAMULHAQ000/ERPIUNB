<?php
/**
 * AI Document Intelligence Service
 *
 * F20: Document Q&A, version suggestions, and document-to-ERP action proposals.
 */
class AIDocumentIntelligenceService
{
    public static function askDocument(PDO $db, int $companyId, int $userId, int $documentId, string $question): array
    {
        if (class_exists('AIKillSwitchService') && !AIKillSwitchService::isEnabled($db, 'document_qna')) {
            return ['success' => false, 'error' => 'Document Q&A is currently disabled.'];
        }

        $doc = AIDocumentService::getById($db, $documentId, $companyId);
        if (!$doc) {
            return ['success' => false, 'error' => 'Document not found.'];
        }

        $question = trim($question);
        if ($question === '') {
            return ['success' => false, 'error' => 'Question is required.'];
        }

        $latestExtraction = self::getLatestExtraction($db, $documentId);
        $chunks = self::getChunks($db, $documentId);
        $evidence = self::findEvidence($question, $chunks, $latestExtraction);
        $answer = self::composeAnswer($doc, $question, $latestExtraction, $evidence);
        $confidence = self::scoreConfidence($evidence, $latestExtraction);

        $db->prepare(
            "INSERT INTO ai_document_qna_sessions
                (company_id, document_id, user_id, question, answer, confidence, evidence_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        )->execute([
            $companyId,
            $documentId,
            $userId,
            $question,
            $answer,
            $confidence,
            json_encode($evidence),
        ]);

        AIDocumentService::logAudit($db, $companyId, $documentId, 'qna_asked', $userId, [
            'question' => $question,
            'confidence' => $confidence,
            'evidence_count' => count($evidence),
        ]);

        return [
            'success' => true,
            'answer' => $answer,
            'confidence' => $confidence,
            'evidence' => $evidence,
            'document' => [
                'id' => (int)$doc['document_id'],
                'name' => $doc['file_name'],
                'type' => $doc['document_type'],
            ],
        ];
    }

    public static function proposeDraftFromDocument(PDO $db, int $companyId, int $userId, int $documentId, string $actionId = 'procurement.create_purchase_requisition'): array
    {
        if (class_exists('AIKillSwitchService') && !AIKillSwitchService::isEnabled($db, 'document_to_erp_actions')) {
            return ['success' => false, 'error' => 'Document-to-ERP actions are currently disabled.'];
        }

        $doc = AIDocumentService::getById($db, $documentId, $companyId);
        if (!$doc) {
            return ['success' => false, 'error' => 'Document not found.'];
        }

        $extraction = self::getLatestReviewedExtraction($db, $documentId);
        if (!$extraction) {
            return ['success' => false, 'error' => 'Reviewed extraction is required before proposing a draft action.'];
        }

        $data = json_decode($extraction['extracted_data'], true) ?: [];
        $params = self::mapActionParameters($doc, $data, $userId);
        if (empty($params)) {
            return ['success' => false, 'error' => 'Unable to map this document to a draft action.'];
        }

        $permissions = function_exists('getUserPermissions') ? getUserPermissions() : [];
        $executor = new AIActionExecutor($db, $userId, $permissions);
        $proposal = $executor->propose($actionId, $params, 0);
        if (empty($proposal['success'])) {
            return $proposal;
        }

        $stmt = $db->prepare(
            "INSERT INTO ai_document_action_requests
                (company_id, document_id, user_id, action_id, pending_action_id, proposed_params_json, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pending_confirmation', NOW())"
        );
        $stmt->execute([
            $companyId,
            $documentId,
            $userId,
            $actionId,
            $proposal['pending_action_id'],
            json_encode($params),
        ]);

        AIDocumentService::logAudit($db, $companyId, $documentId, 'action_proposed', $userId, [
            'action_id' => $actionId,
            'pending_action_id' => $proposal['pending_action_id'],
        ]);

        return [
            'success' => true,
            'proposal' => $proposal,
            'mapped_params' => $params,
        ];
    }

    public static function suggestVersionRelationships(PDO $db, int $companyId, int $documentId): array
    {
        $doc = AIDocumentService::getById($db, $documentId, $companyId);
        if (!$doc) {
            return ['success' => false, 'error' => 'Document not found.'];
        }

        $normalized = self::normalizeFilename($doc['file_name'] ?? '');
        $prefix = preg_replace('/(?:\b(v|ver|version)[-_ ]?\d+\b|\b\d+\b)/i', '', $normalized);
        $prefix = trim(preg_replace('/\s+/', ' ', $prefix));

        $stmt = $db->prepare(
            "SELECT * FROM ai_documents
             WHERE company_id = ? AND document_id <> ? AND status = 'active'
             AND (LOWER(file_name) LIKE ? OR LOWER(file_name) LIKE ? OR file_hash = ?)
             ORDER BY created_at DESC LIMIT 20"
        );
        $stmt->execute([
            $companyId,
            $documentId,
            '%' . $prefix . '%',
            '%' . self::normalizeFilename($doc['file_name'] ?? '') . '%',
            $doc['file_hash'] ?? '',
        ]);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $suggestions = [];
        foreach ($candidates as $candidate) {
            $confidence = self::versionConfidence($doc, $candidate, $prefix);
            if ($confidence < 0.55) {
                continue;
            }

            $existing = $db->prepare(
                "SELECT relationship_id FROM ai_document_relationships
                 WHERE source_document_id = ? AND target_document_id = ? AND relationship_type = 'version'"
            );
            $existing->execute([(int)$documentId, (int)$candidate['document_id']]);
            if ($existing->fetchColumn()) {
                continue;
            }

            $db->prepare(
                "INSERT INTO ai_document_relationships
                    (company_id, source_document_id, target_document_id, relationship_type, confidence, method, created_by)
                 VALUES (?, ?, ?, 'version', ?, 'ai_suggested', ?)"
            )->execute([
                $companyId,
                $documentId,
                $candidate['document_id'],
                $confidence,
                (int)($doc['created_by'] ?? 0),
            ]);

            $db->prepare(
                "INSERT INTO ai_document_version_suggestions
                    (company_id, source_document_id, target_document_id, confidence, status, created_at)
                 VALUES (?, ?, ?, ?, 'pending_review', NOW())"
            )->execute([
                $companyId,
                $documentId,
                $candidate['document_id'],
                $confidence,
            ]);

            $suggestions[] = [
                'document_id' => (int)$candidate['document_id'],
                'file_name' => $candidate['file_name'],
                'confidence' => $confidence,
            ];
        }

        if (!empty($suggestions)) {
            AIDocumentService::logAudit($db, $companyId, $documentId, 'version_suggested', (int)($doc['created_by'] ?? 0), [
                'suggestions' => $suggestions,
            ]);
        }

        return ['success' => true, 'suggestions' => $suggestions];
    }

    public static function getSessionHistory(PDO $db, int $documentId, int $userId, int $limit = 10): array
    {
        $safeLimit = max(1, min(50, $limit));
        $stmt = $db->prepare(
            "SELECT * FROM ai_document_qna_sessions
             WHERE document_id = ? AND user_id = ?
             ORDER BY created_at DESC
             LIMIT {$safeLimit}"
        );
        $stmt->execute([$documentId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function getChunks(PDO $db, int $documentId): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_document_chunks WHERE document_id = ? ORDER BY chunk_index");
        $stmt->execute([$documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function getLatestExtraction(PDO $db, int $documentId): ?array
    {
        $stmt = $db->prepare(
            "SELECT * FROM ai_document_extractions
             WHERE document_id = ?
             ORDER BY FIELD(review_status, 'accepted', 'partially_accepted', 'pending', 'rejected') ASC, created_at DESC
             LIMIT 1"
        );
        $stmt->execute([$documentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function getLatestReviewedExtraction(PDO $db, int $documentId): ?array
    {
        $stmt = $db->prepare(
            "SELECT * FROM ai_document_extractions
             WHERE document_id = ? AND review_status IN ('accepted','partially_accepted')
             ORDER BY reviewed_at DESC, created_at DESC
             LIMIT 1"
        );
        $stmt->execute([$documentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function findEvidence(string $question, array $chunks, ?array $extraction): array
    {
        $words = array_values(array_filter(array_map(function ($w) {
            return trim(preg_replace('/[^a-z0-9]+/i', '', strtolower($w)));
        }, preg_split('/\s+/', strtolower($question)))));
        $stop = ['the', 'what', 'is', 'are', 'was', 'were', 'a', 'an', 'this', 'that', 'of', 'for', 'to', 'in', 'on', 'and', 'or', 'please', 'show', 'me', 'all'];
        $words = array_values(array_diff($words, $stop));

        $scored = [];
        foreach ($chunks as $chunk) {
            $content = strtolower($chunk['content'] ?? '');
            $score = 0;
            foreach ($words as $word) {
                if ($word !== '' && str_contains($content, $word)) {
                    $score++;
                }
            }
            if ($score > 0) {
                $scored[] = ['chunk' => $chunk, 'score' => $score];
            }
        }

        usort($scored, static fn($a, $b) => $b['score'] <=> $a['score']);
        $evidence = [];
        foreach (array_slice($scored, 0, 3) as $row) {
            $chunk = $row['chunk'];
            $evidence[] = [
                'page_number' => $chunk['page_number'] !== null ? (int)$chunk['page_number'] : null,
                'chunk_index' => (int)$chunk['chunk_index'],
                'snippet' => self::snippet($chunk['content'] ?? '', 280),
                'score' => $row['score'],
            ];
        }

        if (empty($evidence) && $extraction) {
            $data = json_decode($extraction['extracted_data'], true) ?: [];
            $evidence[] = [
                'page_number' => null,
                'chunk_index' => null,
                'snippet' => self::snippet(json_encode($data, JSON_UNESCAPED_SLASHES), 280),
                'score' => 1,
            ];
        }

        return $evidence;
    }

    private static function composeAnswer(array $doc, string $question, ?array $extraction, array $evidence): string
    {
        $data = $extraction ? (json_decode($extraction['extracted_data'], true) ?: []) : [];
        $q = strtolower($question);

        if (str_contains($q, 'payment terms') && !empty($data['payment_terms'])) {
            return 'Payment terms: ' . $data['payment_terms'];
        }
        if (str_contains($q, 'supplier') && !empty($data['supplier_name'])) {
            return 'Supplier: ' . $data['supplier_name'];
        }
        if ((str_contains($q, 'valid') || str_contains($q, 'expire') || str_contains($q, 'expiry') || str_contains($q, 'renewal')) && !empty($data['valid_until'])) {
            return 'Relevant date: ' . $data['valid_until'];
        }
        if ((str_contains($q, 'invoice') || str_contains($q, 'total')) && !empty($data['total'])) {
            return 'Total amount: ' . $data['total'] . ' ' . ($data['currency'] ?? '');
        }
        if (str_contains($q, 'summary') || str_contains($q, 'summarize')) {
            return self::buildSummaryText($doc, $data);
        }

        if (!empty($evidence)) {
            return 'I found relevant text in this document. See the evidence snippets below for the most relevant passages.';
        }

        return 'I could not find a precise answer in the indexed text. Review the document or extract more structured data.';
    }

    private static function buildSummaryText(array $doc, array $data): string
    {
        $parts = [];
        $parts[] = 'Document: ' . ($doc['file_name'] ?? 'Unknown');
        $parts[] = 'Type: ' . ucfirst(str_replace('_', ' ', $doc['document_type'] ?? 'unknown'));

        if (!empty($data['supplier_name'])) {
            $parts[] = 'Supplier: ' . $data['supplier_name'];
        }
        if (!empty($data['quotation_number'])) {
            $parts[] = 'Quotation #: ' . $data['quotation_number'];
        }
        if (!empty($data['invoice_number'])) {
            $parts[] = 'Invoice #: ' . $data['invoice_number'];
        }
        if (!empty($data['total'])) {
            $parts[] = 'Total: ' . $data['total'] . ' ' . ($data['currency'] ?? '');
        }
        if (!empty($data['payment_terms'])) {
            $parts[] = 'Payment terms: ' . $data['payment_terms'];
        }
        if (!empty($data['valid_until'])) {
            $parts[] = 'Valid until: ' . $data['valid_until'];
        }

        if (!empty($doc['extracted_text'])) {
            $parts[] = 'Excerpt: ' . self::snippet($doc['extracted_text'], 240);
        }

        return implode("\n", $parts);
    }

    private static function scoreConfidence(array $evidence, ?array $extraction): float
    {
        $base = !empty($evidence) ? 0.72 : 0.45;
        if ($extraction && in_array($extraction['review_status'] ?? '', ['accepted', 'partially_accepted'], true)) {
            $base += 0.15;
        }
        return round(min(0.98, $base), 2);
    }

    private static function normalizeFilename(string $fileName): string
    {
        $base = strtolower(pathinfo($fileName, PATHINFO_FILENAME));
        $base = preg_replace('/[_\-]+/', ' ', $base);
        $base = preg_replace('/\s+/', ' ', $base);
        return trim($base);
    }

    private static function versionConfidence(array $source, array $candidate, string $prefix): float
    {
        $score = 0.0;
        if (!empty($source['file_hash']) && $source['file_hash'] === ($candidate['file_hash'] ?? null)) {
            $score += 0.7;
        }

        $sourceName = self::normalizeFilename($source['file_name'] ?? '');
        $candidateName = self::normalizeFilename($candidate['file_name'] ?? '');
        if ($sourceName === $candidateName) {
            $score += 0.4;
        }
        if ($prefix !== '' && str_contains($candidateName, $prefix)) {
            $score += 0.3;
        }
        if (!empty($source['document_type']) && $source['document_type'] === ($candidate['document_type'] ?? null)) {
            $score += 0.1;
        }

        return round(min(0.99, $score), 2);
    }

    private static function mapActionParameters(array $doc, array $data, int $userId): array
    {
        if (($doc['document_type'] ?? '') !== 'quotation') {
            return [];
        }

        $supplier = trim((string)($data['supplier_name'] ?? ''));
        if ($supplier === '') {
            return [];
        }

        $requiredDate = !empty($data['valid_until']) ? date('Y-m-d', strtotime($data['valid_until'])) : date('Y-m-d', strtotime('+7 days'));
        $purpose = 'Create draft requisition from quotation ' . ($data['quotation_number'] ?? $doc['file_name']);
        $descriptionParts = [];
        if (!empty($data['quotation_number'])) {
            $descriptionParts[] = 'Quotation #' . $data['quotation_number'];
        }
        if (!empty($data['payment_terms'])) {
            $descriptionParts[] = 'Payment terms: ' . $data['payment_terms'];
        }
        if (!empty($data['delivery_terms'])) {
            $descriptionParts[] = 'Delivery terms: ' . $data['delivery_terms'];
        }
        if (!empty($data['total'])) {
            $descriptionParts[] = 'Total: ' . $data['total'] . ' ' . ($data['currency'] ?? '');
        }

        return [
            'requester_id' => $userId,
            'department_id' => null,
            'request_date' => date('Y-m-d'),
            'required_date' => $requiredDate,
            'priority' => 'normal',
            'purpose' => $purpose,
            'description' => implode("\n", $descriptionParts),
            'currency' => strtoupper((string)($data['currency'] ?? 'AED')),
            'supplier_name' => $supplier,
            'source_document_id' => (int)$doc['document_id'],
        ];
    }

    private static function snippet(string $text, int $length = 200): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length - 3) . '...';
    }
}
