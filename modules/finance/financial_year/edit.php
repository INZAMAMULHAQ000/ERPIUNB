<?php
$pageTitle = 'Edit Financial Year';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('finance.year.edit')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$fy = null;

$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
    flash('error', 'Invalid financial year ID.', 'error');
    to('modules/finance/financial_year/index.php');
}

$stmt = $db->prepare("SELECT * FROM financial_years WHERE id = ?");
$stmt->execute([$id]);
$fy = $stmt->fetch();

if (!$fy) {
    flash('error', 'Financial year not found.', 'error');
    to('modules/finance/financial_year/index.php');
}

// --- Activate handler ---
if (isset($_GET['activate'])) {
    try {
        $db->beginTransaction();

        $db->exec("UPDATE financial_years SET status = 'closed' WHERE status = 'active'");

        $stmt2 = $db->prepare(
            "UPDATE financial_years SET status = 'active' WHERE id = ?"
        );
        $stmt2->execute([$fy['id']]);

        $db->commit();

        logAudit(
            $_SESSION['user_id'],
            'Activated financial year: ' . $fy['year_name'],
            'finance'
        );

        flash('success', 'Financial year activated successfully.', 'success');
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        flash('error', 'Failed to activate financial year.', 'error');
    }

    to('modules/finance/financial_year/edit.php?id=' . $fy['id']);
}

// --- Close handler ---
if (isset($_GET['close'])) {

    if ($fy['status'] === 'active') {

        flash(
            'error',
            'Cannot close the active financial year. Activate another year first.',
            'error'
        );
    } else {

        $stmt2 = $db->prepare(
            "UPDATE financial_years SET status = 'closed' WHERE id = ?"
        );
        $stmt2->execute([$fy['id']]);

        logAudit(
            $_SESSION['user_id'],
            'Closed financial year: ' . $fy['year_name'],
            'finance'
        );

        flash(
            'success',
            'Financial year closed successfully.',
            'success'
        );
    }

    to('modules/finance/financial_year/edit.php?id=' . $fy['id']);
}

// --- Update handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $year_name = trim($_POST['year_name'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');

    // Server-side validation
    $vErrors = validate($_POST, [
        'year_name' => 'required|max:50',
        'start_date' => 'required',
        'end_date' => 'required',
    ]);

    if (!empty($vErrors)) {
        $errors = $vErrors;
    }

    // Validate end_date > start_date
    if (empty($vErrors) && $start_date && $end_date) {

        if (strtotime($end_date) <= strtotime($start_date)) {
            $errors['end_date'] = 'End date must be after start date';
        }
    }

    // Check for date overlap (exclude current record)
    if (empty($errors) && $start_date && $end_date) {

        $stmt = $db->prepare("
            SELECT id
            FROM financial_years
            WHERE (
                (start_date <= ? AND end_date >= ?)
                OR
                (start_date <= ? AND end_date >= ?)
            )
            AND id != ?
        ");

        $stmt->execute([
            $end_date,
            $start_date,
            $end_date,
            $start_date,
            $fy['id']
        ]);

        if ($stmt->fetch()) {
            $errors['date_overlap'] =
                'The date range overlaps with an existing financial year';
        }
    }

    if (!empty($errors)) {

        saveFormState($errors);

        to(
            'modules/finance/financial_year/edit.php?id=' . $id
        );
    }

    try {

        $stmt = $db->prepare("
            UPDATE financial_years
            SET year_name = ?, start_date = ?, end_date = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $year_name,
            $start_date,
            $end_date,
            $fy['id']
        ]);

        logAudit(
            $_SESSION['user_id'],
            'Updated financial year: ' . $year_name,
            'finance'
        );

        flash(
            'success',
            'Financial year updated successfully.',
            'success'
        );

        to('modules/finance/financial_year/index.php');
    } catch (Exception $e) {

        if (
            $e->getCode() == 23000 &&
            strpos($e->getMessage(), 'year_name') !== false
        ) {

            $errors['year_name'] =
                'A financial year with this name already exists';
        } else {

            $errors['db'] =
                'An error occurred while updating the financial year';
        }
    }
}
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            Edit Financial Year
        </h1>

        <p class="text-gray-600">
            Update financial year details
        </p>
    </div>

    <a href="index.php"
        class="text-blue-600 hover:text-blue-900">

        <i class="fas fa-arrow-left mr-1"></i>
        Back to Financial Years
    </a>
</div>

