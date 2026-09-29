<?php
$pageTitle = 'Issue Petty Cash';

require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../../../helpers/finance_helpers.php';
require_once '../helpers/banking_helpers.php';

if (!hasPermission('finance.cash.transaction')) {
    die('Access Denied');
}

$db = getDB();

$errors = [];

$cashAccounts = $db->query("
    SELECT
        ca.*,
        coa.account_code,
        coa.id AS coa_id
    FROM cash_accounts ca
    LEFT JOIN chart_of_accounts coa
        ON ca.chart_account_id = coa.id
    WHERE ca.status = 'active'
    ORDER BY ca.cash_name
")->fetchAll();

$employees = $db->query("
    SELECT
        id,
        CONCAT(first_name, ' ', last_name) AS name
    FROM employees
    WHERE status = 'active'
    ORDER BY first_name
")->fetchAll();

if (empty($employees)) {
    $employees = $db->query("
        SELECT id, name
        FROM users
        ORDER BY name
    ")->fetchAll();
}

$activeFY = getActiveFinancialYear($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    }

    if (!$activeFY) {
        $errors[] = 'No active financial year.';
    }

    $fund_name = trim($_POST['fund_name'] ?? '');

    $cash_account_id = (int) (
        $_POST['cash_account_id'] ?? 0
    );

    $custodian_employee_id = (int) (
        $_POST['custodian_employee_id'] ?? 0
    );

    $imprest_amount = (float) str_replace(
        ',',
        '',
        $_POST['imprest_amount'] ?? 0
    );

    $issue_date = trim(
        $_POST['issue_date'] ?? ''
    );

    $description = trim(
        $_POST['description'] ?? ''
    );

    /*
     * Server-side validation
     */
    if (empty($fund_name)) {
        $errors[] = 'Fund name is required.';
    }

    if ($cash_account_id <= 0) {
        $errors[] = 'Select a cash account.';
    }

    if ($imprest_amount <= 0) {
        $errors[] = 'Imprest amount must be greater than zero.';
    }

    if (empty($issue_date)) {
        $errors[] = 'Issue date is required.';
    }

    if (empty($errors)) {

        try {

            $db->beginTransaction();

            /*
             * Get cash account Chart of Account ID
             */
            $stmt = $db->prepare("
                SELECT chart_account_id
                FROM cash_accounts
                WHERE id = ?
            ");

            $stmt->execute([
                $cash_account_id
            ]);

            $ca = $stmt->fetch();

            if (!$ca) {
                throw new Exception(
                    'Cash account not found.'
                );
            }

            $cashChartId = (int) $ca['chart_account_id'];

            /*
             * Create petty cash fund
             */
            $stmt = $db->prepare("
                INSERT INTO petty_cash_funds
                (
                    cash_account_id,
                    fund_name,
                    custodian_employee_id,
                    imprest_amount,
                    current_balance,
                    status
                )
                VALUES (?, ?, ?, ?, ?, 'open')
            ");

            $stmt->execute([
                $cash_account_id,
                $fund_name,
                $custodian_employee_id ?: null,
                $imprest_amount,
                $imprest_amount
            ]);

            $fundId = (int) $db->lastInsertId();

            /*
             * Get Journal Voucher type
             */
            $vtStmt = $db->prepare("
                SELECT id
                FROM voucher_types
                WHERE voucher_prefix = 'JV'
                AND status = 'active'
                LIMIT 1
            ");

            $vtStmt->execute();

            $vt = $vtStmt->fetch();

            if (!$vt) {
                throw new Exception(
                    "Journal Voucher type 'JV' not found."
                );
            }

            $voucherTypeId = (int) $vt['id'];

            $voucherNo = generateVoucherNo(
                $voucherTypeId,
                $db
            );

            /*
             * Create Journal Entry
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
                VALUES (?, ?, ?, ?, ?, 'draft', ?, NOW())
            ");

            $stmt->execute([
                $voucherNo,
                $voucherTypeId,
                $issue_date,
                'PC-' . $fundId,
                $description ?: 'Petty cash issue: ' . $fund_name,
                $_SESSION['user_id']
            ]);

            $journalId = (int) $db->lastInsertId();

            /*
             * Journal Entry Details
             *
             * DR: Petty Cash
             * CR: Cash Account
             *
             * Existing business logic preserved.
             */
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

            /*
             * Debit
             */
            $stmt->execute([
                $journalId,
                $cashChartId,
                $imprest_amount,
                0,
                'Petty cash issued: ' . $fund_name
            ]);

            /*
             * Credit
             */
            $stmt->execute([
                $journalId,
                $cashChartId,
                0,
                $imprest_amount,
                'Petty cash fund created: ' . $fund_name
            ]);

            /*
             * Posting Engine
             */
            require_once __DIR__ . '/../posting_engine/PostingEngine.php';

            $result = PostingEngine::post(
                $journalId,
                $_SESSION['user_id']
            );

            if (!$result['success']) {
                throw new Exception(
                    $result['message']
                );
            }

            /*
             * Record petty cash transaction
             */
            $stmt = $db->prepare("
                INSERT INTO petty_cash_transactions
                (
                    petty_cash_fund_id,
                    transaction_type,
                    amount,
                    description,
                    voucher_number,
                    journal_entry_id,
                    created_by
                )
                VALUES (?, 'issue', ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $fundId,
                $imprest_amount,
                $description ?: 'Petty cash issued',
                $voucherNo,
                $journalId,
                $_SESSION['user_id']
            ]);

            $db->commit();

            /*
             * Audit Log
             */
            logAudit(
                $_SESSION['user_id'],
                'Petty cash issued: ' .
                    $fund_name .
                    ' (AED ' .
                    $imprest_amount .
                    ')',
                'finance'
            );

            flash(
                'success',
                'Petty cash fund "' .
                    $fund_name .
                    '" issued with AED ' .
                    formatNumber($imprest_amount) .
                    '. Voucher: ' .
                    $voucherNo
            );

            to(
                'modules/finance/petty_cash/index.php'
            );
        } catch (Exception $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $errors[] =
                'Failed to issue petty cash: ' .
                $e->getMessage();
        }
    }
}
?>

<div class="mb-6">

    <a
        href="index.php"
        class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Petty Cash
    </a>

</div>

<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">
        Issue Petty Cash Fund
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
        id="issuePettyCashForm"
        novalidate
        class="space-y-4">

       <input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">


        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Fund Name -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Fund Name <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="fund_name"
                    value="<?= old('fund_name') ?>"
                    maxlength="200"
                    autocomplete="off"
                    class="w-full px-3 py-2 border rounded"
                    placeholder="e.g. Office Petty Cash">

            </div>


            <!-- Cash Account -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Cash Account <span class="text-red-600">*</span>
                </label>

                <select
                    name="cash_account_id"
                    class="w-full px-3 py-2 border rounded">

                    <option value="">
                        Select Cash Account
                    </option>

                    <?php foreach ($cashAccounts as $ca): ?>

                        <option
                            value="<?= $ca['id'] ?>"
                            <?= old('cash_account_id') == $ca['id'] ? 'selected' : '' ?>>
                            <?= sanitize($ca['cash_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Imprest Amount -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Imprest Amount (AED) <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="imprest_amount"
                    value="<?= old('imprest_amount') ?>"
                    maxlength="15"
                    autocomplete="off"
                    class="w-full px-3 py-2 border rounded"
                    placeholder="e.g. 5000">

            </div>


            <!-- Custodian -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Custodian
                </label>

                <select
                    name="custodian_employee_id"
                    class="w-full px-3 py-2 border rounded">

                    <option value="">
                        Select Custodian
                    </option>

                    <?php foreach ($employees as $e): ?>

                        <option
                            value="<?= $e['id'] ?>"
                            <?= old('custodian_employee_id') == $e['id'] ? 'selected' : '' ?>>
                            <?= sanitize($e['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Issue Date -->
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Issue Date <span class="text-red-600">*</span>
                </label>

                <input
                    type="date"
                    name="issue_date"
                    value="<?= old('issue_date', date('Y-m-d')) ?>"
                    class="w-full px-3 py-2 border rounded">

            </div>


            <!-- Description -->
            <div class="md:col-span-2">

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Description
                </label>

                <textarea
                    name="description"
                    rows="2"
                    maxlength="1000"
                    class="w-full px-3 py-2 border rounded"><?= old('description') ?></textarea>

            </div>

        </div>


        <div class="pt-4 border-t">

            <p class="text-sm text-gray-500 mb-3">

                <i class="fas fa-info-circle mr-1"></i>

                Issuing petty cash creates a fund with the specified
                imprest amount. Each fund tracks its own balance as
                expenses and top-ups are recorded.

            </p>


            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">

                <i class="fas fa-save mr-2"></i>

                Create Fund

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
         * Fund Name
         *
         * Allowed:
         * A-Z
         * a-z
         * 0-9
         * spaces
         * . , & ( ) _ / @ # + ' -
         */
        $('input[name="fund_name"]').on('input', function() {

            let value = $(this).val();

            value = value.replace(
                /[^A-Za-z0-9 .,&()_/@#+'-]/g,
                ''
            );

            value = value.replace(/\s{2,}/g, ' ');

            $(this).val(value.substring(0, 200));
        });


        /*
         * Imprest Amount
         *
         * Allow numbers and one decimal point.
         * Maximum 2 decimal places.
         */
        $('input[name="imprest_amount"]').on('input', function() {

            let value = $(this).val();

            value = value.replace(/[^0-9.]/g, '');

            const parts = value.split('.');

            /*
             * Only one decimal point
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

            $(this).val(value);
        });


        /*
         * Description
         */
        $('textarea[name="description"]').on('input', function() {

            $(this).val(
                $(this).val().substring(0, 1000)
            );

        });


        /*
         * jQuery Validation
         */
        $('#issuePettyCashForm').validate({

            errorElement: 'span',

            errorClass: 'text-red-500 text-sm mt-1 block',

            errorPlacement: function(error, element) {

                /*
                 * Dropdown validation message
                 * should appear below dropdown.
                 */
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

                fund_name: {
                    required: true,
                    minlength: 2,
                    maxlength: 200
                },

                cash_account_id: {
                    required: true,
                    digits: true,
                    min: 1
                },

                imprest_amount: {
                    required: true,
                    number: true,
                    min: 0.01
                },

                custodian_employee_id: {
                    digits: true,
                    min: 1
                },

                issue_date: {
                    required: true,
                    date: true
                },

                description: {
                    maxlength: 1000
                }

            },

            messages: {

                fund_name: {
                    required: 'Please enter the fund name.',
                    minlength: 'Fund name must be at least 2 characters.',
                    maxlength: 'Fund name cannot exceed 200 characters.'
                },

                cash_account_id: {
                    required: 'Please select a cash account.',
                    digits: 'Invalid cash account.',
                    min: 'Please select a valid cash account.'
                },

                imprest_amount: {
                    required: 'Please enter the imprest amount.',
                    number: 'Please enter a valid amount.',
                    min: 'Imprest amount must be greater than zero.'
                },

                custodian_employee_id: {
                    digits: 'Invalid custodian.',
                    min: 'Please select a valid custodian.'
                },

                issue_date: {
                    required: 'Please select the issue date.',
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