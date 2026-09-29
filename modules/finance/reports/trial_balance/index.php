<?php
/**
 * F11 — Trial Balance Report
 */

$pageTitle = 'Trial Balance';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../helpers/pagination.php';
require_once '../../helpers/finance_helpers.php';
require_once '../FinancialReportService.php';

if (!hasPermission('report.trial_balance.view')) { die('Access Denied'); }

$db = getDB();

// Filters
$fyId = (int)($_GET['financial_year_id'] ?? 0);
$periodId = (int)($_GET['period_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Default to active FY
if (!$fyId) {
    $activeFY = getActiveFinancialYear($db);
    $fyId = $activeFY ? (int)$activeFY['id'] : 0;
}

// Dropdowns
$financialYears = $db->query("SELECT id, year_name, start_date, end_date FROM financial_years ORDER BY start_date DESC")->fetchAll();
$periods = $fyId ? $db->prepare("SELECT id, period_name, start_date, end_date FROM accounting_periods WHERE financial_year_id = ? ORDER BY start_date") : null;
if ($periods) $periods->execute([$fyId]);

// Generate report
$report = FinancialReportService::generateTrialBalance([
    'financial_year_id' => $fyId,
    'period_id' => $periodId,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
]);

// Log report generation
if ($report['success'] && isset($_SESSION['user_id'])) {
    FinancialReportService::logReportGeneration('trial_balance', $report['params'], $_SESSION['user_id']);
}
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Trial Balance</h1>
        <p class="text-gray-600">Debit and Credit totals must be equal</p>
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
            <label class="block text-sm font-medium text-gray-700 mb-1">Financial Year</label>
            <select name="financial_year_id" class="px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
                <?php foreach ($financialYears as $fy): ?>
                <option value="<?= $fy['id'] ?>" <?= $fyId == $fy['id'] ? 'selected' : '' ?>><?= sanitize($fy['year_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Period</label>
            <select name="period_id" class="px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Periods</option>
                <?php if ($periods): foreach ($periods->fetchAll() as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $periodId == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['period_name']) ?></option>
                <?php endforeach; endif; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input type="date" name="date_from" value="<?= sanitize($dateFrom) ?>" class="px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input type="date" name="date_to" value="<?= sanitize($dateTo) ?>" class="px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-search mr-2"></i>Generate</button>
    </form>
</div>

<?php if (!$report['success']): ?>
<div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700"><?= sanitize($report['message']) ?></div>
<?php else: ?>

<!-- Integrity Warning -->
<?php if (!$report['integrity']['is_balanced']): ?>
<div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
    <div class="flex items-center">
        <i class="fas fa-exclamation-triangle text-red-500 text-2xl mr-3"></i>
        <div>
            <h3 class="font-bold text-red-800">CRITICAL ACCOUNTING INTEGRITY ERROR</h3>
            <p class="text-red-700"><?= sanitize($report['integrity']['message']) ?></p>
            <p class="text-sm text-red-600 mt-1">Debit: <?= formatMoney($report['totals']['closing_debit']) ?> | Credit: <?= formatMoney($report['totals']['closing_credit']) ?> | Difference: <?= formatMoney($report['integrity']['closing_diff'] ?? 0) ?></p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Trial Balance Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Code</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Name</th>
                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Opening Dr</th>
                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Opening Cr</th>
                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Period Dr</th>
                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Period Cr</th>
                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Closing Dr</th>
                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Closing Cr</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($report['lines'] as $line): ?>
                <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location.href='../account_ledger/index.php?account_id=<?= $line['account_id'] ?>&financial_year_id=<?= $fyId ?>'">
                    <td class="px-3 py-2 font-mono text-gray-800"><?= sanitize($line['account_code']) ?></td>
                    <td class="px-3 py-2 text-gray-700"><?= sanitize($line['account_name']) ?></td>
                    <td class="px-3 py-2 text-right font-mono text-gray-800"><?= $line['opening_debit'] > 0 ? formatMoney($line['opening_debit']) : '-' ?></td>
                    <td class="px-3 py-2 text-right font-mono text-gray-800"><?= $line['opening_credit'] > 0 ? formatMoney($line['opening_credit']) : '-' ?></td>
                    <td class="px-3 py-2 text-right font-mono text-gray-800"><?= $line['period_debit'] > 0 ? formatMoney($line['period_debit']) : '-' ?></td>
                    <td class="px-3 py-2 text-right font-mono text-gray-800"><?= $line['period_credit'] > 0 ? formatMoney($line['period_credit']) : '-' ?></td>
                    <td class="px-3 py-2 text-right font-mono font-semibold text-gray-900"><?= $line['closing_debit'] > 0 ? formatMoney($line['closing_debit']) : '-' ?></td>
                    <td class="px-3 py-2 text-right font-mono font-semibold text-gray-900"><?= $line['closing_credit'] > 0 ? formatMoney($line['closing_credit']) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-gray-100 font-bold">
                <tr>
                    <td colspan="2" class="px-3 py-3 text-right text-gray-700">TOTALS</td>
                    <td class="px-3 py-3 text-right font-mono text-gray-900"><?= formatMoney($report['totals']['opening_debit']) ?></td>
                    <td class="px-3 py-3 text-right font-mono text-gray-900"><?= formatMoney($report['totals']['opening_credit']) ?></td>
                    <td class="px-3 py-3 text-right font-mono text-gray-900"><?= formatMoney($report['totals']['period_debit']) ?></td>
                    <td class="px-3 py-3 text-right font-mono text-gray-900"><?= formatMoney($report['totals']['period_credit']) ?></td>
                    <td class="px-3 py-3 text-right font-mono text-gray-900 <?= $report['integrity']['is_balanced'] ? '' : 'text-red-600' ?>"><?= formatMoney($report['totals']['closing_debit']) ?></td>
                    <td class="px-3 py-3 text-right font-mono text-gray-900 <?= $report['integrity']['is_balanced'] ? '' : 'text-red-600' ?>"><?= formatMoney($report['totals']['closing_credit']) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <p class="text-xs text-gray-500 uppercase">Total Debit</p>
        <p class="text-2xl font-bold text-gray-800"><?= formatMoney($report['totals']['closing_debit']) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <p class="text-xs text-gray-500 uppercase">Total Credit</p>
        <p class="text-2xl font-bold text-gray-800"><?= formatMoney($report['totals']['closing_credit']) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center <?= $report['integrity']['is_balanced'] ? 'border-green-400 border-2' : 'border-red-400 border-2' ?>">
        <p class="text-xs text-gray-500 uppercase">Status</p>
        <p class="text-lg font-bold <?= $report['integrity']['is_balanced'] ? 'text-green-600' : 'text-red-600' ?>">
            <?= $report['integrity']['is_balanced'] ? '✓ Balanced' : '✗ Not Balanced' ?>
        </p>
    </div>
</div>

<?php endif; ?>

<style media="print">
    .erp-layout, nav, .sidebar, .navbar { display: none !important; }
    body { background: white; }
    @page { margin: 10mm; }
</style>

<?php require_once '../../../../includes/footer.php'; ?>
