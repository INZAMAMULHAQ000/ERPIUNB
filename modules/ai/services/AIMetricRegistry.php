<?php
/**
 * AI F30 — Metric Registry.
 *
 * Centralized registry of all analytical metrics.
 * Prevents different AI responses from calculating the same KPI differently.
 *
 * Covers Section 4: Metric Registry, Section 5: Dimensions.
 */
class AIMetricRegistry
{
    private $db;
    private $companyId;
    private static $permissionCache = [];
    private static $metrics = [];
    private static $dimensions = [];

    public function __construct(PDO $db, int $companyId)
    {
        $this->db = $db;
        $this->companyId = $companyId;
    }

    /**
     * Load all active metrics from the database.
     */
    public function loadMetrics(): void
    {
        $stmt = $this->db->prepare("
            SELECT * FROM ai_metric_definitions WHERE is_active = 1 ORDER BY category, name
        ");
        $stmt->execute();
        self::$metrics = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['allowed_dimensions'] = json_decode($row['allowed_dimensions'] ?? '[]', true) ?: [];
            $row['allowed_filters'] = json_decode($row['allowed_filters'] ?? '[]', true) ?: [];
            self::$metrics[$row['metric_key']] = $this->normalizeMetricDefinition($row);
        }
    }

    /**
     * Load all active dimensions.
     */
    public function loadDimensions(): void
    {
        $stmt = $this->db->prepare("
            SELECT * FROM ai_dimension_definitions WHERE is_active = 1
        ");
        $stmt->execute();
        self::$dimensions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            self::$dimensions[$row['dimension_key']] = $row;
        }
    }

    /**
     * Get a metric definition by key.
     */
    public function getMetric(string $metricKey): ?array
    {
        return self::$metrics[$metricKey] ?? null;
    }

    /**
     * Get all metrics for a given category.
     */
    public function getMetricsByCategory(string $category): array
    {
        return array_filter(self::$metrics, fn($m) => $m['category'] === $category);
    }

    /**
     * Get all available metrics the user can access.
     */
    public function getAvailableMetrics(): array
    {
        $available = [];
        foreach (self::$metrics as $key => $metric) {
            if ($this->hasMetricPermission($metric)) {
                $available[] = $metric;
            }
        }
        return $available;
    }

    /**
     * Get a dimension definition.
     */
    public function getDimension(string $dimensionKey): ?array
    {
        return self::$dimensions[$dimensionKey] ?? null;
    }

    /**
     * Validate that a dimension is allowed for a metric.
     */
    public function isDimensionAllowed(string $metricKey, string $dimensionKey): bool
    {
        $metric = self::$metrics[$metricKey] ?? null;
        if (!$metric) return false;
        return in_array($dimensionKey, $metric['allowed_dimensions']);
    }

    /**
     * Validate that a filter is allowed for a metric.
     */
    public function isFilterAllowed(string $metricKey, string $filterKey): bool
    {
        $metric = self::$metrics[$metricKey] ?? null;
        if (!$metric) return false;

        // All dimensions are implicitly allowed as filters
        if ($this->isDimensionAllowed($metricKey, $filterKey)) return true;

        // Check explicit allowed filters
        $allowed = $metric['allowed_filters'] ?? [];
        return empty($allowed) || in_array($filterKey, $allowed);
    }

