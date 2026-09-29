<?php
/**
 * AI F30 — Analytics Orchestrator Service.
 *
 * Coordinates all analytics operations:
 * ERP Data → Controlled Metrics → Analysis → Forecast/Scenario → Evidence → AI Explanation → F26/F25/F29
 *
 * Covers Sections 1-32.
 */
class AIAnalyticsService
{
    private $db;
    private $companyId;
    private $userId;
    private $queryEngine;
    private $forecastService;
    private $scenarioService;
    private $metricRegistry;

    public function __construct(PDO $db, int $companyId, int $userId)
    {
        $this->db = $db;
        $this->companyId = $companyId;
        $this->userId = $userId;
        $this->metricRegistry = new AIMetricRegistry($db, $companyId);
        $this->metricRegistry->loadMetrics();
        $this->metricRegistry->loadDimensions();
        $this->queryEngine = new AIAnalyticsQueryEngine($db, $companyId, $userId);
        $this->forecastService = new AIForecastingService($db, $companyId, $userId);
        $this->scenarioService = new AIScenarioService($db, $companyId, $userId);
    }

    /**
     * Process a natural-language analytics request through the full pipeline.
     */
    public function processRequest(string $text, array $context = []): array
    {
        $startTime = microtime(true);

        // F24 Governance check
        $governance = $this->checkGovernance($text);
        if (!$governance['allowed']) {
            return [
                'success' => false,
                'error' => $governance['reason'] ?? 'Request denied by governance',
                'latency_ms' => $this->elapsed($startTime),
            ];
        }

        // Determine request type
        $requestType = $this->classifyRequest($text);

        $result = match ($requestType) {
            'forecast' => $this->handleForecastRequest($text, $context),
            'scenario' => $this->handleScenarioRequest($text, $context),
            'briefing' => $this->handleBriefingRequest($text, $context),
            'anomaly' => $this->handleAnomalyRequest($text, $context),
            default => $this->handleAnalyticalQuery($text, $context),
        };

        // F23 Observability
        $this->recordAnalyticsRun($text, $requestType, $result, $this->elapsed($startTime));

        $result['latency_ms'] = $this->elapsed($startTime);
        $result['request_type'] = $requestType;

        return $result;
    }

    /**
     * Classify the analytics request type.
     */
    private function classifyRequest(string $text): string
    {
        $lower = strtolower($text);

        if (preg_match('/forecast|predict|project|next month|next quarter|future/i', $lower)) {
            return 'forecast';
        }
        if (preg_match('/what.if|what happens|what if|scenario|simulate/i', $lower)) {
            return 'scenario';
        }
        if (preg_match('/briefing|executive|overview|snapshot|summary|dashboard/i', $lower)) {
            return 'briefing';
        }
        if (preg_match('/anomal|unusual|spike|drop|alert|warning|abnormal/i', $lower)) {
            return 'anomaly';
        }
        return 'analytical';
    }

    /**
     * Handle a standard analytical query.
     */
    private function handleAnalyticalQuery(string $text, array $context): array
    {
        $result = $this->queryEngine->processAnalyticalRequest($text, $context);
        if (!$result['success']) return $result;

        // Add AI explanation
        $result['explanation'] = $this->generateExplanation($result);

        return $result;
    }

