<?php
/** F13 — Group Dashboard */
$pageTitle = 'Group Dashboard';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../helpers/finance_helpers.php';
require_once __DIR__ . '/ConsolidationService.php';

if (!hasPermission('company.view')) { die('Access Denied'); }
$db = getDB();

$companies = $db->query("SELECT * FROM companies ORDER BY id")->fetchAll();
$groups = $db->query("SELECT g.*, p.company_name parent_name,
                      (SELECT COUNT(*) FROM company_relationships r WHERE r.group_id=g.id AND r.status='active') rel_count
                      FROM company_groups g LEFT JOIN companies p ON p.id=g.parent_company_id")->fetchAll();
$unmatched = (int)$db->query("SELECT COUNT(*) FROM intercompany_transactions WHERE match_status IN ('unmatched','exception')")->fetchColumn();
$icMatrix = IntercompanyService::reconciliationMatrix($db);
$icTotal = 0; foreach ($icMatrix as $m) $icTotal += $m['receivable_a'];

// Latest posted run totals
$lastRun = $db->query("SELECT * FROM consolidation_runs WHERE status='posted' ORDER BY id DESC LIMIT 1")->fetch();
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Group Dashboard</h1>
        <p class="text-gray-600">Multi-company structure &amp; consolidation overview</p>
    </div>
    <div class="flex gap-2">
        <a href="../../companies/index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm"><i class="fas fa-building mr-1"></i>Companies</a>
        <a href="intercompany/transactions.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm"><i class="fas fa-right-left mr-1"></i>New IC Transaction</a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
    <?php if ($lastRun):
        $fmt = fn($v) => formatMoney((float)($v ?? 0));
    ?>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500"><p class="text-xs text-gray-500 uppercase">Group Revenue</p><p class="font-bold font-mono"><?= $fmt($lastRun['total_revenue']) ?></p><p class="text-xs text-gray-400"><?= sanitize($lastRun['period_label']) ?></p></div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500"><p class="text-xs text-gray-500 uppercase">Group Expenses</p><p class="font-bold font-mono"><?= $fmt($lastRun['total_expenses']) ?></p></div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-emerald-600"><p class="text-xs text-gray-500 uppercase">Group Profit</p><p class="font-bold font-mono <?= ($lastRun['net_profit']??0)>=0?'text-green-600':'text-red-600' ?>"><?= $fmt($lastRun['net_profit']) ?></p></div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500"><p class="text-xs text-gray-500 uppercase">Eliminations</p><p class="font-bold font-mono"><?= $fmt($lastRun['elimination_total']) ?></p></div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-cyan-500"><p class="text-xs text-gray-500 uppercase">FX Adjustment</p><p class="font-bold font-mono"><?= $fmt($lastRun['fx_adjustment_total']) ?></p></div>
    <?php else: ?>
    <div class="md:col-span-3 lg:col-span-5 bg-white rounded-lg shadow p-5 flex justify-between items-center flex-wrap gap-3">
        <p class="text-gray-500 text-sm"><i class="fas fa-circle-info mr-2 text-blue-400"></i>No consolidated results yet — create and post a consolidation run.</p>
        <?php if (hasPermission('consolidation.create')): ?><a href="consolidation/runs.php" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">Create Consolidation Run</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Companies -->
    <div class="bg-white rounded-lg shadow lg:col-span-2">
        <div class="px-4 py-3 border-b flex justify-between items-center"><h3 class="font-semibold text-gray-800">Legal Entities</h3>
            <?php if (hasPermission('company.create')): ?><a href="../../companies/index.php" class="text-sm text-blue-600 hover:underline">Manage →</a><?php endif; ?></div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
                <th class="px-4 py-2 text-left">Code</th><th class="px-4 py-2 text-left">Company</th>
                <th class="px-4 py-2 text-left">Currency</th><th class="px-4 py-2 text-left">Status</th></tr></thead>
            <tbody class="divide-y">
            <?php foreach ($companies as $c): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 font-mono"><?= sanitize($c['company_code']) ?><?= $c['is_default'] ? ' <span class="text-xs bg-blue-50 text-blue-600 px-1 rounded">default</span>' : '' ?></td>
                    <td class="px-4 py-2"><?= sanitize($c['company_name']) ?></td>
                    <td class="px-4 py-2"><?= sanitize($c['base_currency']) ?></td>
                    <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs <?= $c['status']==='active'?'bg-green-100 text-green-700':'bg-gray-100' ?>"><?= strtoupper($c['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Structure & IC -->
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800">Groups</h3></div>
            <div class="p-4 space-y-2 text-sm">
                <?php foreach ($groups as $g): ?>
                    <div class="flex justify-between"><span><?= sanitize($g['group_name']) ?> <span class="text-gray-400">(<?= (int)$g['rel_count'] ?>)</span></span>
                    <span class="text-gray-500"><?= sanitize($g['base_currency']) ?></span></div>
                <?php endforeach; if (empty($groups)): ?><p class="text-gray-400">No groups.</p><?php endif; ?>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 space-y-2">
            <div class="flex justify-between items-center text-sm">
                <span class="text-gray-600">Intercompany gross balance</span><b class="font-mono"><?= formatMoney($icTotal) ?></b></div>
            <div class="flex justify-between items-center text-sm">
                <span class="text-gray-600">Unmatched / exceptions</span>
                <b class="<?= $unmatched ? 'text-orange-600' : 'text-green-600' ?>"><?= $unmatched ?></b></div>
            <a href="intercompany/reconciliation.php" class="inline-block text-blue-600 hover:underline text-sm">Reconciliation →</a>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>
