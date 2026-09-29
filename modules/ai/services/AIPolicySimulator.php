<?php
/**
 * AI F24 — Policy Simulator.
 *
 * Allows administrators to simulate policy evaluation without executing anything.
 * Simulates a hypothetical request and shows what the governance layer would decide.
 *
 * Covers Section 40: Policy Simulation.
 */
class AIPolicySimulator
{
    /**
     * Simulate a policy evaluation for a hypothetical request.
     *
     * @param PDO    $db      Database connection
     * @param array  $input   Simulation input
     * @return array Simulation result
     */
    public static function simulate(PDO $db, array $input): array
    {
        $simulation = [
            'simulation' => true,
            'note' => 'This is a simulation — no changes were made',
            'input' => $input,
            'result' => null,
        ];

        $action = $input['action'] ?? '';
        $context = $input['context'] ?? [];
        $user = $input['user'] ?? null;
        $amount = $input['amount'] ?? null;

        if (empty($action)) {
            $simulation['result'] = ['error' => 'Action is required for simulation'];
            return $simulation;
        }

        // Build simulated context
        $simContext = array_merge($context, [
            'simulation' => true,
        ]);

        // Resolve user context if provided
        if ($user) {
            if (is_int($user) || ctype_digit((string)$user)) {
                $simContext['user_id'] = (int) $user;
            } else {
                // Try to find user by name
                try {
                    $stmt = $db->prepare("SELECT id, company_id FROM users WHERE name LIKE ? OR email LIKE ? LIMIT 1");
                    $stmt->execute(["%$user%", "%$user%"]);
                    $userInfo = $stmt->fetch();
                    if ($userInfo) {
                        $simContext['user_id'] = $userInfo['id'];
                        $simContext['company_id'] = $userInfo['company_id'];
                    }
                } catch (\Throwable $e) {
                    // User not found — continue with defaults
                }
            }
        }

        if ($amount !== null) {
            $simContext['amount'] = (float) $amount;
        }

        // 1. Risk Classification
        $riskResult = null;
        if (class_exists('AIRiskClassifier')) {
            $riskResult = AIRiskClassifier::classify($db, $action, $simContext, $simContext['company_id'] ?? null);
        }

        // 2. Policy Evaluation
        $policyResult = null;
        if (class_exists('AIPolicyEngine')) {
            $policyResult = AIPolicyEngine::evaluate($db, $action, $simContext);
        }

        // 3. Financial check (if amount provided)
        $financialResult = null;
        if ($amount !== null && class_exists('AIFinancialProtectionService')) {
            $financialResult = AIFinancialProtectionService::checkFinancialAction(
                $db,
                $action,
                (float) $amount,
                $simContext
            );
        }

        // 4. Bulk action check (if record count provided)
        $bulkResult = null;
        if (isset($context['record_count']) && class_exists('AIBulkActionService')) {
            $bulkResult = AIBulkActionService::checkBulkAction(
                $db,
                $action,
                (int) $context['record_count'],
                $riskResult['level'] ?? 'low',
                $simContext['company_id'] ?? 0
            );
        }

        // 5. Autonomy check
        $autonomyLevel = 'suggest';
        if (class_exists('AIPolicyEngine')) {
            $autonomyLevel = AIPolicyEngine::getAutonomyLevel(
                $db,
                $simContext['company_id'] ?? null,
                $simContext['role_id'] ?? null,
                $simContext['user_id'] ?? null
            );
        }

        // 6. Build final simulation result
        $finalDecision = 'allow';
        $reasons = [];

        if ($policyResult && $policyResult['decision'] === 'deny') {
            $finalDecision = 'deny';
            $reasons[] = 'Policy: ' . ($policyResult['reason'] ?? 'Denied by policy');
        }

        if ($financialResult && !$financialResult['allowed']) {
            $finalDecision = 'deny';
            $reasons[] = 'Financial: ' . ($financialResult['reason'] ?? 'Exceeds financial threshold');
        } elseif ($financialResult && $financialResult['requires_review']) {
            $finalDecision = 'review';
            $reasons[] = 'Financial: Review required (amount: $' . number_format($amount, 2) . ')';
        } elseif ($financialResult && $financialResult['requires_confirmation']) {
            if ($finalDecision === 'allow') {
                $finalDecision = 'confirm';
            }
            $reasons[] = 'Financial: Confirmation required';
        }

        if ($bulkResult && !$bulkResult['allowed']) {
            $finalDecision = 'deny';
            $reasons[] = 'Bulk: ' . ($bulkResult['reason'] ?? 'Exceeds bulk threshold');
        } elseif ($bulkResult && $bulkResult['requires_review']) {
            $finalDecision = 'review';
            $reasons[] = 'Bulk: Review required';
        }

        if ($policyResult && $policyResult['requires_review']) {
            $finalDecision = 'review';
            $reasons[] = 'Policy: Review required';
        }

        if ($policyResult && $policyResult['requires_confirmation'] && $finalDecision === 'allow') {
            $finalDecision = 'confirm';
            $reasons[] = 'Policy: Confirmation required';
        }

        if (in_array($autonomyLevel, ['restricted']) && $action !== 'read') {
            $finalDecision = 'deny';
            $reasons[] = 'Autonomy: Level is restricted';
        }

        $simulation['result'] = [
            'risk' => $riskResult ? [
                'level' => $riskResult['level'],
                'rules_matched' => $riskResult['rules_matched'] ?? [],
                'confidence' => $riskResult['confidence'] ?? 0,
            ] : null,
            'policy' => $policyResult ? [
                'decision' => $policyResult['decision'],
                'matched_policies' => count($policyResult['policies_matched'] ?? []),
                'reason' => $policyResult['reason'],
            ] : null,
            'financial' => $financialResult ? [
                'allowed' => $financialResult['allowed'],
                'action' => $financialResult['action'],
                'reason' => $financialResult['reason'],
            ] : null,
            'bulk_action' => $bulkResult ? [
                'allowed' => $bulkResult['allowed'],
                'action' => $bulkResult['action'],
                'record_count' => $bulkResult['record_count'],
            ] : null,
            'autonomy_level' => $autonomyLevel,
            'final_decision' => strtoupper($finalDecision),
            'reasons' => $reasons,
        ];

        return $simulation;
    }

    /**
     * Simulate multiple actions at once.
     */
    public static function simulateBatch(PDO $db, array $actions): array
    {
        $results = [];
        foreach ($actions as $action) {
            $results[] = self::simulate($db, $action);
        }
        return $results;
    }

    /**
     * Get simulation history (stored in database for audit).
     */
    public static function getHistory(PDO $db, int $limit = 20): array
    {
        try {
            $stmt = $db->prepare(
                "SELECT * FROM ai_policy_simulations
                 ORDER BY created_at DESC
                 LIMIT ?"
            );
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Store a simulation result for audit.
     */
    public static function storeResult(PDO $db, array $simulation, int $userId): void
    {
        try {
            $db->prepare(
                "INSERT INTO ai_policy_simulations (input_json, result_json, user_id, created_at)
                 VALUES (?, ?, ?, NOW())"
            )->execute([
                json_encode($simulation['input'] ?? []),
                json_encode($simulation['result'] ?? []),
                $userId,
            ]);
        } catch (\Throwable $e) {
            // Non-critical
        }
    }
}
