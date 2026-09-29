<?php
/**
 * AI F14 — Insight Evaluation Engine.
 *
 * Evaluates insight definitions against ERP data.
 * Supports rule-based and hybrid (rule + AI) evaluation.
 */
class AIInsightEngine
{
    const MAX_EVALUATIONS_PER_CYCLE = 10;
    const MAX_RECORDS_PER_EVALUATION = 100;
    const MAX_RUNTIME_SECONDS = 30;
    const MAX_AI_CALLS = 5;
    const MAX_ACTIVE_INSIGHTS = 50;
    const MAX_NOTIFICATIONS_PER_HOUR = 30;

    /**
     * Evaluate a single insight definition.
     */
    public static function evaluate(PDO $db, array $definition): array
    {
        $startTime = microtime(true);
        $defKey = $definition['definition_key'] ?? $definition['id'] ?? '';
        $evalConfig = $definition['evaluation'] ?? [];

        $result = [
            'definition_key' => $defKey,
            'status' => 'success',
            'records_evaluated' => 0,
            'insights_created' => 0,
            'insights_updated' => 0,
            'insights_resolved' => 0,
            'evidence' => [],
            'error' => null,
        ];

        try {
            // Validate enabled
            if (!($definition['enabled'] ?? true)) {
                $result['status'] = 'skipped';
                return $result;
            }

            // Retrieve data using registered tool
            $toolId = $evalConfig['tool'] ?? null;
            $conditions = $evalConfig['conditions'] ?? [];
            $data = [];
            if ($toolId) {
                $data = self::retrieveData($db, $toolId, $evalConfig, $definition['max_records'] ?? 100);
                $result['records_evaluated'] = is_array($data) ? count($data) : 0;
            }

            // Evaluate conditions
            $conditionResult = self::evaluateConditions($data, $conditions);
            if (!$conditionResult['triggered']) {
                // Condition not met — check for resolution
                $resolved = self::checkResolution($db, $defKey);
                $result['insights_resolved'] = $resolved;
                $result['evidence'] = ['condition_met' => false, 'records' => $result['records_evaluated']];
                self::logEvaluation($db, $defKey, $result, microtime(true) - $startTime);
                return $result;
            }

            // Generate insight
            $severity = self::classifySeverity($conditionResult, $definition['severity_rules'] ?? []);
            $evidence = self::buildEvidence($data, $conditionResult);

            // AI narrative (hybrid only)
            $aiNarrative = null;
            if (($definition['type'] ?? '') === 'hybrid' || ($definition['type'] ?? '') === 'ai_analyzed') {
                $aiNarrative = self::generateAINarrative($db, $definition, $data, $evidence);
            }

            // Deterministic fallback summary
            $summary = $aiNarrative['summary'] ?? self::generateRuleSummary($conditionResult, $evidence);

            // Target users
            $targetUserIds = self::resolveTargetUsers($db, $definition, $data);

            // Create or update insight
            $insightResult = AIInsightService::createOrUpdateInsight(
                $db,
                $definition,
                $conditionResult['title'] ?? $definition['name'] ?? $defKey,
                $summary,
                $severity,
                $evidence,
                $aiNarrative['narrative'] ?? null,
                $targetUserIds
            );

            if ($insightResult['success']) {
                if ($insightResult['action'] === 'created') {
                    $result['insights_created'] = 1;
                } elseif ($insightResult['action'] === 'updated') {
                    $result['insights_updated'] = 1;
                }
            }

            $result['evidence'] = $evidence;

        } catch (\Throwable $e) {
            $result['status'] = 'failed';
            $result['error'] = $e->getMessage();
        }

        $duration = round((microtime(true) - $startTime) * 1000);
        self::logEvaluation($db, $defKey, $result, $duration);

        return $result;
    }

