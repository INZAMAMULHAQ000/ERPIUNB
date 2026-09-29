<?php
/**
 * AI F24 — Governance Middleware.
 *
 * Reusable governance guard for all AI operations.
 * Implements defense-in-depth: UI → Controller → Governance → Service → Tool → Database.
 *
 * Covers Sections 44 (Governance Middleware) and 45 (Defense in Depth).
 */
class AIGovernanceMiddleware
{
    /**
     * Full governance check for any AI operation.
     * This is the single entry point for all governance enforcement.
     *
     * @param PDO    $db        Database connection
     * @param string $feature   Feature being accessed
     * @param string $operation Specific operation name
     * @param array  $context   Full operation context
     * @return array Governance decision
     */
    public static function enforce(PDO $db, string $feature, string $operation, array $context = []): array
    {
        $result = [
            'allowed' => true,
            'decision' => 'allow',
            'requires_confirmation' => false,
            'requires_review' => false,
            'masking_required' => false,
            'provider_restricted' => false,
            'reason' => '',
            'checks' => [],
            'context' => $context,
        ];

        $userId = $context['user_id'] ?? ($_SESSION['user_id'] ?? 0);
        $companyId = $context['company_id'] ?? ($_SESSION['company_id'] ?? 1);

        // ═══ CHECK 1: Emergency Controls (Section 35) ═══
        if (class_exists('AIEmergencyControlsService')) {
            $emergencyCheck = self::checkEmergencyControls($db, $feature);
            $result['checks']['emergency_controls'] = $emergencyCheck;
            if (!$emergencyCheck['passed']) {
                $result['allowed'] = false;
                $result['decision'] = 'deny';
                $result['reason'] = $emergencyCheck['reason'];
                return $result;
            }
        }

        // ═══ CHECK 2: Central Policy Engine (Section 2) ═══
        if (class_exists('AIPolicyEngine')) {
            $policyResult = AIPolicyEngine::evaluate($db, $operation, $context);
            $result['checks']['policy_engine'] = [
                'passed' => $policyResult['decision'] !== 'deny',
                'decision' => $policyResult['decision'],
                'reason' => $policyResult['reason'],
            ];

            if ($policyResult['decision'] === 'deny') {
                $result['allowed'] = false;
                $result['decision'] = 'deny';
                $result['reason'] = $policyResult['reason'];
                self::recordViolation($db, $userId, $companyId, $operation, 'policy_denied', $context);
                return $result;
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
        }

        // ═══ CHECK 3: Kill Switch / Feature Switch (F16) ═══
        if (class_exists('AIGovernanceService') && class_exists('AIKillSwitchService')) {
            $switchKey = AIGovernanceService::switchForFeature($feature);
            if ($switchKey && !AIKillSwitchService::isEnabled($db, $switchKey)) {
                $result['allowed'] = false;
                $result['decision'] = 'deny';
                $result['reason'] = "AI feature '$feature' is currently disabled";
                $result['checks']['kill_switch'] = ['passed' => false, 'reason' => $result['reason']];
                return $result;
            }
            $result['checks']['kill_switch'] = ['passed' => true, 'reason' => ''];
        }

        // ═══ CHECK 4: Rate Limiting (Section 33) ═══
        if (class_exists('AIGovernanceService')) {
            $rateCheck = AIGovernanceService::consumeRateLimit($db, $feature, $userId, $companyId);
            $result['checks']['rate_limit'] = [
                'passed' => $rateCheck['allowed'],
                'reason' => $rateCheck['allowed'] ? '' : 'Rate limit exceeded',
            ];
            if (!$rateCheck['allowed']) {
                $result['allowed'] = false;
                $result['decision'] = 'deny';
                $result['reason'] = 'Rate limit reached. Please try again shortly.';
                if (class_exists('AISecurityEventService')) {
                    AISecurityEventService::recordRateLimitExceeded($db, [
                        'user_id' => $userId,
                        'company_id' => $companyId,
                        'feature' => $feature,
                    ]);
                }
                return $result;
            }
        }

        // ═══ CHECK 5: Cost Guardrails (Section 34) ═══
        if (isset($context['estimated_cost']) && class_exists('AICostGuardrailsService')) {
            $costCheck = AICostGuardrailsService::checkBudget($db, $context['estimated_cost'], $context);
            $result['checks']['cost_guardrails'] = [
                'passed' => $costCheck['allowed'],
                'reason' => $costCheck['allowed'] ? '' : 'Budget limit reached',
            ];
            if (!$costCheck['allowed']) {
                $result['allowed'] = false;
                $result['decision'] = 'deny';
                $result['reason'] = 'Cost budget exceeded for this operation';
                return $result;
            }
        }

        // ═══ CHECK 6: Autonomy Level (Section 24) ═══
        if (class_exists('AIPolicyEngine') && isset($context['action_type'])) {
            $autonomy = AIPolicyEngine::getAutonomyLevel($db, $companyId, $context['role_id'] ?? null, $userId);
            $autonomyCheck = self::checkAutonomyLevel($autonomy, $context);
            $result['checks']['autonomy'] = $autonomyCheck;

            if (!$autonomyCheck['passed']) {
                $result['allowed'] = false;
                $result['decision'] = 'deny';
                $result['reason'] = $autonomyCheck['reason'];
                return $result;
            }

            if ($autonomy === 'confirm' && isset($context['action_type']) && $context['action_type'] !== 'read') {
                $result['requires_confirmation'] = true;
            }

            if ($autonomy === 'restricted') {
                if (isset($context['action_type']) && $context['action_type'] !== 'read') {
                    $result['allowed'] = false;
                    $result['decision'] = 'deny';
                    $result['reason'] = 'AI is restricted to read-only operations';
                    return $result;
                }
            }
        }

        // ═══ CHECK 7: Bulk Action Protection (Section 27) ═══
        if (isset($context['record_count']) && class_exists('AIBulkActionService')) {
            $bulkCheck = AIBulkActionService::checkBulkAction(
                $db,
                $operation,
                (int) $context['record_count'],
                $context['risk_level'] ?? 'low',
                $companyId
            );
            $result['checks']['bulk_action'] = [
                'passed' => $bulkCheck['allowed'],
                'action' => $bulkCheck['action'],
                'reason' => $bulkCheck['reason'],
            ];

            if (!$bulkCheck['allowed']) {
                $result['allowed'] = false;
                $result['decision'] = 'deny';
                $result['reason'] = $bulkCheck['reason'];
                return $result;
            }

            if ($bulkCheck['requires_confirmation']) {
                $result['requires_confirmation'] = true;
            }
            if ($bulkCheck['requires_review']) {
                $result['requires_review'] = true;
            }
        }

        // ═══ CHECK 8: Financial Protection (Section 28) ═══
        if (isset($context['amount']) && class_exists('AIFinancialProtectionService')) {
            $finCheck = AIFinancialProtectionService::checkFinancialAction(
                $db,
                $operation,
                (float) $context['amount'],
                $context
            );
            $result['checks']['financial_protection'] = [
                'passed' => $finCheck['allowed'],
                'action' => $finCheck['action'],
                'reason' => $finCheck['reason'],
            ];

            if (!$finCheck['allowed']) {
                $result['allowed'] = false;
                $result['decision'] = 'deny';
                $result['reason'] = $finCheck['reason'];
                return $result;
            }

            if ($finCheck['requires_confirmation']) {
                $result['requires_confirmation'] = true;
            }
            if ($finCheck['requires_review']) {
                $result['requires_review'] = true;
            }
        }

        // ═══ CHECK 9: Prompt Injection Detection (Section 19) ═══
        if (isset($context['message']) && class_exists('AIInjectionDetectionService')) {
            $injectionCheck = AIInjectionDetectionService::analyze(
                $db,
                $context['message'],
                AIInjectionDetectionService::TRUST_USER_INPUT,
                $context
            );
            $result['checks']['injection_detection'] = [
                'passed' => $injectionCheck['safe'],
                'action' => $injectionCheck['action'],
            ];

            if ($injectionCheck['action'] === 'block') {
                $result['allowed'] = false;
                $result['decision'] = 'deny';
                $result['reason'] = 'Content blocked by security policy';
                return $result;
            }
        }

        // ═══ CHECK 10: Secret Protection (Section 16) ═══
        if (class_exists('AISecretProtectionService')) {
            $secretCheck = AISecretProtectionService::validateServerSideExecution($operation, $context);
            $result['checks']['secret_protection'] = [
                'passed' => $secretCheck['safe'],
                'warnings' => $secretCheck['warnings'],
            ];
        }

        // Determine final decision
        if ($result['requires_review']) {
            $result['decision'] = 'review';
        } elseif ($result['requires_confirmation']) {
            $result['decision'] = 'confirm';
        }

        return $result;
    }

    /**
     * Check emergency controls for a feature.
     */
    private static function checkEmergencyControls(PDO $db, string $feature): array
    {
        $controlMap = [
            'ai_chat' => 'disable_all_ai',
            'tool_calling' => 'disable_all_ai',
            'action' => 'disable_ai_actions',
            'write_action' => 'disable_ai_actions',
            'agent' => 'disable_autonomous_agents',
            'agents' => 'disable_autonomous_agents',
            'provider' => 'disable_external_providers',
            'integration' => 'disable_external_integrations',
            'document' => 'disable_document_ai',
            'voice' => 'disable_voice',
            'workflow' => 'disable_workflows',
            'knowledge' => 'disable_knowledge_ai',
            'insight' => 'disable_insights',
        ];

        $controlKey = $controlMap[$feature] ?? null;
        if ($controlKey && AIEmergencyControlsService::isControlActive($db, $controlKey)) {
            return ['passed' => false, 'reason' => "Emergency control active: $controlKey"];
        }

        return ['passed' => true, 'reason' => ''];
    }

    /**
     * Check autonomy level allows the operation.
     */
    private static function checkAutonomyLevel(string $autonomy, array $context): array
    {
        $actionType = $context['action_type'] ?? 'read';

        // Restricted: read-only
        if ($autonomy === 'restricted' && $actionType !== 'read') {
            return ['passed' => false, 'reason' => 'Autonomy level restricted to read-only'];
        }

        // Assist: no actions
        if ($autonomy === 'assist' && $actionType !== 'read' && $actionType !== 'suggest') {
            return ['passed' => false, 'reason' => 'Autonomy level "assist" does not allow write actions'];
        }

        return ['passed' => true, 'reason' => ''];
    }

    /**
     * Record a policy violation for security events.
     */
    private static function recordViolation(PDO $db, int $userId, int $companyId, string $operation, string $type, array $context): void
    {
        try {
            if (class_exists('AISecurityEventService')) {
                AISecurityEventService::recordPolicyViolation($db, [
                    'user_id' => $userId,
                    'company_id' => $companyId,
                    'operation' => $operation,
                    'violation_type' => $type,
                ]);
            }
        } catch (\Throwable $e) {
            // Non-critical
        }
    }

    /**
     * Convenience: enforce and return a simple allowed/denied boolean.
     */
    public static function isAllowed(PDO $db, string $feature, string $operation, array $context = []): bool
    {
        $result = self::enforce($db, $feature, $operation, $context);
        return $result['allowed'];
    }

    /**
     * Convenience: enforce and return a response for denied requests.
     */
    public static function guardOrDeny(PDO $db, string $feature, string $operation, array $context = []): ?array
    {
        $result = self::enforce($db, $feature, $operation, $context);
        if ($result['allowed']) {
            return null; // No denial
        }

        return [
            'success' => false,
            'error' => $result['reason'] ?: 'Operation denied by governance policy',
            'decision' => $result['decision'],
        ];
    }
}
