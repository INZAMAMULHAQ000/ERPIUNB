<?php
$pageTitle = 'Adjust Inventory';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('inventory.manage')) { die('Access Denied'); }

$db = getDB();
$item_id = $_GET['id'] ?? 0;

$item = $db->prepare("SELECT * FROM inventory_items WHERE id = ?");
$item->execute([$item_id]);
$item = $item->fetch();

if (!$item) { die('Item not found'); }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $movement_type = $_POST['movement_type'] ?? '';
    $quantity = $_POST['quantity'] ?? 0;
    
    $errors = validate(['movement_type' => $movement_type, 'quantity' => $quantity], ['movement_type' => 'required', 'quantity' => 'required']);
    
    if (empty($errors)) {
        $quantity = intval($quantity);
        $newQuantity = $movement_type === 'in' ? $item['quantity'] + $quantity : max(0, $item['quantity'] - $quantity);
        
        $db->prepare("UPDATE inventory_items SET quantity = ? WHERE id = ?")->execute([$newQuantity, $item_id]);
        
        $db->prepare("INSERT INTO inventory_logs (item_id, movement_type, quantity, reference_number, notes, created_by) VALUES (?, ?, ?, ?, ?, ?)")->execute([
            $item_id,
            $movement_type,
            $movement_type === 'in' ? $quantity : -$quantity,
            $_POST['reference_number'] ?: null,
            $_POST['notes'] ?: null,
            $_SESSION['user_id']
        ]);
        
        logAudit($_SESSION['user_id'], 'adjust_inventory', 'inventory_items');
        flash('success', 'Stock adjusted successfully');
        to('http://localhost/ERP/modules/inventory/index.php');
    }
}

$logs = $db->prepare("SELECT l.*, u.name as created_by_name FROM inventory_logs l LEFT JOIN users u ON l.created_by = u.id WHERE l.item_id = ? ORDER BY l.created_at DESC LIMIT 10");
$logs->execute([$item_id]);
$logs = $logs->fetchAll();
?>

<div class="mb-6">
    <a href="<?= url('modules/inventory/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Inventory</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Adjust Stock - <?= sanitize($item['item_name']) ?></h2>
        
        <div class="bg-gray-50 p-4 rounded mb-4">
            <div class="flex justify-between mb-2">
                <span class="text-gray-600">Current Quantity:</span>
                <span class="font-bold text-xl"><?= $item['quantity'] ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Min Quantity:</span>
                <span><?= $item['min_quantity'] ?></span>
            </div>
        </div>
        
        <?php if ($errors): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Movement Type <span class="text-red-600">*</span></labe>
                    <select name="movement_type" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="in">Stock In</option>
                        <option value="out">Stock Out</option>
                        <option value="adjustment">Adjustment</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity <span class="text-red-600">*</span></labe>
                    <input type="number" name="quantity" value="1" min="1" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
                <input type="text" name="reference_number" placeholder="PO, Invoice, etc." class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="2" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            
            <div class="pt-4">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">Adjust Stock</button>
                <a href="<?= url('modules/inventory/index.php') ?>" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
            </div>
        </form>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Stock History</h2>
        <?php if ($logs): ?>
        <div class="space-y-2">
            <?php foreach ($logs as $log): ?>
            <div class="flex justify-between items-center py-2 border-b">
                <div>
                    <span class="font-medium <?= $log['movement_type'] == 'in' ? 'text-green-600' : 'text-red-600' ?>">
                        <?= $log['movement_type'] == 'in' ? '+' : ($log['movement_type'] == 'out' ? '-' : '~') ?><?= abs($log['quantity']) ?>
                    </span>
                    <span class="text-sm text-gray-500 ml-2"><?= ucfirst($log['movement_type']) ?></span>
                    <?php if ($log['reference_number']): ?>
                    <div class="text-xs text-gray-400">Ref: <?= sanitize($log['reference_number']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="text-right text-sm text-gray-500">
                    <div><?= date('M d, Y', strtotime($log['created_at'])) ?></div>
                    <div><?= sanitize($log['created_by_name']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-gray-500 text-center py-4">No history</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

