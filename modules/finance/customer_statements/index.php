<?php
$pageTitle = 'Customer Statement';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('customer_statements.view')) {
    die('Access Denied');
}

$db = getDB();

/*
|--------------------------------------------------------------------------
| Load Active Clients
|--------------------------------------------------------------------------
*/
$clients = $db->query("
    SELECT id, client_code, company_name
    FROM clients
    WHERE status = 'active' AND deleted_at IS NULL
    ORDER BY company_name
")->fetchAll();

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$client_id = (int)($_GET['client_id'] ?? 0);

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date   = $_GET['to_date'] ?? date('Y-m-d');

$statementData = [];

/*
|--------------------------------------------------------------------------
| Generate Customer Statement
|--------------------------------------------------------------------------
*/
if (
    $client_id > 0 &&
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    isset($_GET['generate'])
) {

    /*
    |--------------------------------------------------------------------------
    | Validate Date Range Server Side
    |--------------------------------------------------------------------------
    */
    if ($from_date > $to_date) {
        flash('error', 'From date cannot be greater than To date.');
        redirect(
            'index.php?client_id=' . $client_id .
                '&from_date=' . urlencode($from_date) .
                '&to_date=' . urlencode($to_date)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Load Client Information
    |--------------------------------------------------------------------------
    */
    $stmt = $db->prepare("
        SELECT
            id,
            client_code,
            company_name,
            email,
            phone,
            address
        FROM clients
        WHERE id = ?
    ");

    $stmt->execute([$client_id]);

    $client = $stmt->fetch();

    if (!$client) {
        flash('error', 'Client not found.');
        redirect('index.php');
    }

    /*
    |--------------------------------------------------------------------------
    | Opening Balance
    |--------------------------------------------------------------------------
    |
    | Opening Balance =
    | Invoice Total Before From Date
    | -
    | Paid Total Before From Date
    |
    */
    $stmt = $db->prepare("
        SELECT
            COALESCE(SUM(total_amount), 0) AS invoice_total,
            COALESCE(SUM(paid_amount), 0) AS paid_total
        FROM customer_invoices
        WHERE client_id = ?
          AND invoice_date < ?
    ");

    $stmt->execute([
        $client_id,
        $from_date
    ]);

    $openingData = $stmt->fetch();

    $openingBalance =
        (float)$openingData['invoice_total']
        -
        (float)$openingData['paid_total'];

    /*
    |--------------------------------------------------------------------------
    | Load Invoices
    |--------------------------------------------------------------------------
    */
    $stmt = $db->prepare("
        SELECT
            id,
            invoice_number,
            invoice_date,
            due_date,
            total_amount,
            paid_amount,
            balance_amount,
            status,
            created_at
        FROM customer_invoices
        WHERE client_id = ?
          AND invoice_date BETWEEN ? AND ?
        ORDER BY invoice_date ASC, id ASC
    ");

    $stmt->execute([
        $client_id,
        $from_date,
        $to_date
    ]);

    $invoices = $stmt->fetchAll();

    /*
    |--------------------------------------------------------------------------
    | Load Receipts
    |--------------------------------------------------------------------------
    */
    $stmt = $db->prepare("
        SELECT
            cr.receipt_number,
            cr.receipt_date,
            cr.amount,
            cr.reference_number,
            cr.payment_method,
            ra.invoice_id,
            ra.amount AS allocated_amount,
            cr.created_at
        FROM customer_receipts cr
        LEFT JOIN receipt_allocations ra
            ON cr.id = ra.receipt_id
        WHERE cr.client_id = ?
          AND cr.receipt_date BETWEEN ? AND ?
        ORDER BY cr.receipt_date ASC, cr.id ASC
    ");

    $stmt->execute([
        $client_id,
        $from_date,
        $to_date
    ]);

    $receipts = $stmt->fetchAll();

    /*
    |--------------------------------------------------------------------------
    | Load Credit Notes
    |--------------------------------------------------------------------------
    */
    $stmt = $db->prepare("
        SELECT
            credit_note_number,
            credit_note_date,
            credit_note_type,
            total_amount,
            reason,
            status,
            created_at
        FROM credit_notes
        WHERE client_id = ?
          AND credit_note_date BETWEEN ? AND ?
          AND status IN ('issued', 'applied')
        ORDER BY credit_note_date ASC
    ");

    $stmt->execute([
        $client_id,
        $from_date,
        $to_date
    ]);

    $creditNotes = $stmt->fetchAll();

    /*
    |--------------------------------------------------------------------------
    | Calculate Totals
    |--------------------------------------------------------------------------
    */
    $invoiceTotal = 0;
    $receiptTotal = 0;
    $cnTotal      = 0;

    foreach ($invoices as $inv) {
        $invoiceTotal += (float)$inv['total_amount'];
    }

    foreach ($receipts as $r) {
        /*
         * If receipt is allocated, use allocated amount.
         * Otherwise use receipt amount.
         */
        $allocatedAmount = isset($r['allocated_amount'])
            ? (float)$r['allocated_amount']
            : 0;

        $receiptTotal += $allocatedAmount;
    }

    foreach ($creditNotes as $cn) {
        $cnTotal += (float)$cn['total_amount'];
    }

    /*
    |--------------------------------------------------------------------------
    | Closing Balance
    |--------------------------------------------------------------------------
    |
    | Closing = Opening + Invoices - Payments - Credit Notes
    |
    */
    $closingBalance =
        $openingBalance
        +
        $invoiceTotal
        -
        $receiptTotal
        -
        $cnTotal;

    /*
    |--------------------------------------------------------------------------
    | Build Statement Entries
    |--------------------------------------------------------------------------
    */
    $entries = [];

    /*
    |--------------------------------------------------------------------------
    | Invoice Entries
    |--------------------------------------------------------------------------
    */
    foreach ($invoices as $inv) {

        $entries[] = [
            'date' => $inv['invoice_date'],
            'type' => 'Invoice',
            'reference' => $inv['invoice_number'],
            'description' =>
            'Invoice - ' .
                ucwords(
                    str_replace(
                        '_',
                        ' ',
                        $inv['status']
                    )
                ),
            'debit' => (float)$inv['total_amount'],
            'credit' => 0,
            'created_at' => $inv['created_at'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Receipt Entries
    |--------------------------------------------------------------------------
    */
    foreach ($receipts as $r) {

        $allocatedAmount = isset($r['allocated_amount'])
            ? (float)$r['allocated_amount']
            : 0;

        $description =
            'Payment - ' .
            ucwords(
                str_replace(
                    '_',
                    ' ',
                    $r['payment_method']
                )
            );

        if (!empty($r['reference_number'])) {
            $description .=
                ' (Ref: ' .
                $r['reference_number'] .
                ')';
        }

        $entries[] = [
            'date' => $r['receipt_date'],
            'type' => 'Receipt',
            'reference' => $r['receipt_number'],
            'description' => $description,
            'debit' => 0,
            'credit' => $allocatedAmount,
            'created_at' => $r['created_at'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Credit Note Entries
    |--------------------------------------------------------------------------
    */
    foreach ($creditNotes as $cn) {

        $entries[] = [
            'date' => $cn['credit_note_date'],
            'type' => 'Credit Note',
            'reference' => $cn['credit_note_number'],
            'description' =>
            'Credit Note - ' .
                ucwords(
                    str_replace(
                        '_',
                        ' ',
                        $cn['credit_note_type']
                    )
                ),
            'debit' => 0,
            'credit' => (float)$cn['total_amount'],
            'created_at' => $cn['created_at'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Sort Entries Chronologically
    |--------------------------------------------------------------------------
    */
    usort(
        $entries,
        function ($a, $b) {

            if ($a['date'] === $b['date']) {

                return strtotime(
                    $a['created_at'] ?? $a['date']
                ) -
                    strtotime(
                        $b['created_at'] ?? $b['date']
                    );
            }

            return strtotime($a['date']) -
                strtotime($b['date']);
        }
    );

    /*
    |--------------------------------------------------------------------------
    | Statement Data
    |--------------------------------------------------------------------------
    */
    $statementData = [
        'client' => $client,
        'opening_balance' => $openingBalance,
        'closing_balance' => $closingBalance,
        'entries' => $entries,
        'invoice_total' => $invoiceTotal,
        'receipt_total' => $receiptTotal,
        'cn_total' => $cnTotal,
    ];
}
?>

<!--
|--------------------------------------------------------------------------
| Page Header
|--------------------------------------------------------------------------
-->
<div class="mb-6 flex justify-between items-center">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            Customer Statement
        </h1>

        <p class="text-gray-600">
            Generate detailed customer statements
        </p>
    </div>

    <div class="flex gap-2">

        <a href="<?= url('modules/finance/index.php') ?>"
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">

            <i class="fas fa-arrow-left mr-2"></i>
            Back to Finance

        </a>

        <?php if (!empty($statementData)): ?>

            <button
                type="button"
                onclick="window.print()"
                class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">

                <i class="fas fa-print mr-2"></i>
                Print Statement

            </button>

        <?php endif; ?>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| Filter Form
|--------------------------------------------------------------------------
-->
<div class="bg-white rounded-lg shadow mb-6 p-4">

    <form
        method="GET"
        id="customerStatementForm"
        class="flex flex-wrap gap-4 items-end"
        novalidate>

        <!-- Client -->
        <div class="w-56">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Client <span class="text-red-500">*</span>
            </label>

            <select
                name="client_id"
                id="clientSelect"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                <option value="">
                    Select Client
                </option>

                <?php foreach ($clients as $c): ?>

                    <option
                        value="<?= (int)$c['id'] ?>"
                        <?= $client_id == $c['id'] ? 'selected' : '' ?>>

                        <?= sanitize($c['company_name']) ?>
                        (<?= sanitize($c['client_code']) ?>)

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- From Date -->
        <div class="w-40">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                From Date
            </label>

            <input
                type="date"
                name="from_date"
                id="fromDate"
                value="<?= sanitize($from_date) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

        </div>


        <!-- To Date -->
        <div class="w-40">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                To Date
            </label>

            <input
                type="date"
                name="to_date"
                id="toDate"
                value="<?= sanitize($to_date) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

        </div>


        <!-- Generate -->
        <button
            type="submit"
            name="generate"
            value="1"
            class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">

            <i class="fas fa-file-alt mr-2"></i>
            Generate Statement

        </button>

    </form>

</div>


<?php if (!empty($statementData)): ?>

    <!--
    |--------------------------------------------------------------------------
    | Statement
    |--------------------------------------------------------------------------
    -->
    <div class="bg-white rounded-lg shadow p-6 mb-6 print-area">

        <!-- Statement Header -->
        <div class="flex justify-between items-start mb-6">

            <div>

                <h2 class="text-2xl font-bold text-gray-800 mb-2">
                    CUSTOMER STATEMENT
                </h2>

                <p class="text-gray-500">

                    Period:

                    <?= date(
                        'M d, Y',
                        strtotime($from_date)
                    ) ?>

                    -

                    <?= date(
                        'M d, Y',
                        strtotime($to_date)
                    ) ?>

                </p>

            </div>


            <div class="text-right">

                <p class="font-semibold text-gray-800">
                    <?= sanitize($companyName ?? 'Your Company') ?>
                </p>

                <p class="text-sm text-gray-500">
                    <?= sanitize($companyTaxNumber ?? '') ?>
                </p>

            </div>

        </div>


        <!--
        |--------------------------------------------------------------------------
        | Client Information
        |--------------------------------------------------------------------------
        -->
        <div class="border rounded p-4 mb-6">

            <h3 class="text-sm font-medium text-gray-500 uppercase mb-2">
                Customer Details
            </h3>

            <div class="grid grid-cols-2 gap-4">

                <div>

                    <p class="font-semibold text-gray-800">
                        <?= sanitize(
                            $statementData['client']['company_name']
                        ) ?>
                    </p>

                    <p class="text-sm text-gray-600">
                        <?= nl2br(
                            sanitize(
                                $statementData['client']['address'] ?? ''
                            )
                        ) ?>
                    </p>

                </div>


                <div class="text-right">

                    <p class="text-sm text-gray-600">
                        Client Code:
                        <?= sanitize(
                            $statementData['client']['client_code']
                        ) ?>
                    </p>

                    <p class="text-sm text-gray-600">
                        Email:
                        <?= sanitize(
                            $statementData['client']['email'] ?? ''
                        ) ?>
                    </p>

                    <p class="text-sm text-gray-600">
                        Phone:
                        <?= sanitize(
                            $statementData['client']['phone'] ?? ''
                        ) ?>
                    </p>

                </div>

            </div>

        </div>


        <!--
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        -->
        <div class="grid grid-cols-4 gap-4 mb-6">

            <!-- Opening -->
            <div class="bg-gray-50 rounded p-3 text-center">

                <p class="text-xs text-gray-500 uppercase">
                    Opening Balance
                </p>

                <p class="text-lg font-bold
                    <?= $statementData['opening_balance'] >= 0
                        ? 'text-gray-800'
                        : 'text-green-600' ?>">

                    <?= formatMoney(
                        $statementData['opening_balance']
                    ) ?>

                </p>

            </div>


            <!-- Invoices -->
            <div class="bg-blue-50 rounded p-3 text-center">

                <p class="text-xs text-gray-500 uppercase">
                    Invoices
                </p>

                <p class="text-lg font-bold text-blue-600">

                    <?= formatMoney(
                        $statementData['invoice_total']
                    ) ?>

                </p>

            </div>


            <!-- Payments -->
            <div class="bg-green-50 rounded p-3 text-center">

                <p class="text-xs text-gray-500 uppercase">
                    Payments
                </p>

                <p class="text-lg font-bold text-green-600">

                    <?= formatMoney(
                        $statementData['receipt_total']
                    ) ?>

                </p>

            </div>


            <!-- Credit Notes -->
            <div class="bg-red-50 rounded p-3 text-center">

                <p class="text-xs text-gray-500 uppercase">
                    Credit Notes
                </p>

                <p class="text-lg font-bold text-red-600">

                    <?= formatMoney(
                        $statementData['cn_total']
                    ) ?>

                </p>

            </div>

        </div>


        <!--
        |--------------------------------------------------------------------------
        | Balance Summary
        |--------------------------------------------------------------------------
        -->
        <div class="flex justify-between items-center bg-gray-100 rounded p-4 mb-6">

            <div>

                <p class="text-sm text-gray-600">

                    Closing Balance:

                    <span
                        class="font-bold text-xl
                        <?= $statementData['closing_balance'] >= 0
                            ? 'text-red-600'
                            : 'text-green-600' ?>">

                        <?= formatMoney(
                            abs(
                                $statementData['closing_balance']
                            )
                        ) ?>

                    </span>

                    <span class="text-sm text-gray-500">

                        (
                        <?= $statementData['closing_balance'] >= 0
                            ? 'Outstanding'
                            : 'Credit' ?>
                        )

                    </span>

                </p>

            </div>


            <div>

                <p class="text-sm text-gray-600">

                    Total Outstanding:

                    <span class="font-bold text-lg text-red-600">

                        <?= formatMoney(
                            max(
                                0,
                                $statementData['closing_balance']
                            )
                        ) ?>

                    </span>

                </p>

            </div>

        </div>


        <!--
        |--------------------------------------------------------------------------
        | Transaction Details
        |--------------------------------------------------------------------------
        -->
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            Transaction Details
        </h3>


        <div class="overflow-x-auto">

            <table class="w-full">

                <thead class="bg-gray-50 border-y">

                    <tr>

                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            Date
                        </th>

                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            Type
                        </th>

                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            Reference
                        </th>

                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            Description
                        </th>

                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                            Debit (Invoice)
                        </th>

                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                            Credit (Payment)
                        </th>

                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                            Balance
                        </th>

                    </tr>

                </thead>


                <tbody class="divide-y divide-gray-200">

                    <!-- Opening Balance -->
                    <tr class="bg-gray-50">

                        <td
                            colspan="6"
                            class="px-4 py-2 text-sm font-medium text-gray-600">

                            Opening Balance

                        </td>

                        <td
                            class="px-4 py-2 text-sm text-right font-mono font-medium
                            <?= $statementData['opening_balance'] >= 0
                                ? 'text-gray-800'
                                : 'text-green-600' ?>">

                            <?= formatMoney(
                                $statementData['opening_balance']
                            ) ?>

                        </td>

                    </tr>


                    <?php
                    $runningBalance =
                        $statementData['opening_balance'];
                    ?>


                    <?php foreach ($statementData['entries'] as $entry): ?>

                        <?php

                        $runningBalance +=
                            $entry['debit'] -
                            $entry['credit'];

                        $rowClass = '';

                        if ($entry['type'] === 'Receipt') {
                            $rowClass = 'bg-green-50';
                        } elseif ($entry['type'] === 'Credit Note') {
                            $rowClass = 'bg-red-50';
                        }

                        ?>

                        <tr class="<?= $rowClass ?>">

                            <!-- Date -->
                            <td class="px-4 py-2 text-sm text-gray-600 whitespace-nowrap">

                                <?= date(
                                    'M d, Y',
                                    strtotime($entry['date'])
                                ) ?>

                            </td>


                            <!-- Type -->
                            <td class="px-4 py-2 text-sm whitespace-nowrap">

                                <span
                                    class="px-2 py-0.5 text-xs rounded font-medium
                                    <?= $entry['type'] === 'Invoice'
                                        ? 'bg-blue-100 text-blue-800'
                                        : '' ?>

                                    <?= $entry['type'] === 'Receipt'
                                        ? 'bg-green-100 text-green-800'
                                        : '' ?>

                                    <?= $entry['type'] === 'Credit Note'
                                        ? 'bg-red-100 text-red-800'
                                        : '' ?>">

                                    <?= sanitize(
                                        $entry['type']
                                    ) ?>

                                </span>

                            </td>


                            <!-- Reference -->
                            <td class="px-4 py-2 text-sm font-mono text-gray-800">

                                <?= sanitize(
                                    $entry['reference']
                                ) ?>

                            </td>


                            <!-- Description -->
                            <td class="px-4 py-2 text-sm text-gray-600">

                                <?= sanitize(
                                    $entry['description']
                                ) ?>

                            </td>


                            <!-- Debit -->
                            <td class="px-4 py-2 text-sm text-right font-mono text-gray-800">

                                <?= $entry['debit'] > 0
                                    ? formatMoney($entry['debit'])
                                    : '-' ?>

                            </td>


                            <!-- Credit -->
                            <td class="px-4 py-2 text-sm text-right font-mono text-green-600">

                                <?= $entry['credit'] > 0
                                    ? formatMoney($entry['credit'])
                                    : '-' ?>

                            </td>


                            <!-- Balance -->
                            <td
                                class="px-4 py-2 text-sm text-right font-mono font-medium
                                <?= $runningBalance >= 0
                                    ? 'text-gray-800'
                                    : 'text-green-600' ?>">

                                <?= formatMoney(
                                    $runningBalance
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>


                <!-- Footer -->
                <tfoot class="bg-gray-100 font-semibold">

                    <tr>

                        <td
                            colspan="6"
                            class="px-4 py-3 text-sm text-right text-gray-700">

                            Closing Balance

                        </td>

                        <td
                            class="px-4 py-3 text-sm text-right font-mono
                            <?= $statementData['closing_balance'] >= 0
                                ? 'text-red-600'
                                : 'text-green-600' ?>">

                            <?= formatMoney(
                                $statementData['closing_balance']
                            ) ?>

                        </td>

                    </tr>

                </tfoot>

            </table>

        </div>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Print CSS
    |--------------------------------------------------------------------------
    -->
    <style media="print">
        .erp-layout,
        .erp-main {
            margin: 0 !important;
            padding: 0 !important;
        }

        .print-area {
            box-shadow: none !important;
            border: none !important;
        }

        nav,
        .sidebar,
        .navbar,
        .erp-layout>nav,
        .erp-layout>div:first-child {
            display: none !important;
        }

        button,
        .bg-blue-600,
        .flex.justify-between.items-center.mb-6 {
            display: none !important;
        }

        body {
            background: white;
        }

        @page {
            margin: 15mm;
        }
    </style>


<?php elseif ($client_id > 0 && isset($_GET['generate'])): ?>

    <!-- No Transactions -->
    <div class="bg-white rounded-lg shadow p-6">

        <div class="text-center py-8 text-gray-500">

            <i class="fas fa-file-alt text-5xl mb-4 text-gray-300"></i>

            <p>
                No transactions found for the selected period.
            </p>

        </div>

    </div>

<?php endif; ?>


<!--
|--------------------------------------------------------------------------
| jQuery Validation
|--------------------------------------------------------------------------
-->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>


<script>
    $(document).ready(function() {

        /*
        |--------------------------------------------------------------------------
        | Custom Date Range Validation
        |--------------------------------------------------------------------------
        */
        $.validator.addMethod(
            'dateRangeValid',
            function(value, element) {

                const fromDate = $('#fromDate').val();
                const toDate = $('#toDate').val();

                /*
                 * If either date is empty,
                 * required validation will handle it.
                 */
                if (!fromDate || !toDate) {
                    return true;
                }

                return fromDate <= toDate;

            },
            'To date must be greater than or equal to From date.'
        );


        /*
        |--------------------------------------------------------------------------
        | Set To Date Minimum Date
        |--------------------------------------------------------------------------
        */
        function updateToDateMin() {

            const fromDate = $('#fromDate').val();

            if (fromDate) {

                $('#toDate').attr(
                    'min',
                    fromDate
                );

            } else {

                $('#toDate').removeAttr('min');

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Initial To Date Minimum
        |--------------------------------------------------------------------------
        */
        updateToDateMin();


        /*
        |--------------------------------------------------------------------------
        | From Date Change
        |--------------------------------------------------------------------------
        */
        $('#fromDate').on(
            'change input',
            function() {

                const fromDate = $(this).val();
                const toDate = $('#toDate').val();

                /*
                 * Set minimum To Date
                 */
                updateToDateMin();

                /*
                 * If current To Date is before From Date,
                 * clear To Date.
                 */
                if (
                    fromDate &&
                    toDate &&
                    toDate < fromDate
                ) {

                    $('#toDate').val('');

                }

                /*
                 * Remove previous validation error
                 */
                $('#toDate')
                    .removeClass('border-red-500');

                $('#toDate-error').remove();

                /*
                 * Revalidate To Date
                 */
                if ($('#customerStatementForm').data('validator')) {

                    $('#toDate').valid();

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | To Date Change
        |--------------------------------------------------------------------------
        */
        $('#toDate').on(
            'change input',
            function() {

                const fromDate = $('#fromDate').val();
                const toDate = $(this).val();

                if (
                    fromDate &&
                    toDate &&
                    fromDate > toDate
                ) {

                    $(this).addClass(
                        'border-red-500'
                    );

                } else {

                    $(this).removeClass(
                        'border-red-500'
                    );

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Client Change
        |--------------------------------------------------------------------------
        */
        $('#clientSelect').on(
            'change',
            function() {

                /*
                 * Revalidate client field
                 */
                if ($('#customerStatementForm').data('validator')) {

                    $(this).valid();

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Customer Statement Form Validation
        |--------------------------------------------------------------------------
        */
        $('#customerStatementForm').validate({

            /*
             * Validation message element
             */
            errorElement: 'span',

            /*
             * Validation message design
             */
            errorClass: 'text-red-500 text-sm mt-1 block',

            /*
             |--------------------------------------------------------------------------
             | IMPORTANT
             |--------------------------------------------------------------------------
             | Message will appear directly below the input/select.
             | Existing design will not change.
             */
            errorPlacement: function(error, element) {

                error.insertAfter(element);

            },


            /*
             |--------------------------------------------------------------------------
             | Highlight Invalid Field
             |--------------------------------------------------------------------------
             */
            highlight: function(element) {

                $(element).addClass(
                    'border-red-500'
                );

            },


            /*
             |--------------------------------------------------------------------------
             | Remove Highlight
             |--------------------------------------------------------------------------
             */
            unhighlight: function(element) {

                $(element).removeClass(
                    'border-red-500'
                );

            },


            /*
             |--------------------------------------------------------------------------
             | Validation Rules
             |--------------------------------------------------------------------------
             */
            rules: {

                client_id: {

                    required: true,
                    digits: true,
                    min: 1

                },


                from_date: {

                    required: true,
                    dateISO: true

                },


                to_date: {

                    required: true,
                    dateISO: true,
                    dateRangeValid: true

                }

            },


            /*
             |--------------------------------------------------------------------------
             | Validation Messages
             |--------------------------------------------------------------------------
             */
            messages: {

                client_id: {

                    required: 'Please select a client.',

                    digits: 'Invalid client.',

                    min: 'Please select a valid client.'

                },


                from_date: {

                    required: 'Please select the from date.',

                    dateISO: 'Please enter a valid from date.'

                },


                to_date: {

                    required: 'Please select the to date.',

                    dateISO: 'Please enter a valid to date.',

                    dateRangeValid: 'To date must be greater than or equal to From date.'

                }

            },


            /*
             |--------------------------------------------------------------------------
             | Submit Handler
             |--------------------------------------------------------------------------
             */
            submitHandler: function(form) {

                form.submit();

            }

        });

    });
</script>


<?php
require_once '../../../includes/footer.php';
?>