<?php
$pageTitle = 'Contractors';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';
require_once __DIR__ . '/document_service.php';
require_once __DIR__ . '/contract_service.php';

if (!hasPermission('contractors.view')) {
    die('Access Denied');
}

$db = getDB();

$categories = lookupOptions('contractor.category', [
    'consultant' => 'Consultant', 'technical' => 'Technical / Engineering', 'it_services' => 'IT Services',
    'creative' => 'Creative / Media', 'administrative' => 'Administrative Support', 'skilled_labour' => 'Skilled Labour',
    'driver' => 'Driver / Logistics', 'training' => 'Training', 'other' => 'Other',
]);

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$status   = trim($_GET['status'] ?? '');
$source   = trim($_GET['source'] ?? '');
$compliance = trim($_GET['compliance'] ?? '');

$sortable = [
    'name'       => "c.first_name, c.last_name",
    'code'       => "c.contractor_code",
    'category'   => "c.contractor_category",
    'status'     => "c.status",
    'end_date'   => "cur.end_date",
    'rate'       => "cur.rate_amount",
];
$sortKey = $_GET['sort'] ?? 'name';
if (!isset($sortable[$sortKey])) { $sortKey = 'name'; }
$dir = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

$expiredExpr = "(
       (c.emirates_id_expiry IS NOT NULL AND c.emirates_id_expiry < CURDATE())
    OR (c.passport_expiry     IS NOT NULL AND c.passport_expiry     < CURDATE())
    OR (c.visa_expiry         IS NOT NULL AND c.visa_expiry         < CURDATE())
    OR (c.work_permit_expiry  IS NOT NULL AND c.work_permit_expiry  < CURDATE())
    OR (c.labor_card_expiry   IS NOT NULL AND c.labor_card_expiry   < CURDATE())
)";
$expiringExpr = "(
       (c.emirates_id_expiry BETWEEN CURDATE() AND CURDATE() + INTERVAL 60 DAY)
    OR (c.passport_expiry     BETWEEN CURDATE() AND CURDATE() + INTERVAL 60 DAY)
    OR (c.visa_expiry         BETWEEN CURDATE() AND CURDATE() + INTERVAL 60 DAY)
    OR (c.work_permit_expiry  BETWEEN CURDATE() AND CURDATE() + INTERVAL 60 DAY)
    OR (c.labor_card_expiry   BETWEEN CURDATE() AND CURDATE() + INTERVAL 60 DAY)
)";

