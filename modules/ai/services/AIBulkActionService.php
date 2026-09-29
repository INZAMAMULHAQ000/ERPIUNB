<?php
/**
 * AI F24 — Bulk Action Protection Service.
 *
 * Enforces configurable thresholds for actions affecting many records.
 * Prevents AI from inferring approval for bulk actions from generic requests.
 *
 * Covers Section 27: Bulk Action Protection.
 */
class AIBulkActionService
{
    // Default thresholds
    const DEFAULT_THRESHOLDS = [
        'low'    => ['max_records' => 10,  'action' => 'execute'],
        'medium' => ['max_records' => 100, 'action' => 'confirm'],
        'high'   => ['max_records' => 1000,'action' => 'review'],
        'critical' => ['max_records' => 1000, 'action' => 'deny'],
    ];

    /**
     * Check if a bulk action is allowed.
     *
     * @param PDO    $db         Database connection
     * @param string $actionType The type of bulk action
     * @param int    $recordCount Number of records affected
     * @param string $riskLevel  Risk level of the action
     * @param int    $companyId  Company ID
     * @return array Decision result
     */
    public static function checkBulkAction(
        PDO $db,
        string $actionType,
        int $recordCount,
        string $riskLevel = 'low',
        int $companyId = 0
    ): array {
        $result = [
            'allowed' => true,
            'action' => 'execute', // execute, confirm, review, deny
            'requires_confirmation' => false,
            'requires_review' => false,
            'reason' => '',
            'record_count' => $recordCount,
        ];

        // Single record — no bulk protection needed
        if ($recordCount <= 1) {
            return $result;
        }

        // Get configurable thresholds
        $thresholds = self::getThresholds($db, $actionType, $companyId);

        // Check each threshold level
        foreach ($thresholds as $level) {
            if ($recordCount > $level['max_records']) {
                $result['action'] = $level['action'];

                switch ($level['action']) {
                    case 'deny':
                        $result['allowed'] = false;
                        $result['reason'] = "Bulk action affecting {$recordCount} records exceeds maximum threshold ({$level['max_records']})";
                        return $result;

                    case 'review':
                        $result['requires_review'] = true;
                        $result['reason'] = "Bulk action affecting {$recordCount} records requires human review";
                        break;

                    case 'confirm':
                        $result['requires_confirmation'] = true;
                        $result['reason'] = "Bulk action affecting {$recordCount} records requires explicit confirmation";
                        break;
                }
            }
        }

        // Risk-based escalation
        if ($riskLevel === 'critical' && $recordCount > 10) {
            $result['action'] = 'deny';
            $result['allowed'] = false;
            $result['reason'] = 'Critical risk bulk actions on more than 10 records are denied';
            return $result;
        }

        if ($riskLevel === 'high' && $recordCount > 50) {
            $result['action'] = 'review';
            $result['requires_review'] = true;
            $result['reason'] = 'High risk bulk action on more than 50 records requires review';
        }

        return $result;
    }

    /**
     * Get configurable thresholds for an action type.
     */
    private static function getThresholds(PDO $db, string $actionType, int $companyId): array
    {
        try {
            $stmt = $db->prepare(
                "SELECT threshold, required_action FROM ai_bulk_action_thresholds
                 WHERE action_type = ? AND is_active = 1
                 AND (company_id IS NULL OR company_id = ?)
                 ORDER BY threshold ASC"
            );
            $stmt->execute([$actionType, $companyId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                return array_map(fn($r) => [
                    'max_records' => (int) $r['threshold'],
                    'action' => $r['required_action'],
                ], $rows);
            }
        } catch (\Throwable $e) {
            // Fall through to defaults
        }

        // Default thresholds
        return array_map(fn($t) => [
            'max_records' => $t['max_records'],
            'action' => $t['action'],
        ], self::DEFAULT_THRESHOLDS);
    }

    /**
     * Get bulk action summary for UI display.
     */
    public static function getSummary(PDO $db, string $actionType, int $companyId = 0): array
    {
        $thresholds = self::getThresholds($db, $actionType, $companyId);
        return [
            'action_type' => $actionType,
            'thresholds' => $thresholds,
            'max_records' => end($thresholds)['max_records'] ?? PHP_INT_MAX,
        ];
    }
}
