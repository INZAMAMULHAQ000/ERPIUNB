<?php
session_start();

require_once '../../config/database.php';
require_once '../../helpers/security.php';
require_once '../../helpers/url.php';

if (isset($_SESSION['user_id'])) {
    logAudit($_SESSION['user_id'], 'logout', 'auth');
}

session_destroy();
header('Location: ' . rtrim(BASE_URL, '/') . '/modules/auth/login.php');
exit;