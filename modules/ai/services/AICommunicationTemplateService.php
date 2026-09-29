<?php
/**
 * AI F32 — Communication Template Service
 * 
 * AI-assisted templates with controlled variables, versioning, and rules.
 * Sections 20-22 of F32 specification.
 */

class AICommunicationTemplateService {

    private $companyId;
    private $userId;

    private $allowedVariables = [
        'customer_name', 'supplier_name', 'employee_name',
        'invoice_number', 'invoice_date', 'amount', 'currency', 'due_date',
        'order_number', 'order_date', 'delivery_date', 'delivery_status',
        'quotation_number', 'quotation_date', 'valid_until',
        'ticket_number', 'issue_summary', 'resolution_text',
        'po_number', 'item_count', 'delay_reason',
        'sender_name', 'sender_title', 'sender_company',
        'original_date', 'new_date', 'subject_summary', 'followup_details',
    ];

    public function __construct($companyId, $userId) {
        $this->companyId = $companyId;
        $this->userId = $userId;
    }

    /**
     * Create a new template (AI-generated or manual).
     */
    public function createTemplate($params) {
        $name = $params['name'] ?? '';
        $body = $params['body'] ?? '';
        $subject = $params['subject'] ?? '';
        $commType = $params['communication_type'] ?? 'email';
        $category = $params['category'] ?? 'custom';
        $tone = $params['tone'] ?? 'professional';
        $language = $params['language'] ?? 'en';
        $aiGenerated = $params['ai_generated'] ?? 0;

        if (empty($name) || empty($body)) {
            return ['error' => 'Name and body are required'];
        }

        // Validate template variables
        $variables = $this->extractVariables($body . ' ' . $subject);
        $invalidVars = array_diff($variables, $this->allowedVariables);
        if (!empty($invalidVars)) {
            return ['error' => 'Invalid template variables: ' . implode(', ', $invalidVars)];
        }

        // Check for executable expressions (F32 Section 21 safety)
        if (preg_match('/\b(eval|exec|system|passthru|shell|popen|proc_open|phpinfo|file_get_contents|file_put_contents)\b/', $body)) {
            return ['error' => 'Template contains executable expressions — blocked'];
        }

        $templateKey = bin2hex(random_bytes(16));

        $db = $this->getDb();
        if (!$db) return ['error' => 'Database not available'];

        $sql = "INSERT INTO ai_communication_templates
                (company_id, template_key, name, description, communication_type, category,
                 subject_template, body_template, variables, tone, language,
                 version, status, created_by, ai_generated)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'draft', ?, ?)";

        $stmt = $db->prepare($sql);
        if (!$stmt) return ['error' => 'Query failed'];

        $desc = $params['description'] ?? '';
        $varsJson = json_encode($variables);

        $stmt->bind_param('isssssssssii i',
            $this->companyId, $templateKey, $name, $desc, $commType, $category,
            $subject, $body, $varsJson, $tone, $language,
            $this->userId, $aiGenerated
        );

        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        // Store initial version
        $this->storeVersion($id, 1, $subject, $body, $variables);

