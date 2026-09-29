<?php
/**
 * Inventory Portal — landing page & command center.
 *
 * Single sidebar entry point for all inventory submodules. Provides:
 *   - KPI cards (stock value, items, on-hand, reserved, available)
 *   - Carbon Charts visualizations (trend, movement mix, warehouse
 *     breakdown, workflow status)
 *   - Quick-link navigation to every submodule (Current Stock, Adjustments,
 *     Transfers, Ledger, Valuation, Cost Audit, Reservations,
 *     Reconciliation, Reports, Settings)
 *
 * Charts render through window.renderCarbonChart so the IBM Light/Dark
 * toggle re-themes them live (carbon-charts.js reTheme registry).
 */
$pageTitle = 'Inventory';
require_once '../../includes/header.php';
require_once __DIR__ . '/InventoryEngine.php';

if (!hasPermission('inventory.view')) { die('Access Denied'); }
$db = getDB();

// ---------------------------------------------------------------
// Company scope (mirrors procurementAccessibleCompanyIds pattern)
// ---------------------------------------------------------------
$accessibleCompanies = [];
try {
    if (!class_exists('CompanyService')) {
        require_once __DIR__ . '/../finance/group/CompanyService.php';
    }
    if (class_exists('CompanyService')) {
        $accessibleCompanies = CompanyService::accessibleCompanies($db, (int)$_SESSION['user_id']);
    }
} catch (Throwable $e) { $accessibleCompanies = []; }
$companyIds = array_values(array_unique(array_filter(
    array_map(static fn($c) => (int)($c['id'] ?? 0), $accessibleCompanies)
)));
$balScope = $companyIds
    ? 'b.company_id IN (' . implode(',', array_fill(0, count($companyIds), '?')) . ')'
    : '1=0';
$balParams = $companyIds;

// ---------------------------------------------------------------
// 1 · Headline KPIs
// ---------------------------------------------------------------
$totalItems      = (int)$db->query("SELECT COUNT(*) FROM inventory_items WHERE status='active'")->fetchColumn();
$totalOnHand     = (float)$db->query("SELECT COALESCE(SUM(qty_on_hand),0) FROM inventory_stock_balances")->fetchColumn();
$totalReserved   = (float)$db->query("SELECT COALESCE(SUM(qty_reserved),0) FROM inventory_stock_balances")->fetchColumn();
$totalAvailable  = max(0.0, $totalOnHand - $totalReserved);
$totalValue      = (float)$db->query("SELECT COALESCE(SUM(stock_value),0) FROM inventory_stock_balances")->fetchColumn();

