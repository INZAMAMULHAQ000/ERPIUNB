<?php
/**
 * F11 — Financial Dashboard
 * Executive summary with KPIs and charts
 */

$pageTitle = 'Financial Dashboard';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once '../FinancialReportService.php';

if (!hasPermission('report.dashboard.view')) { die('Access Denied'); }

$db = getDB();

// Get active financial year
$activeFY = getActiveFinancialYear($db);
$fyId = $activeFY ? (int)$activeFY['id'] : 0;

// Get date range (default to current month)
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');

// Generate key reports
$pl = FinancialReportService::generateProfitLoss(['financial_year_id' => $fyId, 'date_from' => $dateFrom, 'date_to' => $dateTo]);
$bs = FinancialReportService::generateBalanceSheet(['financial_year_id' => $fyId, 'date_as_of' => $dateTo]);
$arAging = FinancialReportService::generateARAging(['as_of_date' => $dateTo]);
$apAging = FinancialReportService::generateAPAging(['as_of_date' => $dateTo]);
$taxSum = FinancialReportService::generateTaxSummary(['date_from' => $dateFrom, 'date_to' => $dateTo]);
$ratios = FinancialReportService::calculateRatios(['financial_year_id' => $fyId, 'date_as_of' => $dateTo]);

// Extract KPIs
$revenue = $pl['profit_loss']['revenue']['amount'] ?? 0;
$expenses = ($pl['profit_loss']['cost_of_sales']['amount'] ?? 0) + ($pl['profit_loss']['operating_expenses']['amount'] ?? 0);
$grossProfit = $pl['profit_loss']['gross_profit']['amount'] ?? 0;
$netProfit = $pl['profit_loss']['net_profit']['amount'] ?? 0;
$cashBank = $bs['balance_sheet']['assets']['current']['amount'] ?? 0;
$totalAR = $arAging['total_outstanding'] ?? 0;
$totalAP = $apAging['total_outstanding'] ?? 0;
$taxPayable = $taxSum['net_payable'] ?? 0;
$totalAssets = $bs['balance_sheet']['assets']['total']['amount'] ?? 0;
$totalLiabilities = $bs['balance_sheet']['liabilities']['total']['amount'] ?? 0;
$totalEquity = $bs['balance_sheet']['equity']['total']['amount'] ?? 0;

