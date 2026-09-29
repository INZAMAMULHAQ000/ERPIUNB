<?php
$pageTitle = 'New Tax Group';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.create')) { die('Access Denied'); }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid or expired form token.', 'error');
        redirect('create.php');
    }
    $groupName = trim($_POST['group_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    $rateIds = array_values(array_filter(array_map('intval', (array)($_POST['tax_group_rates'] ?? []))));

    if ($groupName === '') {
        flash('error', 'Group name is required.', 'error');
        redirect('create.php');
    }

    $db->beginTransaction();
    $gid = null;
    try {
        $db->prepare("INSERT INTO tax_groups (group_name, description, status) VALUES (?, ?, ?)")
           ->execute([$groupName, $description, $status]);
        $gid = (int)$db->lastInsertId();

        $ins = $db->prepare("INSERT IGNORE INTO tax_group_lines (tax_group_id, tax_rate_id, sequence) VALUES (?, ?, ?)");
        foreach ($rateIds as $i => $rid) {
            $ins->execute([$gid, $rid, $i]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        flash('error', 'Failed to create tax group: ' . $e->getMessage(), 'error');
        redirect('create.php');
    }

    logAudit($_SESSION['user_id'], 'Created tax group #' . $gid . ' (' . $groupName . ')', 'tax');
    flash('success', 'Tax group "' . $groupName . '" created.', 'success');
    redirect('view.php?id=' . $gid);
}

$rates = $db->query("
    SELECT r.id, r.rate_name, r.rate_percentage, t.name AS type_name
    FROM tax_rates r
    JOIN tax_types t ON t.id = r.tax_type_id
    ORDER BY t.name, r.rate_percentage DESC
")->fetchAll();
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:underline text-sm">&larr; Back to Tax Groups</a>
    <h1 class="text-2xl font-bold text-gray-800 mt-1">New Tax Group</h1>
    <p class="text-gray-600">A tax group applies multiple tax rates to one line (e.g. VAT 5% + WHT 3%).</p>
</div>

<?= taxui_tabs('groups') ?>

<?php if ($err = getFlash('error')): ?>
<div class="mb-4 p-3 rounded bg-red-50 text-red-700 border border-red-200"><?= sanitize($err['message'] ?? '') ?></div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="post" action="create.php" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Group Name <span class="text-red-500">*</span></label>
            <input type="text" name="group_name" required maxlength="100" placeholder="e.g. VAT + WHT Combined"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="3" placeholder="Optional — what this group is used for"
                      class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500"></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
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
                        <input type="checkbox" name="tax_group_rates[]" value="<?= $r['id'] ?>" class="rounded">
                        <span class="text-sm text-gray-800"><?= sanitize($r['rate_name']) ?>
                            <span class="text-gray-500">(<?= sanitize($r['type_name']) ?> ·
                            <?= rtrim(rtrim(number_format((float)$r['rate_percentage'], 4), '0'), '.') ?>%)</span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p class="text-xs text-gray-500 mt-1">Check one or more rates. Order follows this list.</p>
        </div>
        <div class="flex justify-end gap-3 pt-2">
            <a href="index.php" class="px-4 py-2 text-sm border rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Save Tax Group</button>
        </div>
    </form>
</div>

<?php require_once '../../../../includes/footer.php'; ?>