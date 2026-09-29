<?php
/**
 * Template Designer — Template Sanitizer
 * Security layer that sanitizes template HTML content to prevent XSS and injection.
 */

/**
 * Sanitize template data JSON before saving.
 * Strips dangerous HTML, validates structure, and normalizes data.
 *
 * @param array $data Template data array (decoded from JSON)
 * @return array Sanitized template data
 */
function sanitizeTemplateData(array $data): array {
    $safe = [];

    // Page settings
    $safe['page'] = [
        'size' => in_array($data['page']['size'] ?? '', ['A4', 'Letter', 'Legal']) ? $data['page']['size'] : 'A4',
        'orientation' => in_array($data['page']['orientation'] ?? '', ['portrait', 'landscape']) ? $data['page']['orientation'] : 'portrait',
        'margins' => [
            'top' => min(100, max(0, (int)($data['page']['margins']['top'] ?? 20))),
            'right' => min(100, max(0, (int)($data['page']['margins']['right'] ?? 15))),
            'bottom' => min(100, max(0, (int)($data['page']['margins']['bottom'] ?? 20))),
            'left' => min(100, max(0, (int)($data['page']['margins']['left'] ?? 15))),
        ],
    ];

    // Editor mode (visual vs classic)
    $safe['editor_mode'] = in_array($data['editor_mode'] ?? '', ['visual', 'classic']) ? $data['editor_mode'] : 'classic';

    // Header
    $safe['header'] = [
        'enabled' => !empty($data['header']['enabled']),
        'content' => sanitizeComponentArray($data['header']['content'] ?? []),
        'different_first_page' => !empty($data['header']['different_first_page']),
    ];
    if (isset($data['header']['height'])) {
        $safe['header']['height'] = min(300, max(20, (int)$data['header']['height']));
    }

    // Footer
    $safe['footer'] = [
        'enabled' => !empty($data['footer']['enabled']),
        'content' => sanitizeComponentArray($data['footer']['content'] ?? []),
        'different_first_page' => !empty($data['footer']['different_first_page']),
    ];
    if (isset($data['footer']['height'])) {
        $safe['footer']['height'] = min(300, max(20, (int)$data['footer']['height']));
    }

    // Body sections
    $safe['body']['sections'] = [];
    foreach ($data['body']['sections'] ?? [] as $section) {
        $safe['body']['sections'][] = [
            'type' => in_array($section['type'] ?? '', ['header', 'body', 'footer', 'section']) ? $section['type'] : 'section',
            'label' => substr(strip_tags($section['label'] ?? ''), 0, 200),
            'components' => sanitizeComponentArray($section['components'] ?? []),
        ];
    }

    // Styles
    $safe['styles'] = [
        'font_family' => strip_tags($data['styles']['font_family'] ?? 'DejaVu Sans'),
        'font_size' => min(24, max(8, (int)($data['styles']['font_size'] ?? 11))),
        'colors' => [
            'primary' => sanitizeColor($data['styles']['colors']['primary'] ?? '#4f46e5'),
            'text' => sanitizeColor($data['styles']['colors']['text'] ?? '#1e293b'),
            'background' => sanitizeColor($data['styles']['colors']['background'] ?? '#ffffff'),
        ],
        'table_styles' => [
            'header_bg' => sanitizeColor($data['styles']['table_styles']['header_bg'] ?? '#f3f4f6'),
            'border_color' => sanitizeColor($data['styles']['table_styles']['border_color'] ?? '#e5e7eb'),
            'alternate_rows' => !empty($data['styles']['table_styles']['alternate_rows']),
        ],
    ];

    return $safe;
}

/**
 * Sanitize an array of components.
 */
