<?php
/**
 * F11 — Profit & Loss Statement
 */

$pageTitle = 'Profit & Loss Statement';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once '../FinancialReportService.php';

if (!hasPermission('report.profit_loss.view')) { die('Access Denied'); }

$db = getDB();

// Filters
$fyId = (int)($_GET['financial_year_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$periodType = $_GET['period_type'] ?? 'yearly';

// Default to active FY
if (!$fyId) {
    $activeFY = getActiveFinancialYear($db);
    $fyId = $activeFY ? (int)$activeFY['id'] : 0;
}

$fyStmt = $db->prepare("SELECT start_date, end_date FROM financial_years WHERE id = ?");
$fyStmt->execute([$fyId]);
$fy = $fyStmt->fetch();

// Set date range based on period type
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
        default:
            $dateFrom = $fy['start_date'];
            $dateTo = $fy['end_date'];
    }
}

$financialYears = $db->query("SELECT id, year_name FROM financial_years ORDER BY start_date DESC")->fetchAll();

// Generate report
$report = FinancialReportService::generateProfitLoss([
    'financial_year_id' => $fyId,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
]);
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Profit & Loss Statement</h1>
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

<!-- P&L Statement -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b bg-gradient-to-r from-blue-50 to-white">
        <h2 class="text-lg font-bold text-gray-800">PROFIT & LOSS STATEMENT</h2>
        <p class="text-sm text-gray-600">Period: <?= date('M d, Y', strtotime($dateFrom)) ?> - <?= date('M d, Y', strtotime($dateTo)) ?></p>
    </div>
    
    <div class="p-6">
        <table class="w-full">
            <tbody>
                <?php foreach ($report['profit_loss'] as $key => $item): ?>
                <tr class="<?= $item['is_subtotal'] ?? false ? 'border-t-2 border-gray-300 bg-gray-50' : '' ?> <?= $item['is_total'] ?? false ? 'border-t-2 border-blue-500 bg-blue-50' : '' ?>">
                    <td class="py-3 <?= ($item['is_subtotal'] ?? false) || ($item['is_total'] ?? false) ? 'font-bold' : '' ?> text-gray-800">
                        <?= sanitize($item['label']) ?>
                    </td>
                    <td class="py-3 text-right font-mono <?= ($item['amount'] ?? 0) < 0 ? 'text-red-600' : 'text-gray-800' ?> <?= ($item['is_subtotal'] ?? false) || ($item['is_total'] ?? false) ? 'font-bold text-lg' : '' ?>">
                        <?= formatMoney(abs($item['amount'] ?? 0)) ?>
                    </td>
                    <?php if ($key === 'revenue' || $key === 'gross_profit' || $key === 'net_profit'): ?>
                    <td class="py-3 text-right text-sm text-gray-500">
                        <?php if ($key === 'gross_profit'): ?><?= $report['margins']['gross_margin'] ?>%<?php endif; ?>
                        <?php if ($key === 'net_profit'): ?><?= $report['margins']['net_margin'] ?>%<?php endif; ?>
                    </td>
                    <?php else: ?>
                    <td></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Key Metrics -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
        <p class="text-xs text-gray-500 uppercase">Revenue</p>
        <p class="text-xl font-bold text-gray-800"><?= formatMoney($report['profit_loss']['revenue']['amount']) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
        <p class="text-xs text-gray-500 uppercase">Gross Profit</p>
        <p class="text-xl font-bold <?= $report['profit_loss']['gross_profit']['amount'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
            <?= formatMoney($report['profit_loss']['gross_profit']['amount']) ?>
        </p>
        <p class="text-xs text-gray-400">Gross Margin: <?= $report['margins']['gross_margin'] ?>%</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-indigo-500">
        <p class="text-xs text-gray-500 uppercase">Operating Profit</p>
        <p class="text-xl font-bold <?= $report['profit_loss']['operating_profit']['amount'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
            <?= formatMoney($report['profit_loss']['operating_profit']['amount']) ?>
        </p>
        <p class="text-xs text-gray-400">Operating Margin: <?= $report['margins']['operating_margin'] ?>%</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-emerald-600">
        <p class="text-xs text-gray-500 uppercase">Net Profit</p>
        <p class="text-xl font-bold <?= $report['profit_loss']['net_profit']['amount'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
            <?= formatMoney($report['profit_loss']['net_profit']['amount']) ?>
        </p>
        <p class="text-xs text-gray-400">Net Margin: <?= $report['margins']['net_margin'] ?>%</p>
    </div>
</div>

<?php endif; ?>

<style media="print">
    .erp-layout, nav, .sidebar, .navbar, form, button { display: none !important; }
    body { background: white; }
    @page { margin: 10mm; }
</style>

<?php require_once '../../../../includes/footer.php'; ?>
