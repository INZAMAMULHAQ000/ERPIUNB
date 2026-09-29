<?php
/**
 * AI F27 — Document Governance Service.
 *
 * Integrates with F24 governance middleware to enforce policies
 * on all document operations.
 *
 * Covers Section 25: F24 Governance.
 */
class AIDocumentGovernanceService
{
    /**
     * Enforce F24 governance before any document operation.
     */
    public static function enforce(PDO $db, string $operation, array $context = []): array
    {
        if (!class_exists('AIGovernanceMiddleware')) {
            return ['allowed' => true];
        }

        $userId = $context['user_id'] ?? ($_SESSION['user_id'] ?? 0);
        $companyId = $context['company_id'] ?? ($_SESSION['company_id'] ?? 1);

        // 1. Emergency kill switch check
        if (class_exists('AIEmergencyControlsService')) {
            if (AIEmergencyControlsService::isControlActive($db, 'disable_document_ai')) {
                return [
                    'allowed' => false,
                    'decision' => 'deny',
                    'reason' => 'Document AI is disabled by emergency control',
                ];
            }
        }

        // 2. Full governance enforcement
        $governanceResult = AIGovernanceMiddleware::enforce($db, 'document', $operation, array_merge($context, [
            'user_id' => $userId,
            'company_id' => $companyId,
            'action_type' => self::getActionType($operation),
        ]));

        return $governanceResult;
    }

    /**
     * Check document permissions for a user.
     */
    public static function checkDocumentPermission(PDO $db, int $userId, int $companyId, int $documentId, string $action): bool
    {
        // Basic permission check
        if (!hasPermission('ai.documents.view') && $action === 'view') return false;
        if (!hasPermission('ai.documents.upload') && $action === 'upload') return false;
        if (!hasPermission('ai.documents.process') && $action === 'process') return false;
        if (!hasPermission('ai.documents.review') && $action === 'review') return false;

        // Tenant isolation — document must belong to user's company
        $doc = AIDocumentService::getById($db, $documentId, $companyId);
        if (!$doc) return false;

        // Sensitive document check
        if (!empty($doc['is_sensitive'])) {
            if (!hasPermission('ai.documents.review')) return false;
        }

        return true;
    }

    /**
     * Check if user can export/send document externally.
     */
    public static function canExportDocument(PDO $db, int $userId, int $companyId, int $documentId): array
    {
        $doc = AIDocumentService::getById($db, $documentId, $companyId);
        if (!$doc) {
            return ['allowed' => false, 'reason' => 'Document not found'];
        }

        // Sensitive documents cannot be exported without approval
        if (!empty($doc['is_sensitive'])) {
            if (!hasPermission('ai.documents.manage')) {
                return ['allowed' => false, 'reason' => 'Sensitive document — management permission required for export'];
            }
        }

        // Check external processing policy
        return AIDocumentSecurityService::canProcessExternally($db, $companyId, $documentId);
    }

    /**
     * Map operation to action type for governance.
     */
    private static function getActionType(string $operation): string
    {
        $readOps = ['view', 'search', 'list', 'get_document', 'get_extraction', 'ask_document', 'get_summary'];
        $writeOps = ['upload', 'register', 'process', 'extract', 'classify', 'update', 'delete', 'archive'];
        $reviewOps = ['review_extraction', 'approve_extraction', 'reject_extraction'];

        if (in_array($operation, $readOps)) return 'read';
        if (in_array($operation, $reviewOps)) return 'review';
        if (in_array($operation, $writeOps)) return 'write';

        return 'read';
    }

    /**
     * Get governance summary for document operations.
     */
    public static function getGovernanceSummary(PDO $db, int $companyId): array
    {
        $summary = [
            'emergency_controls' => false,
            'active_policies' => 0,
            'pending_reviews' => 0,
            'security_events_24h' => 0,
            'classification_violations' => 0,
        ];

        // Check emergency controls
        if (class_exists('AIEmergencyControlsService')) {
            $summary['emergency_controls'] = AIEmergencyControlsService::isControlActive($db, 'disable_document_ai');
        }

        // Count pending document reviews
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM ai_documents
            WHERE company_id = ? AND status = 'active'
            AND processing_status = 'needs_review'
        ");
        $stmt->execute([$companyId]);
        $summary['pending_reviews'] = (int) $stmt->fetchColumn();

        // Count security events in last 24h
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM ai_document_security_events
            WHERE company_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$companyId]);
        $summary['security_events_24h'] = (int) $stmt->fetchColumn();

        // Count classification violations
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM ai_document_security_events
            WHERE company_id = ? AND event_type = 'classification_violation'
        ");
        $stmt->execute([$companyId]);
        $summary['classification_violations'] = (int) $stmt->fetchColumn();

        return $summary;
    }
}
