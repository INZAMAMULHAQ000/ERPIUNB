<?php
/**
 * AI F33 — Knowledge Review & Lifecycle Service
 * 
 * Review queue, knowledge lifecycle management.
 * Sections 17, 27 of F33 specification.
 */

class AIKnowledgeReviewService {

    private $db;
    private $userId;
    private $companyId;

    public function __construct(PDO $db, int $userId) {
        $this->db = $db;
        $this->userId = $userId;
        $this->companyId = $_SESSION['company_id'] ?? 0;
    }

    /**
     * Create a review item.
     */
    public function createReview(array $params): array {
        $type = $params['review_type'] ?? '';
        $title = $params['title'] ?? '';
        $severity = $params['severity'] ?? 'normal';
        $sourceId = (int) ($params['source_id'] ?? 0);
        $versionId = (int) ($params['version_id'] ?? 0);
        $collectionId = (int) ($params['collection_id'] ?? 0);

        if (!$type || !$title) return ['success' => false, 'error' => 'Type and title required'];
        if ($sourceId > 0 && !$this->sourceBelongsToCompany($sourceId)) {
            return ['success' => false, 'error' => 'Source not found or access denied'];
        }
        if ($versionId > 0 && !$this->versionBelongsToCompany($versionId)) {
            return ['success' => false, 'error' => 'Version not found or access denied'];
        }
        if ($collectionId > 0 && !$this->collectionBelongsToCompany($collectionId)) {
            return ['success' => false, 'error' => 'Collection not found or access denied'];
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_knowledge_reviews 
                (company_id, source_id, version_id, collection_id, review_type, severity, title, description, evidence, assigned_to)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $this->companyId,
                $sourceId ?: null,
                $versionId ?: null,
                $collectionId ?: null,
                $type, $severity, $title,
                $params['description'] ?? null,
                json_encode($params['evidence'] ?? []),
                $params['assigned_to'] ?? null,
            ]);

            $reviewId = (int) $this->db->lastInsertId();
            $this->logEvent('review', 'review', $reviewId, ['type' => $type, 'severity' => $severity]);

