<?php

$pageTitle = 'Tax Settings';

require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('finance.view')) {
    die('Access Denied');
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('finance.view')) {

    $tax_name = $_POST['tax_name'] ?? '';
    $tax_percentage = $_POST['tax_percentage'] ?? 0;
    $tax_type = $_POST['tax_type'] ?? 'gst';

    if ($tax_name && $tax_percentage >= 0) {

        try {

            $stmt = $db->prepare("
                INSERT INTO tax_settings
                (tax_name, tax_percentage, tax_type)
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $tax_name,
                $tax_percentage,
                $tax_type
            ]);

            flash('success', 'Tax setting added successfully');
        } catch (Exception $e) {

            flash('error', 'Failed to add tax setting');
        }

        to('http://localhost/ERP/modules/tax_settings/index.php');
    }
}


if (isset($_GET['toggle']) && hasPermission('finance.view')) {

    $id = $_GET['toggle'];

    $tax = $db->prepare("
        SELECT is_active
        FROM tax_settings
        WHERE id = ?
    ");

    $tax->execute([$id]);

    $tax = $tax->fetch();

    if ($tax) {

        $newStatus = $tax['is_active'] ? 0 : 1;

        $db->prepare("
            UPDATE tax_settings
            SET is_active = ?
            WHERE id = ?
        ")->execute([
            $newStatus,
            $id
        ]);

        to('http://localhost/ERP/modules/tax_settings/index.php');
    }
}


