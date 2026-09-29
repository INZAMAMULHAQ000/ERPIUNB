<?php
/**
 * Template Designer — Delete Template
 */
$pageTitle = 'Delete Template';
require_once '../../includes/header.php';

if (!hasPermission('template_designer.delete')) {
    die('Access Denied');
}

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

// Load template
$stmt = $db->prepare("SELECT * FROM templates WHERE id = ?");
$stmt->execute([$id]);
$template = $stmt->fetch();

if (!$template) {
    flash('error', 'Template not found.', 'error');
    to('index.php');
    exit;
}

if ($template['is_system']) {
    flash('error', 'System templates cannot be deleted.', 'error');
    to('index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '') || !isset($_POST['confirm'])) {
        flash('error', 'Invalid security token.', 'error');
        to('index.php');
        exit;
    }
    try {
        $db->beginTransaction();

        // Log deletion
        $stmt = $db->prepare("INSERT INTO template_history (template_id, action, old_data, performed_by) VALUES (?, 'deleted', ?, ?)");
        $stmt->execute([$id, json_encode(['template_name' => $template['template_name']]), $_SESSION['user_id']]);
        logAudit($_SESSION['user_id'], 'delete_template', 'template_designer');

        // Delete cascade (template_versions, template_assets, template_module_mappings, etc. via FK CASCADE)
        $stmt = $db->prepare("DELETE FROM templates WHERE id = ?");
        $stmt->execute([$id]);

        $db->commit();

        flash('success', 'Template "' . sanitize($template['template_name']) . '" deleted successfully.', 'success');
        to('index.php');
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        flash('error', 'Failed to delete template: ' . $e->getMessage(), 'error');
    }
}
?>

<div class="max-w-lg mx-auto mt-8">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 text-center">
        <div class="text-5xl text-red-300 mb-4">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h2 class="text-xl font-bold text-slate-800 mb-2">Delete Template</h2>
        <p class="text-slate-600 mb-2">
            Are you sure you want to delete the template:
        </p>
        <p class="text-lg font-medium text-slate-800 mb-1"><?= sanitize($template['template_name']) ?></p>
        <p class="text-sm text-slate-400 mb-6">
            Type: <?= ucwords(str_replace('_', ' ', $template['template_type'])) ?> |
            Version: v<?= (int)$template['version'] ?> |
            Status: <?= ucfirst($template['status']) ?>
        </p>
        <p class="text-sm text-red-600 bg-red-50 rounded-lg p-3 mb-6">
            <i class="fas fa-info-circle mr-1"></i>
            This action cannot be undone. All versions, assets, and mappings for this template will be permanently deleted.
        </p>

        <form method="POST" class="flex items-center justify-center gap-3">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="confirm" value="1">
            <button type="submit" class="px-6 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium">
                <i class="fas fa-trash mr-2"></i> Delete Permanently
            </button>
            <a href="edit.php?id=<?= $id ?>" class="px-4 py-2.5 text-slate-600 hover:text-slate-800 rounded-lg hover:bg-slate-100">
                Cancel
            </a>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>