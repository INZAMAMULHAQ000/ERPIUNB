<?php
/**
 * F11 — General Ledger Report
 */

$pageTitle = 'General Ledger Report';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once '../FinancialReportService.php';

if (!hasPermission('report.general_ledger.view')) { die('Access Denied'); }

$db = getDB();

$fyId = (int)($_GET['financial_year_id'] ?? 0);
$accountId = (int)($_GET['account_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$voucherType = $_GET['voucher_type'] ?? '';
$reference = $_GET['reference'] ?? '';
$userId = (int)($_GET['user_id'] ?? 0);

if (!$fyId) {
    $activeFY = getActiveFinancialYear($db);
    $fyId = $activeFY ? (int)$activeFY['id'] : 0;
}

$financialYears = $db->query("SELECT id, year_name, start_date, end_date FROM financial_years ORDER BY start_date DESC")->fetchAll();
$accounts = $db->query("
    SELECT coa.id, coa.account_code, coa.account_name, ag.group_name
    FROM chart_of_accounts coa
    JOIN account_groups ag ON ag.id = coa.group_id
    WHERE coa.status = 'active'
    ORDER BY coa.account_code
")->fetchAll();
$voucherTypes = $db->query("SELECT DISTINCT voucher_type FROM general_ledger ORDER BY voucher_type")->fetchAll(PDO::FETCH_COLUMN);
$users = $db->query("SELECT id, name FROM users WHERE deleted_at IS NULL ORDER BY name")->fetchAll();

$report = FinancialReportService::generateGeneralLedger([
    'financial_year_id' => $fyId,
    'account_id' => $accountId,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'voucher_type' => $voucherType,
    'reference' => $reference,
    'user_id' => $userId,
]);

if (($report['success'] ?? false) && isset($_SESSION['user_id'])) {
    FinancialReportService::logReportGeneration('general_ledger', $report['params'], $_SESSION['user_id']);
}
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">General Ledger</h1>
        <p class="text-gray-600">Complete GL movement sourced from posted ledger entries</p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"><i class="fas fa-print mr-2"></i>Print</button>
        <a href="../exports/index.php?export=1&report_type=general_ledger&format=csv&financial_year_id=<?= $fyId ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-file-csv mr-2"></i>CSV</a>
        <a href="../dashboard/index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-arrow-left mr-2"></i>Dashboard</a>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6 p-4">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-6 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Financial Year</label>
            <select name="financial_year_id" class="w-full px-3 py-2 border rounded">
                <?php foreach ($financialYears as $fy): ?>
                <option value="<?= $fy['id'] ?>" <?= $fyId == $fy['id'] ? 'selected' : '' ?>><?= sanitize($fy['year_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Account</label>
            <select name="account_id" class="w-full px-3 py-2 border rounded">
                <option value="">All Accounts</option>
                <?php foreach ($accounts as $account): ?>
                <option value="<?= $account['id'] ?>" <?= $accountId == $account['id'] ? 'selected' : '' ?>>
                    <?= sanitize($account['account_code'] . ' - ' . $account['account_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input type="date" name="date_from" value="<?= sanitize($dateFrom) ?>" class="w-full px-3 py-2 border rounded">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input type="date" name="date_to" value="<?= sanitize($dateTo) ?>" class="w-full px-3 py-2 border rounded">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Voucher Type</label>
            <select name="voucher_type" class="w-full px-3 py-2 border rounded">
                <option value="">All Types</option>
                <?php foreach ($voucherTypes as $type): ?>
                <option value="<?= sanitize($type) ?>" <?= $voucherType === $type ? 'selected' : '' ?>><?= sanitize($type) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Reference / Text</label>
            <input type="text" name="reference" value="<?= sanitize($reference) ?>" class="w-full px-3 py-2 border rounded" placeholder="Voucher, ref, description">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
            <select name="user_id" class="w-full px-3 py-2 border rounded">
                <option value="">All Users</option>
                <?php foreach ($users as $user): ?>
                <option value="<?= $user['id'] ?>" <?= $userId == $user['id'] ? 'selected' : '' ?>><?= sanitize($user['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-search mr-2"></i>Generate</button>
            <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400"><i class="fas fa-times"></i></a>
        </div>
    </form>
</div>

<?php if (!($report['success'] ?? false)): ?>
<div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700"><?= sanitize($report['message'] ?? 'Unable to generate General Ledger report') ?></div>
<?php else: ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
        <p class="text-xs text-gray-500 uppercase">Total Debit</p>
        <p class="text-xl font-bold text-gray-800"><?= formatMoney($report['totals']['debit']) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
        <p class="text-xs text-gray-500 uppercase">Total Credit</p>
        <p class="text-xl font-bold text-gray-800"><?= formatMoney($report['totals']['credit']) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-emerald-500">
        <p class="text-xs text-gray-500 uppercase">Net Movement</p>
        <p class="text-xl font-bold <?= $report['totals']['net'] >= 0 ? 'text-green-600' : 'text-red-600' ?>"><?= formatMoney($report['totals']['net']) ?></p>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voucher</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit</th>
                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Running</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['rows'])): ?>
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">No ledger entries found for the selected filters</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['rows'] as $row): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2 whitespace-nowrap"><?= date('M d, Y', strtotime($row['posting_date'])) ?></td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <?php if (!empty($row['journal_entry_id'])): ?>
                        <a class="text-blue-600 hover:underline font-mono" href="../../journal_entries/edit.php?id=<?= (int)$row['journal_entry_id'] ?>"><?= sanitize($row['voucher_no']) ?></a>
                        <?php else: ?>
                        <span class="font-mono"><?= sanitize($row['voucher_no']) ?></span>
                        <?php endif; ?>
                        <div class="text-xs text-gray-400"><?= sanitize($row['voucher_type']) ?></div>
                    </td>
                    <td class="px-3 py-2">
                        <a class="text-blue-600 hover:underline" href="../account_ledger/index.php?account_id=<?= (int)$row['account_id'] ?>&financial_year_id=<?= $fyId ?>">
                            <span class="font-mono"><?= sanitize($row['account_code']) ?></span> — <?= sanitize($row['account_name']) ?>
                        </a>
                        <div class="text-xs text-gray-400"><?= sanitize($row['group_name']) ?></div>
                    </td>
                    <td class="px-3 py-2"><?= sanitize($row['reference_no'] ?? '') ?></td>
                    <td class="px-3 py-2 max-w-sm truncate"><?= sanitize($row['description'] ?? '') ?></td>
                    <td class="px-3 py-2 text-right font-mono"><?= (float)$row['debit'] > 0 ? formatMoney($row['debit']) : '-' ?></td>
                    <td class="px-3 py-2 text-right font-mono"><?= (float)$row['credit'] > 0 ? formatMoney($row['credit']) : '-' ?></td>
                    <td class="px-3 py-2 text-right font-mono"><?= formatMoney($row['running_balance'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="bg-gray-100 font-bold">
                <tr>
                    <td colspan="5" class="px-3 py-3 text-right">TOTALS</td>
                    <td class="px-3 py-3 text-right font-mono"><?= formatMoney($report['totals']['debit']) ?></td>
                    <td class="px-3 py-3 text-right font-mono"><?= formatMoney($report['totals']['credit']) ?></td>
                    <td class="px-3 py-3 text-right font-mono"><?= formatMoney($report['totals']['net']) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php endif; ?>

<style media="print">
    .erp-layout, nav, .sidebar, .navbar, form, button, a.bg-blue-600, a.bg-gray-600 { display: none !important; }
    body { background: white; }
    @page { margin: 10mm; }
</style>

<?php require_once '../../../../includes/footer.php'; ?>
