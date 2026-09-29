<?php
/**
 * Delete AI Model.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('modules/ai/models/index.php'));
    exit;
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('error', 'Invalid CSRF token.', 'error');
    header('Location: ' . url('modules/ai/models/index.php'));
    exit;
}

if (!hasPermission('ai.models.manage')) { die('Access Denied'); }

$id = (int) ($_POST['id'] ?? 0);
if (!$id) {
    flash('error', 'Invalid model ID.', 'error');
    header('Location: ' . url('modules/ai/models/index.php'));
    exit;
}

$db = getDB();
$result = AIModelService::deleteModel($db, $id, $_SESSION['user_id']);

if ($result['success']) {
    flash('success', 'Model deleted successfully.', 'success');
} else {
    flash('error', $result['errors'][0] ?? 'Failed to delete model.', 'error');
}

header('Location: ' . url('modules/ai/models/index.php'));
exit;
