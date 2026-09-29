<?php

$pageTitle = 'Edit Purchase Order';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../../../helpers/security.php';

if (!hasPermission('purchase_orders.edit')) {
    die('Access Denied');
}

$db = getDB();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Invalid PO ID.');
    redirect('index.php');
}

/*
|--------------------------------------------------------------------------
| Get Purchase Order
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT *
    FROM purchase_orders
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$po = $stmt->fetch();

if (!$po) {
    flash('error', 'Purchase order not found.');
    redirect('index.php');
}

if ($po['status'] !== 'draft') {
    flash('error', 'Only draft POs can be edited.');
    redirect('view.php?id=' . $id);
}

/*
|--------------------------------------------------------------------------
| Restore Form Errors
|--------------------------------------------------------------------------
*/

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

/*
|--------------------------------------------------------------------------
| Vendors
|--------------------------------------------------------------------------
*/

$vendors = $db->query("
    SELECT
        id,
        vendor_code,
        vendor_name
    FROM vendors
    WHERE status = 'active'
    ORDER BY vendor_name ASC
")->fetchAll();

/*
|--------------------------------------------------------------------------
| Existing PO Items
|--------------------------------------------------------------------------
|
| IMPORTANT:
| purchase_order_items table does NOT contain sort_order.
|
*/

$itemsStmt = $db->prepare("
    SELECT *
    FROM purchase_order_items
    WHERE po_id = ?
    ORDER BY id ASC
");

$itemsStmt->execute([$id]);

$items = $itemsStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid or expired form token.';
    }

    /*
    |--------------------------------------------------------------------------
    | Get Form Data
    |--------------------------------------------------------------------------
    */

    $vendor_id = (int)($_POST['vendor_id'] ?? 0);

    $order_date = trim(
        (string)($_POST['order_date'] ?? '')
    );

    $expected_delivery_date = trim(
        (string)($_POST['expected_delivery_date'] ?? '')
    );

    $currency = trim(
        (string)($_POST['currency'] ?? 'AED')
    );

    $payment_terms = trim(
        (string)($_POST['payment_terms'] ?? '')
    );

    $notes = trim(
        (string)($_POST['notes'] ?? '')
    );

    $itemsData = $_POST['items'] ?? [];

    /*
    |--------------------------------------------------------------------------
    | Header Validation
    |--------------------------------------------------------------------------
    */

    if ($vendor_id <= 0) {

        $errors[] = 'Please select a vendor.';
    } else {

        $vendorCheck = $db->prepare("
            SELECT id
            FROM vendors
            WHERE id = ?
              AND status = 'active'
            LIMIT 1
        ");

        $vendorCheck->execute([
            $vendor_id
        ]);

        if (!$vendorCheck->fetch()) {
            $errors[] = 'Selected vendor is invalid or inactive.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Order Date Validation
    |--------------------------------------------------------------------------
    */

    if ($order_date === '') {

        $errors[] = 'Order date is required.';
    } else {

        $dateObj = DateTime::createFromFormat(
            'Y-m-d',
            $order_date
        );

        if (
            !$dateObj ||
            $dateObj->format('Y-m-d') !== $order_date
        ) {
            $errors[] = 'Please enter a valid order date.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Expected Delivery Date Validation
    |--------------------------------------------------------------------------
    */

    if ($expected_delivery_date !== '') {

        $deliveryObj = DateTime::createFromFormat(
            'Y-m-d',
            $expected_delivery_date
        );

        if (
            !$deliveryObj ||
            $deliveryObj->format('Y-m-d') !== $expected_delivery_date
        ) {

            $errors[] = 'Please enter a valid expected delivery date.';
        } elseif (
            $order_date !== '' &&
            $expected_delivery_date < $order_date
        ) {

            $errors[] =
                'Expected delivery date cannot be earlier than order date.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Currency Validation
    |--------------------------------------------------------------------------
    */

    $allowedCurrencies = [
        'AED',
        'USD',
        'EUR'
    ];

    if (!in_array($currency, $allowedCurrencies, true)) {

        $errors[] =
            'Please select a valid currency.';
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

        $errors[] =
            'Please select valid payment terms.';
    }

    if (strlen($payment_terms) > 50) {

        $errors[] =
            'Payment terms cannot exceed 50 characters.';
    }

    /*
    |--------------------------------------------------------------------------
    | Notes Validation
    |--------------------------------------------------------------------------
    */

    if (strlen($notes) > 1000) {

        $errors[] =
            'Notes cannot exceed 1000 characters.';
    }

    /*
    |--------------------------------------------------------------------------
    | Items Validation
    |--------------------------------------------------------------------------
    */

    $subtotal = 0.00;
    $total_tax = 0.00;
    $total_discount = 0.00;

    $processedItems = [];

    $validItemCount = 0;

    if (!is_array($itemsData)) {
        $itemsData = [];
    }

    foreach ($itemsData as $itemIndex => $item) {

        if (!is_array($item)) {
            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Item Values
        |--------------------------------------------------------------------------
        */

        $description = trim(
            (string)($item['description'] ?? '')
        );

        $unit = trim(
            (string)($item['unit'] ?? '')
        );

        $qtyRaw = trim(
            (string)($item['quantity'] ?? '')
        );

        $priceRaw = trim(
            (string)($item['unit_price'] ?? '')
        );

        $discRaw = trim(
            (string)($item['discount_percent'] ?? '')
        );

        $taxRaw = trim(
            (string)($item['tax_percent'] ?? '')
        );

        /*
        |--------------------------------------------------------------------------
        | Remove Commas
        |--------------------------------------------------------------------------
        */

        $qtyClean = str_replace(
            ',',
            '',
            $qtyRaw
        );

        $priceClean = str_replace(
            ',',
            '',
            $priceRaw
        );

        $discClean = str_replace(
            ',',
            '',
            $discRaw
        );

        $taxClean = str_replace(
            ',',
            '',
            $taxRaw
        );

        /*
        |--------------------------------------------------------------------------
        | Convert Numbers
        |--------------------------------------------------------------------------
        */

        $qty = is_numeric($qtyClean)
            ? (float)$qtyClean
            : 0;

        $price = is_numeric($priceClean)
            ? (float)$priceClean
            : 0;

        $discountPercent = is_numeric($discClean)
            ? (float)$discClean
            : 0;

        $taxPercent = is_numeric($taxClean)
            ? (float)$taxClean
            : 0;

        /*
        |--------------------------------------------------------------------------
        | Completely Blank Row
        |--------------------------------------------------------------------------
        */

        if (
            $description === '' &&
            $qtyRaw === '' &&
            $priceRaw === '' &&
            $discRaw === '' &&
            $taxRaw === '' &&
            $unit === ''
        ) {
            continue;
        }

        $displayItemNumber =
            (int)$itemIndex + 1;

        /*
        |--------------------------------------------------------------------------
        | Description
        |--------------------------------------------------------------------------
        */

        if ($description === '') {

            $errors[] =
                'Item ' .
                $displayItemNumber .
                ': Description is required.';

            continue;
        }

        if (strlen($description) > 255) {

            $errors[] =
                'Item ' .
                $displayItemNumber .
                ': Description cannot exceed 255 characters.';

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Quantity
        |--------------------------------------------------------------------------
        */

        if (
            $qtyRaw === '' ||
            !is_numeric($qtyClean) ||
            $qty <= 0
        ) {

            $errors[] =
                'Item ' .
                $displayItemNumber .
                ': Quantity must be greater than zero.';

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Unit Price
        |--------------------------------------------------------------------------
        */

        if (
            $priceRaw === '' ||
            !is_numeric($priceClean) ||
            $price <= 0
        ) {

            $errors[] =
                'Item ' .
                $displayItemNumber .
                ': Unit price must be greater than zero.';

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Discount
        |--------------------------------------------------------------------------
        */

        if (
            !is_numeric($discClean) ||
            $discountPercent < 0 ||
            $discountPercent > 100
        ) {

            $errors[] =
                'Item ' .
                $displayItemNumber .
                ': Discount must be between 0 and 100.';

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Tax
        |--------------------------------------------------------------------------
        */

        if (
            !is_numeric($taxClean) ||
            $taxPercent < 0 ||
            $taxPercent > 100
        ) {

            $errors[] =
                'Item ' .
                $displayItemNumber .
                ': Tax must be between 0 and 100.';

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Unit
        |--------------------------------------------------------------------------
        */

        if (strlen($unit) > 50) {

            $errors[] =
                'Item ' .
                $displayItemNumber .
                ': Unit cannot exceed 50 characters.';

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Line Calculation
        |--------------------------------------------------------------------------
        */

        $lineSubtotal =
            $qty * $price;

        $discountAmount =
            $lineSubtotal *
            ($discountPercent / 100);

        $afterDiscount =
            $lineSubtotal -
            $discountAmount;

        $taxAmount =
            $afterDiscount *
            ($taxPercent / 100);

        $finalLineTotal =
            $afterDiscount +
            $taxAmount;

        /*
        |--------------------------------------------------------------------------
        | Overall Totals
        |--------------------------------------------------------------------------
        */

        $subtotal += $lineSubtotal;

        $total_discount += $discountAmount;

        $total_tax += $taxAmount;

        /*
        |--------------------------------------------------------------------------
        | Processed Item
        |--------------------------------------------------------------------------
        */

        $processedItems[] = [
            'description' => $description,
            'quantity' => $qty,
            'unit' => $unit,
            'unit_price' => $price,
            'discount_percent' => $discountPercent,
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'line_total' => $finalLineTotal
        ];

        $validItemCount++;
    }

    /*
    |--------------------------------------------------------------------------
    | At Least One Item
    |--------------------------------------------------------------------------
    */

    if ($validItemCount === 0) {

        $errors[] =
            'Please add at least one valid purchase order item.';
    }

    /*
    |--------------------------------------------------------------------------
    | Stop Validation Errors
    |--------------------------------------------------------------------------
    */

    if (!empty($errors)) {

        saveFormState($errors);

        to(
            'modules/finance/purchase_orders/edit.php?id=' . $id
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Grand Total
    |--------------------------------------------------------------------------
    */

    $total_amount =
        $subtotal -
        $total_discount +
        $total_tax;

    /*
    |--------------------------------------------------------------------------
    | Database Transaction
    |--------------------------------------------------------------------------
    */

    try {

        $db->beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | Update PO
        |--------------------------------------------------------------------------
        */

        $updatePO = $db->prepare("
            UPDATE purchase_orders
            SET
                vendor_id = ?,
                order_date = ?,
                expected_delivery_date = ?,
                currency = ?,
                payment_terms = ?,
                subtotal = ?,
                discount_amount = ?,
                discount_type = ?,
                tax_amount = ?,
                total_amount = ?,
                notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $updatePO->execute([
            $vendor_id,
            $order_date,
            $expected_delivery_date !== ''
                ? $expected_delivery_date
                : null,
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
            $id
        ]);

        /*
        |--------------------------------------------------------------------------
        | Delete Existing Items
        |--------------------------------------------------------------------------
        */

        $deleteItems = $db->prepare("
            DELETE FROM purchase_order_items
            WHERE po_id = ?
        ");

        $deleteItems->execute([
            $id
        ]);

        /*
        |--------------------------------------------------------------------------
        | Insert Updated Items
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | No sort_order column.
        |
        */

        $insertItem = $db->prepare("
            INSERT INTO purchase_order_items (
                po_id,
                description,
                quantity,
                unit,
                unit_price,
                discount_percent,
                tax_percent,
                tax_amount,
                line_total
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($processedItems as $processedItem) {

            $insertItem->execute([
                $id,
                $processedItem['description'],
                $processedItem['quantity'],
                $processedItem['unit'],
                $processedItem['unit_price'],
                $processedItem['discount_percent'],
                $processedItem['tax_percent'],
                $processedItem['tax_amount'],
                $processedItem['line_total']
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Commit
        |--------------------------------------------------------------------------
        */

        $db->commit();

        /*
        |--------------------------------------------------------------------------
        | Audit
        |--------------------------------------------------------------------------
        */

        logAudit(
            $_SESSION['user_id'],
            'Updated PO: ' . $po['po_number'],
            'purchase_orders'
        );

        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */

        flash(
            'success',
            'Purchase order updated successfully.',
            'success'
        );

        redirect(
            'view.php?id=' . $id
        );

        exit;
    } catch (Exception $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        flash(
            'error',
            'Error: ' . $e->getMessage()
        );

        to(
            'modules/finance/purchase_orders/edit.php?id=' . $id
        );

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Form Values
|--------------------------------------------------------------------------
|
| Keep old() outside HTML attributes.
| This avoids complicated PHP expressions inside HTML.
|
*/

$oldVendorId = old(
    'vendor_id',
    $po['vendor_id'] ?? ''
);

$oldOrderDate = old(
    'order_date',
    $po['order_date'] ?? ''
);

$oldExpectedDeliveryDate = old(
    'expected_delivery_date',
    $po['expected_delivery_date'] ?? ''
);

$oldCurrency = old(
    'currency',
    $po['currency'] ?? 'AED'
);

$oldPaymentTerms = old(
    'payment_terms',
    $po['payment_terms'] ?? ''
);

$oldNotes = old(
    'notes',
    $po['notes'] ?? ''
);

/*
|--------------------------------------------------------------------------
| Convert Scalar Values
|--------------------------------------------------------------------------
*/

$oldVendorId = (string)$oldVendorId;
$oldOrderDate = (string)$oldOrderDate;
$oldExpectedDeliveryDate = (string)$oldExpectedDeliveryDate;
$oldCurrency = (string)$oldCurrency;
$oldPaymentTerms = (string)$oldPaymentTerms;
$oldNotes = (string)$oldNotes;


$poSubtotal = isset($po['subtotal']) ? (float)$po['subtotal'] : 0.00;
$poDiscountTotal = isset($po['discount_total']) ? (float)$po['discount_total'] : 0.00;
$poTaxTotal = isset($po['tax_total']) ? (float)$po['tax_total'] : 0.00;
$poGrandTotal = isset($po['grand_total']) ? (float)$po['grand_total'] : 0.00;

?>

<!-- =========================================================
     PAGE HEADER
========================================================== -->

<div class="mb-6 flex justify-between items-center">

    <div>

        <h1 class="text-2xl font-bold text-gray-800">

            Edit PO:

            <?= sanitize((string)$po['po_number']) ?>

        </h1>

        <p class="text-gray-600">
            Modify draft purchase order
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
     SERVER ERRORS
========================================================== -->

<?php if (!empty($errors)): ?>

    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">

        <?php foreach ($errors as $error): ?>

            <p>
                <?= sanitize((string)$error) ?>
            </p>

        <?php endforeach; ?>

    </div>

<?php endif; ?>


<!-- =========================================================
     FORM
========================================================== -->

<form
    method="POST"
    id="poForm"
    novalidate>

    <input
        type="hidden"
        name="csrf_token"
        value="<?= sanitize((string)csrfToken()) ?>">


    <!-- =====================================================
         PO INFORMATION
    ====================================================== -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">


            <!-- PO Number -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    PO Number
                </label>

                <input
                    type="text"
                    value="<?= sanitize((string)$po['po_number']) ?>"
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

                    <?php foreach ($vendors as $vendor): ?>

                        <?php
                        $vendorId = (string)$vendor['id'];

                        $vendorSelected =
                            ($oldVendorId === $vendorId);
                        ?>

                        <option
                            value="<?= (int)$vendor['id'] ?>"
                            <?= $vendorSelected ? 'selected' : '' ?>>

                            <?= sanitize((string)$vendor['vendor_name']) ?>

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
                    value="<?= sanitize($oldOrderDate) ?>"
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
                    value="<?= sanitize($oldExpectedDeliveryDate) ?>"
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
                        <?= $oldCurrency === 'AED' ? 'selected' : '' ?>>
                        AED
                    </option>

                    <option
                        value="USD"
                        <?= $oldCurrency === 'USD' ? 'selected' : '' ?>>
                        USD
                    </option>

                    <option
                        value="EUR"
                        <?= $oldCurrency === 'EUR' ? 'selected' : '' ?>>
                        EUR
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

                    <option
                        value=""
                        <?= $oldPaymentTerms === '' ? 'selected' : '' ?>>
                        -- Select --
                    </option>

                    <option
                        value="Due on Receipt"
                        <?= $oldPaymentTerms === 'Due on Receipt' ? 'selected' : '' ?>>
                        Due on Receipt
                    </option>

                    <option
                        value="Net 15"
                        <?= $oldPaymentTerms === 'Net 15' ? 'selected' : '' ?>>
                        Net 15
                    </option>

                    <option
                        value="Net 30"
                        <?= $oldPaymentTerms === 'Net 30' ? 'selected' : '' ?>>
                        Net 30
                    </option>

                    <option
                        value="Net 45"
                        <?= $oldPaymentTerms === 'Net 45' ? 'selected' : '' ?>>
                        Net 45
                    </option>

                    <option
                        value="Net 60"
                        <?= $oldPaymentTerms === 'Net 60' ? 'selected' : '' ?>>
                        Net 60
                    </option>

                </select>

            </div>

        </div>

    </div>


    <!-- =========================================================
         ITEMS
    ========================================================== -->

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


        <div id="itemError"></div>


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


                    <?php if (!empty($items)): ?>

                        <?php foreach ($items as $index => $item): ?>

                            <?php

                            $itemDescription =
                                (string)($item['description'] ?? '');

                            $itemQuantity =
                                (float)($item['quantity'] ?? 0);

                            $itemUnit =
                                (string)($item['unit'] ?? '');

                            $itemUnitPrice =
                                (float)($item['unit_price'] ?? 0);

                            $itemDiscount =
                                (float)($item['discount_percent'] ?? 0);

                            $itemTax =
                                (float)($item['tax_percent'] ?? 0);

                            $itemLineTotal =
                                (float)($item['line_total'] ?? 0);

                            ?>

                            <tr class="item-row">

                                <td class="px-3 py-2 text-sm text-gray-500 item-num">
                                    <?= $index + 1 ?>
                                </td>


                                <!-- Description -->

                                <td class="px-3 py-2">

                                    <input
                                        type="text"
                                        name="items[<?= $index ?>][description]"
                                        value="<?= sanitize($itemDescription) ?>"
                                        maxlength="255"
                                        class="w-full px-2 py-1.5 border rounded text-sm item-description">

                                </td>


                                <!-- Quantity -->

                                <td class="px-3 py-2">

                                    <input
                                        type="number"
                                        name="items[<?= $index ?>][quantity]"
                                        value="<?= $itemQuantity ?>"
                                        step="0.01"
                                        min="0.01"
                                        class="w-24 px-2 py-1.5 border rounded text-sm text-right qty">

                                </td>


                                <!-- Unit -->

                                <td class="px-3 py-2">

                                    <input
                                        type="text"
                                        name="items[<?= $index ?>][unit]"
                                        value="<?= sanitize($itemUnit) ?>"
                                        maxlength="50"
                                        class="w-20 px-2 py-1.5 border rounded text-sm unit">

                                </td>


                                <!-- Unit Price -->

                                <td class="px-3 py-2">

                                    <input
                                        type="number"
                                        name="items[<?= $index ?>][unit_price]"
                                        value="<?= $itemUnitPrice ?>"
                                        step="0.0001"
                                        min="0.01"
                                        class="w-28 px-2 py-1.5 border rounded text-sm text-right price">

                                </td>


                                <!-- Discount -->

                                <td class="px-3 py-2">

                                    <input
                                        type="number"
                                        name="items[<?= $index ?>][discount_percent]"
                                        value="<?= $itemDiscount ?>"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        class="w-20 px-2 py-1.5 border rounded text-sm text-right disc">

                                </td>


                                <!-- Tax -->

                                <td class="px-3 py-2">

                                    <input
                                        type="number"
                                        name="items[<?= $index ?>][tax_percent]"
                                        value="<?= $itemTax ?>"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        class="w-20 px-2 py-1.5 border rounded text-sm text-right tax">

                                </td>


                                <!-- Line Total -->

                                <td class="px-3 py-2">

                                    <input
                                        type="text"
                                        class="w-28 px-2 py-1.5 border rounded text-sm text-right font-mono line-total bg-gray-50"
                                        value="<?= number_format($itemLineTotal, 2, '.', '') ?>"
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

                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr class="item-row">

                            <td class="px-3 py-2 text-sm text-gray-500 item-num">
                                1
                            </td>


                            <td class="px-3 py-2">

                                <input
                                    type="text"
                                    name="items[0][description]"
                                    maxlength="255"
                                    class="w-full px-2 py-1.5 border rounded text-sm item-description"
                                    placeholder="Item description">

                            </td>


                            <td class="px-3 py-2">

                                <input
                                    type="number"
                                    name="items[0][quantity]"
                                    value="1"
                                    step="0.01"
                                    min="0.01"
                                    class="w-24 px-2 py-1.5 border rounded text-sm text-right qty">

                            </td>


                            <td class="px-3 py-2">

                                <input
                                    type="text"
                                    name="items[0][unit]"
                                    maxlength="50"
                                    class="w-20 px-2 py-1.5 border rounded text-sm unit"
                                    placeholder="pcs">

                            </td>


                            <td class="px-3 py-2">

                                <input
                                    type="number"
                                    name="items[0][unit_price]"
                                    step="0.0001"
                                    min="0.01"
                                    class="w-28 px-2 py-1.5 border rounded text-sm text-right price">

                            </td>


                            <td class="px-3 py-2">

                                <input
                                    type="number"
                                    name="items[0][discount_percent]"
                                    value="0"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    class="w-20 px-2 py-1.5 border rounded text-sm text-right disc">

                            </td>


                            <td class="px-3 py-2">

                                <input
                                    type="number"
                                    name="items[0][tax_percent]"
                                    value="0"
                                    step="0.01"
                                    min="0"
                                    max="100"
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

                        </tr>


                    <?php endif; ?>

                </tbody>


                <!-- =================================================
                     TOTALS
                ================================================== -->

                <tfoot class="bg-gray-50">


                    <!-- Subtotal -->

                    <tr>

                        <td
                            colspan="7"
                            class="px-3 py-2 text-right text-sm font-medium text-gray-700">

                            Subtotal:

                        </td>

                        <td class="px-3 py-2">

                            <input
                                type="text"
                                id="subtotal"
                                class="w-28 px-2 py-1.5 border-0 text-right font-mono font-bold bg-transparent"
                                value="<?= number_format($poSubtotal, 2, '.', '') ?>"
                                readonly>

                        </td>

                        <td></td>

                    </tr>


                    <!-- Discount -->

                    <tr>

                        <td
                            colspan="7"
                            class="px-3 py-2 text-right text-sm font-medium text-gray-700">

                            Discount:

                        </td>

                        <td class="px-3 py-2">

                            <input
                                type="text"
                                id="totalDiscount"
                                class="w-28 px-2 py-1.5 border-0 text-right font-mono bg-transparent"
                                value="<?= number_format($poDiscountTotal, 2, '.', '') ?>"
                                readonly>

                        </td>

                        <td></td>

                    </tr>


                    <!-- Tax -->

                    <tr>

                        <td
                            colspan="7"
                            class="px-3 py-2 text-right text-sm font-medium text-gray-700">

                            Tax:

                        </td>

                        <td class="px-3 py-2">

                            <input
                                type="text"
                                id="totalTax"
                                class="w-28 px-2 py-1.5 border-0 text-right font-mono bg-transparent"
                                value="<?= number_format($poTaxTotal, 2, '.', '') ?>"
                                readonly>

                        </td>

                        <td></td>

                    </tr>


                    <!-- Grand Total -->

                    <tr>

                        <td
                            colspan="7"
                            class="px-3 py-2 text-right text-sm font-bold text-gray-700">

                            Grand Total:

                        </td>

                        <td class="px-3 py-2">

                            <input
                                type="text"
                                id="grandTotal"
                                class="w-28 px-2 py-1.5 border-0 text-right font-mono font-bold text-lg bg-transparent"
                                value="<?= number_format($poGrandTotal, 2, '.', '') ?>"
                                readonly>

                        </td>

                        <td></td>

                    </tr>

                </tfoot>

            </table>

        </div>

    </div>


    <!-- =========================================================
         NOTES
    ========================================================== -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <label class="block text-sm font-medium text-gray-700 mb-1">
            Notes
        </label>

        <textarea
            name="notes"
            rows="3"
            maxlength="1000"
            class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= sanitize($oldNotes) ?></textarea>

    </div>


    <!-- =========================================================
         BUTTONS
    ========================================================== -->

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

            Update PO

        </button>

    </div>

</form>


<!-- =========================================================
     JAVASCRIPT
========================================================== -->

<script>
    let itemIndex = <?= (int)count($items) ?>;


    /*
    |--------------------------------------------------------------------------
    | Show Item Error
    |--------------------------------------------------------------------------
    */

    function showItemError(message) {

        $('#itemError').remove();

        $('#itemsTable').before(
            '<div id="itemError" class="text-red-500 text-sm mb-3">' +
            message +
            '</div>'
        );
    }


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

        const index =
            itemIndex;

        row.innerHTML = `
        <td class="px-3 py-2 text-sm text-gray-500 item-num">
            1
        </td>

        <td class="px-3 py-2">
            <input
                type="text"
                name="items[${index}][description]"
                maxlength="255"
                class="w-full px-2 py-1.5 border rounded text-sm item-description"
                placeholder="Item description">
        </td>

        <td class="px-3 py-2">
            <input
                type="number"
                name="items[${index}][quantity]"
                value="1"
                step="0.01"
                min="0.01"
                class="w-24 px-2 py-1.5 border rounded text-sm text-right qty">
        </td>

        <td class="px-3 py-2">
            <input
                type="text"
                name="items[${index}][unit]"
                maxlength="50"
                class="w-20 px-2 py-1.5 border rounded text-sm unit"
                placeholder="pcs">
        </td>

        <td class="px-3 py-2">
            <input
                type="number"
                name="items[${index}][unit_price]"
                step="0.0001"
                min="0.01"
                class="w-28 px-2 py-1.5 border rounded text-sm text-right price">
        </td>

        <td class="px-3 py-2">
            <input
                type="number"
                name="items[${index}][discount_percent]"
                value="0"
                step="0.01"
                min="0"
                max="100"
                class="w-20 px-2 py-1.5 border rounded text-sm text-right disc">
        </td>

        <td class="px-3 py-2">
            <input
                type="number"
                name="items[${index}][tax_percent]"
                value="0"
                step="0.01"
                min="0"
                max="100"
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

        calcAll();

        $('#itemError').remove();
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Item
    |--------------------------------------------------------------------------
    */

    function removeItem(button) {

        const rows =
            document.querySelectorAll('.item-row');

        const row =
            button.closest('.item-row');

        if (!row) {
            return;
        }

        /*
        | If only one row exists,
        | clear the row instead of removing it.
        */

        if (rows.length <= 1) {

            row.querySelector('.item-description').value = '';

            row.querySelector('.qty').value = '1';

            row.querySelector('.unit').value = '';

            row.querySelector('.price').value = '';

            row.querySelector('.disc').value = '0';

            row.querySelector('.tax').value = '0';

            row.querySelector('.line-total').value = '0.00';

            row.querySelectorAll('input').forEach(function(input) {
                input.classList.remove('border-red-500');
            });

            calcAll();

            return;
        }

        row.remove();

        renumberItems();

        calcAll();

        $('#itemError').remove();
    }


    /*
    |--------------------------------------------------------------------------
    | Renumber Items
    |--------------------------------------------------------------------------
    */

    function renumberItems() {

        document
            .querySelectorAll('.item-row')
            .forEach(function(row, index) {

                const number =
                    row.querySelector('.item-num');

                if (number) {
                    number.textContent =
                        index + 1;
                }

            });
    }


    /*
    |--------------------------------------------------------------------------
    | Calculate Row
    |--------------------------------------------------------------------------
    */

    function calcRow(row) {

        if (!row) {
            return;
        }

        const qty =
            parseFloat(
                row.querySelector('.qty')?.value
            ) || 0;

        const price =
            parseFloat(
                row.querySelector('.price')?.value
            ) || 0;

        const discount =
            parseFloat(
                row.querySelector('.disc')?.value
            ) || 0;

        const tax =
            parseFloat(
                row.querySelector('.tax')?.value
            ) || 0;

        const line =
            qty * price;

        const discountAmount =
            line * (discount / 100);

        const afterDiscount =
            line - discountAmount;

        const taxAmount =
            afterDiscount * (tax / 100);

        const finalTotal =
            afterDiscount + taxAmount;

        const totalInput =
            row.querySelector('.line-total');

        if (totalInput) {

            totalInput.value =
                finalTotal.toFixed(2);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Calculate All
    |--------------------------------------------------------------------------
    */

    function calcAll() {

        let subtotal = 0;

        let totalDiscount = 0;

        let totalTax = 0;

        document
            .querySelectorAll('.item-row')
            .forEach(function(row) {

                const qty =
                    parseFloat(
                        row.querySelector('.qty')?.value
                    ) || 0;

                const price =
                    parseFloat(
                        row.querySelector('.price')?.value
                    ) || 0;

                const discount =
                    parseFloat(
                        row.querySelector('.disc')?.value
                    ) || 0;

                const tax =
                    parseFloat(
                        row.querySelector('.tax')?.value
                    ) || 0;

                const line =
                    qty * price;

                const discountAmount =
                    line * (discount / 100);

                const afterDiscount =
                    line - discountAmount;

                const taxAmount =
                    afterDiscount * (tax / 100);

                const finalTotal =
                    afterDiscount + taxAmount;

                subtotal += line;

                totalDiscount +=
                    discountAmount;

                totalTax +=
                    taxAmount;

                const totalInput =
                    row.querySelector('.line-total');

                if (totalInput) {

                    totalInput.value =
                        finalTotal.toFixed(2);
                }

            });

        const grandTotal =
            subtotal -
            totalDiscount +
            totalTax;


        const subtotalInput =
            document.getElementById('subtotal');

        const discountInput =
            document.getElementById('totalDiscount');

        const taxInput =
            document.getElementById('totalTax');

        const grandTotalInput =
            document.getElementById('grandTotal');


        if (subtotalInput) {

            subtotalInput.value =
                subtotal.toFixed(2);
        }

        if (discountInput) {

            discountInput.value =
                totalDiscount.toFixed(2);
        }

        if (taxInput) {

            taxInput.value =
                totalTax.toFixed(2);
        }

        if (grandTotalInput) {

            grandTotalInput.value =
                grandTotal.toFixed(2);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Input Sanitization - Description
    |--------------------------------------------------------------------------
    */

    $(document).on(
        'input',
        'input[name$="[description]"]',
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
                value.substring(0, 255)
            );

            $('#itemError').remove();
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Input Sanitization - Unit
    |--------------------------------------------------------------------------
    */

    $(document).on(
        'input',
        'input[name$="[unit]"]',
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
                value.substring(0, 50)
            );

            $('#itemError').remove();
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

            let value =
                $(this).val();

            $(this).val(
                value.substring(0, 1000)
            );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Quantity
    |--------------------------------------------------------------------------
    */

    $(document).on(
        'input',
        '.qty',
        function() {

            let value =
                $(this).val();

            value =
                value.replace(
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

            if (value.includes('.')) {

                const decimal =
                    value.split('.');

                value =
                    decimal[0] +
                    '.' +
                    decimal[1].substring(0, 2);
            }

            $(this).val(value);

            $(this)
                .removeClass('border-red-500');

            calcAll();

            $('#itemError').remove();
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Price
    |--------------------------------------------------------------------------
    */

    $(document).on(
        'input',
        '.price',
        function() {

            let value =
                $(this).val();

            value =
                value.replace(
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

            if (value.includes('.')) {

                const decimal =
                    value.split('.');

                value =
                    decimal[0] +
                    '.' +
                    decimal[1].substring(0, 4);
            }

            $(this).val(value);

            $(this)
                .removeClass('border-red-500');

            calcAll();

            $('#itemError').remove();
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Discount / Tax
    |--------------------------------------------------------------------------
    */

    $(document).on(
        'input',
        '.disc, .tax',
        function() {

            let value =
                $(this).val();

            value =
                value.replace(
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

            if (value.includes('.')) {

                const decimal =
                    value.split('.');

                value =
                    decimal[0] +
                    '.' +
                    decimal[1].substring(0, 2);
            }

            let number =
                parseFloat(value);

            if (!isNaN(number) && number > 100) {
                value = '100';
            }

            $(this).val(value);

            $(this)
                .removeClass('border-red-500');

            calcAll();

            $('#itemError').remove();
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Recalculate
    |--------------------------------------------------------------------------
    */

    $(document).on(
        'input',
        '.qty, .price, .disc, .tax',
        function() {

            const row =
                $(this).closest('.item-row')[0];

            calcRow(row);

            calcAll();
        }
    );


    /*
    |--------------------------------------------------------------------------
    | jQuery Validation
    |--------------------------------------------------------------------------
    */

    $(document).ready(function() {

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

                $(element)
                    .addClass('border-red-500');
            },

            unhighlight: function(element) {

                $(element)
                    .removeClass('border-red-500');
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
                    date: true
                },

                currency: {
                    required: true
                },

                payment_terms: {
                    maxlength: 50
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

                order_date: {
                    required: 'Please select the order date.',

                    date: 'Please enter a valid order date.'
                },

                expected_delivery_date: {
                    date: 'Please enter a valid delivery date.'
                },

                currency: {
                    required: 'Please select a currency.'
                },

                payment_terms: {
                    maxlength: 'Payment terms cannot exceed 50 characters.'
                },

                notes: {
                    maxlength: 'Notes cannot exceed 1000 characters.'
                }
            },


            /*
            |--------------------------------------------------------------------------
            | Submit Handler
            |--------------------------------------------------------------------------
            */

            submitHandler: function(form) {

                $('#itemError').remove();

                $('.item-row input')
                    .removeClass('border-red-500');


                let validItems = 0;

                let hasInvalidItem = false;


                $('.item-row').each(
                    function() {

                        const row =
                            $(this);


                        const description =
                            $.trim(
                                row.find(
                                    '.item-description'
                                ).val() || ''
                            );


                        const qty =
                            parseFloat(
                                row.find('.qty').val()
                            ) || 0;


                        const price =
                            parseFloat(
                                row.find('.price').val()
                            ) || 0;


                        const discount =
                            parseFloat(
                                row.find('.disc').val()
                            ) || 0;


                        const tax =
                            parseFloat(
                                row.find('.tax').val()
                            ) || 0;


                        /*
                        |--------------------------------------------------------------------------
                        | Completely Blank Row
                        |--------------------------------------------------------------------------
                        */

                        if (
                            description === '' &&
                            qty === 0 &&
                            price === 0
                        ) {

                            return;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Description
                        |--------------------------------------------------------------------------
                        */

                        if (description === '') {

                            hasInvalidItem = true;

                            row.find(
                                    '.item-description'
                                )
                                .addClass(
                                    'border-red-500'
                                );

                            return;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Quantity
                        |--------------------------------------------------------------------------
                        */

                        if (qty <= 0) {

                            hasInvalidItem = true;

                            row.find('.qty')
                                .addClass(
                                    'border-red-500'
                                );

                            return;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Price
                        |--------------------------------------------------------------------------
                        */

                        if (price <= 0) {

                            hasInvalidItem = true;

                            row.find('.price')
                                .addClass(
                                    'border-red-500'
                                );

                            return;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Discount
                        |--------------------------------------------------------------------------
                        */

                        if (
                            discount < 0 ||
                            discount > 100
                        ) {

                            hasInvalidItem = true;

                            row.find('.disc')
                                .addClass(
                                    'border-red-500'
                                );

                            return;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Tax
                        |--------------------------------------------------------------------------
                        */

                        if (
                            tax < 0 ||
                            tax > 100
                        ) {

                            hasInvalidItem = true;

                            row.find('.tax')
                                .addClass(
                                    'border-red-500'
                                );

                            return;
                        }


                        validItems++;

                    }
                );


                /*
                |--------------------------------------------------------------------------
                | No Valid Items
                |--------------------------------------------------------------------------
                */

                if (validItems === 0) {

                    showItemError(
                        'Please add at least one valid purchase order item.'
                    );

                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | Invalid Items
                |--------------------------------------------------------------------------
                */

                if (hasInvalidItem) {

                    showItemError(
                        'Please correct the highlighted item fields.'
                    );

                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | Recalculate
                |--------------------------------------------------------------------------
                */

                calcAll();


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
        | Remove Item Error When User Changes Form
        |--------------------------------------------------------------------------
        */

        $(document).on(
            'input change',
            '#poForm input, #poForm select, #poForm textarea',
            function() {

                $('#itemError').remove();
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Initial Calculation
        |--------------------------------------------------------------------------
        */

        $('.item-row').each(
            function() {

                calcRow(this);
            }
        );

        calcAll();

    });
</script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>
<script>
    /*
|--------------------------------------------------------------------------
| jQuery Validation
|--------------------------------------------------------------------------
*/

    $(document).ready(function() {

        $('#poForm').validate({

            errorElement: 'span',

            errorClass: 'text-red-500 text-sm mt-1 block',

            errorPlacement: function(error, element) {

                if (element.is('select')) {

                    error.insertAfter(element);

                } else {

                    error.insertAfter(element);
                }
            },

            highlight: function(element) {

                $(element).addClass('border-red-500');
            },

            unhighlight: function(element) {

                $(element).removeClass('border-red-500');
            },

            rules: {

                vendor_id: {
                    required: true,
                    digits: true,
                    min: 1
                },

                order_date: {
                    required: true
                },

                expected_delivery_date: {
                    date: true
                },

                currency: {
                    required: true
                },

                payment_terms: {
                    maxlength: 50
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

                order_date: {
                    required: 'Please select the order date.'
                },

                expected_delivery_date: {
                    date: 'Please enter a valid delivery date.'
                },

                currency: {
                    required: 'Please select a currency.'
                },

                payment_terms: {
                    maxlength: 'Payment terms cannot exceed 50 characters.'
                },

                notes: {
                    maxlength: 'Notes cannot exceed 1000 characters.'
                }
            },

            submitHandler: function(form) {

                $('#itemError').remove();

                $('.item-row input')
                    .removeClass('border-red-500');


                let validItems = 0;

                let hasInvalidItem = false;


                /*
                |--------------------------------------------------------------------------
                | Date Validation
                |--------------------------------------------------------------------------
                */

                const orderDate =
                    $('input[name="order_date"]').val();

                const deliveryDate =
                    $('input[name="expected_delivery_date"]').val();


                if (
                    orderDate &&
                    deliveryDate &&
                    deliveryDate < orderDate
                ) {

                    const error =
                        $('<span>')
                        .addClass('text-red-500 text-sm mt-1 block')
                        .text(
                            'Expected delivery date cannot be earlier than order date.'
                        );

                    $('input[name="expected_delivery_date"]')
                        .addClass('border-red-500')
                        .after(error);

                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | Item Validation
                |--------------------------------------------------------------------------
                */

                $('.item-row').each(function(index) {

                    const row = $(this);

                    const itemNumber = index + 1;


                    /*
                    | Description
                    |--------------------------------------------------------------------------
                    */

                    const description =
                        $.trim(
                            row.find('.item-description').val() || ''
                        );


                    /*
                    | Quantity
                    |--------------------------------------------------------------------------
                    */

                    const qtyValue =
                        $.trim(
                            row.find('.qty').val() || ''
                        );

                    const qty =
                        parseFloat(qtyValue);


                    /*
                    | Unit
                    |--------------------------------------------------------------------------
                    */

                    const unit =
                        $.trim(
                            row.find('.unit').val() || ''
                        );


                    /*
                    | Unit Price
                    |--------------------------------------------------------------------------
                    */

                    const priceValue =
                        $.trim(
                            row.find('.price').val() || ''
                        );

                    const price =
                        parseFloat(priceValue);


                    /*
                    | Discount
                    |--------------------------------------------------------------------------
                    */

                    const discountValue =
                        $.trim(
                            row.find('.disc').val() || ''
                        );

                    const discount =
                        parseFloat(discountValue);


                    /*
                    | Tax
                    |--------------------------------------------------------------------------
                    */

                    const taxValue =
                        $.trim(
                            row.find('.tax').val() || ''
                        );

                    const tax =
                        parseFloat(taxValue);


                    /*
                    |--------------------------------------------------------------------------
                    | Completely Empty Row
                    |--------------------------------------------------------------------------
                    */

                    if (
                        description === '' &&
                        qtyValue === '' &&
                        unit === '' &&
                        priceValue === '' &&
                        discountValue === '' &&
                        taxValue === ''
                    ) {

                        return;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Description Required
                    |--------------------------------------------------------------------------
                    */

                    if (description === '') {

                        hasInvalidItem = true;

                        row.find('.item-description')
                            .addClass('border-red-500');

                        return;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Description Max Length
                    |--------------------------------------------------------------------------
                    */

                    if (description.length > 255) {

                        hasInvalidItem = true;

                        row.find('.item-description')
                            .addClass('border-red-500');

                        return;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Quantity Required
                    |--------------------------------------------------------------------------
                    */

                    if (
                        qtyValue === '' ||
                        isNaN(qty) ||
                        qty <= 0
                    ) {

                        hasInvalidItem = true;

                        row.find('.qty')
                            .addClass('border-red-500');

                        return;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Unit Max Length
                    |--------------------------------------------------------------------------
                    */

                    if (unit.length > 50) {

                        hasInvalidItem = true;

                        row.find('.unit')
                            .addClass('border-red-500');

                        return;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Unit Price Required
                    |--------------------------------------------------------------------------
                    */

                    if (
                        priceValue === '' ||
                        isNaN(price) ||
                        price <= 0
                    ) {

                        hasInvalidItem = true;

                        row.find('.price')
                            .addClass('border-red-500');

                        return;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Discount
                    |--------------------------------------------------------------------------
                    */

                    if (
                        discountValue === '' ||
                        isNaN(discount) ||
                        discount < 0 ||
                        discount > 100
                    ) {

                        hasInvalidItem = true;

                        row.find('.disc')
                            .addClass('border-red-500');

                        return;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Tax
                    |--------------------------------------------------------------------------
                    */

                    if (
                        taxValue === '' ||
                        isNaN(tax) ||
                        tax < 0 ||
                        tax > 100
                    ) {

                        hasInvalidItem = true;

                        row.find('.tax')
                            .addClass('border-red-500');

                        return;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Valid Item
                    |--------------------------------------------------------------------------
                    */

                    validItems++;

                });


                /*
                |--------------------------------------------------------------------------
                | No Valid Items
                |--------------------------------------------------------------------------
                */

                if (validItems === 0) {

                    showItemError(
                        'Please add at least one valid purchase order item.'
                    );

                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | Invalid Item
                |--------------------------------------------------------------------------
                */

                if (hasInvalidItem) {

                    showItemError(
                        'Please correct the highlighted item fields.'
                    );

                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | Recalculate Totals
                |--------------------------------------------------------------------------
                */

                calcAll();


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
        | Remove Item Error
        |--------------------------------------------------------------------------
        */

        $(document).on(
            'input change',
            '#poForm input, #poForm select, #poForm textarea',
            function() {

                $('#itemError').remove();
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Initial Calculation
        |--------------------------------------------------------------------------
        */

        $('.item-row').each(function() {

            calcRow(this);
        });

        calcAll();

    });
</script>

<?php

require_once '../../../includes/footer.php';

?>