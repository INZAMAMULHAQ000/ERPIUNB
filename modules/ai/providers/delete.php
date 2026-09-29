<?php
/**
 * Delete AI Provider.
 *
 * Form handler to delete an AI provider.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('modules/ai/providers/index.php'));
    exit;
}

// CSRF check
if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('error', 'Invalid CSRF token.', 'error');
    header('Location: ' . url('modules/ai/providers/index.php'));
    exit;
}

// Permission check
if (!hasPermission('ai.providers.delete')) {
    die('Access Denied');
}

$id = (int) ($_POST['id'] ?? 0);

if (!$id) {
    flash('error', 'Invalid provider ID.', 'error');
    header('Location: ' . url('modules/ai/providers/index.php'));
    exit;
}

$db = getDB();
$result = AIProviderService::deleteProvider($db, $id, $_SESSION['user_id']);

if ($result['success']) {
    flash('success', 'Provider deleted successfully.', 'success');
} else {
    flash('error', $result['errors'][0] ?? 'Failed to delete provider.', 'error');
}

header('Location: ' . url('modules/ai/providers/index.php'));
exit;