    /**
     * Build the SQL for a metric calculation.
     * Returns a parameterized query — never raw SQL from user input.
     */
    public function buildMetricQuery(
        string $metricKey,
        array $dimensions = [],
        array $filters = [],
        string $periodStart = '',
        string $periodEnd = '',
        string $comparisonPeriodStart = '',
        string $comparisonPeriodEnd = ''
    ): array {
        $metric = self::$metrics[$metricKey] ?? null;
        if (!$metric) {
            return ['success' => false, 'error' => 'Unknown metric: ' . $metricKey];
        }

        $params = [];
        $whereClauses = [];
        if ($this->tableHasColumn($metric['source_table'], 'company_id')) {
            $whereClauses[] = "{$metric['source_table']}.company_id = ?";
            $params[] = $this->companyId;
        }

        // Date filter
        $dateCol = $this->getDateColumn($metric['source_table']);
        if ($periodStart && $dateCol) {
            $whereClauses[] = "{$metric['source_table']}.{$dateCol} >= ?";
            $params[] = $periodStart;
        }
        if ($periodEnd && $dateCol) {
            $whereClauses[] = "{$metric['source_table']}.{$dateCol} <= ?";
            $params[] = $periodEnd;
        }

        // Custom filters
        foreach ($filters as $filterKey => $filterValue) {
            if (!$this->isFilterAllowed($metricKey, $filterKey)) {
                continue; // Skip disallowed filters
            }
            $filterCol = $this->resolveColumn($filterKey);
            if ($filterCol) {
                if (is_array($filterValue)) {
                    $placeholders = implode(',', array_fill(0, count($filterValue), '?'));
                    $whereClauses[] = "{$filterCol} IN ({$placeholders})";
                    $params = array_merge($params, $filterValue);
                } else {
                    $whereClauses[] = "{$filterCol} = ?";
                    $params[] = $filterValue;
                }
            }
        }

        $whereSQL = implode(' AND ', $whereClauses);

        // Build SELECT
        $selectParts = [];
        $groupParts = [];

        if (!empty($dimensions)) {
            foreach ($dimensions as $dim) {
                if (!$this->isDimensionAllowed($metricKey, $dim)) continue;
                $dimCol = $this->resolveDimensionColumn($metric, $dim);
                if ($dimCol) {
                    $selectParts[] = "{$dimCol} AS `{$dim}`";
                    $groupParts[] = $dimCol;
                } else {
                    return ['success' => false, 'error' => "Dimension '{$dim}' is not available for metric '{$metricKey}'."];
                }
            }
        }

        // Metric calculation
        $calcSql = $this->buildCalculationSQL($metric);
        $selectParts[] = "{$calcSql} AS metric_value";

        $sql = "SELECT " . implode(', ', $selectParts) .
               " FROM {$metric['source_table']}" .
               " WHERE {$whereSQL}";

        if (!empty($groupParts)) {
            $sql .= " GROUP BY " . implode(', ', $groupParts);
        }

        $sql .= " ORDER BY metric_value DESC";
        $sql .= " LIMIT 500"; // Safety limit

        return [
            'success' => true,
            'sql' => $sql,
            'params' => $params,
            'metric' => $metric,
            'dimensions' => $dimensions,
            'filters' => $filters,
        ];
    }

    /**
     * Build comparison query for period-over-period.
     */
    public function buildComparisonQuery(
        string $metricKey,
        array $dimensions = [],
        array $filters = [],
        string $currentStart = '',
        string $currentEnd = '',
        string $previousStart = '',
        string $previousEnd = ''
    ): array {
        $currentQuery = $this->buildMetricQuery($metricKey, $dimensions, $filters, $currentStart, $currentEnd);
        $previousQuery = $this->buildMetricQuery($metricKey, $dimensions, $filters, $previousStart, $previousEnd);

        if (!$currentQuery['success'] || !$previousQuery['success']) {
            return ['success' => false, 'error' => 'Could not build comparison query'];
        }

        return [
            'success' => true,
            'current' => $currentQuery,
            'previous' => $previousQuery,
        ];
    }

