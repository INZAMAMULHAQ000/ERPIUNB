<?php
$pageTitle = 'Goods Receipt Notes';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';

if (!hasPermission('goods_receipt.view')) { die('Access Denied'); }

$db = getDB();

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (grn.grn_number LIKE ? OR po.po_number LIKE ? OR v.vendor_name LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($status !== '') {
    $where .= " AND grn.status = ?";
    $params[] = $status;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM goods_receipt_notes grn JOIN purchase_orders po ON grn.po_id = po.id JOIN vendors v ON grn.vendor_id = v.id $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT grn.*, po.po_number, v.vendor_name, v.vendor_code
    FROM goods_receipt_notes grn
    JOIN purchase_orders po ON grn.po_id = po.id
    JOIN vendors v ON grn.vendor_id = v.id
    $where ORDER BY grn.created_at DESC LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$grns = $stmt->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Goods Receipt Notes</h1>
        <p class="text-gray-600">Record goods received against purchase orders</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
        <?php if (hasPermission('goods_receipt.create')): ?>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New GRN</a>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="GRN #, PO#, or Vendor..." class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-36">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-search"></i></button>
        <?php if ($search || $status !== ''): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">GRN #</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">PO #</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendor</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Receipt Date</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($grns as $g): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-mono font-medium text-gray-900"><?= sanitize($g['grn_number']) ?></td>
                    <td class="px-4 py-3 text-sm font-mono text-gray-600"><?= sanitize($g['po_number']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($g['vendor_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($g['receipt_date'])) ?></td>
                    <td class="px-4 py-3 text-center whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded font-medium <?= $g['status'] === 'completed' ? 'bg-green-100 text-green-800' : ($g['status'] === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') ?>">
                            <?= ucfirst($g['status']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="view.php?id=<?= $g['id'] ?>" class="text-blue-600 hover:text-blue-900" title="View"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($grns)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-clipboard-check text-4xl mb-3 text-gray-300"></i>
                    <p>No goods receipt notes found</p>
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../../includes/footer.php'; ?>
