<?php
/**
 * F11 — Balance Sheet
 */

$pageTitle = 'Balance Sheet';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once '../FinancialReportService.php';

if (!hasPermission('report.balance_sheet.view')) { die('Access Denied'); }

$db = getDB();

$fyId = (int)($_GET['financial_year_id'] ?? 0);
$dateAsOf = $_GET['date_as_of'] ?? '';

if (!$fyId) {
    $activeFY = getActiveFinancialYear($db);
    $fyId = $activeFY ? (int)$activeFY['id'] : 0;
}

$fyStmt = $db->prepare("SELECT start_date, end_date FROM financial_years WHERE id = ?");
$fyStmt->execute([$fyId]);
$fy = $fyStmt->fetch();

if (!$dateAsOf && $fy) $dateAsOf = $fy['end_date'];

$financialYears = $db->query("SELECT id, year_name FROM financial_years ORDER BY start_date DESC")->fetchAll();

$report = FinancialReportService::generateBalanceSheet([
    'financial_year_id' => $fyId,
    'date_as_of' => $dateAsOf,
]);
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Balance Sheet</h1>
        <p class="text-gray-600">As of <?= date('M d, Y', strtotime($dateAsOf)) ?></p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"><i class="fas fa-print mr-2"></i>Print</button>
        <a href="../dashboard/index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-arrow-left mr-2"></i>Dashboard</a>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6 p-4">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Financial Year</label>
            <select name="financial_year_id" class="px-3 py-2 border rounded">
                <?php foreach ($financialYears as $fy): ?>
                <option value="<?= $fy['id'] ?>" <?= $fyId == $fy['id'] ? 'selected' : '' ?>><?= sanitize($fy['year_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">As of Date</label>
            <input type="date" name="date_as_of" value="<?= sanitize($dateAsOf) ?>" class="px-3 py-2 border rounded">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-search mr-2"></i>Generate</button>
    </form>
</div>

<?php if ($report['success']): ?>

<?php if (!$report['integrity']['is_balanced']): ?>
<div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
    <h3 class="font-bold text-red-800">CRITICAL: Balance Sheet NOT Balanced</h3>
    <p class="text-red-700">Assets: <?= formatMoney($report['integrity']['total_assets']) ?> | Liabilities + Equity: <?= formatMoney($report['integrity']['total_liabilities_equity']) ?></p>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- ASSETS -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-500">
            <h2 class="text-lg font-bold text-white">ASSETS</h2>
        </div>
        <div class="p-6">
            <?php foreach ($report['balance_sheet']['assets'] as $key => $item): ?>
            <?php if ($key === 'total') continue; ?>
            <div class="flex justify-between py-2 border-b border-gray-100">
                <span class="text-gray-700"><?= sanitize($item['label']) ?></span>
                <span class="font-mono text-gray-800"><?= formatMoney($item['amount']) ?></span>
            </div>
            <?php endforeach; ?>
            <div class="flex justify-between py-3 mt-4 bg-blue-50 font-bold text-lg">
                <span>TOTAL ASSETS</span>
                <span class="font-mono text-blue-700"><?= formatMoney($report['balance_sheet']['assets']['total']['amount']) ?></span>
            </div>
        </div>
    </div>
    
    <!-- LIABILITIES & EQUITY -->
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-red-600 to-red-500">
                <h2 class="text-lg font-bold text-white">LIABILITIES</h2>
            </div>
            <div class="p-6">
                <?php foreach ($report['balance_sheet']['liabilities'] as $key => $item): ?>
                <?php if ($key === 'total') continue; ?>
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="text-gray-700"><?= sanitize($item['label']) ?></span>
                    <span class="font-mono text-gray-800"><?= formatMoney($item['amount']) ?></span>
                </div>
                <?php endforeach; ?>
                <div class="flex justify-between py-3 mt-4 bg-red-50 font-bold">
                    <span>Total Liabilities</span>
                    <span class="font-mono text-red-700"><?= formatMoney($report['balance_sheet']['liabilities']['total']['amount']) ?></span>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-green-600 to-green-500">
                <h2 class="text-lg font-bold text-white">EQUITY</h2>
            </div>
            <div class="p-6">
                <?php foreach ($report['balance_sheet']['equity'] as $key => $item): ?>
                <?php if ($key === 'total') continue; ?>
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="text-gray-700"><?= sanitize($item['label']) ?></span>
                    <span class="font-mono <?= $item['amount'] < 0 ? 'text-red-600' : 'text-gray-800' ?>"><?= formatMoney($item['amount']) ?></span>
                </div>
                <?php endforeach; ?>
                <div class="flex justify-between py-3 mt-4 bg-green-50 font-bold">
                    <span>Total Equity</span>
                    <span class="font-mono text-green-700"><?= formatMoney($report['balance_sheet']['equity']['total']['amount']) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Validation Box -->
<div class="mt-6 bg-white rounded-lg shadow p-6 border-2 <?= $report['integrity']['is_balanced'] ? 'border-green-500' : 'border-red-500' ?>">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="font-bold text-gray-800">Balance Sheet Validation</h3>
            <p class="text-sm text-gray-600">Total Liabilities + Equity must equal Total Assets</p>
        </div>
        <div class="text-right">
            <p class="text-sm text-gray-500">Assets = Liabilities + Equity</p>
            <p class="font-mono font-bold text-lg">
                <?= formatMoney($report['integrity']['total_assets']) ?> = <?= formatMoney($report['integrity']['total_liabilities_equity']) ?>
            </p>
        </div>
        <div class="px-4 py-2 rounded <?= $report['integrity']['is_balanced'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
            <i class="fas <?= $report['integrity']['is_balanced'] ? 'fa-check-circle' : 'fa-times-circle' ?> mr-2"></i>
            <?= $report['integrity']['is_balanced'] ? 'Balanced' : 'Not Balanced' ?>
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
