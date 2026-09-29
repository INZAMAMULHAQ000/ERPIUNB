<?php
/**
 * AI F33 — Knowledge Version Service
 * 
 * Version tracking, effective-date intelligence, supersession.
 * Sections 9-10 of F33 specification.
 */

class AIKnowledgeVersionService {

    private $db;
    private $userId;
    private $companyId;

    public function __construct(PDO $db, int $userId) {
        $this->db = $db;
        $this->userId = $userId;
        $this->companyId = $_SESSION['company_id'] ?? 1;
    }

    /**
     * Create a new version of a knowledge source.
     */
    public function createVersion(int $sourceId, string $content, array $options = []): array {
        $source = $this->getSource($sourceId);
        if (!$source) return ['success' => false, 'error' => 'Source not found'];

        // Get next version number
        $stmt = $this->db->prepare("SELECT MAX(version_number) as max_ver FROM ai_knowledge_versions WHERE source_id = ?");
        $stmt->execute([$sourceId]);
        $maxVer = (int) ($stmt->fetchColumn() ?: 0);
        $newVersion = $maxVer + 1;

        $contentHash = hash('sha256', $content);
        $effectiveDate = $options['effective_date'] ?? date('Y-m-d');
        $expirationDate = $options['expiration_date'] ?? null;
        $changeSummary = $options['change_summary'] ?? null;

        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_knowledge_versions 
                (source_id, version_number, title, content, content_hash, effective_date, expiration_date, 
                 approval_status, created_by, change_summary)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?)
            ");
            $stmt->execute([
                $sourceId, $newVersion, $source['title'], $content, $contentHash,
                $effectiveDate, $expirationDate, $this->userId, $changeSummary
            ]);

            $versionId = (int) $this->db->lastInsertId();

            // Update source's current version
            $this->db->prepare("UPDATE ai_knowledge_sources SET current_version_id = ? WHERE id = ?")->execute([$versionId, $sourceId]);

            $this->logEvent('version_create', 'version', $versionId, ['source_id' => $sourceId, 'version' => $newVersion]);

