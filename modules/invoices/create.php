<?php

$pageTitle = 'Create Invoice';

require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('invoices.create')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$clients = $db->query("
    SELECT id, company_name
    FROM clients
    WHERE status = 'active' AND deleted_at IS NULL AND deleted_at IS NULL
    ORDER BY company_name
")->fetchAll();

$projects = $db->query("
    SELECT id, project_name
    FROM projects
    WHERE status IN ('active','planning')
    ORDER BY project_name
")->fetchAll();

$taxes = $db->query("
    SELECT *
    FROM tax_settings
    WHERE is_active = 1
")->fetchAll();


function generateInvoiceNumber($db)
{
    $year = date('Y');
    $month = date('m');

    $stmt = $db->query("
        SELECT COUNT(*) as cnt
        FROM invoices
        WHERE invoice_number LIKE 'INV$year$month%'
    ");

    return "INV"
        . $year
        . $month
        . str_pad(
            $stmt->fetch()['cnt'] + 1,
            4,
            '0',
            STR_PAD_LEFT
        );
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = [
        'client_id' => $_POST['client_id'] ?? '',
        'project_id' => $_POST['project_id'] ?? '',
        'invoice_date' => $_POST['invoice_date'] ?? '',
        'due_date' => $_POST['due_date'] ?? '',
        'notes' => $_POST['notes'] ?? ''
    ];


    $errors = validate(
        $data,
        [
            'client_id' => 'required',
            'invoice_date' => 'required'
        ]
    );


    if (!empty($errors)) {

        saveFormState($errors);

        to('modules/invoices/create.php');
    }


    $invoiceNumber = generateInvoiceNumber($db);

    $subtotal = 0;


    /*
     * Create Invoice
     */
    $stmt = $db->prepare("
        INSERT INTO invoices
        (
            invoice_number,
            client_id,
            project_id,
            invoice_date,
            due_date,
            notes,
            status,
            subtotal,
            tax_amount,
            discount_amount,
            total_amount,
            paid_amount,
            balance_amount
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'draft',
            0,
            0,
            0,
            0,
            0,
            0
        )
    ");


    $stmt->execute([
        $invoiceNumber,
        $data['client_id'],
        $data['project_id'] ?: null,
        $data['invoice_date'],
        $data['due_date'] ?: null,
        $data['notes']
    ]);


    $invoiceId = $db->lastInsertId();


    /*
     * Invoice Items
     */
    if (!empty($_POST['item_name'])) {

        $itemStmt = $db->prepare("
            INSERT INTO invoice_items
            (
                invoice_id,
                item_name,
                description,
                quantity,
                unit_price,
                tax_percentage,
                total
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");


        foreach ($_POST['item_name'] as $i => $itemName) {

            if ($itemName) {

                $qty = $_POST['quantity'][$i] ?: 1;

                $price = $_POST['unit_price'][$i] ?: 0;

                $tax = $_POST['tax_percentage'][$i] ?: 0;

                $lineTotal = $qty * $price;

                $lineTax = $lineTotal * ($tax / 100);

                $lineTotal += $lineTax;

                $subtotal += ($qty * $price);


                $itemStmt->execute([
                    $invoiceId,
                    $itemName,
                    $_POST['description'][$i] ?? '',
                    $qty,
                    $price,
                    $tax,
                    $lineTotal
                ]);
            }
        }
    }


    /*
     * Invoice totals
     */
    $taxAmount = $subtotal * 0.18;

    $totalAmount = $subtotal + $taxAmount;


    $updateStmt = $db->prepare("
        UPDATE invoices
        SET
            subtotal = ?,
            tax_amount = ?,
            total_amount = ?,
            balance_amount = ?
        WHERE id = ?
    ");


    $updateStmt->execute([
        $subtotal,
        $taxAmount,
        $totalAmount,
        $totalAmount,
        $invoiceId
    ]);


    /*
     * Audit
     */
    logAudit(
        $_SESSION['user_id'],
        'create_invoice',
        'invoices'
    );


    flash(
        'success',
        'Invoice created successfully'
    );


    to(
        'http://localhost/ERP/modules/invoices/index.php'
    );
}

?>

<div class="mb-6">

    <a
        href="<?= url('modules/invoices/index.php') ?>"
        class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Invoices
    </a>

</div>


<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">
        Create New Invoice
    </h2>


    <?php if ($errors): ?>

        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">

            <ul class="list-disc list-inside">

                <?php foreach ($errors as $e): ?>

                    <li>
                        <?= sanitize($e) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        class="space-y-6"
        id="invoiceForm"
        novalidate>


        <!-- Invoice Header -->

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">


            <!-- Client -->

            <div>
                <label>Client <span class="text-red-600">*</span></labe>

                <select
                    name="client_id"
                    id="client_id"
                    class="w-full px-3 py-2 border rounded">
                    <option value="">Select Client</option>

                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= sanitize($c['company_name']) ?>
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
                    id="project_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        Select Project
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


            <!-- Invoice Date -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Invoice Date <span class="text-red-600">*</span>
                </label>

                <input
                    type="date"
                    name="invoice_date"
                    id="invoice_date"
                    value="<?= old('invoice_date', date('Y-m-d')) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


            <!-- Due Date -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Due Date
                </label>

                <input
                    type="date"
                    name="due_date"
                    id="due_date"
                    value="<?= old('due_date', date('Y-m-d', strtotime('+30 days'))) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

        </div>


        <!-- Invoice Items -->

        <div>

            <h3 class="text-lg font-medium text-gray-800 mb-3">
                Invoice Items
            </h3>


            <div class="overflow-x-auto">

                <table
                    class="w-full"
                    id="itemsTable">

                    <thead class="bg-gray-50">

                        <tr>

                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">
                                Item
                            </th>

                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">
                                Description
                            </th>

                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 w-24">
                                Qty
                            </th>

                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 w-32">
                                Price
                            </th>

                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 w-24">
                                Tax %
                            </th>

                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 w-32">
                                Total
                            </th>

                            <th class="px-3 py-2 w-10"></th>

                        </tr>

                    </thead>


                    <tbody id="itemsBody">

                        <tr>

                            <!-- Item -->

                            <td class="px-2 py-2">

                                <input
                                    type="text"
                                    name="item_name[]"
                                    placeholder="Item name"
                                    class="w-full px-2 py-1 border rounded">

                            </td>


                            <!-- Description -->

                            <td class="px-2 py-2">

                                <input
                                    type="text"
                                    name="description[]"
                                    placeholder="Description"
                                    class="w-full px-2 py-1 border rounded">

                            </td>


                            <!-- Quantity -->

                            <td class="px-2 py-2">

                                <input
                                    type="number"
                                    name="quantity[]"
                                    value="1"
                                    min="1"
                                    step="1"
                                    class="w-full px-2 py-1 border rounded"
                                    onchange="calculateTotals()">

                            </td>


                            <!-- Unit Price -->

                            <td class="px-2 py-2">

                                <input
                                    type="number"
                                    name="unit_price[]"
                                    value="0"
                                    min="0"
                                    step="0.01"
                                    class="w-full px-2 py-1 border rounded"
                                    onchange="calculateTotals()">

                            </td>


                            <!-- Tax -->

                            <td class="px-2 py-2">

                                <input
                                    type="number"
                                    name="tax_percentage[]"
                                    value="18"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    class="w-full px-2 py-1 border rounded"
                                    onchange="calculateTotals()">

                            </td>


                            <!-- Line Total -->

                            <td class="px-2 py-2">

                                <input
                                    type="text"
                                    name="line_total[]"
                                    value="0"
                                    readonly
                                    class="w-full px-2 py-1 border rounded bg-gray-50">

                            </td>


                            <!-- Delete -->

                            <td class="px-2 py-2">

                                <button
                                    type="button"
                                    onclick="removeRow(this)"
                                    class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>


            <button
                type="button"
                onclick="addRow()"
                class="mt-2 text-blue-600 hover:text-blue-900 text-sm">

                <i class="fas fa-plus mr-1"></i>
                Add Item

            </button>

        </div>


        <!-- Totals -->

        <div class="border-t pt-4">

            <div class="flex justify-end">

                <div class="w-64 space-y-2">


                    <div class="flex justify-between">

                        <span class="text-gray-600">
                            Subtotal:
                        </span>

                        <span
                            class="font-medium"
                            id="subtotalDisplay">
                            $0.00
                        </span>

                    </div>


                    <div class="flex justify-between">

                        <span class="text-gray-600">
                            Tax (18%):
                        </span>

                        <span
                            class="font-medium"
                            id="taxDisplay">
                            $0.00
                        </span>

                    </div>


                    <div class="flex justify-between text-lg font-bold border-t pt-2">

                        <span>
                            Total:
                        </span>

                        <span id="totalDisplay">
                            $0.00
                        </span>

                    </div>

                </div>

            </div>

        </div>


        <!-- Notes -->

        <div>

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Notes
            </label>

            <textarea
                name="notes"
                rows="2"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('notes') ?></textarea>

        </div>


        <!-- Buttons -->

        <div class="pt-4">

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Create Invoice
            </button>


            <a
                href="<?= url('modules/invoices/index.php') ?>"
                class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">
                Cancel
            </a>

        </div>

    </form>

</div>


<style>
    #invoiceForm p.error {
        display: block !important;
        width: 100% !important;
        margin: 4px 0 0 0 !important;
        padding: 0 !important;
        clear: both !important;
        color: #dc2626 !important;
        font-size: 12px !important;
        line-height: 16px !important;
        position: static !important;
    }

    #invoiceForm select.error,
    #invoiceForm input.error,
    #invoiceForm textarea.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    /* SELECT ERROR MUST BE BELOW DROPDOWN */
    #invoiceForm select+p.error {
        display: block !important;
        width: 100% !important;
        margin-top: 4px !important;
        clear: both !important;
    }
</style>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>


<script>
    /*
     * Add Invoice Item
     */
    function addRow() {

        const tbody = document.getElementById('itemsBody');

        const row = tbody.insertRow();


        row.innerHTML = `

        <td class="px-2 py-2">

            <input
                type="text"
                name="item_name[]"
                placeholder="Item name"
                class="w-full px-2 py-1 border rounded"
            >

        </td>


        <td class="px-2 py-2">

            <input
                type="text"
                name="description[]"
                placeholder="Description"
                class="w-full px-2 py-1 border rounded"
            >

        </td>


        <td class="px-2 py-2">

            <input
                type="number"
                name="quantity[]"
                value="1"
                min="1"
                step="1"
                class="w-full px-2 py-1 border rounded"
                onchange="calculateTotals()"
            >

        </td>


        <td class="px-2 py-2">

            <input
                type="number"
                name="unit_price[]"
                value="0"
                min="0"
                step="0.01"
                class="w-full px-2 py-1 border rounded"
                onchange="calculateTotals()"
            >

        </td>


        <td class="px-2 py-2">

            <input
                type="number"
                name="tax_percentage[]"
                value="18"
                min="0"
                max="100"
                step="0.01"
                class="w-full px-2 py-1 border rounded"
                onchange="calculateTotals()"
            >

        </td>


        <td class="px-2 py-2">

            <input
                type="text"
                name="line_total[]"
                value="0"
                readonly
                class="w-full px-2 py-1 border rounded bg-gray-50"
            >

        </td>


        <td class="px-2 py-2">

            <button
                type="button"
                onclick="removeRow(this)"
                class="text-red-600 hover:text-red-900"
            >
                <i class="fas fa-trash"></i>
            </button>

        </td>

    `;


        calculateTotals();

    }


    /*
     * Remove Invoice Item
     */
    function removeRow(btn) {

        const row = btn.closest('tr');


        if (
            document.querySelectorAll('#itemsBody tr').length > 1
        ) {

            row.remove();

            calculateTotals();

        }

    }


    /*
     * Calculate Invoice Totals
     */
    function calculateTotals() {

        let subtotal = 0;


        const rows = document.querySelectorAll(
            '#itemsBody tr'
        );


        rows.forEach(row => {

            const qtyElement = row.querySelector(
                '[name="quantity[]"]'
            );

            const priceElement = row.querySelector(
                '[name="unit_price[]"]'
            );

            const taxElement = row.querySelector(
                '[name="tax_percentage[]"]'
            );

            const totalElement = row.querySelector(
                '[name="line_total[]"]'
            );


            const qty =
                parseFloat(qtyElement.value) || 0;


            const price =
                parseFloat(priceElement.value) || 0;


            const tax =
                parseFloat(taxElement.value) || 0;


            const lineSubtotal =
                qty * price;


            const lineTax =
                lineSubtotal * (tax / 100);


            const lineTotal =
                lineSubtotal + lineTax;


            subtotal += lineSubtotal;


            totalElement.value =
                lineTotal.toFixed(2);

        });


        /*
         * Existing invoice calculation logic
         */
        const tax = subtotal * 0.18;

        const total = subtotal + tax;


        const currencySymbol =
            '<?= getCurrencies()[getActiveCurrency()]['symbol'] ?>';


        document.getElementById(
                'subtotalDisplay'
            ).textContent =
            currencySymbol + subtotal.toFixed(2);


        document.getElementById(
                'taxDisplay'
            ).textContent =
            currencySymbol + tax.toFixed(2);


        document.getElementById(
                'totalDisplay'
            ).textContent =
            currencySymbol + total.toFixed(2);

    }


    /*
     * jQuery Validation
     */
    $(document).ready(function() {


        /*
         * Custom Due Date Validation
         */
        $.validator.addMethod(
            'validDueDate',
            function(value, element) {

                const invoiceDate =
                    $('#invoice_date').val();


                if (!value || !invoiceDate) {

                    return true;

                }


                return value >= invoiceDate;

            },
            'Due date cannot be before invoice date.'
        );


        /*
         * Invoice Form Validation
         */
        $('#invoiceForm').validate({

            rules: {

                client_id: {

                    required: true

                },


                invoice_date: {

                    required: true

                },


                due_date: {

                    validDueDate: true

                }

            },


            messages: {

                client_id: {

                    required: 'Please select client.'

                },


                invoice_date: {

                    required: 'Please select invoice date.'

                },


                due_date: {

                    validDueDate: 'Due date cannot be before invoice date.'

                }

            },


            errorElement: 'p',

            errorClass: 'error',


            /*
             * IMPORTANT:
             * Dropdown error appears BELOW select
             */
            errorPlacement: function(error, element) {

                if (element.is('select')) {

                    error
                        .appendTo(element.parent());

                } else {

                    error.insertAfter(element);

                }

            },


            highlight: function(element) {

                $(element)
                    .addClass('error')
                    .removeClass('valid');

            },


            unhighlight: function(element) {

                $(element)
                    .removeClass('error')
                    .addClass('valid');

            },


            /*
             * Form submit
             */
            submitHandler: function(form) {


                let validItems = true;


                const rows =
                    $('#itemsBody tr');


                /*
                 * Validate every invoice row
                 */
                rows.each(function() {

                    const row = $(this);


                    const itemName =
                        row.find(
                            '[name="item_name[]"]'
                        );


                    const quantity =
                        row.find(
                            '[name="quantity[]"]'
                        );


                    const unitPrice =
                        row.find(
                            '[name="unit_price[]"]'
                        );


                    const taxPercentage =
                        row.find(
                            '[name="tax_percentage[]"]'
                        );


                    /*
                     * Item Name
                     */
                    if (
                        $.trim(itemName.val()) === ''
                    ) {

                        validItems = false;


                        itemName
                            .addClass('error')
                            .removeClass('valid');


                        if (
                            itemName.next(
                                'p.error'
                            ).length === 0
                        ) {

                            $('<p class="error">' +
                                    'Please enter item name.' +
                                    '</p>')
                                .insertAfter(itemName);

                        }

                    } else {

                        itemName
                            .removeClass('error')
                            .addClass('valid');


                        itemName
                            .next('p.error')
                            .remove();

                    }


                    /*
                     * Quantity
                     */
                    const qty =
                        parseFloat(
                            quantity.val()
                        );


                    if (
                        quantity.val() === '' ||
                        isNaN(qty) ||
                        qty < 1
                    ) {

                        validItems = false;


                        quantity
                            .addClass('error')
                            .removeClass('valid');


                        if (
                            quantity.next(
                                'p.error'
                            ).length === 0
                        ) {

                            $('<p class="error">' +
                                    'Quantity must be at least 1.' +
                                    '</p>')
                                .insertAfter(quantity);

                        }

                    } else {

                        quantity
                            .removeClass('error')
                            .addClass('valid');


                        quantity
                            .next('p.error')
                            .remove();

                    }


                    /*
                     * Unit Price
                     */
                    const price =
                        parseFloat(
                            unitPrice.val()
                        );


                    if (
                        unitPrice.val() === '' ||
                        isNaN(price) ||
                        price < 0
                    ) {

                        validItems = false;


                        unitPrice
                            .addClass('error')
                            .removeClass('valid');


                        if (
                            unitPrice.next(
                                'p.error'
                            ).length === 0
                        ) {

                            $('<p class="error">' +
                                    'Please enter a valid unit price.' +
                                    '</p>')
                                .insertAfter(unitPrice);

                        }

                    } else {

                        unitPrice
                            .removeClass('error')
                            .addClass('valid');


                        unitPrice
                            .next('p.error')
                            .remove();

                    }


                    /*
                     * Tax Percentage
                     */
                    const tax =
                        parseFloat(
                            taxPercentage.val()
                        );


                    if (
                        taxPercentage.val() === '' ||
                        isNaN(tax) ||
                        tax < 0 ||
                        tax > 100
                    ) {

                        validItems = false;


                        taxPercentage
                            .addClass('error')
                            .removeClass('valid');


                        if (
                            taxPercentage.next(
                                'p.error'
                            ).length === 0
                        ) {

                            $('<p class="error">' +
                                    'Tax must be between 0 and 100.' +
                                    '</p>')
                                .insertAfter(
                                    taxPercentage
                                );

                        }

                    } else {

                        taxPercentage
                            .removeClass('error')
                            .addClass('valid');


                        taxPercentage
                            .next('p.error')
                            .remove();

                    }

                });


                /*
                 * Stop submit if item validation fails
                 */
                if (!validItems) {

                    return false;

                }


                /*
                 * Submit
                 */
                form.submit();

            }

        });


        /*
         * Revalidate Due Date
         * when Invoice Date changes
         */
        $('#invoice_date').on(
            'change',
            function() {

                $('#due_date').valid();

            }
        );


        /*
         * Validate Due Date on change
         */
        $('#due_date').on(
            'change',
            function() {

                $(this).valid();

            }
        );


        /*
         * Remove item validation error
         * while user edits the field
         */
        $(document).on(
            'input change',
            '#itemsBody input[name="item_name[]"], \
         #itemsBody input[name="quantity[]"], \
         #itemsBody input[name="unit_price[]"], \
         #itemsBody input[name="tax_percentage[]"]',
            function() {

                const element = $(this);


                element
                    .removeClass('error');


                element
                    .next('p.error')
                    .remove();

            }
        );


        /*
         * Initial calculation
         */
        calculateTotals();

    });
</script>


<?php

require_once '../../includes/footer.php';

?>