<?php
$pageTitle = 'Edit Asset';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('assets.edit')) {
    die('Access Denied');
}

$db = getDB();
$id = $_GET['id'] ?? 0;

$asset = $db->prepare("SELECT * FROM assets WHERE id = ?");
$asset->execute([$id]);
$asset = $asset->fetch();

if (!$asset) {
    die('Asset not found');
}

$categories = $db->query("SELECT id, category_name FROM asset_categories WHERE status = 'active' ORDER BY category_name")->fetchAll();
$vendors = $db->query("SELECT id, vendor_name FROM vendors WHERE status = 'active' ORDER BY vendor_name")->fetchAll();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = [
        'asset_name' => $_POST['asset_name'] ?? '',
        'category_id' => $_POST['category_id'] ?? ''
    ];

    $errors = validate($data, [
        'asset_name' => 'required',
        'category_id' => 'required'
    ]);

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/assets/edit.php?id=' . $id);
    }

    $stmt = $db->prepare("
        UPDATE assets SET
            asset_name = ?,
            category_id = ?,
            serial_number = ?,
            brand = ?,
            model = ?,
            purchase_date = ?,
            purchase_cost = ?,
            vendor_id = ?,
            warranty_expiry = ?,
            asset_condition = ?,
            status = ?,
            notes = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $data['asset_name'],
        $data['category_id'],
        $_POST['serial_number'] ?: null,
        $_POST['brand'] ?: null,
        $_POST['model'] ?: null,
        $_POST['purchase_date'] ?: null,
        $_POST['purchase_cost'] ?: null,
        $_POST['vendor_id'] ?: null,
        $_POST['warranty_expiry'] ?: null,
        $_POST['asset_condition'],
        $_POST['status'],
        $_POST['notes'] ?: null,
        $id
    ]);

    logAudit($_SESSION['user_id'], 'update_asset', 'assets');
    flash('success', 'Asset updated successfully');
    to('http://localhost/ERP/modules/assets/index.php');
}
?>

<style>
    /* Validation message */
    #assetForm p.error {
        display: block !important;
        width: 100% !important;
        margin: 4px 0 0 0 !important;
        padding: 0 !important;
        clear: both !important;
        color: #dc2626 !important;
        font-size: 12px !important;
        line-height: 16px !important;
        position: static !important;
    }

    /* Invalid fields */
    #assetForm input.error,
    #assetForm select.error,
    #assetForm textarea.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    /* Valid fields */
    #assetForm input.valid,
    #assetForm select.valid,
    #assetForm textarea.valid {
        border-color: #22c55e !important;
    }
</style>

