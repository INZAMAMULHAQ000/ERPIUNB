<?php
$pageTitle = 'Vendor Payments';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';

if (!hasPermission('vendor_payments.view')) { die('Access Denied'); }

$db = getDB();

$search = $_GET['search'] ?? '';
$vendor_id = $_GET['vendor_id'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (vp.payment_number LIKE ? OR v.vendor_name LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($vendor_id) {
    $where .= " AND vp.vendor_id = ?";
    $params[] = (int)$vendor_id;
}
if ($payment_method !== '') {
    $where .= " AND vp.payment_method = ?";
    $params[] = $payment_method;
}
if ($date_from) { $where .= " AND vp.payment_date >= ?"; $params[] = $date_from; }
if ($date_to) { $where .= " AND vp.payment_date <= ?"; $params[] = $date_to; }

$countStmt = $db->prepare("SELECT COUNT(*) FROM vendor_payments vp JOIN vendors v ON vp.vendor_id = v.id $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT vp.*, v.vendor_name, v.vendor_code,
        je.voucher_no AS je_voucher_no
    FROM vendor_payments vp
    JOIN vendors v ON vp.vendor_id = v.id
    LEFT JOIN journal_entries je ON vp.journal_entry_id = je.id
    $where ORDER BY vp.created_at DESC LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$payments = $stmt->fetchAll();

$vendors = $db->query("SELECT id, vendor_code, vendor_name FROM vendors WHERE status = 'active' ORDER BY vendor_name")->fetchAll();

$stats['total'] = $db->query("SELECT COALESCE(SUM(amount),0) FROM vendor_payments")->fetchColumn();
$stats['this_month'] = $db->query("SELECT COALESCE(SUM(amount),0) FROM vendor_payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())")->fetchColumn();
$stats['count'] = $db->query("SELECT COUNT(*) FROM vendor_payments")->fetchColumn();

$methodLabels = ['cash' => 'Cash', 'bank_transfer' => 'Bank Transfer', 'cheque' => 'Cheque', 'credit_card' => 'Credit Card', 'online' => 'Online'];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Vendor Payments</h1>
        <p class="text-gray-600">Record and manage payments to vendors</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
        <?php if (hasPermission('vendor_payments.create')): ?>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New Payment</a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div><p class="text-sm text-gray-500">Total Payments</p><p class="text-xl font-bold text-gray-800"><?= formatMoney((float)$stats['total']) ?></p></div>
            <div class="bg-blue-100 p-3 rounded-full"><i class="fas fa-credit-card text-blue-600"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div><p class="text-sm text-gray-500">This Month</p><p class="text-xl font-bold text-green-600"><?= formatMoney((float)$stats['this_month']) ?></p></div>
            <div class="bg-green-100 p-3 rounded-full"><i class="fas fa-calendar text-green-600"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div><p class="text-sm text-gray-500">Total Transactions</p><p class="text-xl font-bold text-gray-800"><?= (int)$stats['count'] ?></p></div>
            <div class="bg-purple-100 p-3 rounded-full"><i class="fas fa-list text-purple-600"></i></div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Payment # or Vendor..." class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
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
            <label class="block text-sm font-medium text-gray-700 mb-1">Method</label>
            <select name="payment_method" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach ($methodLabels as $k => $l): ?>
                <option value="<?= $k ?>" <?= $payment_method === $k ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input type="date" id="date_from" name="date_from" value="<?= sanitize($date_from) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input type="date" id="date_to" name="date_to" value="<?= sanitize($date_to) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-search"></i></button>
        <?php if ($search || $vendor_id || $payment_method !== '' || $date_from || $date_to): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment #</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendor</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Unallocated</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($payments as $p): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-mono font-medium text-gray-900"><?= sanitize($p['payment_number']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($p['vendor_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($p['payment_date'])) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= $methodLabels[$p['payment_method']] ?? $p['payment_method'] ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600 font-mono"><?= sanitize($p['reference_number'] ?? '--') ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney((float)$p['amount']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono <?= (float)$p['unallocated_amount'] > 0 ? 'text-yellow-600' : 'text-gray-500' ?>"><?= formatMoney((float)$p['unallocated_amount']) ?></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="view.php?id=<?= $p['id'] ?>" class="text-blue-600 hover:text-blue-900" title="View"><i class="fas fa-eye"></i></a>
                        <?php if ((float)$p['unallocated_amount'] > 0 && hasPermission('vendor_payments.create')): ?>
                        <a href="allocate.php?id=<?= $p['id'] ?>" class="text-green-600 hover:text-green-900 ml-2" title="Allocate"><i class="fas fa-link"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($payments)): ?>
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-credit-card text-4xl mb-3 text-gray-300"></i>
                    <p>No vendor payments found</p>
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= paginationLinks($pagination, '?') ?>
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

<?php require_once '../../../includes/footer.php'; ?>
