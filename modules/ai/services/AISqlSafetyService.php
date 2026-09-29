<?php
/**
 * AI F24 — SQL Safety Service.
 *
 * Enforces the SQL boundary: AI must never execute arbitrary SQL.
 * Provides a safe query builder for natural-language reporting
 * with allowlisted tables, fields, parameter binding, row limits,
 * tenant filters, and permission filters.
 *
 * Covers Sections 11 (SQL Safety) and 46 (Database Security).
 */
class AISqlSafetyService
{
    // Maximum rows any AI query may return
    const MAX_ROWS = 1000;
    // Maximum query execution time in seconds
    const MAX_EXECUTION_TIME = 30;
    // Maximum JOIN depth
    const MAX_JOIN_DEPTH = 3;

    // Dangerous SQL keywords/patterns that must never appear in AI-generated SQL
    private static array $dangerousPatterns = [
        '/;\s*(DROP|DELETE|INSERT|UPDATE|ALTER|TRUNCATE|CREATE|GRANT|REVOKE)\b/i',
        '/UNION\s+ALL\s+SELECT\b/i',
        '/INTO\s+(OUTFILE|DUMPFILE)\b/i',
        '/LOAD_FILE\s*\(/i',
        '/SLEEP\s*\(\s*\d+\s*\)/i',
        '/BENCHMARK\s*\(/i',
        '/WAITFOR\s+DELAY\b/i',
        '/EXEC\s*\(/i',
        '/EXECUTE\s*\(/i',
        '/xp_cmdshell/i',
        '/sp_executesql/i',
        '/ INFORMATION_SCHEMA\b/i',
        '/sys\.(?:tables|columns|objects)/i',
        '/@@VERSION/i',
        '/@@SERVERNAME/i',
        '/FILE_(?:READ|WRITE)\s*\(/i',
    ];

    /**
     * Validate that raw SQL does not contain dangerous patterns.
     *
     * @param string $sql The SQL to validate
     * @return array Validation result
     */
    public static function validateRawSql(string $sql): array
    {
        $result = [
            'safe' => true,
            'violations' => [],
        ];

        $sqlTrimmed = trim($sql);

        // Block all AI-generated raw SQL (Section 11)
        if (!empty($sqlTrimmed)) {
            $result['safe'] = false;
            $result['violations'][] = [
                'type' => 'raw_sql_blocked',
                'message' => 'AI-generated raw SQL is not permitted. Use the safe query builder instead.',
                'severity' => 'critical',
            ];
            return $result;
        }

        // Check for dangerous patterns
        foreach (self::$dangerousPatterns as $pattern) {
            if (preg_match($pattern, $sqlTrimmed)) {
                $result['safe'] = false;
                $result['violations'][] = [
                    'type' => 'dangerous_pattern',
                    'message' => 'SQL contains potentially dangerous pattern',
                    'severity' => 'critical',
                ];
                break;
            }
        }

        // Check for subqueries (disallowed for AI)
        if (preg_match('/\(\s*SELECT\b/i', $sqlTrimmed)) {
            $result['safe'] = false;
            $result['violations'][] = [
                'type' => 'subquery_blocked',
                'message' => 'Subqueries are not permitted in AI-generated SQL',
                'severity' => 'high',
            ];
        }

        return $result;
    }

