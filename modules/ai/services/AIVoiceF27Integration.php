<?php
/**
 * AI F28 — Voice + F27 Document Integration.
 *
 * Allows users to ask document-related questions via voice.
 * Covers Section 19: Voice + F27 Documents.
 */
class AIVoiceF27Integration
{
    /**
     * Answer a voice query about a document.
     */
    public static function queryDocument(
        PDO $db,
        int $userId,
        int $companyId,
        string $text,
        ?int $documentId = null
    ): array {
        $lower = strtolower($text);

        // Detect document-related queries
        $isDocQuery = preg_match('/\b(document|invoice|quotation|contract|po|purchase order|payment term|what does|says|match)\b/i', $lower);

        if (!$isDocQuery) {
            return ['success' => false, 'is_document_query' => false];
        }

        // If no specific document, try to find from context
        if (!$documentId) {
            // Search for document references in the text
            $documentId = self::extractDocumentReference($db, $companyId, $text);
        }

        if (!$documentId) {
            return [
                'success' => true,
                'is_document_query' => true,
                'response' => 'Which document would you like me to look at? You can say the document name or ID.',
                'needs_document' => true,
            ];
        }

        // Check permissions
        $doc = AIDocumentService::getById($db, $documentId, $companyId);
        if (!$doc) {
            return ['success' => false, 'response' => 'Document not found.'];
        }

        // F24: Check if user can access this document
        if (class_exists('AIDocumentGovernanceService')) {
            if (!AIDocumentGovernanceService::checkDocumentPermission($db, $userId, $companyId, $documentId, 'view')) {
                return ['success' => false, 'response' => 'You do not have permission to access this document.'];
            }
        }

        // Ask the document a question
        $result = AIDocumentIntelligenceService::askDocument($db, $companyId, $userId, $documentId, $text);

        if ($result['success']) {
            return [
                'success' => true,
                'is_document_query' => true,
                'response' => $result['answer'],
                'confidence' => $result['confidence'],
                'evidence' => $result['evidence'] ?? [],
                'document' => $result['document'] ?? null,
            ];
        }

        return ['success' => false, 'response' => $result['error'] ?? 'Could not answer that question about the document.'];
    }

    /**
     * Extract document reference from voice text.
     */
    private static function extractDocumentReference(PDO $db, int $companyId, string $text): ?int
    {
        // Try to match document ID patterns
        if (preg_match('/\b(?:document|#|doc)\s*(\d+)\b/i', $text, $matches)) {
            return (int) $matches[1];
        }

        // Try to match invoice number patterns
        if (preg_match('/\b(?:invoice|inv)\s*[:#-]?\s*([A-Za-z0-9-]+)\b/i', $text, $matches)) {
            $stmt = $db->prepare("
                SELECT document_id FROM ai_documents
                WHERE company_id = ? AND file_name LIKE ? AND status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$companyId, '%' . $matches[1] . '%']);
            $docId = $stmt->fetchColumn();
            if ($docId) return (int) $docId;
        }

        return null;
    }
}
