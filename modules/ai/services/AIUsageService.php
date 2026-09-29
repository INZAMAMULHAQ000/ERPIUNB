<?php
/**
 * AI F16 — Usage Tracking Service.
 *
 * Tracks AI usage, costs, and provides aggregated statistics.
 */
class AIUsageService
{
    /**
     * Log a usage event.
     */
    public static function log(PDO $db, array $data): int
    {
        if (class_exists('AIGovernanceService') && !AIGovernanceService::tableExists($db, 'ai_usage_logs')) {
            return 0;
        }

        if (!isset($data['estimated_cost']) && !empty($data['model_id']) && class_exists('AIGovernanceService')) {
            $estimate = AIGovernanceService::estimateCostForModelId(
                $db,
                (int) $data['model_id'],
                isset($data['input_tokens']) ? (int) $data['input_tokens'] : null,
                isset($data['output_tokens']) ? (int) $data['output_tokens'] : null
            );
            $data['estimated_cost'] = $estimate['estimated_cost'] ?? 0;
            $data['metadata']['cost_basis'] = $estimate['basis'] ?? 'unavailable';
            $data['metadata']['cost_available'] = $estimate['available'] ?? false;
        }

        $db->prepare("
            INSERT INTO ai_usage_logs (provider_id, model_id, feature, user_id, company_id, request_type,
            input_tokens, output_tokens, estimated_cost, duration_ms, status, error_category, metadata_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $data['provider_id'] ?? null,
            $data['model_id'] ?? null,
            $data['feature'] ?? 'unknown',
            $data['user_id'] ?? $_SESSION['user_id'] ?? null,
            $data['company_id'] ?? $_SESSION['company_id'] ?? 1,
            $data['request_type'] ?? null,
            $data['input_tokens'] ?? 0,
            $data['output_tokens'] ?? 0,
            $data['estimated_cost'] ?? 0,
            $data['duration_ms'] ?? 0,
            $data['status'] ?? 'success',
            $data['error_category'] ?? null,
            (!empty($data['metadata']))
                ? json_encode(class_exists('AIGovernanceService') ? AIGovernanceService::sanitizeValue($data['metadata']) : $data['metadata'])
                : null,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Get usage statistics for a date range.
     */
    public static function getStats(PDO $db, array $filters = []): array
    {
        if (class_exists('AIGovernanceService') && !AIGovernanceService::tableExists($db, 'ai_usage_logs')) {
            return self::emptyStats();
        }

        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            $filters['date_from'] = date('Y-m-d 00:00:00', strtotime('-30 days'));
        }

        $where = ["1=1"];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['feature'])) {
            $where[] = "feature = ?";
            $params[] = $filters['feature'];
        }
        if (!empty($filters['provider_id'])) {
            $where[] = "provider_id = ?";
            $params[] = $filters['provider_id'];
        }
        if (!empty($filters['company_id'])) {
            $where[] = "company_id = ?";
            $params[] = $filters['company_id'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }

        $whereClause = implode(' AND ', $where);

        // Total stats
        $stmt = $db->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
                   SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                   COALESCE(AVG(duration_ms), 0) as avg_duration,
                   COALESCE(SUM(input_tokens), 0) as total_input_tokens,
                   COALESCE(SUM(output_tokens), 0) as total_output_tokens,
                   COALESCE(SUM(estimated_cost), 0) as total_cost
            FROM ai_usage_logs WHERE {$whereClause}
        ");
        $stmt->execute($params);
        $stats = $stmt->fetch() ?: self::emptyStats();

        // By feature
        $stmt2 = $db->prepare("
            SELECT feature, COUNT(*) as count, SUM(estimated_cost) as cost
            FROM ai_usage_logs WHERE {$whereClause}
            GROUP BY feature ORDER BY count DESC
        ");
        $stmt2->execute($params);
        $stats['by_feature'] = $stmt2->fetchAll();

        // By status
        $stmt3 = $db->prepare("
            SELECT status, COUNT(*) as count
            FROM ai_usage_logs WHERE {$whereClause}
            GROUP BY status
        ");
        $stmt3->execute($params);
        $stats['by_status'] = $stmt3->fetchAll();

        return $stats;
    }

    public static function getGroupedStats(PDO $db, array $filters = [], string $groupBy = 'feature'): array
    {
        if (class_exists('AIGovernanceService') && !AIGovernanceService::tableExists($db, 'ai_usage_logs')) {
            return [];
        }

        $groupMap = [
            'provider' => ['expr' => 'COALESCE(p.name, "Unknown")', 'join' => 'LEFT JOIN ai_providers p ON p.id = l.provider_id'],
            'model' => ['expr' => 'COALESCE(m.display_name, l.model_id, "Unknown")', 'join' => 'LEFT JOIN ai_models m ON m.id = l.model_id'],
            'company' => ['expr' => 'CAST(l.company_id AS CHAR)', 'join' => ''],
            'user' => ['expr' => 'COALESCE(u.name, CONCAT("User #", l.user_id))', 'join' => 'LEFT JOIN users u ON u.id = l.user_id'],
            'feature' => ['expr' => 'l.feature', 'join' => ''],
            'status' => ['expr' => 'l.status', 'join' => ''],
        ];

        $group = $groupMap[$groupBy] ?? $groupMap['feature'];
        [$where, $params] = self::buildWhere($filters, 'l');

        $sql = "
            SELECT {$group['expr']} as label,
                   COUNT(*) as total_requests,
                   SUM(CASE WHEN l.status = 'success' THEN 1 ELSE 0 END) as successful_requests,
                   SUM(CASE WHEN l.status <> 'success' THEN 1 ELSE 0 END) as failed_requests,
                   COALESCE(AVG(l.duration_ms), 0) as avg_duration_ms,
                   COALESCE(SUM(l.input_tokens), 0) as input_tokens,
                   COALESCE(SUM(l.output_tokens), 0) as output_tokens,
                   COALESCE(SUM(l.estimated_cost), 0) as estimated_cost
            FROM ai_usage_logs l
            {$group['join']}
            WHERE {$where}
            GROUP BY label
            ORDER BY total_requests DESC
            LIMIT 50
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getRecent(PDO $db, int $limit = 25): array
    {
        if (class_exists('AIGovernanceService') && !AIGovernanceService::tableExists($db, 'ai_usage_logs')) {
            return [];
        }

        $safeLimit = min(100, max(1, $limit));
        $stmt = $db->query("
            SELECT l.*, p.name as provider_name, m.display_name as model_name, u.name as user_name
            FROM ai_usage_logs l
            LEFT JOIN ai_providers p ON p.id = l.provider_id
            LEFT JOIN ai_models m ON m.id = l.model_id
            LEFT JOIN users u ON u.id = l.user_id
            ORDER BY l.created_at DESC
            LIMIT {$safeLimit}
        ");
        return $stmt->fetchAll();
    }

    /**
     * Get daily usage for charts.
     */
    public static function getDailyUsage(PDO $db, int $days = 30, ?int $companyId = null): array
    {
        if (class_exists('AIGovernanceService') && !AIGovernanceService::tableExists($db, 'ai_usage_logs')) {
            return [];
        }

        $sql = "SELECT DATE(created_at) as date, COUNT(*) as requests, SUM(estimated_cost) as cost
                FROM ai_usage_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params = [$days];

        if ($companyId) {
            $sql .= " AND company_id = ?";
            $params[] = $companyId;
        }

        $sql .= " GROUP BY DATE(created_at) ORDER BY date ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get today's usage summary.
     */
    public static function getTodaySummary(PDO $db, ?int $companyId = null): array
    {
        if (class_exists('AIGovernanceService') && !AIGovernanceService::tableExists($db, 'ai_usage_logs')) {
            return ['requests' => 0, 'successful' => 0, 'failed' => 0, 'total_tokens' => 0, 'total_cost' => 0];
        }

        $sql = "SELECT COUNT(*) as requests,
                       SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
                       SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                       SUM(input_tokens + output_tokens) as total_tokens,
                       SUM(estimated_cost) as total_cost
                FROM ai_usage_logs WHERE DATE(created_at) = CURDATE()";
        $params = [];
        if ($companyId) {
            $sql .= " AND company_id = ?";
            $params[] = $companyId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: ['requests' => 0, 'successful' => 0, 'failed' => 0, 'total_tokens' => 0, 'total_cost' => 0];
    }

    public static function refreshDailyAggregates(PDO $db, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        if (
            !class_exists('AIGovernanceService') ||
            !AIGovernanceService::tableExists($db, 'ai_usage_logs') ||
            !AIGovernanceService::tableExists($db, 'ai_usage_daily_aggregates')
        ) {
            return ['success' => false, 'error' => 'Usage aggregate tables are unavailable.'];
        }

        $dateFrom = $dateFrom ?: date('Y-m-d', strtotime('-30 days'));
        $dateTo = $dateTo ?: date('Y-m-d');

        $db->prepare("DELETE FROM ai_usage_daily_aggregates WHERE usage_date BETWEEN ? AND ?")
           ->execute([$dateFrom, $dateTo]);

        $db->prepare("
            INSERT INTO ai_usage_daily_aggregates
                (usage_date, provider_id, model_id, feature, user_id, company_id, status,
                 request_count, input_tokens, output_tokens, estimated_cost, avg_duration_ms)
            SELECT DATE(created_at), provider_id, model_id, feature, user_id, company_id, status,
                   COUNT(*), COALESCE(SUM(input_tokens), 0), COALESCE(SUM(output_tokens), 0),
                   COALESCE(SUM(estimated_cost), 0), COALESCE(AVG(duration_ms), 0)
            FROM ai_usage_logs
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at), provider_id, model_id, feature, user_id, company_id, status
        ")->execute([$dateFrom, $dateTo]);

        return ['success' => true, 'date_from' => $dateFrom, 'date_to' => $dateTo];
    }

    private static function buildWhere(array $filters, string $alias = ''): array
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            $filters['date_from'] = date('Y-m-d 00:00:00', strtotime('-30 days'));
        }

        $where = ["1=1"];
        $params = [];
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $op) {
            if (!empty($filters[$key])) {
                $where[] = "{$prefix}created_at {$op} ?";
                $params[] = $filters[$key];
            }
        }
        foreach (['feature', 'status', 'provider_id', 'model_id', 'company_id', 'user_id'] as $key) {
            if (!empty($filters[$key])) {
                $where[] = "{$prefix}{$key} = ?";
                $params[] = $filters[$key];
            }
        }

        return [implode(' AND ', $where), $params];
    }

    private static function emptyStats(): array
    {
        return [
            'total' => 0,
            'successful' => 0,
            'failed' => 0,
            'avg_duration' => 0,
            'total_input_tokens' => 0,
            'total_output_tokens' => 0,
            'total_cost' => 0,
            'by_feature' => [],
            'by_status' => [],
        ];
    }
}
