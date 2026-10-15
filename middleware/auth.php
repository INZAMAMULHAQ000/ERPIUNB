<?php

/**
 * Authentication guard.
 *
 * A session id is not proof of access. Until now `$_SESSION['user_id']` being
 * set was enough, so an account that was deleted, suspended or renamed kept a
 * fully working session until the browser happened to restart — and a deleted
 * user kept writing audit rows under an id that no longer existed. The session
 * is now re-checked against the users table once per request.
 */
function requireAuth() {
    $base = (defined('BASE_URL') ? BASE_URL : '');

    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . $base . '/modules/auth/login.php');
        exit;
    }

    static $verified = false;
    if ($verified) {
        return;
    }

    $stmt = getDB()->prepare("SELECT status, deleted_at FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['deleted_at'] !== null || $user['status'] !== 'active') {
        // Keep only a notice, drop every trace of the identity, and hand the
        // browser a fresh session id so nothing stale survives the redirect.
        $_SESSION = [
            'flash' => [
                'expired' => [
                    'message' => $user
                        ? 'Your account is no longer active. Contact an administrator to restore access.'
                        : 'Your account no longer exists. Please sign in again.',
                    'type' => 'warning',
                ],
            ],
        ];
        session_regenerate_id(true);
        header('Location: ' . $base . '/modules/auth/login.php');
        exit;
    }

    $verified = true;
}
