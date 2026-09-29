<?php
/**
 * AI F24 — Central Policy Engine.
 *
 * Centralized policy evaluation for all AI operations.
 * Every AI operation MUST pass through this engine.
 */
class AIPolicyEngine
{
    /**
     * Evaluate all policies for an AI operation.
     *
     * @param PDO $db Database connection
     * @param string $action The action to evaluate
     * @param array $context Context including user, company, risk level, etc.
     * @return array Decision and evaluation details
     */
    public static function evaluate(PDO $db, string $action, array $context = []): array
    {
        $result = [
            'action' => $action,
            'decision' => 'allow',
            'policies_matched' => [],
            'risk_level' => 'low',
            'requires_confirmation' => false,
            'requires_review' => false,
            'masking_required' => false,
            'provider_restricted' => false,
            'reason' => '',
            'details' => [],
        ];

        try {
            // 1. Get user context
            $userId = $context['user_id'] ?? ($_SESSION['user_id'] ?? null);
            $companyId = $context['company_id'] ?? ($_SESSION['company_id'] ?? null);
            $userRole = $context['user_role'] ?? null;

            // 2. Classify risk
            if (class_exists('AIRiskClassifier')) {
                $riskResult = AIRiskClassifier::classify($db, $action, $context, $companyId);
                $result['risk_level'] = $riskResult['level'];
                $result['requires_confirmation'] = $riskResult['requires_confirmation'];
                $result['requires_review'] = $riskResult['requires_review'];
            }

            // 3. Get applicable policies
            $policies = self::getApplicablePolicies($db, $action, $context, $companyId, $userRole);

            // 4. Evaluate each policy
            foreach ($policies as $policy) {
                $policyResult = self::evaluatePolicy($policy, $context);

                if ($policyResult['blocked']) {
                    $result['decision'] = 'deny';
                    $result['reason'] = $policyResult['reason'];
                    $result['policies_matched'][] = $policy;
                    break;
                }

                if ($policyResult['requires_confirmation']) {
                    $result['requires_confirmation'] = true;
                }
                if ($policyResult['requires_review']) {
                    $result['requires_review'] = true;
                }
                if ($policyResult['masking_required']) {
                    $result['masking_required'] = true;
                }
                if ($policyResult['provider_restricted']) {
                    $result['provider_restricted'] = true;
                }

                $result['policies_matched'][] = $policy;
                $result['details'][] = $policyResult;
            }

            // 5. Determine final decision
            $result['decision'] = self::determineFinalDecision($result);

            // 6. Log policy evaluation for audit
            if (class_exists('AIGovernanceAudit')) {
                AIGovernanceAudit::log($db, 'policy_evaluation', [
                    'actor_id' => $userId,
                    'target_type' => 'ai_operation',
                    'new_value' => json_encode([
                        'action' => $action,
                        'decision' => $result['decision'],
                        'risk_level' => $result['risk_level'],
                    ]),
                    'change_reason' => "Policy evaluation for $action",
                ]);
            }

        } catch (\Throwable $e) {
            // On error, default to deny for safety
            $result['decision'] = 'deny';
            $result['reason'] = 'Policy evaluation error: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Get all applicable policies for an action.
     */
    private static function getApplicablePolicies(PDO $db, string $action, array $context, ?int $companyId, ?int $userRole): array
    {
        try {
            // Build WHERE clause for policy matching
            $conditions = [];
            $params = [];

            // Active policies only
            $conditions[] = 'status = ?';
            $params[] = 'active';

            // Match by scope - most specific first
            // User-specific policies
            if ($userId = $context['user_id'] ?? null) {
                $conditions[] = '(scope = ? AND scope_id = ?) OR scope = ?';
                $params[] = 'user';
                $params[] = $userId;
                $params[] = 'user';
            }

            // Role-specific policies
            if ($userRole) {
                $conditions[] = '(scope = ? AND scope_id = ?) OR scope = ?';
                $params[] = 'role';
                $params[] = $userRole;
                $params[] = 'role';
            }

            // Company-specific policies
            if ($companyId) {
                $conditions[] = '(scope = ? AND scope_id = ?) OR scope = ?';
                $params[] = 'company';
                $params[] = $companyId;
                $params[] = 'company';
            }

            // Global policies (catch-all)
            $conditions[] = 'scope = ?';
            $params[] = 'global';

            // Type matching
            $conditions[] = 'type = ?';
            $params[] = $action;

            $whereClause = implode(' OR ', $conditions);

            $stmt = $db->prepare("
                SELECT * FROM ai_policies
                WHERE $whereClause
                ORDER BY priority DESC, scope DESC
            ");
            $stmt->execute($params);
            $policies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $policies;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Evaluate a single policy against context.
     */
    private static function evaluatePolicy(array $policy, array $context): array
    {
        $result = [
            'policy_id' => $policy['id'],
            'policy_key' => $policy['policy_key'],
            'name' => $policy['name'],
            'effect' => $policy['effect'],
            'blocked' => false,
            'requires_confirmation' => false,
            'requires_review' => false,
            'masking_required' => false,
            'provider_restricted' => false,
            'reason' => '',
        ];

        // Check conditions
        if (!self::conditionsMet($policy, $context)) {
            return $result;
        }

        switch ($policy['effect']) {
            case 'deny':
                $result['blocked'] = true;
                $result['reason'] = "Policy denied: {$policy['name']}";
                break;

            case 'require_confirmation':
                $result['requires_confirmation'] = true;
                $result['reason'] = "Confirmation required by policy: {$policy['name']}";
                break;

            case 'require_review':
                $result['requires_review'] = true;
                $result['reason'] = "Review required by policy: {$policy['name']}";
                break;

            case 'mask_data':
                $result['masking_required'] = true;
                $result['reason'] = "Data masking required by policy: {$policy['name']}";
                break;

            case 'restrict_provider':
                $result['provider_restricted'] = true;
                $result['reason'] = "Provider restricted by policy: {$policy['name']}";
                break;

            case 'log_only':
                $result['reason'] = "Logged by policy: {$policy['name']}";
                break;

            case 'allow':
                $result['reason'] = "Allowed by policy: {$policy['name']}";
                break;
        }

        return $result;
    }

    /**
     * Check if policy conditions are met.
     */
    private static function conditionsMet(array $policy, array $context): bool
    {
        $conditions = json_decode($policy['conditions_json'] ?? '{}', true);

        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $key => $value) {
            $contextValue = $context[$key] ?? null;

            if (is_array($value)) {
                if (!in_array($contextValue, $value)) {
                    return false;
                }
            } else {
                if ($contextValue !== $value) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Determine final decision based on all policy evaluations.
     */
    private static function determineFinalDecision(array $result): string
    {
        // If any policy blocked, deny
        foreach ($result['details'] as $detail) {
            if ($detail['blocked']) {
                return 'deny';
            }
        }

        // If requires_review and risk is high/critical
        if ($result['requires_review'] && in_array($result['risk_level'], ['high', 'critical'])) {
            return 'review';
        }

        // If requires_confirmation
        if ($result['requires_confirmation']) {
            return 'confirm';
        }

        // Allow by default
        return 'allow';
    }

    /**
     * Check if an action is allowed.
     */
    public static function isAllowed(PDO $db, string $action, array $context = []): bool
    {
        $evaluation = self::evaluate($db, $action, $context);
        return in_array($evaluation['decision'], ['allow', 'confirm']);
    }

    /**
     * Check if confirmation is required.
     */
    public static function requiresConfirmation(PDO $db, string $action, array $context = []): bool
    {
        $evaluation = self::evaluate($db, $action, $context);
        return $evaluation['requires_confirmation'] || $evaluation['decision'] === 'confirm';
    }

    /**
     * Check if review is required.
     */
    public static function requiresReview(PDO $db, string $action, array $context = []): bool
    {
        $evaluation = self::evaluate($db, $action, $context);
        return $evaluation['requires_review'] || $evaluation['decision'] === 'review';
    }

    /**
     * Get effective autonomy level for a user/context.
     */
    public static function getAutonomyLevel(PDO $db, ?int $companyId = null, ?int $roleId = null, ?int $userId = null): string
    {
        try {
            // Check for user-specific autonomy
            if ($userId) {
                $stmt = $db->prepare("
                    SELECT autonomy_level FROM ai_autonomy_settings
                    WHERE user_id = ? AND is_active = 1
                    ORDER BY company_id IS NULL, company_id DESC
                    LIMIT 1
                ");
                $stmt->execute([$userId]);
                $result = $stmt->fetch();
                if ($result) {
                    return $result['autonomy_level'];
                }
            }

            // Check for role-specific autonomy
            if ($roleId) {
                $stmt = $db->prepare("
                    SELECT autonomy_level FROM ai_autonomy_settings
                    WHERE role_id = ? AND company_id IS NULL AND is_active = 1
                    LIMIT 1
                ");
                $stmt->execute([$roleId]);
                $result = $stmt->fetch();
                if ($result) {
                    return $result['autonomy_level'];
                }
            }

            // Check for company-specific autonomy
            if ($companyId) {
                $stmt = $db->prepare("
                    SELECT autonomy_level FROM ai_autonomy_settings
                    WHERE company_id = ? AND role_id IS NULL AND user_id IS NULL AND is_active = 1
                    LIMIT 1
                ");
                $stmt->execute([$companyId]);
                $result = $stmt->fetch();
                if ($result) {
                    return $result['autonomy_level'];
                }
            }

            // Default
            return 'suggest';
        } catch (\Throwable $e) {
            return 'suggest';
        }
    }

    /**
     * Evaluate provider eligibility.
     */
    public static function evaluateProvider(PDO $db, int $providerId, string $dataClassification, ?int $companyId = null): array
    {
        try {
            $stmt = $db->prepare("
                SELECT * FROM ai_provider_rules
                WHERE provider_id = ?
                AND (company_id IS NULL OR company_id = ?)
                AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$providerId, $companyId]);
            $rule = $stmt->fetch();

            if (!$rule) {
                return ['allowed' => true, 'reason' => 'No specific provider rule found'];
            }

            if (!$rule['allowed']) {
                return ['allowed' => false, 'reason' => 'Provider is blocked by policy'];
            }

            if ($rule['allowed_for_classification'] && $rule['allowed_for_classification'] !== $dataClassification) {
                return ['allowed' => false, 'reason' => 'Provider not allowed for this data classification'];
            }

            return ['allowed' => true, 'reason' => 'Provider is allowed'];
        } catch (\Throwable $e) {
            return ['allowed' => true, 'reason' => 'Error evaluating provider'];
        }
    }

    /**
     * Create a new policy.
     */
    public static function createPolicy(PDO $db, array $policyData, int $createdBy): array
    {
        try {
            // Validate required fields
            $required = ['policy_key', 'name', 'type', 'scope', 'effect'];
            foreach ($required as $field) {
                if (empty($policyData[$field])) {
                    return ['success' => false, 'error' => "Missing required field: $field"];
                }
            }

            // Check for duplicate key
            $stmt = $db->prepare("SELECT id FROM ai_policies WHERE policy_key = ?");
            $stmt->execute([$policyData['policy_key']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'Policy key already exists'];
            }

            // Insert policy
            $stmt = $db->prepare("
                INSERT INTO ai_policies
                    (policy_key, name, description, type, scope, scope_id,
                     conditions_json, effect, priority, status, version, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
            ");

            $stmt->execute([
                $policyData['policy_key'],
                $policyData['name'],
                $policyData['description'] ?? null,
                $policyData['type'],
                $policyData['scope'],
                $policyData['scope_id'] ?? null,
                json_encode($policyData['conditions'] ?? []),
                $policyData['effect'],
                $policyData['priority'] ?? 0,
                $policyData['status'] ?? 'draft',
                $createdBy,
                $createdBy,
            ]);

            $policyId = (int) $db->lastInsertId();

            // Create initial version
            if (class_exists('AIPolicyVersionService')) {
                AIPolicyVersionService::createVersion($db, $policyId, 1, 'Initial version', $createdBy);
            }

            // Audit log
            if (class_exists('AIGovernanceAudit')) {
                AIGovernanceAudit::logPolicyCreate($db, $createdBy, ['id' => $policyId] + $policyData);
            }

            return [
                'success' => true,
                'id' => $policyId,
                'policy_key' => $policyData['policy_key'],
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update a policy and create a new version.
     */
    public static function updatePolicy(PDO $db, int $policyId, array $policyData, int $updatedBy): array
    {
        try {
            // Get current policy for versioning
            $stmt = $db->prepare("SELECT * FROM ai_policies WHERE id = ?");
            $stmt->execute([$policyId]);
            $currentPolicy = $stmt->fetch();

            if (!$currentPolicy) {
                return ['success' => false, 'error' => 'Policy not found'];
            }

            // Increment version
            $newVersion = $currentPolicy['version'] + 1;

            // Get previous version data
            $previousValue = [
                'conditions' => json_decode($currentPolicy['conditions_json'] ?? '{}', true),
                'effect' => $currentPolicy['effect'],
                'priority' => $currentPolicy['priority'],
                'status' => $currentPolicy['status'],
            ];

            // Update policy
            $stmt = $db->prepare("
                UPDATE ai_policies
                SET name = ?, description = ?, conditions_json = ?, effect = ?,
                    priority = ?, status = ?, version = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $policyData['name'] ?? $currentPolicy['name'],
                $policyData['description'] ?? $currentPolicy['description'],
                json_encode($policyData['conditions'] ?? json_decode($currentPolicy['conditions_json'] ?? '{}', true)),
                $policyData['effect'] ?? $currentPolicy['effect'],
                $policyData['priority'] ?? $currentPolicy['priority'],
                $policyData['status'] ?? $currentPolicy['status'],
                $newVersion,
                $updatedBy,
                $policyId,
            ]);

            // Create version record
            if (class_exists('AIPolicyVersionService')) {
                AIPolicyVersionService::createVersion($db, $policyId, $newVersion, 'Policy updated', $updatedBy, $previousValue);
            }

            // Audit log
            if (class_exists('AIGovernanceAudit')) {
                AIGovernanceAudit::logPolicyUpdate($db, $updatedBy, $policyId, $previousValue, [
                    'conditions' => $policyData['conditions'] ?? [],
                    'effect' => $policyData['effect'] ?? $currentPolicy['effect'],
                ]);
            }

            return ['success' => true, 'version' => $newVersion];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}