    /**
     * Handle a forecast request.
     */
    private function handleForecastRequest(string $text, array $context): array
    {
        $lower = strtolower($text);

        // Detect metric
        $engine = $this->queryEngine;
        $parsed = $engine->processAnalyticalRequest($text, $context);

        // Try to get metric from the parsed result
        $metricKey = null;
        if (!empty($parsed['metric']['key'])) {
            $metricKey = $parsed['metric']['key'];
        } else {
            // Fallback: detect from text
            $metricKey = $this->detectForecastMetric($lower);
        }

        if (!$metricKey) {
            return [
                'success' => false,
                'error' => 'Could not determine which metric to forecast. Try: "Forecast revenue" or "Predict inventory levels"',
            ];
        }

        // Detect period
        $periods = 30;
        if (preg_match('/(\\d+)\\s*days?/i', $lower, $m)) {
            $periods = (int)$m[1];
        } elseif (preg_match('/next\\s+month/i', $lower)) {
            $periods = 30;
        } elseif (preg_match('/next\\s+quarter/i', $lower)) {
            $periods = 90;
        }

        // Detect method
        $method = 'moving_average';
        if (preg_match('/weighted/i', $lower)) $method = 'weighted_moving_average';
        elseif (preg_match('/trend/i', $lower)) $method = 'historical_trend';
        elseif (preg_match('/regression/i', $lower)) $method = 'linear_regression';

        $result = $this->forecastService->generateForecast($metricKey, $periods, $method);
        if (!$result['success']) return $result;

        // Add explanation
        $result['explanation'] = $this->generateForecastExplanation($result);

        return $result;
    }

    /**
     * Handle a scenario request.
     */
    private function handleScenarioRequest(string $text, array $context): array
    {
        $parsed = $this->scenarioService->parseScenarioRequest($text, $context);
        if (!$parsed['success']) return $parsed;

        $result = $this->scenarioService->runScenario(
            $parsed['metric_key'] ?? 'revenue',
            $parsed['variables'],
            '',
            $parsed['description']
        );

        if ($result['success']) {
            $result['explanation'] = $this->generateScenarioExplanation($result);
        }

        return $result;
    }

    /**
     * Handle an executive briefing request (Section 17).
     */
    private function handleBriefingRequest(string $text, array $context): array
    {
        $briefing = [
            'success' => true,
            'type' => 'executive_briefing',
            'business_snapshot' => [],
            'major_changes' => [],
            'risks' => [],
            'opportunities' => [],
        ];

        // Get key metrics
        $financeMetrics = ['revenue', 'gross_profit', 'outstanding_receivables', 'overdue_invoices'];
        $procurementMetrics = ['purchase_spend', 'purchase_order_count'];
        $inventoryMetrics = ['inventory_value'];

        foreach ($financeMetrics as $mk) {
            $result = $this->queryEngine->processAnalyticalRequest("Show {$mk} this quarter");
            if ($result['success']) {
                $briefing['business_snapshot'][] = [
                    'metric' => $result['metric']['name'] ?? $mk,
                    'value' => $result['total'],
                    'comparison' => $result['comparison'] ?? null,
                    'currency_dependent' => $result['metric']['currency_dependent'] ?? false,
                ];
            }
        }

        foreach ($procurementMetrics as $mk) {
            $result = $this->queryEngine->processAnalyticalRequest("Show {$mk} this quarter");
            if ($result['success']) {
                $briefing['business_snapshot'][] = [
                    'metric' => $result['metric']['name'] ?? $mk,
                    'value' => $result['total'],
                    'comparison' => $result['comparison'] ?? null,
                ];
            }
        }

        // Detect risks from negative trends
        foreach ($briefing['business_snapshot'] as $snapshot) {
            if (!empty($snapshot['comparison']['change']) && $snapshot['comparison']['change'] < -10) {
                $briefing['risks'][] = [
                    'metric' => $snapshot['metric'],
                    'change' => $snapshot['comparison']['change'],
                    'description' => "{$snapshot['metric']} decreased by " . abs($snapshot['comparison']['change']) . "%",
                ];
            }
            if (!empty($snapshot['comparison']['change']) && $snapshot['comparison']['change'] > 10) {
                $briefing['opportunities'][] = [
                    'metric' => $snapshot['metric'],
                    'change' => $snapshot['comparison']['change'],
                    'description' => "{$snapshot['metric']} increased by " . $snapshot['comparison']['change'] . "%",
                ];
            }
        }

        return $briefing;
    }

