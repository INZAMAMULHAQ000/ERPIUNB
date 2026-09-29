<?php
/**
 * AI F24 — Parameter Validation Service.
 *
 * Validates and sanitizes parameters passed to AI tools and actions.
 * Ensures type safety, range checks, and pattern validation.
 */
class AIParameterValidator
{
    // Validation result codes
    const VALID = 'valid';
    const INVALID = 'invalid';
    const WARNING = 'warning';
    const BLOCKED = 'blocked';

    // Risk levels for invalid parameters
    const RISK_NONE = 'none';
    const RISK_LOW = 'low';
    const RISK_MEDIUM = 'medium';
    const RISK_HIGH = 'high';
    const RISK_CRITICAL = 'critical';

    /**
     * Validate parameters against a schema.
     *
     * @param array $params The parameters to validate
     * @param array $schema The validation schema
     * @return array Validation result
     */
    public static function validate(array $params, array $schema): array
    {
        $result = [
            'valid' => true,
            'status' => self::VALID,
            'risk_level' => self::RISK_NONE,
            'errors' => [],
            'warnings' => [],
            'sanitized' => [],
            'blocked_fields' => [],
        ];

        foreach ($schema as $field => $rules) {
            $value = $params[$field] ?? null;
            $hasValue = array_key_exists($field, $params);

            // Check required
            if (!empty($rules['required']) && !$hasValue) {
                $result['valid'] = false;
                $result['status'] = self::INVALID;
                $result['errors'][] = "Missing required field: {$field}";
                continue;
            }

            // Skip if not provided and not required
            if (!$hasValue) {
                continue;
            }

            // Type validation
            $typeResult = self::validateType($field, $value, $rules);
            if ($typeResult !== null) {
                if ($typeResult['blocked']) {
                    $result['valid'] = false;
                    $result['status'] = self::BLOCKED;
                    $result['risk_level'] = max($result['risk_level'], $typeResult['risk']);
                    $result['blocked_fields'][] = $field;
                    $result['errors'][] = $typeResult['message'];
                } else {
                    $result['warnings'][] = $typeResult['message'];
                    $value = $typeResult['sanitized'] ?? $value;
                }
            }

            // Range validation
            $rangeResult = self::validateRange($field, $value, $rules);
            if ($rangeResult !== null) {
                $result['warnings'][] = $rangeResult['message'];
                $value = $rangeResult['sanitized'] ?? $value;
            }

            // Pattern validation
            $patternResult = self::validatePattern($field, $value, $rules);
            if ($patternResult !== null) {
                if ($patternResult['blocked']) {
                    $result['valid'] = false;
                    $result['status'] = self::BLOCKED;
                    $result['risk_level'] = max($result['risk_level'], $patternResult['risk']);
                    $result['blocked_fields'][] = $field;
                    $result['errors'][] = $patternResult['message'];
                } else {
                    $result['warnings'][] = $patternResult['message'];
                    $value = $patternResult['sanitized'] ?? $value;
                }
            }

            // Custom validation
            if (isset($rules['validator']) && is_callable($rules['validator'])) {
                $customResult = $rules['validator']($value);
                if ($customResult !== true) {
                    $result['warnings'][] = is_string($customResult) ? $customResult : "Validation failed for {$field}";
                    if (is_array($customResult) && isset($customResult['blocked']) && $customResult['blocked']) {
                        $result['valid'] = false;
                        $result['status'] = self::BLOCKED;
                        $result['blocked_fields'][] = $field;
                    }
                }
            }

            // Sanitize the value
            $result['sanitized'][$field] = self::sanitizeValue($value, $rules);
        }

        // Determine final status
        if (!$result['valid'] && empty($result['sanitized'])) {
            $result['status'] = self::BLOCKED;
        } elseif ($result['status'] === self::VALID && !empty($result['warnings'])) {
            $result['status'] = self::WARNING;
        }

        return $result;
    }

