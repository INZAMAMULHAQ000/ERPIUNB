<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$pageTitle = 'Payroll Reconciliation';
require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../../../helpers/redirect.php';
require_once __DIR__ . '/../../../../helpers/pagination.php';
require_once __DIR__ . '/../../../../helpers/finance_helpers.php';

require_once __DIR__ . '/../../../../helpers/rbac.php';

if (!hasPermission('payrollfinance.view')) { die('Access Denied'); }

$db = getDB();
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = getPageNumber();
$perPage = 20;

$where = "WHERE 1=1";
$params = [];
if ($search) {
    $where .= " AND (pr.id LIKE ? OR pr.period_name LIKE ?)";
    $s = "%$search%";
    $params[] = $s;
    $params[] = $s;
}
if ($statusFilter !== '') {
    $where .= " AND pr.status = ?";
    $params[] = $statusFilter;
}
if ($dateFrom) {
    $where .= " AND pr.pay_date >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where .= " AND pr.pay_date <= ?";
    $params[] = $dateTo;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM payroll_runs pr $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT 
        pr.id,
        pr.period_name,
        pr.net_salary AS payroll_calculated,
        COALESCE(SUM(pfp.total_net), 0) AS finance_posted,
        COALESCE(SUM(pb.total_amount), 0) AS bank_paid
    FROM payroll_runs pr
    LEFT JOIN payroll_finance_postings pfp ON pr.id = pfp.payroll_run_id
    LEFT JOIN payroll_payment_batches pb ON pr.id = pb.payroll_run_id AND pb.status = 'Paid'
    $where
    GROUP BY pr.id, pr.period_name, pr.net_salary
    ORDER BY pr.pay_date DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$runs = $stmt->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Payroll Reconciliation</h1>
        <p class="text-gray-600">Compare payroll totals with finance posted and bank paid amounts</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
        <?php if (hasPermission('payrollfinance.post')): ?>
        <a href="../postings/" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-plus mr-2"></i>Post Payroll to Finance</a>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Payroll Run ID, Period" class="w-full px-3 py-2 border rounded">
        </div>
        <div class="w-36">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded">
                <option value="">All</option>
                <?php
                $statuses = ['Draft', 'Approved', 'Posted', 'Paid'];
                foreach ($statuses as $status): ?>
                <option value="<?= $status ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= $status ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-36">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input type="date" name="date_from" value="<?= sanitize($dateFrom) ?>" class="w-full px-3 py-2 border rounded">
        </div>
        <div class="w-36">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input type="date" name="date_to" value="<?= sanitize($dateTo) ?>" class="w-full px-3 py-2 border rounded">
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-search"></i></button>
        <?php if ($search || $statusFilter !== '' || $dateFrom || $dateTo): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payroll Run ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Payroll Calculated Total</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Finance Posted Total</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Bank Paid Total</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Difference</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($runs as $r): ?>
                <?php
                $payrollCalculated = (float)$r['payroll_calculated'];
                $financePosted = (float)$r['finance_posted'];
                $bankPaid = (float)$r['bank_paid'];
                $difference = $payrollCalculated - $bankPaid;
                $reconciled = ($payrollCalculated == $financePosted && $financePosted == $bankPaid) ? 'Reconciled' : 'Not Reconciled';
                $reconciledClass = $reconciled === 'Reconciled' ? 'text-green-600' : 'text-red-600';
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= $r['id'] ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($r['period_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono><?= formatNumber($payrollCalculated) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono><?= formatNumber($financePosted) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono><?= formatNumber($bankPaid) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono <?= $difference == 0 ? 'text-green-600' : 'text-red-600' ?>><?= formatNumber($difference) ?></td>
                    <td class="px-4 py-3 text-center"><span class="px-2 py-1 text-xs rounded font-medium <?= $reconciledClass ?>><?= $reconciled ?></span></td>
                    <td class="px-4 py-3 text-right">
                        <?php if (hasPermission('payrollfinance.view')): ?>
                        <a href="view.php?id=<?= $r['id'] ?>" class="text-blue-600 hover:text-blue-900" title="View"><i class="fas fa-eye"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($runs)): ?>
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-balance-scale text-4xl mb-3 text-gray-300"></i><p>No payroll runs found</p></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= paginationLinks($pagination, '?') ?>
<?php require_once __DIR__ . '/../../../../includes/footer.php'; ?>
