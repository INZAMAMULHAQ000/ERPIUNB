<?php
$pageTitle = 'Inventory Settings';
require_once '../../includes/header.php';
require_once __DIR__ . '/InventoryEngine.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('inventory.settings')) { die('Access Denied'); }
$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) { clearOldInput(); }

$settings = InventoryEngine::getSettings($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid CSRF token.'; }
    if (empty($errors)) {
        $allowNeg   = !empty($_POST['allow_negative_stock']) ? 1 : 0;
        $costing    = $_POST['default_costing_method'] ?? 'AVG';
        $defWh      = !empty($_POST['default_warehouse_id']) ? (int)$_POST['default_warehouse_id'] : null;
        $defLoc     = !empty($_POST['default_location_id']) ? (int)$_POST['default_location_id'] : null;
        $prefix     = trim($_POST['stock_movement_prefix'] ?? 'SM');
        $adjAppr    = !empty($_POST['require_adj_approval']) ? 1 : 0;
        $trfAppr    = !empty($_POST['require_transfer_approval']) ? 1 : 0;
        $allowedCosting = ['FIFO','LIFO','AVG','STD','MA'];
        if (!in_array($costing, $allowedCosting, true)) { $costing = 'AVG'; }
        if ($prefix === '') $prefix = 'SM';

        try {
            $db->prepare("
                UPDATE inventory_settings SET
                    allow_negative_stock = :neg,
                    default_costing_method = :cost,
                    default_warehouse_id = :wh,
                    default_location_id = :loc,
                    stock_movement_prefix = :prefix,
                    require_adj_approval = :adj,
                    require_transfer_approval = :trf,
                    updated_by = :uid
                WHERE id = 1
            ")->execute([
                ':neg'   => $allowNeg, ':cost' => $costing,
                ':wh'    => $defWh,    ':loc'  => $defLoc,
                ':prefix'=> $prefix,   ':adj'  => $adjAppr,
                ':trf'   => $trfAppr,  ':uid'  => $_SESSION['user_id'],
            ]);
            logAudit($_SESSION['user_id'], 'updated inventory settings', 'inventory');
            flash('success', 'Settings saved');
            to('modules/inventory/settings.php');
        } catch (Exception $e) {
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

$warehouses = $db->query("SELECT id, warehouse_code, warehouse_name FROM warehouses WHERE status='active' ORDER BY warehouse_name")->fetchAll();
$defaultWh = (int)($settings['default_warehouse_id'] ?? 1);
$locations = $db->prepare("SELECT id, location_code, location_name FROM locations WHERE warehouse_id = ? AND status='active' ORDER BY location_name");
$locations->execute([$defaultWh]);
$locations = $locations->fetchAll();
$defaultLoc = (int)($settings['default_location_id'] ?? 0);
?>

<div class="mb-6">
    <a href="<?= url('modules/inventory/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Inventory</a>
</div>

<div class="max-w-2xl bg-white rounded-lg shadow p-6">
    <h1 class="text-xl font-bold text-gray-800 mb-4">Inventory Settings</h1>

    <?php if ($errors): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Allow Negative Stock</label>
                <select name="allow_negative_stock" class="w-full border rounded px-3 py-2">
                    <option value="1" <?= !empty($settings['allow_negative_stock']) ? 'selected' : '' ?>>Yes</option>
                    <option value="0" <?= empty($settings['allow_negative_stock']) ? 'selected' : '' ?>>No</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Default Costing Method</label>
                <select name="default_costing_method" class="w-full border rounded px-3 py-2">
                    <?php foreach (['FIFO','LIFO','AVG','STD','MA'] as $m): ?>
                    <option value="<?= $m ?>" <?= ($settings['default_costing_method'] ?? 'AVG') === $m ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Default Warehouse</label>
                <select name="default_warehouse_id" id="def_wh" class="w-full border rounded px-3 py-2">
                    <option value="">—</option>
                    <?php foreach ($warehouses as $w): ?>
                    <option value="<?= $w['id'] ?>" <?= $defaultWh == $w['id'] ? 'selected' : '' ?>><?= sanitize($w['warehouse_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Default Location</label>
                <select name="default_location_id" id="def_loc" class="w-full border rounded px-3 py-2">
                    <option value="">—</option>
                    <?php foreach ($locations as $l): ?>
                    <option value="<?= $l['id'] ?>" <?= $defaultLoc == $l['id'] ? 'selected' : '' ?>><?= sanitize($l['location_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Stock Movement Prefix</label>
                <input type="text" name="stock_movement_prefix" value="<?= sanitize($settings['stock_movement_prefix'] ?? 'SM') ?>" class="w-full border rounded px-3 py-2" maxlength="10">
            </div>
            <div class="flex items-center gap-6 pt-6">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="require_adj_approval" value="1" <?= !empty($settings['require_adj_approval']) ? 'checked' : '' ?>>
                    <span class="text-sm text-gray-700">Require Adjustment Approval</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="require_transfer_approval" value="1" <?= !empty($settings['require_transfer_approval']) ? 'checked' : '' ?>>
                    <span class="text-sm text-gray-700">Require Transfer Approval</span>
                </label>
            </div>
        </div>

        <div class="pt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Save Settings</button>
        </div>
    </form>
</div>

<script>
document.getElementById('def_wh').addEventListener('change', function() {
    var wh = this.value;
    var locSelect = document.getElementById('def_loc');
    if (!wh) { locSelect.innerHTML = '<option value="">--</option>'; return; }
    fetch('modules/inventory/ajax_get_locations.php?warehouse_id=' + wh)
        .then(function(r){ return r.json(); })
        .then(function(data){
            locSelect.innerHTML = '<option value="">--</option>';
            data.forEach(function(l){
                var opt = document.createElement('option');
                opt.value = l.id; opt.textContent = l.location_name;
                locSelect.appendChild(opt);
            });
        });
});
</script>

<?php require_once '../../includes/footer.php'; ?>

