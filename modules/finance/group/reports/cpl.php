<?php
/** F13 — Consolidated P&L */
$pageTitle = 'Consolidated P&L';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../ConsolidationService.php';
if (!hasPermission('consolidation.report')) { die('Access Denied'); }
$db = getDB();
$runId = (int)($_GET['run'] ?? 0);
$run = $runId ? ConsolidationService::getRun($db, $runId) : null;
$pl = $run ? ConsolidationService::consolidatedPL($db, $runId) : null;
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="text-2xl font-bold text-gray-800">Consolidated Profit &amp; Loss</h1>
    <?php if ($run): ?><p class="text-gray-600"><?= sanitize($run['group_name']) ?> · <?= sanitize($run['period_label']) ?> · <?= sanitize($run['group_currency']) ?></p><?php endif; ?></div>
    <div class="flex gap-2">
        <form method="get"><select name="run" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm bg-white">
            <option value="">— select run —</option>
            <?php foreach ($db->query("SELECT id, period_label FROM consolidation_runs ORDER BY id DESC") as $r): ?>
            <option value="<?= $r['id'] ?>" <?= $r['id']==$runId?'selected':'' ?>>#<?= $r['id'] ?> <?= sanitize($r['period_label']) ?></option><?php endforeach; ?></select></form>
        <button onclick="window.print()" type="button" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm"><i class="fas fa-print mr-1"></i>Print</button>
        <a href="index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
    </div>
</div>

<?php if (!$pl): ?><p class="text-center text-gray-400 py-10">Select a run.</p>
<?php else: ?>
<div class="max-w-2xl mx-auto bg-white rounded-lg shadow p-6">
    <?php foreach (['revenue' => 'Revenue', 'expense' => 'Expenses'] as $key => $label): ?>
        <h3 class="font-semibold text-gray-700 uppercase text-xs tracking-wide mt-4 mb-2"><?= $label ?></h3>
        <table class="w-full text-sm">
            <tbody class="divide-y">
            <?php foreach ($pl['sections'][$key] as $row): ?>
                <tr><td class="py-1.5"><?= sanitize($row['code']) ?> — <?= sanitize($row['name']) ?>
                        <?php if (abs($row['elim']) > 0.004): ?><span class="text-purple-600 text-xs ml-2">(elim <?= number_format(-$row['elim'],2) ?>)</span><?php endif; ?></td>
                    <td class="py-1.5 text-right font-mono <?= $row['amount']>=0 ? ($key==='revenue'?'text-green-700':'') : 'text-red-600' ?>">
                        <?= number_format($key==='expense' ? -$row['amount'] : $row['amount'], 2) ?></td></tr>
            <?php endforeach; if (empty($pl['sections'][$key])): ?><tr><td class="py-2 text-gray-300 text-center" colspan="2">none</td></tr><?php endif; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
    <hr class="my-4">
    <div class="space-y-2 text-sm">
        <div class="flex justify-between"><span>Total Revenue</span><b class="font-mono text-green-700"><?= number_format($pl['totals']['revenue'],2) ?></b></div>
        <div class="flex justify-between"><span>Total Expenses</span><b class="font-mono text-red-600">(<?= number_format(abs($pl['totals']['expenses']),2) ?>)</b></div>
        <div class="flex justify-between text-base pt-2 border-t"><span class="font-bold">Net Profit / (Loss)</span>
            <b class="font-mono <?= $pl['totals']['net_profit']>=0?'text-green-700':'text-red-700' ?>"><?= number_format($pl['totals']['net_profit'],2) ?></b></div>
    </div>
</div>
<?php endif; ?>

<?php require_once '../../../../includes/footer.php'; ?>
