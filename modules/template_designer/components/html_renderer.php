<?php
/**
 * Template Designer — HTML Renderer
 * Converts template JSON data into a full HTML document ready for PDF generation.
 * Reuses the existing placeholder_registry for placeholder resolution.
 */

require_once dirname(__DIR__, 3) . '/helpers/placeholder_registry.php';

/**
 * Render a full HTML document from template data and data array.
 *
 * @param array $templateData The JSON-decoded template definition
 * @param array $data Key-value pairs for placeholder replacement
 * @param string $templateType Used to load module-specific sample data
 * @return string Complete HTML document
 */
function renderTemplateHtml(array $templateData, array $data = [], string $templateType = 'custom'): string {
    // Merge defaults and resolve placeholders
    $placeholders = getPlaceholdersForType($templateType);
    $resolved = resolvePlaceholders($data, $placeholders);

    // Extract page settings
    $page = $templateData['page'] ?? [];
    $pageSize = strtolower($page['size'] ?? 'a4');
    $orientation = $page['orientation'] ?? 'portrait';
    $margins = $page['margins'] ?? ['top' => 20, 'right' => 15, 'bottom' => 20, 'left' => 15];

    // Extract styles
    $styles = $templateData['styles'] ?? [];
    $fontFamily = $styles['font_family'] ?? 'DejaVu Sans';
    $fontSize = $styles['font_size'] ?? 11;
    $colors = $styles['colors'] ?? [];
    $primaryColor = $colors['primary'] ?? '#4f46e5';
    $textColor = $colors['text'] ?? '#1e293b';
    $bgColor = $colors['background'] ?? '#ffffff';
    $tableStyles = $styles['table_styles'] ?? [];
    $tableHeaderBg = $tableStyles['header_bg'] ?? '#f3f4f6';
    $tableBorderColor = $tableStyles['border_color'] ?? '#e5e7eb';
    $alternateRows = $tableStyles['alternate_rows'] ?? true;

    // Detect if position-based rendering is needed
    $usePosition = ($templateData['editor_mode'] ?? 'classic') === 'visual' && hasPositionData($templateData);

    // Start building HTML
    $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
    $html .= '<style>
        @page { margin: ' . ($margins['top'] ?? 20) . 'px ' . ($margins['right'] ?? 15) . 'px ' . ($margins['bottom'] ?? 20) . 'px ' . ($margins['left'] ?? 15) . 'px; }
        body { font-family: ' . $fontFamily . ', sans-serif; font-size: ' . $fontSize . 'px; color: ' . $textColor . '; background: ' . $bgColor . '; line-height: 1.5; margin: 0; padding: 0; }
        h1 { font-size: ' . ($fontSize + 8) . 'px; color: ' . $primaryColor . '; margin: 0 0 8px 0; }
        h2 { font-size: ' . ($fontSize + 4) . 'px; color: ' . $primaryColor . '; margin: 0 0 6px 0; }
        h3 { font-size: ' . ($fontSize + 2) . 'px; color: ' . $textColor . '; margin: 0 0 4px 0; }
        p { margin: 0 0 8px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th { background: ' . $tableHeaderBg . '; text-align: left; padding: 6px 8px; font-size: ' . ($fontSize - 1) . 'px; font-weight: 600; border: 1px solid ' . $tableBorderColor . '; }
        td { padding: 6px 8px; font-size: ' . ($fontSize - 1) . 'px; border: 1px solid ' . $tableBorderColor . '; }
        ' . ($alternateRows ? 'tr:nth-child(even) td { background: #f9fafb; }' : '') . '
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-justify { text-align: justify; }
        .header-section { margin-bottom: 20px; }
        .footer-section { margin-top: 20px; font-size: ' . ($fontSize - 2) . 'px; color: #94a3b8; }
        .page-break { page-break-before: always; }
        .watermark { position: fixed; opacity: 0.1; font-size: 60px; transform: rotate(-45deg); top: 40%; left: 20%; z-index: 999; }
        .signature-area { border-top: 1px solid ' . $tableBorderColor . '; width: 200px; margin-top: 10px; padding-top: 4px; font-size: ' . ($fontSize - 2) . 'px; color: #94a3b8; }
        .divider { border: 0; height: 1px; margin: 12px 0; }
        .text-block { margin-bottom: 8px; }
        .component-wrapper { margin-bottom: 12px; }
        .logo-img { max-height: 80px; }
        /* Position-based rendering */
        .td-positioned-container { position: relative; width: 100%; height: 100%; min-height: 500px; }
        .td-element { position: absolute; overflow: hidden; box-sizing: border-box; }
        .td-divider { border: none; }
        .td-signature-area { border-top: 1px solid ' . $tableBorderColor . '; padding-top: 4px; }
    </style></head><body>' . "\n";

    if ($usePosition) {
        $html .= renderPositionedContent($templateData, $resolved, $fontSize, $primaryColor, $tableHeaderBg, $tableBorderColor);
    } else {
        // ============================================================
        // HEADER SECTION (classic mode)
        // ============================================================
        $header = $templateData['header'] ?? [];
        if (!empty($header['enabled']) && !empty($header['content'])) {
            $html .= '<div class="header-section">';
            foreach ($header['content'] as $comp) {
                $html .= renderComponent($comp, $resolved, $fontSize, $primaryColor);
            }
            $html .= '</div>';
        }

        // ============================================================
        // BODY SECTIONS (classic mode)
        // ============================================================
        $sections = $templateData['body']['sections'] ?? [];
        foreach ($sections as $section) {
            $html .= renderComponent($section, $resolved, $fontSize, $primaryColor, $tableHeaderBg, $tableBorderColor);
        }

        // ============================================================
        // FOOTER SECTION (classic mode)
        // ============================================================
        $footer = $templateData['footer'] ?? [];
        if (!empty($footer['enabled']) && !empty($footer['content'])) {
            $html .= '<div class="footer-section">';
            foreach ($footer['content'] as $comp) {
                $html .= renderComponent($comp, $resolved, $fontSize, $primaryColor);
            }
            $html .= '</div>';
        }
    }

    $html .= '</body></html>';
    return $html;
}

/**
 * Render a single component from template data into HTML.
 */
function renderComponent(array $comp, array $data, int $fontSize, string $primaryColor, string $tableHeaderBg = '#f3f4f6', string $tableBorderColor = '#e5e7eb'): string {
    $html = '';
    $type = $comp['type'] ?? 'text';

    switch ($type) {
        case 'text':
            $content = replacePlaceholders($comp['content'] ?? '', $data);
            $align = $comp['align'] ?? 'left';
            $html .= '<div class="text-block text-' . $align . '">' . nl2br(sanitizeHtml($content)) . '</div>';
            break;

        case 'field':
            $placeholder = $comp['placeholder'] ?? '';
            $resolved = replacePlaceholders($placeholder, $data);
            $default = $comp['default'] ?? '';
            $value = ($resolved !== $placeholder) ? $resolved : $default;
            $hideEmpty = !empty($comp['hide_if_empty']);
            if ($hideEmpty && empty($value)) break;
            $label = $comp['label'] ?? '';
            $html .= '<div class="component-wrapper">';
            if ($label) {
                $html .= '<span style="font-weight:600;margin-right:4px;">' . sanitizeHtml($label) . '</span>';
            }
            $html .= '<span>' . sanitizeHtml($value) . '</span></div>';
            break;

        case 'image':
            $src = replacePlaceholders($comp['image'] ?? $comp['src'] ?? '', $data);
            if ($src && $src !== '{{logo_url}}') {
                $width = $comp['width'] ?? 100;
                $align = $comp['align'] ?? 'left';
                $html .= '<div class="component-wrapper text-' . $align . '">';
                $html .= '<img src="' . sanitizeHtml($src) . '" width="' . (int)$width . '" class="header-img" alt="' . sanitizeHtml($comp['label'] ?? 'Image') . '" />';
                $html .= '</div>';
            } else {
                // Logo placeholder — show company name as fallback
                $html .= '<div class="component-wrapper text-' . ($comp['align'] ?? 'left') . '">';
                $html .= '<h1>' . sanitizeHtml($data['company_name'] ?? 'Company Name') . '</h1></div>';
            }
            break;

        case 'table':
            $columns = $comp['columns'] ?? [];
            if (empty($columns)) break;
            $rows = $data[$comp['data_key'] ?? 'table_rows'] ?? [];
            $html .= '<div class="component-wrapper"><table>';
            $html .= '<thead><tr>';
            foreach ($columns as $col) {
                $w = ($col['width'] ?? '') ? ' style="width:' . (int)$col['width'] . '%"' : '';
                $html .= '<th' . $w . '>' . sanitizeHtml($col['label'] ?? '') . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            if (!empty($rows) && is_array($rows)) {
                foreach ($rows as $row) {
                    $html .= '<tr>';
                    foreach ($columns as $col) {
                        $key = $col['key'] ?? '';
                        $align = $col['align'] ?? 'left';
                        $value = replacePlaceholders($row[$key] ?? '', $data);
                        $html .= '<td class="text-' . $align . '">' . sanitizeHtml($value) . '</td>';
                    }
                    $html .= '</tr>';
                }
            } else {
                $html .= '<tr><td colspan="' . count($columns) . '" style="text-align:center;color:#94a3b8;padding:16px;">No data</td></tr>';
            }
            $html .= '</tbody></table></div>';
            break;

        case 'divider':
            $style = $comp['style'] ?? 'solid';
            $color = $comp['color'] ?? '#e5e7eb';
            $html .= '<hr class="divider" style="border-top-style:' . $style . '; border-top-color:' . $color . ';" />';
            break;

        case 'qrcode':
            $qrData = replacePlaceholders($comp['data'] ?? '{{qrcode_data}}', $data);
            $size = $comp['size'] ?? 80;
            // For PDF rendering, QR codes are represented as placeholder text.
            // In production, use a QR code generation library or API.
            $html .= '<div class="component-wrapper text-center">';
            $html .= '<div style="display:inline-block;width:' . (int)$size . 'px;height:' . (int)$size . 'px;background:#fff;border:1px solid #e5e7eb;text-align:center;line-height:' . (int)$size . 'px;font-size:9px;color:#94a3b8;">';
            $html .= 'QR: ' . sanitizeHtml($qrData);
            $html .= '</div></div>';
            break;

        case 'barcode':
            $bcData = replacePlaceholders($comp['data'] ?? '{{barcode_data}}', $data);
            $html .= '<div class="component-wrapper text-center">';
            $html .= '<div style="font-family:monospace;font-size:10px;letter-spacing:2px;padding:8px;background:#fff;border:1px solid #e5e7eb;display:inline-block;">';
            $html .= sanitizeHtml($bcData);
            $html .= '</div></div>';
            break;

        case 'signature':
            $showLabel = $comp['show_label'] !== false;
            $html .= '<div class="component-wrapper">';
            if ($showLabel && !empty($comp['label'])) {
                $html .= '<div style="font-size:' . ($fontSize - 1) . 'px;margin-bottom:4px;">' . sanitizeHtml($comp['label']) . '</div>';
            }
            $html .= '<div class="signature-area"></div></div>';
            break;

        case 'term':
            $content = replacePlaceholders($comp['content'] ?? '', $data);
            $html .= '<div class="component-wrapper"><div style="font-size:' . ($fontSize - 1) . 'px;color:#64748b;padding:8px;border:1px solid #e5e7eb;border-radius:4px;">' . nl2br(sanitizeHtml($content)) . '</div></div>';
            break;

        case 'html':
            $raw = $comp['html'] ?? '';
            // Sanitize — allow safe tags only
            $safe = strip_tags($raw, '<p><br><b><i><u><strong><em><h1><h2><h3><h4><h5><h6><table><tr><td><th><thead><tbody><div><span><ul><ol><li><a><img><hr><br>');
            $html .= '<div class="component-wrapper">' . $safe . '</div>';
            break;

        default:
            // If it's a section wrapper (used if sections contain components array)
            if (!empty($comp['components'])) {
                foreach ($comp['components'] as $child) {
                    $html .= renderComponent($child, $data, $fontSize, $primaryColor, $tableHeaderBg, $tableBorderColor);
                }
            }
            break;
    }

    return $html;
}

/**
 * Replace {{placeholders}} in a string with data values.
 * Unknown placeholders are left as-is.
 */
function replacePlaceholders(string $text, array $data): string {
    return preg_replace_callback('/\{\{(\w+)\}\}/', function($matches) use ($data) {
        return $data[$matches[1]] ?? $matches[0];
    }, $text);
}

/**
 * Resolve all placeholders from data + defaults
 */
function resolvePlaceholders(array $data, array $placeholders): array {
    $result = [];
    foreach ($placeholders as $key => $def) {
        $result[$key] = $data[$key] ?? $def['default'] ?? '';
    }
    // Override with actual data
    foreach ($data as $key => $value) {
        $result[$key] = $value;
    }
    return $result;
}

/**
 * Lightweight HTML sanitization — escape HTML special chars
 * but preserve basic formatting.
 */
function sanitizeHtml(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Check if any component in the template has position data.
 */
function hasPositionData(array $templateData): bool {
    $check = function($content) use (&$check): bool {
        if (!is_array($content)) return false;
        foreach ($content as $comp) {
            if (isset($comp['position']) && is_array($comp['position'])) {
                return true;
            }
            if (!empty($comp['components'])) {
                if ($check($comp['components'])) return true;
            }
        }
        return false;
    };

    if (!empty($templateData['header']['content']) && $check($templateData['header']['content'])) return true;
    if (!empty($templateData['footer']['content']) && $check($templateData['footer']['content'])) return true;
    if (!empty($templateData['body']['sections'])) {
        foreach ($templateData['body']['sections'] as $section) {
            if (!empty($section['components']) && $check($section['components'])) return true;
        }
    }
    return false;
}

/**
 * Render content with absolute positioning (visual editor mode).
 * Converts pixel coordinates from the editor to mm for Dompdf.
 */
function renderPositionedContent(array $templateData, array $data, int $fontSize, string $primaryColor, string $tableHeaderBg, string $tableBorderColor): string {
    // Pixel-to-mm conversion factor (~0.2646 at 96dpi)
    $toMm = 0.2646;

    $html = '<div class="td-positioned-container">' . "\n";

    // Helper to render a component with absolute positioning
    $renderPos = function($comp) use ($data, $fontSize, $primaryColor, $tableHeaderBg, $tableBorderColor, $toMm) {
        $pos = $comp['position'] ?? null;
        if (!$pos) return '';

        $x = round(($pos['x'] ?? 0) * $toMm, 1);
        $y = round(($pos['y'] ?? 0) * $toMm, 1);
        $w = round(($pos['w'] ?? 200) * $toMm, 1);
        $h = round(($pos['h'] ?? 40) * $toMm, 1);
        $style = sprintf('left:%.1fmm; top:%.1fmm; width:%.1fmm; height:%.1fmm;', $x, $y, $w, $h);
        $type = $comp['type'] ?? 'text';

        switch ($type) {
            case 'text':
                $align = $comp['styles']['align'] ?? $comp['align'] ?? 'left';
                $fs = $comp['styles']['fontSize'] ?? $fontSize;
                $fw = !empty($comp['styles']['bold']) ? 'bold' : 'normal';
                $color = $comp['styles']['color'] ?? '#1e293b';
                $text = replacePlaceholders($comp['content'] ?? '', $data);
                return sprintf(
                    '<div class="td-element" style="%s text-align:%s; font-size:%dpx; font-weight:%s; color:%s; padding:2px 4px;">%s</div>',
                    $style, $align, $fs, $fw, $color, nl2br(sanitizeHtml($text))
                );

            case 'field':
                $placeholder = $comp['placeholder'] ?? '';
                $resolved = replacePlaceholders($placeholder, $data);
                $default = $comp['default'] ?? '';
                $value = ($resolved !== $placeholder) ? $resolved : $default;
                if (!empty($comp['hide_if_empty']) && empty($value)) return '';
                $label = !empty($comp['label']) ? sanitizeHtml($comp['label']) . ': ' : '';
                return sprintf(
                    '<div class="td-element" style="%s">%s%s</div>',
                    $style, $label, sanitizeHtml($value)
                );

            case 'image':
                $src = replacePlaceholders($comp['image'] ?? $comp['src'] ?? '', $data);
                if ($src && $src !== '{{logo_url}}') {
                    return sprintf(
                        '<div class="td-element" style="%s text-align:%s;"><img src="%s" style="max-width:100%%;max-height:100%%;object-fit:contain;"></div>',
                        $style, $comp['align'] ?? 'left', sanitizeHtml($src)
                    );
                }
                return sprintf(
                    '<div class="td-element" style="%s display:flex;align-items:center;font-weight:bold;"><h1>%s</h1></div>',
                    $style, sanitizeHtml($data['company_name'] ?? 'Company')
                );

            case 'table':
                $columns = $comp['columns'] ?? [];
                if (empty($columns)) return '';
                $rows = $data[$comp['data_key'] ?? 'report_data'] ?? [];
                if (empty($rows)) {
                    return sprintf('<div class="td-element" style="%s color:#94a3b8;">No data</div>', $style);
                }
                $tableHtml = '<table>';
                $tableHtml .= '<thead><tr>';
                foreach ($columns as $col) {
                    $w = ($col['width'] ?? '') ? ' style="width:' . (int)$col['width'] . '%"' : '';
                    $tableHtml .= '<th' . $w . '>' . sanitizeHtml($col['label'] ?? '') . '</th>';
                }
                $tableHtml .= '</tr></thead><tbody>';
                foreach ($rows as $row) {
                    $tableHtml .= '<tr>';
                    foreach ($columns as $col) {
                        $key = $col['key'] ?? '';
                        $tableHtml .= '<td class="text-' . ($col['align'] ?? 'left') . '">' . sanitizeHtml($row[$key] ?? '-') . '</td>';
                    }
                    $tableHtml .= '</tr>';
                }
                $tableHtml .= '</tbody></table>';
                return sprintf(
                    '<div class="td-element" style="%s overflow:visible; font-size:%dpx;">%s</div>',
                    $style, max(8, $fontSize - 2), $tableHtml
                );

            case 'divider':
                $borderStyle = $comp['style'] ?? 'solid';
                $color = $comp['color'] ?? '#e5e7eb';
                return sprintf(
                    '<hr class="td-element td-divider" style="%s border-top:1px %s %s;">',
                    $style, $borderStyle, $color
                );

            case 'qrcode':
                return sprintf(
                    '<div class="td-element" style="%s display:flex;align-items:center;justify-content:center;background:#f9fafb;border:1px dashed #d1d5db;color:#94a3b8;font-size:9px;">[QR]</div>',
                    $style
                );

            case 'barcode':
                return sprintf(
                    '<div class="td-element" style="%s display:flex;align-items:center;justify-content:center;font-family:monospace;background:#fff;border:1px solid #e5e7eb;">[Barcode]</div>',
                    $style
                );

            case 'signature':
                $label = (!isset($comp['show_label']) || $comp['show_label']) ? sanitizeHtml($comp['label'] ?? 'Signature:') : '';
                return sprintf(
                    '<div class="td-element" style="%s"><div class="td-signature-area">%s</div></div>',
                    $style, $label
                );

            case 'term':
                $text = replacePlaceholders($comp['content'] ?? '', $data);
                return sprintf(
                    '<div class="td-element" style="%s font-size:%dpx; color:#64748b;">%s</div>',
                    $style, max(9, $fontSize - 2), nl2br(sanitizeHtml($text))
                );

            case 'html':
                $raw = $comp['html'] ?? '';
                $safe = strip_tags($raw, '<p><br><b><i><u><strong><em><h1><h2><h3><h4><h5><h6><table><tr><td><th><div><span><ul><ol><li><a>');
                return sprintf(
                    '<div class="td-element" style="%s">%s</div>',
                    $style, $safe
                );

            default:
                return sprintf('<div class="td-element" style="%s color:#94a3b8;">[%s]</div>', $style, sanitizeHtml($type));
        }
    };

    // Header
    if (!empty($templateData['header']['enabled']) && !empty($templateData['header']['content'])) {
        foreach ($templateData['header']['content'] as $comp) {
            $html .= $renderPos($comp);
        }
    }

    // Body
    if (!empty($templateData['body']['sections'])) {
        foreach ($templateData['body']['sections'] as $section) {
            if (!empty($section['components'])) {
                foreach ($section['components'] as $comp) {
                    $html .= $renderPos($comp);
                }
            }
        }
    }

    // Footer
    if (!empty($templateData['footer']['enabled']) && !empty($templateData['footer']['content'])) {
        foreach ($templateData['footer']['content'] as $comp) {
            $html .= $renderPos($comp);
        }
    }

    $html .= '</div>' . "\n";
    return $html;
}