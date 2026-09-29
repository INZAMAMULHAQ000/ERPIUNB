<?php
/**
 * Widget Definitions API
 *
 * Returns all available widget definitions for the current user.
 * Used by the catalog/panel to show addable widgets.
 *
 * Usage: api/widgets/definitions.php?module=executive
 */

require_once dirname(__DIR__, 2) . '/includes/header.php';

header('Content-Type: application/json');

require_once dirname(__DIR__, 2) . '/config/widgets.php';

$module = $_GET['module'] ?? null;
$available = getAvailableWidgets($module);

// Add default layout positions for catalog display
$result = [];
foreach ($available as $widget) {
    $result[] = [
        'widget_key' => $widget['widget_key'],
        'module' => $widget['module'],
        'name' => $widget['name'],
        'description' => $widget['description'],
        'icon' => $widget['icon'],
        'category' => $widget['category'],
        'default_width' => (int)$widget['default_width'],
        'default_height' => (int)$widget['default_height'],
        'render_type' => $widget['render_type'],
    ];
}

echo json_encode([
    'success' => true,
    'widgets' => $result,
    'categories' => getWidgetCategories(),
    'modules' => getWidgetModules()
]);
