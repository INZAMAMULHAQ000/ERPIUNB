<?php
$pageTitle = 'Create Stock Adjustment';
require_once '../../includes/header.php';
require_once __DIR__ . '/InventoryEngine.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('inventory.adjust')) { die('Access Denied'); }
$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) { clearOldInput(); }

$settings = InventoryEngine::getSettings($db);
$warehouses = $db->query("SELECT id, warehouse_code, warehouse_name FROM warehouses WHERE status='active' ORDER BY warehouse_name")->fetchAll();
$defaultWh = (int)($settings['default_warehouse_id'] ?? 1);
$locations = $db->prepare("SELECT id, location_code, location_name FROM locations WHERE warehouse_id=? AND status='active' ORDER BY location_name");
$locations->execute([$defaultWh]);
$locations = $locations->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid request token.'; }
    $whId     = (int)($_POST['warehouse_id'] ?? 0);
    $locId    = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null;
    $itemId   = (int)($_POST['item_id'] ?? 0);
    $counted  = (float)($_POST['counted_quantity'] ?? 0);
    $reason   = trim($_POST['reason'] ?? '');
    $notes    = trim($_POST['notes'] ?? '');

    if (!$whId || !$itemId) $errors[] = 'Warehouse and item are required.';
    if ($reason === '') $errors[] = 'Reason is required.';

    // Get current stock at this warehouse/location
    $current = InventoryEngine::getCurrentStock($db, $itemId, $whId, $locId);
    $systemQty = (float)($current['qty_on_hand'] ?? 0);
    $variance  = round($counted - $systemQty, 3);

    $adjType = $variance > 0 ? 'gain' : ($variance < 0 ? 'loss' : '');

    if (empty($errors) && $adjType === '') {
        $errors[] = 'Counted quantity equals system quantity — no adjustment needed.';
    }

    // Attachment
    $attachmentPath = null;
    if (!empty($_FILES['attachment']['tmp_name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $dir = __DIR__ . '/../../../uploads/inventory/adjustments';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $safe = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['attachment']['name']));
        move_uploaded_file($_FILES['attachment']['tmp_name'], $dir . '/' . $safe);
        $attachmentPath = 'uploads/inventory/adjustments/' . $safe;
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();
            $adjNumber = InventoryEngine::nextAdjustmentNumber($db);
            $stmt = $db->prepare("
                INSERT INTO inventory_adjustments
                    (adjustment_number, company_id, warehouse_id, location_id, item_id,
                     current_qty, counted_qty, variance_qty, adjustment_type,
                     unit_cost, variance_value, reason, notes, attachment_path,
                     status, requires_approval, created_by, created_at)
                VALUES
                    (:num, 1, :wh, :loc, :item, :cur, :count, :var, :atype,
                     :uc, :val, :reason, :notes, :att,
                     :status, :appr, :uid, NOW())
            ");
            $requiresAppr = (int)$settings['require_adj_approval'];
            $status = $requiresAppr ? 'pending_approval' : 'approved';
            $unitCost = (float)($current['avg_unit_cost'] ?? 0);
            $varianceValue = abs($variance) * $unitCost;

            $stmt->execute([
                ':num'    => $adjNumber, ':wh'  => $whId, ':loc' => $locId,
                ':item'   => $itemId, ':cur' => $systemQty, ':count' => $counted,
                ':var'    => $variance, ':atype' => $adjType,
                ':uc'     => $unitCost, ':val' => $varianceValue,
                ':reason' => $reason, ':notes' => $notes,
                ':att'    => $attachmentPath, ':status' => $status,
                ':appr'   => $requiresAppr, ':uid' => $_SESSION['user_id'],
            ]);
            $adjId = (int)$db->lastInsertId();

            if ($attachmentPath) {
                $db->prepare("
                    INSERT INTO inventory_adjustment_attachments
                        (adjustment_id, original_name, stored_path, file_size, uploaded_by)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([
                    $adjId,
                    $_FILES['attachment']['name'] ?? 'attachment',
                    $attachmentPath,
                    (int)($_FILES['attachment']['size'] ?? 0),
                    $_SESSION['user_id'],
                ]);
            }

            logAudit($_SESSION['user_id'], 'Created adjustment ' . $adjNumber, 'inventory');

            if ($status === 'approved') {
                $adj = $db->prepare("SELECT * FROM inventory_adjustments WHERE id = ?")->execute([$adjId]);
                $adj = $db->query("SELECT * FROM inventory_adjustments WHERE id = $adjId")->fetch();
                $result = InventoryEngine::finalizeAdjustment($db, $adj, $_SESSION['user_id']);
                if ($result['success']) {
                    $db->prepare("UPDATE inventory_adjustments SET status='finalised', finalised_at=NOW(), finalised_by=?, movement_id=? WHERE id=?")
                       ->execute([$_SESSION['user_id'], $result['movement_id'] ?? null, $adjId]);
                    logAudit($_SESSION['user_id'], 'Finalised adjustment ' . $adjNumber, 'inventory');
                }
            }

            $db->commit();
            flash('success', 'Adjustment ' . $adjNumber . ' created' . ($status === 'pending_approval' ? ' (pending approval)' : ''));
            to('modules/inventory/adjustments.php');
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errors[] = 'Failed: ' . $e->getMessage();
        }
    }
}

$items = $db->query("SELECT id, item_code, item_name FROM inventory_items WHERE status='active' ORDER BY item_name")->fetchAll();
?>

<div class="mb-6">
    <a href="<?= url('modules/inventory/adjustments.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Adjustments</a>
</div>

<div class="max-w-2xl bg-white rounded-lg shadow p-6">
    <h1 class="text-xl font-bold text-gray-800 mb-4">New Stock Adjustment</h1>

    <?php if ($errors): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse <span class="text-red-600">*</span></labe>
                <select name="warehouse_id" id="adj_warehouse" required class="w-full border rounded px-3 py-2">
                    <option value="">Select</option>
                    <?php foreach ($warehouses as $w): ?>
                    <option value="<?= $w['id'] ?>" <?= $defaultWh==$w['id']?'selected':'' ?>><?= sanitize($w['warehouse_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                <select name="location_id" id="adj_location" class="w-full border rounded px-3 py-2">
                    <option value="">None</option>
                    <?php foreach ($locations as $l): ?>
                    <option value="<?= $l['id'] ?>"><?= sanitize($l['location_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Item <span class="text-red-600">*</span></labe>
                <select name="item_id" required class="w-full border rounded px-3 py-2">
                    <option value="">Select</option>
                    <?php foreach ($items as $i): ?>
                    <option value="<?= $i['id'] ?>"><?= sanitize($i['item_code'] . ' - ' . $i['item_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">System Quantity</label>
                <input type="text" id="system_qty" readonly class="w-full border rounded px-3 py-2 bg-gray-100" value="—">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Counted Quantity <span class="text-red-600">*</span></labe>
                <input type="number" step="0.001" name="counted_quantity" id="counted_qty" required class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Variance</label>
                <input type="text" id="variance_display" readonly class="w-full border rounded px-3 py-2 bg-gray-100" value="—">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Reason <span class="text-red-600">*</span></labe>
                <select name="reason" required class="w-full border rounded px-3 py-2">
                    <option value="">Select</option>
                    <?php foreach (['Damaged','Lost','Found','Counting error','Expired','Spoilage','Other'] as $r): ?>
                    <option value="<?= $r ?>"><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="2" class="w-full border rounded px-3 py-2"></textarea>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Attachment</label>
                <input type="file" name="attachment" class="w-full border rounded px-3 py-2 text-sm">
            </div>
        </div>
        <div class="pt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Create Adjustment</button>
            <a href="<?= url('modules/inventory/adjustments.php') ?>" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>

<script>
function refreshQty() {
    var wh = document.getElementById('adj_warehouse').value;
    var loc = document.getElementById('adj_location').value;
    var itemId = document.querySelector('select[name=item_id]').value;
    var sysEl = document.getElementById('system_qty');
    if (!wh || !itemId) { sysEl.value = '—'; return; }
    fetch('modules/inventory/ajax_get_stock.php?item_id=' + itemId + '&warehouse_id=' + wh + '&location_id=' + (loc || ''))
        .then(function(r){ return r.json(); })
        .then(function(d){
            sysEl.value = (d.qty_on_hand || 0).toFixed(3);
            updateVariance();
        });
}
function updateVariance() {
    var sys = parseFloat(document.getElementById('system_qty').value) || 0;
    var cnt = parseFloat(document.getElementById('counted_qty').value) || 0;
    var v = (cnt - sys);
    var el = document.getElementById('variance_display');
    el.value = (v > 0 ? '+' : '') + v.toFixed(3);
}
document.getElementById('adj_warehouse').addEventListener('change', refreshQty);
document.querySelector('select[name=item_id]').addEventListener('change', refreshQty);
document.getElementById('counted_qty').addEventListener('input', updateVariance);
document.getElementById('adj_location').addEventListener('change', refreshQty);
</script>

<?php require_once '../../includes/footer.php'; ?>

