<?php
$pageTitle = 'Edit Tax Group';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.edit')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM tax_groups WHERE id = ?");
$stmt->execute([$id]);
$group = $stmt->fetch();
if (!$group) { flash('error', 'Tax group not found.', 'error'); redirect('index.php'); }

$stmt = $db->prepare("SELECT tax_rate_id FROM tax_group_lines WHERE tax_group_id = ? ORDER BY sequence, id");
$stmt->execute([$id]);
$currentIds = array_map('intval', array_column($stmt->fetchAll(), 'tax_rate_id'));

$rates = $db->query("
    SELECT r.id, r.rate_name, r.rate_percentage, t.name AS type_name
    FROM tax_rates r
    JOIN tax_types t ON t.id = r.tax_type_id
    ORDER BY t.name, r.rate_percentage DESC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid or expired form token.', 'error');
        redirect('edit.php?id=' . $id);
    }
    $groupName = trim($_POST['group_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    $rateIds = array_values(array_filter(array_map('intval', (array)($_POST['tax_group_rates'] ?? []))));

    if ($groupName === '') {
        flash('error', 'Group name is required.', 'error');
        redirect('edit.php?id=' . $id);
    }

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE tax_groups SET group_name = ?, description = ?, status = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$groupName, $description, $status, $id]);

        $db->prepare("DELETE FROM tax_group_lines WHERE tax_group_id = ?")->execute([$id]);
        $ins = $db->prepare("INSERT IGNORE INTO tax_group_lines (tax_group_id, tax_rate_id, sequence) VALUES (?, ?, ?)");
        foreach ($rateIds as $i => $rid) {
            $ins->execute([$id, $rid, $i]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        flash('error', 'Failed to update tax group: ' . $e->getMessage(), 'error');
        redirect('edit.php?id=' . $id);
    }

    logAudit($_SESSION['user_id'], 'Updated tax group #' . $id . ' (' . $groupName . ')', 'tax');
    flash('success', 'Tax group updated.', 'success');
    redirect('view.php?id=' . $id);
}
?>

<div class="mb-6">
    <a href="view.php?id=<?= $id ?>" class="text-blue-600 hover:underline text-sm">&larr; Back to <?= sanitize($group['group_name']) ?></a>
    <h1 class="text-2xl font-bold text-gray-800 mt-1">Edit Tax Group</h1>
</div>

<?= taxui_tabs('groups') ?>

<?php if ($err = getFlash('error')): ?>
<div class="mb-4 p-3 rounded bg-red-50 text-red-700 border border-red-200"><?= sanitize($err['message'] ?? '') ?></div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="post" action="edit.php?id=<?= $id ?>" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Group Name <span class="text-red-500">*</span></label>
            <input type="text" name="group_name" required maxlength="100" value="<?= sanitize($group['group_name']) ?>"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="3" class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500"><?= sanitize($group['description']) ?></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
                <option value="active" <?= $group['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $group['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Rates in this group</label>
            <div class="max-h-64 overflow-y-auto p-3 border rounded bg-white">
                <?php if (empty($rates)): ?>
                <p class="text-sm text-gray-500">No tax rates defined yet. <a href="../tax_rates/create.php" class="text-blue-600 hover:underline">Add a rate</a> first.</p>
                <?php else: ?>
                    <?php foreach ($rates as $r): ?>
                    <label class="flex items-center gap-2 py-1.5 cursor-pointer hover:bg-gray-50 rounded px-1">
                        <input type="checkbox" name="tax_group_rates[]" value="<?= $r['id'] ?>" class="rounded" <?= in_array((int)$r['id'], $currentIds) ? 'checked' : '' ?>>
                        <span class="text-sm text-gray-800"><?= sanitize($r['rate_name']) ?>
                            <span class="text-gray-500">(<?= sanitize($r['type_name']) ?> ·
                            <?= rtrim(rtrim(number_format((float)$r['rate_percentage'], 4), '0'), '.') ?>%)</span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p class="text-xs text-gray-500 mt-1">Unchecking a rate removes it from this group.</p>
        </div>
        <div class="flex justify-end gap-3 pt-2">
            <a href="view.php?id=<?= $id ?>" class="px-4 py-2 text-sm border rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Save Changes</button>
        </div>
    </form>
</div>

<?php require_once '../../../../includes/footer.php'; ?>