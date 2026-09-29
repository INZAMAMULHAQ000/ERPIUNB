<?php
/**
 * Template Designer — Preview
 * Shows a live HTML preview of the template with sample data,
 * or generates a PDF preview.
 */
session_start();
$basePath = dirname(__DIR__, 2);

$id = (int)($_GET['id'] ?? 0);
$format = $_GET['format'] ?? 'html'; // 'html' or 'pdf'

require_once $basePath . '/config/database.php';
require_once $basePath . '/helpers/security.php';
require_once $basePath . '/helpers/rbac.php';
require_once $basePath . '/helpers/redirect.php';
require_once $basePath . '/middleware/auth.php';
require_once $basePath . '/helpers/placeholder_registry.php';

requireAuth();

if (!hasPermission('template_designer.preview')) {
    die('Access Denied');
}

$db = getDB();

$stmt = $db->prepare("SELECT t.*, tc.name AS category_name FROM templates t LEFT JOIN template_categories tc ON t.category_id = tc.id WHERE t.id = ?");
$stmt->execute([$id]);
$template = $stmt->fetch();

if (!$template) {
    die('Template not found.');
}

$stmt = $db->prepare("SELECT * FROM template_versions WHERE template_id = ? ORDER BY version_number DESC LIMIT 1");
$stmt->execute([$id]);
$version = $stmt->fetch();

if (!$version) {
    die('No version data found for this template.');
}

$templateData = json_decode($version['template_data'], true);

if (!$templateData) {
    die('Invalid template data.');
}

require_once dirname(__DIR__) . '/template_designer/components/html_renderer.php';

// Get sample data for this template type
$sampleData = getSampleDataForType($template['template_type']);

// Add some sample table rows for templates that have tables
$sampleData['table_rows'] = [
    ['col1' => 'Sample Item 1', 'col2' => 'Description A', 'col3' => '2', 'col4' => '$100.00'],
    ['col1' => 'Sample Item 2', 'col2' => 'Description B', 'col3' => '1', 'col4' => '$250.00'],
    ['col1' => 'Sample Item 3', 'col2' => 'Description C', 'col3' => '3', 'col4' => '$75.00'],
];

// For invoices specifically
$sampleData['qty'] = '2';
$sampleData['unit_price'] = '$100.00';
$sampleData['total'] = '$200.00';

$html = renderTemplateHtml($templateData, $sampleData, $template['template_type']);

if ($format === 'pdf') {
    require_once dirname(__DIR__) . '/template_designer/components/pdf_generator.php';

    $filename = 'preview_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $template['template_name']) . '_' . date('Ymd');

    streamPdf($html, $filename, [
        'page_size' => $template['page_size'] ?? 'A4',
        'orientation' => $template['page_orientation'] ?? 'portrait',
        'inline' => true, // Display in browser, don't force download
    ]);
    exit;
}

// HTML preview mode — embed in the ERP layout
$pageTitle = 'Preview: ' . $template['template_name'];

require_once $basePath . '/includes/header.php';
?>

<div class="max-w-5xl mx-auto">
    <!-- Preview Toolbar -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold text-slate-800">Preview: <?= sanitize($template['template_name']) ?></h2>
                <p class="text-xs text-slate-400">
                    v<?= (int)$template['version'] ?> &middot;
                    <?= ucwords(str_replace('_', ' ', $template['template_type'])) ?> &middot;
                    <?= $template['page_size'] ?? 'A4' ?> / <?= ucfirst($template['page_orientation'] ?? 'portrait') ?>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="preview.php?id=<?= $id ?>&format=pdf" target="_blank"
                   class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium shadow-sm">
                    <i class="fas fa-file-pdf mr-2"></i> Download PDF
                </a>
                <a href="edit.php?id=<?= $id ?>" class="px-4 py-2 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 text-sm">
                    <i class="fas fa-edit mr-1"></i> Edit
                </a>
                <button onclick="window.print()" class="px-4 py-2 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 text-sm">
                    <i class="fas fa-print mr-1"></i> Print
                </button>
            </div>
        </div>
    </div>

    <!-- Sample Data Info -->
    <details class="bg-white rounded-xl shadow-sm border border-slate-200 mb-4">
        <summary class="px-4 py-3 cursor-pointer text-sm font-medium text-slate-600 hover:text-slate-800">
            <i class="fas fa-database mr-2"></i> Sample Data Used for Preview
        </summary>
        <div class="px-4 pb-3">
            <pre class="text-xs text-slate-500 bg-slate-50 p-3 rounded-lg overflow-x-auto"><?php
                // Show only non-empty sample data
                $displayData = array_filter($sampleData, function($v) { return is_scalar($v); });
                echo sanitize(json_encode($displayData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            ?></pre>
        </div>
    </div>

    <!-- Rendered Preview -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-4 py-2 bg-slate-50 border-b border-slate-200 text-xs text-slate-400 flex items-center gap-2">
            <i class="fas fa-desktop"></i>
            <span>Rendered Preview — <span class="font-medium">This is how the template will appear in the generated document.</span></span>
        </div>
        <div class="p-8" style="background:#f1f5f9;">
            <div class="mx-auto" style="max-width: 210mm; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 20px;">
                <?= $html ?>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .erp-navbar, .erp-layout > aside, .erp-main > *:not(.max-w-5xl) { display: none !important; }
    .max-w-5xl > :not(:last-child) { display: none !important; }
    .max-w-5xl > :last-child .p-8 { padding: 0 !important; background: white !important; }
    .max-w-5xl > :last-child .mx-auto { box-shadow: none !important; max-width: 100% !important; }
    @page { margin: 0; }
}
</style>

<?php require_once $basePath . '/includes/footer.php'; ?>