<?php
$pageTitle = 'Tax Group Details';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM tax_groups WHERE id = ?");
$stmt->execute([$id]);
$group = $stmt->fetch();
if (!$group) { flash('error', 'Tax group not found.', 'error'); redirect('index.php'); }

$lines = $db->prepare("
    SELECT l.id, l.sequence, r.rate_name, r.rate_percentage, r.status AS rate_status, t.name AS type_name
    FROM tax_group_lines l
    JOIN tax_rates r ON r.id = l.tax_rate_id
    JOIN tax_types t ON t.id = r.tax_type_id
    WHERE l.tax_group_id = ?
    ORDER BY l.sequence, l.id
");
$lines->execute([$id]);
$lines = $lines->fetchAll();

$stmt = $db->prepare("SELECT COUNT(*) FROM tax_group_lines WHERE tax_group_id = ?");
$stmt->execute([$id]);
$rateCount = (int)$stmt->fetchColumn();

$activeCount = count(array_filter($lines, fn($l) => $l['rate_status'] === 'active'));

$stmt = $db->prepare("SELECT COUNT(*) FROM tax_transactions WHERE tax_group_id = ?");
$stmt->execute([$id]);
$txnCount = (int)$stmt->fetchColumn();
?>

<div class="mb-6 flex justify-between items-start">
    <div>
        <a href="index.php" class="text-blue-600 hover:underline text-sm">&larr; Back to Tax Groups</a>
        <h1 class="text-2xl font-bold text-gray-800 mt-1"><?= sanitize($group['group_name']) ?></h1>
        <p class="text-gray-600">
            <span class="px-2 py-0.5 text-xs rounded font-medium <?= $group['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600' ?>"><?= ucfirst($group['status']) ?></span>
        </p>
    </div>
    <?php if (hasPermission('tax.edit')): ?>
    <a href="edit.php?id=<?= $id ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm"><i class="fas fa-edit mr-2"></i>Edit</a>
    <?php endif; ?>
</div>

<?= taxui_tabs('groups') ?>

<?php if ($group['description']): ?>
<div class="mb-6">
    <p class="text-gray-700 bg-white rounded-lg shadow p-4"><?= sanitize($group['description']) ?></p>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Rates in Group</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?= $rateCount ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Active Rates</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?= number_format($activeCount) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Transactions Referencing Group</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?= number_format($txnCount) ?></div>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-800">Rates in <?= sanitize($group['group_name']) ?></h2>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Seq</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rate Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Percentage</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($lines)): ?>
            <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No rates in this group.</td></tr>
            <?php endif; ?>
            <?php foreach ($lines as $l): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 text-center text-gray-600"><?= (int)$l['sequence'] ?></td>
                <td class="px-6 py-4 text-gray-900"><?= sanitize($l['rate_name']) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= sanitize($l['type_name']) ?></td>
                <td class="px-6 py-4 text-right font-mono text-gray-900"><?= rtrim(rtrim(number_format((float)$l['rate_percentage'], 4), '0'), '.') ?>%</td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2 py-1 text-xs rounded font-medium <?= $l['rate_status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600' ?>"><?= ucfirst($l['rate_status']) ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>