    /**
     * Handle anomaly detection request.
     */
    private function handleAnomalyRequest(string $text, array $context): array
    {
        $result = $this->queryEngine->processAnalyticalRequest($text, $context);
        if (!$result['success']) return $result;

        // Check for anomalies in the data
        $anomalies = [];
        if (!empty($result['results'])) {
            $values = array_column($result['results'], 'metric_value');
            $mean = array_sum($values) / max(1, count($values));
            $stdDev = $this->standardDeviation($values);

            foreach ($result['results'] as $row) {
                $zScore = $stdDev > 0 ? abs(((float)$row['metric_value'] - $mean) / $stdDev) : 0;
                if ($zScore > 2) {
                    $anomalies[] = [
                        'dimension' => $row,
                        'value' => (float)$row['metric_value'],
                        'z_score' => round($zScore, 2),
                        'severity' => $zScore > 3 ? 'high' : 'medium',
                    ];
                }
            }
        }

        $result['anomalies'] = $anomalies;
        $result['explanation'] = $this->generateAnomalyExplanation($result, $anomalies);

        // F26 Integration: Create intelligence events for high-severity anomalies (Section 19)
        foreach ($anomalies as $anomaly) {
            if ($anomaly['severity'] === 'high') {
                $this->createF26Event($anomaly, $result['metric'] ?? []);
            }
        }

        return $result;
    }

    /**
     * Generate AI explanation for analytical results (Section 14).
     */
    private function generateExplanation(array $result): array
    {
        $explanation = [
            'summary' => '',
            'factors' => [],
            'recommendation' => '',
            'caveat' => 'Analysis based on available authorized data.',
        ];

        $metricName = $result['metric']['name'] ?? 'metric';
        $total = $result['total'] ?? 0;

        $explanation['summary'] = "{$metricName}: " . number_format($total, 2);

        if (!empty($result['comparison'])) {
            $change = $result['comparison']['change'];
            $direction = $change > 0 ? 'increased' : ($change < 0 ? 'decreased' : 'remained stable');
            $explanation['summary'] .= " ({$direction} by " . abs($change) . "% vs previous period)";

            if ($change < -10) {
                $explanation['factors'][] = "The largest contributor appears to be the decrease in this period";
                $explanation['recommendation'] = "Investigate the underlying causes for this decline";
            } elseif ($change > 10) {
                $explanation['factors'][] = "Strong performance detected in this period";
                $explanation['recommendation'] = "Analyze which segments contributed most to this growth";
            }
        }

        return $explanation;
    }

    /**
     * Generate forecast explanation.
     */
    private function generateForecastExplanation(array $result): array
    {
        $method = str_replace('_', ' ', $result['forecast_method'] ?? 'unknown');
        $confidence = $result['confidence']['level'] ?? 'unknown';
        $horizon = $result['forecast_period']['periods_ahead'] ?? 0;

        return [
            'summary' => "Projected value: " . number_format($result['forecast_value'] ?? 0, 2) .
                         " (Horizon: {$horizon} days, " .
                         "Method: {$method}, Confidence: {$confidence})",
            'caveat' => 'This is a statistical forecast based on historical patterns. ' .
                       'It does not account for external factors, market changes, or policy decisions. ' .
                       'Do not treat this as a guarantee.',
            'assumptions' => $result['assumptions'] ?? [],
            'limitations' => $result['limitations'] ?? [],
        ];
    }

    /**
     * Generate scenario explanation.
     */
    private function generateScenarioExplanation(array $result): array
    {
        $impact = $result['impact_percentage'] ?? 0;
        $direction = $impact > 0 ? 'increase' : 'decrease';

        return [
            'summary' => "Scenario shows a " . abs($impact) . "% {$direction} from baseline " .
                         number_format($result['baseline_value'] ?? 0, 2) .
                         " to " . number_format($result['scenario_value'] ?? 0, 2),
            'safety' => $result['safety_label'] ?? 'Simulation — no ERP data has been changed',
            'methodology' => $result['methodology'] ?? '',
        ];
    }

