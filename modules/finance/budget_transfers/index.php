<?php
$pageTitle = 'Budget Transfers';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.view')) { die('Access Denied'); }

$db = getDB();

$budget_id = $_GET['budget_id'] ?? '';
$status    = $_GET['status'] ?? '';

$where = " WHERE 1=1 ";
$params = [];
if ($budget_id !== '') { $where .= " AND t.budget_id = ?"; $params[] = (int)$budget_id; }
if ($status !== '')    { $where .= " AND t.status = ?"; $params[] = $status; }

$page  = getPageNumber();
$perPage = 15;
$countStmt = $db->prepare("SELECT COUNT(*) FROM budget_transfers t $where");
$countStmt->execute($params);
$pagination = paginate($countStmt->fetchColumn(), $perPage, $page);

$base = "SELECT t.*, b.budget_code, b.budget_name, fy.year_name,
                fl.account_code AS from_acct_code, fl.account_name AS from_acct_name,
                fl.department_name AS from_dept_name, fl.project_name AS from_proj_name,
                tl.account_code AS to_acct_code, tl.account_name AS to_acct_name,
                tl.department_name AS to_dept_name, tl.project_name AS to_proj_name
         FROM budget_transfers t
         JOIN budgets b ON b.id = t.budget_id
         JOIN financial_years fy ON fy.id = b.financial_year_id
         LEFT JOIN (SELECT bl.id, acct.account_code, acct.account_name, d.department_name, p.project_name
                    FROM budget_lines bl
                    JOIN chart_of_accounts acct ON acct.id = bl.chart_of_accounts_id
                    LEFT JOIN departments d ON d.id = bl.department_id
                    LEFT JOIN projects p ON p.id = bl.project_id) fl ON fl.id = t.from_line_id
         LEFT JOIN (SELECT bl.id, acct.account_code, acct.account_name, d.department_name, p.project_name
                    FROM budget_lines bl
                    JOIN chart_of_accounts acct ON acct.id = bl.chart_of_accounts_id
                    LEFT JOIN departments d ON d.id = bl.department_id
                    LEFT JOIN projects p ON p.id = bl.project_id) tl ON tl.id = t.to_line_id
         $where";
$stmt = $db->prepare($base . " ORDER BY t.requested_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $pagination['offset']]));
$transfers = $stmt->fetchAll();

$budgets = $db->query("SELECT id, budget_code, budget_name FROM budgets WHERE status NOT IN ('closed') ORDER BY budget_code")->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Budget Transfers</h1>
        <p class="text-gray-600">Move budget between departments, projects or accounts</p>
    </div>
    <div class="flex gap-2">
        <a href="../budgets/index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Budgets</a>
        <?php if (hasPermission('budget.transfer')): ?>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New Transfer</a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="w-64">
            <label class="block text-sm font-medium text-gray-700 mb-1">Budget</label>
            <select name="budget_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Budgets</option>
                <?php foreach ($budgets as $b): ?>
                <option value="<?= (int)$b['id'] ?>" <?= $budget_id == $b['id'] ? 'selected' : '' ?>><?= sanitize($b['budget_code']) ?> — <?= sanitize($b['budget_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach (['pending','approved','rejected','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-search"></i></button>
        <?php if ($budget_id || $status): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<!-- List -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transfer No</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Budget</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">From</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">To</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($transfers as $t): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-mono text-gray-900"><?= sanitize($t['transfer_no']) ?></td>
                    <td class="px-4 py-3 text-sm">
                        <span class="font-mono text-gray-900"><?= sanitize($t['budget_code']) ?></span>
                        <div class="text-gray-600 text-xs"><?= sanitize($t['budget_name']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600 capitalize"><?= sanitize($t['transfer_type']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize(($t['from_dept_name'] ?? '—') . ' / ' . ($t['from_proj_name'] ?? '—') . ' / ' . ($t['from_acct_code'] ?? '—')) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize(($t['to_dept_name'] ?? '—') . ' / ' . ($t['to_proj_name'] ?? '—') . ' / ' . ($t['to_acct_code'] ?? '—')) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney((float)$t['amount']) ?></td>
                    <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded font-medium <?= transfer_status_badge($t['status']) ?>"><?= ucfirst($t['status']) ?></span></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="view.php?id=<?= (int)$t['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3" title="View"><i class="fas fa-eye"></i></a>
                        <?php if ($t['status'] === 'pending' && hasPermission('budget.approve')): ?>
                        <form method="POST" action="approve.php" class="inline" onsubmit="return confirm('Approve this transfer?');">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="transfer_id" value="<?= (int)$t['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="text-green-600 hover:text-green-900 mr-3" title="Approve"><i class="fas fa-check"></i></button>
                        </form>
                        <form method="POST" action="approve.php" class="inline reject-form" data-transfer-id="<?= (int)$t['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="transfer_id" value="<?= (int)$t['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="rejection_reason" class="reject-reason">
                            <button type="button" class="text-red-600 hover:text-red-900 mr-3 reject-btn" title="Reject"><i class="fas fa-times"></i></button>
                        </form>
                        <?php endif; ?>
                        <?php if ($t['status'] === 'pending' && hasPermission('budget.transfer')): ?>
                        <form method="POST" action="cancel.php" class="inline" onsubmit="return confirm('Cancel this transfer?');">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="transfer_id" value="<?= (int)$t['id'] ?>">
                            <button type="submit" class="text-gray-600 hover:text-gray-900" title="Cancel"><i class="fas fa-ban"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($transfers)): ?>
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-exchange-alt text-4xl mb-3 text-gray-300"></i>
                    <p>No transfers found</p>
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4"><?= paginationLinks($pagination, '?') ?></div>

<script>
// Reject requires a reason (matching approve.php validation)
document.querySelectorAll('.reject-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const form = this.closest('.reject-form');
        const reason = prompt('Enter rejection reason (required):');
        if (reason !== null && reason.trim() !== '') {
            form.querySelector('.reject-reason').value = reason.trim();
            form.submit();
        } else if (reason !== null) {
            alert('Rejection reason is required.');
        }
    });
});
</script>
<?php require_once '../../../includes/footer.php'; ?>