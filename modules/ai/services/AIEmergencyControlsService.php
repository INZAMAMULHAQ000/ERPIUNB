<?php
/**
 * AI F24 — Emergency Controls Service.
 *
 * Provides centralized emergency kill switches for disabling
 * all AI capabilities server-side. All controls are enforced
 * server-side and cannot be bypassed by AI.
 *
 * Covers Section 35: Emergency Controls.
 */
class AIEmergencyControlsService
{
    // Master emergency controls
    private static array $controls = [
        'disable_all_ai'                => ['name' => 'Disable All AI',                'description' => 'Disable all AI functionality immediately',                    'category' => 'global'],
        'disable_ai_actions'            => ['name' => 'Disable AI Actions',            'description' => 'Disable all AI write actions (create, update, delete)',       'category' => 'actions'],
        'disable_autonomous_agents'     => ['name' => 'Disable Autonomous Agents',     'description' => 'Disable all autonomous AI agent execution',                  'category' => 'agents'],
        'disable_external_providers'    => ['name' => 'Disable External Providers',    'description' => 'Block all external AI provider requests',                    'category' => 'providers'],
        'disable_external_integrations' => ['name' => 'Disable External Integrations', 'description' => 'Disable all external connector execution',                  'category' => 'integrations'],
        'disable_document_ai'           => ['name' => 'Disable Document AI',           'description' => 'Disable AI document processing and analysis',               'category' => 'documents'],
        'disable_voice'                 => ['name' => 'Disable Voice',                 'description' => 'Disable voice input/output and STT/TTS',                    'category' => 'voice'],
        'disable_workflows'             => ['name' => 'Disable Workflows',             'description' => 'Disable all AI workflow execution',                         'category' => 'workflows'],
        'disable_knowledge_ai'          => ['name' => 'Disable Knowledge AI',          'description' => 'Disable AI knowledge search and retrieval',                  'category' => 'knowledge'],
        'disable_insights'              => ['name' => 'Disable Insights',              'description' => 'Disable AI insight generation',                              'category' => 'insights'],
    ];

    /**
     * Check if an emergency control is active (feature is disabled).
     *
     * @param PDO    $db        Database connection
     * @param string $controlKey The control key to check
     * @return bool True if the control is active (feature is disabled)
     */
    public static function isControlActive(PDO $db, string $controlKey): bool
    {
        // Master kill switch — if AI is disabled entirely, everything is off
        if ($controlKey !== 'disable_all_ai' && self::isControlActive($db, 'disable_all_ai')) {
            return true;
        }

        try {
            $stmt = $db->prepare(
                "SELECT is_active FROM ai_emergency_controls WHERE control_key = ?"
            );
            $stmt->execute([$controlKey]);
            $result = $stmt->fetch();

            return $result ? (bool) $result['is_active'] : false;
        } catch (\Throwable $e) {
            // If table doesn't exist or error, controls are inactive (feature enabled)
            return false;
        }
    }

