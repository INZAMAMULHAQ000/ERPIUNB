<?php
/**
 * AJAX: Load grouped placeholders for data binding dropdown
 * GET /ajax/load_placeholders.php?module=reports&action=employee_summary
 *
 * Returns JSON of grouped placeholders consumable by the visual editor's
 * data binding panel.
 */
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/helpers/security.php';
require_once dirname(__DIR__, 3) . '/helpers/rbac.php';
require_once dirname(__DIR__, 3) . '/middleware/auth.php';
require_once dirname(__DIR__, 3) . '/helpers/placeholder_registry.php';

requireAuth();
if (!hasPermission('template_designer.view')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

$module = $_GET['module'] ?? '';
$action = $_GET['action'] ?? '';

// Map module/action to a template type for placeholder lookup
// This mirrors the mapping in template_module_mappings
$typeMap = [
    'reports' => [
        'employee_summary'   => 'employee_report',
        'attendance_report'  => 'attendance_report',
        'leave_summary'      => 'leave_report',
        'default'            => 'report',
    ],
    'leave_management' => [
        'employee'  => 'leave_report',
        'department'=> 'leave_report',
        'type'      => 'leave_report',
        'default'   => 'leave_report',
    ],
    'invoices' => [
        'default' => 'invoice',
    ],
    'employees' => [
        'default' => 'employee_report',
    ],
    'purchase' => [
        'default' => 'purchase_order',
    ],
];

// Resolve template type
$templateType = null;
if (isset($typeMap[$module])) {
    $templateType = $typeMap[$module][$action] ?? $typeMap[$module]['default'] ?? null;
}

// Get grouped placeholders
$groups = getGroupedPlaceholders($templateType);

// Transform to the format expected by the visual editor
$result = [
    'module' => $module,
    'action' => $action,
    'template_type' => $templateType,
    'groups' => [],
];

foreach ($groups as $key => $group) {
    $items = [];
    foreach ($group['placeholders'] as $pkey => $pval) {
        $items[] = [
            'key'   => $pkey,
            'label' => $pval['label'] ?? $pkey,
            'type'  => $pval['type'] ?? 'text',
            'default' => $pval['default'] ?? '',
        ];
    }
    $result['groups'][] = [
        'key'   => $key,
        'label' => $group['label'] ?? ucfirst($key),
        'items' => $items,
    ];
}

// Also include sample data
$result['sample_data'] = getSampleDataForType($templateType ?? 'report');

echo json_encode($result);