    /**
     * Validate parameter type.
     */
    private static function validateType(string $field, mixed $value, array $rules): ?array
    {
        if (!isset($rules['type'])) return null;

        $type = $rules['type'];
        $blocked = $rules['block_invalid_type'] ?? true;

        switch ($type) {
            case 'string':
                if (!is_string($value)) {
                    return [
                        'blocked' => $blocked,
                        'message' => "Field '{$field}' must be a string",
                        'sanitized' => (string) $value,
                        'risk' => self::RISK_LOW,
                    ];
                }
                // Check max length
                if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
                    return [
                        'blocked' => true,
                        'message' => "Field '{$field}' exceeds max length of {$rules['max_length']}",
                        'sanitized' => substr($value, 0, $rules['max_length']),
                        'risk' => self::RISK_MEDIUM,
                    ];
                }
                // Check pattern
                if (!empty($rules['pattern'])) {
                    if (!preg_match('/' . $rules['pattern'] . '/', $value)) {
                        return [
                            'blocked' => true,
                            'message' => "Field '{$field}' contains invalid characters",
                            'sanitized' => $value,
                            'risk' => self::RISK_HIGH,
                        ];
                    }
                }
                break;

            case 'int':
            case 'integer':
                if (!is_numeric($value)) {
                    return [
                        'blocked' => $blocked,
                        'message' => "Field '{$field}' must be an integer",
                        'sanitized' => (int) $value,
                        'risk' => self::RISK_LOW,
                    ];
                }
                break;

            case 'float':
            case 'number':
                if (!is_numeric($value)) {
                    return [
                        'blocked' => $blocked,
                        'message' => "Field '{$field}' must be a number",
                        'sanitized' => (float) $value,
                        'risk' => self::RISK_LOW,
                    ];
                }
                break;

            case 'bool':
            case 'boolean':
                if (!is_bool($value) && !is_numeric($value) && !in_array(strtolower((string)$value), ['true', 'false', '1', '0'])) {
                    return [
                        'blocked' => $blocked,
                        'message' => "Field '{$field}' must be a boolean",
                        'sanitized' => (bool) $value,
                        'risk' => self::RISK_LOW,
                    ];
                }
                break;

            case 'array':
                if (!is_array($value)) {
                    return [
                        'blocked' => $blocked,
                        'message' => "Field '{$field}' must be an array",
                        'sanitized' => (array) $value,
                        'risk' => self::RISK_LOW,
                    ];
                }
                break;

            case 'json':
                if (is_string($value) && !self::isValidJson($value)) {
                    return [
                        'blocked' => true,
                        'message' => "Field '{$field}' contains invalid JSON",
                        'risk' => self::RISK_HIGH,
                    ];
                }
                break;
        }

