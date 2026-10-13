<?php
$pageTitle = 'Customer Master';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('customers.view')) { die('Access Denied'); }

$db = getDB();

// --- Filters ---
$search  = trim($_GET['q'] ?? '');
$type    = $_GET['type'] ?? '';
$status  = $_GET['status'] ?? '';
$segment = trim($_GET['segment'] ?? '');
$hold    = $_GET['hold'] ?? '';
$sort    = $_GET['sort'] ?? 'company_name';
$dir     = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

$sortable = [
    'client_code' => 'Code', 'company_name' => 'Customer', 'customer_type' => 'Type',
    'credit_limit' => 'Credit Limit', 'city' => 'City', 'status' => 'Status',
    'customer_since' => 'Customer Since', 'outstanding' => 'Outstanding',
];
if (!isset($sortable[$sort])) $sort = 'company_name';

$where = ['c.deleted_at IS NULL'];
$args  = [];
if ($search !== '') {
    $where[] = "(c.company_name LIKE ? OR c.legal_name LIKE ? OR c.trade_name LIKE ? OR c.client_code LIKE ?
                 OR c.search_term LIKE ? OR c.email LIKE ? OR c.tax_id LIKE ? OR c.gst_vat_number LIKE ? OR c.city LIKE ?)";
    $like = "%$search%";
    array_push($args, $like, $like, $like, $like, $like, $like, $like, $like, $like);
}
if (in_array($type, ['company','individual','partnership','sole_proprietorship','government','trust'], true)) {
    $where[] = 'c.customer_type = ?'; $args[] = $type;
}
if (in_array($status, ['active','inactive','prospect','blocked','archived'], true)) {
    $where[] = 'c.status = ?'; $args[] = $status;
}
if ($segment !== '') { $where[] = 'c.segment = ?'; $args[] = $segment; }
if (in_array($hold, ['no','manual','automatic'], true)) {
    $where[] = 'c.credit_hold = ?'; $args[] = $hold;
}
$whereSql = implode(' AND ', $where);

// Outstanding / overdue are derived from AR, not stored on the master.
$exposure = "
    (SELECT COALESCE(SUM(CASE WHEN ci.status NOT IN ('cancelled','draft','void') THEN ci.balance_amount ELSE 0 END), 0)
       FROM customer_invoices ci WHERE ci.client_id = c.id) AS outstanding,
    (SELECT COALESCE(SUM(CASE WHEN ci.status NOT IN ('cancelled','draft','void') AND ci.due_date < CURDATE()
                                THEN ci.balance_amount ELSE 0 END), 0)
       FROM customer_invoices ci WHERE ci.client_id = c.id) AS overdue,
    (SELECT COUNT(*) FROM customer_invoices ci WHERE ci.client_id = c.id) AS invoice_count";

$countStmt = $db->prepare("SELECT COUNT(*) FROM clients c WHERE $whereSql");
$countStmt->execute($args);
$total = (int) $countStmt->fetchColumn();

$pagination = paginate($total, 25, getPageNumber());
$orderBy = $sort === 'outstanding' ? "outstanding $dir" : "c.$sort $dir";

