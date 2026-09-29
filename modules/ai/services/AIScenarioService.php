<?php
/**
 * AI F30 — Scenario Service.
 *
 * Controlled what-if analysis operating on simulations only.
 * Never writes to production tables.
 *
 * Covers Section 11-13: Scenario Analysis, Variables, Safety.
 */
class AIScenarioService
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
     * Run a what-if scenario.
     * Operates on a simulation of data — never modifies production.
     */
    public function runScenario(string $metricKey, array $variables, string $name = '', string $description = ''): array
    {
        $startTime = microtime(true);

        // Step 1: Validate variables
        $validation = $this->validateVariables($variables);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        // Step 2: Get baseline value
        $baseline = $this->getBaselineValue($metricKey);
        if (!$baseline['success']) {
            return $baseline;
        }

        // Step 3: Calculate scenario value (simulation only)
        $scenarioResult = $this->calculateScenario($metricKey, $baseline['value'], $variables);

        // Step 4: Build result
        $result = [
            'success' => true,
            'metric_key' => $metricKey,
            'metric_name' => $baseline['metric_name'] ?? $metricKey,
            'baseline_value' => $baseline['value'],
            'scenario_value' => $scenarioResult['value'],
            'impact_value' => $scenarioResult['value'] - $baseline['value'],
            'impact_percentage' => $baseline['value'] != 0
                ? round((($scenarioResult['value'] - $baseline['value']) / abs($baseline['value'])) * 100, 2)
                : 0,
            'variables' => $variables,
            'affected_records' => $scenarioResult['affected_records'] ?? [],
            'safety_label' => 'Simulation — no ERP data has been changed',
            'methodology' => $scenarioResult['methodology'] ?? 'Applied variable adjustments to baseline',
            'latency_ms' => (int)((microtime(true) - $startTime) * 1000),
        ];

        // Step 5: Save the scenario
        $result['scenario_id'] = $this->saveScenario($name ?: "Scenario for {$metricKey}", $description, $result);

        return $result;
    }

    /**
     * Parse a natural language scenario request.
     */
    public function parseScenarioRequest(string $text, array $context = []): array
    {
        $lower = strtolower($text);
        $variables = [];

        // Detect variable changes
        $patterns = [
            'sales\\s+(?:increase|grow|rise|up)\\s+(\\d+)%?' => ['key' => 'sales_growth', 'sign' => 1],
            'sales\\s+(?:decrease|decline|drop|fall|down)\\s+(\\d+)%?' => ['key' => 'sales_growth', 'sign' => -1],
            '(?:purchase|cost|price)\\s+(?:increase|grow|rise|up|higher)\\s+(\\d+)%?' => ['key' => 'purchase_price_change', 'sign' => 1],
            '(?:purchase|cost|price)\\s+(?:decrease|decline|drop|lower)\\s+(\\d+)%?' => ['key' => 'purchase_price_change', 'sign' => -1],
            '(?:demand|order|quantity)\\s+(?:increase|grow|rise)\\s+(\\d+)%?' => ['key' => 'demand_rate', 'sign' => 1],
            '(?:demand|order|quantity)\\s+(?:decrease|decline|drop)\\s+(\\d+)%?' => ['key' => 'demand_rate', 'sign' => -1],
            'payment\\s+(?:delay|late|slower)\\s+(\\d+)\\s+days?' => ['key' => 'payment_delay', 'sign' => 1],
            'lead\\s+time\\s+(?:increase|longer|more)\\s+(\\d+)%?' => ['key' => 'lead_time', 'sign' => 1],
            'lead\\s+time\\s+(?:decrease|shorter|less|reduce)\\s+(\\d+)%?' => ['key' => 'lead_time', 'sign' => -1],
            'reorder\\s+(?:level|point)\\s+(?:increase|higher|more)\\s+(\\d+)%?' => ['key' => 'reorder_level', 'sign' => 1],
            'reorder\\s+(?:level|point)\\s+(?:decrease|lower|less|reduce)\\s+(\\d+)%?' => ['key' => 'reorder_level', 'sign' => -1],
        ];

        foreach ($patterns as $pattern => $config) {
            if (preg_match('/' . $pattern . '/i', $lower, $m)) {
                $variables[$config['key']] = (float)$m[1] * $config['sign'];
            }
        }

        // Detect metric
        $metricMap = [
            'revenue' => 'revenue', 'sales' => 'revenue',
            'purchase' => 'purchase_spend', 'cost' => 'purchase_spend', 'procurement' => 'purchase_spend',
            'inventory' => 'inventory_value', 'stock' => 'inventory_value',
        ];
        $metricKey = null;
        foreach ($metricMap as $keyword => $mk) {
            if (strpos($lower, $keyword) !== false) {
                $metricKey = $mk;
                break;
            }
        }

        if (empty($variables)) {
            return [
                'success' => false,
                'error' => 'Could not identify scenario variables. Try: "What if sales increase 10%?" or "What if supplier prices increase 5%?"',
            ];
        }

        return [
            'success' => true,
            'metric_key' => $metricKey,
            'variables' => $variables,
            'description' => $text,
        ];
    }

    /**
     * Get available scenario variables.
     */
    public function getAvailableVariables(): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM ai_scenario_variables WHERE is_active = 1");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Validate scenario variables.
     */
    private function validateVariables(array $variables): array
    {
        $available = $this->getAvailableVariables();
        $availableKeys = array_column($available, 'variable_key');

        foreach ($variables as $key => $value) {
            if (!in_array($key, $availableKeys)) {
                return ['valid' => false, 'error' => "Unknown scenario variable: {$key}"];
            }

            $varDef = null;
            foreach ($available as $v) {
                if ($v['variable_key'] === $key) { $varDef = $v; break; }
            }

            if ($varDef) {
                if ($varDef['min_value'] !== null && $value < $varDef['min_value']) {
                    return ['valid' => false, 'error' => "{$key} value {$value} is below minimum {$varDef['min_value']}"];
                }
                if ($varDef['max_value'] !== null && $value > $varDef['max_value']) {
                    return ['valid' => false, 'error' => "{$key} value {$value} is above maximum {$varDef['max_value']}"];
                }
            }
        }

        return ['valid' => true];
    }

    /**
     * Get the current baseline value for a metric.
     */
    private function getBaselineValue(string $metricKey): array
    {
        $registry = new AIMetricRegistry($this->db, $this->companyId);
        $registry->loadMetrics();
        $metric = $registry->getMetric($metricKey);

        if (!$metric) {
            return ['success' => false, 'error' => 'Unknown metric: ' . $metricKey];
        }

        // Get current period value (last 30 days)
        $result = $registry->getMetricTotal($metricKey, date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'value' => $result['total'],
            'metric_name' => $metric['name'],
        ];
    }

    /**
     * Calculate scenario impact (simulation only).
     */
    private function calculateScenario(string $metricKey, float $baseline, array $variables): array
    {
        $scenarioValue = $baseline;
        $affectedRecords = [];
        $methodology = 'Applied adjustments:';

        foreach ($variables as $key => $value) {
            switch ($key) {
                case 'sales_growth':
                    $change = $baseline * ($value / 100);
                    $scenarioValue += $change;
                    $methodology .= " Sales adjusted by {$value}% ({$change}).";
                    $affectedRecords[] = 'sales';
                    break;
                case 'purchase_price_change':
                    // For purchase-based metrics, adjust proportionally
                    $change = $baseline * ($value / 100);
                    $scenarioValue += $change;
                    $methodology .= " Purchase costs adjusted by {$value}% ({$change}).";
                    $affectedRecords[] = 'purchases';
                    break;
                case 'quantity_change':
                    $change = $baseline * ($value / 100);
                    $scenarioValue += $change;
                    $methodology .= " Quantities adjusted by {$value}% ({$change}).";
                    $affectedRecords[] = 'quantities';
                    break;
                case 'payment_delay':
                    // Payment delay doesn't directly change the metric value
                    // but affects cash flow timing
                    $methodology .= " Payment delayed by {$value} days (affects cash flow timing).";
                    $affectedRecords[] = 'cash_flow';
                    break;
                case 'demand_rate':
                    $change = $baseline * ($value / 100);
                    $scenarioValue += $change;
                    $methodology .= " Demand adjusted by {$value}% ({$change}).";
                    $affectedRecords[] = 'demand';
                    break;
                default:
                    $change = $baseline * ($value / 100);
                    $scenarioValue += $change;
                    $methodology .= " {$key} adjusted by {$value}%.";
                    $affectedRecords[] = $key;
            }
        }

        return [
            'value' => round($scenarioValue, 4),
            'affected_records' => array_unique($affectedRecords),
            'methodology' => $methodology,
        ];
    }

    /**
     * Save a scenario result.
     */
    private function saveScenario(string $name, string $description, array $result): ?int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_scenarios
                    (company_id, user_id, name, description, base_metric_key, variables,
                     baseline_value, scenario_value, impact_value, impact_percentage,
                     affected_records, simulation_result, safety_label)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $this->companyId,
                $this->userId,
                $name,
                $description,
                $result['metric_key'],
                json_encode($result['variables']),
                $result['baseline_value'],
                $result['scenario_value'],
                $result['impact_value'],
                $result['impact_percentage'],
                json_encode($result['affected_records']),
                json_encode(['methodology' => $result['methodology']]),
                $result['safety_label'],
            ]);
            return (int)$this->db->lastInsertId();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
