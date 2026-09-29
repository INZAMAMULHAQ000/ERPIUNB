<?php
$pageTitle = 'Stock Transfer';
require_once '../../includes/header.php';
require_once __DIR__ . '/InventoryEngine.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('inventory.transfer')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$settings = InventoryEngine::getSettings($db);

$warehouses = $db->query("
    SELECT id, warehouse_code, warehouse_name
    FROM warehouses
    WHERE status='active'
    ORDER BY warehouse_name
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    }

    $srcWh  = (int)($_POST['source_warehouse_id'] ?? 0);
    $srcLoc = !empty($_POST['source_location_id'])
        ? (int)$_POST['source_location_id']
        : null;

    $destWh = (int)($_POST['dest_warehouse_id'] ?? 0);
    $destLoc = !empty($_POST['dest_location_id'])
        ? (int)$_POST['dest_location_id']
        : null;

    $itemId = (int)($_POST['item_id'] ?? 0);
    $qty    = (float)($_POST['quantity'] ?? 0);
    $notes  = trim($_POST['notes'] ?? '');

    if (!$srcWh || !$destWh || !$itemId) {
        $errors[] = 'Warehouses and item are required.';
    }

    if ($srcWh === $destWh) {
        $errors[] = 'Source and destination warehouses must differ.';
    }

    if ($qty <= 0) {
        $errors[] = 'Quantity must be > 0.';
    }

    if (empty($errors)) {

        try {

            $db->beginTransaction();

            $transferNumber = InventoryEngine::nextTransferNumber($db);

            $unitCostRow = $db->prepare("
                SELECT avg_unit_cost
                FROM inventory_stock_balances
                WHERE item_id=?
                  AND warehouse_id=?
                  AND (location_id IS NULL OR location_id=?)
                ORDER BY id DESC
                LIMIT 1
            ");

            $unitCostRow->execute([
                $itemId,
                $srcWh,
                $srcLoc ?? 0
            ]);

            $unitCost = (float)($unitCostRow->fetchColumn() ?: 0);

            $stmt = $db->prepare("
                INSERT INTO inventory_stock_transfers
                    (
                        transfer_number,
                        company_id,
                        source_warehouse_id,
                        source_location_id,
                        dest_warehouse_id,
                        dest_location_id,
                        item_id,
                        quantity,
                        unit_cost,
                        notes,
                        transfer_reason,
                        status,
                        created_by
                    )
                VALUES
                    (
                        :num,
                        1,
                        :src_wh,
                        :src_loc,
                        :dest_wh,
                        :dest_loc,
                        :item,
                        :qty,
                        :uc,
                        :notes,
                        :reason,
                        :status,
                        :uid
                    )
            ");

            $reqAppr = (int)$settings['require_transfer_approval'];

            $status = $reqAppr
                ? 'pending_approval'
                : 'approved';

            $stmt->execute([
                ':num'     => $transferNumber,
                ':src_wh'  => $srcWh,
                ':src_loc' => $srcLoc,
                ':dest_wh' => $destWh,
                ':dest_loc' => $destLoc,
                ':item'    => $itemId,
                ':qty'     => $qty,
                ':uc'      => $unitCost,
                ':notes'   => $notes,
                ':reason'  => 'Stock transfer',
                ':status'  => $status,
                ':uid'     => $_SESSION['user_id'],
            ]);

            $transferId = (int)$db->lastInsertId();

            if ($status === 'approved') {

                $result = InventoryEngine::executeTransfer(
                    $db,
                    [
                        'id'                  => $transferId,
                        'company_id'          => 1,
                        'source_warehouse_id' => $srcWh,
                        'source_location_id'  => $srcLoc,
                        'dest_warehouse_id'   => $destWh,
                        'dest_location_id'    => $destLoc,
                        'item_id'             => $itemId,
                        'quantity'            => $qty,
                        'unit_cost'           => $unitCost,
                        'notes'               => $notes,
                        'transfer_number'     => $transferNumber,
                    ],
                    $_SESSION['user_id']
                );

                if ($result['success']) {

                    $now = date('Y-m-d H:i:s');

                    $db->prepare("
                        UPDATE inventory_stock_transfers
                        SET
                            status='posted',
                            posted_at=?,
                            posted_by=?,
                            movement_out_id=?,
                            movement_in_id=?
                        WHERE id=?
                    ")->execute([
                        $now,
                        $_SESSION['user_id'],
                        $result['movement_out_id'],
                        $result['movement_in_id'],
                        $transferId
                    ]);

                    logAudit(
                        $_SESSION['user_id'],
                        'Posted transfer ' . $transferNumber,
                        'inventory'
                    );
                }
            }

            $db->commit();

            flash(
                'success',
                'Transfer ' . $transferNumber . ' created' .
                    ($status === 'pending_approval'
                        ? ' (pending approval)'
                        : '')
            );

            to('modules/inventory/transfers.php');
        } catch (Exception $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $errors[] = 'Transfer failed: ' . $e->getMessage();
        }
    }
}