            return ['success' => true, 'review_id' => $reviewId];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to create review'];
        }
    }

    /**
     * Get pending reviews.
     */
    public function getPendingReviews(?string $type = null, ?int $assignedTo = null): array {
        $conditions = ["r.company_id = ?", "r.status IN ('pending', 'in_review')"];
        $params = [$this->companyId];

        if ($type) {
            $conditions[] = "r.review_type = ?";
            $params[] = $type;
        }
        if ($assignedTo) {
            $conditions[] = "r.assigned_to = ?";
            $params[] = $assignedTo;
        }

        $where = implode(' AND ', $conditions);
        $stmt = $this->db->prepare("
            SELECT r.*, 
                   s.title as source_title, c.name as collection_name,
                   CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name
            FROM ai_knowledge_reviews r
            LEFT JOIN ai_knowledge_sources s ON r.source_id = s.id AND EXISTS (
                SELECT 1 FROM ai_knowledge_collections sc
                WHERE sc.id = s.collection_id AND sc.company_id = ?
            )
            LEFT JOIN ai_knowledge_collections c ON r.collection_id = c.id AND c.company_id = ?
            LEFT JOIN employees u ON r.assigned_to = u.id
            WHERE {$where}
            ORDER BY FIELD(r.severity, 'urgent', 'high', 'normal', 'low'), r.created_at ASC
            LIMIT 100
        ");
        $stmt->execute(array_merge([$this->companyId, $this->companyId], $params));
        return $stmt->fetchAll();
    }

    /**
     * Resolve a review.
     */
    public function resolveReview(int $reviewId, string $resolution, ?string $notes = null): array {
        $validResolutions = ['approved', 'rejected', 'replaced', 'archived', 'authoritative', 'owner_assigned'];
        if (!in_array($resolution, $validResolutions)) {
            return ['success' => false, 'error' => 'Invalid resolution'];
        }

        try {
            $this->db->prepare("
                UPDATE ai_knowledge_reviews 
                SET status = 'resolved', resolution = ?, resolution_notes = ?, resolved_by = ?, resolved_at = NOW()
                WHERE id = ? AND company_id = ?
            ")->execute([$resolution, $notes, $this->userId, $reviewId, $this->companyId]);

            $this->logEvent('resolution', 'review', $reviewId, ['resolution' => $resolution]);

            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to resolve review'];
        }
    }

    /**
     * Dismiss a review.
     */
    public function dismissReview(int $reviewId, ?string $reason = null): array {
        try {
            $this->db->prepare("
                UPDATE ai_knowledge_reviews SET status = 'dismissed', resolution_notes = ? WHERE id = ? AND company_id = ?
            ")->execute([$reason, $reviewId, $this->companyId]);
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to dismiss'];
        }
    }

    /**
     * Get knowledge lifecycle status.
     */
    public function getLifecycleStatus(): array {
        $statuses = ['draft', 'review', 'approved', 'published', 'superseded', 'archived'];
        $counts = [];

        foreach ($statuses as $status) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM ai_knowledge_versions WHERE approval_status = ? AND source_id IN (SELECT id FROM ai_knowledge_sources WHERE collection_id IN (SELECT id FROM ai_knowledge_collections WHERE company_id = ?))");
            $stmt->execute([$status, $this->companyId]);
            $counts[$status] = (int) $stmt->fetchColumn();
        }

        return $counts;
    }

    /**
     * Get review statistics.
     */
    public function getReviewStats(): array {
        $stmt = $this->db->prepare("
            SELECT review_type, status, COUNT(*) as count
            FROM ai_knowledge_reviews 
            WHERE company_id = ?
            GROUP BY review_type, status
        ");
        $stmt->execute([$this->companyId]);
        $rows = $stmt->fetchAll();

        $stats = ['total' => 0, 'pending' => 0, 'resolved' => 0, 'by_type' => []];
        foreach ($rows as $row) {
            $stats['total'] += $row['count'];
            $stats[$row['status']] = ($stats[$row['status']] ?? 0) + $row['count'];
            $stats['by_type'][$row['review_type']] = ($stats['by_type'][$row['review_type']] ?? 0) + $row['count'];
        }

        return $stats;
    }

    private function logEvent(string $eventType, ?string $entityType, ?int $entityId, array $metadata = []): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_knowledge_events (company_id, event_type, entity_type, entity_id, user_id, metadata_json)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$this->companyId, $eventType, $entityType, $entityId, $this->userId, json_encode($metadata)]);
        } catch (\Throwable $e) {}
    }

    private function collectionBelongsToCompany(int $collectionId): bool {
        $stmt = $this->db->prepare("SELECT 1 FROM ai_knowledge_collections WHERE id = ? AND company_id = ? LIMIT 1");
        $stmt->execute([$collectionId, $this->companyId]);
        return (bool) $stmt->fetchColumn();
    }

    private function sourceBelongsToCompany(int $sourceId): bool {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM ai_knowledge_sources s
            JOIN ai_knowledge_collections c ON s.collection_id = c.id
            WHERE s.id = ? AND c.company_id = ?
            LIMIT 1
        ");
        $stmt->execute([$sourceId, $this->companyId]);
        return (bool) $stmt->fetchColumn();
    }

    private function versionBelongsToCompany(int $versionId): bool {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM ai_knowledge_versions v
            JOIN ai_knowledge_sources s ON v.source_id = s.id
            JOIN ai_knowledge_collections c ON s.collection_id = c.id
            WHERE v.id = ? AND c.company_id = ?
            LIMIT 1
        ");
        $stmt->execute([$versionId, $this->companyId]);
        return (bool) $stmt->fetchColumn();
    }
}
