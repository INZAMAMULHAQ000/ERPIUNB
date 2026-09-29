<?php

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/url.php';

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function back() {
    $referer = $_SERVER['HTTP_REFERER'] ?? BASE_URL;
    redirect($referer);
}

function to($url) {
    redirect(url($url));
}

function redirect_to($url) {
    to($url);
}