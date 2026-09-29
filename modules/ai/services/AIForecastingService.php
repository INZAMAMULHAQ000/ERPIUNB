<?php
/**
 * AI F30 — Forecasting Service.
 *
 * Provider-independent forecasting abstraction.
 * Supports moving average, weighted moving average, and historical trend.
 * Never forces LLM to perform forecasting mathematics.
 *
 * Covers Section 9: Forecasting, Section 10: Forecast Output.
 */
class AIForecastingService
{
    private $db;
    private $companyId;
    private $userId;

    public function __construct(PDO $db, int $companyId, int $userId)
    {
        $this->db = $db;
        $this->companyId = $companyId;
        $this->userId = $userId;
    }

    /**
     * Generate a forecast for a given metric.
     *
     * @param string $metricKey The metric to forecast
     * @param int $periodsAhead Number of periods to forecast
     * @param string $method Forecasting method
     * @param array $options Additional options (weights, window size, etc.)
     * @return array Forecast result with value, confidence, assumptions, limitations
     */
    public function generateForecast(
        string $metricKey,
        int $periodsAhead = 30,
        string $method = 'moving_average',
        array $options = []
    ): array {
        $startTime = microtime(true);

        // Step 1: Get historical data
        $historical = $this->getHistoricalData($metricKey, $options);
        if (!$historical['success']) {
            return $historical;
        }

        $data = $historical['data'];
        if (count($data) < 3) {
            return [
                'success' => false,
                'error' => 'Insufficient historical data for forecasting. Need at least 3 data points, got ' . count($data) . '.',
                'latency_ms' => $this->elapsed($startTime),
            ];
        }

        // Step 2: Apply the forecasting method
        $forecastResult = match ($method) {
            'moving_average' => $this->movingAverage($data, $periodsAhead, $options['window_size'] ?? 7),
            'weighted_moving_average' => $this->weightedMovingAverage($data, $periodsAhead, $options['weights'] ?? []),
            'historical_trend' => $this->historicalTrend($data, $periodsAhead),
            'linear_regression' => $this->linearRegression($data, $periodsAhead),
            default => $this->movingAverage($data, $periodsAhead, $options['window_size'] ?? 7),
        };

        if (!$forecastResult['success']) {
            return $forecastResult;
        }

        // Step 3: Calculate confidence
        $confidence = $this->calculateConfidence($data, $forecastResult['forecast']);

        // Step 4: Build forecast output
        $forecast = [
            'success' => true,
            'metric_key' => $metricKey,
            'forecast_value' => $forecastResult['forecast'],
            'forecast_unit' => $historical['unit'] ?? null,
            'forecast_currency' => $historical['currency'] ?? null,
            'forecast_method' => $method,
            'forecast_period' => [
                'periods_ahead' => $periodsAhead,
                'period_type' => $options['period_type'] ?? 'daily',
            ],
            'confidence' => $confidence,
            'historical_data' => array_slice($data, -12), // Last 12 data points
            'historical_range' => [
                'start' => $data[0]['date'] ?? null,
                'end' => end($data)['date'] ?? null,
                'count' => count($data),
            ],
            'assumptions' => $this->getAssumptions($method, $data),
            'limitations' => $this->getLimitations($method, $data, $periodsAhead),
            'trend' => $this->detectTrend($data),
            'seasonality' => $this->detectSeasonality($data),
            'latency_ms' => $this->elapsed($startTime),
        ];

        // Step 5: Save the forecast
        $forecast['forecast_id'] = $this->saveForecast($forecast, $data);

        return $forecast;
    }

