<?php
/**
 * AI F25 — Agent Approval Service.
 *
 * Manages approval records for agent actions requiring human confirmation/review.
 * Approval is cryptographically bound to exact action parameters — if parameters
 * change, the approval becomes invalid.
 *
 * Covers Section 10: Approvals & Human Handoff.
 */
class AIAgentApprovalService
{
    const EXPIRY_MINUTES = 30;

    /**
     * Create an approval record for an agent step.
     */
    public static function createApproval(
        PDO $db,
        int $taskId,
        int $runId,
        ?int $planId,
        ?int $stepId,
        int $companyId,
        int $requestingUserId,
        string $actionType,
        array $parameters,
        string $riskLevel,
        array $affectedRecords = []
    ): array {
        $approvalKey = 'approval_' . bin2hex(random_bytes(16));
        $parametersHash = hash('sha256', json_encode($parameters));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + (self::EXPIRY_MINUTES * 60));

        try {
            $stmt = $db->prepare("
                INSERT INTO ai_agent_approvals
                    (approval_key, task_id, run_id, plan_id, step_id, company_id,
                     requesting_user_id, action_type, action_parameters_json,
                     parameters_hash, affected_records_json, risk_level, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $approvalKey, $taskId, $runId, $planId, $stepId, $companyId,
                $requestingUserId, $actionType, json_encode($parameters),
                $parametersHash, json_encode($affectedRecords), $riskLevel, $expiresAt,
            ]);

            return [
                'success' => true,
                'approval_id' => (int) $db->lastInsertId(),
                'approval_key' => $approvalKey,
                'expires_at' => $expiresAt,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify an approval is valid for the given parameters.
     * If parameters have changed, the approval is invalid.
     */
    public static function verifyApproval(PDO $db, string $approvalKey, array $currentParameters): array
    {
        $stmt = $db->prepare("SELECT * FROM ai_agent_approvals WHERE approval_key = ?");
        $stmt->execute([$approvalKey]);
        $approval = $stmt->fetch();

        if (!$approval) {
            return ['valid' => false, 'reason' => 'Approval not found'];
        }

        if ($approval['status'] !== 'pending') {
            return ['valid' => false, 'reason' => 'Approval is not in pending status'];
        }

        if (strtotime($approval['expires_at']) < time()) {
            self::expireApproval($db, $approval['id']);
            return ['valid' => false, 'reason' => 'Approval has expired'];
        }

        // Verify parameters match exactly
        $currentHash = hash('sha256', json_encode($currentParameters));
        if (!hash_equals($approval['parameters_hash'], $currentHash)) {
            return ['valid' => false, 'reason' => 'Parameters have changed — approval invalidated'];
        }

        return ['valid' => true, 'approval' => $approval];
    }

    /**
     * Approve a pending approval.
     */
    public static function approve(PDO $db, int $approvalId, int $approverId, string $decision = ''): array
    {
        try {
            $stmt = $db->prepare("
                UPDATE ai_agent_approvals
                SET status = 'approved', approver_id = ?, approval_decision = ?, approved_at = NOW(), updated_at = NOW()
                WHERE id = ? AND status = 'pending' AND expires_at > UTC_TIMESTAMP()
            ");
            $stmt->execute([$approverId, $decision, $approvalId]);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'error' => 'Approval not found, already processed, or expired'];
            }

            // Resume the run if it was paused for this approval
            $approval = self::getApproval($db, $approvalId);
            if ($approval && $approval['run_id']) {
                self::resumeRunAfterApproval($db, $approval['run_id']);
            }

            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Reject a pending approval.
     */
    public static function reject(PDO $db, int $approvalId, int $approverId, string $reason = ''): array
    {
        try {
            $stmt = $db->prepare("
                UPDATE ai_agent_approvals
                SET status = 'rejected', approver_id = ?, approval_decision = ?, approved_at = NOW(), updated_at = NOW()
                WHERE id = ? AND status = 'pending'
            ");
            $stmt->execute([$approverId, $reason, $approvalId]);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'error' => 'Approval not found or already processed'];
            }

            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delegate an approval to another user.
     */
    public static function delegate(PDO $db, int $approvalId, int $delegatedTo, int $delegatedBy): array
    {
        try {
            $stmt = $db->prepare("
                UPDATE ai_agent_approvals
                SET status = 'delegated', delegation_user_id = ?, approver_id = ?, updated_at = NOW()
                WHERE id = ? AND status = 'pending'
            ");
            $stmt->execute([$delegatedTo, $delegatedBy, $approvalId]);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'error' => 'Approval not found or already processed'];
            }

            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Expire an approval.
     */
    private static function expireApproval(PDO $db, int $approvalId): void
    {
        $db->prepare("UPDATE ai_agent_approvals SET status = 'expired', updated_at = NOW() WHERE id = ? AND status = 'pending'")
           ->execute([$approvalId]);
    }

    /**
     * Get pending approvals for a user (as approver).
     */
    public static function getPendingApprovals(PDO $db, int $approverId, int $companyId = 0): array
    {
        $where = "WHERE a.status = 'pending' AND a.expires_at > UTC_TIMESTAMP()";
        $params = [];

        if ($companyId > 0) {
            $where .= " AND a.company_id = ?";
            $params[] = $companyId;
        }

        $stmt = $db->prepare("
            SELECT a.*, t.original_request, u.name as requesting_user_name
            FROM ai_agent_approvals a
            LEFT JOIN ai_agent_tasks t ON a.task_id = t.id
            LEFT JOIN users u ON a.requesting_user_id = u.id
            {$where}
            ORDER BY FIELD(a.risk_level, 'critical', 'high', 'medium', 'low'), a.created_at ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get approvals for a task.
     */
    public static function getTaskApprovals(PDO $db, int $taskId): array
    {
        $stmt = $db->prepare("
            SELECT a.*, u.name as approver_name
            FROM ai_agent_approvals a
            LEFT JOIN users u ON a.approver_id = u.id
            WHERE a.task_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$taskId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cleanup expired approvals.
     */
    public static function cleanupExpired(PDO $db): int
    {
        $stmt = $db->prepare("
            UPDATE ai_agent_approvals SET status = 'expired', updated_at = NOW()
            WHERE status = 'pending' AND expires_at < UTC_TIMESTAMP()
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Resume a run after its approval is granted.
     */
    private static function resumeRunAfterApproval(PDO $db, int $runId): void
    {
        $db->prepare("
            UPDATE ai_agent_task_runs SET status = 'running', updated_at = NOW()
            WHERE id = ? AND status IN ('awaiting_confirmation', 'awaiting_review')
        ")->execute([$runId]);
    }

    /**
     * Get a single approval by ID.
     */
    private static function getApproval(PDO $db, int $approvalId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_agent_approvals WHERE id = ?");
        $stmt->execute([$approvalId]);
        $approval = $stmt->fetch();
        return $approval ?: null;
    }
}
