<?php
$pageTitle = 'Cash Transactions';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';
require_once '../helpers/finance_helpers.php';
require_once '../helpers/banking_helpers.php';

if (!hasPermission('finance.cash.transaction')) { die('Access Denied'); }

$db = getDB();
$accountFilter = (int)($_GET['cash_account_id'] ?? 0);
$typeFilter = $_GET['transaction_type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = getPageNumber();
$perPage = 20;

$where = "WHERE 1=1"; $params = [];
if ($accountFilter) { $where .= " AND ct.cash_account_id = ?"; $params[] = $accountFilter; }
if ($typeFilter !== '') { $where .= " AND ct.transaction_type = ?"; $params[] = $typeFilter; }
if ($dateFrom) { $where .= " AND ct.transaction_date >= ?"; $params[] = $dateFrom; }
if ($dateTo) { $where .= " AND ct.transaction_date <= ?"; $params[] = $dateTo; }

$countStmt = $db->prepare("SELECT COUNT(*) FROM cash_transactions ct $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT ct.*, ca.cash_name, ca.location
    FROM cash_transactions ct
    JOIN cash_accounts ca ON ct.cash_account_id = ca.id
    $where ORDER BY ct.transaction_date DESC, ct.id DESC LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$txns = $stmt->fetchAll();

$cashAccounts = $db->query("SELECT id, cash_name, location FROM cash_accounts WHERE status = 'active' ORDER BY cash_name")->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Cash Transactions</h1>
        <p class="text-gray-600">Record cash receipts, payments, and adjustments</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-plus mr-2"></i>New Cash Transaction</a>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="w-52">
            <select name="cash_account_id" class="w-full px-3 py-2 border rounded">
                <option value="">All Cash Accounts</option>
                <?php foreach ($cashAccounts as $ca): ?>
                <option value="<?= $ca['id'] ?>" <?= $accountFilter === (int)$ca['id'] ? 'selected' : '' ?>><?= sanitize($ca['cash_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-36">
            <select name="transaction_type" class="w-full px-3 py-2 border rounded">
                <option value="">All Types</option>
                <option value="receipt" <?= $typeFilter === 'receipt' ? 'selected' : '' ?>>Receipt</option>
                <option value="payment" <?= $typeFilter === 'payment' ? 'selected' : '' ?>>Payment</option>
                <option value="cash_deposit" <?= $typeFilter === 'cash_deposit' ? 'selected' : '' ?>>Deposit to Bank</option>
                <option value="cash_withdrawal" <?= $typeFilter === 'cash_withdrawal' ? 'selected' : '' ?>>Withdrawal from Bank</option>
                <option value="adjustment" <?= $typeFilter === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
            </select>
        </div>
        <div class="w-36"><input type="date" id="date_from" name="date_from" value="<?= sanitize($dateFrom) ?>" class="w-full px-3 py-2 border rounded" placeholder="From"></div>
        <div class="w-36"><input type="date" id="date_to" name="date_to" value="<?= sanitize($dateTo) ?>" class="w-full px-3 py-2 border rounded" placeholder="To"></div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-search"></i></button>
        <?php if ($accountFilter || $typeFilter || $dateFrom || $dateTo): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50"><tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cash Account</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($txns as $t): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($t['transaction_date'])) ?></td>
                <td class="px-4 py-3 text-sm text-gray-900"><?= sanitize($t['cash_name']) ?></td>
                <td class="px-4 py-3"><?= formatTxType($t['transaction_type']) ?></td>
                <td class="px-4 py-3 text-sm text-right font-mono font-medium <?= in_array($t['transaction_type'], ['receipt']) ? 'text-green-600' : 'text-red-600' ?>"><?= formatNumber($t['amount']) ?></td>
                <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate"><?= sanitize($t['description'] ?? '-') ?></td>
                <td class="px-4 py-3 text-center"><?= formatTxStatus($t['status']) ?></td>
                <td class="px-4 py-3 text-right">
                    <?php if ($t['journal_entry_id']): ?>
                    <a href="<?= url('modules/finance/journal_entries/edit.php?id=' . $t['journal_entry_id']) ?>" class="text-blue-600 hover:text-blue-900" title="View Journal"><i class="fas fa-journal-whills"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($txns)): ?>
            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-hand-holding-usd text-4xl mb-3 text-gray-300"></i><p>No cash transactions found</p></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
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
<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../../includes/footer.php'; ?>

