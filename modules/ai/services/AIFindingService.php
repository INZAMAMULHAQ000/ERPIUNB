<?php
/**
 * AI Finding Service
 * 
 * F19: Shared AI Findings — create, review, evidence references, status lifecycle.
 * All methods are static and take $db as first parameter.
 */

class AIFindingService {

    // ── Finding CRUD ──────────────────────────────────────────

    /**
     * Create a new AI finding.
     */
    public static function create($db, $data) {
        $workspaceId  = (int)($data['workspace_id'] ?? 0);
        $title        = trim($data['title'] ?? '');
        $summary      = trim($data['summary'] ?? '');
        $confidence   = isset($data['confidence']) ? (float)$data['confidence'] : null;
        $sourceConvId = !empty($data['source_conversation_id']) ? (int)$data['source_conversation_id'] : null;
        $createdBy    = (int)($data['created_by_user_id'] ?? 0);
        $createdByAI  = (int)($data['created_by_ai'] ?? 0);

        if ($workspaceId <= 0 || $title === '' || $summary === '') {
            return ['success' => false, 'error' => 'Missing required fields'];
        }

        $stmt = $db->prepare("INSERT INTO ai_findings (workspace_id, title, summary, confidence, source_conversation_id, created_by_user_id, created_by_ai, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'draft')");
        $stmt->execute([$workspaceId, $title, $summary, $confidence, $sourceConvId, $createdBy ?: null, $createdByAI]);
        $findingId = (int)$db->lastInsertId();

        // Log activity
        AIWorkspaceService::logActivity($db, $workspaceId, $createdBy, 'finding_created', 'finding', $findingId, ['title' => $title, 'created_by_ai' => $createdByAI]);

        // Notify workspace members
        $members = $db->prepare("SELECT user_id FROM ai_workspace_members WHERE workspace_id = ? AND user_id != ?");
        $members->execute([$workspaceId, $createdBy]);
        while ($m = $members->fetch(PDO::FETCH_ASSOC)) {
            AIWorkspaceService::createNotification($db, $workspaceId, (int)$m['user_id'], 'finding_created', 'New AI Finding', "A new finding '$title' requires review.", 'finding', $findingId);
        }

        return ['success' => true, 'finding_id' => $findingId];
    }

    /**
     * Get a finding by ID.
     */
    public static function getById($db, $findingId) {
        $stmt = $db->prepare("SELECT f.*, u.name as creator_name, u2.name as reviewer_name
            FROM ai_findings f
            LEFT JOIN users u ON u.id = f.created_by_user_id
            LEFT JOIN users u2 ON u2.id = f.reviewed_by
            WHERE f.finding_id = ?");
        $stmt->execute([(int)$findingId]);
        $finding = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$finding) return null;

        // Get evidence
        $evStmt = $db->prepare("SELECT * FROM ai_finding_evidence WHERE finding_id = ? ORDER BY created_at");
        $evStmt->execute([(int)$findingId]);
        $finding['evidence'] = $evStmt->fetchAll(PDO::FETCH_ASSOC);

        return $finding;
    }

    /**
     * List findings for a workspace.
     */
    public static function listByWorkspace($db, $workspaceId, $status = null) {
        $sql = "SELECT f.*, u.name as creator_name
            FROM ai_findings f
            LEFT JOIN users u ON u.id = f.created_by_user_id
            WHERE f.workspace_id = ?";
        $params = [(int)$workspaceId];
        if ($status) {
            $sql .= " AND f.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY f.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Status Lifecycle ──────────────────────────────────────

    /**
     * Update finding status.
     */
    public static function updateStatus($db, $findingId, $newStatus, $reviewedBy = null) {
        $validStatuses = ['draft','reviewed','accepted','dismissed','archived'];
        if (!in_array($newStatus, $validStatuses)) {
            return ['success' => false, 'error' => 'Invalid status'];
        }

        $fields = ["status = ?"];
        $params = [$newStatus];

        if (in_array($newStatus, ['reviewed','accepted','dismissed']) && $reviewedBy) {
            $fields[] = "reviewed_by = ?";
            $params[] = (int)$reviewedBy;
        }

        $params[] = (int)$findingId;
        $db->prepare("UPDATE ai_findings SET " . implode(', ', $fields) . " WHERE finding_id = ?")->execute($params);

        // Log activity
        $finding = self::getById($db, $findingId);
        if ($finding) {
            AIWorkspaceService::logActivity($db, $finding['workspace_id'], $reviewedBy ?? 0, 'finding_reviewed', 'finding', $findingId, ['new_status' => $newStatus]);
        }

        return ['success' => true];
    }

    /**
     * Accept a finding.
     */
    public static function accept($db, $findingId, $userId) {
        return self::updateStatus($db, $findingId, 'accepted', $userId);
    }

    /**
     * Dismiss a finding.
     */
    public static function dismiss($db, $findingId, $userId) {
        return self::updateStatus($db, $findingId, 'dismissed', $userId);
    }

    /**
     * Archive a finding.
     */
    public static function archive($db, $findingId) {
        return self::updateStatus($db, $findingId, 'archived');
    }

    // ── Evidence Management ───────────────────────────────────

    /**
     * Add evidence reference to a finding.
     */
    public static function addEvidence($db, $findingId, $refType, $refId, $description = null) {
        $validTypes = ['erp_record','tool_result','knowledge_document','insight','conversation'];
        if (!in_array($refType, $validTypes)) {
            return ['success' => false, 'error' => 'Invalid evidence type'];
        }

        $stmt = $db->prepare("INSERT INTO ai_finding_evidence (finding_id, reference_type, reference_id, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([(int)$findingId, $refType, (int)$refId, $description]);
        return ['success' => true, 'evidence_id' => (int)$db->lastInsertId()];
    }

    /**
     * Get evidence for a finding with access revalidation.
     */
    public static function getEvidence($db, $findingId) {
        $stmt = $db->prepare("SELECT * FROM ai_finding_evidence WHERE finding_id = ? ORDER BY created_at");
        $stmt->execute([(int)$findingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark evidence as access-revalidated.
     */
    public static function revalidateEvidence($db, $evidenceId) {
        $db->prepare("UPDATE ai_finding_evidence SET access_revalidated = 1 WHERE evidence_id = ?")->execute([(int)$evidenceId]);
    }

    /**
     * Remove evidence from a finding.
     */
    public static function removeEvidence($db, $evidenceId) {
        $db->prepare("DELETE FROM ai_finding_evidence WHERE evidence_id = ?")->execute([(int)$evidenceId]);
        return ['success' => true];
    }

    // ── Analytics ─────────────────────────────────────────────

    /**
     * Get finding statistics for a workspace.
     */
    public static function getStats($db, $workspaceId) {
        $stmt = $db->prepare("SELECT 
            COUNT(*) as total,
            SUM(status = 'draft') as drafts,
            SUM(status = 'reviewed') as reviewed,
            SUM(status = 'accepted') as accepted,
            SUM(status = 'dismissed') as dismissed,
            SUM(status = 'archived') as archived,
            SUM(created_by_ai = 1) as ai_created,
            SUM(created_by_ai = 0) as human_created
            FROM ai_findings WHERE workspace_id = ?");
        $stmt->execute([(int)$workspaceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
