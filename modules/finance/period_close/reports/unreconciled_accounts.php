<?php
/**
 * F12 Report 6 — Unreconciled Accounts Report
 * Exception checklist items + suspense-named accounts with non-zero balances.
 */
$pageTitle = 'Unreconciled Accounts Report';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';

if (!hasPermission('closing.report')) { die('Access Denied'); }

$db = getDB();
$fys = $db->query("SELECT id, year_name FROM financial_years ORDER BY start_date DESC")->fetchAll();
$fyId = (int)($_GET['fy_id'] ?? 0);
if (!$fyId) { $a = getActiveFinancialYear($db); $fyId = $a ? (int)$a['id'] : 0; }

// Exception items
$exceptions = [];
if ($fyId) {
    $s = $db->prepare("
        SELECT ci.*, p.period_name FROM period_checklist_items ci
        LEFT JOIN accounting_periods p ON p.id = ci.period_id
        WHERE ci.financial_year_id = ? AND ci.status = 'exception'
        ORDER BY ci.period_id, ci.id
    ");
    $s->execute([$fyId]);
    $exceptions = $s->fetchAll();
}

// Suspense / clearing accounts with balances (name-based heuristic, configurable in COA naming)
$suspense = $db->query("
    SELECT coa.account_code, coa.account_name,
           COALESCE(SUM(gl.debit),0)-COALESCE(SUM(gl.credit),0) AS balance
    FROM chart_of_accounts coa
    LEFT JOIN general_ledger gl ON gl.account_id = coa.id AND gl.financial_year_id = ".(int)$fyId."
    WHERE coa.status='active' AND (coa.account_name LIKE '%suspense%' OR coa.account_name LIKE '%clearing%' OR coa.account_name LIKE '%temporary%')
    GROUP BY coa.id, coa.account_code, coa.account_name
    HAVING ABS(COALESCE(SUM(gl.debit),0)-COALESCE(SUM(gl.credit),0)) > 0.004
    ORDER BY ABS(COALESCE(SUM(gl.debit),0)-COALESCE(SUM(gl.credit),0)) DESC
")->fetchAll();

// In-progress items (attention list)
$inProgress = [];
if ($fyId) {
    $s = $db->prepare("SELECT ci.*, p.period_name FROM period_checklist_items ci LEFT JOIN accounting_periods p ON p.id=ci.period_id WHERE ci.financial_year_id=? AND ci.status='in_progress'");
    $s->execute([$fyId]);
    $inProgress = $s->fetchAll();
}
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="text-2xl font-bold text-gray-800">Unreconciled Accounts Report</h1>
    <p class="text-gray-600 text-sm">Checklist exceptions and suspense/clearing balances that block a clean close</p></div>
    <form method="get" class="flex gap-2">
        <select name="fy_id" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm bg-white">
            <?php foreach ($fys as $f): ?><option value="<?= $f['id'] ?>" <?= $f['id']==$fyId?'selected':'' ?>><?= sanitize($f['year_name']) ?></option><?php endforeach; ?>
        </select>
        <button onclick="window.print()" type="button" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm"><i class="fas fa-print mr-1"></i>Print</button>
    </form>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b"><h3 class="font-semibold text-red-600"><i class="fas fa-triangle-exclamation mr-1"></i>Exception Items (<?= count($exceptions) ?>)</h3></div>
        <table class="w-full text-sm"><tbody class="divide-y">
            <?php foreach ($exceptions as $e): ?>
            <tr><td class="px-4 py-2"><?= sanitize($e['item_text']) ?><span class="text-xs text-gray-400 ml-2"><?= $e['period_name'] ? sanitize($e['period_name']) : 'YEAR' ?></span></td></tr>
            <?php endforeach; ?>
            <?php if (empty($exceptions)): ?><tr><td class="px-4 py-6 text-center text-green-600"><i class="fas fa-circle-check mr-1"></i>No exception items.</td></tr><?php endif; ?>
        </tbody></table>
    </div>

    <div class="bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b"><h3 class="font-semibold text-orange-600"><i class="fas fa-hourglass-half mr-1"></i>Suspense / Clearing Balances (<?= count($suspense) ?>)</h3></div>
        <table class="w-full text-sm"><tbody class="divide-y">
            <?php foreach ($suspense as $su): ?>
            <tr><td class="px-4 py-2"><span class="font-mono text-xs"><?= sanitize($su['account_code']) ?></span> <?= sanitize($su['account_name']) ?></td>
                <td class="px-4 py-2 text-right font-mono <?= $su['balance']>0?'text-green-700':'text-red-700' ?>"><?= number_format((float)$su['balance'],2) ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($suspense)): ?><tr><td colspan="2" class="px-4 py-6 text-center text-green-600"><i class="fas fa-circle-check mr-1"></i>All suspense/clearing accounts at zero.</td></tr><?php endif; ?>
        </tbody></table>
    </div>

    <div class="bg-white rounded-lg shadow lg:col-span-2">
        <div class="px-4 py-3 border-b"><h3 class="font-semibold text-blue-600"><i class="fas fa-spinner mr-1"></i>In-Progress Items (<?= count($inProgress) ?>)</h3></div>
        <table class="w-full text-sm"><tbody class="divide-y">
            <?php foreach ($inProgress as $ip): ?>
            <tr><td class="px-4 py-2"><?= sanitize($ip['item_text']) ?><span class="text-xs text-gray-400 ml-2"><?= $ip['period_name'] ? sanitize($ip['period_name']) : 'YEAR' ?></span></td></tr>
            <?php endforeach; ?>
            <?php if (empty($inProgress)): ?><tr><td class="px-4 py-6 text-center text-gray-400">Nothing in progress.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
