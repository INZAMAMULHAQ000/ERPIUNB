<?php

$pageTitle = 'Vendor Statements';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('vendor_statements.view')) {
    die('Access Denied');
}

$db = getDB();

$vendor_id = (int)($_GET['vendor_id'] ?? 0);

$date_from =
    $_GET['date_from']
    ?? date('Y-m-01');

$date_to =
    $_GET['date_to']
    ?? date('Y-m-d');


$vendors = $db->query("
    SELECT
        id,
        vendor_code,
        vendor_name
    FROM vendors
    WHERE status = 'active'
    ORDER BY vendor_name
")->fetchAll();


$statement = [];
$vendor = null;


/*
|--------------------------------------------------------------------------
| Get Vendor Statement
|--------------------------------------------------------------------------
*/

if ($vendor_id > 0) {

    $stmt = $db->prepare("
        SELECT
            id,
            vendor_code,
            vendor_name,
            address,
            email,
            phone
        FROM vendors
        WHERE id = ?
    ");

    $stmt->execute([
        $vendor_id
    ]);

    $vendor = $stmt->fetch();


    if ($vendor) {

        /*
        |--------------------------------------------------------------------------
        | Opening Balance
        |--------------------------------------------------------------------------
        |
        | Bills before date_from
        | minus
        | Payments before date_from
        |
        */

        $openBills = $db->prepare("
            SELECT COALESCE(SUM(total_amount), 0)
            FROM vendor_bills
            WHERE vendor_id = ?
              AND bill_date < ?
              AND status NOT IN ('cancelled', 'draft')
        ");

        $openBills->execute([
            $vendor_id,
            $date_from
        ]);

        $openBillTotal =
            (float)$openBills->fetchColumn();


        $openPayments = $db->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM vendor_payments
            WHERE vendor_id = ?
              AND payment_date < ?
        ");

        $openPayments->execute([
            $vendor_id,
            $date_from
        ]);

        $openPaymentTotal =
            (float)$openPayments->fetchColumn();


        $openingBalance =
            $openBillTotal -
            $openPaymentTotal;


        /*
        |--------------------------------------------------------------------------
        | Transactions
        |--------------------------------------------------------------------------
        */

        $transactions = [];


        /*
        |--------------------------------------------------------------------------
        | Vendor Bills
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            SELECT
                'Bill' AS trans_type,
                bill_number AS trans_no,
                bill_date AS trans_date,
                total_amount AS amount,
                0 AS payment_amount,
                status,
                bill_number AS description

            FROM vendor_bills

            WHERE vendor_id = ?
              AND bill_date BETWEEN ? AND ?
              AND status NOT IN ('cancelled', 'draft')
        ");

        $stmt->execute([
            $vendor_id,
            $date_from,
            $date_to
        ]);


        foreach ($stmt->fetchAll() as $r) {

            $r['amount'] =
                (float)$r['amount'];

            $transactions[] =
                $r;
        }


        /*
        |--------------------------------------------------------------------------
        | Vendor Payments
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            SELECT
                'Payment' AS trans_type,
                payment_number AS trans_no,
                payment_date AS trans_date,
                0 AS amount,
                amount AS payment_amount,
                'completed' AS status,
                remarks AS description

            FROM vendor_payments

            WHERE vendor_id = ?
              AND payment_date BETWEEN ? AND ?
        ");

        $stmt->execute([
            $vendor_id,
            $date_from,
            $date_to
        ]);


        foreach ($stmt->fetchAll() as $r) {

            $r['payment_amount'] =
                (float)$r['payment_amount'];

            $transactions[] =
                $r;
        }


        /*
        |--------------------------------------------------------------------------
        | Debit Notes
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            SELECT
                'Debit Note' AS trans_type,
                debit_note_number AS trans_no,
                debit_note_date AS trans_date,
                -total_amount AS amount,
                0 AS payment_amount,
                status,
                reason AS description

            FROM debit_notes

            WHERE vendor_id = ?
              AND debit_note_date BETWEEN ? AND ?
              AND status IN ('issued', 'applied')
        ");

        $stmt->execute([
            $vendor_id,
            $date_from,
            $date_to
        ]);


        foreach ($stmt->fetchAll() as $r) {

            $r['amount'] =
                (float)$r['amount'];

            $transactions[] =
                $r;
        }


        /*
        |--------------------------------------------------------------------------
        | Sort Transactions By Date
        |--------------------------------------------------------------------------
        */

        usort(
            $transactions,
            function ($a, $b) {

                return strcmp(
                    $a['trans_date'],
                    $b['trans_date']
                );
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Running Balance
        |--------------------------------------------------------------------------
        */

        $runningBalance =
            $openingBalance;


        foreach ($transactions as &$t) {

            $runningBalance +=
                $t['amount'] -
                $t['payment_amount'];

            $t['balance'] =
                $runningBalance;
        }

        unset($t);


        $closingBalance =
            $runningBalance;
    }
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
            Vendor Statements
        </h1>

        <p class="text-gray-600">
            View vendor account activity for a date range
        </p>

    </div>


    <a
        href="<?= url('modules/finance/index.php') ?>"
        class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
        <i class="fas fa-arrow-left mr-2"></i>
        Back to Finance
    </a>

</div>


<!--
|--------------------------------------------------------------------------
| Filter Form
|--------------------------------------------------------------------------
-->

<div class="bg-white rounded-lg shadow mb-6">

    <form
        method="GET"
        id="vendorStatementForm"
        class="p-4 flex flex-wrap gap-4 items-end"
        novalidate>


        <!--
        |--------------------------------------------------------------------------
        | Vendor
        |--------------------------------------------------------------------------
        -->

        <div class="w-64">

            <label
                for="vendorSelect"
                class="block text-sm font-medium text-gray-700 mb-1">
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
                        <?= $vendor_id == $v['id']
                            ? 'selected'
                            : ''
                        ?>>
                        <?= sanitize($v['vendor_name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>


            <!-- Validation Message -->

            <div
                class="validation-error"
                data-error-for="vendor_id"></div>

        </div>


        <!--
        |--------------------------------------------------------------------------
        | From Date
        |--------------------------------------------------------------------------
        -->

        <div class="w-40">

            <label
                for="dateFrom"
                class="block text-sm font-medium text-gray-700 mb-1">
                From
                <span class="text-red-500">*</span>
            </label>


            <input
                type="date"
                name="date_from"
                id="dateFrom"
                value="<?= sanitize($date_from) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">


            <!-- Validation Message -->

            <div
                class="validation-error"
                data-error-for="date_from"></div>

        </div>


        <!--
        |--------------------------------------------------------------------------
        | To Date
        |--------------------------------------------------------------------------
        -->

        <div class="w-40">

            <label
                for="dateTo"
                class="block text-sm font-medium text-gray-700 mb-1">
                To
                <span class="text-red-500">*</span>
            </label>


            <input
                type="date"
                name="date_to"
                id="dateTo"
                value="<?= sanitize($date_to) ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">


            <!-- Validation Message -->

            <div
                class="validation-error"
                data-error-for="date_to"></div>

        </div>


        <!--
        |--------------------------------------------------------------------------
        | Generate
        |--------------------------------------------------------------------------
        -->

        <button
            type="submit"
            class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
            <i class="fas fa-search mr-2"></i>
            Generate
        </button>

    </form>

</div>


<!--
|--------------------------------------------------------------------------
| Vendor Statement
|--------------------------------------------------------------------------
-->

<?php if ($vendor): ?>

    <div class="bg-white rounded-lg shadow p-6 mb-6">


        <!--
    |--------------------------------------------------------------------------
    | Vendor Header
    |--------------------------------------------------------------------------
    -->

        <div class="flex justify-between items-start mb-4">

            <div>

                <h3 class="text-lg font-semibold text-gray-800">
                    <?= sanitize($vendor['vendor_name']) ?>
                </h3>


                <p class="text-sm text-gray-500">
                    <?= sanitize($vendor['vendor_code']) ?>
                </p>


                <?php if ($vendor['address']): ?>

                    <p class="text-sm text-gray-500">
                        <?= sanitize($vendor['address']) ?>
                    </p>

                <?php endif; ?>

            </div>


            <div class="text-right">

                <p class="text-sm text-gray-500">

                    <?= date(
                        'M d, Y',
                        strtotime($date_from)
                    ) ?>

                    –

                    <?= date(
                        'M d, Y',
                        strtotime($date_to)
                    ) ?>

                </p>

            </div>

        </div>


        <!--
    |--------------------------------------------------------------------------
    | Opening / Closing Balance
    |--------------------------------------------------------------------------
    -->

        <div class="grid grid-cols-2 gap-4 mb-4">


            <!-- Opening -->

            <div class="bg-gray-50 p-3 rounded">

                <p class="text-xs text-gray-500">
                    Opening Balance
                </p>


                <p
                    class="text-lg font-bold <?= $openingBalance >= 0
                                                    ? 'text-gray-800'
                                                    : 'text-green-600'
                                                ?>">
                    <?= formatMoney($openingBalance) ?>
                </p>

            </div>


            <!-- Closing -->

            <div class="bg-gray-50 p-3 rounded">

                <p class="text-xs text-gray-500">
                    Closing Balance
                </p>


                <p
                    class="text-lg font-bold <?= $closingBalance >= 0
                                                    ? 'text-red-600'
                                                    : 'text-green-600'
                                                ?>">
                    <?= formatMoney($closingBalance) ?>
                </p>

            </div>

        </div>


        <!--
    |--------------------------------------------------------------------------
    | Transactions Table
    |--------------------------------------------------------------------------
    -->

        <div class="overflow-x-auto">

            <table class="w-full">

                <thead class="bg-gray-50">

                    <tr>

                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            Date
                        </th>

                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            Type
                        </th>

                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            Reference
                        </th>

                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                            Debit
                        </th>

                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                            Credit
                        </th>

                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                            Balance
                        </th>

                    </tr>

                </thead>


                <tbody class="divide-y divide-gray-200">


                    <!--
                |--------------------------------------------------------------------------
                | Opening Balance Row
                |--------------------------------------------------------------------------
                -->

                    <tr class="bg-gray-50">

                        <td
                            class="px-4 py-2 text-sm"
                            colspan="5">
                            Opening Balance
                        </td>


                        <td
                            class="px-4 py-2 text-sm text-right font-mono font-medium">
                            <?= formatMoney($openingBalance) ?>
                        </td>

                    </tr>


                    <?php if (empty($transactions)): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="px-4 py-6 text-center text-gray-500">
                                No transactions in this period.
                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach ($transactions as $t): ?>

                            <tr class="hover:bg-gray-50">


                                <!-- Date -->

                                <td
                                    class="px-4 py-2 text-sm whitespace-nowrap">
                                    <?= date(
                                        'M d, Y',
                                        strtotime($t['trans_date'])
                                    ) ?>
                                </td>


                                <!-- Type -->

                                <td class="px-4 py-2 text-sm">

                                    <span
                                        class="px-2 py-0.5 text-xs rounded <?= $t['trans_type'] === 'Bill'
                                                                                ? 'bg-red-100 text-red-800'
                                                                                : (
                                                                                    $t['trans_type'] === 'Payment'
                                                                                    ? 'bg-green-100 text-green-800'
                                                                                    : 'bg-yellow-100 text-yellow-800'
                                                                                )
                                                                            ?>">
                                        <?= $t['trans_type'] ?>
                                    </span>

                                </td>


                                <!-- Reference -->

                                <td
                                    class="px-4 py-2 text-sm font-mono">
                                    <?= sanitize($t['trans_no']) ?>
                                </td>


                                <!-- Debit -->

                                <td
                                    class="px-4 py-2 text-sm text-right font-mono">

                                    <?=
                                    $t['amount'] > 0
                                        ? formatMoney($t['amount'])
                                        : '--'
                                    ?>

                                </td>


                                <!-- Credit -->

                                <td
                                    class="px-4 py-2 text-sm text-right font-mono">

                                    <?=
                                    $t['payment_amount'] > 0
                                        ? formatMoney(
                                            $t['payment_amount']
                                        )
                                        : (
                                            $t['amount'] < 0
                                            ? formatMoney(
                                                -$t['amount']
                                            )
                                            : '--'
                                        )
                                    ?>

                                </td>


                                <!-- Balance -->

                                <td
                                    class="px-4 py-2 text-sm text-right font-mono font-medium">
                                    <?= formatMoney($t['balance']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>


                    <!--
                |--------------------------------------------------------------------------
                | Closing Balance
                |--------------------------------------------------------------------------
                -->

                    <tr class="bg-gray-100 font-bold">

                        <td
                            class="px-4 py-2 text-sm"
                            colspan="5">
                            Closing Balance
                        </td>


                        <td
                            class="px-4 py-2 text-sm text-right font-mono">
                            <?= formatMoney($closingBalance) ?>
                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>


<?php elseif ($_GET && $vendor_id > 0): ?>

    <div
        class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
        Vendor not found.
    </div>

<?php endif; ?>


<!--
|--------------------------------------------------------------------------
| jQuery
|--------------------------------------------------------------------------
-->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>


<!--
|--------------------------------------------------------------------------
| jQuery Validate
|--------------------------------------------------------------------------
-->

<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>


<script>
    $(document).ready(function() {


        /*
        |--------------------------------------------------------------------------
        | Vendor Statement Validation
        |--------------------------------------------------------------------------
        */

        $('#vendorStatementForm').validate({

            /*
            |--------------------------------------------------------------------------
            | Do not use browser native validation
            |--------------------------------------------------------------------------
            */

            ignore: [],


            /*
            |--------------------------------------------------------------------------
            | Error Element
            |--------------------------------------------------------------------------
            */

            errorElement: 'p',

            errorClass: 'vendor-statement-validation-error',


            /*
            |--------------------------------------------------------------------------
            | Rules
            |--------------------------------------------------------------------------
            */

            rules: {

                vendor_id: {

                    required: true

                },


                date_from: {

                    required: true

                },


                date_to: {

                    required: true,

                    date: true,

                    greaterThanOrEqualToFrom: true

                }

            },


            /*
            |--------------------------------------------------------------------------
            | Messages
            |--------------------------------------------------------------------------
            */

            messages: {

                vendor_id: {

                    required: 'Please select a vendor.'

                },


                date_from: {

                    required: 'Please select from date.'

                },


                date_to: {

                    required: 'Please select to date.',

                    date: 'Please enter a valid date.',

                    greaterThanOrEqualToFrom: 'To date must be greater than or equal to From date.'

                }

            },


            /*
            |--------------------------------------------------------------------------
            | Error Placement
            |--------------------------------------------------------------------------
            |
            | Error will be displayed below the
            | corresponding select/input.
            |
            */

            errorPlacement: function(error, element) {

                const fieldName = element.attr('name');

                const errorContainer = $(
                    '.validation-error[data-error-for="' + fieldName + '"]'
                );

                if (errorContainer.length) {

                    error
                        .appendTo(errorContainer)
                        .addClass('vendor-statement-validation-error');

                } else {

                    error.insertAfter(element);

                }
            },


            /*
            |--------------------------------------------------------------------------
            | Highlight Invalid Field
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Invalid Form
            |--------------------------------------------------------------------------
            */

            invalidHandler: function(
                event,
                validator
            ) {

                if (
                    validator.errorList.length
                ) {

                    const firstElement =
                        validator
                        .errorList[0]
                        .element;


                    if (
                        firstElement
                    ) {

                        $('html, body').animate({

                                scrollTop: $(firstElement)
                                    .offset()
                                    .top - 120

                            },
                            300
                        );

                    }

                }

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
        | Vendor Change
        |--------------------------------------------------------------------------
        */

        $('#vendorSelect').on(
            'change',
            function() {

                $(this).valid();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | From Date Change
        |--------------------------------------------------------------------------
        */

        $('#dateFrom').on(
            'change',
            function() {

                $(this).valid();

                $('#dateTo').valid();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | To Date Change
        |--------------------------------------------------------------------------
        */

        $('#dateTo').on(
            'change',
            function() {

                $(this).valid();

            }
        );

    });



    /*
    |--------------------------------------------------------------------------
    | Custom Date Validation
    |--------------------------------------------------------------------------
    |
    | To Date >= From Date
    |
    */

    $.validator.addMethod(
        'greaterThanOrEqualToFrom',
        function(
            value,
            element
        ) {

            const fromDate =
                $('#dateFrom').val();


            const toDate =
                value;


            if (
                !fromDate ||
                !toDate
            ) {

                return true;

            }


            return toDate >= fromDate;

        },
        'To date must be greater than or equal to From date.'
    );

    $(document).ready(function() {

        const fromDate = document.getElementById('dateFrom');
        const toDate = document.getElementById('dateTo');

        if (!fromDate || !toDate) {
            return;
        }

        function updateToDateLimit() {

            const fromValue = fromDate.value;

            if (fromValue) {

                // To Date can start only from From Date
                toDate.min = fromValue;

                // If To Date is before From Date, clear it
                if (toDate.value && toDate.value < fromValue) {
                    toDate.value = '';
                }

            } else {

                toDate.removeAttribute('min');
            }
        }

        // Initial load
        updateToDateLimit();

        // When From Date changes
        fromDate.addEventListener('change', function() {

            updateToDateLimit();

            if ($('#vendorStatementForm').length) {
                $('#dateFrom').valid();
                $('#dateTo').valid();
            }
        });

    });
</script>


<style>
    /*
|--------------------------------------------------------------------------
| Validation Container
|--------------------------------------------------------------------------
*/

    .validation-error {

        display: block;
        width: 100%;
        height: 20px;
        margin-top: 2px;

    }


    /*
|--------------------------------------------------------------------------
| Validation Message
|--------------------------------------------------------------------------
*/

    .vendor-statement-validation-error {
        display: block !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;

        color: #ef4444 !important;
        font-size: 13px !important;
        line-height: 18px !important;

        white-space: normal;
    }


    /*
|--------------------------------------------------------------------------
| Invalid Border
|--------------------------------------------------------------------------
*/

    .border-red-500 {

        border-color: #ef4444 !important;

    }
</style>


<?php require_once '../../../includes/footer.php'; ?>