<?php
/** F13 — Consolidation Workspace: validate → eliminate → statements → approve → post */
$pageTitle = 'Consolidation Workspace';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../ConsolidationService.php';

if (!hasPermission('company.view')) { die('Access Denied'); }
$db = getDB();
$UID = $_SESSION['user_id'];
$runId = (int)($_GET['id'] ?? 0);
$run = ConsolidationService::getRun($db, $runId);
if (!$run) { flash('error','Run not found.'); to('modules/finance/group/consolidation/runs.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error','Invalid request.'); to("modules/finance/group/consolidation/workspace.php?id=$runId"); }
    $action = $_POST['action'] ?? '';
    if ($action === 'validate' && hasPermission('consolidation.validate')) {
        ConsolidationService::attachRates($db, $runId);
        $v = ConsolidationService::validate($db, $runId);
        $db->prepare("UPDATE consolidation_runs SET status=IF(status='draft','in_progress',status), validation_snapshot=? WHERE id=?")
           ->execute([json_encode($v), $runId]);
        if ($v['passed']) {
            $db->prepare("UPDATE consolidation_runs SET status='validated' WHERE id=? AND status IN ('draft','in_progress')")->execute([$runId]);
            ConsolidationService::log($db,'consolidation_validated',null,$runId,'All checks passed');
            flash('success','Validation PASSED.');
        } else {
            foreach ($v['checks'] as $c) if ($c['status']==='fail') flash('error','✗ '.$c['name'].': '.$c['detail']);
        }
    } elseif ($action === 'eliminate' && hasPermission('consolidation.validate')) {
        $js = ConsolidationService::buildEliminations($db, $runId);
        $saved = ConsolidationService::persistJournals($db, $runId, $js, $UID);
        flash('info', "$saved elimination journal(s) generated (duplicates skipped).");
    } elseif ($action === 'approve' && hasPermission('consolidation.approve')) {
        $db->prepare("UPDATE consolidation_runs SET status='approved', approved_by=?, approved_at=NOW() WHERE id=? AND status='validated'")->execute([$UID,$runId]);
        ConsolidationService::log($db,'consolidation_approved',null,$runId,null,null,$UID);
        flash('success','Run approved.');
    } elseif ($action === 'post' && hasPermission('consolidation.post')) {
        $res = ConsolidationService::postRun($db, $runId, $UID);
        flash($res['success'] ? 'success' : 'error', $res['message']);
    }
    to("modules/finance/group/consolidation/workspace.php?id=$runId");
}

$v = json_decode($run['validation_snapshot'] ?? '', true) ?: ['checks'=>[],'passed'=>false];
$cos = ConsolidationService::runCompanies($db, $runId);
$statusColors = ['draft'=>'bg-gray-100 text-gray-600','in_progress'=>'bg-blue-100 text-blue-700','validated'=>'bg-cyan-100 text-cyan-700',
                 'approved'=>'bg-green-100 text-green-700','posted'=>'bg-emerald-100 text-emerald-700','cancelled'=>'bg-red-100 text-red-700'];
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Workspace — Run #<?= (int)$runId ?> <?= sanitize($run['period_label']) ?></h1>
        <p class="text-gray-600"><?= sanitize($run['group_name']) ?> · <?= date('d M Y', strtotime($run['start_date'])) ?> – <?= date('d M Y', strtotime($run['end_date'])) ?>
           · method: <b><?= strtoupper($run['translation_method']) ?></b></p>
    </div>
    <div class="flex items-center gap-3">
        <span class="px-3 py-1 rounded text-sm font-semibold <?= $statusColors[$run['status']] ?>"><?= strtoupper($run['status']) ?></span>
        <a href="runs.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
    </div>
</div>

