<?php
/**
 * AI F24 — Security Event Service.
 *
 * Tracks and manages security events across all AI operations.
 * Provides monitoring, alerting, and incident response capabilities.
 */
class AISecurityEventService
{
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_CRITICAL = 'critical';

    const STATUS_OPEN = 'open';
    const STATUS_ACKNOWLEDGED = 'acknowledged';
    const STATUS_INVESTIGATING = 'investigating';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_IGNORED = 'ignored';

    private static array $thresholds = [
        'warning' => 5,
        'high' => 10,
        'critical' => 1,
    ];

    /**
     * Record a security event.
     */
    public static function record(PDO $db, string $eventType, array $data = []): array
    {
        $severity = self::determineSeverity($eventType);
        $eventKey = 'sec_' . uniqid();

        try {
            $stmt = $db->prepare("
                INSERT INTO ai_security_events
                    (event_key, event_type, severity, request_id, trace_id,
                     company_id, user_id, event_category, event_data_json, safe_data_json, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $eventKey,
                $eventType,
                $severity,
                $data['request_id'] ?? null,
                $data['trace_id'] ?? null,
                $data['company_id'] ?? ($_SESSION['company_id'] ?? 1),
                $data['user_id'] ?? ($_SESSION['user_id'] ?? null),
                $data['category'] ?? $eventType,
                json_encode($data),
                json_encode(self::sanitizeEventData($data)),
                self::STATUS_OPEN,
            ]);

            $eventId = (int) $db->lastInsertId();

            // Check if alert thresholds are exceeded
            self::checkAlertThresholds($db, $eventType, $severity);

            return [
                'success' => true,
                'event_id' => $eventId,
                'event_key' => $eventKey,
                'severity' => $severity,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Record policy violation.
     */
    public static function recordPolicyViolation(PDO $db, array $data = []): array
    {
        return self::record($db, 'policy_violation', array_merge($data, [
            'category' => 'policy_violation',
        ]));
    }

    /**
     * Record permission denied.
     */
    public static function recordPermissionDenied(PDO $db, array $data = []): array
    {
        return self::record($db, 'permission_denied', array_merge($data, [
            'category' => 'permission_denied',
        ]));
    }

    /**
     * Record prompt injection attempt.
     */
    public static function recordInjectionAttempt(PDO $db, array $data = []): array
    {
        return self::record($db, 'prompt_injection_attempt', array_merge($data, [
            'category' => 'injection',
            'severity' => self::SEVERITY_HIGH,
        ]));
    }

    /**
     * Record unauthorized tool access.
     */
    public static function recordUnauthorizedTool(PDO $db, array $data = []): array
    {
        return self::record($db, 'unauthorized_tool', array_merge($data, [
            'category' => 'unauthorized_access',
        ]));
    }

    /**
     * Record blocked provider.
     */
    public static function recordBlockedProvider(PDO $db, array $data = []): array
    {
        return self::record($db, 'blocked_provider', array_merge($data, [
            'category' => 'provider_restriction',
        ]));
    }

    /**
     * Record sensitive data leak attempt.
     */
    public static function recordDataLeakAttempt(PDO $db, array $data = []): array
    {
        return self::record($db, 'sensitive_data_leak_attempt', array_merge($data, [
            'category' => 'data_leak',
            'severity' => self::SEVERITY_CRITICAL,
        ]));
    }

    /**
     * Record rate limit exceeded.
     */
    public static function recordRateLimitExceeded(PDO $db, array $data = []): array
    {
        return self::record($db, 'rate_limit_exceeded', array_merge($data, [
            'category' => 'rate_limit',
        ]));
    }

    /**
     * Record autonomy limit exceeded.
     */
    public static function recordAutonomyLimitExceeded(PDO $db, array $data = []): array
    {
        return self::record($db, 'autonomy_limit_exceeded', array_merge($data, [
            'category' => 'autonomy_limit',
        ]));
    }

    /**
     * Record cost limit exceeded.
     */
    public static function recordCostLimitExceeded(PDO $db, array $data = []): array
    {
        return self::record($db, 'cost_limit_exceeded', array_merge($data, [
            'category' => 'cost_limit',
        ]));
    }

    /**
     * Record injection detection.
     */
    public static function recordInjectionDetected(PDO $db, array $data = []): array
    {
        return self::record($db, 'injection_detected', array_merge($data, [
            'category' => 'injection',
            'severity' => self::SEVERITY_HIGH,
        ]));
    }

    /**
     * Get security events with filtering.
     */
    public static function getEvents(PDO $db, array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['event_type'])) {
            $where[] = 'event_type = ?';
            $params[] = $filters['event_type'];
        }
        if (!empty($filters['severity'])) {
            $where[] = 'severity = ?';
            $params[] = $filters['severity'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['company_id'])) {
            $where[] = 'company_id = ?';
            $params[] = $filters['company_id'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['category'])) {
            $where[] = 'event_category = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = '(event_type LIKE ? OR event_category LIKE ?)';
            $params[] = $search;
            $params[] = $search;
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($page - 1) * $perPage;

        try {
            // Get total count
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM ai_security_events $whereClause");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            // Get events
            $stmt = $db->prepare("
                SELECT e.*, u.name as user_name, u.email as user_email
                FROM ai_security_events e
                LEFT JOIN users u ON e.user_id = u.id
                $whereClause
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute(array_merge($params, [$perPage, $offset]));
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'events' => $events,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ];
        } catch (\Throwable $e) {
            return ['events' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 0];
        }
    }

    /**
     * Update event status.
     */
    public static function updateStatus(PDO $db, int $eventId, string $status, ?int $userId = null): bool
    {
        try {
            $stmt = $db->prepare("
                UPDATE ai_security_events
                SET status = ?,
                    " . ($userId ? 'acknowledged_by = ?, ' : '') . "
                    " . ($status === 'resolved' ? 'resolved_at = NOW(), resolved_by = ?' : '') . "
                    " . "updated_at = NOW()
                WHERE id = ?
            ");

            $params = [$status];
            if ($userId) {
                $params[] = $userId;
            }
            if ($status === 'resolved') {
                $params[] = $userId;
            }
            $params[] = $eventId;

            $stmt->execute($params);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get security event statistics.
     */
    public static function getStatistics(PDO $db, ?int $companyId = null): array
    {
        try {
            $where = '';
            $params = [];

            if ($companyId) {
                $where = 'WHERE company_id = ?';
                $params[] = $companyId;
            }

            // Events by type
            $stmt = $db->prepare("
                SELECT event_type, COUNT(*) as count
                FROM ai_security_events $where
                GROUP BY event_type
                ORDER BY count DESC
                LIMIT 20
            ");
            $stmt->execute($params);
            $byType = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Events by severity
            $stmt = $db->prepare("
                SELECT severity, COUNT(*) as count
                FROM ai_security_events $where
                GROUP BY severity
            ");
            $stmt->execute($params);
            $bySeverity = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Open events by severity
            $stmt = $db->prepare("
                SELECT severity, COUNT(*) as count
                FROM ai_security_events
                WHERE status = 'open' $where
                GROUP BY severity
            ");
            $stmt->execute($params);
            $openBySeverity = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Events by status
            $stmt = $db->prepare("
                SELECT status, COUNT(*) as count
                FROM ai_security_events $where
                GROUP BY status
            ");
            $stmt->execute($params);
            $byStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Recent events (last 24 hours)
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM ai_security_events
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) $where
            ");
            $stmt->execute($params);
            $last24Hours = (int) $stmt->fetchColumn();

            // Last 7 days
            $stmt = $db->prepare("
                SELECT DATE(created_at) as date, COUNT(*) as count
                FROM ai_security_events
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) $where
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute($params);
            $dailyTrend = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            return [
                'total_events' => $this->getTotalCount($db, $where, $params),
                'last_24_hours' => $last24Hours,
                'by_type' => $byType,
                'by_severity' => $bySeverity,
                'open_by_severity' => $openBySeverity,
                'by_status' => $byStatus,
                'daily_trend' => $dailyTrend,
            ];
        } catch (\Throwable $e) {
            return [
                'total_events' => 0,
                'last_24_hours' => 0,
                'by_type' => [],
                'by_severity' => [],
                'open_by_severity' => [],
                'by_status' => [],
                'daily_trend' => [],
            ];
        }
    }

    /**
     * Get high-severity open events for alerting.
     */
    public static function getAlertableEvents(PDO $db, ?int $companyId = null): array
    {
        try {
            $where = 'WHERE status = "open" AND severity IN ("high", "critical")';
            $params = [];

            if ($companyId) {
                $where .= ' AND company_id = ?';
                $params[] = $companyId;
            }

            $stmt = $db->prepare("
                SELECT * FROM ai_security_events
                $where
                ORDER BY created_at DESC
                LIMIT 100
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Determine severity based on event type.
     */
    private static function determineSeverity(string $eventType): string
    {
        $criticalTypes = [
            'sensitive_data_leak_attempt',
            'prompt_injection_attempt',
            'credential_exposure',
            'security_breach',
            'kill_switch_triggered',
        ];

        $highTypes = [
            'unauthorized_tool',
            'blocked_provider',
            'autonomy_limit_exceeded',
            'cost_limit_exceeded',
            'data_leak_attempt',
            'injection_detected',
            'sql_safety_violation',
            'action_blocked',
        ];

        $warningTypes = [
            'rate_limit_exceeded',
            'policy_violation',
            'permission_denied',
            'confirmation_required',
            'review_required',
        ];

        if (in_array($eventType, $criticalTypes)) {
            return self::SEVERITY_CRITICAL;
        } elseif (in_array($eventType, $highTypes)) {
            return self::SEVERITY_HIGH;
        } elseif (in_array($eventType, $warningTypes)) {
            return self::SEVERITY_WARNING;
        }

        return self::SEVERITY_INFO;
    }

    /**
     * Check if alert thresholds are exceeded.
     */
    private static function checkAlertThresholds(PDO $db, string $eventType, string $severity): void
    {
        if ($severity !== self::SEVERITY_CRITICAL) {
            return;
        }

        try {
            // Count critical events in last hour
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM ai_security_events
                WHERE severity = 'critical'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute();
            $criticalCount = (int) $stmt->fetchColumn();

            // If too many critical events, log an aggregate alert
            if ($criticalCount >= self::$thresholds['critical']) {
                self::record($db, 'alert_critical_threshold', [
                    'category' => 'threshold_exceeded',
                    'severity' => self::SEVERITY_CRITICAL,
                    'critical_count' => $criticalCount,
                ]);
            }
        } catch (\Throwable $e) {
            // Silently fail - alerting is secondary to event recording
        }
    }

    /**
     * Sanitize event data to remove sensitive information.
     */
    private static function sanitizeEventData(array $data): array
    {
        $sensitiveKeys = ['password', 'secret', 'api_key', 'token', 'authorization', 'credential'];
        $safe = [];

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $safe[$key] = '[REDACTED]';
            } else {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }

    /**
     * Get total count.
     */
    private static function getTotalCount(PDO $db, string $where, array $params): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM ai_security_events $where";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}