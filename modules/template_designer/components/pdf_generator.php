<?php
/**
 * Template Designer — PDF Generator
 * Dompdf wrapper for converting template HTML to PDF.
 *
 * Follows the existing pattern from modules/leave_management/report_pdf.php
 * but extended to support any template via the Template Designer system.
 */

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generate a PDF from rendered HTML.
 *
 * @param string $html The full HTML document (output from html_renderer)
 * @param array $options Override options for Dompdf
 * @return Dompdf The Dompdf instance (call ->stream() or ->output())
 */
function generatePdf(string $html, array $options = []): Dompdf {
    $defaultOptions = [
        'isHtml5ParserEnabled' => true,
        'isRemoteEnabled' => false,
        'isPhpEnabled' => false,
        'isJavascriptEnabled' => false,
        'defaultPaperSize' => $options['page_size'] ?? 'A4',
        'defaultPaperOrientation' => $options['orientation'] ?? 'portrait',
        'dpi' => 96,
        'fontCache' => sys_get_temp_dir(),
    ];

    $dompdfOptions = new Options();
    foreach ($defaultOptions as $key => $val) {
        $setter = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        if (method_exists($dompdfOptions, $setter)) {
            $dompdfOptions->$setter($val);
        }
    }

    // Apply custom options
    foreach ($options as $key => $val) {
        if (in_array($key, ['page_size', 'orientation'])) continue; // handled separately
        $setter = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        if (method_exists($dompdfOptions, $setter)) {
            $dompdfOptions->$setter($val);
        }
    }

    $dompdf = new Dompdf($dompdfOptions);
    $dompdf->loadHtml($html, 'UTF-8');

    $paperSize = $options['paper_size'] ?? $options['page_size'] ?? 'A4';
    $paperOrientation = $options['orientation'] ?? 'portrait';
    $dompdf->setPaper($paperSize, $paperOrientation);

    $dompdf->render();

    return $dompdf;
}

/**
 * Stream PDF to browser for download.
 *
 * @param string $html The full HTML document
 * @param string $filename Desired filename (without .pdf)
 * @param array $options Dompdf options
 */
function streamPdf(string $html, string $filename, array $options = []): void {
    $dompdf = generatePdf($html, $options);
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename) . '.pdf';
    $dompdf->stream($filename, ['Attachment' => !($options['inline'] ?? false)]);
    exit;
}

/**
 * Output PDF as a string (for saving to file or embedding).
 */
function outputPdf(string $html, array $options = []): string {
    $dompdf = generatePdf($html, $options);
    return $dompdf->output();
}

/**
 * Render a template and stream it as PDF in one call.
 *
 * @param int $templateId Template ID
 * @param array $data Placeholder data
 * @param string $templateType Type for placeholder resolution
 */
function renderTemplateToPdf(int $templateId, array $data = [], string $templateType = 'custom'): void {
    require_once dirname(__DIR__) . '/components/html_renderer.php';

    $basePath = dirname(__DIR__, 3);
    require_once $basePath . '/config/database.php';

    $db = getDB();

    // Load template
    $stmt = $db->prepare("SELECT * FROM templates WHERE id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch();

    if (!$template) {
        throw new Exception("Template #$templateId not found");
    }

    // Load latest published version
    $stmt = $db->prepare("SELECT * FROM template_versions WHERE template_id = ? AND status = 'published' ORDER BY version_number DESC LIMIT 1");
    $stmt->execute([$templateId]);
    $version = $stmt->fetch();

    if (!$version) {
        throw new Exception("Template #$templateId has no published version");
    }

    $templateData = json_decode($version['template_data'], true);
    if (!$templateData) {
        throw new Exception("Template #$templateId has invalid template data");
    }

    $html = renderTemplateHtml($templateData, $data, $template['template_type']);

    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $template['template_name']) . '_' . date('Ymd');

    streamPdf($html, $filename, [
        'page_size' => $template['page_size'] ?? 'A4',
        'orientation' => $template['page_orientation'] ?? 'portrait',
    ]);
}