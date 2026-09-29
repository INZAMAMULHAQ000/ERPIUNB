<?php
$pageTitle = 'Tax Dashboard';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.view')) { die('Access Denied'); }

$db = getDB();

// ---- Stat counts ----
$activeTypes  = (int)$db->query("SELECT COUNT(*) FROM tax_types WHERE status = 'active'")->fetchColumn();
$activeRates  = (int)$db->query("SELECT COUNT(*) FROM tax_rates WHERE status = 'active'")->fetchColumn();
$activeGroups = (int)$db->query("SELECT COUNT(*) FROM tax_groups WHERE status = 'active'")->fetchColumn();
$openPeriods  = (int)$db->query("SELECT COUNT(*) FROM tax_periods WHERE status = 'open'")->fetchColumn();

// ---- Current month KPIs (output / input / net) ----
$outputTax = (float)$db->query("
    SELECT COALESCE(SUM(x.tax_amount),0) FROM tax_transactions x
    WHERE x.status = 'active' AND x.tax_direction = 'output'
      AND x.transaction_date >= DATE_FORMAT(NOW(),'%Y-%m-01')
      AND x.transaction_date <  DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 MONTH),'%Y-%m-01')
")->fetchColumn();

$inputTax = (float)$db->query("
    SELECT COALESCE(SUM(x.tax_amount),0) FROM tax_transactions x
    WHERE x.status = 'active' AND x.tax_direction = 'input'
      AND x.transaction_date >= DATE_FORMAT(NOW(),'%Y-%m-01')
      AND x.transaction_date <  DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 MONTH),'%Y-%m-01')
")->fetchColumn();

$netPosition = $outputTax - $inputTax;

// ---- Output vs Input Tax — last 6 months ----
$chartRows = $db->query("
    SELECT DATE_FORMAT(x.transaction_date,'%Y-%m') ym, x.tax_direction, SUM(x.tax_amount) total
    FROM tax_transactions x
    WHERE x.status = 'active'
      AND x.transaction_date >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 6 MONTH),'%Y-%m-01')
    GROUP BY ym, x.tax_direction
")->fetchAll();

$labels = [];
$cur = strtotime(date('Y-m-01'));
for ($i = 6; $i >= 0; $i--) {
    $labels[] = date('Y-m', strtotime("-{$i} months", $cur));
}

$outMap = array_fill_keys($labels, 0.0);
$inMap  = array_fill_keys($labels, 0.0);
foreach ($chartRows as $c) {
    $ym = $c['ym'];
    if ($c['tax_direction'] === 'output' && isset($outMap[$ym])) $outMap[$ym] = (float)$c['total'];
    elseif ($c['tax_direction'] === 'input'  && isset($inMap[$ym]))  $inMap[$ym]  = (float)$c['total'];
}
$out = array_values($outMap);
$in  = array_values($inMap);