$stmt = $db->prepare("
    SELECT c.*, {$exposure}
    FROM clients c
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute($args);
$rows = $stmt->fetchAll();

$segments = $db->query("SELECT DISTINCT segment FROM clients WHERE deleted_at IS NULL AND segment IS NOT NULL AND segment <> '' ORDER BY segment")->fetchAll(PDO::FETCH_COLUMN);

// --- Header stats ---
$statTotal     = $db->query("SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL")->fetchColumn();
$statActive    = $db->query("SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL AND status = 'active'")->fetchColumn();
$statProspect  = $db->query("SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL AND status = 'prospect'")->fetchColumn();
$statBlocked   = $db->query("SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL AND credit_hold <> 'no'")->fetchColumn();
$statReceiv    = $db->query("SELECT COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','draft','void') THEN balance_amount ELSE 0 END),0) FROM customer_invoices")->fetchColumn();

$typeLabels = [
    'company' => 'Company', 'individual' => 'Individual', 'partnership' => 'Partnership',
    'sole_proprietorship' => 'Sole Proprietor', 'government' => 'Government', 'trust' => 'Trust',
];
$statusBadges = [
    'active'   => 'bg-green-100 text-green-800',
    'inactive' => 'bg-gray-100 text-gray-700',
    'prospect' => 'bg-blue-100 text-blue-800',
    'blocked'  => 'bg-red-100 text-red-800',
    'archived' => 'bg-slate-100 text-slate-600',
];
?>

<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Customer Master</h1>
        <p class="text-gray-500 text-sm mt-1">Unified customer records for AR, sales and service</p>
    </div>
    <?php if (hasPermission('customers.create')): ?>
    <a href="<?= url('modules/customers/form.php') ?>"
       class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition shadow-sm">
        <i class="fas fa-user-plus"></i> New Customer
    </a>
    <?php endif; ?>
</div>

<!-- ===== Stats ===== -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Total Customers</p>
        <p class="text-xl font-bold text-slate-800 mt-1"><?= number_format($statTotal) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Active</p>
        <p class="text-xl font-bold text-green-600 mt-1"><?= number_format($statActive) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Prospects</p>
        <p class="text-xl font-bold text-blue-600 mt-1"><?= number_format($statProspect) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">On Credit Hold</p>
        <p class="text-xl font-bold <?= $statBlocked > 0 ? 'text-red-600' : 'text-slate-800' ?> mt-1"><?= number_format($statBlocked) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Total Receivables</p>
        <p class="text-xl font-bold text-slate-800 mt-1"><?= number_format($statReceiv, 2) ?></p>
    </div>
</div>

<!-- ===== Filters ===== -->
<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4 mb-5">
    <form method="get" class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <div class="md:col-span-2">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, code, tax ID, email, city…"
                   class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
        </div>
        <select name="type" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
            <option value="">All types</option>
            <?php foreach ($typeLabels as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
            <option value="">All statuses</option>
            <?php foreach (array_keys($statusBadges) as $s): ?>
            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="hold" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
            <option value="">Any credit state</option>
            <option value="no" <?= $hold === 'no' ? 'selected' : '' ?>>No hold</option>
            <option value="manual" <?= $hold === 'manual' ? 'selected' : '' ?>>Manual hold</option>
            <option value="automatic" <?= $hold === 'automatic' ? 'selected' : '' ?>>Automatic hold</option>
        </select>
        <div class="md:col-span-5 flex items-center gap-2">
            <?php if ($segments): ?>
            <select name="segment" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
                <option value="">All segments</option>
                <?php foreach ($segments as $sg): ?>
                <option value="<?= htmlspecialchars($sg) ?>" <?= $segment === $sg ? 'selected' : '' ?>><?= htmlspecialchars($sg) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button class="px-4 py-2 bg-slate-800 text-white text-sm rounded-lg hover:bg-slate-900 transition">
                <i class="fas fa-filter mr-1.5"></i>Apply
            </button>
            <a href="<?= url('modules/customers/index.php') ?>" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-900">Reset</a>
            <span class="ml-auto text-xs text-slate-400"><?= number_format($total) ?> customer<?= $total == 1 ? '' : 's' ?></span>
        </div>
    </form>
</div>

<!-- ===== Table ===== -->
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
                    <th class="text-right px-4 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php if (empty($rows)): ?>
                <tr><td colspan="10" class="px-4 py-12 text-center text-sm text-slate-400">
                    <i class="fas fa-users text-3xl text-slate-200 mb-3 block"></i>
                    No customers match your filters.
                </td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r):
                    $limit = (float) $r['credit_limit'];
                    $used  = (float) $r['outstanding'];
                    $util  = $limit > 0 ? min(100, round(($used / $limit) * 100)) : null;
                    $bar   = $util === null ? 'bg-slate-200' : ($util >= 100 ? 'bg-red-500' : ($util >= 80 ? 'bg-amber-500' : 'bg-green-500'));
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-mono text-xs text-slate-500 whitespace-nowrap"><?= htmlspecialchars($r['client_code']) ?></td>
                    <td class="px-4 py-3">
                        <a href="<?= url('modules/customers/view.php?id=' . $r['id']) ?>" class="font-medium text-slate-800 hover:text-indigo-600">
                            <?= htmlspecialchars($r['company_name']) ?>
                        </a>
                        <div class="text-[11px] text-slate-400">
                            <?= htmlspecialchars($r['trade_name'] ?: ($r['legal_name'] ?: '')) ?>
                            <?php if ($r['credit_hold'] !== 'no'): ?><span class="text-red-500 font-semibold">&middot; <?= ucfirst($r['credit_hold']) ?> hold</span><?php endif; ?>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-slate-600 whitespace-nowrap"><?= htmlspecialchars($typeLabels[$r['customer_type']] ?? ucfirst($r['customer_type'])) ?></td>
                    <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                        <?= htmlspecialchars($r['city'] ?: '—') ?>
                        <div class="text-[11px] text-slate-400"><?= htmlspecialchars($r['country'] ?: '') ?></div>
                    </td>
                    <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                        <span class="text-[10px] font-semibold text-slate-400"><?= htmlspecialchars($r['currency_code']) ?></span>
                        <?= $limit > 0 ? number_format($limit, 0) : '<span class="text-slate-300">—</span>' ?>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <?php if ($util === null): ?>
                            <span class="text-[11px] text-slate-400">no limit set</span>
                        <?php else: ?>
                            <div class="flex items-center gap-2">
                                <div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full <?= $bar ?> rounded-full" style="width: <?= $util ?>%"></div>
                                </div>
                                <span class="text-[11px] text-slate-500"><?= $util ?>%</span>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <span class="font-medium text-slate-800"><?= number_format($used, 2) ?></span>
                        <?php if ((float) $r['overdue'] > 0): ?>
                        <div class="text-[11px] text-red-500"><?= number_format((float)$r['overdue'], 2) ?> overdue</div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 text-[11px] rounded-full <?= $statusBadges[$r['status']] ?? 'bg-gray-100 text-gray-700' ?>"><?= ucfirst($r['status']) ?></span>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">
                        <?= $r['customer_since'] ? date('M Y', strtotime($r['customer_since'])) : '—' ?>
                        <div class="text-[11px] text-slate-400"><?= (int) $r['invoice_count'] ?> invoices</div>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="<?= url('modules/customers/view.php?id=' . $r['id']) ?>" title="View" class="px-2 py-1 text-slate-400 hover:text-indigo-600"><i class="fas fa-eye"></i></a>
                        <?php if (hasPermission('customers.edit')): ?>
                        <a href="<?= url('modules/customers/form.php?id=' . $r['id']) ?>" title="Edit" class="px-2 py-1 text-slate-400 hover:text-amber-600"><i class="fas fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (hasPermission('customers.delete')): ?>
                        <a href="<?= url('modules/customers/delete.php?id=' . $r['id']) ?>" title="Delete" class="px-2 py-1 text-slate-400 hover:text-red-600"><i class="fas fa-trash"></i></a>
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
