<?php
$pageTitle = 'Tax Type Details';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM tax_types WHERE id = ?");
$stmt->execute([$id]);
$type = $stmt->fetch();
if (!$type) { flash('error', 'Tax type not found.', 'error'); redirect('index.php'); }

$rates = $db->prepare("SELECT * FROM tax_rates WHERE tax_type_id = ? ORDER BY is_default DESC, rate_percentage DESC, id");
$rates->execute([$id]);
$rates = $rates->fetchAll();

$stmt = $db->prepare("SELECT COUNT(*) FROM tax_transactions WHERE tax_type_id = ?");
$stmt->execute([$id]);
$txnCount = (int)$stmt->fetchColumn();
?>

<div class="mb-6 flex justify-between items-start">
    <div>
        <a href="index.php" class="text-blue-600 hover:underline text-sm">&larr; Back to Tax Types</a>
        <h1 class="text-2xl font-bold text-gray-800 mt-1"><?= sanitize($type['name']) ?></h1>
        <p class="text-gray-600">
            <span class="px-2 py-0.5 text-xs font-mono bg-slate-100 text-slate-700 rounded"><?= sanitize($type['code']) ?></span>
            <span class="ml-2 px-2 py-0.5 text-xs rounded font-medium <?= $type['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600' ?>"><?= ucfirst($type['status']) ?></span>
        </p>
    </div>
    <?php if (hasPermission('tax.edit')): ?>
    <a href="edit.php?id=<?= $id ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm"><i class="fas fa-edit mr-2"></i>Edit</a>
    <?php endif; ?>
</div>

<?= taxui_tabs('types') ?>

<?php if ($type['description']): ?>
<div class="mb-6">
    <p class="text-gray-700 bg-white rounded-lg shadow p-4"><?= sanitize($type['description']) ?></p>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Tax Rates</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?= count($rates) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Transactions</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?= number_format($txnCount) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Default Rate</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?php
            $defPct = '';
            foreach ($rates as $r) { if ($r['is_default']) { $defPct = rtrim(rtrim(number_format((float)$r['rate_percentage'], 4), '0'), '.') . '%'; break; } }
            echo $defPct ?: '—';
        ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Active Rates</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?= count(array_filter($rates, fn($r) => $r['status'] === 'active')) ?></div>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-800">Tax Rates for <?= sanitize($type['name']) ?></h2>
        <?php if (hasPermission('tax.create')): ?>
        <a href="../tax_rates/create.php?tax_type_id=<?= $id ?>" class="text-sm text-blue-600 hover:underline"><i class="fas fa-plus mr-1"></i>Add Rate</a>
        <?php endif; ?>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rate Name</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Percentage</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Effective From</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Effective To</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Default</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($rates)): ?>
            <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No rates defined for this type yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($rates as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 text-gray-900"><?= sanitize($r['rate_name']) ?></td>
                <td class="px-6 py-4 text-right font-mono text-gray-900"><?= rtrim(rtrim(number_format((float)$r['rate_percentage'], 4), '0'), '.') ?>%</td>
                <td class="px-6 py-4 text-center text-gray-600"><?= sanitize($r['effective_from'] ?? '—') ?></td>
                <td class="px-6 py-4 text-center text-gray-600"><?= sanitize($r['effective_to'] ?? '—') ?></td>
                <td class="px-6 py-4 text-center"><?= $r['is_default'] ? '<span class="text-blue-600"><i class="fas fa-check-circle"></i></span>' : '<span class="text-gray-300"><i class="fas fa-circle"></i></span>' ?></td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2 py-1 text-xs rounded font-medium <?= $r['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600' ?>"><?= ucfirst($r['status']) ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>