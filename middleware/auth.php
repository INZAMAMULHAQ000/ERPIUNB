<?php

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        $base = (defined('BASE_URL') ? BASE_URL : '');
        header('Location: ' . $base . '/modules/auth/login.php');
        exit;
    }
}