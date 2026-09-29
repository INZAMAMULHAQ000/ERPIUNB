<?php
/**
 * Seed Script: Create sample templates for testing the Template Designer
 * Run once after migration: php config/seeds/seed_templates.php
 */

$basePath = dirname(__DIR__, 2);
require_once $basePath . '/config/database.php';
require_once $basePath . '/helpers/security.php';
require_once $basePath . '/helpers/rbac.php';
require_once $basePath . '/helpers/redirect.php';
require_once $basePath . '/middleware/auth.php';

// This script is for CLI or direct execution
$db = getDB();

// ============================================================
// 1. Leave Report — Employee Summary Template
// ============================================================
createTemplate($db, [
    'category_id'         => 2, // HR Documents
    'template_name'       => 'Leave Report — Employee Summary',
    'template_type'       => 'leave_report',
    'description'         => 'Default leave summary report grouped by employee',
    'page_size'           => 'A4',
    'page_orientation'    => 'landscape',
    'module_name'         => 'leave_management',
    'module_action'       => 'employee',
    'is_default'          => 1,
    'body_sections'       => [
        [
            'type'  => 'section',
            'label' => 'Report Header',
            'components' => [
                ['type' => 'text', 'content' => '{{company_name}}', 'align' => 'left'],
                ['type' => 'text', 'content' => '<h1>{{report_title}}</h1>', 'align' => 'center'],
                ['type' => 'field', 'placeholder' => '{{date_from}} to {{date_to}}', 'label' => 'Period:', 'default' => 'Select period'],
            ],
        ],
        [
            'type'  => 'section',
            'label' => 'Data Table',
            'components' => [
                [
                    'type'     => 'table',
                    'label'    => 'Leave Data',
                    'data_key' => 'report_data',
                    'columns'  => [
                        ['key' => 'first_name',    'label' => 'Employee',       'width' => 25],
                        ['key' => 'employee_code',  'label' => 'Code',          'width' => 15],
                        ['key' => 'department_name','label' => 'Department',    'width' => 20],
                        ['key' => 'total_leaves',   'label' => 'Leaves Taken',  'width' => 15, 'align' => 'center'],
                        ['key' => 'total_days_used','label' => 'Days Used',     'width' => 13, 'align' => 'center'],
                        ['key' => 'lop_days',       'label' => 'LOP Days',      'width' => 12, 'align' => 'center'],
                    ],
                ],
            ],
        ],
    ],
    'footer_content' => [
        ['type' => 'divider', 'style' => 'solid'],
        ['type' => 'text', 'content' => 'Generated: {{generated_date}} | {{company_name}} | Page {{page_number}} of {{total_pages}}', 'align' => 'center'],
    ],
    'styles'  => [
        'font_family' => 'DejaVu Sans',
        'font_size'   => 10,
        'colors'      => ['primary' => '#4f46e5', 'text' => '#1e293b', 'background' => '#ffffff'],
        'table_styles'=> ['header_bg' => '#4f46e5', 'border_color' => '#e5e7eb', 'alternate_rows' => true],
    ],
]);

// ============================================================
// 2. Employee Summary Report
// ============================================================
createTemplate($db, [
    'category_id'         => 3, // Reports
    'template_name'       => 'Employee Summary Report',
    'template_type'       => 'report',
    'description'         => 'Standard employee summary report for HR analytics',
    'page_size'           => 'A4',
    'page_orientation'    => 'landscape',
    'module_name'         => 'reports',
    'module_action'       => 'employee_summary',
    'is_default'          => 1,
    'body_sections'       => [
        [
            'type'  => 'section',
            'label' => 'Report Header',
            'components' => [
                ['type' => 'text', 'content' => '{{company_name}}', 'align' => 'left'],
                ['type' => 'text', 'content' => '<h1>{{report_title}}</h1>', 'align' => 'center'],
                ['type' => 'field', 'placeholder' => '{{date_from}} to {{date_to}}', 'label' => 'Period:', 'default' => 'All time'],
                ['type' => 'field', 'placeholder' => '{{record_count}}', 'label' => 'Records:', 'default' => '0'],
            ],
        ],
        [
            'type'  => 'section',
            'label' => 'Employee Data',
            'components' => [
                [
                    'type'     => 'table',
                    'label'    => 'Employees',
                    'data_key' => 'report_data',
                    'columns'  => [
                        ['key' => 'employee_code',   'label' => 'Code',         'width' => 12],
                        ['key' => 'full_name',       'label' => 'Name',         'width' => 20],
                        ['key' => 'department',      'label' => 'Department',   'width' => 18],
                        ['key' => 'designation',     'label' => 'Designation',  'width' => 20],
                        ['key' => 'employment_type', 'label' => 'Type',         'width' => 12],
                        ['key' => 'joining_date',    'label' => 'Joined',       'width' => 10],
                        ['key' => 'status',          'label' => 'Status',       'width' => 8],
                    ],
                ],
            ],
        ],
    ],
    'footer_content' => [
        ['type' => 'divider', 'style' => 'solid'],
        ['type' => 'text', 'content' => 'Generated: {{generated_date}} by {{generated_by}} | {{company_name}}', 'align' => 'center'],
    ],
    'styles'  => [
        'font_family' => 'DejaVu Sans',
        'font_size'   => 9,
        'colors'      => ['primary' => '#1e40af', 'text' => '#1e293b', 'background' => '#ffffff'],
        'table_styles'=> ['header_bg' => '#1e40af', 'border_color' => '#e5e7eb', 'alternate_rows' => true],
    ],
]);

