<?php
/**
 * Template Designer — AJAX Save Endpoint
 * Handles template saving, publishing, and module mapping changes
 */
session_start();
$basePath = dirname(__DIR__, 3);
require_once $basePath . '/config/database.php';
require_once $basePath . '/helpers/security.php';
require_once $basePath . '/helpers/rbac.php';
require_once $basePath . '/helpers/redirect.php';
require_once $basePath . '/middleware/auth.php';
require_once $basePath . '/modules/template_designer/components/template_sanitizer.php';

requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Parse input body (needed for both CSRF check and data processing)
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = [];
}

// Verify CSRF token on non-AJAX POST requests / direct POST
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    if (!verifyCsrf($input['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

$db = getDB();

if (!isset($input['template_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$templateId = (int)$input['template_id'];

// Verify template exists
$stmt = $db->prepare("SELECT * FROM templates WHERE id = ?");
$stmt->execute([$templateId]);
$template = $stmt->fetch();

if (!$template) {
    echo json_encode(['success' => false, 'error' => 'Template not found']);
    exit;
}

try {
    $db->beginTransaction();

    // ============================================================
    // Handle: REMOVE MAPPING
    // ============================================================
    if (isset($input['remove_mapping_id'])) {
        if (!hasPermission('template_designer.edit')) {
            throw new Exception('Access denied');
        }
        $stmt = $db->prepare("DELETE FROM template_module_mappings WHERE id = ? AND template_id = ?");
        $stmt->execute([(int)$input['remove_mapping_id'], $templateId]);
        $db->commit();
        echo json_encode(['success' => true]);
        exit;
    }

    // ============================================================
    // Handle: ADD MAPPING
    // ============================================================
    if (isset($input['add_mapping'])) {
        if (!hasPermission('template_designer.edit')) {
            throw new Exception('Access denied');
        }
        $map = $input['add_mapping'];
        $stmt = $db->prepare("
            INSERT INTO template_module_mappings (template_id, module_name, module_action, is_default)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$templateId, $map['module'], $map['action'] ?? null, $map['is_default'] ?? 0]);
        $db->commit();
        echo json_encode(['success' => true]);
        exit;
    }

    // ============================================================
    // Handle: PUBLISH
    // ============================================================
    if (!empty($input['publish'])) {
        if (!hasPermission('template_designer.publish')) {
            throw new Exception('Access denied');
        }

        // Publish latest draft version
        $stmt = $db->prepare("SELECT id FROM template_versions WHERE template_id = ? AND status = 'draft' ORDER BY version_number DESC LIMIT 1");
        $stmt->execute([$templateId]);
        $draftVersion = $stmt->fetch();

        if ($draftVersion) {
            $stmt = $db->prepare("UPDATE template_versions SET status = 'published' WHERE id = ?");
            $stmt->execute([$draftVersion['id']]);
        }

        // Update template status
        $stmt = $db->prepare("UPDATE templates SET status = 'published' WHERE id = ?");
        $stmt->execute([$templateId]);

        // Audit
        $stmt = $db->prepare("INSERT INTO template_history (template_id, action, performed_by) VALUES (?, 'published', ?)");
        $stmt->execute([$templateId, $_SESSION['user_id']]);
        logAudit($_SESSION['user_id'], 'publish_template', 'template_designer');

        $db->commit();
        echo json_encode(['success' => true]);
        exit;
    }

    // ============================================================
    // Handle: SAVE
    // ============================================================
    if (!hasPermission('template_designer.edit')) {
        throw new Exception('Access denied');
    }

    if (!isset($input['template_data'])) {
        throw new Exception('No template data provided');
    }

    // Sanitize template data before saving
    $templateData = sanitizeTemplateData($input['template_data']);
    $templateName = trim(strip_tags($input['template_name'] ?? $template['template_name']));
    $categoryId = !empty($input['category_id']) ? (int)$input['category_id'] : null;
    $description = trim($input['description'] ?? $template['description']);
    $pageSize = $input['page_size'] ?? 'A4';
    $pageOrientation = $input['page_orientation'] ?? 'portrait';

    // Update template metadata
    $stmt = $db->prepare("
        UPDATE templates SET template_name = ?, category_id = ?, description = ?, page_size = ?, page_orientation = ?, updated_by = ?
        WHERE id = ?
    ");
    $stmt->execute([$templateName, $categoryId, $description, $pageSize, $pageOrientation, $_SESSION['user_id'], $templateId]);

    // Increment version
    $newVersion = (int)$template['version'] + 1;
    $stmt = $db->prepare("UPDATE templates SET version = ? WHERE id = ?");
    $stmt->execute([$newVersion, $templateId]);

    // Extract placeholders from template data
    $placeholders = [];
    array_walk_recursive($templateData, function($v, $k) use (&$placeholders) {
        if (is_string($v) && preg_match_all('/\{\{(\w+)\}\}/', $v, $matches)) {
            foreach ($matches[1] as $m) {
                $placeholders[$m] = $m;
            }
        }
    });
    // Also scan table column keys, field placeholders
    if (isset($templateData['body']['sections'])) {
        foreach ($templateData['body']['sections'] as $section) {
            if (!empty($section['components'])) {
                foreach ($section['components'] as $comp) {
                    if ($comp['type'] === 'field' && !empty($comp['placeholder']) && preg_match('/\{\{(\w+)\}\}/', $comp['placeholder'], $m)) {
                        $placeholders[$m[1]] = $m[1];
                    }
                    if ($comp['type'] === 'table' && !empty($comp['columns'])) {
                        foreach ($comp['columns'] as $col) {
                            if (strpos($col['key'], '{{') !== false && preg_match('/\{\{(\w+)\}\}/', $col['key'], $m)) {
                                $placeholders[$m[1]] = $m[1];
                            }
                        }
                    }
                }
            }
        }
    }

    // Save new version
    $stmt = $db->prepare("
        INSERT INTO template_versions (template_id, version_number, template_data, placeholder_definitions, status, created_by)
        VALUES (?, ?, ?, ?, 'draft', ?)
    ");
    $stmt->execute([
        $templateId,
        $newVersion,
        json_encode($templateData),
        json_encode(array_values($placeholders)),
        $_SESSION['user_id']
    ]);

    // Audit log
    $stmt = $db->prepare("INSERT INTO template_history (template_id, action, performed_by) VALUES (?, 'saved', ?)");
    $stmt->execute([$templateId, $_SESSION['user_id']]);
    logAudit($_SESSION['user_id'], 'save_template', 'template_designer');

    $db->commit();
    echo json_encode(['success' => true, 'version' => $newVersion]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}