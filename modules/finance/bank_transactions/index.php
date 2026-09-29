<?php
$pageTitle = 'Bank Transactions';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';
require_once '../helpers/finance_helpers.php';
require_once '../helpers/banking_helpers.php';

if (!hasPermission('finance.bank.transaction')) { die('Access Denied'); }

$db = getDB();
$accountFilter = (int)($_GET['bank_account_id'] ?? 0);
$typeFilter = $_GET['transaction_type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = getPageNumber();
$perPage = 20;

$where = "WHERE 1=1";
$params = [];
if ($accountFilter) { $where .= " AND bt.bank_account_id = ?"; $params[] = $accountFilter; }
if ($typeFilter !== '') { $where .= " AND bt.transaction_type = ?"; $params[] = $typeFilter; }
if ($dateFrom) { $where .= " AND bt.transaction_date >= ?"; $params[] = $dateFrom; }
if ($dateTo) { $where .= " AND bt.transaction_date <= ?"; $params[] = $dateTo; }

$countStmt = $db->prepare("SELECT COUNT(*) FROM bank_transactions bt $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT bt.*, ba.account_name, ba.bank_name, ba.account_number
    FROM bank_transactions bt
    JOIN bank_accounts ba ON bt.bank_account_id = ba.id
    $where ORDER BY bt.transaction_date DESC, bt.id DESC LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$txns = $stmt->fetchAll();

$bankAccounts = $db->query("SELECT id, account_name, bank_name, account_number FROM bank_accounts WHERE status = 'active' ORDER BY account_name")->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Bank Transactions</h1>
        <p class="text-gray-600">View and record bank deposits, withdrawals, and adjustments</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New Transaction</a>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="w-56">
            <label class="block text-sm font-medium text-gray-700 mb-1">Bank Account</label>
            <select name="bank_account_id" class="w-full px-3 py-2 border rounded">
                <option value="">All Accounts</option>
                <?php foreach ($bankAccounts as $ba): ?>
                <option value="<?= $ba['id'] ?>" <?= $accountFilter === (int)$ba['id'] ? 'selected' : '' ?>><?= sanitize($ba['account_name'] . ' - ' . $ba['bank_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-36">
            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <select name="transaction_type" class="w-full px-3 py-2 border rounded">
                <option value="">All Types</option>
                <option value="deposit" <?= $typeFilter === 'deposit' ? 'selected' : '' ?>>Deposit</option>
                <option value="withdrawal" <?= $typeFilter === 'withdrawal' ? 'selected' : '' ?>>Withdrawal</option>
                <option value="interest" <?= $typeFilter === 'interest' ? 'selected' : '' ?>>Interest</option>
                <option value="bank_charges" <?= $typeFilter === 'bank_charges' ? 'selected' : '' ?>>Bank Charges</option>
                <option value="adjustment" <?= $typeFilter === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
            </select>
        </div>
        <div class="w-40"><label class="block text-sm font-medium text-gray-700 mb-1">From</label><input type="date" id="date_from" name="date_from" value="<?= sanitize($dateFrom) ?>" class="w-full px-3 py-2 border rounded"></div>
        <div class="w-40"><label class="block text-sm font-medium text-gray-700 mb-1">To</label><input type="date" id="date_to" name="date_to" value="<?= sanitize($dateTo) ?>" class="w-full px-3 py-2 border rounded"></div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-search"></i></button>
        <?php if ($accountFilter || $typeFilter !== '' || $dateFrom || $dateTo): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bank Account</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ref No</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount (AED)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($txns as $t): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($t['transaction_date'])) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-900"><?= sanitize($t['account_name']) ?><br><span class="text-xs text-gray-500"><?= sanitize($t['bank_name']) ?></span></td>
                    <td class="px-4 py-3"><?= formatTxType($t['transaction_type']) ?></td>
                    <td class="px-4 py-3 text-sm font-mono text-gray-600"><?= sanitize($t['reference_number'] ?? '-') ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono font-medium <?= in_array($t['transaction_type'], ['deposit','interest']) ? 'text-green-600' : 'text-red-600' ?>">
                        <?= in_array($t['transaction_type'], ['deposit','interest']) ? '+' : '-' ?><?= formatNumber($t['amount']) ?>
                    </td>
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
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-exchange-alt text-4xl mb-3 text-gray-300"></i><p>No bank transactions found</p></td></tr>
                <?php endif; ?>
            </tbody>
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

<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../../includes/footer.php'; ?>

