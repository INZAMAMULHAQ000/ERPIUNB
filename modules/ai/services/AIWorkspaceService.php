<?php
/**
 * AI Workspace Service
 * 
 * F19: Shared AI Workspaces — CRUD, roles, members, archiving, permissions.
 * All methods are static and take $db as first parameter.
 */

class AIWorkspaceService {

    // ── Workspace CRUD ────────────────────────────────────────

    /**
     * Create a new workspace.
     */
    public static function create($db, $data) {
        $companyId = (int)($data['company_id'] ?? 0);
        $ownerId   = (int)($data['owner_user_id'] ?? 0);
        $name      = trim($data['name'] ?? '');
        $desc      = trim($data['description'] ?? '');$vis = !empty($data['visibility']) && in_array($data['visibility'], ['private','restricted','company']) ? $data['visibility'] : 'restricted';

        if ($companyId <= 0 || $ownerId <= 0 || $name === '') {
            return ['success' => false, 'error' => 'Missing required fields'];
        }

        $stmt = $db->prepare("INSERT INTO ai_workspaces (company_id, owner_user_id, name, description, visibility, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$companyId, $ownerId, $name, $desc, $vis]);
        $wsId = (int)$db->lastInsertId();

        // Auto-add owner as owner member
        $db->prepare("INSERT INTO ai_workspace_members (workspace_id, user_id, role) VALUES (?, ?, 'owner')")->execute([$wsId, $ownerId]);

        // Log activity
        self::logActivity($db, $wsId, $ownerId, 'workspace_created', 'workspace', $wsId, ['name' => $name]);

        return ['success' => true, 'workspace_id' => $wsId];
    }

    /**
     * Get a workspace by ID (with company check).
     */
    public static function getById($db, $workspaceId, $companyId = null) {
        $sql = "SELECT * FROM ai_workspaces WHERE workspace_id = ?";
        $params = [(int)$workspaceId];
        if ($companyId !== null) {
            $sql .= " AND company_id = ?";
            $params[] = (int)$companyId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * List workspaces for a company.
     */
    public static function listByCompany($db, $companyId, $status = 'active') {
        $stmt = $db->prepare("SELECT w.*, 
            (SELECT COUNT(*) FROM ai_workspace_members WHERE workspace_id = w.workspace_id) as member_count
            FROM ai_workspaces w 
            WHERE w.company_id = ? AND w.status = ?
            ORDER BY w.updated_at DESC");
        $stmt->execute([(int)$companyId, $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * List workspaces a user is a member of.
     */
    public static function listByUser($db, $userId, $companyId) {
        $stmt = $db->prepare("SELECT w.*, m.role as my_role,
            (SELECT COUNT(*) FROM ai_workspace_members WHERE workspace_id = w.workspace_id) as member_count
            FROM ai_workspaces w
            JOIN ai_workspace_members m ON m.workspace_id = w.workspace_id
            WHERE m.user_id = ? AND w.company_id = ? AND w.status = 'active'
            ORDER BY w.updated_at DESC");
        $stmt->execute([(int)$userId, (int)$companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update workspace details.
     */
    public static function update($db, $workspaceId, $data, $actorUserId) {
        $fields = [];
        $params = [];
        foreach (['name', 'description', 'visibility', 'status'] as $f) {
            if (isset($data[$f])) {
                $fields[] = "$f = ?";
                $params[] = $data[$f];
            }
        }
        if (empty($fields)) return ['success' => false, 'error' => 'No fields to update'];

        $params[] = (int)$workspaceId;
        $db->prepare("UPDATE ai_workspaces SET " . implode(', ', $fields) . " WHERE workspace_id = ?")->execute($params);

        self::logActivity($db, $workspaceId, $actorUserId, 'workspace_updated', 'workspace', $workspaceId, $data);
        return ['success' => true];
    }

    /**
     * Archive a workspace (soft delete).
     */
    public static function archive($db, $workspaceId, $actorUserId) {
        $db->prepare("UPDATE ai_workspaces SET status = 'archived' WHERE workspace_id = ?")->execute([(int)$workspaceId]);
        self::logActivity($db, $workspaceId, $actorUserId, 'workspace_archived', 'workspace', $workspaceId);
        return ['success' => true];
    }

    // ── Member Management ─────────────────────────────────────

    /**
     * Add a member to a workspace.
     */
    public static function addMember($db, $workspaceId, $userId, $role = 'member', $invitedBy = null) {
        $validRoles = ['owner','manager','member','viewer'];
        if (!in_array($role, $validRoles)) $role = 'member';

        try {
            $stmt = $db->prepare("INSERT INTO ai_workspace_members (workspace_id, user_id, role, invited_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([(int)$workspaceId, (int)$userId, $role, $invitedBy ? (int)$invitedBy : null]);
            $memberId = (int)$db->lastInsertId();

            self::logActivity($db, $workspaceId, $invitedBy ?? $userId, 'member_added', 'member', $memberId, ['user_id' => $userId, 'role' => $role]);

            // Create notification
            self::createNotification($db, $workspaceId, $userId, 'member_added', 'You were added to a workspace', 'You have been added as a ' . $role . '.', 'member', $memberId);

            return ['success' => true, 'member_id' => $memberId];
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                return ['success' => false, 'error' => 'User is already a member'];
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Remove a member from a workspace.
     */
    public static function removeMember($db, $workspaceId, $userId, $actorUserId) {
        // Cannot remove the owner
        $ws = self::getById($db, $workspaceId);
        if ($ws && (int)$ws['owner_user_id'] === (int)$userId) {
            return ['success' => false, 'error' => 'Cannot remove workspace owner'];
        }

        $db->prepare("DELETE FROM ai_workspace_members WHERE workspace_id = ? AND user_id = ?")->execute([(int)$workspaceId, (int)$userId]);
        self::logActivity($db, $workspaceId, $actorUserId, 'member_removed', 'member', $userId, ['user_id' => $userId]);
        return ['success' => true];
    }

    /**
     * Change a member's role.
     */
    public static function changeRole($db, $workspaceId, $userId, $newRole, $actorUserId) {
        $validRoles = ['owner','manager','member','viewer'];
        if (!in_array($newRole, $validRoles)) return ['success' => false, 'error' => 'Invalid role'];

        $db->prepare("UPDATE ai_workspace_members SET role = ? WHERE workspace_id = ? AND user_id = ?")->execute([$newRole, (int)$workspaceId, (int)$userId]);
        self::logActivity($db, $workspaceId, $actorUserId, 'role_changed', 'member', $userId, ['user_id' => $userId, 'new_role' => $newRole]);
        return ['success' => true];
    }

    /**
     * List members of a workspace.
     */
    public static function getMembers($db, $workspaceId) {
        $stmt = $db->prepare("SELECT m.*, u.name as username
            FROM ai_workspace_members m 
            LEFT JOIN users u ON u.id = m.user_id 
            WHERE m.workspace_id = ? 
            ORDER BY FIELD(m.role, 'owner','manager','member','viewer'), m.joined_at");
        $stmt->execute([(int)$workspaceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a user's role in a workspace.
     */
    public static function getMemberRole($db, $workspaceId, $userId) {
        $stmt = $db->prepare("SELECT role FROM ai_workspace_members WHERE workspace_id = ? AND user_id = ?");
        $stmt->execute([(int)$workspaceId, (int)$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['role'] : null;
    }

    /**
     * Check if a user is a member of a workspace.
     */
    public static function isMember($db, $workspaceId, $userId) {
        return self::getMemberRole($db, $workspaceId, $userId) !== null;
    }

    /**
     * Check if user has at least the given role level.
     */
    public static function hasRole($db, $workspaceId, $userId, $minRole = 'member') {
        $role = self::getMemberRole($db, $workspaceId, $userId);
        if ($role === null) return false;
        $hierarchy = ['viewer' => 1, 'member' => 2, 'manager' => 3, 'owner' => 4];
        return ($hierarchy[$role] ?? 0) >= ($hierarchy[$minRole] ?? 0);
    }

    // ── Permissions ───────────────────────────────────────────

    /**
     * Check if user can perform an action in the workspace.
     * Workspace role + underlying ERP permission required.
     */
    public static function canPerform($db, $workspaceId, $userId, $requiredRole = 'viewer') {
        if (!self::isMember($db, $workspaceId, $userId)) return false;
        return self::hasRole($db, $workspaceId, $userId, $requiredRole);
    }

    /**
     * Get workspace with full context (members, pins, instructions, goals).
     */
    public static function getFull($db, $workspaceId, $companyId = null) {
        $ws = self::getById($db, $workspaceId, $companyId);
        if (!$ws) return null;

        $ws['members'] = self::getMembers($db, $workspaceId);
        $ws['pins'] = AIWorkspaceContextService::getPins($db, $workspaceId);
        $ws['instructions'] = AIWorkspaceContextService::getInstructions($db, $workspaceId);
        $ws['goals'] = AIWorkspaceContextService::getGoals($db, $workspaceId);
        $ws['knowledge'] = AIWorkspaceContextService::getKnowledgeRefs($db, $workspaceId);
        $ws['recent_activity'] = AIWorkspaceActivityService::getRecent($db, $workspaceId, 10);

        return $ws;
    }

    // ── Activity Logging ──────────────────────────────────────

    /**
     * Log a workspace activity event.
     */
    public static function logActivity($db, $workspaceId, $actorUserId, $actionType, $targetType = null, $targetId = null, $metadata = null) {
        $metaJson = $metadata ? json_encode($metadata) : null;
        $db->prepare("INSERT INTO ai_workspace_activity (workspace_id, actor_user_id, action_type, target_type, target_id, metadata_json) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([(int)$workspaceId, (int)$actorUserId, $actionType, $targetType, $targetId, $metaJson]);
    }

    // ── Notifications ─────────────────────────────────────────

    /**
     * Create a workspace notification for a user.
     */
    public static function createNotification($db, $workspaceId, $recipientUserId, $type, $title, $message = null, $refType = null, $refId = null) {
        $db->prepare("INSERT INTO ai_workspace_notifications (workspace_id, recipient_user_id, notification_type, title, message, reference_type, reference_id) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([(int)$workspaceId, (int)$recipientUserId, $type, $title, $message, $refType, $refId]);
    }

    /**
     * Get notifications for a user in a workspace.
     */
    public static function getNotifications($db, $workspaceId, $userId, $unreadOnly = false) {
        $sql = "SELECT * FROM ai_workspace_notifications WHERE workspace_id = ? AND recipient_user_id = ?";
        $params = [(int)$workspaceId, (int)$userId];
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        $sql .= " ORDER BY created_at DESC LIMIT 50";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark notifications as read.
     */
    public static function markRead($db, $workspaceId, $userId, $notificationIds = null) {
        if ($notificationIds) {
            $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
            $params = array_merge([$workspaceId, $userId], $notificationIds);
            $db->prepare("UPDATE ai_workspace_notifications SET is_read = 1 WHERE workspace_id = ? AND recipient_user_id = ? AND notification_id IN ($placeholders)")->execute($params);
        } else {
            $db->prepare("UPDATE ai_workspace_notifications SET is_read = 1 WHERE workspace_id = ? AND recipient_user_id = ?")->execute([(int)$workspaceId, (int)$userId]);
        }
    }
}
