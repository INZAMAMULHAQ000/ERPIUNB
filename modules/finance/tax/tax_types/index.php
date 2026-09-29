<?php
$pageTitle = 'Tax Types';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.view')) { die('Access Denied'); }

$db = getDB();

// ---- Toggle status ----
if (isset($_GET['toggle']) && hasPermission('tax.edit')) {
    $stmt = $db->prepare("SELECT status FROM tax_types WHERE id = ?");
    $stmt->execute([(int)$_GET['toggle']]);
    if ($stmt->fetch()) {
        $db->prepare("UPDATE tax_types SET status = IF(status='active','inactive','active'), updated_at = NOW() WHERE id = ?")->execute([(int)$_GET['toggle']]);
        logAudit($_SESSION['user_id'], 'Toggled tax type #' . (int)$_GET['toggle'], 'tax');
        flash('success', 'Tax type status updated.');
    }
    redirect('index.php');
}

// ---- Delete ----
if (isset($_GET['delete']) && hasPermission('tax.edit')) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("SELECT COUNT(*) FROM tax_rates WHERE tax_type_id = ?");
    $stmt->execute([$id]);
    if ((int)$stmt->fetchColumn() > 0) {
        flash('error', 'Cannot delete: tax rates exist for this type. Deactivate it instead.', 'error');
    } else {
        $db->prepare("DELETE FROM tax_types WHERE id = ?")->execute([$id]);
        logAudit($_SESSION['user_id'], 'Deleted tax type #' . $id, 'tax');
        flash('success', 'Tax type deleted.');
    }
    redirect('index.php');
}

$types = $db->query("
    SELECT tt.*, (SELECT COUNT(*) FROM tax_rates r WHERE r.tax_type_id = tt.id) as rate_count,
           (SELECT COUNT(*) FROM tax_transactions t WHERE t.tax_type_id = tt.id) as txn_count
    FROM tax_types tt ORDER BY tt.status = 'inactive', tt.name
")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Tax Types</h1>
        <p class="text-gray-600">Tax regimes this ERP accounts for — VAT, GST, sales tax and more</p>
    </div>
    <?php if (hasPermission('tax.create')): ?>
    <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>New Tax Type
    </a>
    <?php endif; ?>
</div>

<?= taxui_tabs('types') ?>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tax Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Rates</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Transactions</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($types)): ?>
            <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No tax types yet. <a href="create.php" class="text-blue-600 hover:underline">Create one</a>.</td></tr>
            <?php endif; ?>
            <?php foreach ($types as $t): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <a href="view.php?id=<?= $t['id'] ?>" class="font-medium text-gray-900 hover:text-blue-600"><?= sanitize($t['name']) ?></a>
                </td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs font-mono bg-slate-100 text-slate-700 rounded"><?= sanitize($t['code']) ?></span>
                </td>
                <td class="px-6 py-4 text-gray-600 max-w-xs truncate"><?= sanitize($t['description']) ?></td>
                <td class="px-6 py-4 text-center text-gray-700"><?= (int)$t['rate_count'] ?></td>
                <td class="px-6 py-4 text-center text-gray-700"><?= (int)$t['txn_count'] ?></td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2 py-1 text-xs rounded font-medium <?= $t['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600' ?>">
                        <?= ucfirst($t['status']) ?>
                    </span>
                </td>
                <td class="px-6 py-4 text-right whitespace-nowrap">
                    <a href="view.php?id=<?= $t['id'] ?>" class="text-gray-500 hover:text-gray-800 mr-2" title="View"><i class="fas fa-eye"></i></a>
                    <?php if (hasPermission('tax.edit')): ?>
                    <a href="edit.php?id=<?= $t['id'] ?>" class="text-gray-500 hover:text-blue-600 mr-2" title="Edit"><i class="fas fa-edit"></i></a>
                    <a href="index.php?toggle=<?= $t['id'] ?>" class="text-gray-500 hover:text-amber-600 mr-2" title="<?= $t['status'] === 'active' ? 'Deactivate' : 'Activate' ?>">
                        <i class="fas fa-toggle-<?= $t['status'] === 'active' ? 'on' : 'off' ?>"></i>
                    </a>
                    <a href="index.php?delete=<?= $t['id'] ?>" class="text-gray-500 hover:text-red-600" title="Delete" onclick="return confirm('Delete this tax type?')"><i class="fas fa-trash"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>