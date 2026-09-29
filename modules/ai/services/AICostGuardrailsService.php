<?php
/**
 * AI F24 — Cost Guardrails Service.
 *
 * Estimates and enforces cost budgets for AI operations.
 * Prevents AI from circumventing budgets by switching providers.
 *
 * Covers Section 34: Cost Guardrails.
 */
class AICostGuardrailsService
{
    /**
     * Estimate the cost of an AI operation before execution.
     *
     * @param PDO    $db        Database connection
     * @param int    $providerId Provider ID
     * @param int    $modelId   Model ID
     * @param int    $inputTokens  Estimated input tokens
     * @param int    $outputTokens Estimated output tokens
     * @param array  $options   Additional context
     * @return array Cost estimation result
     */
    public static function estimateCost(
        PDO $db,
        int $providerId,
        int $modelId,
        int $inputTokens,
        int $outputTokens,
        array $options = []
    ): array {
        $result = [
            'available' => false,
            'estimated_cost' => 0.0,
            'currency' => 'USD',
            'breakdown' => [],
            'within_budget' => true,
            'budget_remaining' => 0.0,
        ];

        // Get model pricing
        try {
            $stmt = $db->prepare("SELECT * FROM ai_models WHERE id = ? AND provider_id = ?");
            $stmt->execute([$modelId, $providerId]);
            $model = $stmt->fetch();

            if (!$model) {
                return $result;
            }

            $costResult = AIGovernanceService::estimateCost($model, $inputTokens, $outputTokens);
            $result['available'] = $costResult['available'];
            $result['estimated_cost'] = $costResult['estimated_cost'];
            $result['breakdown'] = [
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'basis' => $costResult['basis'],
            ];
        } catch (\Throwable $e) {
            return $result;
        }

        // Check against budget
        $companyId = $options['company_id'] ?? ($_SESSION['company_id'] ?? 1);
        $userId = $options['user_id'] ?? ($_SESSION['user_id'] ?? 0);
        $feature = $options['feature'] ?? 'ai_chat';

        $budgetCheck = AIGovernanceService::checkCostControls($db, $feature, $userId, $companyId, $modelId, $result['estimated_cost']);
        $result['within_budget'] = $budgetCheck['allowed'];

        if (!$budgetCheck['allowed']) {
            $result['budget_remaining'] = max(0, (float)($budgetCheck['limit'] ?? 0) - (float)($budgetCheck['used'] ?? 0));
        }

        return $result;
    }

    /**
     * Check if an operation is within budget.
     *
     * @param PDO    $db      Database connection
     * @param float  $estimatedCost Estimated cost
     * @param array  $context  Operation context
     * @return array Budget check result
     */
    public static function checkBudget(PDO $db, float $estimatedCost, array $context = []): array
    {
        $companyId = $context['company_id'] ?? ($_SESSION['company_id'] ?? 1);
        $userId = $context['user_id'] ?? ($_SESSION['user_id'] ?? 0);
        $modelId = $context['model_id'] ?? null;
        $feature = $context['feature'] ?? 'ai_chat';

        $check = AIGovernanceService::checkCostControls($db, $feature, $userId, $companyId, $modelId, $estimatedCost);

        return [
            'allowed' => $check['allowed'],
            'estimated_cost' => $estimatedCost,
            'used' => $check['used'] ?? 0,
            'limit' => $check['limit'] ?? 0,
            'remaining' => max(0, ($check['limit'] ?? 0) - ($check['used'] ?? 0)),
            'control_type' => $check['control_type'] ?? null,
        ];
    }

    /**
     * Prevent provider switching to circumvent budgets.
     * If one provider is blocked by budget, switching to another does NOT bypass it.
     */
    public static function checkProviderSwitchBudget(
        PDO $db,
        int $originalProviderId,
        int $newProviderId,
        int $modelId,
        array $context = []
    ): array {
        $result = [
            'allowed' => true,
            'reason' => '',
        ];

        // Check if the original provider was blocked by budget
        $originalCheck = AIGovernanceService::checkCostControls(
            $db,
            $context['feature'] ?? 'ai_chat',
            $context['user_id'] ?? ($_SESSION['user_id'] ?? 0),
            $context['company_id'] ?? ($_SESSION['company_id'] ?? 1),
            $originalProviderId,
            0
        );

        if (!$originalCheck['allowed']) {
            // If blocked by a per-model or per-provider limit, block the switch
            if (in_array($originalCheck['control_type'] ?? '', ['per_model_daily', 'per_provider_daily'])) {
                $result['allowed'] = false;
                $result['reason'] = 'Budget limit reached for this provider/model — switching providers does not bypass budget controls';
                return $result;
            }
        }

        return $result;
    }

    /**
     * Get cost summary for a user/company.
     */
    public static function getCostSummary(PDO $db, int $companyId, int $userId = 0): array
    {
        try {
            $today = date('Y-m-d');
            $monthStart = date('Y-m-01');

            // Today's cost
            $stmt = $db->prepare(
                "SELECT COALESCE(SUM(estimated_cost), 0) FROM ai_usage_logs
                 WHERE company_id = ? AND created_at >= ? AND status = 'success'"
            );
            $stmt->execute([$companyId, $today . ' 00:00:00']);
            $todayCost = (float) $stmt->fetchColumn();

            // This month's cost
            $stmt->execute([$companyId, $monthStart . ' 00:00:00']);
            $monthCost = (float) $stmt->fetchColumn();

            // Cost by provider
            $stmt = $db->prepare(
                "SELECT p.name as provider_name, COALESCE(SUM(u.estimated_cost), 0) as cost
                 FROM ai_usage_logs u
                 JOIN ai_providers p ON u.provider_id = p.id
                 WHERE u.company_id = ? AND u.created_at >= ? AND u.status = 'success'
                 GROUP BY u.provider_id, p.name
                 ORDER BY cost DESC"
            );
            $stmt->execute([$companyId, $monthStart . ' 00:00:00']);
            $byProvider = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Budget limits
            $controls = AIGovernanceService::getCostControlStatuses($db, $companyId);

            return [
                'today_cost' => round($todayCost, 4),
                'month_cost' => round($monthCost, 4),
                'by_provider' => $byProvider,
                'controls' => $controls,
            ];
        } catch (\Throwable $e) {
            return [
                'today_cost' => 0,
                'month_cost' => 0,
                'by_provider' => [],
                'controls' => [],
            ];
        }
    }
}
