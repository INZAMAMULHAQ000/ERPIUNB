<?php
$pageTitle = 'Opening Stock';

require_once '../../includes/header.php';
require_once __DIR__ . '/InventoryEngine.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('inventory.create')) {
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

$defaultWh = (int)($settings['default_warehouse_id'] ?? 1);

$locations = $db->prepare("
    SELECT id, location_code, location_name
    FROM locations
    WHERE warehouse_id=? 
      AND status='active'
    ORDER BY location_name
");

$locations->execute([$defaultWh]);
$locations = $locations->fetchAll();

$items = $db->query("
    SELECT id, item_code, item_name
    FROM inventory_items
    WHERE status='active'
    ORDER BY item_name
")->fetchAll();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    }

    $whId = (int)($_POST['warehouse_id'] ?? 0);

    $locId = !empty($_POST['location_id'])
        ? (int)$_POST['location_id']
        : null;

    $itemId = (int)($_POST['item_id'] ?? 0);

    $qty = (float)($_POST['quantity'] ?? 0);

    $ucost = (float)($_POST['unit_cost'] ?? 0);


    if (!$whId || !$itemId) {
        $errors[] = 'Warehouse and item required.';
    }

    if ($qty < 0) {
        $errors[] = 'Quantity must be >= 0.';
    }


    if (empty($errors)) {

        try {

            $result = InventoryEngine::createOpeningStock($db, [
                'company_id'       => 1,
                'item_id'          => $itemId,
                'warehouse_id'     => $whId,
                'location_id'      => $locId,
                'quantity'         => $qty,
                'unit_cost'        => $ucost,
                'reference_number' => 'Opening Stock',
                'notes'            => 'Opening balance',
                'created_by'       => $_SESSION['user_id'],
                'status'           => InventoryEngine::STATUS_POSTED,
            ]);


            logAudit(
                $_SESSION['user_id'],
                'Created opening stock for item #' . $itemId,
                'inventory'
            );


            flash(
                'success',
                'Opening stock created: ' . number_format($qty, 3)
            );


            to('modules/inventory/current_stock.php');
        } catch (Exception $e) {

            $errors[] = 'Failed: ' . $e->getMessage();
        }
    }
}
?>


<div class="mb-6">

    <a
        href="<?= url('modules/inventory/index.php') ?>"
        class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>
        Back
    </a>

</div>


<div class="w-full bg-white rounded-lg shadow p-6">

    <h1 class="text-xl font-bold text-gray-800 mb-4">
        Opening Stock Entry
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
        id="openingStockForm"
        novalidate
        class="space-y-4">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= csrfToken() ?>">


        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">


            <!-- Warehouse -->
            <div>

                <label
                    for="os_wh"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Warehouse
                    <span class="text-red-600">*</span>
                </label>

                <select
                    name="warehouse_id"
                    id="os_wh"
                    class="w-full border rounded px-3 py-2">

                    <option value="">Select</option>

                    <?php foreach ($warehouses as $w): ?>

                        <option
                            value="<?= $w['id'] ?>"
                            <?= $defaultWh == $w['id'] ? 'selected' : '' ?>>
                            <?= sanitize($w['warehouse_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Location -->
            <div>

                <label
                    for="os_loc"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Location
                </label>

                <select
                    name="location_id"
                    id="os_loc"
                    class="w-full border rounded px-3 py-2">

                    <option value="">None</option>

                    <?php foreach ($locations as $l): ?>

                        <option value="<?= $l['id'] ?>">
                            <?= sanitize($l['location_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Item -->
            <div class="md:col-span-2">

                <label
                    for="os_item"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Item
                    <span class="text-red-600">*</span>
                </label>

                <select
                    name="item_id"
                    id="os_item"
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
                    for="os_quantity"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Quantity
                    <span class="text-red-600">*</span>
                </label>

                <input
                    type="number"
                    step="0.001"
                    name="quantity"
                    id="os_quantity"
                    min="0"
                    class="w-full border rounded px-3 py-2">

            </div>


            <!-- Unit Cost -->
            <div>

                <label
                    for="os_unit_cost"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Unit Cost
                </label>

                <input
                    type="number"
                    step="0.0001"
                    name="unit_cost"
                    id="os_unit_cost"
                    value="0"
                    min="0"
                    class="w-full border rounded px-3 py-2">

            </div>

        </div>


        <div class="pt-4">

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                Create Opening Stock
            </button>

        </div>

    </form>

</div>


<style>
    /* Validation Error */
    #openingStockForm label.error {
        display: block !important;
        width: 100% !important;
        margin: 4px 0 0 0 !important;
        padding: 0 !important;
        clear: both !important;
        color: #dc2626 !important;
        font-size: 12px !important;
        line-height: 16px !important;
        font-weight: 400 !important;
        position: static !important;
    }


    /* Invalid field */
    #openingStockForm input.error,
    #openingStockForm select.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }


    /* Valid field */
    #openingStockForm input.valid,
    #openingStockForm select.valid {
        border-color: #22c55e !important;
        box-shadow: none !important;
    }
</style>


<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- jQuery Validation -->
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>


<script>
    $(document).ready(function() {


        /*
         * Warehouse -> Location AJAX
         * Existing logic preserved
         */
        $('#os_wh').on('change', function() {

            var wh = $(this).val();

            var loc = $('#os_loc');


            if (!wh) {

                loc.html('<option value="">None</option>');

                return;
            }


            fetch(
                    'modules/inventory/ajax_get_locations.php?warehouse_id=' + wh
                )
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {

                    loc.html('<option value="">None</option>');

                    data.forEach(function(l) {

                        var opt = document.createElement('option');

                        opt.value = l.id;

                        opt.textContent = l.location_name;

                        loc.append(opt);

                    });

                })
                .catch(function() {

                    loc.html('<option value="">None</option>');

                });

        });



        /*
         * jQuery Validation
         */
        $('#openingStockForm').validate({

            errorElement: 'label',

            errorClass: 'error',


            rules: {

                warehouse_id: {
                    required: true
                },


                item_id: {
                    required: true
                },


                quantity: {
                    required: true,
                    number: true,
                    min: 0
                },


                unit_cost: {
                    number: true,
                    min: 0
                }

            },


            messages: {

                warehouse_id: {
                    required: 'Please select a warehouse.'
                },


                item_id: {
                    required: 'Please select an item.'
                },


                quantity: {
                    required: 'Please enter quantity.',
                    number: 'Please enter a valid quantity.',
                    min: 'Quantity cannot be negative.'
                },


                unit_cost: {
                    number: 'Please enter a valid unit cost.',
                    min: 'Unit cost cannot be negative.'
                }

            },


            /*
             * Select validation error BELOW select
             */
            errorPlacement: function(error, element) {

                if (element.is('select')) {

                    error.appendTo(element.parent());

                } else {

                    error.insertAfter(element);

                }

            },


            /*
             * Highlight invalid field
             */
            highlight: function(element) {

                $(element)
                    .addClass('border-red-500')
                    .removeClass('border-green-500');

            },


            /*
             * Remove error styling
             */
            unhighlight: function(element) {

                $(element)
                    .removeClass('border-red-500')
                    .addClass('border-green-500');

            },


            /*
             * Submit only after validation
             */
            submitHandler: function(form) {

                form.submit();

            }

        });

    });
</script>


<?php require_once '../../includes/footer.php'; ?>