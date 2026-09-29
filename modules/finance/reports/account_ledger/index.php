<?php
/**
 * F11 — Account Ledger (Account Statement)
 * Drill-down from Trial Balance to individual account transactions
 */

$pageTitle = 'Account Ledger';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once '../FinancialReportService.php';

if (!hasPermission('report.account_ledger.view')) { die('Access Denied'); }

$db = getDB();

// Get account ID from URL or redirect back
$accountId = (int)($_GET['account_id'] ?? 0);
$fyId = (int)($_GET['financial_year_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Default to active FY
if (!$fyId) {
    $activeFY = getActiveFinancialYear($db);
    $fyId = $activeFY ? (int)$activeFY['id'] : 0;
}

$fyStmt = $db->prepare("SELECT start_date, end_date FROM financial_years WHERE id = ?");
$fyStmt->execute([$fyId]);
$fy = $fyStmt->fetch();

if (!$dateFrom && $fy) $dateFrom = $fy['start_date'];
if (!$dateTo && $fy) $dateTo = $fy['end_date'];

// If no account selected, show account selector
$showSelector = !$accountId;
$accounts = $db->query("
    SELECT coa.id, coa.account_code, coa.account_name, ag.group_name, ag.nature
    FROM chart_of_accounts coa
    JOIN account_groups ag ON ag.id = coa.group_id
    WHERE coa.status = 'active'
    ORDER BY coa.account_code
")->fetchAll();

$report = null;
if ($accountId) {
    $report = FinancialReportService::generateAccountLedger($accountId, [
        'financial_year_id' => $fyId,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
    ]);
}

$financialYears = $db->query("SELECT id, year_name FROM financial_years ORDER BY start_date DESC")->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Account Ledger</h1>
        <p class="text-gray-600"><?= $report && $report['success'] ? sanitize($report['account']['account_code'] . ' - ' . $report['account']['account_name']) : 'Select an account to view transactions' ?></p>
    </div>
    <div class="flex gap-2">
        <?php if ($report && $report['success']): ?>
        <button onclick="window.print()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"><i class="fas fa-print mr-2"></i>Print</button>
        <?php endif; ?>
        <a href="../trial_balance/index.php?financial_year_id=<?= $fyId ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-arrow-left mr-2"></i>Back to Trial Balance</a>
    </div>
</div>

<?php if (!$accountId): ?>
<!-- Account Selector -->
<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Select Account</h2>
    <form method="GET">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Financial Year</label>
                <select name="financial_year_id" class="px-3 py-2 border rounded w-full" onchange="this.form.submit()">
                    <?php foreach ($financialYears as $fy): ?>
                    <option value="<?= $fy['id'] ?>" <?= $fyId == $fy['id'] ? 'selected' : '' ?>><?= sanitize($fy['year_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Select Account</label>
                <select name="account_id" class="px-3 py-2 border rounded w-full" onchange="this.form.submit()">
                    <option value="">-- Select Account --</option>
                    <?php foreach ($accounts as $acct): ?>
                    <option value="<?= $acct['id'] ?>"><?= sanitize($acct['account_code'] . ' - ' . $acct['account_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                <div class="flex gap-2">
                    <input type="date" name="date_from" value="<?= sanitize($dateFrom) ?>" class="px-3 py-2 border rounded w-full" placeholder="From">
                    <input type="date" name="date_to" value="<?= sanitize($dateTo) ?>" class="px-3 py-2 border rounded w-full" placeholder="To">
                </div>
            </div>
        </div>
        <button type="submit" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">View Ledger</button>
    </form>
</div>

<?php elseif ($report && $report['success']): ?>

<!-- Account Info Header -->
<div class="bg-white rounded-lg shadow mb-6 p-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div>
            <p class="text-xs text-gray-500 uppercase">Account Code</p>
            <p class="text-lg font-bold text-gray-800"><?= sanitize($report['account']['account_code']) ?></p>
        </div>
        <div>
            <p class="text-xs text-gray-500 uppercase">Account Name</p>
            <p class="text-lg font-bold text-gray-800"><?= sanitize($report['account']['account_name']) ?></p>
        </div>
        <div>
            <p class="text-xs text-gray-500 uppercase">Account Group</p>
            <p class="text-gray-700"><?= sanitize($report['account']['group_name']) ?></p>
        </div>
        <div>
            <p class="text-xs text-gray-500 uppercase">Nature</p>
            <p class="text-gray-700"><?= ucfirst(sanitize($report['account']['nature'])) ?></p>
        </div>
    </div>
</div>

<!-- Date Filter -->
<div class="bg-white rounded-lg shadow mb-6 p-4">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <input type="hidden" name="account_id" value="<?= $accountId ?>">
        <input type="hidden" name="financial_year_id" value="<?= $fyId ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input type="date" name="date_from" value="<?= sanitize($dateFrom) ?>" class="px-3 py-2 border rounded">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input type="date" name="date_to" value="<?= sanitize($dateTo) ?>" class="px-3 py-2 border rounded">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-search mr-2"></i>Filter</button>
        <a href="?account_id=<?= $accountId ?>&financial_year_id=<?= $fyId ?>" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Reset</a>
    </form>
</div>

<!-- Opening Balance Card -->
<div class="bg-white rounded-lg shadow mb-6 p-4 border-l-4 border-blue-500">
    <div class="flex justify-between items-center">
        <div>
            <p class="text-sm text-gray-500">Opening Balance</p>
            <p class="text-2xl font-bold <?= $report['opening_balance'] >= 0 ? 'text-gray-800' : 'text-red-600' ?>">
                <?= formatMoney(abs($report['opening_balance'])) ?>
                <span class="text-sm font-normal"><?= $report['opening_balance'] >= 0 ? 'Dr' : 'Cr' ?></span>
            </p>
        </div>
        <div class="text-right">
            <p class="text-sm text-gray-500">Period</p>
            <p class="text-gray-700"><?= date('M d, Y', strtotime($dateFrom)) ?> - <?= date('M d, Y', strtotime($dateTo)) ?></p>
        </div>
    </div>
</div>

<!-- Transactions Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Journal #</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit</th>
                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Running Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['transactions'])): ?>
                <tr>
                    <td colspan="7" class="px-3 py-8 text-center text-gray-500">No transactions found for this period</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['transactions'] as $txn): ?>
                <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location.href='../../journal_entries/edit.php?id=<?= $txn['journal_entry_id'] ?>'">
                    <td class="px-3 py-2 text-gray-700"><?= date('M d, Y', strtotime($txn['posting_date'])) ?></td>
                    <td class="px-3 py-2 font-mono text-blue-600"><?= sanitize($txn['reference_no'] ?? '-') ?></td>
                    <td class="px-3 py-2 text-gray-600"><?= sanitize($txn['reference_no'] ?? '') ?></td>
                    <td class="px-3 py-2 text-gray-700"><?= sanitize($txn['description'] ?? '-') ?></td>
                    <td class="px-3 py-2 text-right font-mono text-gray-800"><?= $txn['debit'] > 0 ? formatMoney($txn['debit']) : '-' ?></td>
                    <td class="px-3 py-2 text-right font-mono text-gray-800"><?= $txn['credit'] > 0 ? formatMoney($txn['credit']) : '-' ?></td>
                    <td class="px-3 py-2 text-right font-mono font-semibold <?= $txn['running_balance'] >= 0 ? 'text-gray-800' : 'text-red-600' ?>">
                        <?= formatMoney(abs($txn['running_balance'])) ?> <span class="text-xs"><?= $txn['running_balance'] >= 0 ? 'Dr' : 'Cr' ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="bg-gray-100 font-bold">
                <tr>
                    <td colspan="4" class="px-3 py-3 text-right">TOTALS</td>
                    <td class="px-3 py-3 text-right font-mono text-gray-900"><?= formatMoney($report['totals']['debit']) ?></td>
                    <td class="px-3 py-3 text-right font-mono text-gray-900"><?= formatMoney($report['totals']['credit']) ?></td>
                    <td class="px-3 py-3 text-right font-mono text-gray-900"><?= formatMoney(abs($report['closing_balance'])) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Closing Balance Card -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
    <div class="bg-white rounded-lg shadow p-4">
        <p class="text-sm text-gray-500">Closing Balance</p>
        <p class="text-2xl font-bold <?= $report['closing_balance'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
            <?= formatMoney(abs($report['closing_balance'])) ?>
            <span class="text-sm font-normal"><?= $report['closing_balance'] >= 0 ? 'Debit' : 'Credit' ?></span>
        </p>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <p class="text-sm text-gray-500">Net Movement</p>
        <p class="text-2xl font-bold <?= ($report['totals']['debit'] - $report['totals']['credit']) >= 0 ? 'text-green-600' : 'text-red-600' ?>">
            <?= formatMoney(abs($report['totals']['debit'] - $report['totals']['credit'])) ?>
            <span class="text-sm font-normal"><?= ($report['totals']['debit'] - $report['totals']['credit']) >= 0 ? 'Debit' : 'Credit' ?></span>
        </p>
    </div>
</div>

<?php else: ?>
<div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
    <?= sanitize($report['message'] ?? 'Unable to generate account ledger') ?>
</div>
<?php endif; ?>

<style media="print">
    .erp-layout, nav, .sidebar, .navbar, form { display: none !important; }
    body { background: white; }
    @page { margin: 10mm; }
</style>

<?php require_once '../../../../includes/footer.php'; ?>
