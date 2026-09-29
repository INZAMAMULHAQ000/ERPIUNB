<?php
$pageTitle = 'Edit Tax Type';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.edit')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM tax_types WHERE id = ?");
$stmt->execute([$id]);
$type = $stmt->fetch();
if (!$type) { flash('error', 'Tax type not found.', 'error'); redirect('index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid or expired form token.', 'error'); redirect('edit.php?id=' . $id); }
    $name = trim($_POST['name'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    $errors = [];
    if ($name === '') $errors[] = 'Name is required.';
    if ($code === '') $errors[] = 'Code is required.';

    if (!$errors && $code !== '') {
        $stmt2 = $db->prepare("SELECT COUNT(*) FROM tax_types WHERE code = ? AND id != ?");
        $stmt2->execute([$code, $id]);
        if ((int)$stmt2->fetchColumn() > 0) $errors[] = 'Another tax type already uses code "' . $code . '".';
    }

    if ($errors) {
        flash('error', implode(' ', $errors));
        redirect('edit.php?id=' . $id);
    }

    $db->prepare("UPDATE tax_types SET name = ?, code = ?, description = ?, status = ?, updated_at = NOW() WHERE id = ?")
       ->execute([$name, $code, $description, $status, $id]);
    logAudit($_SESSION['user_id'], 'Updated tax type #' . $id . ' (' . $code . ')', 'tax');
    flash('success', 'Tax type updated.', 'success');
    redirect('view.php?id=' . $id);
}
?>

<div class="mb-6">
    <a href="view.php?id=<?= $id ?>" class="text-blue-600 hover:underline text-sm">&larr; Back to <?= sanitize($type['name']) ?></a>
    <h1 class="text-2xl font-bold text-gray-800 mt-1">Edit Tax Type</h1>
</div>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="post" action="edit.php?id=<?= $id ?>" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" required maxlength="100" value="<?= sanitize($type['name']) ?>"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
            <input type="text" name="code" required maxlength="20" value="<?= sanitize($type['code']) ?>"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="3" class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500"><?= sanitize($type['description']) ?></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
                <option value="active" <?= $type['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $type['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="flex justify-end gap-3 pt-2">
            <a href="view.php?id=<?= $id ?>" class="px-4 py-2 text-sm border rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Save Changes</button>
        </div>
    </form>
</div>

<?php require_once '../../../../includes/footer.php'; ?>