<?php
$pageTitle = 'Edit Tax Exemption';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.edit')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM tax_exemptions WHERE id = ?");
$stmt->execute([$id]);
$exemption = $stmt->fetch();
if (!$exemption) { flash('error', 'Tax exemption not found.', 'error'); redirect('index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid or expired form token.', 'error');
        redirect('edit.php?id=' . $id);
    }
    $code = strtoupper(trim($_POST['exemption_code'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    $errors = [];
    if ($code === '') $errors[] = 'Exemption code is required.';
    if ($name === '') $errors[] = 'Name is required.';

    if (!$errors && $code !== '') {
        $stmt2 = $db->prepare("SELECT COUNT(*) FROM tax_exemptions WHERE exemption_code = ? AND id != ?");
        $stmt2->execute([$code, $id]);
        if ((int)$stmt2->fetchColumn() > 0) $errors[] = 'Code already in use.';
    }

    if ($errors) {
        flash('error', implode(' ', $errors));
        redirect('edit.php?id=' . $id);
    }

    $db->prepare("UPDATE tax_exemptions SET exemption_code = ?, name = ?, description = ?, reason = ?, status = ?, updated_at = NOW() WHERE id = ?")
       ->execute([$code, $name, $description, $reason, $status, $id]);
    logAudit($_SESSION['user_id'], 'Updated tax exemption #' . $id . ' (' . $code . ')', 'tax');
    flash('success', 'Tax exemption updated.');
    redirect('index.php');
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:underline text-sm">&larr; Back to Tax Exemptions</a>
    <h1 class="text-2xl font-bold text-gray-800 mt-1">Edit Tax Exemption</h1>
    <p class="text-gray-600">
        <span class="px-2 py-0.5 text-xs font-mono bg-slate-100 text-slate-700 rounded"><?= sanitize($exemption['exemption_code']) ?></span>
    </p>
</div>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="post" action="edit.php?id=<?= $id ?>" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Exemption Code <span class="text-red-500">*</span></label>
            <input type="text" name="exemption_code" required maxlength="30" value="<?= sanitize($exemption['exemption_code']) ?>"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
            <p class="text-xs text-gray-500 mt-1">Short unique code used on invoices and reports (auto-uppercased).</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" required maxlength="150" value="<?= sanitize($exemption['name']) ?>"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="3" class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500"><?= sanitize($exemption['description']) ?></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
            <textarea name="reason" rows="3" class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500"><?= sanitize($exemption['reason']) ?></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
                <option value="active" <?= $exemption['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $exemption['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="flex justify-end gap-3 pt-2">
            <a href="index.php" class="px-4 py-2 text-sm border rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Save Changes</button>
        </div>
    </form>
</div>

<?php require_once '../../../../includes/footer.php'; ?>