function sanitizeComponentArray(array $components): array {
    return array_map(function($comp) {
        $safe = [
            'type' => in_array($comp['type'] ?? '', ['text', 'field', 'table', 'image', 'divider', 'qrcode', 'barcode', 'signature', 'term', 'html']) ? $comp['type'] : 'text',
            'label' => substr(strip_tags($comp['label'] ?? ''), 0, 255),
            'content' => strip_tags($comp['content'] ?? '', '<b><i><u><strong><em><br><p><span>'),
            'placeholder' => sanitizePlaceholder($comp['placeholder'] ?? ''),
            'default' => strip_tags($comp['default'] ?? ''),
            'hide_if_empty' => !empty($comp['hide_if_empty']),
            'image' => filter_var($comp['image'] ?? $comp['src'] ?? '', FILTER_SANITIZE_URL),
            'src' => null,
            'width' => min(500, max(20, (int)($comp['width'] ?? 100))),
            'align' => in_array($comp['align'] ?? '', ['left', 'center', 'right', 'justify']) ? $comp['align'] : 'left',
            'columns' => sanitizeColumns($comp['columns'] ?? []),
            'data_key' => preg_replace('/[^a-zA-Z0-9_-]/', '', $comp['data_key'] ?? 'table_rows'),
            'data' => sanitizePlaceholder($comp['data'] ?? ''),
            'size' => min(300, max(20, (int)($comp['size'] ?? 80))),
            'format' => in_array($comp['format'] ?? '', ['code128', 'ean13', 'code39']) ? $comp['format'] : 'code128',
            'show_label' => $comp['show_label'] ?? true,
            'style' => in_array($comp['style'] ?? '', ['solid', 'dashed', 'dotted']) ? $comp['style'] : 'solid',
            'color' => sanitizeColor($comp['color'] ?? '#e5e7eb'),
            'html' => sanitizeCustomHtml($comp['html'] ?? ''),
        ];

        // Preserve position data for visual editor
        if (isset($comp['position'])) {
            $safe['position'] = [
                'x' => max(0, (int)($comp['position']['x'] ?? 0)),
                'y' => max(0, (int)($comp['position']['y'] ?? 0)),
                'w' => max(10, (int)($comp['position']['w'] ?? 100)),
                'h' => max(10, (int)($comp['position']['h'] ?? 30)),
            ];
        }

        // Preserve data_bind
        if (isset($comp['data_bind'])) {
            $safe['data_bind'] = preg_replace('/[^a-zA-Z0-9_-]/', '', $comp['data_bind']);
        }

        // Preserve component-level styles (fontSize, bold, align, color)
        if (isset($comp['styles']) && is_array($comp['styles'])) {
            $safe['styles'] = [];
            if (isset($comp['styles']['fontSize'])) {
                $safe['styles']['fontSize'] = min(72, max(8, (int)$comp['styles']['fontSize']));
            }
            if (isset($comp['styles']['bold'])) {
                $safe['styles']['bold'] = !empty($comp['styles']['bold']);
            }
            if (isset($comp['styles']['align'])) {
                $safe['styles']['align'] = in_array($comp['styles']['align'], ['left', 'center', 'right', 'justify']) ? $comp['styles']['align'] : 'left';
            }
            if (isset($comp['styles']['color'])) {
                $safe['styles']['color'] = sanitizeColor($comp['styles']['color']);
            }
        }

        return $safe;
    }, $components);
}

/**
 * Sanitize a hex color value.
 */
function sanitizeColor(string $color): string {
    if (preg_match('/^#[a-fA-F0-9]{6}$/', $color)) {
        return $color;
    }
    return '#4f46e5';
}

/**
 * Allow only safe placeholder syntax.
 */
function sanitizePlaceholder(string $text): string {
    // Allow {{word}} patterns and safe text
    return preg_replace('/[^a-zA-Z0-9_{}\s\-.:\/]/', '', $text);
}

/**
 * Sanitize custom HTML — allow only safe HTML tags.
 */
function sanitizeCustomHtml(string $html): string {
    $allowed = '<p><br><b><i><u><strong><em><h1><h2><h3><h4><h5><h6>'
             . '<table><tr><td><th><thead><tbody><div><span>'
             . '<ul><ol><li><a><img><hr><br><pre><code><blockquote>';
    // Also strip onmouseover, onclick, etc.
    $html = preg_replace('/\son\w+\s*=\s*"[^"]*"/i', '', $html);
    $html = preg_replace("/\son\w+\s*=\s*'[^']*'/i", '', $html);
    return strip_tags($html, $allowed);
}

/**
 * Validate a file upload for template assets (logo, images).
 */
function validateAssetUpload(array $file): ?string {
    $allowedMimes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'image/svg+xml', 'application/pdf',
    ];
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'Upload failed with error code: ' . $file['error'];
    }
    if ($file['size'] > $maxSize) {
        return 'File too large. Maximum size: 5MB.';
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) {
        return 'Invalid file type. Allowed: ' . implode(', ', $allowedExts);
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowedMimes)) {
        return 'Invalid MIME type: ' . $mime;
    }

    return null; // no error
}

/**
 * Sanitize a filename for safe storage.
 */
function sanitizeFilename(string $name): string {
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    return substr($name, 0, 200);
}