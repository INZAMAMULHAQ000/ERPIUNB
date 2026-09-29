<?php
$pageTitle = 'General Ledger';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';
require_once '../helpers/finance_helpers.php';

if (!hasPermission('finance.ledger.view')) { die('Access Denied'); }

$db = getDB();

// --- Filters ---
$accountId      = (int) ($_GET['account_id'] ?? 0);
$dateFrom       = $_GET['date_from'] ?? '';
$dateTo         = $_GET['date_to'] ?? '';
$fyId           = (int) ($_GET['financial_year_id'] ?? 0);
$voucherFilter  = $_GET['voucher_type'] ?? '';
$page           = getPageNumber();
$perPage        = 25;

// Default to active financial year
$activeFY = getActiveFinancialYear($db);
if (!$fyId && $activeFY) {
    $fyId = (int) $activeFY['id'];
}

// --- Build WHERE for general_ledger ---
$where  = "WHERE 1=1";
$params = [];

if ($accountId) {
    $where .= " AND gl.account_id = ?";
    $params[] = $accountId;
}
if ($dateFrom) {
    $where .= " AND gl.posting_date >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where .= " AND gl.posting_date <= ?";
    $params[] = $dateTo;
}
if ($fyId) {
    $where .= " AND gl.financial_year_id = ?";
    $params[] = $fyId;
}
if ($voucherFilter !== '') {
    $where .= " AND gl.voucher_type = ?";
    $params[] = $voucherFilter;
}

// --- Dropdown data ---
$accounts       = $db->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE status = 'active' ORDER BY account_code")->fetchAll();
$financialYears = $db->query("SELECT id, year_name FROM financial_years ORDER BY start_date DESC")->fetchAll();
$voucherTypes   = $db->query("SELECT DISTINCT voucher_name FROM voucher_types WHERE status = 'active' ORDER BY voucher_name")->fetchAll();

// --- Opening balance for selected account ---
$openingBalance     = 0.00;
$openingBalanceType = 'debit';
$effectiveOpening   = 0.00;

if ($accountId) {
    $acctStmt = $db->prepare("SELECT opening_balance, opening_balance_type FROM chart_of_accounts WHERE id = ?");
    $acctStmt->execute([$accountId]);
    $acct = $acctStmt->fetch();
    if ($acct) {
        $openingBalance     = (float) $acct['opening_balance'];
        $openingBalanceType = $acct['opening_balance_type'];
    }
    $effectiveOpening = ($openingBalanceType === 'credit') ? -$openingBalance : $openingBalance;
}

// --- Count & paginate ---
$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM general_ledger gl $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetch()['cnt'];
$pagination = paginate($total, $perPage, $page);

// --- Compute running balance at the start of the current page ---
// We need the cumulative (debit - credit) for ALL entries before the first
// entry on this page, then add the effective opening balance.
$runningStart = $effectiveOpening;

