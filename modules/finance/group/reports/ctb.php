<?php
/** F13 — Consolidated Trial Balance report */
$pageTitle = 'Consolidated Trial Balance';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../ConsolidationService.php';
if (!hasPermission('consolidation.report')) { die('Access Denied'); }
$db = getDB();
$runId = (int)($_GET['run'] ?? 0);
$run = $runId ? ConsolidationService::getRun($db, $runId) : null;
$ctb = $run ? ConsolidationService::consolidatedTrialBalance($db, $runId) : [];
$cos = $run ? ConsolidationService::runCompanies($db, $runId) : [];
$dr = array_sum(array_column($ctb,'total_debit')); $cr = array_sum(array_column($ctb,'total_credit'));
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="text-2xl font-bold text-gray-800">Consolidated Trial Balance</h1>
    <?php if ($run): ?><p class="text-gray-600"><?= sanitize($run['group_name']) ?> · <?= sanitize($run['period_label']) ?></p><?php endif; ?></div>
    <div class="flex gap-2">
        <form method="get"><select name="run" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm bg-white">
            <option value="">— select run —</option>
            <?php foreach ($db->query("SELECT id, period_label FROM consolidation_runs ORDER BY id DESC") as $r): ?>
            <option value="<?= $r['id'] ?>" <?= $r['id']==$runId?'selected':'' ?>>#<?= $r['id'] ?> <?= sanitize($r['period_label']) ?></option><?php endforeach; ?>
        </select></form>
        <button onclick="window.print()" type="button" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm"><i class="fas fa-print mr-1"></i>Print</button>
        <a href="index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
    </div>
</div>

<?php if (!$run): ?><p class="text-center text-gray-400 py-10">Select a consolidation run.</p>
<?php else: ?>
<div class="bg-white rounded-lg shadow overflow-x-auto mb-4">
    <div class="px-4 py-3 border-b flex justify-between items-center flex-wrap gap-2">
        <span></span>
        <span class="font-semibold text-sm <?= abs($dr-$cr)<0.01?'text-green-600':'text-red-600' ?>">
            Consolidated: Dr <?= number_format($dr,2) ?> = Cr <?= number_format($cr,2) ?> <?= abs($dr-$cr)<0.01?'✓':'✗' ?>
        </span>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-3 text-left">Code</th><th class="px-4 py-3 text-left">Account</th>
            <?php foreach ($cos as $co): if(!$co['included']) continue; ?>
            <th class="px-4 py-3 text-right"><?= sanitize($co['company_code']) ?><br><span class="normal-case font-normal text-[10px]">rate <?= number_format((float)$co['exchange_rate'],4) ?></span></th>
            <?php endforeach; ?>
            <th class="px-4 py-3 text-right">Eliminations</th>
            <th class="px-4 py-3 text-right">Net (Consolidated)</th>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($ctb as $a):
              // per-company net
              $perNets = [];
              foreach ($cos as $co) { if(!$co['included']) continue; $pc = $a['per_company'][$co['company_id']] ?? null;
                  $perNets[] = $pc ? round($pc['debit']-$pc['credit'],2) : null; }
              $net = round($a['total_debit'] - $a['total_credit'], 2); ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-1.5 font-mono text-xs"><?= sanitize($a['code']) ?></td>
                <td class="px-4 py-1.5"><?= sanitize($a['name']) ?></td>
                <?php foreach ($perNets as $v): ?>
                    <td class="px-4 py-1.5 text-right font-mono text-xs <?= $v===null?'text-gray-300':'' ?>"><?= $v!==null?number_format($v,2):'—' ?></td>
                <?php endforeach; ?>
                <td class="px-4 py-1.5 text-right font-mono text-purple-700"><?= number_format($a['elim_debit']-$a['elim_credit'],2) ?></td>
                <td class="px-4 py-1.5 text-right font-mono font-semibold"><?= number_format($net,2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-gray-50 font-bold"><tr>
            <td colspan="2" class="px-4 py-3">TOTAL</td>
            <?php foreach ($cos as $co): if(!$co['included']) continue;
                  $sd=0;$sc=0;
                  foreach ($ctb as $a){ $pc=$a['per_company'][$co['company_id']]??null; if($pc){$sd+=$pc['debit'];$sc+=$pc['credit'];} } ?>
                <td class="px-4 py-3 text-right font-mono"><?= number_format(round($sd-$sc,2),2) ?></td>
            <?php endforeach; ?>
            <td class="px-4 py-3 text-right font-mono"><?= number_format(array_sum(array_map(fn($a)=>$a['elim_debit']-$a['elim_credit'],$ctb)),2) ?></td>
            <td class="px-4 py-3 text-right font-mono <?= abs($dr-$cr)<0.01?'':'text-red-600' ?>"><?= number_format(round($dr-$cr,2),2) ?></td>
        </tr></tfoot>
    </table>
</div>
<?php endif; ?>

<?php require_once '../../../../includes/footer.php'; ?>
