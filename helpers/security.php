<?php

/**
 * Auto-detect the application's base URL from the server environment.
 *
 * Uses SCRIPT_NAME + filesystem position to compute the app root URL.
 * This is bulletproof across localhost, cPanel, hPanel subdomain hosting,
 * and VPS setups where DOCUMENT_ROOT may not match the real filesystem path.
 */
function detectBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // The URL path of the currently executing script, e.g. /ERP/modules/users/index.php
    // or /index.php when the app is at the document root.
    $scriptUrl  = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    // The filesystem path of the currently executing script.
    $scriptFile = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');

    // The filesystem path of the app root (one level above /helpers/).
    $appRoot = str_replace('\\', '/', dirname(__DIR__));

    // How many directory levels does the script sit below the app root?
    // e.g. script at <appRoot>/modules/users/index.php  → 'modules/users/index.php'
    //      substr_count of slashes in that string       → 2
    $relativeScript = ltrim(str_replace($appRoot, '', $scriptFile), '/');
    $depth = substr_count($relativeScript, '/');

    // Split the script URL into segments: /ERP/modules/users/index.php
    //   → ['', 'ERP', 'modules', 'users', 'index.php']
    $urlParts = explode('/', rtrim($scriptUrl, '/'));

    // We need to drop ($depth + 1) segments from the right:
    //   +1 for the filename itself, +$depth for each sub-directory level.
    // This leaves only the app-root path segments.
    $keep = count($urlParts) - 1 - $depth;
    $appUrlParts = ($keep > 0) ? array_slice($urlParts, 0, $keep) : [''];
    $relativePath = rtrim(implode('/', $appUrlParts), '/');

    return $protocol . '://' . $host . $relativePath;
}

define('BASE_URL', detectBaseUrl());

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

function escape($data) {
    return sanitize($data);
}

function getClientIP() {
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    return $ip;
}

function logAudit($userId, $action, $module) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, module, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $module, getClientIP()]);
}

function csrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateRequired($data, $fields) {
    foreach ($fields as $field) {
        if (empty($data[$field] ?? '')) {
            return false;
        }
    }
    return true;
}

function flash($key, $message, $type = 'info') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'][$key] = ['message' => $message, 'type' => $type];
}

function getFlash($key) {
    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }
    $flash = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $flash;
}

function hasFlash($key) {
    return isset($_SESSION['flash'][$key]);
}