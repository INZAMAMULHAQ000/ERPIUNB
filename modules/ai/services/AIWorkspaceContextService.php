<?php
/**
 * AI Workspace Context Service
 * 
 * F19: Context Pinning, Instructions, Goals, Knowledge References, Shared Context.
 * All methods are static and take $db as first parameter.
 */

class AIWorkspaceContextService {

    // ── Context Pinning ───────────────────────────────────────

    /**
     * Pin context to a workspace.
     */
    public static function pin($db, $workspaceId, $userId, $refType, $refId, $title = null, $note = null) {
        $validTypes = ['conversation','task','insight','knowledge','erp_record','agent_result','finding','research_session'];
        if (!in_array($refType, $validTypes)) {
            return ['success' => false, 'error' => 'Invalid reference type'];
        }

        try {
            $stmt = $db->prepare("INSERT INTO ai_workspace_pins (workspace_id, pinned_by, reference_type, reference_id, title, note) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([(int)$workspaceId, (int)$userId, $refType, (int)$refId, $title, $note]);
            $pinId = (int)$db->lastInsertId();

            AIWorkspaceService::logActivity($db, $workspaceId, $userId, 'context_pinned', 'pin', $pinId, ['type' => $refType, 'ref_id' => $refId, 'title' => $title]);

            return ['success' => true, 'pin_id' => $pinId];
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                return ['success' => false, 'error' => 'Already pinned'];
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get all pins for a workspace.
     */
    public static function getPins($db, $workspaceId) {
        $stmt = $db->prepare("SELECT p.*, u.name as pinned_by_name
            FROM ai_workspace_pins p
            LEFT JOIN users u ON u.id = p.pinned_by
            WHERE p.workspace_id = ?
            ORDER BY p.pinned_at DESC");
        $stmt->execute([(int)$workspaceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Unpin context from a workspace.
     */
    public static function unpin($db, $pinId) {
        $db->prepare("DELETE FROM ai_workspace_pins WHERE pin_id = ?")->execute([(int)$pinId]);
        return ['success' => true];
    }

    // ── Workspace Instructions ────────────────────────────────

    /**
     * Set a workspace instruction.
     */
    public static function setInstruction($db, $workspaceId, $userId, $text, $priority = 50) {
        $stmt = $db->prepare("INSERT INTO ai_workspace_instructions (workspace_id, set_by, instruction_text, priority) VALUES (?, ?, ?, ?)");
        $stmt->execute([(int)$workspaceId, (int)$userId, trim($text), (int)$priority]);
        $instId = (int)$db->lastInsertId();

        AIWorkspaceService::logActivity($db, $workspaceId, $userId, 'instruction_set', 'instruction', $instId, ['text' => substr($text, 0, 100)]);

        return ['success' => true, 'instruction_id' => $instId];
    }

    /**
     * Get active instructions for a workspace.
     */
    public static function getInstructions($db, $workspaceId) {
        $stmt = $db->prepare("SELECT i.*, u.name as set_by_name
            FROM ai_workspace_instructions i
            LEFT JOIN users u ON u.id = i.set_by
            WHERE i.workspace_id = ? AND i.is_active = 1
            ORDER BY i.priority DESC, i.created_at DESC");
        $stmt->execute([(int)$workspaceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Deactivate an instruction.
     */
    public static function deactivateInstruction($db, $instructionId) {
        $db->prepare("UPDATE ai_workspace_instructions SET is_active = 0 WHERE instruction_id = ?")->execute([(int)$instructionId]);
        return ['success' => true];
    }

    /**
     * Delete an instruction.
     */
    public static function deleteInstruction($db, $instructionId) {
        $db->prepare("DELETE FROM ai_workspace_instructions WHERE instruction_id = ?")->execute([(int)$instructionId]);
        return ['success' => true];
    }

    // ── Workspace Goals ───────────────────────────────────────

    /**
     * Create a workspace goal.
     */
    public static function createGoal($db, $workspaceId, $userId, $title, $description = null, $successCriteria = null) {
        $stmt = $db->prepare("INSERT INTO ai_workspace_goals (workspace_id, set_by, title, description, success_criteria) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([(int)$workspaceId, (int)$userId, trim($title), $description, $successCriteria]);
        $goalId = (int)$db->lastInsertId();

        AIWorkspaceService::logActivity($db, $workspaceId, $userId, 'goal_created', 'goal', $goalId, ['title' => $title]);

        return ['success' => true, 'goal_id' => $goalId];
    }

    /**
     * Get goals for a workspace.
     */
    public static function getGoals($db, $workspaceId, $status = null) {
        $sql = "SELECT g.*, u.name as set_by_name
            FROM ai_workspace_goals g
            LEFT JOIN users u ON u.id = g.set_by
            WHERE g.workspace_id = ?";
        $params = [(int)$workspaceId];
        if ($status) {
            $sql .= " AND g.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY g.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update goal status.
     */
    public static function updateGoalStatus($db, $goalId, $newStatus) {
        $valid = ['active','achieved','archived'];
        if (!in_array($newStatus, $valid)) return ['success' => false, 'error' => 'Invalid status'];
        $db->prepare("UPDATE ai_workspace_goals SET status = ? WHERE goal_id = ?")->execute([$newStatus, (int)$goalId]);
        return ['success' => true];
    }

    /**
     * Delete a goal.
     */
    public static function deleteGoal($db, $goalId) {
        $db->prepare("DELETE FROM ai_workspace_goals WHERE goal_id = ?")->execute([(int)$goalId]);
        return ['success' => true];
    }

    // ── Knowledge References ──────────────────────────────────

    /**
     * Add a knowledge collection reference to a workspace.
     */
    public static function addKnowledgeRef($db, $workspaceId, $knowledgeCollectionId, $userId) {
        try {
            $workspaceCompanyId = self::getWorkspaceCompanyId($db, (int) $workspaceId);
            if ($workspaceCompanyId === null) {
                return ['success' => false, 'error' => 'Workspace not found'];
            }
            if (!self::collectionBelongsToCompany($db, (int) $knowledgeCollectionId, $workspaceCompanyId)) {
                return ['success' => false, 'error' => 'Collection not found or access denied'];
            }
            $stmt = $db->prepare("INSERT INTO ai_workspace_knowledge (workspace_id, knowledge_collection_id, added_by) VALUES (?, ?, ?)");
            $stmt->execute([(int)$workspaceId, (int)$knowledgeCollectionId, (int)$userId]);

            AIWorkspaceService::logActivity($db, $workspaceId, $userId, 'knowledge_added', 'knowledge', $knowledgeCollectionId);

            return ['success' => true];
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                return ['success' => false, 'error' => 'Already linked'];
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get knowledge references for a workspace.
     */
    public static function getKnowledgeRefs($db, $workspaceId) {
        $stmt = $db->prepare("SELECT wk.*, kc.name as collection_title, u.name as added_by_name
            FROM ai_workspace_knowledge wk
            JOIN ai_workspaces w ON w.workspace_id = wk.workspace_id
            LEFT JOIN ai_knowledge_collections kc ON kc.id = wk.knowledge_collection_id AND kc.company_id = w.company_id
            LEFT JOIN users u ON u.id = wk.added_by
            WHERE wk.workspace_id = ? AND w.company_id = ?");
        $workspaceCompanyId = self::getWorkspaceCompanyId($db, (int) $workspaceId);
        if ($workspaceCompanyId === null) {
            return [];
        }
        $stmt->execute([(int)$workspaceId, $workspaceCompanyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Remove a knowledge reference.
     */
    public static function removeKnowledgeRef($db, $workspaceId, $knowledgeCollectionId) {
        $workspaceCompanyId = self::getWorkspaceCompanyId($db, (int) $workspaceId);
        if ($workspaceCompanyId === null || !self::collectionBelongsToCompany($db, (int) $knowledgeCollectionId, $workspaceCompanyId)) {
            return ['success' => false, 'error' => 'Reference not found or access denied'];
        }
        $db->prepare("DELETE FROM ai_workspace_knowledge WHERE workspace_id = ? AND knowledge_collection_id = ?")->execute([(int)$workspaceId, (int)$knowledgeCollectionId]);
        return ['success' => true];
    }

    // ── Shared Context Builder ────────────────────────────────

    /**
     * Build workspace context for AI requests.
     * Returns a structured context array with pinned items, instructions, goals, and knowledge refs.
     */
    public static function buildContext($db, $workspaceId, $maxContextSize = 4000) {
        $context = [
            'workspace_id' => $workspaceId,
            'pinned' => [],
            'instructions' => [],
            'goals' => [],
            'knowledge_refs' => [],
        ];

        // Pinned context (prioritized)
        $pins = self::getPins($db, $workspaceId);
        foreach ($pins as $pin) {
            $context['pinned'][] = [
                'type' => $pin['reference_type'],
                'id' => $pin['reference_id'],
                'title' => $pin['title'],
            ];
        }

        // Workspace instructions
        $instructions = self::getInstructions($db, $workspaceId);
        foreach ($instructions as $inst) {
            $context['instructions'][] = $inst['instruction_text'];
        }

        // Active goals
        $goals = self::getGoals($db, $workspaceId, 'active');
        foreach ($goals as $goal) {
            $context['goals'][] = [
                'title' => $goal['title'],
                'criteria' => $goal['success_criteria'],
            ];
        }

        // Knowledge references
        $knowledge = self::getKnowledgeRefs($db, $workspaceId);
        foreach ($knowledge as $k) {
            $context['knowledge_refs'][] = [
                'collection_id' => $k['knowledge_collection_id'],
                'title' => $k['collection_title'],
            ];
        }

        // Enforce context size limit
        $json = json_encode($context);
        if (strlen($json) > $maxContextSize) {
            // Trim knowledge refs first, then goals
            while (strlen(json_encode($context)) > $maxContextSize && count($context['knowledge_refs']) > 0) {
                array_pop($context['knowledge_refs']);
            }
            while (strlen(json_encode($context)) > $maxContextSize && count($context['goals']) > 0) {
                array_pop($context['goals']);
            }
        }

        return $context;
    }

    // ── Context Isolation ─────────────────────────────────────

    /**
     * Determine context mode for a request.
     * Returns 'private', 'workspace', or 'both'.
     */
    public static function resolveContextMode($db, $userId, $workspaceId = null) {
        if ($workspaceId && self::isWorkspaceContext($db, $userId, $workspaceId)) {
            return 'workspace';
        }
        return 'private';
    }

    /**
     * Check if user has workspace context available.
     */
    public static function isWorkspaceContext($db, $userId, $workspaceId) {
        return AIWorkspaceService::isMember($db, $workspaceId, $userId);
    }

    // ── Shared Agent Results ──────────────────────────────────

    /**
     * Share an agent run result into a workspace.
     */
    public static function shareAgentResult($db, $workspaceId, $agentRunId, $userId, $summary = null) {
        try {
            $stmt = $db->prepare("INSERT INTO ai_workspace_shared_agent_runs (workspace_id, agent_run_id, shared_by, shared_summary) VALUES (?, ?, ?, ?)");
            $stmt->execute([(int)$workspaceId, (int)$agentRunId, (int)$userId, $summary]);
            $shareId = (int)$db->lastInsertId();

            AIWorkspaceService::logActivity($db, $workspaceId, $userId, 'agent_result_shared', 'agent_run', $agentRunId, ['summary' => substr($summary ?? '', 0, 200)]);

            // Notify members
            $members = $db->prepare("SELECT user_id FROM ai_workspace_members WHERE workspace_id = ? AND user_id != ?");
            $members->execute([$workspaceId, $userId]);
            while ($m = $members->fetch(PDO::FETCH_ASSOC)) {
                AIWorkspaceService::createNotification($db, $workspaceId, (int)$m['user_id'], 'agent_result_shared', 'Agent Result Shared', 'A new agent result has been shared in the workspace.', 'agent_run', $agentRunId);
            }

            return ['success' => true, 'share_id' => $shareId];
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                return ['success' => false, 'error' => 'Already shared'];
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get shared agent runs for a workspace.
     */
    public static function getSharedAgentRuns($db, $workspaceId) {
        $stmt = $db->prepare("SELECT sar.*, u.name as shared_by_name
            FROM ai_workspace_shared_agent_runs sar
            LEFT JOIN users u ON u.id = sar.shared_by
            WHERE sar.workspace_id = ?
            ORDER BY sar.created_at DESC");
        $stmt->execute([(int)$workspaceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function getWorkspaceCompanyId($db, int $workspaceId): ?int {
        $stmt = $db->prepare("SELECT company_id FROM ai_workspaces WHERE workspace_id = ? LIMIT 1");
        $stmt->execute([$workspaceId]);
        $companyId = $stmt->fetchColumn();
        if ($companyId === false) {
            return null;
        }
        return (int) $companyId;
    }

    private static function collectionBelongsToCompany($db, int $collectionId, int $companyId): bool {
        $stmt = $db->prepare("SELECT 1 FROM ai_knowledge_collections WHERE id = ? AND company_id = ? LIMIT 1");
        $stmt->execute([$collectionId, $companyId]);
        return (bool) $stmt->fetchColumn();
    }
}
