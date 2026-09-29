<?php
/**
 * AI F33 — Knowledge Freshness & Quality Service
 * 
 * Freshness tracking, quality scoring, F26 proactive intelligence.
 * Sections 15-16, 24 of F33 specification.
 */

class AIKnowledgeFreshnessService {

    private $db;
    private $userId;
    private $companyId;

    public function __construct(PDO $db, int $userId) {
        $this->db = $db;
        $this->userId = $userId;
        $this->companyId = $_SESSION['company_id'] ?? 0;
    }

    /**
     * Check freshness of all knowledge sources and generate alerts.
     */
    public function checkFreshness(): array {
        $alerts = [];

        // 1. Expired documents
        $alerts = array_merge($alerts, $this->detectExpiredDocuments());

        // 2. Stale documents (not reviewed recently)
        $alerts = array_merge($alerts, $this->detectStaleDocuments());

        // 3. Documents approaching expiration
        $alerts = array_merge($alerts, $this->detectExpiringDocuments());

        // 4. Conflicting active versions
        $alerts = array_merge($alerts, $this->detectConflictingVersions());

        // 5. Missing reviews
        $alerts = array_merge($alerts, $this->detectMissingReviews());

        // Create F26 intelligence events for critical alerts
        foreach ($alerts as $alert) {
            if ($alert['severity'] === 'high' || $alert['severity'] === 'urgent') {
                $this->createIntelligenceEvent($alert);
            }
        }

        $this->logEvent('freshness_check', null, null, ['alerts_count' => count($alerts)]);

        return $alerts;
    }

