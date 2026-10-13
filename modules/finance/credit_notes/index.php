<?php
$pageTitle = 'Credit Notes';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/finance_helpers.php';

if (!hasPermission('credit_notes.view')) { die('Access Denied'); }

$db = getDB();

// --- Filters ---
$search = $_GET['search'] ?? '';
$client_id = $_GET['client_id'] ?? '';
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (cn.credit_note_number LIKE ? OR c.company_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($client_id) {
    $where .= " AND cn.client_id = ?";
    $params[] = (int)$client_id;
}
if ($type) {
    $where .= " AND cn.credit_note_type = ?";
    $params[] = $type;
}
if ($status !== '') {
    $where .= " AND cn.status = ?";
    $params[] = $status;
}
if ($date_from) {
    $where .= " AND cn.credit_note_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where .= " AND cn.credit_note_date <= ?";
    $params[] = $date_to;
}

// Count total
$countStmt = $db->prepare("SELECT COUNT(*) FROM credit_notes cn JOIN clients c ON cn.client_id = c.id $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

// Fetch credit notes
$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT cn.*, c.company_name, c.client_code, ci.invoice_number
    FROM credit_notes cn
    JOIN clients c ON cn.client_id = c.id
    LEFT JOIN customer_invoices ci ON cn.invoice_id = ci.id
    $where
    ORDER BY cn.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$creditNotes = $stmt->fetchAll();

// Fetch clients for filter
$clients = $db->query("SELECT id, client_code, company_name FROM clients WHERE status = 'active' AND deleted_at IS NULL ORDER BY company_name")->fetchAll();

// Stats
$stats = [];
$stats['total'] = $db->query("SELECT COUNT(*) FROM credit_notes")->fetchColumn();
$stats['issued_amount'] = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM credit_notes WHERE status = 'issued'")->fetchColumn();
$stats['applied_amount'] = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM credit_notes WHERE status = 'applied'")->fetchColumn();

$creditNoteTypes = [
    'price_correction' => 'Price Correction',
    'return' => 'Return',
    'discount_adjustment' => 'Discount Adjustment',
    'cancellation' => 'Cancellation',
];

$typeBadges = [
    'price_correction' => 'bg-purple-100 text-purple-800',
    'return' => 'bg-orange-100 text-orange-800',
    'discount_adjustment' => 'bg-teal-100 text-teal-800',
    'cancellation' => 'bg-red-100 text-red-800',
];

$statusBadges = [
    'draft' => 'bg-gray-100 text-gray-800',
    'issued' => 'bg-blue-100 text-blue-800',
    'applied' => 'bg-green-100 text-green-800',
    'cancelled' => 'bg-red-100 text-red-800',
];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Credit Notes</h1>
        <p class="text-gray-600">Manage credit notes and customer adjustments</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Finance
        </a>
        <?php if (hasPermission('credit_notes.create')): ?>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i>New Credit Note
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Total Credit Notes</p>
                <p class="text-xl font-bold text-gray-800"><?= (int)$stats['total'] ?></p>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
                <i class="fas fa-file-invoice text-blue-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Issued Amount</p>
                <p class="text-xl font-bold text-blue-600"><?= formatMoney((float)$stats['issued_amount']) ?></p>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
                <i class="fas fa-paper-plane text-blue-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Applied Amount</p>
                <p class="text-xl font-bold text-green-600"><?= formatMoney((float)$stats['applied_amount']) ?></p>
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
        <div class="flex-1 min-w-[160px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="CN # or Client..."
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-44">
            <label class="block text-sm font-medium text-gray-700 mb-1">Client</label>
            <select name="client_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Clients</option>
                <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $client_id == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-36">
            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <select name="type" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Types</option>
                <?php foreach ($creditNoteTypes as $val => $label): ?>
                <option value="<?= $val ?>" <?= $type === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-32">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="issued" <?= $status === 'issued' ? 'selected' : '' ?>>Issued</option>
                <option value="applied" <?= $status === 'applied' ? 'selected' : '' ?>>Applied</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <div class="w-36">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input type="date" name="date_from" value="<?= sanitize($date_from) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-36">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input type="date" name="date_to" value="<?= sanitize($date_to) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-search"></i>
        </button>
        <?php if ($search || $client_id || $type || $status !== '' || $date_from || $date_to): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">
            <i class="fas fa-times"></i>
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Credit Notes Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">CN #</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($creditNotes as $cn): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-mono font-medium text-gray-900 whitespace-nowrap"><?= sanitize($cn['credit_note_number']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($cn['credit_note_date'])) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($cn['company_name']) ?></td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded font-medium <?= $typeBadges[$cn['credit_note_type']] ?? 'bg-gray-100 text-gray-800' ?>">
                            <?= $creditNoteTypes[$cn['credit_note_type']] ?? ucwords(str_replace('_', ' ', $cn['credit_note_type'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm font-mono text-gray-600"><?= sanitize($cn['invoice_number'] ?? '-') ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900 whitespace-nowrap"><?= formatMoney((float)$cn['total_amount']) ?></td>
                    <td class="px-4 py-3 text-center whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded font-medium <?= $statusBadges[$cn['status']] ?? 'bg-gray-100 text-gray-800' ?>">
                            <?= ucfirst($cn['status']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="view.php?id=<?= $cn['id'] ?>" class="text-blue-600 hover:text-blue-900" title="View">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($creditNotes)): ?>
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                        <i class="fas fa-file-invoice text-4xl mb-3 text-gray-300"></i>
                        <p>No credit notes found</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= paginationLinks($pagination, '?') ?>

<?php require_once '../../../includes/footer.php'; ?>
