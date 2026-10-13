<?php
$pageTitle = 'Invoices';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('invoices.view')) { die('Access Denied'); }

$db = getDB();
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$client_id = $_GET['client_id'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) { $where .= " AND (i.invoice_number LIKE ? OR c.company_name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status) { $where .= " AND i.status = ?"; $params[] = $status; }
if ($client_id) { $where .= " AND i.client_id = ?"; $params[] = $client_id; }

$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM invoices i LEFT JOIN clients c ON i.client_id = c.id $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("SELECT i.*, c.company_name, c.client_code FROM invoices i LEFT JOIN clients c ON i.client_id = c.id $where ORDER BY i.invoice_date DESC LIMIT ? OFFSET ?");
$stmt->execute($queryParams);
$invoices = $stmt->fetchAll();

$clients = $db->query("SELECT id, company_name FROM clients WHERE status = 'active' AND deleted_at IS NULL AND deleted_at IS NULL ORDER BY company_name")->fetchAll();

$statusColors = ['draft' => 'bg-gray-100 text-gray-800', 'sent' => 'bg-blue-100 text-blue-800', 'partially_paid' => 'bg-yellow-100 text-yellow-800', 'paid' => 'bg-green-100 text-green-800', 'overdue' => 'bg-red-100 text-red-800', 'cancelled' => 'bg-red-200 text-red-800'];
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Invoices</h1>
        <p class="text-gray-600">Manage invoices</p>
    </div>
    <?php if (hasPermission('invoices.create')): ?>
    <a href="<?= url('modules/invoices/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New Invoice</a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <?php
    $stats = [
        ['label' => 'Total Invoices', 'value' => $total],
        ['label' => 'Paid', 'value' => $db->query("SELECT COUNT(*) as c FROM invoices WHERE status = 'paid'")->fetch()['c']],
        ['label' => 'Pending', 'value' => $db->query("SELECT COUNT(*) as c FROM invoices WHERE status IN ('sent','partially_paid')")->fetch()['c']],
        ['label' => 'Overdue', 'value' => $db->query("SELECT COUNT(*) as c FROM invoices WHERE status = 'overdue'")->fetch()['c']]
    ];
    foreach ($stats as $s): ?>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500"><?= $s['label'] ?></div>
        <div class="text-2xl font-bold text-gray-800"><?= $s['value'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Invoice number..." class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Sent</option>
                <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="overdue" <?= $status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-search"></i></button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paid</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Balance</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($invoices as $inv): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-medium text-gray-900"><?= sanitize($inv['invoice_number']) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= sanitize($inv['company_name'] ?? '-') ?></td>
                <td class="px-6 py-4 text-gray-600"><?= date('M d, Y', strtotime($inv['invoice_date'])) ?></td>
                <td class="px-6 py-4 font-medium"><?= formatMoney($inv['total_amount']) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= formatMoney($inv['paid_amount']) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= formatMoney($inv['balance_amount']) ?></td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs rounded <?= $statusColors[$inv['status']] ?? 'bg-gray-100' ?>"><?= ucwords(str_replace('_', ' ', $inv['status'])) ?></span>
                </td>
                <td class="px-6 py-4 text-right">
                    <a href="<?= url('modules/invoices/view.php?id=' . $inv['id'] . '') ?>" class="text-blue-600 hover:text-blue-900 mr-2"><i class="fas fa-eye"></i></a>
                    <?php if (hasPermission('invoices.edit')): ?>
                    <a href="<?= url('modules/invoices/edit.php?id=' . $inv['id'] . '') ?>" class="text-yellow-600 hover:text-yellow-900 mr-2"><i class="fas fa-edit"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($invoices)): ?>
            <tr><td colspan="8" class="px-6 py-8 text-center text-gray-500">No invoices found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../includes/footer.php'; ?>

