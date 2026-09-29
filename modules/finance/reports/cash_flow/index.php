<?php
/**
 * F11 — Cash Flow Statement
 * Operating, Investing, and Financing Activities
 */

$pageTitle = 'Cash Flow Statement';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once '../FinancialReportService.php';

if (!hasPermission('report.cash_flow.view')) { die('Access Denied'); }

$db = getDB();

$fyId = (int)($_GET['financial_year_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$periodType = $_GET['period_type'] ?? 'yearly';

if (!$fyId) {
    $activeFY = getActiveFinancialYear($db);
    $fyId = $activeFY ? (int)$activeFY['id'] : 0;
}

$fyStmt = $db->prepare("SELECT start_date, end_date FROM financial_years WHERE id = ?");
$fyStmt->execute([$fyId]);
$fy = $fyStmt->fetch();

// Set default date range based on period type
if (!$dateFrom && $fy) {
    switch ($periodType) {
        case 'monthly':
            $dateFrom = date('Y-m-01');
            $dateTo = date('Y-m-t');
            break;
        case 'quarterly':
            $quarter = ceil(date('n') / 3);
            $dateFrom = date('Y-' . str_pad(($quarter - 1) * 3 + 1, 2, '0', STR_PAD_LEFT) . '-01');
            $dateTo = date('Y-m-t', strtotime($dateFrom . ' +2 months'));
            break;
        default: // yearly
            $dateFrom = $fy['start_date'];
            $dateTo = $fy['end_date'];
    }
}

$financialYears = $db->query("SELECT id, year_name FROM financial_years ORDER BY start_date DESC")->fetchAll();

// Generate cash flow report
$report = FinancialReportService::generateCashFlow([
    'financial_year_id' => $fyId,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
]);

$operatingActivities = (float)($report['cash_flow']['operating']['amount'] ?? 0);
$investingActivities = (float)($report['cash_flow']['investing']['amount'] ?? 0);
$financingActivities = (float)($report['cash_flow']['financing']['amount'] ?? 0);
$netCashFlow = (float)($report['cash_flow']['net_cash_flow']['amount'] ?? 0);
$openingCash = (float)($report['cash_flow']['opening_cash']['amount'] ?? 0);
$closingCash = (float)($report['cash_flow']['closing_cash']['amount'] ?? 0);
$cashAccounts = $report['cash_accounts'] ?? [];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Cash Flow Statement</h1>
        <p class="text-gray-600">For the period <?= date('M d, Y', strtotime($dateFrom)) ?> to <?= date('M d, Y', strtotime($dateTo)) ?></p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"><i class="fas fa-print mr-2"></i>Print</button>
        <a href="../dashboard/index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-arrow-left mr-2"></i>Dashboard</a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow mb-6 p-4">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Period Type</label>
            <select name="period_type" class="px-3 py-2 border rounded" onchange="updateDates(this.value)">
                <option value="monthly" <?= $periodType === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                <option value="quarterly" <?= $periodType === 'quarterly' ? 'selected' : '' ?>>Quarterly</option>
                <option value="yearly" <?= $periodType === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                <option value="custom" <?= $periodType === 'custom' ? 'selected' : '' ?>>Custom</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Financial Year</label>
            <select name="financial_year_id" class="px-3 py-2 border rounded">
                <?php foreach ($financialYears as $fy): ?>
                <option value="<?= $fy['id'] ?>" <?= $fyId == $fy['id'] ? 'selected' : '' ?>><?= sanitize($fy['year_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div id="customDates" class="<?= $periodType === 'custom' ? '' : 'hidden' ?>">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input type="date" name="date_from" value="<?= sanitize($dateFrom) ?>" class="px-3 py-2 border rounded">
        </div>
        <div id="customDatesTo" class="<?= $periodType === 'custom' ? '' : 'hidden' ?>">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input type="date" name="date_to" value="<?= sanitize($dateTo) ?>" class="px-3 py-2 border rounded">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-search mr-2"></i>Generate</button>
    </form>
</div>

<script>
function updateDates(type) {
    document.getElementById('customDates').classList.toggle('hidden', type !== 'custom');
    document.getElementById('customDatesTo').classList.toggle('hidden', type !== 'custom');
}
</script>

<?php if ($report['success']): ?>

<!-- Cash Flow Statement -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-500">
        <h2 class="text-lg font-bold text-white">CASH FLOW STATEMENT</h2>
        <p class="text-sm text-blue-100">Period: <?= date('M d, Y', strtotime($dateFrom)) ?> - <?= date('M d, Y', strtotime($dateTo)) ?></p>
    </div>
    
    <div class="p-6">
        <!-- Operating Activities -->
        <div class="mb-6">
            <h3 class="text-md font-bold text-gray-800 mb-3 pb-2 border-b border-gray-200">CASH FLOWS FROM OPERATING ACTIVITIES</h3>
            <div class="space-y-2">
                <div class="flex justify-between py-2">
                    <span class="text-gray-700">Net Profit Before Tax</span>
                    <span class="font-mono text-gray-800"><?= formatMoney($report['cash_flow']['operating']['amount'] ?? 0) ?></span>
                </div>
                <div class="flex justify-between py-2 pl-4 bg-gray-50">
                    <span class="text-gray-600 text-sm">Adjustments for non-cash items (depreciation, etc.)</span>
                    <span class="font-mono text-gray-600 text-sm">-</span>
                </div>
                <div class="flex justify-between py-2 border-t border-gray-100 font-semibold">
                    <span>Net Cash from Operating Activities</span>
                    <span class="font-mono text-blue-700"><?= formatMoney($operatingActivities) ?></span>
                </div>
            </div>
        </div>
        
        <!-- Investing Activities -->
        <div class="mb-6">
            <h3 class="text-md font-bold text-gray-800 mb-3 pb-2 border-b border-gray-200">CASH FLOWS FROM INVESTING ACTIVITIES</h3>
            <div class="space-y-2">
                <div class="flex justify-between py-2">
                    <span class="text-gray-700">Purchase of Fixed Assets</span>
                    <span class="font-mono text-gray-800"><?= formatMoney(abs($investingActivities)) ?></span>
                </div>
                <div class="flex justify-between py-2">
                    <span class="text-gray-700">Proceeds from Asset Disposals</span>
                    <span class="font-mono text-gray-800">-</span>
                </div>
                <div class="flex justify-between py-2 border-t border-gray-100 font-semibold">
                    <span>Net Cash from Investing Activities</span>
                    <span class="font-mono <?= $investingActivities < 0 ? 'text-red-600' : 'text-green-600' ?>"><?= formatMoney($investingActivities) ?></span>
                </div>
            </div>
        </div>
        
        <!-- Financing Activities -->
        <div class="mb-6">
            <h3 class="text-md font-bold text-gray-800 mb-3 pb-2 border-b border-gray-200">CASH FLOWS FROM FINANCING ACTIVITIES</h3>
            <div class="space-y-2">
                <div class="flex justify-between py-2">
                    <span class="text-gray-700">Proceeds from Loans</span>
                    <span class="font-mono text-gray-800">-</span>
                </div>
                <div class="flex justify-between py-2">
                    <span class="text-gray-700">Loan Repayments</span>
                    <span class="font-mono text-gray-800">-</span>
                </div>
                <div class="flex justify-between py-2">
                    <span class="text-gray-700">Dividends Paid</span>
                    <span class="font-mono text-gray-800">-</span>
                </div>
                <div class="flex justify-between py-2 border-t border-gray-100 font-semibold">
                    <span>Net Cash from Financing Activities</span>
                    <span class="font-mono <?= $financingActivities < 0 ? 'text-red-600' : 'text-green-600' ?>"><?= formatMoney($financingActivities) ?></span>
                </div>
            </div>
        </div>
        
        <!-- Net Cash Flow -->
        <div class="border-t-2 border-gray-300 pt-4">
            <div class="flex justify-between py-3 bg-gray-100 font-bold text-lg">
                <span>NET INCREASE/(DECREASE) IN CASH</span>
                <span class="font-mono <?= $netCashFlow >= 0 ? 'text-green-600' : 'text-red-600' ?>"><?= formatMoney($netCashFlow) ?></span>
            </div>
        </div>
        
        <!-- Opening and Closing Cash -->
        <div class="mt-4 space-y-2">
            <div class="flex justify-between py-2">
                <span class="text-gray-700">Opening Cash and Bank Balances</span>
                <span class="font-mono text-gray-800"><?= formatMoney($openingCash) ?></span>
            </div>
            <div class="flex justify-between py-2 border-t border-gray-100 font-semibold text-lg bg-blue-50 px-4">
                <span>CLOSING CASH AND BANK BALANCES</span>
                <span class="font-mono text-blue-700"><?= formatMoney($closingCash) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Cash Position Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
        <p class="text-xs text-gray-500 uppercase">Operating Cash Flow</p>
        <p class="text-xl font-bold <?= $operatingActivities >= 0 ? 'text-green-600' : 'text-red-600' ?>">
            <?= formatMoney($operatingActivities) ?>
        </p>
        <p class="text-xs text-gray-400">Core business operations</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
        <p class="text-xs text-gray-500 uppercase">Investing Cash Flow</p>
        <p class="text-xl font-bold <?= $investingActivities >= 0 ? 'text-green-600' : 'text-red-600' ?>">
            <?= formatMoney($investingActivities) ?>
        </p>
        <p class="text-xs text-gray-400">Asset purchases & disposals</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-indigo-500">
        <p class="text-xs text-gray-500 uppercase">Financing Cash Flow</p>
        <p class="text-xl font-bold <?= $financingActivities >= 0 ? 'text-green-600' : 'text-red-600' ?>">
            <?= formatMoney($financingActivities) ?>
        </p>
        <p class="text-xs text-gray-400">Loans & equity</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-emerald-500">
        <p class="text-xs text-gray-500 uppercase">Net Cash Movement</p>
        <p class="text-xl font-bold <?= $netCashFlow >= 0 ? 'text-green-600' : 'text-red-600' ?>">
            <?= formatMoney($netCashFlow) ?>
        </p>
        <p class="text-xs text-gray-400">Total change in cash</p>
    </div>
</div>

<!-- Cash Accounts Detail -->
<?php if (!empty($cashAccounts)): ?>
<div class="bg-white rounded-lg shadow mt-6">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-800">Cash & Bank Account Details</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left">Account Code</th>
                    <th class="px-4 py-2 text-left">Account Name</th>
                    <th class="px-4 py-2 text-right">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($cashAccounts as $ca): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 font-mono"><?= sanitize($ca['account_code']) ?></td>
                    <td class="px-4 py-2"><?= sanitize($ca['account_name']) ?></td>
                    <td class="px-4 py-2 text-right font-mono"><?= formatMoney($ca['balance']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Note about classifications -->
<div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
    <div class="flex items-start">
        <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
        <div>
            <h4 class="font-semibold text-blue-800">Configurable Cash Flow Classification</h4>
            <p class="text-sm text-blue-700 mt-1">
                <?= sanitize($report['classification_note'] ?? 'This statement uses configured account mappings to categorize movements as Operating, Investing, Financing, or Non-Cash.') ?>
            </p>
        </div>
    </div>
</div>

<?php endif; ?>

<style media="print">
    .erp-layout, nav, .sidebar, .navbar, form, button { display: none !important; }
    body { background: white; }
    @page { margin: 10mm; }
</style>

<?php require_once '../../../../includes/footer.php'; ?>
