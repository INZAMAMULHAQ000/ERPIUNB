<?php
$pageTitle = 'New Bank Reconciliation';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/finance_helpers.php';
require_once '../helpers/banking_helpers.php';

if (!hasPermission('finance.reconciliation.manage')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$bankAccounts = $db->query("
    SELECT
        ba.*,
        coa.account_code
    FROM bank_accounts ba
    LEFT JOIN chart_of_accounts coa
        ON ba.chart_account_id = coa.id
    WHERE ba.status = 'active'
    ORDER BY ba.account_name
")->fetchAll();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * CSRF Validation
     */
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    }

    $bank_account_id = (int)(
        $_POST['bank_account_id'] ?? 0
    );

    $statement_date = trim(
        $_POST['statement_date'] ?? ''
    );

    $statement_balance = (float)str_replace(
        ',',
        '',
        $_POST['statement_balance'] ?? 0
    );

    $notes = trim(
        $_POST['notes'] ?? ''
    );


    /*
     * Server-side validation
     */
    if ($bank_account_id <= 0) {
        $errors[] = 'Select a bank account.';
    }

    if (empty($statement_date)) {
        $errors[] = 'Statement date is required.';
    }


    if (!empty($errors)) {
        saveFormState($errors);

        to(
            'modules/finance/bank_reconciliation/create.php'
        );
    }


    try {

        $db->beginTransaction();


        /*
         * Get ledger balance (book balance)
         */
        $stmt = $db->prepare("
            SELECT chart_account_id
            FROM bank_accounts
            WHERE id = ?
        ");

        $stmt->execute([
            $bank_account_id
        ]);

        $ba = $stmt->fetch();

        if (!$ba) {
            throw new Exception(
                'Bank account not found.'
            );
        }


        $chartId = (int)$ba['chart_account_id'];

        $bookBalance = getAccountBalance(
            $chartId,
            $db
        );

        $difference =
            $statement_balance -
            $bookBalance;


        /*
         * Create reconciliation
         */
        $stmt = $db->prepare("
            INSERT INTO bank_reconciliation
            (
                bank_account_id,
                statement_date,
                statement_balance,
                book_balance,
                difference,
                status,
                notes,
                created_by
            )
            VALUES (?, ?, ?, ?, ?, 'in_progress', ?, ?)
        ");

        $stmt->execute([
            $bank_account_id,
            $statement_date,
            $statement_balance,
            $bookBalance,
            $difference,
            $notes,
            $_SESSION['user_id']
        ]);


        $reconId = (int)$db->lastInsertId();


        /*
         * Fetch unreconciled bank transactions
         */
        $txns = $db->prepare("
            SELECT
                id,
                amount,
                transaction_type
            FROM bank_transactions
            WHERE bank_account_id = ?
              AND status = 'posted'
              AND transaction_date <= ?
            ORDER BY transaction_date
        ");

        $txns->execute([
            $bank_account_id,
            $statement_date
        ]);

        $items = $txns->fetchAll();


        /*
         * Insert reconciliation items
         */
        $insItem = $db->prepare("
            INSERT INTO bank_reconciliation_items
            (
                reconciliation_id,
                bank_transaction_id,
                amount,
                is_matched
            )
            VALUES (?, ?, ?, 0)
        ");


        foreach ($items as $item) {

            $insItem->execute([
                $reconId,
                $item['id'],
                $item['amount']
            ]);
        }


        $db->commit();


        /*
         * Audit Log
         */
        logAudit(
            $_SESSION['user_id'],
            'Bank reconciliation started: ' .
                sanitize($ba['account_name']) .
                ' (Statement: ' .
                formatNumber($statement_balance) .
                ')',
            'finance'
        );


        /*
         * Success Message
         */
        flash(
            'success',
            'Reconciliation created. Statement: AED ' .
                formatNumber($statement_balance) .
                ' | Book: AED ' .
                formatNumber($bookBalance) .
                ' | Difference: AED ' .
                formatNumber($difference)
        );


        /*
         * Redirect
         */
        to(
            'modules/finance/bank_reconciliation/view.php?id=' .
                $reconId
        );
    } catch (Exception $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $errors[] =
            'Reconciliation failed: ' .
            $e->getMessage();
    }
}
?>


<div class="mb-6">

    <a
        href="index.php"
        class="text-blue-600 hover:text-blue-900">

        <i class="fas fa-arrow-left mr-1"></i>

        Back to Reconciliations

    </a>

</div>


<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">
        New Bank Reconciliation
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
        id="bankReconciliationForm"
        novalidate
        class="space-y-4">


 <input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">


            <!-- Bank Account -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Bank Account <span class="text-red-600">*</span>
                </label>

                <select
                    name="bank_account_id"
                    class="w-full px-3 py-2 border rounded">

                    <option value="">
                        Select Bank Account
                    </option>

                    <?php foreach ($bankAccounts as $ba): ?>

                        <option
                            value="<?= $ba['id'] ?>"
                            <?= old('bank_account_id') == $ba['id']
                                ? 'selected'
                                : '' ?>>

                            <?= sanitize(
                                $ba['account_name'] .
                                    ' - ' .
                                    $ba['bank_name']
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Statement Date -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Statement Date <span class="text-red-600">*</span>
                </label>

                <input
                    type="date"
                    name="statement_date"
                    value="<?= old(
                                'statement_date',
                                date('Y-m-d')
                            ) ?>"
                    class="w-full px-3 py-2 border rounded">

            </div>


            <!-- Statement Balance -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Statement Balance (AED) <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="statement_balance"
                    value="<?= old('statement_balance') ?>"
                    maxlength="15"
                    autocomplete="off"
                    class="w-full px-3 py-2 border rounded"
                    placeholder="From bank statement">

                <p class="text-xs text-gray-500 mt-1">
                    The ending balance according to the bank statement
                </p>

            </div>


            <!-- Notes -->
            <div class="md:col-span-2">

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Notes
                </label>

                <textarea
                    name="notes"
                    rows="2"
                    maxlength="1000"
                    class="w-full px-3 py-2 border rounded"><?= old('notes') ?></textarea>

            </div>

        </div>


        <div class="pt-4 border-t">

            <p class="text-sm text-gray-500 mb-3">

                <i class="fas fa-info-circle mr-1"></i>

                The book balance will be calculated automatically
                from the General Ledger. You will be able to match
                individual transactions on the next screen.

            </p>


            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">

                <i class="fas fa-save mr-2"></i>

                Start Reconciliation

            </button>


            <a
                href="index.php"
                class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">

                Cancel

            </a>

        </div>

    </form>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>
<script>
    $(document).ready(function() {

        /*
         * Statement Balance
         *
         * Allow:
         * 0-9
         * One decimal point
         * Maximum 2 decimal places
         */
        $('input[name="statement_balance"]').on('input', function() {

            let value = $(this).val();

            /*
             * Remove invalid characters
             */
            value = value.replace(/[^0-9.]/g, '');

            const parts = value.split('.');

            /*
             * Allow only one decimal point
             */
            if (parts.length > 2) {

                value =
                    parts[0] +
                    '.' +
                    parts.slice(1).join('');

            }

            /*
             * Maximum 2 decimal places
             */
            if (value.includes('.')) {

                const decimalParts =
                    value.split('.');

                value =
                    decimalParts[0] +
                    '.' +
                    decimalParts[1].substring(0, 2);

            }

            /*
             * Maximum 15 characters
             */
            value = value.substring(0, 15);

            $(this).val(value);

        });


        /*
         * Notes
         *
         * Maximum 1000 characters
         */
        $('textarea[name="notes"]').on('input', function() {

            $(this).val(
                $(this).val().substring(0, 1000)
            );

        });


        /*
         * jQuery Validation
         */
        $('#bankReconciliationForm').validate({

            errorElement: 'span',

            errorClass: 'text-red-500 text-sm mt-1 block',


            /*
             * Error placement
             */
            errorPlacement: function(error, element) {

                /*
                 * Select
                 */
                if (element.is('select')) {

                    element.parent().append(error);

                } else {

                    /*
                     * Input / Textarea
                     */
                    error.insertAfter(element);

                }

            },


            /*
             * Add red border when invalid
             */
            highlight: function(element) {

                $(element).addClass(
                    'border-red-500'
                );

            },


            /*
             * Remove red border when valid
             */
            unhighlight: function(element) {

                $(element).removeClass(
                    'border-red-500'
                );

            },


            /*
             * Validation Rules
             */
            rules: {

                bank_account_id: {

                    required: true,

                    digits: true,

                    min: 1

                },


                statement_date: {

                    required: true,

                    date: true

                },


                statement_balance: {

                    required: true,

                    number: true,

                    min: 0

                },


                notes: {

                    maxlength: 1000

                }

            },


            /*
             * Validation Messages
             */
            messages: {

                bank_account_id: {

                    required: 'Please select a bank account.',

                    digits: 'Invalid bank account.',

                    min: 'Please select a valid bank account.'

                },


                statement_date: {

                    required: 'Please select the statement date.',

                    date: 'Please enter a valid date.'

                },


                statement_balance: {

                    required: 'Please enter the statement balance.',

                    number: 'Please enter a valid statement balance.',

                    min: 'Statement balance cannot be negative.'

                },


                notes: {

                    maxlength: 'Notes cannot exceed 1000 characters.'

                }

            }

        });

    });
</script>


<?php require_once '../../../includes/footer.php'; ?>