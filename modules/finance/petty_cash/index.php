<?php
$pageTitle = 'Petty Cash';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';
require_once '../helpers/finance_helpers.php';
require_once '../helpers/banking_helpers.php';

if (!hasPermission('finance.cash.transaction')) { die('Access Denied'); }

$db = getDB();
$page = getPageNumber();
$perPage = 20;

// Get all petty cash funds with latest transaction info
$funds = $db->query("
    SELECT pcf.*, ca.cash_name, ca.location,
           CONCAT(e.first_name, ' ', e.last_name) AS custodian_name,
           (SELECT COUNT(*) FROM petty_cash_transactions WHERE petty_cash_fund_id = pcf.id) AS txn_count,
           (SELECT MAX(created_at) FROM petty_cash_transactions WHERE petty_cash_fund_id = pcf.id) AS last_activity
    FROM petty_cash_funds pcf
    JOIN cash_accounts ca ON pcf.cash_account_id = ca.id
    LEFT JOIN employees e ON pcf.custodian_employee_id = e.id
    ORDER BY pcf.status ASC, pcf.fund_name ASC
")->fetchAll();

// Get recent transactions across all funds
$recentTxns = $db->query("
    SELECT pct.*, pcf.fund_name, ca.cash_name,
           u.name AS created_by_name
    FROM petty_cash_transactions pct
    JOIN petty_cash_funds pcf ON pct.petty_cash_fund_id = pcf.id
    JOIN cash_accounts ca ON pcf.cash_account_id = ca.id
    JOIN users u ON pct.created_by = u.id
    ORDER BY pct.created_at DESC
    LIMIT 20
")->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Petty Cash</h1>
        <p class="text-gray-600">Manage petty cash funds, expenses, and top-ups</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
        <a href="issue.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-plus mr-2"></i>Issue Petty Cash</a>
    </div>
</div>

<!-- Fund Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
    <?php foreach ($funds as $f): ?>
    <div class="bg-white rounded-lg shadow p-5 border-l-4 <?= $f['status'] === 'open' ? 'border-green-500' : 'border-gray-400' ?>">
        <div class="flex justify-between items-start">
            <div>
                <h3 class="font-semibold text-gray-900"><?= sanitize($f['fund_name']) ?></h3>
                <p class="text-xs text-gray-500"><?= sanitize($f['cash_name']) ?></p>
            </div>
            <span class="px-2 py-0.5 text-xs rounded <?= $f['status'] === 'open' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>"><?= ucfirst($f['status']) ?></span>
        </div>
        <?php if ($f['custodian_name']): ?>
        <p class="text-sm text-gray-600 mt-2"><i class="fas fa-user mr-1"></i><?= sanitize($f['custodian_name']) ?></p>
        <?php endif; ?>
        <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
            <div><span class="text-gray-500">Imprest:</span> <span class="font-mono font-medium">AED <?= formatNumber($f['imprest_amount']) ?></span></div>
            <div><span class="text-gray-500">Balance:</span> <span class="font-mono font-medium <?= (float)$f['current_balance'] > 0 ? 'text-green-600' : 'text-red-600' ?>">AED <?= formatNumber($f['current_balance']) ?></span></div>
        </div>
        <div class="mt-3 text-xs text-gray-500">
            <?= (int)$f['txn_count'] ?> transactions
            <?php if ($f['last_activity']): ?> &middot; Last: <?= date('M d', strtotime($f['last_activity'])) ?><?php endif; ?>
        </div>
        <?php if ($f['status'] === 'open'): ?>
        <div class="mt-3 flex gap-2">
            <a href="expense.php?fund_id=<?= $f['id'] ?>" class="text-xs bg-orange-100 text-orange-700 px-2 py-1 rounded hover:bg-orange-200"><i class="fas fa-receipt mr-1"></i>Expense</a>
            <a href="topup.php?fund_id=<?= $f['id'] ?>" class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded hover:bg-blue-200"><i class="fas fa-plus-circle mr-1"></i>Top-up</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (empty($funds)): ?>
    <div class="col-span-full text-center py-12 text-gray-500">
        <i class="fas fa-coins text-5xl mb-3 text-gray-300"></i>
        <p>No petty cash funds yet</p>
        <a href="issue.php" class="inline-block mt-3 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm"><i class="fas fa-plus mr-1"></i>Issue First Fund</a>
    </div>
    <?php endif; ?>
</div>

<!-- Recent Transactions -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold">Recent Petty Cash Activity</h3>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50"><tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fund</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created By</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Voucher</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($recentTxns as $t): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
                <td class="px-4 py-3 text-sm text-gray-900"><?= sanitize($t['fund_name']) ?></td>
                <td class="px-4 py-3">
                    <?php
                        $typeLabels = ['issue' => ['Issue', 'green'], 'expense' => ['Expense', 'orange'], 'topup' => ['Top-up', 'blue'], 'close' => ['Close', 'red']];
                        $tl = $typeLabels[$t['transaction_type']] ?? [$t['transaction_type'], 'gray'];
                    ?>
                    <span class="px-2 py-0.5 text-xs rounded bg-<?= $tl[1] ?>-100 text-<?= $tl[1] ?>-800"><?= $tl[0] ?></span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($t['expense_category'] ?? '-') ?></td>
                <td class="px-4 py-3 text-sm text-right font-mono font-medium"><?= formatNumber($t['amount']) ?></td>
                <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate"><?= sanitize($t['description'] ?? '-') ?></td>
                <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($t['created_by_name']) ?></td>
                <td class="px-4 py-3 text-center">
                    <?php if ($t['journal_entry_id']): ?>
                    <a href="<?= url('modules/finance/journal_entries/edit.php?id=' . $t['journal_entry_id']) ?>" class="text-blue-600 hover:text-blue-900 text-sm" title="View Journal"><i class="fas fa-journal-whills"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentTxns)): ?>
            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-coins text-4xl mb-3 text-gray-300"></i><p>No petty cash activity yet</p></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once '../../../includes/footer.php'; ?>

