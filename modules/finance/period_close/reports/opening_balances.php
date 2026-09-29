<?php
/**
 * F12 Report 4 — Opening Balance Report (carry-forward verification)
 */
$pageTitle = 'Opening Balance Report';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';

if (!hasPermission('closing.report')) { die('Access Denied'); }

$db = getDB();
$fys = $db->query("SELECT id, year_name FROM financial_years ORDER BY start_date DESC")->fetchAll();
$fyId = (int)($_GET['fy_id'] ?? 0);
if (!$fyId) { $a = getActiveFinancialYear($db); $fyId = $a ? (int)$a['id'] : 0; }

$yec = null; $rows = [];
if ($fyId) {
    $s = $db->prepare("SELECT y.*, f2.year_name prev_year FROM year_end_closings y JOIN financial_years f2 ON f2.id=y.financial_year_id WHERE y.next_financial_year_id=?");
    $s->execute([$fyId]);
    $yec = $s->fetch();

    $s2 = $db->prepare("
        SELECT po.*, coa.account_code, coa.account_name, ag.nature
        FROM period_opening_balances po
        JOIN chart_of_accounts coa ON coa.id = po.account_id
        JOIN account_groups ag ON ag.id = coa.group_id
        WHERE po.financial_year_id = ? ORDER BY coa.account_code
    ");
    $s2->execute([$fyId]);
    $rows = $s2->fetchAll();
}
$totD = array_sum(array_column($rows,'debit')); $totC = array_sum(array_column($rows,'credit'));
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="text-2xl font-bold text-gray-800">Opening Balance Report</h1>
    <p class="text-gray-600 text-sm">STEP 15 — New-year opening must equal previous-year closing for every permanent account.</p></div>
    <form method="get" class="flex gap-2">
        <select name="fy_id" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm bg-white">
            <?php foreach ($fys as $f): ?><option value="<?= $f['id'] ?>" <?= $f['id']==$fyId?'selected':'' ?>><?= sanitize($f['year_name']) ?></option><?php endforeach; ?>
        </select>
        <button onclick="window.print()" type="button" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm"><i class="fas fa-print mr-1"></i>Print</button>
    </form>
</div>

<?php if (empty($rows)): ?>
<p class="text-center text-gray-400 py-10">No opening balances generated for this year yet.</p>
<?php else: ?>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-gray-500 uppercase">Carried From</p><p class="font-bold"><?= sanitize($yec['prev_year'] ?? '') ?></p></div>
    <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-gray-500 uppercase">Accounts</p><p class="font-bold"><?= count($rows) ?></p></div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 <?= abs($totD-$totC)<0.001?'border-green-500':'border-red-500' ?>">
        <p class="text-xs text-gray-500 uppercase">Total Dr / Cr</p>
        <p class="font-bold font-mono text-sm"><?= number_format((float)$totD,2) ?> / <?= number_format((float)$totC,2) ?></p></div>
    <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-gray-500 uppercase">Verification</p>
        <p class="font-bold <?= $yec && $yec['verification_passed']?'text-green-600':'text-red-600' ?>"><?= $yec ? ($yec['verification_passed']?'PASSED':'FAILED') : '—' ?></p></div>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-3 text-left">Account Code</th><th class="px-4 py-3 text-left">Account Name</th>
            <th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-right">Opening Debit</th>
            <th class="px-4 py-3 text-right">Opening Credit</th>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($rows as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-mono"><?= sanitize($r['account_code']) ?></td>
                <td class="px-4 py-2"><?= sanitize($r['account_name']) ?></td>
                <td class="px-4 py-2 text-xs capitalize"><?= sanitize($r['nature']) ?></td>
                <td class="px-4 py-2 text-right font-mono"><?= $r['debit']>0?number_format((float)$r['debit'],2):'' ?></td>
                <td class="px-4 py-2 text-right font-mono"><?= $r['credit']>0?number_format((float)$r['credit'],2):'' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-gray-50 font-bold"><tr>
            <td colspan="3" class="px-4 py-3">Total (Dr must equal Cr)</td>
            <td class="px-4 py-3 text-right font-mono"><?= number_format((float)$totD,2) ?></td>
            <td class="px-4 py-3 text-right font-mono"><?= number_format((float)$totC,2) ?></td>
        </tr></tfoot>
    </table>
</div>
<?php endif; ?>

<?php require_once '../../../../includes/footer.php'; ?>
