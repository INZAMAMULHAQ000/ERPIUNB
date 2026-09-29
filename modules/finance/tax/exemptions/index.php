<?php
$pageTitle = 'Tax Exemptions';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.view')) { die('Access Denied'); }

$db = getDB();

// ---- Toggle status ----
if (isset($_GET['toggle']) && hasPermission('tax.edit')) {
    $stmt = $db->prepare("SELECT status FROM tax_exemptions WHERE id = ?");
    $stmt->execute([(int)$_GET['toggle']]);
    if ($stmt->fetch()) {
        $db->prepare("UPDATE tax_exemptions SET status = IF(status='active','inactive','active'), updated_at = NOW() WHERE id = ?")->execute([(int)$_GET['toggle']]);
        logAudit($_SESSION['user_id'], 'Toggled tax exemption #' . (int)$_GET['toggle'], 'tax');
        flash('success', 'Tax exemption status updated.');
    }
    redirect('index.php');
}

// ---- Delete ----
if (isset($_GET['delete']) && hasPermission('tax.edit')) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("SELECT COUNT(*) FROM tax_transactions WHERE exemption_id = ?");
    $stmt->execute([$id]);
    if ((int)$stmt->fetchColumn() > 0) {
        flash('error', 'Cannot delete: used by tax transactions. Deactivate instead.', 'error');
    } else {
        $db->prepare("DELETE FROM tax_exemptions WHERE id = ?")->execute([$id]);
        logAudit($_SESSION['user_id'], 'Deleted tax exemption #' . $id, 'tax');
        flash('success', 'Tax exemption deleted.');
    }
    redirect('index.php');
}

$exemptions = $db->query("SELECT * FROM tax_exemptions ORDER BY status = 'inactive', name")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Tax Exemptions</h1>
        <p class="text-gray-600">Exemption categories that remove supplies from tax — export, medical, financial services and more</p>
    </div>
    <?php if (hasPermission('tax.create')): ?>
    <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>New Exemption
    </a>
    <?php endif; ?>
</div>

<?= taxui_tabs('exemptions') ?>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($exemptions)): ?>
            <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No tax exemptions yet. <a href="create.php" class="text-blue-600 hover:underline">Create one</a>.</td></tr>
            <?php endif; ?>
            <?php foreach ($exemptions as $e): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs font-mono bg-slate-100 text-slate-700 rounded"><?= sanitize($e['exemption_code']) ?></span>
                </td>
                <td class="px-6 py-4 font-medium text-gray-900"><?= sanitize($e['name']) ?></td>
                <td class="px-6 py-4 text-gray-600 max-w-xs truncate"><?= sanitize($e['description']) ?></td>
                <td class="px-6 py-4 text-gray-600 max-w-xs truncate"><?= sanitize($e['reason']) ?></td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2 py-1 text-xs rounded font-medium <?= $e['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600' ?>">
                        <?= ucfirst($e['status']) ?>
                    </span>
                </td>
                <td class="px-6 py-4 text-right whitespace-nowrap">
                    <?php if (hasPermission('tax.edit')): ?>
                    <a href="edit.php?id=<?= $e['id'] ?>" class="text-gray-500 hover:text-blue-600 mr-2" title="Edit"><i class="fas fa-edit"></i></a>
                    <a href="index.php?toggle=<?= $e['id'] ?>" class="text-gray-500 hover:text-amber-600 mr-2" title="<?= $e['status'] === 'active' ? 'Deactivate' : 'Activate' ?>">
                        <i class="fas fa-toggle-<?= $e['status'] === 'active' ? 'on' : 'off' ?>"></i>
                    </a>
                    <a href="index.php?delete=<?= $e['id'] ?>" class="text-gray-500 hover:text-red-600" title="Delete" onclick="return confirm('Delete this tax exemption?')"><i class="fas fa-trash"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>