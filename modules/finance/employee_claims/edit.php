<?php
$pageTitle = 'Edit Expense Claim';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/finance_helpers.php';

if (!hasPermission('employee_claims.edit')) {
    die('Access Denied');
}

$db = getDB();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Invalid claim ID.');
    redirect('index.php');
}

$stmt = $db->prepare("
    SELECT *
    FROM employee_expense_claims
    WHERE id = ?
");

$stmt->execute([$id]);

$claim = $stmt->fetch();

if (!$claim) {
    flash('error', 'Claim not found.');
    redirect('index.php');
}

if ($claim['status'] !== 'draft') {
    flash('error', 'Only draft claims can be edited.');
    redirect('view.php?id=' . $id);
}


$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}


$categories = $db->query("
    SELECT id, name
    FROM expense_categories
    WHERE is_active = 1
    ORDER BY name
")->fetchAll();

$employees = $db->query("
    SELECT id, first_name, last_name, employee_code
    FROM employees
    WHERE status = 'active'
    ORDER BY first_name
")->fetchAll();

$projects = $db->query("
    SELECT id, project_name
    FROM projects
    WHERE status = 'active'
    ORDER BY project_name
")->fetchAll();


/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid or expired form token.';
    }


    $employee_id = (int)($_POST['employee_id'] ?? 0);

    $category_id = (int)($_POST['category_id'] ?? 0);

    $project_id = !empty($_POST['project_id'])
        ? (int)$_POST['project_id']
        : null;

    $expense_date = trim(
        $_POST['expense_date'] ?? ''
    );

    $description = trim(
        $_POST['description'] ?? ''
    );

    $amount = (float)str_replace(
        ',',
        '',
        $_POST['amount'] ?? 0
    );

    $tax_amount = (float)str_replace(
        ',',
        '',
        $_POST['tax_amount'] ?? 0
    );


    /*
    |--------------------------------------------------------------------------
    | Server-side validation
    |--------------------------------------------------------------------------
    */

    if ($employee_id <= 0) {
        $errors[] = 'Please select an employee.';
    }

    if ($category_id <= 0) {
        $errors[] = 'Please select a category.';
    }

    if (empty($expense_date)) {
        $errors[] = 'Expense date is required.';
    }

    if ($amount < 0) {
        $errors[] = 'Amount cannot be negative.';
    }

    if ($tax_amount < 0) {
        $errors[] = 'Tax amount cannot be negative.';
    }

    if ($amount + $tax_amount <= 0) {
        $errors[] = 'Total amount must be greater than zero.';
    }


    /*
    |--------------------------------------------------------------------------
    | Active Financial Year Guard
    |--------------------------------------------------------------------------
    */

    $fy = getActiveFinancialYear($db);

    if (
        $fy
        && !empty($expense_date)
        && (
            $expense_date < $fy['start_date']
            || $expense_date > $fy['end_date']
        )
    ) {

        $errors[] =
            'Expense date must be within the active financial year ('
            . date(
                'M d, Y',
                strtotime($fy['start_date'])
            )
            . ' - '
            . date(
                'M d, Y',
                strtotime($fy['end_date'])
            )
            . ').';
    }


    /*
    |--------------------------------------------------------------------------
    | Redirect if validation errors
    |--------------------------------------------------------------------------
    */

    if (!empty($errors)) {

        saveFormState($errors);

        to(
            'modules/finance/employee_claims/edit.php?id=' . $id
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    try {

        $total_amount =
            $amount + $tax_amount;


        $stmt = $db->prepare("
            UPDATE employee_expense_claims
            SET
                employee_id = ?,
                category_id = ?,
                project_id = ?,
                expense_date = ?,
                description = ?,
                amount = ?,
                tax_amount = ?,
                total_amount = ?,
                updated_at = NOW()
            WHERE id = ?
              AND status = 'draft'
        ");


        $stmt->execute([

            $employee_id,

            $category_id,

            $project_id,

            $expense_date,

            $description ?: null,

            $amount,

            $tax_amount,

            $total_amount,

            $id

        ]);


        /*
        |--------------------------------------------------------------------------
        | Audit
        |--------------------------------------------------------------------------
        */

        logAudit(
            $_SESSION['user_id'],
            'Updated claim: ' . $claim['claim_number'],
            'employee_claims'
        );


        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */

        flash(
            'success',
            'Claim updated successfully.',
            'success'
        );


        redirect(
            'view.php?id=' . $id
        );


    } catch (Exception $e) {

        flash(
            'error',
            'Error: ' . $e->getMessage()
        );


        to(
            'modules/finance/employee_claims/edit.php?id=' . $id
        );
    }
}
?>


<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="mb-6 flex justify-between items-center">

    <div>

        <h1 class="text-2xl font-bold text-gray-800">
            Edit Expense Claim
        </h1>

        <p class="text-gray-600">
            <?= sanitize($claim['claim_number']) ?>
        </p>

    </div>


    <a
        href="view.php?id=<?= $id ?>"
        class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"
    >

        <i class="fas fa-arrow-left mr-2"></i>

        Back

    </a>

</div>


<!-- =========================================================
     SERVER SIDE ERRORS
========================================================= -->

<?php if (!empty($errors)): ?>

    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">

        <?php foreach ($errors as $e): ?>

            <p><?= sanitize($e) ?></p>

        <?php endforeach; ?>

    </div>

<?php endif; ?>


<!-- =========================================================
     FORM
========================================================= -->

<form
    method="POST"
    id="expenseClaimForm"
>

    <input
        type="hidden"
        name="csrf_token"
        value="<?= csrfToken() ?>"
    >


    <!-- =====================================================
         CLAIM DETAILS
    ====================================================== -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">


            <!-- Employee -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Employee

                    <span class="text-red-500">*</span>

                </label>


                <select
                    name="employee_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    required
                >

                    <option value="">
                        -- Select --
                    </option>


                    <?php foreach ($employees as $e): ?>

                        <option
                            value="<?= $e['id'] ?>"
                            <?= old(
                                'employee_id',
                                $claim['employee_id']
                            ) == $e['id']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= sanitize(
                                $e['first_name']
                                . ' '
                                . $e['last_name']
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Expense Date -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Expense Date

                    <span class="text-red-500">*</span>

                </label>


                <input
                    type="date"
                    name="expense_date"
                    value="<?= sanitize(
                        old(
                            'expense_date',
                            $claim['expense_date']
                        )
                    ) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    required
                >

            </div>


            <!-- Category -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Category

                    <span class="text-red-500">*</span>

                </label>


                <select
                    name="category_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    required
                >

                    <option value="">
                        -- Select --
                    </option>


                    <?php foreach ($categories as $c): ?>

                        <option
                            value="<?= $c['id'] ?>"
                            <?= old(
                                'category_id',
                                $claim['category_id']
                            ) == $c['id']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= sanitize($c['name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Project -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Project

                </label>


                <select
                    name="project_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                >

                    <option value="">
                        -- Optional --
                    </option>


                    <?php foreach ($projects as $p): ?>

                        <option
                            value="<?= $p['id'] ?>"
                            <?= old(
                                'project_id',
                                $claim['project_id']
                            ) == $p['id']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= sanitize($p['project_name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


        </div>


        <!-- Description -->

        <div class="mt-4">

            <label class="block text-sm font-medium text-gray-700 mb-1">

                Description

            </label>


            <textarea
                name="description"
                rows="2"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
            ><?= sanitize(
                old(
                    'description',
                    $claim['description'] ?? ''
                )
            ) ?></textarea>

        </div>

    </div>


    <!-- =====================================================
         AMOUNT
    ====================================================== -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">


            <!-- Amount -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Amount

                    <span class="text-red-500">*</span>

                </label>


                <input
                    type="number"
                    name="amount"
                    step="0.01"
                    min="0.01"
                    value="<?= sanitize(
                        old(
                            'amount',
                            $claim['amount']
                        )
                    ) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    required
                >

            </div>


            <!-- Tax Amount -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Tax Amount

                </label>


                <input
                    type="number"
                    name="tax_amount"
                    step="0.01"
                    min="0"
                    value="<?= sanitize(
                        old(
                            'tax_amount',
                            $claim['tax_amount']
                        )
                    ) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                >

            </div>


        </div>

    </div>


    <!-- =====================================================
         BUTTONS
    ====================================================== -->

    <div class="flex justify-end gap-4 mb-8">

        <a
            href="view.php?id=<?= $id ?>"
            class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400 transition"
        >

            Cancel

        </a>


        <button
            type="submit"
            class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition"
        >

            <i class="fas fa-save mr-2"></i>

            Update Claim

        </button>

    </div>

</form>


<!-- =========================================================
     JQUERY VALIDATION
========================================================= -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>


<style>

    /*
    |--------------------------------------------------------------------------
    | Validation Message
    |--------------------------------------------------------------------------
    */

    #expenseClaimForm p.error {

        display: block !important;

        width: 100%;

        margin-top: 4px !important;

        margin-bottom: 0 !important;

        clear: both;

    }


    /*
    |--------------------------------------------------------------------------
    | Invalid Field
    |--------------------------------------------------------------------------
    */

    #expenseClaimForm input.error,
    #expenseClaimForm select.error,
    #expenseClaimForm textarea.error {

        border-color: #ef4444 !important;

    }

</style>


<script>

$(document).ready(function () {


    /*
    |--------------------------------------------------------------------------
    | jQuery Validation
    |--------------------------------------------------------------------------
    */

    $("#expenseClaimForm").validate({

        ignore: [],


        /*
        |--------------------------------------------------------------------------
        | Rules
        |--------------------------------------------------------------------------
        */

        rules: {

            employee_id: {

                required: true

            },


            expense_date: {

                required: true

            },


            category_id: {

                required: true

            },


            amount: {

                required: true,

                number: true,

                min: 0.01

            },


            tax_amount: {

                number: true,

                min: 0

            },


            description: {

                maxlength: 500

            }

        },


        /*
        |--------------------------------------------------------------------------
        | Messages
        |--------------------------------------------------------------------------
        */

        messages: {

            employee_id: {

                required:
                    "Please select an employee."

            },


            expense_date: {

                required:
                    "Expense date is required."

            },


            category_id: {

                required:
                    "Please select a category."

            },


            amount: {

                required:
                    "Amount is required.",

                number:
                    "Please enter a valid amount.",

                min:
                    "Amount must be greater than zero."

            },


            tax_amount: {

                number:
                    "Please enter a valid tax amount.",

                min:
                    "Tax amount cannot be negative."

            },


            description: {

                maxlength:
                    "Description cannot exceed 500 characters."

            }

        },


        /*
        |--------------------------------------------------------------------------
        | Error Element
        |--------------------------------------------------------------------------
        */

        errorElement: "p",

        errorClass:
            "error text-red-500 text-sm mt-1 block",


        /*
        |--------------------------------------------------------------------------
        | Error Placement
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | Select error is appended inside its parent DIV.
        |
        | So validation message appears BELOW dropdown.
        |
        |--------------------------------------------------------------------------
        */

        errorPlacement: function (error, element) {

            if (element.is("select")) {

                element.parent().append(error);

            } else {

                element.after(error);

            }

        },


        /*
        |--------------------------------------------------------------------------
        | Highlight
        |--------------------------------------------------------------------------
        */

        highlight: function (element) {

            $(element)
                .addClass("border-red-500")
                .removeClass("border-gray-300");

        },


        /*
        |--------------------------------------------------------------------------
        | Unhighlight
        |--------------------------------------------------------------------------
        */

        unhighlight: function (element) {

            $(element)
                .removeClass("border-red-500")
                .addClass("border-gray-300");

        },


        /*
        |--------------------------------------------------------------------------
        | Submit
        |--------------------------------------------------------------------------
        */

        submitHandler: function (form) {

            form.submit();

        }

    });

});

</script>


<?php require_once '../../../includes/footer.php'; ?>