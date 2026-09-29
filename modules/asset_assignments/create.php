<?php
$pageTitle = 'Assign Asset';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('asset_assignments.create')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$preSelectedAsset = $_GET['asset_id'] ?? null;

$assets = $db->query("
    SELECT id, asset_code, asset_name, status 
    FROM assets 
    WHERE status = 'available' 
    ORDER BY asset_code
")->fetchAll();

$employees = $db->query("
    SELECT id, first_name, last_name, employee_code, department_id 
    FROM employees 
    WHERE status = 'active' 
    ORDER BY first_name
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = [
        'asset_id' => $_POST['asset_id'] ?? '',
        'employee_id' => $_POST['employee_id'] ?? '',
        'assigned_date' => $_POST['assigned_date'] ?? ''
    ];

    $errors = validate(
        $data,
        [
            'asset_id' => 'required',
            'employee_id' => 'required',
            'assigned_date' => 'required'
        ]
    );

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/asset_assignments/create.php');
    }

    $stmt = $db->prepare("
        INSERT INTO asset_assignments 
        (
            asset_id, 
            employee_id, 
            assigned_date, 
            expected_return_date, 
            assigned_by, 
            assignment_notes
        ) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data['asset_id'],
        $data['employee_id'],
        $data['assigned_date'],
        $_POST['expected_return_date'] ?: null,
        $_SESSION['user_id'],
        $_POST['assignment_notes'] ?: null
    ]);

    $db->prepare("
        UPDATE assets 
        SET status = 'assigned' 
        WHERE id = ?
    ")->execute([
        $data['asset_id']
    ]);

    logAudit(
        $_SESSION['user_id'],
        'assign_asset',
        'asset_assignments'
    );

    flash('success', 'Asset assigned successfully');

    to('http://localhost/ERP/modules/asset_assignments/index.php');
}
?>

<div class="mb-6">
    <a href="<?= url('modules/asset_assignments/index.php') ?>"
        class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Assignments
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6 w-full">

    <h2 class="text-xl font-semibold mb-6">
        Assign Asset to Employee
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
        id="assetAssignmentForm"
        class="space-y-4"
        novalidate>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Asset -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Asset <span class="text-red-600">*</span>
                </label>

                <select
                    name="asset_id"
                    id="asset_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Asset</option>

                    <?php foreach ($assets as $a): ?>

                        <option
                            value="<?= htmlspecialchars($a['id'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= $preSelectedAsset == $a['id'] ? 'selected' : '' ?>>
                            <?= sanitize($a['asset_code']) ?> -
                            <?= sanitize($a['asset_name']) ?>
                        </option>

                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Employee -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Employee <span class="text-red-600">*</span>
                </label>

                <select
                    name="employee_id"
                    id="employee_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Employee</option>

                    <?php foreach ($employees as $e): ?>

                        <option
                            value="<?= htmlspecialchars($e['id'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= sanitize($e['first_name'] . ' ' . $e['last_name']) ?>
                            (<?= sanitize($e['employee_code']) ?>)
                        </option>

                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <!-- Dates -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Assigned Date -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Assigned Date <span class="text-red-600">*</span>
                </label>

                <input
                    type="date"
                    name="assigned_date"
                    id="assigned_date"
                    value="<?= old('assigned_date', date('Y-m-d')) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Expected Return Date -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Expected Return Date
                </label>

                <input
                    type="date"
                    name="expected_return_date"
                    id="expected_return_date"
                    value="<?= old('expected_return_date') ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>

        </div>

        <!-- Notes -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Notes
            </label>

            <textarea
                name="assignment_notes"
                id="assignment_notes"
                rows="2"
                maxlength="500"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('assignment_notes') ?></textarea>
        </div>

        <!-- Buttons -->
        <div class="pt-4">

            <button
                type="submit"
                class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition">
                Assign Asset
            </button>

            <a
                href="<?= url('modules/asset_assignments/index.php') ?>"
                class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">
                Cancel
            </a>

        </div>

    </form>
</div>

<style>
    /* Validation message */
    #assetAssignmentForm label.error,
    #assetAssignmentForm span.error {
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

    /* Invalid fields */
    #assetAssignmentForm select.error,
    #assetAssignmentForm input.error,
    #assetAssignmentForm textarea.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    /* Valid fields */
    #assetAssignmentForm select.valid,
    #assetAssignmentForm input.valid,
    #assetAssignmentForm textarea.valid {
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
         * Set minimum date for Assigned Date.
         * Past dates cannot be selected.
         */
        const today = new Date().toISOString().split('T')[0];

        $('#assigned_date').attr('min', today);

        /*
         * Expected Return Date:
         * Initially today can be selected.
         */
        $('#expected_return_date').attr('min', today);


        /*
         * When Assigned Date changes,
         * Expected Return Date must be same or after Assigned Date.
         */
        $('#assigned_date').on('change', function() {

            const assignedDate = $(this).val();

            if (assignedDate) {
                $('#expected_return_date').attr('min', assignedDate);

                const returnDate = $('#expected_return_date').val();

                if (returnDate && returnDate < assignedDate) {
                    $('#expected_return_date').val('');
                }
            } else {
                $('#expected_return_date').attr('min', today);
            }

        });


        /*
         * jQuery Validation
         */
        $('#assetAssignmentForm').validate({

            rules: {

                asset_id: {
                    required: true
                },

                employee_id: {
                    required: true
                },

                assigned_date: {
                    required: true,
                    date: true
                },

                expected_return_date: {
                    date: true,
                    afterAssignedDate: true
                },

                assignment_notes: {
                    maxlength: 500
                }

            },

            messages: {

                asset_id: {
                    required: "Please select an asset"
                },

                employee_id: {
                    required: "Please select an employee"
                },

                assigned_date: {
                    required: "Please select assigned date",
                    date: "Please enter a valid date"
                },

                expected_return_date: {
                    date: "Please enter a valid return date",
                    afterAssignedDate: "Return date must be on or after assigned date"
                },

                assignment_notes: {
                    maxlength: "Notes cannot exceed 500 characters"
                }

            },

            /*
             * Put dropdown validation message
             * directly below the dropdown.
             */
            errorPlacement: function(error, element) {

                if (element.is('select')) {

                    error.appendTo(element.parent());

                } else {

                    error.insertAfter(element);

                }

            },

            highlight: function(element) {

                $(element).addClass('border-red-500');

            },

            unhighlight: function(element) {

                $(element).removeClass('border-red-500');

            }

        });


        /*
         * Custom validation:
         *
         * Expected Return Date cannot be
         * before Assigned Date.
         */
        $.validator.addMethod(
            "afterAssignedDate",
            function(value, element) {

                if (!value) {
                    return true;
                }

                const assignedDate = $('#assigned_date').val();

                if (!assignedDate) {
                    return true;
                }

                return value >= assignedDate;

            },
            "Return date must be on or after assigned date"
        );


        /*
         * Revalidate return date when
         * assigned date changes.
         */
        $('#assigned_date').on('change', function() {

            $('#expected_return_date').valid();

        });

    });
</script>

<?php require_once '../../includes/footer.php'; ?>