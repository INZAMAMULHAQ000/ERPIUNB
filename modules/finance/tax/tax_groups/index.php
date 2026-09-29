<?php
$pageTitle = 'Tax Groups';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.view')) { die('Access Denied'); }

$db = getDB();

// ---- Toggle status ----
if (isset($_GET['toggle']) && hasPermission('tax.edit')) {
    $stmt = $db->prepare("SELECT status FROM tax_groups WHERE id = ?");
    $stmt->execute([(int)$_GET['toggle']]);
    if ($stmt->fetch()) {
        $db->prepare("UPDATE tax_groups SET status = IF(status='active','inactive','active'), updated_at = NOW() WHERE id = ?")->execute([(int)$_GET['toggle']]);
        logAudit($_SESSION['user_id'], 'Toggled tax group #' . (int)$_GET['toggle'], 'tax');
        flash('success', 'Tax group status updated.', 'success');
    }
    redirect('index.php');
}

// ---- Delete ----
if (isset($_GET['delete']) && hasPermission('tax.edit')) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("SELECT COUNT(*) FROM tax_group_lines WHERE tax_group_id = ?");
    $stmt->execute([$id]);
    if ((int)$stmt->fetchColumn() > 0) {
        flash('error', 'Cannot delete: group contains tax rates. Deactivate it instead.', 'error');
    } else {
        $db->prepare("DELETE FROM tax_groups WHERE id = ?")->execute([$id]);
        logAudit($_SESSION['user_id'], 'Deleted tax group #' . $id, 'tax');
        flash('success', 'Tax group deleted.', 'success');
    }
    redirect('index.php');
}

$groups = $db->query("
    SELECT g.*, (SELECT COUNT(*) FROM tax_group_lines l WHERE l.tax_group_id = g.id) AS rate_count
    FROM tax_groups g ORDER BY g.status = 'inactive', g.group_name
")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Tax Groups</h1>
        <p class="text-gray-600">Combinations of rates applied together to a single line — e.g. VAT 5% + WHT 3%</p>
    </div>
    <?php if (hasPermission('tax.create')): ?>
    <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>New Tax Group
    </a>
    <?php endif; ?>
</div>

<?= taxui_tabs('groups') ?>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Group Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Rates</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($groups)): ?>
            <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No tax groups yet. <a href="create.php" class="text-blue-600 hover:underline">Create one</a>.</td></tr>
            <?php endif; ?>
            <?php foreach ($groups as $g): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <a href="view.php?id=<?= $g['id'] ?>" class="font-medium text-gray-900 hover:text-blue-600"><?= sanitize($g['group_name']) ?></a>
                </td>
                <td class="px-6 py-4 text-gray-600 max-w-xs truncate"><?= sanitize($g['description']) ?></td>
                <td class="px-6 py-4 text-center text-gray-700"><?= (int)$g['rate_count'] ?></td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2 py-1 text-xs rounded font-medium <?= $g['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600' ?>">
                        <?= ucfirst($g['status']) ?>
                    </span>
                </td>
                <td class="px-6 py-4 text-right whitespace-nowrap">
                    <a href="view.php?id=<?= $g['id'] ?>" class="text-gray-500 hover:text-gray-800 mr-2" title="View"><i class="fas fa-eye"></i></a>
                    <?php if (hasPermission('tax.edit')): ?>
                    <a href="edit.php?id=<?= $g['id'] ?>" class="text-gray-500 hover:text-blue-600 mr-2" title="Edit"><i class="fas fa-edit"></i></a>
                    <a href="index.php?toggle=<?= $g['id'] ?>" class="text-gray-500 hover:text-amber-600 mr-2" title="<?= $g['status'] === 'active' ? 'Deactivate' : 'Activate' ?>">
                        <i class="fas fa-toggle-<?= $g['status'] === 'active' ? 'on' : 'off' ?>"></i>
                    </a>
                    <a href="index.php?delete=<?= $g['id'] ?>" class="text-gray-500 hover:text-red-600" title="Delete" onclick="return confirm('Delete this tax group?')"><i class="fas fa-trash"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>