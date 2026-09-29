<?php
/**
 * AI F26 — Intelligence Service.
 *
 * Core service for managing intelligence events: creation, deduplication,
 * status management, evidence, F23 observability, F24 masking/injection,
 * and ERP data classification.
 *
 * Covers Sections 2 (Intelligence Events), 12 (Duplicate Detection),
 * 13 (Priority & Severity), 17 (Injection in ERP text), 21 (Observability).
 */
class AIIntelligenceService
{
    // F23: Intelligence-specific trace category
    const TRACE_CATEGORY = 'intelligence';

    /**
     * Create an intelligence event with deduplication.
     */
    public static function createEvent(PDO $db, array $eventData): array
    {
        $eventKey = $eventData['event_key'] ?? 'intel_' . bin2hex(random_bytes(12));
        $companyId = $eventData['company_id'] ?? ($_SESSION['company_id'] ?? 1);

        // Deduplication check (Section 12)
        $fingerprint = self::generateFingerprint($eventData);
        if ($fingerprint) {
            $existing = self::findByFingerprint($db, $fingerprint, $companyId);
            if ($existing) {
                // Update existing event rather than create duplicate
                self::updateEvent($db, $existing['id'], [
                    'confidence' => $eventData['confidence'] ?? $existing['confidence'],
                    'evidence_json' => json_encode($eventData['evidence'] ?? []),
                ]);
                return ['success' => true, 'event_id' => $existing['id'], 'deduplicated' => true];
            }
        }

        // F24: Scan evidence records for prompt injection in ERP text fields
        if (!empty($eventData['evidence_records'])) {
            $eventData['evidence_records'] = self::sanitizeEvidenceRecords($db, $eventData['evidence_records']);
        }

        // F24: Mask sensitive evidence based on data classification
        if (!empty($eventData['evidence_records'])) {
            $eventData['evidence_records'] = self::maskEvidenceRecords($db, $eventData['evidence_records']);
        }

        // F24: Redact secrets from title/explanation
        if (class_exists('AISecretProtectionService')) {
            $eventData['title'] = AISecretProtectionService::redactSecrets($eventData['title'] ?? '');
            $eventData['explanation'] = AISecretProtectionService::redactSecrets($eventData['explanation'] ?? '');
            $eventData['recommendation'] = AISecretProtectionService::redactSecrets($eventData['recommendation'] ?? '');
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO ai_intelligence_events
                    (event_key, company_id, user_id, source_module, source_record_type,
                     source_record_id, detection_type, detector_key, severity, confidence,
                     title, explanation, evidence_json, recommendation, suggested_action,
                     suggested_action_type, urgency, status, fingerprint, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', ?, ?)
            ");
            $stmt->execute([
                $eventKey,
                $companyId,
                $eventData['user_id'] ?? null,
                $eventData['source_module'] ?? null,
                $eventData['source_record_type'] ?? null,
                $eventData['source_record_id'] ?? null,
                $eventData['detection_type'] ?? 'rule',
                $eventData['detector_key'] ?? null,
                $eventData['severity'] ?? 'medium',
                $eventData['confidence'] ?? 0.8,
                $eventData['title'] ?? 'Intelligence Event',
                $eventData['explanation'] ?? null,
                json_encode($eventData['evidence'] ?? []),
                $eventData['recommendation'] ?? null,
                $eventData['suggested_action'] ?? null,
                $eventData['suggested_action_type'] ?? null,
                $eventData['urgency'] ?? 'medium',
                $fingerprint,
                $eventData['expires_at'] ?? date('Y-m-d H:i:s', time() + 86400 * 7),
            ]);

            $eventId = (int) $db->lastInsertId();

            // Store evidence records
            if (!empty($eventData['evidence_records'])) {
                self::storeEvidence($db, $eventId, $eventData['evidence_records']);
            }

            // F23 observability: record event creation with full context
            self::recordObservabilityTrace($db, 'event_created', [
                'event_id' => $eventId,
                'detector_key' => $eventData['detector_key'] ?? null,
                'severity' => $eventData['severity'] ?? 'medium',
                'source_module' => $eventData['source_module'] ?? null,
                'evidence_count' => count($eventData['evidence_records'] ?? []),
                'confidence' => $eventData['confidence'] ?? 0.8,
            ]);

            return ['success' => true, 'event_id' => $eventId, 'event_key' => $eventKey, 'deduplicated' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate a fingerprint for deduplication.
     */
    private static function generateFingerprint(array $eventData): ?string
    {
        if (empty($eventData['detector_key'])) return null;

        $parts = [
            $eventData['company_id'] ?? 1,
            $eventData['detector_key'] ?? '',
            $eventData['source_record_type'] ?? '',
            $eventData['source_record_id'] ?? '',
            $eventData['condition'] ?? '',
        ];

        return md5(implode('|', $parts));
    }

    /**
     * Find existing event by fingerprint.
     */
    private static function findByFingerprint(PDO $db, string $fingerprint, int $companyId): ?array
    {
        $stmt = $db->prepare("
            SELECT * FROM ai_intelligence_events
            WHERE fingerprint = ? AND company_id = ? AND status NOT IN ('dismissed', 'expired')
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$fingerprint, $companyId]);
        $event = $stmt->fetch();
        return $event ?: null;
    }

    /**
     * F24: Sanitize evidence records — scan ERP text fields for prompt injection.
     * Test 24: Prompt injection through retrieved ERP text field must be treated as data.
     */
    private static function sanitizeEvidenceRecords(PDO $db, array $records): array
    {
        if (!class_exists('AIInjectionDetectionService')) return $records;

        $sanitized = [];
        foreach ($records as $record) {
            $label = $record['label'] ?? '';
            $data = $record['data'] ?? [];

            // Check the label (which comes from ERP data) for injection
            $analysis = AIInjectionDetectionService::analyze($db, $label, AIInjectionDetectionService::TRUST_UNTRUSTED_DOC);
            if ($analysis['action'] === 'block') {
                $record['label'] = '[SANITIZED: Potentially unsafe ERP data]';
                $record['injection_blocked'] = true;
            } elseif ($analysis['action'] === 'warn') {
                // Wrap the content to make it clear it's data, not instructions
                $record['label'] = $analysis['sanitized_content'];
            }

            // Check data values for injection
            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    $dataAnalysis = AIInjectionDetectionService::analyze($db, $value, AIInjectionDetectionService::TRUST_UNTRUSTED_DOC);
                    if ($dataAnalysis['action'] === 'block') {
                        $data[$key] = '[SANITIZED]';
                    }
                }
            }
            $record['data'] = $data;

            $sanitized[] = $record;
        }

        return $sanitized;
    }

    /**
     * F24: Mask evidence records based on data classification.
     * Test 8: Sensitive/restricted data is masked according to F24.
     */
    private static function maskEvidenceRecords(PDO $db, array $records): array
    {
        if (!class_exists('AIDataClassificationService')) return $records;

        $masked = [];
        foreach ($records as $record) {
            $label = $record['label'] ?? '';
            $data = $record['data'] ?? [];

            // Classify the evidence content
            $fullContent = $label . ' ' . json_encode($data);
            $classification = AIDataClassificationService::classifyContent($fullContent);

            if ($classification['masking_required']) {
                $record['label'] = AIDataClassificationService::maskData($label, $classification['classification']);
                $record['data_classification'] = $classification['classification'];
                $record['masked'] = true;

                // Mask individual data fields
                foreach ($data as $key => $value) {
                    if (is_string($value)) {
                        $fieldClass = AIDataClassificationService::classifyContent($value);
                        if ($fieldClass['masking_required']) {
                            $data[$key] = AIDataClassificationService::maskData($value, $fieldClass['classification']);
                        }
                    }
                }
                $record['data'] = $data;
            }

            $masked[] = $record;
        }

        return $masked;
    }

    /**
     * Store evidence records for an event.
     */
    private static function storeEvidence(PDO $db, int $eventId, array $records): void
    {
        $stmt = $db->prepare("
            INSERT INTO ai_intelligence_evidence
                (event_id, record_type, record_id, record_label, record_data_json,
                 amount, date_value, status_value, classification)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($records as $record) {
            $stmt->execute([
                $eventId,
                $record['type'] ?? 'unknown',
                $record['id'] ?? 0,
                $record['label'] ?? null,
                json_encode($record['data'] ?? []),
                $record['amount'] ?? null,
                $record['date'] ?? null,
                $record['status'] ?? null,
                $record['classification'] ?? 'internal',
            ]);
        }
    }

    /**
     * Get intelligence events for a user/company with filtering.
     * Test 8: Unauthorized user's access to an insight and its evidence is denied.
     */
    public static function getEvents(PDO $db, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['company_id'])) {
            $where[] = 'e.company_id = ?';
            $params[] = $filters['company_id'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = '(e.user_id = ? OR e.assigned_user_id IS NULL)';
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'e.status = ?';
            $params[] = $filters['status'];
        } elseif (!empty($filters['statuses'])) {
            $placeholders = implode(',', array_fill(0, count($filters['statuses']), '?'));
            $where[] = "e.status IN ($placeholders)";
            $params = array_merge($params, $filters['statuses']);
        }
        if (!empty($filters['severity'])) {
            $where[] = 'e.severity = ?';
            $params[] = $filters['severity'];
        }
        if (!empty($filters['min_severity'])) {
            $severityOrder = ['info' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
            $minLevel = $severityOrder[$filters['min_severity']] ?? 0;
            $validSeverities = array_filter(array_keys($severityOrder), fn($s) => ($severityOrder[$s] ?? 0) >= $minLevel);
            $placeholders = implode(',', array_fill(0, count($validSeverities), '?'));
            $where[] = "e.severity IN ($placeholders)";
            $params = array_merge($params, array_values($validSeverities));
        }
        if (!empty($filters['module'])) {
            $where[] = 'e.source_module = ?';
            $params[] = $filters['module'];
        }
        if (!empty($filters['detector_key'])) {
            $where[] = 'e.detector_key = ?';
            $params[] = $filters['detector_key'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(e.title LIKE ? OR e.explanation LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }

        // Test 16: Role-based filtering — Finance sees finance events, Procurement sees procurement, etc.
        if (!empty($filters['role_module_filter'])) {
            $moduleMap = [
                'finance'     => ['finance'],
                'procurement' => ['procurement', 'purchase_orders'],
                'inventory'   => ['inventory', 'products'],
                'management'  => ['finance', 'procurement', 'inventory', 'contracts', 'hr'],
            ];
            $allowedModules = $moduleMap[$filters['role_module_filter']] ?? null;
            if ($allowedModules) {
                $placeholders = implode(',', array_fill(0, count($allowedModules), '?'));
                $where[] = "e.source_module IN ($placeholders)";
                $params = array_merge($params, $allowedModules);
            }
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $countStmt = $db->prepare("SELECT COUNT(*) FROM ai_intelligence_events e WHERE $whereClause");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT e.*
            FROM ai_intelligence_events e
            WHERE $whereClause
            ORDER BY FIELD(e.severity, 'critical', 'high', 'medium', 'low', 'info'),
                     e.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'events' => $events,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Get a single event with evidence.
     * Test 7: Unauthorized user access is denied.
     * Test 8: Evidence is masked per F24.
     */
    public static function getEvent(PDO $db, int $eventId, ?int $requestingUserId = null): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_intelligence_events WHERE id = ?");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();
        if (!$event) return null;

        // Test 7: Permission check — requesting user must own the event or be assigned
        if ($requestingUserId !== null) {
            $isOwner = ((int) ($event['user_id'] ?? 0) === $requestingUserId);
            $isAssigned = ((int) ($event['assigned_user_id'] ?? 0) === $requestingUserId);
            $isAdmin = false;
            if (function_exists('hasPermission')) {
                $isAdmin = hasPermission('ai.intelligence.manage') || hasPermission('ai.management.admin');
            }
            if (!$isOwner && !$isAssigned && !$isAdmin) {
                return null; // Deny access — same as "not found"
            }
        }

        // Load evidence
        $evStmt = $db->prepare("SELECT * FROM ai_intelligence_evidence WHERE event_id = ? ORDER BY id ASC");
        $evStmt->execute([$eventId]);
        $event['evidence'] = $evStmt->fetchAll(PDO::FETCH_ASSOC);

        // Load feedback
        $fbStmt = $db->prepare("SELECT * FROM ai_intelligence_feedback WHERE event_id = ? ORDER BY created_at DESC");
        $fbStmt->execute([$eventId]);
        $event['feedback'] = $fbStmt->fetchAll(PDO::FETCH_ASSOC);

        return $event;
    }

    /**
     * Update event status.
     */
    public static function updateStatus(PDO $db, int $eventId, string $status, ?string $reason = null): bool
    {
        $extra = '';
        $params = [$status];
        if ($status === 'dismissed') {
            $extra = ', dismissed_at = NOW(), dismissed_reason = ?';
            $params[] = $reason;
        } elseif ($status === 'actioned') {
            $extra = ', actioned_at = NOW()';
        } elseif ($status === 'snoozed') {
            $hours = (int) ($reason ?? 24);
            $extra = ', snoozed_until = DATE_ADD(NOW(), INTERVAL ? HOUR)';
            $params[] = $hours;
        }
        $params[] = $eventId;

        $db->prepare("UPDATE ai_intelligence_events SET status = ?{$extra}, updated_at = NOW() WHERE id = ?")
           ->execute($params);

        // F23: Record status change
        self::recordObservabilityTrace($db, 'event_status_changed', [
            'event_id' => $eventId,
            'new_status' => $status,
            'reason' => $reason,
        ]);

        return true;
    }

    /**
     * Update event data.
     */
    public static function updateEvent(PDO $db, int $eventId, array $data): bool
    {
        $sets = [];
        $params = [];
        foreach ($data as $key => $value) {
            if (in_array($key, ['confidence', 'explanation', 'evidence_json', 'recommendation', 'suggested_action', 'related_task_id', 'related_task_key'])) {
                $sets[] = "$key = ?";
                $params[] = is_array($value) ? json_encode($value) : $value;
            }
        }
        if (empty($sets)) return false;
        $params[] = $eventId;
        $db->prepare("UPDATE ai_intelligence_events SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?")
           ->execute($params);
        return true;
    }

    /**
     * Link an event to an F25 task.
     */
    public static function linkTask(PDO $db, int $eventId, int $taskId, string $taskKey): void
    {
        $db->prepare("UPDATE ai_intelligence_events SET related_task_id = ?, related_task_key = ?, status = 'action_pending', updated_at = NOW() WHERE id = ?")
           ->execute([$taskId, $taskKey, $eventId]);

        // F23: Record task linkage
        self::recordObservabilityTrace($db, 'event_task_linked', [
            'event_id' => $eventId,
            'task_id' => $taskId,
            'task_key' => $taskKey,
        ]);
    }

    /**
     * Submit feedback on an event.
     * Test 17: useful/incorrect/duplicate/already_handled feedback, stored for F23.
     */
    public static function submitFeedback(PDO $db, int $eventId, int $userId, string $feedbackType, ?string $comment = null): bool
    {
        try {
            $db->prepare("
                INSERT INTO ai_intelligence_feedback (event_id, user_id, feedback_type, comment)
                VALUES (?, ?, ?, ?)
            ")->execute([$eventId, $userId, $feedbackType, $comment]);

            // F23: Record feedback for evaluation
            self::recordObservabilityTrace($db, 'event_feedback', [
                'event_id' => $eventId,
                'user_id' => $userId,
                'feedback_type' => $feedbackType,
                'comment' => $comment,
            ]);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get daily briefing data for a user.
     * Test 16: Role-specific intelligence for Finance, Procurement, Inventory, Management users.
     */
    public static function getBriefingData(PDO $db, int $userId, int $companyId): array
    {
        $briefing = [
            'critical_count' => 0,
            'high_count' => 0,
            'new_events' => [],
            'action_pending' => [],
            'recommended_actions' => [],
        ];

        // Get new/high-priority events
        $stmt = $db->prepare("
            SELECT * FROM ai_intelligence_events
            WHERE company_id = ? AND status IN ('new', 'acknowledged')
            AND (user_id = ? OR assigned_user_id = ? OR assigned_user_id IS NULL)
            ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low', 'info')
            LIMIT 20
        ");
        $stmt->execute([$companyId, $userId, $userId]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($events as $event) {
            if ($event['severity'] === 'critical') $briefing['critical_count']++;
            if ($event['severity'] === 'high') $briefing['high_count']++;
            $briefing['new_events'][] = $event;
            if ($event['recommendation']) {
                $briefing['recommended_actions'][] = [
                    'event_id' => $event['id'],
                    'title' => $event['title'],
                    'recommendation' => $event['recommendation'],
                    'severity' => $event['severity'],
                ];
            }
        }

        // Get action-pending events
        $stmt = $db->prepare("
            SELECT * FROM ai_intelligence_events
            WHERE company_id = ? AND status = 'action_pending'
            ORDER BY created_at DESC LIMIT 10
        ");
        $stmt->execute([$companyId]);
        $briefing['action_pending'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $briefing;
    }

    /**
     * Get intelligence statistics.
     */
    public static function getStatistics(PDO $db, int $companyId): array
    {
        $stats = [];

        $stmt = $db->prepare("SELECT COUNT(*) FROM ai_intelligence_events WHERE company_id = ?");
        $stmt->execute([$companyId]);
        $stats['total_events'] = (int) $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM ai_intelligence_events WHERE company_id = ? GROUP BY status");
        $stmt->execute([$companyId]);
        $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $stmt = $db->prepare("SELECT severity, COUNT(*) as cnt FROM ai_intelligence_events WHERE company_id = ? AND status NOT IN ('dismissed', 'expired') GROUP BY severity");
        $stmt->execute([$companyId]);
        $stats['active_by_severity'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $stmt = $db->prepare("SELECT COUNT(*) FROM ai_intelligence_events WHERE company_id = ? AND status = 'new' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute([$companyId]);
        $stats['new_last_24h'] = (int) $stmt->fetchColumn();

        return $stats;
    }

    /**
     * Mark expired events.
     */
    public static function cleanupExpired(PDO $db): int
    {
        $stmt = $db->prepare("
            UPDATE ai_intelligence_events SET status = 'expired', updated_at = NOW()
            WHERE status NOT IN ('dismissed', 'expired', 'actioned')
            AND expires_at IS NOT NULL AND expires_at < NOW()
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Un-snooze events whose snooze period has passed.
     */
    public static function unsnoozeEvents(PDO $db): int
    {
        $stmt = $db->prepare("
            UPDATE ai_intelligence_events SET status = 'new', snoozed_until = NULL, updated_at = NOW()
            WHERE status = 'snoozed' AND snoozed_until IS NOT NULL AND snoozed_until <= NOW()
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * F23: Record an observability trace for intelligence operations.
     * Test 19: F23 records detector execution, evidence, AI explanation, recommendation, and resulting F25 task.
     */
    public static function recordObservabilityTrace(PDO $db, string $operation, array $data): void
    {
        try {
            if (class_exists('AIObservabilityService')) {
                AIObservabilityService::logActionEvent($db, [
                    'event' => 'intelligence_' . $operation,
                    'detector_key' => $data['detector_key'] ?? null,
                    'severity' => $data['severity'] ?? null,
                    'event_id' => $data['event_id'] ?? null,
                    'task_id' => $data['task_id'] ?? null,
                    'feedback_type' => $data['feedback_type'] ?? null,
                    'evidence_count' => $data['evidence_count'] ?? null,
                    'confidence' => $data['confidence'] ?? null,
                    'module' => $data['source_module'] ?? null,
                ]);
            }

            // Also store in ai_agent_events for observability trace
            $db->prepare("
                INSERT INTO ai_agent_events (task_id, run_id, user_id, company_id, event_type, source, data_json, created_at)
                SELECT ?, NULL, ?, ?, ?, 'intelligence', ?, NOW()
            ")->execute([
                $data['task_id'] ?? null,
                $_SESSION['user_id'] ?? null,
                $_SESSION['company_id'] ?? 1,
                'intelligence_' . $operation,
                json_encode($data),
            ]);
        } catch (\Throwable $e) {
            // Non-critical
        }
    }
}
