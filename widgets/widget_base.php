<?php
/**
 * Widget Handler Base Class
 *
 * All widget handlers extend this class.
 * Provides standardized response format and helper methods.
 */

abstract class WidgetHandler {
    protected $db;
    protected $definition;
    protected $params;

    public function __construct($definition, $params = []) {
        $this->db = getDB();
        $this->definition = $definition;
        $this->params = $params;
    }

    /**
     * Each widget handler must implement this method.
     * Returns an associative array with widget data.
     */
    abstract public function getData();

    /**
     * Standard success response
     */
    protected function success($data, $chartType = null, $chartOptions = null) {
        $response = [
            'success' => true,
            'widget_key' => $this->definition['widget_key'],
            'data' => $data
        ];

        if ($chartType) {
            $response['chart_type'] = $chartType;
        }

        if ($chartOptions) {
            $response['chart_options'] = $chartOptions;
        }

        return $response;
    }

    /**
     * Standard error response
     */
    protected function error($message) {
        return [
            'success' => false,
            'widget_key' => $this->definition['widget_key'],
            'error' => $message
        ];
    }

    /**
     * Get a parameter value with default
     */
    protected function getParam($key, $default = null) {
        return $this->params[$key] ?? $default;
    }

    /**
     * Get current user ID
     */
    protected function getUserId() {
        return $_SESSION['user_id'] ?? 0;
    }

    /**
     * Format a number as currency
     */
    protected function formatMoney($amount, $decimals = null) {
        return formatMoney($amount, $decimals);
    }

    /**
     * Sanitize output
     */
    protected function sanitize($data) {
        return sanitize($data);
    }

    /**
     * Check if data is empty
     */
    protected function isEmpty($data) {
        return empty($data);
    }

    /**
     * Return empty state
     */
    protected function emptyState($message = 'No data available') {
        return $this->success([
            'empty' => true,
            'message' => $message
        ]);
    }
}

/**
 * Render a widget view from a template file
 *
 * @param string $templatePath Path to template file (relative to widgets/views/)
 * @param array $data Data to extract into template scope
 * @return string Rendered HTML
 */
function renderWidgetView($templatePath, $data = []) {
    extract($data);
    ob_start();
    include $templatePath;
    return ob_get_clean();
}

/**
 * Load and dispatch a widget handler
 */
function loadWidgetHandler($widgetKey, $params = []) {
    $definition = getWidgetDefinition($widgetKey);

    if (!$definition) {
        return ['success' => false, 'error' => 'Widget not found'];
    }

    if ($definition['status'] !== 'active') {
        return ['success' => false, 'error' => 'Widget is inactive'];
    }

    if ($definition['permission_required'] && !hasPermission($definition['permission_required'])) {
        return ['success' => false, 'error' => 'Permission denied'];
    }

    // Build handler class name from widget key
    $className = 'Widget_' . str_replace(' ', '', ucwords(str_replace('_', ' ', $widgetKey)));

    // Check if a handler file exists
    $handlerFile = __DIR__ . '/handlers/' . $widgetKey . '.php';
    if (file_exists($handlerFile)) {
        require_once $handlerFile;
        if (class_exists($className)) {
            $handler = new $className($definition, $params);
            return $handler->getData();
        }
    }

    // Fallback: use a generic handler based on render type
    $handlerFile = __DIR__ . '/handlers/' . $definition['render_type'] . '.php';
    if (file_exists($handlerFile)) {
        require_once $handlerFile;
        $genericClass = 'Widget_' . $definition['render_type'];
        if (class_exists($genericClass)) {
            $handler = new $genericClass($definition, $params);
            return $handler->getData();
        }
    }

    return ['success' => false, 'error' => 'Widget handler not implemented'];
}
