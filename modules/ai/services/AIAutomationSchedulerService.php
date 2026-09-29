<?php
/**
 * AI F13 — Automation Scheduler & Event Trigger Service.
 *
 * Manages: scheduled triggers, event triggers, scheduler locking,
 * safety classification, notifications, retry, and concurrency.
 */
class AIAutomationSchedulerService
{
    // ── Limits ──
    const LOCK_DURATION_MINUTES = 30;
    const MAX_CONCURRENT_RUNS_GLOBAL = 10;
    const MAX_RUNS_PER_HOUR = 20;
    const DEFAULT_MAX_RUNTIME = 300;
    const DEFAULT_MAX_STEPS = 20;
    const DEFAULT_MAX_RETRIES = 3;
    const DEFAULT_RETRY_DELAY = 60;
    const MAX_RETRY_DELAY = 3600;

    // ═══════════════════════════════════════════════════════
    // TRIGGER MANAGEMENT
    // ═══════════════════════════════════════════════════════

    /**
     * Create or update a trigger for an automation.
     */
    public static function setTrigger(PDO $db, int $automationId, string $triggerType, array $config, int $userId): array
    {
        if (!in_array($triggerType, ['manual', 'scheduled', 'event'])) {
            return ['success' => false, 'error' => 'Invalid trigger type.'];
        }

        // Validate automation exists and user owns it
        $auto = self::getAutomation($db, $automationId, $userId);
        if (!$auto) return ['success' => false, 'error' => 'Automation not found.'];

        // Validate config based on type
        $validation = self::validateTriggerConfig($triggerType, $config);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => 'Invalid trigger config: ' . $validation['error']];
        }

        // Safety classification
        $safety = self::classifyWorkflow($db, $automationId);

        try {
            // Upsert trigger
            $existing = $db->prepare("SELECT id FROM ai_automation_triggers WHERE automation_id = ? AND trigger_type = ?");
            $existing->execute([$automationId, $triggerType]);
            $existingRow = $existing->fetch();

            if ($existingRow) {
                $db->prepare("
                    UPDATE ai_automation_triggers SET config_json = ?, enabled = 1, updated_at = NOW()
                    WHERE id = ?
                ")->execute([json_encode($config), $existingRow['id']]);
            } else {
                $db->prepare("
                    INSERT INTO ai_automation_triggers (automation_id, trigger_type, config_json, enabled, created_at)
                    VALUES (?, ?, ?, 1, NOW())
                ")->execute([$automationId, $triggerType, json_encode($config)]);
            }

            // Update automation trigger_type and safety classification
            $db->prepare("UPDATE ai_automations SET trigger_type = ?, safety_classification = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$triggerType, $safety, $automationId]);

            // Update automation schedule fields
            if ($triggerType === 'scheduled') {
                $nextRun = self::calculateNextRun($config);
                $sql = 'UPDATE ai_automations SET schedule_type = ?, schedule_config_json = ?, schedule_timezone = ?, next_run_at = ?, updated_at = NOW() WHERE id = ?';
                $db->prepare($sql)->execute([
                    $config['schedule_type'] ?? 'none',
                    json_encode($config),
                    $config['timezone'] ?? 'UTC',
                    $nextRun,
                    $automationId,
                ]);
            } elseif ($triggerType === 'event') {
                // Create/update event subscription
                $eventName = $config['event_name'] ?? '';
                $filter = $config['filter'] ?? [];

                $subExisting = $db->prepare("SELECT id FROM ai_event_subscriptions WHERE automation_id = ?");
                $subExisting->execute([$automationId]);
                if ($subExisting->fetch()) {
                    $db->prepare("
                        UPDATE ai_event_subscriptions SET event_name = ?, filter_json = ?, enabled = 1 WHERE automation_id = ?
                    ")->execute([$eventName, json_encode($filter), $automationId]);
                } else {
                    $db->prepare("
                        INSERT INTO ai_event_subscriptions (automation_id, event_name, filter_json, enabled)
                        VALUES (?, ?, ?, 1)
                    ")->execute([$automationId, $eventName, json_encode($filter)]);
                }

            }

            self::log('trigger_set', $automationId, $userId, ['type' => $triggerType]);
            return ['success' => true, 'safety_classification' => $safety];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to set trigger.'];
        }
    }

    /**
     * Enable/disable a trigger.
     */
    public static function toggleTrigger(PDO $db, int $automationId, string $triggerType, bool $enabled, int $userId): array
    {
        $stmt = $db->prepare("
            UPDATE ai_automation_triggers SET enabled = ?, updated_at = NOW()
            WHERE automation_id = ? AND trigger_type = ?
        ");
        $stmt->execute([$enabled ? 1 : 0, $automationId, $triggerType]);

        if ($triggerType === 'scheduled' && !$enabled) {
            $db->prepare("UPDATE ai_automations SET next_run_at = NULL WHERE id = ?")->execute([$automationId]);
        }

        // Also toggle event subscriptions for event triggers
        if ($triggerType === 'event') {
            $db->prepare("UPDATE ai_event_subscriptions SET enabled = ? WHERE automation_id = ?")
               ->execute([$enabled ? 1 : 0, $automationId]);
        }

        self::log($enabled ? 'trigger_enabled' : 'trigger_disabled', $automationId, $userId, ['type' => $triggerType]);
        return ['success' => true];
    }

    /**
     * Validate trigger configuration.
     */
    private static function validateTriggerConfig(string $type, array $config): array
    {
        return match($type) {
            'manual' => ['valid' => true],
            'scheduled' => self::validateScheduleConfig($config),
            'event' => self::validateEventConfig($config),
            default => ['valid' => false, 'error' => 'Unknown trigger type'],
        };
    }

    private static function validateScheduleConfig(array $config): array
    {
        $scheduleType = $config['schedule_type'] ?? '';
        if (!in_array($scheduleType, ['interval', 'daily', 'weekly'])) {
            return ['valid' => false, 'error' => 'Invalid schedule type. Use: interval, daily, weekly'];
        }

        if ($scheduleType === 'interval') {
            $minutes = (int) ($config['interval_minutes'] ?? 0);
            if ($minutes < 5 || $minutes > 1440) {
                return ['valid' => false, 'error' => 'Interval must be 5-1440 minutes.'];
            }
        }

        if (in_array($scheduleType, ['daily', 'weekly'])) {
            if (empty($config['time'])) {
                return ['valid' => false, 'error' => 'Time is required for daily/weekly schedules.'];
            }
            if (!preg_match('/^\d{2}:\d{2}$/', $config['time'])) {
                return ['valid' => false, 'error' => 'Time must be in HH:MM format.'];
            }
            // Validate hour (00-23) and minute (00-59)
            $parts = explode(':', $config['time']);
            $hour = (int) $parts[0];
            $minute = (int) $parts[1];
            if ($hour > 23 || $minute > 59) {
                return ['valid' => false, 'error' => 'Invalid time: hour must be 00-23, minute must be 00-59.'];
            }
        }

        if ($scheduleType === 'weekly' && empty($config['day_of_week'])) {
            return ['valid' => false, 'error' => 'Day of week is required for weekly schedules.'];
        }

        // Validate timezone
        $tz = $config['timezone'] ?? 'UTC';
        if (!in_array($tz, DateTimeZone::listIdentifiers())) {
            return ['valid' => false, 'error' => 'Invalid timezone: ' . $tz];
        }

        return ['valid' => true];
    }

    private static function validateEventConfig(array $config): array
    {
        $eventName = $config['event_name'] ?? '';
        if (empty($eventName)) {
            return ['valid' => false, 'error' => 'Event name is required.'];
        }

        if (!preg_match('/^[a-z][a-z0-9_.]+$/', $eventName)) {
            return ['valid' => false, 'error' => 'Invalid event name format.'];
        }

        return ['valid' => true];
    }

    // ═══════════════════════════════════════════════════════
    // SCHEDULE CALCULATOR
    // ═══════════════════════════════════════════════════════

    /**
     * Calculate next run time from schedule config.
     */
    public static function calculateNextRun(array $config, ?string $lastRunAt = null): ?string
    {
        $timezone = $config['timezone'] ?? 'UTC';
        try {
            $tz = new DateTimeZone($timezone);
        } catch (\Throwable $e) {
            $tz = new DateTimeZone('UTC');
        }

        $now = new DateTime('now', $tz);
        $baseTime = $lastRunAt ? new DateTime($lastRunAt, $tz) : $now;

        $scheduleType = $config['schedule_type'] ?? '';

        switch ($scheduleType) {
            case 'interval':
                $minutes = (int) ($config['interval_minutes'] ?? 60);
                $next = clone $baseTime;
                if (!$lastRunAt) {
                    // First run: start from now + interval
                    $next->modify("+{$minutes} minutes");
                } else {
                    $next->modify("+{$minutes} minutes");
                }
                // Ensure next run is in the future
                if ($next <= $now) {
                    $next = clone $now;
                    $next->modify("+{$minutes} minutes");
                }
                return $next->format('Y-m-d H:i:s');

            case 'daily':
                $time = $config['time'] ?? '09:00';
                $next = clone $now;
                $next->setTime((int) substr($time, 0, 2), (int) substr($time, 3, 2));
                if ($next <= $now) {
                    $next->modify('+1 day');
                }
                return $next->format('Y-m-d H:i:s');

            case 'weekly':
                $dayMap = [
                    'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                    'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 0,
                ];
                $day = $dayMap[strtolower($config['day_of_week'] ?? '')] ?? 1;
                $time = $config['time'] ?? '09:00';
                $next = clone $now;
                $next->setTime((int) substr($time, 0, 2), (int) substr($time, 3, 2));
                // Find next occurrence of this day
                $currentDay = (int) $now->format('w');
                $daysUntil = ($day - $currentDay + 7) % 7;
                if ($daysUntil === 0 && $next <= $now) {
                    $daysUntil = 7;
                }
                $next->modify("+{$daysUntil} days");
                return $next->format('Y-m-d H:i:s');

            default:
                return null;
        }
    }

    // ═══════════════════════════════════════════════════════
    // SAFETY CLASSIFICATION
    // ═══════════════════════════════════════════════════════

    /**
     * Classify an automation workflow safety level.
     */
    public static function classifyWorkflow(PDO $db, int $automationId): string
    {
        $auto = $db->prepare("SELECT workflow_json FROM ai_automations WHERE id = ?");
        $auto->execute([$automationId]);
        $row = $auto->fetch();
        if (!$row) return 'blocked';

        $workflow = json_decode($row['workflow_json'] ?? '{}', true);
        $steps = $workflow['steps'] ?? [];

        foreach ($steps as $step) {
            $type = $step['type'] ?? '';
            if ($type === 'action') {
                return 'confirmation_required';
            }
        }

        return 'read_only';
    }

    // ═══════════════════════════════════════════════════════
    // SCHEDULER LOCKING
    // ═══════════════════════════════════════════════════════

    /**
     * Acquire an execution lock for an automation.
     */
    public static function acquireLock(PDO $db, int $automationId, string $lockKey): array
    {
        // Clean expired locks
        $db->prepare("DELETE FROM ai_scheduler_locks WHERE expires_at < NOW()")->execute();

        // Try to acquire
        try {
            $db->prepare("
                INSERT INTO ai_scheduler_locks (automation_id, lock_key, locked_by, locked_at, expires_at)
                VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE))
            ")->execute([$automationId, $lockKey, gethostname() ?: 'unknown', self::LOCK_DURATION_MINUTES]);

            return ['success' => true];
        } catch (\Throwable $e) {
            // Lock already held — check if expired
            $existing = $db->prepare("
                SELECT id, expires_at FROM ai_scheduler_locks WHERE lock_key = ?
            ");
            $existing->execute([$lockKey]);
            $lock = $existing->fetch();

            if ($lock && strtotime($lock['expires_at']) < time()) {
                // Stale lock — force release
                $db->prepare("DELETE FROM ai_scheduler_locks WHERE id = ?")->execute([$lock['id']]);
                return self::acquireLock($db, $automationId, $lockKey);
            }

            return ['success' => false, 'error' => 'Lock already held.'];
        }
    }

    /**
     * Release an execution lock.
     */
    public static function releaseLock(PDO $db, string $lockKey): void
    {
        $db->prepare("DELETE FROM ai_scheduler_locks WHERE lock_key = ?")->execute([$lockKey]);
    }

    // ═══════════════════════════════════════════════════════
    // CONCURRENCY CONTROLS
    // ═══════════════════════════════════════════════════════

    /**
     * Check if concurrency limits allow a new run.
     */
    public static function canRun(PDO $db, int $automationId): array
    {
        // Global limit
        $stmt = $db->prepare("SELECT COUNT(*) FROM ai_automation_runs WHERE status IN ('running','queued')");
        $stmt->execute();
        $globalCount = (int) $stmt->fetchColumn();
        if ($globalCount >= self::MAX_CONCURRENT_RUNS_GLOBAL) {
            return ['ok' => false, 'reason' => 'Global concurrency limit reached.'];
        }

        // Per-automation limit
        $auto = $db->prepare("SELECT max_concurrent_runs FROM ai_automations WHERE id = ?");
        $auto->execute([$automationId]);
        $maxPerAuto = (int) ($auto->fetchColumn() ?: 1);

        $perAuto = $db->prepare("SELECT COUNT(*) FROM ai_automation_runs WHERE automation_id = ? AND status IN ('running','queued')");
        $perAuto->execute([$automationId]);
        if ((int) $perAuto->fetchColumn() >= $maxPerAuto) {
            return ['ok' => false, 'reason' => 'Automation concurrency limit reached.'];
        }

        // Hourly limit per user
        $hourly = $db->prepare("SELECT COUNT(*) FROM ai_automation_runs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $hourly->execute();
        if ((int) $hourly->fetchColumn() >= self::MAX_RUNS_PER_HOUR) {
            return ['ok' => false, 'reason' => 'Hourly run limit reached.'];
        }

        return ['ok' => true];
    }

    // ═══════════════════════════════════════════════════════
    // EVENT BUS
    // ═══════════════════════════════════════════════════════

    /**
     * Register an ERP event.
     */
    public static function registerEvent(PDO $db, string $eventName, string $module, ?string $description = null, ?array $payloadSchema = null): array
    {
        try {
            $db->prepare("
                INSERT INTO ai_event_registry (event_name, module, description, payload_schema, enabled)
                VALUES (?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE description = VALUES(description), payload_schema = VALUES(payload_schema)
            ")->execute([$eventName, $module, $description, $payloadSchema ? json_encode($payloadSchema) : null]);
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to register event.'];
        }
    }

    /**
     * Emit an ERP event — triggers matching automations.
     */
    public static function emitEvent(PDO $db, string $eventName, array $payload, ?int $userId = null): array
    {
        // Create event hash for deduplication
        $eventHash = hash('sha256', $eventName . json_encode($payload) . ($payload['record_id'] ?? ''));

        // Check for duplicate
        $dup = $db->prepare("SELECT id FROM ai_event_log WHERE event_hash = ?");
        $dup->execute([$eventHash]);
        if ($dup->fetch()) {
            return ['success' => true, 'duplicate' => true, 'message' => 'Duplicate event ignored.'];
        }

        // Log event
        try {
            $db->prepare("
                INSERT INTO ai_event_log (event_name, record_id, record_type, payload_json, event_hash, processed, created_at)
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ")->execute([
                $eventName,
                $payload['record_id'] ?? null,
                $payload['record_type'] ?? null,
                json_encode($payload),
                $eventHash,
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to log event.'];
        }

        // Find matching subscriptions
        $subs = $db->prepare("
            SELECT es.*, a.is_active, a.created_by
            FROM ai_event_subscriptions es
            JOIN ai_automations a ON es.automation_id = a.id
            WHERE es.event_name = ? AND es.enabled = 1 AND a.is_active = 1
        ");
        $subs->execute([$eventName]);
        $subscriptions = $subs->fetchAll();

        $triggered = 0;
        foreach ($subscriptions as $sub) {
            // Check filter
            $filter = json_decode($sub['filter_json'] ?? '{}', true);
            if (!self::matchesFilter($payload, $filter)) continue;

            // Check concurrency
            $canRun = self::canRun($db, $sub['automation_id']);
            if (!$canRun['ok']) continue;

            // Create run
            $lockKey = "event_{$sub['automation_id']}_{$eventHash}";
            $lock = self::acquireLock($db, $sub['automation_id'], $lockKey);
            if (!$lock['success']) continue;

            try {
                $db->prepare("
                    INSERT INTO ai_automation_runs (automation_id, task_id, status, trigger_type, event_name, event_record_id, lock_key, started_at, triggered_by, created_at)
                    VALUES (?, NULL, 'queued', 'event', ?, ?, ?, NOW(), ?, NOW())
                ")->execute([
                    $sub['automation_id'],
                    $eventName,
                    $payload['record_id'] ?? null,
                    $lockKey,
                    $userId ?? $sub['created_by'],
                ]);
                $triggered++;
            } catch (\Throwable $e) {
                self::releaseLock($db, $lockKey);
            }
        }

        // Mark event as processed
        $db->prepare("UPDATE ai_event_log SET processed = 1 WHERE event_hash = ?")->execute([$eventHash]);

        self::log('event_emitted', 0, $userId, ['event' => $eventName, 'triggered' => $triggered]);
        return ['success' => true, 'triggered' => $triggered];
    }

    /**
     * Check if event payload matches subscription filter.
     */
    private static function matchesFilter(array $payload, array $filter): bool
    {
        if (empty($filter)) return true;

        foreach ($filter as $key => $expectedValue) {
            $actualValue = $payload[$key] ?? null;
            if ($actualValue != $expectedValue) return false;
        }

        return true;
    }

    /**
     * Get registered events.
     */
    public static function getRegisteredEvents(PDO $db, ?string $module = null): array
    {
        $sql = "SELECT * FROM ai_event_registry WHERE enabled = 1";
        $params = [];
        if ($module) {
            $sql .= " AND module = ?";
            $params[] = $module;
        }
        $sql .= " ORDER BY event_name";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ═══════════════════════════════════════════════════════
    // RETRY POLICY
    // ═══════════════════════════════════════════════════════

    /**
     * Check if a failed run should be retried.
     */
    public static function shouldRetry(PDO $db, int $runId): array
    {
        $run = $db->prepare("
            SELECT r.*, a.max_retries, a.retry_delay_seconds
            FROM ai_automation_runs r
            JOIN ai_automations a ON r.automation_id = a.id
            WHERE r.id = ?
        ");
        $run->execute([$runId]);
        $row = $run->fetch();
        if (!$row) return ['retry' => false];

        $maxRetries = (int) ($row['max_retries'] ?? self::DEFAULT_MAX_RETRIES);
        $currentAttempt = (int) ($row['retry_attempt'] ?? 0);

        // Don't retry completed or cancelled runs
        $status = $row['status'] ?? '';
        if (in_array($status, ['completed', 'cancelled'])) {
            return ['retry' => false, 'reason' => 'Run not in retryable state.'];
        }

        if ($currentAttempt >= $maxRetries) {
            return ['retry' => false, 'reason' => 'Max retries reached.'];
        }

        // Only retry read-only or transient failures
        $metadata = json_decode($row['metadata_json'] ?? '{}', true);
        $error = $metadata['error'] ?? '';

        // Don't retry write action failures or confirmation-paused
        if (str_contains($error, 'confirmation') || str_contains($error, 'write')) {
            return ['retry' => false, 'reason' => 'Non-retryable failure.'];
        }

        $delay = (int) ($row['retry_delay_seconds'] ?? self::DEFAULT_RETRY_DELAY);
        $delay = min($delay * pow(2, $currentAttempt), self::MAX_RETRY_DELAY); // Exponential backoff

        return [
            'retry' => true,
            'attempt' => $currentAttempt + 1,
            'delay' => $delay,
        ];
    }

    // ═══════════════════════════════════════════════════════
    // NOTIFICATIONS
    // ═══════════════════════════════════════════════════════

    /**
     * Create an in-app notification.
     */
    public static function notify(PDO $db, int $userId, string $title, string $message, ?int $automationId = null, ?int $runId = null): void
    {
        try {
            $db->prepare("
                INSERT INTO ai_automation_notifications (automation_id, run_id, user_id, notification_type, title, message, created_at)
                VALUES (?, ?, ?, 'in_app', ?, ?, NOW())
            ")->execute([$automationId, $runId, $userId, $title, $message]);
        } catch (\Throwable $e) {}
    }

    /**
     * Get unread notifications for a user.
     */
    public static function getNotifications(PDO $db, int $userId, int $limit = 20): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_automation_notifications WHERE user_id = ? AND read_at IS NULL ORDER BY created_at DESC LIMIT " . (int) $limit);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Mark notification as read.
     */
    public static function markRead(PDO $db, int $notificationId, int $userId): void
    {
        $db->prepare("
            UPDATE ai_automation_notifications SET read_at = NOW()
            WHERE id = ? AND user_id = ?
        ")->execute([$notificationId, $userId]);
    }

    // ═══════════════════════════════════════════════════════
    // SCHEDULER ENTRY POINT
    // ═══════════════════════════════════════════════════════

    /**
     * Process due scheduled automations (called by cron/CLI).
     *
     * This is the main scheduler loop — safe, single-instance, locked.
     */
    public static function processDueAutomations(PDO $db): array
    {
        $results = ['processed' => 0, 'skipped' => 0, 'failed' => 0];

        // Find automations due for execution
        $due = $db->prepare("
            SELECT a.id, a.name, a.created_by, a.safety_classification, a.schedule_config_json,
                   a.next_run_at, t.id as trigger_id
            FROM ai_automations a
            JOIN ai_automation_triggers t ON a.id = t.automation_id
            WHERE t.trigger_type = 'scheduled' AND t.enabled = 1
            AND a.is_active = 1
            AND a.next_run_at IS NOT NULL
            AND a.next_run_at <= NOW()
            ORDER BY a.next_run_at ASC
            LIMIT 50
        ");
        $due->execute();
        $automations = $due->fetchAll();

        foreach ($automations as $auto) {
            $automationId = (int) $auto['id'];
            $lockKey = "schedule_{$automationId}";

            // Acquire lock
            $lock = self::acquireLock($db, $automationId, $lockKey);
            if (!$lock['success']) {
                $results['skipped']++;
                continue;
            }

            // Check concurrency
            $canRun = self::canRun($db, $automationId);
            if (!$canRun['ok']) {
                self::releaseLock($db, $lockKey);
                $results['skipped']++;
                continue;
            }

            // Check owner permissions
            $ownerPerms = self::getOwnerPermissions($db, (int) $auto['created_by']);
            if (empty($ownerPerms)) {
                self::releaseLock($db, $lockKey);
                self::log('scheduler_skip', $automationId, (int) $auto['created_by'], ['reason' => 'owner_inactive']);
                $results['skipped']++;
                continue;
            }

            // Create run
            try {
                $db->prepare("
                    INSERT INTO ai_automation_runs (automation_id, task_id, status, trigger_type, lock_key, started_at, triggered_by, created_at)
                    VALUES (?, NULL, 'queued', 'scheduled', ?, NOW(), ?, NOW())
                ")->execute([$automationId, $lockKey, $auto['created_by']]);

                $runId = (int) $db->lastInsertId();

                // Calculate next run
                $config = json_decode($auto['schedule_config_json'] ?? '{}', true);
                $nextRun = self::calculateNextRun($config, $auto['next_run_at']);
                $db->prepare("UPDATE ai_automations SET next_run_at = ?, last_run_at = NOW() WHERE id = ?")
                   ->execute([$nextRun, $automationId]);

                self::log('scheduler_trigger', $automationId, (int) $auto['created_by'], ['run_id' => $runId]);
                $results['processed']++;
            } catch (\Throwable $e) {
                $results['failed']++;
            }

            self::releaseLock($db, $lockKey);
        }

        return $results;
    }

    // ═══════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════

    private static function getAutomation(PDO $db, int $id, int $userId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_automations WHERE id = ? AND created_by = ?");
        $stmt->execute([$id, $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    private static function getOwnerPermissions(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("
            SELECT DISTINCT CONCAT(p.module, '.', p.name) as perm
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(), 'perm');
    }

    private static function log(string $event, int $automationId, ?int $userId, ?array $extra = null): void
    {
        try {
            $db = getDB();
            $details = array_merge(['automation_id' => $automationId, 'event' => $event], $extra ?? []);
            $db->prepare("
                INSERT INTO ai_audit_log (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, ?, 'automation', ?, ?, NOW())
            ")->execute([$userId ?? 0, "f13_{$event}", $automationId, json_encode($details)]);
        } catch (\Throwable $e) {}
    }
}
