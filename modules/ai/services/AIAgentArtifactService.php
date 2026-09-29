<?php
/**
 * AI F25 — Agent Artifact Service.
 *
 * Manages controlled artifacts produced by agent runs: reports, drafts,
 * summaries, structured results, and generated documents.
 *
 * Covers Section 22: Artifacts.
 */
class AIAgentArtifactService
{
    /**
     * Create an artifact from agent output.
     */
    public static function createArtifact(
        PDO $db,
        int $taskId,
        ?int $runId,
        int $companyId,
        int $userId,
        string $type,
        string $title,
        string $content = '',
        string $contentType = 'text/plain',
        string $classification = 'internal',
        array $metadata = [],
        ?int $sourceStepNumber = null
    ): array {
        $artifactKey = 'artifact_' . bin2hex(random_bytes(12));

        try {
            $stmt = $db->prepare("
                INSERT INTO ai_agent_artifacts
                    (artifact_key, task_id, run_id, company_id, user_id,
                     artifact_type, title, content, content_type,
                     classification, metadata_json, source_step_number)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $artifactKey, $taskId, $runId, $companyId, $userId,
                $type, $title, $content, $contentType,
                $classification, json_encode($metadata), $sourceStepNumber,
            ]);

            return [
                'success' => true,
                'artifact_id' => (int) $db->lastInsertId(),
                'artifact_key' => $artifactKey,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get artifacts for a task.
     */
    public static function getTaskArtifacts(PDO $db, int $taskId): array
    {
        $stmt = $db->prepare("
            SELECT a.*, u.name as user_name
            FROM ai_agent_artifacts a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.task_id = ?
            ORDER BY a.created_at ASC
        ");
        $stmt->execute([$taskId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a single artifact by key.
     */
    public static function getArtifact(PDO $db, string $artifactKey): ?array
    {
        $stmt = $db->prepare("SELECT * FROM ai_agent_artifacts WHERE artifact_key = ?");
        $stmt->execute([$artifactKey]);
        $artifact = $stmt->fetch();
        return $artifact ?: null;
    }

    /**
     * Get artifacts for a user across tasks.
     */
    public static function getUserArtifacts(PDO $db, int $userId, int $limit = 50): array
    {
        $stmt = $db->prepare("
            SELECT a.*, t.original_request
            FROM ai_agent_artifacts a
            LEFT JOIN ai_agent_tasks t ON a.task_id = t.id
            WHERE a.user_id = ?
            ORDER BY a.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete an artifact (soft delete by clearing content).
     */
    public static function deleteArtifact(PDO $db, int $artifactId, int $userId): bool
    {
        try {
            $db->prepare("
                UPDATE ai_agent_artifacts
                SET content = NULL, title = CONCAT(title, ' [DELETED]'), updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ")->execute([$artifactId, $userId]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
