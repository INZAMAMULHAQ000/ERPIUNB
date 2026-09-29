<?php
/**
 * AI F14 — Insight Service.
 *
 * Manages insight records, lifecycle, deduplication, user interaction, and preferences.
 */
class AIInsightService
{
    // ═══════════════════════════════════════════════════════
    // INSIGHT RECORDS
    // ═══════════════════════════════════════════════════════

    /**
     * Create or update an insight (with deduplication).
     */
    public static function createOrUpdateInsight(
        PDO $db,
        array $definition,
        string $title,
        string $summary,
        string $severity,
        array $evidence,
        ?string $aiNarrative,
        array $targetUserIds
    ): array {
        $defKey = $definition['definition_key'] ?? $definition['id'] ?? '';
        $defId = $definition['db_id'] ?? null;

        if (!$defId) {
            $stmt = $db->prepare("SELECT id FROM ai_insight_definitions WHERE definition_key = ?");
            $stmt->execute([$defKey]);
            $defRow = $stmt->fetch();
            $defId = $defRow ? (int) $defRow['id'] : null;
        }
        if (!$defId) {
            return ['success' => false, 'error' => 'Definition not found.'];
        }

        // Compute dedup hash
        $dedupHash = self::computeDedupHash($definition, $evidence);

        // Check for existing active insight
        $existing = $db->prepare("
            SELECT id FROM ai_insight_records
            WHERE definition_id = ? AND status = 'active' AND dedup_hash = ?
        ");
        $existing->execute([$defId, $dedupHash]);
        $existingRow = $existing->fetch();

        $nav = $definition['navigation'] ?? [];

        if ($existingRow) {
            // Update existing
            $db->prepare("
                UPDATE ai_insight_records SET
                    last_detected_at = NOW(),
                    evidence_json = ?,
                    ai_narrative = COALESCE(?, ai_narrative),
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([json_encode($evidence), $aiNarrative, $existingRow['id']]);

            self::log('insight_updated', (int) $existingRow['id'], ['def' => $defKey]);
            return ['success' => true, 'action' => 'updated', 'insight_id' => (int) $existingRow['id']];
        }

        // Check max active insights per company
        $countStmt = $db->prepare("SELECT COUNT(*) FROM ai_insight_records WHERE status = 'active'");
        $countStmt->execute();
        if ((int) $countStmt->fetchColumn() >= AIInsightEngine::MAX_ACTIVE_INSIGHTS) {
            return ['success' => false, 'error' => 'Max active insights reached.'];
        }

        // Create new insight
        $navRoute = $nav['route'] ?? null;
        $navParams = $nav['params'] ?? [];

        $db->prepare("
            INSERT INTO ai_insight_records
            (definition_id, company_id, title, summary, severity, category, source_type,
             evidence_json, ai_narrative, navigation_json, dedup_hash, status, first_detected_at, created_at)
            VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ")->execute([
            $defId,
            $title,
            $summary,
            $severity,
            $definition['category'] ?? 'general',
            $definition['module'] ?? 'general',
            json_encode($evidence),
            $aiNarrative,
            json_encode(['route' => $navRoute, 'params' => $navParams]),
            $dedupHash,
        ]);

        $insightId = (int) $db->lastInsertId();

        // Create recipients
        self::createRecipients($db, $insightId, $targetUserIds);

        // Create notifications
        self::createNotifications($db, $insightId, $title, $summary, $severity, $targetUserIds, $definition['module'] ?? '', $navRoute);

        self::log('insight_created', $insightId, ['def' => $defKey, 'severity' => $severity]);

        return ['success' => true, 'action' => 'created', 'insight_id' => $insightId];
    }

    /**
     * Compute deterministic deduplication hash.
     */
    private static function computeDedupHash(array $definition, array $evidence): string
    {
        $strategy = $definition['dedup_strategy'] ?? 'same_hash';
        $defKey = $definition['definition_key'] ?? '';

        return match($strategy) {
            'same_day' => hash('sha256', $defKey . date('Y-m-d')),
            'same_records' => hash('sha256', $defKey . json_encode($evidence['sample_ids'] ?? [])),
            'same_hash' => hash('sha256', $defKey . json_encode($evidence)),
            'always_new' => hash('sha256', $defKey . microtime(true)),
            default => hash('sha256', $defKey . json_encode($evidence)),
        };
    }

    /**
     * Create recipient records.
     */
    private static function createRecipients(PDO $db, int $insightId, array $userIds): void
    {
        $stmt = $db->prepare("
            INSERT IGNORE INTO ai_insight_recipients (insight_id, user_id, status, created_at)
            VALUES (?, ?, 'pending', NOW())
        ");
        foreach ($userIds as $userId) {
            $stmt->execute([$insightId, (int) $userId]);
        }
    }

    /**
     * Create in-app notifications for recipients.
     */
    private static function createNotifications(PDO $db, int $insightId, string $title, string $summary, string $severity, array $userIds, string $module, ?string $route): void
    {
        $severityIcon = match($severity) {
            'critical' => '🔴',
            'warning' => '⚠️',
            'notice' => 'ℹ️',
            'info' => '💡',
            default => '📋',
        };

        $notifTitle = "{$severityIcon} {$title}";
        $notifMessage = $summary;
        if ($route) {
            $notifMessage .= "\n\n[View Details]";
        }

        $stmt = $db->prepare("
            INSERT INTO ai_automation_notifications (user_id, notification_type, title, message, created_at)
            VALUES (?, 'in_app', ?, ?, NOW())
        ");

        foreach ($userIds as $userId) {
            // Check user preferences
            if (!self::userWantsNotification($db, (int) $userId, $severity)) continue;
            $stmt->execute([(int) $userId, $notifTitle, $notifMessage]);
        }
    }

    /**
     * Check if user wants this severity level notification.
     */
    private static function userWantsNotification(PDO $db, int $userId, string $severity): bool
    {
        $stmt = $db->prepare("
            SELECT * FROM ai_insight_preferences WHERE user_id = ? AND module IS NULL LIMIT 1
        ");
        $stmt->execute([$userId]);
        $prefs = $stmt->fetch();

        if (!$prefs) return true; // Default: allow all

        if (!$prefs['insights_enabled']) return false;

        return match($severity) {
            'critical' => (bool) $prefs['critical_enabled'],
            'warning' => (bool) $prefs['warning_enabled'],
            'notice' => (bool) $prefs['notice_enabled'],
            'info' => (bool) $prefs['info_enabled'],
            default => true,
        };
    }

    /**
     * Get insights for a user.
     */
    public static function getUserInsights(PDO $db, int $userId, array $filters = []): array
    {
        $sql = "
            SELECT ir.*, idk.definition_key, idk.name as def_name, idk.module, idk.navigation_config,
                   rec.status as recipient_status, rec.seen_at, rec.dismissed_at
            FROM ai_insight_records ir
            JOIN ai_insight_recipients rec ON ir.id = rec.insight_id
            JOIN ai_insight_definitions idk ON ir.definition_id = idk.id
            WHERE rec.user_id = ?
        ";
        $params = [$userId];

        // Apply filters
        if (!empty($filters['status'])) {
            $sql .= " AND ir.status = ?";
            $params[] = $filters['status'];
        } elseif (!empty($filters['recipient_status'])) {
            $sql .= " AND rec.status = ?";
            $params[] = $filters['recipient_status'];
        } else {
            $sql .= " AND ir.status = 'active'";
        }

        if (!empty($filters['severity'])) {
            $sql .= " AND ir.severity = ?";
            $params[] = $filters['severity'];
        }

        if (!empty($filters['module'])) {
            $sql .= " AND ir.module = ?";
            $params[] = $filters['module'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND ir.created_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND ir.created_at <= ?";
            $params[] = $filters['date_to'];
        }

        $sql .= " ORDER BY
            CASE ir.severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 WHEN 'notice' THEN 2 ELSE 3 END,
            ir.created_at DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int) $filters['limit'];
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get insight counts for a user (by severity).
     */
    public static function getUserInsightCounts(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("
            SELECT ir.severity, COUNT(*) as count
            FROM ai_insight_records ir
            JOIN ai_insight_recipients rec ON ir.id = rec.insight_id
            WHERE rec.user_id = ? AND ir.status = 'active'
            GROUP BY ir.severity
        ");
        $stmt->execute([$userId]);
        $counts = ['critical' => 0, 'warning' => 0, 'notice' => 0, 'info' => 0];
        while ($row = $stmt->fetch()) {
            $counts[$row['severity']] = (int) $row['count'];
        }
        $counts['total'] = array_sum($counts);
        return $counts;
    }

    // ═══════════════════════════════════════════════════════
    // USER INTERACTION
    // ═══════════════════════════════════════════════════════

    /**
     * Mark insight as seen by user.
     */
    public static function markSeen(PDO $db, int $insightId, int $userId): array
    {
        $stmt = $db->prepare("
            UPDATE ai_insight_recipients SET status = 'seen', seen_at = NOW()
            WHERE insight_id = ? AND user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$insightId, $userId]);

        // Update insight status if all recipients have seen
        $pending = $db->prepare("SELECT COUNT(*) FROM ai_insight_recipients WHERE insight_id = ? AND status = 'pending'");
        $pending->execute([$insightId]);
        if ((int) $pending->fetchColumn() === 0) {
            $db->prepare("UPDATE ai_insight_records SET status = 'seen' WHERE id = ? AND status = 'active'")->execute([$insightId]);
        }

        self::log('insight_seen', $insightId, ['user_id' => $userId]);
        return ['success' => true];
    }

    /**
     * Dismiss insight by user.
     */
    public static function dismiss(PDO $db, int $insightId, int $userId): array
    {
        $stmt = $db->prepare("
            UPDATE ai_insight_recipients SET status = 'dismissed', dismissed_at = NOW()
            WHERE insight_id = ? AND user_id = ?
        ");
        $stmt->execute([$insightId, $userId]);

        // Check if all recipients dismissed
        $active = $db->prepare("SELECT COUNT(*) FROM ai_insight_recipients WHERE insight_id = ? AND status NOT IN ('dismissed')");
        $active->execute([$insightId]);
        if ((int) $active->fetchColumn() === 0) {
            $db->prepare("UPDATE ai_insight_records SET status = 'dismissed' WHERE id = ?")->execute([$insightId]);
        }

        self::log('insight_dismissed', $insightId, ['user_id' => $userId]);
        return ['success' => true];
    }

    /**
     * Submit feedback on an insight.
     */
    public static function submitFeedback(PDO $db, int $insightId, int $userId, string $feedbackType, ?string $reason = null): array
    {
        if (!in_array($feedbackType, ['helpful', 'not_helpful', 'dismiss'])) {
            return ['success' => false, 'error' => 'Invalid feedback type.'];
        }

        $db->prepare("
            INSERT INTO ai_insight_feedback (insight_id, user_id, feedback_type, reason, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ")->execute([$insightId, $userId, $feedbackType, $reason]);

        if ($feedbackType === 'dismiss') {
            self::dismiss($db, $insightId, $userId);
        }

        self::log('insight_feedback', $insightId, ['user_id' => $userId, 'type' => $feedbackType]);
        return ['success' => true];
    }

    /**
     * Get a single insight with full details.
     */
    public static function getInsight(PDO $db, int $insightId, ?int $userId = null): ?array
    {
        $stmt = $db->prepare("
            SELECT ir.*, idk.definition_key, idk.name as def_name, idk.module, idk.navigation_config,
                   idk.description as def_description
            FROM ai_insight_records ir
            JOIN ai_insight_definitions idk ON ir.definition_id = idk.id
            WHERE ir.id = ?
        ");
        $stmt->execute([$insightId]);
        $insight = $stmt->fetch();
        if (!$insight) return null;

        if ($userId) {
            $recStmt = $db->prepare("SELECT * FROM ai_insight_recipients WHERE insight_id = ? AND user_id = ?");
            $recStmt->execute([$insightId, $userId]);
            $insight['recipient'] = $recStmt->fetch();
        }

        // Get feedback
        $fbStmt = $db->prepare("SELECT * FROM ai_insight_feedback WHERE insight_id = ? ORDER BY created_at DESC");
        $fbStmt->execute([$insightId]);
        $insight['feedback'] = $fbStmt->fetchAll();

        return $insight;
    }

    // ═══════════════════════════════════════════════════════
    // PREFERENCES
    // ═══════════════════════════════════════════════════════

    /**
     * Get or create user preferences.
     */
    public static function getPreferences(PDO $db, int $userId, ?string $module = null): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_insight_preferences WHERE user_id = ? AND " . ($module ? "module = ?" : "module IS NULL"));
        $params = $module ? [$userId, $module] : [$userId];
        $stmt->execute($params);
        $prefs = $stmt->fetch();

        if (!$prefs) {
            return [
                'insights_enabled' => true,
                'info_enabled' => true,
                'notice_enabled' => true,
                'warning_enabled' => true,
                'critical_enabled' => true,
                'notification_frequency' => 'immediate',
            ];
        }
        return $prefs;
    }

    /**
     * Update user preferences.
     */
    public static function updatePreferences(PDO $db, int $userId, ?string $module, array $updates): array
    {
        $existing = $db->prepare("SELECT id FROM ai_insight_preferences WHERE user_id = ? AND " . ($module ? "module = ?" : "module IS NULL"));
        $params = $module ? [$userId, $module] : [$userId];
        $existing->execute($params);
        $row = $existing->fetch();

        $fields = [];
        $values = [];
        foreach (['insights_enabled', 'info_enabled', 'notice_enabled', 'warning_enabled', 'critical_enabled'] as $field) {
            if (isset($updates[$field])) {
                $fields[] = "{$field} = ?";
                $values[] = $updates[$field] ? 1 : 0;
            }
        }
        if (isset($updates['notification_frequency'])) {
            $fields[] = "notification_frequency = ?";
            $values[] = $updates['notification_frequency'];
        }

        if (empty($fields)) return ['success' => false, 'error' => 'No fields to update.'];

        if ($row) {
            $values[] = $row['id'];
            $db->prepare("UPDATE ai_insight_preferences SET " . implode(', ', $fields) . " WHERE id = ?")->execute($values);
        } else {
            // Build column names (strip ' = ?' from field definitions)
            $columnNames = array_map(fn($f) => str_replace(' = ?', '', $f), $fields);
            $columnNames[] = 'user_id';
            $values[] = $userId;
            $columnNames[] = 'module';
            $values[] = $module;
            $colStr = implode(', ', $columnNames);
            $placeholders = implode(', ', array_fill(0, count($columnNames), '?'));
            $db->prepare("INSERT INTO ai_insight_preferences ({$colStr}) VALUES ({$placeholders})")->execute($values);
        }

        return ['success' => true];
    }

    // ═══════════════════════════════════════════════════════
    // DASHBOARD DATA
    // ═══════════════════════════════════════════════════════

    /**
     * Get dashboard widget data for a user.
     */
    public static function getDashboardData(PDO $db, int $userId, int $limit = 5): array
    {
        $counts = self::getUserInsightCounts($db, $userId);
        $recentInsights = self::getUserInsights($db, $userId, ['limit' => $limit]);
        $resolved = $db->prepare("
            SELECT COUNT(*) FROM ai_insight_records ir
            JOIN ai_insight_recipients rec ON ir.id = rec.insight_id
            WHERE rec.user_id = ? AND ir.status = 'resolved' AND ir.resolved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $resolved->execute([$userId]);

        return [
            'counts' => $counts,
            'recent' => $recentInsights,
            'resolved_count' => (int) $resolved->fetchColumn(),
        ];
    }

    // ═══════════════════════════════════════════════════════
    // HISTORY
    // ═══════════════════════════════════════════════════════

    /**
     * Get insight history (all statuses).
     */
    public static function getHistory(PDO $db, int $userId, int $limit = 50): array
    {
        $stmt = $db->prepare("SELECT ir.*, idk.name as def_name, idk.module, rec.status as recipient_status FROM ai_insight_records ir JOIN ai_insight_recipients rec ON ir.id = rec.insight_id JOIN ai_insight_definitions idk ON ir.definition_id = idk.id WHERE rec.user_id = ? ORDER BY ir.created_at DESC LIMIT " . (int) $limit);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    // ═══════════════════════════════════════════════════════
    // AUDIT
    // ═══════════════════════════════════════════════════════

    private static function log(string $event, int $insightId, ?array $extra = null): void
    {
        try {
            $db = getDB();
            $details = array_merge(['insight_id' => $insightId, 'event' => $event], $extra ?? []);
            $userId = $_SESSION['user_id'] ?? 0;
            $db->prepare("
                INSERT INTO ai_audit_log (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, ?, 'insight', ?, ?, NOW())
            ")->execute([$userId, "f14_{$event}", $insightId, json_encode($details)]);
        } catch (\Throwable $e) {}
    }
}
