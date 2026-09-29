<?php
/**
 * AI Tools.
 *
 * Manage ERP capabilities exposed to AI.
 */
$pageTitle = 'AI Tools';
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('ai.tools.view')) {
    die('Access Denied');
}
?>
<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">AI Tools</h1>
        <p class="text-gray-600">Manage ERP capabilities exposed to AI</p>
    </div>
</div>

<?php ai_render_module_nav('ai.tools'); ?>

<div class="bg-white rounded-lg shadow p-8 text-center">
    <div class="bg-orange-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-screwdriver-wrench text-orange-600 text-2xl"></i>
    </div>
    <h2 class="text-xl font-semibold text-gray-800 mb-2">AI Tools</h2>
    <p class="text-gray-600 mb-6 max-w-md mx-auto">
        Configure and manage ERP tools and capabilities that AI can access and utilize.
    </p>
    <div class="bg-gray-50 rounded-lg p-6 max-w-lg mx-auto">
        <p class="text-sm text-gray-500">
            <i class="fas fa-info-circle mr-1"></i>
            Coming Soon — Tool management will be available in a future update.
        </p>
    </div>
</div>

<?php require_once AI_APP_ROOT . '/templates/footer.php'; ?>