    /**
     * Generate anomaly explanation.
     */
    private function generateAnomalyExplanation(array $result, array $anomalies): array
    {
        if (empty($anomalies)) {
            return ['summary' => 'No significant anomalies detected in the analyzed data.'];
        }

        return [
            'summary' => count($anomalies) . ' potential anomal' . (count($anomalies) === 1 ? 'y' : 'ies') . ' detected',
            'details' => array_map(fn($a) => [
                'value' => $a['value'],
                'severity' => $a['severity'],
                'z_score' => $a['z_score'],
                'description' => "Value " . number_format($a['value'], 2) . " is {$a['severity']}ly unusual (z-score: {$a['z_score']})",
            ], $anomalies),
        ];
    }

    /**
     * Create F26 intelligence event from anomaly (Section 19).
     */
    private function createF26Event(array $anomaly, array $metric): void
    {
        try {
            if (class_exists('AIIntelligenceService')) {
                AIIntelligenceService::createEvent($this->db, [
                    'event_key' => 'analytics_anomaly_' . ($metric['key'] ?? 'metric'),
                    'company_id' => $this->companyId,
                    'user_id' => $this->userId,
                    'source_module' => 'analytics',
                    'source_record_type' => 'analytics_metric',
                    'source_record_id' => $metric['key'] ?? null,
                    'detection_type' => 'analytics_anomaly',
                    'detector_key' => 'f30.anomaly',
                    'severity' => $anomaly['severity'] === 'high' ? 'high' : 'medium',
                    'confidence' => 0.82,
                    'title' => 'Unusual ' . ($metric['name'] ?? 'metric') . ' detected',
                    'explanation' => 'Value ' . number_format($anomaly['value'], 2) . ' detected with z-score ' . $anomaly['z_score'],
                    'evidence' => [
                        [
                            'type' => 'analytics_result',
                            'id' => $metric['key'] ?? null,
                            'label' => $metric['name'] ?? 'metric',
                            'data' => [
                                'value' => $anomaly['value'],
                                'z_score' => $anomaly['z_score'],
                                'metric_key' => $metric['key'] ?? null,
                            ],
                        ],
                    ],
                    'recommendation' => 'Review the source records behind the anomaly.',
                    'urgency' => 'high',
                ]);
            }
        } catch (\Throwable $e) {
            // Non-critical
        }
    }

    /**
     * Check F24 governance.
     */
    private function checkGovernance(string $text): array
    {
        if (class_exists('AIEmergencyControlsService')) {
            if (AIEmergencyControlsService::isControlActive($this->db, 'disable_ai_actions')) {
                return ['allowed' => false, 'reason' => 'AI actions are disabled'];
            }
        }

        return ['allowed' => true];
    }

    /**
     * Detect metric for forecast from text.
     */
    private function detectForecastMetric(string $lower): ?string
    {
        $map = [
            'revenue' => 'revenue', 'sales' => 'revenue',
            'purchase' => 'purchase_spend', 'procurement' => 'purchase_spend',
            'inventory' => 'inventory_value', 'stock' => 'inventory_value',
            'receivable' => 'outstanding_receivables',
            'overdue' => 'overdue_invoices',
        ];
        foreach ($map as $k => $v) {
            if (strpos($lower, $k) !== false) return $v;
        }
        return 'revenue'; // Default
    }

