<?php
$pageTitle = 'Bank Reconciliation';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';
require_once '../helpers/finance_helpers.php';
require_once '../helpers/banking_helpers.php';

if (!hasPermission('finance.reconciliation.manage')) { die('Access Denied'); }

$db = getDB();
$page = getPageNumber();
$perPage = 20;
$bankFilter = (int)($_GET['bank_account_id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';

$where = "WHERE 1=1"; $params = [];
if ($bankFilter) { $where .= " AND br.bank_account_id = ?"; $params[] = $bankFilter; }
if ($statusFilter) { $where .= " AND br.status = ?"; $params[] = $statusFilter; }

$countStmt = $db->prepare("SELECT COUNT(*) FROM bank_reconciliation br $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT br.*, ba.account_name, ba.bank_name, u.name AS created_by_name
    FROM bank_reconciliation br
    JOIN bank_accounts ba ON br.bank_account_id = ba.id
    JOIN users u ON br.created_by = u.id
    $where ORDER BY br.created_at DESC LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$reconciliations = $stmt->fetchAll();

$bankAccounts = $db->query("SELECT id, account_name, bank_name FROM bank_accounts WHERE status = 'active' ORDER BY account_name")->fetchAll();

// Check for unreconciled accounts
$unreconciledCount = $db->query("SELECT COUNT(*) FROM bank_accounts ba WHERE ba.status = 'active' AND NOT EXISTS (SELECT 1 FROM bank_reconciliation br WHERE br.bank_account_id = ba.id AND br.status = 'completed')")->fetchColumn();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Bank Reconciliation</h1>
        <p class="text-gray-600">Reconcile bank statements with ledger balances</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-plus mr-2"></i>New Reconciliation</a>
    </div>
</div>

<?php if ((int)$unreconciledCount > 0): ?>
<div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
    <div class="flex items-start">
        <i class="fas fa-exclamation-triangle text-yellow-600 mt-0.5 mr-3"></i>
        <div>
            <p class="text-sm font-medium text-yellow-800"><?= $unreconciledCount ?> bank account(s) pending reconciliation</p>
            <p class="text-xs text-yellow-700 mt-1">Start a new reconciliation to compare statement balances with ledger entries.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="w-56">
            <select name="bank_account_id" class="w-full px-3 py-2 border rounded">
                <option value="">All Accounts</option>
                <?php foreach ($bankAccounts as $ba): ?>
                <option value="<?= $ba['id'] ?>" <?= $bankFilter === (int)$ba['id'] ? 'selected' : '' ?>><?= sanitize($ba['account_name'] . ' - ' . $ba['bank_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-36">
            <select name="status" class="w-full px-3 py-2 border rounded">
                <option value="">All Status</option>
                <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-search"></i></button>
        <?php if ($bankFilter || $statusFilter): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50"><tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bank Account</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Statement Balance</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Book Balance</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Difference</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created By</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($reconciliations as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($r['statement_date'])) ?></td>
                <td class="px-4 py-3 text-sm text-gray-900"><?= sanitize($r['account_name']) ?><br><span class="text-xs text-gray-500"><?= sanitize($r['bank_name']) ?></span></td>
                <td class="px-4 py-3 text-sm text-right font-mono"><?= formatNumber($r['statement_balance']) ?></td>
                <td class="px-4 py-3 text-sm text-right font-mono"><?= formatNumber($r['book_balance']) ?></td>
                <td class="px-4 py-3 text-sm text-right font-mono font-medium <?= (float)$r['difference'] == 0 ? 'text-green-600' : 'text-red-600' ?>"><?= formatNumber($r['difference']) ?></td>
                <td class="px-4 py-3 text-center"><?= formatReconStatus($r['status']) ?></td>
                <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($r['created_by_name']) ?></td>
                <td class="px-4 py-3 text-center">
                    <a href="view.php?id=<?= $r['id'] ?>" class="text-blue-600 hover:text-blue-900" title="View"><i class="fas fa-eye"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($reconciliations)): ?>
            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-balance-scale text-4xl mb-3 text-gray-300"></i><p>No reconciliations found</p></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../../includes/footer.php'; ?>

