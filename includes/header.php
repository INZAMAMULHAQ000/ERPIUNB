<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';
require_once $basePath . '/helpers/security.php';
require_once $basePath . '/helpers/url.php';
require_once $basePath . '/helpers/rbac.php';
require_once $basePath . '/helpers/redirect.php';
require_once $basePath . '/helpers/validation.php';
require_once $basePath . '/helpers/pagination.php';
require_once $basePath . '/helpers/breadcrumbs.php';
require_once $basePath . '/middleware/auth.php';
require_once $basePath . '/helpers/currency.php';
require_once $basePath . '/helpers/company.php';
require_once $basePath . '/helpers/lookup.php';

requireAuth();

// Currency switcher via GET param — handled before any output
if (isset($_GET['set_currency'])) {
    setActiveCurrency($_GET['set_currency']);
    $redirect = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: $redirect");
    exit;
}

// Theme switcher — IBM Light/Dark only.
// Persists to $_SESSION AND users.theme (survives logout / re-login).
if (isset($_GET['set_theme']) && in_array($_GET['set_theme'], ['ibm-light', 'ibm-dark'])) {
    $_SESSION['theme'] = $_GET['set_theme'];
    if (!empty($_SESSION['user_id'])) {
        try {
            getDB()->prepare("UPDATE users SET theme = ? WHERE id = ?")
                   ->execute([$_GET['set_theme'], (int)$_SESSION['user_id']]);
        } catch (Throwable $e) { /* column not migrated yet — session still works */ }
    }
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
        $redirect = strtok($_SERVER['REQUEST_URI'], '?');
        header("Location: $redirect");
        exit;
    }
}

// ——— Active company context ———
// Documents (invoices, credit notes, statements) render an issuer block from
// these session keys. Before the Company Master existed, `company_tax_number`
// was never written, so those blocks silently fell back to the *customer's*
// tax registration number.
if (isset($_GET['set_company'])) {
    $wanted = (int) $_GET['set_company'];
    try {
        $allowed = getDB()->prepare("
            SELECT id FROM companies c
            WHERE c.id = ? AND c.deleted_at IS NULL AND c.status = 'active'
              AND (EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = ? AND ur.role_id = 1)
                   OR EXISTS (SELECT 1 FROM user_companies uc WHERE uc.user_id = ? AND uc.company_id = c.id))
        ");
        $allowed->execute([$wanted, $_SESSION['user_id'], $_SESSION['user_id']]);
        if ($allowed->fetchColumn()) {
            $_SESSION['active_company_id'] = $wanted;
            unset($_SESSION['company_snapshot']);
        }
    } catch (Throwable $e) { /* companies not migrated yet — ignore the switch */ }
    $redirect = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: $redirect");
    exit;
}

// Resolution and access rules live in helpers/company.php so every page agrees.
try {
    $activeCompany = activeCompany();
    if ($activeCompany) {
        $_SESSION['company_name']          = $activeCompany['company_name'];
        $_SESSION['company_legal_name']    = $activeCompany['legal_name'] ?: $activeCompany['company_name'];
        $_SESSION['company_tax_number']    = $activeCompany['vat_number'] ?: $activeCompany['tax_number'];
        $_SESSION['company_currency']      = $activeCompany['base_currency'];
        $_SESSION['company_logo']          = $activeCompany['logo_path'];
        $_SESSION['company_invoice_prefix']= $activeCompany['invoice_prefix'];
        $_SESSION['company_address'] = trim(implode(', ', array_filter([
            $activeCompany['address_line1'], $activeCompany['address_line2'],
            $activeCompany['city'], $activeCompany['state'],
            $activeCompany['postal_code'], $activeCompany['country'],
        ])));
        $COMPANY = $activeCompany;
    }
} catch (Throwable $e) {
    // The companies table may predate the Company Master migration.
}

$pageTitle = $pageTitle ?? 'Dashboard';
$currentTheme = $_SESSION['theme'] ?? 'ibm-light';
$bodyClasses = ['bg-gray-50'];
if (!empty($bodyClass)) {
    $bodyClasses[] = trim((string) $bodyClass);
}
if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/modules/ai/') !== false) {
    $bodyClasses[] = 'ai-module-page';
}
$bodyClassAttr = trim(implode(' ', array_values(array_unique(array_filter($bodyClasses)))));

// Dashboard pages set $dashboardKey (main/executive/helpdesk/…) BEFORE
// requiring this file. When the user has a saved custom widget layout for
// it, #dashboard-old-content must start hidden — otherwise the legacy
// static dashboard paints first and only gets hidden once dashboard-core.js
// finishes loading the layout (and, previously, all widget data), which is
// the "content flashes then disappears" bug. Deciding this server-side, in
// the same response, means the browser never paints the old content at all.
$dashboardHasCustomLayout = false;
if (!empty($dashboardKey)) {
    require_once $basePath . '/config/widgets.php';
    $layoutCheck = getUserDashboardLayout($_SESSION['user_id'], $dashboardKey);
    $dashboardHasCustomLayout = $layoutCheck && $layoutCheck['is_customized'];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $currentTheme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= htmlspecialchars(appName()) ?></title>
    <script src="<?= rtrim(BASE_URL, '/') ?>/assets/js/theme.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@carbon/charts@1.27.18/dist/umd/bundle.umd.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@carbon/charts@1.27.18/dist/styles.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/assets/css/light-m365.css">
    <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/assets/css/ibm-carbon.css">
    <?php if ($dashboardHasCustomLayout): ?>
    <style>#dashboard-old-content{display:none}</style>
    <?php endif; ?>
</head>
<body class="<?= htmlspecialchars($bodyClassAttr) ?>" data-theme="<?= $currentTheme ?>">
    <?php if (function_exists('ai_render_shell_styles') && strpos($_SERVER['SCRIPT_NAME'] ?? '', '/modules/ai/') !== false): ?>
        <?php ai_render_shell_styles(); ?>
    <?php endif; ?>
    <?php require_once $basePath . '/templates/navbar.php'; ?>
    <div class="erp-layout">
        <?php require_once $basePath . '/templates/sidebar.php'; ?>
        <main class="erp-main">
        <?php if (isset($_SESSION['flash'])): ?>
            <?php foreach ($_SESSION['flash'] as $key => $flash): ?>
                <?php 
                $colors = ['success' => 'bg-green-100 border-green-400 text-green-700', 'error' => 'bg-red-100 border-red-400 text-red-700', 'info' => 'bg-blue-100 border-blue-400 text-blue-700', 'warning' => 'bg-yellow-100 border-yellow-400 text-yellow-700'];
                ?>
                <div class="<?= $colors[$flash['type']] ?? 'bg-gray-100' ?> px-4 py-3 rounded mb-4 border">
                    <?= sanitize($flash['message']) ?>
                </div>
                <?php unset($_SESSION['flash'][$key]); ?>
            <?php endforeach; ?>
        <?php endif; ?>
        <!-- Breadcrumb Navigation -->
        <div class="bg-white border-b px-6 py-3 mb-6">
            <?= renderBreadcrumbs() ?>
        </div>
