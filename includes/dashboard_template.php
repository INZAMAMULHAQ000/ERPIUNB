<?php
/**
 * Dashboard Template Include
 *
 * Bridges the PHP backend with the dynamic dashboard JS engine.
 * Include this at the bottom of any dashboard page to enable
 * the widget customization system.
 *
 * Usage:
 *   $dashboardKey = 'main'; // or 'executive', 'helpdesk', etc.
 *   require_once 'includes/dashboard_template.php';
 *
 * Backward compatible: if user hasn't customized, the old static
 * content remains visible. Once they customize, the dynamic grid
 * replaces it on subsequent visits.
 */

$dashboardKey = $dashboardKey ?? 'main';

require_once dirname(__DIR__) . '/config/widgets.php';

// header.php already resolves this (see $dashboardHasCustomLayout there) when
// the page sets $dashboardKey before including it, so #dashboard-old-content
// can start hidden and avoid a flash of the legacy layout. Reuse that result
// instead of querying again; fall back to a fresh lookup for any caller that
// includes this template without going through that early check.
$hasCustomLayout = $dashboardHasCustomLayout ?? (function () use ($dashboardKey) {
    $layoutCheck = getUserDashboardLayout($_SESSION['user_id'], $dashboardKey);
    return $layoutCheck && $layoutCheck['is_customized'];
})();
?>
<link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/assets/css/dashboard.css">

<!-- Dynamic dashboard container (always present, shown when customized) -->
<div id="dashboard-grid-container"
     data-dashboard-key="<?= $dashboardKey ?>"
     data-has-custom-layout="<?= $hasCustomLayout ? '1' : '0' ?>"
     <?= $hasCustomLayout ? '' : 'style="display:none"' ?>></div>

<script src="<?= rtrim(BASE_URL, '/') ?>/assets/js/dashboard-core.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var container = document.getElementById('dashboard-grid-container');
    if (!container) return;

    var dashboardKey = container.dataset.dashboardKey || 'main';
    var hasCustomLayout = container.dataset.hasCustomLayout === '1';

    // Initialize dashboard engine
    var dashboard = new Dashboard('dashboard-grid-container', {
        dashboardKey: dashboardKey,
        // Absolute paths so layout/widget calls resolve against the site root
        // even from module pages (executive_dashboard, analytics, …) where the
        // old relative defaults produced 404s under modules/<module>/api/…
        apiBase: '<?= rtrim(BASE_URL, '/') ?>/api/dashboard',
        widgetApiBase: '<?= rtrim(BASE_URL, '/') ?>/api/widgets',
        csrfToken: '<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>',
        editMode: false,
        renderOnInit: hasCustomLayout,
        onError: function(msg) {
            console.warn('Dashboard:', msg);
        },
        onLayoutLoad: function(isCustomized) {
            // When user customizes for the first time or layout changes
            if (isCustomized) {
                // Show grid container
                container.style.display = '';
                // Hide old static content
                var oldContent = document.getElementById('dashboard-old-content');
                if (oldContent) {
                    oldContent.style.display = 'none';
                }
            }
        }
    });

    // Expose for debugging
    window.__dashboard = dashboard;
});
</script>