    /**
     * Activate an emergency control (disable a feature).
     */
    public static function activateControl(PDO $db, string $controlKey, int $userId, string $reason = ''): array
    {
        if (!isset(self::$controls[$controlKey])) {
            return ['success' => false, 'error' => 'Unknown control key: ' . $controlKey];
        }

        try {
            $db->prepare(
                "INSERT INTO ai_emergency_controls (control_key, is_active, activated_by, activated_at, reason, updated_at)
                 VALUES (?, 1, ?, NOW(), ?, NOW())
                 ON DUPLICATE KEY UPDATE is_active = 1, activated_by = ?, activated_at = NOW(), reason = ?, updated_at = NOW()"
            )->execute([$controlKey, $userId, $reason, $userId, $reason]);

            // Audit
            if (class_exists('AIGovernanceAudit')) {
                AIGovernanceAudit::logEmergencyActivation($db, $userId, $controlKey, 'activated');
            }

            return ['success' => true, 'control' => $controlKey, 'status' => 'activated'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Deactivate an emergency control (re-enable a feature).
     */
    public static function deactivateControl(PDO $db, string $controlKey, int $userId, string $reason = ''): array
    {
        try {
            $db->prepare(
                "UPDATE ai_emergency_controls SET is_active = 0, deactivated_by = ?, deactivated_at = NOW(), reason = ?, updated_at = NOW()
                 WHERE control_key = ?"
            )->execute([$userId, $reason, $controlKey]);

            if (class_exists('AIGovernanceAudit')) {
                AIGovernanceAudit::logEmergencyActivation($db, $userId, $controlKey, 'deactivated');
            }

            return ['success' => true, 'control' => $controlKey, 'status' => 'deactivated'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Activate ALL emergency controls at once.
     */
    public static function activateAll(PDO $db, int $userId, string $reason = 'Emergency shutdown'): array
    {
        $results = [];
        foreach (array_keys(self::$controls) as $controlKey) {
            $results[$controlKey] = self::activateControl($db, $controlKey, $userId, $reason);
        }

        return [
            'success' => true,
            'results' => $results,
            'message' => 'All AI emergency controls activated',
        ];
    }

    /**
     * Deactivate ALL emergency controls at once.
     */
    public static function deactivateAll(PDO $db, int $userId, string $reason = 'Emergency controls lifted'): array
    {
        $results = [];
        foreach (array_keys(self::$controls) as $controlKey) {
            $results[$controlKey] = self::deactivateControl($db, $controlKey, $userId, $reason);
        }

        return [
            'success' => true,
            'results' => $results,
            'message' => 'All AI emergency controls deactivated',
        ];
    }

    /**
     * Get status of all emergency controls.
     */
    public static function getAllStatus(PDO $db): array
    {
        $statuses = [];

        // Ensure controls exist in DB
        self::ensureControlsExist($db);

        try {
            $stmt = $db->query("SELECT control_key, is_active, activated_by, activated_at, reason FROM ai_emergency_controls ORDER BY control_key");
            $dbControls = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            foreach (self::$controls as $key => $def) {
                $dbControl = $dbControls[$key] ?? null;
                $statuses[$key] = [
                    'key' => $key,
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'category' => $def['category'],
                    'is_active' => $dbControl ? (bool) $dbControl['is_active'] : false,
                    'activated_by' => $dbControl['activated_by'] ?? null,
                    'activated_at' => $dbControl['activated_at'] ?? null,
                    'reason' => $dbControl['reason'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            // If table doesn't exist, return all as inactive
            foreach (self::$controls as $key => $def) {
                $statuses[$key] = [
                    'key' => $key,
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'category' => $def['category'],
                    'is_active' => false,
                    'activated_by' => null,
                    'activated_at' => null,
                    'reason' => null,
                ];
            }
        }

        return $statuses;
    }

    /**
     * Get the count of active controls.
     */
    public static function getActiveCount(PDO $db): int
    {
        $statuses = self::getAllStatus($db);
        return count(array_filter($statuses, fn($s) => $s['is_active']));
    }

    /**
     * Check if an emergency is in effect (any control active).
     */
    public static function isEmergencyActive(PDO $db): bool
    {
        return self::getActiveCount($db) > 0;
    }

    /**
     * Ensure all control records exist in the database.
     */
    private static function ensureControlsExist(PDO $db): void
    {
        try {
            foreach (self::$controls as $key => $def) {
                $db->prepare(
                    "INSERT IGNORE INTO ai_emergency_controls (control_key, name, description, category, is_active, created_at, updated_at)
                     VALUES (?, ?, ?, ?, 0, NOW(), NOW())"
                )->execute([$key, $def['name'], $def['description'], $def['category']]);
            }
        } catch (\Throwable $e) {
            // Non-critical — controls can still be checked
        }
    }

    /**
     * Get emergency control definitions (without DB state).
     */
    public static function getControlDefinitions(): array
    {
        return self::$controls;
    }
}
