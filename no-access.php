<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /XXX/modules/auth/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Access Denied</title></head>
<body>
<div style="max-width:400px;margin:100px auto;text-align:center;">
    <h1>Access Denied</h1>
    <p>You do not have permission to access this page.</p>
    <a href="/XXX/modules/auth/login.php">Back to Login</a>
</div>
</body>
</html>