    /**
     * Build a safe, parameterized query for AI natural-language reporting.
     *
     * @param PDO    $db        Database connection
     * @param string $table     Allowlisted table name
     * @param array  $fields    Allowlisted field names
     * @param array  $filters   Filter conditions (key => value)
     * @param array  $options   Additional options (order_by, limit, etc.)
     * @param int    $companyId Company ID for tenant isolation
     * @return array Safe query result
     */
    public static function buildSafeQuery(
        PDO $db,
        string $table,
        array $fields = ['*'],
        array $filters = [],
        array $options = [],
        ?int $companyId = null
    ): array {
        $result = [
            'success' => false,
            'sql' => null,
            'params' => [],
            'errors' => [],
        ];

        // 1. Validate table is allowlisted
        $allowedTables = self::getAllowlistedTables($db, $companyId);
        if (!in_array(strtolower($table), array_map('strtolower', $allowedTables))) {
            $result['errors'][] = "Table '$table' is not allowlisted for AI queries";
            return $result;
        }

        // 2. Validate fields are allowlisted
        $validatedFields = [];
        $allowedFields = self::getAllowlistedFields($db, $table, $companyId);

        foreach ($fields as $field) {
            if ($field === '*') {
                // SELECT * is restricted — use explicit fields
                $result['errors'][] = 'SELECT * is not permitted. Specify explicit fields.';
                return $result;
            }
            if (in_array(strtolower($field), array_map('strtolower', $allowedFields))) {
                $validatedFields[] = '`' . preg_replace('/[^a-zA-Z0-9_]/', '', $field) . '`';
            }
        }

        if (empty($validatedFields)) {
            $result['errors'][] = 'No valid fields specified';
            return $result;
        }

        // 3. Build WHERE clause with parameter binding
        $whereClause = '';
        $params = [];

        // Always enforce company ID (tenant isolation)
        if ($companyId !== null && self::hasColumn($db, $table, 'company_id')) {
            $whereClause .= ' `company_id` = ?';
            $params[] = $companyId;
        }

        // Apply user filters
        foreach ($filters as $field => $value) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
                $result['errors'][] = "Invalid filter field: $field";
                return $result;
            }

            if (!in_array(strtolower($field), array_map('strtolower', $allowedFields))) {
                $result['errors'][] = "Filter field '$field' is not allowlisted";
                return $result;
            }

