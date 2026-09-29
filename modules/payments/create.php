<?php
$pageTitle = 'Record Payment';

require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('payments.create')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$preSelectedInvoice = $_GET['invoice_id'] ?? '';

/*
|--------------------------------------------------------------------------
| Allowed payment methods
|--------------------------------------------------------------------------
*/
$allowedPaymentMethods = [
    'bank_transfer',
    'cash',
    'upi',
    'credit_card',
    'paypal'
];

/*
|--------------------------------------------------------------------------
| Load invoices
|--------------------------------------------------------------------------
*/
$invoices = $db->query("
    SELECT
        i.id,
        i.invoice_number,
        i.total_amount,
        i.paid_amount,
        i.balance_amount,
        c.company_name
    FROM invoices i
    LEFT JOIN clients c ON i.client_id = c.id
    WHERE i.status IN ('sent', 'partially_paid', 'overdue')
      AND i.balance_amount > 0
    ORDER BY i.invoice_date DESC
")->fetchAll();

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
        $errors['csrf_token'] = 'Invalid request. Please refresh the page and try again.';
    }

    $data = [
        'invoice_id'       => trim($_POST['invoice_id'] ?? ''),
        'payment_date'     => trim($_POST['payment_date'] ?? ''),
        'amount'           => trim($_POST['amount'] ?? ''),
        'payment_method'   => trim($_POST['payment_method'] ?? ''),
        'reference_number' => trim($_POST['reference_number'] ?? ''),
        'notes'            => trim($_POST['notes'] ?? '')
    ];

    /*
    |--------------------------------------------------------------------------
    | Basic validation
    |--------------------------------------------------------------------------
    */
    $validationErrors = validate($data, [
        'invoice_id'   => 'required',
        'payment_date' => 'required',
        'amount'      => 'required'
    ]);

    if (!empty($validationErrors)) {
        $errors = array_merge($errors, $validationErrors);
    }

    /*
    |--------------------------------------------------------------------------
    | Invoice ID validation
    |--------------------------------------------------------------------------
    */
    if ($data['invoice_id'] !== '' && !ctype_digit($data['invoice_id'])) {
        $errors['invoice_id'] = 'Please select a valid invoice.';
    }

    /*
    |--------------------------------------------------------------------------
    | Payment date validation
    |--------------------------------------------------------------------------
    */
    if ($data['payment_date'] !== '') {

        $dateObject = DateTime::createFromFormat(
            'Y-m-d',
            $data['payment_date']
        );

        $dateErrors = DateTime::getLastErrors();

        if (
            !$dateObject ||
            ($dateErrors !== false &&
                ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        ) {
            $errors['payment_date'] = 'Please enter a valid payment date.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Amount validation
    |--------------------------------------------------------------------------
    */
    $amount = 0;

    if ($data['amount'] !== '') {

        if (!is_numeric($data['amount'])) {

            $errors['amount'] = 'Amount must be a valid number.';
        } else {

            $amount = (float)$data['amount'];

            if ($amount <= 0) {
                $errors['amount'] = 'Amount must be greater than 0.';
            }

            /*
            | Allow maximum 2 decimal places
            */
            if (preg_match('/^\d+(?:\.\d{3,})$/', $data['amount'])) {
                $errors['amount'] = 'Amount can have a maximum of 2 decimal places.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Payment method validation
    |--------------------------------------------------------------------------
    */
    if ($data['payment_method'] === '') {

        $errors['payment_method'] = 'Please select a payment method.';
    } elseif (!in_array($data['payment_method'], $allowedPaymentMethods, true)) {

        $errors['payment_method'] = 'Invalid payment method selected.';
    }

    /*
    |--------------------------------------------------------------------------
    | Reference validation
    |--------------------------------------------------------------------------
    */
    if ($data['reference_number'] !== '') {

        if (mb_strlen($data['reference_number']) > 100) {

            $errors['reference_number'] =
                'Reference number cannot exceed 100 characters.';
        } elseif (!preg_match(
            '/^[A-Za-z0-9\s\-_\/.#()]+$/',
            $data['reference_number']
        )) {

            $errors['reference_number'] =
                'Reference number contains invalid characters.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Notes validation
    |--------------------------------------------------------------------------
    */
    if (mb_strlen($data['notes']) > 500) {
        $errors['notes'] = 'Notes cannot exceed 500 characters.';
    }

    /*
    |--------------------------------------------------------------------------
    | Get invoice and validate balance
    |--------------------------------------------------------------------------
    */
    $invoice = null;

    if (
        empty($errors['invoice_id']) &&
        $data['invoice_id'] !== ''
    ) {

        $invoiceStmt = $db->prepare("
            SELECT
                id,
                invoice_number,
                total_amount,
                paid_amount,
                balance_amount,
                status
            FROM invoices
            WHERE id = ?
            LIMIT 1
        ");

        $invoiceStmt->execute([
            (int)$data['invoice_id']
        ]);

        $invoice = $invoiceStmt->fetch();

        if (!$invoice) {

            $errors['invoice_id'] = 'Selected invoice was not found.';
        } elseif (
            !in_array(
                $invoice['status'],
                ['sent', 'partially_paid', 'overdue'],
                true
            )
        ) {

            $errors['invoice_id'] =
                'Payment cannot be recorded for this invoice.';
        } elseif ((float)$invoice['balance_amount'] <= 0) {

            $errors['invoice_id'] =
                'This invoice has no outstanding balance.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Amount cannot exceed invoice balance
    |--------------------------------------------------------------------------
    */
    if (
        empty($errors['amount']) &&
        $invoice &&
        $amount > (float)$invoice['balance_amount']
    ) {

        $errors['amount'] =
            'Payment amount cannot exceed the invoice balance of ' .
            formatMoney($invoice['balance_amount']) . '.';
    }

    /*
    |--------------------------------------------------------------------------
    | Stop if validation failed
    |--------------------------------------------------------------------------
    */
    if (!empty($errors)) {

        saveFormState($errors);

        to('modules/payments/create.php');

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Save payment
    |--------------------------------------------------------------------------
    */
    try {

        $db->beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | Re-read invoice inside transaction
        |--------------------------------------------------------------------------
        | This prevents using an old balance if another payment was
        | recorded after the page was opened.
        |--------------------------------------------------------------------------
        */
        $invoiceStmt = $db->prepare("
            SELECT
                id,
                invoice_number,
                total_amount,
                paid_amount,
                balance_amount,
                status
            FROM invoices
            WHERE id = ?
            FOR UPDATE
        ");

        $invoiceStmt->execute([
            (int)$data['invoice_id']
        ]);

        $invoice = $invoiceStmt->fetch();

        if (!$invoice) {
            throw new Exception('Invoice not found.');
        }

        $invoiceBalance = (float)$invoice['balance_amount'];

        if ($invoiceBalance <= 0) {
            throw new Exception('This invoice has no outstanding balance.');
        }

        if ($amount > $invoiceBalance) {
            throw new Exception(
                'Payment amount cannot exceed the invoice balance.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Insert payment
        |--------------------------------------------------------------------------
        */
        $stmt = $db->prepare("
            INSERT INTO payments
            (
                invoice_id,
                payment_date,
                amount,
                payment_method,
                reference_number,
                notes,
                received_by
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            (int)$data['invoice_id'],
            $data['payment_date'],
            number_format($amount, 2, '.', ''),
            $data['payment_method'],
            $data['reference_number'] !== ''
                ? $data['reference_number']
                : null,
            $data['notes'] !== ''
                ? $data['notes']
                : null,
            $_SESSION['user_id']
        ]);

        /*
        |--------------------------------------------------------------------------
        | Update invoice
        |--------------------------------------------------------------------------
        */
        $newPaid = (float)$invoice['paid_amount'] + $amount;

        $newBalance = max(
            0,
            (float)$invoice['total_amount'] - $newPaid
        );

        $newStatus = $newBalance <= 0
            ? 'paid'
            : 'partially_paid';

        $updateStmt = $db->prepare("
            UPDATE invoices
            SET
                paid_amount = ?,
                balance_amount = ?,
                status = ?
            WHERE id = ?
        ");

        $updateStmt->execute([
            number_format($newPaid, 2, '.', ''),
            number_format($newBalance, 2, '.', ''),
            $newStatus,
            (int)$data['invoice_id']
        ]);

        /*
        |--------------------------------------------------------------------------
        | Audit
        |--------------------------------------------------------------------------
        */
        logAudit(
            $_SESSION['user_id'],
            'create_payment',
            'payments'
        );

        $db->commit();

        flash(
            'success',
            'Payment recorded successfully.'
        );

        to('modules/payments/index.php');

        exit;
    } catch (Throwable $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $errors['db'] =
            $e->getMessage();

        saveFormState($errors);

        to('modules/payments/create.php');

        exit;
    }
}
?>

<style>
    /* =========================================================
   Payment Form Validation
   ========================================================= */

    #paymentForm label.error {
        display: block !important;
        width: 100%;
        margin-top: 4px !important;
        margin-bottom: 0 !important;
        color: #dc2626;
        font-size: 12px;
        line-height: 16px;
        font-weight: 400;
    }

    #paymentForm input.error,
    #paymentForm select.error,
    #paymentForm textarea.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    #paymentForm input.valid,
    #paymentForm select.valid,
    #paymentForm textarea.valid {
        border-color: #d1d5db !important;
        box-shadow: none !important;
    }

    /* Select custom arrow */
    #paymentForm select {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        background-image: none !important;
    }

    /* Field wrapper */
    .form-field {
        min-height: 76px;
    }
</style>

<div class="mb-5">
    <a
        href="<?= url('modules/payments/index.php') ?>"
        class="text-sm text-blue-600 hover:text-blue-800">

        <i class="fas fa-arrow-left mr-1"></i>
        Back to Payments
    </a>
</div>

<div class="bg-white border border-gray-200 shadow-sm w-full">

    <!-- Header -->
    <div class="px-5 py-4 border-b border-gray-200 bg-gray-50">
        <h2 class="text-lg font-semibold text-gray-800">
            Record Payment
        </h2>

        <p class="text-xs text-gray-500 mt-1">
            Record a payment received against an outstanding invoice.
        </p>
    </div>

    <!-- Form -->
    <div class="p-5">

        <?php if (!empty($errors)): ?>

            <div class="mb-5 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">

                <div class="flex items-start gap-2">

                    <i class="fas fa-exclamation-circle mt-0.5"></i>

                    <div>
                        <div class="font-semibold mb-1">
                            Please correct the following errors:
                        </div>

                        <ul class="list-disc list-inside space-y-1">

                            <?php foreach ($errors as $e): ?>

                                <li>
                                    <?= sanitize($e) ?>
                                </li>

                            <?php endforeach; ?>

                        </ul>
                    </div>

                </div>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            id="paymentForm"
            novalidate>

            <!-- CSRF -->
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">


            <!-- Invoice -->
            <div class="form-field mb-4">

                <label
                    for="invoiceSelect"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Invoice
                    <span class="text-red-600">*</span>

                </label>

                <div class="relative">

                    <select
                        name="invoice_id"
                        id="invoiceSelect"
                        class="w-full h-10 px-3 pr-9 border border-gray-300 bg-white text-sm text-gray-700 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">

                        <option value="">
                            Select Invoice
                        </option>

                        <?php foreach ($invoices as $inv): ?>

                            <option
                                value="<?= (int)$inv['id'] ?>"
                                data-balance="<?= htmlspecialchars($inv['balance_amount'], ENT_QUOTES, 'UTF-8') ?>"
                                <?= (string)$preSelectedInvoice === (string)$inv['id'] ? 'selected' : '' ?>>

                                <?= sanitize($inv['invoice_number']) ?>
                                -
                                <?= sanitize($inv['company_name'] ?? '-') ?>
                                (Balance:
                                <?= formatMoney($inv['balance_amount']) ?>)

                            </option>

                        <?php endforeach;  ?>

                    </select>

                </div>

            </div>


            <!-- Date + Amount -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <!-- Payment Date -->
                <div class="form-field">

                    <label
                        for="payment_date"
                        class="block text-sm font-medium text-gray-700 mb-1">

                        Payment Date
                        <span class="text-red-600">*</span>

                    </label>

                    <input
                        type="date"
                        name="payment_date"
                        id="payment_date"
                        value="<?= old('payment_date', date('Y-m-d')) ?>"
                        class="w-full h-10 px-3 border border-gray-300 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">

                </div>


                <!-- Amount -->
                <div class="form-field">

                    <label
                        for="amount"
                        class="block text-sm font-medium text-gray-700 mb-1">

                        Amount
                        <span class="text-red-600">*</span>

                    </label>

                    <input
                        type="number"
                        name="amount"
                        id="amount"
                        min="0.01"
                        step="0.01"
                        value="<?= old('amount') ?>"
                        placeholder="0.00"
                        class="w-full h-10 px-3 border border-gray-300 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">

                </div>

            </div>


            <!-- Payment Method + Reference -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-1">

                <!-- Payment Method -->
                <div class="form-field">

                    <label
                        for="payment_method"
                        class="block text-sm font-medium text-gray-700 mb-1">

                        Payment Method
                        <span class="text-red-600">*</span>

                    </label>

                    <div class="relative">

                        <select
                            name="payment_method"
                            id="payment_method"
                            class="w-full h-10 px-3 pr-9 border border-gray-300 bg-white text-sm text-gray-700 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">

                            <option value="">
                                Select Payment Method
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
                                value="upi"
                                <?= old('payment_method') === 'upi' ? 'selected' : '' ?>>
                                UPI
                            </option>

                            <option
                                value="credit_card"
                                <?= old('payment_method') === 'credit_card' ? 'selected' : '' ?>>
                                Credit Card
                            </option>

                            <option
                                value="paypal"
                                <?= old('payment_method') === 'paypal' ? 'selected' : '' ?>>
                                PayPal
                            </option>

                        </select>

                    </div>

                </div>


                <!-- Reference -->
                <div class="form-field">

                    <label
                        for="reference_number"
                        class="block text-sm font-medium text-gray-700 mb-1">

                        Reference Number
                    </label>

                    <input
                        type="text"
                        name="reference_number"
                        id="reference_number"
                        maxlength="100"
                        value="<?= old('reference_number') ?>"
                        placeholder="Transaction ID"
                        class="w-full h-10 px-3 border border-gray-300 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">

                </div>

            </div>


            <!-- Notes -->
            <div class="form-field mt-1">

                <label
                    for="notes"
                    class="block text-sm font-medium text-gray-700 mb-1">

                    Notes
                </label>

                <textarea
                    name="notes"
                    id="notes"
                    rows="3"
                    maxlength="500"
                    placeholder="Optional notes..."
                    class="w-full px-3 py-2 border border-gray-300 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"><?= old('notes') ?></textarea>

            </div>


            <!-- Buttons -->
            <div class="mt-5 pt-4 border-t border-gray-200 flex items-center gap-3">

                <button
                    type="submit"
                    class="h-10 px-5 bg-green-600 text-white text-sm font-medium hover:bg-green-700 transition">

                    <i class="fas fa-check mr-1"></i>
                    Record Payment
                </button>

                <a
                    href="<?= url('modules/payments/index.php') ?>"
                    class="h-10 inline-flex items-center px-5 border border-gray-300 text-sm text-gray-600 hover:bg-gray-50">

                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>
<style>
    #paymentForm label.error {
        display: block !important;
        width: 100%;
        margin-top: 5px !important;
        margin-bottom: 0 !important;
        color: #dc2626;
        font-size: 12px;
        line-height: 16px;
        font-weight: 400;
    }

    #paymentForm select.error {
        border-color: #ef4444 !important;
    }
</style>

<script>
    $(document).ready(function() {

        /*
        |--------------------------------------------------------------------------
        | Custom validation methods
        |--------------------------------------------------------------------------
        */

        $.validator.addMethod(
            'validPaymentDate',
            function(value, element) {

                if (!value) {
                    return true;
                }

                const date = new Date(value + 'T00:00:00');

                return !isNaN(date.getTime());

            },
            'Please enter a valid payment date.'
        );


        $.validator.addMethod(
            'validPaymentAmount',
            function(value, element) {

                if (!value) {
                    return true;
                }

                return /^\d+(?:\.\d{1,2})?$/.test(value) &&
                    parseFloat(value) > 0;

            },
            'Please enter a valid amount greater than 0.'
        );


        $.validator.addMethod(
            'validReference',
            function(value, element) {

                if (!value) {
                    return true;
                }

                return /^[A-Za-z0-9\s\-_\/.#()]+$/.test(value);

            },
            'Reference number contains invalid characters.'
        );


        /*
        |--------------------------------------------------------------------------
        | jQuery Validation
        |--------------------------------------------------------------------------
        */

        $('#paymentForm').validate({

            ignore: [],

            rules: {

                invoice_id: {
                    required: true
                },

                payment_date: {
                    required: true,
                    validPaymentDate: true
                },

                amount: {
                    required: true,
                    number: true,
                    min: 0.01,
                    validPaymentAmount: true
                },

                payment_method: {
                    required: true
                },

                reference_number: {
                    maxlength: 100,
                    validReference: true
                },

                notes: {
                    maxlength: 500
                }

            },

            messages: {

                invoice_id: {
                    required: 'Please select an invoice.'
                },

                payment_date: {
                    required: 'Please select the payment date.',
                    validPaymentDate: 'Please enter a valid payment date.'
                },

                amount: {
                    required: 'Please enter the payment amount.',
                    number: 'Please enter a valid amount.',
                    min: 'Amount must be greater than 0.',
                    validPaymentAmount: 'Amount must be greater than 0 with maximum 2 decimal places.'
                },

                payment_method: {
                    required: 'Please select a payment method.'
                },

                reference_number: {
                    maxlength: 'Reference number cannot exceed 100 characters.',
                    validReference: 'Reference number contains invalid characters.'
                },

                notes: {
                    maxlength: 'Notes cannot exceed 500 characters.'
                }

            },

            errorElement: 'label',

            errorClass: 'error',

            errorPlacement: function(error, element) {

                error.css({
                    display: 'block',
                    width: '100%',
                    clear: 'both',
                    marginTop: '5px'
                });

                if (element.is('select')) {

                    const wrapper = element.closest('.relative');

                    if (wrapper.length) {
                        error.insertAfter(wrapper);
                    } else {
                        error.insertAfter(element);
                    }

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

            success: function(label) {

                label.remove();

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
        | Invoice change
        |--------------------------------------------------------------------------
        */

        $('#invoiceSelect').on('change', function() {

            const option = this.options[this.selectedIndex];

            const balance = parseFloat(
                option ? option.getAttribute('data-balance') : 0
            ) || 0;

            const amountInput = $('#amount');

            if (balance > 0) {

                amountInput
                    .val(balance.toFixed(2))
                    .attr('max', balance.toFixed(2));

            } else {

                amountInput
                    .val('')
                    .removeAttr('max');

            }

            /*
            | Revalidate amount
            */
            amountInput.valid();

        });


        /*
        |--------------------------------------------------------------------------
        | Amount validation against invoice balance
        |--------------------------------------------------------------------------
        */

        $.validator.addMethod(
            'withinInvoiceBalance',
            function(value, element) {

                if (!value) {
                    return true;
                }

                const selectedOption =
                    $('#invoiceSelect option:selected');

                const balance =
                    parseFloat(
                        selectedOption.data('balance')
                    ) || 0;

                const amount =
                    parseFloat(value) || 0;

                return amount <= balance;

            },
            function() {

                const selectedOption =
                    $('#invoiceSelect option:selected');

                const balance =
                    parseFloat(
                        selectedOption.data('balance')
                    ) || 0;

                return 'Amount cannot exceed invoice balance of ' +
                    balance.toFixed(2) + '.';
            }
        );


        /*
        | Add balance rule dynamically
        */
        $('#amount').rules('add', {
            withinInvoiceBalance: true
        });


        /*
        |--------------------------------------------------------------------------
        | Revalidate amount when invoice changes
        |--------------------------------------------------------------------------
        */

        $('#invoiceSelect').on('change', function() {

            $('#amount').valid();

        });


        /*
        |--------------------------------------------------------------------------
        | Trim reference before validation
        |--------------------------------------------------------------------------
        */

        $('#reference_number').on('blur', function() {

            this.value = $.trim(this.value);

        });


        /*
        |--------------------------------------------------------------------------
        | Trim notes
        |--------------------------------------------------------------------------
        */

        $('#notes').on('blur', function() {

            this.value = $.trim(this.value);

        });


        /*
        |--------------------------------------------------------------------------
        | Payment date
        |--------------------------------------------------------------------------
        */

        const today = new Date().toISOString().split('T')[0];

        $('#payment_date').attr('max', today);

    });
</script>

<?php require_once '../../includes/footer.php'; ?>