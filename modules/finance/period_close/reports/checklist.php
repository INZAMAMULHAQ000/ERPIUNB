<?php
/**
 * F12 Report 3 — Closing Checklist Report
 */
$pageTitle = 'Closing Checklist Report';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../PeriodCloseService.php';

if (!hasPermission('closing.report')) { die('Access Denied'); }

$db = getDB();
$fys = $db->query("SELECT id, year_name FROM financial_years ORDER BY start_date DESC")->fetchAll();
$fyId = (int)($_GET['fy_id'] ?? 0);
if (!$fyId) { $a = getActiveFinancialYear($db); $fyId = $a ? (int)$a['id'] : 0; }

$rows = [];
if ($fyId) {
    $s = $db->prepare("
        SELECT ci.*, u.name AS completed_by_name, p.period_name, ct.module_area
        FROM period_checklist_items ci
        LEFT JOIN users u ON u.id = ci.completed_by
        LEFT JOIN accounting_periods p ON p.id = ci.period_id
        LEFT JOIN period_checklist_templates ct ON ct.id = ci.template_id
        WHERE ci.financial_year_id = ?
        ORDER BY ci.period_id, COALESCE(ct.sort_order,999), ci.id
    ");
    $s->execute([$fyId]);
    $rows = $s->fetchAll();
}
$styles = ['pending'=>'bg-gray-100 text-gray-600','in_progress'=>'bg-blue-100 text-blue-700','completed'=>'bg-green-100 text-green-700','exception'=>'bg-red-100 text-red-700'];
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="text-2xl font-bold text-gray-800">Closing Checklist Report</h1></div>
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
            <th class="px-4 py-3 text-left">Scope</th><th class="px-4 py-3 text-left">Item</th>
            <th class="px-4 py-3 text-left">Area</th><th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Comments</th><th class="px-4 py-3 text-left">Completed By / At</th>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($rows as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 text-xs"><?= $r['period_name'] ? sanitize($r['period_name']) : '<b>YEAR</b>' ?></td>
                <td class="px-4 py-2"><?= sanitize($r['item_text']) ?></td>
                <td class="px-4 py-2 text-xs capitalize"><?= sanitize(str_replace('_',' ',$r['module_area'] ?? '')) ?></td>
                <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs font-semibold <?= $styles[$r['status']] ?>"><?= strtoupper(str_replace('_',' ',$r['status'])) ?></span></td>
                <td class="px-4 py-2 text-xs text-gray-500"><?= sanitize($r['comments'] ?? '') ?></td>
                <td class="px-4 py-2 text-xs"><?= $r['completed_by_name'] ? sanitize($r['completed_by_name']).'<br>'.date('d M Y H:i', strtotime($r['completed_at'])) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?><tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No checklist data.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
