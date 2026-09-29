<?php

$pageTitle = 'Create Vendor Bill';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../tax/helpers/tax_ui.php';

if (!hasPermission('vendor_bills.create')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$vendors = $db->query("
    SELECT
        id,
        vendor_code,
        vendor_name,
        default_expense_account_id,
        default_payable_account_id
    FROM vendors
    WHERE status = 'active'
    ORDER BY vendor_name
")->fetchAll();


// Generate bill number
$prefix = 'BILL' . date('Ymd');

$stmt = $db->prepare("
    SELECT COUNT(*)
    FROM vendor_bills
    WHERE bill_number LIKE ?
");

$stmt->execute([$prefix . '%']);

$bill_number = $prefix . '-' . str_pad(
    (int) $stmt->fetchColumn() + 1,
    3,
    '0',
    STR_PAD_LEFT
);


// Chart of accounts for expense dropdown
$expenseAccounts = $db->query("
    SELECT
        id,
        account_code,
        account_name
    FROM chart_of_accounts
    WHERE type_id = (
        SELECT id
        FROM account_types
        WHERE type_name = 'Expense'
        LIMIT 1
    )
    AND status = 'active'
    ORDER BY account_code
")->fetchAll();

if (empty($expenseAccounts)) {
    $expenseAccounts = $db->query("
        SELECT
            id,
            account_code,
            account_name
        FROM chart_of_accounts
        WHERE status = 'active'
        ORDER BY account_code
        LIMIT 50
    ")->fetchAll();
}


// Default tax type / rate for new lines
$defaultType = TaxService::getDefaultTaxType();
$defaultTypeId = $defaultType ? (int) $defaultType['id'] : 0;

$defRate = TaxService::getDefaultTaxRate($defaultTypeId);
$defaultRateId = $defRate ? (int) $defRate['id'] : 0;


// Check AP account configuration
$apAccountId = $db->query("
    SELECT setting_value
    FROM settings
    WHERE setting_key = 'finance_ap_account_id'
")->fetchColumn();

$expenseAccountSetting = $db->query("
    SELECT setting_value
    FROM settings
    WHERE setting_key = 'finance_expense_account_id'
")->fetchColumn();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid or expired form token.';
    }

    $vendor_id = (int) ($_POST['vendor_id'] ?? 0);

    $po_id = !empty($_POST['po_id'])
        ? (int) $_POST['po_id']
        : null;

    $grn_id = !empty($_POST['grn_id'])
        ? (int) $_POST['grn_id']
        : null;

    $bill_date = trim($_POST['bill_date'] ?? '');

    $due_date = trim($_POST['due_date'] ?? '');

    $currency = trim($_POST['currency'] ?? 'AED');

    $vendor_invoice_number = trim(
        $_POST['vendor_invoice_number'] ?? ''
    );

    $notes = trim($_POST['notes'] ?? '');

    $items = $_POST['items'] ?? [];


    /*
    |--------------------------------------------------------------------------
    | Server-side validation
    |--------------------------------------------------------------------------
    */

    if ($vendor_id <= 0) {
        $errors[] = 'Please select a vendor.';
    }

    if (empty($bill_date)) {
        $errors[] = 'Bill date is required.';
    }

    if (empty($due_date)) {
        $errors[] = 'Due date is required.';
    }

    if (
        $due_date &&
        $bill_date &&
        $due_date < $bill_date
    ) {
        $errors[] = 'Due date cannot be before bill date.';
    }


    if (empty($items) || !is_array($items)) {

        $errors[] = 'At least one item is required.';
    } else {

        $validItems = 0;

        foreach ($items as $item) {

            $desc = trim($item['description'] ?? '');

            $qty = (float) str_replace(
                ',',
                '',
                $item['quantity'] ?? 0
            );

            $price = (float) str_replace(
                ',',
                '',
                $item['unit_price'] ?? 0
            );

            if (
                empty($desc) ||
                $qty <= 0 ||
                $price <= 0
            ) {
                continue;
            }

            $validItems++;
        }

        if ($validItems === 0) {
            $errors[] =
                'At least one valid item (with description, quantity, unit price) is required.';
        }
    }


    if (!empty($errors)) {

        saveFormState($errors);

        to('modules/finance/vendor_bills/create.php');
    }


    $subtotal = 0;
    $total_tax = 0;
    $total_discount = 0;
    $total_amount = 0;


    try {

        $db->beginTransaction();

        $processedItems = [];
        $idx = 0;


        /*
        |--------------------------------------------------------------------------
        | Process line items
        |--------------------------------------------------------------------------
        */

        foreach ($items as $item) {

            $desc = trim(
                $item['description'] ?? ''
            );

            $qty = (float) str_replace(
                ',',
                '',
                $item['quantity'] ?? 0
            );

            $unit = trim(
                $item['unit'] ?? ''
            );

            $price = (float) str_replace(
                ',',
                '',
                $item['unit_price'] ?? 0
            );

            $disc_pct = (float) str_replace(
                ',',
                '',
                $item['discount_percent'] ?? 0
            );

            $tax_rate_id = (int) (
                $item['tax_rate_id'] ?? 0
            );

            $tax_status = $item['tax_status']
                ?? 'taxable';

            $exemption_id = (int) (
                $item['exemption_id'] ?? 0
            );

            $expense_account_id =
                !empty($item['expense_account_id'])
                ? (int) $item['expense_account_id']
                : null;


            if (
                empty($desc) ||
                $qty <= 0 ||
                $price <= 0
            ) {
                continue;
            }


            $gross_base = $qty * $price;

            $disc_amt =
                $gross_base * ($disc_pct / 100);

            $after_disc =
                $gross_base - $disc_amt;


            $calc = TaxService::calculateLineTax(
                $after_disc,
                [
                    'tax_rate_id' =>
                    $tax_rate_id ?: null,

                    'tax_status' =>
                    $tax_status,

                    'exemption_id' =>
                    $exemption_id ?: null,

                    'date' =>
                    $bill_date,
                ]
            );


            $subtotal += $gross_base;

            $total_discount += $disc_amt;

            $total_tax +=
                (float) $calc['tax_amount'];

            $total_amount +=
                (float) $calc['total_amount'];


            $processedItems[] = [

                'description' => $desc,

                'quantity' => $qty,

                'unit' => $unit,

                'unit_price' => $price,

                'discount_percent' => $disc_pct,

                'tax_type_id' =>
                $calc['tax_type_id']
                    ? (int) $calc['tax_type_id']
                    : null,

                'tax_rate_id' =>
                $calc['tax_rate_id']
                    ? (int) $calc['tax_rate_id']
                    : null,

                'tax_status' =>
                $calc['tax_status'],

                'taxable_amount' =>
                (float) $calc['net_amount'],

                'tax_exemption_id' =>
                $calc['exemption_id']
                    ? (int) $calc['exemption_id']
                    : null,

                'tax_percent' =>
                (float) $calc['rate_percentage'],

                'tax_amount' =>
                (float) $calc['tax_amount'],

                'line_total' =>
                (float) $calc['total_amount'],

                'expense_account_id' =>
                $expense_account_id,

                'sort_order' => $idx++,
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | Quantity / amount validation against PO
        |--------------------------------------------------------------------------
        */

        if ($po_id) {

            $poCheck = $db->prepare("
                SELECT
                    id,
                    total_amount,
                    status
                FROM purchase_orders
                WHERE id = ?
            ");

            $poCheck->execute([$po_id]);

            $poData = $poCheck->fetch();


            if (!$poData) {

                $errors[] =
                    'Selected purchase order not found.';
            } elseif (
                in_array(
                    $poData['status'],
                    [
                        'draft',
                        'cancelled',
                        'closed'
                    ]
                )
            ) {

                $errors[] =
                    'Selected purchase order is ' .
                    $poData['status'] .
                    ' and cannot be billed against.';
            } else {

                $billedSoFar = $db->prepare("
                    SELECT
                        COALESCE(SUM(total_amount), 0)
                    FROM vendor_bills
                    WHERE po_id = ?
                    AND id != ?
                    AND status NOT IN ('cancelled')
                ");

                $billedSoFar->execute([
                    $po_id,
                    0
                ]);

                $alreadyBilled =
                    (float) $billedSoFar->fetchColumn();

                $remaining =
                    (float) $poData['total_amount']
                    - $alreadyBilled;


                if (
                    $total_amount >
                    $remaining + 0.01
                ) {

                    $errors[] =
                        'Bill total (' .
                        number_format(
                            $total_amount,
                            2
                        ) .
                        ') exceeds remaining unbilled PO amount (' .
                        number_format(
                            $remaining,
                            2
                        ) .
                        ').';
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Quantity / amount validation against GRN
        |--------------------------------------------------------------------------
        */

        if ($grn_id && empty($errors)) {

            $grnCheck = $db->prepare("
                SELECT
                    id,
                    status
                FROM goods_receipt_notes
                WHERE id = ?
            ");

            $grnCheck->execute([$grn_id]);

            $grnData = $grnCheck->fetch();


            if (!$grnData) {

                $errors[] =
                    'Selected GRN not found.';
            } elseif (
                $grnData['status'] !== 'completed'
            ) {

                $errors[] =
                    'Selected GRN is not yet completed.';
            } else {

                $grnTotal = $db->prepare("
                    SELECT
                        COALESCE(SUM(line_total), 0)
                    FROM finance_goods_receipt_items
                    WHERE grn_id = ?
                ");

                $grnTotal->execute([$grn_id]);

                $grnTotalAmount =
                    (float) $grnTotal->fetchColumn();


                $billedSoFar = $db->prepare("
                    SELECT
                        COALESCE(SUM(total_amount), 0)
                    FROM vendor_bills
                    WHERE grn_id = ?
                    AND id != ?
                    AND status NOT IN ('cancelled')
                ");

                $billedSoFar->execute([
                    $grn_id,
                    0
                ]);

                $alreadyBilled =
                    (float) $billedSoFar->fetchColumn();


                $remaining =
                    $grnTotalAmount -
                    $alreadyBilled;


                if (
                    $total_amount >
                    $remaining + 0.01
                ) {

                    $errors[] =
                        'Bill total (' .
                        number_format(
                            $total_amount,
                            2
                        ) .
                        ') exceeds remaining unbilled GRN amount (' .
                        number_format(
                            $remaining,
                            2
                        ) .
                        ').';
                }
            }
        }


        if (!empty($errors)) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            saveFormState($errors);

            to(
                'modules/finance/vendor_bills/create.php'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Insert Vendor Bill
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            INSERT INTO vendor_bills (
                bill_number,
                vendor_invoice_number,
                vendor_id,
                po_id,
                grn_id,
                bill_date,
                due_date,
                currency,
                status,
                subtotal,
                discount_amount,
                discount_type,
                tax_amount,
                total_amount,
                paid_amount,
                balance_amount,
                notes,
                created_by,
                created_at,
                updated_at
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, 'draft',
                ?, ?, ?, ?, ?, 0, ?, ?, ?, NOW(), NOW()
            )
        ");


        $stmt->execute([

            $bill_number,

            $vendor_invoice_number
                ?: null,

            $vendor_id,

            $po_id,

            $grn_id,

            $bill_date,

            $due_date,

            $currency,

            $subtotal,

            $total_discount,

            $total_discount > 0
                ? 'percentage'
                : null,

            $total_tax,

            $total_amount,

            $total_amount,

            $notes,

            $_SESSION['user_id'],
        ]);


        $bill_id =
            (int) $db->lastInsertId();


        /*
        |--------------------------------------------------------------------------
        | Insert Bill Items
        |--------------------------------------------------------------------------
        */

        $itemStmt = $db->prepare("
            INSERT INTO vendor_bill_items (
                bill_id,
                description,
                quantity,
                unit,
                unit_price,
                discount_percent,
                tax_type_id,
                tax_rate_id,
                tax_status,
                taxable_amount,
                tax_exemption_id,
                tax_percent,
                tax_amount,
                line_total,
                expense_account_id,
                sort_order
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?
            )
        ");


        foreach ($processedItems as $pi) {

            $itemStmt->execute([

                $bill_id,

                $pi['description'],

                $pi['quantity'],

                $pi['unit'],

                $pi['unit_price'],

                $pi['discount_percent'],

                $pi['tax_type_id'],

                $pi['tax_rate_id'],

                $pi['tax_status'],

                $pi['taxable_amount'],

                $pi['tax_exemption_id'],

                $pi['tax_percent'],

                $pi['tax_amount'],

                $pi['line_total'],

                $pi['expense_account_id'],

                $pi['sort_order'],
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Status history
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            INSERT INTO bill_status_history (
                bill_id,
                from_status,
                to_status,
                changed_by,
                change_note
            )
            VALUES (
                ?, NULL, 'draft', ?, 'Vendor bill created'
            )
        ");

        $stmt->execute([
            $bill_id,
            $_SESSION['user_id']
        ]);


        $db->commit();


        logAudit(
            $_SESSION['user_id'],
            'Created vendor bill: ' . $bill_number,
            'vendor_bills'
        );


        flash(
            'success',
            'Vendor bill ' .
                $bill_number .
                ' created successfully.',
            'success'
        );


        redirect(
            'view.php?id=' . $bill_id
        );
    } catch (Exception $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        flash(
            'error',
            'Error: ' . $e->getMessage()
        );

        to(
            'modules/finance/vendor_bills/create.php'
        );
    }
}

?>

<div class="mb-6 flex justify-between items-center">

    <div>

        <h1 class="text-2xl font-bold text-gray-800">
            Create Vendor Bill
        </h1>

        <p class="text-gray-600">
            Record a bill from a vendor
        </p>

    </div>

    <a
        href="index.php"
        class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
        <i class="fas fa-arrow-left mr-2"></i>
        Back
    </a>

</div>


<?php if (!$apAccountId || !$expenseAccountSetting): ?>

    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 px-4 py-3 rounded mb-6 flex items-start gap-3">

        <i class="fas fa-exclamation-triangle mt-1"></i>

        <div>

            <p class="font-medium">
                Finance account mapping not fully configured
            </p>

            <p class="text-sm mt-1">

                The AP account
                <?= !$expenseAccountSetting
                    ? ' or default expense account'
                    : '' ?>
                is not set.

                Vendor bills cannot be approved until these are configured in

                <a
                    href="<?= url('modules/finance/settings/index.php') ?>"
                    class="underline font-medium hover:text-yellow-900">
                    Finance Settings
                </a>.

            </p>

        </div>

    </div>

<?php endif; ?>


<?php if (!empty($errors)): ?>

    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">

        <?php foreach ($errors as $e): ?>

            <p><?= sanitize($e) ?></p>

        <?php endforeach; ?>

    </div>

<?php endif; ?>


<form
    method="POST"
    id="billForm"
    novalidate>

    <input
        type="hidden"
        name="csrf_token"
        value="<?= csrfToken() ?>">


    <!-- Bill Information -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">


            <!-- Bill Number -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Bill Number
                </label>

                <input
                    type="text"
                    value="<?= sanitize($bill_number) ?>"
                    class="w-full px-3 py-2 border rounded bg-gray-50 text-gray-500"
                    readonly>

            </div>


            <!-- Vendor -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Vendor

                    <span class="text-red-500">*</span>

                </label>

                <select
                    name="vendor_id"
                    id="vendorSelect"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Select Vendor --
                    </option>

                    <?php foreach ($vendors as $v): ?>

                        <option
                            value="<?= $v['id'] ?>"
                            <?= old('vendor_id') == $v['id']
                                ? 'selected'
                                : '' ?>>
                            <?= sanitize(
                                $v['vendor_name'] .
                                    ' (' .
                                    $v['vendor_code'] .
                                    ')'
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Bill Date -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Bill Date

                    <span class="text-red-500">*</span>

                </label>

                <input
                    type="date"
                    name="bill_date"
                    value="<?= old(
                                'bill_date',
                                date('Y-m-d')
                            ) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


            <!-- Due Date -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Due Date

                    <span class="text-red-500">*</span>

                </label>

                <input
                    type="date"
                    name="due_date"
                    value="<?= old(
                                'due_date',
                                date(
                                    'Y-m-d',
                                    strtotime('+30 days')
                                )
                            ) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


            <!-- Currency -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Currency
                </label>

                <select
                    name="currency"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option
                        value="AED"
                        <?= old('currency', 'AED') === 'AED'
                            ? 'selected'
                            : '' ?>>
                        AED
                    </option>

                    <option
                        value="USD"
                        <?= old('currency') === 'USD'
                            ? 'selected'
                            : '' ?>>
                        USD
                    </option>

                    <option
                        value="EUR"
                        <?= old('currency') === 'EUR'
                            ? 'selected'
                            : '' ?>>
                        EUR
                    </option>

                    <option
                        value="GBP"
                        <?= old('currency') === 'GBP'
                            ? 'selected'
                            : '' ?>>
                        GBP
                    </option>

                </select>

            </div>


            <!-- Vendor Invoice Number -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Vendor Invoice #
                </label>

                <input
                    type="text"
                    name="vendor_invoice_number"
                    id="vendorInvoiceNumber"
                    value="<?= sanitize(
                                old('vendor_invoice_number')
                            ) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    placeholder="Vendor's invoice reference">

            </div>


            <!-- Purchase Order -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Purchase Order
                </label>

                <select
                    name="po_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Optional --
                    </option>

                </select>

            </div>


            <!-- GRN -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    GRN
                </label>

                <select
                    name="grn_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Optional --
                    </option>

                </select>

            </div>

        </div>

    </div>


    <!-- Items -->

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
                            style="min-width:180px">
                            Description
                        </th>

                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                            Qty
                        </th>

                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            Unit
                        </th>

                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                            Unit Price
                        </th>

                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                            Disc %
                        </th>

                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">
                            Tax
                        </th>

                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                            Tax Amt
                        </th>

                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            Expense Acct
                        </th>

                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                            Total
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
                                class="w-full px-2 py-2 border rounded text-sm"
                                placeholder="Item description">

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
                                type="text"
                                name="items[0][unit]"
                                class="w-16 px-2 py-2 border rounded text-sm"
                                placeholder="pcs">

                        </td>


                        <td class="px-3 py-2">

                            <input
                                type="number"
                                name="items[0][unit_price]"
                                step="0.0001"
                                min="0"
                                value="0"
                                class="w-28 px-2 py-2 border rounded text-sm text-right price-input">

                        </td>


                        <td class="px-3 py-2">

                            <input
                                type="number"
                                name="items[0][discount_percent]"
                                step="0.01"
                                min="0"
                                max="100"
                                value="0"
                                class="w-16 px-2 py-2 border rounded text-sm text-right disc-input">

                        </td>


                        <td class="px-3 py-2">

                            <input
                                type="hidden"
                                class="base-before-tax"
                                value="0">

                            <?= taxui_doc_line_picker(
                                'items[0]',
                                $defaultRateId,
                                $defaultTypeId
                            ) ?>

                        </td>


                        <td class="px-3 py-2">

                            <input
                                type="text"
                                class="w-24 px-2 py-2 border-0 bg-transparent text-right font-mono text-sm line-tax"
                                value="0.00"
                                readonly>

                        </td>


                        <td class="px-3 py-2">

                            <select
                                name="items[0][expense_account_id]"
                                class="w-36 px-2 py-2 border rounded text-sm">

                                <option value="">
                                    Default
                                </option>

                                <?php foreach ($expenseAccounts as $a): ?>

                                    <option value="<?= $a['id'] ?>">
                                        <?= sanitize(
                                            $a['account_code']
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </td>


                        <td class="px-3 py-2">

                            <input
                                type="text"
                                class="w-28 px-2 py-2 border-0 bg-transparent text-right font-mono font-bold text-sm line-total"
                                value="0.00"
                                readonly>

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


                <tfoot class="bg-gray-50">

                    <tr>

                        <td
                            colspan="9"
                            class="px-3 py-2 text-right text-sm font-medium text-gray-700">
                            Subtotal (before discount):
                        </td>

                        <td
                            class="px-3 py-2 font-mono text-sm font-bold"
                            id="displaySubtotal">
                            0.00
                        </td>

                        <td></td>

                    </tr>


                    <tr>

                        <td
                            colspan="9"
                            class="px-3 py-2 text-right text-sm text-gray-600">
                            Discount:
                        </td>

                        <td
                            class="px-3 py-2 font-mono text-sm text-gray-600"
                            id="displayDiscount">
                            0.00
                        </td>

                        <td></td>

                    </tr>


                    <tr>

                        <td
                            colspan="9"
                            class="px-3 py-2 text-right text-sm text-gray-600">
                            Tax:
                        </td>

                        <td
                            class="px-3 py-2 font-mono text-sm text-gray-600"
                            id="displayTax">
                            0.00
                        </td>

                        <td></td>

                    </tr>


                    <tr>

                        <td
                            colspan="9"
                            class="px-3 py-2 text-right text-sm font-bold text-gray-900">
                            Grand Total:
                        </td>

                        <td
                            class="px-3 py-2 font-mono text-sm font-bold text-lg text-gray-900"
                            id="displayTotal">
                            0.00
                        </td>

                        <td></td>

                    </tr>

                </tfoot>

            </table>

        </div>

    </div>


    <input
        type="hidden"
        name="total_subtotal"
        id="subtotal_hidden"
        value="0">

    <input
        type="hidden"
        name="total_discount"
        id="discount_hidden"
        value="0">

    <input
        type="hidden"
        name="total_tax"
        id="tax_hidden"
        value="0">

    <input
        type="hidden"
        name="total_amount"
        id="total_hidden"
        value="0">


    <!-- Notes -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <label class="block text-sm font-medium text-gray-700 mb-1">
            Notes
        </label>

        <textarea
            name="notes"
            id="billNotes"
            rows="2"
            class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('notes') ?></textarea>

    </div>


    <!-- Buttons -->

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
            Create Bill
        </button>

    </div>

</form>


<script src="../../../tax/helpers/tax_doc.js"></script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>
<script>
    window.TAX_INCLUSIVE =
        <?= TaxService::pricesInclusive() ? '1' : '0' ?>;

    let itemIndex = 1;


    /*
    |--------------------------------------------------------------------------
    | Vendor → PO / GRN
    |--------------------------------------------------------------------------
    */

    document
        .getElementById('vendorSelect')
        .addEventListener('change', function() {

            const vendorId = this.value;

            const poSelect =
                document.querySelector(
                    'select[name="po_id"]'
                );

            const grnSelect =
                document.querySelector(
                    'select[name="grn_id"]'
                );


            poSelect.innerHTML =
                '<option value="">-- Optional --</option>';

            grnSelect.innerHTML =
                '<option value="">-- Optional --</option>';


            if (!vendorId) {
                return;
            }


            fetch(
                    'ajax_get_po_grn.php?vendor_id=' +
                    encodeURIComponent(vendorId)
                )

                .then(function(r) {
                    return r.json();
                })

                .then(function(data) {

                    if (
                        data.pos &&
                        data.pos.length
                    ) {

                        data.pos.forEach(function(po) {

                            const remaining =
                                parseFloat(
                                    po.total_amount
                                ) -
                                parseFloat(
                                    po.total_billed
                                );


                            const opt =
                                document.createElement(
                                    'option'
                                );

                            opt.value = po.id;

                            opt.textContent =
                                po.po_number +
                                ' (' +
                                po.status.replace(
                                    '_',
                                    ' '
                                ) +
                                ' - ' +
                                remaining.toFixed(2) +
                                ' unbilled)';

                            poSelect.appendChild(opt);

                        });
                    }


                    if (
                        data.grns &&
                        data.grns.length
                    ) {

                        data.grns.forEach(function(grn) {

                            const opt =
                                document.createElement(
                                    'option'
                                );

                            opt.value = grn.id;

                            opt.textContent =
                                grn.grn_number +
                                ' (' +
                                grn.receipt_date +
                                ')';

                            grnSelect.appendChild(opt);

                        });
                    }

                })

                .catch(function() {
                    // Keep empty PO / GRN lists if AJAX fails.
                });

        });


    /*
    |--------------------------------------------------------------------------
    | Add Item
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
            .querySelectorAll(
                'input, select'
            )
            .forEach(function(input) {

                const name =
                    input.getAttribute(
                        'name'
                    );


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


                const tag =
                    input.tagName.toLowerCase();


                if (tag === 'input') {

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

                        } else if (
                            input.classList.contains(
                                'disc-input'
                            )
                        ) {

                            input.value = '0';
                        }

                    } else if (
                        input.type === 'text'
                    ) {

                        const isCalc =
                            input.classList.contains(
                                'line-total'
                            ) ||
                            input.classList.contains(
                                'line-tax'
                            );


                        if (
                            input.classList.contains(
                                'line-total'
                            ) ||
                            input.classList.contains(
                                'line-tax'
                            )
                        ) {

                            input.value = '0.00';

                        } else if (!isCalc) {

                            input.value = '';
                        }

                    } else if (
                        input.type === 'hidden'
                    ) {

                        input.value = '0';
                    }

                } else if (
                    tag === 'select'
                ) {

                    input.selectedIndex = 0;
                }

            });


        const expSel =
            row.querySelector(
                '.tax-doc-exemption'
            );


        if (expSel) {
            expSel.hidden = true;
        }


        if (
            typeof window.wireTaxRow ===
            'function'
        ) {

            window.wireTaxRow(row);
        }


        tbody.appendChild(row);

        itemIndex++;

        renumberItems();

        recalcDocTotals(
            'itemsTable'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Item
    |--------------------------------------------------------------------------
    */

    function removeItem(btn) {

        const row =
            btn.closest('tr');


        if (
            document.querySelectorAll(
                '.item-row'
            ).length <= 1
        ) {

            return;
        }


        row.remove();

        renumberItems();

        recalcDocTotals(
            'itemsTable'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Renumber Items
    |--------------------------------------------------------------------------
    */

    function renumberItems() {

        document
            .querySelectorAll(
                '.item-row'
            )
            .forEach(
                function(row, idx) {

                    const number =
                        row.querySelector(
                            '.item-num'
                        );

                    if (number) {
                        number.textContent =
                            idx + 1;
                    }

                }
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Initial Tax Setup
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'DOMContentLoaded',
        function() {

            document
                .querySelectorAll(
                    '#itemsBody .item-row'
                )
                .forEach(
                    function(row) {

                        if (
                            typeof window.wireTaxRow ===
                            'function'
                        ) {

                            window.wireTaxRow(row);
                        }

                    }
                );


            recalcDocTotals(
                'itemsTable'
            );


            /*
            |--------------------------------------------------------------------------
            | Auto-populate expense account from vendor default
            |--------------------------------------------------------------------------
            */

            const vendorSelect =
                document.getElementById(
                    'vendorSelect'
                );


            const expenseSelects =
                document.querySelectorAll(
                    'select[name$="[expense_account_id]"]'
                );


            const vendorId =
                vendorSelect.value;


            if (vendorId) {

                <?php foreach ($vendors as $v): ?>

                    if (
                        vendorId ===
                        '<?= $v['id'] ?>' &&
                        '<?= (int) $v['default_expense_account_id'] ?>'
                    ) {

                        expenseSelects.forEach(
                            function(sel) {

                                sel.value =
                                    '<?= (int) $v['default_expense_account_id'] ?>';

                            }
                        );
                    }

                <?php endforeach; ?>

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | jQuery Validation
    |--------------------------------------------------------------------------
    */

    $(document).ready(function() {


        /*
        |--------------------------------------------------------------------------
        | Input Filtering
        |--------------------------------------------------------------------------
        */

        $(document).on(
            'input',
            'input[name="vendor_invoice_number"]',
            function() {

                this.value =
                    this.value
                    .replace(
                        /[^A-Za-z0-9 ._\/#\-]/g,
                        ''
                    )
                    .slice(0, 100);

            }
        );


        $(document).on(
            'input',
            'textarea[name="notes"]',
            function() {

                this.value =
                    this.value.slice(
                        0,
                        1000
                    );

            }
        );


        $(document).on(
            'input',
            '.item-row input[name$="[description]"]',
            function() {

                this.value =
                    this.value
                    .replace(
                        /[^A-Za-z0-9 .,&()_\/@#+'\-]/g,
                        ''
                    )
                    .replace(
                        /\s{2,}/g,
                        ' '
                    )
                    .slice(
                        0,
                        200
                    );

            }
        );


        $(document).on(
            'input',
            '.item-row input[name$="[unit]"]',
            function() {

                this.value =
                    this.value
                    .replace(
                        /[^A-Za-z0-9 .\/\-]/g,
                        ''
                    )
                    .slice(
                        0,
                        30
                    );

            }
        );


        $(document).on(
            'input',
            '.item-row input[name$="[quantity]"]',
            function() {

                let value =
                    this.value.replace(
                        /[^0-9.]/g,
                        ''
                    );


                const parts =
                    value.split('.');


                if (parts.length > 2) {

                    value =
                        parts[0] +
                        '.' +
                        parts.slice(1).join('');

                }


                if (parts.length === 2) {

                    value =
                        parts[0] +
                        '.' +
                        parts[1].slice(0, 3);
                }


                this.value = value;

            }
        );


        $(document).on(
            'input',
            '.item-row input[name$="[unit_price]"]',
            function() {

                let value =
                    this.value.replace(
                        /[^0-9.]/g,
                        ''
                    );


                const parts =
                    value.split('.');


                if (parts.length > 2) {

                    value =
                        parts[0] +
                        '.' +
                        parts.slice(1).join('');

                }


                if (parts.length === 2) {

                    value =
                        parts[0] +
                        '.' +
                        parts[1].slice(0, 4);
                }


                this.value = value;

            }
        );


        $(document).on(
            'input',
            '.item-row input[name$="[discount_percent]"]',
            function() {

                let value =
                    this.value.replace(
                        /[^0-9.]/g,
                        ''
                    );


                const parts =
                    value.split('.');


                if (parts.length > 2) {

                    value =
                        parts[0] +
                        '.' +
                        parts.slice(1).join('');

                }


                if (parts.length === 2) {

                    value =
                        parts[0] +
                        '.' +
                        parts[1].slice(0, 2);
                }


                let number =
                    parseFloat(value);


                if (
                    !isNaN(number) &&
                    number > 100
                ) {

                    number = 100;
                }


                this.value =
                    isNaN(number) ?
                    value :
                    number;

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Custom Due Date Validation
        |--------------------------------------------------------------------------
        */

        $.validator.addMethod(
            'validDueDate',
            function(value, element) {

                const billDate =
                    $('input[name="bill_date"]').val();


                if (
                    !value ||
                    !billDate
                ) {

                    return true;
                }


                return new Date(value) >=
                    new Date(billDate);

            },
            'Due date cannot be before bill date'
        );


        /*
        |--------------------------------------------------------------------------
        | Vendor Bill Validation
        |--------------------------------------------------------------------------
        */

        $('#billForm').validate({

            errorElement: 'span',

            errorClass: 'text-red-500 text-sm mt-1 block',


            errorPlacement: function(error, element) {

                if (
                    element.is('select')
                ) {

                    element
                        .parent()
                        .append(error);

                } else {

                    error.insertAfter(
                        element
                    );
                }
            },


            highlight: function(element) {

                $(element)
                    .addClass(
                        'border-red-500'
                    );
            },


            unhighlight: function(element) {

                $(element)
                    .removeClass(
                        'border-red-500'
                    );
            },


            rules: {

                vendor_id: {
                    required: true,
                    digits: true,
                    min: 1
                },

                bill_date: {
                    required: true,
                    date: true
                },

                due_date: {
                    required: true,
                    date: true,
                    validDueDate: true
                },

                currency: {
                    required: true
                },

                vendor_invoice_number: {
                    maxlength: 100
                },

                po_id: {
                    digits: true
                },

                grn_id: {
                    digits: true
                },

                notes: {
                    maxlength: 1000
                }

            },


            messages: {

                vendor_id: {
                    required: 'Please select a vendor.',

                    digits: 'Invalid vendor.',

                    min: 'Please select a valid vendor.'
                },

                bill_date: {
                    required: 'Bill date is required.',

                    date: 'Please enter a valid bill date.'
                },

                due_date: {
                    required: 'Due date is required.',

                    date: 'Please enter a valid due date.',

                    validDueDate: 'Due date cannot be before bill date.'
                },

                currency: {
                    required: 'Please select currency.'
                },

                vendor_invoice_number: {
                    maxlength: 'Vendor invoice number cannot exceed 100 characters.'
                },

                po_id: {
                    digits: 'Invalid purchase order.'
                },

                grn_id: {
                    digits: 'Invalid GRN.'
                },

                notes: {
                    maxlength: 'Notes cannot exceed 1000 characters.'
                }

            },


            /*
            |--------------------------------------------------------------------------
            | Final Client-side Submit Validation
            |--------------------------------------------------------------------------
            */

            submitHandler: function(form) {

                let validItems = 0;

                let invalidItem = false;


                $('.item-row').each(
                    function() {

                        const row =
                            $(this);


                        const description =
                            row
                            .find(
                                'input[name$="[description]"]'
                            )
                            .val()
                            .trim();


                        const quantity =
                            parseFloat(
                                row
                                .find(
                                    'input[name$="[quantity]"]'
                                )
                                .val()
                            ) || 0;


                        const unitPrice =
                            parseFloat(
                                row
                                .find(
                                    'input[name$="[unit_price]"]'
                                )
                                .val()
                            ) || 0;


                        const discount =
                            parseFloat(
                                row
                                .find(
                                    'input[name$="[discount_percent]"]'
                                )
                                .val()
                            ) || 0;


                        /*
                        | Completely empty row
                        */

                        if (
                            !description &&
                            quantity === 0 &&
                            unitPrice === 0
                        ) {

                            return;
                        }


                        /*
                        | Description
                        */

                        if (!description) {

                            invalidItem = true;

                            return;
                        }


                        /*
                        | Quantity
                        */

                        if (
                            quantity <= 0
                        ) {

                            invalidItem = true;

                            return;
                        }


                        /*
                        | Unit price
                        */

                        if (
                            unitPrice <= 0
                        ) {

                            invalidItem = true;

                            return;
                        }


                        /*
                        | Discount
                        */

                        if (
                            discount < 0 ||
                            discount > 100
                        ) {

                            invalidItem = true;

                            return;
                        }


                        validItems++;

                    }
                );


                if (invalidItem) {

                    alert(
                        'Please check the line items. ' +
                        'Description, quantity and unit price are required.'
                    );

                    return false;
                }


                if (
                    validItems === 0
                ) {

                    alert(
                        'At least one valid item is required.'
                    );

                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | Grand Total
                |--------------------------------------------------------------------------
                */

                const total =
                    parseFloat(
                        document.getElementById(
                            'total_hidden'
                        ).value
                    ) || 0;


                if (
                    total <= 0
                ) {

                    alert(
                        'Bill total must be greater than zero. Please add valid items.'
                    );

                    return false;
                }


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
        | Revalidate Due Date
        |--------------------------------------------------------------------------
        */

        $('input[name="bill_date"]').on(
            'change',
            function() {

                $('input[name="due_date"]').valid();

            }
        );


        $('input[name="due_date"]').on(
            'change',
            function() {

                $(this).valid();

            }
        );

    });
</script>


<?php require_once '../../../includes/footer.php'; ?>