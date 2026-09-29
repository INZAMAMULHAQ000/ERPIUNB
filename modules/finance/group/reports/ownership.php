<?php
/** F13 — Eliminations, Ownership/NCI & FX reports */
$pageTitle = 'Elimination Report';
$which = basename(__FILE__) === 'ownership.php' ? 'ownership' : 'eliminations';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../ConsolidationService.php';
if (!hasPermission('consolidation.report')) { die('Access Denied'); }
$db = getDB();

if ($which === 'ownership') :
    $tree = ConsolidationService::ownershipTree($db);
    $runs = $db->query("SELECT id, period_label FROM consolidation_runs ORDER BY id DESC LIMIT 10")->fetchAll();
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="text-2xl font-bold text-gray-800">Ownership &amp; NCI Report</h1></div>
    <a href="index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
</div>
<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-3 text-left">Group</th><th class="px-4 py-3 text-left">Parent</th><th class="px-4 py-3 text-left">Subsidiary</th>
            <th class="px-4 py-3 text-right">Ownership</th><th class="px-4 py-3 text-right">NCI %</th><th class="px-4 py-3 text-left">Since</th>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($tree as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2"><?= sanitize($r['group_name']) ?></td>
                <td class="px-4 py-2"><?= sanitize($r['parent_name']) ?></td>
                <td class="px-4 py-2"><?= sanitize($r['child_name']) ?></td>
                <td class="px-4 py-2 text-right font-mono"><?= number_format((float)$r['ownership_percentage'],1) ?>%</td>
                <td class="px-4 py-2 text-right font-mono <?= $r['ownership_percentage']<100?'text-orange-600':'' ?>"><?= number_format(100-(float)$r['ownership_percentage'],1) ?>%</td>
                <td class="px-4 py-2 text-xs"><?= date('d M Y', strtotime($r['effective_from'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="bg-white rounded-lg shadow p-5">
    <h3 class="font-semibold mb-2">NCI share per posted run (architecture-ready)</h3>
    <?php foreach ($runs as $run): $shares = ConsolidationService::nciShares($db, (int)$run['id']);
          if (!$shares) continue; ?>
        <p class="text-sm mt-3 font-medium">Run #<?= (int)$run['id'] ?> — <?= sanitize($run['period_label']) ?></p>
        <ul class="text-sm list-disc ml-6">
            <?php foreach ($shares as $s): ?>
            <li><?= sanitize($db->query("SELECT company_name FROM companies WHERE id=".$s['company_id'])->fetchColumn()) ?>:
                profit <?= number_format($s['subsidiary_profit'],2) ?> × NCI <?= $s['nci_pct']?>% =
                <b class="font-mono"><?= number_format($s['nci_amount'],2) ?></b></li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>
</div>

<?php else : ?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="text-2xl font-bold text-gray-800">Elimination Report</h1></div>
    <a href="index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
</div>
<?php
$journals = $db->query("
    SELECT j.*, g.group_name FROM consolidation_journals j
    JOIN consolidation_runs cr ON cr.id=j.run_id JOIN company_groups g ON g.id=cr.group_id
    ORDER BY j.run_id DESC, j.id")->fetchAll();
?>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-3">Run</th><th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-left">Description</th>
            <th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3 text-left">Pair Key</th><th class="px-4 py-3 text-left">Created</th>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($journals as $j): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-mono"><?= (int)$j['run_id'] ?></td>
                <td class="px-4 py-2 text-xs uppercase"><?= sanitize(str_replace('_',' ',$j['adjustment_type'])) ?></td>
                <td class="px-4 py-2"><?= sanitize($j['description']) ?></td>
                <td class="px-4 py-2 text-right font-mono"><?= number_format((float)$j['amount'],2) ?></td>
                <td class="px-4 py-2 font-mono text-xs text-gray-400"><?= sanitize($j['partner_pair_key'] ?? '') ?></td>
                <td class="px-4 py-2 text-xs text-gray-500"><?= date('d M Y H:i', strtotime($j['created_at'])) ?></td>
            </tr>
        <?php endforeach; if (empty($journals)): ?><tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No eliminations yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once '../../../../includes/footer.php'; ?>
