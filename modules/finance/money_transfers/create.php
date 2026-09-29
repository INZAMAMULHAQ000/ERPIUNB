<?php

$pageTitle = 'New Money Transfer';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/finance_helpers.php';
require_once '../helpers/banking_helpers.php';

if (!hasPermission('finance.transfer.create')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}


/*
|--------------------------------------------------------------------------
| Load Active Bank Accounts
|--------------------------------------------------------------------------
*/

$bankAccounts = $db->query("
    SELECT
        ba.id,
        ba.account_name,
        ba.bank_name,
        ba.status,
        ba.chart_account_id,
        coa.id AS coa_id
    FROM bank_accounts ba
    LEFT JOIN chart_of_accounts coa
        ON ba.chart_account_id = coa.id
    WHERE ba.status = 'active'
    ORDER BY ba.account_name
")->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Load Active Cash Accounts
|--------------------------------------------------------------------------
*/

$cashAccounts = $db->query("
    SELECT
        ca.id,
        ca.cash_name,
        ca.location,
        ca.status,
        ca.chart_account_id,
        coa.id AS coa_id
    FROM cash_accounts ca
    LEFT JOIN chart_of_accounts coa
        ON ca.chart_account_id = coa.id
    WHERE ca.status = 'active'
    ORDER BY ca.cash_name
")->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Active Financial Year
|--------------------------------------------------------------------------
*/

$activeFY = getActiveFinancialYear($db);


/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * CSRF
     */
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    }


    /*
     * Financial Year
     */
    if (!$activeFY) {
        $errors[] = 'No active financial year.';
    }


    /*
     * Form Values
     */
    $from_type = strtolower(trim($_POST['from_type'] ?? 'bank'));
    $to_type   = strtolower(trim($_POST['to_type'] ?? 'bank'));

    $from_id = (int) ($_POST['from_id'] ?? 0);
    $to_id   = (int) ($_POST['to_id'] ?? 0);

    $amount = (float) str_replace(
        ',',
        '',
        trim($_POST['amount'] ?? '0')
    );

    $transfer_date = trim(
        $_POST['transfer_date'] ?? ''
    );

    $description = trim(
        $_POST['description'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if (!in_array($from_type, ['bank', 'cash'], true)) {
        $errors[] = 'Invalid source account type.';
    }

    if (!in_array($to_type, ['bank', 'cash'], true)) {
        $errors[] = 'Invalid destination account type.';
    }


    if ($from_id <= 0) {
        $errors[] = 'Select source account.';
    }


    if ($to_id <= 0) {
        $errors[] = 'Select destination account.';
    }


    if (
        $from_id > 0 &&
        $to_id > 0 &&
        $from_id === $to_id &&
        $from_type === $to_type
    ) {
        $errors[] =
            'Source and destination accounts must be different.';
    }


    if ($amount <= 0) {
        $errors[] =
            'Amount must be greater than zero.';
    }


    if ($amount > 999999999999) {
        $errors[] =
            'Amount is too large.';
    }


    if ($transfer_date === '') {
        $errors[] =
            'Transfer date is required.';
    } else {

        $dateObject = DateTime::createFromFormat(
            'Y-m-d',
            $transfer_date
        );

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $transfer_date
        ) {
            $errors[] =
                'Invalid transfer date.';
        }
    }


    if (strlen($description) > 1000) {
        $errors[] =
            'Description cannot exceed 1000 characters.';
    }


    /*
    |--------------------------------------------------------------------------
    | Stop Before Transaction
    |--------------------------------------------------------------------------
    */

    if (!empty($errors)) {

        saveFormState($errors);

        to('modules/finance/money_transfers/create.php');

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Database Transaction
    |--------------------------------------------------------------------------
    */

    try {

        $db->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | Resolve Source Account
        |--------------------------------------------------------------------------
        */

        if ($from_type === 'bank') {

            $stmt = $db->prepare("
                SELECT
                    id,
                    account_name,
                    status,
                    chart_account_id
                FROM bank_accounts
                WHERE id = ?
                  AND status = 'active'
                LIMIT 1
            ");

            $stmt->execute([
                $from_id
            ]);

            $fromRow = $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$fromRow) {

                throw new Exception(
                    'Source bank account not found or inactive.'
                );
            }


            if (
                empty($fromRow['chart_account_id']) ||
                (int) $fromRow['chart_account_id'] <= 0
            ) {

                throw new Exception(
                    'Source bank account is not linked to a Chart of Account.'
                );
            }


            $fromChartId =
                (int) $fromRow['chart_account_id'];
        } elseif ($from_type === 'cash') {

            $stmt = $db->prepare("
                SELECT
                    id,
                    cash_name,
                    status,
                    chart_account_id
                FROM cash_accounts
                WHERE id = ?
                  AND status = 'active'
                LIMIT 1
            ");

            $stmt->execute([
                $from_id
            ]);

            $fromRow = $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$fromRow) {

                throw new Exception(
                    'Source cash account not found or inactive.'
                );
            }


            if (
                empty($fromRow['chart_account_id']) ||
                (int) $fromRow['chart_account_id'] <= 0
            ) {

                throw new Exception(
                    'Source cash account is not linked to a Chart of Account.'
                );
            }


            $fromChartId =
                (int) $fromRow['chart_account_id'];
        } else {

            throw new Exception(
                'Invalid source account type.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Resolve Destination Account
        |--------------------------------------------------------------------------
        */

        if ($to_type === 'bank') {

            $stmt = $db->prepare("
                SELECT
                    id,
                    account_name,
                    status,
                    chart_account_id
                FROM bank_accounts
                WHERE id = ?
                  AND status = 'active'
                LIMIT 1
            ");

            $stmt->execute([
                $to_id
            ]);

            $toRow = $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$toRow) {

                throw new Exception(
                    'Destination bank account not found or inactive.'
                );
            }


            if (
                empty($toRow['chart_account_id']) ||
                (int) $toRow['chart_account_id'] <= 0
            ) {

                throw new Exception(
                    'Destination bank account is not linked to a Chart of Account.'
                );
            }


            $toChartId =
                (int) $toRow['chart_account_id'];
        } elseif ($to_type === 'cash') {

            $stmt = $db->prepare("
                SELECT
                    id,
                    cash_name,
                    status,
                    chart_account_id
                FROM cash_accounts
                WHERE id = ?
                  AND status = 'active'
                LIMIT 1
            ");

            $stmt->execute([
                $to_id
            ]);

            $toRow = $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$toRow) {

                throw new Exception(
                    'Destination cash account not found or inactive.'
                );
            }


            if (
                empty($toRow['chart_account_id']) ||
                (int) $toRow['chart_account_id'] <= 0
            ) {

                throw new Exception(
                    'Destination cash account is not linked to a Chart of Account.'
                );
            }


            $toChartId =
                (int) $toRow['chart_account_id'];
        } else {

            throw new Exception(
                'Invalid destination account type.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Same Chart of Account
        |--------------------------------------------------------------------------
        */

        if ($fromChartId === $toChartId) {

            throw new Exception(
                'Source and destination accounts are linked to the same Chart of Account.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Verify Source Chart Account Exists
        |--------------------------------------------------------------------------
        */

        $coaStmt = $db->prepare("
            SELECT id
            FROM chart_of_accounts
            WHERE id = ?
            LIMIT 1
        ");

        $coaStmt->execute([
            $fromChartId
        ]);

        if (!$coaStmt->fetchColumn()) {

            throw new Exception(
                'Source Chart of Account not found.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Verify Destination Chart Account Exists
        |--------------------------------------------------------------------------
        */

        $coaStmt->execute([
            $toChartId
        ]);

        if (!$coaStmt->fetchColumn()) {

            throw new Exception(
                'Destination Chart of Account not found.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Contra Voucher Type
        |--------------------------------------------------------------------------
        */

        $vtStmt = $db->prepare("
            SELECT id
            FROM voucher_types
            WHERE voucher_prefix = 'CV'
              AND status = 'active'
            LIMIT 1
        ");

        $vtStmt->execute();

        $vt = $vtStmt->fetch(PDO::FETCH_ASSOC);


        if (!$vt) {

            throw new Exception(
                "Contra Voucher type 'CV' not found or inactive."
            );
        }


        $voucherTypeId =
            (int) $vt['id'];


        /*
        |--------------------------------------------------------------------------
        | Generate Voucher Number
        |--------------------------------------------------------------------------
        */

        $voucherNo =
            generateVoucherNo(
                $voucherTypeId,
                $db
            );


        /*
        |--------------------------------------------------------------------------
        | Create Journal Entry
        |--------------------------------------------------------------------------
        */

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
                created_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                'draft',
                ?,
                NOW()
            )
        ");

        $stmt->execute([
            $voucherNo,
            $voucherTypeId,
            $transfer_date,
            'Transfer',
            $description,
            $_SESSION['user_id']
        ]);


        $journalId =
            (int) $db->lastInsertId();


        if ($journalId <= 0) {

            throw new Exception(
                'Failed to create journal entry.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Journal Entry Details
        |--------------------------------------------------------------------------
        */

        $detailStmt = $db->prepare("
            INSERT INTO journal_entry_details
            (
                journal_entry_id,
                account_id,
                debit_amount,
                credit_amount,
                description
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");


        /*
         * Debit Destination
         */
        $detailStmt->execute([
            $journalId,
            $toChartId,
            $amount,
            0,
            $description ?: 'Transfer - To Account'
        ]);


        /*
         * Credit Source
         */
        $detailStmt->execute([
            $journalId,
            $fromChartId,
            0,
            $amount,
            $description ?: 'Transfer - From Account'
        ]);


        /*
        |--------------------------------------------------------------------------
        | Posting Engine
        |--------------------------------------------------------------------------
        */

        require_once __DIR__ .
            '/../posting_engine/PostingEngine.php';


        $result = PostingEngine::post(
            $journalId,
            $_SESSION['user_id']
        );


        if (
            !is_array($result) ||
            empty($result['success'])
        ) {

            throw new Exception(
                $result['message'] ??
                    'Posting Engine failed.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Generate Transfer Reference
        |--------------------------------------------------------------------------
        */

        $refStmt = $db->query("
            SELECT COUNT(*)
            FROM money_transfers
        ");

        $refCount =
            (int) $refStmt->fetchColumn() + 1;


        $reference_number =
            'TRF-' .
            date('ymd') .
            '-' .
            str_pad(
                $refCount,
                3,
                '0',
                STR_PAD_LEFT
            );


        /*
        |--------------------------------------------------------------------------
        | Save Money Transfer
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            INSERT INTO money_transfers
            (
                from_account_type,
                from_account_id,
                to_account_type,
                to_account_id,
                amount,
                transfer_date,
                reference_number,
                description,
                journal_entry_id,
                status
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
                ?,
                ?,
                'posted'
            )
        ");


        $stmt->execute([
            $from_type,
            $from_id,
            $to_type,
            $to_id,
            $amount,
            $transfer_date,
            $reference_number,
            $description,
            $journalId
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
            'Transfer: ' .
                $reference_number .
                ' (' .
                $from_type .
                '→' .
                $to_type .
                ', Voucher: ' .
                $voucherNo .
                ')',
            'finance'
        );


        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */

        flash(
            'success',
            'Transfer of ' .
                formatNumber($amount) .
                ' completed successfully. Voucher: ' .
                $voucherNo
        );


        to(
            'modules/finance/money_transfers/index.php'
        );

        exit;
    } catch (Exception $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }


        $errors[] =
            'Transfer failed: ' .
            $e->getMessage();
    }
}

?>

<div class="mb-6">

    <a
        href="index.php"
        class="text-blue-600 hover:text-blue-900">

        <i class="fas fa-arrow-left mr-1"></i>

        Back to Money Transfers

    </a>

</div>


<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">
        New Money Transfer
    </h2>


    <?php if ($errors): ?>

        <div
            class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">

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
        id="createMoneyTransferForm"
        novalidate
        class="space-y-4">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                        csrfToken(),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>">


        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">


            <!-- FROM TYPE -->

            <div>

                <label
                    class="block text-sm font-medium text-gray-700 mb-1">

                    From Account Type
                    <span class="text-red-600">*</span>

                </label>


                <select
                    name="from_type"
                    id="from_type"
                    class="w-full px-3 py-2 border rounded">

                    <option
                        value="bank"
                        <?= old(
                            'from_type',
                            'bank'
                        ) === 'bank'
                            ? 'selected'
                            : '' ?>>

                        Bank Account

                    </option>


                    <option
                        value="cash"
                        <?= old(
                            'from_type'
                        ) === 'cash'
                            ? 'selected'
                            : '' ?>>

                        Cash Account

                    </option>

                </select>

            </div>


            <!-- FROM ACCOUNT -->

            <div>

                <label
                    class="block text-sm font-medium text-gray-700 mb-1">

                    From Account
                    <span class="text-red-600">*</span>

                </label>


                <select
                    name="from_id"
                    id="from_id"
                    class="w-full px-3 py-2 border rounded">

                    <option value="">
                        Select Source
                    </option>


                    <?php foreach ($bankAccounts as $ba): ?>

                        <option
                            value="<?= (int) $ba['id'] ?>"
                            data-type="bank"
                            <?= old('from_id') == $ba['id'] &&
                                old(
                                    'from_type',
                                    'bank'
                                ) === 'bank'
                                ? 'selected'
                                : '' ?>>

                            <?= sanitize(
                                $ba['account_name'] .
                                    ' - ' .
                                    $ba['bank_name']
                            ) ?>

                        </option>

                    <?php endforeach; ?>


                    <?php foreach ($cashAccounts as $ca): ?>

                        <option
                            value="<?= (int) $ca['id'] ?>"
                            data-type="cash"
                            <?= old('from_id') == $ca['id'] &&
                                old(
                                    'from_type'
                                ) === 'cash'
                                ? 'selected'
                                : '' ?>>

                            <?= sanitize(
                                $ca['cash_name']
                            ) ?>

                            <?php if (!empty($ca['location'])): ?>

                                (<?= sanitize(
                                        $ca['location']
                                    ) ?>)

                            <?php endif; ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- TO TYPE -->

            <div>

                <label
                    class="block text-sm font-medium text-gray-700 mb-1">

                    To Account Type
                    <span class="text-red-600">*</span>

                </label>


                <select
                    name="to_type"
                    id="to_type"
                    class="w-full px-3 py-2 border rounded">

                    <option
                        value="bank"
                        <?= old(
                            'to_type',
                            'bank'
                        ) === 'bank'
                            ? 'selected'
                            : '' ?>>

                        Bank Account

                    </option>


                    <option
                        value="cash"
                        <?= old(
                            'to_type'
                        ) === 'cash'
                            ? 'selected'
                            : '' ?>>

                        Cash Account

                    </option>

                </select>

            </div>


            <!-- TO ACCOUNT -->

            <div>

                <label
                    class="block text-sm font-medium text-gray-700 mb-1">

                    To Account
                    <span class="text-red-600">*</span>

                </label>


                <select
                    name="to_id"
                    id="to_id"
                    class="w-full px-3 py-2 border rounded">

                    <option value="">
                        Select Destination
                    </option>


                    <?php foreach ($bankAccounts as $ba): ?>

                        <option
                            value="<?= (int) $ba['id'] ?>"
                            data-type="bank"
                            <?= old('to_id') == $ba['id'] &&
                                old(
                                    'to_type',
                                    'bank'
                                ) === 'bank'
                                ? 'selected'
                                : '' ?>>

                            <?= sanitize(
                                $ba['account_name'] .
                                    ' - ' .
                                    $ba['bank_name']
                            ) ?>

                        </option>

                    <?php endforeach; ?>


                    <?php foreach ($cashAccounts as $ca): ?>

                        <option
                            value="<?= (int) $ca['id'] ?>"
                            data-type="cash"
                            <?= old('to_id') == $ca['id'] &&
                                old(
                                    'to_type'
                                ) === 'cash'
                                ? 'selected'
                                : '' ?>>

                            <?= sanitize(
                                $ca['cash_name']
                            ) ?>

                            <?php if (!empty($ca['location'])): ?>

                                (<?= sanitize(
                                        $ca['location']
                                    ) ?>)

                            <?php endif; ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- AMOUNT -->

            <div>

                <label
                    class="block text-sm font-medium text-gray-700 mb-1">

                    Amount (AED)
                    <span class="text-red-600">*</span>

                </label>


                <input
                    type="text"
                    name="amount"
                    value="<?= old('amount') ?>"
                    maxlength="15"
                    class="w-full px-3 py-2 border rounded"
                    placeholder="0.00"
                    autocomplete="off">

            </div>


            <!-- TRANSFER DATE -->

            <div>

                <label
                    class="block text-sm font-medium text-gray-700 mb-1">

                    Transfer Date
                    <span class="text-red-600">*</span>

                </label>


                <input
                    type="date"
                    name="transfer_date"
                    value="<?= old(
                                'transfer_date',
                                date('Y-m-d')
                            ) ?>"
                    class="w-full px-3 py-2 border rounded">

            </div>


            <!-- DESCRIPTION -->

            <div class="md:col-span-2">

                <label
                    class="block text-sm font-medium text-gray-700 mb-1">

                    Description

                </label>


                <textarea
                    name="description"
                    rows="2"
                    maxlength="1000"
                    class="w-full px-3 py-2 border rounded"><?= old(
                                                                'description'
                                                            ) ?></textarea>

            </div>

        </div>


        <div class="pt-4 border-t">

            <p class="text-sm text-gray-500 mb-3">

                <i class="fas fa-info-circle mr-1"></i>

                This creates a Contra Voucher (CV) —
                debiting the destination and crediting the source.
                All money movement goes through the Posting Engine.

            </p>


            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">

                <i class="fas fa-save mr-2"></i>

                Complete Transfer

            </button>


            <a
                href="index.php"
                class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">

                Cancel

            </a>

        </div>

    </form>

</div>


<script>
    /*
|--------------------------------------------------------------------------
| Account Dropdown
|--------------------------------------------------------------------------
*/

    function toggleAccounts() {

        const fromType =
            document.querySelector(
                'select[name="from_type"]'
            ).value;

        const toType =
            document.querySelector(
                'select[name="to_type"]'
            ).value;


        const fromSelect =
            document.querySelector(
                'select[name="from_id"]'
            );

        const toSelect =
            document.querySelector(
                'select[name="to_id"]'
            );


        /*
         * Filter Source
         */
        fromSelect.querySelectorAll(
            'option'
        ).forEach(function(option) {

            if (option.value === '') {

                option.hidden = false;

                return;
            }


            option.hidden =
                option.dataset.type !== fromType;

        });


        /*
         * Filter Destination
         */
        toSelect.querySelectorAll(
            'option'
        ).forEach(function(option) {

            if (option.value === '') {

                option.hidden = false;

                return;
            }


            option.hidden =
                option.dataset.type !== toType;

        });


        /*
         * Clear Invalid Source
         */
        const selectedFrom =
            fromSelect.options[
                fromSelect.selectedIndex
            ];


        if (
            selectedFrom &&
            selectedFrom.value !== '' &&
            selectedFrom.dataset.type !== fromType
        ) {

            fromSelect.value = '';

        }


        /*
         * Clear Invalid Destination
         */
        const selectedTo =
            toSelect.options[
                toSelect.selectedIndex
            ];


        if (
            selectedTo &&
            selectedTo.value !== '' &&
            selectedTo.dataset.type !== toType
        ) {

            toSelect.value = '';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | DOM Ready
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'DOMContentLoaded',
        function() {

            toggleAccounts();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | jQuery
    |--------------------------------------------------------------------------
    */
</script>


<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>


<script>
    $(document).ready(function() {


        /*
        |--------------------------------------------------------------------------
        | Account Type Change
        |--------------------------------------------------------------------------
        */

        $('select[name="from_type"], select[name="to_type"]')
            .on('change', function() {

                toggleAccounts();

                /*
                 * Revalidate account fields
                 */
                $('select[name="from_id"]').valid();
                $('select[name="to_id"]').valid();

            });


        /*
        |--------------------------------------------------------------------------
        | Amount Input
        |--------------------------------------------------------------------------
        */

        $('input[name="amount"]').on(
            'input',
            function() {

                let value = $(this).val();


                /*
                 * Remove invalid characters
                 */
                value =
                    value.replace(
                        /[^0-9.]/g,
                        ''
                    );


                /*
                 * Only one decimal point
                 */
                const parts =
                    value.split('.');


                if (parts.length > 2) {

                    value =
                        parts[0] +
                        '.' +
                        parts
                        .slice(1)
                        .join('');

                }


                /*
                 * Maximum 2 decimals
                 */
                if (value.includes('.')) {

                    const decimalParts =
                        value.split('.');


                    value =
                        decimalParts[0] +
                        '.' +
                        decimalParts[1]
                        .substring(0, 2);

                }


                $(this).val(value);

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Description
        |--------------------------------------------------------------------------
        */

        $('textarea[name="description"]').on(
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
        | jQuery Validation
        |--------------------------------------------------------------------------
        */

        $('#createMoneyTransferForm').validate({

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
                    .addClass(
                        'border-red-500'
                    );

            },


            unhighlight: function(element) {

                $(element)
                    .removeClass(
                        'border-red-500'
                    );

            },


            rules: {

                from_type: {

                    required: true

                },


                from_id: {

                    required: true,

                    digits: true,

                    min: 1

                },


                to_type: {

                    required: true

                },


                to_id: {

                    required: true,

                    digits: true,

                    min: 1

                },


                amount: {

                    required: true,

                    number: true,

                    min: 0.01

                },


                transfer_date: {

                    required: true,

                    date: true

                },


                description: {

                    maxlength: 1000

                }

            },


            messages: {

                from_type: {

                    required: 'Please select the source account type.'

                },


                from_id: {

                    required: 'Please select the source account.',

                    digits: 'Invalid source account.',

                    min: 'Please select the source account.'

                },


                to_type: {

                    required: 'Please select the destination account type.'

                },


                to_id: {

                    required: 'Please select the destination account.',

                    digits: 'Invalid destination account.',

                    min: 'Please select the destination account.'

                },


                amount: {

                    required: 'Please enter the transfer amount.',

                    number: 'Please enter a valid amount.',

                    min: 'Amount must be greater than zero.'

                },


                transfer_date: {

                    required: 'Please select the transfer date.',

                    date: 'Please enter a valid date.'

                },


                description: {

                    maxlength: 'Description cannot exceed 1000 characters.'

                }

            }

        });

    });
</script>


<?php require_once '../../../includes/footer.php'; ?>