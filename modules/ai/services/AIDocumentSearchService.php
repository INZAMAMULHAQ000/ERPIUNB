<?php
/**
 * AI Document Search Service
 * 
 * F20/F27: Document Search — keyword, semantic, metadata filters.
 * All methods are static and take $db as first parameter.
 *
 * Test 11: Search with authorized and unauthorized users
 * Test 12: Restricted documents cannot appear in search results or AI answers
 */
class AIDocumentSearchService {

    /**
     * Test 11: Search documents with permission checks and tenant isolation.
     * Test 12: Restricted/sensitive documents filtered based on user permissions.
     */
    public static function search($db, $companyId, $params = []) {
        $userId = $params['user_id'] ?? ($_SESSION['user_id'] ?? 0);

        // Test 11: Permission check
        if (!hasPermission('ai.documents.view') && !hasPermission('ai.documents.search')) {
            return ['results' => [], 'total' => 0, 'limit' => $params['limit'] ?? 20, 'offset' => 0, 'error' => 'Permission denied'];
        }

        $sql = "SELECT d.*, u.name as created_by_name
            FROM ai_documents d
            LEFT JOIN users u ON u.id = d.created_by
            WHERE d.company_id = ? AND d.status = 'active'";
        $queryParams = [(int)$companyId];

        // Test 11: Tenant isolation — always scoped by company
        // (already done via WHERE company_id = ?)

        // Test 12: Filter restricted documents based on user permissions
        $canViewSensitive = hasPermission('ai.documents.review') || hasPermission('ai.documents.manage');
        if (!$canViewSensitive) {
            $sql .= " AND d.is_sensitive = 0";
        }

        // Test 12: Filter by classification if user doesn't have review permission
        if (!$canViewSensitive) {
            $sql .= " AND (d.sensitive_category IS NULL OR d.sensitive_category IN ('public', 'internal'))";
        }

        // Keyword search
        if (!empty($params['query'])) {
            $searchTerm = '%' . $params['query'] . '%';
            $sql .= " AND (d.file_name LIKE ? OR d.extracted_text LIKE ? OR d.document_type LIKE ?)";
            $queryParams[] = $searchTerm;
            $queryParams[] = $searchTerm;
            $queryParams[] = $searchTerm;
        }

        // Metadata filters
        if (!empty($params['document_type'])) {
            $sql .= " AND d.document_type = ?";
            $queryParams[] = $params['document_type'];
        }
        if (!empty($params['processing_status'])) {
            $sql .= " AND d.processing_status = ?";
            $queryParams[] = $params['processing_status'];
        }
        if (!empty($params['source_module'])) {
            $sql .= " AND d.source_module = ?";
            $queryParams[] = $params['source_module'];
        }
        if (!empty($params['date_from'])) {
            $sql .= " AND d.created_at >= ?";
            $queryParams[] = $params['date_from'];
        }
        if (!empty($params['date_to'])) {
            $sql .= " AND d.created_at <= ?";
            $queryParams[] = $params['date_to'];
        }
        if (!empty($params['is_sensitive'])) {
            // Only allow sensitive filter if user has permission
            if ($canViewSensitive) {
                $sql .= " AND d.is_sensitive = ?";
                $queryParams[] = (int)$params['is_sensitive'];
            }
        }

        // Sort
        $orderBy = $params['sort_by'] ?? 'created_at';
        $orderDir = ($params['sort_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $allowedSorts = ['created_at', 'file_name', 'document_type', 'file_size', 'processing_status'];
        if (in_array($orderBy, $allowedSorts)) {
            $sql .= " ORDER BY d.$orderBy $orderDir";
        } else {
            $sql .= " ORDER BY d.created_at DESC";
        }

        // Pagination
        $limit = min(100, max(1, (int)($params['limit'] ?? 20)));
        $offset = max(0, (int)($params['offset'] ?? 0));
        $sql .= " LIMIT $limit OFFSET $offset";

        $stmt = $db->prepare($sql);
        $stmt->execute($queryParams);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Test 12: Sanitize sensitive content from search results
        if (class_exists('AISecretProtectionService')) {
            foreach ($results as &$result) {
                if (!empty($result['extracted_text'])) {
                    $result['extracted_text'] = AISecretProtectionService::redactSecrets($result['extracted_text']);
                }
            }
        }

        // Get total count
        $countSql = "SELECT COUNT(*) FROM ai_documents d WHERE d.company_id = ? AND d.status = 'active'";
        $countParams = [(int)$companyId];

        if (!$canViewSensitive) {
            $countSql .= " AND d.is_sensitive = 0";
        }

        if (!empty($params['query'])) {
            $searchTerm = '%' . $params['query'] . '%';
            $countSql .= " AND (d.file_name LIKE ? OR d.extracted_text LIKE ? OR d.document_type LIKE ?)";
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
        }
        if (!empty($params['document_type'])) {
            $countSql .= " AND d.document_type = ?";
            $countParams[] = $params['document_type'];
        }

        $countStmt = $db->prepare($countSql);
        $countStmt->execute($countParams);
        $total = (int)$countStmt->fetchColumn();

        return ['results' => $results, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /**
     * Test 12: Semantic search using document chunks — respects permissions.
     */
    public static function semanticSearch($db, $companyId, $query, $limit = 10, $userId = null) {
        // Test 11: Permission check
        if (!hasPermission('ai.documents.view') && !hasPermission('ai.documents.search')) {
            return [];
        }

        $canViewSensitive = hasPermission('ai.documents.review') || hasPermission('ai.documents.manage');

        $sql = "SELECT c.*, d.file_name, d.document_type, d.document_id, d.is_sensitive
            FROM ai_document_chunks c
            JOIN ai_documents d ON d.document_id = c.document_id
            WHERE d.company_id = ? AND d.status = 'active'";

        // Test 12: Filter restricted documents
        if (!$canViewSensitive) {
            $sql .= " AND d.is_sensitive = 0";
        }

        $sql .= " AND c.content LIKE ?
            ORDER BY d.created_at DESC
            LIMIT ?";

        $stmt = $db->prepare($sql);
        $stmt->execute([$companyId, '%' . $query . '%', (int)$limit]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Test 12: Sanitize sensitive content from results
        if (class_exists('AISecretProtectionService')) {
            foreach ($results as &$result) {
                if (!empty($result['content'])) {
                    $result['content'] = AISecretProtectionService::redactSecrets($result['content']);
                }
            }
        }

        return $results;
    }

    /**
     * Get documents by related ERP record — tenant isolated.
     */
    public static function getByRecord($db, $companyId, $recordType, $recordId) {
        $stmt = $db->prepare("SELECT * FROM ai_documents 
            WHERE company_id = ? AND status = 'active'
            AND related_record_type = ? AND related_record_id = ?
            ORDER BY created_at DESC");
        $stmt->execute([$companyId, $recordType, (int)$recordId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get expiring documents.
     */
    public static function getExpiring($db, $companyId, $withinDays = 90) {
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
}