    /**
     * Execute a metric query and return results.
     */
    public function executeMetricQuery(array $queryDef): array
    {
        if (!$queryDef['success']) return $queryDef;

        try {
            $stmt = $this->db->prepare($queryDef['sql']);
            $stmt->execute($queryDef['params']);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return [
                'success' => true,
                'results' => $results,
                'row_count' => count($results),
                'metric' => $queryDef['metric'],
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Query execution failed: ' . $e->getMessage()];
        }
    }

    /**
     * Get the total for a metric over a period.
     */
    public function getMetricTotal(
        string $metricKey,
        string $periodStart = '',
        string $periodEnd = '',
        array $filters = []
    ): array {
        $query = $this->buildMetricQuery($metricKey, [], $filters, $periodStart, $periodEnd);
        if (!$query['success']) return $query;

        $result = $this->executeMetricQuery($query);
        if (!$result['success']) return $result;

        $total = 0;
        foreach ($result['results'] as $row) {
            $total += (float)($row['metric_value'] ?? 0);
        }

        return [
            'success' => true,
            'total' => $total,
            'metric' => $query['metric'],
        ];
    }

    /**
     * Get role-specific metrics.
     */
    public function getRoleMetrics(string $role): array
    {
        $roleMetricMap = [
            'finance' => ['revenue', 'gross_profit', 'outstanding_receivables', 'overdue_invoices', 'overdue_invoice_amount', 'average_invoice_value'],
            'procurement' => ['purchase_spend', 'purchase_order_count', 'supplier_count'],
            'inventory' => ['inventory_value', 'product_count'],
            'sales' => ['revenue', 'customer_count', 'average_invoice_value'],
            'management' => ['revenue', 'gross_profit', 'purchase_spend', 'inventory_value', 'outstanding_receivables', 'overdue_invoices'],
        ];

        $metricKeys = $roleMetricMap[$role] ?? $roleMetricMap['management'];
        $metrics = [];
        foreach ($metricKeys as $key) {
            if (isset(self::$metrics[$key])) {
                $metrics[] = self::$metrics[$key];
            }
        }
        return $metrics;
    }

    /**
     * Build calculation SQL for a metric.
     */
    private function buildCalculationSQL(array $metric): string
    {
        if (($metric['calculation_type'] ?? '') === 'custom' && !empty($metric['calculation_sql'])) {
            return '(' . $metric['calculation_sql'] . ')';
        }

        $col = "{$metric['source_table']}.{$metric['source_column']}";

        switch ($metric['calculation_type']) {
            case 'sum':
                return "COALESCE(SUM({$col}), 0)";
            case 'count':
                return "COUNT({$col})";
            case 'avg':
                return "COALESCE(AVG({$col}), 0)";
            case 'min':
                return "MIN({$col})";
            case 'max':
                return "MAX({$col})";
            case 'ratio':
                return "COALESCE(SUM({$col}), 0)";
            default:
                return "COALESCE(SUM({$col}), 0)";
        }
    }

    /**
     * Get the date column for a table.
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
     * Resolve a filter key to a column name.
     */
    private function resolveColumn(string $key): ?string
    {
        $dim = self::$dimensions[$key] ?? null;
        if ($dim) return "{$dim['source_column']}";

        // Common filter mappings
        $filterMap = [
            'invoice_date' => 'invoices.invoice_date',
            'order_date' => 'purchase_orders.order_date',
            'status' => null, // varies by table
        ];
        return $filterMap[$key] ?? null;
    }

    /**
     * Resolve a dimension column in the context of the metric's source table.
     */
    private function resolveDimensionColumn(array $metric, string $dimensionKey): ?string
    {
        $table = $metric['source_table'];

        switch ($dimensionKey) {
            case 'date':
                $dateCol = $this->getDateColumn($table);
                return $dateCol ? "{$table}.{$dateCol}" : null;
            case 'customer':
                return $this->tableHasColumn($table, 'client_id') ? "{$table}.client_id" : null;
            case 'supplier':
                if ($this->tableHasColumn($table, 'vendor_id')) {
                    return "{$table}.vendor_id";
                }
                return $this->tableHasColumn($table, 'supplier_id') ? "{$table}.supplier_id" : null;
            case 'product':
                if ($this->tableHasColumn($table, 'item_name')) return "{$table}.item_name";
                if ($this->tableHasColumn($table, 'product_id')) return "{$table}.product_id";
                return null;
            case 'category':
                return $this->tableHasColumn($table, 'category') ? "{$table}.category" : null;
            case 'branch':
                return $this->tableHasColumn($table, 'branch_id') ? "{$table}.branch_id" : null;
            case 'salesperson':
                return $this->tableHasColumn($table, 'salesperson_id') ? "{$table}.salesperson_id" : null;
            case 'department':
                return $this->tableHasColumn($table, 'department_id') ? "{$table}.department_id" : null;
            case 'warehouse':
                return $this->tableHasColumn($table, 'warehouse_id') ? "{$table}.warehouse_id" : null;
            default:
                $dim = self::$dimensions[$dimensionKey] ?? null;
                return $dim ? ($this->tableHasColumn($table, $dim['source_column']) ? "{$table}.{$dim['source_column']}" : null) : null;
        }
    }

    /**
     * Normalize registry entries to the local ERP schema.
     */
    private function normalizeMetricDefinition(array $row): array
    {
        $tableAliases = [
            'invoices' => 'customer_invoices',
            'customers' => 'clients',
            'suppliers' => 'vendors',
            'products' => 'inventory_items',
        ];

        if (isset($tableAliases[$row['source_table']])) {
            $row['source_table'] = $tableAliases[$row['source_table']];
        }

        switch ($row['metric_key']) {
            case 'revenue':
                $row['source_table'] = 'customer_invoices';
                $row['source_column'] = 'total_amount';
                $row['calculation_type'] = 'sum';
                break;
            case 'gross_profit':
                $row['source_table'] = 'customer_invoices';
                $row['source_column'] = 'total_amount';
                $row['calculation_type'] = 'custom';
                $row['calculation_sql'] = 'COALESCE(SUM(COALESCE(total_amount, 0)), 0)';
                break;
            case 'outstanding_receivables':
                $row['source_table'] = 'customer_invoices';
                $row['source_column'] = 'balance_amount';
                $row['calculation_type'] = 'sum';
                break;
            case 'overdue_invoices':
                $row['source_table'] = 'customer_invoices';
                $row['source_column'] = 'id';
                $row['calculation_type'] = 'count';
                break;
            case 'overdue_invoice_amount':
                $row['source_table'] = 'customer_invoices';
                $row['source_column'] = 'balance_amount';
                $row['calculation_type'] = 'sum';
                break;
            case 'purchase_spend':
                $row['source_table'] = 'purchase_orders';
                $row['source_column'] = 'grand_total';
                $row['calculation_type'] = 'sum';
                break;
            case 'inventory_value':
                $row['source_table'] = 'inventory_items';
                $row['source_column'] = 'unit_price';
                $row['calculation_type'] = 'custom';
                $row['calculation_sql'] = 'COALESCE(SUM(COALESCE(quantity, 0) * COALESCE(unit_price, 0)), 0)';
                break;
            case 'supplier_count':
                $row['source_table'] = 'vendors';
                $row['source_column'] = 'id';
                $row['calculation_type'] = 'count';
                break;
            case 'customer_count':
                $row['source_table'] = 'clients';
                $row['source_column'] = 'id';
                $row['calculation_type'] = 'count';
                break;
            case 'product_count':
                $row['source_table'] = 'inventory_items';
                $row['source_column'] = 'id';
                $row['calculation_type'] = 'count';
                break;
            case 'average_invoice_value':
                $row['source_table'] = 'customer_invoices';
                $row['source_column'] = 'total_amount';
                $row['calculation_type'] = 'avg';
                break;
            case 'purchase_order_count':
                $row['source_table'] = 'purchase_orders';
                $row['source_column'] = 'id';
                $row['calculation_type'] = 'count';
                break;
        }

        return $row;
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

    /**
     * Check if user has permission for a metric.
     */
    private function hasMetricPermission(array $metric): bool
    {
        $perm = $metric['permissions_required'] ?? null;
        if ($perm && function_exists('hasPermission')) {
            if (hasPermission($perm)) {
                return true;
            }
        }

        return $this->currentUserHasElevatedRole();
    }

    /**
     * Allow super-admin/admin fallback when the session permission layer is not available.
     */
    private function currentUserHasElevatedRole(): bool
    {
        $userId = (int) ($_SESSION['user_id'] ?? 1);
        if ($userId <= 0) {
            return false;
        }

        if (array_key_exists($userId, self::$permissionCache)) {
            return self::$permissionCache[$userId];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT r.name
                FROM user_roles ur
                JOIN roles r ON r.id = ur.role_id
                WHERE ur.user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $role = (string) ($stmt->fetchColumn() ?: '');
            self::$permissionCache[$userId] = in_array($role, ['Super Admin', 'Admin'], true);
        } catch (\Throwable $e) {
            self::$permissionCache[$userId] = false;
        }

        return self::$permissionCache[$userId];
    }

    /**
     * Get all metric keys.
     */
    public function getAllMetricKeys(): array
    {
        return array_keys(self::$metrics);
    }
}