    /**
     * Get historical data for a metric.
     */
    private function getHistoricalData(string $metricKey, array $options): array
    {
        $registry = new AIMetricRegistry($this->db, $this->companyId);
        $registry->loadMetrics();
        $metric = $registry->getMetric($metricKey);

        if (!$metric) {
            return ['success' => false, 'error' => 'Unknown metric: ' . $metricKey];
        }

        $dateCol = $this->getDateColumn($metric['source_table']);
        if (!$dateCol) {
            return ['success' => false, 'error' => 'Cannot determine date column for metric.'];
        }

        $expression = $this->buildHistoricalExpression($metric);
        if (!$expression) {
            return ['success' => false, 'error' => 'Cannot determine calculation expression for metric.'];
        }

        $days = $options['historical_days'] ?? 365;

        try {
            $where = [];
            $params = [];
            if ($this->tableHasColumn($metric['source_table'], 'company_id')) {
                $where[] = "{$metric['source_table']}.company_id = ?";
                $params[] = $this->companyId;
            }
            $where[] = "{$metric['source_table']}.{$dateCol} >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
            $params[] = $days;

            $sql = "SELECT DATE({$metric['source_table']}.{$dateCol}) as date, {$expression} as value
                    FROM {$metric['source_table']}
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY DATE({$metric['source_table']}.{$dateCol})
                    ORDER BY date ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $data = [];
            foreach ($rows as $row) {
                $data[] = [
                    'date' => $row['date'],
                    'value' => (float)$row['value'],
                ];
            }

            return [
                'success' => true,
                'data' => $data,
                'unit' => $metric['currency_dependent'] ? 'currency' : 'count',
                'currency' => $metric['currency_dependent'] ? 'AED' : null,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to retrieve historical data: ' . $e->getMessage()];
        }
    }

    /**
     * Simple Moving Average forecast.
     */
    private function movingAverage(array $data, int $periodsAhead, int $windowSize = 7): array
    {
        if (count($data) < $windowSize) {
            $windowSize = count($data);
        }

        $values = array_column($data, 'value');
        $forecast = [];

        // Calculate initial moving average
        $window = array_slice($values, -$windowSize);
        $avg = array_sum($window) / count($window);

        // Forecast forward
        for ($i = 0; $i < $periodsAhead; $i++) {
            $forecast[] = round($avg, 4);
        }

        return [
            'success' => true,
            'forecast' => end($forecast),
            'forecast_series' => $forecast,
            'method' => 'moving_average',
            'window_size' => $windowSize,
        ];
    }

    /**
     * Weighted Moving Average forecast.
     */
    private function weightedMovingAverage(array $data, int $periodsAhead, array $weights = []): array
    {
        $values = array_column($data, 'value');
        $n = count($values);

        // Default weights: more recent = higher weight
        if (empty($weights)) {
            $weights = [];
            for ($i = 1; $i <= min($n, 10); $i++) {
                $weights[] = $i;
            }
        }

        $weightSum = array_sum($weights);
        if ($weightSum == 0) $weightSum = 1;

        $weightedSum = 0;
        $weightIndex = count($weights) - 1;
        for ($i = $n - 1; $i >= max(0, $n - count($weights)); $i--) {
            $weightedSum += $values[$i] * ($weights[$weightIndex] ?? 1);
            $weightIndex--;
        }

        $forecast = round($weightedSum / $weightSum, 4);

        return [
            'success' => true,
            'forecast' => $forecast,
            'forecast_series' => array_fill(0, $periodsAhead, $forecast),
            'method' => 'weighted_moving_average',
        ];
    }

    /**
     * Historical Trend forecast (linear extrapolation).
     */
    private function historicalTrend(array $data, int $periodsAhead): array
    {
        $values = array_column($data, 'value');
        $n = count($values);

        if ($n < 2) {
            return ['success' => false, 'error' => 'Need at least 2 data points for trend analysis.'];
        }

        // Calculate simple linear trend
        $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
        for ($i = 0; $i < $n; $i++) {
            $x = $i;
            $y = $values[$i];
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $denominator = ($n * $sumX2 - $sumX * $sumX);
        if ($denominator == 0) {
            // Flat trend
            $slope = 0;
            $intercept = $sumY / $n;
        } else {
            $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
            $intercept = ($sumY - $slope * $sumX) / $n;
        }

        // Forecast forward
        $forecast = [];
        for ($i = 0; $i < $periodsAhead; $i++) {
            $value = $intercept + $slope * ($n + $i);
            $forecast[] = max(0, round($value, 4)); // No negative forecasts
        }

        return [
            'success' => true,
            'forecast' => end($forecast),
            'forecast_series' => $forecast,
            'method' => 'historical_trend',
            'slope' => round($slope, 6),
            'intercept' => round($intercept, 4),
        ];
    }

    /**
     * Linear Regression forecast.
     */
    private function linearRegression(array $data, int $periodsAhead): array
    {
        // Same as historical_trend but with R² calculation
        $values = array_column($data, 'value');
        $n = count($values);

        $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
        for ($i = 0; $i < $n; $i++) {
            $sumX += $i;
            $sumY += $values[$i];
            $sumXY += $i * $values[$i];
            $sumX2 += $i * $i;
        }

        $meanY = $sumY / $n;
        $denominator = ($n * $sumX2 - $sumX * $sumX);

        if ($denominator == 0) {
            return $this->movingAverage($data, $periodsAhead, 7);
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
        $intercept = ($sumY - $slope * $sumX) / $n;

        // Calculate R²
        $ssRes = 0; $ssTot = 0;
        for ($i = 0; $i < $n; $i++) {
            $predicted = $intercept + $slope * $i;
            $ssRes += pow($values[$i] - $predicted, 2);
            $ssTot += pow($values[$i] - $meanY, 2);
        }
        $r2 = $ssTot > 0 ? 1 - ($ssRes / $ssTot) : 0;

        $forecast = [];
        for ($i = 0; $i < $periodsAhead; $i++) {
            $forecast[] = max(0, round($intercept + $slope * ($n + $i), 4));
        }

        return [
            'success' => true,
            'forecast' => end($forecast),
            'forecast_series' => $forecast,
            'method' => 'linear_regression',
            'r_squared' => round($r2, 4),
            'slope' => round($slope, 6),
            'intercept' => round($intercept, 4),
        ];
    }

    /**
     * Calculate confidence level for the forecast.
     */
    private function calculateConfidence(array $data, float $forecastValue): array
    {
        $values = array_column($data, 'value');
        $n = count($values);

        if ($n < 5) return ['level' => 'low', 'score' => 0.3, 'reason' => 'Limited historical data'];

        // Calculate coefficient of variation
        $mean = array_sum($values) / $n;
        $variance = 0;
        foreach ($values as $v) {
            $variance += pow($v - $mean, 2);
        }
        $stdDev = sqrt($variance / $n);
        $cv = $mean > 0 ? $stdDev / $mean : 1;

        // Calculate trend consistency
        $recentValues = array_slice($values, -min(10, $n));
        $trendDirection = 0;
        for ($i = 1; $i < count($recentValues); $i++) {
            $trendDirection += ($recentValues[$i] >= $recentValues[$i - 1]) ? 1 : -1;
        }
        $consistency = abs($trendDirection) / max(1, count($recentValues) - 1);

        // Determine confidence
        $score = 0;
        $reasons = [];

        if ($cv < 0.2) { $score += 0.4; $reasons[] = 'Low volatility'; }
        elseif ($cv < 0.5) { $score += 0.2; $reasons[] = 'Moderate volatility'; }
        else { $reasons[] = 'High volatility'; }

        if ($n >= 30) { $score += 0.3; $reasons[] = 'Sufficient history'; }
        elseif ($n >= 14) { $score += 0.2; $reasons[] = 'Moderate history'; }
        else { $reasons[] = 'Limited history'; }

        if ($consistency > 0.7) { $score += 0.2; $reasons[] = 'Consistent trend'; }
        else { $reasons[] = 'Inconsistent trend'; }

        if ($forecastValue >= 0 && $forecastValue <= $mean * 3) { $score += 0.1; $reasons[] = 'Reasonable range'; }

        $level = $score >= 0.7 ? 'high' : ($score >= 0.4 ? 'medium' : 'low');

        return [
            'level' => $level,
            'score' => round($score, 2),
            'reasons' => $reasons,
        ];
    }

    /**
     * Detect trend direction from data.
     */
    private function detectTrend(array $data): array
    {
        $values = array_column($data, 'value');
        $n = count($values);
        if ($n < 2) return ['direction' => 'flat', 'strength' => 0];

        $recent = array_slice($values, -min(7, $n));
        $older = array_slice($values, 0, max(1, $n - count($recent)));

        $recentAvg = array_sum($recent) / count($recent);
        $olderAvg = array_sum($older) / count($older);

        $change = $olderAvg > 0 ? (($recentAvg - $olderAvg) / $olderAvg) * 100 : 0;
        $direction = $change > 5 ? 'increasing' : ($change < -5 ? 'decreasing' : 'stable');
        $strength = min(100, abs($change));

        return [
            'direction' => $direction,
            'strength' => round($strength, 1),
            'recent_average' => round($recentAvg, 2),
            'previous_average' => round($olderAvg, 2),
            'change_percentage' => round($change, 2),
        ];
    }

    /**
     * Detect seasonality in data.
     */
    private function detectSeasonality(array $data): array
    {
        $values = array_column($data, 'value');
        $n = count($values);
        if ($n < 14) return ['detected' => false];

        // Simple: check if there's a weekly pattern (7-day cycle)
        $weeklyPattern = [];
        for ($i = 0; $i < 7; $i++) {
            $sum = 0; $count = 0;
            for ($j = $i; $j < $n; $j += 7) {
                $sum += $values[$j];
                $count++;
            }
            $weeklyPattern[] = $count > 0 ? $sum / $count : 0;
        }

        $avg = array_sum($weeklyPattern) / 7;
        $variation = 0;
        foreach ($weeklyPattern as $v) {
            $variation += abs($v - $avg);
        }
        $variation /= 7;

        $hasSeasonality = $avg > 0 && ($variation / $avg) > 0.1;

        return [
            'detected' => $hasSeasonality,
            'type' => $hasSeasonality ? 'weekly' : null,
            'pattern' => $hasSeasonality ? $weeklyPattern : null,
        ];
    }

    /**
     * Get assumptions for the forecast.
     */
    private function getAssumptions(string $method, array $data): array
    {
        $assumptions = [
            'Historical patterns will continue into the future',
            'No major structural changes in the business',
        ];

        $methodAssumptions = [
            'moving_average' => ['Recent average is representative of near-term future'],
            'weighted_moving_average' => ['More recent data is more relevant'],
            'historical_trend' => ['Historical trend will continue linearly'],
            'linear_regression' => ['Linear relationship holds for the forecast period'],
        ];

        return array_merge($assumptions, $methodAssumptions[$method] ?? []);
    }

    /**
     * Get limitations for the forecast.
     */
    private function getLimitations(string $method, array $data, int $periodsAhead): array
    {
        $limitations = [];

        if (count($data) < 30) {
            $limitations[] = 'Limited historical data reduces forecast accuracy';
        }
        if ($periodsAhead > 90) {
            $limitations[] = 'Long-range forecasts are less reliable';
        }
        if ($method === 'moving_average') {
            $limitations[] = 'Does not capture trend or seasonality';
        }

        $limitations[] = 'Does not account for external factors (market changes, policy changes)';
        $limitations[] = 'This is a statistical estimate, not a guarantee';

        return $limitations;
    }

    /**
     * Save the forecast to the database.
     */
    private function saveForecast(array $forecast, array $data): ?int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_forecasts
                    (company_id, user_id, metric_key, forecast_method, forecast_value,
                     forecast_unit, forecast_currency, forecast_period_start, forecast_period_end,
                     historical_data, historical_range_start, historical_range_end,
                     confidence_level, confidence_score, assumptions, limitations, parameters)
                VALUES (?, ?, ?, ?, ?, ?, ?, DATE_SUB(CURDATE(), INTERVAL ? DAY), CURDATE(),
                        ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $historicalRangeStart = $data[0]['date'] ?? date('Y-m-d', strtotime('-365 days'));
            $historicalRangeEnd = end($data)['date'] ?? date('Y-m-d');

            $stmt->execute([
                $this->companyId,
                $this->userId,
                $forecast['metric_key'],
                $forecast['forecast_method'],
                $forecast['forecast_value'],
                $forecast['forecast_unit'],
                $forecast['forecast_currency'],
                $forecast['forecast_period']['periods_ahead'],
                json_encode($forecast['historical_data']),
                $historicalRangeStart,
                $historicalRangeEnd,
                $forecast['confidence']['level'],
                $forecast['confidence']['score'],
                json_encode($forecast['assumptions']),
                json_encode($forecast['limitations']),
                json_encode($forecast['forecast_period']),
            ]);

            return (int)$this->db->lastInsertId();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get the date column for a table.
     */
    private function getDateColumn(string $table): ?string
    {
        $map = [
            'invoices' => 'invoice_date',
            'customer_invoices' => 'invoice_date',
            'purchase_orders' => 'po_date',
            'inventory_items' => 'created_at',
            'clients' => 'created_at',
            'vendors' => 'created_at',
        ];
        return $map[$table] ?? null;
    }

    /**
     * Build the metric expression used for historical data.
     */
    private function buildHistoricalExpression(array $metric): ?string
    {
        if (($metric['calculation_type'] ?? '') === 'custom' && !empty($metric['calculation_sql'])) {
            return '(' . $metric['calculation_sql'] . ')';
        }

        $table = $metric['source_table'];
        $column = $metric['source_column'];

        switch ($metric['calculation_type'] ?? 'sum') {
            case 'count':
                return 'COUNT(*)';
            case 'avg':
                return "COALESCE(AVG({$table}.{$column}), 0)";
            case 'min':
                return "MIN({$table}.{$column})";
            case 'max':
                return "MAX({$table}.{$column})";
            default:
                return "COALESCE(SUM({$table}.{$column}), 0)";
        }
    }

    /**
     * Check whether a table contains a column.
     */
    private function tableHasColumn(string $table, string $column): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND column_name = ?
            ");
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function elapsed(float $startTime): int
    {
        return (int)((microtime(true) - $startTime) * 1000);
    }
}
