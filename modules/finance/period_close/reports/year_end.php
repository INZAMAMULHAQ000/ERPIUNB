<?php
/**
 * F12 Report 2 — Year-End Closing Report
 */
$pageTitle = 'Year-End Closing Report';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../PeriodCloseService.php';

if (!hasPermission('closing.report')) { die('Access Denied'); }

$db = getDB();
$fys = $db->query("SELECT id, year_name FROM financial_years ORDER BY start_date DESC")->fetchAll();
$fyId = (int)($_GET['fy_id'] ?? 0);
if (!$fyId) { $a = getActiveFinancialYear($db); $fyId = $a ? (int)$a['id'] : 0; }

$yec = null; $je = null; $jeLines = [];
if ($fyId) {
    $s = $db->prepare("
        SELECT y.*, f.year_name, f.start_date, f.end_date, cu.name AS closed_by_name, re.account_name re_account
        FROM year_end_closings y
        JOIN financial_years f ON f.id = y.financial_year_id
        LEFT JOIN users cu ON cu.id = y.closed_by
        LEFT JOIN chart_of_accounts re ON re.id = y.retained_earnings_account_id
        WHERE y.financial_year_id = ?
    ");
    $s->execute([$fyId]);
    $yec = $s->fetch();

    if ($yec && $yec['closing_journal_id']) {
        $j = $db->prepare("SELECT je.*, vt.voucher_name FROM journal_entries je JOIN voucher_types vt ON vt.id=je.voucher_type_id WHERE je.id=?");
        $j->execute([(int)$yec['closing_journal_id']]);
        $je = $j->fetch();
        if ($je) {
            $l = $db->prepare("
                SELECT jed.*, coa.account_code, coa.account_name FROM journal_entry_details jed
                JOIN chart_of_accounts coa ON coa.id = jed.account_id
                WHERE jed.journal_entry_id = ? ORDER BY jed.debit_amount DESC, jed.credit_amount DESC
            ");
            $l->execute([(int)$yec['closing_journal_id']]);
            $jeLines = $l->fetchAll();
        }
    }
}
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="text-2xl font-bold text-gray-800">Year-End Closing Report</h1></div>
    <form method="get" class="flex gap-2">
        <select name="fy_id" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm bg-white">
            <?php foreach ($fys as $f): ?><option value="<?= $f['id'] ?>" <?= $f['id']==$fyId?'selected':'' ?>><?= sanitize($f['year_name']) ?></option><?php endforeach; ?>
        </select>
        <button onclick="window.print()" type="button" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm"><i class="fas fa-print mr-1"></i>Print</button>
    </form>
</div>

<?php if (!$yec): ?>
<p class="text-center text-gray-400 py-10">No year-end closing recorded for this year.</p>
<?php else: ?>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-gray-500 uppercase">Financial Year</p><p class="font-bold"><?= sanitize($yec['year_name']) ?></p><p class="text-xs text-gray-400"><?= date('d M Y', strtotime($yec['start_date'])) ?> – <?= date('d M Y', strtotime($yec['end_date'])) ?></p></div>
    <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-gray-500 uppercase">Net <?= $yec['net_profit']>=0?'Profit':'Loss' ?></p><p class="font-bold font-mono <?= $yec['net_profit']>=0?'text-green-600':'text-red-600' ?>"><?= formatMoney(abs($yec['net_profit'])) ?></p></div>
    <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-gray-500 uppercase">Retained Earnings A/C</p><p class="font-bold text-sm"><?= sanitize($yec['re_account'] ?? '—') ?></p><p class="text-xs text-gray-400"><?= sanitize(str_replace('_',' ',$yec['closing_method'])) ?></p></div>
    <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-gray-500 uppercase">Closed By / At</p><p class="font-bold"><?= sanitize($yec['closed_by_name'] ?? '') ?></p><p class="text-xs text-gray-400"><?= date('d M Y H:i', strtotime($yec['closed_at'])) ?></p></div>
</div>

<?php if ($je): ?>
<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <div class="px-4 py-3 border-b flex justify-between items-center flex-wrap gap-2">
        <h3 class="font-semibold text-gray-800">Closing Journal — <span class="font-mono"><?= sanitize($je['voucher_no']) ?></span></h3>
        <span class="text-sm text-gray-500"><?= sanitize($je['voucher_name']) ?> · dated <?= date('d M Y', strtotime($je['voucher_date'])) ?> · ref <?= sanitize($je['reference_no'] ?? '') ?></span>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-3 text-left">Account</th><th class="px-4 py-3 text-left">Description</th>
            <th class="px-4 py-3 text-right">Debit</th><th class="px-4 py-3 text-right">Credit</th>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($jeLines as $l): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2"><span class="font-mono text-xs"><?= sanitize($l['account_code']) ?></span> <?= sanitize($l['account_name']) ?></td>
                <td class="px-4 py-2 text-gray-500 text-xs"><?= sanitize($l['description']) ?></td>
                <td class="px-4 py-2 text-right font-mono"><?= $l['debit_amount']>0?number_format((float)$l['debit_amount'],2):'' ?></td>
                <td class="px-4 py-2 text-right font-mono"><?= $l['credit_amount']>0?number_format((float)$l['credit_amount'],2):'' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-gray-50 font-semibold"><tr>
            <td colspan="2" class="px-4 py-3">Totals (must balance)</td>
            <td class="px-4 py-3 text-right font-mono"><?= number_format(array_sum(array_column($jeLines,'debit_amount')),2) ?></td>
            <td class="px-4 py-3 text-right font-mono"><?= number_format(array_sum(array_column($jeLines,'credit_amount')),2) ?></td>
        </tr></tfoot>
    </table>
</div>
<?php endif; ?>

<!-- Validation snapshot -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800">Validation Snapshot at Close</h3></div>
    <?php
    $snap = json_decode($yec['validation_snapshot'] ?? '', true);
    $checks = $snap['validation']['checks'] ?? [];
    ?>
    <table class="w-full text-sm"><tbody class="divide-y">
    <?php foreach ($checks as $c): ?>
        <tr>
            <td class="px-4 py-2 w-10 text-center"><?= $c['status']==='pass'?'<i class="fas fa-circle-check text-green-500"></i>':'<i class="fas fa-circle-xmark text-red-500"></i>' ?></td>
            <td class="px-4 py-2 font-medium w-72"><?= sanitize($c['name']) ?></td>
            <td class="px-4 py-2 text-gray-600"><?= sanitize($c['detail']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table>
</div>

<!-- Carry-forward summary -->
<?php $cf = $snap['carry_forward'] ?? null; ?>
<?php if ($cf): ?>
<div class="bg-white rounded-lg shadow p-4">
    <h3 class="font-semibold text-gray-800 mb-2">Carry-Forward Summary</h3>
    <p class="text-sm text-gray-600">New year: <b><?= sanitize($cf['next_fy_name'] ?? '') ?></b>
       (<?= !empty($cf['created_fy']) ? 'auto-created' : 'pre-existing' ?>) ·
       <?= count($cf['rows'] ?? []) ?> accounts ·
       Dr <?= number_format($cf['totals']['debit'] ?? 0,2) ?> / Cr <?= number_format($cf['totals']['credit'] ?? 0,2) ?> ·
       Verification: <b class="<?= !empty($cf['verification_passed'])?'text-green-600':'text-red-600' ?>"><?= !empty($cf['verification_passed'])?'PASSED':'FAILED' ?></b></p>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once '../../../../includes/footer.php'; ?>