<div class="bg-white rounded-lg shadow w-full">

    <?php if (!empty($errors)): ?>

        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 m-6">

            <ul class="list-disc list-inside">

                <?php foreach ($errors as $error): ?>

                    <li><?= sanitize($error) ?></li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <!-- Status bar -->
    <div class="mx-6 mt-6 flex items-center gap-4 p-3 rounded
        <?= $fy['status'] === 'active'
            ? 'bg-green-50 border border-green-200'
            : 'bg-gray-50 border border-gray-200' ?>">

        <div>

            <?php if ($fy['status'] === 'active'): ?>

                <span class="px-2 py-1 text-xs rounded bg-green-100 text-green-800 font-medium">
                    Active
                </span>

            <?php else: ?>

                <span class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-800 font-medium">
                    Closed
                </span>

            <?php endif; ?>

        </div>

        <div class="text-sm text-gray-600">

            <?php if ($fy['is_default']): ?>

                <span class="text-blue-600 font-medium">
                    Default Financial Year
                </span>

            <?php endif; ?>

        </div>

        <div class="ml-auto flex gap-2">

            <?php if ($fy['status'] === 'active'): ?>

                <a href="?id=<?= $fy['id'] ?>&close=1"
                    class="text-orange-600 hover:text-orange-900 text-sm font-medium"
                    onclick="return confirm('Are you sure you want to close <?= sanitize(addslashes($fy['year_name'])) ?>?')">

                    <i class="fas fa-lock mr-1"></i>
                    Close

                </a>

            <?php else: ?>

                <a href="?id=<?= $fy['id'] ?>&activate=1"
                    class="text-green-600 hover:text-green-900 text-sm font-medium"
                    onclick="return confirm('Activate <?= sanitize(addslashes($fy['year_name'])) ?>? This will deactivate all other financial years.')">

                    <i class="fas fa-check-circle mr-1"></i>
                    Activate

                </a>

            <?php endif; ?>

        </div>
    </div>


    <!-- Edit Form -->
    <form method="POST"
        id="editFinancialYearForm"
        class="p-6 space-y-4"
        novalidate>

        <input type="hidden"
            name="csrf_token"
            value="<?= csrfToken() ?>">


        <!-- Year Name -->
        <div>

            <label for="year_name"
                class="block text-sm font-medium text-gray-700 mb-1">

                Year Name<span class="text-red-600">*</span>

            </label>

            <input type="text"
                id="year_name"
                name="year_name"
                value="<?= sanitize($_POST['year_name'] ?? $fy['year_name']) ?>"
                placeholder="e.g., FY 2026-2027"
                maxlength="50"
                autocomplete="off"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500
                   <?= isset($errors['year_name']) ? 'border-red-500' : '' ?>">

            <p class="text-xs text-gray-500 mt-1">
                A unique name for this financial year
            </p>

        </div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Start Date -->
        <div>

            <label for="start_date"
                class="block text-sm font-medium text-gray-700 mb-1">

                Start Date<span class="text-red-600">*</span>

            </label>

            <input type="date"
                id="start_date"
                name="start_date"
                value="<?= sanitize($_POST['start_date'] ?? $fy['start_date']) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500
                   <?= isset($errors['start_date']) ? 'border-red-500' : '' ?>">

        </div>


        <!-- End Date -->
        <div>

            <label for="end_date"
                class="block text-sm font-medium text-gray-700 mb-1">

                End Date<span class="text-red-600">*</span>

            </label>

            <input type="date"
                id="end_date"
                name="end_date"
                value="<?= sanitize($_POST['end_date'] ?? $fy['end_date']) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500
                   <?= isset($errors['end_date']) ? 'border-red-500' : '' ?>">

        </div>
</div>

        <!-- Buttons -->
        <div class="pt-4 flex gap-3">

            <button type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">

                <i class="fas fa-save mr-2"></i>
                Update Financial Year

            </button>

            <a href="index.php"
                class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400 transition">

                Cancel

            </a>

        </div>

    </form>

</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>
<script>
    $(document).ready(function() {

        /*
         * Year Name
         * Allow letters, numbers, spaces and hyphen
         */
        $('#year_name').on('input', function() {

            this.value = this.value
                .replace(/[^A-Za-z0-9 -]/g, '')
                .slice(0, 50);

        });


        /*
         * Date validation
         */
        $.validator.addMethod(
            'validFinancialYearDateRange',
            function(value, element) {

                const startDate = $('#start_date').val();

                if (!value || !startDate) {
                    return true;
                }

                return new Date(value) > new Date(startDate);

            },
            'End date must be after start date'
        );


        /*
         * jQuery Validate
         */
        $('#createFinancialYearForm').validate({

            errorElement: 'span',

            errorClass: 'text-red-500 text-sm mt-1 block',

            errorPlacement: function(error, element) {
                error.insertAfter(element);
            },

            highlight: function(element) {
                $(element).addClass('border-red-500');
            },

            unhighlight: function(element) {
                $(element).removeClass('border-red-500');
            },

            rules: {

                year_name: {
                    required: true,
                    financialYearFormat: true
                },

                start_date: {
                    required: true,
                    date: true
                },

                end_date: {
                    required: true,
                    date: true,
                    validFinancialYearDateRange: true
                }

            },

            messages: {

                year_name: {
                    required: 'Year name is required',
                    financialYearFormat: 'Enter year name like FY 2026-2027'
                },

                start_date: {
                    required: 'Start date is required',
                    date: 'Please enter a valid start date'
                },

                end_date: {
                    required: 'End date is required',
                    date: 'Please enter a valid end date',
                    validFinancialYearDateRange: 'End date must be after start date'
                }

            }

        });


        /*
         * Revalidate End Date when Start Date changes
         */
        $('#start_date').on('change', function() {

            const startDate = $(this).val();

            if (startDate) {
                $('#end_date').attr('min', startDate);

                // Already selected end date is before start date
                const endDate = $('#end_date').val();

                if (endDate && endDate < startDate) {
                    $('#end_date').val('');
                }
            } else {
                $('#end_date').removeAttr('min');
            }

            $('#end_date').valid();
        });

        $.validator.addMethod(
            'financialYearFormat',
            function(value, element) {

                if (!value) {
                    return true;
                }

                // Required format: FY 2026-2027
                const pattern = /^FY \d{4}-\d{4}$/;

                if (!pattern.test(value)) {
                    return false;
                }

                const years = value.match(/\d{4}/g);

                const startYear = parseInt(years[0], 10);
                const endYear = parseInt(years[1], 10);

                return endYear === startYear + 1;
            },
            'Year name must be in the format FY 2026-2027'
        );

    });
</script>


<?php require_once '../../../includes/footer.php'; ?>