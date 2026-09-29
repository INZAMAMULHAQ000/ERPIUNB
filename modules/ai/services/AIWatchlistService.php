<?php
/**
 * AI F26 — Watchlist Service.
 *
 * Manages user watchlists for monitoring ERP entities and
 * admin-configured monitors for reusable detection rules.
 *
 * Covers Sections 9 (Watchlists) and 10 (Monitors).
 */
class AIWatchlistService
{
    /**
     * Create a watchlist entry.
     */
    public static function createWatchlist(PDO $db, int $userId, int $companyId, string $entityType, int $entityId, ?string $label = null, array $config = []): array
    {
        try {
            // Check for duplicate
            $stmt = $db->prepare("SELECT id FROM ai_intelligence_watchlists WHERE user_id = ? AND entity_type = ? AND entity_id = ?");
            $stmt->execute([$userId, $entityType, $entityId]);
            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'Already watching this entity'];
            }

            $stmt = $db->prepare("
                INSERT INTO ai_intelligence_watchlists
                    (company_id, user_id, entity_type, entity_id, entity_label, watch_config_json)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$companyId, $userId, $entityType, $entityId, $label, json_encode($config)]);

            return ['success' => true, 'id' => (int) $db->lastInsertId()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get user's watchlists.
     */
    public static function getUserWatchlists(PDO $db, int $userId, int $companyId): array
    {
        $stmt = $db->prepare("
            SELECT * FROM ai_intelligence_watchlists
            WHERE user_id = ? AND company_id = ? AND enabled = 1
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId, $companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Remove a watchlist entry.
     */
    public static function removeWatchlist(PDO $db, int $watchlistId, int $userId): bool
    {
        $db->prepare("UPDATE ai_intelligence_watchlists SET enabled = 0 WHERE id = ? AND user_id = ?")
           ->execute([$watchlistId, $userId]);
        return $db->rowCount() > 0;
    }

    /**
     * Get all admin-configured monitors.
     */
    public static function getMonitors(PDO $db, ?int $companyId = null): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_intelligence_monitors ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a monitor (admin only).
     */
    public static function createMonitor(PDO $db, array $data, int $userId): array
    {
        try {
            $stmt = $db->prepare("
                INSERT INTO ai_intelligence_monitors
                    (monitor_key, name, description, module, data_source, condition_type,
                     condition_config_json, threshold_value, frequency, severity,
                     notification_target, recommendation_template, enabled, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['monitor_key'], $data['name'], $data['description'] ?? null,
                $data['module'], $data['data_source'], $data['condition_type'],
                json_encode($data['condition_config'] ?? []), $data['threshold_value'] ?? null,
                $data['frequency'] ?? 'daily', $data['severity'] ?? 'medium',
                $data['notification_target'] ?? null, $data['recommendation_template'] ?? null,
                $data['enabled'] ?? 1, $userId,
            ]);

            return ['success' => true, 'id' => (int) $db->lastInsertId()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update a monitor.
     */
    public static function updateMonitor(PDO $db, int $monitorId, array $data): bool
    {
        $sets = [];
        $params = [];
        $allowed = ['name', 'description', 'condition_type', 'condition_config_json', 'threshold_value', 'frequency', 'severity', 'notification_target', 'recommendation_template', 'enabled'];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $sets[] = "$key = ?";
                $params[] = $key === 'condition_config_json' ? json_encode($value) : $value;
            }
        }
        if (empty($sets)) return false;

        $params[] = $monitorId;
        $db->prepare("UPDATE ai_intelligence_monitors SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?")
           ->execute($params);
        return true;
    }
}
