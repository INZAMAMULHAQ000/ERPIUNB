<?php
/**
 * F12 — Opening Balances (carry-forward results + verification) — STEP 14/15
 */
$pageTitle = 'Opening Balance Carry-Forward';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../PeriodCloseService.php';

if (!hasPermission('period.view')) { die('Access Denied'); }

$db = getDB();
$fys = $db->query("SELECT id, year_name FROM financial_years ORDER BY start_date DESC")->fetchAll();
$fyId = (int)($_GET['fy_id'] ?? 0);
if (!$fyId) { $a = getActiveFinancialYear($db); $fyId = $a ? (int)$a['id'] : 0; }

$yec = null; $rows = [];
if ($fyId) {
    $s = $db->prepare("SELECT y.*, f.year_name FROM year_end_closings y JOIN financial_years f ON f.id=y.financial_year_id WHERE y.next_financial_year_id = ?");
    $s->execute([$fyId]);
    $yec = $s->fetch();

    $s2 = $db->prepare("
        SELECT po.*, coa.account_code, coa.account_name, ag.nature
        FROM period_opening_balances po
        JOIN chart_of_accounts coa ON coa.id = po.account_id
        JOIN account_groups ag ON ag.id = coa.group_id
        WHERE po.financial_year_id = ?
        ORDER BY coa.account_code
    ");
    $s2->execute([$fyId]);
    $rows = $s2->fetchAll();
}
$totD = array_sum(array_column($rows,'debit'));
$totC = array_sum(array_column($rows,'credit'));
$balanced = abs($totD-$totC) < 0.001 && $totD > 0;
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Opening Balance Carry-Forward</h1>
        <p class="text-gray-600">Permanent accounts carry forward · temporary accounts start at zero</p>
    </div>
    <a href="../index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
</div>

<form method="get" class="mb-4">
    <select name="fy_id" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm bg-white">
        <?php foreach ($fys as $f): ?><option value="<?= $f['id'] ?>" <?= $f['id']==$fyId?'selected':'' ?>><?= sanitize($f['year_name']) ?></option><?php endforeach; ?>
    </select>
</form>

<?php if (!$yec && empty($rows)): ?>
<div class="bg-white rounded-lg shadow p-8 text-center max-w-xl mx-auto">
    <i class="fas fa-forward text-gray-300 text-4xl mb-3"></i>
    <p class="text-gray-500">No carry-forward record for this financial year.</p>
    <p class="text-xs text-gray-400 mt-1">Opening balances are generated automatically when the PREVIOUS year is closed via Year-End Close.</p>
</div>
<?php else: ?>

<?php if ($yec): ?>
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
        <p class="text-xs text-gray-500 uppercase">Closed From</p>
        <p class="font-bold"><?= sanitize($yec['year_name']) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
        <p class="text-xs text-gray-500 uppercase">Net P/L Transferred</p>
        <p class="font-bold <?= $yec['net_profit']>=0?'text-green-600':'text-red-600' ?> font-mono"><?= formatMoney($yec['net_profit']) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-indigo-500">
        <p class="text-xs text-gray-500 uppercase">Accounts Carried</p>
        <p class="font-bold"><?= count($rows) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 <?= $yec['verification_passed']?'border-green-500':'border-red-500' ?>">
        <p class="text-xs text-gray-500 uppercase">Verification (STEP 15)</p>
        <p class="font-bold <?= $yec['verification_passed']?'text-green-600':'text-red-600' ?>"><?= $yec['verification_passed']?'PASSED':'FAILED' ?></p>
        <a href="../reports/opening_balances.php?fy_id=<?= $fyId ?>" class="text-xs text-blue-600 hover:underline">Report →</a>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <div class="px-4 py-3 border-b flex justify-between items-center">
        <h3 class="font-semibold text-gray-800">Carried-Forward Opening Balances</h3>
        <span class="text-sm <?= $balanced?'text-green-600':'text-red-600' ?> font-medium">
            Totals: <?= number_format((float)$totD,2) ?> Dr / <?= number_format((float)$totC,2) ?> Cr
            <?= $balanced ? ' ✓ balanced' : ' ✗ unbalanced' ?>
        </span>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-3 text-left">Account</th><th class="px-4 py-3 text-left">Type</th>
            <th class="px-4 py-3 text-right">Opening Debit</th><th class="px-4 py-3 text-right">Opening Credit</th>
            <th class="px-4 py-3 text-left">Generated</th>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($rows as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-mono text-xs"><?= sanitize($r['account_code']) ?> <span class="font-sans"><?= sanitize($r['account_name']) ?></span></td>
                <td class="px-4 py-2"><span class="text-xs px-2 py-0.5 rounded bg-gray-100 capitalize"><?= sanitize($r['nature']) ?></span></td>
                <td class="px-4 py-2 text-right font-mono"><?= $r['debit']>0 ? number_format((float)$r['debit'],2) : '' ?></td>
                <td class="px-4 py-2 text-right font-mono"><?= $r['credit']>0 ? number_format((float)$r['credit'],2) : '' ?></td>
                <td class="px-4 py-2 text-xs text-gray-400"><?= date('d M Y H:i', strtotime($r['generated_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-gray-50 font-semibold">
            <tr><td class="px-4 py-3" colspan="2">Total</td>
                <td class="px-4 py-3 text-right font-mono"><?= number_format((float)$totD,2) ?></td>
                <td class="px-4 py-3 text-right font-mono"><?= number_format((float)$totC,2) ?></td>
                <td></td></tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>

<?php require_once '../../../../includes/footer.php'; ?>
