<?php
/**
 * AI F24 — Tool Authorization Service.
 *
 * Centralizes tool authorization checks before every tool execution.
 * AI must never directly invoke an unregistered function.
 *
 * Covers Section 9: AI Tool Authorization.
 */
class AIToolAuthorizationService
{
    /**
     * Full authorization check before tool execution.
     *
     * Implements the authorization pipeline:
     * Tool Exists → Tool Enabled → User Authorized → Company Policy Allows →
     * Risk Accepted → Parameters Valid → Execute
     *
     * @param PDO    $db        Database connection
     * @param string $toolName  Tool or action name
     * @param array  $toolArgs  Tool arguments
     * @param int    $userId    User ID
     * @param int    $companyId Company ID
     * @param array  $permissions User permissions
     * @return array Authorization result
     */
    public static function authorize(
        PDO $db,
        string $toolName,
        array $toolArgs,
        int $userId,
        int $companyId,
        array $permissions = []
    ): array {
        $result = [
            'authorized' => true,
            'decision' => 'allow',
            'requires_confirmation' => false,
            'requires_review' => false,
            'reason' => '',
            'checks' => [],
        ];

        // Step 1: Tool exists
        $toolCheck = self::checkToolExists($toolName);
        $result['checks']['tool_exists'] = $toolCheck;
        if (!$toolCheck['passed']) {
            $result['authorized'] = false;
            $result['decision'] = 'deny';
            $result['reason'] = $toolCheck['reason'];
            self::logUnauthorizedTool($db, $toolName, $userId, $companyId, 'tool_not_found');
            return $result;
        }

        // Step 2: Tool enabled
        $enabledCheck = self::checkToolEnabled($db, $toolName, $companyId);
        $result['checks']['tool_enabled'] = $enabledCheck;
        if (!$enabledCheck['passed']) {
            $result['authorized'] = false;
            $result['decision'] = 'deny';
            $result['reason'] = $enabledCheck['reason'];
            self::logUnauthorizedTool($db, $toolName, $userId, $companyId, 'tool_disabled');
            return $result;
        }

        // Step 3: User authorized
        $permCheck = self::checkUserAuthorization($toolName, $permissions);
        $result['checks']['user_authorized'] = $permCheck;
        if (!$permCheck['passed']) {
            $result['authorized'] = false;
            $result['decision'] = 'deny';
            $result['reason'] = $permCheck['reason'];
            self::logUnauthorizedTool($db, $toolName, $userId, $companyId, 'permission_denied');
            return $result;
        }

        // Step 4: Company policy allows
        $policyCheck = self::checkCompanyPolicy($db, $toolName, $companyId, $userId);
        $result['checks']['company_policy'] = $policyCheck;
        if (!$policyCheck['passed']) {
            $result['authorized'] = false;
            $result['decision'] = 'deny';
            $result['reason'] = $policyCheck['reason'];
            return $result;
        }

        // Step 5: Risk accepted
        $riskCheck = self::checkRiskAccepted($db, $toolName, $companyId, $userId);
        $result['checks']['risk_accepted'] = $riskCheck;
        if (!$riskCheck['passed']) {
            $result['authorized'] = false;
            $result['decision'] = 'deny';
            $result['reason'] = $riskCheck['reason'];
            return $result;
        }

        // Step 6: Parameters valid
        $paramCheck = self::checkParametersValid($toolName, $toolArgs);
        $result['checks']['parameters_valid'] = $paramCheck;
        if (!$paramCheck['passed']) {
            $result['authorized'] = false;
            $result['decision'] = 'deny';
            $result['reason'] = $paramCheck['reason'];
            return $result;
        }

        // Check for confirmation/review requirements from policy engine
        if (class_exists('AIPolicyEngine')) {
            $policyResult = AIPolicyEngine::evaluate($db, 'tool_execution', [
                'user_id' => $userId,
                'company_id' => $companyId,
                'tool_name' => $toolName,
                'tool_args' => $toolArgs,
            ]);

            if ($policyResult['decision'] === 'deny') {
                $result['authorized'] = false;
                $result['decision'] = 'deny';
                $result['reason'] = $policyResult['reason'];
                return $result;
            }

            if ($policyResult['requires_confirmation']) {
                $result['requires_confirmation'] = true;
            }

            if ($policyResult['requires_review']) {
                $result['requires_review'] = true;
            }
        }

        return $result;
    }

