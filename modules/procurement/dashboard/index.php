<?php
/**
 * P1 — Procurement Dashboard (analytics view)
 * Full pipeline visibility: PR → approval → RFQ → quotations → award.
 * All charts render through window.renderCarbonChart so the IBM Light/Dark
 * toggle re-themes them live (carbon-charts.js reTheme registry).
 */
$pageTitle = 'Procurement Dashboard';
require_once __DIR__ . '/../bootstrap.php';
require_once PROCUREMENT_MODULE_DIR . '/RfqService.php';

if (!hasPermission('procurement.view')) { die('Access Denied'); }
$db = getDB();
$companyIds = procurementAccessibleCompanyIds($db, $_SESSION['user_id']);
$prScope   = procurementCompanyScopeSql($companyIds);                 // pr.company_id
$rfqScope  = procurementCompanyScopeSql($companyIds, 'r.company_id'); // aliased joins

// ---------------------------------------------------------------
// Analysis period (trend + spend-by-dept + top items)
// ---------------------------------------------------------------
$daysOptions = [90 => 'Last 90 days', 180 => 'Last 180 days', 365 => 'Last 12 months'];
$days = (int)($_GET['days'] ?? 365);
if (!isset($daysOptions[$days])) { $days = 365; }
$monthsBack = $days === 90 ? 3 : ($days === 180 ? 6 : 12);
$trendStart = date('Y-m-01', strtotime('-' . ($monthsBack - 1) . ' months'));

