<?php
/**
 * User Dashboard Layout API
 *
 * Handles saving, loading, and resetting per-user dashboard layouts.
 *
 * GET:  api/dashboard/layout.php?dashboard=main  → returns user's saved layout
 * POST: api/dashboard/layout.php                  → saves user's layout (body: JSON)
 * POST: api/dashboard/layout.php?action=reset     → resets to defaults
 */

require_once dirname(__DIR__, 2) . '/includes/header.php';
require_once dirname(__DIR__, 2) . '/config/widgets.php';

// header.php starts an output buffer and renders the full HTML page into it.
// This is an API — discard that buffered markup so the response is pure JSON.
while (ob_get_level()) { ob_end_clean(); }

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$dashboardKey = $_GET['dashboard'] ?? 'main';
$action = $_GET['action'] ?? '';

// ── POST: Requires customize permission ──────────────────────────
if ($method === 'POST' && !hasPermission('dashboard.customize')) {
    echo json_encode(['success' => false, 'error' => 'You do not have permission to customize the dashboard']);
    exit;
}

// ── GET: Load layout ───────────────────────────────────────────
if ($method === 'GET') {
    $saved = getUserDashboardLayout($userId, $dashboardKey);

    if ($saved && $saved['is_customized']) {
        echo json_encode([
            'success' => true,
            'layout' => $saved['layout'],
            'is_customized' => true
        ]);
        exit;
    }

    // No custom layout → return default layout
    $default = getDefaultLayout($dashboardKey);
    echo json_encode([
        'success' => true,
        'layout' => $default,
        'is_customized' => false
    ]);
    exit;
}

// ── POST: Save layout ──────────────────────────────────────────
if ($method === 'POST') {
    // CSRF check
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!empty($token) && !verifyCsrf($token)) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    // Reset action
    if ($action === 'reset') {
        resetUserDashboardLayout($userId, $dashboardKey);
        echo json_encode(['success' => true, 'message' => 'Layout reset to defaults']);
        exit;
    }

    // Save layout
    $input = file_get_contents('php://input');
    $layoutData = json_decode($input, true);

    if (!$layoutData || !isset($layoutData['widgets'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid layout data']);
        exit;
    }

    // Validate widget references exist in registry
    $validKeys = [];
    foreach (array_keys($layoutData['widgets']) as $key) {
        $def = getWidgetDefinition($key);
        if ($def) $validKeys[] = $key;
    }

    if (empty($validKeys)) {
        echo json_encode(['success' => false, 'error' => 'No valid widgets in layout']);
        exit;
    }

    $layoutData['version'] = $layoutData['version'] ?? 1;
    $layoutData['dashboard_key'] = $dashboardKey;
    $layoutData['updated_at'] = date('c');

    $saved = saveUserDashboardLayout($userId, $dashboardKey, $layoutData);

    if ($saved) {
        // Audit log
        logAudit($userId, 'customized_dashboard', $dashboardKey);
        echo json_encode(['success' => true, 'message' => 'Layout saved']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save layout']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Method not allowed']);