// Monthly trend data
$monthlyStmt = $db->prepare("
    SELECT DATE_FORMAT(posting_date, '%Y-%m') as month,
           SUM(CASE WHEN ag.nature = 'income' THEN gl.credit - gl.debit ELSE 0 END) as revenue,
           SUM(CASE WHEN ag.nature IN ('expense', 'cost_of_sales') THEN gl.debit - gl.credit ELSE 0 END) as expenses
    FROM general_ledger gl
    JOIN chart_of_accounts coa ON coa.id = gl.account_id
    JOIN account_groups ag ON ag.id = coa.group_id
    WHERE gl.financial_year_id = ?
    GROUP BY DATE_FORMAT(posting_date, '%Y-%m')
    ORDER BY month
");
$monthlyStmt->execute([$fyId]);
$monthlyData = $monthlyStmt->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Financial Dashboard</h1>
        <p class="text-gray-600">Executive summary as of <?= date('M d, Y', strtotime($dateTo)) ?></p>
    </div>
    <div class="flex gap-2">
        <a href="../trial_balance/index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Trial Balance</a>
        <a href="../general_ledger/index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">General Ledger</a>
        <a href="../account_ledger/index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Account Ledger</a>
        <a href="../profit_loss/index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">P&L</a>
        <a href="../balance_sheet/index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Balance Sheet</a>
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
            <i class="fas fa-arrow-left mr-2"></i>Finance
        </a>
    </div>
</div>

<!-- Date Filter -->
<div class="bg-white rounded-lg shadow mb-6 p-4">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input type="date" name="date_from" value="<?= sanitize($dateFrom) ?>" class="px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input type="date" name="date_to" value="<?= sanitize($dateTo) ?>" class="px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Update</button>
    </form>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
        <p class="text-xs text-gray-500 uppercase">Revenue</p>
        <p class="text-xl font-bold text-gray-800"><?= formatMoney($revenue) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
        <p class="text-xs text-gray-500 uppercase">Expenses</p>
        <p class="text-xl font-bold text-gray-800"><?= formatMoney($expenses) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
        <p class="text-xs text-gray-500 uppercase">Gross Profit</p>
        <p class="text-xl font-bold <?= $grossProfit >= 0 ? 'text-green-600' : 'text-red-600' ?>"><?= formatMoney($grossProfit) ?></p>
        <p class="text-xs text-gray-400">Margin: <?= $pl['margins']['gross_margin'] ?? 0 ?>%</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-emerald-600">
        <p class="text-xs text-gray-500 uppercase">Net Profit</p>
        <p class="text-xl font-bold <?= $netProfit >= 0 ? 'text-green-600' : 'text-red-600' ?>"><?= formatMoney($netProfit) ?></p>
        <p class="text-xs text-gray-400">Margin: <?= $pl['margins']['net_margin'] ?? 0 ?>%</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-cyan-500">
        <p class="text-xs text-gray-500 uppercase">Cash & Bank</p>
        <p class="text-xl font-bold text-gray-800"><?= formatMoney($cashBank) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-indigo-500">
        <p class="text-xs text-gray-500 uppercase">Total Assets</p>
        <p class="text-xl font-bold text-gray-800"><?= formatMoney($totalAssets) ?></p>
    </div>
</div>

<!-- Second Row KPIs -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-400">
        <p class="text-xs text-gray-500 uppercase">Accounts Receivable</p>
        <p class="text-xl font-bold text-gray-800"><?= formatMoney($totalAR) ?></p>
        <p class="text-xs text-gray-400">DSO: <?= $ratios['ratios']['receivables_days']['value'] ?? 0 ?> days</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-amber-500">
        <p class="text-xs text-gray-500 uppercase">Accounts Payable</p>
        <p class="text-xl font-bold text-gray-800"><?= formatMoney($totalAP) ?></p>
        <p class="text-xs text-gray-400">DPO: <?= $ratios['ratios']['payables_days']['value'] ?? 0 ?> days</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
        <p class="text-xs text-gray-500 uppercase">Tax Payable</p>
        <p class="text-xl font-bold <?= $taxPayable >= 0 ? 'text-gray-800' : 'text-green-600' ?>"><?= formatMoney(abs($taxPayable)) ?></p>
        <p class="text-xs text-gray-400"><?= $taxPayable >= 0 ? 'Payable' : 'Refundable' ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-gray-600">
        <p class="text-xs text-gray-500 uppercase">Equity</p>
        <p class="text-xl font-bold text-gray-800"><?= formatMoney($totalEquity) ?></p>
        <p class="text-xs text-gray-400">ROE: <?= $ratios['ratios']['roe']['value'] ?? 0 ?>%</p>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Revenue vs Expenses Trend -->
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold text-gray-800 mb-4">Revenue vs Expenses Trend</h3>
        <div style="height: 300px;"><canvas id="revenueChart"></canvas></div>
    </div>
    
    <!-- AR vs AP -->
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold text-gray-800 mb-4">AR vs AP Comparison</h3>
        <div style="height: 300px;"><canvas id="arApChart"></canvas></div>
    </div>
</div>

<!-- Detailed Reports Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Profit & Loss Summary -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b flex justify-between items-center">
            <h3 class="font-semibold text-gray-800">Profit & Loss</h3>
            <a href="../profit_loss/index.php" class="text-sm text-blue-600 hover:underline">View Full</a>
        </div>
        <div class="p-4">
            <table class="w-full text-sm">
                <?php foreach ($pl['profit_loss'] as $key => $item): ?>
                <tr class="<?= $item['is_subtotal'] ?? false ? 'font-semibold bg-gray-50' : '' ?> <?= $item['is_total'] ?? false ? 'font-bold bg-blue-50' : '' ?>">
                    <td class="py-2 text-gray-700"><?= sanitize($item['label']) ?></td>
                    <td class="py-2 text-right font-mono <?= ($item['amount'] ?? 0) < 0 ? 'text-red-600' : 'text-gray-800' ?>">
                        <?= formatMoney(abs($item['amount'] ?? 0)) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
    
    <!-- Balance Sheet Summary -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b flex justify-between items-center">
            <h3 class="font-semibold text-gray-800">Balance Sheet</h3>
            <a href="../balance_sheet/index.php" class="text-sm text-blue-600 hover:underline">View Full</a>
        </div>
        <div class="p-4">
            <table class="w-full text-sm">
                <tr class="font-semibold"><td class="py-2" colspan="2">Assets</td></tr>
                <?php foreach ($bs['balance_sheet']['assets'] as $key => $item): ?>
                <?php if (!isset($item['amount'])) continue; ?>
                <tr class="<?= $item['is_total'] ?? false ? 'font-bold bg-gray-50' : '' ?>">
                    <td class="py-1 pl-4 text-gray-700"><?= sanitize($item['label']) ?></td>
                    <td class="py-1 text-right font-mono"><?= formatMoney($item['amount']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="font-semibold"><td class="py-2" colspan="2">Liabilities</td></tr>
                <?php foreach ($bs['balance_sheet']['liabilities'] as $key => $item): ?>
                <?php if (!isset($item['amount'])) continue; ?>
                <tr class="<?= $item['is_total'] ?? false ? 'font-bold bg-gray-50' : '' ?>">
                    <td class="py-1 pl-4 text-gray-700"><?= sanitize($item['label']) ?></td>
                    <td class="py-1 text-right font-mono"><?= formatMoney($item['amount']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
    
    <!-- Key Ratios -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b flex justify-between items-center">
            <h3 class="font-semibold text-gray-800">Key Ratios</h3>
            <a href="../ratios/index.php" class="text-sm text-blue-600 hover:underline">View All</a>
        </div>
        <div class="p-4">
            <table class="w-full text-sm">
                <?php foreach (array_slice($ratios['ratios'], 0, 8) as $key => $ratio): ?>
                <tr>
                    <td class="py-2 text-gray-700"><?= sanitize($ratio['label']) ?></td>
                    <td class="py-2 text-right font-mono font-semibold"><?= $ratio['value'] ?><?= $ratio['unit'] ?? '' ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>

<!-- AR Aging Summary -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b flex justify-between items-center">
            <h3 class="font-semibold text-gray-800">AR Aging</h3>
            <a href="../ar/index.php" class="text-sm text-blue-600 hover:underline">View Details</a>
        </div>
        <div class="p-4 grid grid-cols-4 gap-4 text-center">
            <div class="bg-green-50 rounded p-3">
                <p class="text-xs text-gray-500">0-30 Days</p>
                <p class="text-lg font-bold text-green-700"><?= formatMoney($arAging['buckets']['current']) ?></p>
            </div>
            <div class="bg-yellow-50 rounded p-3">
                <p class="text-xs text-gray-500">31-60 Days</p>
                <p class="text-lg font-bold text-yellow-700"><?= formatMoney($arAging['buckets']['31_60']) ?></p>
            </div>
            <div class="bg-orange-50 rounded p-3">
                <p class="text-xs text-gray-500">61-90 Days</p>
                <p class="text-lg font-bold text-orange-700"><?= formatMoney($arAging['buckets']['61_90']) ?></p>
            </div>
            <div class="bg-red-50 rounded p-3">
                <p class="text-xs text-gray-500">90+ Days</p>
                <p class="text-lg font-bold text-red-700"><?= formatMoney($arAging['buckets']['90_plus']) ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b flex justify-between items-center">
            <h3 class="font-semibold text-gray-800">AP Aging</h3>
            <a href="../ap/index.php" class="text-sm text-blue-600 hover:underline">View Details</a>
        </div>
        <div class="p-4 grid grid-cols-4 gap-4 text-center">
            <div class="bg-green-50 rounded p-3">
                <p class="text-xs text-gray-500">0-30 Days</p>
                <p class="text-lg font-bold text-green-700"><?= formatMoney($apAging['buckets']['current']) ?></p>
            </div>
            <div class="bg-yellow-50 rounded p-3">
                <p class="text-xs text-gray-500">31-60 Days</p>
                <p class="text-lg font-bold text-yellow-700"><?= formatMoney($apAging['buckets']['31_60']) ?></p>
            </div>
            <div class="bg-orange-50 rounded p-3">
                <p class="text-xs text-gray-500">61-90 Days</p>
                <p class="text-lg font-bold text-orange-700"><?= formatMoney($apAging['buckets']['61_90']) ?></p>
            </div>
            <div class="bg-red-50 rounded p-3">
                <p class="text-xs text-gray-500">90+ Days</p>
                <p class="text-lg font-bold text-red-700"><?= formatMoney($apAging['buckets']['90_plus']) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Integration -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Revenue vs Expenses Chart
const revCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(revCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($monthlyData, 'month')) ?>,
        datasets: [{
            label: 'Revenue',
            data: <?= json_encode(array_column($monthlyData, 'revenue')) ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.3
        }, {
            label: 'Expenses',
            data: <?= json_encode(array_column($monthlyData, 'expenses')) ?>,
            borderColor: '#ef4444',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true } }
    }
});

// AR vs AP Chart
const arApCtx = document.getElementById('arApChart').getContext('2d');
new Chart(arApCtx, {
    type: 'bar',
    data: {
        labels: ['AR Aging', 'AP Aging'],
        datasets: [{
            label: 'Current',
            data: [<?= $arAging['buckets']['current'] ?>, <?= $apAging['buckets']['current'] ?>],
            backgroundColor: '#22c55e'
        }, {
            label: '31-60 Days',
            data: [<?= $arAging['buckets']['31_60'] ?>, <?= $apAging['buckets']['31_60'] ?>],
            backgroundColor: '#eab308'
        }, {
            label: '61-90 Days',
            data: [<?= $arAging['buckets']['61_90'] ?>, <?= $apAging['buckets']['61_90'] ?>],
            backgroundColor: '#f97316'
        }, {
            label: '90+ Days',
            data: [<?= $arAging['buckets']['90_plus'] ?>, <?= $apAging['buckets']['90_plus'] ?>],
            backgroundColor: '#ef4444'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
        scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }
    }
});
</script>

<?php require_once '../../../../includes/footer.php'; ?>