// ---- Current open periods ----
$periods = $db->query("
    SELECT p.*, t.name AS type_name
    FROM tax_periods p
    JOIN tax_types t ON t.id = p.tax_type_id
    WHERE p.status = 'open'
    ORDER BY p.end_date ASC
    LIMIT 6
")->fetchAll();

// ---- Recent tax transactions ----
$txns = $db->query("
    SELECT x.*, t.code AS tax_type_code
    FROM tax_transactions x
    JOIN tax_types t ON t.id = x.tax_type_id
    ORDER BY x.id DESC
    LIMIT 8
")->fetchAll();
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Tax Dashboard</h1>
    <p class="text-gray-600">Overview of your tax configuration, periods and the tax register at a glance</p>
</div>

<?= taxui_tabs('dashboard') ?>

<!-- Stat Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Active Tax Types</div>
            <i class="fas fa-list text-blue-500"></i>
        </div>
        <div class="text-2xl font-bold text-gray-900 mt-2"><?= number_format($activeTypes) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Active Rates</div>
            <i class="fas fa-percent text-indigo-500"></i>
        </div>
        <div class="text-2xl font-bold text-gray-900 mt-2"><?= number_format($activeRates) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Active Groups</div>
            <i class="fas fa-layer-group text-violet-500"></i>
        </div>
        <div class="text-2xl font-bold text-gray-900 mt-2"><?= number_format($activeGroups) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Open Periods</div>
            <i class="fas fa-calendar-alt text-amber-500"></i>
        </div>
        <div class="text-2xl font-bold text-gray-900 mt-2"><?= number_format($openPeriods) ?></div>
    </div>
</div>

<!-- KPI Row -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Current Month Output Tax</div>
            <i class="fas fa-arrow-up text-indigo-500"></i>
        </div>
        <div class="text-xl font-bold font-mono text-gray-900 mt-2"><?= taxui_amount($outputTax) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Current Month Input Tax</div>
            <i class="fas fa-arrow-down text-violet-500"></i>
        </div>
        <div class="text-xl font-bold font-mono text-gray-900 mt-2"><?= taxui_amount($inputTax) ?></div>
    </div>
    <div class="rounded-lg shadow p-5 <?= $netPosition <= 0 ? 'bg-green-50' : 'bg-rose-50' ?>">
        <div class="flex items-center justify-between">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Net Position (Output − Input)</div>
            <i class="fas fa-scale-balanced <?= $netPosition <= 0 ? 'text-emerald-600' : 'text-rose-600' ?>"></i>
        </div>
        <div class="text-xl font-bold font-mono <?= $netPosition <= 0 ? 'text-emerald-700' : 'text-rose-700' ?> mt-2"><?= taxui_amount($netPosition) ?></div>
    </div>
</div>

<!-- Output vs Input Chart -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="font-semibold text-gray-800 mb-1">Output vs Input Tax — last 6 months</h3>
    <p class="text-xs text-gray-500 mb-4">Monthly output and input tax recorded in the tax register</p>
    <div id="taxBarChart" class="h-64"></div>
</div>

<!-- Open Periods + Recent Transactions -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Current Open Periods</h2>
            <a href="<?= url('modules/finance/tax/periods/index.php') ?>" class="text-sm text-blue-600 hover:underline"><i class="fas fa-arrow-right mr-1"></i>View all periods</a>
        </div>
        <?php if (empty($periods)): ?>
        <div class="p-8 text-center text-gray-500">
            <i class="fas fa-calendar-alt text-4xl mb-3 text-gray-300"></i>
            <p>No open tax periods right now.</p>
        </div>
        <?php else: ?>
        <div class="divide-y divide-gray-200">
            <?php foreach ($periods as $p): ?>
            <div class="px-6 py-3 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-sm font-medium text-gray-900"><?= sanitize($p['period_name']) ?></div>
                    <div class="text-xs text-gray-500">
                        <span class="font-medium"><?= sanitize($p['type_name']) ?></span>
                        &middot; <?= sanitize($p['start_date']) ?> → <?= sanitize($p['end_date']) ?>
                    </div>
                </div>
                <?= taxui_period_badge($p['status']) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Recent Tax Transactions</h2>
            <a href="<?= url('modules/finance/tax/transactions/index.php') ?>" class="text-sm text-blue-600 hover:underline"><i class="fas fa-arrow-right mr-1"></i>View register</a>
        </div>
        <?php if (empty($txns)): ?>
        <div class="p-8 text-center text-gray-500">
            <i class="fas fa-receipt text-4xl mb-3 text-gray-300"></i>
            <p>No tax transactions recorded yet.</p>
        </div>
        <?php else: ?>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Document</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Direction</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tax</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($txns as $tx): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3">
                        <span class="font-mono text-sm text-gray-900"><?= sanitize($tx['source_document_no'] ?? '—') ?></span>
                        <div class="text-xs text-gray-500"><?= taxui_source_label($tx['source_module']) ?></div>
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-600"><?= sanitize($tx['transaction_date']) ?></td>
                    <td class="px-6 py-3">
                        <span class="px-2 py-1 text-xs font-mono bg-slate-100 text-slate-700 rounded"><?= sanitize($tx['tax_type_code']) ?></span>
                    </td>
                    <td class="px-6 py-3 text-center"><?= taxui_direction_badge($tx['tax_direction']) ?></td>
                    <td class="px-6 py-3 text-right font-mono text-gray-900"><?= taxui_amount($tx['tax_amount']) ?></td>
                    <td class="px-6 py-3 text-center"><?= taxui_txn_badge($tx['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.renderCarbonChart === 'function') {
        try {
            window.renderCarbonChart(document.getElementById('taxBarChart'), 'bar', {
                labels: <?= json_encode($labels) ?>,
                datasets: [
                    { label: 'Output Tax', data: <?= json_encode($out) ?>, backgroundColor: 'rgba(99, 102, 241, 0.7)', borderColor: 'rgb(99, 102, 241)' },
                    { label: 'Input Tax', data: <?= json_encode($in) ?>, backgroundColor: 'rgba(139, 92, 246, 0.7)', borderColor: 'rgb(139, 92, 246)' }
                ]
            }, {});
        } catch (e) {}
    }
});
</script>

<?php require_once '../../../../includes/footer.php'; ?>