    /**
     * Calculate quality score for a knowledge source.
     */
    public function calculateQuality(int $sourceId): array {
        $source = $this->getSource($sourceId);
        if (!$source) return ['error' => 'Source not found'];

        $score = 0;
        $maxScore = 100;
        $factors = [];

        // 1. Source authority (0-20)
        $authority = $source['source_authority'] ?? '';
        $authScore = match($authority) {
            'official' => 20,
            'approved' => 15,
            'reference' => 10,
            default => 5,
        };
        $score += $authScore;
        $factors[] = ['name' => 'Authority', 'score' => $authScore, 'max' => 20, 'detail' => $authority ?: 'unspecified'];

        // 2. Approval status (0-20)
        $version = $this->getCurrentVersion($sourceId);
        $approvalScore = match($version['approval_status'] ?? '') {
            'published' => 20,
            'approved' => 15,
            'review' => 10,
            'draft' => 5,
            default => 0,
        };
        $score += $approvalScore;
        $factors[] = ['name' => 'Approval', 'score' => $approvalScore, 'max' => 20, 'detail' => $version['approval_status'] ?? 'unknown'];

        // 3. Freshness (0-20)
        $freshnessScore = 20;
        if (!empty($source['expiration_date'])) {
            $daysUntilExpiry = (strtotime($source['expiration_date']) - time()) / 86400;
            if ($daysUntilExpiry < 0) $freshnessScore = 0;
            elseif ($daysUntilExpiry < 30) $freshnessScore = 5;
            elseif ($daysUntilExpiry < 90) $freshnessScore = 10;
            elseif ($daysUntilExpiry < 180) $freshnessScore = 15;
        }
        $score += $freshnessScore;
        $factors[] = ['name' => 'Freshness', 'score' => $freshnessScore, 'max' => 20, 'detail' => $source['expiration_date'] ?? 'no expiry'];

        // 4. Completeness (0-20) — chunk count, content length
        $chunkCount = $source['chunk_count'] ?? 0;
        $completenessScore = min(20, $chunkCount * 2);
        $score += $completenessScore;
        $factors[] = ['name' => 'Completeness', 'score' => $completenessScore, 'max' => 20, 'detail' => "{$chunkCount} chunks"];

        // 5. Conflicts (0-20)
        $conflictScore = 20;
        $conflicts = $this->detectConflictsForSource($sourceId);
        if (!empty($conflicts)) $conflictScore = max(0, 20 - (count($conflicts) * 10));
        $score += $conflictScore;
        $factors[] = ['name' => 'Conflicts', 'score' => $conflictScore, 'max' => 20, 'detail' => count($conflicts) . ' conflicts'];

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'percentage' => round(($score / $maxScore) * 100),
            'grade' => $this->getGrade($score, $maxScore),
            'factors' => $factors,
        ];
    }

    /**
     * Get freshness status for all sources in a collection.
     */
    public function getCollectionFreshness(int $collectionId): array {
        $stmt = $this->db->prepare("
            SELECT s.id, s.title, s.effective_date, s.expiration_date, s.review_due_date,
                   s.last_reviewed_at, s.source_authority, s.status,
                   v.approval_status, v.version_number
            FROM ai_knowledge_sources s
            JOIN ai_knowledge_collections c ON s.collection_id = c.id
            LEFT JOIN ai_knowledge_versions v ON s.current_version_id = v.id
            WHERE s.collection_id = ? AND c.company_id = ? AND s.status = 'indexed'
            ORDER BY s.expiration_date ASC
        ");
        $stmt->execute([$collectionId, $this->companyId]);
        $sources = $stmt->fetchAll();

        $fresh = [];
        $stale = [];
        $expired = [];
        $expiring = [];

        foreach ($sources as $source) {
            $status = $this->getSourceFreshnessStatus($source);
            switch ($status) {
                case 'expired': $expired[] = $source; break;
                case 'stale': $stale[] = $source; break;
                case 'expiring': $expiring[] = $source; break;
                default: $fresh[] = $source;
            }
        }

        return [
            'total' => count($sources),
            'fresh' => count($fresh),
            'expiring' => count($expiring),
            'stale' => count($stale),
            'expired' => count($expired),
            'expired_sources' => $expired,
            'expiring_sources' => $expiring,
            'stale_sources' => $stale,
        ];
    }

    // ============================================================
    // DETECTION METHODS
    // ============================================================

    private function detectExpiredDocuments(): array {
        $stmt = $this->db->prepare("
            SELECT s.id, s.title, s.expiration_date, s.collection_id, c.name as collection_name
            FROM ai_knowledge_sources s
            JOIN ai_knowledge_collections c ON s.collection_id = c.id
            WHERE s.status = 'indexed' 
            AND s.expiration_date IS NOT NULL 
            AND s.expiration_date < CURDATE()
            AND c.company_id = ?
        ");
        $stmt->execute([$this->companyId]);
        $rows = $stmt->fetchAll();

        return array_map(fn($r) => [
            'type' => 'expired',
            'severity' => 'high',
            'title' => "Knowledge expired: {$r['title']}",
            'description' => "Document expired on {$r['expiration_date']}",
            'source_id' => $r['id'],
            'collection_id' => $r['collection_id'],
            'evidence' => ['expiration_date' => $r['expiration_date']],
        ], $rows);
    }

    private function detectStaleDocuments(): array {
        // Check freshness rules
        $rules = $this->getFreshnessRules();

        $stale = [];
        foreach ($rules as $rule) {
            if (!$rule['max_age_days']) continue;

            $stmt = $this->db->prepare("
                SELECT s.id, s.title, s.created_at, s.collection_id, s.document_type,
                       DATEDIFF(CURDATE(), s.created_at) as age_days
                FROM ai_knowledge_sources s
                JOIN ai_knowledge_collections c ON s.collection_id = c.id
                WHERE c.company_id = ?
                AND s.status = 'indexed'
                AND DATEDIFF(CURDATE(), s.created_at) > ?
                AND (s.last_reviewed_at IS NULL OR DATEDIFF(CURDATE(), s.last_reviewed_at) > ?)
            ");
            $stmt->execute([$this->companyId, $rule['max_age_days'], $rule['review_interval_days'] ?? 365]);
            $rows = $stmt->fetchAll();

            foreach ($rows as $r) {
                $stale[] = [
                    'type' => 'stale',
                    'severity' => 'normal',
                    'title' => "Stale knowledge: {$r['title']}",
                    'description' => "Document is {$r['age_days']} days old without review",
                    'source_id' => $r['id'],
                    'collection_id' => $r['collection_id'],
                    'evidence' => ['age_days' => $r['age_days'], 'last_reviewed' => null],
                ];
            }
        }

        return $stale;
    }

    private function detectExpiringDocuments(): array {
        $stmt = $this->db->prepare("
            SELECT s.id, s.title, s.expiration_date, s.collection_id,
                   DATEDIFF(s.expiration_date, CURDATE()) as days_until_expiry
            FROM ai_knowledge_sources s
            JOIN ai_knowledge_collections c ON s.collection_id = c.id
            WHERE s.status = 'indexed'
            AND s.expiration_date IS NOT NULL
            AND s.expiration_date > CURDATE()
            AND c.company_id = ?
            AND DATEDIFF(s.expiration_date, CURDATE()) <= 30
        ");
        $stmt->execute([$this->companyId]);
        $rows = $stmt->fetchAll();

        return array_map(fn($r) => [
            'type' => 'expiring',
            'severity' => (int)$r['days_until_expiry'] <= 7 ? 'high' : 'normal',
            'title' => "Knowledge expiring soon: {$r['title']}",
            'description' => "Expires in {$r['days_until_expiry']} days ({$r['expiration_date']})",
            'source_id' => $r['id'],
            'collection_id' => $r['collection_id'],
            'evidence' => ['expiration_date' => $r['expiration_date'], 'days_remaining' => $r['days_until_expiry']],
        ], $rows);
    }

    private function detectConflictingVersions(): array {
        $stmt = $this->db->prepare("
            SELECT s1.id as s1_id, s1.title, s1.collection_id,
                   v1.id as v1_id, v1.version_number as v1_num, v1.effective_date as v1_eff,
                   v2.id as v2_id, v2.version_number as v2_num, v2.effective_date as v2_eff
            FROM ai_knowledge_sources s1
            JOIN ai_knowledge_versions v1 ON s1.id = v1.source_id AND v1.approval_status = 'published'
            JOIN ai_knowledge_versions v2 ON s1.id = v2.source_id AND v2.approval_status = 'published' AND v2.id != v1.id
            WHERE s1.collection_id IN (SELECT id FROM ai_knowledge_collections WHERE company_id = ?)
            AND v1.content_hash != v2.content_hash
            LIMIT 20
        ");
        $stmt->execute([$this->companyId]);
        $rows = $stmt->fetchAll();

        return array_map(fn($r) => [
            'type' => 'conflict',
            'severity' => 'high',
            'title' => "Conflicting versions: {$r['title']}",
            'description' => "Two published versions exist: v{$r['v1_num']} and v{$r['v2_num']}",
            'source_id' => $r['s1_id'],
            'collection_id' => $r['collection_id'],
            'evidence' => ['v1' => $r['v1_num'], 'v2' => $r['v2_num']],
        ], $rows);
    }

    private function detectMissingReviews(): array {
        $stmt = $this->db->prepare("
            SELECT s.id, s.title, s.collection_id, s.last_reviewed_at,
                   DATEDIFF(CURDATE(), COALESCE(s.last_reviewed_at, s.created_at)) as days_since_review
            FROM ai_knowledge_sources s
            JOIN ai_knowledge_collections c ON s.collection_id = c.id
            WHERE s.status = 'indexed'
            AND c.company_id = ?
            AND (s.last_reviewed_at IS NULL OR DATEDIFF(CURDATE(), s.last_reviewed_at) > 180)
        ");
        $stmt->execute([$this->companyId]);
        $rows = $stmt->fetchAll();

        return array_map(fn($r) => [
            'type' => 'missing_review',
            'severity' => 'normal',
            'title' => "Review overdue: {$r['title']}",
            'description' => "Last reviewed {$r['days_since_review']} days ago",
            'source_id' => $r['id'],
            'collection_id' => $r['collection_id'],
            'evidence' => ['days_since_review' => $r['days_since_review']],
        ], $rows);
    }

    private function getSourceFreshnessStatus(array $source): string {
        if (!empty($source['expiration_date']) && strtotime($source['expiration_date']) < time()) {
            return 'expired';
        }
        if (!empty($source['expiration_date'])) {
            $daysUntil = (strtotime($source['expiration_date']) - time()) / 86400;
            if ($daysUntil <= 30) return 'expiring';
        }
        if (empty($source['last_reviewed_at']) || (time() - strtotime($source['last_reviewed_at'])) > (180 * 86400)) {
            return 'stale';
        }
        return 'fresh';
    }

    // ============================================================
    // HELPER METHODS
    // ============================================================

    private function getSource(int $sourceId): ?array {
        $stmt = $this->db->prepare("
            SELECT s.*
            FROM ai_knowledge_sources s
            JOIN ai_knowledge_collections c ON s.collection_id = c.id
            WHERE s.id = ? AND c.company_id = ?
            LIMIT 1
        ");
        $stmt->execute([$sourceId, $this->companyId]);
        return $stmt->fetch() ?: null;
    }

    private function getCurrentVersion(int $sourceId): ?array {
        $stmt = $this->db->prepare("
            SELECT v.*
            FROM ai_knowledge_versions v
            JOIN ai_knowledge_sources s ON v.source_id = s.id
            JOIN ai_knowledge_collections c ON s.collection_id = c.id
            WHERE s.id = ? AND c.company_id = ?
            ORDER BY v.version_number DESC
            LIMIT 1
        ");
        $stmt->execute([$sourceId, $this->companyId]);
        return $stmt->fetch() ?: null;
    }

    private function detectConflictsForSource(int $sourceId): array {
        // Count published versions for this source
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as cnt
            FROM ai_knowledge_versions v
            JOIN ai_knowledge_sources s ON v.source_id = s.id
            JOIN ai_knowledge_collections c ON s.collection_id = c.id
            WHERE v.source_id = ? AND c.company_id = ? AND v.approval_status = 'published'
        ");
        $stmt->execute([$sourceId, $this->companyId]);
        $count = (int) $stmt->fetchColumn();

        if ($count <= 1) return [];

        // Return all published versions (they conflict)
        $stmt = $this->db->prepare("
            SELECT v.*
            FROM ai_knowledge_versions v
            JOIN ai_knowledge_sources s ON v.source_id = s.id
            JOIN ai_knowledge_collections c ON s.collection_id = c.id
            WHERE v.source_id = ? AND c.company_id = ? AND v.approval_status = 'published'
            ORDER BY version_number
        ");
        $stmt->execute([$sourceId, $this->companyId]);
        return $stmt->fetchAll();
    }

    private function getFreshnessRules(): array {
        $stmt = $this->db->prepare("SELECT * FROM ai_knowledge_freshness_rules WHERE company_id = ? AND enabled = 1");
        $stmt->execute([$this->companyId]);
        return $stmt->fetchAll();
    }

    private function getGrade(int $score, int $max): string {
        $pct = ($score / $max) * 100;
        if ($pct >= 90) return 'A';
        if ($pct >= 80) return 'B';
        if ($pct >= 70) return 'C';
        if ($pct >= 60) return 'D';
        return 'F';
    }

    private function createIntelligenceEvent(array $alert): void {
        if (!class_exists('AIIntelligenceService')) return;

        try {
            $intelService = new AIIntelligenceService($this->companyId, $this->userId);
            if (method_exists($intelService, 'createEvent')) {
                $intelService->createEvent([
                    'event_type' => 'knowledge_freshness_' . $alert['type'],
                    'title' => $alert['title'],
                    'severity' => $alert['severity'],
                    'entity_type' => 'knowledge_source',
                    'entity_id' => $alert['source_id'] ?? null,
                    'evidence' => $alert['evidence'] ?? [],
                    'source' => 'f33_freshness',
                ]);
            }
        } catch (\Throwable $e) {}
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
}
