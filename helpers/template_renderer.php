<?php
/**
 * Template Renderer — Integration Layer
 *
 * The public API for any ERP module to consume templates.
 * Modules call these functions to generate documents using Template Designer.
 *
 * Usage from any module:
 *   require_once 'helpers/template_renderer.php';
 *   $html = renderTemplateForModule('invoices', 'pdf', $invoiceData);
 *   // ... or stream directly:
 *   streamTemplateForModule('invoices', 'pdf', $invoiceData);
 */

require_once dirname(__DIR__) . '/modules/template_designer/components/html_renderer.php';
require_once dirname(__DIR__) . '/modules/template_designer/components/pdf_generator.php';
require_once dirname(__DIR__) . '/helpers/placeholder_registry.php';

/**
 * Find the default template for a given module/action combination.
 * Returns the template record + latest published version data.
 *
 * @param string $module Module name (e.g., 'invoices', 'reports')
 * @param string|null $action Module action (e.g., 'pdf', 'print', or null for any)
 * @return array|null ['template' => [...], 'data' => [...]] or null if not found
 */
function findTemplateForModule(string $module, ?string $action = null): ?array {
    $db = getDB();

    // Look for a default mapping first
    if ($action) {
        $stmt = $db->prepare("
            SELECT t.*, tm.is_default
            FROM templates t
            JOIN template_module_mappings tm ON t.id = tm.template_id
            WHERE tm.module_name = ? AND tm.module_action = ? AND t.status = 'published'
            ORDER BY tm.is_default DESC, t.updated_at DESC
            LIMIT 1
        ");
        $stmt->execute([$module, $action]);
    } else {
        $stmt = $db->prepare("
            SELECT t.*, tm.is_default
            FROM templates t
            JOIN template_module_mappings tm ON t.id = tm.template_id
            WHERE tm.module_name = ? AND t.status = 'published'
            ORDER BY tm.is_default DESC, t.updated_at DESC
            LIMIT 1
        ");
        $stmt->execute([$module]);
    }

    $template = $stmt->fetch();

    if (!$template) return null;

    // Load latest published version data
    $stmt = $db->prepare("SELECT * FROM template_versions WHERE template_id = ? AND status = 'published' ORDER BY version_number DESC LIMIT 1");
    $stmt->execute([$template['id']]);
    $version = $stmt->fetch();

    if (!$version) return null;

    $templateData = json_decode($version['template_data'], true);
    if (!$templateData) return null;

    return [
        'template' => $template,
        'data' => $templateData,
        'version' => $version,
    ];
}

/**
 * Render a template as HTML for a specific module.
 *
 * @param string $module Module name
 * @param string|null $action Module action
 * @param array $moduleData Data values for placeholder replacement
 * @return string|null Full HTML document, or null if no template found
 */
function renderTemplateForModule(string $module, ?string $action, array $moduleData): ?string {
    $found = findTemplateForModule($module, $action);
    if (!$found) return null;

    return renderTemplateHtml($found['data'], $moduleData, $found['template']['template_type']);
}

/**
 * Stream a PDF for a specific module using its mapped template.
 * Falls back to returning null if no template is configured.
 *
 * @param string $module Module name
 * @param string|null $action Module action
 * @param array $moduleData Data values for placeholders
 * @param string $filename Output filename (without .pdf)
 * @param bool $inline Display in browser vs download
 */
function streamTemplateForModule(string $module, ?string $action, array $moduleData, string $filename = 'document', bool $inline = false): void {
    $found = findTemplateForModule($module, $action);
    if (!$found) {
        http_response_code(404);
        die('No template configured for ' . $module . ($action ? '/' . $action : ''));
    }

    $html = renderTemplateHtml($found['data'], $moduleData, $found['template']['template_type']);

    streamPdf($html, $filename, [
        'page_size' => $found['template']['page_size'] ?? 'A4',
        'orientation' => $found['template']['page_orientation'] ?? 'portrait',
        'inline' => $inline,
    ]);
}

/**
 * Low-level render: use a specific template ID with custom data.
 *
 * @param int $templateId
 * @param array $data
 * @return string|null HTML or null
 */
function renderTemplateById(int $templateId, array $data): ?string {
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM templates WHERE id = ? AND status = 'published'");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch();
    if (!$template) return null;

    $stmt = $db->prepare("SELECT * FROM template_versions WHERE template_id = ? AND status = 'published' ORDER BY version_number DESC LIMIT 1");
    $stmt->execute([$templateId]);
    $version = $stmt->fetch();
    if (!$version) return null;

    $templateData = json_decode($version['template_data'], true);
    if (!$templateData) return null;

    return renderTemplateHtml($templateData, $data, $template['template_type']);
}

/**
 * Stream PDF by template ID with custom data.
 */
function streamTemplateById(int $templateId, array $data, string $filename = 'document', bool $inline = false): void {
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM templates WHERE id = ? AND status = 'published'");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch();
    if (!$template) {
        http_response_code(404);
        die('Template not found or not published');
    }

    $stmt = $db->prepare("SELECT * FROM template_versions WHERE template_id = ? AND status = 'published' ORDER BY version_number DESC LIMIT 1");
    $stmt->execute([$templateId]);
    $version = $stmt->fetch();
    if (!$version) {
        http_response_code(404);
        die('No published version found for this template');
    }

    $templateData = json_decode($version['template_data'], true);
    if (!$templateData) {
        http_response_code(500);
        die('Invalid template data');
    }

    $html = renderTemplateHtml($templateData, $data, $template['template_type']);

    streamPdf($html, $filename, [
        'page_size' => $template['page_size'] ?? 'A4',
        'orientation' => $template['page_orientation'] ?? 'portrait',
        'inline' => $inline,
    ]);
}

/**
 * Backward compatibility shim.
 * When modules cannot find a template, they should fall back to their
 * original inline generation logic (which they still have — we never removed it).
 * This function checks if a template is available.
 */
function hasTemplateForModule(string $module, ?string $action = null): bool {
    return findTemplateForModule($module, $action) !== null;
}

/**
 * Register a new placeholder set for a module type.
 * Modules can call this during initialization to add their placeholders.
 *
 * @param string $type Template type key
 * @param array $placeholders Array of [key => ['label' => ..., 'type' => ..., 'default' => ...]]
 */
function registerModulePlaceholders(string $type, array $placeholders): void {
    // This function extends the global placeholder map.
    // The placeholder_registry.php is the static file, but this can be used
    // for dynamic registration at runtime.
    // Placeholder: could be stored in DB or session for custom modules.
    // For now, modules should edit helpers/placeholder_registry.php to add their placeholders.
    // This hook is available for future dynamic registration.
}