<?php
$pageTitle = 'Add Maintenance';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('maintenance.create')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$preSelectedAsset = $_GET['asset_id'] ?? null;

$assets = $db->query("
    SELECT id, asset_code, asset_name
    FROM assets
    ORDER BY asset_code
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = [
        'asset_id' => $_POST['asset_id'] ?? '',
        'maintenance_date' => $_POST['maintenance_date'] ?? '',
        'maintenance_type' => $_POST['maintenance_type'] ?? ''
    ];

    $errors = validate($data, [
        'asset_id' => 'required',
        'maintenance_date' => 'required',
        'maintenance_type' => 'required'
    ]);

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/maintenance/create.php');
    }

    $stmt = $db->prepare("
        INSERT INTO asset_maintenance (
            asset_id,
            maintenance_date,
            maintenance_type,
            vendor_name,
            cost,
            description,
            next_maintenance_date,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data['asset_id'],
        $data['maintenance_date'],
        $data['maintenance_type'],
        $_POST['vendor_name'] ?: null,
        $_POST['cost'] ?: null,
        $_POST['description'] ?: null,
        $_POST['next_maintenance_date'] ?: null,
        $_POST['status'] ?: 'completed'
    ]);

    if (
        $_POST['status'] === 'in_progress' ||
        $_POST['status'] === 'scheduled'
    ) {
        $db->prepare("
            UPDATE assets
            SET status = 'maintenance'
            WHERE id = ?
        ")->execute([
            $data['asset_id']
        ]);
    }

    logAudit(
        $_SESSION['user_id'],
        'create_maintenance',
        'asset_maintenance'
    );

    flash('success', 'Maintenance record added');

    to('http://localhost/ERP/modules/maintenance/index.php');
}
?>

<style>
    /* Validation message */
    #maintenanceForm p.error {
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
    #maintenanceForm input.error,
    #maintenanceForm select.error,
    #maintenanceForm textarea.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    /* Valid fields */
    #maintenanceForm input.valid,
    #maintenanceForm select.valid,
    #maintenanceForm textarea.valid {
        border-color: #22c55e !important;
    }
</style>


<div class="mb-6">

    <a
        href="<?= url('modules/maintenance/index.php') ?>"
        class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Maintenance
    </a>

</div>


<div class="bg-white rounded-lg shadow p-6 w-full">

    <h2 class="text-xl font-semibold mb-6">
        Add Maintenance Record
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


    <form
        method="POST"
        class="space-y-4"
        id="maintenanceForm"
        novalidate>

        <!-- Asset / Maintenance Date -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Asset -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Asset <span class="text-red-600">*</span>
                </label>

                <select
                    name="asset_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        Select Asset
                    </option>

                    <?php foreach ($assets as $a): ?>

                        <option
                            value="<?= $a['id'] ?>"
                            <?= $preSelectedAsset == $a['id'] ? 'selected' : '' ?>>
                            <?= sanitize($a['asset_code']) ?>
                            -
                            <?= sanitize($a['asset_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Maintenance Date -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Maintenance Date
                    <span class="text-red-600">*</span>
                </label>

                <input
                    type="date"
                    name="maintenance_date"
                    value="<?= old('maintenance_date', date('Y-m-d')) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

        </div>


        <!-- Maintenance Type / Status -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Maintenance Type -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Maintenance Type
                    <span class="text-red-600">*</span>
                </label>

                <select
                    name="maintenance_type"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        Select Type
                    </option>

                    <option value="repair">
                        Repair
                    </option>

                    <option value="upgrade">
                        Upgrade
                    </option>

                    <option value="cleaning">
                        Cleaning
                    </option>

                    <option value="replacement">
                        Replacement
                    </option>

                    <option value="inspection">
                        Inspection
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

                    <option value="completed">
                        Completed
                    </option>

                    <option value="in_progress">
                        In Progress
                    </option>

                    <option value="scheduled">
                        Scheduled
                    </option>

                    <option value="cancelled">
                        Cancelled
                    </option>

                </select>

            </div>

        </div>


        <!-- Vendor / Cost -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Vendor -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Vendor/Service Provider
                </label>

                <input
                    type="text"
                    name="vendor_name"
                    value="<?= old('vendor_name') ?>"
                    maxlength="255"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


            <!-- Cost -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Cost
                </label>

                <input
                    type="number"
                    name="cost"
                    value="<?= old('cost') ?>"
                    min="0"
                    step="0.01"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

        </div>


        <!-- Description -->
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Description
            </label>

            <textarea
                name="description"
                rows="2"
                maxlength="1000"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('description') ?></textarea>

        </div>


        <!-- Next Maintenance Date -->
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Next Maintenance Date
            </label>

            <input
                type="date"
                name="next_maintenance_date"
                value="<?= old('next_maintenance_date') ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

        </div>


        <!-- Buttons -->
        <div class="pt-4">

            <button
                type="submit"
                class="bg-yellow-600 text-white px-6 py-2 rounded hover:bg-yellow-700 transition">
                Add Maintenance
            </button>

            <a
                href="<?= url('modules/maintenance/index.php') ?>"
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
         * Custom validation:
         * Next Maintenance Date cannot be before
         * Maintenance Date.
         */
        $.validator.addMethod(
            "validNextMaintenanceDate",
            function(value, element) {

                const maintenanceDate =
                    $('input[name="maintenance_date"]').val();

                if (!value || !maintenanceDate) {
                    return true;
                }

                return new Date(value) >= new Date(maintenanceDate);
            },
            "Next maintenance date cannot be before maintenance date."
        );


        $("#maintenanceForm").validate({

            rules: {

                asset_id: {
                    required: true
                },

                maintenance_date: {
                    required: true,
                    date: true
                },

                maintenance_type: {
                    required: true
                },

                vendor_name: {
                    maxlength: 255
                },

                cost: {
                    number: true,
                    min: 0
                },

                description: {
                    maxlength: 1000
                },

                next_maintenance_date: {
                    date: true,
                    validNextMaintenanceDate: true
                }

            },


            messages: {

                asset_id: {
                    required: "Please select an asset."
                },

                maintenance_date: {
                    required: "Please select maintenance date.",
                    date: "Please enter a valid maintenance date."
                },

                maintenance_type: {
                    required: "Please select maintenance type."
                },

                vendor_name: {
                    maxlength: "Vendor name cannot exceed 255 characters."
                },

                cost: {
                    number: "Please enter a valid cost.",
                    min: "Cost cannot be negative."
                },

                description: {
                    maxlength: "Description cannot exceed 1000 characters."
                },

                next_maintenance_date: {
                    date: "Please enter a valid next maintenance date.",
                    validNextMaintenanceDate: "Next maintenance date cannot be before maintenance date."
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


        /*
         * Revalidate Next Maintenance Date
         * when Maintenance Date changes.
         */
        $('input[name="maintenance_date"]').on('change', function() {

            $('input[name="next_maintenance_date"]').valid();

        });

    });
</script>


<?php require_once '../../includes/footer.php'; ?>