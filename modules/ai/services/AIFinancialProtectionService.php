<?php
/**
 * AI F24 — Financial Action Protection Service.
 *
 * Enforces monetary thresholds for financial actions.
 * Requires appropriate permissions, confirmation/review, and audit trail.
 *
 * Covers Section 28: Financial Action Protection.
 */
class AIFinancialProtectionService
{
    // Default financial thresholds
    private static array $defaultThresholds = [
        ['min' => 0,       'max' => 100,     'action' => 'confirm',  'description' => 'Low value'],
        ['min' => 100,     'max' => 10000,   'action' => 'confirm',  'description' => 'Medium value'],
        ['min' => 10000,   'max' => 100000,  'action' => 'review',   'description' => 'High value'],
        ['min' => 100000,  'max' => PHP_FLOAT_MAX, 'action' => 'deny', 'description' => 'Very high value'],
    ];

    /**
     * Check if a financial action is allowed.
     *
     * @param PDO    $db          Database connection
     * @param string $actionType  Type of financial action (invoice, payment, etc.)
     * @param float  $amount      Monetary amount
     * @param array  $context     Additional context
     * @return array Decision result
     */
    public static function checkFinancialAction(
        PDO $db,
        string $actionType,
        float $amount,
        array $context = []
    ): array {
        $result = [
            'allowed' => true,
            'action' => 'execute',
            'requires_confirmation' => false,
            'requires_review' => false,
            'reason' => '',
            'amount' => $amount,
            'threshold' => null,
        ];

        // Get configurable thresholds
        $thresholds = self::getThresholds($db, $actionType, $context['company_id'] ?? 0);

        foreach ($thresholds as $threshold) {
            if ($amount >= $threshold['min'] && $amount < $threshold['max']) {
                $result['threshold'] = $threshold;
                $result['action'] = $threshold['action'];

                switch ($threshold['action']) {
                    case 'deny':
                        $result['allowed'] = false;
                        $result['reason'] = "Financial action of \${$amount} exceeds maximum threshold (\${$threshold['max']})";
                        return $result;

                    case 'review':
                        $result['requires_review'] = true;
                        $result['reason'] = "Financial action of \${$amount} requires human review (threshold: \${$threshold['min']})";
                        break;

                    case 'confirm':
                        $result['requires_confirmation'] = true;
                        $result['reason'] = "Financial action of \${$amount} requires confirmation";
                        break;
                }
                break;
            }
        }

        return $result;
    }

    /**
     * Validate financial parameters.
     *
     * @param array $params Financial parameters
     * @return array Validation result
     */
    public static function validateFinancialParams(array $params): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
        ];

        // Validate amount
        $amount = $params['amount'] ?? 0;
        if (!is_numeric($amount) || $amount < 0) {
            $result['valid'] = false;
            $result['errors'][] = 'Amount must be a non-negative number';
        }

        if ($amount > 1000000000) {
            $result['valid'] = false;
            $result['errors'][] = 'Amount exceeds maximum allowed value';
        }

        // Validate currency
        $currency = $params['currency'] ?? 'USD';
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $result['warnings'][] = 'Currency code should be a 3-letter ISO code';
        }

        // Check for suspicious patterns
        $description = strtolower($params['description'] ?? '');
        $suspiciousPatterns = ['cash', 'off the books', 'unrecorded', 'personal', 'under the table', 'no receipt'];
        foreach ($suspiciousPatterns as $pattern) {
            if (str_contains($description, $pattern)) {
                $result['warnings'][] = "Description contains potentially suspicious term: '$pattern'";
                break;
            }
        }

        return $result;
    }

    /**
     * Get configurable thresholds from database.
     */
    private static function getThresholds(PDO $db, string $actionType, int $companyId): array
    {
        try {
            $stmt = $db->prepare(
                "SELECT min_amount, max_amount, required_action FROM ai_financial_thresholds
                 WHERE action_type = ? AND is_active = 1
                 AND (company_id IS NULL OR company_id = ?)
                 ORDER BY min_amount ASC"
            );
            $stmt->execute([$actionType, $companyId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                return array_map(fn($r) => [
                    'min' => (float) $r['min_amount'],
                    'max' => (float) $r['max_amount'],
                    'action' => $r['required_action'],
                ], $rows);
            }
        } catch (\Throwable $e) {
            // Fall through to defaults
        }

        return self::$defaultThresholds;
    }

    /**
     * Get financial action summary for UI.
     */
    public static function getSummary(PDO $db, string $actionType, int $companyId = 0): array
    {
        return [
            'action_type' => $actionType,
            'thresholds' => self::getThresholds($db, $actionType, $companyId),
        ];
    }
}
