<?php
$pageTitle = 'Stock Reservation';
require_once '../../includes/header.php';
require_once __DIR__ . '/InventoryEngine.php';
require_once '../../helpers/security.php';

if (!hasPermission('inventory.view')) {
    die('Access Denied');
}

$db = getDB();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrf($csrfToken)) {
        $error = 'Invalid CSRF token.';
    } else {
        try {

            if ($action === 'create') {

                $result = InventoryEngine::createReservation($db, [
                    'company_id'       => (int)$_POST['company_id'],
                    'item_id'          => (int)$_POST['item_id'],
                    'warehouse_id'     => (int)$_POST['warehouse_id'],
                    'location_id'      => $_POST['location_id'] ?? null,
                    'quantity'         => (float)$_POST['quantity'],
                    'reference_type'   => $_POST['reference_type'] ?: null,
                    'reference_id'     => !empty($_POST['reference_id'])
                        ? (int)$_POST['reference_id']
                        : null,
                    'reference_number' => $_POST['reference_number'] ?: null,
                    'notes'            => $_POST['notes'] ?: null,
                    'created_by'       => $_SESSION['user_id'],
                ]);

                $message = 'Reservation created successfully. ID: '
                    . $result['reservation_id'];
            } elseif ($action === 'release') {

                $id = (int)$_POST['id'];

                InventoryEngine::releaseReservation(
                    $db,
                    $id,
                    $_SESSION['user_id'],
                    $_POST['reason'] ?? ''
                );

                $message = 'Reservation released successfully.';
            } elseif ($action === 'consume') {

                $id = (int)$_POST['id'];

                $result = InventoryEngine::consumeReservation(
                    $db,
                    $id,
                    $_SESSION['user_id'],
                    $_POST['reason'] ?? ''
                );

                $message = 'Reservation consumed successfully. Movement ID: '
                    . ($result['movement_id'] ?? '');
            } elseif ($action === 'cancel') {

                $id = (int)$_POST['id'];

                InventoryEngine::cancelReservation(
                    $db,
                    $id,
                    $_SESSION['user_id'],
                    $_POST['reason'] ?? ''
                );

                $message = 'Reservation cancelled successfully.';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

$csrfToken = csrfToken();

$companies = $db->query("
    SELECT id, company_name
    FROM companies
    WHERE status='active'
    ORDER BY company_name
")->fetchAll();

$warehouses = $db->query("
    SELECT id, warehouse_code, warehouse_name
    FROM warehouses
    WHERE status='active'
    ORDER BY warehouse_name
")->fetchAll();

$items = $db->query("
    SELECT id, item_code, item_name
    FROM inventory_items
    WHERE status='active'
    ORDER BY item_name
")->fetchAll();

$reservationId = $_GET['id'] ?? '';
$reservation = null;

if ($reservationId) {

    $r = $db->prepare("
        SELECT *
        FROM inventory_reservations
        WHERE id = ?
    ");

    $r->execute([(int)$reservationId]);

    $reservation = $r->fetch();
}
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">
        <?= $reservation ? 'Reservation Details' : 'New Stock Reservation' ?>
    </h1>

    <p class="text-gray-600">
        Inventory Controls — Phase I3
    </p>
</div>

<!-- Success Message -->
<?php if ($message): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?= sanitize($message) ?>
    </div>
<?php endif; ?>

<!-- Error Message -->
<?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <?= sanitize($error) ?>
    </div>
<?php endif; ?>


<?php if ($reservation): ?>

    <!-- Reservation Details -->
    <div class="bg-white rounded-lg shadow p-6">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <div>
                <span class="font-medium">ID:</span>
                <?= $reservation['id'] ?>
            </div>

            <div>
                <span class="font-medium">Status:</span>
                <?= InventoryEngine::getStatusBadge($reservation['status']) ?>
            </div>

            <div>
                <span class="font-medium">Item:</span>
                <?= sanitize($reservation['item_id']) ?>
            </div>

            <div>
                <span class="font-medium">Warehouse:</span>
                <?= sanitize($reservation['warehouse_id']) ?>
            </div>

            <div>
                <span class="font-medium">Quantity:</span>
                <?= number_format($reservation['quantity'], 3) ?>
            </div>

            <div>
                <span class="font-medium">Reference:</span>
                <?= sanitize($reservation['reference_number'] ?? '-') ?>
            </div>

            <div>
                <span class="font-medium">Created:</span>
                <?= date('Y-m-d H:i', strtotime($reservation['created_at'])) ?>
            </div>

            <div>
                <span class="font-medium">Released:</span>

                <?= $reservation['released_at']
                    ? date('Y-m-d H:i', strtotime($reservation['released_at']))
                    : '-' ?>
            </div>

        </div>

        <div class="mt-4">
            <a
                href="reservations.php"
                class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">
                Back to List
            </a>
        </div>

    </div>

<?php else: ?>

    <!-- Create Reservation -->
    <div class="bg-white rounded-lg shadow p-6">

        <form
            method="post"
            id="reservationForm"
            class="space-y-4"
            novalidate>

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <input
                type="hidden"
                name="action"
                value="create">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <!-- Company -->
                <div>
                    <label
                        for="company_id"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Company
                        <span class="text-red-600">*</span>
                    </label>

                    <select
                        name="company_id"
                        id="company_id"
                        class="w-full border rounded px-3 py-2">
                        <option value="">Select Company</option>

                        <?php foreach ($companies as $c): ?>

                            <option value="<?= htmlspecialchars($c['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= sanitize($c['company_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>


                <!-- Item -->
                <div>
                    <label
                        for="item_id"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Item
                        <span class="text-red-600">*</span>
                    </label>

                    <select
                        name="item_id"
                        id="item_id"
                        class="w-full border rounded px-3 py-2">
                        <option value="">Select Item</option>

                        <?php foreach ($items as $i): ?>

                            <option value="<?= htmlspecialchars($i['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= sanitize($i['item_code'] . ' - ' . $i['item_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>


                <!-- Warehouse -->
                <div>
                    <label
                        for="warehouse_id"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Warehouse
                        <span class="text-red-600">*</span>
                    </label>

                    <select
                        name="warehouse_id"
                        id="warehouse_id"
                        class="w-full border rounded px-3 py-2">
                        <option value="">Select Warehouse</option>

                        <?php foreach ($warehouses as $w): ?>

                            <option value="<?= htmlspecialchars($w['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= sanitize($w['warehouse_code'] . ' - ' . $w['warehouse_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>


                <!-- Quantity -->
                <div>
                    <label
                        for="quantity"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Quantity
                        <span class="text-red-600">*</span>
                    </label>

                    <input
                        type="number"
                        step="any"
                        min="0.001"
                        name="quantity"
                        id="quantity"
                        class="w-full border rounded px-3 py-2"
                        placeholder="Enter quantity">
                </div>


                <!-- Reference Type -->
                <div>
                    <label
                        for="reference_type"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Reference Type
                    </label>

                    <input
                        type="text"
                        name="reference_type"
                        id="reference_type"
                        maxlength="100"
                        class="w-full border rounded px-3 py-2"
                        placeholder="e.g. sales_order">
                </div>


                <!-- Reference ID -->
                <div>
                    <label
                        for="reference_id"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Reference ID
                    </label>

                    <input
                        type="number"
                        name="reference_id"
                        id="reference_id"
                        min="1"
                        class="w-full border rounded px-3 py-2"
                        placeholder="Enter reference ID">
                </div>


                <!-- Reference Number -->
                <div>
                    <label
                        for="reference_number"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Reference Number
                    </label>

                    <input
                        type="text"
                        name="reference_number"
                        id="reference_number"
                        maxlength="100"
                        class="w-full border rounded px-3 py-2"
                        placeholder="Enter reference number">
                </div>


                <!-- Notes -->
                <div>
                    <label
                        for="notes"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Notes
                    </label>

                    <input
                        type="text"
                        name="notes"
                        id="notes"
                        maxlength="500"
                        class="w-full border rounded px-3 py-2"
                        placeholder="Enter notes">
                </div>

            </div>


            <!-- Buttons -->
            <div class="flex gap-2 pt-2">

                <button
                    type="submit"
                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Create Reservation
                </button>

                <a
                    href="reservations.php"
                    class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">
                    Cancel
                </a>

            </div>

        </form>

    </div>

<?php endif; ?>


<!-- Validation CSS -->
<style>
    #reservationForm label.error,
    #reservationForm span.error {
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

    #reservationForm input.error,
    #reservationForm select.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    #reservationForm input.valid,
    #reservationForm select.valid {
        border-color: #22c55e !important;
        box-shadow: none !important;
    }
</style>


<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- jQuery Validation -->
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>


<script>
    $(document).ready(function() {

        /*
         * Stock Reservation Form Validation
         */
        $('#reservationForm').validate({

            rules: {

                company_id: {
                    required: true
                },

                item_id: {
                    required: true
                },

                warehouse_id: {
                    required: true
                },

                quantity: {
                    required: true,
                    number: true,
                    min: 0.001
                },

                reference_type: {
                    maxlength: 100
                },

                reference_id: {
                    digits: true,
                    min: 1
                },

                reference_number: {
                    maxlength: 100
                },

                notes: {
                    maxlength: 500
                }

            },


            messages: {

                company_id: {
                    required: "Please select a company."
                },

                item_id: {
                    required: "Please select an item."
                },

                warehouse_id: {
                    required: "Please select a warehouse."
                },

                quantity: {
                    required: "Please enter quantity.",
                    number: "Please enter a valid quantity.",
                    min: "Quantity must be greater than 0."
                },

                reference_type: {
                    maxlength: "Reference Type cannot exceed 100 characters."
                },

                reference_id: {
                    digits: "Reference ID must contain numbers only.",
                    min: "Reference ID must be greater than 0."
                },

                reference_number: {
                    maxlength: "Reference Number cannot exceed 100 characters."
                },

                notes: {
                    maxlength: "Notes cannot exceed 500 characters."
                }

            },


            /*
             * Dropdown error message
             * will appear below the dropdown
             */
            errorPlacement: function(error, element) {

                if (element.is('select')) {

                    error.appendTo(element.parent());

                } else {

                    error.insertAfter(element);

                }

            },


            /*
             * Add error class
             */
            highlight: function(element) {

                $(element)
                    .addClass('error')
                    .removeClass('valid');

            },


            /*
             * Remove error class
             */
            unhighlight: function(element) {

                $(element)
                    .removeClass('error')
                    .addClass('valid');

            },


            /*
             * Validate on submit
             */
            submitHandler: function(form) {

                form.submit();

            }

        });


        /*
         * Quantity:
         * Allow only numbers and decimal point
         */
        $('#quantity').on('input', function() {

            let value = $(this).val();

            value = value.replace(/[^0-9.]/g, '');

            const parts = value.split('.');

            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }

            $(this).val(value);

        });


        /*
         * Reference ID:
         * Allow only numbers
         */
        $('#reference_id').on('input', function() {

            this.value = this.value.replace(/[^0-9]/g, '');

        });


        /*
         * Reference Type:
         * Prevent leading spaces
         */
        $('#reference_type').on('input', function() {

            this.value = this.value.replace(/^\s+/, '');

        });


        /*
         * Reference Number:
         * Prevent leading spaces
         */
        $('#reference_number').on('input', function() {

            this.value = this.value.replace(/^\s+/, '');

        });


        /*
         * Notes:
         * Prevent leading spaces
         */
        $('#notes').on('input', function() {

            this.value = this.value.replace(/^\s+/, '');

        });

    });
</script>


<?php require_once '../../includes/footer.php'; ?>