<div class="mb-6">
    <a href="<?= url('modules/assets/index.php') ?>"
        class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Assets
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6 w-full">

    <h2 class="text-xl font-semibold mb-6">
        Edit Asset - <?= sanitize($asset['asset_code']) ?>
    </h2>

    <?php if ($errors): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $e): ?>
                    <li><?= sanitize($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST"
        class="space-y-4"
        id="assetForm"
        novalidate>

        <!-- Asset Name / Category -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Asset Name <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="asset_name"
                    value="<?= sanitize($asset['asset_name']) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Category <span class="text-red-600">*</span>
                </label>

                <select
                    name="category_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Category</option>

                    <?php foreach ($categories as $cat): ?>
                        <option
                            value="<?= $cat['id'] ?>"
                            <?= $asset['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                            <?= sanitize($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

        </div>

        <!-- Serial Number / Brand -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Serial Number
                </label>

                <input
                    type="text"
                    name="serial_number"
                    value="<?= sanitize($asset['serial_number'] ?? '') ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Brand
                </label>

                <input
                    type="text"
                    name="brand"
                    value="<?= sanitize($asset['brand'] ?? '') ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

        </div>

        <!-- Model / Vendor -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Model
                </label>

                <input
                    type="text"
                    name="model"
                    value="<?= sanitize($asset['model'] ?? '') ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Vendor
                </label>

                <select
                    name="vendor_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Vendor</option>

                    <?php foreach ($vendors as $v): ?>
                        <option
                            value="<?= $v['id'] ?>"
                            <?= $asset['vendor_id'] == $v['id'] ? 'selected' : '' ?>>
                            <?= sanitize($v['vendor_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

        </div>

        <!-- Purchase Details -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Purchase Date
                </label>

                <input
                    type="date"
                    name="purchase_date"
                    value="<?= sanitize($asset['purchase_date'] ?? '') ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Purchase Cost
                </label>

                <input
                    type="number"
                    name="purchase_cost"
                    value="<?= sanitize($asset['purchase_cost'] ?? '') ?>"
                    min="0"
                    step="0.01"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Warranty Expiry
                </label>

                <input
                    type="date"
                    name="warranty_expiry"
                    value="<?= sanitize($asset['warranty_expiry'] ?? '') ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

        </div>

        <!-- Condition / Status -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Condition
                </label>

                <select
                    name="asset_condition"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option
                        value="new"
                        <?= $asset['asset_condition'] == 'new' ? 'selected' : '' ?>>
                        New
                    </option>

                    <option
                        value="good"
                        <?= $asset['asset_condition'] == 'good' ? 'selected' : '' ?>>
                        Good
                    </option>

                    <option
                        value="fair"
                        <?= $asset['asset_condition'] == 'fair' ? 'selected' : '' ?>>
                        Fair
                    </option>

                    <option
                        value="damaged"
                        <?= $asset['asset_condition'] == 'damaged' ? 'selected' : '' ?>>
                        Damaged
                    </option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Status
                </label>

                <select
                    name="status"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option
                        value="available"
                        <?= $asset['status'] == 'available' ? 'selected' : '' ?>>
                        Available
                    </option>

                    <option
                        value="assigned"
                        <?= $asset['status'] == 'assigned' ? 'selected' : '' ?>>
                        Assigned
                    </option>

                    <option
                        value="maintenance"
                        <?= $asset['status'] == 'maintenance' ? 'selected' : '' ?>>
                        Maintenance
                    </option>

                    <option
                        value="damaged"
                        <?= $asset['status'] == 'damaged' ? 'selected' : '' ?>>
                        Damaged
                    </option>

                    <option
                        value="lost"
                        <?= $asset['status'] == 'lost' ? 'selected' : '' ?>>
                        Lost
                    </option>

                    <option
                        value="retired"
                        <?= $asset['status'] == 'retired' ? 'selected' : '' ?>>
                        Retired
                    </option>

                </select>
            </div>

        </div>

        <!-- Notes -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Notes
            </label>

            <textarea
                name="notes"
                rows="2"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= sanitize($asset['notes'] ?? '') ?></textarea>
        </div>

        <!-- Buttons -->
        <div class="pt-4">

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Update Asset
            </button>

            <a
                href="<?= url('modules/assets/index.php') ?>"
                class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">
                Cancel
            </a>

        </div>

    </form>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- jQuery Validation -->
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function() {

        /*
         * Warranty expiry cannot be before purchase date.
         */
        $.validator.addMethod(
            "validWarrantyDate",
            function(value, element) {

                const purchaseDate = $('input[name="purchase_date"]').val();

                if (!value || !purchaseDate) {
                    return true;
                }

                return new Date(value) >= new Date(purchaseDate);
            },
            "Warranty expiry cannot be before purchase date."
        );


        $("#assetForm").validate({

            rules: {

                asset_name: {
                    required: true,
                    minlength: 2,
                    maxlength: 255
                },

                category_id: {
                    required: true
                },

                serial_number: {
                    maxlength: 100
                },

                brand: {
                    maxlength: 100
                },

                model: {
                    maxlength: 100
                },

                purchase_date: {
                    date: true
                },

                purchase_cost: {
                    number: true,
                    min: 0
                },

                warranty_expiry: {
                    date: true,
                    validWarrantyDate: true
                },

                notes: {
                    maxlength: 1000
                }
            },


            messages: {

                asset_name: {
                    required: "Please enter asset name.",
                    minlength: "Asset name must be at least 2 characters.",
                    maxlength: "Asset name cannot exceed 255 characters."
                },

                category_id: {
                    required: "Please select a category."
                },

                serial_number: {
                    maxlength: "Serial number cannot exceed 100 characters."
                },

                brand: {
                    maxlength: "Brand cannot exceed 100 characters."
                },

                model: {
                    maxlength: "Model cannot exceed 100 characters."
                },

                purchase_date: {
                    date: "Please enter a valid purchase date."
                },

                purchase_cost: {
                    number: "Please enter a valid purchase cost.",
                    min: "Purchase cost cannot be negative."
                },

                warranty_expiry: {
                    date: "Please enter a valid warranty expiry date.",
                    validWarrantyDate: "Warranty expiry cannot be before purchase date."
                },

                notes: {
                    maxlength: "Notes cannot exceed 1000 characters."
                }
            },


            /*
             * IMPORTANT:
             * Select dropdown validation message
             * will appear BELOW the dropdown.
             */
            errorPlacement: function(error, element) {

                if (element.is("select")) {

                    error.appendTo(element.parent());

                } else {

                    error.insertAfter(element);

                }
            },


            errorElement: "p",

            errorClass: "error",


            highlight: function(element) {

                $(element)
                    .addClass("error")
                    .removeClass("valid");
            },


            unhighlight: function(element) {

                $(element)
                    .removeClass("error")
                    .addClass("valid");
            },


            submitHandler: function(form) {

                form.submit();

            }

        });

    });
</script>

<?php require_once '../../includes/footer.php'; ?>