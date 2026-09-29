<?php
$pageTitle = 'Create Credit Note';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../tax/helpers/tax_ui.php';

if (!hasPermission('credit_notes.create')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

// Generate CN number: CN + YYYYMM + sequential
$prefix = 'CN' . date('Ym');

$stmt = $db->prepare("SELECT COUNT(*) FROM credit_notes WHERE credit_note_number LIKE ?");
$stmt->execute([$prefix . '%']);

$count = (int)$stmt->fetchColumn();

$credit_note_number = $prefix . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);

// Load clients
$clients = $db->query("
    SELECT id, client_code, company_name
    FROM clients
    WHERE status = 'active'
    ORDER BY company_name
")->fetchAll();

// If client_id is provided via GET, load their invoices
$selected_client_id = (int)($_GET['client_id'] ?? old('client_id', 0));

$clientInvoices = [];

if ($selected_client_id > 0) {

    $invStmt = $db->prepare("
        SELECT
            id,
            invoice_number,
            invoice_date,
            total_amount,
            tax_amount,
            paid_amount,
            balance_amount,
            status
        FROM customer_invoices
        WHERE client_id = ?
        AND status NOT IN ('cancelled')
        ORDER BY invoice_date DESC
    ");

    $invStmt->execute([$selected_client_id]);

    $clientInvoices = $invStmt->fetchAll();
}

$creditNoteTypes = [
    'price_correction'   => 'Price Correction',
    'return'              => 'Return',
    'discount_adjustment' => 'Discount Adjustment',
    'cancellation'       => 'Cancellation',
];

// Default tax type / rate for the document-level picker
$defaultType = TaxService::getDefaultTaxType();

$defaultTypeId = $defaultType
    ? (int)$defaultType['id']
    : 0;

$defRate = TaxService::getDefaultTaxRate($defaultTypeId);

$defaultRateId = $defRate
    ? (int)$defRate['id']
    : 0;


// --- POST Handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid or expired form token. Please try again.';
    }

    $client_id = (int)($_POST['client_id'] ?? 0);

    $invoice_id = !empty($_POST['invoice_id'])
        ? (int)$_POST['invoice_id']
        : null;

    $credit_note_date = trim(
        $_POST['credit_note_date'] ?? ''
    );

    $credit_note_type = trim(
        $_POST['credit_note_type'] ?? ''
    );

    $reason = trim(
        $_POST['reason'] ?? ''
    );

    $subtotalInput = (float)str_replace(
        ',',
        '',
        $_POST['subtotal'] ?? 0
    );

    // Tax engine inputs
    $tax_rate_id = (int)(
        $_POST['tax']['tax_rate_id'] ?? 0
    );

    $tax_status =
        $_POST['tax']['tax_status'] ?? 'taxable';

    $exemption_id = (int)(
        $_POST['tax']['tax_exemption_id'] ?? 0
    );

    // Validate
    if ($client_id <= 0) {
        $errors[] = 'Please select a client.';
    }

    if (empty($credit_note_date)) {
        $errors[] = 'Credit note date is required.';
    }

    if (empty($credit_note_type)) {
        $errors[] = 'Credit note type is required.';
    }

    if (empty($reason)) {
        $errors[] = 'Reason is required.';
    }

    if (
        $credit_note_type === 'cancellation'
        && !$invoice_id
    ) {
        $errors[] =
            'Please select an invoice for cancellation.';
    }

    if ($subtotalInput <= 0) {
        $errors[] =
            'Subtotal must be greater than zero.';
    }

    if (!empty($errors)) {

        saveFormState($errors);

        to(
            'modules/finance/credit_notes/create.php' .
                (
                    $selected_client_id
                    ? '?client_id=' . $selected_client_id
                    : ''
                )
        );
    }

    try {

        // Compute document tax through centralized engine
        $calc = TaxService::calculateLineTax(
            $subtotalInput,
            [
                'tax_rate_id'  => $tax_rate_id ?: null,
                'tax_status'   => $tax_status,
                'exemption_id' => $exemption_id ?: null,
                'date'         => $credit_note_date,
            ]
        );

        $subtotal =
            (float)$calc['net_amount'];

        $tax_amount =
            (float)$calc['tax_amount'];

        $total_amount =
            (float)$calc['total_amount'];

        $db->beginTransaction();

        // 1. Insert credit note
        $stmt = $db->prepare("
            INSERT INTO credit_notes
                (
                    credit_note_number,
                    invoice_id,
                    client_id,
                    credit_note_date,
                    credit_note_type,
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
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, NOW(), NOW())
        ");

        $stmt->execute([
            $credit_note_number,
            $invoice_id,
            $client_id,
            $credit_note_date,
            $credit_note_type,
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

        $credit_note_id =
            (int)$db->lastInsertId();


        // 2. Create journal entry
        require_once '../posting_engine/PostingEngine.php';

        // Get accounts from settings
        $settingsStmt = $db->query("
            SELECT
                setting_key,
                setting_value
            FROM settings
            WHERE setting_key IN
                (
                    'finance_sales_return_account_id',
                    'finance_ar_account_id'
                )
        ");

        $settings = [];

        foreach ($settingsStmt->fetchAll() as $row) {

            $settings[$row['setting_key']] =
                (int)$row['setting_value'];
        }

        $sales_return_account_id =
            $settings['finance_sales_return_account_id'] ?? 0;

        $ar_account_id =
            $settings['finance_ar_account_id'] ?? 0;


        if (
            $sales_return_account_id
            && $ar_account_id
        ) {

            // Get Credit Note voucher type
            $vtStmt = $db->prepare("
                SELECT id
                FROM voucher_types
                WHERE voucher_name = 'Credit Note'
                AND status = 'active'
                LIMIT 1
            ");

            $vtStmt->execute();

            $voucher_type_id =
                (int)$vtStmt->fetchColumn();


            if ($voucher_type_id > 0) {

                $voucher_no =
                    generateVoucherNo(
                        $voucher_type_id,
                        $db
                    );


                // Create journal entry as draft
                $stmt = $db->prepare("
                    INSERT INTO journal_entries
                        (
                            voucher_no,
                            voucher_type_id,
                            voucher_date,
                            reference_no,
                            description,
                            status,
                            created_by,
                            created_at,
                            updated_at
                        )
                    VALUES
                        (?, ?, ?, ?, ?, 'draft', ?, NOW(), NOW())
                ");

                $stmt->execute([
                    $voucher_no,
                    $voucher_type_id,
                    $credit_note_date,
                    $credit_note_number,
                    'Credit note: ' .
                        $credit_note_number .
                        ' - ' .
                        (
                            $creditNoteTypes[$credit_note_type]
                            ?? $credit_note_type
                        ),
                    $_SESSION['user_id'],
                ]);

                $journal_entry_id =
                    (int)$db->lastInsertId();


                // DR: Sales Returns
                $stmt = $db->prepare("
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

                $stmt->execute([
                    $journal_entry_id,
                    $sales_return_account_id,
                    $subtotal,
                    0,
                    'Credit note - ' .
                        $credit_note_number
                ]);


                // DR: Output Tax
                if ($tax_amount > 0) {

                    $outputTaxAccountId =
                        (int)TaxService::config(
                            'output_tax_account_id',
                            0
                        );

                    if ($outputTaxAccountId <= 0) {

                        throw new Exception(
                            'Output Tax account not configured. Go to Tax Settings to set it up.'
                        );
                    }

                    $stmt->execute([
                        $journal_entry_id,
                        $outputTaxAccountId,
                        $tax_amount,
                        0,
                        'Output Tax - ' .
                            $credit_note_number
                    ]);
                }


                // CR: AR account
                $stmt->execute([
                    $journal_entry_id,
                    $ar_account_id,
                    0,
                    $total_amount,
                    'Accounts Receivable - ' .
                        $credit_note_number
                ]);


                // Register output tax reduction
                if (
                    (int)$calc['tax_type_id']
                    && $subtotal > 0
                ) {

                    $ratePct =
                        (float)$calc['rate_percentage'];

                    TaxService::recordDocumentTax([
                        'source_module' =>
                        'credit_note',

                        'source_record_id' =>
                        $credit_note_id,

                        'source_document_no' =>
                        $credit_note_number,

                        'transaction_date' =>
                        $credit_note_date,

                        'tax_type_id' =>
                        (int)$calc['tax_type_id'],

                        'direction' =>
                        'output',

                        'counterparty_type' =>
                        'customer',

                        'counterparty_id' =>
                        $client_id,

                        'reference' =>
                        'Credit note reducing output tax',

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


                // Update credit note with journal entry id
                $stmt = $db->prepare("
                    UPDATE credit_notes
                    SET
                        journal_entry_id = ?,
                        status = 'issued'
                    WHERE id = ?
                ");

                $stmt->execute([
                    $journal_entry_id,
                    $credit_note_id
                ]);


                // Post via PostingEngine
                $result =
                    PostingEngine::post(
                        $journal_entry_id,
                        $_SESSION['user_id']
                    );

                if (!$result['success']) {

                    throw new Exception(
                        'Posting failed: ' .
                            $result['message']
                    );
                }


                // 3. If linked to invoice, update invoice balance
                if ($invoice_id) {

                    $invStmt = $db->prepare("
                        SELECT
                            total_amount,
                            paid_amount,
                            balance_amount,
                            status
                        FROM customer_invoices
                        WHERE id = ?
                        FOR UPDATE
                    ");

                    $invStmt->execute([
                        $invoice_id
                    ]);

                    $inv = $invStmt->fetch();

                    if ($inv) {

                        // Credit note reduces invoice total and balance
                        $new_total = max(
                            0,
                            (float)$inv['total_amount']
                                - $total_amount
                        );

                        $new_balance = max(
                            0,
                            (float)$inv['balance_amount']
                                - $total_amount
                        );

                        $new_status =
                            $inv['status'];

                        if ($new_balance <= 0) {

                            $new_status = 'paid';
                            $new_balance = 0;
                        }

                        $updateStmt = $db->prepare("
                            UPDATE customer_invoices
                            SET
                                total_amount = ?,
                                balance_amount = ?,
                                status = ?,
                                updated_at = NOW()
                            WHERE id = ?
                        ");

                        $updateStmt->execute([
                            $new_total,
                            $new_balance,
                            $new_status,
                            $invoice_id
                        ]);
                    }
                }
            } else {

                // Voucher type not found
                $stmt = $db->prepare("
                    UPDATE credit_notes
                    SET status = 'issued'
                    WHERE id = ?
                ");

                $stmt->execute([
                    $credit_note_id
                ]);

                error_log(
                    'Credit Note voucher type not found - journal not created for CN ' .
                        $credit_note_number
                );
            }
        } else {

            // Missing account configuration
            $stmt = $db->prepare("
                UPDATE credit_notes
                SET status = 'issued'
                WHERE id = ?
            ");

            $stmt->execute([
                $credit_note_id
            ]);

            error_log(
                'Missing sales return or AR account settings - journal not created for CN ' .
                    $credit_note_number
            );
        }


        $db->commit();


        logAudit(
            $_SESSION['user_id'],
            'Created credit note: ' .
                $credit_note_number .
                ' (Amount: ' .
                $total_amount .
                ')',
            'credit_notes'
        );

        flash(
            'success',
            'Credit note created and posted successfully. CN #' .
                $credit_note_number,
            'success'
        );

        redirect('index.php');
    } catch (Exception $e) {

        $db->rollBack();

        flash(
            'error',
            'Error creating credit note: ' .
                $e->getMessage()
        );

        to(
            'modules/finance/credit_notes/create.php' .
                (
                    $selected_client_id
                    ? '?client_id=' . $selected_client_id
                    : ''
                )
        );
    }
}
?>

<div class="mb-6 flex justify-between items-center">

    <div>

        <h1 class="text-2xl font-bold text-gray-800">
            Create Credit Note
        </h1>

        <p class="text-gray-600">
            Create a credit note for a customer
        </p>

    </div>

    <a href="index.php"
        class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">

        <i class="fas fa-arrow-left mr-2"></i>
        Back to Credit Notes

    </a>

</div>


<?php if (!empty($errors)): ?>

    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">

        <ul class="list-disc pl-5">

            <?php foreach ($errors as $err): ?>

                <li><?= sanitize($err) ?></li>

            <?php endforeach; ?>

        </ul>

    </div>

<?php endif; ?>


<div class="bg-white rounded-lg shadow p-6">

    <form method="POST"
        id="creditNoteForm"
        class="space-y-4"
        novalidate>

        <input type="hidden"
            name="csrf_token"
            value="<?= csrfToken() ?>">


        <!-- Header Row -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Credit Note Number
                </label>

                <input type="text"
                    value="<?= sanitize($credit_note_number) ?>"
                    readonly
                    class="w-full px-3 py-2 border rounded bg-gray-100 text-gray-600">

            </div>


            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Credit Note Date
                    <span class="text-red-500">*</span>
                </label>

                <input type="date"
                    name="credit_note_date"
                    value="<?= old('credit_note_date', date('Y-m-d')) ?>"
                    required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

            </div>


            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Client
                    <span class="text-red-500">*</span>
                </label>

                <select name="client_id"
                    id="clientSelect"
                    required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    onchange="this.form.submit()">

                    <option value="">
                        Select Client
                    </option>

                    <?php foreach ($clients as $c): ?>

                        <option value="<?= $c['id'] ?>"
                            <?= $selected_client_id == $c['id']
                                ? 'selected'
                                : '' ?>>

                            <?= sanitize($c['company_name']) ?>
                            (<?= sanitize($c['client_code']) ?>)

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Credit Note Type
                    <span class="text-red-500">*</span>
                </label>

                <select name="credit_note_type"
                    id="cnType"
                    required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    onchange="toggleAmountFields()">

                    <option value="">
                        Select Type
                    </option>

                    <?php foreach ($creditNoteTypes as $val => $label): ?>

                        <option value="<?= $val ?>"
                            <?= old('credit_note_type') === $val
                                ? 'selected'
                                : '' ?>>

                            <?= $label ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>


        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Invoice (Optional for cancellations)
                </label>

                <select name="invoice_id"
                    id="invoiceSelect"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    onchange="autoFillCancellation()">

                    <option value="">
                        Select Invoice
                    </option>

                    <?php foreach ($clientInvoices as $inv): ?>

                        <option value="<?= $inv['id'] ?>"
                            data-total="<?= (float)$inv['total_amount'] ?>"
                            data-net="<?= max(
                                            0,
                                            (float)$inv['total_amount']
                                                - (float)$inv['tax_amount']
                                        ) ?>"
                            <?= old('invoice_id') == $inv['id']
                                ? 'selected'
                                : '' ?>>

                            <?= sanitize($inv['invoice_number']) ?>
                            -
                            <?= formatMoney(
                                (float)$inv['total_amount']
                            ) ?>

                            (
                            <?= ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $inv['status']
                                )
                            ) ?>
                            )

                        </option>

                    <?php endforeach; ?>

                </select>


                <?php if (
                    $selected_client_id > 0
                    && empty($clientInvoices)
                ): ?>

                    <p class="text-xs text-amber-600 mt-1">
                        This client has no available invoices.
                    </p>

                <?php endif; ?>

            </div>


            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Reason
                    <span class="text-red-500">*</span>
                </label>

                <textarea name="reason"
                    rows="2"
                    maxlength="1000"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    placeholder="Reason for credit note"
                    required><?= old('reason') ?></textarea>

            </div>

        </div>


        <!-- Amount Fields -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4"
            id="amountFields">

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Subtotal (tax base)
                    <span class="text-red-500">*</span>
                </label>

                <input type="number"
                    name="subtotal"
                    id="subtotal"
                    step="0.01"
                    min="0"
                    value="<?= old('subtotal', '0') ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 text-right"
                    oninput="calcNote()">

            </div>


            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Tax Treatment
                </label>

                <div id="cnTaxPicker">

                    <?= taxui_doc_line_picker(
                        'tax',
                        $defaultRateId,
                        $defaultTypeId
                    ) ?>

                </div>

            </div>


            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Tax Amount
                </label>

                <div id="taxDisplay"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 text-right bg-gray-50 font-mono">

                    0.00

                </div>

            </div>


            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Total Amount
                    <span class="text-red-500">*</span>
                </label>

                <input type="number"
                    name="total_amount"
                    id="totalAmount"
                    step="0.01"
                    min="0.01"
                    value="<?= old('total_amount', '0') ?>"
                    required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 text-right font-bold bg-gray-50"
                    readonly>

            </div>

        </div>


        <!-- Submit -->
        <div class="pt-4 border-t">

            <button type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">

                <i class="fas fa-save mr-2"></i>
                Create & Post Credit Note

            </button>

            <a href="index.php"
                class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">

                Cancel

            </a>

        </div>

    </form>

</div>


<script src="../../../tax/helpers/tax_doc.js"></script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- jQuery Validation -->
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>
    window.TAX_INCLUSIVE =
        <?= TaxService::pricesInclusive() ? '1' : '0' ?>;


    function round2(n) {

        return Math.round(
            (n + Number.EPSILON) * 100
        ) / 100;
    }


    function calcNote() {

        const type =
            document.getElementById('cnType').value;

        if (type === 'cancellation') {
            return;
        }

        const base =
            parseFloat(
                document.getElementById('subtotal').value
            ) || 0;

        const rateSel =
            document.querySelector(
                '#cnTaxPicker .tax-doc-rate'
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
                '#cnTaxPicker .tax-doc-status'
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
                'taxDisplay'
            ).textContent =
            round2(tax).toFixed(2);


        document.getElementById(
                'totalAmount'
            ).value =
            round2(total).toFixed(2);


        window.toggleExemptionSelect(
            document.getElementById('cnTaxPicker')
        );
    }


    function toggleAmountFields() {

        const type =
            document.getElementById('cnType').value;

        const isCancellation =
            type === 'cancellation';


        document.getElementById(
                'subtotal'
            ).readOnly =
            isCancellation;


        if (isCancellation) {

            autoFillCancellation();

        } else {

            calcNote();
        }
    }


    function autoFillCancellation() {

        const type =
            document.getElementById('cnType').value;

        if (type !== 'cancellation') {
            return;
        }


        const select =
            document.getElementById('invoiceSelect');

        const option =
            select.options[
                select.selectedIndex
            ];


        if (
            option &&
            option.value
        ) {

            const net =
                parseFloat(
                    option.getAttribute('data-net')
                ) || 0;

            const total =
                parseFloat(
                    option.getAttribute('data-total')
                ) || 0;


            document.getElementById(
                    'subtotal'
                ).value =
                net.toFixed(2);


            document.getElementById(
                    'taxDisplay'
                ).textContent =
                (total - net).toFixed(2);


            document.getElementById(
                    'totalAmount'
                ).value =
                total.toFixed(2);

        } else {

            document.getElementById(
                    'subtotal'
                ).value =
                '0.00';


            document.getElementById(
                    'taxDisplay'
                ).textContent =
                '0.00';


            document.getElementById(
                    'totalAmount'
                ).value =
                '0.00';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | jQuery Validation
    |--------------------------------------------------------------------------
    */

    $(document).ready(function() {


        /*
        |--------------------------------------------------------------------------
        | Reason Input
        |--------------------------------------------------------------------------
        */

        $('textarea[name="reason"]').on(
            'input',
            function() {

                let value =
                    $(this).val();

                value =
                    value.replace(
                        /\s{2,}/g,
                        ' '
                    );

                $(this).val(
                    value.substring(0, 1000)
                );
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Subtotal Input
        |--------------------------------------------------------------------------
        */

        $('input[name="subtotal"]').on(
            'input',
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

                    const decimalParts =
                        value.split('.');

                    value =
                        decimalParts[0] +
                        '.' +
                        decimalParts[1].substring(0, 2);
                }


                $(this).val(value);

                calcNote();
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Custom Cancellation Invoice Validation
        |--------------------------------------------------------------------------
        */

        $.validator.addMethod(
            'requiredForCancellation',
            function(value, element) {

                const type =
                    $('#cnType').val();

                if (type !== 'cancellation') {
                    return true;
                }

                return $.trim(value) !== '';

            },
            'Please select an invoice for cancellation.'
        );


        /*
        |--------------------------------------------------------------------------
        | Credit Note Date Validation
        |--------------------------------------------------------------------------
        */

        $.validator.addMethod(
            'validCreditNoteDate',
            function(value, element) {

                if (!value) {
                    return true;
                }

                return /^\d{4}-\d{2}-\d{2}$/.test(value);

            },
            'Please enter a valid credit note date.'
        );


        /*
        |--------------------------------------------------------------------------
        | Form Validation
        |--------------------------------------------------------------------------
        */

        $('#creditNoteForm').validate({

            errorElement: 'span',

            errorClass: 'text-red-500 text-sm mt-1 block',

            errorPlacement: function(error, element) {

                if (element.is('select')) {

                    element.parent().append(error);

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

                client_id: {
                    required: true,
                    digits: true,
                    min: 1
                },

                credit_note_date: {
                    required: true,
                    dateISO: true,
                    validCreditNoteDate: true
                },

                credit_note_type: {
                    required: true
                },

                invoice_id: {
                    requiredForCancellation: true,
                    digits: true,
                    min: 1
                },

                reason: {
                    required: true,
                    minlength: 2,
                    maxlength: 1000
                },

                subtotal: {
                    required: true,
                    number: true,
                    min: 0.01
                },

                total_amount: {
                    required: true,
                    number: true,
                    min: 0.01
                }
            },


            messages: {

                client_id: {
                    required: 'Please select a client.',
                    digits: 'Invalid client.',
                    min: 'Please select a valid client.'
                },

                credit_note_date: {
                    required: 'Please select the credit note date.',
                    dateISO: 'Please enter a valid credit note date.',
                    validCreditNoteDate: 'Please enter a valid credit note date.'
                },

                credit_note_type: {
                    required: 'Please select a credit note type.'
                },

                invoice_id: {
                    requiredForCancellation: 'Please select an invoice for cancellation.',
                    digits: 'Invalid invoice.',
                    min: 'Please select a valid invoice.'
                },

                reason: {
                    required: 'Please enter the reason.',
                    minlength: 'Reason must be at least 2 characters.',
                    maxlength: 'Reason cannot exceed 1000 characters.'
                },

                subtotal: {
                    required: 'Please enter the subtotal.',
                    number: 'Please enter a valid subtotal.',
                    min: 'Subtotal must be greater than zero.'
                },

                total_amount: {
                    required: 'Total amount is required.',
                    number: 'Please enter a valid total amount.',
                    min: 'Total amount must be greater than zero.'
                }
            },


            submitHandler: function(form) {

                const type =
                    $('#cnType').val();


                /*
                |--------------------------------------------------------------------------
                | Cancellation Invoice Validation
                |--------------------------------------------------------------------------
                */

                if (
                    type === 'cancellation' &&
                    $('#invoiceSelect').val() === ''
                ) {

                    $('#invoiceSelect')
                        .addClass('border-red-500');

                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | Final Total Validation
                |--------------------------------------------------------------------------
                */

                const total =
                    parseFloat(
                        $('#totalAmount').val()
                    ) || 0;


                if (total <= 0) {

                    $('#totalAmount')
                        .addClass('border-red-500');

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
        | Revalidate Cancellation Invoice
        |--------------------------------------------------------------------------
        */

        $('#cnType, #invoiceSelect').on(
            'change',
            function() {

                $('#invoiceSelect').valid();

                $('#subtotal').valid();

                $('#totalAmount').valid();
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Tax Picker Events
        |--------------------------------------------------------------------------
        */

        const taxRate =
            document.querySelector(
                '#cnTaxPicker .tax-doc-rate'
            );

        const taxStatus =
            document.querySelector(
                '#cnTaxPicker .tax-doc-status'
            );

        const taxExemption =
            document.querySelector(
                '#cnTaxPicker .tax-doc-exemption'
            );


        if (taxRate) {

            ['change', 'input'].forEach(
                function(evt) {

                    taxRate.addEventListener(
                        evt,
                        calcNote
                    );
                }
            );
        }


        if (taxStatus) {

            ['change', 'input'].forEach(
                function(evt) {

                    taxStatus.addEventListener(
                        evt,
                        calcNote
                    );
                }
            );
        }


        if (taxExemption) {

            taxExemption.addEventListener(
                'change',
                function() {

                    window.toggleExemptionSelect(
                        document.getElementById(
                            'cnTaxPicker'
                        )
                    );
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Initialize
        |--------------------------------------------------------------------------
        */

        toggleAmountFields();

        const invSelect =
            document.getElementById(
                'invoiceSelect'
            );

        if (invSelect.value) {

            autoFillCancellation();
        }

    });
</script>

<?php require_once '../../../includes/footer.php'; ?>