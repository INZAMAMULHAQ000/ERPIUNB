<?php
/** F13 — Consolidation Reports Hub */
$pageTitle = 'Consolidation Reports';
require_once '../../../../includes/header.php';
$db = getDB();
if (!hasPermission('consolidation.report')) { die('Access Denied'); }
$reports = [
    ['ctb.php',              'fa-table-list',        'Consolidated Trial Balance',  'All companies + eliminations column'],
    ['cpl.php',              'fa-chart-line',        'Consolidated P&L',            'Revenue → Net Profit, group-wide'],
    ['cbs.php',              'fa-scale-balanced',    'Consolidated Balance Sheet',  'A = L + E validation included'],
    ['#ic',                  'fa-right-left',        'Intercompany Reconciliation', 'modules/finance/group/intercompany/reconciliation.php'],
    ['#exc',                 'fa-triangle-exclamation','IC Exception Report',       'modules/finance/group/intercompany/matching.php'],
    ['eliminations.php',     'fa-scissors',          'Elimination Report',          'Every elimination journal by run'],
    ['ownership.php',        'fa-diagram-project',   'Ownership & NCI Report',      'Percentages and NCI-ready shares'],
];
?>
<div class="mb-6 flex justify-between items-center">
    <div><h1 class="text-2xl font-bold text-gray-800">Consolidation Reports</h1>
    <p class="text-gray-600">Group-level financial reporting suite</p></div>
    <a href="../index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
<?php foreach ($reports as $r): ?>
    <a href="<?= $r[0] ?>" class="bg-white rounded-lg shadow p-5 hover:shadow-md transition block">
        <span class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center mb-3"><i class="fas <?= $r[1] ?>"></i></span>
        <h3 class="font-semibold text-gray-800"><?= $r[2] ?></h3>
        <p class="text-sm text-gray-500 mt-1"><?= $r[3] ?></p>
    </a>
<?php endforeach; ?>
</div>

<h2 class="font-bold text-lg mb-3">Run-specific statements — pick a posted run:</h2>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-3">#</th><th class="px-4 py-3 text-left">Period</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3"></th>
        </tr></thead>
        <tbody class="divide-y">
        <?php
        $runs = $db->query("SELECT cr.*, g.group_name FROM consolidation_runs cr JOIN company_groups g ON g.id=cr.group_id ORDER BY cr.id DESC LIMIT 20")->fetchAll();
        foreach ($runs as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-mono"><?= (int)$r['id'] ?></td>
                <td class="px-4 py-2"><?= sanitize($r['group_name']) ?> · <?= sanitize($r['period_label']) ?></td>
                <td class="px-4 py-2"><?= strtoupper($r['status']) ?></td>
                <td class="px-4 py-2 text-right whitespace-nowrap">
                    <a href="../consolidation/workspace.php?id=<?= $r['id'] ?>" class="text-blue-600 hover:underline text-xs">Workspace</a> ·
                    <a href="ctb.php?run=<?= $r['id'] ?>" class="text-blue-600 hover:underline text-xs">TB</a> ·
                    <a href="cpl.php?run=<?= $r['id'] ?>" class="text-blue-600 hover:underline text-xs">P&L</a> ·
                    <a href="cbs.php?run=<?= $r['id'] ?>" class="text-blue-600 hover:underline text-xs">BS</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
