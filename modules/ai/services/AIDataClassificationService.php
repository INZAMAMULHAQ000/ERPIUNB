<?php
/**
 * AI F24 — Data Classification Service.
 *
 * Centralized data classification for AI operations.
 * Enforces data handling policies based on classification levels.
 */
class AIDataClassificationService
{
    const CLASSIFICATION_PUBLIC = 'public';
    const CLASSIFICATION_INTERNAL = 'internal';
    const CLASSIFICATION_CONFIDENTIAL = 'confidential';
    const CLASSIFICATION_SENSITIVE = 'sensitive';
    const CLASSIFICATION_RESTRICTED = 'restricted';

    private static array $cache = [];
    private static array $classificationMap = [];

    /**
     * Initialize data classifications from database.
     */
    public static function initialize(PDO $db): void
    {
        self::loadClassifications($db);
    }

    /**
     * Load classifications from database.
     */
    private static function loadClassifications(PDO $db): void
    {
        try {
            $stmt = $db->query("SELECT * FROM ai_data_classifications ORDER BY classification ASC");
            self::$classificationMap = $stmt->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC, 'classification');
        } catch (\Throwable $e) {
            self::$classificationMap = self::getDefaultClassifications();
        }
    }

    /**
     * Get default data classifications.
     */
    private static function getDefaultClassifications(): array
    {
        return [
            self::CLASSIFICATION_PUBLIC => [
                'name' => 'Public',
                'masking_required' => false,
                'allowed_providers' => ['all'],
            ],
            self::CLASSIFICATION_INTERNAL => [
                'name' => 'Internal',
                'masking_required' => false,
                'allowed_providers' => ['all'],
            ],
            self::CLASSIFICATION_CONFIDENTIAL => [
                'name' => 'Confidential',
                'masking_required' => true,
                'allowed_providers' => ['approved_internal'],
            ],
            self::CLASSIFICATION_SENSITIVE => [
                'name' => 'Sensitive',
                'masking_required' => true,
                'allowed_providers' => ['approved_internal'],
            ],
            self::CLASSIFICATION_RESTRICTED => [
                'name' => 'Restricted',
                'masking_required' => true,
                'allowed_providers' => ['approved_internal_only'],
            ],
        ];
    }

    /**
     * Get the classification level for a specific field.
     */
    public static function getFieldClassification(PDO $db, string $module, string $table, string $field): string
    {
        $cacheKey = "$module:$table:$field";

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        try {
            $stmt = $db->prepare("
                SELECT classification FROM ai_data_classification_fields
                WHERE module_name = ? AND table_name = ? AND field_name = ?
            ");
            $stmt->execute([$module, $table, $field]);
            $result = $stmt->fetch();

            $classification = $result ? $result['classification'] : self::CLASSIFICATION_INTERNAL;
        } catch (\Throwable $e) {
            $classification = self::CLASSIFICATION_INTERNAL;
        }

        self::$cache[$cacheKey] = $classification;
        return $classification;
    }

    /**
     * Classify data based on content analysis.
     */
    public static function classifyContent(string $content, array $context = []): array
    {
        $classification = self::CLASSIFICATION_INTERNAL;
        $confidence = 0.5;
        $reasons = [];

        // Check for personally identifiable information
        if (self::containsPII($content)) {
            $classification = self::CLASSIFICATION_SENSITIVE;
            $confidence = 0.9;
            $reasons[] = 'contains_personal_information';
        }

        // Check for financial data
        if (self::containsFinancialData($content)) {
            if ($classification !== self::CLASSIFICATION_RESTRICTED) {
                $classification = self::CLASSIFICATION_CONFIDENTIAL;
            }
            $confidence = 0.95;
            $reasons[] = 'contains_financial_data';
        }

        // Check for credentials or secrets
        if (self::containsCredentials($content)) {
            $classification = self::CLASSIFICATION_RESTRICTED;
            $confidence = 0.99;
            $reasons[] = 'contains_credentials';
        }

        // Check for restricted keywords
        if (self::containsRestrictedKeywords($content)) {
            $classification = self::CLASSIFICATION_RESTRICTED;
            $confidence = 0.9;
            $reasons[] = 'contains_restricted_keywords';
        }

        return [
            'classification' => $classification,
            'confidence' => $confidence,
            'reasons' => $reasons,
            'masking_required' => self::isMaskingRequired($classification),
            'allowed_providers' => self::getAllowedProviders($classification),
        ];
    }

    /**
     * Check if PII is present in content.
     */
    private static function containsPII(string $content): bool
    {
        $patterns = [
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', // Email
            '/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/', // Phone
            '/\b\d{3}-\d{2}-\d{4}\b/', // SSN
            '/\b\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\b/', // Credit card
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if financial data is present.
     */
    private static function containsFinancialData(string $content): bool
    {
        $patterns = [
            '/\$\d[\d,.]*/', // Dollar amounts
            '/\b(?:invoice|payment|transaction|bank|account)\b/i', // Financial keywords
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if credentials are present.
     */
    private static function containsCredentials(string $content): bool
    {
        $patterns = [
            '/\b(?:api[_-]?key|apikey)\s*[=:]\s*\S+/i',
            '/\b(?:password|passwd|pwd)\s*[=:]\s*\S+/i',
            '/\b(?:secret|token)\s*[=:]\s*\S+/i',
            '/\b(?:bearer|oauth)\s+\S+/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for restricted keywords.
     */
    private static function containsRestrictedKeywords(string $content): bool
    {
        $keywords = ['classified', 'restricted', 'top secret', 'confidential'];
        $contentLower = strtolower($content);

        foreach ($keywords as $keyword) {
            if (strpos($contentLower, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if masking is required for a classification.
     */
    public static function isMaskingRequired(string $classification): bool
    {
        $config = self::$classificationMap[$classification] ?? null;

        if ($config) {
            return (bool) $config['masking_required'];
        }

        return in_array($classification, [self::CLASSIFICATION_CONFIDENTIAL, self::CLASSIFICATION_SENSITIVE, self::CLASSIFICATION_RESTRICTED]);
    }

    /**
     * Get allowed providers for a classification.
     */
    public static function getAllowedProviders(string $classification): array
    {
        $config = self::$classificationMap[$classification] ?? null;

        if ($config && !empty($config['allowed_providers'])) {
            return $config['allowed_providers'];
        }

        return ['all'];
    }

    /**
     * Mask data based on its classification.
     */
    public static function maskData(string $content, string $classification, array $options = []): string
    {
        if (!self::isMaskingRequired($classification)) {
            return $content;
        }

        // Apply field-level masking
        $maskingRules = self::getMaskingRules();

        foreach ($maskingRules as $rule) {
            $content = self::applyMaskingRule($content, $rule);
        }

        return $content;
    }

    /**
     * Get masking rules from database.
     */
    private static function getMaskingRules(): array
    {
        static $rules = null;

        if ($rules !== null) {
            return $rules;
        }

        try {
            global $db;
            $stmt = $db->query("SELECT * FROM ai_masking_rules WHERE is_active = 1 ORDER BY field_type ASC");
            $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $rules = self::getDefaultMaskingRules();
        }

        return $rules;
    }

    /**
     * Get default masking rules.
     */
    private static function getDefaultMaskingRules(): array
    {
        return [
            ['field_type' => 'email', 'masking_type' => 'partial', 'visible_prefix' => 2, 'visible_suffix' => 4, 'mask_character' => '*'],
            ['field_type' => 'phone', 'masking_type' => 'partial', 'visible_prefix' => 3, 'visible_suffix' => 2, 'mask_character' => '*'],
            ['field_type' => 'bank', 'masking_type' => 'partial', 'visible_prefix' => 4, 'visible_suffix' => 2, 'mask_character' => '*'],
            ['field_type' => 'ssn', 'masking_type' => 'partial', 'visible_prefix' => 3, 'visible_suffix' => 4, 'mask_character' => '*'],
            ['field_type' => 'api_key', 'masking_type' => 'redact', 'replacement_text' => '[API_KEY]'],
            ['field_type' => 'token', 'masking_type' => 'redact', 'replacement_text' => '[TOKEN]'],
            ['field_type' => 'password', 'masking_type' => 'redact', 'replacement_text' => '[PASSWORD]'],
            ['field_type' => 'ip_address', 'masking_type' => 'partial', 'visible_prefix' => 2, 'visible_suffix' => 0, 'mask_character' => '*'],
        ];
    }

    /**
     * Apply a single masking rule to content.
     */
    private static function applyMaskingRule(string $content, array $rule): string
    {
        $fieldType = $rule['field_type'];
        $maskingType = $rule['masking_type'];
        $maskChar = $rule['mask_character'] ?? '*';
        $visiblePrefix = (int) ($rule['visible_prefix'] ?? 0);
        $visibleSuffix = (int) ($rule['visible_suffix'] ?? 0);
        $replacement = $rule['replacement_text'] ?? str_repeat($maskChar, 8);

        switch ($fieldType) {
            case 'email':
                return preg_replace_callback('/([a-zA-Z0-9._%+-]{' . $visiblePrefix . '})[a-zA-Z0-9._%+-]*@([a-zA-Z0-9.-]+\.[a-zA-Z]{' . $visibleSuffix . ',})/',
                    function ($m) use ($maskChar) {
                        $masked = str_repeat($maskChar, max(0, strlen($m[1]) - $visiblePrefix));
                        return $m[1][0] . $masked . '@' . $m[2];
                    }, $content);

            case 'phone':
                return preg_replace_callback('/(\+?\d{1,3}[-.\s]?)?(\d{' . $visiblePrefix . '})\d+(\d{' . $visibleSuffix . '})/',
                    function ($m) use ($maskChar) {
                        return ($m[1] ?? '') . $m[2] . str_repeat($maskChar, 4) . $m[3];
                    }, $content);

            case 'bank':
                return preg_replace_callback('/(\d{4})\s*\d+\s*\d+\s*(\d{4})/',
                    function ($m) use ($maskChar) {
                        return $m[1] . ' ' . str_repeat($maskChar, 8) . ' ' . $m[2];
                    }, $content);

            case 'ssn':
                return preg_replace_callback('/\d{3}-\d{2}-(\d{4})/',
                    function ($m) use ($maskChar) {
                        return '***-**-' . $maskChar . $maskChar . $maskChar . $maskChar;
                    }, $content);

            case 'api_key':
            case 'token':
                return preg_replace('/(' . $fieldType . '[:\s]*)([^\s,;]+)/i', '$1' . $replacement, $content);

            default:
                return $content;
        }
    }

    /**
     * Mask specific fields in an array of data.
     */
    public static function maskDataArray(array $data, string $module, string $table): array
    {
        $masked = [];

        foreach ($data as $row) {
            $maskedRow = [];
            foreach ($row as $field => $value) {
                $classification = self::getFieldClassification($db ?? null, $module, $table, $field);
                if (self::isMaskingRequired($classification)) {
                    $maskedRow[$field] = self::maskData((string) $value, $classification);
                } else {
                    $maskedRow[$field] = $value;
                }
            }
            $masked[] = $maskedRow;
        }

        return $masked;
    }

    /**
     * Check if provider is allowed for classification.
     */
    public static function isProviderAllowed(string $classification, string $providerId): bool
    {
        $allowedProviders = self::getAllowedProviders($classification);

        if (in_array('all', $allowedProviders)) {
            return true;
        }

        return in_array($providerId, $allowedProviders);
    }

    /**
     * Get all classification levels.
     */
    public static function getAllClassifications(): array
    {
        return array_keys(self::$classificationMap);
    }

    /**
     * Get classification display name.
     */
    public static function getDisplayName(string $classification): string
    {
        $config = self::$classificationMap[$classification] ?? null;
        return $config['name'] ?? ucfirst($classification);
    }

    /**
     * Update classification in database.
     */
    public static function updateClassification(PDO $db, string $classification, array $data): bool
    {
        try {
            $stmt = $db->prepare("
                UPDATE ai_data_classifications
                SET name = ?, description = ?, masking_required = ?, allowed_providers_json = ?
                WHERE classification = ?
            ");
            $stmt->execute([
                $data['name'] ?? $classification,
                $data['description'] ?? '',
                $data['masking_required'] ?? false,
                json_encode($data['allowed_providers'] ?? []),
                $classification,
            ]);

            self::clearCache();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Clear classification cache.
     */
    public static function clearCache(): void
    {
        self::$cache = [];
        self::$classificationMap = [];
    }
}