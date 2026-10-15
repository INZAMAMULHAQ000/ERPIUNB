<?php
session_start();

require_once '../../config/database.php';
require_once '../../helpers/security.php';
require_once '../../helpers/url.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter email and password';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, name, email, password, status FROM users WHERE email = ? AND deleted_at IS NULL");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = 'Your account is ' . $user['status'];
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['login_time'] = time();

                // Restore this user's saved IBM Light/Dark preference.
                try {
                    $t = $db->prepare("SELECT theme FROM users WHERE id = ?");
                    $t->execute([$user['id']]);
                    $_SESSION['theme'] = $t->fetchColumn() ?: 'ibm-light';
                } catch (Throwable $e) { /* users.theme not migrated yet */ }

                logAudit($user['id'], 'login', 'auth');
                header('Location: ' . rtrim(BASE_URL, '/') . '/index.php');
                exit;
            }
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in to your account</title>
    <script src="<?= rtrim(BASE_URL, '/') ?>/assets/js/theme.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* -- ERP System sign-in ------------------------------------- */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'IBM Plex Sans', 'Segoe UI', -apple-system, BlinkMacSystemFont,
                Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #F4F4F4;
            color: #161616;
            font-weight: 500;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            -webkit-font-smoothing: antialiased;
        }

        .ms-shell {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border: 1px solid #E0E0E0;
            border-radius: 0;
            box-shadow: none;
            padding: 40px 40px 28px;
        }

        /* ERP System logo (pure CSS - SVG) */
        .ms-logo {
            width: 44px; height: 44px;
            margin-bottom: 20px;
            background: #0F62FE;
            border-radius: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27white%27 stroke-width=%272.5%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3E%3Crect x=%273%27 y=%273%27 width=%277%27 height=%277%27 rx=%271.5%27/%3E%3Crect x=%2714%27 y=%273%27 width=%277%27 height=%277%27 rx=%271.5%27/%3E%3Crect x=%273%27 y=%2714%27 width=%277%27 height=%277%27 rx=%271.5%27/%3E%3Crect x=%2714%27 y=%2714%27 width=%277%27 height=%277%27 rx=%271.5%27/%3E%3Cpath d=%27M6.5 10h11M10 6.5v11%27/%3E%3C/svg%3E");
            background-size: 24px 24px;
            background-repeat: no-repeat;
            background-position: center;
        }

        .ms-h1 { font-size: 1.5rem; font-weight: 600; color: #161616; line-height: 1.2; letter-spacing: -0.01em; }
        .ms-sub { font-size: 0.875rem; color: #525252; margin-top: 8px; line-height: 1.4; }

        /* Error → Fluent MessageBar (danger) */
        .ms-err {
            display: flex; align-items: flex-start; gap: 10px;
            margin-top: 24px;
            padding: 10px 12px;
            background: #FFF1F1;
            border-left: 4px solid #DA1E28;
            color: #DA1E28;
            font-size: 0.8125rem;
            border-radius: 0;
        }
        .ms-err i { margin-top: 2px; }

        .ms-form { margin-top: 24px; }

        /* Fluent text field */
        .ms-field { margin-bottom: 20px; }
        .ms-field-label { display: block; font-size: 0.875rem; color: #525252; margin-bottom: 4px; }
        .ms-field-wrap { position: relative; }
        .ms-field-wrap i {
            position: absolute; left: 10px; top: 50%;
            transform: translateY(-50%);
            color: #525252; font-size: 0.9375rem;
            pointer-events: none;
        }
        .ms-field-input {
            width: 100%;
            height: 40px;
            padding: 0 12px 0 34px;
            font-family: inherit;
            font-size: 0.9375rem;
            color: #161616;
            background: #F4F4F4;
            border: none;
            border-bottom: 1px solid #8D8D8D;
            border-radius: 0;
            outline: none;
            transition: background 0.1s linear;
        }
        .ms-field-input::placeholder { color: #6F6F6F; opacity: 1; }
        .ms-field-input:hover { background: #E8E8E8; }
        .ms-field-input:focus {
            background: #E8E8E8;
            outline: 2px solid #0F62FE;
            outline-offset: 0;
            box-shadow: none;
            border-bottom-color: transparent;
        }

        /* Button row: hint link left, primary button right */
        .ms-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .ms-link {
            font-size: 0.8125rem;
            color: #0F62FE;
            background: none;
            border: none;
            font-family: inherit;
            cursor: pointer;
            padding: 4px 0;
            text-decoration: none;
        }
        .ms-link:hover { text-decoration: underline; color: #0043CE; }

        .ms-btn {
            height: 40px;
            padding: 0 20px;
            font-family: inherit;
            font-size: 0.9375rem;
            font-weight: 500;
            color: #ffffff;
            background: #0F62FE;
            border: none;
            border-radius: 0;
            cursor: pointer;
            transition: background 0.1s linear;
        }
        .ms-btn:hover { background: #0043CE; }
        .ms-btn:active { background: #002D9C; }
        .ms-btn:focus-visible { outline: 2px solid #0F62FE; outline-offset: 2px; }

        .ms-demo {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #E0E0E0;
            font-size: 0.75rem;
            color: #6F6F6F;
        }
        .ms-demo code { font-family: 'IBM Plex Mono', 'Cascadia Code', Consolas, monospace; color: #525252; }

        .ms-footer {
            margin-top: 32px;
            font-size: 0.75rem;
            color: #6F6F6F;
        }
        .ms-footer a { color: #525252; text-decoration: none; }
        .ms-footer a:hover { text-decoration: underline; color: #161616; }

        @media (max-width: 480px) {
            .ms-shell { padding: 28px 24px 20px; }
            .ms-logo { margin-bottom: 16px; }
            .ms-h1 { font-size: 1.375rem; }
        }

        /* -- IBM Dark (Carbon g100) sign-in ------------------------- */
        body[data-theme="ibm-dark"] { background: #161616; color: #f4f4f4; }
        body[data-theme="ibm-dark"] .ms-shell { background: #262626; border-color: #393939; }
        body[data-theme="ibm-dark"] .ms-h1 { color: #f4f4f4; }
        body[data-theme="ibm-dark"] .ms-sub { color: #c6c6c6; }
        body[data-theme="ibm-dark"] .ms-sub strong { color: #f4f4f4; }
        body[data-theme="ibm-dark"] .ms-err {
            background: #2a1a1e;
            border-left-color: #fa4d56;
            color: #ff8389;
        }
        body[data-theme="ibm-dark"] .ms-field-label { color: #a8a8a8; }
        body[data-theme="ibm-dark"] .ms-field-wrap i { color: #8d8d8d; }
        body[data-theme="ibm-dark"] .ms-field-input {
            color: #f4f4f4;
            background: #393939;
            border-bottom-color: #6f6f6f;
        }
        body[data-theme="ibm-dark"] .ms-field-input::placeholder { color: #8d8d8d; }
        body[data-theme="ibm-dark"] .ms-field-input:hover,
        body[data-theme="ibm-dark"] .ms-field-input:focus { background: #525252; }
        body[data-theme="ibm-dark"] .ms-field-input:focus { outline-color: #4589ff; }
        body[data-theme="ibm-dark"] .ms-link { color: #78a9ff; }
        body[data-theme="ibm-dark"] .ms-link:hover { color: #a6c8ff; }
        body[data-theme="ibm-dark"] .ms-btn { background: #4589ff; color: #161616; }
        body[data-theme="ibm-dark"] .ms-btn:hover { background: #78a9ff; }
        body[data-theme="ibm-dark"] .ms-btn:active { background: #a6c8ff; }
        body[data-theme="ibm-dark"] .ms-btn:focus-visible { outline-color: #4589ff; }
        body[data-theme="ibm-dark"] .ms-demo { border-top-color: #393939; color: #8d8d8d; }
        body[data-theme="ibm-dark"] .ms-demo code { color: #c6c6c6; }
        body[data-theme="ibm-dark"] .ms-footer { color: #8d8d8d; }
        body[data-theme="ibm-dark"] .ms-footer a { color: #a8a8a8; }
        body[data-theme="ibm-dark"] .ms-footer a:hover { color: #f4f4f4; }
        /* Smooth morph when toggling on this screen too */
        html.theme-switching body,
        html.theme-switching .ms-shell,
        html.theme-switching .ms-field-input {
            transition: background 0.18s ease, color 0.18s ease, border-color 0.18s ease !important;
        }
    </style>
</head>
<body data-theme="<?= htmlspecialchars($_SESSION['theme'] ?? 'ibm-light') ?>">
    <div class="ms-shell">
        <!-- ERP System logo -->
        <div class="ms-logo" role="img" aria-label="ERP System"></div>

        <h1 class="ms-h1">Sign in</h1>
        <p class="ms-sub">to continue to <strong>ERP System</strong></p>

        <?php
        // middleware/auth.php leaves this behind when a session outlived the
        // account using it, so the redirect explains itself instead of looking
        // like a broken login form.
        $notice = getFlash('expired');
        ?>
        <?php if (!empty($notice['message'])): ?>
        <div class="ms-err" role="alert" style="background:#FFF4E5;border-left-color:#B26A00;color:#8A5300">
            <i class="fas fa-triangle-exclamation"></i>
            <span><?= htmlspecialchars($notice['message']) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="ms-err" role="alert">
            <i class="fas fa-circle-exclamation"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="" class="ms-form" novalidate>
            <div class="ms-field">
                <label class="ms-field-label" for="email">Email, phone, or Skype</label>
                <div class="ms-field-wrap">
                    <i class="fas fa-user" aria-hidden="true"></i>
                    <input type="email" name="email" id="email" class="ms-field-input" required
                        placeholder="you@erp.com" autocomplete="username" autofocus>
                </div>
            </div>

            <div class="ms-field">
                <label class="ms-field-label" for="password">Password</label>
                <div class="ms-field-wrap">
                    <i class="fas fa-lock" aria-hidden="true"></i>
                    <input type="password" name="password" id="password" class="ms-field-input" required
                        placeholder="Password" autocomplete="current-password">
                </div>
            </div>

            <div class="ms-row">
                <button type="button" class="ms-link" onclick="alert('Contact your administrator to reset your password.')">Forgot password?</button>
                <button type="submit" class="ms-btn">Sign in</button>
            </div>
        </form>

        <p class="ms-demo">
            <i class="fas fa-circle-info" style="margin-right:4px;"></i>
            Demo: <code>admin@erp.com</code> / <code>admin123</code>
        </p>

        <p class="ms-footer">
            <a href="#" onclick="return false;">Terms of use</a>
            &nbsp;·&nbsp;
            <a href="#" onclick="return false;">Privacy &amp; cookies</a>
        </p>
    </div>
</body>
</html>



