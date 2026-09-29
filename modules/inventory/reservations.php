<?php
$pageTitle = 'Stock Reservations';
require_once '../../includes/header.php';
require_once __DIR__ . '/InventoryEngine.php';

if (!hasPermission('inventory.view')) { die('Access Denied'); }
$db = getDB();

$status      = $_GET['status'] ?? '';
$itemId      = $_GET['item_id'] ?? '';
$warehouseId = $_GET['warehouse_id'] ?? '';
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 20;

$filters = [];
if ($status !== '')      $filters['status']      = $status;
if ($itemId !== '')      $filters['item_id']      = (int)$itemId;
if ($warehouseId !== '') $filters['warehouse_id'] = (int)$warehouseId;

$result = InventoryEngine::listReservations($db, $filters, $perPage, ($page - 1) * $perPage);
$reservations = $result['rows'] ?? [];
$total = $result['total'] ?? 0;
$totalPages = max(1, (int)ceil($total / $perPage));

$warehouses = $db->query("SELECT id, warehouse_code, warehouse_name FROM warehouses WHERE status='active' ORDER BY warehouse_name")->fetchAll();
$items = $db->query("SELECT id, item_code, item_name FROM inventory_items WHERE status='active' ORDER BY item_name")->fetchAll();
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Stock Reservations</h1>
    <p class="text-gray-600">Inventory Controls — Phase I3</p>
</div>

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse</label>
            <select name="warehouse_id" class="w-full border rounded px-3 py-2">
                <option value="">All</option>
                <?php foreach ($warehouses as $w): ?>
                <option value="<?= $w['id'] ?>" <?= ($filters['warehouse_id'] ?? '') === $w['id'] ? 'selected' : '' ?>><?= sanitize($w['warehouse_code']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Item</label>
            <select name="item_id" class="w-full border rounded px-3 py-2">
                <option value="">All</option>
                <?php foreach ($items as $i): ?>
                <option value="<?= $i['id'] ?>" <?= ($filters['item_id'] ?? '') === $i['id'] ? 'selected' : '' ?>><?= sanitize($i['item_code']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full border rounded px-3 py-2">
                <option value="">All</option>
                <option value="reserved" <?= ($filters['status'] ?? '') === 'reserved' ? 'selected' : '' ?>>Reserved</option>
                <option value="released" <?= ($filters['status'] ?? '') === 'released' ? 'selected' : '' ?>>Released</option>
                <option value="consumed" <?= ($filters['status'] ?? '') === 'consumed' ? 'selected' : '' ?>>Consumed</option>
                <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Filter</button>
            <a href="reservations.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Reset</a>
        </div>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-4 py-3 border-b flex justify-between items-center">
        <h2 class="text-lg font-semibold text-gray-800">Reservations (<?= number_format($total) ?>)</h2>
        <a href="reservation_create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">New Reservation</a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Warehouse</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Quantity</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($reservations as $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-900"><?= $r['id'] ?></td>
                    <td class="px-4 py-3 text-sm text-gray-900"><?= sanitize($r['item_code']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($r['warehouse_code'] ?? '') ?></td>
                    <td class="px-4 py-3 text-sm text-right text-gray-900"><?= number_format($r['quantity'], 3) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($r['reference_number'] ?? '-') ?></td>
                    <td class="px-4 py-3 text-sm"><?= InventoryEngine::getStatusBadge($r['status']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-700"><?= date('Y-m-d H:i', strtotime($r['created_at'])) ?></td>
                    <td class="px-4 py-3 text-sm">
                        <?php if ($r['status'] === 'reserved'): ?>
                        <form method="post" action="reservation_create.php" class="inline" onsubmit="return confirm('Release this reservation?')">
                            <input type="hidden" name="action" value="release">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button type="submit" class="text-orange-600 hover:text-orange-800 mr-2">Release</button>
                        </form>
                        <form method="post" action="reservation_create.php" class="inline" onsubmit="return confirm('Consume this reservation?')">
                            <input type="hidden" name="action" value="consume">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button type="submit" class="text-green-600 hover:text-green-800 mr-2">Consume</button>
                        </form>
                        <form method="post" action="reservation_create.php" class="inline" onsubmit="return confirm('Cancel this reservation?')">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button type="submit" class="text-red-600 hover:text-red-800">Cancel</button>
                        </form>
                        <?php else: ?>
                        <span class="text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($reservations)) { echo '<tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">No reservations found</td></tr>'; } ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="px-4 py-3 border-t flex justify-between items-center">
        <span class="text-sm text-gray-500">Page <?= $page ?> of <?= $totalPages ?></span>
        <div class="flex gap-2">
            <?php if ($page > 1): ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="px-3 py-1 border rounded text-sm">Prev</a><?php endif; ?>
            <?php if ($page < $totalPages): ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="px-3 py-1 border rounded text-sm">Next</a><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
