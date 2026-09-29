<?php
/**
 * AI Workspace Activity Service
 * 
 * F19: Multi-User Activity Timeline & Presence Awareness.
 * All methods are static and take $db as first parameter.
 */

class AIWorkspaceActivityService {

    /**
     * Get recent activity for a workspace.
     */
    public static function getRecent($db, $workspaceId, $limit = 20, $offset = 0) {
        $stmt = $db->prepare("SELECT a.*, u.name as actor_name
            FROM ai_workspace_activity a
            LEFT JOIN users u ON u.id = a.actor_user_id
            WHERE a.workspace_id = ?
            ORDER BY a.created_at DESC
            LIMIT ? OFFSET ?");
        $stmt->execute([(int)$workspaceId, (int)$limit, (int)$offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get activity for a specific action type.
     */
    public static function getByType($db, $workspaceId, $actionType, $limit = 20) {
        $stmt = $db->prepare("SELECT a.*, u.name as actor_name
            FROM ai_workspace_activity a
            LEFT JOIN users u ON u.id = a.actor_user_id
            WHERE a.workspace_id = ? AND a.action_type = ?
            ORDER BY a.created_at DESC
            LIMIT ?");
        $stmt->execute([(int)$workspaceId, $actionType, (int)$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get activity count by type for a workspace.
     */
    public static function getCountByType($db, $workspaceId) {
        $stmt = $db->prepare("SELECT action_type, COUNT(*) as count
            FROM ai_workspace_activity
            WHERE workspace_id = ?
            GROUP BY action_type
            ORDER BY count DESC");
        $stmt->execute([(int)$workspaceId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Get activity stats for a workspace.
     */
    public static function getStats($db, $workspaceId) {
        $stmt = $db->prepare("SELECT 
            COUNT(*) as total_events,
            COUNT(DISTINCT actor_user_id) as unique_actors,
            MIN(created_at) as first_event,
            MAX(created_at) as last_event
            FROM ai_workspace_activity
            WHERE workspace_id = ?");
        $stmt->execute([(int)$workspaceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get workspace analytics for management center.
     */
    public static function getAnalytics($db, $companyId) {
        $stmt = $db->prepare("SELECT
            (SELECT COUNT(*) FROM ai_workspaces WHERE company_id = ? AND status = 'active') as active_workspaces,
            (SELECT COUNT(*) FROM ai_workspaces WHERE company_id = ? AND status = 'archived') as archived_workspaces,
            (SELECT COUNT(*) FROM ai_workspace_members m JOIN ai_workspaces w ON w.workspace_id = m.workspace_id WHERE w.company_id = ?) as total_members,
            (SELECT COUNT(*) FROM ai_workspace_conversations c JOIN ai_workspaces w ON w.workspace_id = c.workspace_id WHERE w.company_id = ?) as shared_conversations,
            (SELECT COUNT(*) FROM ai_findings f JOIN ai_workspaces w ON w.workspace_id = f.workspace_id WHERE w.company_id = ?) as total_findings,
            (SELECT COUNT(*) FROM ai_findings f JOIN ai_workspaces w ON w.workspace_id = f.workspace_id WHERE w.company_id = ? AND f.status = 'draft') as pending_findings,
            (SELECT COUNT(*) FROM ai_research_sessions r JOIN ai_workspaces w ON w.workspace_id = r.workspace_id WHERE w.company_id = ?) as research_sessions,
            (SELECT COUNT(*) FROM ai_workspace_shared_agent_runs sar JOIN ai_workspaces w ON w.workspace_id = sar.workspace_id WHERE w.company_id = ?) as shared_agent_runs
        ");
        $stmt->execute([$companyId, $companyId, $companyId, $companyId, $companyId, $companyId, $companyId, $companyId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ── Presence (Lightweight) ────────────────────────────────

    /**
     * Update user presence in a workspace.
     */
    public static function updatePresence($db, $workspaceId, $userId, $status = 'active') {
        $valid = ['viewing','active_recently','editing'];
        if (!in_array($status, $valid)) $status = 'active_recently';

        try {
            $db->prepare("REPLACE INTO ai_workspace_presence (workspace_id, user_id, status, last_seen) VALUES (?, ?, ?, NOW())")
                ->execute([(int)$workspaceId, (int)$userId, $status]);
        } catch (PDOException $e) {
            // Table may not exist — presence is optional
        }
    }

    /**
     * Get active users in a workspace.
     */
    public static function getPresence($db, $workspaceId, $staleMinutes = 5) {
        try {
            $stmt = $db->prepare("SELECT p.*, u.name as username
                FROM ai_workspace_presence p
                LEFT JOIN users u ON u.id = p.user_id
                WHERE p.workspace_id = ? AND p.last_seen > DATE_SUB(NOW(), INTERVAL ? MINUTE)
                ORDER BY p.last_seen DESC");
            $stmt->execute([(int)$workspaceId, (int)$staleMinutes]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}