    /**
     * Record analytics run for F23 observability.
     */
    public function recordAnalyticsRun(string $text, string $type, array $result, int $latencyMs): void
    {
        try {
            $metricKey = $result['metric']['key'] ?? ($result['metric_key'] ?? null);
            $dimensions = $result['dimensions'] ?? [];
            $filters = $result['filters'] ?? [];
            $providerUsed = $result['provider_used'] ?? 'deterministic';
            $modelUsed = $result['model_used'] ?? match ($type) {
                'forecast' => 'forecast-engine',
                'scenario' => 'scenario-engine',
                'briefing' => 'briefing-engine',
                'anomaly' => 'anomaly-engine',
                default => 'rules-engine',
            };
            $forecastMethod = $result['forecast_method'] ?? null;
            $resultSummary = (string) ($result['explanation']['summary']
                ?? $result['answer']
                ?? $result['type']
                ?? '');
            if ($resultSummary === '') {
                if (array_key_exists('forecast_value', $result)) {
                    $resultSummary = 'Forecast projected value: ' . number_format((float) $result['forecast_value'], 2);
                } elseif (array_key_exists('scenario_value', $result)) {
                    $resultSummary = 'Scenario projected value: ' . number_format((float) $result['scenario_value'], 2);
                }
            }
            $resultSummary = substr($resultSummary, 0, 500);
            $resultJson = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $resultRows = isset($result['results']) && is_array($result['results']) ? count($result['results']) : null;

            $stmt = $this->db->prepare("
                INSERT INTO ai_analytics_runs
                    (company_id, user_id, request_text, metric_key, dimensions, filters, operation_type,
                     query_definition, execution_time_ms, data_volume_rows, data_volume_bytes, provider_used,
                     model_used, forecast_method, estimated_cost, result_summary, result_json,
                     governance_decision, risk_level)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'allow', 'low')
            ");
            $stmt->execute([
                $this->companyId,
                $this->userId,
                substr($text, 0, 2000),
                $metricKey,
                json_encode($dimensions),
                json_encode($filters),
                $type,
                json_encode([
                    'request_text' => $text,
                    'operation_type' => $type,
                    'metric_key' => $metricKey,
                    'dimensions' => $dimensions,
                    'filters' => $filters,
                    'forecast_method' => $forecastMethod,
                ]),
                $latencyMs,
                $resultRows,
                strlen($resultJson ?: ''),
                $providerUsed,
                $modelUsed,
                $forecastMethod,
                0.0,
                $resultSummary,
                $resultJson,
            ]);
        } catch (\Throwable $e) {
            // Non-critical
        }
    }

    /**
     * Get saved analyses for the user.
     */
    public function getSavedAnalyses(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM ai_saved_analyses
            WHERE company_id = ? AND (user_id = ? OR visibility != 'private')
            ORDER BY updated_at DESC
        ");
        $stmt->execute([$this->companyId, $this->userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Save an analysis configuration.
     */
    public function saveAnalysis(string $name, string $metricKey, array $options = []): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO ai_saved_analyses
                (company_id, user_id, name, metric_key, dimensions, filters,
                 comparison_type, forecast_settings, scenario_settings, visibility, refresh_schedule)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $this->companyId,
            $this->userId,
            $name,
            $metricKey,
            json_encode($options['dimensions'] ?? []),
            json_encode($options['filters'] ?? []),
            $options['comparison_type'] ?? null,
            json_encode($options['forecast_settings'] ?? null),
            json_encode($options['scenario_settings'] ?? null),
            $options['visibility'] ?? 'private',
            isset($options['refresh_schedule']) ? json_encode($options['refresh_schedule']) : null,
        ]);

        return ['success' => true, 'id' => (int)$this->db->lastInsertId()];
    }

