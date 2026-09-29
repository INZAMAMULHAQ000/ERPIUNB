<?php

/**
 * Build an absolute URL for this app.
 *
 * Accepts either:
 * - a relative path like "modules/assets/index.php"
 * - an absolute URL (returned as-is, except localhost dev URLs are normalized)
 */
function normalizeAppUrl($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return BASE_URL;
    }

    // Normalize old hardcoded dev links after deployment.
    $legacyPrefixes = [
        'http://localhost/ERP',
        'https://localhost/ERP',
    ];
    foreach ($legacyPrefixes as $prefix) {
        if (stripos($value, $prefix) === 0) {
            return rtrim(BASE_URL, '/') . substr($value, strlen($prefix));
        }
    }

    return $value;
}

function isAbsoluteUrl($value) {
    return preg_match('~^https?://~i', (string)$value) === 1;
}

function url($path = '') {
    $path = trim((string)$path);
    if ($path === '') {
        return rtrim(BASE_URL, '/');
    }

    if (isAbsoluteUrl($path)) {
        return normalizeAppUrl($path);
    }

    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function resolveAppUrl($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (isAbsoluteUrl($value)) {
        return normalizeAppUrl($value);
    }

    return url($value);
}

