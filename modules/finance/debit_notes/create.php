<?php

$pageTitle = 'Create Debit Note';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../tax/helpers/tax_ui.php';

if (!hasPermission('debit_notes.create')) {
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
| Generate Debit Note Number
|--------------------------------------------------------------------------
*/

$prefix = 'DN' . date('Ymd');

$stmt = $db->prepare("
    SELECT COUNT(*)
    FROM debit_notes
    WHERE debit_note_number LIKE ?
");

$stmt->execute([
    $prefix . '%'
]);

$debit_note_number =
    $prefix . '-' .
    str_pad(
        (int)$stmt->fetchColumn() + 1,
        3,
        '0',
        STR_PAD_LEFT
    );

$dnTypes = [
    'price_correction'    => 'Price Correction',
    'return'              => 'Return',
    'discount_adjustment' => 'Discount Adjustment',
    'cancellation'        => 'Cancellation'
];

/*
|--------------------------------------------------------------------------
| Default Tax
|--------------------------------------------------------------------------
*/

$defaultType = TaxService::getDefaultTaxType();

$defaultTypeId =
    $defaultType
    ? (int)$defaultType['id']
    : 0;

$defRate =
    TaxService::getDefaultTaxRate(
        $defaultTypeId
    );

$defaultRateId =
    $defRate
    ? (int)$defRate['id']
    : 0;


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

        $errors[] =
            'Invalid or expired form token.';
    }


    /*
    |--------------------------------------------------------------------------
    | Form Values
    |--------------------------------------------------------------------------
    */

    $vendor_id =
        (int)($_POST['vendor_id'] ?? 0);

    $bill_id =
        !empty($_POST['bill_id'])
        ? (int)$_POST['bill_id']
        : null;

    $debit_note_date =
        trim(
            $_POST['debit_note_date'] ?? ''
        );

    $debit_note_type =
        trim(
            $_POST['debit_note_type']
                ?? 'price_correction'
        );

    $reason =
        trim(
            $_POST['reason'] ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | Tax
    |--------------------------------------------------------------------------
    */

    $tax_rate_id =
        (int)(
            $_POST['tax']['tax_rate_id']
            ?? 0
        );

    $tax_status =
        $_POST['tax']['tax_status']
        ?? 'taxable';

    $exemption_id =
        (int)(
            $_POST['tax']['tax_exemption_id']
            ?? 0
        );


    /*
    |--------------------------------------------------------------------------
    | Amount
    |--------------------------------------------------------------------------
    */

    $amountInput =
        (float)str_replace(
            ',',
            '',
            $_POST['amount'] ?? 0
        );

    $issueNow =
        isset($_POST['issue_now']);


    /*
    |--------------------------------------------------------------------------
    | Server Validation
    |--------------------------------------------------------------------------
    */

    if ($vendor_id <= 0) {

        $errors[] =
            'Please select a vendor.';
    }

    if (empty($debit_note_date)) {

        $errors[] =
            'Debit note date is required.';
    }

    if ($amountInput <= 0) {

        $errors[] =
            'Amount must be greater than zero.';
    }

    if (empty($reason)) {

        $errors[] =
            'Reason is required for debit note.';
    }


    /*
    |--------------------------------------------------------------------------
    | Save Validation Errors
    |--------------------------------------------------------------------------
    */

    if (!empty($errors)) {

        saveFormState($errors);

        to(
            'modules/finance/debit_notes/create.php'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Create Debit Note
    |--------------------------------------------------------------------------
    */

    try {

        /*
        |--------------------------------------------------------------------------
        | Tax Calculation
        |--------------------------------------------------------------------------
        */

        $calc =
            TaxService::calculateLineTax(
                $amountInput,
                [
                    'tax_rate_id' =>
                    $tax_rate_id ?: null,

                    'tax_status' =>
                    $tax_status,

                    'exemption_id' =>
                    $exemption_id ?: null,

                    'date' =>
                    $debit_note_date,
                ]
            );


        $subtotal =
            (float)$calc['net_amount'];

        $tax_amount =
            (float)$calc['tax_amount'];

        $total_amount =
            (float)$calc['total_amount'];

        $amount =
            $subtotal;


        /*
        |--------------------------------------------------------------------------
        | Begin Transaction
        |--------------------------------------------------------------------------
        */

        $db->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | Insert Debit Note
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            INSERT INTO debit_notes (
                debit_note_number,
                vendor_id,
                bill_id,
                debit_note_date,
                debit_note_type,
                reason,
                subtotal,
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
            VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                'draft',
                ?,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            $debit_note_number,
            $vendor_id,
            $bill_id,
            $debit_note_date,
            $debit_note_type,
            $reason,

            $subtotal,
            $tax_amount,
            $total_amount,

            $calc['tax_type_id']
                ? (int)$calc['tax_type_id']
                : null,

            $calc['tax_rate_id']
                ? (int)$calc['tax_rate_id']
                : null,

            null,

            $calc['tax_status'],

            $subtotal,

            $calc['exemption_id']
                ? (int)$calc['exemption_id']
                : null,

            $_SESSION['user_id'],
        ]);


        $dn_id =
            (int)$db->lastInsertId();


        /*
        |--------------------------------------------------------------------------
        | Issue Now
        |--------------------------------------------------------------------------
        */

        if ($issueNow) {

            /*
            |--------------------------------------------------------------------------
            | Load Finance Settings
            |--------------------------------------------------------------------------
            */

            $stmt = $db->query("
                SELECT
                    setting_key,
                    setting_value
                FROM settings
                WHERE setting_key IN (
                    'finance_ap_account_id',
                    'finance_expense_account_id'
                )
            ");

            $settings = [];

            foreach ($stmt->fetchAll() as $row) {

                $settings[$row['setting_key']] =
                    (int)$row['setting_value'];
            }


            $apAccountId =
                $settings['finance_ap_account_id'] ?? 0;

            $defaultExpenseAccountId =
                $settings['finance_expense_account_id'] ?? 0;


            if (
                $apAccountId <= 0 ||
                $defaultExpenseAccountId <= 0
            ) {

                throw new Exception(
                    'AP account or default expense account not configured in Finance Settings.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Debit Note Voucher Type
            |--------------------------------------------------------------------------
            */

            $stmt = $db->prepare("
                SELECT id
                FROM voucher_types
                WHERE voucher_name = 'Debit Note'
                LIMIT 1
            ");

            $stmt->execute();

            $vt =
                $stmt->fetch();


            if (!$vt) {

                throw new Exception(
                    'Debit Note voucher type not found.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Voucher Number
            |--------------------------------------------------------------------------
            */

            $voucherNo =
                generateVoucherNo(
                    (int)$vt['id'],
                    $db
                );


            if (!$voucherNo) {

                throw new Exception(
                    'Failed to generate voucher number.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Journal Entry
            |--------------------------------------------------------------------------
            */

            $stmt = $db->prepare("
                INSERT INTO journal_entries (
                    voucher_no,
                    voucher_type_id,
                    voucher_date,
                    description,
                    status,
                    created_by,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?, ?, ?,
                    'draft',
                    ?,
                    NOW(),
                    NOW()
                )
            ");

            $stmt->execute([
                $voucherNo,
                (int)$vt['id'],
                $debit_note_date,

                'Debit Note #' .
                    $debit_note_number .
                    ' (' .
                    $dnTypes[$debit_note_type] .
                    ')',

                $_SESSION['user_id'],
            ]);


            $journalId =
                (int)$db->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Journal Details
            |--------------------------------------------------------------------------
            |
            | DR AP
            | CR Expense
            | CR Input Tax
            |--------------------------------------------------------------------------
            */

            $detailStmt = $db->prepare("
                INSERT INTO journal_entry_details (
                    journal_entry_id,
                    account_id,
                    debit_amount,
                    credit_amount,
                    description
                )
                VALUES (?, ?, ?, ?, ?)
            ");


            /*
            |--------------------------------------------------------------------------
            | DR AP
            |--------------------------------------------------------------------------
            */

            $detailStmt->execute([
                $journalId,
                $apAccountId,
                $total_amount,
                0,
                'Debit Note #' .
                    $debit_note_number
            ]);


            /*
            |--------------------------------------------------------------------------
            | CR Expense
            |--------------------------------------------------------------------------
            */

            $detailStmt->execute([
                $journalId,
                $defaultExpenseAccountId,
                0,
                $subtotal,
                'Debit Note #' .
                    $debit_note_number
            ]);


            /*
            |--------------------------------------------------------------------------
            | CR Input Tax
            |--------------------------------------------------------------------------
            */

            if ($tax_amount > 0) {

                $inputTaxAccountId =
                    (int)TaxService::config(
                        'input_tax_account_id',
                        0
                    );


                if ($inputTaxAccountId <= 0) {

                    throw new Exception(
                        'Input Tax account not configured. Go to Tax Settings to set it up.'
                    );
                }


                $detailStmt->execute([
                    $journalId,
                    $inputTaxAccountId,
                    0,
                    $tax_amount,
                    'Input Tax #' .
                        $debit_note_number
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Record Tax
            |--------------------------------------------------------------------------
            */

            if (
                (int)$calc['tax_type_id'] &&
                $subtotal > 0
            ) {

                $ratePct =
                    (float)$calc['rate_percentage'];


                TaxService::recordDocumentTax([

                    'source_module' =>
                    'debit_note',

                    'source_record_id' =>
                    $dn_id,

                    'source_document_no' =>
                    $debit_note_number,

                    'transaction_date' =>
                    $debit_note_date,

                    'tax_type_id' =>
                    (int)$calc['tax_type_id'],

                    'direction' =>
                    'input',

                    'counterparty_type' =>
                    'vendor',

                    'counterparty_id' =>
                    $vendor_id,

                    'reference' =>
                    'Debit note reducing input tax',

                    'created_by' =>
                    $_SESSION['user_id'],

                    'lines' => [[

                        'taxable_amount' =>
                        -$subtotal,

                        'tax_amount' =>
                        -$tax_amount,

                        'tax_rate' =>
                        $ratePct,

                        'tax_rate_id' =>
                        $calc['tax_rate_id']
                            ? (int)$calc['tax_rate_id']
                            : null,

                        'tax_group_id' =>
                        null,

                        'tax_status' =>
                        $calc['tax_status'],

                        'exemption_id' =>
                        $calc['exemption_id']
                            ? (int)$calc['exemption_id']
                            : null,
                    ]],
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Post Journal
            |--------------------------------------------------------------------------
            */

            require_once __DIR__ .
                '/../posting_engine/PostingEngine.php';


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


            /*
            |--------------------------------------------------------------------------
            | Update Debit Note Status
            |--------------------------------------------------------------------------
            */

            $db->prepare("
                UPDATE debit_notes
                SET
                    status = 'issued',
                    journal_entry_id = ?,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $journalId,
                $dn_id
            ]);


            /*
            |--------------------------------------------------------------------------
            | Update Related Bill
            |--------------------------------------------------------------------------
            */

            if ($bill_id) {

                $db->prepare("
                    UPDATE vendor_bills
                    SET
                        total_amount =
                            total_amount - ?,

                        balance_amount =
                            GREATEST(
                                0,
                                balance_amount - ?
                            ),

                        updated_at = NOW()

                    WHERE id = ?
                      AND balance_amount >= ?
                ")->execute([
                    $total_amount,
                    $total_amount,
                    $bill_id,
                    $total_amount
                ]);


                /*
                |--------------------------------------------------------------------------
                | Recalculate Bill Status
                |--------------------------------------------------------------------------
                */

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

                $vb =
                    $stmt->fetch();


                if ($vb) {

                    $newStatus =
                        (float)$vb['balance_amount']
                        <= 0.001
                        ? 'paid'
                        : 'approved';


                    $db->prepare("
                        UPDATE vendor_bills
                        SET
                            status = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ")->execute([
                        $newStatus,
                        $bill_id
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | Bill Status History
                    |--------------------------------------------------------------------------
                    */

                    $db->prepare("
                        INSERT INTO bill_status_history (
                            bill_id,
                            from_status,
                            to_status,
                            changed_by,
                            change_note
                        )
                        VALUES (
                            ?,
                            'approved',
                            ?,
                            ?,
                            ?
                        )
                    ")->execute([
                        $bill_id,
                        $newStatus,
                        $_SESSION['user_id'],

                        'Debit note ' .
                            $debit_note_number .
                            ' applied (' .
                            $total_amount .
                            ')'
                    ]);
                }
            }
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
            'Created debit note: ' .
                $debit_note_number .
                (
                    $issueNow
                    ? ' (issued)'
                    : ' (draft)'
                ),
            'debit_notes'
        );


        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */

        flash(
            'success',
            'Debit note ' .
                $debit_note_number .
                ' created successfully.',
            'success'
        );


        redirect(
            'view.php?id=' . $dn_id
        );
    } catch (Exception $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }


        flash(
            'error',
            'Error: ' .
                $e->getMessage()
        );


        to(
            'modules/finance/debit_notes/create.php'
        );
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
            Create Debit Note
        </h1>

        <p class="text-gray-600">
            Record a credit/adjustment from a vendor
        </p>

    </div>


    <a
        href="index.php"
        class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
        <i class="fas fa-arrow-left mr-2"></i>
        Back
    </a>

</div>


<!--
|--------------------------------------------------------------------------
| Server Errors
|--------------------------------------------------------------------------
-->

<?php if (!empty($errors)): ?>

    <div
        class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">

        <?php foreach ($errors as $e): ?>

            <p>
                <?= sanitize($e) ?>
            </p>

        <?php endforeach; ?>

    </div>

<?php endif; ?>


<!--
|--------------------------------------------------------------------------
| Form
|--------------------------------------------------------------------------
-->

<form
    method="POST"
    id="dnForm"
    novalidate>

    <input
        type="hidden"
        name="csrf_token"
        value="<?= csrfToken() ?>">


    <!--
    |--------------------------------------------------------------------------
    | Debit Note Information
    |--------------------------------------------------------------------------
    -->

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">


            <!-- Debit Note Number -->

            <div>

                <label
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Debit Note Number
                </label>

                <input
                    type="text"
                    value="<?= sanitize($debit_note_number) ?>"
                    class="w-full px-3 py-2 border rounded bg-gray-50 text-gray-500"
                    readonly>

            </div>


            <!-- Vendor -->

            <div>

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
                            <?= old('vendor_id') == $v['id'] ? 'selected' : '' ?>>
                            <?= sanitize(
                                $v['vendor_name'] .
                                    ' (' .
                                    $v['vendor_code'] .
                                    ')'
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>


                <!-- VALIDATION MESSAGE -->

                <div
                    class="validation-error"
                    data-error-for="vendor_id"></div>

            </div>


            <!-- Date -->

            <div>

                <label
                    for="debitNoteDate"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Date
                    <span class="text-red-500">*</span>
                </label>

                <input
                    type="date"
                    name="debit_note_date"
                    id="debitNoteDate"
                    value="<?= old(
                                'debit_note_date',
                                date('Y-m-d')
                            ) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">


                <!-- VALIDATION MESSAGE -->

                <div
                    class="validation-error"
                    data-error-for="debit_note_date"></div>

            </div>


            <!-- Type -->

            <div>

                <label
                    for="debitNoteType"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Type
                    <span class="text-red-500">*</span>
                </label>

                <select
                    name="debit_note_type"
                    id="debitNoteType"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <?php foreach ($dnTypes as $k => $l): ?>

                        <option
                            value="<?= $k ?>"
                            <?= old(
                                'debit_note_type',
                                'price_correction'
                            ) === $k
                                ? 'selected'
                                : ''
                            ?>>
                            <?= $l ?>
                        </option>

                    <?php endforeach; ?>

                </select>


                <!-- VALIDATION MESSAGE -->

                <div
                    class="validation-error"
                    data-error-for="debit_note_type"></div>

            </div>


            <!-- Amount -->

            <div>

                <label
                    for="amountInput"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Amount (tax base)
                    <span class="text-red-500">*</span>
                </label>

                <input
                    type="number"
                    name="amount"
                    id="amountInput"
                    value="<?= old('amount') ?>"
                    step="0.01"
                    min="0.01"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    oninput="calcDn()">


                <!-- VALIDATION MESSAGE -->

                <div
                    class="validation-error"
                    data-error-for="amount"></div>

            </div>


            <!-- Tax Treatment -->

            <div>

                <label
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Tax Treatment
                </label>

                <div id="dnTaxPicker">

                    <?= taxui_doc_line_picker(
                        'tax',
                        $defaultRateId,
                        $defaultTypeId
                    ) ?>

                </div>

            </div>


            <!-- Related Bill -->

            <div>

                <label
                    for="billSelect"
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Related Bill
                </label>

                <select
                    name="bill_id"
                    id="billSelect"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Optional --
                    </option>

                </select>

            </div>

        </div>


        <!--
        |--------------------------------------------------------------------------
        | Tax Summary
        |--------------------------------------------------------------------------
        -->

        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-6">


            <!-- Net -->

            <div>

                <label
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Net (excluding tax)
                </label>

                <div
                    id="dnNetDisplay"
                    class="w-full px-3 py-2 border rounded bg-gray-50 text-right font-mono">
                    0.00
                </div>

            </div>


            <!-- Tax -->

            <div>

                <label
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Tax Amount
                </label>

                <div
                    id="dnTaxDisplay"
                    class="w-full px-3 py-2 border rounded bg-gray-50 text-right font-mono">
                    0.00
                </div>

            </div>


            <!-- Total -->

            <div>

                <label
                    class="block text-sm font-medium text-gray-700 mb-1">
                    Total
                </label>

                <div
                    id="dnTotalDisplay"
                    class="w-full px-3 py-2 border rounded bg-gray-50 text-right font-mono font-bold">
                    0.00
                </div>

            </div>

        </div>


        <!--
        |--------------------------------------------------------------------------
        | Reason
        |--------------------------------------------------------------------------
        -->

        <div class="mt-4">

            <label
                for="reason"
                class="block text-sm font-medium text-gray-700 mb-1">
                Reason
                <span class="text-red-500">*</span>
            </label>

            <textarea
                name="reason"
                id="reason"
                rows="3"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('reason') ?></textarea>


            <!-- VALIDATION MESSAGE -->

            <div
                class="validation-error"
                data-error-for="reason"></div>

        </div>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Buttons
    |--------------------------------------------------------------------------
    -->

    <div class="flex justify-end gap-4 mb-8">

        <a
            href="index.php"
            class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400 transition">
            Cancel
        </a>


        <button
            type="submit"
            name="issue_now"
            value="1"
            id="issueNowButton"
            class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition">
            <i class="fas fa-check mr-2"></i>
            Create & Issue
        </button>


        <button
            type="submit"
            id="saveDraftButton"
            class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
            <i class="fas fa-save mr-2"></i>
            Save as Draft
        </button>

    </div>

</form>


<script src="../tax/helpers/tax_doc.js"></script>


<script>
    /*
|--------------------------------------------------------------------------
| Tax Inclusive
|--------------------------------------------------------------------------
*/

    window.TAX_INCLUSIVE =
        <?= TaxService::pricesInclusive() ? '1' : '0' ?>;


    /*
    |--------------------------------------------------------------------------
    | Round
    |--------------------------------------------------------------------------
    */

    function round2(n) {

        return Math.round(
            (n + Number.EPSILON) * 100
        ) / 100;

    }


    /*
    |--------------------------------------------------------------------------
    | Calculate Debit Note
    |--------------------------------------------------------------------------
    */

    function calcDn() {

        const amountElement =
            document.getElementById('amountInput');

        const base =
            parseFloat(amountElement.value) || 0;


        const rateSel =
            document.querySelector(
                '#dnTaxPicker .tax-doc-rate'
            );

        let rate = 0;


        if (
            rateSel &&
            rateSel.selectedIndex >= 0
        ) {

            rate =
                parseFloat(
                    rateSel.options[
                        rateSel.selectedIndex
                    ].getAttribute('data-rate')
                ) || 0;
        }


        const statusSel =
            document.querySelector(
                '#dnTaxPicker .tax-doc-status'
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
                'dnNetDisplay'
            ).textContent =
            round2(net).toFixed(2);


        document.getElementById(
                'dnTaxDisplay'
            ).textContent =
            round2(tax).toFixed(2);


        document.getElementById(
                'dnTotalDisplay'
            ).textContent =
            round2(total).toFixed(2);


        if (
            typeof window.toggleExemptionSelect ===
            'function'
        ) {

            window.toggleExemptionSelect(
                document.getElementById(
                    'dnTaxPicker'
                )
            );
        }

    }


    /*
    |--------------------------------------------------------------------------
    | Tax Event Binding
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'DOMContentLoaded',
        function() {

            const taxPicker =
                document.getElementById(
                    'dnTaxPicker'
                );


            const rate =
                taxPicker.querySelector(
                    '.tax-doc-rate'
                );

            const status =
                taxPicker.querySelector(
                    '.tax-doc-status'
                );

            const exemption =
                taxPicker.querySelector(
                    '.tax-doc-exemption'
                );


            if (rate) {

                ['change', 'input'].forEach(
                    function(evt) {

                        rate.addEventListener(
                            evt,
                            calcDn
                        );

                    }
                );
            }


            if (status) {

                ['change', 'input'].forEach(
                    function(evt) {

                        status.addEventListener(
                            evt,
                            calcDn
                        );

                    }
                );
            }


            if (exemption) {

                exemption.addEventListener(
                    'change',
                    function() {

                        if (
                            typeof window.toggleExemptionSelect ===
                            'function'
                        ) {

                            window.toggleExemptionSelect(
                                taxPicker
                            );

                        }

                    }
                );
            }


            if (
                typeof window.toggleExemptionSelect ===
                'function'
            ) {

                window.toggleExemptionSelect(
                    taxPicker
                );
            }


            calcDn();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Vendor -> Bills
    |--------------------------------------------------------------------------
    */

    document
        .getElementById('vendorSelect')
        .addEventListener(
            'change',
            function() {

                const vendorId =
                    this.value;

                const billSelect =
                    document.getElementById(
                        'billSelect'
                    );


                billSelect.innerHTML =
                    '<option value="">-- Optional --</option>';


                if (!vendorId) {

                    return;
                }


                fetch(
                        '../vendor_payments/ajax_get_bills.php?vendor_id=' +
                        encodeURIComponent(vendorId)
                    )

                    .then(
                        function(r) {

                            return r.json();

                        }
                    )

                    .then(
                        function(data) {

                            if (
                                data &&
                                data.length
                            ) {

                                data.forEach(
                                    function(b) {

                                        const opt =
                                            document.createElement(
                                                'option'
                                            );

                                        opt.value =
                                            b.id;

                                        opt.textContent =
                                            b.bill_number +
                                            ' (' +
                                            b.balance_amount +
                                            ' balance)';

                                        billSelect.appendChild(
                                            opt
                                        );

                                    }
                                );
                            }

                        }
                    )

                    .catch(
                        function() {

                            // Ignore bill loading error

                        }
                    );

            }
        );
</script>


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
        | Debit Note Form Validation
        |--------------------------------------------------------------------------
        */

        $('#dnForm').validate({

            /*
            |--------------------------------------------------------------------------
            | Disable browser native validation
            |--------------------------------------------------------------------------
            */

            ignore: [],


            /*
            |--------------------------------------------------------------------------
            | Error Element
            |--------------------------------------------------------------------------
            */

            errorElement: 'p',

            errorClass: 'dn-validation-error',


            /*
            |--------------------------------------------------------------------------
            | Rules
            |--------------------------------------------------------------------------
            */

            rules: {

                vendor_id: {

                    required: true

                },


                debit_note_date: {

                    required: true

                },


                debit_note_type: {

                    required: true

                },


                amount: {

                    required: true,

                    number: true,

                    min: 0.01

                },


                reason: {

                    required: true,

                    minlength: 3

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


                debit_note_date: {

                    required: 'Please select debit note date.'

                },


                debit_note_type: {

                    required: 'Please select debit note type.'

                },


                amount: {

                    required: 'Please enter amount.',

                    number: 'Please enter a valid amount.',

                    min: 'Amount must be greater than zero.'

                },


                reason: {

                    required: 'Please enter reason.',

                    minlength: 'Reason must contain at least 3 characters.'

                }

            },


            /*
            |--------------------------------------------------------------------------
            | IMPORTANT ERROR PLACEMENT
            |--------------------------------------------------------------------------
            |
            | This is the main fix.
            |
            | Error goes into:
            |
            | <div class="validation-error"
            |      data-error-for="vendor_id">
            | </div>
            |
            | So it will ALWAYS appear below the select.
            |
            |--------------------------------------------------------------------------
            */

            errorPlacement: function(
                error,
                element
            ) {

                const fieldName =
                    element.attr('name');


                const errorContainer =
                    $('.validation-error[data-error-for="' +
                        fieldName +
                        '"]');


                if (
                    errorContainer.length
                ) {

                    errorContainer
                        .empty()
                        .append(error);

                } else {

                    error.insertAfter(
                        element
                    );

                }

            },


            /*
            |--------------------------------------------------------------------------
            | Highlight
            |--------------------------------------------------------------------------
            */

            highlight: function(
                element
            ) {

                $(element)
                    .addClass('border-red-500')
                    .removeClass(
                        'border-gray-300'
                    );

            },


            /*
            |--------------------------------------------------------------------------
            | Unhighlight
            |--------------------------------------------------------------------------
            */

            unhighlight: function(
                element
            ) {

                $(element)
                    .removeClass(
                        'border-red-500'
                    )
                    .addClass(
                        'border-gray-300'
                    );

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


                    /*
                    | Scroll to first error
                    */

                    $('html, body').animate({

                            scrollTop: $(firstElement)
                                .offset()
                                .top - 120

                        },
                        300
                    );

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


        /*
        |--------------------------------------------------------------------------
        | Vendor Change Validation
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
        | Date Change Validation
        |--------------------------------------------------------------------------
        */

        $('input[name="debit_note_date"]').on(
            'change',
            function() {

                $(this).valid();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Type Change Validation
        |--------------------------------------------------------------------------
        */

        $('select[name="debit_note_type"]').on(
            'change',
            function() {

                $(this).valid();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Amount Validation
        |--------------------------------------------------------------------------
        */

        $('#amountInput').on(
            'input change',
            function() {

                $(this).valid();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Reason Validation
        |--------------------------------------------------------------------------
        */

        $('textarea[name="reason"]').on(
            'input',
            function() {

                $(this).valid();

            }
        );


    });
</script>


<style>
    /*
|--------------------------------------------------------------------------
| Validation Error Container
|--------------------------------------------------------------------------
*/

    .validation-error {

        display: block;

        width: 100%;

        min-height: 0;

    }


    /*
|--------------------------------------------------------------------------
| Validation Message
|--------------------------------------------------------------------------
*/

    .dn-validation-error {

        display: block !important;

        width: 100% !important;

        margin-top: 4px !important;

        margin-bottom: 0 !important;

        color: #ef4444 !important;

        font-size: 14px !important;

        line-height: 20px !important;

        clear: both !important;

    }


    /*
|--------------------------------------------------------------------------
| Invalid Field
|--------------------------------------------------------------------------
*/

    .dn-validation-error~* {
        box-sizing: border-box;
    }


    /*
|--------------------------------------------------------------------------
| Red Border
|--------------------------------------------------------------------------
*/

    .border-red-500 {

        border-color: #ef4444 !important;

    }
</style>


<?php require_once '../../../includes/footer.php'; ?>