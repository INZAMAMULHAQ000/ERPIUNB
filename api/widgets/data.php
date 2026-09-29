<?php
/**
 * Widget Data API
 *
 * Returns JSON data for a single widget.
 * Handles both simple KPI widgets (via generic handler) and
 * complex chart/table widgets (via dedicated handlers).
 *
 * Usage: api/widgets/data.php?widget=widget_key
 */

require_once dirname(__DIR__, 2) . '/includes/header.php';

// header.php starts an output buffer and renders the full HTML page into it.
// This is an API — discard that buffered markup so the response is pure JSON.
while (ob_get_level()) { ob_end_clean(); }

// Only return JSON, not full HTML
header('Content-Type: application/json');

$widgetKey = $_GET['widget'] ?? '';
$period = $_GET['period'] ?? '';

if (!$widgetKey) {
    echo json_encode(['success' => false, 'error' => 'Widget key is required']);
    exit;
}

// Load widget system
require_once dirname(__DIR__, 2) . '/config/widgets.php';

// Get widget definition
$definition = getWidgetDefinition($widgetKey);

if (!$definition) {
    echo json_encode(['success' => false, 'error' => 'Widget not found: ' . $widgetKey]);
    exit;
}

if ($definition['status'] !== 'active') {
    echo json_encode(['success' => false, 'error' => 'Widget is inactive']);
    exit;
}

// Permission check
if ($definition['permission_required'] && !hasPermission($definition['permission_required'])) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

// Load all handler files (individual + combined + generic)
$individualFile = __DIR__ . '/handlers/' . $widgetKey . '.php';
$combinedFile = __DIR__ . '/handlers/_combined.php';
$genericFile = __DIR__ . '/handlers/_generic_kpi.php';

// Load combined handlers first (most widgets live here)
if (file_exists($combinedFile)) {
    require_once $combinedFile;
}

// Check individual handler (overrides combined)
if (file_exists($individualFile)) {
    require_once $individualFile;
}

// Try the named function (from combined or individual handler)
$funcName = 'widget_' . $widgetKey;
if (function_exists($funcName)) {
    $result = $funcName([
        'period' => $period,
        'user_id' => $_SESSION['user_id'] ?? 0,
        'widget' => $definition
    ]);
    $result['definition_name'] = $definition['name'];
    $result['chart_type'] = $result['chart_type'] ?? ($definition['widget_options'] ? json_decode($definition['widget_options'], true)['chart_type'] ?? null : null);
    echo json_encode($result);
    exit;
}

// Fallback: try generic KPI handler (for simple stat widgets)
require_once $genericFile;
if (function_exists('widget_generic_kpi')) {
    $result = widget_generic_kpi($widgetKey, $definition, [
        'period' => $period,
        'user_id' => $_SESSION['user_id'] ?? 0
    ]);
    if ($result !== false) {
        $result['definition_name'] = $definition['name'];
        echo json_encode($result);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Handler not implemented for: ' . $widgetKey]);