        return [
            'template_id' => $id,
            'template_key' => $templateKey,
            'variables' => $variables,
            'status' => 'draft',
        ];
    }

    /**
     * Generate a template using AI.
     */
    public function generateTemplate($params) {
        $commType = $params['communication_type'] ?? 'email';
        $category = $params['category'] ?? 'custom';
        $tone = $params['tone'] ?? 'professional';
        $description = $params['description'] ?? '';

        $prompt = "Generate a reusable business communication template.\n\n";
        $prompt .= "Type: {$commType}\n";
        $prompt .= "Category: {$category}\n";
        $prompt .= "Tone: {$tone}\n";
        $prompt .= "Description: {$description}\n\n";
        $prompt .= "RULES:\n";
        $prompt .= "- Use template variables in {{variable_name}} format.\n";
        $prompt .= "- Allowed variables: " . implode(', ', $this->allowedVariables) . "\n";
        $prompt .= "- Do NOT use PHP, exec, system, or any executable expressions.\n";
        $prompt .= "- Keep the template professional and reusable.\n\n";
        $prompt .= "Return JSON: {\"name\": \"...\", \"subject\": \"...\", \"body\": \"...\", \"variables\": [...]}";

        $aiResult = $this->callAIProvider($prompt);
        $data = json_decode($aiResult['text'] ?? '{}', true);

        if (!is_array($data) || empty($data['body'])) {
            return ['error' => 'Failed to generate template'];
        }

        return $this->createTemplate([
            'name' => $data['name'] ?? "AI Template - {$category}",
            'subject' => $data['subject'] ?? '',
            'body' => $data['body'],
            'communication_type' => $commType,
            'category' => $category,
            'tone' => $tone,
            'description' => $description,
            'ai_generated' => 1,
        ]);
    }

    /**
     * Resolve template variables from ERP context.
     */
    public function resolveTemplate($templateId, $contextType, $contextId) {
        $template = $this->getTemplate($templateId);
        if (!$template) return ['error' => 'Template not found'];

        $variables = json_decode($template['variables'] ?? '[]', true);
        $resolved = [];

        $db = $this->getDb();
        if (!$db) return ['error' => 'Database not available'];

        foreach ($variables as $var) {
            $resolved[$var] = $this->resolveVariable($var, $contextType, $contextId, $db);
        }

        // Substitute into subject and body
        $subject = $template['subject_template'] ?? '';
        $body = $template['body_template'] ?? '';

        $missing = [];
        foreach ($resolved as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            if ($value === null) {
                $missing[] = $key;
                // Replace with safe placeholder
                $subject = str_replace($placeholder, "[{$key}]", $subject);
                $body = str_replace($placeholder, "[{$key}]", $body);
            } else {
                $subject = str_replace($placeholder, $value, $subject);
                $body = str_replace($placeholder, $value, $body);
            }
        }

        return [
            'template' => $template,
            'resolved' => $resolved,
            'subject' => $subject,
            'body' => $body,
            'missing_variables' => $missing,
        ];
    }

    /**
     * Update a template (creates new version).
     */
    public function updateTemplate($templateId, $updates) {
        $template = $this->getTemplate($templateId);
        if (!$template) return ['error' => 'Template not found'];

        if ($template['status'] === 'active' && !empty($updates['body_template'])) {
            // Creating a new version for active templates
            $newVersion = ($template['version'] ?? 1) + 1;
            $this->storeVersion($templateId, $newVersion, 
                $updates['subject_template'] ?? $template['subject_template'],
                $updates['body_template'] ?? $template['body_template'],
                $this->extractVariables($updates['body_template'] ?? $template['body_template'])
            );

            $updates['version'] = $newVersion;
        }

        $db = $this->getDb();
        if (!$db) return ['error' => 'Database not available'];

        $sql = "UPDATE ai_communication_templates SET updated_at = NOW()";
        $params = [];
        $types = '';

        foreach (['name', 'description', 'subject_template', 'body_template', 'tone', 'language', 'category', 'status', 'version'] as $field) {
            if (array_key_exists($field, $updates)) {
                $sql .= ", {$field} = ?";
                $params[] = $updates[$field];
                $types .= 's';
            }
        }

        if (isset($updates['variables'])) {
            $sql .= ", variables = ?";
            $params[] = json_encode($updates['variables']);
            $types .= 's';
        }

        $sql .= " WHERE id = ? AND company_id = ?";
        $params[] = $templateId;
        $params[] = $this->companyId;
        $types .= 'ii';

        $stmt = $db->prepare($sql);
        if (!$stmt) return ['error' => 'Query failed'];

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();

        return ['success' => true, 'template_id' => $templateId, 'version' => $updates['version'] ?? $template['version']];
    }

    /**
     * Activate a template (requires approval for production use).
     */
    public function activateTemplate($templateId) {
        return $this->updateTemplate($templateId, ['status' => 'active', 'approved_by' => $this->userId, 'approved_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Rollback to a previous template version.
     */
    public function rollbackTemplate($templateId, $targetVersion) {
        $db = $this->getDb();
        if (!$db) return ['error' => 'Database not available'];

        $sql = "SELECT * FROM ai_communication_template_versions WHERE template_id = ? AND version = ? LIMIT 1";
        $stmt = $db->prepare($sql);
        if (!$stmt) return ['error' => 'Query failed'];

        $stmt->bind_param('ii', $templateId, $targetVersion);
        $stmt->execute();
        $result = $stmt->get_result();
        $version = $result->fetch_assoc();
        $stmt->close();

        if (!$version) return ['error' => 'Version not found'];

        return $this->updateTemplate($templateId, [
            'subject_template' => $version['subject_template'],
            'body_template' => $version['body_template'],
            'variables' => json_decode($version['variables'] ?? '[]', true),
        ]);
    }

    /**
     * List templates.
     */
    public function listTemplates($filters = []) {
        $db = $this->getDb();
        if (!$db) return [];

        $where = "company_id = ?";
        $params = [$this->companyId];
        $types = 'i';

        if (!empty($filters['category'])) {
            $where .= " AND category = ?";
            $params[] = $filters['category'];
            $types .= 's';
        }
        if (!empty($filters['status'])) {
            $where .= " AND status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        if (!empty($filters['communication_type'])) {
            $where .= " AND communication_type = ?";
            $params[] = $filters['communication_type'];
            $types .= 's';
        }

        $sql = "SELECT id, template_key, name, description, communication_type, category,
                       tone, language, version, status, ai_generated, usage_count, created_at
                FROM ai_communication_templates
                WHERE {$where}
                ORDER BY updated_at DESC
                LIMIT 100";

        $stmt = $db->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $templates = [];
        while ($row = $result->fetch_assoc()) {
            $templates[] = $row;
        }
        $stmt->close();

        return $templates;
    }

    /**
     * Get a single template.
     */
    public function getTemplate($templateId) {
        $db = $this->getDb();
        if (!$db) return null;

        $stmt = $db->prepare("SELECT * FROM ai_communication_templates WHERE id = ? AND company_id = ?");
        if (!$stmt) return null;

        $stmt->bind_param('ii', $templateId, $this->companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $template = $result->fetch_assoc();
        $stmt->close();

        return $template;
    }

    /**
     * Get template version history.
     */
    public function getVersionHistory($templateId) {
        $db = $this->getDb();
        if (!$db) return [];

        $stmt = $db->prepare("SELECT * FROM ai_communication_template_versions WHERE template_id = ? ORDER BY version DESC");
        if (!$stmt) return [];

        $stmt->bind_param('i', $templateId);
        $stmt->execute();
        $result = $stmt->get_result();
        $versions = [];
        while ($row = $result->fetch_assoc()) {
            $versions[] = $row;
        }
        $stmt->close();

        return $versions;
    }

    // ============================================================
    // PRIVATE METHODS
    // ============================================================

    private function extractVariables($text) {
        $variables = [];
        if (preg_match_all('/\{\{(\w+)\}\}/', $text, $m)) {
            $variables = array_unique($m[1]);
        }
        return $variables;
    }

    private function resolveVariable($var, $contextType, $contextId, $db) {
        $sql = null;
        $params = [];
        $types = '';

        // Map variables to ERP queries
        $map = [
            'customer_name'   => "SELECT name FROM customers WHERE id = ? AND company_id = ?",
            'supplier_name'   => "SELECT name FROM suppliers WHERE id = ? AND company_id = ?",
            'employee_name'   => "SELECT CONCAT(first_name, ' ', last_name) FROM employees WHERE id = ? AND company_id = ?",
            'invoice_number'  => "SELECT invoice_number FROM invoices WHERE id = ? AND company_id = ?",
            'invoice_date'    => "SELECT invoice_date FROM invoices WHERE id = ? AND company_id = ?",
            'amount'          => "SELECT total_amount FROM invoices WHERE id = ? AND company_id = ?",
            'currency'        => "SELECT currency FROM invoices WHERE id = ? AND company_id = ?",
            'due_date'        => "SELECT due_date FROM invoices WHERE id = ? AND company_id = ?",
            'order_number'    => "SELECT order_number FROM sales_orders WHERE id = ? AND company_id = ?",
            'delivery_date'   => "SELECT delivery_date FROM purchase_orders WHERE id = ? AND company_id = ?",
            'po_number'       => "SELECT po_number FROM purchase_orders WHERE id = ? AND company_id = ?",
            'quotation_number'=> "SELECT quotation_number FROM quotations WHERE id = ? AND company_id = ?",
            'valid_until'     => "SELECT valid_until FROM quotations WHERE id = ? AND company_id = ?",
            'ticket_number'   => "SELECT ticket_number FROM support_tickets WHERE id = ? AND company_id = ?",
            'sender_name'     => "SELECT CONCAT(first_name, ' ', last_name) FROM employees WHERE id = ? AND company_id = ?",
        ];

        if (!isset($map[$var])) return null;

        $stmt = $db->prepare($map[$var]);
        if (!$stmt) return null;

        $stmt->bind_param('ii', $contextId, $this->companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row ? reset($row) : null;
    }

    private function storeVersion($templateId, $version, $subject, $body, $variables) {
        $db = $this->getDb();
        if (!$db) return;

        $sql = "INSERT INTO ai_communication_template_versions
                (template_id, version, subject_template, body_template, variables, changed_by)
                VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $db->prepare($sql);
        if (!$stmt) return;

        $varsJson = json_encode($variables);
        $stmt->bind_param('iisssi', $templateId, $version, $subject, $body, $varsJson, $this->userId);
        $stmt->execute();
        $stmt->close();
    }

    private function callAIProvider($prompt) {
        if (class_exists('AIProviderService')) {
            $providerService = new AIProviderService();
            if (method_exists($providerService, 'complete')) {
                $result = $providerService->complete($prompt);
                if ($result) return $result;
            }
        }
        return ['text' => '{}', 'provider' => 'none', 'model' => 'none', 'tokens_used' => 0, 'cost_usd' => 0];
    }

    private function getDb() {
        if (function_exists('getDB')) return getDB();
        global $db;
        return $db ?? null;
    }
}
