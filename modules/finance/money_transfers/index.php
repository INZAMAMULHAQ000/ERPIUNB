<?php
$pageTitle = 'Money Transfers';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';
require_once '../helpers/finance_helpers.php';
require_once '../helpers/banking_helpers.php';

if (!hasPermission('finance.transfer.create')) { die('Access Denied'); }

$db = getDB();
$page = getPageNumber();
$perPage = 20;

$where = "WHERE 1=1"; $params = [];
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

if ($dateFrom) { $where .= " AND mt.transfer_date >= ?"; $params[] = $dateFrom; }
if ($dateTo) { $where .= " AND mt.transfer_date <= ?"; $params[] = $dateTo; }

$countStmt = $db->prepare("SELECT COUNT(*) FROM money_transfers mt $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT mt.*,
           COALESCE(fba.account_name, fca.cash_name) AS from_name,
           COALESCE(fba.bank_name, '') AS from_bank,
           COALESCE(tba.account_name, tca.cash_name) AS to_name,
           COALESCE(tba.bank_name, '') AS to_bank
    FROM money_transfers mt
    LEFT JOIN bank_accounts fba ON mt.from_account_type = 'bank' AND mt.from_account_id = fba.id
    LEFT JOIN bank_accounts tba ON mt.to_account_type = 'bank' AND mt.to_account_id = tba.id
    LEFT JOIN cash_accounts fca ON mt.from_account_type = 'cash' AND mt.from_account_id = fca.id
    LEFT JOIN cash_accounts tca ON mt.to_account_type = 'cash' AND mt.to_account_id = tca.id
    $where ORDER BY mt.transfer_date DESC, mt.id DESC LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$transfers = $stmt->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Money Transfers</h1>
        <p class="text-gray-600">Transfer money between bank and cash accounts</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-plus mr-2"></i>New Transfer</a>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="w-36"><input type="date" id="date_from" name="date_from" value="<?= sanitize($dateFrom) ?>" class="w-full px-3 py-2 border rounded" placeholder="From"></div>
        <div class="w-36"><input type="date" id="date_to" name="date_to" value="<?= sanitize($dateTo) ?>" class="w-full px-3 py-2 border rounded" placeholder="To"></div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-search"></i></button>
        <?php if ($dateFrom || $dateTo): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50"><tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">From</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">To</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount (AED)</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($transfers as $t): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($t['transfer_date'])) ?></td>
                <td class="px-4 py-3 text-sm text-gray-900">
                    <?= sanitize($t['from_name']) ?>
                    <?php if ($t['from_bank']): ?><br><span class="text-xs text-gray-500"><?= sanitize($t['from_bank']) ?></span><?php endif; ?>
                    <span class="text-xs uppercase <?= $t['from_account_type'] === 'bank' ? 'text-blue-500' : 'text-green-500' ?>">(<?= $t['from_account_type'] ?>)</span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-900">
                    <?= sanitize($t['to_name']) ?>
                    <?php if ($t['to_bank']): ?><br><span class="text-xs text-gray-500"><?= sanitize($t['to_bank']) ?></span><?php endif; ?>
                    <span class="text-xs uppercase <?= $t['to_account_type'] === 'bank' ? 'text-blue-500' : 'text-green-500' ?>">(<?= $t['to_account_type'] ?>)</span>
                </td>
                <td class="px-4 py-3 text-sm text-right font-mono font-medium text-gray-900"><?= formatNumber($t['amount']) ?></td>
                <td class="px-4 py-3 text-sm font-mono text-gray-600"><?= sanitize($t['reference_number'] ?? '-') ?></td>
                <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate"><?= sanitize($t['description'] ?? '-') ?></td>
                <td class="px-4 py-3 text-center"><?= formatTxStatus($t['status']) ?></td>
                <td class="px-4 py-3 text-center">
                    <?php if ($t['journal_entry_id']): ?>
                    <a href="<?= url('modules/finance/journal_entries/edit.php?id=' . $t['journal_entry_id']) ?>" class="text-blue-600 hover:text-blue-900" title="View Journal"><i class="fas fa-journal-whills"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($transfers)): ?>
            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-exchange-alt text-4xl mb-3 text-gray-300"></i><p>No money transfers found</p></td></tr>
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

