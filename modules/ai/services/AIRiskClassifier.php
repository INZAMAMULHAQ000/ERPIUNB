<?php
/**
 * AI F24 — Risk Classification Service.
 *
 * Centralized risk classifier for all AI operations.
 * Deterministic risk assessment based on configurable rules.
 */
class AIRiskClassifier
{
    const RISK_LOW = 'low';
    const RISK_MEDIUM = 'medium';
    const RISK_HIGH = 'high';
    const RISK_CRITICAL = 'critical';

    private static array $cache = [];
    private static array $rules = [];

    /**
     * Initialize risk classification rules from database.
     */
    public static function initialize(PDO $db): void
    {
        self::loadRules($db);
    }

    /**
     * Load risk rules from database.
     */
    private static function loadRules(PDO $db): void
    {
        try {
            $stmt = $db->query("SELECT * FROM ai_risk_rules WHERE is_active = 1 ORDER BY priority ASC");
            self::$rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            self::$rules = self::getDefaultRules();
        }
    }

    /**
     * Get default risk rules if database tables are not ready.
     */
    private static function getDefaultRules(): array
    {
        return [
            // Low risk
            ['rule_key' => 'search', 'name' => 'Search', 'category' => 'action', 'level' => self::RISK_LOW, 'match_type' => 'keyword', 'match_value' => 'search', 'priority' => 1],
            ['rule_key' => 'navigation', 'name' => 'Navigation', 'category' => 'action', 'level' => self::RISK_LOW, 'match_type' => 'keyword', 'match_value' => 'navigate', 'priority' => 1],
            ['rule_key' => 'summarization', 'name' => 'Summarization', 'category' => 'action', 'level' => self::RISK_LOW, 'match_type' => 'keyword', 'match_value' => 'summarize', 'priority' => 1],
            ['rule_key' => 'drafting', 'name' => 'Drafting', 'category' => 'action', 'level' => self::RISK_LOW, 'match_type' => 'keyword', 'match_value' => 'draft', 'priority' => 1],
            // Medium risk
            ['rule_key' => 'create_task', 'name' => 'Create Task', 'category' => 'action', 'level' => self::RISK_MEDIUM, 'match_type' => 'keyword', 'match_value' => 'create task', 'priority' => 2],
            ['rule_key' => 'send_notification', 'name' => 'Send Notification', 'category' => 'action', 'level' => self::RISK_MEDIUM, 'match_type' => 'keyword', 'match_value' => 'notify', 'priority' => 2],
            ['rule_key' => 'create_draft_record', 'name' => 'Create Draft Record', 'category' => 'action', 'level' => self::RISK_MEDIUM, 'match_type' => 'keyword', 'match_value' => 'create draft', 'priority' => 2],
            // High risk
            ['rule_key' => 'financial_change', 'name' => 'Financial Change', 'category' => 'action', 'level' => self::RISK_HIGH, 'match_type' => 'keyword', 'match_value' => 'financial|payment|invoice|purchase', 'priority' => 3],
            ['rule_key' => 'external_communication', 'name' => 'External Communication', 'category' => 'action', 'level' => self::RISK_HIGH, 'match_type' => 'keyword', 'match_value' => 'email.*to|send.*external|message.*external', 'priority' => 3],
            ['rule_key' => 'bulk_modification', 'name' => 'Bulk Modification', 'category' => 'action', 'level' => self::RISK_HIGH, 'match_type' => 'keyword', 'match_value' => 'bulk|mass|multiple.*records', 'priority' => 3],
            ['rule_key' => 'external_write', 'name' => 'External Write', 'category' => 'action', 'level' => self::RISK_HIGH, 'match_type' => 'keyword', 'match_value' => 'write.*external|export|sync', 'priority' => 3],
            // Critical risk
            ['rule_key' => 'destructive_action', 'name' => 'Destructive Action', 'category' => 'action', 'level' => self::RISK_CRITICAL, 'match_type' => 'keyword', 'match_value' => 'delete|remove|destroy|drop', 'priority' => 4],
            ['rule_key' => 'mass_deletion', 'name' => 'Mass Deletion', 'category' => 'action', 'level' => self::RISK_CRITICAL, 'match_type' => 'keyword', 'match_value' => 'delete.*all|mass delete|purge', 'priority' => 4],
            ['rule_key' => 'security_config', 'name' => 'Security Configuration', 'category' => 'action', 'level' => self::RISK_CRITICAL, 'match_type' => 'keyword', 'match_value' => 'security|permission|credential|access control', 'priority' => 4],
            ['rule_key' => 'permission_change', 'name' => 'Permission Change', 'category' => 'action', 'level' => self::RISK_CRITICAL, 'match_type' => 'keyword', 'match_value' => 'grant|revoke|permission', 'priority' => 4],
            ['rule_key' => 'credential_operation', 'name' => 'Credential Operation', 'category' => 'action', 'level' => self::RISK_CRITICAL, 'match_type' => 'keyword', 'match_value' => 'password|api.key|token|secret', 'priority' => 4],
        ];
    }

