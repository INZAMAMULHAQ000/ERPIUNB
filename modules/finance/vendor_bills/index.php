<?php
$pageTitle = 'Vendor Bills';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';

if (!hasPermission('vendor_bills.view')) { die('Access Denied'); }

$db = getDB();

// Check AP account configuration
$apAccountId = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'finance_ap_account_id'")->fetchColumn();
$expenseAccountId = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'finance_expense_account_id'")->fetchColumn();

$search = $_GET['search'] ?? '';
$vendor_id = $_GET['vendor_id'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (vb.bill_number LIKE ? OR v.vendor_name LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($vendor_id) {
    $where .= " AND vb.vendor_id = ?";
    $params[] = (int)$vendor_id;
}
if ($status !== '') {
    $where .= " AND vb.status = ?";
    $params[] = $status;
}
if ($date_from) { $where .= " AND vb.bill_date >= ?"; $params[] = $date_from; }
if ($date_to) { $where .= " AND vb.bill_date <= ?"; $params[] = $date_to; }

$countStmt = $db->prepare("SELECT COUNT(*) FROM vendor_bills vb JOIN vendors v ON vb.vendor_id = v.id $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT vb.*, v.vendor_name, v.vendor_code
    FROM vendor_bills vb JOIN vendors v ON vb.vendor_id = v.id
    $where ORDER BY vb.created_at DESC LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$bills = $stmt->fetchAll();

$vendors = $db->query("SELECT id, vendor_code, vendor_name FROM vendors WHERE status = 'active' ORDER BY vendor_name")->fetchAll();

$stats['outstanding'] = $db->query("SELECT COALESCE(SUM(balance_amount),0) FROM vendor_bills WHERE status IN ('approved','partially_paid','overdue')")->fetchColumn();
$stats['overdue'] = $db->query("SELECT COALESCE(SUM(balance_amount),0) FROM vendor_bills WHERE status='overdue' OR (status IN ('approved','partially_paid') AND due_date < CURDATE())")->fetchColumn();
$stats['draft'] = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM vendor_bills WHERE status='draft'")->fetchColumn();
$stats['paid'] = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM vendor_bills WHERE status='paid'")->fetchColumn();

$statusBadges = [
    'draft' => 'bg-gray-100 text-gray-800',
    'approved' => 'bg-blue-100 text-blue-800',
    'partially_paid' => 'bg-yellow-100 text-yellow-800',
    'paid' => 'bg-green-100 text-green-800',
    'overdue' => 'bg-red-100 text-red-800',
    'cancelled' => 'bg-gray-200 text-gray-600',
];
?>
<?php if (!$apAccountId || !$expenseAccountId): ?>
<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 px-4 py-3 rounded mb-6 flex items-start gap-3">
    <i class="fas fa-exclamation-triangle mt-1"></i>
    <div>
        <p class="font-medium">Finance account mapping not fully configured</p>
        <p class="text-sm mt-1">The AP account<?= !$expenseAccountId ? ' or default expense account' : '' ?> is not set. Vendor bills cannot be approved until these are configured in
        <a href="<?= url('modules/finance/settings/index.php') ?>" class="underline font-medium hover:text-yellow-900">Finance Settings</a>.</p>
    </div>
</div>
<?php endif; ?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Vendor Bills</h1>
        <p class="text-gray-600">Record and manage accounts payable</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
        <?php if (hasPermission('vendor_bills.create')): ?>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New Bill</a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div><p class="text-sm text-gray-500">Outstanding</p><p class="text-xl font-bold text-red-600"><?= formatMoney((float)$stats['outstanding']) ?></p></div>
            <div class="bg-red-100 p-3 rounded-full"><i class="fas fa-credit-card text-red-600"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div><p class="text-sm text-gray-500">Overdue</p><p class="text-xl font-bold text-red-700"><?= formatMoney((float)$stats['overdue']) ?></p></div>
            <div class="bg-red-200 p-3 rounded-full"><i class="fas fa-exclamation-triangle text-red-700"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div><p class="text-sm text-gray-500">Draft Bills</p><p class="text-xl font-bold text-gray-800"><?= formatMoney((float)$stats['draft']) ?></p></div>
            <div class="bg-gray-100 p-3 rounded-full"><i class="fas fa-file text-gray-600"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div><p class="text-sm text-gray-500">Paid Total</p><p class="text-xl font-bold text-green-600"><?= formatMoney((float)$stats['paid']) ?></p></div>
            <div class="bg-green-100 p-3 rounded-full"><i class="fas fa-check-circle text-green-600"></i></div>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Bill # or Vendor..." class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-48">
            <label class="block text-sm font-medium text-gray-700 mb-1">Vendor</label>
            <select name="vendor_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Vendors</option>
                <?php foreach ($vendors as $v): ?>
                <option value="<?= $v['id'] ?>" <?= $vendor_id == $v['id'] ? 'selected' : '' ?>><?= sanitize($v['vendor_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-36">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="partially_paid" <?= $status === 'partially_paid' ? 'selected' : '' ?>>Partially Paid</option>
                <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="overdue" <?= $status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input type="date" name="date_from" value="<?= sanitize($date_from) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input type="date" name="date_to" value="<?= sanitize($date_to) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-search"></i></button>
        <?php if ($search || $vendor_id || $status !== '' || $date_from || $date_to): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bill #</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendor</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendor Inv #</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paid</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($bills as $b): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-mono font-medium text-gray-900"><?= sanitize($b['bill_number']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($b['vendor_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-500 font-mono"><?= !empty($b['vendor_invoice_number']) ? sanitize($b['vendor_invoice_number']) : '<span class="text-gray-300">--</span>' ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($b['bill_date'])) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($b['due_date'])) ?></td>
                    <td class="px-4 py-3 text-center whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded font-medium <?= $statusBadges[$b['status']] ?? 'bg-gray-100 text-gray-800' ?>">
                            <?= ucwords(str_replace('_', ' ', $b['status'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney((float)$b['total_amount']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney((float)$b['paid_amount']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono <?= (float)$b['balance_amount'] > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= formatMoney((float)$b['balance_amount']) ?></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="view.php?id=<?= $b['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-2" title="View"><i class="fas fa-eye"></i></a>
                        <?php if ($b['status'] === 'draft' && hasPermission('vendor_bills.edit')): ?>
                        <a href="edit.php?id=<?= $b['id'] ?>" class="text-yellow-600 hover:text-yellow-900 mr-2" title="Edit"><i class="fas fa-pencil"></i></a>
                        <?php endif; ?>
                        <?php if ($b['status'] === 'draft' && hasPermission('vendor_bills.approve')): ?>
                        <form method="POST" action="approve.php" style="display:inline" onsubmit="return confirm('Approve bill <?= sanitize(addslashes($b['bill_number'])) ?>? This will post to the general ledger.')">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="bill_id" value="<?= $b['id'] ?>">
                            <button type="submit" class="text-green-600 hover:text-green-900 mr-2" title="Approve"><i class="fas fa-check"></i></button>
                        </form>
                        <?php endif; ?>
                        <?php if (!in_array($b['status'], ['paid','cancelled']) && hasPermission('vendor_bills.cancel')): ?>
                        <form method="POST" action="cancel.php" style="display:inline" onsubmit="return confirm('Cancel bill <?= sanitize(addslashes($b['bill_number'])) ?>?')">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="bill_id" value="<?= $b['id'] ?>">
                            <button type="submit" class="text-red-600 hover:text-red-900" title="Cancel"><i class="fas fa-ban"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($bills)): ?>
                <tr><td colspan="10" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-file-invoice text-4xl mb-3 text-gray-300"></i>
                    <p>No vendor bills found</p>
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../../includes/footer.php'; ?>
