<?php
$pageTitle = 'Customer Receipts';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/finance_helpers.php';

if (!hasPermission('customer_receipts.view')) { die('Access Denied'); }

$db = getDB();

// --- Filters ---
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (cr.receipt_number LIKE ? OR c.company_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($payment_method) {
    $where .= " AND cr.payment_method = ?";
    $params[] = $payment_method;
}
if ($date_from) {
    $where .= " AND cr.receipt_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where .= " AND cr.receipt_date <= ?";
    $params[] = $date_to;
}

// Count total
$countStmt = $db->prepare("SELECT COUNT(*) FROM customer_receipts cr JOIN clients c ON cr.client_id = c.id $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

// Fetch receipts
$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT cr.*, c.company_name, c.client_code
    FROM customer_receipts cr
    JOIN clients c ON cr.client_id = c.id
    $where
    ORDER BY cr.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$receipts = $stmt->fetchAll();

// Stats
$stats = [];
$stats['total_receipts'] = $db->query("SELECT COUNT(*) FROM customer_receipts")->fetchColumn();
$stats['total_amount'] = $db->query("SELECT COALESCE(SUM(amount),0) FROM customer_receipts")->fetchColumn();
$stats['this_month'] = $db->query("SELECT COALESCE(SUM(amount),0) FROM customer_receipts WHERE MONTH(receipt_date)=MONTH(CURDATE()) AND YEAR(receipt_date)=YEAR(CURDATE())")->fetchColumn();

$paymentMethods = [
    'cash' => 'Cash',
    'bank_transfer' => 'Bank Transfer',
    'cheque' => 'Cheque',
    'credit_card' => 'Credit Card',
    'online' => 'Online',
];

$methodBadges = [
    'cash' => 'bg-green-100 text-green-800',
    'bank_transfer' => 'bg-blue-100 text-blue-800',
    'cheque' => 'bg-yellow-100 text-yellow-800',
    'credit_card' => 'bg-purple-100 text-purple-800',
    'online' => 'bg-indigo-100 text-indigo-800',
];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Customer Receipts</h1>
        <p class="text-gray-600">Manage customer payments and receipts</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Finance
        </a>
        <?php if (hasPermission('customer_receipts.create')): ?>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i>New Receipt
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Total Receipts</p>
                <p class="text-xl font-bold text-gray-800"><?= (int)$stats['total_receipts'] ?></p>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
                <i class="fas fa-receipt text-blue-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Total Amount Received</p>
                <p class="text-xl font-bold text-green-600"><?= formatMoney((float)$stats['total_amount']) ?></p>
            </div>
            <div class="bg-green-100 p-3 rounded-full">
                <i class="fas fa-money-bill-wave text-green-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">This Month</p>
                <p class="text-xl font-bold text-gray-800"><?= formatMoney((float)$stats['this_month']) ?></p>
            </div>
            <div class="bg-indigo-100 p-3 rounded-full">
                <i class="fas fa-calendar-check text-indigo-600"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Receipt # or Client..."
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
            <select name="payment_method" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Methods</option>
                <?php foreach ($paymentMethods as $val => $label): ?>
                <option value="<?= $val ?>" <?= $payment_method === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input type="date" id="date_from" name="date_from" value="<?= sanitize($date_from) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input type="date" id="date_to" name="date_to" value="<?= sanitize($date_to) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-search"></i>
        </button>
        <?php if ($search || $payment_method || $date_from || $date_to): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">
            <i class="fas fa-times"></i>
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Receipts Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Receipt #</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Unallocated</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($receipts as $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-mono font-medium text-gray-900 whitespace-nowrap"><?= sanitize($r['receipt_number']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($r['receipt_date'])) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($r['company_name']) ?></td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded font-medium <?= $methodBadges[$r['payment_method']] ?? 'bg-gray-100 text-gray-800' ?>">
                            <?= $paymentMethods[$r['payment_method']] ?? ucwords(str_replace('_', ' ', $r['payment_method'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900 whitespace-nowrap"><?= formatMoney((float)$r['amount']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono <?= (float)$r['unallocated_amount'] > 0 ? 'text-yellow-600' : 'text-green-600' ?> whitespace-nowrap"><?= formatMoney((float)$r['unallocated_amount']) ?></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="view.php?id=<?= $r['id'] ?>" class="text-blue-600 hover:text-blue-900" title="View">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if (hasPermission('customer_receipts.create') && (float)$r['unallocated_amount'] > 0): ?>
                        <a href="allocate.php?id=<?= $r['id'] ?>" class="text-yellow-600 hover:text-yellow-900 ml-2" title="Allocate (<?= number_format((float)$r['unallocated_amount'], 2) ?>)">
                            <i class="fas fa-hand-holding-usd"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($receipts)): ?>
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                        <i class="fas fa-money-bill-wave text-4xl mb-3 text-gray-300"></i>
                        <p>No receipts found</p>
                    </td>
                </tr>
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