// Low / out-of-stock items (simple min_quantity rule from the item master)
$lowStockItems = $db->query("
    SELECT i.id, i.item_name, i.item_code, i.min_quantity,
           COALESCE(b.qty_on_hand, 0) qty_on_hand
    FROM inventory_items i
    LEFT JOIN (
        SELECT item_id, SUM(qty_on_hand) qty_on_hand
        FROM inventory_stock_balances GROUP BY item_id
    ) b ON b.item_id = i.id
    WHERE i.status = 'active'
      AND COALESCE(b.qty_on_hand, 0) <= i.min_quantity
    ORDER BY COALESCE(b.qty_on_hand, 0) ASC
    LIMIT 8
")->fetchAll();
$lowStockCount = (int)$db->query("
    SELECT COUNT(*) FROM inventory_items i
    LEFT JOIN (
        SELECT item_id, SUM(qty_on_hand) q FROM inventory_stock_balances GROUP BY item_id
    ) b2 ON b2.item_id = i.id
    WHERE i.status = 'active' AND COALESCE(b2.q, 0) <= i.min_quantity
")->fetchColumn();

// ---------------------------------------------------------------
// 2 · Movement trend — last 6 months, in vs out value
// ---------------------------------------------------------------
$trendStart = date('Y-m-01', strtotime('-5 months'));
$trendStmt = $db->prepare("
    SELECT DATE_FORMAT(movement_datetime, '%Y-%m') ym,
           COALESCE(SUM(CASE WHEN qty_in > 0 THEN total_value ELSE 0 END), 0) val_in,
           COALESCE(SUM(CASE WHEN qty_out > 0 THEN total_value ELSE 0 END), 0) val_out
    FROM inventory_stock_ledger
    WHERE movement_datetime >= ?
    GROUP BY DATE_FORMAT(movement_datetime, '%Y-%m')
");
$trendStmt->execute([$trendStart]);
$trendMap = [];
foreach ($trendStmt->fetchAll() as $r) { $trendMap[$r['ym']] = $r; }
$trendLabels = []; $trendIn = []; $trendOut = [];
for ($i = 5; $i >= 0; $i--) {
    $k = date('Y-m', strtotime("-{$i} months"));
    $trendLabels[] = date('M', strtotime($k . '-01'));
    $trendIn[]  = round((float)($trendMap[$k]['val_in'] ?? 0), 2);
    $trendOut[] = round((float)($trendMap[$k]['val_out'] ?? 0), 2);
}

// ---------------------------------------------------------------
// 3 · Movement type mix (last 90 days)
// ---------------------------------------------------------------
$mixStmt = $db->prepare("
    SELECT movement_type, COUNT(*) n
    FROM inventory_stock_ledger
    WHERE movement_datetime >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    GROUP BY movement_type
");
$mixStmt->execute();
$mixOrder = ['purchase_receipt','opening_stock','adjustment_in','transfer_in','sales_issue','adjustment_out','transfer_out','reversal'];
$mixColors = [
    'purchase_receipt' => '#10b981', 'opening_stock' => '#0ea5e9',
    'adjustment_in' => '#06b6d4', 'transfer_in' => '#6366f1',
    'sales_issue' => '#ef4444', 'adjustment_out' => '#f59e0b',
    'transfer_out' => '#8b5cf6', 'reversal' => '#94a3b8',
];
$mixCounts = [];
foreach ($mixStmt->fetchAll() as $r) { $mixCounts[$r['movement_type']] = (int)$r['n']; }
$mixLabels = []; $mixData = []; $mixPalette = [];
foreach ($mixOrder as $mt) {
    if (!empty($mixCounts[$mt])) {
        $mixLabels[] = ucwords(str_replace('_', ' ', $mt));
        $mixData[] = $mixCounts[$mt];
        $mixPalette[] = $mixColors[$mt];
    }
}

// ---------------------------------------------------------------
// 4 · Stock value by warehouse (top 8)
// ---------------------------------------------------------------
$whStmt = $db->prepare("
    SELECT w.warehouse_name, COALESCE(SUM(b.stock_value), 0) v
    FROM inventory_stock_balances b
    JOIN warehouses w ON w.id = b.warehouse_id
    WHERE {$balScope}
    GROUP BY b.warehouse_id, w.warehouse_name
    ORDER BY v DESC LIMIT 8
");
$whStmt->execute($balParams);
$whRows = $whStmt->fetchAll();
$whLabels = [];
foreach ($whRows as $r) {
    $name = (string)$r['warehouse_name'];
    $whLabels[] = mb_strlen($name) > 18 ? mb_substr($name, 0, 17) . '…' : $name;
}
$whValues = array_map(fn($r) => round((float)$r['v'], 2), $whRows);

// ---------------------------------------------------------------
// 5 · Top items by stock value (top 6, bar list)
// ---------------------------------------------------------------
$itemStmt = $db->prepare("
    SELECT i.item_name, i.item_code, COALESCE(SUM(b.stock_value), 0) v,
           COALESCE(SUM(b.qty_on_hand), 0) q
    FROM inventory_stock_balances b
    JOIN inventory_items i ON i.id = b.item_id
    WHERE {$balScope}
    GROUP BY b.item_id, i.item_name, i.item_code
    ORDER BY v DESC LIMIT 6
");
$itemStmt->execute($balParams);
$itemRows = $itemStmt->fetchAll();
$itemMaxVal = 0.0;
foreach ($itemRows as $r) { $itemMaxVal = max($itemMaxVal, (float)$r['v']); }

// ---------------------------------------------------------------
// 6 · Pending workflow queues (adjustments + transfers + reservations)
// ---------------------------------------------------------------
$adjPending = (int)$db->query("SELECT COUNT(*) FROM inventory_adjustments WHERE status='pending_approval'")->fetchColumn();
$trfPending = (int)$db->query("SELECT COUNT(*) FROM inventory_stock_transfers WHERE status='pending_approval'")->fetchColumn();
$resOpen    = (int)$db->query("SELECT COUNT(*) FROM inventory_reservations WHERE status='reserved'")->fetchColumn();
$reconOpen  = (int)$db->query("SELECT COUNT(*) FROM inventory_reconciliations WHERE status IN ('draft','in_progress')")->fetchColumn();

$adjStatusRows = $db->query("SELECT status, COUNT(*) n FROM inventory_adjustments GROUP BY status")->fetchAll();
$trfStatusRows = $db->query("SELECT status, COUNT(*) n FROM inventory_stock_transfers GROUP BY status")->fetchAll();
$flowCounts = ['draft' => 0, 'pending_approval' => 0, 'approved' => 0, 'posted' => 0, 'finalised' => 0, 'rejected' => 0, 'cancelled' => 0];
foreach (array_merge($adjStatusRows, $trfStatusRows) as $r) {
    if (isset($flowCounts[$r['status']])) $flowCounts[$r['status']] += (int)$r['n'];
}
$flowLabels = ['Draft', 'Pending Approval', 'Approved', 'Posted / Finalised', 'Rejected', 'Cancelled'];
$flowData = [
    $flowCounts['draft'],
    $flowCounts['pending_approval'],
    $flowCounts['approved'],
    $flowCounts['posted'] + $flowCounts['finalised'],
    $flowCounts['rejected'],
    $flowCounts['cancelled'],
];
$flowPalette = ['#94a3b8', '#f59e0b', '#3b82f6', '#10b981', '#ef4444', '#6b7280'];

// ---------------------------------------------------------------
// 7 · Recent movements (ledger, latest 8)
// ---------------------------------------------------------------
$recentMovements = InventoryEngine::listLedger($db, [], 8, 0);
$moveInTypes = ['purchase_receipt', 'transfer_in', 'opening_stock', 'adjustment_in'];

// ---------------------------------------------------------------
// Quick-link registry — every inventory submodule
// ---------------------------------------------------------------
$quickLinks = [
    ['inventory.view',      'modules/inventory/current_stock.php',    'Current Stock',    'fa-cubes',            'blue'],
    ['inventory.adjust',    'modules/inventory/adjustments.php',      'Adjustments',      'fa-sliders-h',        'orange'],
    ['inventory.transfer',  'modules/inventory/transfers.php',        'Transfers',        'fa-truck',            'purple'],
    ['inventory.view',      'modules/inventory/ledger.php',           'Ledger',           'fa-book',             'green'],
    ['inventory.reports',   'modules/inventory/valuation_report.php', 'Valuation Report', 'fa-chart-line',       'indigo'],
    ['inventory.reports',   'modules/inventory/cost_audit.php',       'Cost Audit',       'fa-search-dollar',    'teal'],
    ['inventory.view',      'modules/inventory/reservations.php',     'Reservations',     'fa-hand-paper',       'amber'],
    ['inventory.view',      'modules/inventory/reconciliation.php',   'Reconciliation',   'fa-clipboard-check',  'purple'],
    ['inventory.reports',   'modules/inventory/reports.php',          'Reports',          'fa-chart-bar',        'gray'],
    ['inventory.settings',  'modules/inventory/settings.php',         'Settings',         'fa-gear',             'gray'],
];
$hasAdjustPerm   = hasPermission('inventory.adjust');
$hasTransferPerm = hasPermission('inventory.transfer');

$kpiCards = [
    ['Stock Value', number_format($totalValue, 2), 'across all warehouses', 'indigo', 'fa-sack-dollar'],
    ['Active Items', number_format($totalItems), $lowStockCount . ' at/below min level', 'blue', 'fa-boxes'],
    ['On-Hand Qty', rtrim(rtrim(number_format($totalOnHand, 2), '0'), '.'), 'gross units in stock', 'cyan', 'fa-cubes'],
    ['Reserved Qty', rtrim(rtrim(number_format($totalReserved, 2), '0'), '.'), $resOpen . ' open reservations', 'orange', 'fa-hand-paper'],
    ['Available Qty', rtrim(rtrim(number_format($totalAvailable, 2), '0'), '.'), 'on-hand minus reserved', 'green', 'fa-circle-check'],
    ['Pending Approvals', number_format($adjPending + $trfPending), $adjPending . ' adj · ' . $trfPending . ' transfers', 'yellow', 'fa-hourglass-half'],
];
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Inventory Command Center</h1>
        <p class="text-gray-600">Stock, costing, reservations &amp; reconciliation — all inventory operations</p>
    </div>
    <div class="flex gap-2">
        <?php if ($hasAdjustPerm): ?>
        <a href="<?= url('modules/inventory/adjustment_create.php') ?>" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 text-sm"><i class="fas fa-sliders-h mr-1"></i>New Adjustment</a>
        <?php endif; ?>
        <?php if ($hasTransferPerm): ?>
        <a href="<?= url('modules/inventory/transfer_create.php') ?>" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 text-sm"><i class="fas fa-truck mr-1"></i>New Transfer</a>
        <?php endif; ?>
    </div>
</div>

<nav class="flex flex-wrap gap-2 mb-5" aria-label="Inventory navigation">
    <?php foreach ($quickLinks as [$permission, $route, $label, $icon, $color]): ?>
        <?php if (hasPermission($permission)): ?>
        <a href="<?= url($route) ?>" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm text-gray-700">
            <i class="fas <?= $icon ?> mr-1 text-<?= $color ?>-500"></i><?= $label ?>
        </a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>

<div class="flex items-center justify-between mb-3">
    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Key Performance Indicators</h2>
</div>

<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
    <?php foreach ($kpiCards as [$label, $value, $sub, $color, $icon]): ?>
    <div class="bg-white rounded-lg shadow p-4 flex items-center justify-between">
        <div class="min-w-0">
            <p class="text-[11px] text-gray-500 uppercase leading-tight truncate"><?= sanitize($label) ?></p>
            <p class="font-bold text-lg leading-tight mt-0.5 truncate"><?= $value ?></p>
            <p class="text-[11px] text-gray-500 truncate"><?= sanitize($sub) ?></p>
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
            <h3 class="text-sm font-semibold text-gray-800">Movement Value Trend <span class="font-normal text-gray-400 text-xs">(last 6 months)</span></h3>
            <span class="text-xs text-gray-500"><span class="inline-block w-2 h-2 rounded-full bg-emerald-500 mr-1"></span>In <span class="inline-block w-2 h-2 rounded-full bg-red-500 ml-3 mr-1"></span>Out</span>
        </div>
        <div id="trendChart"></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-2">Movement Mix <span class="font-normal text-gray-400 text-xs">(90 days)</span></h3>
        <div id="mixChart"></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-2">Stock Value by Warehouse</h3>
        <div id="warehouseChart"></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-2">Workflow Status <span class="font-normal text-gray-400 text-xs">(adjustments + transfers)</span></h3>
        <div id="flowChart"></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">Top Items by Stock Value</h3>
        <?php if (!empty($itemRows)): ?>
        <div class="space-y-3">
            <?php foreach ($itemRows as $it): ?>
            <?php $pct = $itemMaxVal > 0 ? round((float)$it['v'] / $itemMaxVal * 100) : 0; ?>
            <div>
                <div class="flex justify-between text-sm mb-1 gap-2">
                    <span class="text-gray-700 truncate" title="<?= sanitize($it['item_name']) ?>"><?= sanitize($it['item_name']) ?></span>
                    <span class="font-mono text-xs text-gray-800 whitespace-nowrap"><?= number_format((float)$it['v'], 2) ?></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-1.5">
                    <div class="bg-indigo-500 h-1.5 rounded-full" style="width: <?= max(2, $pct) ?>%"></div>
                </div>
                <p class="text-[11px] text-gray-500 mt-0.5"><?= rtrim(rtrim(number_format((float)$it['q'], 2), '0'), '.') ?> on hand · <?= sanitize($it['item_code'] ?? '') ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-sm text-gray-500 text-center py-8">No stock balances yet</p>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="bg-white rounded-lg shadow p-4 lg:col-span-2 overflow-x-auto">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-semibold text-gray-800">Recent Movements</h3>
            <a href="<?= url('modules/inventory/ledger.php') ?>" class="text-xs text-blue-600 hover:text-blue-900">View all →</a>
        </div>
        <?php if (!empty($recentMovements['rows'])): ?>
        <table class="min-w-full text-sm">
            <thead><tr class="text-left text-xs uppercase text-gray-500 border-b">
                <th class="py-2 pr-3">Type</th><th class="py-2 pr-3">Item</th>
                <th class="py-2 pr-3">Ref</th><th class="py-2 pr-3 text-right">Qty</th>
                <th class="py-2 pr-3 text-right">Value</th><th class="py-2 pr-3">When</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($recentMovements['rows'] as $m): ?>
                <tr class="hover:bg-gray-50">
                    <td class="py-2 pr-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap <?= in_array($m['movement_type'], $moveInTypes, true) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700' ?>">
                            <?= ucwords(str_replace('_', ' ', $m['movement_type'])) ?>
                        </span>
                    </td>
                    <td class="py-2 pr-3 text-gray-700 truncate max-w-[160px]" title="<?= sanitize($m['item_code'] ?? ('#' . $m['item_id'])) ?>"><?= sanitize($m['item_code'] ?? ('#' . $m['item_id'])) ?></td>
                    <td class="py-2 pr-3 text-gray-500 truncate max-w-[120px]"><?= sanitize($m['reference_number'] ?? '—') ?></td>
                    <td class="py-2 pr-3 text-right font-mono whitespace-nowrap <?= $m['qty_in'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= $m['qty_in'] > 0 ? '+' . rtrim(rtrim(number_format((float)$m['qty_in'], 3), '0'), '.') : '-' . rtrim(rtrim(number_format((float)$m['qty_out'], 3), '0'), '.') ?>
                    </td>
                    <td class="py-2 pr-3 text-right font-mono whitespace-nowrap"><?= number_format((float)$m['total_value'], 2) ?></td>
                    <td class="py-2 pr-3 text-gray-500 whitespace-nowrap"><?= date('M d, H:i', strtotime($m['movement_datetime'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="text-sm text-gray-500 text-center py-8">No movements yet — stock will appear here as receipts, adjustments and transfers are posted.</p>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">Awaiting Inventory Action</h3>
        <div class="rounded-lg p-4 mb-3 bg-orange-50">
            <p class="text-3xl font-bold text-orange-600"><?= $adjPending ?></p>
            <p class="text-sm text-gray-600 mt-1">Stock adjustments awaiting approval.</p>
            <a href="<?= url('modules/inventory/adjustments.php') ?>" class="inline-block mt-2 text-orange-700 hover:underline text-sm">Review adjustments →</a>
        </div>
        <div class="rounded-lg p-4 mb-3 bg-purple-50">
            <p class="text-3xl font-bold text-purple-600"><?= $trfPending ?></p>
            <p class="text-sm text-gray-600 mt-1">Warehouse transfers awaiting approval.</p>
            <a href="<?= url('modules/inventory/transfers.php') ?>" class="inline-block mt-2 text-purple-700 hover:underline text-sm">Review transfers →</a>
        </div>
        <div class="rounded-lg p-4 mb-3 bg-amber-50">
            <p class="text-3xl font-bold text-amber-600"><?= $resOpen ?></p>
            <p class="text-sm text-gray-600 mt-1">Active stock reservations holding inventory.</p>
            <a href="<?= url('modules/inventory/reservations.php') ?>" class="inline-block mt-2 text-amber-700 hover:underline text-sm">Open reservations →</a>
        </div>
        <div class="rounded-lg p-4 bg-blue-50">
            <p class="text-3xl font-bold text-blue-600"><?= $reconOpen ?></p>
            <p class="text-sm text-gray-600 mt-1">Reconciliations in progress needing completion.</p>
            <a href="<?= url('modules/inventory/reconciliation.php') ?>" class="inline-block mt-2 text-blue-700 hover:underline text-sm">Open reconciliations →</a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 gap-4 mt-4">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-semibold text-gray-800">Low / Out-of-Stock Items <span class="font-normal text-gray-400 text-xs">(at or below minimum)</span></h3>
            <a href="<?= url('modules/inventory/current_stock.php') ?>" class="text-xs text-blue-600 hover:text-blue-900">Full stock list →</a>
        </div>
        <?php if (!empty($lowStockItems)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <?php foreach ($lowStockItems as $it): ?>
            <?php $onHand = (float)$it['qty_on_hand']; $min = (float)$it['min_quantity']; $pct = $min > 0 ? min(100, round($onHand / $min * 100)) : ($onHand > 0 ? 100 : 0); ?>
            <div class="border rounded p-3">
                <div class="flex justify-between items-center mb-1 gap-2">
                    <span class="text-sm text-gray-700 truncate font-medium" title="<?= sanitize($it['item_name']) ?>"><?= sanitize($it['item_name']) ?></span>
                    <?php if ($onHand <= 0): ?>
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Out of stock</span>
                    <?php else: ?>
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Low</span>
                    <?php endif; ?>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-1.5 mb-1">
                    <div class="<?= $onHand <= 0 ? 'bg-red-500' : 'bg-yellow-500' ?> h-1.5 rounded-full" style="width: <?= max(3, $pct) ?>%"></div>
                </div>
                <p class="text-[11px] text-gray-500"><?= sanitize($it['item_code'] ?? '') ?> · <?= rtrim(rtrim(number_format($onHand, 2), '0'), '.') ?> on hand / min <?= rtrim(rtrim(number_format($min, 2), '0'), '.') ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-sm text-gray-500 text-center py-6">All active items are above their minimum level. 🎉</p>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const render = window.renderCarbonChart;
    if (!render) return;
    const moneyAxis = { axes: { left: { ticks: { formatter: v => Number(v).toLocaleString() } } } };

    // 1 · Movement value trend (in vs out)
    const trendEl = document.getElementById('trendChart');
    if (trendEl) {
        render(trendEl, 'line', {
            labels: <?= json_encode($trendLabels) ?>,
            datasets: [
                { label: 'Stock In',  data: <?= json_encode($trendIn) ?>,  backgroundColor: 'rgba(16,185,129,0.12)', borderColor: '#10b981' },
                { label: 'Stock Out', data: <?= json_encode($trendOut) ?>, backgroundColor: 'rgba(239,68,68,0.12)', borderColor: '#ef4444' }
            ]
        }, Object.assign({ legend: { position: 'bottom' } }, moneyAxis));
    }

    // 2 · Movement type mix doughnut
    const mixEl = document.getElementById('mixChart');
    if (mixEl && <?= json_encode(count($mixData)) ?> > 0) {
        render(mixEl, 'doughnut', {
            labels: <?= json_encode($mixLabels) ?>,
            datasets: [{ data: <?= json_encode($mixData) ?>, backgroundColor: <?= json_encode($mixPalette) ?> }]
        }, { legend: { position: 'bottom' } });
    } else if (mixEl) {
        mixEl.innerHTML = '<p class="text-sm text-gray-400 text-center py-20">No movements in the last 90 days</p>';
    }

    // 3 · Stock value by warehouse (horizontal)
    const whEl = document.getElementById('warehouseChart');
    if (whEl && <?= json_encode(count($whValues)) ?> > 0) {
        render(whEl, 'horizontal', {
            labels: <?= json_encode($whLabels) ?>,
            datasets: [{ label: 'Stock Value', data: <?= json_encode($whValues) ?>, backgroundColor: '#6366f1' }]
        }, moneyAxis);
    } else if (whEl) {
        whEl.innerHTML = '<p class="text-sm text-gray-400 text-center py-20">No stock balances yet</p>';
    }

    // 4 · Workflow status doughnut
    const flowEl = document.getElementById('flowChart');
    if (flowEl && <?= (int)array_sum($flowData) ?> > 0) {
        render(flowEl, 'doughnut', {
            labels: <?= json_encode($flowLabels) ?>,
            datasets: [{ data: <?= json_encode($flowData) ?>, backgroundColor: <?= json_encode($flowPalette) ?> }]
        }, { legend: { position: 'bottom' } });
    } else if (flowEl) {
        flowEl.innerHTML = '<p class="text-sm text-gray-400 text-center py-20">No adjustments or transfers yet</p>';
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