$where = ["c.deleted_at IS NULL"];
$params = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $digits = preg_replace('/\D/', '', $search);
    $alnum  = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $search));
    $where[] = "(
           CONCAT(c.first_name, ' ', c.last_name) LIKE ?
        OR c.contractor_code    LIKE ?
        OR c.email              LIKE ?
        OR c.specialisation     LIKE ?
        OR c.emirates_id_number LIKE ?
        OR v.vendor_name        LIKE ?
        OR c.passport_number    LIKE ?
        OR c.visa_number        LIKE ?
        OR c.work_permit_number LIKE ?
        OR c.labor_card_number  LIKE ?
    )";
    // Document numbers are stored without separators, so a dashed search term has
    // to be reduced to its digits/alphanumerics to match.
    $digitsLike = $digits !== '' ? '%' . $digits . '%' : $like;
    $alnumLike  = $alnum  !== '' ? '%' . $alnum  . '%' : $like;
    array_push($params, $like, $like, $like, $like, $digitsLike, $like, $alnumLike, $alnumLike, $alnumLike, $alnumLike);
}
if (in_array($category, array_keys($categories), true)) {
    $where[] = "c.contractor_category = ?";
    $params[] = $category;
}
if (in_array($status, ['active', 'suspended', 'terminated'], true)) {
    $where[] = "c.status = ?";
    $params[] = $status;
}
if ($source === 'agency') {
    $where[] = "cur.supply_source = 'agency'";
} elseif ($source === 'direct') {
    $where[] = "(cur.id IS NULL OR cur.supply_source = 'direct')";
}
if ($compliance === 'expired') {
    $where[] = $expiredExpr;
} elseif ($compliance === 'expiring') {
    $where[] = $expiringExpr . " AND NOT " . $expiredExpr;
} elseif ($compliance === 'valid') {
    $where[] = "NOT " . $expiredExpr . " AND NOT " . $expiringExpr;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

/*
|--------------------------------------------------------------------------
| KPIs — measured on their own, never on the filtered page
|--------------------------------------------------------------------------
*/
$kpis = $db->query("
    SELECT
        COUNT(*)                                                              AS total,
        SUM(c.status = 'active')                                              AS active,
        SUM(c.status = 'suspended')                                           AS suspended,
        SUM($expiredExpr)                                                     AS compliance_expired,
        SUM($expiringExpr AND NOT $expiredExpr)                                AS compliance_expiring,
        SUM(cur.id IS NOT NULL AND cur.supply_source = 'agency')               AS agency_supplied,
        SUM(cur.id IS NOT NULL AND cur.status = 'active'
            AND cur.end_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 60 DAY) AS contracts_ending
    FROM contractors c
    LEFT JOIN contractor_contracts cur
           ON cur.contractor_id = c.id AND cur.is_current = 1 AND cur.deleted_at IS NULL
    WHERE c.deleted_at IS NULL
")->fetch();

$runRateRows = $db->query("
    SELECT rate_amount, rate_basis FROM contractor_contracts
     WHERE is_current = 1 AND status = 'active' AND deleted_at IS NULL
")->fetchAll();
$monthlyRunRate = 0.0;
foreach ($runRateRows as $r) {
    $monthlyRunRate += contractorMonthlyEquivalent($r['rate_amount'], $r['rate_basis']);
}

$baseCurrency = function_exists('getActiveCurrency') ? getActiveCurrency() : 'AED';

/*
|--------------------------------------------------------------------------
| Register
|--------------------------------------------------------------------------
*/
$countStmt = $db->prepare("
    SELECT COUNT(*)
      FROM contractors c
      LEFT JOIN contractor_contracts cur
             ON cur.contractor_id = c.id AND cur.is_current = 1 AND cur.deleted_at IS NULL
      LEFT JOIN vendors v ON v.id = cur.agency_vendor_id
      $whereSql
");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$perPage = 25;
$pagination = paginate($total, $perPage, getPageNumber());

$listStmt = $db->prepare("
    SELECT c.*,
           cur.id AS contract_id, cur.contract_number, cur.title AS contract_title,
           cur.contract_type, cur.status AS contract_status, cur.start_date, cur.end_date,
           cur.rate_amount, cur.rate_basis, cur.currency_code, cur.supply_source,
           cur.not_to_exceed_amount, cur.approved_at,
           v.vendor_name AS agency_name,
           dep.department_name
      FROM contractors c
      LEFT JOIN contractor_contracts cur
             ON cur.contractor_id = c.id AND cur.is_current = 1 AND cur.deleted_at IS NULL
      LEFT JOIN vendors v ON v.id = cur.agency_vendor_id
      LEFT JOIN departments dep ON dep.id = cur.department_id
      $whereSql
     ORDER BY {$sortable[$sortKey]} $dir
       LIMIT ? OFFSET ?
");
$listStmt->execute(array_merge($params, [$perPage, $pagination['offset']]));
$rows = $listStmt->fetchAll();

$statusColors = [
    'active'     => 'bg-emerald-100 text-emerald-700',
    'suspended'  => 'bg-amber-100 text-amber-700',
    'terminated' => 'bg-red-100 text-red-700',
];
$typeLabels = lookupOptions('contractor.contract_type', [
    'statement_of_work' => 'Statement of Work', 'purchase_order' => 'Purchase Order',
    'direct_engagement' => 'Direct Engagement', 'retainer' => 'Retainer',
    'framework_agreement' => 'Framework Agreement', 'master_service_agreement' => 'MSA',
    'staffing_placement' => 'Agency Placement',
]);
$expiryDates = function ($row) {
    return array_filter([
        $row['emirates_id_expiry'] ?? '', $row['passport_expiry'] ?? '', $row['visa_expiry'] ?? '',
        $row['work_permit_expiry'] ?? '', $row['labor_card_expiry'] ?? '',
    ]);
};
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Contractor Register</h1>
        <p class="text-gray-500 text-sm mt-1">
            Contingent workers, their right-to-work compliance and the contracts engaging them.
        </p>
    </div>
    <div class="flex items-center gap-2">
        <?php if (hasPermission('contractors.create')): ?>
        <a href="<?= url('modules/contractors/form.php') ?>"
           class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition shadow-sm">
            <i class="fas fa-plus mr-2"></i>New Contractor
        </a>
        <?php endif; ?>
        <a href="<?= url('modules/hrms_dashboard/index.php') ?>"
           class="px-4 py-2 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition">
            <i class="fas fa-users-cog mr-2"></i>HRMS
        </a>
    </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
    <?php
    $cards = [
        ['Active',        (int) $kpis['active'],            'fa-user-check',        'text-emerald-600', 'modules/contractors/index.php?status=active'],
        ['On Register',   (int) $kpis['total'],             'fa-users',             'text-slate-700',   'modules/contractors/index.php'],
        ['Agency-Supplied', (int) $kpis['agency_supplied'], 'fa-building-user',     'text-indigo-600',  'modules/contractors/index.php?source=agency'],
        ['Docs Expired',  (int) $kpis['compliance_expired'],'fa-triangle-exclamation', 'text-red-600',  'modules/contractors/index.php?compliance=expired'],
        ['Docs Expiring', (int) $kpis['compliance_expiring'],'fa-hourglass-half',   'text-amber-600',   'modules/contractors/index.php?compliance=expiring'],
        ['Contracts ≤60d', (int) $kpis['contracts_ending'], 'fa-file-signature',    'text-amber-600',   'modules/contractors/index.php?sort=end_date'],
    ];
    foreach ($cards as [$label, $value, $icon, $color, $href]): ?>
    <a href="<?= url($href) ?>" class="bg-white rounded-xl shadow-sm border border-slate-100 p-4 hover:shadow-md transition block">
        <div class="flex items-center gap-2">
            <i class="fas <?= $icon ?> <?= $color ?> text-sm"></i>
            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide"><?= $label ?></span>
        </div>
        <p class="text-2xl font-bold mt-1 <?= $color ?>"><?= number_format($value) ?></p>
    </a>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4 mb-5">
    <div class="flex flex-wrap items-end gap-3">
        <form method="get" class="flex flex-wrap items-end gap-3 flex-1">
            <div class="min-w-[220px] flex-1">
                <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wide mb-1">Search</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                       placeholder="Name, code, Emirates ID, passport, visa, work permit, agency…"
                       class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            <div>
                <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wide mb-1">Category</label>
                <select name="category" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
                    <option value="">All</option>
                    <?php foreach ($categories as $k => $l): ?>
                    <option value="<?= htmlspecialchars($k) ?>" <?= $category === $k ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wide mb-1">Status</label>
                <select name="status" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
                    <option value="">All</option>
                    <?php foreach (['active', 'suspended', 'terminated'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wide mb-1">Source</label>
                <select name="source" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
                    <option value="">All</option>
                    <option value="direct" <?= $source === 'direct' ? 'selected' : '' ?>>Direct</option>
                    <option value="agency" <?= $source === 'agency' ? 'selected' : '' ?>>Agency-supplied</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wide mb-1">Compliance</label>
                <select name="compliance" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
                    <option value="">All</option>
                    <option value="expired" <?= $compliance === 'expired' ? 'selected' : '' ?>>Expired document</option>
                    <option value="expiring" <?= $compliance === 'expiring' ? 'selected' : '' ?>>Expiring ≤ 60 days</option>
                    <option value="valid" <?= $compliance === 'valid' ? 'selected' : '' ?>>All valid</option>
                </select>
            </div>
            <button class="px-4 py-2 bg-slate-800 text-white text-sm rounded-lg hover:bg-slate-900 transition">Apply</button>
            <?php if ($search !== '' || $category !== '' || $status !== '' || $source !== '' || $compliance !== ''): ?>
            <a href="<?= url('modules/contractors/index.php') ?>" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Clear</a>
            <?php endif; ?>
        </form>
        <div class="text-right">
            <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Current run-rate</p>
            <p class="text-lg font-bold text-slate-800"><?= htmlspecialchars($baseCurrency) ?> <?= number_format($monthlyRunRate, 0) ?>/mo</p>
            <p class="text-[11px] text-slate-400">active current contracts, monthly equivalent</p>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <?php
                    $th = function ($label, $key = null) use ($sortKey, $dir) {
                        $arrow = '';
                        $href = $_SERVER['REQUEST_URI'];
                        if ($key) {
                            $newDir = ($sortKey === $key && $dir === 'asc') ? 'desc' : 'asc';
                            $arrow = $sortKey === $key ? ($dir === 'asc' ? ' <i class="fas fa-arrow-up text-[10px]"></i>' : ' <i class="fas fa-arrow-down text-[10px]"></i>') : '';
                            $href = url('modules/contractors/index.php?' . http_build_query(array_merge($_GET, ['sort' => $key, 'dir' => $newDir])));
                        }
                        echo '<th class="px-4 py-3 text-left text-[10px] font-semibold text-slate-500 uppercase tracking-wide">'
                            . ($key ? '<a href="' . htmlspecialchars($href) . '" class="hover:text-slate-800">' . $label . $arrow . '</a>' : $label)
                            . '</th>';
                    };
                    $th('Contractor', 'name');
                    $th('Code', 'code');
                    $th('Category', 'category');
                    $th('Current Contract', null);
                    $th('Rate', 'rate');
                    $th('Ends', 'end_date');
                    $th('Compliance', null);
                    $th('Status', 'status');
                    $th('Actions', null);
                    ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="9" class="px-4 py-14 text-center">
                        <i class="fas fa-user-plus text-3xl text-slate-300 mb-3 block"></i>
                        <p class="text-sm text-slate-500">No contractors match these filters.</p>
                        <?php if (hasPermission('contractors.create')): ?>
                        <a href="<?= url('modules/contractors/form.php') ?>" class="inline-block mt-3 text-sm text-indigo-600 hover:text-indigo-800">
                            Register the first contractor
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($rows as $r):
                $name = trim($r['first_name'] . ' ' . $r['last_name']);
                $dates = $expiryDates($r);
                $worst = null;
                foreach ($dates as $d) {
                    $days = intdiv(strtotime($d) - time(), 86400);
                    if ($worst === null || $days < $worst) { $worst = $days; }
                }
                $state = $r['contract_id'] ? contractorContractState($r) : null;
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <?php if (!empty($r['photo_path'])): ?>
                            <img src="<?= attachmentFileUrl($r['photo_path']) ?>" alt="" class="h-9 w-9 rounded-full object-cover border border-slate-200">
                            <?php else: ?>
                            <div class="h-9 w-9 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm font-bold">
                                <?= strtoupper(substr($r['first_name'], 0, 1)) ?>
                            </div>
                            <?php endif; ?>
                            <div class="min-w-0">
                                <a href="<?= url('modules/contractors/view.php?id=' . (int) $r['id']) ?>"
                                   class="text-sm font-semibold text-slate-800 hover:text-indigo-700 block truncate"><?= htmlspecialchars($name) ?></a>
                                <p class="text-[11px] text-slate-400 truncate">
                                    <?= htmlspecialchars($r['specialisation'] ?: ($r['nationality'] ?: '—')) ?>
                                </p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3"><span class="font-mono text-xs text-slate-500"><?= htmlspecialchars($r['contractor_code']) ?></span></td>
                    <td class="px-4 py-3 text-xs text-slate-600"><?= htmlspecialchars($categories[$r['contractor_category']] ?? '—') ?></td>
                    <td class="px-4 py-3">
                        <?php if ($r['contract_id']): ?>
                        <a href="<?= url('modules/contractors/view.php?id=' . (int) $r['id'] . '#contracts') ?>"
                           class="text-xs font-medium text-slate-700 hover:text-indigo-700 block truncate max-w-[190px]">
                            <?= htmlspecialchars($r['contract_title']) ?>
                        </a>
                        <p class="text-[11px] text-slate-400">
                            <?= htmlspecialchars($typeLabels[$r['contract_type']] ?? ($r['contract_type'] ?: 'Unspecified')) ?>
                            <?php if ($r['supply_source'] === 'agency' && $r['agency_name']): ?>
                                &middot; via <?= htmlspecialchars($r['agency_name']) ?>
                            <?php endif; ?>
                        </p>
                        <?php else: ?>
                        <span class="text-[11px] text-slate-400 italic">No contract yet</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600 whitespace-nowrap">
                        <?php if ($r['rate_amount'] !== null && (float)$r['rate_amount'] > 0): ?>
                            <span class="font-medium text-slate-800"><?= htmlspecialchars($r['currency_code'] ?? '') ?> <?= number_format((float)$r['rate_amount'], 2) ?></span>
                            <span class="text-slate-400">/<?= htmlspecialchars($r['rate_basis'] ?: 'rate') ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-xs whitespace-nowrap">
                        <?php if ($r['end_date']): ?>
                            <span class="<?= $state && in_array($state['key'], ['expired', 'ending'], true) ? $state['class'] . ' px-2 py-0.5 rounded-full' : 'text-slate-600' ?>">
                                <?= date('M d, Y', strtotime($r['end_date'])) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-slate-400">Open-ended</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-xs whitespace-nowrap">
                        <?php if ($worst === null): ?>
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">No dates</span>
                        <?php elseif ($worst < 0): ?>
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-red-100 text-red-700">Expired <?= abs($worst) ?>d ago</span>
                        <?php elseif ($worst <= 60): ?>
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-700"><?= $worst ?>d left</span>
                        <?php else: ?>
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Valid</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-[11px] px-2 py-0.5 rounded-full <?= $statusColors[$r['status']] ?? 'bg-slate-100 text-slate-600' ?>">
                            <?= ucfirst($r['status']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <a href="<?= url('modules/contractors/view.php?id=' . (int) $r['id']) ?>" class="text-slate-500 hover:text-indigo-600" title="Profile">
                                <i class="fas fa-folder-open"></i>
                            </a>
                            <?php if (hasPermission('contractors.edit')): ?>
                            <a href="<?= url('modules/contractors/form.php?id=' . (int) $r['id']) ?>" class="text-slate-500 hover:text-indigo-600" title="Edit">
                                <i class="fas fa-pen-to-square"></i>
                            </a>
                            <a href="<?= url('modules/contractors/contract_form.php?contractor_id=' . (int) $r['id']) ?>" class="text-slate-500 hover:text-indigo-600" title="New contract">
                                <i class="fas fa-file-circle-plus"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (hasPermission('contractors.delete')): ?>
                            <a href="<?= url('modules/contractors/delete.php?id=' . (int) $r['id']) ?>" class="text-slate-500 hover:text-red-600" title="Delete">
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total > 0): ?>
    <div class="px-4 py-3 border-t border-slate-100 flex items-center justify-between">
        <p class="text-xs text-slate-500"><?= number_format($total) ?> contractor<?= $total === 1 ? '' : 's' ?></p>
        <?= paginationLinks($pagination, '?') ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
