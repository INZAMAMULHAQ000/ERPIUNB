<?php
$pageTitle = 'Company Master';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('company.view')) { die('Access Denied'); }

$db = getDB();

$search  = trim($_GET['q'] ?? '');
$type    = $_GET['type'] ?? '';
$status  = $_GET['status'] ?? '';
$groupId = (int) ($_GET['group'] ?? 0);
$sort    = $_GET['sort'] ?? 'company_name';
$dir     = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

$sortable = [
    'company_code' => 'Code', 'company_name' => 'Company', 'entity_type' => 'Entity Type',
    'base_currency' => 'Currency', 'country' => 'Country', 'status' => 'Status',
    'established_date' => 'Established', 'journal_count' => 'Journals',
];
if (!isset($sortable[$sort])) $sort = 'company_name';

$where = ['co.deleted_at IS NULL'];
$args  = [];
if ($search !== '') {
    $where[] = "(co.company_name LIKE ? OR co.legal_name LIKE ? OR co.trade_name LIKE ? OR co.company_code LIKE ?
                 OR co.search_term LIKE ? OR co.tax_number LIKE ? OR co.vat_number LIKE ? OR co.city LIKE ?)";
    $like = "%$search%";
    array_push($args, $like, $like, $like, $like, $like, $like, $like, $like);
}
$entityTypes = ['limited_liability','sole_proprietorship','partnership','free_zone_establishment','branch','subsidiary','holding','ngo','government','trust'];
if (in_array($type, $entityTypes, true)) { $where[] = 'co.entity_type = ?'; $args[] = $type; }
$statuses = ['active','inactive','dormant','archived'];
if (in_array($status, $statuses, true)) { $where[] = 'co.status = ?'; $args[] = $status; }
if ($groupId > 0) { $where[] = 'co.group_id = ?'; $args[] = $groupId; }
$whereSql = implode(' AND ', $where);

// Derived columns shown in the grid.
$derived = "
    (SELECT COUNT(*) FROM user_companies uc WHERE uc.company_id = co.id) AS user_count,
    (SELECT COUNT(*) FROM companies sub WHERE sub.parent_company_id = co.id AND sub.deleted_at IS NULL) AS subsidiary_count,
    (SELECT COUNT(*) FROM journal_entries je WHERE je.company_id = co.id) AS journal_count,
    (SELECT COUNT(*) FROM financial_years fy WHERE fy.company_id = co.id) AS fy_count,
    (SELECT COUNT(*) FROM company_bank_accounts ba WHERE ba.company_id = co.id AND ba.deleted_at IS NULL) AS bank_count";

$countStmt = $db->prepare("SELECT COUNT(*) FROM companies co WHERE $whereSql");
$countStmt->execute($args);
$total = (int) $countStmt->fetchColumn();

$pagination = paginate($total, 20, getPageNumber());
$orderBy = in_array($sort, ['journal_count','user_count','subsidiary_count','fy_count','bank_count'], true)
    ? "$sort $dir" : "co.$sort $dir";