if (isset($_GET['delete']) && hasPermission('finance.view')) {

    $id = $_GET['delete'];

    $db->prepare("
        DELETE FROM tax_settings
        WHERE id = ?
    ")->execute([$id]);

    flash('success', 'Tax setting deleted');

    to('http://localhost/ERP/modules/tax_settings/index.php');
}


$taxes = $db->query("
    SELECT *
    FROM tax_settings
    ORDER BY tax_name
")->fetchAll();

?>

<div class="mb-6 flex justify-between items-center">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            Tax Settings
        </h1>

        <p class="text-gray-600">
            Configure tax rates for invoices
        </p>
    </div>

</div>


<?php if (hasPermission('finance.view')): ?>

    <div class="bg-white rounded-lg shadow mb-6 p-6">

        <h2 class="text-lg font-semibold text-gray-800 mb-5">
            Add New Tax
        </h2>

        <form
            method="POST"
            id="taxForm"
            novalidate
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">

            <!-- Tax Name -->
            <div>

                <label class="block text-xs font-medium text-gray-600 mb-1.5">
                    Tax Name <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="tax_name"
                    id="tax_name"
                    maxlength="100"
                    placeholder="e.g. VAT"
                    class="w-full h-10 px-3 border border-gray-300 rounded-md text-sm
                       focus:outline-none focus:ring-2 focus:ring-blue-500
                       focus:border-blue-500">

            </div>


            <!-- Percentage -->
            <div>

                <label class="block text-xs font-medium text-gray-600 mb-1.5">
                    Percentage <span class="text-red-600">*</span>
                </label>

                <input
                    type="number"
                    name="tax_percentage"
                    id="tax_percentage"
                    min="0"
                    max="100"
                    step="0.01"
                    placeholder="0.00"
                    class="w-full h-10 px-3 border border-gray-300 rounded-md text-sm
                       focus:outline-none focus:ring-2 focus:ring-blue-500
                       focus:border-blue-500">

            </div>


            <!-- Tax Type -->
            <div>

                <label class="block text-xs font-medium text-gray-600 mb-1.5">
                    Type <span class="text-red-600">*</span>
                </label>

                <select
                    name="tax_type"
                    id="tax_type"
                    class="w-full h-10 px-3 border border-gray-300 rounded-md text-sm bg-white">
                    <option value="">Select type...</option>
                    <option value="gst">GST</option>
                    <option value="vat">VAT</option>
                    <option value="tax">Tax</option>
                </select>

            </div>

            <!-- Add Button -->
            <div class="flex items-end">

                <button
                    type="submit"
                    class="w-full h-10 bg-blue-600 text-white px-4 rounded-md
                       hover:bg-blue-700 transition text-sm font-medium
                       whitespace-nowrap">
                    <i class="fas fa-plus mr-1.5"></i>
                    Add Tax
                </button>

            </div>

        </form>

    </div>

<?php endif; ?>


<!-- Tax List -->

<div class="bg-white rounded-lg shadow overflow-hidden">

    <div class="overflow-x-auto">

        <table class="w-full text-sm">

            <thead class="bg-gray-50">

                <tr>

                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Tax Name
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Percentage
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Type
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Status
                    </th>

                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                        Actions
                    </th>

                </tr>

            </thead>


            <tbody class="divide-y divide-gray-200">

                <?php foreach ($taxes as $tax): ?>

                    <tr class="hover:bg-gray-50">

                        <!-- Tax Name -->
                        <td class="px-6 py-4 font-medium text-gray-900">
                            <?= sanitize($tax['tax_name']) ?>
                        </td>


                        <!-- Percentage -->
                        <td class="px-6 py-4 text-gray-600">
                            <?= $tax['tax_percentage'] ?>%
                        </td>


                        <!-- Type -->
                        <td class="px-6 py-4 text-gray-600">
                            <?= strtoupper($tax['tax_type']) ?>
                        </td>


                        <!-- Status -->
                        <td class="px-6 py-4">

                            <span
                                class="px-2 py-1 text-xs rounded
                            <?= $tax['is_active']
                                ? 'bg-green-100 text-green-800'
                                : 'bg-red-100 text-red-800'
                            ?>">
                                <?= $tax['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>

                        </td>


                        <!-- Actions -->
                        <td class="px-6 py-4 text-right">

                            <a
                                href="<?= url(
                                            'modules/tax_settings/index.php?toggle=' . $tax['id']
                                        ) ?>"
                                class="text-gray-600 hover:text-gray-900 mr-2"
                                title="<?= $tax['is_active']
                                            ? 'Deactivate'
                                            : 'Activate'
                                        ?>">

                                <i
                                    class="fas fa-toggle-<?= $tax['is_active']
                                                                ? 'on'
                                                                : 'off'
                                                            ?>"></i>

                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>


                <?php if (empty($taxes)): ?>

                    <tr>

                        <td
                            colspan="5"
                            class="px-6 py-8 text-center text-gray-400">
                            No tax settings found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- jQuery Validation -->

<style>
    #taxForm p.error {
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

    #taxForm select.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    #taxForm input.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    #taxForm select.valid,
    #taxForm input.valid {
        border-color: #d1d5db !important;
    }
</style>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function() {

        $('#taxForm').validate({

            rules: {

                tax_name: {
                    required: true,
                    minlength: 2,
                    maxlength: 100
                },

                tax_percentage: {
                    required: true,
                    number: true,
                    min: 0,
                    max: 100
                },

                tax_type: {
                    required: true
                }

            },

            messages: {

                tax_name: {
                    required: "Please enter tax name.",
                    minlength: "Tax name must be at least 2 characters.",
                    maxlength: "Tax name cannot exceed 100 characters."
                },

                tax_percentage: {
                    required: "Please enter tax percentage.",
                    number: "Please enter a valid percentage.",
                    min: "Percentage cannot be less than 0.",
                    max: "Percentage cannot be greater than 100."
                },

                tax_type: {
                    required: "Please select tax type."
                }

            },

            errorElement: 'p',

            errorClass: 'error',

            errorPlacement: function(error, element) {

                if (element.is('select')) {

                    // Dropdown error BELOW select
                    error.appendTo(element.closest('div'));

                } else {

                    // Input error BELOW input
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

    });
</script>


<?php require_once '../../includes/footer.php'; ?>