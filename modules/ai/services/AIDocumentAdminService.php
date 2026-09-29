<?php
/**
 * AI F27 — Document Administration Service.
 *
 * Manages document type configurations, admin settings, and
 * configuration changes with audit logging.
 *
 * Covers Section 27: Administration.
 */
class AIDocumentAdminService
{
    /**
     * Get all admin configuration for a company.
     */
    public static function getConfig(PDO $db, int $companyId): array
    {
        $stmt = $db->prepare("
            SELECT config_key, config_value, config_type, description
            FROM ai_document_admin_config WHERE company_id = ?
        ");
        $stmt->execute([$companyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $config = [];
        foreach ($rows as $row) {
            $value = match ($row['config_type']) {
                'boolean' => (bool) $row['config_value'],
                'integer' => (int) $row['config_value'],
                'json' => json_decode($row['config_value'], true),
                default => $row['config_value'],
            };
            $config[$row['config_key']] = [
                'value' => $value,
                'type' => $row['config_type'],
                'description' => $row['description'],
            ];
        }

        return $config;
    }

    /**
     * Update an admin configuration value.
     */
    public static function setConfig(PDO $db, int $companyId, string $key, $value, int $userId): bool
    {
        $existing = $db->prepare("
            SELECT config_type FROM ai_document_admin_config
            WHERE company_id = ? AND config_key = ?
        ");
        $existing->execute([$companyId, $key]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);

        if (!$row) return false;

        // Type coercion
        $typedValue = match ($row['config_type']) {
            'boolean' => ($value || $value === '1' || $value === 'true') ? '1' : '0',
            'integer' => (string) (int) $value,
            'json' => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };

        $db->prepare("
            UPDATE ai_document_admin_config
            SET config_value = ?, updated_by = ?, updated_at = NOW()
            WHERE company_id = ? AND config_key = ?
        ")->execute([$typedValue, $userId, $companyId, $key]);

        return true;
    }

    /**
     * Get document type configurations.
     */
    public static function getDocumentTypes(PDO $db, int $companyId): array
    {
        $stmt = $db->prepare("
            SELECT * FROM ai_document_type_configs WHERE company_id = ? ORDER BY type_name
        ");
        $stmt->execute([$companyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['extraction_schema_json'] = json_decode($row['extraction_schema_json'] ?? '{}', true);
        }

        return $rows;
    }

    /**
     * Create or update a document type configuration.
     */
    public static function setDocumentType(PDO $db, int $companyId, string $typeKey, array $data, int $userId): bool
    {
        $db->prepare("
            INSERT INTO ai_document_type_configs
                (company_id, type_key, type_name, description, extraction_schema_json,
                 auto_extract, requires_review, enabled)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                type_name = VALUES(type_name),
                description = VALUES(description),
                extraction_schema_json = VALUES(extraction_schema_json),
                auto_extract = VALUES(auto_extract),
                requires_review = VALUES(requires_review),
                enabled = VALUES(enabled)
        ")->execute([
            $companyId,
            $typeKey,
            $data['type_name'] ?? ucfirst(str_replace('_', ' ', $typeKey)),
            $data['description'] ?? null,
            json_encode($data['extraction_schema'] ?? []),
            $data['auto_extract'] ?? 1,
            $data['requires_review'] ?? 0,
            $data['enabled'] ?? 1,
        ]);

        return true;
    }

    /**
     * Get extraction templates with their configuration.
     */
    public static function getTemplates(PDO $db, int $companyId): array
    {
        return AIDocumentTemplateService::listByCompany($db, $companyId);
    }
}
