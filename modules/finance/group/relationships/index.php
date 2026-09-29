<?php
/** F13 — Company Relationships & Ownership */
$pageTitle = 'Company Relationships';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';

if (!hasPermission('company.view')) { die('Access Denied'); }
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error','Invalid request.'); to('modules/finance/group/relationships/index.php'); }
    $gid = (int)($_POST['group_id'] ?? 0);
    $parent = (int)($_POST['parent_company_id'] ?? 0);
    $child = (int)($_POST['child_company_id'] ?? 0);
    $own = min(100, max(0.001, (float)($_POST['ownership_percentage'] ?? 100)));
    if (!$gid || !$parent || !$child || $parent === $child) { flash('error','Invalid relationship.'); }
    else {
        try {
            $db->prepare("INSERT INTO company_relationships (group_id,parent_company_id,child_company_id,ownership_percentage,effective_from,relationship_type,status)
                          VALUES (?,?,?,?,?,'subsidiary','active')")
               ->execute([$gid,$parent,$child,$own,$_POST['effective_from'] ?? date('Y-m-d')]);
            IntercompanyService::log($db,'relationship_created',$child,null,"Parent #$parent owns $own%",null,$_SESSION['user_id']);
            flash('success','Relationship created.');
        } catch (Exception $e) { flash('error',$e->getMessage()); }
    }
    to('modules/finance/group/relationships/index.php');
}
require_once __DIR__ . '/../IntercompanyService.php';
require_once __DIR__ . '/../ConsolidationService.php';
$groups = $db->query("SELECT * FROM company_groups WHERE status='active'")->fetchAll();
$companies = $db->query("SELECT id, company_code, company_name FROM companies WHERE status='active' ORDER BY company_name")->fetchAll();
$tree = ConsolidationService::ownershipTree($db);
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="text-2xl font-bold text-gray-800">Company Relationships</h1>
    <p class="text-gray-600">Ownership structure — parent / subsidiary / associate percentages</p></div>
    <a href="../index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
</div>

<form method="post" class="bg-white rounded-lg shadow p-4 mb-6 grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
    <?= csrfToken() ?>
    <div><label class="text-xs text-gray-500 block mb-1">Group</label>
        <select name="group_id" required class="w-full border rounded px-2 py-2 text-sm">
            <?php foreach ($groups as $g): ?><option value="<?= $g['id'] ?>"><?= sanitize($g['group_name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="text-xs text-gray-500 block mb-1">Parent</label>
        <select name="parent_company_id" required class="w-full border rounded px-2 py-2 text-sm">
            <?php foreach ($companies as $c): ?><option value="<?= $c['id'] ?>"><?= sanitize($c['company_name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="text-xs text-gray-500 block mb-1">Child</label>
        <select name="child_company_id" required class="w-full border rounded px-2 py-2 text-sm">
            <?php foreach ($companies as $c): ?><option value="<?= $c['id'] ?>"><?= sanitize($c['company_name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="text-xs text-gray-500 block mb-1">Ownership %</label>
        <input type="number" name="ownership_percentage" value="100" step="0.001" min="0.001" max="100" class="w-full border rounded px-2 py-2 text-sm"></div>
    <div><label class="text-xs text-gray-500 block mb-1">Effective from</label>
        <input type="date" name="effective_from" value="<?= date('Y-m-d') ?>" class="w-full border rounded px-2 py-2 text-sm"></div>
    <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm whitespace-nowrap">Add</button>
</form>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-3 text-left">Group</th><th class="px-4 py-3 text-left">Parent</th>
            <th class="px-4 py-3 text-left">Child</th><th class="px-4 py-3 text-right">Ownership</th>
            <th class="px-4 py-3 text-center">NCI</th><th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-left">Since</th>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($tree as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3"><?= sanitize($r['group_name']) ?></td>
                <td class="px-4 py-3"><?= sanitize($r['parent_name']) ?></td>
                <td class="px-4 py-3 font-medium"><?= sanitize($r['child_name']) ?></td>
                <td class="px-4 py-3 text-right font-mono"><?= number_format((float)$r['ownership_percentage'],1) ?>%</td>
                <td class="px-4 py-3 text-center <?= $r['ownership_percentage']<100?'text-orange-600 font-semibold':'text-gray-300' ?>">
                    <?= $r['ownership_percentage']<100 ? number_format(100-(float)$r['ownership_percentage'],1).'%' : '—' ?></td>
                <td class="px-4 py-3"><span class="text-xs px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded uppercase"><?= sanitize($r['relationship_type']) ?></span></td>
                <td class="px-4 py-3 text-xs text-gray-500"><?= date('d M Y', strtotime($r['effective_from'])) ?></td>
            </tr>
        <?php endforeach; if (empty($tree)): ?><tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No relationships defined.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
