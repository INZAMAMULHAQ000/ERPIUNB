<?php
/** F13 — Consolidated Balance Sheet */
$pageTitle = 'Consolidated Balance Sheet';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../ConsolidationService.php';
if (!hasPermission('consolidation.report')) { die('Access Denied'); }
$db = getDB();
$runId = (int)($_GET['run'] ?? 0);
$run = $runId ? ConsolidationService::getRun($db, $runId) : null;
$bs = $run ? ConsolidationService::consolidatedBalanceSheet($db, $runId) : null;
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="text-2xl font-bold text-gray-800">Consolidated Balance Sheet</h1>
    <?php if ($run): ?><p class="text-gray-600"><?= sanitize($run['group_name']) ?> · <?= sanitize($run['period_label']) ?></p><?php endif; ?></div>
    <div class="flex gap-2">
        <form method="get"><select name="run" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm bg-white">
            <option value="">— select run —</option>
            <?php foreach ($db->query("SELECT id, period_label FROM consolidation_runs ORDER BY id DESC") as $r): ?>
            <option value="<?= $r['id'] ?>" <?= $r['id']==$runId?'selected':'' ?>>#<?= $r['id'] ?> <?= sanitize($r['period_label']) ?></option><?php endforeach; ?></select></form>
        <button onclick="window.print()" type="button" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm"><i class="fas fa-print mr-1"></i>Print</button>
        <a href="index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
    </div>
</div>

<?php if (!$bs): ?><p class="text-center text-gray-400 py-10">Select a run.</p>
<?php else: ?>
<div class="max-w-3xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
    <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-gray-500 uppercase">Assets</p><p class="font-bold font-mono"><?= number_format($bs['totals']['assets'],2) ?></p></div>
    <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-gray-500 uppercase">Liabilities</p><p class="font-bold font-mono"><?= number_format($bs['totals']['liabilities'],2) ?></p></div>
    <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-gray-500 uppercase">Equity</p><p class="font-bold font-mono"><?= number_format($bs['totals']['equity'],2) ?></p></div>
</div>

<?php foreach ([['asset'=>'Assets'], ['liability'=>'Liabilities'], ['equity'=>'Equity']] as $sec):
      $nature = array_key_first($sec); $label = $sec[$nature];
      $totalKey = $nature === 'asset' ? 'assets' : ($nature === 'liability' ? 'liabilities' : 'equity_incl_profit');
      $totalVal = (float)$bs['totals'][$totalKey]; ?>
<div class="max-w-3xl mx-auto bg-white rounded-lg shadow p-5 mb-4">
    <h3 class="font-semibold text-gray-800 uppercase text-xs mb-2"><?= $label ?></h3>
    <table class="w-full text-sm">
        <tbody class="divide-y">
        <?php foreach ($bs['sections'][$nature] ?? [] as $r): ?>
            <tr><td class="py-1"><?= sanitize($r['code']) ?> — <?= sanitize($r['name']) ?></td>
                <td class="py-1 text-right font-mono <?= $r['signed']<0?'text-red-600':'' ?>"><?= number_format($nature==='asset'?$r['signed']:-$r['signed'],2) ?></td></tr>
        <?php endforeach; if (empty($bs['sections'][$nature])): ?><tr><td colspan="2" class="py-2 text-center text-gray-300">none</td></tr><?php endif; ?>
        <?php if ($nature === 'equity'): ?>
            <tr><td class="py-1 font-medium">Profit for the period</td>
                <td class="py-1 text-right font-mono text-green-700"><?= number_format((float)$bs['totals']['profit_for_period'],2) ?></td></tr>
        <?php endif; ?>
        </tbody>
        <tfoot><tr class="border-t-2 font-bold"><td class="pt-2">Total <?= $label ?></td>
            <td class="pt-2 text-right font-mono"><?= number_format($totalVal,2) ?></td></tr></tfoot>
    </table>
</div>
<?php endforeach; ?>

<div class="max-w-3xl mx-auto rounded-lg p-4 text-sm font-semibold <?= $bs['totals']['balanced']?'bg-green-50 text-green-800 border border-green-200':'bg-red-50 text-red-800 border border-red-200' ?>">
    Assets = Liabilities + Equity + Profit :
    <?= number_format($bs['totals']['assets'],2) ?> = <?= number_format($bs['totals']['liabilities']+$bs['totals']['equity_incl_profit'],2) ?>
    → <?= $bs['totals']['balanced']?'BALANCED ✓':'OUT OF BALANCE ✗' ?>
</div>
<?php endif; ?>

<?php require_once '../../../../includes/footer.php'; ?>