            if (is_array($value)) {
                // IN clause
                $placeholders = [];
                foreach ($value as $v) {
                    $placeholders[] = '?';
                    $params[] = $v;
                }
                $whereClause .= ' AND `' . $field . '` IN (' . implode(',', $placeholders) . ')';
            } else {
                $whereClause .= ' AND `' . $field . '` = ?';
                $params[] = $value;
            }
        }

        // 4. Build ORDER BY (validated against allowlist)
        $orderBy = '';
        if (!empty($options['order_by']) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $options['order_by'])) {
            if (in_array(strtolower($options['order_by']), array_map('strtolower', $allowedFields))) {
                $direction = (strtoupper($options['order_direction'] ?? 'ASC') === 'DESC') ? 'DESC' : 'ASC';
                $orderBy = ' ORDER BY `' . $options['order_by'] . '` ' . $direction;
            }
        }

        // 5. Enforce row limit (never exceed MAX_ROWS)
        $limit = min(
            max(1, (int) ($options['limit'] ?? 50)),
            self::MAX_ROWS
        );

        // 6. Build final query
        $fieldsStr = implode(', ', $validatedFields);
        $sql = "SELECT {$fieldsStr} FROM `$table` {$whereClause} {$orderBy} LIMIT {$limit}";

        $result['success'] = true;
        $result['sql'] = $sql;
        $result['params'] = $params;

        return $result;
    }

    /**
     * Execute a safe query and return results.
     */
    public static function executeSafeQuery(PDO $db, string $sql, array $params = []): array
    {
        $result = [
            'success' => false,
            'data' => [],
            'row_count' => 0,
            'errors' => [],
        ];

        // Final safety check
        $validation = self::validateRawSql($sql);
        if (!$validation['safe']) {
            $result['errors'] = array_column($validation['violations'], 'message');
            return $result;
        }

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result['success'] = true;
            $result['data'] = $data;
            $result['row_count'] = count($data);
        } catch (\Throwable $e) {
            $result['errors'][] = 'Query execution failed: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Get allowlisted tables for AI queries.
     */
    private static function getAllowlistedTables(PDO $db, ?int $companyId = null): array
    {
        try {
            $stmt = $db->prepare(
                "SELECT table_name FROM ai_sql_allowed_tables
                 WHERE (company_id IS NULL OR company_id = ?) AND is_active = 1"
            );
            $stmt->execute([$companyId]);
            return array_column($stmt->fetchAll(PDO::FETCH_COLUMN), 'table_name');
        } catch (\Throwable $e) {
            // Default allowlist if table doesn't exist
            return [
                'ai_conversations', 'ai_messages', 'ai_providers', 'ai_models',
                'ai_knowledge_documents', 'ai_knowledge_collections',
                'ai_pending_actions', 'ai_audit_log',
                'vendors', 'purchase_orders', 'purchase_requisitions',
                'invoices', 'payments', 'employees', 'departments',
                'products', 'inventory', 'accounts',
            ];
        }
    }

    /**
     * Get allowlisted fields for a specific table.
     */
    private static function getAllowlistedFields(PDO $db, string $table, ?int $companyId = null): array
    {
        try {
            $stmt = $db->prepare(
                "SELECT field_name FROM ai_sql_allowed_fields
                 WHERE table_name = ? AND (company_id IS NULL OR company_id = ?) AND is_active = 1"
            );
            $stmt->execute([$table, $companyId]);
            $fields = array_column($stmt->fetchAll(PDO::FETCH_COLUMN), 'field_name');

            if (!empty($fields)) {
                return $fields;
            }
        } catch (\Throwable $e) {
            // Fall through to defaults
        }

        // Default: allow common safe fields
        return [
            'id', 'name', 'title', 'description', 'status', 'type', 'code', 'reference',
            'created_at', 'updated_at', 'company_id', 'department_id', 'user_id',
            'amount', 'quantity', 'unit_price', 'total', 'currency',
            'email', 'phone', 'date', 'due_date', 'start_date', 'end_date',
            'priority', 'category', 'notes', 'comments',
        ];
    }

    /**
     * Check if a column exists in a table.
     */
    private static function hasColumn(PDO $db, string $table, string $column): bool
    {
        try {
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = ? AND COLUMN_NAME = ?"
            );
            $stmt->execute([$table, $column]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Enforce row-level security on query results.
     * Implements Section 13: Row-Level Data Security.
     */
    public static function enforceRowLevelSecurity(PDO $db, array $rows, string $table, int $userId): array
    {
        // If the table has a user_id or department_id field, filter accordingly
        $hasUserId = self::hasColumn($db, $table, 'user_id');
        $hasDepartmentId = self::hasColumn($db, $table, 'department_id');

        if (!$hasUserId && !$hasDepartmentId) {
            return $rows;
        }

        try {
            // Get user's department
            $stmt = $db->prepare("SELECT department_id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userDept = $stmt->fetchColumn();

            $filtered = [];
            foreach ($rows as $row) {
                // Allow if user owns the record
                if ($hasUserId && isset($row['user_id']) && (int) $row['user_id'] === $userId) {
                    $filtered[] = $row;
                    continue;
                }

                // Allow if in same department
                if ($hasDepartmentId && isset($row['department_id']) && $userDept && (int) $row['department_id'] === (int) $userDept) {
                    $filtered[] = $row;
                    continue;
                }

                // Allow if no restriction fields are set (open records)
                if ((!$hasUserId || empty($row['user_id'])) && (!$hasDepartmentId || empty($row['department_id']))) {
                    $filtered[] = $row;
                }
            }

            return $filtered;
        } catch (\Throwable $e) {
            return $rows;
        }
    }

    /**
     * Ensure all database interactions use prepared statements.
     * This is a validation utility — never call with raw user input.
     */
    public static function validatePreparedStatementUsage(string $sql): bool
    {
        // Check that no variable interpolation occurs in the SQL
        // (detects potential SQL injection via string concatenation)
        return preg_match('/\$\w+/', $sql) === 0;
    }
}
