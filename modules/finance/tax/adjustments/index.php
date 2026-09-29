<?php
$pageTitle = 'Tax Adjustments';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.view')) { die('Access Denied'); }

$db = getDB();

$adjustments = $db->query("
    SELECT a.*, t.name AS type_name, t.code AS type_code, u.name AS created_name
    FROM tax_adjustments a
    JOIN tax_types t ON t.id = a.tax_type_id
    LEFT JOIN users u ON u.id = a.created_by
    ORDER BY a.created_at DESC
    LIMIT 300
")->fetchAll();

$totalTaxable = 0.0;
$totalTax = 0.0;
foreach ($adjustments as $a) {
    $totalTaxable += (float)$a['taxable_amount'];
    $totalTax += (float)$a['tax_amount'];
}

$statusBadge = [
    'draft'    => ['bg-amber-100 text-amber-800', 'Draft'],
    'approved' => ['bg-green-100 text-green-800', 'Approved'],
    'rejected' => ['bg-rose-100 text-rose-800', 'Rejected'],
];
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Tax Adjustments</h1>
        <p class="text-gray-600">Manual corrections to output or input tax — posted to the ledger on approval</p>
    </div>
    <?php if (hasPermission('tax.adjust')): ?>
    <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>New Adjustment
    </a>
    <?php endif; ?>
</div>

<?= taxui_tabs('adjustments') ?>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Adjustment No</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Direction</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Taxable</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tax</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created By</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($adjustments)): ?>
            <tr><td colspan="9" class="px-6 py-8 text-center text-gray-500">No adjustments yet. <a href="create.php" class="text-blue-600 hover:underline">Create one</a>.</td></tr>
            <?php endif; ?>
            <?php foreach ($adjustments as $a): ?>
            <?php $sb = $statusBadge[$a['status']] ?? ['bg-gray-100 text-gray-700', ucfirst((string)$a['status'])]; ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <a href="view.php?id=<?= $a['id'] ?>" class="font-mono text-sm font-medium text-gray-900 hover:text-blue-600"><?= sanitize($a['adjustment_no']) ?></a>
                </td>
                <td class="px-6 py-4 text-gray-600 whitespace-nowrap"><?= sanitize($a['adjustment_date']) ?></td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs font-mono bg-slate-100 text-slate-700 rounded"><?= sanitize($a['type_code']) ?></span>
                </td>
                <td class="px-6 py-4"><?= taxui_direction_badge($a['direction']) ?></td>
                <td class="px-6 py-4 text-right text-gray-900"><?= taxui_amount($a['taxable_amount']) ?></td>
                <td class="px-6 py-4 text-right text-gray-900"><?= taxui_amount($a['tax_amount']) ?></td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2 py-1 text-xs rounded font-medium <?= $sb[0] ?>"><?= $sb[1] ?></span>
                </td>
                <td class="px-6 py-4 text-gray-600"><?= sanitize($a['created_name']) ?></td>
                <td class="px-6 py-4 text-right whitespace-nowrap">
                    <a href="view.php?id=<?= $a['id'] ?>" class="text-gray-500 hover:text-gray-800 mr-2" title="View"><i class="fas fa-eye"></i></a>
                    <?php if ($a['status'] === 'draft' && hasPermission('tax.adjust')): ?>
                    <form method="post" action="approve.php" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                        <button type="submit" class="text-green-600 hover:text-green-800 mr-2" title="Approve"><i class="fas fa-check-circle"></i></button>
                    </form>
                    <form method="post" action="reject.php" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                        <button type="submit" class="text-rose-600 hover:text-rose-800" title="Reject"><i class="fas fa-times-circle"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <?php if (!empty($adjustments)): ?>
        <tfoot class="bg-gray-50 border-t border-gray-200">
            <tr>
                <td colspan="4" class="px-6 py-3 text-xs font-medium text-gray-500 uppercase">Totals (this list)</td>
                <td class="px-6 py-3 text-right font-semibold text-gray-900"><?= taxui_amount($totalTaxable) ?></td>
                <td class="px-6 py-3 text-right font-semibold text-gray-900"><?= taxui_amount($totalTax) ?></td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
</div>

<p class="text-xs text-gray-500 mt-3">Approving an adjustment posts DR/CR to the configured tax accounts and registers it in the tax register.</p>

<?php require_once '../../../../includes/footer.php'; ?>