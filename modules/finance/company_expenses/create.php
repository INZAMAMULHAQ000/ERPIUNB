<?php

$pageTitle = 'Create Company Expense';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../tax/helpers/tax_ui.php';

if (!hasPermission('company_expenses.create')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

/*
|--------------------------------------------------------------------------
| Load data for dropdowns
|--------------------------------------------------------------------------
*/

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
| Expense accounts for line items
|--------------------------------------------------------------------------
*/

$expenseAccounts = $db->query("
    SELECT coa.id, coa.account_code, coa.account_name
    FROM chart_of_accounts coa
    JOIN account_types at ON coa.type_id = at.id
    WHERE at.type_name = 'Expense'
      AND coa.status = 'active'
    ORDER BY coa.account_code
")->fetchAll();


/*
|--------------------------------------------------------------------------
| Generate expense number
|--------------------------------------------------------------------------
*/

$prefix = 'EXP' . date('Ymd');

$stmt = $db->prepare("
    SELECT COUNT(*)
    FROM company_expenses
    WHERE expense_number LIKE ?
");

$stmt->execute([$prefix . '%']);

$expense_number =
    $prefix . '-' .
    str_pad(
        (int)$stmt->fetchColumn() + 1,
        3,
        '0',
        STR_PAD_LEFT
    );


/*
|--------------------------------------------------------------------------
| Default tax type / rate for document-level picker
|--------------------------------------------------------------------------
*/

$defaultType = TaxService::getDefaultTaxType();

$defaultTypeId = $defaultType
    ? (int)$defaultType['id']
    : 0;

$defRate = TaxService::getDefaultTaxRate($defaultTypeId);

$defaultRateId = $defRate
    ? (int)$defRate['id']
    : 0;


/*
|--------------------------------------------------------------------------
| Check expense payable account config
|--------------------------------------------------------------------------
*/

$expPayableId = $db->query("
    SELECT setting_value
    FROM settings
    WHERE setting_key = 'finance_expense_payable_account_id'
")->fetchColumn();


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

    $amountInput = (float)str_replace(
        ',',
        '',
        $_POST['amount'] ?? 0
    );


    /*
    |--------------------------------------------------------------------------
    | Tax engine inputs
    |--------------------------------------------------------------------------
    */

    $tax_rate_id = (int)(
        $_POST['tax']['tax_rate_id'] ?? 0
    );

    $tax_status = $_POST['tax']['tax_status'] ?? 'taxable';

    $exemption_id = (int)(
        $_POST['tax']['tax_exemption_id'] ?? 0
    );

    $items = $_POST['items'] ?? [];


    /*
    |--------------------------------------------------------------------------
    | Basic validation
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


    /*
    |--------------------------------------------------------------------------
    | Validate items
    |--------------------------------------------------------------------------
    */

    $validItems = 0;

    if (!empty($items) && is_array($items)) {

        foreach ($items as $item) {

            $desc = trim(
                $item['description'] ?? ''
            );

            $amt = (float)str_replace(
                ',',
                '',
                $item['amount'] ?? 0
            );

            if (empty($desc) || $amt <= 0) {
                continue;
            }

            $validItems++;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Taxable base
    |--------------------------------------------------------------------------
    */

    $baseAmount = $amountInput;

    if ($validItems > 0) {

        $baseAmount = 0;

        foreach ($items as $item) {

            $desc = trim(
                $item['description'] ?? ''
            );

            $amt = (float)str_replace(
                ',',
                '',
                $item['amount'] ?? 0
            );

            if (empty($desc) || $amt <= 0) {
                continue;
            }

            $baseAmount += $amt;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Positive amount guard
    |--------------------------------------------------------------------------
    */

    if (
        $validItems === 0
        && $amountInput <= 0
    ) {
        $errors[] =
            'Amount must be greater than zero, or add line items.';
    }


    /*
    |--------------------------------------------------------------------------
    | Financial year validation
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
            'Expense date must be within the active financial year (' .
            date('M d, Y', strtotime($fy['start_date'])) .
            ' - ' .
            date('M d, Y', strtotime($fy['end_date'])) .
            ').';
    }


    /*
    |--------------------------------------------------------------------------
    | Redirect if validation errors
    |--------------------------------------------------------------------------
    */

    if (!empty($errors)) {

        saveFormState($errors);

        to(
            'modules/finance/company_expenses/create.php'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Retry on concurrent expense-number collision
    |--------------------------------------------------------------------------
    */

    for ($attempt = 1; $attempt <= 5; $attempt++) {

        $db->beginTransaction();

        try {


            /*
            |--------------------------------------------------------------------------
            | Calculate document tax
            |--------------------------------------------------------------------------
            */

            $calc = TaxService::calculateLineTax(
                $baseAmount,
                [
                    'tax_rate_id' => $tax_rate_id ?: null,
                    'tax_status' => $tax_status,
                    'exemption_id' => $exemption_id ?: null,
                    'date' => $expense_date,
                ]
            );


            $netAmount = (float)$calc['net_amount'];

            $taxAmount = (float)$calc['tax_amount'];

            $totalAmount = (float)$calc['total_amount'];

            $amount = $netAmount;


            /*
            |--------------------------------------------------------------------------
            | Insert company expense
            |--------------------------------------------------------------------------
            */

            $stmt = $db->prepare("
                INSERT INTO company_expenses
                (
                    expense_number,
                    expense_date,
                    category_id,
                    department_id,
                    project_id,
                    vendor_id,
                    description,
                    payment_method,
                    cash_account_id,
                    bank_account_id,
                    amount,
                    tax_amount,
                    total_amount,
                    tax_type_id,
                    tax_rate_id,
                    tax_group_id,
                    tax_status,
                    taxable_amount,
                    tax_exemption_id,
                    status,
                    created_by,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft',
                    ?, NOW(), NOW()
                )
            ");


            $stmt->execute([
                $expense_number,
                $expense_date,
                $category_id,
                $department_id,
                $project_id,
                $vendor_id,
                $description ?: null,
                $payment_method ?: null,
                $cash_account_id,
                $bank_account_id,
                $netAmount,
                $taxAmount,
                $totalAmount,
                $calc['tax_type_id']
                    ? (int)$calc['tax_type_id']
                    : null,
                $calc['tax_rate_id']
                    ? (int)$calc['tax_rate_id']
                    : null,
                null,
                $calc['tax_status'],
                $netAmount,
                $calc['exemption_id']
                    ? (int)$calc['exemption_id']
                    : null,
                $_SESSION['user_id']
            ]);


            $expense_id = (int)$db->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Insert line items
            |--------------------------------------------------------------------------
            */

            if (
                !empty($items)
                && is_array($items)
                && $validItems > 0
            ) {

                $itemStmt = $db->prepare("
                    INSERT INTO expense_items
                    (
                        expense_type,
                        expense_id,
                        description,
                        quantity,
                        unit_price,
                        amount,
                        tax_amount,
                        line_total,
                        sort_order
                    )
                    VALUES
                    (
                        'company_expense',
                        ?, ?, ?, ?, ?, ?, ?, ?
                    )
                ");


                $sort = 0;

                $lines = [];


                foreach ($items as $item) {

                    $desc = trim(
                        $item['description'] ?? ''
                    );

                    $amt = (float)str_replace(
                        ',',
                        '',
                        $item['amount'] ?? 0
                    );

                    if (
                        empty($desc)
                        || $amt <= 0
                    ) {
                        continue;
                    }

                    $qty = (float)str_replace(
                        ',',
                        '',
                        $item['quantity'] ?? 1
                    );

                    $price = $qty > 0
                        ? $amt / $qty
                        : 0;

                    $lines[] = [
                        'desc' => $desc,
                        'qty' => $qty,
                        'price' => $price,
                        'amt' => $amt
                    ];

                    $sort++;
                }


                /*
                |--------------------------------------------------------------------------
                | Distribute tax proportionally
                |--------------------------------------------------------------------------
                */

                $distTotal = 0;

                foreach ($lines as $k => $L) {

                    if ($baseAmount <= 0) {

                        $lineTax = 0;
                    } else {

                        $lineTax = round(
                            (
                                $L['amt'] / $baseAmount
                            ) * $taxAmount,
                            2
                        );
                    }


                    if (
                        $k === array_key_last($lines)
                    ) {

                        $lineTax = round(
                            $taxAmount - $distTotal,
                            2
                        );
                    }


                    $distTotal += $lineTax;

                    $lineTotal =
                        $L['amt'] + $lineTax;


                    $itemStmt->execute([
                        'company_expense',
                        $expense_id,
                        $L['desc'],
                        $L['qty'],
                        $L['price'],
                        $L['amt'],
                        $lineTax,
                        $lineTotal,
                        $k
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

            $db->commit();


            logAudit(
                $_SESSION['user_id'],
                'Created company expense: ' . $expense_number,
                'company_expenses'
            );


            flash(
                'success',
                'Company expense ' .
                    $expense_number .
                    ' created successfully.',
                'success'
            );


            redirect(
                'view.php?id=' . $expense_id
            );
        } catch (Exception $e) {

            $db->rollBack();


            $isDupNumber =
                $e instanceof PDOException
                && (int)$e->getCode() === 23000
                && stripos(
                    $e->getMessage(),
                    'expense_number'
                ) !== false;


            if (
                $isDupNumber
                && $attempt < 5
            ) {

                $stmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM company_expenses
                    WHERE expense_number LIKE ?
                ");

                $stmt->execute([
                    $prefix . '%'
                ]);


                $expense_number =
                    $prefix . '-' .
                    str_pad(
                        (int)$stmt->fetchColumn() + 1,
                        3,
                        '0',
                        STR_PAD_LEFT
                    );

                continue;
            }


            flash(
                'error',
                'Error: ' . $e->getMessage()
            );

            to(
                'modules/finance/company_expenses/create.php'
            );
        }
    }
}

?>


<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="mb-6 flex justify-between items-center">

    <div>

        <h1 class="text-2xl font-bold text-gray-800">
            Create Company Expense
        </h1>

        <p class="text-gray-600">
            Record a company-paid expense
        </p>

    </div>


    <a
        href="index.php"
        class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
        <i class="fas fa-arrow-left mr-2"></i>
        Back
    </a>

</div>


<!-- =========================================================
     EXPENSE PAYABLE WARNING
========================================================= -->

<?php if (!$expPayableId): ?>

    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 px-4 py-3 rounded mb-6 flex items-start gap-3">

        <i class="fas fa-exclamation-triangle mt-1"></i>

        <div>

            <p class="font-medium">
                Expense Payable account not configured
            </p>

            <p class="text-sm mt-1">

                The expense payable account is not set.
                Company expenses cannot be approved until this is configured in

                <a
                    href="<?= url('modules/finance/settings/index.php') ?>"
                    class="underline font-medium hover:text-yellow-900">
                    Finance Settings
                </a>.

            </p>

        </div>

    </div>

<?php endif; ?>


<!-- =========================================================
     SERVER ERRORS
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


    <!-- =========================================================
     EXPENSE DETAILS
========================================================= -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            Expense Details
        </h3>


        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">


            <!-- Expense Number -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Expense Number
                </label>

                <input
                    type="text"
                    value="<?= sanitize($expense_number) ?>"
                    class="w-full px-3 py-2 border rounded bg-gray-50 text-gray-500"
                    readonly>

            </div>


            <!-- Expense Date -->

            <div>

                <label
                    for="expense_date"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Expense Date
                    <span class="text-red-500">*</span>
                </label>

                <input
                    type="date"
                    name="expense_date"
                    id="expense_date"
                    value="<?= sanitize(old('expense_date', date('Y-m-d'))) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


            <!-- Category -->

            <div>

                <label
                    for="category_id"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Category
                    <span class="text-red-500">*</span>
                </label>

                <select
                    name="category_id"
                    id="category_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Select Category --
                    </option>

                    <?php foreach ($categories as $c): ?>

                        <option
                            value="<?= $c['id'] ?>"
                            <?= old('category_id') == $c['id'] ? 'selected' : '' ?>>
                            <?= sanitize($c['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Department -->

            <div>

                <label
                    for="department_id"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Department
                </label>

                <select
                    name="department_id"
                    id="department_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Optional --
                    </option>

                    <?php foreach ($departments as $d): ?>

                        <option
                            value="<?= $d['id'] ?>"
                            <?= old('department_id') == $d['id'] ? 'selected' : '' ?>>
                            <?= sanitize($d['department_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Project -->

            <div>

                <label
                    for="project_id"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Project
                </label>

                <select
                    name="project_id"
                    id="project_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Optional --
                    </option>

                    <?php foreach ($projects as $p): ?>

                        <option
                            value="<?= $p['id'] ?>"
                            <?= old('project_id') == $p['id'] ? 'selected' : '' ?>>
                            <?= sanitize($p['project_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Vendor -->

            <div>

                <label
                    for="vendor_id"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Vendor
                </label>

                <select
                    name="vendor_id"
                    id="vendor_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Optional --
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


            <!-- Payment Method -->

            <div>

                <label
                    for="paymentMethod"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Payment Method
                </label>

                <select
                    name="payment_method"
                    id="paymentMethod"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Select --
                    </option>

                    <option
                        value="bank_transfer"
                        <?= old('payment_method') === 'bank_transfer' ? 'selected' : '' ?>>
                        Bank Transfer
                    </option>

                    <option
                        value="cash"
                        <?= old('payment_method') === 'cash' ? 'selected' : '' ?>>
                        Cash
                    </option>

                    <option
                        value="cheque"
                        <?= old('payment_method') === 'cheque' ? 'selected' : '' ?>>
                        Cheque
                    </option>

                    <option
                        value="credit_card"
                        <?= old('payment_method') === 'credit_card' ? 'selected' : '' ?>>
                        Credit Card
                    </option>

                </select>

            </div>


            <!-- Bank Account -->

            <div
                id="bankField"
                class="<?= old('payment_method') === 'bank_transfer' ? '' : 'hidden' ?>">

                <label
                    for="bank_account_id"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Bank Account
                </label>

                <select
                    name="bank_account_id"
                    id="bank_account_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Select --
                    </option>

                    <?php foreach ($bankAccounts as $b): ?>

                        <option
                            value="<?= $b['id'] ?>"
                            <?= old('bank_account_id') == $b['id'] ? 'selected' : '' ?>>
                            <?= sanitize(
                                $b['account_name'] .
                                    ' (' .
                                    $b['account_number'] .
                                    ')'
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Cash Account -->

            <div
                id="cashField"
                class="<?= old('payment_method') === 'cash' ? '' : 'hidden' ?>">

                <label
                    for="cash_account_id"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Cash Account
                </label>

                <select
                    name="cash_account_id"
                    id="cash_account_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Select --
                    </option>

                    <?php foreach ($cashAccounts as $c): ?>

                        <option
                            value="<?= $c['id'] ?>"
                            <?= old('cash_account_id') == $c['id'] ? 'selected' : '' ?>>
                            <?= sanitize($c['cash_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>


        <!-- Description -->

        <div class="mt-4">

            <label
                for="description"
                class="block text-sm font-medium text-gray-700 mb-1">
                Description
            </label>

            <textarea
                name="description"
                id="description"
                rows="2"
                maxlength="500"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                placeholder="Brief description of the expense"><?= sanitize(old('description')) ?></textarea>

        </div>

    </div>


    <!-- =========================================================
     LINE ITEMS
========================================================= -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <div class="flex justify-between items-center mb-4">

            <h3 class="text-lg font-semibold text-gray-800">
                Line Items
            </h3>

            <button
                type="button"
                onclick="addItem()"
                class="bg-green-600 text-white px-3 py-1.5 rounded text-sm hover:bg-green-700 transition">
                <i class="fas fa-plus mr-1"></i>
                Add Item
            </button>

        </div>


        <div class="overflow-x-auto">

            <table
                class="w-full"
                id="itemsTable">

                <thead class="bg-gray-50">

                    <tr>

                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            #
                        </th>

                        <th
                            class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                            style="min-width:200px">
                            Description
                        </th>

                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                            Qty
                        </th>

                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                            Amount
                        </th>

                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody
                    class="divide-y divide-gray-200"
                    id="itemsBody">

                    <tr class="item-row">

                        <td class="px-3 py-2 text-sm text-gray-500 item-num">
                            1
                        </td>

                        <td class="px-3 py-2">

                            <input
                                type="text"
                                name="items[0][description]"
                                class="w-full px-2 py-2 border rounded text-sm item-description"
                                placeholder="Description">

                        </td>

                        <td class="px-3 py-2">

                            <input
                                type="number"
                                name="items[0][quantity]"
                                value="1"
                                step="0.01"
                                min="0.01"
                                class="w-20 px-2 py-2 border rounded text-sm text-right qty-input">

                        </td>

                        <td class="px-3 py-2">

                            <input
                                type="number"
                                name="items[0][amount]"
                                step="0.01"
                                min="0"
                                value="0"
                                class="w-28 px-2 py-2 border rounded text-sm text-right price-input">

                        </td>

                        <td class="px-3 py-2 text-center">

                            <button
                                type="button"
                                onclick="removeItem(this)"
                                class="text-red-500 hover:text-red-700">
                                <i class="fas fa-trash"></i>
                            </button>

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>


    <!-- =========================================================
     AMOUNT + TAX
========================================================= -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            Amount &amp; Tax
        </h3>


        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">


            <!-- Simple Amount -->

            <div>

                <label
                    for="simpleAmount"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Amount
                    <span class="text-red-500">*</span>
                </label>

                <input
                    type="number"
                    name="amount"
                    id="simpleAmount"
                    step="0.01"
                    min="0"
                    value="<?= sanitize(old('amount', '0')) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <p class="text-xs text-gray-500 mt-1">
                    Used when no line items are entered.
                </p>

            </div>


            <!-- Tax Treatment -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Tax Treatment
                </label>

                <div id="expenseTaxPicker">

                    <?= taxui_doc_line_picker(
                        'tax',
                        $defaultRateId,
                        $defaultTypeId
                    ) ?>

                </div>

            </div>


            <!-- Net Base -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Net Base
                </label>

                <div
                    id="displayBase"
                    class="w-full px-3 py-2 border rounded bg-gray-50 text-right font-mono">
                    0.00
                </div>

            </div>


            <!-- Total -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Total (incl. tax)
                </label>

                <div
                    id="displayTotal"
                    class="w-full px-3 py-2 border rounded bg-gray-50 text-right font-mono font-bold">
                    0.00
                </div>

            </div>

        </div>


        <div class="mt-3 flex items-center justify-between">

            <p class="text-sm text-gray-600">

                Tax Amount:

                <span
                    id="displayTaxAmt"
                    class="font-mono font-bold">
                    0.00
                </span>

            </p>

            <p class="text-xs text-gray-400">
                Line item amounts and the simple amount are the tax base.
                Tax is computed by the Tax Engine.
            </p>

        </div>

    </div>


    <!-- =========================================================
     BUTTONS
========================================================= -->

    <div class="flex justify-end gap-4 mb-8">

        <a
            href="index.php"
            class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400 transition">
            Cancel
        </a>

        <button
            type="submit"
            class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
            <i class="fas fa-save mr-2"></i>
            Create Expense
        </button>

    </div>


</form>


<!-- =========================================================
     TAX JS
========================================================= -->

<script src="../../../tax/helpers/tax_doc.js"></script>


<script>
    window.TAX_INCLUSIVE =
        <?= TaxService::pricesInclusive() ? '1' : '0' ?>;


    function round2(n) {

        return Math.round(
            (n + Number.EPSILON) * 100
        ) / 100;

    }


    function getTaxBase() {

        let sum = 0;

        document
            .querySelectorAll('#itemsBody .item-row')
            .forEach(function(row) {

                const descInput =
                    row.querySelector(
                        'input[name*="[description]"]'
                    );

                const amountInput =
                    row.querySelector('.price-input');

                const desc =
                    descInput ?
                    descInput.value.trim() :
                    '';

                const amt =
                    amountInput ?
                    parseFloat(amountInput.value) || 0 :
                    0;

                if (desc && amt > 0) {
                    sum += amt;
                }

            });


        if (sum > 0) {
            return sum;
        }


        return (
            parseFloat(
                document.getElementById('simpleAmount').value
            ) || 0
        );

    }


    function calcExpense() {

        const base = getTaxBase();


        const rateSel =
            document.querySelector(
                '#expenseTaxPicker .tax-doc-rate'
            );


        let rate = 0;


        if (
            rateSel &&
            rateSel.selectedIndex >= 0
        ) {

            rate =
                parseFloat(
                    rateSel
                    .options[
                        rateSel.selectedIndex
                    ]
                    .getAttribute('data-rate')
                ) || 0;

        }


        const statusSel =
            document.querySelector(
                '#expenseTaxPicker .tax-doc-status'
            );


        const status =
            statusSel ?
            statusSel.value :
            'taxable';


        const excluded = [
            'exempt',
            'zero_rated',
            'out_of_scope'
        ].indexOf(status) !== -1;


        const r =
            excluded ?
            0 :
            rate;


        let net;
        let tax;
        let total;


        if (window.TAX_INCLUSIVE) {

            total = base;

            net =
                base /
                (1 + r / 100);

            tax =
                total - net;

        } else {

            net = base;

            tax =
                net * r / 100;

            total =
                net + tax;

        }


        document.getElementById(
                'displayBase'
            ).textContent =
            round2(net).toFixed(2);


        document.getElementById(
                'displayTaxAmt'
            ).textContent =
            round2(tax).toFixed(2);


        document.getElementById(
                'displayTotal'
            ).textContent =
            round2(total).toFixed(2);


        if (
            window.toggleExemptionSelect &&
            document.getElementById(
                'expenseTaxPicker'
            )
        ) {

            window.toggleExemptionSelect(
                document.getElementById(
                    'expenseTaxPicker'
                )
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Tax / Amount events
    |--------------------------------------------------------------------------
    */

    ['change', 'input'].forEach(function(evt) {

        const taxRate =
            document.querySelector(
                '#expenseTaxPicker .tax-doc-rate'
            );

        const taxStatus =
            document.querySelector(
                '#expenseTaxPicker .tax-doc-status'
            );

        const taxExemption =
            document.querySelector(
                '#expenseTaxPicker .tax-doc-exemption'
            );

        const simpleAmount =
            document.getElementById(
                'simpleAmount'
            );


        if (taxRate) {

            taxRate.addEventListener(
                evt,
                calcExpense
            );

        }


        if (taxStatus) {

            taxStatus.addEventListener(
                evt,
                calcExpense
            );

        }


        if (taxExemption) {

            taxExemption.addEventListener(
                evt,
                function() {

                    if (
                        window.toggleExemptionSelect
                    ) {

                        window.toggleExemptionSelect(
                            document.getElementById(
                                'expenseTaxPicker'
                            )
                        );

                    }

                }
            );

        }


        if (simpleAmount) {

            simpleAmount.addEventListener(
                evt,
                calcExpense
            );

        }


        document
            .querySelectorAll(
                '#itemsBody .item-row .price-input, ' +
                '#itemsBody .item-row .qty-input'
            )
            .forEach(function(el) {

                el.addEventListener(
                    evt,
                    calcExpense
                );

            });

    });


    /*
    |--------------------------------------------------------------------------
    | Payment method toggle
    |--------------------------------------------------------------------------
    */

    document
        .getElementById('paymentMethod')
        .addEventListener(
            'change',
            function() {

                document
                    .getElementById('bankField')
                    .classList
                    .toggle(
                        'hidden',
                        this.value !== 'bank_transfer'
                    );


                document
                    .getElementById('cashField')
                    .classList
                    .toggle(
                        'hidden',
                        this.value !== 'cash'
                    );


                /*
                 * Revalidate payment account
                 */

                if (
                    window.jQuery &&
                    typeof $.fn.validate === 'function'
                ) {

                    $("#expenseForm")
                        .validate()
                        .element("#bank_account_id");


                    $("#expenseForm")
                        .validate()
                        .element("#cash_account_id");

                }

            }
        );


    /*
    |--------------------------------------------------------------------------
    | Dynamic line item index
    |--------------------------------------------------------------------------
    */

    let itemIndex = 1;


    /*
    |--------------------------------------------------------------------------
    | Add item
    |--------------------------------------------------------------------------
    */

    function addItem() {

        const tbody =
            document.getElementById(
                'itemsBody'
            );


        const first =
            tbody.querySelector(
                '.item-row'
            );


        if (!first) {
            return;
        }


        const row =
            first.cloneNode(true);


        const i = itemIndex;


        row
            .querySelectorAll('input')
            .forEach(function(input) {

                const name =
                    input.getAttribute('name');


                if (
                    name &&
                    name.indexOf('[') !== -1
                ) {

                    input.setAttribute(
                        'name',
                        name.replace(
                            /\[0\]/g,
                            '[' + i + ']'
                        )
                    );

                }


                input
                    .classList
                    .remove('border-red-500');


                input
                    .classList
                    .add('border-gray-300');


                if (
                    input.type === 'number'
                ) {

                    if (
                        input.classList.contains(
                            'qty-input'
                        )
                    ) {

                        input.value = '1';

                    } else if (
                        input.classList.contains(
                            'price-input'
                        )
                    ) {

                        input.value = '0';

                    }

                } else {

                    input.value = '';

                }

            });


        /*
         * Remove old validation messages
         */

        row
            .querySelectorAll(
                '.error, .line-error'
            )
            .forEach(function(error) {

                error.remove();

            });


        /*
         * Add events to new row
         */

        row
            .querySelectorAll(
                '.price-input, .qty-input'
            )
            .forEach(function(el) {

                ['change', 'input']
                .forEach(function(evt) {

                    el.addEventListener(
                        evt,
                        calcExpense
                    );

                });

            });


        tbody.appendChild(row);


        itemIndex++;


        renumberItems();


        calcExpense();

    }


    /*
    |--------------------------------------------------------------------------
    | Remove item
    |--------------------------------------------------------------------------
    */

    function removeItem(btn) {

        if (
            document.querySelectorAll(
                '.item-row'
            ).length <= 1
        ) {

            return;
        }


        btn
            .closest('tr')
            .remove();


        renumberItems();

        calcExpense();

    }


    /*
    |--------------------------------------------------------------------------
    | Renumber items
    |--------------------------------------------------------------------------
    */

    function renumberItems() {

        document
            .querySelectorAll('.item-row')
            .forEach(function(row, idx) {

                row
                    .querySelector('.item-num')
                    .textContent =
                    idx + 1;

            });

    }


    /*
    |--------------------------------------------------------------------------
    | DOM loaded
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'DOMContentLoaded',
        function() {

            if (
                window.toggleExemptionSelect
            ) {

                window.toggleExemptionSelect(
                    document.getElementById(
                        'expenseTaxPicker'
                    )
                );

            }


            calcExpense();

        }
    );
</script>


<!-- =========================================================
     jQuery Validation
========================================================= -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function() {

        /*
        |--------------------------------------------------------------------------
        | Check jQuery Validation plugin
        |--------------------------------------------------------------------------
        */

        if (typeof $.fn.validate !== 'function') {

            console.error(
                'jQuery Validation plugin is not loaded.'
            );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Expense Form Validation
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


                description: {
                    maxlength: 500
                },


                amount: {
                    number: true,
                    min: 0
                },


                bank_account_id: {

                    required: function() {

                        return (
                            $("#paymentMethod").val() ===
                            "bank_transfer"
                        );

                    }

                },


                cash_account_id: {

                    required: function() {

                        return (
                            $("#paymentMethod").val() ===
                            "cash"
                        );

                    }

                }

            },


            messages: {

                expense_date: {
                    required: "Expense date is required."
                },


                category_id: {
                    required: "Please select a category."
                },


                description: {
                    maxlength: "Description cannot exceed 500 characters."
                },


                amount: {

                    number: "Please enter a valid amount.",

                    min: "Amount cannot be negative."

                },


                bank_account_id: {
                    required: "Please select a bank account for bank payment."
                },


                cash_account_id: {
                    required: "Please select a cash account for cash payment."
                }

            },


            /*
            |--------------------------------------------------------------------------
            | Error element
            |--------------------------------------------------------------------------
            */

            errorElement: "p",


            /*
            |--------------------------------------------------------------------------
            | Error class
            |--------------------------------------------------------------------------
            |
            | block = message will always occupy a new line.
            |--------------------------------------------------------------------------
            */

            errorClass: "text-red-500 text-sm mt-1 block",


            /*
            |--------------------------------------------------------------------------
            | Error placement
            |--------------------------------------------------------------------------
            |
            | This places the validation message directly below
            | the input/select element.
            |--------------------------------------------------------------------------
            */

            errorPlacement: function(error, element) {

                if (element.is("select")) {

                    var parent = element.parent();

                    error
                        .appendTo(parent)
                        .css({
                            "display": "block",
                            "width": "100%",
                            "margin-top": "4px"
                        });

                } else {

                    error.insertAfter(element);

                }

            },


            /*
            |--------------------------------------------------------------------------
            | Highlight
            |--------------------------------------------------------------------------
            */

            highlight: function(element) {

                $(element)
                    .addClass(
                        "border-red-500"
                    )
                    .removeClass(
                        "border-gray-300"
                    );

            },


            /*
            |--------------------------------------------------------------------------
            | Unhighlight
            |--------------------------------------------------------------------------
            */

            unhighlight: function(element) {

                $(element)
                    .removeClass(
                        "border-red-500"
                    )
                    .addClass(
                        "border-gray-300"
                    );

            },


            /*
            |--------------------------------------------------------------------------
            | Submit handler
            |--------------------------------------------------------------------------
            */

            submitHandler: function(form) {

                /*
                |--------------------------------------------------------------------------
                | Validate dynamic line items
                |--------------------------------------------------------------------------
                */

                if (
                    !validateExpenseLineItems()
                ) {

                    return false;

                }


                /*
                |--------------------------------------------------------------------------
                | Validate simple amount / line items
                |--------------------------------------------------------------------------
                */

                const hasValidItem =
                    hasValidExpenseLineItem();


                const simpleAmount =
                    parseFloat(
                        $("#simpleAmount").val()
                    ) || 0;


                if (
                    !hasValidItem &&
                    simpleAmount <= 0
                ) {

                    showSimpleAmountError();

                    return false;

                }


                removeSimpleAmountError();


                /*
                |--------------------------------------------------------------------------
                | Submit
                |--------------------------------------------------------------------------
                */

                form.submit();

            }

        });


        /*
        |--------------------------------------------------------------------------
        | Validate line items
        |--------------------------------------------------------------------------
        */

        function validateExpenseLineItems() {

            let valid = true;


            $("#itemsBody .item-row")
                .each(function() {

                    const row = $(this);


                    const descriptionInput =
                        row.find(
                            'input[name*="[description]"]'
                        );


                    const quantityInput =
                        row.find(
                            ".qty-input"
                        );


                    const amountInput =
                        row.find(
                            ".price-input"
                        );


                    const description =
                        $.trim(
                            descriptionInput.val() || ""
                        );


                    const quantity =
                        parseFloat(
                            quantityInput.val()
                        ) || 0;


                    const amount =
                        parseFloat(
                            amountInput.val()
                        ) || 0;


                    /*
                    |--------------------------------------------------------------------------
                    | Completely blank row
                    |--------------------------------------------------------------------------
                    */

                    if (
                        description === "" &&
                        amount === 0
                    ) {

                        clearLineError(
                            descriptionInput
                        );

                        clearLineError(
                            quantityInput
                        );

                        clearLineError(
                            amountInput
                        );

                        return;

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Description
                    |--------------------------------------------------------------------------
                    */

                    if (
                        amount > 0 &&
                        description === ""
                    ) {

                        showLineError(
                            descriptionInput,
                            "Description is required."
                        );

                        valid = false;

                    } else {

                        clearLineError(
                            descriptionInput
                        );

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Quantity
                    |--------------------------------------------------------------------------
                    */

                    if (
                        description !== "" &&
                        quantity <= 0
                    ) {

                        showLineError(
                            quantityInput,
                            "Quantity must be greater than zero."
                        );

                        valid = false;

                    } else {

                        clearLineError(
                            quantityInput
                        );

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Amount
                    |--------------------------------------------------------------------------
                    */

                    if (
                        description !== "" &&
                        amount <= 0
                    ) {

                        showLineError(
                            amountInput,
                            "Amount must be greater than zero."
                        );

                        valid = false;

                    } else {

                        clearLineError(
                            amountInput
                        );

                    }

                });


            return valid;

        }


        /*
        |--------------------------------------------------------------------------
        | Show line error
        |--------------------------------------------------------------------------
        */

        function showLineError(
            input,
            message
        ) {

            input
                .addClass(
                    "border-red-500"
                )
                .removeClass(
                    "border-gray-300"
                );


            if (
                input.next(".line-error").length === 0
            ) {

                input.after(
                    '<p class="line-error text-red-500 text-xs mt-1 block">' +
                    message +
                    '</p>'
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Clear line error
        |--------------------------------------------------------------------------
        */

        function clearLineError(input) {

            input
                .removeClass(
                    "border-red-500"
                )
                .addClass(
                    "border-gray-300"
                );


            input
                .next(".line-error")
                .remove();

        }


        /*
        |--------------------------------------------------------------------------
        | Check valid line item
        |--------------------------------------------------------------------------
        */

        function hasValidExpenseLineItem() {

            let hasValid = false;


            $("#itemsBody .item-row")
                .each(function() {

                    const description =
                        $.trim(
                            $(this)
                            .find(
                                'input[name*="[description]"]'
                            )
                            .val() || ""
                        );


                    const amount =
                        parseFloat(
                            $(this)
                            .find(".price-input")
                            .val()
                        ) || 0;


                    if (
                        description !== "" &&
                        amount > 0
                    ) {

                        hasValid = true;

                    }

                });


            return hasValid;

        }


        /*
        |--------------------------------------------------------------------------
        | Simple amount error
        |--------------------------------------------------------------------------
        */

        function showSimpleAmountError() {

            $("#simpleAmount")
                .addClass(
                    "border-red-500"
                )
                .removeClass(
                    "border-gray-300"
                );


            if (
                $("#simpleAmount-error-custom").length === 0
            ) {

                $("#simpleAmount").after(
                    '<p id="simpleAmount-error-custom" ' +
                    'class="text-red-500 text-sm mt-1 block">' +
                    'Amount must be greater than zero, or add line items.' +
                    '</p>'
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Remove simple amount error
        |--------------------------------------------------------------------------
        */

        function removeSimpleAmountError() {

            $("#simpleAmount")
                .removeClass(
                    "border-red-500"
                )
                .addClass(
                    "border-gray-300"
                );


            $("#simpleAmount-error-custom")
                .remove();

        }


        /*
        |--------------------------------------------------------------------------
        | Live line item validation
        |--------------------------------------------------------------------------
        */

        $(document).on(
            "input change",
            "#itemsBody .item-description, " +
            "#itemsBody .qty-input, " +
            "#itemsBody .price-input",
            function() {

                validateExpenseLineItems();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Amount live validation
        |--------------------------------------------------------------------------
        */

        $("#simpleAmount").on(
            "input change",
            function() {

                const amount =
                    parseFloat(
                        $(this).val()
                    ) || 0;


                if (amount < 0) {

                    $(this)
                        .addClass(
                            "border-red-500"
                        )
                        .removeClass(
                            "border-gray-300"
                        );

                } else {

                    $(this)
                        .removeClass(
                            "border-red-500"
                        )
                        .addClass(
                            "border-gray-300"
                        );

                }

            }
        );

    });
</script>


<?php require_once '../../../includes/footer.php'; ?>