    /**
     * Attach or update a refresh schedule for a saved analysis.
     */
    public function scheduleAnalysis(int $analysisId, array $schedule): array
    {
        try {
            $lookup = $this->db->prepare("
                SELECT id
                FROM ai_saved_analyses
                WHERE id = ? AND company_id = ? AND user_id = ?
                LIMIT 1
            ");
            $lookup->execute([$analysisId, $this->companyId, $this->userId]);
            if (!$lookup->fetchColumn()) {
                return ['success' => false, 'error' => 'Saved analysis not found or not owned by the current tenant/user.'];
            }

            $stmt = $this->db->prepare("
                UPDATE ai_saved_analyses
                SET refresh_schedule = ?, updated_at = NOW()
                WHERE id = ? AND company_id = ? AND user_id = ?
            ");
            $stmt->execute([json_encode($schedule), $analysisId, $this->companyId, $this->userId]);

            return ['success' => true, 'analysis_id' => $analysisId, 'refresh_schedule' => $schedule];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to update refresh schedule.'];
        }
    }

    /**
     * Execute any scheduled analyses that are due for this tenant.
     */
    public function runDueScheduledAnalyses(): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM ai_saved_analyses
            WHERE company_id = ? AND refresh_schedule IS NOT NULL
            ORDER BY updated_at DESC
        ");
        $stmt->execute([$this->companyId]);
        $analyses = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $executed = [];
        foreach ($analyses as $analysis) {
            if (!$this->isAnalysisDue($analysis)) {
                continue;
            }

            $query = 'Show ' . $analysis['metric_key'] . ' this quarter';
            if (!empty($analysis['comparison_type'])) {
                $query .= ' compared to previous period';
            }

            $result = $this->processRequest($query, [
                'saved_analysis_id' => (int) $analysis['id'],
                'dimensions' => json_decode($analysis['dimensions'] ?? '[]', true) ?: [],
                'filters' => json_decode($analysis['filters'] ?? '[]', true) ?: [],
            ]);

            $this->db->prepare("
                UPDATE ai_saved_analyses
                SET last_run_at = NOW(), updated_at = NOW()
                WHERE id = ? AND company_id = ?
            ")->execute([(int) $analysis['id'], $this->companyId]);

            $executed[] = [
                'analysis_id' => (int) $analysis['id'],
                'name' => $analysis['name'],
                'metric_key' => $analysis['metric_key'],
                'success' => (bool) ($result['success'] ?? false),
            ];
        }

        return [
            'success' => true,
            'company_id' => $this->companyId,
            'executed_count' => count($executed),
            'executed' => $executed,
        ];
    }

    /**
     * Determine whether a scheduled analysis is due.
     */
    private function isAnalysisDue(array $analysis): bool
    {
        $schedule = json_decode($analysis['refresh_schedule'] ?? 'null', true);
        if (!is_array($schedule) || empty($schedule['schedule_type'])) {
            return false;
        }

        if (empty($analysis['last_run_at'])) {
            return true;
        }

        if (class_exists('AIAutomationSchedulerService')) {
            $nextRun = AIAutomationSchedulerService::calculateNextRun($schedule, $analysis['last_run_at']);
            if (!$nextRun) {
                return true;
            }

            return strtotime($nextRun) <= time();
        }

        return true;
    }

    /**
     * Get available metrics by role.
     */
    public function getRoleAnalytics(string $role): array
    {
        return $this->metricRegistry->getRoleMetrics($role);
    }

    /**
     * Standard deviation helper.
     */
    private function standardDeviation(array $values): float
    {
        $n = count($values);
        if ($n < 2) return 0;
        $mean = array_sum($values) / $n;
        $sumSquaredDiff = 0;
        foreach ($values as $v) {
            $sumSquaredDiff += pow($v - $mean, 2);
        }
        return sqrt($sumSquaredDiff / $n);
    }

    private function elapsed(float $startTime): int
    {
        return (int)((microtime(true) - $startTime) * 1000);
    }

    /**
     * Get the query engine.
     */
    public function getQueryEngine(): AIAnalyticsQueryEngine
    {
        return $this->queryEngine;
    }

    /**
     * Get the forecast service.
     */
    public function getForecastService(): AIForecastingService
    {
        return $this->forecastService;
    }

    /**
     * Get the scenario service.
     */
    public function getScenarioService(): AIScenarioService
    {
        return $this->scenarioService;
    }
}
