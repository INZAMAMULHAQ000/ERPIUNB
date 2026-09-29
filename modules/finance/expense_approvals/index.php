<?php
$pageTitle = 'Expense Approvals';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('expense_approvals.view')) { die('Access Denied'); }

$db = getDB();

// Resolve the acting user's manager scope: managers only see claims from employees who report to them.
// Users without an employee record (e.g. finance/admins) see all pending claims, as before.
$managerScope = '';
$empStmt = $db->prepare("SELECT id FROM employees WHERE user_id = ? AND status = 'active' LIMIT 1");
$empStmt->execute([$_SESSION['user_id']]);
$actingEmployee = $empStmt->fetch();
if ($actingEmployee) {
    $managerScope = " AND e.reporting_manager_id = " . (int)$actingEmployee['id'];
}

// Company expenses pending approval
$pendingExpenses = $db->query("
    SELECT ce.*, ec.name AS category_name, u.name AS created_by_name
    FROM company_expenses ce
    LEFT JOIN expense_categories ec ON ce.category_id = ec.id
    LEFT JOIN users u ON ce.created_by = u.id
    WHERE ce.status = 'pending_approval'
    ORDER BY ce.expense_date ASC
")->fetchAll();

// Employee claims pending manager approval
$pendingManagerClaims = $db->query("
    SELECT ec.*, e.first_name, e.last_name, e.employee_code, cat.name AS category_name, u.name AS created_by_name
    FROM employee_expense_claims ec
    LEFT JOIN employees e ON ec.employee_id = e.id
    LEFT JOIN expense_categories cat ON ec.category_id = cat.id
    LEFT JOIN users u ON ec.created_by = u.id
    WHERE ec.status = 'pending_manager' $managerScope
    ORDER BY ec.expense_date ASC
")->fetchAll();

// Employee claims pending finance approval
$pendingFinanceClaims = $db->query("
    SELECT ec.*, e.first_name, e.last_name, e.employee_code, cat.name AS category_name, u.name AS created_by_name
    FROM employee_expense_claims ec
    LEFT JOIN employees e ON ec.employee_id = e.id
    LEFT JOIN expense_categories cat ON ec.category_id = cat.id
    LEFT JOIN users u ON ec.created_by = u.id
    WHERE ec.status = 'pending_finance'
    ORDER BY ec.expense_date ASC
")->fetchAll();

$totalPending = count($pendingExpenses) + count($pendingManagerClaims) + count($pendingFinanceClaims);

$statusBadges = [
    'draft' => 'bg-gray-100 text-gray-800',
    'pending_approval' => 'bg-yellow-100 text-yellow-800',
    'approved' => 'bg-blue-100 text-blue-800',
    'rejected' => 'bg-red-100 text-red-800',
    'paid' => 'bg-green-100 text-green-800',
    'pending_manager' => 'bg-yellow-100 text-yellow-800',
    'pending_finance' => 'bg-orange-100 text-orange-800',
];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Expense Approvals</h1>
        <p class="text-gray-600">Approve or reject expense claims and company expenses</p>
    </div>
    <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
</div>

<div class="bg-blue-50 border-l-4 border-blue-500 text-blue-800 px-4 py-3 rounded mb-6">
    <p><span class="font-bold"><?= $totalPending ?></span> item(s) pending your approval.</p>
</div>

<!-- Company Expenses Pending Approval -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-4 py-3 border-b flex justify-between items-center">
        <h3 class="font-semibold text-gray-800">Company Expenses <span class="text-sm font-normal text-gray-500">(Pending Approval)</span></h3>
        <span class="px-2 py-1 text-xs rounded font-medium bg-yellow-100 text-yellow-800"><?= count($pendingExpenses) ?> pending</span>
    </div>
    <?php if (empty($pendingExpenses)): ?>
    <div class="p-6 text-center text-gray-500"><i class="fas fa-check-circle text-green-400 text-3xl mb-2"></i><p>No company expenses pending approval</p></div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Expense #</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Submitted By</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($pendingExpenses as $e): ?>
                <tr>
                    <td class="px-4 py-3 text-sm font-mono font-medium"><?= sanitize($e['expense_number']) ?></td>
                    <td class="px-4 py-3 text-sm"><?= sanitize($e['category_name']) ?></td>
                    <td class="px-4 py-3 text-sm"><?= sanitize($e['created_by_name'] ?? '--') ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= date('M d, Y', strtotime($e['expense_date'])) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono"><?= formatMoney((float)$e['total_amount']) ?></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="../company_expenses/view.php?id=<?= $e['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-2" title="View"><i class="fas fa-eye"></i></a>
                        <?php if (hasPermission('company_expenses.approve')): ?>
                        <form method="POST" action="../company_expenses/approve.php" style="display:inline" onsubmit="return confirm('Approve and post to GL?')">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="expense_id" value="<?= $e['id'] ?>">
                            <button type="submit" class="text-green-600 hover:text-green-900 mr-2" title="Approve"><i class="fas fa-check"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Claims Pending Manager Approval -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-4 py-3 border-b flex justify-between items-center">
        <h3 class="font-semibold text-gray-800">Employee Claims <span class="text-sm font-normal text-gray-500">(Pending Manager)</span></h3>
        <span class="px-2 py-1 text-xs rounded font-medium bg-yellow-100 text-yellow-800"><?= count($pendingManagerClaims) ?> pending</span>
    </div>
    <?php if (empty($pendingManagerClaims)): ?>
    <div class="p-6 text-center text-gray-500"><i class="fas fa-check-circle text-green-400 text-3xl mb-2"></i><p>No claims pending manager approval</p></div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Claim #</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($pendingManagerClaims as $c): ?>
                <tr>
                    <td class="px-4 py-3 text-sm font-mono font-medium"><?= sanitize($c['claim_number']) ?></td>
                    <td class="px-4 py-3 text-sm"><?= sanitize($c['first_name'] . ' ' . $c['last_name']) ?></td>
                    <td class="px-4 py-3 text-sm"><?= sanitize($c['category_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= date('M d, Y', strtotime($c['expense_date'])) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono"><?= formatMoney((float)$c['total_amount']) ?></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="../employee_claims/view.php?id=<?= $c['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-2" title="View"><i class="fas fa-eye"></i></a>
                        <?php if (hasPermission('employee_claims.approve')): ?>
                        <form method="POST" action="../employee_claims/approve_manager.php" style="display:inline" onsubmit="return confirm('Manager-approve this claim?')">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="claim_id" value="<?= $c['id'] ?>">
                            <button type="submit" class="text-green-600 hover:text-green-900 mr-2" title="Approve"><i class="fas fa-check"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Claims Pending Finance Approval -->
<div class="bg-white rounded-lg shadow">
    <div class="px-4 py-3 border-b flex justify-between items-center">
        <h3 class="font-semibold text-gray-800">Employee Claims <span class="text-sm font-normal text-gray-500">(Pending Finance Approval)</span></h3>
        <span class="px-2 py-1 text-xs rounded font-medium bg-orange-100 text-orange-800"><?= count($pendingFinanceClaims) ?> pending</span>
    </div>
    <?php if (empty($pendingFinanceClaims)): ?>
    <div class="p-6 text-center text-gray-500"><i class="fas fa-check-circle text-green-400 text-3xl mb-2"></i><p>No claims pending finance approval</p></div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Claim #</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($pendingFinanceClaims as $c): ?>
                <tr>
                    <td class="px-4 py-3 text-sm font-mono font-medium"><?= sanitize($c['claim_number']) ?></td>
                    <td class="px-4 py-3 text-sm"><?= sanitize($c['first_name'] . ' ' . $c['last_name']) ?></td>
                    <td class="px-4 py-3 text-sm"><?= sanitize($c['category_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= date('M d, Y', strtotime($c['expense_date'])) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono"><?= formatMoney((float)$c['total_amount']) ?></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="../employee_claims/view.php?id=<?= $c['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-2" title="View"><i class="fas fa-eye"></i></a>
                        <?php if (hasPermission('employee_claims.approve')): ?>
                        <form method="POST" action="../employee_claims/approve_finance.php" style="display:inline" onsubmit="return confirm('Finance-approve and post to GL?')">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="claim_id" value="<?= $c['id'] ?>">
                            <button type="submit" class="text-green-600 hover:text-green-900 mr-2" title="Approve"><i class="fas fa-check"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../../includes/footer.php'; ?>
