<?php
/**
 * AI F24 — Review Queue Service.
 *
 * Manages human review queue for high-risk operations.
 */
class AIReviewQueueService
{
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_ESCALATED = 'escalated';

    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_CRITICAL = 'critical';

    /**
     * Add an item to the review queue.
     */
    public static function addToQueue(PDO $db, array $item): array
    {
        try {
            $itemKey = 'review_' . uniqid();

            $stmt = $db->prepare("
                INSERT INTO ai_review_queue
                    (item_key, request_id, trace_id, company_id, user_id,
                     item_type, item_data_json, risk_level, priority, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $itemKey,
                $item['request_id'] ?? null,
                $item['trace_id'] ?? null,
                $item['company_id'] ?? ($_SESSION['company_id'] ?? 1),
                $item['user_id'] ?? null,
                $item['item_type'] ?? 'other',
                json_encode($item['item_data'] ?? $item),
                $item['risk_level'] ?? self::PRIORITY_MEDIUM,
                $item['priority'] ?? self::PRIORITY_MEDIUM,
                self::STATUS_PENDING,
            ]);

            $id = (int) $db->lastInsertId();

            return [
                'success' => true,
                'id' => $id,
                'item_key' => $itemKey,
                'status' => self::STATUS_PENDING,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get pending items for review.
     */
    public static function getPendingItems(PDO $db, array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $where = ['status = ?'];
        $params = [self::STATUS_PENDING];

        if (!empty($filters['risk_level'])) {
            $where[] = 'risk_level = ?';
            $params[] = $filters['risk_level'];
        }
        if (!empty($filters['priority'])) {
            $where[] = 'priority = ?';
            $params[] = $filters['priority'];
        }
        if (!empty($filters['item_type'])) {
            $where[] = 'item_type = ?';
            $params[] = $filters['item_type'];
        }
        if (!empty($filters['company_id'])) {
            $where[] = 'company_id = ?';
            $params[] = $filters['company_id'];
        }
        if (!empty($filters['assigned_to'])) {
            $where[] = 'assigned_to = ?';
            $params[] = $filters['assigned_to'];
        }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = '(item_key LIKE ? OR review_notes LIKE ?)';
            $params[] = $search;
            $params[] = $search;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        try {
            $countStmt = $db->prepare("SELECT COUNT(*) FROM ai_review_queue $whereClause");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $db->prepare("
                SELECT q.*, u.name as user_name, u.email as user_email,
                       a.name as assigned_to_name
                FROM ai_review_queue q
                LEFT JOIN users u ON q.user_id = u.id
                LEFT JOIN users a ON q.assigned_to = a.id
                $whereClause
                ORDER BY priority DESC, risk_level DESC, created_at ASC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute(array_merge($params, [$perPage, $offset]));
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'items' => $items,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ];
        } catch (\Throwable $e) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 0];
        }
    }

    /**
     * Get items needing review by risk level.
     */
    public static function getItemsByRisk(PDO $db, string $riskLevel): array
    {
        try {
            $stmt = $db->prepare("
                SELECT * FROM ai_review_queue
                WHERE risk_level = ? AND status = ?
                ORDER BY priority DESC, created_at ASC
                LIMIT 50
            ");
            $stmt->execute([$riskLevel, self::STATUS_PENDING]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Approve a review item.
     */
    public static function approve(PDO $db, int $itemId, int $reviewerId, string $notes = ''): bool
    {
        return self::updateStatus($db, $itemId, self::STATUS_APPROVED, $reviewerId, $notes);
    }

    /**
     * Reject a review item.
     */
    public static function reject(PDO $db, int $itemId, int $reviewerId, string $notes = ''): bool
    {
        return self::updateStatus($db, $itemId, self::STATUS_REJECTED, $reviewerId, $notes);
    }

    /**
     * Escalate a review item.
     */
    public static function escalate(PDO $db, int $itemId, int $reviewerId, string $notes = ''): bool
    {
        return self::updateStatus($db, $itemId, self::STATUS_ESCALATED, $reviewerId, $notes);
    }

    /**
     * Update review status.
     */
    private static function updateStatus(PDO $db, int $itemId, string $status, int $reviewerId, string $notes = ''): bool
    {
        try {
            $stmt = $db->prepare("
                UPDATE ai_review_queue
                SET status = ?,
                    assigned_to = ?,
                    review_notes = ?,
                    reviewed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND status = 'pending'
            ");

            $stmt->execute([$status, $reviewerId, $notes, $itemId]);
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get queue statistics.
     */
    public static function getStatistics(PDO $db): array
    {
        try {
            // Total pending by risk level
            $stmt = $db->query("
                SELECT risk_level, COUNT(*) as count
                FROM ai_review_queue
                WHERE status = 'pending'
                GROUP BY risk_level
                ORDER BY FIELD(risk_level, 'critical', 'high', 'medium', 'low')
            ");
            $pendingByRisk = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Total items by status
            $stmt = $db->query("
                SELECT status, COUNT(*) as count
                FROM ai_review_queue
                GROUP BY status
                ORDER BY FIELD(status, 'pending', 'approved', 'rejected', 'escalated')
            ");
            $byStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Items by type
            $stmt = $db->query("
                SELECT item_type, COUNT(*) as count
                FROM ai_review_queue
                WHERE status = 'pending'
                GROUP BY item_type
                ORDER BY count DESC
                LIMIT 10
            ");
            $byType = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Total pending
            $stmt = $db->query("SELECT COUNT(*) FROM ai_review_queue WHERE status = 'pending'");
            $totalPending = (int) $stmt->fetchColumn();

            // Last 24 hours
            $stmt = $db->query("
                SELECT COUNT(*) FROM ai_review_queue
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $last24Hours = (int) $stmt->fetchColumn();

            return [
                'total_pending' => $totalPending,
                'last_24_hours' => $last24Hours,
                'pending_by_risk' => $pendingByRisk,
                'by_status' => $byStatus,
                'by_type' => $byType,
            ];
        } catch (\Throwable $e) {
            return [
                'total_pending' => 0,
                'last_24_hours' => 0,
                'pending_by_risk' => [],
                'by_status' => [],
                'by_type' => [],
            ];
        }
    }
}