        return null;
    }

    /**
     * Validate parameter range.
     */
    private static function validateRange(string $field, mixed $value, array $rules): ?array
    {
        if (!is_numeric($value)) return null;

        $numVal = (float) $value;
        $messages = [];
        $sanitized = $numVal;
        $risk = self::RISK_NONE;

        if (isset($rules['min']) && $numVal < $rules['min']) {
            $risk = max($risk, self::RISK_MEDIUM);
            $messages[] = "Field '{$field}' is below minimum ({$rules['min']})";
            $sanitized = $rules['min'];
        }

        if (isset($rules['max']) && $numVal > $rules['max']) {
            $risk = max($risk, self::RISK_HIGH);
            $messages[] = "Field '{$field}' exceeds maximum ({$rules['max']})";
            $sanitized = $rules['max'];
        }

        // Amount-based risk escalation
        if ($field === 'amount' || $field === 'quantity') {
            if ($numVal > 10000) {
                $risk = max($risk, self::RISK_CRITICAL);
                $messages[] = "Amount exceeds critical threshold";
            } elseif ($numVal > 1000) {
                $risk = max($risk, self::RISK_HIGH);
                $messages[] = "Amount exceeds high threshold";
            } elseif ($numVal > 100) {
                $risk = max($risk, self::RISK_MEDIUM);
                $messages[] = "Amount exceeds medium threshold";
            }
        }

        if (empty($messages)) return null;

        return [
            'blocked' => $risk === self::RISK_CRITICAL,
            'message' => implode('; ', $messages),
            'sanitized' => $sanitized,
            'risk' => $risk,
        ];
    }

    /**
     * Validate parameter against a pattern.
     */
    private static function validatePattern(string $field, mixed $value, array $rules): ?array
    {
        if (!is_string($value) || empty($rules['pattern'])) return null;

        // Security patterns - block dangerous content
        $securityPatterns = [
            'sql_injection' => '/(DROP\s+TABLE|DELETE\s+FROM|INSERT\s+INTO|UPDATE.*SET|UNION\s+SELECT|ALTER\s+TABLE|TRUNCATE|EXEC\s*\(|EXECUTE\s*\()/',
            'xss' => '/<script[^>]*>.*<\/script>|javascript:|on\w+\s*=/i',
            'path_traversal' => '/\.\.\/|\.\.\\|%2e%2e/i',
            'command_injection' => '/;\s*(rm|cat|wget|curl|chmod|chown|sudo|bash|sh|nc|python|perl|php)/i',
        ];

        if (isset($securityPatterns[$rules['pattern']])) {
            if (preg_match($securityPatterns[$rules['pattern']], $value)) {
                return [
                    'blocked' => true,
                    'message' => "Field '{$field}' contains blocked content",
                    'risk' => self::RISK_CRITICAL,
                ];
            }
        }

        // Custom regex pattern
        if (isset($rules['regex'])) {
            if (!preg_match('/' . $rules['regex'] . '/', $value)) {
                return [
                    'blocked' => false,
                    'message' => "Field '{$field}' does not match expected format",
                    'sanitized' => preg_replace('/[^a-zA-Z0-9\s\-_@.]/', '', $value),
                    'risk' => self::RISK_LOW,
                ];
            }
        }

        return null;
    }

    /**
     * Sanitize a value.
     */
    private static function sanitizeValue(mixed $value, array $rules): mixed
    {
        if (is_string($value)) {
            // Trim whitespace
            $value = trim($value);

            // Strip HTML tags if not allowed
            if (empty($rules['allow_html']) && !isset($rules['type'])) {
                $value = strip_tags($value);
            }

            // Escape HTML entities for output safety
            if (isset($rules['escape_output']) && $rules['escape_output']) {
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }

            // Limit length
            if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
                $value = substr($value, 0, $rules['max_length']);
            }
        }

        if (is_array($value)) {
            foreach ($value as $k => $v) {
                if (isset($rules['items'])) {
                    $value[$k] = self::sanitizeValue($v, $rules['items']);
                } else {
                    $value[$k] = self::sanitizeValue($v, []);
                }
            }
        }

        return $value;
    }

    /**
     * Check if JSON string is valid.
     */
    private static function isValidJson(string $value): bool
    {
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Validate a financial action specifically.
     */
    public static function validateFinancial(array $params, array $config = []): array
    {
        $result = self::validate($params, [
            'amount' => [
                'type' => 'number',
                'required' => true,
                'min' => 0,
                'max' => $config['max_amount'] ?? 1000000,
                'block_invalid_type' => true,
            ],
            'currency' => [
                'type' => 'string',
                'required' => false,
                'pattern' => '/^[A-Z]{3}$/',
                'max_length' => 3,
            ],
            'description' => [
                'type' => 'string',
                'required' => true,
                'max_length' => 500,
                'escape_output' => true,
            ],
            'account_id' => [
                'type' => 'int',
                'required' => true,
                'min' => 1,
            ],
        ]);

        // Additional financial security checks
        if ($result['valid']) {
            $amount = (float) ($params['amount'] ?? 0);

            // Check financial thresholds from config
            if ($amount > ($config['approval_threshold'] ?? 10000)) {
                $result['warnings'][] = "Amount exceeds approval threshold";
                $result['risk_level'] = self::RISK_HIGH;
            }

            // Check for suspicious patterns in description
            if (!empty($params['description'])) {
                $suspiciousPatterns = ['cash', 'off the books', 'unrecorded', 'personal'];
                $descLower = strtolower($params['description']);
                foreach ($suspiciousPatterns as $pattern) {
                    if (strpos($descLower, $pattern) !== false) {
                        $result['warnings'][] = "Description contains potentially suspicious term";
                        $result['risk_level'] = max($result['risk_level'], self::RISK_MEDIUM);
                        break;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Validate an action parameter set against an action definition schema.
     */
    public static function validateActionParams(array $params, array $actionDef): array
    {
        $schema = [];
        $inputSchema = $actionDef['input_schema'] ?? [];

        foreach ($inputSchema as $field => $fieldDef) {
            $schema[$field] = [
                'type' => $fieldDef['type'] ?? 'string',
                'required' => !empty($fieldDef['required']),
                'max_length' => $fieldDef['max_length'] ?? 200,
                'pattern' => $fieldDef['pattern'] ?? null,
            ];
        }

        return self::validate($params, $schema);
    }
}
