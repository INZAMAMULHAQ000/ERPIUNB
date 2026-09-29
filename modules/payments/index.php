<?php
$pageTitle = 'Payments';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('payments.view')) { die('Access Denied'); }

$db = getDB();
$search = $_GET['search'] ?? '';
$invoice_id = $_GET['invoice_id'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) { $where .= " AND (p.reference_number LIKE ? OR i.invoice_number LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($invoice_id) { $where .= " AND p.invoice_id = ?"; $params[] = $invoice_id; }

$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM payments p LEFT JOIN invoices i ON p.invoice_id = i.id $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("SELECT p.*, i.invoice_number, i.total_amount as invoice_total, c.company_name 
    FROM payments p 
    LEFT JOIN invoices i ON p.invoice_id = i.id 
    LEFT JOIN clients c ON i.client_id = c.id 
    $where 
    ORDER BY p.payment_date DESC, p.id DESC 
    LIMIT ? OFFSET ?");
$stmt->execute($queryParams);
$payments = $stmt->fetchAll();

$invoices = $db->query("SELECT id, invoice_number FROM invoices WHERE status IN ('sent','partially_paid','overdue') ORDER BY invoice_number DESC")->fetchAll();

$methodColors = ['cash' => 'bg-gray-100 text-gray-800', 'bank_transfer' => 'bg-blue-100 text-blue-800', 'upi' => 'bg-purple-100 text-purple-800', 'credit_card' => 'bg-yellow-100 text-yellow-800', 'paypal' => 'bg-indigo-100 text-indigo-800'];
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Payments</h1>
        <p class="text-gray-600">Track payments received</p>
    </div>
    <?php if (hasPermission('payments.create')): ?>
    <a href="<?= url('modules/payments/create.php') ?>" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition"><i class="fas fa-plus mr-2"></i>Record Payment</a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <?php
    $totalReceived = $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments")->fetch()['total'];
    $thisMonth = $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE) AND YEAR(payment_date) = YEAR(CURRENT_DATE)")->fetch()['total'];
    $pendingPayments = $db->query("SELECT COALESCE(SUM(balance_amount), 0) as total FROM invoices WHERE status IN ('sent','partially_paid','overdue')")->fetch()['total'];
    ?>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Total Received</div>
        <div class="text-2xl font-bold text-green-600"><?= formatMoney($totalReceived) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">This Month</div>
        <div class="text-2xl font-bold text-blue-600"><?= formatMoney($thisMonth) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Pending Payments</div>
        <div class="text-2xl font-bold text-red-600"><?= formatMoney($pendingPayments) ?></div>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Invoice # or Reference..." class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-48">
            <label class="block text-sm font-medium text-gray-700 mb-1">Invoice</label>
            <select name="invoice_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Invoices</option>
                <?php foreach ($invoices as $inv): ?><option value="' . $inv['id'] . '" <?= $invoice_id == $inv['id'] ? 'selected' : '' ?>><?= sanitize($inv['invoice_number']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-search"></i></button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($payments as $p): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 text-gray-600"><?= date('M d, Y', strtotime($p['payment_date'])) ?></td>
                <td class="px-6 py-4 font-medium text-gray-900">
                    <a href="<?= url('modules/invoices/view.php?id=' . $p['invoice_id']) ?>" class="text-blue-600 hover:text-blue-900"><?= sanitize($p['invoice_number'] ?? '-') ?></a>
                </td>
                <td class="px-6 py-4 text-gray-600"><?= sanitize($p['company_name'] ?? '-') ?></td>
                <td class="px-6 py-4 text-right font-medium text-green-600"><?= formatMoney($p['amount']) ?></td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs rounded <?= $methodColors[$p['payment_method']] ?? 'bg-gray-100' ?>"><?= ucwords(str_replace('_', ' ', $p['payment_method'])) ?></span>
                </td>
                <td class="px-6 py-4 text-gray-600"><?= sanitize($p['reference_number'] ?? '-') ?></td>
                <td class="px-6 py-4 text-right">
                    <?php if (hasPermission('payments.edit')): ?>
                    <a href="<?= url('modules/payments/edit.php?id=' . $p['id'] . '') ?>" class="text-yellow-600 hover:text-yellow-900"><i class="fas fa-edit"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($payments)): ?>
            <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No payments found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../includes/footer.php'; ?>