    /**
     * Classify the risk level of an action based on input and context.
     *
     * @param PDO $db Database connection
     * @param string $action The action to classify
     * @param array $context Context data including parameters, target records, etc.
     * @param int|null $companyId Company ID for scope-specific classification
     * @return array Risk classification result
     */
    public static function classify(PDO $db, string $action, array $context = [], ?int $companyId = null): array
    {
        $cacheKey = self::getCacheKey($action, $context, $companyId);

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $classification = self::determineRisk($db, $action, $context, $companyId);
        self::$cache[$cacheKey] = $classification;

        // Log for audit if critical
        if ($classification['level'] === self::RISK_CRITICAL) {
            self::logCriticalClassification($db, $action, $classification, $context);
        }

        return $classification;
    }

    /**
     * Determine the risk level based on rules and context.
     */
    private static function determineRisk(PDO $db, string $action, array $context, ?int $companyId): array
    {
        // Ensure rules are loaded
        if (empty(self::$rules)) {
            self::initialize($db);
        }

        $actionLower = strtolower($action);
        $inputText = self::buildInputText($action, $context);
        $maxRisk = self::RISK_LOW;
        $matchedRules = [];

        // Check each active rule
        foreach (self::$rules as $rule) {
            if (!self::matchesRule($rule, $inputText, $actionLower)) {
                continue;
            }

            $matchedRules[] = $rule['rule_key'];

            // Higher priority rules take precedence (higher priority number = more specific)
            if ($rule['priority'] > self::getPriorityForLevel($maxRisk)) {
                $maxRisk = $rule['level'];
            }
        }

        // Check context-based risk factors
        $contextRisk = self::evaluateContextRisk($context, $companyId);
        if (self::compareRiskLevel($contextRisk['level'], $maxRisk) > 0) {
            $maxRisk = $contextRisk['level'];
            $matchedRules = array_merge($matchedRules, $contextRisk['matched_rules']);
        }

        // Check for amount-based risk escalation
        if (isset($context['amount']) || isset($context['quantity'])) {
            $amountRisk = self::evaluateAmountRisk($context);
            if (self::compareRiskLevel($amountRisk, $maxRisk) > 0) {
                $maxRisk = $amountRisk;
            }
        }

        // Check for bulk operations
        if (isset($context['record_count']) && $context['record_count'] > 100) {
            $maxRisk = self::RISK_HIGH;
        } elseif (isset($context['record_count']) && $context['record_count'] > 1000) {
            $maxRisk = self::RISK_CRITICAL;
        }

        $classification = [
            'level' => $maxRisk,
            'rules_matched' => array_unique($matchedRules),
            'confidence' => self::calculateConfidence($matchedRules, $context),
            'factors' => [
                'action' => $action,
                'context' => $context,
                'matched_rules' => $matchedRules,
            ],
            'requires_confirmation' => in_array($maxRisk, [self::RISK_MEDIUM, self::RISK_HIGH, self::RISK_CRITICAL]),
            'requires_review' => in_array($maxRisk, [self::RISK_CRITICAL]),
        ];

        return $classification;
    }

    /**
     * Check if a rule matches the input.
     */
    private static function matchesRule(array $rule, string $inputText, string $actionLower): bool
    {
        $matchType = $rule['match_type'] ?? 'keyword';
        $matchValue = strtolower($rule['match_value'] ?? '');

        switch ($matchType) {
            case 'keyword':
                return strpos($inputText, $matchValue) !== false || strpos($actionLower, $matchValue) !== false;
            case 'pattern':
                return (bool) preg_match('/' . $matchValue . '/i', $inputText);
            case 'exact':
                return strtolower($inputText) === $matchValue || $actionLower === $matchValue;
            default:
                return false;
        }
    }

    /**
     * Build input text from action and context.
     */
    private static function buildInputText(string $action, array $context): string
    {
        $parts = [strtolower($action)];

        if (!empty($context['description'])) {
            $parts[] = strtolower($context['description']);
        }
        if (!empty($context['intent'])) {
            $parts[] = strtolower($context['intent']);
        }
        if (!empty($context['target'])) {
            $parts[] = strtolower(implode(' ', (array) $context['target']));
        }

        return implode(' ', $parts);
    }

    /**
     * Evaluate context-based risk factors.
     */
    private static function evaluateContextRisk(array $context, ?int $companyId): array
    {
        $risk = self::RISK_LOW;
        $matchedRules = [];

        // Check if target is sensitive
        if (!empty($context['data_classification'])) {
            $classification = strtolower($context['data_classification']);
            if (in_array($classification, ['confidential', 'sensitive', 'restricted'])) {
                $risk = self::RISK_HIGH;
                $matchedRules[] = 'sensitive_data_access';
            }
        }

        // Check for external destinations
        if (!empty($context['external_destination']) || !empty($context['external_url'])) {
            $risk = self::RISK_HIGH;
            $matchedRules[] = 'external_destination';
        }

        // Check for credential-related actions
        if (!empty($context['credential']) || !empty($context['auth'])) {
            $risk = self::RISK_CRITICAL;
            $matchedRules[] = 'credential_access';
        }

        // Check for security-related actions
        if (!empty($context['security']) || !empty($context['permission'])) {
            $risk = self::RISK_CRITICAL;
            $matchedRules[] = 'security_operation';
        }

        return [
            'level' => $risk,
            'matched_rules' => $matchedRules,
        ];
    }

