<?php
$pageTitle = 'Customer Invoices';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';

if (!hasPermission('customer_invoices.view')) { die('Access Denied'); }

$db = getDB();

// --- Filters ---
$search = $_GET['search'] ?? '';
$client_id = $_GET['client_id'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (ci.invoice_number LIKE ? OR c.company_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($client_id) {
    $where .= " AND ci.client_id = ?";
    $params[] = (int)$client_id;
}
if ($status !== '') {
    $where .= " AND ci.status = ?";
    $params[] = $status;
}
if ($date_from) {
    $where .= " AND ci.invoice_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where .= " AND ci.invoice_date <= ?";
    $params[] = $date_to;
}

// Count total
$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM customer_invoices ci JOIN clients c ON ci.client_id = c.id $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];
$pagination = paginate($total, $perPage, $page);

// Fetch invoices
$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT ci.*, c.company_name, c.client_code
    FROM customer_invoices ci
    JOIN clients c ON ci.client_id = c.id
    $where
    ORDER BY ci.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$invoices = $stmt->fetchAll();

// Fetch clients for filter
$clients = $db->query("SELECT id, client_code, company_name FROM clients WHERE status = 'active' AND deleted_at IS NULL ORDER BY company_name")->fetchAll();

// Stats
$stats = [];
$stats['outstanding'] = $db->query("SELECT COALESCE(SUM(balance_amount),0) FROM customer_invoices WHERE status IN ('approved','sent','partially_paid','overdue')")->fetchColumn();
$stats['overdue'] = $db->query("SELECT COALESCE(SUM(balance_amount),0) FROM customer_invoices WHERE status='overdue' OR (status IN ('approved','sent','partially_paid') AND due_date < CURDATE())")->fetchColumn();
$stats['this_month'] = $db->query("SELECT COUNT(*) FROM customer_invoices WHERE MONTH(invoice_date)=MONTH(CURDATE()) AND YEAR(invoice_date)=YEAR(CURDATE())")->fetchColumn();
$stats['paid_this_month'] = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM customer_invoices WHERE status='paid' AND MONTH(updated_at)=MONTH(CURDATE()) AND YEAR(updated_at)=YEAR(CURDATE())")->fetchColumn();

$statusBadges = [
    'draft' => 'bg-gray-100 text-gray-800',
    'approved' => 'bg-blue-100 text-blue-800',
    'sent' => 'bg-indigo-100 text-indigo-800',
    'partially_paid' => 'bg-yellow-100 text-yellow-800',
    'paid' => 'bg-green-100 text-green-800',
    'overdue' => 'bg-red-100 text-red-800',
    'cancelled' => 'bg-gray-200 text-gray-600',
];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Customer Invoices</h1>
        <p class="text-gray-600">Manage customer invoices and billing</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Finance
        </a>
        <?php if (hasPermission('customer_invoices.create')): ?>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i>New Invoice
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Total Outstanding</p>
                <p class="text-xl font-bold text-gray-800"><?= formatMoney((float)$stats['outstanding']) ?></p>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
                <i class="fas fa-file-invoice text-blue-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Overdue Amount</p>
                <p class="text-xl font-bold text-red-600"><?= formatMoney((float)$stats['overdue']) ?></p>
            </div>
            <div class="bg-red-100 p-3 rounded-full">
                <i class="fas fa-exclamation-triangle text-red-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">This Month</p>
                <p class="text-xl font-bold text-gray-800"><?= (int)$stats['this_month'] ?></p>
            </div>
            <div class="bg-indigo-100 p-3 rounded-full">
                <i class="fas fa-calendar-check text-indigo-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Paid This Month</p>
                <p class="text-xl font-bold text-green-600"><?= formatMoney((float)$stats['paid_this_month']) ?></p>
            </div>
            <div class="bg-green-100 p-3 rounded-full">
                <i class="fas fa-check-circle text-green-600"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Invoice # or Client..."
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-48">
            <label class="block text-sm font-medium text-gray-700 mb-1">Client</label>
            <select name="client_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Clients</option>
                <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $client_id == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-36">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Sent</option>
                <option value="partially_paid" <?= $status === 'partially_paid' ? 'selected' : '' ?>>Partially Paid</option>
                <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="overdue" <?= $status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
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
        <?php if ($search || $client_id || $status !== '' || $date_from || $date_to): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">
            <i class="fas fa-times"></i>
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Invoices Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paid</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($invoices as $inv): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-mono font-medium text-gray-900 whitespace-nowrap"><?= sanitize($inv['invoice_number']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($inv['company_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($inv['invoice_date'])) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($inv['due_date'])) ?></td>
                    <td class="px-4 py-3 text-center whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded font-medium <?= $statusBadges[$inv['status']] ?? 'bg-gray-100 text-gray-800' ?>">
                            <?= ucwords(str_replace('_', ' ', $inv['status'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900 whitespace-nowrap"><?= formatMoney((float)$inv['total_amount']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900 whitespace-nowrap"><?= formatMoney((float)$inv['paid_amount']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono <?= (float)$inv['balance_amount'] > 0 ? 'text-red-600' : 'text-green-600' ?> whitespace-nowrap"><?= formatMoney((float)$inv['balance_amount']) ?></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="view.php?id=<?= $inv['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-2" title="View">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if ($inv['status'] === 'draft' && hasPermission('customer_invoices.edit')): ?>
                        <a href="edit.php?id=<?= $inv['id'] ?>" class="text-yellow-600 hover:text-yellow-900 mr-2" title="Edit">
                            <i class="fas fa-pencil"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ($inv['status'] === 'draft' && hasPermission('customer_invoices.approve')): ?>
                        <form method="POST" action="approve.php" style="display:inline" onsubmit="return confirm('Approve invoice <?= sanitize(addslashes($inv['invoice_number'])) ?>? This action will post to the general ledger.')">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                            <button type="submit" class="text-green-600 hover:text-green-900 mr-2" title="Approve">
                                <i class="fas fa-check"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php if (!in_array($inv['status'], ['paid','cancelled']) && hasPermission('customer_invoices.cancel')): ?>
                        <form method="POST" action="cancel.php" style="display:inline" onsubmit="return confirm('Cancel invoice <?= sanitize(addslashes($inv['invoice_number'])) ?>? This action cannot be undone.')">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                            <button type="submit" class="text-red-600 hover:text-red-900" title="Cancel">
                                <i class="fas fa-ban"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($invoices)): ?>
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                        <i class="fas fa-file-invoice text-4xl mb-3 text-gray-300"></i>
                        <p>No invoices found</p>
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
