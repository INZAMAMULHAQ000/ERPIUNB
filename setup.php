<?php
/**
 * Dashboard System Setup
 *
 * Run this file ONCE from your browser to set up the dashboard
 * customization system. It creates the required database tables
 * and seeds all widget definitions.
 *
 * Visit: http://localhost/ERP/setup.php
 */

// Prevent production execution
$isLocal = (php_sapi_name() === 'cli')
        || in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1'])
        || in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']);

if (!$isLocal) {
    die('<h1 style="color:red;text-align:center;margin-top:4rem">This setup script can only run on localhost.</h1>');
}

// ── Bootstrap ────────────────────────────────────────────────────
require_once __DIR__ . '/config/database.php';

$db = getDB();
$output = [];
$hasError = false;

// ── Helper ───────────────────────────────────────────────────────
function runSqlFile($db, $path, $label) {
    global $output;
    if (!file_exists($path)) {
        $output[] = ['type' => 'error', 'msg' => "File not found: $path"];
        return false;
    }

    $sql = file_get_contents($path);
    if (!$sql) {
        $output[] = ['type' => 'warning', 'msg' => "$label: empty file"];
        return false;
    }

    // Remove USE statements (DB is already selected)
    $sql = preg_replace('/^USE\s+\w+;/mi', '', $sql);

    // Remove single-line comments (-- ...)
    $sql = preg_replace('/^--.*$/m', '', $sql);

    // Remove multi-line comments (/* ... */)
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    // Split by semicolons
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => !empty($s)
    );

    $count = 0;
    $errors = [];
    foreach ($statements as $stmt) {
        try {
            $db->exec($stmt);
            $count++;
        } catch (PDOException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if ($count > 0) {
        $output[] = ['type' => 'success', 'msg' => "$label: $count statement(s) executed"];
    }
    if (!empty($errors)) {
        $output[] = ['type' => 'warning', 'msg' => "$label: " . implode('; ', array_slice($errors, 0, 3))];
    }

    return empty($errors);
}

// ══════════════════════════════════════════════════════════════════
// STEP 1: Check if tables already exist
// ══════════════════════════════════════════════════════════════════
$output[] = ['type' => 'info', 'msg' => 'Starting dashboard system setup...'];

try {
    $db->query("SELECT 1 FROM widget_definitions LIMIT 1");
    $output[] = ['type' => 'info', 'msg' => 'widget_definitions table exists (will be updated if needed)'];
} catch (PDOException $e) {
    $output[] = ['type' => 'info', 'msg' => 'widget_definitions table does not exist — will create'];
}

try {
    $db->query("SELECT 1 FROM user_dashboard_layouts LIMIT 1");
    $output[] = ['type' => 'info', 'msg' => 'user_dashboard_layouts table exists'];
} catch (PDOException $e) {
    $output[] = ['type' => 'info', 'msg' => 'user_dashboard_layouts table does not exist — will create'];
}

// ══════════════════════════════════════════════════════════════════
// STEP 2: Run migration SQL (table creation + permissions)
// ══════════════════════════════════════════════════════════════════
$output[] = ['type' => 'info', 'msg' => '--- Running schema migration ---'];
runSqlFile($db, __DIR__ . '/config/migrations/001_dashboard_customization.sql', 'Schema Migration');

// ══════════════════════════════════════════════════════════════════
// STEP 3: Seed widget definitions
// ══════════════════════════════════════════════════════════════════
$output[] = ['type' => 'info', 'msg' => '--- Seeding widget definitions ---'];
runSqlFile($db, __DIR__ . '/config/migrations/001_widget_seed.sql', 'Widget Seed');

// ══════════════════════════════════════════════════════════════════
// STEP 3b: Run template designer migration (tables + permissions)
// ══════════════════════════════════════════════════════════════════
$output[] = ['type' => 'info', 'msg' => '--- Running template designer migration ---'];
runSqlFile($db, __DIR__ . '/config/migrations/002_template_designer.sql', 'Template Designer Migration');

// ══════════════════════════════════════════════════════════════════
// STEP 4: Verify installation
// ══════════════════════════════════════════════════════════════════
$widgetCount = $db->query("SELECT COUNT(*) FROM widget_definitions")->fetchColumn();
$permCount = $db->query("SELECT COUNT(*) FROM permissions WHERE module IN ('dashboard','widgets')")->fetchColumn();

$output[] = ['type' => 'info', 'msg' => "--- Verification ---"];
$output[] = ['type' => ($widgetCount > 0 ? 'success' : 'error'), 'msg' => "Widget definitions: $widgetCount"];
$output[] = ['type' => ($permCount > 0 ? 'success' : 'warning'), 'msg' => "Dashboard permissions found: $permCount"];

$hasError = ($widgetCount == 0);

// ══════════════════════════════════════════════════════════════════
// Render Results
// ══════════════════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-2xl">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <!-- Header -->
            <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm0 8a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1v-2zm0 8a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1v-2z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-slate-800">Dashboard System Setup</h1>
                    <p class="text-sm text-slate-500">Database migration &amp; widget seed</p>
                </div>
            </div>

            <!-- Status badges -->
            <div class="px-6 py-4 space-y-0.5">
                <?php if ($hasError): ?>
                    <div class="flex items-center gap-2 text-sm text-red-600 bg-red-50 px-4 py-3 rounded-lg border border-red-100">
                        <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                        <span>Some errors occurred — check details below.</span>
                    </div>
                <?php else: ?>
                    <div class="flex items-center gap-2 text-sm text-green-700 bg-green-50 px-4 py-3 rounded-lg border border-green-100">
                        <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        <span>Setup completed successfully!</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Log -->
            <div class="px-6 pb-4">
                <div class="bg-slate-50 rounded-xl border border-slate-200 divide-y divide-slate-100 text-sm max-h-80 overflow-y-auto">
                    <?php foreach ($output as $entry): ?>
                        <?php
                        $styles = [
                            'success' => 'text-green-700',
                            'error'   => 'text-red-600',
                            'warning' => 'text-amber-700',
                            'info'    => 'text-slate-600',
                        ];
                        $icons = [
                            'success' => '✓',
                            'error'   => '✗',
                            'warning' => '⚠',
                            'info'    => '→',
                        ];
                        $style = $styles[$entry['type']] ?? 'text-slate-600';
                        $icon  = $icons[$entry['type']] ?? '→';
                        ?>
                        <div class="px-4 py-2.5 flex items-start gap-2.5 <?= $style ?>">
                            <span class="font-mono flex-shrink-0"><?= $icon ?></span>
                            <span><?= htmlspecialchars($entry['msg']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Actions -->
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-wrap gap-3">
                <a href="index.php"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Go to Dashboard
                </a>
                <a href="modules/widgets/index.php"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-white text-slate-700 text-sm font-medium rounded-lg border border-slate-300 hover:bg-slate-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                    </svg>
                    Manage Widgets
                </a>
                <a href="setup.php"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-white text-slate-700 text-sm font-medium rounded-lg border border-slate-300 hover:bg-slate-50 transition ml-auto"
                   onclick="return confirm('Re-run setup? This is safe to run multiple times.')">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Re-run
                </a>
            </div>
        </div>
    </div>
</body>
</html>
