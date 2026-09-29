<?php
$pageTitle = 'Create Financial Year';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('finance.year.create')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $year_name  = trim($_POST['year_name'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date   = trim($_POST['end_date'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */
    $vErrors = validate($_POST, [
        'year_name'  => 'required|max:50',
        'start_date' => 'required',
        'end_date'   => 'required',
    ]);

    if (!empty($vErrors)) {
        $errors = $vErrors;
    }

    /*
    |--------------------------------------------------------------------------
    | Year Name Format Validation
    | Required: FY 2026-2027
    |--------------------------------------------------------------------------
    */
    if (empty($errors) && $year_name !== '') {

        if (!preg_match('/^FY \d{4}-\d{4}$/', $year_name)) {

            $errors['year_name'] =
                'Year name must be in the format FY 2026-2027';
        } else {

            preg_match_all('/\d{4}/', $year_name, $matches);

            $startYear = (int)($matches[0][0] ?? 0);
            $endYear   = (int)($matches[0][1] ?? 0);

            if ($endYear !== ($startYear + 1)) {

                $errors['year_name'] =
                    'Financial year must contain consecutive years, e.g. FY 2026-2027';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Date Format
    |--------------------------------------------------------------------------
    */
    if (empty($errors) && $start_date !== '') {

        $startDateObj = DateTime::createFromFormat('Y-m-d', $start_date);

        if (
            !$startDateObj ||
            $startDateObj->format('Y-m-d') !== $start_date
        ) {
            $errors['start_date'] = 'Please enter a valid start date';
        }
    }

    if (empty($errors) && $end_date !== '') {

        $endDateObj = DateTime::createFromFormat('Y-m-d', $end_date);

        if (
            !$endDateObj ||
            $endDateObj->format('Y-m-d') !== $end_date
        ) {
            $errors['end_date'] = 'Please enter a valid end date';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | End Date Must Be After Start Date
    |--------------------------------------------------------------------------
    */
    if (
        empty($errors) &&
        $start_date !== '' &&
        $end_date !== ''
    ) {

        if (strtotime($end_date) <= strtotime($start_date)) {

            $errors['end_date'] =
                'End date must be after start date';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Check Date Overlap
    |--------------------------------------------------------------------------
    */
    if (
        empty($errors) &&
        $start_date !== '' &&
        $end_date !== ''
    ) {

        $stmt = $db->prepare("
            SELECT id
            FROM financial_years
            WHERE start_date <= ?
              AND end_date >= ?
            LIMIT 1
        ");

        $stmt->execute([
            $end_date,
            $start_date
        ]);

        if ($stmt->fetch()) {

            $errors['date_overlap'] =
                'The date range overlaps with an existing financial year';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Stop Processing When Validation Fails
    |--------------------------------------------------------------------------
    */
    if (!empty($errors)) {

        saveFormState($errors);

        to('modules/finance/financial_year/create.php');

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Check Existing Financial Years
    |--------------------------------------------------------------------------
    */
    $countStmt = $db->query("
        SELECT COUNT(*) AS cnt
        FROM financial_years
    ");

    $count = (int)$countStmt->fetch()['cnt'];

    /*
    |--------------------------------------------------------------------------
    | First Financial Year = Active
    |--------------------------------------------------------------------------
    */
    $status = ($count === 0) ? 'active' : 'closed';

    try {

        if ($status === 'active') {

            $db->beginTransaction();

            $db->exec("
                UPDATE financial_years
                SET status = 'closed'
                WHERE status = 'active'
            ");
        }

        /*
        |--------------------------------------------------------------------------
        | Insert Financial Year
        |--------------------------------------------------------------------------
        */
        $stmt = $db->prepare("
            INSERT INTO financial_years
            (
                year_name,
                start_date,
                end_date,
                status
            )
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $year_name,
            $start_date,
            $end_date,
            $status
        ]);

        /*
        |--------------------------------------------------------------------------
        | Commit
        |--------------------------------------------------------------------------
        */
        if ($status === 'active') {
            $db->commit();
        }

        /*
        |--------------------------------------------------------------------------
        | Audit Log
        |--------------------------------------------------------------------------
        */
        logAudit(
            $_SESSION['user_id'],
            'Created financial year: ' . $year_name,
            'finance'
        );

        /*
        |--------------------------------------------------------------------------
        | Success Message
        |--------------------------------------------------------------------------
        */
        flash(
            'success',
            'Financial year created successfully.',
            'success'
        );

        to('modules/finance/financial_year/index.php');

        exit;
    } catch (Exception $e) {

        if (
            $status === 'active' &&
            $db->inTransaction()
        ) {
            $db->rollBack();
        }

        /*
        |--------------------------------------------------------------------------
        | Duplicate Financial Year Name
        |--------------------------------------------------------------------------
        */
        if (
            $e->getCode() == 23000 &&
            stripos($e->getMessage(), 'year_name') !== false
        ) {

            $errors['year_name'] =
                'A financial year with this name already exists';
        } else {

            $errors['db'] =
                'An error occurred while creating the financial year';
        }
    }
}
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <!-- =========================================================
         Page Header
    ========================================================== -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">

        <div>

            <h1 class="text-2xl font-bold text-gray-800">
                Create Financial Year
            </h1>

            <p class="text-sm text-gray-500 mt-1">
                Add a new financial year period
            </p>

        </div>

        <a
            href="index.php"
            class="inline-flex items-center gap-2
                   px-4 py-2
                   bg-white text-gray-700
                   text-sm font-medium
                   border border-gray-300
                   hover:bg-gray-50
                   transition">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Financial Years
        </a>

    </div>


    <!-- =========================================================
         Form Card
    ========================================================== -->
    <div class="bg-white border border-gray-200 shadow-sm">

        <!-- Card Header -->
        <div class="px-6 py-4 border-b border-gray-200">

            <div class="flex items-center gap-2">

                <i class="fa-solid fa-calendar-days text-blue-600"></i>

                <h2 class="text-sm font-semibold text-gray-800">
                    Financial Year Details
                </h2>

            </div>

        </div>


        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>

            <div class="mx-6 mt-6 bg-red-50 border border-red-200
                        text-red-700 px-4 py-3">

                <div class="flex items-start gap-3">

                    <i class="fa-solid fa-circle-exclamation mt-0.5"></i>

                    <div>

                        <p class="font-semibold text-sm mb-1">
                            Please correct the following errors:
                        </p>

                        <ul class="list-disc list-inside text-sm">

                            <?php foreach ($errors as $error): ?>

                                <li>
                                    <?= sanitize($error) ?>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    </div>

                </div>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             Form
        ====================================================== -->
        <form
            method="POST"
            id="createFinancialYearForm"
            class="p-6"
            novalidate>

            <!-- CSRF -->
            <input
                type="hidden"
                name="csrf_token"
                value="<?= csrfToken() ?>">


            <!-- =================================================
                 Year Name
            ================================================== -->
            <div class="mb-5">

                <label
                    for="year_name"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Year Name
                    <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    id="year_name"
                    name="year_name"
                    value="<?= sanitize(old('year_name')) ?>"
                    maxlength="50"
                    autocomplete="off"
                    placeholder="FY 2026-2027"
                    class="w-full px-3 py-2 text-sm border border-gray-300
                           focus:border-blue-500
                           focus:ring-1 focus:ring-blue-500
                           outline-none
                           <?= isset($errors['year_name'])
                                ? 'border-red-500'
                                : '' ?>">

                <p class="text-xs text-gray-500 mt-1">
                    Format: FY 2026-2027
                </p>

            </div>


            <!-- =================================================
                 Dates
            ================================================== -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                <!-- Start Date -->
                <div>

                    <label
                        for="start_date"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        Start Date
                        <span class="text-red-600">*</span>
                    </label>

                    <input
                        type="date"
                        id="start_date"
                        name="start_date"
                        value="<?= sanitize(old('start_date')) ?>"
                        class="w-full px-3 py-2 text-sm border border-gray-300
                               focus:border-blue-500
                               focus:ring-1 focus:ring-blue-500
                               outline-none
                               <?= isset($errors['start_date'])
                                    ? 'border-red-500'
                                    : '' ?>">

                </div>


                <!-- End Date -->
                <div>

                    <label
                        for="end_date"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        End Date
                        <span class="text-red-600">*</span>
                    </label>

                    <input
                        type="date"
                        id="end_date"
                        name="end_date"
                        value="<?= sanitize(old('end_date')) ?>"
                        class="w-full px-3 py-2 text-sm border border-gray-300
                               focus:border-blue-500
                               focus:ring-1 focus:ring-blue-500
                               outline-none
                               <?= isset($errors['end_date'])
                                    ? 'border-red-500'
                                    : '' ?>">

                    <p class="text-xs text-gray-500 mt-1">
                        End date must be after the start date.
                    </p>

                </div>

            </div>


            <!-- =================================================
                 Buttons
            ================================================== -->
            <div class="flex items-center gap-3 mt-6 pt-5 border-t border-gray-200">

                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2
                           px-5 py-2
                           bg-blue-600 text-white
                           text-sm font-medium
                           border border-blue-600
                           hover:bg-blue-700
                           transition">
                    <i class="fa-solid fa-save"></i>
                    Create Financial Year
                </button>

                <a
                    href="index.php"
                    class="inline-flex items-center justify-center gap-2
                           px-5 py-2
                           bg-white text-gray-700
                           text-sm font-medium
                           border border-gray-300
                           hover:bg-gray-50
                           transition">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>


<script>
    $(document).ready(function() {

        /*
        |--------------------------------------------------------------------------
        | Financial Year Format Validation
        |--------------------------------------------------------------------------
        | Required format:
        | FY 2026-2027
        |--------------------------------------------------------------------------
        */
        $.validator.addMethod(
            'financialYearFormat',
            function(value, element) {

                if (!value) {
                    return true;
                }

                const pattern = /^FY \d{4}-\d{4}$/;

                if (!pattern.test(value)) {
                    return false;
                }

                const years = value.match(/\d{4}/g);

                if (!years || years.length !== 2) {
                    return false;
                }

                const startYear = parseInt(years[0], 10);
                const endYear = parseInt(years[1], 10);

                return endYear === startYear + 1;

            },
            'Year name must be in the format FY 2026-2027'
        );


        /*
        |--------------------------------------------------------------------------
        | End Date Validation
        |--------------------------------------------------------------------------
        */
        $.validator.addMethod(
            'validFinancialYearDateRange',
            function(value, element) {

                const startDate = $('#start_date').val();

                if (!value || !startDate) {
                    return true;
                }

                return value > startDate;

            },
            'End date must be after start date'
        );


        /*
        |--------------------------------------------------------------------------
        | Year Name Input Restriction
        |--------------------------------------------------------------------------
        | Allows:
        | A-Z
        | a-z
        | 0-9
        | Space
        | Hyphen
        |--------------------------------------------------------------------------
        */
        $('#year_name').on('input', function() {

            let value = this.value;

            value = value
                .replace(/[^A-Za-z0-9 -]/g, '')
                .replace(/\s+/g, ' ')
                .slice(0, 50);

            this.value = value;

        });


        /*
        |--------------------------------------------------------------------------
        | Start Date Change
        |--------------------------------------------------------------------------
        */
        $('#start_date').on('change', function() {

            const startDate = $(this).val();
            const endDate = $('#end_date').val();

            if (startDate) {

                /*
                 * End date cannot be before start date.
                 */
                $('#end_date').attr('min', startDate);

                /*
                 * If existing end date is invalid,
                 * clear it.
                 */
                if (endDate && endDate <= startDate) {
                    $('#end_date').val('');
                }

            } else {

                $('#end_date').removeAttr('min');
            }

            $('#end_date').valid();

        });


        /*
        |--------------------------------------------------------------------------
        | Form Validation
        |--------------------------------------------------------------------------
        */
        $('#createFinancialYearForm').validate({

            errorElement: 'span',

            errorClass: 'text-red-500 text-sm mt-1 block',

            errorPlacement: function(error, element) {

                error.insertAfter(element);

            },

            highlight: function(element) {

                $(element)
                    .addClass('border-red-500')
                    .removeClass('border-gray-300');

            },

            unhighlight: function(element) {

                $(element)
                    .removeClass('border-red-500')
                    .addClass('border-gray-300');

            },

            rules: {

                year_name: {
                    required: true,
                    maxlength: 50,
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
                    maxlength: 'Year name cannot exceed 50 characters',
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
        |--------------------------------------------------------------------------
        | Set End Date Minimum on Page Load
        |--------------------------------------------------------------------------
        */
        const existingStartDate = $('#start_date').val();

        if (existingStartDate) {
            $('#end_date').attr('min', existingStartDate);
        }

    });
</script>


<?php require_once '../../../includes/footer.php'; ?>