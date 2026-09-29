<?php
$pageTitle = 'Create Goods Receipt Note';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';

if (!hasPermission('goods_receipt.create')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$pre_po_id = (int)($_GET['po_id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Get eligible Purchase Orders
|--------------------------------------------------------------------------
| A PO is eligible when:
| approved / sent / partially_received
| and it still has remaining quantity.
*/
$eligiblePOs = $db->prepare("
    SELECT
        po.id,
        po.po_number,
        v.vendor_name,

        (
            SELECT COUNT(*)
            FROM purchase_order_items poi
            WHERE poi.po_id = po.id
              AND poi.remaining_quantity > 0
        ) AS items_pending

    FROM purchase_orders po

    INNER JOIN vendors v
        ON po.vendor_id = v.id

    WHERE po.status IN ('approved', 'sent', 'partially_received')

      AND EXISTS (
          SELECT 1
          FROM purchase_order_items poi2
          WHERE poi2.po_id = po.id
            AND poi2.remaining_quantity > 0
      )

    ORDER BY po.po_number DESC
");

$eligiblePOs->execute();
$eligiblePOs = $eligiblePOs->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Generate GRN Number
|--------------------------------------------------------------------------
*/
$prefix = 'GRN' . date('Ymd');

$stmt = $db->prepare("
    SELECT COUNT(*)
    FROM goods_receipt_notes
    WHERE grn_number LIKE ?
");

$stmt->execute([$prefix . '%']);

$grn_number = $prefix . '-' .
    str_pad(
        (int)$stmt->fetchColumn() + 1,
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

    $po_id = (int)($_POST['po_id'] ?? 0);

    $receipt_date = trim(
        $_POST['receipt_date'] ?? ''
    );

    $delivery_note_number = trim(
        $_POST['delivery_note_number'] ?? ''
    );

    $notes = trim(
        $_POST['notes'] ?? ''
    );

    $items = $_POST['items'] ?? [];


    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if ($po_id <= 0) {
        $errors[] = 'Please select a purchase order.';
    }

    if ($receipt_date === '') {
        $errors[] = 'Receipt date is required.';
    }

    if (!empty($receipt_date)) {

        $dateObj = DateTime::createFromFormat(
            'Y-m-d',
            $receipt_date
        );

        if (
            !$dateObj ||
            $dateObj->format('Y-m-d') !== $receipt_date
        ) {
            $errors[] = 'Invalid receipt date.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Load PO
    |--------------------------------------------------------------------------
    */

    $po = null;

    if ($po_id > 0) {

        $poCheck = $db->prepare("
            SELECT *
            FROM purchase_orders
            WHERE id = ?
            LIMIT 1
        ");

        $poCheck->execute([$po_id]);

        $po = $poCheck->fetch(PDO::FETCH_ASSOC);

        if (!$po) {

            $errors[] = 'Purchase order not found.';
        } elseif (
            in_array(
                $po['status'],
                ['draft', 'cancelled', 'closed', 'received'],
                true
            )
        ) {

            $errors[] =
                'This PO is not eligible for receiving (status: ' .
                $po['status'] .
                ').';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Validate submitted items
    |--------------------------------------------------------------------------
    */

    if (
        empty($items) ||
        !is_array($items)
    ) {

        $errors[] =
            'At least one item with quantity > 0 is required.';
    } else {

        $hasQty = false;

        foreach ($items as $item) {

            $qty = (float)str_replace(
                ',',
                '',
                $item['quantity_received'] ?? 0
            );

            if ($qty > 0) {
                $hasQty = true;
                break;
            }
        }

        if (!$hasQty) {

            $errors[] =
                'At least one item must have a received quantity greater than 0.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Stop if validation errors
    |--------------------------------------------------------------------------
    */

    if (!empty($errors)) {

        saveFormState($errors);

        to(
            'modules/finance/goods_receipt/create.php' .
                ($pre_po_id
                    ? '?po_id=' . $pre_po_id
                    : '')
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Create GRN
    |--------------------------------------------------------------------------
    */

    try {

        $db->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | Lock PO
        |--------------------------------------------------------------------------
        */

        $poStmt = $db->prepare("
            SELECT *
            FROM purchase_orders
            WHERE id = ?
            FOR UPDATE
        ");

        $poStmt->execute([$po_id]);

        $po = $poStmt->fetch(PDO::FETCH_ASSOC);

        if (!$po) {
            throw new Exception(
                'Purchase order not found.'
            );
        }

        if (
            in_array(
                $po['status'],
                ['draft', 'cancelled', 'closed', 'received'],
                true
            )
        ) {

            throw new Exception(
                'This PO is not eligible for receiving.'
            );
        }


        $vendor_id = (int)$po['vendor_id'];


        /*
        |--------------------------------------------------------------------------
        | Insert GRN Header
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            INSERT INTO goods_receipt_notes
            (
                grn_number,
                po_id,
                vendor_id,
                receipt_date,
                delivery_note_number,
                status,
                notes,
                created_by,
                created_at,
                updated_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                'completed',
                ?,
                ?,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            $grn_number,
            $po_id,
            $vendor_id,
            $receipt_date,
            $delivery_note_number !== ''
                ? $delivery_note_number
                : null,
            $notes !== ''
                ? $notes
                : null,
            $_SESSION['user_id']
        ]);

        $grn_id = (int)$db->lastInsertId();


        /*
        |--------------------------------------------------------------------------
        | Prepare statements
        |--------------------------------------------------------------------------
        */

        $getPOItem = $db->prepare("
            SELECT
                poi.*
            FROM purchase_order_items poi
            WHERE poi.id = ?
              AND poi.po_id = ?
            FOR UPDATE
        ");


        $getReceived = $db->prepare("
            SELECT
                COALESCE(
                    SUM(fgri.quantity_received),
                    0
                ) AS received_qty
            FROM finance_goods_receipt_items fgri

            INNER JOIN goods_receipt_notes grn
                ON grn.id = fgri.grn_id

            WHERE fgri.po_item_id = ?
              AND grn.po_id = ?
              AND grn.status <> 'cancelled'
        ");


        $insertItem = $db->prepare("
            INSERT INTO finance_goods_receipt_items
            (
                grn_id,
                po_item_id,
                description,
                quantity_received,
                unit,
                unit_price,
                line_total,
                sort_order
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");


        $updateRemaining = $db->prepare("
            UPDATE purchase_order_items
            SET remaining_quantity = ?
            WHERE id = ?
              AND po_id = ?
        ");


        $idx = 0;

        $receivedSomething = false;


        /*
        |--------------------------------------------------------------------------
        | Process GRN Items
        |--------------------------------------------------------------------------
        */

        foreach ($items as $po_item_id => $item) {

            $qty = (float)str_replace(
                ',',
                '',
                $item['quantity_received'] ?? 0
            );

            /*
            | Ignore zero quantity
            */
            if ($qty <= 0) {
                continue;
            }

            $po_item_id = (int)$po_item_id;

            if ($po_item_id <= 0) {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Get PO Item
            |--------------------------------------------------------------------------
            */

            $getPOItem->execute([
                $po_item_id,
                $po_id
            ]);

            $poi = $getPOItem->fetch(PDO::FETCH_ASSOC);

            if (!$poi) {

                throw new Exception(
                    'Invalid purchase order item selected.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Ordered Quantity
            |--------------------------------------------------------------------------
            */

            $orderedQty = (float)$poi['ordered_quantity'];


            /*
            |--------------------------------------------------------------------------
            | Calculate already received
            |--------------------------------------------------------------------------
            */

            $getReceived->execute([
                $po_item_id,
                $po_id
            ]);

            $alreadyReceived = (float)$getReceived
                ->fetchColumn();


            /*
            |--------------------------------------------------------------------------
            | Calculate remaining
            |--------------------------------------------------------------------------
            */

            $remainingQty =
                $orderedQty - $alreadyReceived;


            /*
            |--------------------------------------------------------------------------
            | Protect floating point rounding
            |--------------------------------------------------------------------------
            */

            if ($remainingQty < 0) {
                $remainingQty = 0;
            }


            /*
            |--------------------------------------------------------------------------
            | Prevent over receipt
            |--------------------------------------------------------------------------
            */

            if ($qty > $remainingQty + 0.000001) {

                throw new Exception(
                    'Over-receipt prevented for item "' .
                        ($poi['item_name'] ?: $poi['description']) .
                        '". Maximum remaining: ' .
                        number_format(
                            $remainingQty,
                            3,
                            '.',
                            ''
                        )
                );
            }


            /*
            |--------------------------------------------------------------------------
            | New remaining quantity
            |--------------------------------------------------------------------------
            */

            $newRemaining =
                $remainingQty - $qty;


            if ($newRemaining < 0) {
                $newRemaining = 0;
            }


            /*
            |--------------------------------------------------------------------------
            | Description
            |--------------------------------------------------------------------------
            */

            $description =
                trim(
                    $poi['description'] ??
                        ''
                );

            if ($description === '') {

                $description =
                    $poi['item_name'] ??
                    'PO Item';
            }


            /*
            |--------------------------------------------------------------------------
            | Unit Price
            |--------------------------------------------------------------------------
            */

            $unitPrice =
                (float)$poi['unit_price'];


            /*
            |--------------------------------------------------------------------------
            | Line Total
            |--------------------------------------------------------------------------
            */

            $lineTotal =
                $qty * $unitPrice;


            /*
            |--------------------------------------------------------------------------
            | Insert GRN Item
            |--------------------------------------------------------------------------
            */

            $insertItem->execute([
                $grn_id,
                $po_item_id,
                $description,
                $qty,
                $poi['unit'],
                $unitPrice,
                $lineTotal,
                $idx++
            ]);


            /*
            |--------------------------------------------------------------------------
            | Update PO Remaining Quantity
            |--------------------------------------------------------------------------
            */

            $updateRemaining->execute([
                $newRemaining,
                $po_item_id,
                $po_id
            ]);


            $receivedSomething = true;
        }


        /*
        |--------------------------------------------------------------------------
        | Make sure at least one item was received
        |--------------------------------------------------------------------------
        */

        if (!$receivedSomething) {

            throw new Exception(
                'At least one item must have a received quantity greater than 0.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Check PO remaining quantities
        |--------------------------------------------------------------------------
        */

        $pendingStmt = $db->prepare("
            SELECT COUNT(*)
            FROM purchase_order_items
            WHERE po_id = ?
              AND remaining_quantity > 0
        ");

        $pendingStmt->execute([
            $po_id
        ]);

        $pendingCount =
            (int)$pendingStmt->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | Update PO Status
        |--------------------------------------------------------------------------
        */

        if ($pendingCount === 0) {

            $status = 'received';
        } else {

            $status = 'partially_received';
        }


        $updatePO = $db->prepare("
            UPDATE purchase_orders
            SET
                status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $updatePO->execute([
            $status,
            $po_id
        ]);


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
            'Created GRN: ' .
                $grn_number .
                ' for PO: ' .
                $po['po_number'],
            'goods_receipt'
        );


        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */

        flash(
            'success',
            'Goods receipt note ' .
                $grn_number .
                ' created successfully.',
            'success'
        );


        redirect(
            'view.php?id=' . $grn_id
        );
    } catch (Throwable $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        flash(
            'error',
            'Error: ' . $e->getMessage()
        );

        to(
            'modules/finance/goods_receipt/create.php' .
                ($pre_po_id
                    ? '?po_id=' . $pre_po_id
                    : '')
        );
    }
}
?>

<div class="mb-6 flex justify-between items-center">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            Create Goods Receipt Note
        </h1>

        <p class="text-gray-600">
            Receive goods against a purchase order
        </p>
    </div>

    <a
        href="index.php"
        class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
        <i class="fas fa-arrow-left mr-2"></i>
        Back
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
    id="grnForm">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= csrfToken() ?>">


    <!-- Header -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    GRN Number
                </label>

                <input
                    type="text"
                    value="<?= sanitize($grn_number) ?>"
                    class="w-full px-3 py-2 border rounded bg-gray-50 text-gray-500"
                    readonly>

            </div>


            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Receipt Date
                    <span class="text-red-500">*</span>
                </label>

                <input
                    type="date"
                    name="receipt_date"
                    value="<?= old('receipt_date', date('Y-m-d')) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    required>

            </div>


            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Purchase Order
                    <span class="text-red-500">*</span>
                </label>

                <select
                    name="po_id"
                    id="poSelect"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    required
                    onchange="loadPOItems(this.value)">

                    <option value="">
                        -- Select PO --
                    </option>

                    <?php foreach ($eligiblePOs as $poOption): ?>

                        <option
                            value="<?= (int)$poOption['id'] ?>"
                            <?= old('po_id', $pre_po_id) == $poOption['id'] ? 'selected' : '' ?>>

                            <?= sanitize(
                                $poOption['po_number'] .
                                    ' - ' .
                                    $poOption['vendor_name'] .
                                    ' (' .
                                    $poOption['items_pending'] .
                                    ' items pending)'
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Delivery Note #
                </label>

                <input
                    type="text"
                    name="delivery_note_number"
                    value="<?= old('delivery_note_number') ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    maxlength="100">

            </div>

        </div>

    </div>


    <!-- Items -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            Items Received
        </h3>

        <div class="overflow-x-auto">

            <table
                class="w-full"
                id="itemsTable">

                <thead class="bg-gray-50">

                    <tr>

                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            #
                        </th>

                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            Description
                        </th>

                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                            Ordered
                        </th>

                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                            Previously Received
                        </th>

                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                            Qty Receiving
                        </th>

                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                            Unit Price
                        </th>

                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                            Line Total
                        </th>

                    </tr>

                </thead>


                <tbody id="itemsBody">

                    <tr id="noItemsRow">

                        <td
                            colspan="7"
                            class="px-3 py-4 text-center text-gray-500">
                            Select a purchase order to load items.
                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>


    <!-- Notes -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <label class="block text-sm font-medium text-gray-700 mb-1">
            Notes
        </label>

        <textarea
            name="notes"
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
            Create GRN
        </button>

    </div>

</form>


<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- jQuery Validate -->
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>
    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }


    /*
    |--------------------------------------------------------------------------
    | Load PO Items
    |--------------------------------------------------------------------------
    */
    function loadPOItems(poId) {

        const tbody = document.getElementById('itemsBody');

        if (!poId) {

            tbody.innerHTML = `
            <tr id="noItemsRow">
                <td colspan="7"
                    class="px-3 py-4 text-center text-gray-500">
                    Select a purchase order to load items.
                </td>
            </tr>
        `;

            reinitializeValidation();

            return;
        }

        tbody.innerHTML = `
        <tr>
            <td colspan="7"
                class="px-3 py-4 text-center text-gray-500">
                <i class="fas fa-spinner fa-spin mr-2"></i>
                Loading items...
            </td>
        </tr>
    `;


        fetch(
                '<?= url('modules/finance/purchase_orders/ajax_get_items.php') ?>?po_id=' +
                encodeURIComponent(poId)
            )

            .then(response => {

                if (!response.ok) {
                    throw new Error('HTTP error');
                }

                return response.json();
            })

            .then(data => {

                if (data.error) {

                    tbody.innerHTML = `
                <tr>
                    <td colspan="7"
                        class="px-3 py-4 text-center text-red-500">
                        ${escapeHtml(data.error)}
                    </td>
                </tr>
            `;

                    reinitializeValidation();

                    return;
                }


                if (!Array.isArray(data) || !data.length) {

                    tbody.innerHTML = `
                <tr>
                    <td colspan="7"
                        class="px-3 py-4 text-center text-gray-500">
                        No remaining items found for this PO.
                    </td>
                </tr>
            `;

                    reinitializeValidation();

                    return;
                }


                tbody.innerHTML = '';


                data.forEach((item, idx) => {

                    const ordered =
                        parseFloat(item.ordered_quantity ?? 0);

                    const previouslyReceived =
                        parseFloat(item.received_quantity ?? 0);

                    const remaining =
                        parseFloat(item.remaining_quantity ?? 0);

                    const price =
                        parseFloat(item.unit_price ?? 0);


                    const tr = document.createElement('tr');

                    tr.className = 'hover:bg-gray-50';


                    tr.innerHTML = `

                <td class="px-3 py-2 text-sm text-gray-500">
                    ${idx + 1}
                </td>


                <td class="px-3 py-2 text-sm text-gray-900">
                    ${escapeHtml(
                        item.description ||
                        item.item_name ||
                        'PO Item'
                    )}
                </td>


                <td class="px-3 py-2 text-sm text-right font-mono">
                    ${ordered.toFixed(3)}
                </td>


                <td class="px-3 py-2 text-sm text-right font-mono text-gray-500">
                    ${previouslyReceived.toFixed(3)}
                </td>


                <td class="px-3 py-2">

                    <input
                        type="number"
                        name="items[${item.id}][quantity_received]"
                        value="${remaining > 0 ? remaining : 0}"
                        step="0.01"
                        min="0"
                        max="${remaining}"
                        data-remaining="${remaining}"
                        data-price="${price}"
                        class="w-24 px-2 py-1.5 border rounded text-sm text-right qty-input"
                        oninput="calcLine(this)"
                    >

                </td>


                <td class="px-3 py-2 text-sm text-right font-mono">
                    ${price.toFixed(2)}
                </td>


                <td class="px-3 py-2 text-sm text-right font-mono line-total">
                    0.00
                </td>

            `;


                    tbody.appendChild(tr);


                    const qtyInput =
                        tr.querySelector('.qty-input');


                    if (qtyInput) {
                        calcLine(qtyInput);
                    }

                });


                reinitializeValidation();

            })

            .catch(error => {

                console.error(error);

                tbody.innerHTML = `
            <tr>
                <td colspan="7"
                    class="px-3 py-4 text-center text-red-500">
                    Error loading items.
                </td>
            </tr>
        `;

                reinitializeValidation();
            });
    }


    /*
    |--------------------------------------------------------------------------
    | Calculate Line Total
    |--------------------------------------------------------------------------
    */
    function calcLine(input) {

        const row = input.closest('tr');

        let qty =
            parseFloat(input.value) || 0;

        const price =
            parseFloat(input.dataset.price) || 0;

        const max =
            parseFloat(input.dataset.remaining);


        if (!isNaN(max) && qty > max) {

            qty = max;

            input.value = max.toFixed(2);
        }


        if (qty < 0) {

            qty = 0;

            input.value = '0';
        }


        const total =
            qty * price;


        const lineTotal =
            row.querySelector('.line-total');


        if (lineTotal) {

            lineTotal.textContent =
                total.toFixed(2);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Custom jQuery Validation Method
    |--------------------------------------------------------------------------
    |
    | At least one GRN item must have quantity > 0.
    |
    */
    if ($.validator) {

        $.validator.addMethod(
            'atLeastOneQty',
            function() {

                let valid = false;

                $('.qty-input').each(function() {

                    const qty =
                        parseFloat($(this).val()) || 0;

                    const max =
                        parseFloat($(this).data('remaining')) || 0;

                    if (
                        qty > 0 &&
                        qty <= max
                    ) {
                        valid = true;
                        return false;
                    }

                });

                return valid;
            },
            'Enter quantity greater than 0 for at least one item.'
        );


        /*
        |--------------------------------------------------------------------------
        | Remaining Quantity Validation
        |--------------------------------------------------------------------------
        */
        $.validator.addMethod(
            'validRemainingQty',
            function(value, element) {

                const qty =
                    parseFloat(value) || 0;

                const max =
                    parseFloat($(element).data('remaining')) || 0;

                return qty >= 0 && qty <= max;

            },
            'Quantity cannot exceed the remaining quantity.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Initialize / Reinitialize Validation
    |--------------------------------------------------------------------------
    */
    function reinitializeValidation() {

        if (
            typeof $ === 'undefined' ||
            !$.validator
        ) {
            console.error(
                'jQuery Validation plugin is not loaded.'
            );

            return;
        }


        const form =
            $('#grnForm');


        /*
        |--------------------------------------------------------------------------
        | Destroy previous validator
        |--------------------------------------------------------------------------
        */
        if (form.data('validator')) {
            form.validate().destroy();
        }


        /*
        |--------------------------------------------------------------------------
        | jQuery Validation
        |--------------------------------------------------------------------------
        */
        form.validate({

            ignore: [],


            /*
            |--------------------------------------------------------------------------
            | Validation Rules
            |--------------------------------------------------------------------------
            */
            rules: {

                receipt_date: {
                    required: true,
                    date: true
                },

                po_id: {
                    required: true
                },

                delivery_note_number: {
                    maxlength: 100
                },

                notes: {
                    maxlength: 1000
                }
            },


            /*
            |--------------------------------------------------------------------------
            | Messages
            |--------------------------------------------------------------------------
            */
            messages: {

                receipt_date: {
                    required: 'Receipt date is required.',
                    date: 'Please enter a valid receipt date.'
                },

                po_id: {
                    required: 'Please select a purchase order.'
                },

                delivery_note_number: {
                    maxlength: 'Delivery Note number cannot exceed 100 characters.'
                },

                notes: {
                    maxlength: 'Notes cannot exceed 1000 characters.'
                }
            },


            /*
            |--------------------------------------------------------------------------
            | Error Element
            |--------------------------------------------------------------------------
            */
            errorElement: 'div',


            /*
            |--------------------------------------------------------------------------
            | Error Class
            |--------------------------------------------------------------------------
            */
            errorClass: 'text-red-600 text-sm mt-1',


            /*
            |--------------------------------------------------------------------------
            | Highlight
            |--------------------------------------------------------------------------
            */
            highlight: function(element) {

                $(element)
                    .addClass('border-red-500')
                    .removeClass('border-gray-300');

            },


            /*
            |--------------------------------------------------------------------------
            | Unhighlight
            |--------------------------------------------------------------------------
            */
            unhighlight: function(element) {

                $(element)
                    .removeClass('border-red-500')
                    .addClass('border-gray-300');

            },


            /*
            |--------------------------------------------------------------------------
            | Error Placement
            |--------------------------------------------------------------------------
            */
            errorPlacement: function(error, element) {

                /*
                | Select2 / special fields
                */
                if (
                    element.hasClass('select2-hidden-accessible')
                ) {

                    error.insertAfter(
                        element.next('.select2')
                    );

                    return;
                }


                /*
                | Quantity input
                */
                if (
                    element.hasClass('qty-input')
                ) {

                    error.insertAfter(element);

                    return;
                }


                /*
                | Normal fields
                */
                error.insertAfter(element);
            },


            /*
            |--------------------------------------------------------------------------
            | Submit Handler
            |--------------------------------------------------------------------------
            */
            submitHandler: function(form) {

                let hasValidQty = false;


                $('.qty-input').each(function() {

                    const qty =
                        parseFloat($(this).val()) || 0;

                    const max =
                        parseFloat($(this).data('remaining')) || 0;


                    if (
                        qty > 0 &&
                        qty <= max
                    ) {

                        hasValidQty = true;

                    }

                });


                /*
                |--------------------------------------------------------------------------
                | At least one quantity required
                |--------------------------------------------------------------------------
                */
                if (!hasValidQty) {

                    showQtyError();

                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | Validate every quantity
                |--------------------------------------------------------------------------
                */
                let quantityValid = true;


                $('.qty-input').each(function() {

                    const input =
                        $(this);

                    const qty =
                        parseFloat(input.val()) || 0;

                    const max =
                        parseFloat(input.data('remaining')) || 0;


                    if (
                        qty < 0 ||
                        qty > max
                    ) {

                        quantityValid = false;


                        input
                            .addClass('border-red-500')
                            .removeClass('border-gray-300');


                        if (
                            input.next('.qty-error').length === 0
                        ) {

                            input.after(`
                            <div class="qty-error text-red-600 text-sm mt-1">
                                Maximum allowed: ${max.toFixed(2)}
                            </div>
                        `);
                        }

                    } else {

                        input
                            .removeClass('border-red-500')
                            .addClass('border-gray-300');

                        input
                            .next('.qty-error')
                            .remove();

                    }

                });


                if (!quantityValid) {
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
        | Add dynamic quantity validation
        |--------------------------------------------------------------------------
        */
        $('.qty-input').each(function() {

            $(this).rules('add', {

                required: true,

                number: true,

                min: 0,

                validRemainingQty: true,

                messages: {

                    required: 'Quantity is required.',

                    number: 'Please enter a valid quantity.',

                    min: 'Quantity cannot be negative.',

                    validRemainingQty: 'Quantity cannot exceed the remaining quantity.'
                }

            });

        });

    }


    /*
    |--------------------------------------------------------------------------
    | Show quantity error
    |--------------------------------------------------------------------------
    */
    function showQtyError() {

        $('.qty-summary-error').remove();


        const message = `
        <div
            class="qty-summary-error bg-red-50 border border-red-300
                   text-red-700 px-4 py-3 rounded mb-4"
        >
            <i class="fas fa-exclamation-circle mr-2"></i>
            Please enter a received quantity greater than 0
            for at least one item.
        </div>
    `;


        $('#itemsTable').before(message);


        $('html, body').animate({
                scrollTop: $('.qty-summary-error').offset().top - 100
            },
            300
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Remove quantity summary error when user changes quantity
    |--------------------------------------------------------------------------
    */
    $(document).on(
        'input',
        '.qty-input',
        function() {

            $('.qty-summary-error').remove();

            $(this)
                .next('.qty-error')
                .remove();

            calcLine(this);
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Page Load
    |--------------------------------------------------------------------------
    */
    $(document).ready(function() {

        /*
        | Make sure jQuery Validate is available
        */
        if (!$.validator) {

            console.error(
                'jQuery Validation plugin is not available.'
            );

            return;
        }


        /*
        | Initialize form validation
        */
        reinitializeValidation();


        /*
        | Auto-load PO
        */
        const poSelect =
            document.getElementById('poSelect');


        if (
            poSelect &&
            poSelect.value
        ) {

            loadPOItems(
                poSelect.value
            );
        }

    });
</script>




<?php require_once '../../../includes/footer.php'; ?>