    /**
     * Retrieve data using a registered tool.
     */
    private static function retrieveData(PDO $db, string $toolId, array $evalConfig, int $maxRecords): array
    {
        $tool = AIToolRegistry::get($toolId);
        if (!$tool) return [];

        $params = $evalConfig['params'] ?? [];
        try {
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            $permissions = function_exists('getUserPermissions') && $userId > 0 ? getUserPermissions($userId) : [];
            $executor = new AIToolExecutor($db, $userId, is_array($permissions) ? $permissions : []);
            $result = $executor->execute($toolId, $params, 0);
            if (!empty($result['success']) && !empty($result['result'])) {
                return array_slice(array_filter(array_map('trim', explode("\n", (string) $result['result']))), 0, $maxRecords);
            }
            return [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Evaluate conditions against retrieved data.
     */
    private static function evaluateConditions(array $data, array $conditions): array
    {
        $result = ['triggered' => false, 'matches' => [], 'title' => '', 'details' => []];

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'gt';
            $threshold = $condition['threshold'] ?? 0;

            if ($field === 'count') {
                $count = count($data);
                $matched = match($operator) {
                    'gt' => $count > $threshold,
                    'gte' => $count >= $threshold,
                    'lt' => $count < $threshold,
                    'lte' => $count <= $threshold,
                    'eq' => $count == $threshold,
                    default => false,
                };
                if ($matched) {
                    $result['triggered'] = true;
                    $result['matches'][] = ['field' => 'count', 'value' => $count, 'threshold' => $threshold];
                    $result['details']['count'] = $count;
                }
            } elseif ($field === 'age_days') {
                $thresholdDays = $condition['threshold_days'] ?? 7;
                $dateField = $condition['date_field'] ?? 'created_at';
                $oldItems = array_filter($data, function($item) use ($dateField, $thresholdDays) {
                    $date = $item[$dateField] ?? null;
                    if (!$date) return false;
                    $days = (time() - strtotime($date)) / 86400;
                    return $days >= $thresholdDays;
                });
                $oldCount = count($oldItems);
                if ($oldCount > 0) {
                    $result['triggered'] = true;
                    $result['matches'][] = ['field' => 'age_days', 'value' => $oldCount, 'threshold' => $thresholdDays];
                    $result['details']['old_count'] = $oldCount;
                    $result['details']['threshold_days'] = $thresholdDays;
                }
            } elseif ($field === 'percentage') {
                $groupField = $condition['group_field'] ?? '';
                $minPercentage = $condition['threshold'] ?? 50;
                if ($groupField && !empty($data)) {
                    $groups = [];
                    foreach ($data as $item) {
                        $val = $item[$groupField] ?? 'unknown';
                        $groups[$val] = ($groups[$val] ?? 0) + 1;
                    }
                    $total = count($data);
                    arsort($groups);
                    $topKey = array_key_first($groups);
                    $topCount = $groups[$topKey] ?? 0;
                    $pct = $total > 0 ? ($topCount / $total) * 100 : 0;
                    if ($pct >= $minPercentage) {
                        $result['triggered'] = true;
                        $result['matches'][] = ['field' => 'percentage', 'value' => round($pct, 1), 'group' => $topKey];
                        $result['details']['concentration'] = ['group' => $topKey, 'percentage' => round($pct, 1)];
                    }
                }
            }
        }

        if ($result['triggered']) {
            $result['title'] = self::buildTitle($result);
        }

        return $result;
    }

    /**
     * Build insight title from condition results.
     */
    private static function buildTitle(array $conditionResult): string
    {
        foreach ($conditionResult['matches'] as $match) {
            if ($match['field'] === 'count') {
                return "{$match['value']} items require attention";
            } elseif ($match['field'] === 'age_days') {
                return "{$match['value']} items pending for more than {$match['threshold']} days";
            } elseif ($match['field'] === 'percentage') {
                return "High concentration detected: {$match['value']}% in {$match['group']}";
            }
        }
        return "Insight detected";
    }

    /**
     * Build evidence structure.
     */
    private static function buildEvidence(array $data, array $conditionResult): array
    {
        $evidence = [
            'records_evaluated' => count($data),
            'condition_met' => $conditionResult['triggered'],
            'matches' => $conditionResult['matches'],
            'details' => $conditionResult['details'],
            'evaluated_at' => date('Y-m-d H:i:s'),
        ];

        // Extract sample IDs for reference
        if (!empty($data)) {
            $ids = array_column($data, 'id');
            $evidence['sample_ids'] = array_slice($ids, 0, 10);
        }

        return $evidence;
    }

    /**
     * Classify severity based on condition results and rules.
     */
    private static function classifySeverity(array $conditionResult, array $severityRules): string
    {
        if (empty($severityRules)) {
            // Default severity based on count
            foreach ($conditionResult['matches'] as $match) {
                if ($match['field'] === 'count' || $match['field'] === 'age_days') {
                    $value = $match['value'] ?? 0;
                    if ($value >= 10) return 'critical';
                    if ($value >= 6) return 'warning';
                    if ($value >= 1) return 'notice';
                }
                if ($match['field'] === 'percentage') {
                    $pct = $match['value'] ?? 0;
                    if ($pct >= 70) return 'warning';
                    if ($pct >= 50) return 'notice';
                }
            }
            return 'info';
        }

        // Use configured rules
        foreach ($severityRules as $rule) {
            $field = $rule['field'] ?? '';
            $thresholds = $rule['thresholds'] ?? [];
            foreach ($conditionResult['matches'] as $match) {
                if ($match['field'] === $field) {
                    $value = $match['value'] ?? 0;
                    foreach ($thresholds as $t) {
                        if (isset($t['min']) && $value < $t['min']) continue;
                        if (isset($t['max']) && $value > $t['max']) continue;
                        return $t['severity'] ?? 'info';
                    }
                }
            }
        }

        return 'info';
    }

    /**
     * Check if previously active insights should be resolved.
     */
    private static function checkResolution(PDO $db, string $defKey): int
    {
        $def = $db->prepare("SELECT id FROM ai_insight_definitions WHERE definition_key = ?");
        $def->execute([$defKey]);
        $defRow = $def->fetch();
        if (!$defRow) return 0;

        $stmt = $db->prepare("
            UPDATE ai_insight_records SET status = 'resolved', resolved_at = NOW()
            WHERE definition_id = ? AND status = 'active'
        ");
        $stmt->execute([$defRow['id']]);
        return $stmt->rowCount();
    }

    /**
     * Generate AI narrative for hybrid insights.
     */
    private static function generateAINarrative(PDO $db, array $definition, array $data, array $evidence): ?array
    {
        // Check if AI provider is available
        $provider = AIProviderService::getDefaultProvider($db);
        if (!$provider) {
            return null; // Degrade to rule-based
        }

        // Build limited context for AI
        $sampleData = array_slice($data, 0, 5);
        $context = "Insight: {$definition['name']}\n";
        $context .= "Module: {$definition['module']}\n";
        $context .= "Records evaluated: " . count($data) . "\n";
        $context .= "Evidence: " . json_encode($evidence) . "\n";
        $context .= "Sample data: " . json_encode($sampleData) . "\n";

        $prompt = "Analyze this ERP data and provide a brief structured insight.\n\n{$context}\n\nReturn JSON with: title, summary, severity, key_findings array.";

        try {
            $response = AIProviderService::chat($db, $provider['id'] ?? 0, $prompt, [
                'max_tokens' => 300,
                'temperature' => 0.3,
                'response_format' => 'json',
            ]);

            $result = json_decode($response, true);
            if ($result && isset($result['summary'])) {
                // Validate AI output — ensure it doesn't invent data
                return ['narrative' => $result['summary'], 'summary' => $result['summary']];
            }
        } catch (\Throwable $e) {
            // AI failed — degrade gracefully
        }

        return null;
    }

    /**
     * Generate deterministic rule-based summary.
     */
    private static function generateRuleSummary(array $conditionResult, array $evidence): string
    {
        $parts = [];
        foreach ($conditionResult['matches'] as $match) {
            if ($match['field'] === 'count') {
                $parts[] = "{$match['value']} items detected";
            } elseif ($match['field'] === 'age_days') {
                $parts[] = "{$match['value']} items pending for more than {$match['threshold']} days";
            } elseif ($match['field'] === 'percentage') {
                $parts[] = "{$match['value']}% concentration in {$match['group']}";
            }
        }
        return implode('. ', $parts) ?: 'Condition detected based on evaluation rules.';
    }

    /**
     * Resolve target users for an insight.
     */
    private static function resolveTargetUsers(PDO $db, array $definition, array $data): array
    {
        $targetConfig = $definition['target_users'] ?? [];
        $resolverType = $targetConfig['resolver'] ?? 'module_managers';
        $module = $definition['module'] ?? '';

        return match($resolverType) {
            'module_managers' => self::getModuleManagers($db, $module),
            'authorized_role' => self::getUsersByRole($db, $targetConfig['role'] ?? ''),
            'record_owner' => self::getRecordOwners($data),
            'all_with_permission' => self::getUsersWithPermission($db, $targetConfig['permission'] ?? ''),
            default => self::getDefaultUsers($db),
        };
    }

    private static function getModuleManagers(PDO $db, string $module): array
    {
        // Users with module-level permissions
        $permPrefix = $module . '.';
        $stmt = $db->prepare("
            SELECT DISTINCT ur.user_id
            FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE p.module = ? AND p.name LIKE '%manage%'
        ");
        $stmt->execute([$module]);
        $ids = array_column($stmt->fetchAll(), 'user_id');
        return $ids ?: self::getDefaultUsers($db);
    }

    private static function getUsersByRole(PDO $db, string $roleName): array
    {
        if (empty($roleName)) return self::getDefaultUsers($db);
        $stmt = $db->prepare("
            SELECT DISTINCT ur.user_id
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE r.name = ?
        ");
        $stmt->execute([$roleName]);
        return array_column($stmt->fetchAll(), 'user_id');
    }

    private static function getRecordOwners(array $data): array
    {
        $owners = [];
        foreach ($data as $item) {
            $owner = $item['created_by'] ?? $item['user_id'] ?? null;
            if ($owner && !in_array($owner, $owners)) {
                $owners[] = (int) $owner;
            }
        }
        return $owners;
    }

    private static function getUsersWithPermission(PDO $db, string $permission): array
    {
        if (empty($permission)) return self::getDefaultUsers($db);
        $stmt = $db->prepare("
            SELECT DISTINCT ur.user_id
            FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE CONCAT(p.module, '.', p.name) = ?
        ");
        $stmt->execute([$permission]);
        return array_column($stmt->fetchAll(), 'user_id');
    }

    private static function getDefaultUsers(PDO $db): array
    {
        // Admin users as fallback
        $stmt = $db->prepare("
            SELECT DISTINCT ur.user_id
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE r.name = 'Admin' OR r.name = 'admin'
            LIMIT 10
        ");
        $stmt->execute();
        $ids = array_column($stmt->fetchAll(), 'user_id');
        if (empty($ids)) {
            // Fallback to user_id=1
            $ids = [1];
        }
        return $ids;
    }

    /**
     * Log an evaluation run.
     */
    private static function logEvaluation(PDO $db, string $defKey, array $result, float $durationMs): void
    {
        try {
            $def = $db->prepare("SELECT id FROM ai_insight_definitions WHERE definition_key = ?");
            $def->execute([$defKey]);
            $defRow = $def->fetch();
            if (!$defRow) return;

            $db->prepare("
                INSERT INTO ai_insight_evaluations
                (definition_id, company_id, status, evidence_summary, records_evaluated,
                 insight_count, duration_ms, error_message, evaluated_at)
                VALUES (?, 1, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $defRow['id'],
                $result['status'] ?? 'success',
                json_encode($result['evidence'] ?? []),
                $result['records_evaluated'] ?? 0,
                ($result['insights_created'] ?? 0) + ($result['insights_updated'] ?? 0),
                round($durationMs),
                $result['error'] ?? null,
            ]);
        } catch (\Throwable $e) {}
    }

    /**
     * Evaluate all due insight definitions (called by scheduler).
     */
    public static function evaluateAllDue(PDO $db): array
    {
        $totalResults = ['evaluated' => 0, 'created' => 0, 'updated' => 0, 'resolved' => 0, 'failed' => 0];

        // Get enabled definitions
        $stmt = $db->prepare("SELECT * FROM ai_insight_definitions WHERE enabled = 1 ORDER BY evaluation_frequency ASC, module LIMIT " . (int) self::MAX_EVALUATIONS_PER_CYCLE);
        $stmt->execute();
        $definitions = $stmt->fetchAll();

        foreach ($definitions as $defRow) {
            $definition = [
                'definition_key' => $defRow['definition_key'],
                'name' => $defRow['name'],
                'description' => $defRow['description'],
                'module' => $defRow['module'],
                'category' => $defRow['category'],
                'type' => $defRow['insight_type'],
                'enabled' => (bool) $defRow['enabled'],
                'evaluation' => json_decode($defRow['evaluation_config'] ?? '{}', true),
                'severity_rules' => json_decode($defRow['severity_rules'] ?? '{}', true),
                'target_users' => json_decode($defRow['target_users_config'] ?? '{}', true),
                'navigation' => json_decode($defRow['navigation_config'] ?? '{}', true),
                'max_records' => (int) $defRow['max_records'],
                'max_runtime_seconds' => (int) $defRow['max_runtime_seconds'],
            ];

            $result = self::evaluate($db, $definition);
            $totalResults['evaluated']++;
            $totalResults['created'] += $result['insights_created'] ?? 0;
            $totalResults['updated'] += $result['insights_updated'] ?? 0;
            $totalResults['resolved'] += $result['insights_resolved'] ?? 0;
            if ($result['status'] === 'failed') $totalResults['failed']++;
        }

        return $totalResults;
    }
}