<!-- Workflow buttons -->
<?php if (!in_array($run['status'], ['posted','cancelled'])): ?>
<div class="bg-white rounded-lg shadow p-4 mb-6 flex flex-wrap gap-2">
    <?php if (hasPermission('consolidation.validate')): ?>
    <form method="post"><?= csrfToken() ?><input type="hidden" name="action" value="validate">
        <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm"><i class="fas fa-play mr-1"></i>Validate &amp; Load</button></form>
    <form method="post" onsubmit="return confirm('Generate eliminations? Duplicates are skipped automatically.')"><?= csrfToken() ?><input type="hidden" name="action" value="eliminate">
        <button class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 text-sm"><i class="fas fa-scissors mr-1"></i>Build Eliminations</button></form>
    <?php endif; ?>
    <?php if (hasPermission('consolidation.approve')): ?>
    <form method="post" onsubmit="return confirm('Approve this consolidation run?')"><?= csrfToken() ?><input type="hidden" name="action" value="approve">
        <button class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 text-sm"><i class="fas fa-check mr-1"></i>Approve</button></form>
    <?php endif; ?>
    <?php if (hasPermission('consolidation.post')): ?>
    <form method="post" onsubmit="return confirm('POST consolidated statements? This run can never post again.')"><?= csrfToken() ?><input type="hidden" name="action" value="post">
        <button class="bg-emerald-700 text-white px-4 py-2 rounded hover:bg-emerald-800 text-sm"><i class="fas fa-lock mr-1"></i>Post (final)</button></form>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 mb-6 text-sm text-emerald-800">
    <i class="fas fa-circle-check mr-1"></i>This run was POSTED on <?= date('d M Y H:i', strtotime($run['posted_at'])) ?> and is immutable.
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Companies in scope -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800">Companies in Scope</h3></div>
        <table class="w-full text-sm"><tbody class="divide-y">
        <?php foreach ($cos as $co): ?>
            <tr><td class="px-4 py-2"><span class="font-mono text-xs"><?= sanitize($co['company_code']) ?></span> <?= sanitize($co['company_name']) ?></td>
                <td class="px-4 py-2 text-right font-mono text-xs">rate <?= number_format((float)$co['exchange_rate'],4) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>

    <!-- Validation -->
    <div class="lg:col-span-2 bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b flex justify-between items-center">
            <h3 class="font-semibold text-gray-800">Validation</h3>
            <span class="px-2 py-0.5 rounded text-xs font-semibold <?= !empty($v['passed'])?'bg-green-100 text-green-700':'bg-yellow-100 text-yellow-700' ?>">
                <?= empty($v['checks']) ? 'NOT RUN' : (!empty($v['passed'])?'PASSED':'FAILED') ?></span>
        </div>
        <table class="w-full text-sm"><tbody class="divide-y max-h-48 overflow-y-auto">
        <?php foreach ($v['checks'] as $c): ?>
            <tr><td class="px-4 py-1.5 w-8"><?= $c['status']==='pass'?'<i class="fas fa-circle-check text-green-500"></i>':'<i class="fas fa-circle-xmark text-red-500"></i>' ?></td>
                <td class="px-4 py-1.5 font-medium"><?= sanitize($c['name']) ?></td><td class="px-4 py-1.5 text-xs text-gray-500"><?= sanitize($c['detail']) ?></td></tr>
        <?php endforeach; if (empty($v['checks'])): ?><tr><td class="px-4 py-4 text-center text-gray-400" colspan="3">Run Validate &amp; Load first.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>

<?php
// Statements preview
$ctb = ConsolidationService::consolidatedTrialBalance($db, $runId);
$dr = array_sum(array_column($ctb,'total_debit')); $cr = array_sum(array_column($ctb,'total_credit'));
$pl = ConsolidationService::consolidatedPL($db, $runId);
$bs = ConsolidationService::consolidatedBalanceSheet($db, $runId);
$nci = ConsolidationService::nciShares($db, $runId);
$journals = $db->query("SELECT * FROM consolidation_journals WHERE run_id=$runId ORDER BY id")->fetchAll();
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold text-gray-800 mb-2 text-sm uppercase text-gray-400">Consolidated P&amp;L</h3>
        <div class="space-y-1 text-sm">
            <div class="flex justify-between"><span class="text-gray-500">Revenue</span><b class="font-mono text-green-600"><?= number_format($pl['totals']['revenue'],2) ?></b></div>
            <div class="flex justify-between"><span class="text-gray-500">Expenses</span><b class="font-mono text-red-600"><?= number_format($pl['totals']['expenses'],2) ?></b></div>
            <hr>
            <div class="flex justify-between"><span class="font-semibold">Net Profit</span><b class="font-mono <?= $pl['totals']['net_profit']>=0?'text-green-700':'text-red-700' ?>"><?= number_format($pl['totals']['net_profit'],2) ?></b></div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold text-gray-800 mb-2 text-sm uppercase text-gray-400">Balance Sheet Check</h3>
        <div class="space-y-1 text-sm">
            <div class="flex justify-between"><span class="text-gray-500">Assets</span><b class="font-mono"><?= number_format($bs['totals']['assets'],2) ?></b></div>
            <div class="flex justify-between"><span class="text-gray-500">Liabilities</span><b class="font-mono"><?= number_format($bs['totals']['liabilities'],2) ?></b></div>
            <div class="flex justify-between"><span class="text-gray-500">Equity</span><b class="font-mono"><?= number_format($bs['totals']['equity'],2) ?></b></div>
            <hr>
            <div class="flex justify-between"><span class="font-semibold">A = L + E</span>
                <b class="<?= $bs['totals']['balanced']?'text-green-600':'text-red-600' ?>"><?= $bs['totals']['balanced']?'BALANCED':'OFF' ?></b></div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold text-gray-800 mb-2 text-sm uppercase text-gray-400">Eliminations &amp; NCI</h3>
        <div class="space-y-1 text-sm">
            <div class="flex justify-between"><span class="text-gray-500">Elimination journals</span><b><?= count($journals) ?></b></div>
            <div class="flex justify-between"><span class="text-gray-500">Eliminated amount</span><b class="font-mono"><?= number_format(array_sum(array_map(fn($j)=>(float)$j['amount'],$journals)),2) ?></b></div>
            <div class="flex justify-between"><span class="text-gray-500">NCI total</span><b class="font-mono"><?= number_format(array_sum(array_column($nci,'nci_amount')),2) ?></b></div>
        </div>
    </div>