if ($pagination['offset'] > 0 && $total > 0) {
    // Locate the first entry on the current page so we know the boundary
    $firstStmt = $db->prepare("
        SELECT posting_date, id
        FROM general_ledger gl
        $where
        ORDER BY gl.posting_date, gl.id
        LIMIT 1 OFFSET ?
    ");
    $firstStmt->execute(array_merge($params, [$pagination['offset']]));
    $firstEntry = $firstStmt->fetch();

    if ($firstEntry) {
        // Sum all entries strictly before the first entry on this page
        $beforeWhere = $where . " AND (gl.posting_date < ? OR (gl.posting_date = ? AND gl.id < ?))";
        $beforeParams = array_merge($params, [
            $firstEntry['posting_date'],
            $firstEntry['posting_date'],
            $firstEntry['id'],
        ]);
        $sumStmt = $db->prepare("
            SELECT COALESCE(SUM(gl.debit), 0) as d, COALESCE(SUM(gl.credit), 0) as c
            FROM general_ledger gl
            $beforeWhere
        ");
        $sumStmt->execute($beforeParams);
        $sums = $sumStmt->fetch();
        $runningStart = $effectiveOpening + (float) $sums['d'] - (float) $sums['c'];
    }
}

// --- Fetch current page of GL entries ---
$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT gl.*, je.description
    FROM general_ledger gl
    LEFT JOIN journal_entries je ON gl.journal_entry_id = je.id
    $where
    ORDER BY gl.posting_date, gl.id
    LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$entries = $stmt->fetchAll();

// Compute display running balance and totals
$currentRunning = $runningStart;
$totalDebit     = 0.00;
$totalCredit    = 0.00;

foreach ($entries as &$entry) {
    $currentRunning += (float) $entry['debit'] - (float) $entry['credit'];
    $entry['display_running'] = $currentRunning;
    $totalDebit  += (float) $entry['debit'];
    $totalCredit += (float) $entry['credit'];
}
unset($entry);
?>
<!-- Page Header -->
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">General Ledger</h1>
        <p class="text-gray-600">View ledger entries with running balances</p>
    </div>
    <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
        <i class="fas fa-arrow-left mr-2"></i>Back to Finance
    </a>
</div>

<!-- Filter Form -->
<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="w-60">
            <label class="block text-sm font-medium text-gray-700 mb-1">Account</label>
            <select name="account_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Accounts</option>
                <?php foreach ($accounts as $a): ?>
                <option value="<?= $a['id'] ?>" <?= $accountId === (int) $a['id'] ? 'selected' : '' ?>>
                    <?= sanitize($a['account_code'] . ' - ' . $a['account_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-44">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input type="date" id="date_from" name="date_from" value="<?= sanitize($dateFrom) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-44">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input type="date" id="date_to" name="date_to" value="<?= sanitize($dateTo) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-44">
            <label class="block text-sm font-medium text-gray-700 mb-1">Financial Year</label>
            <select name="financial_year_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach ($financialYears as $fy): ?>
                <option value="<?= $fy['id'] ?>" <?= $fyId === (int) $fy['id'] ? 'selected' : '' ?>>
                    <?= sanitize($fy['year_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Voucher Type</label>
            <select name="voucher_type" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach ($voucherTypes as $vt): ?>
                <option value="<?= sanitize($vt['voucher_name']) ?>" <?= $voucherFilter === $vt['voucher_name'] ? 'selected' : '' ?>>
                    <?= sanitize($vt['voucher_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-search"></i>
        </button>
        <?php if ($accountId || $dateFrom || $dateTo || $fyId || $voucherFilter !== ''): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">
            <i class="fas fa-times"></i>
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Ledger Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voucher No</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voucher Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Running Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($accountId && $openingBalance != 0): ?>
                <!-- Opening Balance Row -->
                <tr class="bg-blue-50 font-medium">
                    <td class="px-4 py-3 text-sm text-gray-500" colspan="2">-</td>
                    <td class="px-4 py-3 text-sm text-gray-700" colspan="2">Opening Balance</td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900">
                        <?= $openingBalanceType === 'debit' ? formatNumber($openingBalance) : '-' ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900">
                        <?= $openingBalanceType === 'credit' ? formatNumber($openingBalance) : '-' ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-right font-mono font-semibold text-gray-900">
                        <?= formatNumber($effectiveOpening) ?>
                    </td>
                </tr>
                <?php endif; ?>

                <?php if (empty($entries) && !($accountId && $openingBalance != 0)): ?>
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                        <i class="fas fa-book-open text-4xl mb-3 text-gray-300"></i>
                        <p>No ledger entries found</p>
                        <?php if (!$accountId): ?>
                        <p class="text-xs mt-1">Select an account and apply filters to view ledger entries.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($entries as $entry): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($entry['posting_date'])) ?></td>
                    <td class="px-4 py-3 text-sm font-mono text-gray-900 whitespace-nowrap"><?= sanitize($entry['voucher_no']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($entry['voucher_type']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate"><?= sanitize($entry['description'] ?? '-') ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900">
                        <?= (float) $entry['debit'] > 0 ? formatNumber($entry['debit']) : '-' ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900">
                        <?= (float) $entry['credit'] > 0 ? formatNumber($entry['credit']) : '-' ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-right font-mono font-medium text-gray-900">
                        <?php $bal = $entry['display_running']; ?>
                        <span class="<?= $bal < 0 ? 'text-red-600' : ($bal > 0 ? 'text-blue-600' : '') ?>">
                            <?= formatNumber($bal) ?>
                            <span class="text-xs ml-0.5"><?= $bal >= 0 ? 'Dr' : 'Cr' ?></span>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>

            <?php if (!empty($entries)): ?>
            <tfoot class="bg-gray-100 font-semibold">
                <tr>
                    <td colspan="4" class="px-4 py-3 text-sm text-gray-700 text-right">Page Totals</td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatNumber($totalDebit) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatNumber($totalCredit) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-500">-</td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>
$(document).ready(function () {

    function updateToDate() {

        const fromDate = $('#date_from').val();
        const $toDate = $('#date_to');

        if (fromDate) {

            $toDate.attr('min', fromDate);

            if ($toDate.val() && $toDate.val() < fromDate) {
                $toDate.val('');
            }

        } else {

            $toDate.removeAttr('min');
            $toDate.val('');
        }
    }

    // Page load
    updateToDate();

    // From Date change
    $('#date_from').on('change', function () {

        updateToDate();

        // To Date validation refresh
        $('#date_to').valid();
    });

    // To Date change
    $('#date_to').on('change', function () {

        const fromDate = $('#date_from').val();
        const toDate = $(this).val();

        if (!fromDate) {
            $(this).val('');
            return;
        }

        if (toDate < fromDate) {
            $(this).val('');
        }
    });

});
</script>

<?= paginationLinks($pagination, 'index.php') ?>
<?php require_once '../../../includes/footer.php'; ?>

