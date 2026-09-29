<?php
/** P3 — Purchase Order List */
$pageTitle = 'Purchase Orders';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../finance/helpers/finance_helpers.php';
require_once __DIR__ . '/../ProcurementService.php';
require_once __DIR__ . '/../PoService.php';

if (!hasPermission('procurement.po.view')) die('Access Denied');
$db = getDB();

$filters = [];
if (!empty($_GET['status']))   $filters['status'] = $_GET['status'];
if (!empty($_GET['search']))   $filters['search'] = $_GET['search'];
if (!empty($_GET['vendor_id']))$filters['vendor_id'] = (int)$_GET['vendor_id'];
if (!empty($_GET['company_id']))$filters['company_id'] = (int)$_GET['company_id'];

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;
$result = PoService::listPOs($db, $filters, $perPage, $offset);
$rows = $result['rows'];
$total = $result['total'];
$totalPages = ceil($total / $perPage);

$statusStyles = [
    'draft'=>'bg-gray-100 text-gray-600','pending_approval'=>'bg-yellow-100 text-yellow-700',
    'approved'=>'bg-green-100 text-green-700','rejected'=>'bg-red-100 text-red-700',
    'sent'=>'bg-blue-100 text-blue-700','acknowledged'=>'bg-indigo-100 text-indigo-700',
    'partially_received'=>'bg-cyan-100 text-cyan-700','fully_received'=>'bg-emerald-100 text-emerald-700',
    'closed'=>'bg-gray-200 text-gray-700','cancelled'=>'bg-purple-100 text-purple-700',
];
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Purchase Orders</h1>
        <p class="text-gray-500 text-sm"><?= number_format($total) ?> PO(s) found</p>
    </div>
    <div class="flex gap-2">
        <?php if (hasPermission('procurement.po.create')): ?>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
            <i class="fas fa-plus mr-1"></i>New PO
        </a>
        <?php endif; ?>
        <a href="dashboard.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Dashboard</a>
    </div>
</div>

<!-- Filters -->
<form method="get" class="bg-white rounded-lg shadow p-4 mb-4 flex flex-wrap gap-3 items-end">
    <div>
        <label class="text-xs text-gray-500 block mb-1">Search</label>
        <input name="search" value="<?= sanitize($filters['search'] ?? '') ?>" placeholder="PO number" class="border rounded px-3 py-2 text-sm">
    </div>
    <div>
        <label class="text-xs text-gray-500 block mb-1">Status</label>
        <select name="status" class="border rounded px-3 py-2 text-sm">
            <option value="">All</option>
            <?php foreach (PoService::STATUSES as $s): ?>
            <option <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="bg-gray-800 text-white px-4 py-2 rounded text-sm">Filter</button>
    <?php if ($filters): ?>
    <a href="list.php" class="text-gray-500 px-4 py-2 border border-gray-200 hover:text-gray-700 text-sm">Clear</a>
    <?php endif; ?>
</form>

<!-- Table -->
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="px-4 py-3 text-left">PO Number</th>
                <th class="px-4 py-3 text-left">Date</th>
                <th class="px-4 py-3 text-left">Vendor</th>
                <th class="px-4 py-3 text-left">Company</th>
                <th class="px-4 py-3 text-right">Total</th>
                <th class="px-4 py-3 text-left">Expected Delivery</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y">
        <?php foreach ($rows as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-mono text-xs"><?= sanitize($r['po_number']) ?></td>
                <td class="px-4 py-2 text-xs"><?= $r['po_date'] ? date('d M Y', strtotime($r['po_date'])) : '—' ?></td>
                <td class="px-4 py-2"><?= sanitize($r['vendor_name']) ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($r['company_name']) ?></td>
                <td class="px-4 py-2 text-right font-mono font-semibold"><?= formatMoney((float)$r['grand_total']) ?></td>
                <td class="px-4 py-2 text-xs"><?= $r['expected_delivery_date'] ? date('d M Y', strtotime($r['expected_delivery_date'])) : '—' ?></td>
                <td class="px-4 py-2">
                    <span class="px-2 py-0.5 rounded text-xs font-semibold <?= $statusStyles[$r['status']] ?? 'bg-gray-100' ?>">
                        <?= strtoupper(str_replace('_', ' ', $r['status'])) ?>
                    </span>
                </td>
                <td class="px-4 py-2 text-right whitespace-nowrap">
                    <a href="view.php?id=<?= $r['id'] ?>" class="text-blue-600 hover:underline text-xs">View</a>
                    <?php if (in_array($r['status'], ['draft','rejected']) && hasPermission('procurement.po.edit')): ?>
                    · <a href="edit.php?id=<?= $r['id'] ?>" class="text-blue-600 hover:underline text-xs">Edit</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; if (empty($rows)): ?>
            <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">No purchase orders found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="flex justify-center gap-2 mt-4">
    <?php for ($p = 1; $p <= min($totalPages, 10); $p++): ?>
    <a href="?page=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>"
       class="px-3 py-1 rounded text-sm <?= $p === $page ? 'bg-blue-600 text-white' : 'bg-white border hover:bg-gray-50' ?>">
        <?= $p ?>
    </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php require_once '../../../includes/footer.php'; ?>