            return ['success' => true, 'version_id' => $versionId, 'version_number' => $newVersion];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to create version'];
        }
    }

    /**
     * Approve and publish a version.
     */
    public function approveVersion(int $versionId): array {
        $version = $this->getVersion($versionId);
        if (!$version) return ['success' => false, 'error' => 'Version not found'];

        if ($version['approval_status'] === 'published') {
            return ['success' => false, 'error' => 'Version already published'];
        }

        try {
            // Mark previous published version as superseded
            $this->db->prepare("
                UPDATE ai_knowledge_versions 
                SET approval_status = 'superseded', superseded_by = ?
                WHERE source_id = ? AND approval_status = 'published' AND id != ?
            ")->execute([$versionId, $version['source_id'], $versionId]);

            // Approve and publish new version
            $this->db->prepare("
                UPDATE ai_knowledge_versions 
                SET approval_status = 'published', approved_by = ?, approved_at = NOW(), published_at = NOW()
                WHERE id = ?
            ")->execute([$this->userId, $versionId]);

            // Update source effective dates
            $this->db->prepare("
                UPDATE ai_knowledge_sources 
                SET effective_date = ?, expiration_date = ?, current_version_id = ?
                WHERE id = ?
            ")->execute([$version['effective_date'], $version['expiration_date'], $versionId, $version['source_id']]);

            $this->logEvent('version_approve', 'version', $versionId, ['source_id' => $version['source_id']]);

            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to approve version'];
        }
    }

    /**
     * Get the currently effective version for a source.
     */
    public function getEffectiveVersion(int $sourceId): ?array {
        // Try published version first
        $stmt = $this->db->prepare("
            SELECT v.*
            FROM ai_knowledge_versions v
            JOIN ai_knowledge_sources s ON v.source_id = s.id
            JOIN ai_knowledge_collections c ON s.collection_id = c.id
            WHERE s.id = ? AND c.company_id = ? AND v.approval_status = 'published'
            AND (effective_date IS NULL OR effective_date <= CURDATE())
            AND (expiration_date IS NULL OR expiration_date >= CURDATE())
            ORDER BY version_number DESC LIMIT 1
        ");
        $stmt->execute([$sourceId, $this->companyId]);
        $version = $stmt->fetch();

        if ($version) return $version;

        // Fallback to latest version
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

    /**
     * Get a historical version by date.
     */
    public function getHistoricalVersion(int $sourceId, string $date): ?array {
        $stmt = $this->db->prepare("
            SELECT v.*
            FROM ai_knowledge_versions v
            JOIN ai_knowledge_sources s ON v.source_id = s.id
            JOIN ai_knowledge_collections c ON s.collection_id = c.id
            WHERE s.id = ? AND c.company_id = ?
            AND approval_status IN ('published', 'superseded')
            AND effective_date <= ?
            AND (expiration_date IS NULL OR expiration_date >= ?)
            ORDER BY v.version_number DESC LIMIT 1
        ");
        $stmt->execute([$sourceId, $this->companyId, $date, $date]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get all versions for a source.
     */
    public function getVersionHistory(int $sourceId): array {
        // Verify user has access to this source's collection
        $source = $this->getSource($sourceId);
        if (!$source) return [];
        $accessible = AIKnowledgeService::getAccessibleCollections($this->db, $this->userId);
        if (!in_array($source['collection_id'] ?? 0, $accessible)) {
            return []; // Access denied
        }

        $stmt = $this->db->prepare("
            SELECT v.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as created_by_name
            FROM ai_knowledge_versions v
            LEFT JOIN employees u ON v.created_by = u.id
            WHERE v.source_id = ? 
            ORDER BY v.version_number DESC
        ");
        $stmt->execute([$sourceId]);
        return $stmt->fetchAll();
    }

    /**
     * Detect version conflicts for a collection.
     */
    public function detectConflicts(int $collectionId): array {
        // Verify user has access to this collection
        $accessible = AIKnowledgeService::getAccessibleCollections($this->db, $this->userId);
        if (!in_array($collectionId, $accessible)) {
            return []; // Access denied — don't reveal collection existence
        }

        $stmt = $this->db->prepare("
            SELECT v1.id as v1_id, v1.source_id as s1_id, v1.version_number as v1_num, v1.title as v1_title,
                   v2.id as v2_id, v2.source_id as s2_id, v2.version_number as v2_num, v2.title as v2_title,
                   v1.effective_date as v1_eff, v2.effective_date as v2_eff,
                   s1.title as source1_title, s2.title as source2_title
            FROM ai_knowledge_versions v1
            JOIN ai_knowledge_sources s1 ON v1.source_id = s1.id
            JOIN ai_knowledge_versions v2 ON v1.source_id != v2.source_id
            JOIN ai_knowledge_sources s2 ON v2.source_id = s2.id
            WHERE s1.collection_id = ? AND s2.collection_id = ?
            AND v1.approval_status = 'published' AND v2.approval_status = 'published'
            AND v1.content_hash != v2.content_hash
            AND v1.title = v2.title
            AND v1.id < v2.id
            LIMIT 50
        ");
        $stmt->execute([$collectionId, $collectionId]);
        return $stmt->fetchAll();
    }

    /**
     * Get version-aware content for RAG retrieval.
     * Returns the effective version's content for a source.
     */
    public function getVersionAwareContent(int $sourceId, ?string $date = null): ?string {
        $version = $date 
            ? $this->getHistoricalVersion($sourceId, $date)
            : $this->getEffectiveVersion($sourceId);

        return $version ? $version['content'] : null;
    }

    /**
     * Check if a source's content has been superseded.
     */
    public function isSuperseded(int $sourceId): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM ai_knowledge_versions v
            JOIN ai_knowledge_sources s ON v.source_id = s.id
            JOIN ai_knowledge_collections c ON s.collection_id = c.id
            WHERE v.source_id = ? AND c.company_id = ? AND v.superseded_by IS NOT NULL AND v.approval_status = 'superseded'
        ");
        $stmt->execute([$sourceId, $this->companyId]);
        return (int) $stmt->fetchColumn() > 0;
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

    private function getVersion(int $versionId): ?array {
        $stmt = $this->db->prepare("
            SELECT v.*
            FROM ai_knowledge_versions v
            JOIN ai_knowledge_sources s ON v.source_id = s.id
            JOIN ai_knowledge_collections c ON s.collection_id = c.id
            WHERE v.id = ? AND c.company_id = ?
            LIMIT 1
        ");
        $stmt->execute([$versionId, $this->companyId]);
        return $stmt->fetch() ?: null;
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
