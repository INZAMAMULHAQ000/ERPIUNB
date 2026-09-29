<?php
/**
 * AI Document Template Service
 * 
 * F20: Extraction Templates — CRUD, validation rules, versions.
 * All methods are static and take $db as first parameter.
 */

class AIDocumentTemplateService {

    /**
     * Create an extraction template.
     */
    public static function create($db, $data) {
        $companyId = (int)($data['company_id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $docType = $data['document_type'] ?? 'unknown';
        $schema = $data['schema_json'] ?? [];

        if ($companyId <= 0 || $name === '' || empty($schema)) {
            return ['success' => false, 'error' => 'Missing required fields'];
        }

        $stmt = $db->prepare("INSERT INTO ai_extraction_templates 
            (company_id, name, document_type, schema_json, required_fields, optional_fields, validation_rules, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $companyId, $name, $docType,
            json_encode($schema),
            json_encode($data['required_fields'] ?? []),
            json_encode($data['optional_fields'] ?? []),
            json_encode($data['validation_rules'] ?? []),
            (int)($data['created_by'] ?? 0),
        ]);

        return ['success' => true, 'template_id' => (int)$db->lastInsertId()];
    }

    /**
     * Get a template by ID.
     */
    public static function getById($db, $templateId, $companyId = null) {
        $sql = "SELECT * FROM ai_extraction_templates WHERE template_id = ?";
        $params = [(int)$templateId];
        if ($companyId !== null) { $sql .= " AND company_id = ?"; $params[] = (int)$companyId; }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['schema_json'] = json_decode($row['schema_json'], true);
            $row['required_fields'] = json_decode($row['required_fields'], true);
            $row['optional_fields'] = json_decode($row['optional_fields'], true);
            $row['validation_rules'] = json_decode($row['validation_rules'], true);
        }
        return $row ?: null;
    }

    /**
     * List templates for a company.
     */
    public static function listByCompany($db, $companyId, $documentType = null, $status = null) {
        $sql = "SELECT * FROM ai_extraction_templates WHERE company_id = ?";
        $params = [(int)$companyId];
        if ($documentType) { $sql .= " AND document_type = ?"; $params[] = $documentType; }
        if ($status) { $sql .= " AND status = ?"; $params[] = $status; }
        $sql .= " ORDER BY version DESC, name ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['schema_json'] = json_decode($r['schema_json'], true);
        }
        return $rows;
    }

    /**
     * Publish a template.
     */
    public static function publish($db, $templateId) {
        $db->prepare("UPDATE ai_extraction_templates SET status = 'published' WHERE template_id = ? AND status IN ('draft','approved','testing')")->execute([(int)$templateId]);
        return ['success' => true];
    }

    /**
     * Archive a template.
     */
    public static function archive($db, $templateId) {
        $db->prepare("UPDATE ai_extraction_templates SET status = 'archived' WHERE template_id = ?")->execute([(int)$templateId]);
        return ['success' => true];
    }

    /**
     * Create a new version of a template.
     */
    public static function newVersion($db, $templateId, $data) {
        $existing = self::getById($db, $templateId);
        if (!$existing) return ['success' => false, 'error' => 'Template not found'];

        $newData = array_merge($existing, $data);
        $newData['name'] = $existing['name'];
        $newData['document_type'] = $existing['document_type'];
        $newData['version'] = $existing['version'] + 1;
        $newData['status'] = 'draft';
        $newData['created_by'] = $data['created_by'] ?? $existing['created_by'];

        return self::create($db, $newData);
    }
}
