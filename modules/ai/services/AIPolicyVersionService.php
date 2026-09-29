<?php
/**
 * AI F24 — Policy Version Service.
 *
 * Manages policy version history, rollback, and change tracking.
 */
class AIPolicyVersionService
{
    /**
     * Create a new policy version.
     */
    public static function createVersion(PDO $db, int $policyId, int $version, string $changeReason, int $authorId, array $previousValue = null): bool
    {
        try {
            // Get current policy data
            $stmt = $db->prepare("SELECT * FROM ai_policies WHERE id = ?");
            $stmt->execute([$policyId]);
            $policy = $stmt->fetch();

            if (!$policy) {
                return false;
            }

            // Check if this version already exists
            $stmt = $db->prepare("SELECT id FROM ai_policy_versions WHERE policy_id = ? AND version = ?");
            $stmt->execute([$policyId, $version]);
            if ($stmt->fetch()) {
                return true; // Version already exists
            }

            // Insert version record
            $stmt = $db->prepare("
                INSERT INTO ai_policy_versions
                    (policy_id, version, change_reason, author_id, previous_version,
                     conditions_json, effect, priority, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $policyId,
                $version,
                $changeReason,
                $authorId,
                $previousValue ? json_encode($previousValue) : null,
                $policy['conditions_json'],
                $policy['effect'],
                $policy['priority'],
                $policy['status'],
            ]);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get version history for a policy.
     */
    public static function getVersionHistory(PDO $db, int $policyId, int $page = 1, int $perPage = 20): array
    {
        try {
            $offset = ($page - 1) * $perPage;

            // Get policy info
            $stmt = $db->prepare("SELECT * FROM ai_policies WHERE id = ?");
            $stmt->execute([$policyId]);
            $policy = $stmt->fetch();

            if (!$policy) {
                return ['versions' => [], 'policy' => null];
            }

            // Get versions
            $stmt = $db->prepare("
                SELECT v.*, u.name as author_name
                FROM ai_policy_versions v
                LEFT JOIN users u ON v.author_id = u.id
                WHERE v.policy_id = ?
                ORDER BY v.version DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$policyId, $perPage, $offset]);
            $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count
            $stmt = $db->prepare("SELECT COUNT(*) FROM ai_policy_versions WHERE policy_id = ?");
            $stmt->execute([$policyId]);
            $total = (int) $stmt->fetchColumn();

            return [
                'policy' => $policy,
                'versions' => $versions,
                'total' => $total,
                'page' => $page,
                'total_pages' => ceil($total / $perPage),
            ];
        } catch (\Throwable $e) {
            return ['versions' => [], 'policy' => null];
        }
    }

    /**
     * Get a specific version.
     */
    public static function getVersion(PDO $db, int $policyId, int $version): ?array
    {
        try {
            $stmt = $db->prepare("SELECT * FROM ai_policy_versions WHERE policy_id = ? AND version = ?");
            $stmt->execute([$policyId, $version]);
            return $stmt->fetch();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Rollback to a previous version.
     */
    public static function rollback(PDO $db, int $policyId, int $targetVersion, int $userId): array
    {
        try {
            // Get target version
            $targetVersionData = self::getVersion($db, $policyId, $targetVersion);
            if (!$targetVersionData) {
                return ['success' => false, 'error' => 'Target version not found'];
            }

            // Get current version
            $currentVersion = self::getCurrentVersion($db, $policyId);

            // Update policy to target version
            $stmt = $db->prepare("
                UPDATE ai_policies
                SET conditions_json = ?, effect = ?, priority = ?, status = ?,
                    version = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $targetVersionData['conditions_json'],
                $targetVersionData['effect'],
                $targetVersionData['priority'],
                $targetVersionData['status'],
                $targetVersion + 1, // New version number
                $userId,
                $policyId,
            ]);

            // Create new version record for the rollback
            self::createVersion($db, $policyId, $targetVersion + 1,
                "Rollback to version $targetVersion", $userId, $currentVersion);

            // Audit log
            if (class_exists('AIGovernanceAudit')) {
                AIGovernanceAudit::log($db, 'policy_rollback', [
                    'actor_id' => $userId,
                    'target_type' => 'policy',
                    'target_id' => $policyId,
                    'new_value' => json_encode(['rolled_back_to' => $targetVersion]),
                    'change_reason' => "Rollback from version " . ($currentVersion['version'] ?? '?') . " to version $targetVersion",
                ]);
            }

            return [
                'success' => true,
                'rolled_back_to' => $targetVersion,
                'new_version' => $targetVersion + 1,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get current version number.
     */
    public static function getCurrentVersion(PDO $db, int $policyId): ?array
    {
        try {
            $stmt = $db->prepare("SELECT version, * FROM ai_policies WHERE id = ?");
            $stmt->execute([$policyId]);
            return $stmt->fetch();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get latest version for a policy.
     */
    public static function getLatestVersion(PDO $db, int $policyId): ?array
    {
        try {
            $stmt = $db->prepare("SELECT * FROM ai_policy_versions WHERE policy_id = ? ORDER BY version DESC LIMIT 1");
            $stmt->execute([$policyId]);
            return $stmt->fetch();
        } catch (\Throwable $e) {
            return null;
        }
    }
}