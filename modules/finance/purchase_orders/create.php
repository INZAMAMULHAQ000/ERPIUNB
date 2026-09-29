<?php

$pageTitle = 'Create Purchase Order';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';

if (!hasPermission('purchase_orders.create')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$vendors = $db->query("
    SELECT id, vendor_code, vendor_name
    FROM vendors
    WHERE status = 'active'
    ORDER BY vendor_name
")->fetchAll();

/*
|--------------------------------------------------------------------------
| Generate Next PO Number
|--------------------------------------------------------------------------
*/

$prefix = 'PO' . date('Ymd');

$stmt = $db->prepare("
    SELECT COUNT(*) AS cnt
    FROM purchase_orders
    WHERE po_number LIKE ?
");
$stmt->execute([$prefix . '%']);

$count = (int)$stmt->fetch()['cnt'];

$po_number = $prefix . '-' . str_pad(
    $count + 1,
    3,
    '0',
    STR_PAD_LEFT
);

/*
|--------------------------------------------------------------------------
| POST Handler
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid or expired form token.';
    }

    $vendor_id = (int)($_POST['vendor_id'] ?? 0);

    $order_date = trim(
        $_POST['order_date'] ?? ''
    );

    $expected_delivery_date = trim(
        $_POST['expected_delivery_date'] ?? ''
    );

    $currency = trim(
        $_POST['currency'] ?? 'AED'
    );

    $payment_terms = trim(
        $_POST['payment_terms'] ?? ''
    );

    $notes = trim(
        $_POST['notes'] ?? ''
    );

    $terms_conditions = trim(
        $_POST['terms_conditions'] ?? ''
    );

    $items = $_POST['items'] ?? [];

    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if ($vendor_id <= 0) {
        $errors[] = 'Please select a vendor.';
    }

    if (empty($order_date)) {
        $errors[] = 'Order date is required.';
    }

    /*
    |--------------------------------------------------------------------------
    | Date Validation
    |--------------------------------------------------------------------------
    */

    if (
        !empty($expected_delivery_date) &&
        !empty($order_date) &&
        $expected_delivery_date < $order_date
    ) {
        $errors[] =
            'Expected delivery date cannot be before order date.';
    }

    /*
    |--------------------------------------------------------------------------
    | Currency Validation
    |--------------------------------------------------------------------------
    */

    $allowedCurrencies = [
        'AED',
        'USD',
        'EUR',
        'GBP'
    ];

    if (!in_array($currency, $allowedCurrencies, true)) {
        $errors[] = 'Please select a valid currency.';
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Terms Validation
    |--------------------------------------------------------------------------
    */

    $allowedPaymentTerms = [
        '',
        'Due on Receipt',
        'Net 15',
        'Net 30',
        'Net 45',
        'Net 60'
    ];

    if (!in_array($payment_terms, $allowedPaymentTerms, true)) {
        $errors[] = 'Please select valid payment terms.';
    }

    /*
    |--------------------------------------------------------------------------
    | Item Validation
    |--------------------------------------------------------------------------
    */

    if (empty($items) || !is_array($items)) {

        $errors[] = 'At least one item is required.';
    } else {

        $validItems = 0;

        foreach ($items as $item) {

            $desc = trim(
                $item['description'] ?? ''
            );

            $qty = (float)str_replace(
                ',',
                '',
                $item['quantity'] ?? 0
            );

            $price = (float)str_replace(
                ',',
                '',
                $item['unit_price'] ?? 0
            );

            $discount = (float)str_replace(
                ',',
                '',
                $item['discount_percent'] ?? 0
            );

            $tax = (float)str_replace(
                ',',
                '',
                $item['tax_percent'] ?? 0
            );

            /*
            |--------------------------------------------------------------------------
            | Ignore Completely Empty Rows
            |--------------------------------------------------------------------------
            */

            if (
                $desc === '' &&
                $qty <= 0 &&
                $price <= 0 &&
                $discount <= 0 &&
                $tax <= 0
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Item
            |--------------------------------------------------------------------------
            */

            if ($desc === '') {
                $errors[] = 'Item description is required.';
                continue;
            }

            if (strlen($desc) > 500) {
                $errors[] =
                    'Item description cannot exceed 500 characters.';
                continue;
            }

            if ($qty <= 0) {
                $errors[] =
                    'Item quantity must be greater than zero.';
                continue;
            }

            if ($price <= 0) {
                $errors[] =
                    'Item unit price must be greater than zero.';
                continue;
            }

            if ($discount < 0 || $discount > 100) {
                $errors[] =
                    'Discount must be between 0 and 100%.';
                continue;
            }

            if ($tax < 0 || $tax > 100) {
                $errors[] =
                    'Tax must be between 0 and 100%.';
                continue;
            }

            $validItems++;
        }

        if ($validItems === 0) {
            $errors[] =
                'At least one valid item with description, quantity and unit price is required.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save Errors
    |--------------------------------------------------------------------------
    */

    if (!empty($errors)) {

        saveFormState($errors);

        to(
            'modules/finance/purchase_orders/create.php'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate Totals
    |--------------------------------------------------------------------------
    */

    $subtotal = 0.00;
    $total_tax = 0.00;
    $total_discount = 0.00;

    $processedItems = [];

    $idx = 0;

    try {

        $db->beginTransaction();

        foreach ($items as $item) {

            $desc = trim(
                $item['description'] ?? ''
            );

            $qty = (float)str_replace(
                ',',
                '',
                $item['quantity'] ?? 0
            );

            $unit = trim(
                $item['unit'] ?? ''
            );

            $price = (float)str_replace(
                ',',
                '',
                $item['unit_price'] ?? 0
            );

            $disc_pct = (float)str_replace(
                ',',
                '',
                $item['discount_percent'] ?? 0
            );

            $tax_pct = (float)str_replace(
                ',',
                '',
                $item['tax_percent'] ?? 0
            );

            /*
            |--------------------------------------------------------------------------
            | Skip Empty Row
            |--------------------------------------------------------------------------
            */

            if (
                $desc === '' &&
                $qty <= 0 &&
                $price <= 0
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Skip Invalid Row
            |--------------------------------------------------------------------------
            */

            if (
                $desc === '' ||
                $qty <= 0 ||
                $price <= 0
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Calculation
            |--------------------------------------------------------------------------
            |
            | Gross Amount
            | = Quantity × Unit Price
            |
            | Discount Amount
            | = Gross Amount × Discount %
            |
            | After Discount
            | = Gross Amount - Discount Amount
            |
            | Tax Amount
            | = After Discount × Tax %
            |
            | Final Item Total
            | = After Discount + Tax Amount
            |
            */

            $gross_amount =
                $qty * $price;

            $disc_amt =
                $gross_amount * ($disc_pct / 100);

            $after_discount =
                $gross_amount - $disc_amt;

            $tax_amt =
                $after_discount * ($tax_pct / 100);

            $final_line =
                $after_discount + $tax_amt;

            /*
            |--------------------------------------------------------------------------
            | Grand Totals
            |--------------------------------------------------------------------------
            */

            $subtotal += $gross_amount;

            $total_discount += $disc_amt;

            $total_tax += $tax_amt;

            /*
            |--------------------------------------------------------------------------
            | Store Processed Item
            |--------------------------------------------------------------------------
            */

            $processedItems[] = [
                'description' => $desc,
                'quantity' => $qty,
                'unit' => $unit,
                'unit_price' => $price,
                'discount_percent' => $disc_pct,
                'tax_percent' => $tax_pct,
                'tax_amount' => $tax_amt,
                'line_total' => $final_line,
                'sort_order' => $idx++,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Overall Total
        |--------------------------------------------------------------------------
        */

        $total_amount =
            $subtotal
            - $total_discount
            + $total_tax;

        /*
        |--------------------------------------------------------------------------
        | Insert Purchase Order
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            INSERT INTO purchase_orders (
                po_number,
                vendor_id,
                order_date,
                expected_delivery_date,
                currency,
                payment_terms,
                status,
                subtotal,
                discount_amount,
                discount_type,
                tax_amount,
                total_amount,
                notes,
                terms_conditions,
                created_by,
                created_at,
                updated_at
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, 'draft',
                ?, ?, ?, ?, ?, ?, ?, ?,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            $po_number,
            $vendor_id,
            $order_date,
            $expected_delivery_date ?: null,
            $currency,
            $payment_terms,
            $subtotal,
            $total_discount,
            $total_discount > 0
                ? 'percentage'
                : null,
            $total_tax,
            $total_amount,
            $notes,
            $terms_conditions,
            $_SESSION['user_id'],
        ]);

        $po_id = (int)$db->lastInsertId();

        /*
        |--------------------------------------------------------------------------
        | Insert Purchase Order Items
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            INSERT INTO purchase_order_items (
                po_id,
                description,
                quantity,
                unit,
                unit_price,
                discount_percent,
                tax_percent,
                tax_amount,
                line_total,
                sort_order
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($processedItems as $pi) {

            $stmt->execute([
                $po_id,
                $pi['description'],
                $pi['quantity'],
                $pi['unit'],
                $pi['unit_price'],
                $pi['discount_percent'],
                $pi['tax_percent'],
                $pi['tax_amount'],
                $pi['line_total'],
                $pi['sort_order']
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Insert Status History
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            INSERT INTO po_status_history (
                po_id,
                from_status,
                to_status,
                changed_by,
                change_note
            )
            VALUES (
                ?,
                NULL,
                'draft',
                ?,
                ?
            )
        ");

        $stmt->execute([
            $po_id,
            $_SESSION['user_id'],
            'Purchase order created'
        ]);

        /*
        |--------------------------------------------------------------------------
        | Commit
        |--------------------------------------------------------------------------
        */

        $db->commit();

        /*
        |--------------------------------------------------------------------------
        | Audit Log
        |--------------------------------------------------------------------------
        */

        logAudit(
            $_SESSION['user_id'],
            'Created PO: ' . $po_number,
            'purchase_orders'
        );

        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */

        flash(
            'success',
            'Purchase order ' .
                $po_number .
                ' created successfully.',
            'success'
        );

        redirect(
            'view.php?id=' . $po_id
        );
    } catch (Exception $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        flash(
            'error',
            'Error creating purchase order: ' .
                $e->getMessage()
        );

        to(
            'modules/finance/purchase_orders/create.php'
        );
    }
}

?>

<div class="mb-6 flex justify-between items-center">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            Create Purchase Order
        </h1>

        <p class="text-gray-600">
            Create a new purchase order for a vendor
        </p>
    </div>

    <a
        href="index.php"
        class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">

        <i class="fas fa-arrow-left mr-2"></i>

        Back to POs

    </a>

</div>

<?php if (!empty($errors)): ?>

    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">

        <?php foreach ($errors as $e): ?>

            <p><?= sanitize($e) ?></p>

        <?php endforeach; ?>

    </div>

<?php endif; ?>

<form
    method="POST"
    id="poForm"
    novalidate>

    <input
        type="hidden"
        name="csrf_token"
        value="<?= csrfToken() ?>">

    <!-- PO Details -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <!-- PO Number -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    PO Number
                </label>

                <input
                    type="text"
                    value="<?= sanitize($po_number) ?>"
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
                                ($v['vendor_name'] ?? '') .
                                    ' (' .
                                    ($v['vendor_code'] ?? '') .
                                    ')'
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- Order Date -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Order Date

                    <span class="text-red-500">*</span>

                </label>

                <input
                    type="date"
                    name="order_date"
                    value="<?= old(
                                'order_date',
                                date('Y-m-d')
                            ) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

            <!-- Expected Delivery -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Expected Delivery
                </label>

                <input
                    type="date"
                    name="expected_delivery_date"
                    value="<?= old(
                                'expected_delivery_date'
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
                        <?= old(
                            'currency',
                            'AED'
                        ) === 'AED'
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

            <!-- Payment Terms -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Payment Terms
                </label>

                <select
                    name="payment_terms"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Select --
                    </option>

                    <option
                        value="Due on Receipt"
                        <?= old('payment_terms') === 'Due on Receipt'
                            ? 'selected'
                            : '' ?>>

                        Due on Receipt

                    </option>

                    <option
                        value="Net 15"
                        <?= old('payment_terms') === 'Net 15'
                            ? 'selected'
                            : '' ?>>

                        Net 15

                    </option>

                    <option
                        value="Net 30"
                        <?= old('payment_terms') === 'Net 30'
                            ? 'selected'
                            : '' ?>>

                        Net 30

                    </option>

                    <option
                        value="Net 45"
                        <?= old('payment_terms') === 'Net 45'
                            ? 'selected'
                            : '' ?>>

                        Net 45

                    </option>

                    <option
                        value="Net 60"
                        <?= old('payment_terms') === 'Net 60'
                            ? 'selected'
                            : '' ?>>

                        Net 60

                    </option>

                </select>

            </div>

        </div>

    </div>

    <!-- Items Table -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <div class="flex justify-between items-center mb-4">

            <h3 class="text-lg font-semibold text-gray-800">
                Items
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
                            Quantity
                        </th>

                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            Unit
                        </th>

                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                            Unit Price
                        </th>

                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                            Discount %
                        </th>

                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                            Tax %
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

                        <!-- Description -->

                        <td class="px-3 py-2">

                            <input
                                type="text"
                                name="items[0][description]"
                                class="w-full px-2 py-1.5 border rounded text-sm item-description"
                                placeholder="Item description"
                                maxlength="500">

                        </td>

                        <!-- Quantity -->

                        <td class="px-3 py-2">

                            <input
                                type="number"
                                name="items[0][quantity]"
                                value="1"
                                step="0.01"
                                min="0.01"
                                class="w-24 px-2 py-1.5 border rounded text-sm text-right qty">

                        </td>

                        <!-- Unit -->

                        <td class="px-3 py-2">

                            <input
                                type="text"
                                name="items[0][unit]"
                                class="w-20 px-2 py-1.5 border rounded text-sm item-unit"
                                placeholder="pcs"
                                maxlength="20">

                        </td>

                        <!-- Unit Price -->

                        <td class="px-3 py-2">

                            <input
                                type="number"
                                name="items[0][unit_price]"
                                step="0.0001"
                                min="0.0001"
                                class="w-28 px-2 py-1.5 border rounded text-sm text-right price">

                        </td>

                        <!-- Discount -->

                        <td class="px-3 py-2">

                            <input
                                type="number"
                                name="items[0][discount_percent]"
                                step="0.01"
                                min="0"
                                max="100"
                                value="0"
                                class="w-20 px-2 py-1.5 border rounded text-sm text-right disc">

                        </td>

                        <!-- Tax -->

                        <td class="px-3 py-2">

                            <input
                                type="number"
                                name="items[0][tax_percent]"
                                step="0.01"
                                min="0"
                                max="100"
                                value="0"
                                class="w-20 px-2 py-1.5 border rounded text-sm text-right tax">

                        </td>

                        <!-- Line Total -->

                        <td class="px-3 py-2">

                            <input
                                type="text"
                                class="w-28 px-2 py-1.5 border rounded text-sm text-right font-mono line-total bg-gray-50"
                                value="0.00"
                                readonly>

                        </td>

                        <!-- Action -->

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

                    <!-- Subtotal -->

                    <tr>

                        <td
                            colspan="6"
                            class="px-3 py-2 text-right text-sm font-medium text-gray-700">

                            Subtotal:

                        </td>

                        <td class="px-3 py-2">

                            <input
                                type="text"
                                id="subtotal"
                                class="w-28 px-2 py-1.5 border-0 text-right font-mono font-bold bg-transparent"
                                value="0.00"
                                readonly>

                        </td>

                        <td></td>

                    </tr>

                    <!-- Total -->

                    <tr>

                        <td
                            colspan="6"
                            class="px-3 py-2 text-right text-sm font-medium text-gray-700">

                            Total:

                        </td>

                        <td class="px-3 py-2">

                            <input
                                type="text"
                                id="grandTotal"
                                class="w-28 px-2 py-1.5 border-0 text-right font-mono font-bold text-lg bg-transparent"
                                value="0.00"
                                readonly>

                        </td>

                        <td></td>

                    </tr>

                </tfoot>

            </table>

        </div>

    </div>

    <!-- Notes & Terms -->

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">

        <!-- Notes -->

        <div class="bg-white rounded-lg shadow p-6">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Notes
            </label>

            <textarea
                name="notes"
                rows="3"
                maxlength="1000"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('notes') ?></textarea>

        </div>

        <!-- Terms -->

        <div class="bg-white rounded-lg shadow p-6">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Terms & Conditions
            </label>

            <textarea
                name="terms_conditions"
                rows="3"
                maxlength="2000"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('terms_conditions') ?></textarea>

        </div>

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

            Create PO

        </button>

    </div>

</form>

<!-- jQuery -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- jQuery Validation -->

<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>
    /*
|--------------------------------------------------------------------------
| Item Index
|--------------------------------------------------------------------------
*/

    let itemIndex = 1;


    /*
    |--------------------------------------------------------------------------
    | Add Item
    |--------------------------------------------------------------------------
    */

    function addItem() {

        const tbody =
            document.getElementById('itemsBody');

        const row =
            document.createElement('tr');

        row.className = 'item-row';

        const i = itemIndex;

        row.innerHTML = `
        <td class="px-3 py-2 text-sm text-gray-500 item-num">
            ${i + 1}
        </td>

        <td class="px-3 py-2">
            <input
                type="text"
                name="items[${i}][description]"
                class="w-full px-2 py-1.5 border rounded text-sm item-description"
                placeholder="Item description"
                maxlength="500">
        </td>

        <td class="px-3 py-2">
            <input
                type="number"
                name="items[${i}][quantity]"
                value="1"
                step="0.01"
                min="0.01"
                class="w-24 px-2 py-1.5 border rounded text-sm text-right qty">
        </td>

        <td class="px-3 py-2">
            <input
                type="text"
                name="items[${i}][unit]"
                class="w-20 px-2 py-1.5 border rounded text-sm item-unit"
                placeholder="pcs"
                maxlength="20">
        </td>

        <td class="px-3 py-2">
            <input
                type="number"
                name="items[${i}][unit_price]"
                step="0.0001"
                min="0.0001"
                class="w-28 px-2 py-1.5 border rounded text-sm text-right price">
        </td>

        <td class="px-3 py-2">
            <input
                type="number"
                name="items[${i}][discount_percent]"
                step="0.01"
                min="0"
                max="100"
                value="0"
                class="w-20 px-2 py-1.5 border rounded text-sm text-right disc">
        </td>

        <td class="px-3 py-2">
            <input
                type="number"
                name="items[${i}][tax_percent]"
                step="0.01"
                min="0"
                max="100"
                value="0"
                class="w-20 px-2 py-1.5 border rounded text-sm text-right tax">
        </td>

        <td class="px-3 py-2">
            <input
                type="text"
                class="w-28 px-2 py-1.5 border rounded text-sm text-right font-mono line-total bg-gray-50"
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
    `;

        tbody.appendChild(row);

        itemIndex++;

        renumberItems();

        /*
        |--------------------------------------------------------------------------
        | Bind Validation
        |--------------------------------------------------------------------------
        */

        bindDynamicValidation(row);

        /*
        |--------------------------------------------------------------------------
        | Bind Calculation
        |--------------------------------------------------------------------------
        */

        bindCalculation(row);

        /*
        |--------------------------------------------------------------------------
        | Initial Row Calculation
        |--------------------------------------------------------------------------
        */

        calcRow(row);

        calcAll();
    }


    /*
    |--------------------------------------------------------------------------
    | Calculate Single Row
    |--------------------------------------------------------------------------
    */

    function calcRow(row) {

        if (!row) {
            return {
                gross: 0,
                discount: 0,
                tax: 0,
                total: 0
            };
        }

        const qtyInput =
            row.querySelector('.qty');

        const priceInput =
            row.querySelector('.price');

        const discInput =
            row.querySelector('.disc');

        const taxInput =
            row.querySelector('.tax');

        const totalInput =
            row.querySelector('.line-total');

        if (
            !qtyInput ||
            !priceInput ||
            !discInput ||
            !taxInput
        ) {
            return {
                gross: 0,
                discount: 0,
                tax: 0,
                total: 0
            };
        }

        const qty =
            parseFloat(qtyInput.value) || 0;

        const price =
            parseFloat(priceInput.value) || 0;

        const disc =
            parseFloat(discInput.value) || 0;

        const tax =
            parseFloat(taxInput.value) || 0;


        /*
        |--------------------------------------------------------------------------
        | Gross Amount
        |--------------------------------------------------------------------------
        */

        const grossAmount =
            qty * price;


        /*
        |--------------------------------------------------------------------------
        | Discount Amount
        |--------------------------------------------------------------------------
        */

        const discountAmount =
            grossAmount * (disc / 100);


        /*
        |--------------------------------------------------------------------------
        | Amount After Discount
        |--------------------------------------------------------------------------
        */

        const afterDiscount =
            grossAmount - discountAmount;


        /*
        |--------------------------------------------------------------------------
        | Tax Amount
        |--------------------------------------------------------------------------
        */

        const taxAmount =
            afterDiscount * (tax / 100);


        /*
        |--------------------------------------------------------------------------
        | Final Line Total
        |--------------------------------------------------------------------------
        */

        const finalTotal =
            afterDiscount + taxAmount;


        /*
        |--------------------------------------------------------------------------
        | Display Row Total
        |--------------------------------------------------------------------------
        */

        if (totalInput) {

            totalInput.value =
                finalTotal.toFixed(2);

        }


        return {
            gross: grossAmount,
            discount: discountAmount,
            tax: taxAmount,
            total: finalTotal
        };
    }


    /*
    |--------------------------------------------------------------------------
    | Calculate All Rows
    |--------------------------------------------------------------------------
    */

    function calcAll() {

        let subtotal = 0;

        let totalDiscount = 0;

        let totalTax = 0;


        document
            .querySelectorAll('#itemsBody .item-row')
            .forEach(function(row) {

                const result =
                    calcRow(row);

                subtotal += result.gross;

                totalDiscount += result.discount;

                totalTax += result.tax;

            });


        /*
        |--------------------------------------------------------------------------
        | Grand Total
        |--------------------------------------------------------------------------
        */

        const grandTotal =
            subtotal -
            totalDiscount +
            totalTax;


        /*
        |--------------------------------------------------------------------------
        | Display Subtotal
        |--------------------------------------------------------------------------
        */

        const subtotalInput =
            document.getElementById('subtotal');

        if (subtotalInput) {

            subtotalInput.value =
                subtotal.toFixed(2);

        }


        /*
        |--------------------------------------------------------------------------
        | Display Grand Total
        |--------------------------------------------------------------------------
        */

        const grandTotalInput =
            document.getElementById('grandTotal');

        if (grandTotalInput) {

            grandTotalInput.value =
                grandTotal.toFixed(2);

        }
    }


    /*
    |--------------------------------------------------------------------------
    | Bind Calculation Events
    |--------------------------------------------------------------------------
    */

    function bindCalculation(row) {

        if (!row) {
            return;
        }

        const $row =
            $(row);

        $row
            .find('.qty, .price, .disc, .tax')
            .off(
                'input.poCalculation change.poCalculation'
            )
            .on(
                'input.poCalculation change.poCalculation',
                function() {

                    calcRow(row);

                    calcAll();

                }
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Item
    |--------------------------------------------------------------------------
    */

    function removeItem(btn) {

        const row =
            btn.closest('.item-row');

        const rows =
            document.querySelectorAll(
                '#itemsBody .item-row'
            );

        /*
        |--------------------------------------------------------------------------
        | Keep At Least One Row
        |--------------------------------------------------------------------------
        */

        if (rows.length <= 1) {

            return;

        }

        if (row) {

            row.remove();

        }

        renumberItems();

        calcAll();
    }


    /*
    |--------------------------------------------------------------------------
    | Renumber Items
    |--------------------------------------------------------------------------
    */

    function renumberItems() {

        document
            .querySelectorAll('#itemsBody .item-row')
            .forEach(function(row, idx) {

                const number =
                    row.querySelector('.item-num');

                if (number) {

                    number.textContent =
                        idx + 1;

                }

            });
    }


    /*
    |--------------------------------------------------------------------------
    | Dynamic Item Validation
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | This function is outside document.ready().
    | Therefore addItem() can access it.
    |--------------------------------------------------------------------------
    */

    function bindDynamicValidation(row) {

        if (!row) {
            return;
        }

        const $row =
            $(row);

        const description =
            $row.find('.item-description');

        const quantity =
            $row.find('.qty');

        const unit =
            $row.find('.item-unit');

        const price =
            $row.find('.price');

        const discount =
            $row.find('.disc');

        const tax =
            $row.find('.tax');


        /*
        |--------------------------------------------------------------------------
        | Description
        |--------------------------------------------------------------------------
        */

        if (description.length) {

            description.rules('add', {

                maxlength: 500,

                messages: {

                    maxlength: 'Item description cannot exceed 500 characters.'

                }

            });

        }


        /*
        |--------------------------------------------------------------------------
        | Quantity
        |--------------------------------------------------------------------------
        */

        if (quantity.length) {

            quantity.rules('add', {

                number: true,

                min: 0.01,

                messages: {

                    number: 'Please enter a valid quantity.',

                    min: 'Quantity must be greater than zero.'

                }

            });

        }


        /*
        |--------------------------------------------------------------------------
        | Unit
        |--------------------------------------------------------------------------
        */

        if (unit.length) {

            unit.rules('add', {

                maxlength: 20,

                messages: {

                    maxlength: 'Unit cannot exceed 20 characters.'

                }

            });

        }


        /*
        |--------------------------------------------------------------------------
        | Unit Price
        |--------------------------------------------------------------------------
        */

        if (price.length) {

            price.rules('add', {

                number: true,

                min: 0.0001,

                messages: {

                    number: 'Please enter a valid unit price.',

                    min: 'Unit price must be greater than zero.'

                }

            });

        }


        /*
        |--------------------------------------------------------------------------
        | Discount
        |--------------------------------------------------------------------------
        */

        if (discount.length) {

            discount.rules('add', {

                number: true,

                min: 0,

                max: 100,

                validPercentage: true,

                messages: {

                    number: 'Please enter a valid discount.',

                    min: 'Discount cannot be negative.',

                    max: 'Discount cannot exceed 100%.',

                    validPercentage: 'Discount must be between 0 and 100%.'

                }

            });

        }


        /*
        |--------------------------------------------------------------------------
        | Tax
        |--------------------------------------------------------------------------
        */

        if (tax.length) {

            tax.rules('add', {

                number: true,

                min: 0,

                max: 100,

                validPercentage: true,

                messages: {

                    number: 'Please enter a valid tax percentage.',

                    min: 'Tax cannot be negative.',

                    max: 'Tax cannot exceed 100%.',

                    validPercentage: 'Tax must be between 0 and 100%.'

                }

            });

        }
    }


    /*
    |--------------------------------------------------------------------------
    | Document Ready
    |--------------------------------------------------------------------------
    */

    $(document).ready(function() {


        /*
        |--------------------------------------------------------------------------
        | Currency Validation
        |--------------------------------------------------------------------------
        */

        $.validator.addMethod(
            'validCurrency',
            function(value, element) {

                const currencies = [
                    'AED',
                    'USD',
                    'EUR',
                    'GBP'
                ];

                return this.optional(element) ||
                    currencies.includes(value);

            },
            'Please select a valid currency.'
        );


        /*
        |--------------------------------------------------------------------------
        | Payment Terms Validation
        |--------------------------------------------------------------------------
        */

        $.validator.addMethod(
            'validPaymentTerms',
            function(value, element) {

                if (value === '') {
                    return true;
                }

                const terms = [
                    'Due on Receipt',
                    'Net 15',
                    'Net 30',
                    'Net 45',
                    'Net 60'
                ];

                return terms.includes(value);

            },
            'Please select valid payment terms.'
        );


        /*
        |--------------------------------------------------------------------------
        | Expected Delivery Date Validation
        |--------------------------------------------------------------------------
        */

        $.validator.addMethod(
            'validDeliveryDate',
            function(value, element) {

                const orderDate =
                    $('input[name="order_date"]').val();

                if (!value || !orderDate) {
                    return true;
                }

                return value >= orderDate;

            },
            'Expected delivery date cannot be before order date.'
        );


        /*
        |--------------------------------------------------------------------------
        | Decimal Validation
        |--------------------------------------------------------------------------
        */

        $.validator.addMethod(
            'validDecimal',
            function(value, element) {

                if (value === '') {
                    return true;
                }

                return /^\d+(\.\d{1,4})?$/.test(value);

            },
            'Please enter a valid number.'
        );


        /*
        |--------------------------------------------------------------------------
        | Percentage Validation
        |--------------------------------------------------------------------------
        */

        $.validator.addMethod(
            'validPercentage',
            function(value, element) {

                if (value === '') {
                    return true;
                }

                const number =
                    parseFloat(value);

                return !isNaN(number) &&
                    number >= 0 &&
                    number <= 100;

            },
            'Percentage must be between 0 and 100.'
        );


        /*
        |--------------------------------------------------------------------------
        | Description Sanitization
        |--------------------------------------------------------------------------
        */

        $(document).on(
            'input',
            'input[name*="[description]"]',
            function() {

                let value =
                    $(this).val();

                value =
                    value.replace(
                        /[^A-Za-z0-9 .,&()_/@#+'-]/g,
                        ''
                    );

                value =
                    value.replace(
                        /\s{2,}/g,
                        ' '
                    );

                $(this).val(
                    value.substring(0, 500)
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Unit Sanitization
        |--------------------------------------------------------------------------
        */

        $(document).on(
            'input',
            'input[name*="[unit]"]',
            function() {

                let value =
                    $(this).val();

                value =
                    value.replace(
                        /[^A-Za-z0-9 ._-]/g,
                        ''
                    );

                value =
                    value.replace(
                        /\s{2,}/g,
                        ' '
                    );

                $(this).val(
                    value.substring(0, 20)
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Notes
        |--------------------------------------------------------------------------
        */

        $('textarea[name="notes"]').on(
            'input',
            function() {

                $(this).val(
                    $(this)
                    .val()
                    .substring(0, 1000)
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Terms
        |--------------------------------------------------------------------------
        */

        $('textarea[name="terms_conditions"]').on(
            'input',
            function() {

                $(this).val(
                    $(this)
                    .val()
                    .substring(0, 2000)
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Form Validation
        |--------------------------------------------------------------------------
        */

        $('#poForm').validate({

            errorElement: 'span',

            errorClass: 'text-red-500 text-sm mt-1 block',

            errorPlacement: function(error, element) {

                if (element.is('select')) {

                    element
                        .parent()
                        .append(error);

                } else {

                    error.insertAfter(element);

                }

            },

            highlight: function(element) {

                $(element).addClass(
                    'border-red-500'
                );

            },

            unhighlight: function(element) {

                $(element).removeClass(
                    'border-red-500'
                );

            },

            rules: {

                vendor_id: {

                    required: true,

                    digits: true,

                    min: 1

                },

                order_date: {

                    required: true,

                    date: true

                },

                expected_delivery_date: {

                    date: true,

                    validDeliveryDate: true

                },

                currency: {

                    required: true,

                    validCurrency: true

                },

                payment_terms: {

                    validPaymentTerms: true

                },

                notes: {

                    maxlength: 1000

                },

                terms_conditions: {

                    maxlength: 2000

                }

            },

            messages: {

                vendor_id: {

                    required: 'Please select a vendor.',

                    digits: 'Invalid vendor.',

                    min: 'Please select a valid vendor.'

                },

                order_date: {

                    required: 'Please select the order date.',

                    date: 'Please enter a valid order date.'

                },

                expected_delivery_date: {

                    date: 'Please enter a valid delivery date.',

                    validDeliveryDate: 'Expected delivery date cannot be before order date.'

                },

                currency: {

                    required: 'Please select a currency.',

                    validCurrency: 'Please select a valid currency.'

                },

                payment_terms: {

                    validPaymentTerms: 'Please select valid payment terms.'

                },

                notes: {

                    maxlength: 'Notes cannot exceed 1000 characters.'

                },

                terms_conditions: {

                    maxlength: 'Terms & Conditions cannot exceed 2000 characters.'

                }

            },


            /*
            |--------------------------------------------------------------------------
            | Submit Handler
            |--------------------------------------------------------------------------
            */

            submitHandler: function(form) {

                let validItems = 0;

                $('.item-row').each(
                    function() {

                        const description =
                            $(this)
                            .find('.item-description')
                            .val()
                            .trim();

                        const quantity =
                            parseFloat(
                                $(this)
                                .find('.qty')
                                .val()
                            ) || 0;

                        const price =
                            parseFloat(
                                $(this)
                                .find('.price')
                                .val()
                            ) || 0;

                        if (
                            description !== '' &&
                            quantity > 0 &&
                            price > 0
                        ) {

                            validItems++;

                        }

                    }
                );


                /*
                |--------------------------------------------------------------------------
                | No Valid Item
                |--------------------------------------------------------------------------
                */

                if (validItems === 0) {

                    if (
                        $('#itemError').length === 0
                    ) {

                        $('#itemsTable').after(`

                            <div
                                id="itemError"
                                class="text-red-500 text-sm mt-2">

                                At least one valid item with
                                description, quantity and
                                unit price is required.

                            </div>

                        `);

                    }

                    return false;

                }


                /*
                |--------------------------------------------------------------------------
                | Remove Item Error
                |--------------------------------------------------------------------------
                */

                $('#itemError').remove();


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
        | Bind Existing Row Validation
        |--------------------------------------------------------------------------
        */

        $('.item-row').each(
            function() {

                bindDynamicValidation(this);

                bindCalculation(this);

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Initial Calculation
        |--------------------------------------------------------------------------
        */

        calcAll();


        /*
        |--------------------------------------------------------------------------
        | Revalidate Delivery Date
        |--------------------------------------------------------------------------
        */

        $('input[name="order_date"]').on(
            'change',
            function() {

                $('input[name="expected_delivery_date"]')
                    .valid();

            }
        );

    });
</script>

<?php require_once '../../../includes/footer.php'; ?>