    /**
     * Check if a tool exists (is registered).
     */
    private static function checkToolExists(string $toolName): array
    {
        // Check tool registry
        if (class_exists('AIToolRegistry')) {
            $tool = AIToolRegistry::get($toolName);
            if ($tool) {
                return ['passed' => true, 'type' => 'tool', 'reason' => ''];
            }
        }

        // Check action registry
        if (class_exists('AIActionRegistry')) {
            $action = AIActionRegistry::get($toolName);
            if ($action) {
                return ['passed' => true, 'type' => 'action', 'reason' => ''];
            }
        }

        return ['passed' => false, 'type' => null, 'reason' => "Tool '$toolName' is not registered"];
    }

    /**
     * Check if a tool is enabled.
     */
    private static function checkToolEnabled(PDO $db, string $toolName, int $companyId): array
    {
        try {
            $stmt = $db->prepare(
                "SELECT is_active FROM ai_tool_configs
                 WHERE tool_name = ? AND (company_id IS NULL OR company_id = ?)
                 LIMIT 1"
            );
            $stmt->execute([$toolName, $companyId]);
            $config = $stmt->fetch();

            if ($config && !$config['is_active']) {
                return ['passed' => false, 'reason' => "Tool '$toolName' is disabled"];
            }
        } catch (\Throwable $e) {
            // If config table doesn't exist, assume enabled
        }

        return ['passed' => true, 'reason' => ''];
    }

    /**
     * Check if user has required permissions.
     */
    private static function checkUserAuthorization(string $toolName, array $permissions): array
    {
        // Check tool-specific permissions
        $requiredPerm = 'ai.tools.use';
        if (class_exists('AIToolRegistry')) {
            $tool = AIToolRegistry::get($toolName);
            if ($tool && !empty($tool['required_permissions'])) {
                foreach ($tool['required_permissions'] as $perm) {
                    if (!in_array($perm, $permissions)) {
                        return ['passed' => false, 'reason' => "Missing permission: $perm"];
                    }
                }
                return ['passed' => true, 'reason' => ''];
            }
        }

        // Default check
        if (!in_array($requiredPerm, $permissions) && !in_array('ai.settings.manage', $permissions)) {
            return ['passed' => false, 'reason' => 'Missing tool execution permission'];
        }

        return ['passed' => true, 'reason' => ''];
    }

    /**
     * Check company policy.
     */
    private static function checkCompanyPolicy(PDO $db, string $toolName, int $companyId, int $userId): array
    {
        if (class_exists('AIPolicyEngine')) {
            $evaluation = AIPolicyEngine::evaluate($db, 'tool_usage', [
                'user_id' => $userId,
                'company_id' => $companyId,
                'tool_name' => $toolName,
            ]);

            if ($evaluation['decision'] === 'deny') {
                return ['passed' => false, 'reason' => $evaluation['reason'] ?: 'Tool blocked by company policy'];
            }
        }

        return ['passed' => true, 'reason' => ''];
    }

    /**
     * Check if risk is within acceptable level.
     */
    private static function checkRiskAccepted(PDO $db, string $toolName, int $companyId, int $userId): array
    {
        if (class_exists('AIRiskClassifier')) {
            $risk = AIRiskClassifier::classify($db, $toolName, ['tool_name' => $toolName], $companyId);

            if ($risk['level'] === 'critical') {
                // Critical risk tools need review
                return ['passed' => false, 'reason' => "Tool '$toolName' classified as critical risk — review required"];
            }
        }

        return ['passed' => true, 'reason' => ''];
    }

    /**
     * Check if parameters are valid.
     */
    private static function checkParametersValid(string $toolName, array $args): array
    {
        // Check for dangerous parameter values
        $dangerous = ['__proto__', 'constructor', 'prototype'];

        foreach ($args as $key => $value) {
            if (in_array($key, $dangerous)) {
                return ['passed' => false, 'reason' => "Blocked dangerous parameter key: $key"];
            }

            if (is_string($value)) {
                // Check for SQL injection in parameters
                if (class_exists('AIParameterValidator')) {
                    $result = AIParameterValidator::validate([$key => $value], [
                        $key => ['type' => 'string', 'block_invalid_type' => true],
                    ]);

                    if (!$result['valid']) {
                        return ['passed' => false, 'reason' => "Invalid parameter '$key': " . implode(', ', $result['errors'])];
                    }
                }
            }
        }

        return ['passed' => true, 'reason' => ''];
    }

    /**
     * Log unauthorized tool access attempt.
     */
    private static function logUnauthorizedTool(PDO $db, string $toolName, int $userId, int $companyId, string $reason): void
    {
        try {
            if (class_exists('AISecurityEventService')) {
                AISecurityEventService::recordUnauthorizedTool($db, [
                    'tool_name' => $toolName,
                    'user_id' => $userId,
                    'company_id' => $companyId,
                    'reason' => $reason,
                ]);
            }
        } catch (\Throwable $e) {
            // Non-critical
        }
    }
}