</div>

<!-- Consolidated TB -->
<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <div class="px-4 py-3 border-b flex justify-between items-center flex-wrap gap-2">
        <h3 class="font-semibold text-gray-800">Consolidated Trial Balance</h3>
        <span class="text-sm font-medium <?= abs($dr-$cr)<0.01?'text-green-600':'text-red-600' ?>">Dr <?= number_format($dr,2) ?> / Cr <?= number_format($cr,2) ?></span>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-3 py-2 text-left">Code</th><th class="px-3 py-2 text-left">Account</th>
            <?php foreach ($cos as $co): if (!$co['included']) continue; ?>
            <th class="px-3 py-2 text-right"><?= sanitize($co['company_code']) ?></th>
            <?php endforeach; ?>
            <th class="px-3 py-2 text-right">Elim.</th><th class="px-3 py-2 text-right">Net</th><th class="px-3 py-2 text-left">Type</th>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($ctb as $a):
              $net = round($a['total_debit'] - $a['total_credit'], 2); ?>
            <tr class="hover:bg-gray-50">
                <td class="px-3 py-1.5 font-mono text-xs"><?= sanitize($a['code']) ?></td>
                <td class="px-3 py-1.5"><?= sanitize($a['name']) ?></td>
                <?php foreach ($cos as $co): if (!$co['included']) continue; $pc = $a['per_company'][$co['company_id']] ?? null;
                      $v = $pc ? round($pc['debit']-$pc['credit'],2) : null; ?>
                    <td class="px-3 py-1.5 text-right font-mono text-xs <?= $v===null?'text-gray-300':'' ?>"><?= $v!==null?number_format($v,2):'—' ?></td>
                <?php endforeach; ?>
                <td class="px-3 py-1.5 text-right font-mono text-xs text-purple-600"><?= ($a['elim_debit']||$a['elim_credit'])?number_format($a['elim_debit']-$a['elim_credit'],2):'' ?></td>
                <td class="px-3 py-1.5 text-right font-mono font-semibold"><?= number_format($net,2) ?></td>
                <td class="px-3 py-1.5"><span class="text-xs capitalize text-gray-400"><?= sanitize($a['nature']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Eliminations detail -->
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800">Elimination / Adjustment Journals</h3></div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-2">#</th><th class="px-4 py-2 text-left">Type</th><th class="px-4 py-2 text-left">Description</th>
            <th class="px-4 py-2 text-right">Amount</th><th class="px-4 py-2 text-left">Pair Key</th>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($journals as $j): ?>
            <tr><td class="px-4 py-2"><?= (int)$j['id'] ?></td>
                <td class="px-4 py-2 text-xs uppercase"><?= sanitize(str_replace('_',' ',$j['adjustment_type'])) ?></td>
                <td class="px-4 py-2"><?= sanitize($j['description']) ?></td>
                <td class="px-4 py-2 text-right font-mono"><?= number_format((float)$j['amount'],2) ?></td>
                <td class="px-4 py-2 font-mono text-xs text-gray-400"><?= sanitize($j['partner_pair_key'] ?? '') ?></td></tr>
        <?php endforeach; if (empty($journals)): ?><tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">No eliminations yet — use “Build Eliminations”.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
