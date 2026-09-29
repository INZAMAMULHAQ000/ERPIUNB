<?php
$pageTitle = 'Inventory Valuation Report';
require_once '../../includes/header.php';
require_once __DIR__ . '/InventoryEngine.php';

if (!hasPermission('inventory.reports')) { die('Access Denied'); }
$db = getDB();

$companyId   = $_GET['company_id'] ?? '';
$warehouseId = $_GET['warehouse_id'] ?? '';
$itemId      = $_GET['item_id'] ?? '';
$itemName    = $_GET['item_name'] ?? '';

$filters = [];
if ($companyId !== '')   $filters['company_id']   = (int)$companyId;
if ($warehouseId !== '') $filters['warehouse_id'] = (int)$warehouseId;
if ($itemId !== '')      $filters['item_id']      = (int)$itemId;
if ($itemName !== '')    $filters['item_name']    = $itemName;

$report = InventoryEngine::getValuationReport($db, $filters);
$rows = $report['rows'] ?? [];
$totalValue = $report['total_value'] ?? 0;

$companies = $db->query("SELECT id, company_name FROM companies WHERE status='active' ORDER BY company_name")->fetchAll();
$warehouses = $db->query("SELECT id, warehouse_code, warehouse_name FROM warehouses WHERE status='active' ORDER BY warehouse_name")->fetchAll();
$items = $db->query("SELECT id, item_code, item_name FROM inventory_items WHERE status='active' ORDER BY item_name")->fetchAll();
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Inventory Valuation Report</h1>
    <p class="text-gray-600">Inventory Costing & Valuation — Phase I2</p>
</div>

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="get" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Company</label>
            <select name="company_id" class="w-full border rounded px-3 py-2">
                <option value="">All</option>
                <?php foreach ($companies as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($filters['company_id'] ?? '') === $c['id'] ? 'selected' : '' ?>><?= sanitize($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse</label>
            <select name="warehouse_id" class="w-full border rounded px-3 py-2">
                <option value="">All</option>
                <?php foreach ($warehouses as $w): ?>
                <option value="<?= $w['id'] ?>" <?= ($filters['warehouse_id'] ?? '') === $w['id'] ? 'selected' : '' ?>><?= sanitize($w['warehouse_code'] . ' - ' . $w['warehouse_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Item</label>
            <select name="item_id" class="w-full border rounded px-3 py-2">
                <option value="">All</option>
                <?php foreach ($items as $i): ?>
                <option value="<?= $i['id'] ?>" <?= ($filters['item_id'] ?? '') === $i['id'] ? 'selected' : '' ?>><?= sanitize($i['item_code'] . ' - ' . $i['item_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Item Name</label>
            <input type="text" name="item_name" value="<?= sanitize($itemName) ?>" placeholder="Search..." class="w-full border rounded px-3 py-2">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Filter</button>
            <a href="valuation_report.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Reset</a>
        </div>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-4 py-3 border-b flex justify-between items-center">
        <h2 class="text-lg font-semibold text-gray-800">Valuation Details</h2>
        <div class="text-right">
            <span class="text-sm text-gray-500">Total Inventory Value:</span>
            <span class="text-lg font-bold text-blue-600 ml-2"><?= number_format($totalValue, 2) ?></span>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Warehouse</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty On Hand</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Unit Cost</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Stock Value</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($rows as $row): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm">
                        <div class="font-medium text-gray-900"><?= sanitize($row['item_code']) ?></div>
                        <div class="text-gray-500"><?= sanitize($row['item_name']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($row['warehouse_code'] . ' - ' . $row['warehouse_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($row['location_code'] ?? '') ?></td>
                    <td class="px-4 py-3 text-sm text-right text-gray-900"><?= number_format($row['qty_on_hand'], 3) ?></td>
                    <td class="px-4 py-3 text-sm text-right text-gray-900"><?= number_format($row['avg_unit_cost'], 4) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-medium text-blue-600"><?= number_format($row['stock_value'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)) { echo '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No valuation data found</td></tr>'; } ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
