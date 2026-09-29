<?php
/** P4 � Goods Receipt List */
$pageTitle = 'Goods Receipts';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../finance/helpers/finance_helpers.php';
require_once __DIR__ . '/../../ProcurementService.php';
require_once __DIR__ . '/../../ReceiptService.php';

if (!hasPermission('procurement.receipt.view')) die('Access Denied');
$db = getDB();

$filters = [];
if (!empty($_GET['status']))    $filters['status'] = $_GET['status'];
if (!empty($_GET['search']))    $filters['search'] = $_GET['search'];
if (!empty($_GET['po_id']))     $filters['po_id'] = (int)$_GET['po_id'];
if (!empty($_GET['vendor_id'])) $filters['vendor_id'] = (int)$_GET['vendor_id'];
if (!empty($_GET['company_id']))$filters['company_id'] = (int)$_GET['company_id'];
if (!empty($_GET['from_date'])) $filters['from_date'] = $_GET['from_date'];
if (!empty($_GET['to_date']))   $filters['to_date'] = $_GET['to_date'];

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;
$result = ReceiptService::listReceipts($db, $filters, $perPage, $offset);
$rows = $result['rows'];
$total = $result['total'];
$totalPages = ceil($total / $perPage);

$statusStyles = [
    'draft'=>'bg-gray-100 text-gray-600','pending_inspection'=>'bg-yellow-100 text-yellow-700',
    'partially_accepted'=>'bg-orange-100 text-orange-700','accepted'=>'bg-green-100 text-green-700',
    'rejected'=>'bg-red-100 text-red-700','cancelled'=>'bg-purple-100 text-purple-700',
];
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Goods Receipts</h1>
        <p class="text-gray-500 text-sm"><?= number_format($total) ?> receipt(s) found</p>
    </div>
    <div class="flex gap-2">
        <a href="../dashboard/index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Dashboard</a>
        <?php if (hasPermission('procurement.receipt.create')): ?>
        <a href="../create/index.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
            <i class="fas fa-plus mr-1"></i>New GR
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<form method="get" class="bg-white rounded-lg shadow p-4 mb-4 flex flex-wrap gap-3 items-end">
    <div>
        <label class="text-xs text-gray-500 block mb-1">Search</label>
        <input name="search" value="<?= sanitize($filters['search'] ?? '') ?>" placeholder="GR #, PO #" class="border rounded px-3 py-2 text-sm">
    </div>
    <div>
        <label class="text-xs text-gray-500 block mb-1">Status</label>
        <select name="status" class="border rounded px-3 py-2 text-sm">
            <option value="">All</option>
            <?php foreach (ReceiptService::GR_STATUSES as $s): ?>
            <option <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="text-xs text-gray-500 block mb-1">From Date</label>
        <input type="date" name="from_date" value="<?= sanitize($filters['from_date'] ?? '') ?>" class="border rounded px-3 py-2 text-sm">
    </div>
    <div>
        <label class="text-xs text-gray-500 block mb-1">To Date</label>
        <input type="date" name="to_date" value="<?= sanitize($filters['to_date'] ?? '') ?>" class="border rounded px-3 py-2 text-sm">
    </div>
    <button class="bg-gray-800 text-white px-4 py-2 rounded text-sm">Filter</button>
    <?php if ($filters): ?>
    <a href="index.php" class="text-gray-500 px-4 py-2 border border-gray-200 hover:text-gray-700 text-sm">Clear</a>
    <?php endif; ?>
</form>

<!-- Table -->
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="px-4 py-3 text-left">GR Number</th>
                <th class="px-4 py-3 text-left">Date</th>
                <th class="px-4 py-3 text-left">PO</th>
                <th class="px-4 py-3 text-left">Vendor</th>
                <th class="px-4 py-3 text-left">Company</th>
                <th class="px-4 py-3 text-left">Received By</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y">
        <?php foreach ($rows as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-mono text-xs"><?= sanitize($r['gr_number']) ?></td>
                <td class="px-4 py-2 text-xs"><?= $r['receipt_date'] ? date('d M Y', strtotime($r['receipt_date'])) : '�' ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($r['po_number']) ?></td>
                <td class="px-4 py-2"><?= sanitize($r['vendor_name']) ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($r['company_name']) ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($r['received_by_name'] ?? '�') ?></td>
                <td class="px-4 py-2">
                    <span class="px-2 py-0.5 rounded text-xs font-semibold <?= $statusStyles[$r['status']] ?? 'bg-gray-100' ?>">
                        <?= strtoupper(str_replace('_', ' ', $r['status'])) ?>
                    </span>
                </td>
                <td class="px-4 py-2 text-right whitespace-nowrap">
                    <a href="../view/index.php?id=<?= $r['id'] ?>" class="text-blue-600 hover:underline text-xs">View</a>
                </td>
            </tr>
        <?php endforeach; if (empty($rows)) { ?>
            <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">No receipts found.</td></tr>
        <?php } ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="flex justify-center gap-2 mt-4">
    <?php for ($p = 1; $p <= min($totalPages, 10); $p++): ?>
    <a href="?page=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>" class="px-3 py-1 rounded border text-sm <?= $p === $page ? 'bg-blue-600 text-white' : 'bg-white hover:bg-gray-50' ?>"><?= $p ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>