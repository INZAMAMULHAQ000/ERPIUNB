<?php
$pageTitle = 'Tax Rates';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.view')) { die('Access Denied'); }

$db = getDB();

// ---- Toggle status ----
if (isset($_GET['toggle']) && hasPermission('tax.edit')) {
    $stmt = $db->prepare("SELECT status FROM tax_rates WHERE id = ?");
    $stmt->execute([(int)$_GET['toggle']]);
    if ($stmt->fetch()) {
        $db->prepare("UPDATE tax_rates SET status = IF(status='active','inactive','active'), updated_at = NOW() WHERE id = ?")->execute([(int)$_GET['toggle']]);
        logAudit($_SESSION['user_id'], 'Toggled tax rate #' . (int)$_GET['toggle'], 'tax');
        flash('success', 'Tax rate status updated.');
    }
    redirect('index.php');
}

// ---- Delete ----
if (isset($_GET['delete']) && hasPermission('tax.edit')) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("SELECT COUNT(*) FROM tax_group_lines WHERE tax_rate_id = ?");
    $stmt->execute([$id]);
    $inGroups = (int)$stmt->fetchColumn();
    $stmt = $db->prepare("SELECT COUNT(*) FROM tax_transactions WHERE tax_rate_id = ?");
    $stmt->execute([$id]);
    $inTxns = (int)$stmt->fetchColumn();
    if ($inGroups > 0 || $inTxns > 0) {
        flash('error', 'Cannot delete: this rate is used by tax groups or transactions. Deactivate it instead.', 'error');
    } else {
        $db->prepare("DELETE FROM tax_rates WHERE id = ?")->execute([$id]);
        logAudit($_SESSION['user_id'], 'Deleted tax rate #' . $id, 'tax');
        flash('success', 'Tax rate deleted.');
    }
    redirect('index.php');
}

// ---- List (optional tax type filter) ----
$taxTypeId = (int)($_GET['tax_type_id'] ?? 0);
$where = '';
$params = [];
if ($taxTypeId > 0) {
    $where = ' WHERE r.tax_type_id = :id';
    $params = ['id' => $taxTypeId];
}
$stmt = $db->prepare("
    SELECT r.*, t.name AS type_name, t.code AS type_code
    FROM tax_rates r
    JOIN tax_types t ON t.id = r.tax_type_id
    " . $where . "
    ORDER BY t.name, r.rate_percentage DESC, r.id
");
$stmt->execute($params);
$rates = $stmt->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Tax Rates</h1>
        <p class="text-gray-600">Percentage rates applicable per tax type, with optional effective windows</p>
    </div>
    <?php if (hasPermission('tax.create')): ?>
    <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>New Tax Rate
    </a>
    <?php endif; ?>
</div>

<?= taxui_tabs('rates') ?>

<form method="get" class="mb-6 bg-white rounded-lg shadow p-4 flex items-end gap-3">
    <div class="flex-1">
        <label class="block text-sm font-medium text-gray-700 mb-1">Tax Type</label>
        <?= taxui_types_select((string)($_GET['tax_type_id'] ?? ''), 'tax_type_id') ?>
    </div>
    <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700"><i class="fas fa-filter mr-1"></i>Filter</button>
</form>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rate Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tax Type</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Percentage</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Effective From / To</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Default</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($rates)): ?>
            <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No rates yet. <a href="create.php" class="text-blue-600 hover:underline">Create one</a>.</td></tr>
            <?php endif; ?>
            <?php foreach ($rates as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-medium text-gray-900"><?= sanitize($r['rate_name']) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= sanitize($r['type_name']) ?></td>
                <td class="px-6 py-4 text-right font-mono text-gray-900"><?= rtrim(rtrim(number_format((float)$r['rate_percentage'], 4), '0'), '.') ?>%</td>
                <td class="px-6 py-4 text-gray-600">
                    <span class="inline-block bg-slate-50 border border-slate-200 rounded px-2 py-0.5 text-xs font-mono text-slate-600 whitespace-nowrap">
                        <?= $r['effective_from'] ? sanitize($r['effective_from']) : '—' ?> &rarr; <?= $r['effective_to'] ? sanitize($r['effective_to']) : '—' ?>
                    </span>
                </td>
                <td class="px-6 py-4 text-center"><?= $r['is_default'] ? '<span class="text-blue-600"><i class="fas fa-check-circle"></i></span>' : '<span class="text-gray-300">—</span>' ?></td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2 py-1 text-xs rounded font-medium <?= $r['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600' ?>">
                        <?= ucfirst($r['status']) ?>
                    </span>
                </td>
                <td class="px-6 py-4 text-right whitespace-nowrap">
                    <?php if (hasPermission('tax.edit')): ?>
                    <a href="edit.php?id=<?= $r['id'] ?>" class="text-gray-500 hover:text-blue-600 mr-2" title="Edit"><i class="fas fa-edit"></i></a>
                    <a href="index.php?toggle=<?= $r['id'] ?>" class="text-gray-500 hover:text-amber-600 mr-2" title="<?= $r['status'] === 'active' ? 'Deactivate' : 'Activate' ?>">
                        <i class="fas fa-toggle-<?= $r['status'] === 'active' ? 'on' : 'off' ?>"></i>
                    </a>
                    <a href="index.php?delete=<?= $r['id'] ?>" class="text-gray-500 hover:text-red-600" title="Delete" onclick="return confirm('Delete this tax rate?')"><i class="fas fa-trash"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>