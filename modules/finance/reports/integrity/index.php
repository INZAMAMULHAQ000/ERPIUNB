<?php
/**
 * F11 — Integrity Checker
 * Accounting reconciliation and data integrity validation
 */

$pageTitle = 'Integrity Checker';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once '../FinancialReportService.php';

if (!hasPermission('report.integrity.view')) { die('Access Denied'); }

$db = getDB();

// Get parameters
$fyId = (int)($_GET['financial_year_id'] ?? 0);
$dateAsOf = $_GET['date_as_of'] ?? date('Y-m-d');

// Default to active FY
if (!$fyId) {
    $activeFY = getActiveFinancialYear($db);
    $fyId = $activeFY ? (int)$activeFY['id'] : 0;
}

$fyStmt = $db->prepare("SELECT start_date, end_date FROM financial_years WHERE id = ?");
$fyStmt->execute([$fyId]);
$fy = $fyStmt->fetch();

if (!$dateAsOf && $fy) $dateAsOf = $fy['end_date'];

$financialYears = $db->query("SELECT id, year_name FROM financial_years ORDER BY start_date DESC")->fetchAll();

// Run integrity check
$report = FinancialReportService::runIntegrityCheck([
    'financial_year_id' => $fyId,
    'date_as_of' => $dateAsOf,
]);
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Integrity Checker</h1>
        <p class="text-gray-600">Accounting reconciliation and data validation as of <?= date('M d, Y', strtotime($dateAsOf)) ?></p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.location.reload()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-sync-alt mr-2"></i>Refresh</button>
        <a href="../dashboard/index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-arrow-left mr-2"></i>Dashboard</a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow mb-6 p-4">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Financial Year</label>
            <select name="financial_year_id" class="px-3 py-2 border rounded" onchange="this.form.submit()">
                <?php foreach ($financialYears as $fy): ?>
                <option value="<?= $fy['id'] ?>" <?= $fyId == $fy['id'] ? 'selected' : '' ?>><?= sanitize($fy['year_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">As of Date</label>
            <input type="date" name="date_as_of" value="<?= sanitize($dateAsOf) ?>" class="px-3 py-2 border rounded">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-search mr-2"></i>Run Check</button>
    </form>
</div>

<?php if ($report && $report['success']): ?>

<!-- Overall Status -->
<div class="mb-6">
    <?php if ($report['overall_status'] === 'pass'): ?>
    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-check-circle text-green-500 text-3xl mr-4"></i>
            <div>
                <h3 class="font-bold text-green-800 text-lg">All Integrity Checks Passed</h3>
                <p class="text-green-700">Your accounting data is balanced and reconciled.</p>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle text-red-500 text-3xl mr-4"></i>
            <div>
                <h3 class="font-bold text-red-800 text-lg">Integrity Issues Detected</h3>
                <p class="text-red-700">Some accounting reconciliations require attention.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Integrity Checks Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <?php foreach (($report['checks'] ?? []) as $check): ?>
    <?php
        $status = $check['status'] ?? 'warning';
        $isPass = $status === 'pass';
        $tone = $isPass ? 'green' : ($status === 'fail' ? 'red' : 'yellow');
        $bg = $tone === 'green' ? 'bg-green-50' : ($tone === 'red' ? 'bg-red-50' : 'bg-yellow-50');
        $text = $tone === 'green' ? 'text-green-800' : ($tone === 'red' ? 'text-red-800' : 'text-yellow-800');
        $badge = $tone === 'green' ? 'bg-green-100 text-green-800' : ($tone === 'red' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800');
        $metrics = $check;
        unset($metrics['label'], $metrics['status'], $metrics['message'], $metrics['details']);
    ?>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b <?= $bg ?>">
            <div class="flex justify-between items-center">
                <h3 class="font-semibold <?= $text ?>"><?= sanitize($check['label'] ?? 'Integrity Check') ?></h3>
                <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $badge ?>"><?= ucfirst($status) ?></span>
            </div>
        </div>
        <div class="p-4">
            <p class="text-sm text-gray-600 mb-4"><?= sanitize($check['message'] ?? '') ?></p>
            <?php if (!empty($check['details']) && is_array($check['details'])): ?>
            <div class="grid grid-cols-2 gap-4 text-sm mb-3">
                <?php foreach ($check['details'] as $key => $value): ?>
                <div>
                    <p class="text-gray-500"><?= sanitize(ucwords(str_replace('_', ' ', $key))) ?></p>
                    <p class="font-mono font-semibold"><?= is_numeric($value) ? formatMoney($value) : sanitize((string)$value) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($metrics)): ?>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <?php foreach ($metrics as $key => $value): ?>
                <?php if (is_array($value)) continue; ?>
                <div>
                    <p class="text-gray-500"><?= sanitize(ucwords(str_replace('_', ' ', $key))) ?></p>
                    <p class="font-mono font-semibold"><?= is_numeric($value) ? formatMoney($value) : sanitize((string)$value) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Additional Validation Info -->
<div class="bg-gray-50 rounded-lg p-6 mt-6">
    <h4 class="font-semibold text-gray-800 mb-4">About Integrity Checks</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-600">
        <div>
            <h5 class="font-medium text-gray-700 mb-2">What is checked:</h5>
            <ul class="space-y-1">
                <li>• Trial Balance: Total Debits = Total Credits</li>
                <li>• Balance Sheet: Assets = Liabilities + Equity</li>
                <li>• AR Control: GL matches customer subledger</li>
                <li>• AP Control: GL matches vendor subledger</li>
                <li>• Tax: tax subledger compared with GL tax payable</li>
                <li>• Fixed Assets, Payroll, Bank and Budget checks</li>
            </ul>
        </div>
        <div>
            <h5 class="font-medium text-gray-700 mb-2">What to do if issues found:</h5>
            <ul class="space-y-1">
                <li>• Review journal entries for errors</li>
                <li>• Check for unposted transactions</li>
                <li>• Verify account mappings</li>
                <li>• Reconcile subledger to GL</li>
                <li>• Contact your accountant</li>
            </ul>
        </div>
    </div>
</div>

<!-- Timestamp -->
<div class="text-center text-xs text-gray-500 mt-6">
    <p>Integrity check last run: <?= date('Y-m-d H:i:s') ?></p>
    <p>Financial Year: <?= $fyId ? sanitize($fy['year_name'] ?? '') : 'All' ?> | As of: <?= $dateAsOf ?></p>
</div>

<?php else: ?>
<div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
    Unable to run integrity check. Please ensure financial data exists.
</div>
<?php endif; ?>

<style media="print">
    .erp-layout, nav, .sidebar, .navbar, form, button { display: none !important; }
    body { background: white; }
    @page { margin: 10mm; }
</style>

<?php require_once '../../../../includes/footer.php'; ?>
