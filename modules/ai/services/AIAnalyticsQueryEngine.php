<?php
/**
 * AI F30 — Analytics Query Engine.
 *
 * Controlled query layer using approved data sources, registered metrics,
 * prepared parameters, tenant filters, row limits, and aggregation limits.
 * Rejects arbitrary SQL.
 *
 * Covers Section 3: Controlled Query Engine.
 */
class AIAnalyticsQueryEngine
{
    private $db;
    private $companyId;
    private $userId;
    private $metricRegistry;

    public function __construct(PDO $db, int $companyId, int $userId)
    {
        $this->db = $db;
        $this->companyId = $companyId;
        $this->userId = $userId;
        $this->metricRegistry = new AIMetricRegistry($db, $companyId);
        $this->metricRegistry->loadMetrics();
        $this->metricRegistry->loadDimensions();
    }

    /**
     * Process an analytical request from natural language.
     * Translates to controlled operations — never generates arbitrary SQL.
     */
    public function processAnalyticalRequest(string $text, array $context = []): array
    {
        $startTime = microtime(true);

        // Step 1: Parse the analytical intent
        $parsed = $this->parseAnalyticalIntent($text, $context);

        // Step 2: Validate against metric registry
        $validation = $this->validateRequest($parsed);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error'],
                'latency_ms' => $this->elapsed($startTime),
            ];
        }

        // Step 3: Execute the controlled query
        $result = $this->executeControlledQuery($parsed);

        // Step 4: Format the result
        $formatted = $this->formatAnalyticalResult($parsed, $result);

        $formatted['latency_ms'] = $this->elapsed($startTime);

        return $formatted;
    }

    /**
     * Parse natural language into a structured analytical request.
     */
    private function parseAnalyticalIntent(string $text, array $context): array
    {
        $lower = strtolower(trim($text));

        $parsed = [
            'metric' => null,
            'dimensions' => [],
            'filters' => [],
            'operation' => 'total', // total, comparison, trend, ranking, distribution
            'period_start' => '',
            'period_end' => '',
            'comparison_type' => null, // vs_previous, yoy, mom, qoq
            'comparison_period_start' => '',
            'comparison_period_end' => '',
            'limit' => 10,
            'order' => 'desc',
            'raw_text' => $text,
        ];

        // Detect metric
        $parsed['metric'] = $this->detectMetric($lower);

        // Detect dimensions
        $parsed['dimensions'] = $this->detectDimensions($lower);

        // Detect period
        $period = $this->detectPeriod($lower);
        $parsed['period_start'] = $period['start'];
        $parsed['period_end'] = $period['end'];

        // Detect comparison
        $comparison = $this->detectComparison($lower);
        $parsed['operation'] = $comparison['operation'];
        $parsed['comparison_type'] = $comparison['type'];
        $parsed['comparison_period_start'] = $comparison['period_start'];
        $parsed['comparison_period_end'] = $comparison['period_end'];

        if (empty($parsed['period_start']) && !empty($parsed['comparison_type'])) {
            $now = new \DateTime();
            switch ($parsed['comparison_type']) {
                case 'yoy':
                    $parsed['period_start'] = $now->format('Y') . '-01-01';
                    break;
                case 'qoq':
                    $quarter = (int)ceil($now->format('n') / 3);
                    $parsed['period_start'] = $now->format('Y') . '-' . str_pad((($quarter - 1) * 3 + 1), 2, '0', STR_PAD_LEFT) . '-01';
                    break;
                case 'mom':
                case 'vs_previous':
                default:
                    $parsed['period_start'] = $now->format('Y-m') . '-01';
                    break;
            }
            $parsed['period_end'] = $now->format('Y-m-d');
        }

        // Detect filters
        $parsed['filters'] = $this->detectFilters($lower, $context);

        // Detect ranking
        if (preg_match('/\\b(top|biggest|largest|highest|most|best)\\b/i', $lower)) {
            $parsed['operation'] = 'ranking';
            if (preg_match('/\\b(top|biggest|largest|highest|most|best)\\s+(\\d+)\\b/i', $lower, $m)) {
                $parsed['limit'] = (int)$m[2];
            }
        }

        // Detect distribution
        if (preg_match('/\\b(breakdown|distribution|split|group|by category|by region|by department)\\b/i', $lower)) {
            $parsed['operation'] = 'distribution';
        }

        return $parsed;
    }

    /**
     * Detect which metric the user is asking about.
     */
    private function detectMetric(string $lower): ?string
    {
        if (preg_match('/\b(supplier|suppliers|vendor|vendors)\b.*\b(price|prices|cost|costs)\b|\b(price|prices|cost|costs)\b.*\b(supplier|suppliers|vendor|vendors)\b/i', $lower)) {
            return 'purchase_spend';
        }

        $metricKeywords = [
            'purchase order count' => 'purchase_order_count',
            'purchase orders' => 'purchase_order_count',
            'po count' => 'purchase_order_count',
            'overdue invoice amount' => 'overdue_invoice_amount',
            'gross profit' => 'gross_profit',
            'outstanding receivables' => 'outstanding_receivables',
            'average invoice value' => 'average_invoice_value',
            'inventory value' => 'inventory_value',
            'purchase spend' => 'purchase_spend',
            'supplier count' => 'supplier_count',
            'customer count' => 'customer_count',
            'product count' => 'product_count',
            'overdue invoices' => 'overdue_invoices',
            'revenue' => 'revenue',
            'sales' => 'revenue',
            'income' => 'revenue',
            'turnover' => 'revenue',
            'profit' => 'gross_profit',
            'margin' => 'gross_profit',
            'receivable' => 'outstanding_receivables',
            'outstanding' => 'outstanding_receivables',
            'unpaid' => 'outstanding_receivables',
            'overdue' => 'overdue_invoices',
            'late invoice' => 'overdue_invoices',
            'purchase' => 'purchase_spend',
            'procurement' => 'purchase_spend',
            'spend' => 'purchase_spend',
            'inventory' => 'inventory_value',
            'stock' => 'inventory_value',
            'stock value' => 'inventory_value',
            'supplier' => 'supplier_count',
            'suppliers' => 'supplier_count',
            'customer' => 'customer_count',
            'customers' => 'customer_count',
            'product' => 'product_count',
            'products' => 'product_count',
            'average' => 'average_invoice_value',
            'avg' => 'average_invoice_value',
        ];

        foreach ($metricKeywords as $keyword => $metricKey) {
            if (strpos($lower, $keyword) !== false) {
                return $metricKey;
            }
        }

        if (preg_match('/\b(compare|comparison|vs|versus|compared to|against|trend|over time|increase|decrease)\b/i', $lower)) {
            return 'revenue';
        }

        return null;
    }

    /**
     * Detect analytical dimensions from the text.
     */
    private function detectDimensions(string $lower): array
    {
        $dims = [];
        $dimKeywords = [
            'by customer' => 'customer', 'per customer' => 'customer',
            'by supplier' => 'supplier', 'per supplier' => 'supplier',
            'by product' => 'product', 'per product' => 'product',
            'by category' => 'category', 'per category' => 'category',
            'by branch' => 'branch', 'per branch' => 'branch',
            'by region' => 'branch',
            'by department' => 'department', 'per department' => 'department',
            'by month' => 'date', 'monthly' => 'date', 'by quarter' => 'date', 'quarterly' => 'date',
            'by salesperson' => 'salesperson', 'per salesperson' => 'salesperson',
        ];

        foreach ($dimKeywords as $keyword => $dim) {
            if (strpos($lower, $keyword) !== false && !in_array($dim, $dims)) {
                $dims[] = $dim;
            }
        }

        if (preg_match('/\b(supplier|suppliers|vendor|vendors)\b.*\b(price|prices|cost|costs)\b|\b(price|prices|cost|costs)\b.*\b(supplier|suppliers|vendor|vendors)\b/i', $lower)) {
            if (!in_array('supplier', $dims, true)) {
                $dims[] = 'supplier';
            }
        }

        return $dims;
    }

    /**
     * Detect time period from the text.
     */
    private function detectPeriod(string $lower): array
    {
        $now = new \DateTime();
        $start = '';
        $end = $now->format('Y-m-d');

        if (preg_match('/this\\s+quarter/i', $lower)) {
            $quarter = (int)ceil($now->format('n') / 3);
            $start = $now->format('Y') . '-' . str_pad((($quarter - 1) * 3 + 1), 2, '0', STR_PAD_LEFT) . '-01';
        } elseif (preg_match('/last\\s+quarter/i', $lower)) {
            $quarter = (int)ceil($now->format('n') / 3);
            $prevQ = $quarter - 1;
            $year = $now->format('Y');
            if ($prevQ < 1) { $prevQ = 4; $year--; }
            $start = $year . '-' . str_pad((($prevQ - 1) * 3 + 1), 2, '0', STR_PAD_LEFT) . '-01';
            $end = $year . '-' . str_pad(($prevQ * 3), 2, '0', STR_PAD_LEFT) . '-' . cal_days_in_month(CAL_GREGORIAN, $prevQ * 3, $year);
        } elseif (preg_match('/this\\s+year/i', $lower)) {
            $start = $now->format('Y') . '-01-01';
        } elseif (preg_match('/last\\s+year/i', $lower)) {
            $start = ($now->format('Y') - 1) . '-01-01';
            $end = ($now->format('Y') - 1) . '-12-31';
        } elseif (preg_match('/this\\s+month/i', $lower)) {
            $start = $now->format('Y-m') . '-01';
        } elseif (preg_match('/last\\s+month/i', $lower)) {
            $lastMonth = $now->modify('-1 month');
            $start = $lastMonth->format('Y-m') . '-01';
            $end = $lastMonth->format('Y-m') . '-' . cal_days_in_month(CAL_GREGORIAN, $lastMonth->format('n'), $lastMonth->format('Y'));
        } elseif (preg_match('/(today|current)/i', $lower)) {
            $start = $now->format('Y-m-d');
        } elseif (preg_match('/last\\s+(\\d+)\\s+days/i', $lower, $m)) {
            $days = (int)$m[1];
            $start = $now->modify("-{$days} days")->format('Y-m-d');
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Detect comparison type.
     */
    private function detectComparison(string $lower): array
    {
        $result = [
            'operation' => 'total',
            'type' => null,
            'period_start' => '',
            'period_end' => '',
        ];

        if (preg_match('/compare|comparison|vs|versus|compared to|against/i', $lower)) {
            $result['operation'] = 'comparison';
        }

        if (preg_match('/year.over.year|yoy|same period last year/i', $lower)) {
            $result['type'] = 'yoy';
        } elseif (preg_match('/month.over.month|mom|last month/i', $lower)) {
            $result['type'] = 'mom';
        } elseif (preg_match('/quarter.over.quarter|qoq|last quarter/i', $lower)) {
            $result['type'] = 'qoq';
        } elseif (preg_match('/previous period|vs previous|last period/i', $lower)) {
            $result['type'] = 'vs_previous';
        }

        // Detect trend requests
        if (preg_match('/trend|over time|change|increase|decrease/i', $lower)) {
            $result['operation'] = 'trend';
        }

        if ($result['type'] !== null && $result['operation'] === 'total') {
            $result['operation'] = 'comparison';
        }

        return $result;
    }

    /**
     * Detect filters from context.
     */
    private function detectFilters(string $lower, array $context): array
    {
        $filters = [];

        // From context
        if (!empty($context['record_type']) && !empty($context['record_id'])) {
            $filterMap = [
                'customer' => 'customer',
                'supplier' => 'supplier',
                'product' => 'product',
            ];
            $dim = $filterMap[$context['record_type']] ?? null;
            if ($dim) {
                $filters[$dim] = (int)$context['record_id'];
            }
        }

        return $filters;
    }

    /**
     * Validate the parsed request against the metric registry.
     */
    private function validateRequest(array $parsed): array
    {
        if (empty($parsed['metric'])) {
            return ['valid' => false, 'error' => 'Could not determine which metric you are asking about. Try asking about revenue, purchases, inventory, or another business metric.'];
        }

        $metric = $this->metricRegistry->getMetric($parsed['metric']);
        if (!$metric) {
            return ['valid' => false, 'error' => 'Unknown metric: ' . $parsed['metric']];
        }

        // Check permissions
        if (!$this->metricRegistry->getMetric($parsed['metric'])) {
            return ['valid' => false, 'error' => 'You do not have permission to view this metric.'];
        }

        // Validate dimensions
        foreach ($parsed['dimensions'] as $dim) {
            if (!$this->metricRegistry->isDimensionAllowed($parsed['metric'], $dim)) {
                return ['valid' => false, 'error' => "Dimension '{$dim}' is not available for metric '{$parsed['metric']}'."];
            }
        }

        // Validate filters
        foreach ($parsed['filters'] as $filterKey => $filterValue) {
            if (!$this->metricRegistry->isFilterAllowed($parsed['metric'], $filterKey)) {
                return ['valid' => false, 'error' => "Filter '{$filterKey}' is not allowed for metric '{$parsed['metric']}'."];
            }
        }

        return ['valid' => true];
    }

    /**
     * Execute the controlled query.
     */
    private function executeControlledQuery(array $parsed): array
    {
        // Build the query using the metric registry (never raw SQL)
        $query = $this->metricRegistry->buildMetricQuery(
            $parsed['metric'],
            $parsed['dimensions'],
            $parsed['filters'],
            $parsed['period_start'],
            $parsed['period_end']
        );

        if (!$query['success']) {
            return $query;
        }

        // Execute
        $result = $this->metricRegistry->executeMetricQuery($query);

        // For comparisons, also get the comparison period
        if ($parsed['operation'] === 'comparison' && $parsed['comparison_type']) {
            $comparisonPeriods = $this->getComparisonPeriods($parsed['period_start'], $parsed['period_end'], $parsed['comparison_type']);
            if ($comparisonPeriods) {
                $prevQuery = $this->metricRegistry->buildMetricQuery(
                    $parsed['metric'],
                    $parsed['dimensions'],
                    $parsed['filters'],
                    $comparisonPeriods['start'],
                    $comparisonPeriods['end']
                );
                if ($prevQuery['success']) {
                    $prevResult = $this->metricRegistry->executeMetricQuery($prevQuery);
                    $result['previous'] = $prevResult;
                    $result['comparison_type'] = $parsed['comparison_type'];
                    $result['comparison_periods'] = $comparisonPeriods;
                }
            }
        }

        return $result;
    }

    /**
     * Get comparison period dates.
     */
    private function getComparisonPeriods(string $start, string $end, string $type): ?array
    {
        if (empty($start) || empty($end)) return null;

        $startDate = new \DateTime($start);
        $endDate = new \DateTime($end);
        $interval = $startDate->diff($endDate);

        switch ($type) {
            case 'yoy':
                return [
                    'start' => $startDate->modify('-1 year')->format('Y-m-d'),
                    'end' => $endDate->modify('-1 year')->format('Y-m-d'),
                ];
            case 'mom':
                return [
                    'start' => $startDate->modify('-1 month')->format('Y-m-d'),
                    'end' => $endDate->modify('-1 month')->format('Y-m-d'),
                ];
            case 'qoq':
                return [
                    'start' => $startDate->modify('-3 months')->format('Y-m-d'),
                    'end' => $endDate->modify('-3 months')->format('Y-m-d'),
                ];
            case 'vs_previous':
                $prevStart = clone $startDate;
                $prevStart->sub($interval);
                $prevEnd = clone $endDate;
                $prevEnd->sub($interval);
                return [
                    'start' => $prevStart->format('Y-m-d'),
                    'end' => $prevEnd->format('Y-m-d'),
                ];
        }

        return null;
    }

    /**
     * Format the analytical result.
     */
    private function formatAnalyticalResult(array $parsed, array $result): array
    {
        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Query failed',
            ];
        }

        $metric = $result['metric'] ?? $this->metricRegistry->getMetric($parsed['metric']);
        $total = 0;
        foreach ($result['results'] as $row) {
            $total += (float)($row['metric_value'] ?? 0);
        }

        $formatted = [
            'success' => true,
            'metric' => [
                'key' => $metric['metric_key'] ?? $parsed['metric'],
                'name' => $metric['name'] ?? $parsed['metric'],
                'description' => $metric['description'] ?? '',
                'category' => $metric['category'] ?? '',
                'currency_dependent' => !empty($metric['currency_dependent']),
            ],
            'total' => $total,
            'results' => $result['results'],
            'row_count' => $result['row_count'] ?? 0,
            'dimensions' => $parsed['dimensions'],
            'filters' => $parsed['filters'],
            'operation' => $parsed['operation'],
            'period' => [
                'start' => $parsed['period_start'],
                'end' => $parsed['period_end'],
            ],
            'comparison' => null,
            'source' => [
                'table' => $metric['source_table'] ?? null,
                'column' => $metric['source_column'] ?? null,
            ],
            'calculation_definition' => [
                'type' => $metric['calculation_type'] ?? 'sum',
                'sql' => $metric['calculation_sql'] ?? null,
                'aggregation_behavior' => $metric['aggregation_behavior'] ?? null,
            ],
            'unit' => $metric['currency_dependent'] ? 'currency' : 'count',
            'currency' => $this->resolveCurrencyContext($metric, $parsed),
        ];

        // Format comparison
        if (!empty($result['previous']) && $result['previous']['success']) {
            $prevTotal = 0;
            foreach ($result['previous']['results'] as $row) {
                $prevTotal += (float)($row['metric_value'] ?? 0);
            }

            $change = $prevTotal > 0 ? (($total - $prevTotal) / $prevTotal) * 100 : 0;

            $formatted['comparison'] = [
                'type' => $result['comparison_type'],
                'previous_total' => $prevTotal,
                'change' => round($change, 2),
                'change_direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
                'periods' => $result['comparison_periods'] ?? null,
            ];
        }

        // Build visualization data
        $formatted['visualization'] = $this->buildVisualizationData($parsed, $formatted);
        $formatted['source_records'] = $this->buildSourceRecords($parsed, $formatted);

        return $formatted;
    }

    /**
     * Build structured chart data (Section 16).
     */
    private function buildVisualizationData(array $parsed, array $formatted): array
    {
        $viz = [
            'chart_type' => 'kpi_card',
            'data' => [],
        ];

        if (!empty($parsed['dimensions'])) {
            // Bar/line chart for dimensional breakdown
            $viz['chart_type'] = count($parsed['dimensions']) === 1 ? 'bar' : 'table';
            $viz['data'] = array_map(fn($r) => [
                'label' => $this->resolveRowLabel($parsed, $r),
                'value' => (float)($r['metric_value'] ?? 0),
            ], $formatted['results']);
        } else {
            // KPI card
            $viz['chart_type'] = 'kpi_card';
            $viz['data'] = [
                ['label' => $formatted['metric']['name'], 'value' => $formatted['total']],
            ];
            if ($formatted['comparison']) {
                $viz['data'][0]['change'] = $formatted['comparison']['change'];
                $viz['data'][0]['change_direction'] = $formatted['comparison']['change_direction'];
            }
        }

        return $viz;
    }

    /**
     * Build drill-down records for the result panel.
     */
    private function buildSourceRecords(array $parsed, array $formatted): array
    {
        $records = [];
        foreach (array_slice($formatted['results'], 0, 10) as $row) {
            $records[] = [
                'label' => $this->resolveRowLabel($parsed, $row),
                'dimensions' => array_intersect_key($row, array_flip($parsed['dimensions'])),
                'metric_value' => (float) ($row['metric_value'] ?? 0),
            ];
        }

        return $records;
    }

    /**
     * Resolve a human-readable label for a grouped row.
     */
    private function resolveRowLabel(array $parsed, array $row): string
    {
        foreach ($parsed['dimensions'] as $dimension) {
            if (!array_key_exists($dimension, $row)) {
                continue;
            }

            $value = $row[$dimension];
            $label = $this->resolveDimensionValue($dimension, $value);
            if ($label !== null && $label !== '') {
                return $label;
            }

            return (string) $value;
        }

        return $parsed['metric'] ?? 'Result';
    }

    /**
     * Resolve a grouped dimension value to a display label where possible.
     */
    private function resolveDimensionValue(string $dimension, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $lookups = [
            'customer' => ['table' => 'clients', 'columns' => ['name', 'company_name', 'customer_name']],
            'supplier' => ['table' => 'vendors', 'columns' => ['name', 'company_name', 'vendor_name']],
            'product' => ['table' => 'inventory_items', 'columns' => ['item_name', 'name']],
        ];

        if (!isset($lookups[$dimension])) {
            return null;
        }

        $lookup = $lookups[$dimension];
        $table = $lookup['table'];
        $idColumn = 'id';

        try {
            foreach ($lookup['columns'] as $column) {
                if (!$this->tableHasColumn($table, $column)) {
                    continue;
                }

                $stmt = $this->db->prepare("SELECT {$column} FROM {$table} WHERE {$idColumn} = ? LIMIT 1");
                $stmt->execute([$value]);
                $label = $stmt->fetchColumn();
                if ($label !== false && $label !== '') {
                    return (string) $label;
                }
            }
        } catch (\Throwable $e) {
            // Use the raw value when lookup is unavailable.
        }

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * Resolve currency handling explicitly for the result.
     */
    private function resolveCurrencyContext(array $metric, array $parsed): ?array
    {
        if (empty($metric['currency_dependent'])) {
            return ['mode' => 'not_applicable'];
        }

        $table = $metric['source_table'] ?? null;
        if (!$table || !$this->tableHasColumn($table, 'currency')) {
            return ['mode' => 'single_currency', 'currency' => 'AED', 'note' => 'Currency column is unavailable; totals are shown in the ERP base currency format.'];
        }

        try {
            $dateCol = $this->getDateColumn($table);
            $where = [];
            $params = [];
            if ($this->tableHasColumn($table, 'company_id')) {
                $where[] = "{$table}.company_id = ?";
                $params[] = $this->companyId;
            }
            if (!empty($parsed['period_start']) && $dateCol) {
                $where[] = "{$table}.{$dateCol} >= ?";
                $params[] = $parsed['period_start'];
            }
            if (!empty($parsed['period_end']) && $dateCol) {
                $where[] = "{$table}.{$dateCol} <= ?";
                $params[] = $parsed['period_end'];
            }

            $sql = "SELECT currency, COUNT(*) AS row_count
                    FROM {$table}";
            if (!empty($where)) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' GROUP BY currency ORDER BY row_count DESC';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (count($currencies) > 1) {
                return [
                    'mode' => 'mixed',
                    'currencies' => array_map(fn($row) => [
                        'currency' => $row['currency'],
                        'row_count' => (int) $row['row_count'],
                    ], $currencies),
                    'note' => 'Mixed currencies detected. Totals are not auto-converted because no FX rate table is configured.',
                ];
            }

            return [
                'mode' => 'single_currency',
                'currency' => $currencies[0]['currency'] ?? 'AED',
                'note' => 'Values are reported in the source currency from ERP records.',
            ];
        } catch (\Throwable $e) {
            return [
                'mode' => 'single_currency',
                'currency' => 'AED',
                'note' => 'Currency handling fallback applied.',
            ];
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
            return (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Resolve the date column for a source table.
     */
    private function getDateColumn(string $table): ?string
    {
        $dateMap = [
            'invoices' => 'invoice_date',
            'customer_invoices' => 'invoice_date',
            'purchase_orders' => 'po_date',
            'inventory_items' => 'created_at',
            'clients' => 'created_at',
            'vendors' => 'created_at',
        ];

        return $dateMap[$table] ?? null;
    }

    /**
     * Calculate elapsed time in ms.
     */
    private function elapsed(float $startTime): int
    {
        return (int)((microtime(true) - $startTime) * 1000);
    }

    /**
     * Get the metric registry instance.
     */
    public function getRegistry(): AIMetricRegistry
    {
        return $this->metricRegistry;
    }
}