// ============================================================
// 3. Attendance Report
// ============================================================
createTemplate($db, [
    'category_id'         => 3, // Reports
    'template_name'       => 'Attendance Report',
    'template_type'       => 'report',
    'description'         => 'Monthly attendance summary with percentages',
    'page_size'           => 'A4',
    'page_orientation'    => 'landscape',
    'module_name'         => 'reports',
    'module_action'       => 'attendance_report',
    'is_default'          => 1,
    'body_sections'       => [
        [
            'type'  => 'section',
            'label' => 'Report Header',
            'components' => [
                ['type' => 'text', 'content' => '{{company_name}}', 'align' => 'left'],
                ['type' => 'text', 'content' => '<h1>{{report_title}}</h1>', 'align' => 'center'],
                ['type' => 'field', 'placeholder' => '{{date_from}} to {{date_to}}', 'label' => 'Period:', 'default' => 'All time'],
            ],
        ],
        [
            'type'  => 'section',
            'label' => 'Attendance Data',
            'components' => [
                [
                    'type'     => 'table',
                    'label'    => 'Attendance',
                    'data_key' => 'report_data',
                    'columns'  => [
                        ['key' => 'full_name',      'label' => 'Employee',        'width' => 22],
                        ['key' => 'department',     'label' => 'Department',      'width' => 18],
                        ['key' => 'total_days',     'label' => 'Total Days',      'width' => 12, 'align' => 'center'],
                        ['key' => 'present',        'label' => 'Present',         'width' => 12, 'align' => 'center'],
                        ['key' => 'absent',         'label' => 'Absent',          'width' => 12, 'align' => 'center'],
                        ['key' => 'late',           'label' => 'Late',            'width' => 10, 'align' => 'center'],
                        ['key' => 'attendance_pct', 'label' => 'Attendance %',    'width' => 14, 'align' => 'center'],
                    ],
                ],
            ],
        ],
    ],
    'footer_content' => [
        ['type' => 'divider', 'style' => 'solid'],
        ['type' => 'text', 'content' => 'Generated: {{generated_date}} | Page {{page_number}} of {{total_pages}}', 'align' => 'center'],
    ],
    'styles'  => [
        'font_family' => 'DejaVu Sans',
        'font_size'   => 9,
        'colors'      => ['primary' => '#059669', 'text' => '#1e293b', 'background' => '#ffffff'],
        'table_styles'=> ['header_bg' => '#059669', 'border_color' => '#e5e7eb', 'alternate_rows' => true],
    ],
]);

echo "Sample templates created successfully!\n";

// ============================================================
// Helper Function
// ============================================================
function createTemplate(PDO $db, array $opts): void {
    // Check if already exists
    $stmt = $db->prepare("SELECT id FROM templates WHERE template_name = ?");
    $stmt->execute([$opts['template_name']]);
    if ($stmt->fetch()) {
        echo "  [SKIP] '{$opts['template_name']}' already exists\n";
        return;
    }

    $db->beginTransaction();
    try {
        $userId = 1; // Super Admin

        // Build template data JSON
        $templateData = [
            'page' => [
                'size'        => $opts['page_size'],
                'orientation' => $opts['page_orientation'],
                'margins'     => ['top' => 20, 'right' => 15, 'bottom' => 20, 'left' => 15],
            ],
            'header' => [
                'enabled'              => true,
                'content'              => $opts['header_content'] ?? [],
                'different_first_page' => false,
            ],
            'body' => [
                'sections' => $opts['body_sections'],
            ],
            'footer' => [
                'enabled'              => true,
                'content'              => $opts['footer_content'] ?? [],
                'different_first_page' => false,
            ],
            'styles' => $opts['styles'],
        ];

        // Create template
        $stmt = $db->prepare("
            INSERT INTO templates (category_id, template_name, template_type, description, page_size, page_orientation, status, version, created_by)
            VALUES (?, ?, ?, ?, ?, ?, 'published', 1, ?)
        ");
        $stmt->execute([
            $opts['category_id'],
            $opts['template_name'],
            $opts['template_type'],
            $opts['description'],
            $opts['page_size'],
            $opts['page_orientation'],
            $userId,
        ]);
        $templateId = $db->lastInsertId();

        // Create version
        $stmt = $db->prepare("
            INSERT INTO template_versions (template_id, version_number, template_data, status, created_by)
            VALUES (?, 1, ?, 'published', ?)
        ");
        $stmt->execute([$templateId, json_encode($templateData), $userId]);

        // Create module mapping
        $stmt = $db->prepare("
            INSERT INTO template_module_mappings (template_id, module_name, module_action, is_default)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $templateId,
            $opts['module_name'],
            $opts['module_action'],
            $opts['is_default'] ?? 0,
        ]);

        // Audit log
        $stmt = $db->prepare("INSERT INTO template_history (template_id, action, performed_by) VALUES (?, 'created', ?)");
        $stmt->execute([$templateId, $userId]);

        $db->commit();
        echo "  [OK] Created '{$opts['template_name']}' (ID: $templateId)\n";

    } catch (Exception $e) {
        $db->rollBack();
        echo "  [FAIL] '{$opts['template_name']}': " . $e->getMessage() . "\n";
    }
}
