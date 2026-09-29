<?php
$pageTitle = 'Add Asset';

require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('assets.create')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$categories = $db->query("
    SELECT id, category_name
    FROM asset_categories
    WHERE status = 'active'
    ORDER BY category_name
")->fetchAll();

$vendors = $db->query("
    SELECT id, vendor_name
    FROM vendors
    WHERE status = 'active'
    ORDER BY vendor_name
")->fetchAll();


function generateAssetCode($db)
{
    $year = date('Y');

    $stmt = $db->query("
        SELECT COUNT(*) as cnt
        FROM assets
        WHERE asset_code LIKE 'AST$year%'
    ");

    return "AST" . $year . str_pad(
        $stmt->fetch()['cnt'] + 1,
        4,
        '0',
        STR_PAD_LEFT
    );
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = [
        'asset_name'     => $_POST['asset_name'] ?? '',
        'category_id'    => $_POST['category_id'] ?? '',
        'serial_number'  => trim($_POST['serial_number'] ?? '')
    ];


    /*
    |--------------------------------------------------------------------------
    | Server-side validation
    |--------------------------------------------------------------------------
    */
    $errors = validate(
        $data,
        [
            'asset_name'  => 'required',
            'category_id' => 'required'
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Serial Number Length Validation
    |--------------------------------------------------------------------------
    */
    if (!empty($data['serial_number']) && strlen($data['serial_number']) > 100) {

        $errors['serial_number'] =
            'Serial number cannot exceed 100 characters.';
    }


    /*
    |--------------------------------------------------------------------------
    | Brand Length Validation
    |--------------------------------------------------------------------------
    */
    $brand = trim($_POST['brand'] ?? '');

    if ($brand !== '' && strlen($brand) > 100) {

        $errors['brand'] =
            'Brand cannot exceed 100 characters.';
    }


    /*
    |--------------------------------------------------------------------------
    | Model Length Validation
    |--------------------------------------------------------------------------
    */
    $model = trim($_POST['model'] ?? '');

    if ($model !== '' && strlen($model) > 100) {

        $errors['model'] =
            'Model cannot exceed 100 characters.';
    }


    /*
    |--------------------------------------------------------------------------
    | Duplicate Serial Number Validation
    |--------------------------------------------------------------------------
    |
    | Empty serial number is allowed.
    | Only non-empty serial numbers are checked.
    |
    |--------------------------------------------------------------------------
    */
    if (!empty($data['serial_number'])) {

        $duplicateStmt = $db->prepare("
            SELECT id
            FROM assets
            WHERE serial_number = ?
            LIMIT 1
        ");

        $duplicateStmt->execute([
            $data['serial_number']
        ]);

        if ($duplicateStmt->fetch()) {

            $errors['serial_number'] =
                'This serial number already exists.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Stop if validation errors
    |--------------------------------------------------------------------------
    */
    if (!empty($errors)) {

        saveFormState($errors);

        to('modules/assets/create.php');
    }


    /*
    |--------------------------------------------------------------------------
    | Generate Asset Code
    |--------------------------------------------------------------------------
    */
    $assetCode = generateAssetCode($db);


    /*
    |--------------------------------------------------------------------------
    | Insert Asset
    |--------------------------------------------------------------------------
    */
    $stmt = $db->prepare("
        INSERT INTO assets (
            asset_code,
            asset_name,
            category_id,
            serial_number,
            brand,
            model,
            purchase_date,
            purchase_cost,
            vendor_id,
            warranty_expiry,
            asset_condition,
            status,
            notes
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available', ?)
    ");


    $stmt->execute([
        $assetCode,
        $data['asset_name'],
        $data['category_id'],
        $_POST['serial_number'] ?: null,
        $_POST['brand'] ?: null,
        $_POST['model'] ?: null,
        $_POST['purchase_date'] ?: null,
        $_POST['purchase_cost'] ?: null,
        $_POST['vendor_id'] ?: null,
        $_POST['warranty_expiry'] ?: null,
        $_POST['asset_condition'] ?: 'new',
        $_POST['notes'] ?: null
    ]);


    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    */
    logAudit(
        $_SESSION['user_id'],
        'create_asset',
        'assets'
    );


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */
    flash(
        'success',
        'Asset added successfully'
    );

    to(
        'http://localhost/ERP/modules/assets/index.php'
    );
}
?>

<style>
    /*
|--------------------------------------------------------------------------
| Validation Message
|--------------------------------------------------------------------------
*/

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


    /*
|--------------------------------------------------------------------------
| Invalid Field
|--------------------------------------------------------------------------
*/

    #assetForm input.error,
    #assetForm select.error,
    #assetForm textarea.error {

        border-color: #ef4444 !important;

        box-shadow: none !important;
    }


    /*
|--------------------------------------------------------------------------
| Valid Field
|--------------------------------------------------------------------------
*/

    #assetForm input.valid,
    #assetForm select.valid,
    #assetForm textarea.valid {

        border-color: #22c55e !important;

    }
</style>


<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="mb-6">

    <a
        href="<?= url('modules/assets/index.php') ?>"
        class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Assets
    </a>

</div>


<!-- =========================================================
     MAIN CARD
========================================================= -->

<div class="bg-white rounded-lg shadow p-6 w-full">

    <h2 class="text-xl font-semibold mb-6">
        Add New Asset
    </h2>


    <!-- =====================================================
         SERVER ERRORS
    ====================================================== -->

    <?php if ($errors): ?>

        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">

            <ul class="list-disc list-inside">

                <?php foreach ($errors as $e): ?>

                    <li>
                        <?= sanitize($e) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         FORM
    ====================================================== -->

    <form
        method="POST"
        class="space-y-4"
        id="assetForm"
        novalidate>


        <!-- =================================================
             Asset Name / Category
        ================================================== -->

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Asset Name -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Asset Name

                    <span class="text-red-600">*</span>

                </label>

                <input
                    type="text"
                    name="asset_name"
                    value="<?= old('asset_name') ?>"
                    maxlength="255"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


            <!-- Category -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Category

                    <span class="text-red-600">*</span>

                </label>

                <select
                    name="category_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        Select Category
                    </option>

                    <?php foreach ($categories as $cat): ?>

                        <option
                            value="<?= $cat['id'] ?>"
                            <?= old('category_id') == $cat['id'] ? 'selected' : '' ?>>

                            <?= sanitize($cat['category_name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>


        <!-- =================================================
             Serial Number / Brand
        ================================================== -->

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Serial Number -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Serial Number

                </label>

                <input
                    type="text"
                    name="serial_number"
                    id="serial_number"
                    value="<?= old('serial_number') ?>"
                    maxlength="100"
                    autocomplete="off"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">


            </div>


            <!-- Brand -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Brand

                </label>

                <input
                    type="text"
                    name="brand"
                    id="brand"
                    value="<?= old('brand') ?>"
                    maxlength="100"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

        </div>


        <!-- =================================================
             Model / Vendor
        ================================================== -->

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Model -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Model

                </label>

                <input
                    type="text"
                    name="model"
                    id="model"
                    value="<?= old('model') ?>"
                    maxlength="100"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">


            </div>


            <!-- Vendor -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Vendor

                </label>

                <select
                    name="vendor_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        Select Vendor
                    </option>

                    <?php foreach ($vendors as $v): ?>

                        <option
                            value="<?= $v['id'] ?>"
                            <?= old('vendor_id') == $v['id'] ? 'selected' : '' ?>>

                            <?= sanitize($v['vendor_name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>


        <!-- =================================================
             Purchase Details
        ================================================== -->

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            <!-- Purchase Date -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Purchase Date

                </label>

                <input
                    type="date"
                    name="purchase_date"
                    id="purchase_date"
                    value="<?= old('purchase_date') ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


            <!-- Purchase Cost -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Purchase Cost

                </label>

                <input
                    type="number"
                    name="purchase_cost"
                    value="<?= old('purchase_cost') ?>"
                    min="0"
                    step="0.01"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


            <!-- Warranty Expiry -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Warranty Expiry

                </label>

                <input
                    type="date"
                    name="warranty_expiry"
                    id="warranty_expiry"
                    value="<?= old('warranty_expiry') ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

        </div>


        <!-- =================================================
             Condition / Status
        ================================================== -->

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Condition -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Condition

                </label>

                <select
                    name="asset_condition"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option
                        value="new"
                        <?= old('asset_condition') == 'new' || !old('asset_condition') ? 'selected' : '' ?>>
                        New
                    </option>

                    <option
                        value="good"
                        <?= old('asset_condition') == 'good' ? 'selected' : '' ?>>
                        Good
                    </option>

                    <option
                        value="fair"
                        <?= old('asset_condition') == 'fair' ? 'selected' : '' ?>>
                        Fair
                    </option>

                    <option
                        value="damaged"
                        <?= old('asset_condition') == 'damaged' ? 'selected' : '' ?>>
                        Damaged
                    </option>

                </select>

            </div>


            <!-- Status -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Status

                </label>

                <select
                    name="status"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="available">
                        Available
                    </option>

                    <option value="assigned">
                        Assigned
                    </option>

                    <option value="maintenance">
                        Maintenance
                    </option>

                    <option value="damaged">
                        Damaged
                    </option>

                    <option value="retired">
                        Retired
                    </option>

                </select>

            </div>

        </div>


        <!-- =================================================
             Notes
        ================================================== -->

        <div>

            <label class="block text-sm font-medium text-gray-700 mb-1">

                Notes

            </label>

            <textarea
                name="notes"
                rows="2"
                maxlength="1000"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('notes') ?></textarea>

        </div>


        <!-- =================================================
             BUTTONS
        ================================================== -->

        <div class="pt-4">

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">

                Add Asset

            </button>


            <a
                href="<?= url('modules/assets/index.php') ?>"
                class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">

                Cancel

            </a>

        </div>

    </form>

</div>


<!-- =========================================================
     JQUERY
========================================================= -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>


<!-- =========================================================
     JQUERY VALIDATION
========================================================= -->

<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>


<script>
    $(document).ready(function() {


        /* ======================================================
           WARRANTY DATE VALIDATION
        ====================================================== */

        $.validator.addMethod(
            "validWarrantyDate",
            function(value, element) {

                const purchaseDate =
                    $('#purchase_date').val();

                if (!value || !purchaseDate) {
                    return true;
                }

                return new Date(value) >= new Date(purchaseDate);

            },
            "Warranty expiry cannot be before purchase date."
        );


        /* ======================================================
           SERIAL NUMBER VALIDATION
        ====================================================== */

        $.validator.addMethod(
            "validSerialNumber",
            function(value, element) {

                if (!value) {
                    return true;
                }

                return value.length <= 100;

            },
            "Serial number cannot exceed 100 characters."
        );


        /* ======================================================
           BRAND VALIDATION
        ====================================================== */

        $.validator.addMethod(
            "validBrand",
            function(value, element) {

                if (!value) {
                    return true;
                }

                return value.length <= 100;

            },
            "Brand cannot exceed 100 characters."
        );


        /* ======================================================
           MODEL VALIDATION
        ====================================================== */

        $.validator.addMethod(
            "validModel",
            function(value, element) {

                if (!value) {
                    return true;
                }

                return value.length <= 100;

            },
            "Model cannot exceed 100 characters."
        );


        /* ======================================================
           FORM VALIDATION
        ====================================================== */

        $('#assetForm').validate({

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

                    maxlength: 100,

                    validSerialNumber: true

                },


                brand: {

                    maxlength: 100,

                    validBrand: true

                },


                model: {

                    maxlength: 100,

                    validModel: true

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

                    maxlength: "Serial number cannot exceed 100 characters.",

                    validSerialNumber: "Serial number cannot exceed 100 characters."

                },


                brand: {

                    maxlength: "Brand cannot exceed 100 characters.",

                    validBrand: "Brand cannot exceed 100 characters."

                },


                model: {

                    maxlength: "Model cannot exceed 100 characters.",

                    validModel: "Model cannot exceed 100 characters."

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


            /* ==================================================
               ERROR PLACEMENT
            ================================================== */

            errorPlacement: function(error, element) {

                if (element.is('select')) {

                    error.appendTo(element.parent());

                } else {

                    error.insertAfter(element);

                }

            },


            errorElement: 'p',

            errorClass: 'error',


            /* ==================================================
               HIGHLIGHT
            ================================================== */

            highlight: function(element) {

                $(element)
                    .addClass('error')
                    .removeClass('valid');

            },


            /* ==================================================
               UNHIGHLIGHT
            ================================================== */

            unhighlight: function(element) {

                $(element)
                    .removeClass('error')
                    .addClass('valid');

            },


            submitHandler: function(form) {

                form.submit();

            }

        });


        /* ======================================================
           PURCHASE DATE CHANGE
           Update warranty minimum date
        ====================================================== */

        $('#purchase_date').on('change', function() {

            const purchaseDate = $(this).val();

            if (purchaseDate) {

                $('#warranty_expiry').attr(
                    'min',
                    purchaseDate
                );

            } else {

                $('#warranty_expiry').removeAttr('min');

            }

            $('#warranty_expiry').valid();

        });


        /* ======================================================
           SERIAL NUMBER TRIM
        ====================================================== */

        $('#serial_number').on('blur', function() {

            $(this).val(
                $.trim($(this).val())
            );

        });


        /* ======================================================
           BRAND TRIM
        ====================================================== */

        $('#brand').on('blur', function() {

            $(this).val(
                $.trim($(this).val())
            );

        });


        /* ======================================================
           MODEL TRIM
        ====================================================== */

        $('#model').on('blur', function() {

            $(this).val(
                $.trim($(this).val())
            );

        });

    });
</script>


<?php require_once '../../includes/footer.php'; ?>