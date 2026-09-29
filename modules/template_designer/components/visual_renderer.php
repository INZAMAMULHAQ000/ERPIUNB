<?php
/**
 * Visual Renderer — Absolute-Positioned HTML for PDF Output
 *
 * Renders template_data into HTML with absolute-positioned elements
 * suitable for Dompdf conversion. Used when template_data has
 * position data (editor_mode = 'visual').
 *
 * Relies on placeholder_registry.php for default values.
 */
require_once dirname(__DIR__, 3) . '/helpers/placeholder_registry.php';

/**
 * Render template data with absolute positioning into an HTML string.
 *
 * @param array $templateData The full template definition JSON array
 * @param array $data         Key-value data to fill placeholders
 * @param string $templateType Template type for placeholder defaults
 * @return string Full HTML document
 */
function renderVisualTemplateHtml(array $templateData, array $data, string $templateType): string {
    $page = $templateData['page'] ?? [];
    $styles = $templateData['styles'] ?? [];
    $colors = $styles['colors'] ?? [];
    $tableStyles = $styles['table_styles'] ?? [];

    $fontFamily = $styles['font_family'] ?? 'DejaVu Sans';
    $fontSize   = $styles['font_size'] ?? 11;

    // Page dimensions (mm for Dompdf)
    $pageW = 210; // A4 width in mm
    $pageH = 297; // A4 height in mm

    // Load default placeholders and merge with provided data
    $placeholders = getPlaceholdersForType($templateType);
    $resolved = resolveVisualPlaceholders($data, $placeholders);

    ob_start();
    ?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<style>
    @page {
        size: <?= $page['size'] ?? 'A4' ?> <?= $page['orientation'] ?? 'portrait' ?>;
        margin: <?= ($page['margins']['top'] ?? 20) . 'mm ' . ($page['margins']['right'] ?? 15) . 'mm ' . ($page['margins']['bottom'] ?? 20) . 'mm ' . ($page['margins']['left'] ?? 15) . 'mm' ?>;
    }
    body {
        font-family: '<?= $fontFamily ?>', sans-serif;
        font-size: <?= $fontSize ?>pt;
        color: <?= $colors['text'] ?? '#1e293b' ?>;
        line-height: 1.5;
        position: relative;
        width: 100%;
        height: 100%;
    }
    .td-element {
        position: absolute;
        overflow: hidden;
        box-sizing: border-box;
    }
    .td-element.text { padding: 2px 4px; }
    .td-element.table { overflow: visible; }

    /* Table styles */
    .td-table {
        width: 100%;
        border-collapse: collapse;
        font-size: <?= max(8, $fontSize - 2) ?>pt;
    }
    .td-table th {
        background: <?= $tableStyles['header_bg'] ?? '#4f46e5' ?>;
        color: #ffffff;
        padding: 6px 8px;
        text-align: left;
        font-weight: 600;
    }
    .td-table td {
        padding: 4px 8px;
        border-bottom: 1px solid <?= $tableStyles['border_color'] ?? '#e5e7eb' ?>;
    }
    <?php if (!empty($tableStyles['alternate_rows'])): ?>
    .td-table tr:nth-child(even) td {
        background: #f8fafc;
    }
    <?php endif; ?>

    .td-divider { border: none; }
    .td-signature-area { border-top: 1px solid #9ca3af; padding-top: 4px; font-size: 9pt; color: #6b7280; }
    .td-qr-placeholder, .td-barcode-placeholder {
        display: flex; align-items: center; justify-content: center;
        background: #f9fafb; border: 1px dashed #d1d5db;
        height: 100%; color: #9ca3af; font-size: 9pt;
    }
    img.td-image { max-width: 100%; max-height: 100%; object-fit: contain; }
</style>
</head>
<body>
    <?php
    // Header zone
    if (!empty($templateData['header']['enabled']) && !empty($templateData['header']['content'])) {
        foreach ($templateData['header']['content'] as $comp) {
            echo renderVisualComponent($comp, $resolved);
        }
    }

    // Body sections
    if (!empty($templateData['body']['sections'])) {
        foreach ($templateData['body']['sections'] as $section) {
            if (!empty($section['components'])) {
                foreach ($section['components'] as $comp) {
                    echo renderVisualComponent($comp, $resolved);
                }
            }
        }
    }

    // Footer zone
    if (!empty($templateData['footer']['enabled']) && !empty($templateData['footer']['content'])) {
        foreach ($templateData['footer']['content'] as $comp) {
            echo renderVisualComponent($comp, $resolved);
        }
    }
    ?>
</body>
</html>
    <?php
    return ob_get_clean();
}

/**
 * Render a single visual component with absolute positioning CSS.
 */
function renderVisualComponent(array $comp, array $data): string {
    $pos = $comp['position'] ?? null;

    // If no position data, fall back to inline block (classic mode)
    if (!$pos) {
        return '<!-- No position data -->';
    }

    // Convert coordinates from pixels to approximate mm for Dompdf
    // (96dpi → 1px ≈ 0.2646mm)
    $scale = 0.2646;
    $x = round(($pos['x'] ?? 0) * $scale, 1);
    $y = round(($pos['y'] ?? 0) * $scale, 1);
    $w = round(($pos['w'] ?? 200) * $scale, 1);
    $h = round(($pos['h'] ?? 40) * $scale, 1);

    $style = sprintf('left:%.1fmm; top:%.1fmm; width:%.1fmm; height:%.1fmm;', $x, $y, $w, $h);
    $type = $comp['type'] ?? 'text';

    $content = '';
    switch ($type) {
        case 'text':
            $align = ($comp['styles']['align'] ?? 'left');
            $fs = ($comp['styles']['fontSize'] ?? 14);
            $fw = !empty($comp['styles']['bold']) ? 'bold' : 'normal';
            $color = ($comp['styles']['color'] ?? '#1e293b');
            $text = sanitizeVisualHtml($comp['content'] ?? '');
            $text = replaceVisualPlaceholders($text, $data);
            $content = sprintf(
                '<div class="td-element text" style="%s text-align:%s; font-size:%.1fpt; font-weight:%s; color:%s;">%s</div>',
                $style, $align, $fs, $fw, $color, nl2br($text)
            );
            break;

        case 'field':
            $placeholder = $comp['placeholder'] ?? '';
            $default = $comp['default'] ?? '';
            $value = replaceVisualPlaceholders($placeholder, $data);
            if ($value === $placeholder || trim(strip_tags($value)) === '') {
                if (!empty($comp['hide_if_empty'])) {
                    return ''; // Hide entire element
                }
                $value = $default ?: $placeholder;
            }
            $content = sprintf(
                '<div class="td-element" style="%s">%s</div>',
                $style, sanitizeVisualHtml($value)
            );
            break;

        case 'image':
            $src = $comp['src'] ?? $comp['image'] ?? '{{logo_url}}';
            $src = replaceVisualPlaceholders($src, $data);
            if ($src === '{{logo_url}}' || empty($src)) {
                $content = sprintf(
                    '<div class="td-element" style="%s display:flex; align-items:center; font-weight:bold; color:%s;"><h1>%s</h1></div>',
                    $style, '#4f46e5', sanitizeVisualHtml($data['company_name'] ?? 'Company')
                );
            } else {
                $align = $comp['align'] ?? 'left';
                $content = sprintf(
                    '<div class="td-element" style="%s text-align:%s;"><img class="td-image" src="%s" alt="logo"></div>',
                    $style, $align, sanitizeVisualHtml($src)
                );
            }
            break;

        case 'table':
            $dataKey = $comp['data_key'] ?? 'report_data';
            $rows = $data[$dataKey] ?? [];
            if (empty($rows)) {
                return sprintf('<div class="td-element" style="%s color:#9ca3af; font-style:italic;">No data available</div>', $style);
            }
            $columns = $comp['columns'] ?? [];
            // Auto-detect columns from data if none defined
            if (empty($columns) && !empty($rows[0])) {
                $columns = [];
                foreach (array_keys($rows[0]) as $key) {
                    $columns[] = ['key' => $key, 'label' => ucwords(str_replace('_', ' ', $key)), 'width' => 20];
                }
            }
            $th = '';
            foreach ($columns as $col) {
                $th .= '<th>' . sanitizeVisualHtml($col['label'] ?? $col['key'] ?? '') . '</th>';
            }
            $tb = '';
            foreach ($rows as $row) {
                $tb .= '<tr>';
                foreach ($columns as $col) {
                    $key = $col['key'] ?? '';
                    $tb .= '<td>' . sanitizeVisualHtml($row[$key] ?? '-') . '</td>';
                }
                $tb .= '</tr>';
            }
            $content = sprintf(
                '<div class="td-element table" style="%s overflow:visible;"><table class="td-table"><thead><tr>%s</tr></thead><tbody>%s</tbody></table></div>',
                $style, $th, $tb
            );
            break;

        case 'divider':
            $border = 'solid';
            if (($comp['style'] ?? 'solid') === 'dashed') $border = 'dashed';
            elseif (($comp['style'] ?? 'solid') === 'dotted') $border = 'dotted';
            $color = $comp['color'] ?? '#e5e7eb';
            $content = sprintf(
                '<hr class="td-divider td-element" style="%s border-top:1px %s %s;">',
                $style, $border, $color
            );
            break;

        case 'qrcode':
            $content = sprintf(
                '<div class="td-element" style="%s"><div class="td-qr-placeholder"><span>[QR: %s]</span></div></div>',
                $style, sanitizeVisualHtml($comp['data'] ?? 'data')
            );
            break;

        case 'barcode':
            $content = sprintf(
                '<div class="td-element" style="%s"><div class="td-barcode-placeholder"><span>[Barcode: %s]</span></div></div>',
                $style, sanitizeVisualHtml($comp['format'] ?? 'code128')
            );
            break;

        case 'signature':
            $label = (!isset($comp['show_label']) || $comp['show_label']) ? ($comp['label'] ?? 'Signature:') : '';
            $content = sprintf(
                '<div class="td-element" style="%s"><div class="td-signature-area">%s</div></div>',
                $style, sanitizeVisualHtml($label)
            );
            break;

        case 'term':
            $text = sanitizeVisualHtml($comp['content'] ?? '');
            $text = replaceVisualPlaceholders($text, $data);
            $content = sprintf(
                '<div class="td-element" style="%s font-size:9pt; color:#6b7280;">%s</div>',
                $style, nl2br($text)
            );
            break;

        case 'html':
            $html = $comp['html'] ?? '';
            $html = replaceVisualPlaceholders($html, $data);
            $allowed = '<p><br><b><i><u><strong><em><h1><h2><h3><h4><h5><h6><ul><ol><li><span><div><table><tr><td><th><thead><tbody>';
            $content = sprintf(
                '<div class="td-element" style="%s">%s</div>',
                $style, strip_tags($html, $allowed)
            );
            break;

        default:
            $content = sprintf(
                '<div class="td-element" style="%s color:#9ca3af;">[%s]</div>',
                $style, sanitizeVisualHtml($type)
            );
    }

    return $content;
}

/**
 * Merge data with placeholder defaults, giving data precedence.
 */
function resolveVisualPlaceholders(array $data, array $placeholders): array {
    $resolved = $data;
    foreach ($placeholders as $key => $config) {
        if (!isset($resolved[$key]) && isset($config['default'])) {
            $resolved[$key] = $config['default'];
        }
    }
    return $resolved;
}

/**
 * Replace {{key}} patterns with data values.
 */
function replaceVisualPlaceholders(string $text, array $data): string {
    return preg_replace_callback('/\{\{(\w+)\}\}/', function($m) use ($data) {
        $key = $m[1];
        return $data[$key] ?? $m[0];
    }, $text);
}

/**
 * Sanitize output for HTML display.
 */
function sanitizeVisualHtml(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
