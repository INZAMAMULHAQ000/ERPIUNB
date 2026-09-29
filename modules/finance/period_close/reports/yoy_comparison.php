<?php
/**
 * F12 Report 7 — Year-over-Year Financial Comparison (STEP 17)
 */
$pageTitle = 'Year-over-Year Comparison';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';

if (!hasPermission('closing.report')) { die('Access Denied'); }

$db = getDB();

// All FYs with P&L + BS totals computed straight from GL per FY
$fys = $db->query("
    SELECT f.id, f.year_name, f.start_date, f.end_date, f.status,
      (SELECT COALESCE(SUM(gl.credit-gl.debit),0) FROM general_ledger gl
         JOIN chart_of_accounts c ON c.id=gl.account_id JOIN account_groups g ON g.id=c.group_id
        WHERE gl.financial_year_id=f.id AND g.nature='income') AS revenue,
      (SELECT COALESCE(SUM(gl.debit-gl.credit),0) FROM general_ledger gl
         JOIN chart_of_accounts c ON c.id=gl.account_id JOIN account_groups g ON g.id=c.group_id
        WHERE gl.financial_year_id=f.id AND g.nature IN ('expense')) AS expenses,
      (SELECT COALESCE(SUM(gl.debit-gl.credit),0) FROM general_ledger gl
         JOIN chart_of_accounts c ON c.id=gl.account_id JOIN account_groups g ON g.id=c.group_id
        WHERE gl.financial_year_id=f.id AND g.nature='asset') AS assets_total,
      (SELECT COALESCE(SUM(gl.credit-gl.debit),0) FROM general_ledger gl
         JOIN chart_of_accounts c ON c.id=gl.account_id JOIN account_groups g ON g.id=c.group_id
        WHERE gl.financial_year_id=f.id AND g.nature IN ('liability','equity')) AS liab_equity_total
    FROM financial_years f ORDER BY f.start_date ASC
")->fetchAll();

// compute net profit + YoY variance rows (newest last)
$data = [];
foreach ($fys as $f) {
    $f['net_profit'] = round((float)$f['revenue'] - (float)$f['expenses'], 2);
    $data[] = $f;
}
foreach ($data as $i => &$d) {
    if ($i > 0) {
        $prev = $data[$i-1];
        foreach ([['revenue','Revenue'],['expenses','Expenses'],['net_profit','Net Profit'],['assets_total','Total Assets']] as $m) {
            $k = $m[0]; $pk = 'prev_'.$k; $vk = 'var_'.$k;
            $d[$pk] = $prev[$k];
            $d[$vk] = round((float)$d[$k] - (float)$prev[$k], 2);
            $d['pct_'.$k] = abs((float)$prev[$k]) > 0.004 ? round(((float)$d[$k]-(float)$prev[$k])/abs((float)$prev[$k])*100, 1) : null;
        }
    }
}
unset($d);
$data = array_reverse($data); // newest first for display
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="text-2xl font-bold text-gray-800">Year-over-Year Financial Report</h1>
    <p class="text-gray-600 text-sm">Multi-year comparison: opening vs closing view, current year, previous years and variance</p></div>
    <button onclick="window.print()" type="button" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm"><i class="fas fa-print mr-1"></i>Print</button>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-3 text-left">Financial Year</th><th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-right">Revenue</th><th class="px-4 py-3 text-right">Expenses</th>
            <th class="px-4 py-3 text-right">Net Profit / (Loss)</th>
            <th class="px-4 py-3 text-right">Assets Total</th><th class="px-4 py-3 text-right">Liab + Equity</th>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($data as $d): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium"><?= sanitize($d['year_name']) ?></td>
                <td class="px-4 py-3"><span class="text-xs uppercase <?= $d['status']==='active'?'text-green-600':'text-gray-400' ?> font-semibold"><?= strtoupper($d['status']) ?></span></td>
                <td class="px-4 py-3 text-right font-mono"><?= number_format((float)$d['revenue'],2) ?></td>
                <td class="px-4 py-3 text-right font-mono"><?= number_format((float)$d['expenses'],2) ?></td>
                <td class="px-4 py-3 text-right font-mono font-semibold <?= $d['net_profit']>=0?'text-green-600':'text-red-600' ?>"><?= number_format($d['net_profit'],2) ?></td>
                <td class="px-4 py-3 text-right font-mono"><?= number_format((float)$d['assets_total'],2) ?></td>
                <td class="px-4 py-3 text-right font-mono"><?= number_format((float)$d['liab_equity_total'],2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (count($data) > 1): ?>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800">Year-over-Year Variance</h3></div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-3 text-left">Financial Year</th>
            <?php foreach ([['var_revenue','Revenue Δ'],['var_expenses','Expenses Δ'],['var_net_profit','Net Profit Δ'],['pct_net_profit','NP Δ %'],['var_assets_total','Assets Δ']] as $c): ?>
            <th class="px-4 py-3 text-right"><?= $c[1] ?></th>
            <?php endforeach; ?>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($data as $d): if (!isset($d['var_net_profit'])) { echo '<tr><td class="px-4 py-3">'.sanitize($d['year_name']).'</td><td colspan="5" class="px-4 py-3 text-center text-gray-300 text-xs">first year — no comparison</td></tr>'; continue; } ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium"><?= sanitize($d['year_name']) ?> <span class="text-xs text-gray-400">vs previous</span></td>
                <td class="px-4 py-3 text-right font-mono <?= $d['var_revenue']>=0?'text-green-600':'text-red-600' ?>"><?= number_format($d['var_revenue'],2) ?></td>
                <td class="px-4 py-3 text-right font-mono <?= $d['var_expenses']<=0?'text-green-600':'text-red-600' ?>"><?= number_format($d['var_expenses'],2) ?></td>
                <td class="px-4 py-3 text-right font-mono font-semibold <?= $d['var_net_profit']>=0?'text-green-600':'text-red-600' ?>"><?= number_format($d['var_net_profit'],2) ?></td>
                <td class="px-4 py-3 text-right <?= ($d['pct_net_profit']??0)>=0?'text-green-600':'text-red-600' ?>"><?= $d['pct_net_profit']!==null ? $d['pct_net_profit'].'%' : '—' ?></td>
                <td class="px-4 py-3 text-right font-mono"><?= number_format($d['var_assets_total'],2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once '../../../../includes/footer.php'; ?>
