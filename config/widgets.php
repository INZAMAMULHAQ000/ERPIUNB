<?php
/**
 * Widget Registry Configuration
 *
 * Central manifest defining where widget handlers live and
 * how to discover available widgets for each module.
 *
 * Widget discovery order:
 * 1. Database (widget_definitions table) — primary source
 * 2. Module manifest files to register additional widgets
 *
 * A widget is "available" to a user when:
 * - widget_definitions.status = 'active'
 * - User has the required permission (if permission_required is set)
 * - Widget is not in the user's hidden list
 */

/**
 * Get all registered widget definitions from the database
 */
function getWidgetDefinitions($module = null, $activeOnly = true) {
    $db = getDB();
    $sql = "SELECT * FROM widget_definitions WHERE 1=1";
    $params = [];

    if ($module) {
        $sql .= " AND module = ?";
        $params[] = $module;
    }

    if ($activeOnly) {
        $sql .= " AND status = 'active'";
    }

    $sql .= " ORDER BY module, category, name";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get a single widget definition by key
 */
function getWidgetDefinition($widgetKey) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM widget_definitions WHERE widget_key = ? LIMIT 1");
    $stmt->execute([$widgetKey]);
    return $stmt->fetch();
}

/**
 * Get available widgets for the current user (permission-filtered)
 */
function getAvailableWidgets($module = null, $userId = null) {
    $widgets = getWidgetDefinitions($module, true);
    $userId = $userId ?? ($_SESSION['user_id'] ?? null);

    if (!$userId) return [];

    $available = [];
    foreach ($widgets as $widget) {
        if ($widget['permission_required'] && !hasPermission($widget['permission_required'])) {
            continue;
        }
        if ($widget['widget_options']) {
            $widget['widget_options'] = json_decode($widget['widget_options'], true);
        }
        $available[] = $widget;
    }

    return $available;
}

/**
 * Register a widget definition (insert if not exists, update if exists)
 */
function registerWidgetDefinition($definition) {
    $db = getDB();

    $required = ['widget_key', 'module', 'name'];
    foreach ($required as $field) {
        if (empty($definition[$field])) {
            return false;
        }
    }

    $stmt = $db->prepare("
        INSERT INTO widget_definitions
            (widget_key, module, name, description, icon, category,
             default_width, default_height, min_width, min_height,
             data_url, render_type, permission_required, is_system, status, widget_options)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            description = VALUES(description),
            icon = VALUES(icon),
            category = VALUES(category),
            default_width = VALUES(default_width),
            default_height = VALUES(default_height),
            data_url = VALUES(data_url),
            render_type = VALUES(render_type),
            permission_required = VALUES(permission_required),
            status = VALUES(status),
            widget_options = VALUES(widget_options),
            updated_at = NOW()
    ");

    return $stmt->execute([
        $definition['widget_key'],
        $definition['module'],
        $definition['name'],
        $definition['description'] ?? '',
        $definition['icon'] ?? 'fas fa-chart-bar',
        $definition['category'] ?? 'charts',
        $definition['default_width'] ?? 1,
        $definition['default_height'] ?? 1,
        $definition['min_width'] ?? 1,
        $definition['min_height'] ?? 1,
        $definition['data_url'] ?? null,
        $definition['render_type'] ?? 'server_html',
        $definition['permission_required'] ?? null,
        $definition['is_system'] ?? 0,
        $definition['status'] ?? 'active',
        isset($definition['widget_options']) ? json_encode($definition['widget_options']) : null
    ]);
}

/**
 * Get all module names that have widgets
 */
function getWidgetModules() {
    $db = getDB();
    return $db->query("SELECT DISTINCT module FROM widget_definitions WHERE status = 'active' ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Get widget categories
 */
function getWidgetCategories() {
    $db = getDB();
    return $db->query("SELECT DISTINCT category FROM widget_definitions WHERE status = 'active' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Get user's saved layout for a dashboard
 */
function getUserDashboardLayout($userId, $dashboardKey) {
    $db = getDB();
    $stmt = $db->prepare("SELECT layout_json, is_customized FROM user_dashboard_layouts WHERE user_id = ? AND dashboard_key = ? LIMIT 1");
    $stmt->execute([$userId, $dashboardKey]);
    $row = $stmt->fetch();

    if ($row) {
        return [
            'layout' => json_decode($row['layout_json'], true),
            'is_customized' => (bool)$row['is_customized']
        ];
    }

    return null;
}

/**
 * Save user's dashboard layout
 */
function saveUserDashboardLayout($userId, $dashboardKey, $layoutData) {
    $db = getDB();
    $json = json_encode($layoutData);

    $stmt = $db->prepare("
        INSERT INTO user_dashboard_layouts (user_id, dashboard_key, layout_json, is_customized)
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            layout_json = VALUES(layout_json),
            is_customized = 1,
            updated_at = NOW()
    ");

    return $stmt->execute([$userId, $dashboardKey, $json]);
}

/**
 * Reset user's dashboard layout to default
 */
function resetUserDashboardLayout($userId, $dashboardKey) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM user_dashboard_layouts WHERE user_id = ? AND dashboard_key = ?");
    return $stmt->execute([$userId, $dashboardKey]);
}

/**
 * Get the default layout for a dashboard (all available widgets in default positions)
 */
function getDefaultLayout($dashboardKey) {
    $widgets = getAvailableWidgets($dashboardKey === 'main' ? null : $dashboardKey);

    $layout = [
        'version' => 1,
        'dashboard_key' => $dashboardKey,
        'columns' => 4,
        'widgets' => [],
        'hidden_widgets' => []
    ];

    $index = 0;
    $col = 0;
    $row = 0;
    $maxCol = 4;

    foreach ($widgets as $widget) {
        $w = $widget['default_width'];

        if ($col + $w > $maxCol) {
            $col = 0;
            $row++;
        }

        $layout['widgets'][$widget['widget_key']] = [
            'instance_id' => 'w_' . uniqid(),
            'enabled' => true,
            'x' => $col,
            'y' => $row,
            'width' => (int)$widget['default_width'],
            'height' => (int)$widget['default_height'],
            'settings' => [],
            'order' => $index
        ];

        $col += $w;
        $index++;
    }

    return $layout;
}
