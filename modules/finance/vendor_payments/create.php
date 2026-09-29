<?php

$pageTitle = 'Create Vendor Payment';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/finance_helpers.php';

if (!hasPermission('vendor_payments.create')) {
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

$bankAccounts = $db->query("
    SELECT id, account_name, bank_name, account_number, chart_account_id
    FROM bank_accounts
    WHERE status = 'active'
    ORDER BY account_name
")->fetchAll();

$cashAccounts = $db->query("
    SELECT id, cash_name, location, chart_account_id
    FROM cash_accounts
    WHERE status = 'active'
    ORDER BY cash_name
")->fetchAll();


// Generate payment number
$prefix = 'PAY' . date('Ymd');

$stmt = $db->prepare("
    SELECT COUNT(*)
    FROM vendor_payments
    WHERE payment_number LIKE ?
");

$stmt->execute([$prefix . '%']);

$payment_number = $prefix . '-' .
    str_pad(
        (int)$stmt->fetchColumn() + 1,
        3,
        '0',
        STR_PAD_LEFT
    );


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid or expired form token.';
    }

    $vendor_id = (int)($_POST['vendor_id'] ?? 0);

    $payment_date = trim(
        $_POST['payment_date'] ?? ''
    );

    $payment_method = trim(
        $_POST['payment_method'] ?? 'bank_transfer'
    );

    $bank_account_id = !empty($_POST['bank_account_id'])
        ? (int)$_POST['bank_account_id']
        : null;

    $cash_account_id = !empty($_POST['cash_account_id'])
        ? (int)$_POST['cash_account_id']
        : null;

    $reference_number = trim(
        $_POST['reference_number'] ?? ''
    );

    $amount = (float)str_replace(
        ',',
        '',
        $_POST['amount'] ?? 0
    );

    $remarks = trim(
        $_POST['remarks'] ?? ''
    );

    $allocations = $_POST['allocations'] ?? [];


    // Basic validation

    if ($vendor_id <= 0) {
        $errors[] = 'Please select a vendor.';
    }

    if (empty($payment_date)) {
        $errors[] = 'Payment date is required.';
    }

    if ($amount <= 0) {
        $errors[] = 'Amount must be greater than zero.';
    }


    // Validate bank/cash account selection

    if (
        $payment_method === 'cash'
        && !$cash_account_id
    ) {

        $errors[] =
            'Please select a cash account for cash payments.';
    } elseif (
        in_array(
            $payment_method,
            [
                'bank_transfer',
                'cheque',
                'credit_card',
                'online'
            ]
        )
        && !$bank_account_id
    ) {

        $errors[] =
            'Please select a bank account.';
    }


    // Validate allocations

    $totalAllocated = 0;

    if (
        !empty($allocations)
        && is_array($allocations)
    ) {

        foreach ($allocations as $bill_id => $alloc_amt) {

            $alloc_amt = (float)str_replace(
                ',',
                '',
                $alloc_amt ?? 0
            );

            if ($alloc_amt > 0) {

                // Load bill to validate balance

                $stmt = $db->prepare("
                    SELECT balance_amount
                    FROM vendor_bills
                    WHERE id = ?
                    AND vendor_id = ?
                    AND balance_amount > 0
                ");

                $stmt->execute([
                    (int)$bill_id,
                    $vendor_id
                ]);

                $bill = $stmt->fetch();

                if (!$bill) {

                    $errors[] =
                        "Bill ID $bill_id is not valid or has no outstanding balance.";

                    continue;
                }

                if (
                    $alloc_amt >
                    (float)$bill['balance_amount']
                ) {

                    $errors[] =
                        "Allocation amount $alloc_amt exceeds outstanding balance of "
                        . formatMoney(
                            (float)$bill['balance_amount']
                        )
                        . " for bill ID $bill_id.";
                }

                $totalAllocated += $alloc_amt;
            }
        }
    }


    if ($totalAllocated > $amount) {

        $errors[] =
            "Total allocated amount ($totalAllocated) cannot exceed payment amount ($amount).";
    }


    if (!empty($errors)) {

        saveFormState($errors);

        to(
            'modules/finance/vendor_payments/create.php'
        );
    }


    try {

        $db->beginTransaction();


        // Insert payment

        $stmt = $db->prepare("
            INSERT INTO vendor_payments
            (
                payment_number,
                vendor_id,
                payment_date,
                payment_method,
                bank_account_id,
                cash_account_id,
                reference_number,
                amount,
                unallocated_amount,
                remarks,
                created_by,
                created_at,
                updated_at
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
            )
        ");

        $unallocated =
            $amount - $totalAllocated;

        $stmt->execute([
            $payment_number,
            $vendor_id,
            $payment_date,
            $payment_method,
            $bank_account_id,
            $cash_account_id,
            $reference_number ?: null,
            $amount,
            $unallocated,
            $remarks ?: null,
            $_SESSION['user_id']
        ]);

        $payment_id =
            (int)$db->lastInsertId();


        // Process allocations

        if (
            !empty($allocations)
            && is_array($allocations)
        ) {

            $allocStmt = $db->prepare("
                INSERT INTO payment_allocations
                (
                    payment_id,
                    bill_id,
                    amount
                )
                VALUES (?, ?, ?)
            ");

            $updateBill = $db->prepare("
                UPDATE vendor_bills
                SET
                    paid_amount = paid_amount + ?,
                    balance_amount = balance_amount - ?,
                    updated_at = NOW()
                WHERE id = ?
            ");


            foreach (
                $allocations as $bill_id => $alloc_amt
            ) {

                $alloc_amt = (float)str_replace(
                    ',',
                    '',
                    $alloc_amt ?? 0
                );

                if ($alloc_amt <= 0) {
                    continue;
                }

                $bill_id = (int)$bill_id;


                $allocStmt->execute([
                    $payment_id,
                    $bill_id,
                    $alloc_amt
                ]);


                $updateBill->execute([
                    $alloc_amt,
                    $alloc_amt,
                    $bill_id
                ]);


                // Update bill status if fully paid

                $stmt = $db->prepare("
                    SELECT
                        total_amount,
                        paid_amount,
                        balance_amount
                    FROM vendor_bills
                    WHERE id = ?
                ");

                $stmt->execute([
                    $bill_id
                ]);

                $bill = $stmt->fetch();


                if (
                    $bill
                    && (float)$bill['balance_amount'] <= 0
                ) {

                    $db->prepare("
                        UPDATE vendor_bills
                        SET
                            status = 'paid',
                            updated_at = NOW()
                        WHERE id = ?
                    ")->execute([
                        $bill_id
                    ]);


                    $db->prepare("
                        INSERT INTO bill_status_history
                        (
                            bill_id,
                            from_status,
                            to_status,
                            changed_by,
                            change_note
                        )
                        VALUES
                        (
                            ?,
                            'approved',
                            'paid',
                            ?,
                            'Payment allocation completed'
                        )
                    ")->execute([
                        $bill_id,
                        $_SESSION['user_id']
                    ]);
                } elseif (
                    $bill
                    && (float)$bill['paid_amount'] > 0
                ) {

                    $db->prepare("
                        UPDATE vendor_bills
                        SET
                            status = 'partially_paid',
                            updated_at = NOW()
                        WHERE id = ?
                        AND status = 'approved'
                    ")->execute([
                        $bill_id
                    ]);


                    $db->prepare("
                        INSERT INTO bill_status_history
                        (
                            bill_id,
                            from_status,
                            to_status,
                            changed_by,
                            change_note
                        )
                        VALUES
                        (
                            ?,
                            'approved',
                            'partially_paid',
                            ?,
                            'Partial payment received'
                        )
                    ")->execute([
                        $bill_id,
                        $_SESSION['user_id']
                    ]);
                }
            }
        }


        // Create journal entry
        // DR AP, CR Bank/Cash

        $stmt = $db->query("
            SELECT setting_value
            FROM settings
            WHERE setting_key = 'finance_ap_account_id'
        ");

        $apAccountId =
            (int)$stmt->fetchColumn();


        if ($apAccountId <= 0) {

            throw new Exception(
                'AP account not configured in Finance Settings.'
            );
        }


        // Get credit account

        $creditAccountId = 0;


        if (
            in_array(
                $payment_method,
                [
                    'bank_transfer',
                    'cheque',
                    'credit_card',
                    'online'
                ]
            )
            && $bank_account_id
        ) {

            $stmt = $db->prepare("
                SELECT chart_account_id
                FROM bank_accounts
                WHERE id = ?
            ");

            $stmt->execute([
                $bank_account_id
            ]);

            $creditAccountId =
                (int)$stmt->fetchColumn();
        } elseif (
            $payment_method === 'cash'
            && $cash_account_id
        ) {

            $stmt = $db->prepare("
                SELECT chart_account_id
                FROM cash_accounts
                WHERE id = ?
            ");

            $stmt->execute([
                $cash_account_id
            ]);

            $creditAccountId =
                (int)$stmt->fetchColumn();
        }


        if ($creditAccountId <= 0) {

            throw new Exception(
                'Selected payment account has no linked chart of accounts entry.'
            );
        }


        // Get Payment Voucher voucher type

        $stmt = $db->prepare("
            SELECT
                id,
                voucher_prefix
            FROM voucher_types
            WHERE voucher_name = 'Payment Voucher'
            LIMIT 1
        ");

        $stmt->execute();

        $vt = $stmt->fetch();


        if (!$vt) {

            throw new Exception(
                'Payment Voucher voucher type not found.'
            );
        }


        $voucherTypeId =
            (int)$vt['id'];


        // Generate voucher number

        $voucherNo =
            generateVoucherNo(
                $voucherTypeId,
                $db
            );


        if (!$voucherNo) {

            throw new Exception(
                'Failed to generate voucher number.'
            );
        }


        // Create journal entry

        $stmt = $db->prepare("
            INSERT INTO journal_entries
            (
                voucher_no,
                voucher_type_id,
                voucher_date,
                description,
                status,
                created_by,
                created_at,
                updated_at
            )
            VALUES
            (
                ?, ?, ?, ?, 'draft', ?, NOW(), NOW()
            )
        ");


        $stmt->execute([
            $voucherNo,
            $voucherTypeId,
            $payment_date,
            'Vendor Payment #'
                . $payment_number
                . ' - '
                . $amount,
            $_SESSION['user_id']
        ]);


        $journalId =
            (int)$db->lastInsertId();


        // Add journal lines

        $detailStmt = $db->prepare("
            INSERT INTO journal_entry_details
            (
                journal_entry_id,
                account_id,
                debit_amount,
                credit_amount,
                description
            )
            VALUES (?, ?, ?, ?, ?)
        ");


        // DR AP

        $detailStmt->execute([
            $journalId,
            $apAccountId,
            $amount,
            0,
            'Vendor Payment #' . $payment_number
        ]);


        // CR Bank/Cash

        $detailStmt->execute([
            $journalId,
            $creditAccountId,
            0,
            $amount,
            'Vendor Payment #' . $payment_number
        ]);


        // Post journal

        require_once
            __DIR__
            . '/../posting_engine/PostingEngine.php';


        $result =
            PostingEngine::post(
                $journalId,
                $_SESSION['user_id']
            );


        if (!$result['success']) {

            throw new Exception(
                $result['message']
            );
        }


        // Update payment with journal entry ID

        $db->prepare("
            UPDATE vendor_payments
            SET
                journal_entry_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ")->execute([
            $journalId,
            $payment_id
        ]);


        $db->commit();


        logAudit(
            $_SESSION['user_id'],
            'Created vendor payment: '
                . $payment_number
                . ' (Voucher: '
                . $voucherNo
                . ')',
            'vendor_payments'
        );


        flash(
            'success',
            'Vendor payment '
                . $payment_number
                . ' created and posted successfully. Voucher #'
                . $voucherNo,
            'success'
        );


        redirect(
            'view.php?id=' . $payment_id
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
            'modules/finance/vendor_payments/create.php'
        );
    }
}

?>

<div class="mb-6 flex justify-between items-center">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            Create Vendor Payment
        </h1>

        <p class="text-gray-600">
            Record a payment to a vendor
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
    id="paymentForm"
    novalidate>

    <input
        type="hidden"
        name="csrf_token"
        value="<?= csrfToken() ?>">


    <!-- Payment Details -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">


            <!-- Payment Number -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Payment Number
                </label>

                <input
                    type="text"
                    value="<?= sanitize($payment_number) ?>"
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
                            <?= old('vendor_id') == $v['id'] ? 'selected' : '' ?>>

                            <?= sanitize(
                                $v['vendor_name']
                                    . ' ('
                                    . $v['vendor_code']
                                    . ')'
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Payment Date -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Payment Date

                    <span class="text-red-500">*</span>

                </label>

                <input
                    type="date"
                    name="payment_date"
                    value="<?= old(
                                'payment_date',
                                date('Y-m-d')
                            ) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


            <!-- Payment Method -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Payment Method

                    <span class="text-red-500">*</span>

                </label>

                <select
                    name="payment_method"
                    id="paymentMethod"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option
                        value="bank_transfer"
                        <?= old(
                            'payment_method',
                            'bank_transfer'
                        ) === 'bank_transfer'
                            ? 'selected'
                            : '' ?>>
                        Bank Transfer
                    </option>

                    <option
                        value="cheque"
                        <?= old('payment_method') === 'cheque'
                            ? 'selected'
                            : '' ?>>
                        Cheque
                    </option>

                    <option
                        value="credit_card"
                        <?= old('payment_method') === 'credit_card'
                            ? 'selected'
                            : '' ?>>
                        Credit Card
                    </option>

                    <option
                        value="online"
                        <?= old('payment_method') === 'online'
                            ? 'selected'
                            : '' ?>>
                        Online
                    </option>

                    <option
                        value="cash"
                        <?= old('payment_method') === 'cash'
                            ? 'selected'
                            : '' ?>>
                        Cash
                    </option>

                </select>

            </div>


            <!-- Bank Account -->

            <div id="bankAccountGroup">

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Bank Account

                    <span class="text-red-500">*</span>

                </label>

                <select
                    name="bank_account_id"
                    id="bankAccountId"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Select Bank Account --
                    </option>

                    <?php foreach ($bankAccounts as $b): ?>

                        <option
                            value="<?= $b['id'] ?>"
                            <?= old('bank_account_id') == $b['id']
                                ? 'selected'
                                : '' ?>>

                            <?= sanitize(
                                $b['account_name']
                                    . ' - '
                                    . $b['bank_name']
                                    . ' ('
                                    . $b['account_number']
                                    . ')'
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Cash Account -->

            <div
                id="cashAccountGroup"
                style="display:none">

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Cash Account

                    <span class="text-red-500">*</span>

                </label>

                <select
                    name="cash_account_id"
                    id="cashAccountId"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Select Cash Account --
                    </option>

                    <?php foreach ($cashAccounts as $c): ?>

                        <option
                            value="<?= $c['id'] ?>"
                            <?= old('cash_account_id') == $c['id']
                                ? 'selected'
                                : '' ?>>

                            <?= sanitize(
                                $c['cash_name']
                                    . (
                                        $c['location']
                                        ? ' ('
                                        . $c['location']
                                        . ')'
                                        : ''
                                    )
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Reference -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Reference Number
                </label>

                <input
                    type="text"
                    name="reference_number"
                    value="<?= old('reference_number') ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    placeholder="Cheque / Ref #">

            </div>


            <!-- Amount -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Amount

                    <span class="text-red-500">*</span>

                </label>

                <input
                    type="number"
                    name="amount"
                    id="paymentAmount"
                    value="<?= old('amount') ? old('amount') : '' ?>"
                    step="0.01"
                    min="0.01"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>

        </div>

    </div>


    <!-- Bill Allocations -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <div class="flex justify-between items-center mb-4">

            <h3 class="text-lg font-semibold text-gray-800">
                Bill Allocations
            </h3>

            <span class="text-sm text-gray-500">
                Select a vendor above to load open bills
            </span>

        </div>


        <div id="allocationsContainer">

            <p class="text-sm text-gray-500 py-4 text-center">
                Select a vendor to see outstanding bills for allocation.
            </p>

        </div>


        <div class="flex justify-between items-center mt-4 pt-4 border-t">

            <div>

                <span class="text-sm text-gray-600">

                    Total Allocated:

                    <strong id="totalAllocated">
                        0.00
                    </strong>

                </span>


                <span class="text-sm text-gray-600 ml-4">

                    Unallocated:

                    <strong id="unallocatedAmount">
                        0.00
                    </strong>

                </span>

            </div>


            <div class="text-sm">

                Payment Amount:

                <strong id="displayAmount">
                    <?= old('amount', '0.00') ?>
                </strong>

            </div>

        </div>

    </div>


    <!-- Remarks -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <label class="block text-sm font-medium text-gray-700 mb-1">
            Remarks
        </label>

        <textarea
            name="remarks"
            rows="2"
            class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('remarks') ?></textarea>

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

            Create Payment

        </button>

    </div>

</form>


<!-- jQuery -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- jQuery Validation -->

<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>


<script>
    function togglePaymentAccounts() {

        const method =
            document.getElementById('paymentMethod').value;

        const bankGroup =
            document.getElementById('bankAccountGroup');

        const cashGroup =
            document.getElementById('cashAccountGroup');


        if (method === 'cash') {

            bankGroup.style.display = 'none';
            cashGroup.style.display = '';

        } else {

            bankGroup.style.display = '';
            cashGroup.style.display = 'none';
        }
    }



    function validateAllocations() {

        const amount =
            parseFloat(
                document.getElementById('paymentAmount').value
            ) || 0;


        document.getElementById('displayAmount')
            .textContent = amount.toFixed(2);


        let total = 0;


        document.querySelectorAll('.alloc-input')
            .forEach(function(inp) {

                total +=
                    parseFloat(inp.value) || 0;

            });


        document.getElementById('totalAllocated')
            .textContent = total.toFixed(2);


        const unallocated =
            amount - total;


        document.getElementById('unallocatedAmount')
            .textContent = unallocated.toFixed(2);


        if (unallocated < 0) {

            document.getElementById('unallocatedAmount')
                .classList.add('text-red-600');

        } else {

            document.getElementById('unallocatedAmount')
                .classList.remove('text-red-600');
        }
    }



    $(document).ready(function() {


        /*
         * jQuery Validation
         */

        $("#paymentForm").validate({

            rules: {

                vendor_id: {
                    required: true
                },

                payment_date: {
                    required: true
                },

                payment_method: {
                    required: true
                },

                bank_account_id: {

                    required: function() {

                        return $("#paymentMethod").val() !==
                            "cash";

                    }

                },

                cash_account_id: {

                    required: function() {

                        return $("#paymentMethod").val() ===
                            "cash";

                    }

                },

                reference_number: {

                    maxlength: 100

                },

                amount: {

                    required: true,
                    number: true,
                    min: 0.01

                },

                remarks: {

                    maxlength: 1000

                }

            },


            messages: {

                vendor_id: {
                    required: "Please select a vendor."
                },

                payment_date: {
                    required: "Payment date is required."
                },

                payment_method: {
                    required: "Please select a payment method."
                },

                bank_account_id: {
                    required: "Please select a bank account."
                },

                cash_account_id: {
                    required: "Please select a cash account."
                },

                reference_number: {
                    maxlength: "Reference number cannot exceed 100 characters."
                },

                amount: {

                    required: "Payment amount is required.",

                    number: "Please enter a valid amount.",

                    min: "Amount must be greater than zero."

                },

                remarks: {
                    maxlength: "Remarks cannot exceed 1000 characters."
                }

            },


            /*
             * Validation message
             * will appear BELOW the input/select
             */

            errorElement: "p",

            errorClass: "text-red-500 text-sm mt-1 block",

            highlight: function(element) {

                $(element)
                    .addClass("border-red-500")
                    .removeClass("border-gray-300");
            },

            unhighlight: function(element) {

                $(element)
                    .removeClass("border-red-500");
            },

            errorPlacement: function(error, element) {

                /*
                 * Always place error message
                 * BELOW the input/select
                 */

                error.insertAfter(element);

                error.addClass(
                    "text-red-500 text-sm mt-1 block"
                );
            },


            submitHandler: function(form) {


                /*
                 * Allocation validation
                 */

                $(".allocation-error").remove();

                $(".allocation-total-error").remove();


                const amount =
                    parseFloat(
                        $("#paymentAmount").val()
                    ) || 0;


                let totalAllocated = 0;

                let allocationError = false;


                $(".alloc-input").each(
                    function() {

                        const input =
                            $(this);

                        const value =
                            parseFloat(
                                input.val()
                            ) || 0;

                        const max =
                            parseFloat(
                                input.attr("max")
                            ) || 0;


                        input.removeClass(
                            "border-red-500"
                        );


                        if (value < 0) {

                            allocationError = true;

                            input.addClass(
                                "border-red-500"
                            );

                            input.after(
                                '<p class="allocation-error text-red-500 text-xs mt-1">' +
                                'Allocation cannot be negative.' +
                                '</p>'
                            );

                        }


                        if (value > max) {

                            allocationError = true;

                            input.addClass(
                                "border-red-500"
                            );

                            input.after(
                                '<p class="allocation-error text-red-500 text-xs mt-1">' +
                                'Allocation cannot exceed bill balance.' +
                                '</p>'
                            );

                        }


                        totalAllocated += value;

                    }
                );


                /*
                 * Total allocation cannot exceed payment
                 */

                if (totalAllocated > amount) {

                    $("#totalAllocated")
                        .addClass("text-red-600");


                    $("#unallocatedAmount")
                        .addClass("text-red-600");


                    $("#allocationsContainer")
                        .after(
                            '<p class="allocation-total-error block w-full text-red-500 text-sm mt-2">' +
                            'Total allocated amount cannot exceed payment amount.' +
                            '</p>'
                        );


                    return false;
                }


                if (allocationError) {

                    return false;

                }


                /*
                 * Everything valid
                 */

                form.submit();

            }

        });


        /*
         * Payment method change
         */

        $("#paymentMethod").on(
            "change",
            function() {

                togglePaymentAccounts();


                /*
                 * Remove validation error
                 * from hidden account
                 */

                if ($(this).val() === "cash") {

                    $("#bankAccountId")
                        .removeClass("border-red-500");

                    $("#bankAccountId")
                        .next("p.error")
                        .remove();

                    $("#cashAccountId")
                        .valid();

                } else {

                    $("#cashAccountId")
                        .removeClass("border-red-500");

                    $("#cashAccountId")
                        .next("p.error")
                        .remove();

                    $("#bankAccountId")
                        .valid();
                }

            }
        );


        /*
         * Amount input
         */

        $("#paymentAmount").on(
            "input",
            function() {

                validateAllocations();

                $(".allocation-total-error")
                    .remove();

            }
        );


        /*
         * Dynamic allocation input
         */

        $(document).on(
            "input",
            ".alloc-input",
            function() {

                const input =
                    $(this);


                $(".allocation-error")
                    .remove();


                input.removeClass(
                    "border-red-500"
                );


                const value =
                    parseFloat(
                        input.val()
                    ) || 0;


                const max =
                    parseFloat(
                        input.attr("max")
                    ) || 0;


                if (value < 0) {

                    input.addClass(
                        "border-red-500"
                    );

                    input.after(
                        '<p class="allocation-error block text-red-500 text-xs mt-1">' +
                        'Allocation cannot be negative.' +
                        '</p>'
                    );

                } else if (value > max) {

                    input.addClass(
                        "border-red-500"
                    );

                    input.after(
                        '<p class="allocation-error block text-red-500 text-xs mt-1">' +
                        'Maximum allocation is ' +
                        max.toFixed(2) +
                        '.' +
                        '</p>'
                    );

                }


                validateAllocations();


                /*
                 * Check total allocation
                 */

                const amount =
                    parseFloat(
                        $("#paymentAmount").val()
                    ) || 0;


                let total = 0;


                $(".alloc-input").each(
                    function() {

                        total +=
                            parseFloat(
                                $(this).val()
                            ) || 0;

                    }
                );


                $(".allocation-total-error")
                    .remove();


                if (total > amount) {

                    $("#totalAllocated")
                        .addClass("text-red-600");


                    $("#unallocatedAmount")
                        .addClass("text-red-600");


                    $("#allocationsContainer")
                        .after(
                            '<p class="allocation-total-error block w-full text-red-500 text-sm mt-2">' +
                            'Total allocated amount cannot exceed payment amount.' +
                            '</p>'
                        );

                } else {

                    $("#totalAllocated")
                        .removeClass("text-red-600");


                    $("#unallocatedAmount")
                        .removeClass("text-red-600");

                }

            }
        );


        /*
         * Vendor change
         */

        $("#vendorSelect").on(
            "change",
            function() {

                const vendorId =
                    this.value;


                const container =
                    document.getElementById(
                        'allocationsContainer'
                    );


                if (!vendorId) {

                    container.innerHTML =
                        '<p class="text-sm text-gray-500 py-4 text-center">' +
                        'Select a vendor to see outstanding bills for allocation.' +
                        '</p>';

                    return;
                }


                container.innerHTML =
                    '<p class="text-sm text-gray-500 py-4 text-center">' +
                    'Loading bills...' +
                    '</p>';


                fetch(
                        'ajax_get_bills.php?vendor_id=' +
                        encodeURIComponent(vendorId)
                    )

                    .then(function(response) {

                        if (!response.ok) {

                            throw new Error(
                                'Network response was not ok'
                            );

                        }

                        return response.json();

                    })

                    .then(function(data) {


                        if (data.error) {

                            container.innerHTML =
                                '<p class="text-sm text-red-500 py-4 text-center">' +
                                $('<div>')
                                .text(data.error)
                                .html() +
                                '</p>';

                            return;
                        }


                        if (!data.length) {

                            container.innerHTML =
                                '<p class="text-sm text-gray-500 py-4 text-center">' +
                                'No outstanding bills for this vendor.' +
                                '</p>';

                            return;
                        }


                        let html =
                            '<div class="overflow-x-auto">' +

                            '<table class="w-full">' +

                            '<thead class="bg-gray-50">' +

                            '<tr>' +

                            '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">' +
                            'Bill #' +
                            '</th>' +

                            '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">' +
                            'Date' +
                            '</th>' +

                            '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">' +
                            'Due' +
                            '</th>' +

                            '<th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">' +
                            'Total' +
                            '</th>' +

                            '<th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">' +
                            'Balance' +
                            '</th>' +

                            '<th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">' +
                            'Allocation' +
                            '</th>' +

                            '</tr>' +

                            '</thead>' +

                            '<tbody>';


                        data.forEach(
                            function(b) {

                                const billNumber =
                                    $('<div>')
                                    .text(
                                        b.bill_number
                                    )
                                    .html();

                                const billDate =
                                    $('<div>')
                                    .text(
                                        b.bill_date
                                    )
                                    .html();

                                const dueDate =
                                    $('<div>')
                                    .text(
                                        b.due_date
                                    )
                                    .html();

                                const totalAmount =
                                    $('<div>')
                                    .text(
                                        b.total_amount
                                    )
                                    .html();

                                const balanceAmount =
                                    parseFloat(
                                        b.balance_amount
                                    ) || 0;


                                html +=

                                    '<tr class="hover:bg-gray-50">' +

                                    '<td class="px-3 py-2 text-sm font-mono">' +
                                    billNumber +
                                    '</td>' +

                                    '<td class="px-3 py-2 text-sm">' +
                                    billDate +
                                    '</td>' +

                                    '<td class="px-3 py-2 text-sm">' +
                                    dueDate +
                                    '</td>' +

                                    '<td class="px-3 py-2 text-sm text-right font-mono">' +
                                    totalAmount +
                                    '</td>' +

                                    '<td class="px-3 py-2 text-sm text-right font-mono text-red-600">' +
                                    balanceAmount.toFixed(2) +
                                    '</td>' +

                                    '<td class="px-3 py-2 text-right">' +

                                    '<input' +

                                    ' type="number"' +

                                    ' name="allocations[' +
                                    parseInt(b.id, 10) +
                                    ']"' +

                                    ' value="0"' +

                                    ' step="0.01"' +

                                    ' min="0"' +

                                    ' max="' +
                                    balanceAmount.toFixed(2) +
                                    '"' +

                                    ' class="w-28 px-2 py-1.5 border rounded text-sm text-right alloc-input"' +

                                    '>' +

                                    '</td>' +

                                    '</tr>';

                            }
                        );


                        html +=
                            '</tbody>' +
                            '</table>' +
                            '</div>';


                        container.innerHTML =
                            html;


                        /*
                         * Recalculate after AJAX load
                         */

                        validateAllocations();

                    })

                    .catch(function() {

                        container.innerHTML =
                            '<p class="text-sm text-red-500 py-4 text-center">' +
                            'Failed to load bills.' +
                            '</p>';

                    });

            }
        );


        /*
         * Initial payment account state
         */

        togglePaymentAccounts();


        /*
         * Initial allocation calculation
         */

        validateAllocations();

    });
</script>


<?php require_once '../../../includes/footer.php'; ?>