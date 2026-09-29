<?php
/**
 * F12 — Closing History & Audit Trail — STEP 20
 */
$pageTitle = 'Closing History';
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

$audit = [];
if ($fyId) {
    $s = $db->prepare("
        SELECT pa.*, u.name AS username FROM period_close_audit pa
        LEFT JOIN users u ON u.id = pa.user_id
        WHERE pa.financial_year_id = ? OR pa.financial_year_id IS NULL
        ORDER BY pa.created_at DESC LIMIT 200
    ");
    // scope strictly to the FY
    $s = $db->prepare("
        SELECT pa.*, u.name AS username FROM period_close_audit pa
        LEFT JOIN users u ON u.id = pa.user_id
        WHERE pa.financial_year_id = ?
        ORDER BY pa.created_at DESC LIMIT 200
    ");
    $s->execute([$fyId]);
    $audit = $s->fetchAll();
}

// Year-end closing records across ALL years
$closings = $db->query("
    SELECT y.*, f.year_name, je.voucher_no,
           cu.name AS closed_by_name, ru.name AS reopened_by_name
    FROM year_end_closings y
    JOIN financial_years f ON f.id = y.financial_year_id
    LEFT JOIN journal_entries je ON je.id = y.closing_journal_id
    LEFT JOIN users cu ON cu.id = y.closed_by
    LEFT JOIN users ru ON ru.id = y.reopened_by
    ORDER BY f.start_date DESC
")->fetchAll();

$actionLabels = [
    'period_closed'=>'Period Closed','period_reopened'=>'Period Reopened','validation_run'=>'Validation Run',
    'checklist_completed'=>'Checklist Completed','year_validation_started'=>'Year Validation Started',
    'year_validation_completed'=>'Year Validation Completed','year_closed'=>'Year Closed',
    'year_reopened'=>'Year Reopened','opening_balances_generated'=>'Opening Balances Generated',
    'closing_journal_reversed'=>'Closing Journal Reversed','adjustment_created'=>'Adjustment Created',
    'adjustment_submitted'=>'Adjustment Submitted','adjustment_approved'=>'Adjustment Approved',
    'adjustment_rejected'=>'Adjustment Rejected','adjustment_posted'=>'Adjustment Posted',
    'settings_updated'=>'Settings Updated','fy_created'=>'FY Created','fy_activated'=>'FY Activated','fy_closing_started'=>'FY Closing Started',
];
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Closing History &amp; Audit Trail</h1>
        <p class="text-gray-600">Every open / close / reopen / adjustment event with user, timestamp, reason and IP</p>
    </div>
    <a href="../index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
</div>

<!-- Year-end closings -->
<div class="bg-white rounded-lg shadow mb-6 overflow-x-auto">
    <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800">Year-End Closings</h3></div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-3 text-left">Year</th><th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Closing JE</th><th class="px-4 py-3 text-right">Net P/L</th>
            <th class="px-4 py-3 text-left">Verified</th><th class="px-4 py-3 text-left">Closed By</th>
            <th class="px-4 py-3 text-left">Reopened By</th><th class="px-4 py-3"></th>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($closings as $c): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium"><?= sanitize($c['year_name']) ?></td>
                <td class="px-4 py-3"><span class="px-2 py-1 rounded text-xs font-semibold <?= $c['status']==='completed'?'bg-green-100 text-green-700':'bg-orange-100 text-orange-700' ?>"><?= strtoupper($c['status']) ?></span></td>
                <td class="px-4 py-3 font-mono text-xs"><?= sanitize($c['voucher_no'] ?? '—') ?></td>
                <td class="px-4 py-3 text-right font-mono <?= $c['net_profit']>=0?'text-green-600':'text-red-600' ?>"><?= formatMoney($c['net_profit']) ?></td>
                <td class="px-4 py-3"><?= $c['verification_passed'] ? '<i class="fas fa-circle-check text-green-500"></i>' : '<i class="fas fa-circle-xmark text-red-500"></i>' ?></td>
                <td class="px-4 py-3 text-xs"><?= sanitize($c['closed_by_name'] ?? '') ?><br><?= date('d M Y H:i', strtotime($c['closed_at'])) ?></td>
                <td class="px-4 py-3 text-xs"><?= $c['reopened_by_name'] ? sanitize($c['reopened_by_name']).'<br>'.date('d M Y H:i', strtotime($c['reopened_at'])) : '—' ?>
                    <?php if ($c['reopen_reason']): ?><br><span class="text-gray-400 italic"><?= sanitize(mb_strimwidth($c['reopen_reason'],0,60,'…')) ?></span><?php endif; ?>
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <a href="../reports/year_end.php?fy_id=<?= $c['financial_year_id'] ?>" class="text-blue-600 hover:underline text-xs">Report</a>
                    <?php
                    if ($c['status'] === 'reopened' && $c['closing_journal_id'] && hasPermission('year.reopen')) {
                        $jst = $db->prepare("SELECT status FROM journal_entries WHERE id = ?");
                        $jst->execute([(int)$c['closing_journal_id']]);
                        if ($jst->fetchColumn() === 'posted') {
                    ?>
                        <form method="post" action="../closing/revjournal.php" class="inline" onsubmit="this.reason.value = prompt('Reason for reversing the closing journal?') || ''; return this.reason.value.trim() !== '';">
                            <?= csrfToken() ?><input type="hidden" name="fy_id" value="<?= $c['financial_year_id'] ?>">
                            <input type="hidden" name="reason" value="">
                            <button class="text-orange-600 hover:underline text-xs ml-2">Reverse JE</button>
                        </form>
                    <?php } } ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($closings)): ?><tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No year-end closings recorded yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Audit trail -->
<form method="get" class="mb-3">
    <select name="fy_id" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm bg-white">
        <?php foreach ($fys as $f): ?><option value="<?= $f['id'] ?>" <?= $f['id']==$fyId?'selected':'' ?>><?= sanitize($f['year_name']) ?></option><?php endforeach; ?>
    </select>
</form>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800">Audit Trail (latest 200)</h3></div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-3 text-left">When</th><th class="px-4 py-3 text-left">Event</th>
            <th class="px-4 py-3 text-left">User</th><th class="px-4 py-3 text-left">Details</th>
            <th class="px-4 py-3 text-left">Reason</th><th class="px-4 py-3 text-left">IP</th>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($audit as $a): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 text-xs text-gray-500 whitespace-nowrap"><?= date('d M Y H:i:s', strtotime($a['created_at'])) ?></td>
                <td class="px-4 py-2 font-medium"><?= sanitize($actionLabels[$a['action']] ?? ucwords(str_replace('_',' ',$a['action']))) ?></td>
                <td class="px-4 py-2"><?= sanitize($a['username'] ?? 'system') ?></td>
                <td class="px-4 py-2 text-xs text-gray-600 max-w-md truncate" title="<?= sanitize($a['details'] ?? '') ?>"><?= sanitize(mb_strimwidth($a['details'] ?? '', 0, 120, '…')) ?></td>
                <td class="px-4 py-2 text-xs italic text-gray-500"><?= sanitize(mb_strimwidth($a['reason'] ?? '', 0, 80, '…')) ?></td>
                <td class="px-4 py-2 text-xs text-gray-400"><?= sanitize($a['ip_address'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($audit)): ?><tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No audit events yet for this year.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