    /**
     * Evaluate risk based on amounts.
     */
    private static function evaluateAmountRisk(array $context): string
    {
        $amount = $context['amount'] ?? $context['quantity'] ?? 0;

        if ($amount > 10000) {
            return self::RISK_CRITICAL;
        } elseif ($amount > 1000) {
            return self::RISK_HIGH;
        } elseif ($amount > 100) {
            return self::RISK_MEDIUM;
        }

        return self::RISK_LOW;
    }

    /**
     * Compare two risk levels.
     */
    private static function compareRiskLevel(string $level1, string $level2): int
    {
        $order = [self::RISK_LOW => 1, self::RISK_MEDIUM => 2, self::RISK_HIGH => 3, self::RISK_CRITICAL => 4];
        return ($order[$level1] ?? 0) - ($order[$level2] ?? 0);
    }

    /**
     * Get priority number for a risk level.
     */
    private static function getPriorityForLevel(string $level): int
    {
        $priorities = [
            self::RISK_LOW => 1,
            self::RISK_MEDIUM => 2,
            self::RISK_HIGH => 3,
            self::RISK_CRITICAL => 4,
        ];
        return $priorities[$level] ?? 0;
    }

    /**
     * Calculate confidence score for classification.
     */
    private static function calculateConfidence(array $matchedRules, array $context): float
    {
        if (empty($matchedRules)) {
            return 0.5; // Default medium confidence
        }

        // More matched rules = higher confidence
        $baseConfidence = 0.7;
        $ruleBonus = min(count($matchedRules) * 0.1, 0.2);

        return min($baseConfidence + $ruleBonus, 1.0);
    }

    /**
     * Log critical classifications for audit.
     */
    private static function logCriticalClassification(PDO $db, string $action, array $classification, array $context): void
    {
        try {
            $stmt = $db->prepare("
                INSERT INTO ai_security_events
                    (event_key, event_type, severity, company_id, event_category, event_data_json, status)
                VALUES (?, 'risk_classification', 'high', ?, 'risk_classification', ?, 'open')
            ");
            $stmt->execute([
                'risk_critical_' . uniqid(),
                $_SESSION['company_id'] ?? 1,
                json_encode([
                    'action' => $action,
                    'classification' => $classification['level'],
                    'matched_rules' => $classification['rules_matched'],
                    'context' => self::safePayload($context),
                ])
            ]);
        } catch (\Throwable $e) {
            // Log failure silently - should not block operation
        }
    }

    /**
     * Generate cache key.
     */
    private static function getCacheKey(string $action, array $context, ?int $companyId): string
    {
        $contextHash = md5(json_encode($context));
        return $action . ':' . ($companyId ?? 'global') . ':' . $contextHash;
    }

    /**
     * Sanitize payload for logging.
     */
    private static function safePayload(array $payload): array
    {
        $sensitiveKeys = ['password', 'secret', 'api_key', 'token', 'authorization'];
        $safe = [];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $safe[$key] = '[REDACTED]';
            } else {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }

    /**
     * Get human-readable risk description.
     */
    public static function getRiskDescription(string $level): string
    {
        $descriptions = [
            self::RISK_LOW => 'Low risk - minimal impact',
            self::RISK_MEDIUM => 'Medium risk - requires confirmation',
            self::RISK_HIGH => 'High risk - requires review',
            self::RISK_CRITICAL => 'Critical risk - restricted access',
        ];

        return $descriptions[$level] ?? 'Unknown risk level';
    }

    /**
     * Clear classification cache.
     */
    public static function clearCache(): void
    {
        self::$cache = [];
        self::$rules = [];
    }

    /**
     * Get risk escalation path based on risk level.
     */
    public static function getEscalationPath(string $riskLevel): array
    {
        $paths = [
            self::RISK_LOW => ['action' => 'execute', 'requires' => []],
            self::RISK_MEDIUM => ['action' => 'confirm', 'requires' => ['confirmation']],
            self::RISK_HIGH => ['action' => 'confirm_review', 'requires' => ['confirmation', 'review']],
            self::RISK_CRITICAL => ['action' => 'deny_or_review', 'requires' => ['review', 'approval']],
        ];

        return $paths[$riskLevel] ?? ['action' => 'confirm', 'requires' => ['confirmation']];
    }

    /**
     * Register a custom risk classification rule.
     */
    public static function registerRule(PDO $db, array $rule): bool
    {
        try {
            $stmt = $db->prepare("
                INSERT INTO ai_risk_rules
                    (rule_name, rule_key, category, level, match_type, match_value, priority)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $rule['name'],
                $rule['rule_key'],
                $rule['category'] ?? 'action',
                $rule['level'] ?? self::RISK_LOW,
                $rule['match_type'] ?? 'keyword',
                $rule['match_value'] ?? '',
                $rule['priority'] ?? 0,
            ]);

            self::clearCache();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}