$items = $db->query("
    SELECT id, item_code, item_name
    FROM inventory_items
    WHERE status='active'
    ORDER BY item_name
")->fetchAll();

$destWhId = (int)($_POST['dest_warehouse_id'] ?? 0);
$srcWhId  = (int)($_POST['source_warehouse_id'] ?? 0);

$srcLocs = $db->prepare("
    SELECT id, location_code, location_name
    FROM locations
    WHERE warehouse_id=?
      AND status='active'
    ORDER BY location_name
");

$srcLocs->execute([
    $srcWhId ?: 0
]);

$srcLocs = $srcLocs->fetchAll();

$destLocs = $db->prepare("
    SELECT id, location_code, location_name
    FROM locations
    WHERE warehouse_id=?
      AND status='active'
    ORDER BY location_name
");

$destLocs->execute([
    $destWhId ?: 0
]);

$destLocs = $destLocs->fetchAll();
?>

<style>
    /* Validation error */
    #stockTransferForm label.error {
        display: block !important;
        width: 100% !important;
        margin: 4px 0 0 0 !important;
        padding: 0 !important;
        color: #dc2626 !important;
        font-size: 12px !important;
        font-weight: 400 !important;
        line-height: 16px !important;
        position: static !important;
    }

    /* Input/select error border */
    #stockTransferForm input.error,
    #stockTransferForm select.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    /* Valid field */
    #stockTransferForm input.valid,
    #stockTransferForm select.valid {
        border-color: #22c55e !important;
        box-shadow: none !important;
    }
</style>

<div class="mb-6">
    <a
        href="<?= url('modules/inventory/transfers.php') ?>"
        class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Transfers
    </a>
</div>

<div class="w-full bg-white rounded-lg shadow p-6">

    <h1 class="text-xl font-bold text-gray-800 mb-4">
        New Stock Transfer
    </h1>

    <?php if ($errors): ?>

        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">

            <ul class="list-disc list-inside">

                <?php foreach ($errors as $e): ?>

                    <li><?= sanitize($e) ?></li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>

    <form
        method="POST"
        id="stockTransferForm"
        class="space-y-4"
        novalidate>

        <input
            type="hidden"
            name="csrf_token"
            value="<?= csrfToken() ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Source Warehouse -->
            <div>

                <label
                    class="block text-sm font-medium text-gray-700 mb-1"
                    for="src_wh">
                    Source Warehouse
                    <span class="text-red-600">*</span>
                </label>

                <select
                    name="source_warehouse_id"
                    id="src_wh"
                    class="w-full border rounded px-3 py-2">

                    <option value="">Select</option>

                    <?php foreach ($warehouses as $w): ?>

                        <option
                            value="<?= $w['id'] ?>"
                            <?= $srcWhId == $w['id'] ? 'selected' : '' ?>>
                            <?= sanitize($w['warehouse_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- Source Location -->
            <div>

                <label
                    class="block text-sm font-medium text-gray-700 mb-1"
                    for="src_loc">
                    Source Location
                </label>

                <select
                    name="source_location_id"
                    id="src_loc"
                    class="w-full border rounded px-3 py-2">

                    <option value="">None</option>

                    <?php foreach ($srcLocs as $l): ?>

                        <option value="<?= $l['id'] ?>">
                            <?= sanitize($l['location_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- Destination Warehouse -->
            <div>

                <label
                    class="block text-sm font-medium text-gray-700 mb-1"
                    for="dest_wh">
                    Destination Warehouse
                    <span class="text-red-600">*</span>
                </label>

                <select
                    name="dest_warehouse_id"
                    id="dest_wh"
                    class="w-full border rounded px-3 py-2">

                    <option value="">Select</option>

                    <?php foreach ($warehouses as $w): ?>

                        <option
                            value="<?= $w['id'] ?>"
                            <?= $destWhId == $w['id'] ? 'selected' : '' ?>>
                            <?= sanitize($w['warehouse_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- Destination Location -->
            <div>

                <label
                    class="block text-sm font-medium text-gray-700 mb-1"
                    for="dest_loc">
                    Destination Location
                </label>

                <select
                    name="dest_location_id"
                    id="dest_loc"
                    class="w-full border rounded px-3 py-2">

                    <option value="">None</option>

                    <?php foreach ($destLocs as $l): ?>

                        <option value="<?= $l['id'] ?>">
                            <?= sanitize($l['location_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- Item -->
            <div class="md:col-span-2">

                <label
                    class="block text-sm font-medium text-gray-700 mb-1"
                    for="item_id">
                    Item
                    <span class="text-red-600">*</span>
                </label>

                <select
                    name="item_id"
                    id="item_id"
                    class="w-full border rounded px-3 py-2">

                    <option value="">Select</option>

                    <?php foreach ($items as $i): ?>

                        <option value="<?= $i['id'] ?>">
                            <?= sanitize(
                                $i['item_code'] . ' - ' . $i['item_name']
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- Quantity -->
            <div>

                <label
                    class="block text-sm font-medium text-gray-700 mb-1"
                    for="quantity">
                    Quantity
                    <span class="text-red-600">*</span>
                </label>

                <input
                    type="number"
                    step="0.001"
                    name="quantity"
                    id="quantity"
                    min="0.001"
                    class="w-full border rounded px-3 py-2">

            </div>

            <!-- Notes -->
            <div>

                <label
                    class="block text-sm font-medium text-gray-700 mb-1"
                    for="notes">
                    Notes
                </label>

                <input
                    type="text"
                    name="notes"
                    id="notes"
                    maxlength="1000"
                    class="w-full border rounded px-3 py-2">

            </div>

        </div>

        <div class="pt-4">

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                Create Transfer
            </button>

            <a
                href="<?= url('modules/inventory/transfers.php') ?>"
                class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">
                Cancel
            </a>

        </div>

    </form>

</div>
<style>
    #stockTransferForm label.error {
        display: block !important;
        width: 100% !important;
        margin: 4px 0 0 0 !important;
        padding: 0 !important;
        clear: both !important;
        color: #dc2626 !important;
        font-size: 12px !important;
        font-weight: 400 !important;
        line-height: 16px !important;
        position: static !important;
    }
</style>

<!-- Existing Location AJAX -->
<script>
    function refreshLocs(whId, locId) {

        var locSelect =
            locId === 'src' ?
            document.getElementById('src_loc') :
            document.getElementById('dest_loc');

        if (!whId) {

            locSelect.innerHTML =
                '<option value="">None</option>';

            return;
        }

        fetch(
                'modules/inventory/ajax_get_locations.php?warehouse_id=' +
                encodeURIComponent(whId)
            )
            .then(function(r) {
                return r.json();
            })
            .then(function(data) {

                locSelect.innerHTML =
                    '<option value="">None</option>';

                data.forEach(function(l) {

                    var opt = document.createElement('option');

                    opt.value = l.id;
                    opt.textContent = l.location_name;

                    locSelect.appendChild(opt);

                });

            })
            .catch(function() {

                locSelect.innerHTML =
                    '<option value="">None</option>';

            });
    }

    document.getElementById('src_wh').addEventListener(
        'change',
        function() {
            refreshLocs(this.value, 'src');
        }
    );

    document.getElementById('dest_wh').addEventListener(
        'change',
        function() {
            refreshLocs(this.value, 'dest');
        }
    );
</script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- jQuery Validation -->
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function() {

        // Custom validation:
        // Source and Destination warehouse must be different
        $.validator.addMethod(
            'differentWarehouse',
            function(value, element) {

                return value !== $('#src_wh').val();

            },
            'Source and destination warehouses must be different.'
        );

        $('#stockTransferForm').validate({

            rules: {

                source_warehouse_id: {
                    required: true
                },

                dest_warehouse_id: {
                    required: true,
                    differentWarehouse: true
                },

                item_id: {
                    required: true
                },

                quantity: {
                    required: true,
                    number: true,
                    min: 0.001
                },

                notes: {
                    maxlength: 1000
                }

            },

            messages: {

                source_warehouse_id: {
                    required: 'Please select source warehouse.'
                },

                dest_warehouse_id: {
                    required: 'Please select destination warehouse.',
                    differentWarehouse: 'Source and destination warehouses must be different.'
                },

                item_id: {
                    required: 'Please select an item.'
                },

                quantity: {
                    required: 'Please enter quantity.',
                    number: 'Please enter a valid quantity.',
                    min: 'Quantity must be greater than 0.'
                },

                notes: {
                    maxlength: 'Notes cannot exceed 1000 characters.'
                }

            },

            errorElement: 'label',

            errorClass: 'error',

            errorPlacement: function(error, element) {

                if (element.is('select')) {

                    error.appendTo(element.parent());

                } else {

                    error.insertAfter(element);

                }

            },

            highlight: function(element) {

                $(element)
                    .addClass('error')
                    .removeClass('valid');

            },

            unhighlight: function(element) {

                $(element)
                    .removeClass('error')
                    .addClass('valid');

            },

            submitHandler: function(form) {

                form.submit();

            }

        });

        // Destination warehouse re-validation
        $('#src_wh').on('change', function() {

            $('#dest_wh').valid();

        });

        $('#dest_wh').on('change', function() {

            $(this).valid();

        });

    });
</script>

<?php require_once '../../includes/footer.php'; ?>