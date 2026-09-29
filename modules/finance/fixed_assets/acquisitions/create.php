<?php

$pageTitle = 'Create Capitalization';

require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../helpers/validation.php';
require_once '../depreciationservice.php';

if (!hasPermission('fixedasset.create')) {
    die('Access Denied');
}

$db = getDB();

$asset_id = $_GET['asset_id'] ?? '';
$assetDetails = null;

/*
|--------------------------------------------------------------------------
| Load Asset Details
|--------------------------------------------------------------------------
*/
if ($asset_id) {

    $stmt = $db->prepare("
        SELECT 
            a.*, 
            c.category_name
        FROM assets a
        LEFT JOIN asset_categories c 
            ON a.category_id = c.id
        WHERE a.id = ?
    ");

    $stmt->execute([$asset_id]);

    $assetDetails = $stmt->fetch();

    if ($assetDetails) {

        // Check whether financial record already exists
        $stmt = $db->prepare("
            SELECT *
            FROM fixed_asset_financials
            WHERE asset_id = ?
        ");

        $stmt->execute([$asset_id]);

        $financialAsset = $stmt->fetch();

        if ($financialAsset) {

            flash(
                'info',
                'This asset already has a financial record. Please use the edit page to update it.'
            );

            to(
                'modules/finance/fixed_assets/financial_records/edit.php?id=' .
                    $financialAsset['id']
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| Restore Errors / Old Input
|--------------------------------------------------------------------------
*/
$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = [

        'asset_id' => $_POST['asset_id'] ?? '',

        'category_id' => $_POST['category_id'] ?? '',

        'capitalization_date' =>
        $_POST['capitalization_date'] ?? date('Y-m-d'),

        'in_service_date' =>
        $_POST['in_service_date'] ?? '',

        'acquisition_cost' =>
        $_POST['acquisition_cost'] ?? '',

        'capitalized_cost' =>
        $_POST['capitalized_cost'] ?? '',

        'residual_value' =>
        $_POST['residual_value'] ?? '',

        'useful_life_months' =>
        $_POST['useful_life_months'] ?? '',

        'depreciation_method_id' =>
        $_POST['depreciation_method_id'] ?? '',

        'status' =>
        $_POST['status'] ?? 'active',
    ];

    /*
    |--------------------------------------------------------------------------
    | Server-side Validation
    |--------------------------------------------------------------------------
    */
    $errors = validate($data, [

        'asset_id' => 'required',

        'category_id' => 'required',

        'capitalization_date' => 'required',

        'acquisition_cost' => 'required|numeric',

        'capitalized_cost' => 'required|numeric',

        'residual_value' => 'required|numeric',

        'useful_life_months' => 'required|numeric',

        'depreciation_method_id' => 'required',

        'status' => 'required',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Additional Server-side Validation
    |--------------------------------------------------------------------------
    */
    if (empty($errors)) {

        // Validate dates
        if (!empty($data['in_service_date'])) {

            if ($data['in_service_date'] < $data['capitalization_date']) {

                $errors['in_service_date'] =
                    'In Service Date cannot be before Capitalization Date.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Cost Relationships
    |--------------------------------------------------------------------------
    */
    if (empty($errors)) {

        $acquisitionCost = (float) $data['acquisition_cost'];

        $capitalizedCost = (float) $data['capitalized_cost'];

        $residualValue = (float) $data['residual_value'];

        if ($capitalizedCost > $acquisitionCost) {

            $errors['capitalized_cost'] =
                'Capitalized Cost cannot be greater than Acquisition Cost.';
        }

        if ($residualValue > $capitalizedCost) {

            $errors['residual_value'] =
                'Residual Value cannot be greater than Capitalized Cost.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Check Existing Financial Record
    |--------------------------------------------------------------------------
    */
    if (empty($errors)) {

        $checkStmt = $db->prepare("
            SELECT id
            FROM fixed_asset_financials
            WHERE asset_id = ?
        ");

        $checkStmt->execute([
            $data['asset_id']
        ]);

        if ($checkStmt->fetch()) {

            $errors['asset_id'] =
                'Asset already has a financial record. Use edit instead.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Asset Category
    |--------------------------------------------------------------------------
    */
    if (empty($errors)) {

        $stmt = $db->prepare("
            SELECT 
                a.category_id,
                c.category_name,
                f.asset_category_id
            FROM assets a
            LEFT JOIN asset_categories c
                ON a.category_id = c.id
            LEFT JOIN fixed_asset_financials f
                ON a.id = f.asset_id
            WHERE a.id = ?
        ");

        $stmt->execute([
            $data['asset_id']
        ]);

        $assetCategory = $stmt->fetch();

        /*
        |--------------------------------------------------------------------------
        | Prevent Category Override
        |--------------------------------------------------------------------------
        */
        if (
            $assetCategory &&
            $assetCategory['category_id'] &&
            $data['category_id'] !== $assetCategory['category_id']
        ) {

            $errors['category_id'] =
                'Cannot override asset category. Use asset category: ' .
                $assetCategory['category_name'];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Financial Category
    |--------------------------------------------------------------------------
    */
    if (empty($errors)) {

        $stmt = $db->prepare("
            SELECT id
            FROM fixed_asset_categories
            WHERE asset_category_id = ?
        ");

        $stmt->execute([
            $data['category_id']
        ]);

        $financialCategory = $stmt->fetch();

        if (!$financialCategory) {

            $errors['category_id'] =
                'Invalid asset category. No financial setup found.';
        } else {

            /*
            |--------------------------------------------------------------------------
            | Validate Depreciation Method
            |--------------------------------------------------------------------------
            */
            $stmt = $db->prepare("
                SELECT *
                FROM depreciation_methods
                WHERE id = ?
            ");

            $stmt->execute([
                $data['depreciation_method_id']
            ]);

            $deprMethod = $stmt->fetch();

            if (!$deprMethod) {

                $errors['depreciation_method_id'] =
                    'Invalid depreciation method';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save Financial Record
    |--------------------------------------------------------------------------
    */
    if (empty($errors)) {

        $stmt = $db->prepare("
            SELECT *
            FROM fixed_asset_categories
            WHERE asset_category_id = ?
        ");

        $stmt->execute([
            $data['category_id']
        ]);

        $fixedCategory = $stmt->fetch();

        $depreciationMethodId =
            $fixedCategory['depreciation_method_id'];

        $usefulLifeMonths =
            $fixedCategory['useful_life_months'];

        $residualValue =
            $data['residual_value'];

        /*
        |--------------------------------------------------------------------------
        | Override with Selected Values
        |--------------------------------------------------------------------------
        */
        if (!empty($data['depreciation_method_id'])) {

            $depreciationMethodId =
                $data['depreciation_method_id'];
        }

        if (!empty($data['useful_life_months'])) {

            $usefulLifeMonths =
                $data['useful_life_months'];
        }

        /*
        |--------------------------------------------------------------------------
        | Insert Financial Record
        |--------------------------------------------------------------------------
        */
        $stmt = $db->prepare("
            INSERT INTO fixed_asset_financials (
                asset_id,
                asset_category_id,
                capitalization_date,
                in_service_date,
                acquisition_cost,
                capitalized_cost,
                residual_value,
                useful_life_months,
                depreciation_method_id,
                depreciation_start_date,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([

            $data['asset_id'],

            $financialCategory['id'],

            $data['capitalization_date'],

            $data['in_service_date']
                ?: $data['capitalization_date'],

            $data['acquisition_cost'],

            $data['capitalized_cost'],

            $data['residual_value'],

            $usefulLifeMonths,

            $depreciationMethodId,

            $data['capitalization_date'],

            $data['status'],
        ]);

        $financialAssetId =
            $db->lastInsertId();

        /*
        |--------------------------------------------------------------------------
        | Audit Log
        |--------------------------------------------------------------------------
        */
        logAudit(
            $_SESSION['user_id'],
            'Capitalized asset: ' . $data['asset_id'],
            'fixed_assets'
        );

        flash(
            'success',
            'Asset capitalized successfully'
        );

        to(
            'modules/finance/fixed_assets/acquisitions/view.php?id=' .
                $financialAssetId
        );
    }
}

?>

<!-- Back -->
<div class="mb-6">

    <a
        href="<?= url('modules/finance/fixed_assets/acquisitions/index.php') ?>"
        class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Capitalizations
    </a>

</div>


<!-- Main Card -->
<div class="bg-white rounded-lg shadow p-6 w-full">

    <h1 class="text-xl font-semibold mb-6">
        Capitalize Asset
    </h1>


    <!-- Asset Details -->
    <?php if ($assetDetails): ?>

        <div class="bg-blue-50 border border-blue-200 p-4 rounded mb-6">

            <h3 class="font-semibold text-blue-800">
                Asset Details
            </h3>

            <div class="grid grid-cols-2 gap-2 mt-2">

                <div>
                    <span class="font-medium">
                        Asset Code:
                    </span>

                    <?= sanitize($assetDetails['asset_code']) ?>
                </div>

                <div>
                    <span class="font-medium">
                        Name:
                    </span>

                    <?= sanitize($assetDetails['asset_name']) ?>
                </div>

                <div>
                    <span class="font-medium">
                        Category:
                    </span>

                    <?= sanitize(
                        $assetDetails['category_name'] ?? 'N/A'
                    ) ?>
                </div>

                <div>
                    <span class="font-medium">
                        Purchase Cost:
                    </span>

                    <?= formatAmount(
                        $assetDetails['purchase_cost'] ?? '0'
                    ) ?>
                </div>

            </div>

        </div>

    <?php endif; ?>


    <!-- Server-side Errors -->
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


    <!-- Form -->
    <form
        method="POST"
        id="capitalizationForm"
        class="space-y-4"
        novalidate>

        <!-- Asset -->
        <input
            type="hidden"
            name="asset_id"
            value="<?= sanitize($asset_id) ?>">


        <!-- Asset + Category -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Asset -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Asset
                </label>

                <input
                    type="text"
                    value="<?= $asset_id
                                ? sanitize($assetDetails['asset_name'] ?? '')
                                : 'Select an asset' ?>"
                    class="w-full px-3 py-2 border rounded bg-gray-100"
                    readonly>

            </div>


            <!-- Category -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Category <span class="text-red-600">*</span>
                </label>

                <select
                    name="category_id"
                    id="category_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        Select Category
                    </option>

                    <?php

                    $categories = $db->query("
                        SELECT
                            a.id,
                            a.category_name
                        FROM asset_categories a
                        LEFT JOIN fixed_asset_categories f
                            ON a.id = f.asset_category_id
                        WHERE a.status = 'active'
                        AND f.id IS NOT NULL
                        ORDER BY a.category_name
                    ")->fetchAll();

                    foreach ($categories as $cat):

                    ?>

                        <option
                            value="<?= $cat['id'] ?>"
                            <?= (
                                $asset_id &&
                                $assetDetails['category_id'] == $cat['id']
                            )
                                ? 'selected'
                                : ''
                            ?>>
                            <?= sanitize($cat['category_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>


        <!-- Dates -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Capitalization Date -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Capitalization Date <span class="text-red-600">*</span>
                </label>

                <input
                    type="date"
                    name="capitalization_date"
                    id="capitalization_date"
                    value="<?= old(
                                'capitalization_date',
                                date('Y-m-d')
                            ) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


            <!-- In Service Date -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    In Service Date
                </label>

                <input
                    type="date"
                    name="in_service_date"
                    id="in_service_date"
                    value="<?= old('in_service_date') ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

        </div>


        <!-- Costs -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            <!-- Acquisition Cost -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Acquisition Cost <span class="text-red-600">*</span>
                </label>

                <input
                    type="number"
                    name="acquisition_cost"
                    id="acquisition_cost"
                    value="<?= old('acquisition_cost') ?>"
                    min="0"
                    step="0.01"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


            <!-- Capitalized Cost -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Capitalized Cost <span class="text-red-600">*</span>
                </label>

                <input
                    type="number"
                    name="capitalized_cost"
                    id="capitalized_cost"
                    value="<?= old('capitalized_cost') ?>"
                    min="0"
                    step="0.01"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


            <!-- Residual Value -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Residual Value <span class="text-red-600">*</span>
                </label>

                <input
                    type="number"
                    name="residual_value"
                    id="residual_value"
                    value="<?= old('residual_value') ?>"
                    min="0"
                    step="0.01"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

        </div>


        <!-- Useful Life + Depreciation -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Useful Life -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Useful Life (Months) <span class="text-red-600">*</span>
                </label>

                <input
                    type="number"
                    name="useful_life_months"
                    id="useful_life_months"
                    value="<?= old('useful_life_months') ?>"
                    min="1"
                    step="1"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


            <!-- Depreciation Method -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Depreciation Method <span class="text-red-600">*</span>
                </label>

                <select
                    name="depreciation_method_id"
                    id="depreciation_method_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        Select Method
                    </option>

                    <?php

                    $methods = $db->query("
                        SELECT *
                        FROM depreciation_methods
                        WHERE status = 'active'
                        ORDER BY method_name
                    ")->fetchAll();

                    foreach ($methods as $method):

                    ?>

                        <option
                            value="<?= $method['id'] ?>"
                            <?= old(
                                'depreciation_method_id'
                            ) == $method['id']
                                ? 'selected'
                                : ''
                            ?>>
                            <?= sanitize($method['method_name']) ?>
                            -
                            <?= sanitize($method['method_code']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>


        <!-- Status -->
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Status
            </label>

            <select
                name="status"
                id="status"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <option
                    value="active"
                    <?= old('status', 'active') === 'active'
                        ? 'selected'
                        : ''
                    ?>>
                    Active
                </option>

                <option
                    value="disposed"
                    <?= old('status') === 'disposed'
                        ? 'selected'
                        : ''
                    ?>>
                    Disposed
                </option>

                <option
                    value="impaired"
                    <?= old('status') === 'impaired'
                        ? 'selected'
                        : ''
                    ?>>
                    Impaired
                </option>

            </select>

        </div>


        <!-- Buttons -->
        <div class="pt-4">

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Capitalize Asset
            </button>

            <a
                href="<?= url('modules/finance/fixed_assets/acquisitions/index.php') ?>"
                class="ml-4 text-gray-600 hover:text-gray-900">
                Cancel
            </a>

        </div>

    </form>

</div>

<!-- =========================================================
     jQuery Validation
========================================================= -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>


<script>
    $(document).ready(function() {

        /*
        |--------------------------------------------------------------------------
        | Custom Method
        | In Service Date >= Capitalization Date
        |--------------------------------------------------------------------------
        */
        $.validator.addMethod(
            "afterOrEqualCapitalization",
            function(value, element) {

                // Optional field
                if (!value) {
                    return true;
                }

                var capitalizationDate =
                    $("#capitalization_date").val();

                if (!capitalizationDate) {
                    return true;
                }

                return value >= capitalizationDate;

            },
            "In Service Date cannot be before Capitalization Date."
        );


        /*
        |--------------------------------------------------------------------------
        | Capitalized Cost <= Acquisition Cost
        |--------------------------------------------------------------------------
        */

        $.validator.addMethod(
            "capitalizedLessThanAcquisition",
            function(value, element) {

                if (value === "") {
                    return true;
                }

                var acquisitionCost =
                    parseFloat($("#acquisition_cost").val());

                var capitalizedCost =
                    parseFloat(value);

                if (isNaN(acquisitionCost) ||
                    isNaN(capitalizedCost)) {

                    return true;
                }

                return capitalizedCost <= acquisitionCost;

            },
            "Capitalized Cost cannot be greater than Acquisition Cost."
        );


        /*
        |--------------------------------------------------------------------------
        | Residual Value <= Capitalized Cost
        |--------------------------------------------------------------------------
        */

        $.validator.addMethod(
            "residualLessThanCapitalized",
            function(value, element) {

                if (value === "") {
                    return true;
                }

                var capitalizedCost =
                    parseFloat($("#capitalized_cost").val());

                var residualValue =
                    parseFloat(value);

                if (isNaN(capitalizedCost) ||
                    isNaN(residualValue)) {

                    return true;
                }

                return residualValue <= capitalizedCost;

            },
            "Residual Value cannot be greater than Capitalized Cost."
        );


        /*
        |--------------------------------------------------------------------------
        | Form Validation
        |--------------------------------------------------------------------------
        */

        $("#capitalizationForm").validate({

            rules: {

                category_id: {
                    required: true
                },

                capitalization_date: {
                    required: true,
                    date: true
                },

                in_service_date: {
                    date: true,
                    afterOrEqualCapitalization: true
                },

                acquisition_cost: {
                    required: true,
                    number: true,
                    min: 0
                },

                capitalized_cost: {
                    required: true,
                    number: true,
                    min: 0,
                    capitalizedLessThanAcquisition: true
                },

                residual_value: {
                    required: true,
                    number: true,
                    min: 0,
                    residualLessThanCapitalized: true
                },

                useful_life_months: {
                    required: true,
                    digits: true,
                    min: 1
                },

                depreciation_method_id: {
                    required: true
                },

                status: {
                    required: true
                }

            },


            /*
            |--------------------------------------------------------------------------
            | Error Messages
            |--------------------------------------------------------------------------
            */

            messages: {

                category_id: {
                    required: "Please select a category."
                },

                capitalization_date: {
                    required: "Please select capitalization date.",
                    date: "Please enter a valid date."
                },

                in_service_date: {
                    date: "Please enter a valid date."
                },

                acquisition_cost: {
                    required: "Please enter acquisition cost.",
                    number: "Please enter a valid amount.",
                    min: "Acquisition Cost cannot be negative."
                },

                capitalized_cost: {
                    required: "Please enter capitalized cost.",
                    number: "Please enter a valid amount.",
                    min: "Capitalized Cost cannot be negative."
                },

                residual_value: {
                    required: "Please enter residual value.",
                    number: "Please enter a valid amount.",
                    min: "Residual Value cannot be negative."
                },

                useful_life_months: {
                    required: "Please enter useful life.",
                    digits: "Only whole numbers are allowed.",
                    min: "Useful Life must be at least 1 month."
                },

                depreciation_method_id: {
                    required: "Please select depreciation method."
                },

                status: {
                    required: "Please select status."
                }

            },


            /*
            |--------------------------------------------------------------------------
            | Error Element
            |--------------------------------------------------------------------------
            */

            errorElement: "span",

            errorClass: "text-red-600 text-sm mt-1 block",


            /*
            |--------------------------------------------------------------------------
            | Highlight Invalid Field
            |--------------------------------------------------------------------------
            */

            highlight: function(element) {

                $(element)
                    .addClass("border-red-500")
                    .removeClass("border-gray-300");

            },


            /*
            |--------------------------------------------------------------------------
            | Remove Highlight
            |--------------------------------------------------------------------------
            */

            unhighlight: function(element) {

                $(element)
                    .removeClass("border-red-500")
                    .addClass("border-gray-300");

            },


            /*
            |--------------------------------------------------------------------------
            | Error Placement
            |--------------------------------------------------------------------------
            */

            errorPlacement: function(error, element) {

                error.insertAfter(element);

            },


            /*
            |--------------------------------------------------------------------------
            | Submit
            |--------------------------------------------------------------------------
            */

            submitHandler: function(form) {

                form.submit();

            }

        });


        /*
        |--------------------------------------------------------------------------
        | Revalidate Related Fields
        |--------------------------------------------------------------------------
        */

        $("#acquisition_cost").on("input change", function() {

            $("#capitalized_cost").valid();

        });


        $("#capitalized_cost").on("input change", function() {

            $("#residual_value").valid();

        });


        $("#capitalization_date").on("change", function() {

            $("#in_service_date").valid();

        });


        $("#in_service_date").on("change", function() {

            $("#in_service_date").valid();

        });

    });
</script>


<?php require_once '../../../../includes/footer.php'; ?>