$stmt = $db->prepare("
    SELECT co.*, g.group_name, p.company_name AS parent_name, {$derived}
    FROM companies co
    LEFT JOIN company_groups g ON g.id = co.group_id
    LEFT JOIN companies p      ON p.id = co.parent_company_id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute($args);
$rows = $stmt->fetchAll();

$groups = $db->query("SELECT id, group_code, group_name FROM company_groups WHERE status = 'active' ORDER BY group_name")->fetchAll();

$statTotal     = $db->query("SELECT COUNT(*) FROM companies WHERE deleted_at IS NULL")->fetchColumn();
$statActive    = $db->query("SELECT COUNT(*) FROM companies WHERE deleted_at IS NULL AND status = 'active'")->fetchColumn();
$statLegal     = $db->query("SELECT COUNT(*) FROM companies WHERE deleted_at IS NULL AND is_legal_entity = 1")->fetchColumn();
$statNoFyStart = $db->query("SELECT COUNT(*) FROM companies WHERE deleted_at IS NULL AND (financial_year_start IS NULL OR financial_year_start = '0000-00-00')")->fetchColumn();
$statConsol    = $db->query("SELECT COUNT(*) FROM companies WHERE deleted_at IS NULL AND consolidation_method <> 'none'")->fetchColumn();

$typeLabels = [
    'limited_liability' => 'Limited Liability', 'sole_proprietorship' => 'Sole Proprietorship',
    'partnership' => 'Partnership', 'free_zone_establishment' => 'Free Zone Establishment',
    'branch' => 'Branch', 'subsidiary' => 'Subsidiary', 'holding' => 'Holding',
    'ngo' => 'NGO', 'government' => 'Government', 'trust' => 'Trust',
];
$statusBadges = ['active' => 'bg-green-100 text-green-800', 'inactive' => 'bg-gray-100 text-gray-700', 'dormant' => 'bg-amber-100 text-amber-800', 'archived' => 'bg-slate-100 text-slate-600'];
$consolLabels = ['full' => 'Full', 'proportionate' => 'Proportionate', 'equity' => 'Equity', 'none' => 'Not consolidated'];
?>

<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Company Master</h1>
        <p class="text-gray-500 text-sm mt-1">Legal entities, operating units and group structure</p>
    </div>
    <?php if (hasPermission('company.create')): ?>
    <a href="<?= url('modules/companies/form.php') ?>"
       class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition shadow-sm">
        <i class="fas fa-building"></i> New Company
    </a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Companies</p>
        <p class="text-xl font-bold text-slate-800 mt-1"><?= number_format($statTotal) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Active</p>
        <p class="text-xl font-bold text-green-600 mt-1"><?= number_format($statActive) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Legal Entities</p>
        <p class="text-xl font-bold text-slate-800 mt-1"><?= number_format($statLegal) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">In Consolidation</p>
        <p class="text-xl font-bold text-indigo-600 mt-1"><?= number_format($statConsol) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4 <?= $statNoFyStart > 0 ? 'ring-1 ring-amber-200' : '' ?>">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Missing FY Start</p>
        <p class="text-xl font-bold <?= $statNoFyStart > 0 ? 'text-amber-600' : 'text-green-600' ?> mt-1"><?= number_format($statNoFyStart) ?></p>
        <p class="text-[11px] text-slate-400 mt-0.5">blocks period close</p>
    </div>
</div>

<?php if ($statNoFyStart > 0): ?>
<div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
    <i class="fas fa-triangle-exclamation mr-1.5"></i>
    <strong><?= (int) $statNoFyStart ?> company<?= $statNoFyStart == 1 ? ' has' : 's have' ?></strong> no fiscal year start date.
    Period closing and consolidation resolve financial years per company, so those entities cannot post.
    <a href="<?= url('modules/companies/index.php?sort=company_name') ?>" class="underline font-medium">Open a company</a> and set it under Financial &amp; Ledger Controls.
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4 mb-5">
    <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="md:col-span-2">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, code, tax number, city…"
                   class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
        </div>
        <select name="type" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
            <option value="">All entity types</option>
            <?php foreach ($typeLabels as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $s): ?>
            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="md:col-span-4 flex items-center gap-2">
            <?php if ($groups): ?>
            <select name="group" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
                <option value="">All groups</option>
                <?php foreach ($groups as $g): ?>
                <option value="<?= $g['id'] ?>" <?= $groupId === (int) $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['group_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button class="px-4 py-2 bg-slate-800 text-white text-sm rounded-lg hover:bg-slate-900 transition"><i class="fas fa-filter mr-1.5"></i>Apply</button>
            <a href="<?= url('modules/companies/index.php') ?>" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-900">Reset</a>
            <span class="ml-auto text-xs text-slate-400"><?= number_format($total) ?> compan<?= $total == 1 ? 'y' : 'ies' ?></span>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <?php foreach ($sortable as $col => $lbl): ?>
                    <th class="text-left px-4 py-3">
                        <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['sort' => $col, 'dir' => ($sort === $col && $dir === 'ASC') ? 'DESC' : 'ASC', 'page' => 1]))) ?>"
                           class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide hover:text-slate-700 whitespace-nowrap">
                            <?= $lbl ?><?= $sort === $col ? ($dir === 'ASC' ? ' ↑' : ' ↓') : '' ?>
                        </a>
                    </th>
                    <?php endforeach; ?>
                    <th class="text-left px-4 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Structure</th>
                    <th class="text-right px-4 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php if (empty($rows)): ?>
                <tr><td colspan="10" class="px-4 py-12 text-center text-sm text-slate-400">
                    <i class="fas fa-building text-3xl text-slate-200 mb-3 block"></i>No companies match your filters.
                </td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-mono text-xs text-slate-500 whitespace-nowrap">
                        <?= htmlspecialchars($r['company_code']) ?>
                        <?php if ((int) $r['is_default'] === 1): ?><span class="ml-1 px-1.5 py-0.5 text-[9px] bg-indigo-100 text-indigo-700 rounded font-semibold">DEFAULT</span><?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <a href="<?= url('modules/companies/view.php?id=' . $r['id']) ?>" class="font-medium text-slate-800 hover:text-indigo-600"><?= htmlspecialchars($r['company_name']) ?></a>
                        <div class="text-[11px] text-slate-400"><?= htmlspecialchars($r['legal_name'] ?: ($r['trade_name'] ?: '')) ?></div>
                    </td>
                    <td class="px-4 py-3 text-slate-600 whitespace-nowrap"><?= htmlspecialchars($typeLabels[$r['entity_type']] ?? $r['entity_type']) ?></td>
                    <td class="px-4 py-3 text-slate-600"><span class="text-xs font-semibold"><?= htmlspecialchars($r['base_currency']) ?></span></td>
                    <td class="px-4 py-3 text-slate-600 text-xs whitespace-nowrap">
                        <?= htmlspecialchars($r['country'] ?: '—') ?>
                        <div class="text-[11px] text-slate-400"><?= htmlspecialchars($r['city'] ?: '') ?></div>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 text-[11px] rounded-full <?= $statusBadges[$r['status']] ?? 'bg-gray-100' ?>"><?= ucfirst($r['status']) ?></span>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap"><?= $r['established_date'] ? date('M Y', strtotime($r['established_date'])) : '—' ?></td>
                    <td class="px-4 py-3 text-xs whitespace-nowrap">
                        <span class="<?= (int) $r['journal_count'] > 0 ? 'text-slate-700 font-medium' : 'text-slate-300' ?>"><?= number_format($r['journal_count']) ?></span> JE
                        <span class="text-slate-300">·</span>
                        <span class="<?= (int) $r['fy_count'] > 0 ? 'text-slate-700' : 'text-amber-600' ?>"><?= number_format($r['fy_count']) ?></span> FY
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-500">
                        <?php if ($r['parent_name']): ?>
                            <i class="fas fa-arrow-up text-slate-300 mr-1"></i>child of <?= htmlspecialchars($r['parent_name']) ?><br>
                        <?php endif; ?>
                        <?php if ((int) $r['subsidiary_count'] > 0): ?>
                            <i class="fas fa-sitemap text-indigo-400 mr-1"></i><?= (int) $r['subsidiary_count'] ?> subs
                        <?php endif; ?>
                        <?php if ($r['group_name']): ?><span class="text-slate-400">· <?= htmlspecialchars($r['group_name']) ?></span><?php endif; ?>
                        <?php if ($consolLabels[$r['consolidation_method']] === 'Not consolidated'): ?><span class="text-amber-600">· excluded</span><?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="<?= url('modules/companies/view.php?id=' . $r['id']) ?>" title="View" class="px-2 py-1 text-slate-400 hover:text-indigo-600"><i class="fas fa-eye"></i></a>
                        <?php if (hasPermission('company.edit')): ?>
                        <a href="<?= url('modules/companies/form.php?id=' . $r['id']) ?>" title="Edit" class="px-2 py-1 text-slate-400 hover:text-amber-600"><i class="fas fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (hasPermission('company.delete')): ?>
                        <a href="<?= url('modules/companies/delete.php?id=' . $r['id']) ?>" title="Delete" class="px-2 py-1 text-slate-400 hover:text-red-600"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-5"><?= paginationLinks($pagination, '?') ?></div>

<?php require_once '../../includes/footer.php'; ?>
