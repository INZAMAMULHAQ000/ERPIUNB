<?php
/**
 * F12 — Reconciliation Status & Validation Runner (STEP 4 / STEP 10 preview)
 */
$pageTitle = 'Reconciliation & Validation';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../PeriodCloseService.php';
require_once __DIR__ . '/../YearEndClosingService.php';

if (!hasPermission('period.view')) { die('Access Denied'); }

$db = getDB();
$fys = $db->query("SELECT id, year_name FROM financial_years ORDER BY start_date DESC")->fetchAll();
$fyId = (int)($_GET['fy_id'] ?? 0);
if (!$fyId) { $a = getActiveFinancialYear($db); $fyId = $a ? (int)$a['id'] : 0; }

// Run year validation?
$yearValidation = null;
if ($fyId && isset($_GET['run_year_validation']) && ($_SERVER['REQUEST_METHOD'] === 'GET')) {
    if (hasPermission('year.close') || hasPermission('closing.report')) {
        $yearValidation = YearEndClosingService::validateYearEnd($db, $fyId);
    } else {
        flash('error', 'No permission to run year-end validation.');
    }
}

// Per-period latest validations for this FY
$periodRows = [];
if ($fyId) {
    $s = $db->prepare("
        SELECT p.id, p.period_name, p.status,
          (SELECT COUNT(*) FROM journal_entries j WHERE j.voucher_date BETWEEN p.start_date AND p.end_date AND j.status='draft') drafts,
          (SELECT COALESCE(SUM(debit),0) FROM general_ledger gl WHERE gl.posting_date BETWEEN p.start_date AND p.end_date) dr,
          (SELECT COALESCE(SUM(credit),0) FROM general_ledger gl WHERE gl.posting_date BETWEEN p.start_date AND p.end_date) cr,
          (SELECT SUM(ci.status='completed') FROM period_checklist_items ci WHERE ci.financial_year_id=p.financial_year_id AND ci.period_id=p.id) chk_done,
          (SELECT COUNT(*) FROM period_checklist_items ci WHERE ci.financial_year_id=p.financial_year_id AND ci.period_id=p.id) chk_total
        FROM accounting_periods p WHERE p.financial_year_id = ? ORDER BY p.start_date
    ");
    $s->execute([$fyId]);
    $periodRows = $s->fetchAll();
}
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Reconciliation &amp; Validation</h1>
        <p class="text-gray-600">Automated checks + reconciliation attestations per period</p>
    </div>
    <div class="flex gap-2">
        <?php if ($fyId && (hasPermission('year.close') || hasPermission('closing.report'))): ?>
        <a href="?fy_id=<?= $fyId ?>&run_year_validation=1" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm"><i class="fas fa-play mr-1"></i>Run Year Validation</a>
        <?php endif; ?>
        <a href="../index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
    </div>
</div>

<form method="get" class="mb-4">
    <select name="fy_id" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm bg-white">
        <?php foreach ($fys as $f): ?><option value="<?= $f['id'] ?>" <?= $f['id']==$fyId?'selected':'' ?>><?= sanitize($f['year_name']) ?></option><?php endforeach; ?>
    </select>
</form>

<?php if ($yearValidation): ?>
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-4 py-3 border-b flex justify-between items-center">
        <h3 class="font-semibold text-gray-800">Year-End Validation Result</h3>
        <span class="px-3 py-1 rounded text-sm font-semibold <?= $yearValidation['passed']?'bg-green-100 text-green-700':'bg-red-100 text-red-700' ?>">
            <?= $yearValidation['passed']?'PASSED':'FAILED' ?>
        </span>
    </div>
    <table class="w-full text-sm">
        <tbody class="divide-y">
        <?php foreach ($yearValidation['checks'] as $c): ?>
            <tr>
                <td class="px-4 py-2 w-10 text-center">
                    <?php if ($c['status']==='pass'): ?><i class="fas fa-circle-check text-green-500"></i>
                    <?php elseif ($c['status']==='warn'): ?><i class="fas fa-triangle-exclamation text-yellow-500"></i>
                    <?php else: ?><i class="fas fa-circle-xmark text-red-500"></i><?php endif; ?>
                </td>
                <td class="px-4 py-2 font-medium text-gray-800 w-72"><?= sanitize($c['name']) ?></td>
                <td class="px-4 py-2 text-gray-600"><?= sanitize($c['detail']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800">Per-Period Reconciliation Snapshot</h3></div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="px-4 py-3 text-left">Period</th><th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-center">Drafts</th><th class="px-4 py-3 text-right">Debits</th>
                <th class="px-4 py-3 text-right">Credits</th><th class="px-4 py-3 text-left">Dr=Cr</th>
                <th class="px-4 py-3 text-center">Checklist</th><th class="px-4 py-3 text-right"></th>
            </tr>
        </thead>
        <tbody class="divide-y">
        <?php foreach ($periodRows as $p):
            $balDiff = abs((float)$p['dr'] - (float)$p['cr']);
            $balanced = $balDiff < 0.001 && ((float)$p['dr'] + (float)$p['cr']) > 0;
        ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium"><?= sanitize($p['period_name']) ?></td>
                <td class="px-4 py-3"><span class="px-2 py-1 rounded text-xs font-semibold <?= $p['status']==='closed'?'bg-red-100 text-red-700':'bg-green-100 text-green-800' ?>"><?= strtoupper($p['status']) ?></span></td>
                <td class="px-4 py-3 text-center <?= $p['drafts']>0?'text-orange-600 font-semibold':'text-green-600' ?>"><?= (int)$p['drafts'] ?: '✓' ?></td>
                <td class="px-4 py-3 text-right font-mono"><?= number_format((float)$p['dr'], 2) ?></td>
                <td class="px-4 py-3 text-right font-mono"><?= number_format((float)$p['cr'], 2) ?></td>
                <td class="px-4 py-3"><?= ((float)$p['dr']+(float)$p['cr'])==0 ? '<span class="text-gray-300">no activity</span>' : ($balanced ? '<i class="fas fa-circle-check text-green-500"></i>' : '<i class="fas fa-circle-xmark text-red-500"></i>') ?></td>
                <td class="px-4 py-3 text-center text-xs"><?= $p['chk_total'] ? "{$p['chk_done']}/{$p['chk_total']}" : '—' ?></td>
                <td class="px-4 py-3 text-right"><a href="../checklist/index.php?fy_id=<?= $fyId ?>&period_id=<?= $p['id'] ?>" class="text-blue-600 hover:underline text-xs">Checklist →</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