// ---------------------------------------------------------------
// 1 · Requisition KPIs
// ---------------------------------------------------------------
$statsStmt = $db->prepare("
    SELECT COUNT(*) total,
      COALESCE(SUM(status='draft'), 0) drafts,
      COALESCE(SUM(status IN ('submitted','pending_approval')), 0) pending,
      COALESCE(SUM(status='approved'), 0) approved,
      COALESCE(SUM(status='rejected'), 0) rejected,
      COALESCE(SUM(priority='urgent' AND status NOT IN ('cancelled','closed')), 0) urgent,
      COALESCE(SUM(YEAR(request_date)=YEAR(CURDATE()) AND MONTH(request_date)=MONTH(CURDATE())), 0) this_month_count,
      COALESCE(SUM(grand_total), 0) total_value,
      COALESCE(AVG(CASE WHEN approved_at IS NOT NULL AND submitted_at IS NOT NULL
                        THEN TIMESTAMPDIFF(DAY, submitted_at, approved_at) END), 0) avg_approval_days
    FROM purchase_requisitions pr
    WHERE {$prScope}");
$statsStmt->execute($companyIds);
$stats = $statsStmt->fetch();

$monthStmt = $db->prepare("SELECT COALESCE(SUM(grand_total),0) FROM purchase_requisitions pr
                           WHERE {$prScope}
                             AND YEAR(request_date)=YEAR(CURDATE())
                             AND MONTH(request_date)=MONTH(CURDATE())
                             AND status<>'cancelled'");
$monthStmt->execute($companyIds);
$monthValue = (float)$monthStmt->fetchColumn();

// ---------------------------------------------------------------
// 2 · Monthly value trend (continuous window, requested vs approved)
// ---------------------------------------------------------------
$trendStmt = $db->prepare("SELECT DATE_FORMAT(request_date,'%Y-%m') ym,
      COALESCE(SUM(grand_total),0) val,
      COALESCE(SUM(CASE WHEN status IN ('approved','closed') THEN grand_total ELSE 0 END),0) app_val
    FROM purchase_requisitions pr
    WHERE {$prScope} AND pr.status <> 'cancelled' AND request_date >= ?
    GROUP BY DATE_FORMAT(request_date,'%Y-%m')");
$trendStmt->execute(array_merge($companyIds, [$trendStart]));
$trendMap = [];
foreach ($trendStmt->fetchAll() as $r) { $trendMap[$r['ym']] = $r; }
$trendLabels = $trendRequested = $trendApproved = [];
for ($i = $monthsBack - 1; $i >= 0; $i--) {
    $k = date('Y-m', strtotime("-{$i} months"));
    $trendLabels[]   = date('M', strtotime($k . '-01'));
    $trendRequested[] = round((float)($trendMap[$k]['val'] ?? 0), 2);
    $trendApproved[]  = round((float)($trendMap[$k]['app_val'] ?? 0), 2);
}

// ---------------------------------------------------------------
// 3 · Status mix · Priority mix (open only)
// ---------------------------------------------------------------
$statusOrder = ['draft','submitted','pending_approval','approved','rejected','cancelled','closed'];
$statusColors = ['draft'=>'#94a3b8','submitted'=>'#60a5fa','pending_approval'=>'#f59e0b',
                 'approved'=>'#10b981','rejected'=>'#ef4444','cancelled'=>'#6b7280','closed'=>'#8b5cf6'];
$mixRows = $db->prepare("SELECT status, COUNT(*) n FROM purchase_requisitions pr WHERE {$prScope} GROUP BY status");
$mixRows->execute($companyIds);
$statusCounts = array_fill_keys($statusOrder, 0);
foreach ($mixRows->fetchAll() as $r) { $statusCounts[$r['status']] = (int)$r['n']; }
$statusLabels = []; $statusData = []; $statusPalette = [];
foreach ($statusOrder as $s) {
    if ($statusCounts[$s] > 0 || $stats['total'] == 0) {
        $statusLabels[] = ucwords(str_replace('_', ' ', $s));
        $statusData[] = $statusCounts[$s];
        $statusPalette[] = $statusColors[$s];
    }
}

$prioRows = $db->prepare("SELECT priority, COUNT(*) n FROM purchase_requisitions pr
                          WHERE {$prScope} AND status NOT IN ('cancelled','closed') GROUP BY priority");
$prioRows->execute($companyIds);
$prioMap = ['low'=>0,'normal'=>0,'high'=>0,'urgent'=>0];
foreach ($prioRows->fetchAll() as $r) { $prioMap[$r['priority']] = (int)$r['n']; }

// ---------------------------------------------------------------
// 4 · Spend by department (period)
// ---------------------------------------------------------------
$deptStmt = $db->prepare("SELECT COALESCE(d.department_name,'Unassigned') dept,
       COUNT(*) cnt, COALESCE(SUM(pr.grand_total),0) v
    FROM purchase_requisitions pr
    LEFT JOIN departments d ON d.id = pr.department_id
    WHERE {$prScope} AND pr.status <> 'cancelled' AND pr.request_date >= ?
    GROUP BY d.id, d.department_name
    ORDER BY v DESC LIMIT 8");
$deptStmt->execute(array_merge($companyIds, [$trendStart]));
$deptRows = $deptStmt->fetchAll();
$deptLabels = array_map(fn($r) => $r['dept'], $deptRows);
$deptValues = array_map(fn($r) => round((float)$r['v'], 2), $deptRows);

// ---------------------------------------------------------------
// 5 · Pipeline funnel (all-time, scoped)
// ---------------------------------------------------------------
$rfqCreatedStmt = $db->prepare("SELECT COUNT(DISTINCT pr_id) FROM request_for_quotations r
                                WHERE {$rfqScope} AND pr_id IS NOT NULL AND status <> 'cancelled'");
$rfqCreatedStmt->execute($companyIds);
$rfqFromPr = (int)$rfqCreatedStmt->fetchColumn();

$quotesReceivedStmt = $db->prepare("SELECT COUNT(*) FROM vendor_quotations q
    JOIN request_for_quotations r ON r.id = q.rfq_id
    WHERE {$rfqScope} AND q.status NOT IN ('draft','cancelled')");
$quotesReceivedStmt->execute($companyIds);
$quotesIn = (int)$quotesReceivedStmt->fetchColumn();

$awardedStmt = $db->prepare("SELECT COUNT(*) FROM request_for_quotations r
                             WHERE {$rfqScope} AND status='awarded'");
$awardedStmt->execute($companyIds);
$awardedCount = (int)$awardedStmt->fetchColumn();

$rfqTotalStmt = $db->prepare("SELECT COUNT(*),
       COALESCE(SUM(status IN ('open','sent','partially_responded','fully_responded','evaluation')),0) inflight,
       COALESCE(SUM(status IN ('open','sent','partially_responded')),0) awaiting,
       COALESCE(SUM(status='evaluation'),0) in_eval
    FROM request_for_quotations r WHERE {$rfqScope}");
$rfqTotalStmt->execute($companyIds);
$rfqAgg = $rfqTotalStmt->fetch();
$awardRate = ((int)$rfqAgg['COUNT(*)']) > 0 ? round($awardedCount / (int)$rfqAgg['COUNT(*)'] * 100) : 0;

$funnelLabels = ['Draft PRs', 'Pending Approval', 'Approved', 'RFQs Created', 'Quotes Received', 'Awards'];
$funnelData   = [(int)$stats['drafts'], (int)$stats['pending'], (int)$stats['approved'],
                 $rfqFromPr, $quotesIn, $awardedCount];
$funnelColors = ['#94a3b8', '#f59e0b', '#10b981', '#0ea5e9', '#6366f1', '#8b5cf6'];

// ---------------------------------------------------------------
// 6 · Quotation status mix (scoped)
// ---------------------------------------------------------------
$quoteStatusStmt = $db->prepare("SELECT q.status, COUNT(*) n FROM vendor_quotations q
    JOIN request_for_quotations r ON r.id = q.rfq_id WHERE {$rfqScope} GROUP BY q.status");
$quoteStatusStmt->execute($companyIds);
$quoteOrder = ['received','under_evaluation','shortlisted','selected','rejected','expired','draft','cancelled'];
$quoteColors = ['received'=>'#3b82f6','under_evaluation'=>'#8b5cf6','shortlisted'=>'#06b6d4',
                'selected'=>'#10b981','rejected'=>'#ef4444','expired'=>'#f59e0b',
                'draft'=>'#94a3b8','cancelled'=>'#6b7280'];
$quoteCounts = [];
foreach ($quoteStatusStmt->fetchAll() as $r) { $quoteCounts[$r['status']] = (int)$r['n']; }
$quoteLabels = []; $quoteData = []; $quotePalette = [];
foreach ($quoteOrder as $s) {
    if (!empty($quoteCounts[$s])) {
        $quoteLabels[] = ucwords(str_replace('_', ' ', $s));
        $quoteData[] = $quoteCounts[$s];
        $quotePalette[] = $quoteColors[$s];
    }
}
$totalQuotes = array_sum($quoteCounts);

// ---------------------------------------------------------------
// 7 · Vendor performance (top 8 by quotations handled, scoped)
// ---------------------------------------------------------------
$vendorStmt = $db->prepare("SELECT v.vendor_name,
       COUNT(*) quotes,
       COALESCE(SUM(q.status='selected'),0) won
    FROM vendor_quotations q
    JOIN vendors v ON v.id = q.vendor_id
    JOIN request_for_quotations r ON r.id = q.rfq_id
    WHERE {$rfqScope}
    GROUP BY v.id, v.vendor_name
    ORDER BY quotes DESC LIMIT 8");
$vendorStmt->execute($companyIds);
$vendorRows = $vendorStmt->fetchAll();
$vendorLabels = array_map(fn($r) => strlen($r['vendor_name']) > 16 ? substr($r['vendor_name'], 0, 15) . '…' : $r['vendor_name'], $vendorRows);
$vendorReceived = array_map(fn($r) => (int)$r['quotes'], $vendorRows);
$vendorWon = array_map(fn($r) => (int)$r['won'], $vendorRows);

// ---------------------------------------------------------------
// 8 · Most-requested items (period)
// ---------------------------------------------------------------
$itemStmt = $db->prepare("SELECT i.item_name, i.unit,
       COALESCE(SUM(i.quantity),0) qty, COALESCE(SUM(i.line_total),0) v
    FROM purchase_requisition_items i
    JOIN purchase_requisitions pr ON pr.id = i.pr_id
    WHERE {$prScope} AND pr.status <> 'cancelled' AND pr.request_date >= ?
    GROUP BY i.item_name, i.unit ORDER BY v DESC LIMIT 6");
$itemStmt->execute(array_merge($companyIds, [$trendStart]));
$itemRows = $itemStmt->fetchAll();
$itemMaxVal = 0.0;
foreach ($itemRows as $r) { $itemMaxVal = max($itemMaxVal, (float)$r['v']); }

// ---------------------------------------------------------------
// 9 · Realized savings across awarded RFQs (informational, STEP 27)
// ---------------------------------------------------------------
$savings = 0.0;
$awardedIds = $db->prepare("SELECT id FROM request_for_quotations r WHERE {$rfqScope} AND status='awarded'");
$awardedIds->execute($companyIds);
foreach ($awardedIds->fetchAll(PDO::FETCH_COLUMN) as $rid) {
    $s = RfqService::savingsSummary($db, (int)$rid);
    $savings += max(0.0, (float)($s['savings_vs_estimate'] ?? 0));
}

// ---------------------------------------------------------------
// 10 · Recent requisitions
// ---------------------------------------------------------------
$recentStmt = $db->prepare("SELECT pr.id, pr.pr_number, pr.priority, pr.status, pr.grand_total,
       pr.currency, pr.request_date, u.name requester_name, d.department_name
    FROM purchase_requisitions pr
    LEFT JOIN users u ON u.id = pr.requester_id
    LEFT JOIN departments d ON d.id = pr.department_id
    WHERE {$prScope}
    ORDER BY pr.id DESC LIMIT 7");
$recentStmt->execute($companyIds);
$recentPRs = $recentStmt->fetchAll();

$statusBadge = [
    'draft'            => 'bg-gray-100 text-gray-700',
    'submitted'        => 'bg-yellow-100 text-yellow-800',
    'pending_approval' => 'bg-yellow-100 text-yellow-800',
    'approved'         => 'bg-green-100 text-green-800',
    'rejected'         => 'bg-red-100 text-red-700',
    'cancelled'        => 'bg-gray-100 text-gray-500',
    'closed'           => 'bg-indigo-100 text-indigo-700',
];
$prioDot = ['low' => '#94a3b8', 'normal' => '#3b82f6', 'high' => '#f97316', 'urgent' => '#ef4444'];

$kpiCards = [
    ['Total Requisitions', number_format((int)$stats['total']), $stats['this_month_count'] . ' created this month', 'blue', 'fa-file-lines'],
    ['Pending Approval', number_format((int)$stats['pending']), (int)$stats['urgent'] . ' urgent open', 'yellow', 'fa-hourglass-half'],
    ['Ready for RFQ', number_format((int)$stats['approved']), 'avg approval ' . round((float)$stats['avg_approval_days'], 1) . ' days', 'green', 'fa-circle-check'],
    ['PR Value This Month', formatMoney($monthValue), 'all-time ' . formatMoney((float)$stats['total_value']), 'indigo', 'fa-sack-dollar'],
    ['RFQs In Flight', number_format((int)$rfqAgg['inflight']), (int)$rfqAgg['awaiting'] . ' awaiting vendor quotes', 'cyan', 'fa-envelope-open-text'],
    ['Quotations Received', number_format($totalQuotes), (int)($quoteCounts['under_evaluation'] ?? 0) . ' under evaluation', 'purple', 'fa-inbox'],
    ['Awards', number_format($awardedCount), $awardRate . '% of all RFQs awarded', 'orange', 'fa-gavel'],
    ['Realized Savings', formatMoney($savings), 'vs PR estimates (awarded)', 'emerald', 'fa-piggy-bank'],
];

$quickLinks = [
    ['procurement.view', 'modules/procurement/requisitions/index.php', 'Requisitions', 'fa-file-lines'],
    ['procurement.view', 'modules/procurement/requisitions/approvals.php', 'Pending Approvals', 'fa-stamp'],
    ['procurement.view', 'modules/procurement/rfq/dashboard.php', 'RFQ Center', 'fa-envelope-open-text'],
    ['procurement.view', 'modules/procurement/quotations/comparison.php', 'Quote Comparison', 'fa-scale-balanced'],
    ['procurement.pr.report', 'modules/procurement/reports/index.php', 'Reports', 'fa-chart-column'],
    ['procurement.settings.manage', 'modules/procurement/settings/index.php', 'Settings', 'fa-gear'],
];
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="text-2xl font-bold text-gray-800">Procurement Command Center</h1>
    <p class="text-gray-600">Requisitions → approval → RFQ → quotations → award</p></div>
    <div class="flex gap-2">
        <?php if (hasPermission('procurement.pr.create')): ?>
        <a href="<?= url('modules/procurement/requisitions/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm"><i class="fas fa-plus mr-1"></i>New Requisition</a>
        <?php endif; ?>
    </div>
</div>

<nav class="flex flex-wrap gap-2 mb-5" aria-label="Procurement navigation">
    <?php foreach ($quickLinks as [$permission, $route, $label, $icon]): ?>
        <?php if (hasPermission($permission)): ?>
        <a href="<?= url($route) ?>" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm text-gray-700">
            <i class="fas <?= $icon ?> mr-1 text-blue-500"></i><?= $label ?>
        </a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>

<div class="flex items-center justify-between mb-3">
    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Key Performance Indicators</h2>
    <div class="flex gap-1 text-xs">
        <?php foreach ($daysOptions as $d => $label): ?>
        <a href="?days=<?= $d ?>" class="px-3 py-1 rounded-full border transition <?= $d === $days ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <?php foreach ($kpiCards as [$label, $value, $sub, $color, $icon]): ?>
    <div class="bg-white rounded-lg shadow p-4 flex items-center justify-between">
        <div class="min-w-0">
            <p class="text-[11px] text-gray-500 uppercase leading-tight truncate"><?= $label ?></p>
            <p class="font-bold text-lg leading-tight mt-0.5 truncate"><?= $value ?></p>
            <p class="text-[11px] text-gray-500 truncate"><?= $sub ?></p>
        </div>
        <div class="bg-<?= $color ?>-100 p-2.5 rounded-full shrink-0 ml-2">
            <i class="fas <?= $icon ?> text-<?= $color ?>-600"></i>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
    <div class="bg-white rounded-lg shadow p-4 lg:col-span-2">
        <div class="flex justify-between items-center mb-2">
            <h3 class="text-sm font-semibold text-gray-800">Requisition Value Trend</h3>
            <span class="text-xs text-gray-500"><span class="inline-block w-2 h-2 rounded-full bg-blue-500 mr-1"></span>Requested <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 ml-3 mr-1"></span>Approved</span>
        </div>
        <div id="trendChart"></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-2">Requisition Status Mix</h3>
        <div id="statusChart"></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-2">Spend by Department <span class="font-normal text-gray-400 text-xs">(<?= $daysOptions[$days] ?>)</span></h3>
        <div id="deptChart"></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-2">Procurement Pipeline</h3>
        <div id="funnelChart"></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-2">Open Requisitions by Priority</h3>
        <div id="priorityChart"></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-2">Vendor Performance <span class="font-normal text-gray-400 text-xs">(quotes vs wins)</span></h3>
        <div id="vendorChart"></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">Most-Requested Items <span class="font-normal text-gray-400 text-xs">(<?= $daysOptions[$days] ?>)</span></h3>
        <?php if (!empty($itemRows)): ?>
        <div class="space-y-3">
            <?php foreach ($itemRows as $it): ?>
            <?php $pct = $itemMaxVal > 0 ? round((float)$it['v'] / $itemMaxVal * 100) : 0; ?>
            <div>
                <div class="flex justify-between text-sm mb-1 gap-2">
                    <span class="text-gray-700 truncate" title="<?= sanitize($it['item_name']) ?>"><?= sanitize($it['item_name']) ?></span>
                    <span class="font-mono text-xs text-gray-800 whitespace-nowrap"><?= formatMoney((float)$it['v']) ?></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-1.5">
                    <div class="bg-cyan-500 h-1.5 rounded-full" style="width: <?= max(2, $pct) ?>%"></div>
                </div>
                <p class="text-[11px] text-gray-500 mt-0.5"><?= rtrim(rtrim(number_format((float)$it['qty'], 2), '0'), '.') ?> <?= sanitize($it['unit']) ?> requested</p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-sm text-gray-500 text-center py-8">No requisition items in this period</p>
        <?php endif; ?>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-2">Quotation Outcomes</h3>
        <div id="quoteChart"></div>
    </div>
</div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg shadow p-4 lg:col-span-2 overflow-x-auto">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-sm font-semibold text-gray-800">Latest Requisitions</h3>
                <a href="<?= url('modules/procurement/requisitions/index.php') ?>" class="text-xs text-blue-600 hover:text-blue-900">View all →</a>
            </div>
            <?php if (!empty($recentPRs)): ?>
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-gray-500 border-b">
                    <th class="py-2 pr-3">PR Number</th><th class="py-2 pr-3">Department</th>
                    <th class="py-2 pr-3">Requester</th><th class="py-2 pr-3">Date</th>
                    <th class="py-2 pr-3 text-right">Value</th><th class="py-2 pr-3">Status</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($recentPRs as $pr): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-2 pr-3">
                            <a href="<?= url('modules/procurement/requisitions/view.php?id=' . (int)$pr['id']) ?>" class="font-mono text-blue-600 hover:underline"><?= sanitize($pr['pr_number']) ?></a>
                            <span class="inline-block w-2 h-2 rounded-full ml-1 align-middle" title="<?= ucfirst($pr['priority']) ?>" style="background: <?= $prioDot[$pr['priority']] ?? '#94a3b8' ?>"></span>
                        </td>
                        <td class="py-2 pr-3 text-gray-700 truncate max-w-[140px]" title="<?= sanitize($pr['department_name'] ?? '') ?>"><?= sanitize($pr['department_name'] ?? '—') ?></td>
                        <td class="py-2 pr-3 text-gray-700 truncate max-w-[120px]"><?= sanitize($pr['requester_name'] ?? '—') ?></td>
                        <td class="py-2 pr-3 text-gray-500 whitespace-nowrap"><?= date('M j, Y', strtotime($pr['request_date'])) ?></td>
                        <td class="py-2 pr-3 text-right font-mono whitespace-nowrap"><?= sanitize($pr['currency']) ?> <?= number_format((float)$pr['grand_total'], 2) ?></td>
                        <td class="py-2 pr-3"><span class="px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap <?= $statusBadge[$pr['status']] ?? 'bg-gray-100 text-gray-600' ?>"><?= ucwords(str_replace('_', ' ', $pr['status'])) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-sm text-gray-500 text-center py-8">No requisitions yet — create the first one.</p>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Awaiting Procurement Action</h3>
            <div class="rounded-lg p-4 mb-3 bg-blue-50">
                <p class="text-3xl font-bold text-blue-600"><?= (int)$stats['approved'] ?></p>
                <p class="text-sm text-gray-600 mt-1">Approved requisitions ready to move to RFQ.</p>
                <a href="<?= url('modules/procurement/requisitions/index.php?status=approved') ?>" class="inline-block mt-2 text-blue-600 hover:underline text-sm">View approved →</a>
            </div>
            <div class="rounded-lg p-4 mb-3 bg-yellow-50">
                <p class="text-3xl font-bold text-yellow-600"><?= (int)$stats['pending'] ?></p>
                <p class="text-sm text-gray-600 mt-1">Awaiting approval decision<span class="text-red-500 font-medium"> · <?= (int)$stats['urgent'] ?> urgent</span>.</p>
                <a href="<?= url('modules/procurement/requisitions/approvals.php') ?>" class="inline-block mt-2 text-yellow-700 hover:underline text-sm">Review queue →</a>
            </div>
            <div class="rounded-lg p-4 bg-emerald-50">
                <p class="text-3xl font-bold text-emerald-600"><?= (int)$rfqAgg['in_eval'] ?></p>
                <p class="text-sm text-gray-600 mt-1">RFQs in evaluation — quotations ready to compare &amp; award.</p>
                <a href="<?= url('modules/procurement/rfq/dashboard.php') ?>" class="inline-block mt-2 text-emerald-700 hover:underline text-sm">Open RFQ center →</a>
            </div>
        </div>
    </div>

    <?php
    $billStats = BillService::dashboardStats($db);
    $billKpiCards = [
        ['Open Bills', number_format((int)($billStats['draft'] ?? 0) + (int)($billStats['pending_approval'] ?? 0)), 'Draft or pending approval', 'blue', 'fa-file-invoice'],
        ['Pending Approval', number_format((int)($billStats['pending_approval'] ?? 0)), 'Awaiting review', 'yellow', 'fa-hourglass-half'],
        ['Matched', number_format((int)($billStats['matched'] ?? 0)), '3-way match passed', 'green', 'fa-circle-check'],
        ['Exceptions', number_format((int)($billStats['exception'] ?? 0)), 'Require attention', 'red', 'fa-triangle-exclamation'],
        ['Posted', number_format((int)($billStats['posted'] ?? 0)), 'Posted to GL', 'emerald', 'fa-book'],
        ['Open Value', formatMoney((float)($billStats['open_value'] ?? 0)), 'Total procurement value', 'indigo', 'fa-sack-dollar'],
    ];
    ?>
    <div class="mt-6">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">Vendor Bills</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
            <?php foreach ($billKpiCards as [$label, $value, $sub, $color, $icon]): ?>
            <div class="bg-white rounded-lg shadow p-4 flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] text-gray-500 uppercase leading-tight truncate"><?= $label ?></p>
                    <p class="font-bold text-lg leading-tight mt-0.5 truncate"><?= $value ?></p>
                    <p class="text-[11px] text-gray-500 truncate"><?= $sub ?></p>
                </div>
                <div class="bg-<?= $color ?>-100 p-2.5 rounded-full shrink-0 ml-2">
                    <i class="fas <?= $icon ?> text-<?= $color ?>-600"></i>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-sm font-semibold text-gray-800">Quick Links — Vendor Bills</h3>
                <a href="<?= url('modules/procurement/bills/index.php') ?>" class="text-xs text-blue-600 hover:text-blue-900">View all bills →</a>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if (hasPermission('procurement.bills.create')): ?>
                <a href="<?= url('modules/procurement/bills/create.php') ?>" class="bg-blue-50 hover:bg-blue-100 text-blue-700 px-3 py-2 rounded text-sm"><i class="fas fa-plus mr-1"></i>New Bill</a>
                <?php endif; ?>
                <?php if (hasPermission('procurement.bills.view')): ?>
                <a href="<?= url('modules/procurement/bills/index.php?status=pending_approval') ?>" class="bg-yellow-50 hover:bg-yellow-100 text-yellow-700 px-3 py-2 rounded text-sm"><i class="fas fa-hourglass-half mr-1"></i>Pending Approval</a>
                <?php endif; ?>
                <?php if (hasPermission('procurement.bills.match')): ?>
                <a href="<?= url('modules/procurement/bills/match.php') ?>" class="bg-purple-50 hover:bg-purple-100 text-purple-700 px-3 py-2 rounded text-sm"><i class="fas fa-code-compare mr-1"></i>3-Way Match</a>
                <?php endif; ?>
                <?php if (hasPermission('procurement.bills.report')): ?>
                <a href="<?= url('modules/procurement/bills/reports/index.php?report=exceptions') ?>" class="bg-red-50 hover:bg-red-100 text-red-700 px-3 py-2 rounded text-sm"><i class="fas fa-triangle-exclamation mr-1"></i>Exceptions</a>
                <a href="<?= url('modules/procurement/bills/reports/index.php?report=outstanding') ?>" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-3 py-2 rounded text-sm"><i class="fas fa-chart-line mr-1"></i>Outstanding</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const render = window.renderCarbonChart;
    if (!render) return;
    const moneyAxis = { axes: { left: { ticks: { formatter: v => Number(v).toLocaleString() } } } };

    // 1 · Value trend (requested vs approved)
    const trendEl = document.getElementById('trendChart');
    if (trendEl) {
        render(trendEl, 'line', {
            labels: <?= json_encode($trendLabels) ?>,
            datasets: [
                { label: 'Requested', data: <?= json_encode($trendRequested) ?>, backgroundColor: 'rgba(59,130,246,0.12)', borderColor: '#3b82f6' },
                { label: 'Approved',  data: <?= json_encode($trendApproved) ?>,  backgroundColor: 'rgba(16,185,129,0.12)', borderColor: '#10b981' }
            ]
        }, Object.assign({ legend: { position: 'bottom' } }, moneyAxis));
    }

    // 2 · Status mix doughnut
    const statusEl = document.getElementById('statusChart');
    if (statusEl && <?= json_encode(count($statusData)) ?> > 0) {
        render(statusEl, 'doughnut', {
            labels: <?= json_encode($statusLabels) ?>,
            datasets: [{ data: <?= json_encode($statusData) ?>, backgroundColor: <?= json_encode($statusPalette) ?> }]
        }, { legend: { position: 'bottom' } });
    } else if (statusEl) {
        statusEl.innerHTML = '<p class="text-sm text-gray-400 text-center py-20">No data available</p>';
    }

    // 3 · Spend by department (horizontal)
    const deptEl = document.getElementById('deptChart');
    if (deptEl && <?= json_encode(count($deptValues)) ?> > 0) {
        render(deptEl, 'horizontal', {
            labels: <?= json_encode($deptLabels) ?>,
            datasets: [{ label: 'PR Value', data: <?= json_encode($deptValues) ?>, backgroundColor: '#3b82f6' }]
        }, moneyAxis);
    } else if (deptEl) {
        deptEl.innerHTML = '<p class="text-sm text-gray-400 text-center py-20">No spend recorded</p>';
    }

    // 4 · Pipeline funnel (horizontal, stage-colored slices)
    const funnelEl = document.getElementById('funnelChart');
    if (funnelEl) {
        render(funnelEl, 'horizontal', {
            labels: <?= json_encode($funnelLabels) ?>,
            datasets: [{ label: 'Items at stage', data: <?= json_encode($funnelData) ?>, backgroundColor: <?= json_encode($funnelColors) ?> }]
        }, {});
    }

    // 5 · Priority mix doughnut
    const prioEl = document.getElementById('priorityChart');
    if (prioEl) {
        render(prioEl, 'doughnut', {
            labels: ['Low', 'Normal', 'High', 'Urgent'],
            datasets: [{ data: [<?= (int)$prioMap['low'] ?>, <?= (int)$prioMap['normal'] ?>, <?= (int)$prioMap['high'] ?>, <?= (int)$prioMap['urgent'] ?>],
                         backgroundColor: ['#94a3b8', '#3b82f6', '#f97316', '#ef4444'] }]
        }, { legend: { position: 'bottom' } });
    }

    // 6 · Vendor performance (grouped: quotes vs wins)
    const vendorEl = document.getElementById('vendorChart');
    if (vendorEl && <?= json_encode(count($vendorRows)) ?> > 0) {
        render(vendorEl, 'grouped', {
            labels: <?= json_encode($vendorLabels) ?>,
            datasets: [
                { label: 'Quotations', data: <?= json_encode($vendorReceived) ?>, backgroundColor: '#818cf8' },
                { label: 'Wins',       data: <?= json_encode($vendorWon) ?>,      backgroundColor: '#10b981' }
            ]
        }, { legend: { position: 'bottom' } });
    } else if (vendorEl) {
        vendorEl.innerHTML = '<p class="text-sm text-gray-400 text-center py-20">No quotations yet</p>';
    }

    // 7 · Quotation outcomes doughnut
    const quoteEl = document.getElementById('quoteChart');
    if (quoteEl && <?= json_encode(count($quoteData)) ?> > 0) {
        render(quoteEl, 'doughnut', {
            labels: <?= json_encode($quoteLabels) ?>,
            datasets: [{ data: <?= json_encode($quoteData) ?>, backgroundColor: <?= json_encode($quotePalette) ?> }]
        }, { legend: { position: 'bottom' } });
    } else if (quoteEl) {
        quoteEl.innerHTML = '<p class="text-sm text-gray-400 text-center py-20">No quotations yet</p>';
    }
});
</script>

<?php require_once PROCUREMENT_APP_ROOT . '/includes/footer.php'; ?>
