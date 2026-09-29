<?php
/**
 * F12 Report 1 — Period Closing Report
 */
$pageTitle = 'Period Closing Report';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../PeriodCloseService.php';

if (!hasPermission('closing.report')) { die('Access Denied'); }

$db = getDB();
$fys = $db->query("SELECT id, year_name FROM financial_years ORDER BY start_date DESC")->fetchAll();
$fyId = (int)($_GET['fy_id'] ?? 0);
if (!$fyId) { $a = getActiveFinancialYear($db); $fyId = $a ? (int)$a['id'] : 0; }

$periods = [];
if ($fyId) {
    $s = $db->prepare("
        SELECT p.*, u.name AS closed_by_name,
          (SELECT COUNT(*) FROM journal_entries j WHERE j.voucher_date BETWEEN p.start_date AND p.end_date) journals_in_period
        FROM accounting_periods p LEFT JOIN users u ON u.id = p.closed_by
        WHERE p.financial_year_id = ? ORDER BY p.start_date
    ");
    $s->execute([$fyId]);
    $periods = $s->fetchAll();
}
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="text-2xl font-bold text-gray-800">Period Closing Report</h1></div>
    <form method="get" class="flex gap-2">
        <select name="fy_id" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm bg-white">
            <?php foreach ($fys as $f): ?><option value="<?= $f['id'] ?>" <?= $f['id']==$fyId?'selected':'' ?>><?= sanitize($f['year_name']) ?></option><?php endforeach; ?>
        </select>
        <button onclick="window.print()" type="button" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm"><i class="fas fa-print mr-1"></i>Print</button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-3 text-left">Period</th><th class="px-4 py-3 text-left">Dates</th>
            <th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-center">Journals</th>
            <th class="px-4 py-3 text-center">Reopens</th><th class="px-4 py-3 text-left">Closed By / At</th>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($periods as $p): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-medium"><?= sanitize($p['period_name']) ?></td>
                <td class="px-4 py-2"><?= date('d M', strtotime($p['start_date'])) ?> – <?= date('d M Y', strtotime($p['end_date'])) ?></td>
                <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs font-semibold <?= $p['status']==='closed'?'bg-red-100 text-red-700':'bg-green-100 text-green-800' ?>"><?= strtoupper($p['status']) ?></span></td>
                <td class="px-4 py-2 text-center"><?= (int)$p['journals_in_period'] ?></td>
                <td class="px-4 py-2 text-center <?= $p['reopen_count']>0?'text-orange-600':'' ?>"><?= (int)$p['reopen_count'] ?></td>
                <td class="px-4 py-2 text-xs"><?= $p['closed_at'] ? date('d M Y H:i', strtotime($p['closed_at'])) . ' · ' . sanitize($p['closed_by_name'] ?? '') : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
