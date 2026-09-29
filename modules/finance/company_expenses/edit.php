<?php
$pageTitle = 'Edit Company Expense';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/finance_helpers.php';

if (!hasPermission('company_expenses.edit')) {
    die('Access Denied');
}

$db = getDB();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Invalid expense ID.');
    redirect('index.php');
}

$stmt = $db->prepare("SELECT * FROM company_expenses WHERE id = ?");
$stmt->execute([$id]);

$expense = $stmt->fetch();

if (!$expense) {
    flash('error', 'Expense not found.');
    redirect('index.php');
}

if ($expense['status'] !== 'draft') {
    flash('error', 'Only draft expenses can be edited.');
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

$departments = $db->query("
    SELECT id, department_name
    FROM departments
    WHERE status = 'active'
    ORDER BY department_name
")->fetchAll();

$projects = $db->query("
    SELECT id, project_name
    FROM projects
    WHERE status = 'active'
    ORDER BY project_name
")->fetchAll();

$vendors = $db->query("
    SELECT id, vendor_name
    FROM vendors
    WHERE status = 'active'
    ORDER BY vendor_name
")->fetchAll();

$bankAccounts = $db->query("
    SELECT id, account_name, account_number
    FROM bank_accounts
    WHERE status = 'active'
    ORDER BY account_name
")->fetchAll();

$cashAccounts = $db->query("
    SELECT id, cash_name
    FROM cash_accounts
    WHERE status = 'active'
    ORDER BY cash_name
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

    $category_id = (int)($_POST['category_id'] ?? 0);

    $department_id = !empty($_POST['department_id'])
        ? (int)$_POST['department_id']
        : null;

    $project_id = !empty($_POST['project_id'])
        ? (int)$_POST['project_id']
        : null;

    $vendor_id = !empty($_POST['vendor_id'])
        ? (int)$_POST['vendor_id']
        : null;

    $expense_date = trim($_POST['expense_date'] ?? '');

    $description = trim($_POST['description'] ?? '');

    $payment_method = trim($_POST['payment_method'] ?? '');

    $cash_account_id = !empty($_POST['cash_account_id'])
        ? (int)$_POST['cash_account_id']
        : null;

    $bank_account_id = !empty($_POST['bank_account_id'])
        ? (int)$_POST['bank_account_id']
        : null;

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

    if ($category_id <= 0) {
        $errors[] = 'Please select a category.';
    }

    if (empty($expense_date)) {
        $errors[] = 'Expense date is required.';
    }

    if (
        $payment_method === 'bank_transfer'
        && $bank_account_id <= 0
    ) {
        $errors[] = 'Please select a bank account for bank payment.';
    }

    if (
        $payment_method === 'cash'
        && $cash_account_id <= 0
    ) {
        $errors[] = 'Please select a cash account for cash payment.';
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
            . date('M d, Y', strtotime($fy['start_date']))
            . ' - '
            . date('M d, Y', strtotime($fy['end_date']))
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
            'modules/finance/company_expenses/edit.php?id=' . $id
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    try {

        $total_amount = $amount + $tax_amount;

        $stmt = $db->prepare("
            UPDATE company_expenses
            SET
                category_id = ?,
                department_id = ?,
                project_id = ?,
                vendor_id = ?,
                expense_date = ?,
                description = ?,
                payment_method = ?,
                cash_account_id = ?,
                bank_account_id = ?,
                amount = ?,
                tax_amount = ?,
                total_amount = ?,
                updated_at = NOW()
            WHERE id = ?
              AND status = 'draft'
        ");

        $stmt->execute([
            $category_id,
            $department_id,
            $project_id,
            $vendor_id,
            $expense_date,
            $description ?: null,
            $payment_method ?: null,
            $cash_account_id,
            $bank_account_id,
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
            'Updated company expense: ' . $expense['expense_number'],
            'company_expenses'
        );


        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */

        flash(
            'success',
            'Expense updated successfully.',
            'success'
        );

        redirect('view.php?id=' . $id);
    } catch (Exception $e) {

        flash(
            'error',
            'Error: ' . $e->getMessage()
        );

        to(
            'modules/finance/company_expenses/edit.php?id=' . $id
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
            Edit Company Expense
        </h1>

        <p class="text-gray-600">
            <?= sanitize($expense['expense_number']) ?>
        </p>

    </div>


    <a
        href="view.php?id=<?= $id ?>"
        class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
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
    id="expenseForm">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= csrfToken() ?>">


    <!-- =====================================================
         BASIC INFORMATION
    ====================================================== -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">


            <!-- Expense Date -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Expense Date

                    <span class="text-red-500">*</span>

                </label>

                <input
                    type="date"
                    name="expense_date"
                    value="<?= sanitize(old('expense_date', $expense['expense_date'])) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    required>

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
                    required>

                    <option value="">
                        -- Select --
                    </option>

                    <?php foreach ($categories as $c): ?>

                        <option
                            value="<?= $c['id'] ?>"
                            <?= old('category_id', $expense['category_id']) == $c['id'] ? 'selected' : '' ?>>
                            <?= sanitize($c['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Department -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Department

                </label>

                <select
                    name="department_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Optional --
                    </option>

                    <?php foreach ($departments as $d): ?>

                        <option
                            value="<?= $d['id'] ?>"
                            <?= old('department_id', $expense['department_id']) == $d['id'] ? 'selected' : '' ?>>
                            <?= sanitize($d['department_name']) ?>
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
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Optional --
                    </option>

                    <?php foreach ($projects as $p): ?>

                        <option
                            value="<?= $p['id'] ?>"
                            <?= old('project_id', $expense['project_id']) == $p['id'] ? 'selected' : '' ?>>
                            <?= sanitize($p['project_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

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
                        -- Optional --
                    </option>

                    <?php foreach ($vendors as $v): ?>

                        <option
                            value="<?= $v['id'] ?>"
                            <?= old('vendor_id', $expense['vendor_id']) == $v['id'] ? 'selected' : '' ?>>
                            <?= sanitize($v['vendor_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Payment Method -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Payment Method

                </label>

                <select
                    name="payment_method"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Select --
                    </option>

                    <option
                        value="bank_transfer"
                        <?= old('payment_method', $expense['payment_method']) === 'bank_transfer' ? 'selected' : '' ?>>
                        Bank Transfer
                    </option>

                    <option
                        value="cash"
                        <?= old('payment_method', $expense['payment_method']) === 'cash' ? 'selected' : '' ?>>
                        Cash
                    </option>

                    <option
                        value="cheque"
                        <?= old('payment_method', $expense['payment_method']) === 'cheque' ? 'selected' : '' ?>>
                        Cheque
                    </option>

                    <option
                        value="credit_card"
                        <?= old('payment_method', $expense['payment_method']) === 'credit_card' ? 'selected' : '' ?>>
                        Credit Card
                    </option>

                </select>

            </div>


            <!-- Bank Account -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Bank Account

                </label>

                <select
                    name="bank_account_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Select --
                    </option>

                    <?php foreach ($bankAccounts as $b): ?>

                        <option
                            value="<?= $b['id'] ?>"
                            <?= old('bank_account_id', $expense['bank_account_id']) == $b['id'] ? 'selected' : '' ?>>
                            <?= sanitize($b['account_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Cash Account -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Cash Account

                </label>

                <select
                    name="cash_account_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Select --
                    </option>

                    <?php foreach ($cashAccounts as $c): ?>

                        <option
                            value="<?= $c['id'] ?>"
                            <?= old('cash_account_id', $expense['cash_account_id']) == $c['id'] ? 'selected' : '' ?>>
                            <?= sanitize($c['cash_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


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

                </label>

                <input
                    type="number"
                    name="amount"
                    step="0.01"
                    min="0"
                    value="<?= sanitize(old('amount', $expense['amount'])) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

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
                    value="<?= sanitize(old('tax_amount', $expense['tax_amount'])) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


        </div>

    </div>


    <!-- =====================================================
         DESCRIPTION
    ====================================================== -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <label class="block text-sm font-medium text-gray-700 mb-1">

            Description

        </label>

        <textarea
            name="description"
            rows="2"
            class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= sanitize(old('description', $expense['description'] ?? '')) ?></textarea>

    </div>


    <!-- =====================================================
         BUTTONS
    ====================================================== -->

    <div class="flex justify-end gap-4 mb-8">

        <a
            href="view.php?id=<?= $id ?>"
            class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400 transition">
            Cancel
        </a>

        <button
            type="submit"
            class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
            <i class="fas fa-save mr-2"></i>
            Update Expense
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
     * Validation message
     */
    #expenseForm p.error {
        display: block !important;
        width: 100%;
        margin-top: 4px !important;
        margin-bottom: 0 !important;
        clear: both;
    }


    /*
     * Invalid fields
     */
    #expenseForm input.error,
    #expenseForm select.error,
    #expenseForm textarea.error {
        border-color: #ef4444 !important;
    }
</style>


<script>
    $(document).ready(function() {

        /*
        |--------------------------------------------------------------------------
        | jQuery Validation
        |--------------------------------------------------------------------------
        */

        $("#expenseForm").validate({

            ignore: [],

            rules: {

                expense_date: {
                    required: true
                },

                category_id: {
                    required: true
                },

                payment_method: {
                    required: true
                },

                bank_account_id: {

                    required: function() {

                        return $('select[name="payment_method"]').val() ===
                            'bank_transfer';

                    }

                },

                cash_account_id: {

                    required: function() {

                        return $('select[name="payment_method"]').val() ===
                            'cash';

                    }

                },

                amount: {

                    number: true,

                    min: 0

                },

                tax_amount: {

                    number: true,

                    min: 0

                },

                description: {

                    maxlength: 500

                }

            },


            messages: {

                expense_date: {

                    required: "Expense date is required."

                },

                category_id: {

                    required: "Please select a category."

                },

                payment_method: {

                    required: "Please select a payment method."

                },

                bank_account_id: {

                    required: "Please select a bank account for bank payment."

                },

                cash_account_id: {

                    required: "Please select a cash account for cash payment."

                },

                amount: {

                    number: "Please enter a valid amount.",

                    min: "Amount cannot be negative."

                },

                tax_amount: {

                    number: "Please enter a valid tax amount.",

                    min: "Tax amount cannot be negative."

                },

                description: {

                    maxlength: "Description cannot exceed 500 characters."

                }

            },


            /*
            |--------------------------------------------------------------------------
            | Error Element
            |--------------------------------------------------------------------------
            */

            errorElement: "p",

            errorClass: "error text-red-500 text-sm mt-1 block",


            /*
            |--------------------------------------------------------------------------
            | Error Placement
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            | Select error is appended inside its parent DIV.
            | Therefore message appears BELOW the dropdown.
            |
            |--------------------------------------------------------------------------
            */

            errorPlacement: function(error, element) {

                if (element.is("select")) {

                    /*
                     * Example:
                     *
                     * <div>
                     *     <label>Category</label>
                     *     <select></select>
                     *     <p class="error">Please select...</p>
                     * </div>
                     */

                    element.parent().append(error);

                } else {

                    /*
                     * Input / textarea
                     */

                    element.after(error);

                }

            },


            /*
            |--------------------------------------------------------------------------
            | Highlight
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
            | Submit
            |--------------------------------------------------------------------------
            */

            submitHandler: function(form) {

                form.submit();

            }

        });


        /*
        |--------------------------------------------------------------------------
        | Payment Method Change
        |--------------------------------------------------------------------------
        |
        | When payment method changes:
        |
        | Bank Transfer -> Bank Account validation
        | Cash          -> Cash Account validation
        |
        |--------------------------------------------------------------------------
        */

        $('select[name="payment_method"]').on('change', function() {

            $('select[name="bank_account_id"]').valid();

            $('select[name="cash_account_id"]').valid();

        });


        /*
        |--------------------------------------------------------------------------
        | Bank Account Change
        |--------------------------------------------------------------------------
        */

        $('select[name="bank_account_id"]').on('change', function() {

            if (
                $('select[name="payment_method"]').val() ===
                'bank_transfer'
            ) {

                $(this).valid();

            }

        });


        /*
        |--------------------------------------------------------------------------
        | Cash Account Change
        |--------------------------------------------------------------------------
        */

        $('select[name="cash_account_id"]').on('change', function() {

            if (
                $('select[name="payment_method"]').val() ===
                'cash'
            ) {

                $(this).valid();

            }

        });


    });
</script>


<?php require_once '../../../includes/footer.php'; ?>