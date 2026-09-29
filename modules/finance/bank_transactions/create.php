<?php
$pageTitle = 'New Bank Transaction';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../../../helpers/finance_helpers.php';
require_once '../helpers/banking_helpers.php';

if (!hasPermission('finance.bank.transaction')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}

$bankAccounts = $db->query("
    SELECT ba.*, coa.account_code
    FROM bank_accounts ba
    LEFT JOIN chart_of_accounts coa ON ba.chart_account_id = coa.id
    WHERE ba.status = 'active'
    ORDER BY ba.account_name
")->fetchAll();

$coaAccounts = $db->query("
    SELECT id, account_code, account_name
    FROM chart_of_accounts
    WHERE status = 'active'
    ORDER BY account_code
")->fetchAll();

$activeFY = getActiveFinancialYear($db);

// Voucher type mapping
$voucherTypeMap = [
    'deposit'      => 'RV',
    'withdrawal'   => 'PV',
    'interest'     => 'JV',
    'bank_charges' => 'JV',
    'adjustment'   => 'JV',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    }

    if (!$activeFY) {
        $errors[] = 'No active financial year.';
    }

    $bank_account_id = (int)($_POST['bank_account_id'] ?? 0);
    $transaction_type = $_POST['transaction_type'] ?? '';
    $offset_account_id = (int)($_POST['offset_account_id'] ?? 0);
    $amount = (float)str_replace(',', '', $_POST['amount'] ?? 0);
    $transaction_date = trim($_POST['transaction_date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $reference_number = trim($_POST['reference_number'] ?? '');

    if ($bank_account_id <= 0) {
        $errors[] = 'Select a bank account.';
    }

    if (!array_key_exists($transaction_type, $voucherTypeMap)) {
        $errors[] = 'Invalid transaction type.';
    }

    if ($offset_account_id <= 0) {
        $errors[] = 'Select an offset account.';
    }

    if ($amount <= 0) {
        $errors[] = 'Amount must be greater than zero.';
    }

    if (empty($transaction_date)) {
        $errors[] = 'Transaction date is required.';
    }

    // Prevent duplicate offset account
    $stmt = $db->prepare("
        SELECT chart_account_id
        FROM bank_accounts
        WHERE id = ?
    ");
    $stmt->execute([$bank_account_id]);

    $bank = $stmt->fetch();

    if (!$bank) {
        $errors[] = 'Bank account not found.';
    }

    if ($bank) {
        $bankChartId = (int)$bank['chart_account_id'];

        if (
            $bankChartId === $offset_account_id &&
            in_array($transaction_type, ['deposit', 'withdrawal'])
        ) {
            $errors[] = 'Offset account cannot be the same as the bank account linked account.';
        }
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/finance/bank_transactions/create.php');
    }

    try {

        $db->beginTransaction();

        // Get voucher type prefix
        $voucherPrefix = $voucherTypeMap[$transaction_type];

        $vtStmt = $db->prepare("
            SELECT id
            FROM voucher_types
            WHERE voucher_prefix = ?
            AND status = 'active'
            LIMIT 1
        ");

        $vtStmt->execute([$voucherPrefix]);

        $vt = $vtStmt->fetch();

        if (!$vt) {
            throw new Exception(
                "Voucher type '$voucherPrefix' not found or inactive."
            );
        }

        $voucherTypeId = (int)$vt['id'];

        // Generate voucher number
        $voucherNo = generateVoucherNo($voucherTypeId, $db);

        // Determine DR/CR
        $isBankDebit = in_array(
            $transaction_type,
            ['deposit', 'interest']
        );

        // Create journal entry
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
            $transaction_date,
            $reference_number ?: $description,
            $description,
            $_SESSION['user_id']
        ]);

        $journalId = (int)$db->lastInsertId();

        // Create journal details
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

        if ($isBankDebit) {

            $stmt->execute([
                $journalId,
                $bankChartId,
                $amount,
                0,
                $description ?: ($transaction_type . ' - Bank')
            ]);

            $stmt->execute([
                $journalId,
                $offset_account_id,
                0,
                $amount,
                $description ?: ($transaction_type . ' - Offset')
            ]);
        } else {

            $stmt->execute([
                $journalId,
                $bankChartId,
                0,
                $amount,
                $description ?: ($transaction_type . ' - Bank')
            ]);

            $stmt->execute([
                $journalId,
                $offset_account_id,
                $amount,
                0,
                $description ?: ($transaction_type . ' - Offset')
            ]);
        }

        // Post via PostingEngine
        require_once __DIR__ . '/../posting_engine/PostingEngine.php';

        $result = PostingEngine::post(
            $journalId,
            $_SESSION['user_id']
        );

        if (!$result['success']) {
            throw new Exception($result['message']);
        }

        // Generate reference number if empty
        if (empty($reference_number)) {

            $refStmt = $db->prepare("
                SELECT COUNT(*)
                FROM bank_transactions
                WHERE transaction_type = ?
            ");

            $refStmt->execute([$transaction_type]);

            $refCount = (int)$refStmt->fetchColumn() + 1;

            $reference_number =
                strtoupper(substr($transaction_type, 0, 3))
                . '-'
                . date('ymd')
                . '-'
                . str_pad($refCount, 3, '0', STR_PAD_LEFT);
        }

        // Record bank transaction
        $stmt = $db->prepare("
            INSERT INTO bank_transactions
            (
                bank_account_id,
                transaction_date,
                transaction_type,
                reference_number,
                amount,
                description,
                journal_entry_id,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, 'posted')
        ");

        $stmt->execute([
            $bank_account_id,
            $transaction_date,
            $transaction_type,
            $reference_number,
            $amount,
            $description,
            $journalId
        ]);

        $db->commit();

        logAudit(
            $_SESSION['user_id'],
            ucfirst($transaction_type)
                . ' transaction: '
                . $reference_number
                . ' (Voucher: '
                . $voucherNo
                . ')',
            'finance'
        );

        flash(
            'success',
            ucfirst($transaction_type)
                . ' of '
                . formatNumber($amount)
                . ' recorded successfully. Voucher: '
                . $voucherNo
        );

        to('modules/finance/bank_transactions/index.php');
    } catch (Exception $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $errors[] = 'Transaction failed: ' . $e->getMessage();
    }
}
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>
        Back to Bank Transactions
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">
        New Bank Transaction
    </h2>

    <?php if ($errors): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $e): ?>
                    <li><?= sanitize($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form
        method="POST"
        id="createBankTransactionForm"
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
                    <option value="">Select Bank Account</option>

                    <?php foreach ($bankAccounts as $ba): ?>
                        <option
                            value="<?= $ba['id'] ?>"
                            <?= old('bank_account_id') == $ba['id'] ? 'selected' : '' ?>>
                            <?= sanitize($ba['account_name']) ?>
                            (<?= sanitize($ba['bank_name']) ?>)
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <!-- Transaction Type -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Transaction Type <span class="text-red-600">*</span>
                </label>

                <select
                    name="transaction_type"
                    class="w-full px-3 py-2 border rounded">
                    <option value="">Select Type</option>

                    <option
                        value="deposit"
                        <?= old('transaction_type') === 'deposit' ? 'selected' : '' ?>>
                        Deposit (Money In)
                    </option>

                    <option
                        value="withdrawal"
                        <?= old('transaction_type') === 'withdrawal' ? 'selected' : '' ?>>
                        Withdrawal (Money Out)
                    </option>

                    <option
                        value="interest"
                        <?= old('transaction_type') === 'interest' ? 'selected' : '' ?>>
                        Interest Earned
                    </option>

                    <option
                        value="bank_charges"
                        <?= old('transaction_type') === 'bank_charges' ? 'selected' : '' ?>>
                        Bank Charges
                    </option>

                    <option
                        value="adjustment"
                        <?= old('transaction_type') === 'adjustment' ? 'selected' : '' ?>>
                        Adjustment
                    </option>
                </select>
            </div>

            <!-- Amount -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Amount (AED) <span class="text-red-600">*</span>
                </label>

                <input
                    type="text"
                    name="amount"
                    value="<?= old('amount') ?>"
                    maxlength="15"
                    class="w-full px-3 py-2 border rounded"
                    placeholder="0.00">
            </div>

            <!-- Transaction Date -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Transaction Date <span class="text-red-600">*</span>
                </label>

                <input
                    type="date"
                    name="transaction_date"
                    value="<?= old('transaction_date', date('Y-m-d')) ?>"
                    class="w-full px-3 py-2 border rounded">
            </div>

            <!-- Reference Number -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Reference Number
                </label>

                <input
                    type="text"
                    name="reference_number"
                    value="<?= old('reference_number') ?>"
                    maxlength="100"
                    class="w-full px-3 py-2 border rounded"
                    placeholder="Auto-generated if empty">
            </div>

            <!-- Offset Account -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Offset Account <span class="text-red-600">*</span>
                </label>

                <select
                    name="offset_account_id"
                    class="w-full px-3 py-2 border rounded">
                    <option value="">Select Offset Account</option>

                    <?php foreach ($coaAccounts as $a): ?>
                        <option
                            value="<?= $a['id'] ?>"
                            <?= old('offset_account_id') == $a['id'] ? 'selected' : '' ?>>
                            <?= sanitize($a['account_code'] . ' - ' . $a['account_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>

                <p class="text-xs text-gray-500 mt-1">
                    The other side of the double-entry transaction
                </p>
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
                This transaction will create a journal entry and post it via
                the Posting Engine. All money movements go through the
                General Ledger.
            </p>

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                <i class="fas fa-save mr-2"></i>
                Record Transaction
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
         * Remove invalid characters while typing/pasting
         */

        $('input[name="amount"]').on('input', function() {
            let value = $(this).val();

            value = value.replace(/[^0-9.]/g, '');

            // Keep only the first decimal point
            const parts = value.split('.');

            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }

            // Maximum 2 decimal places
            if (value.includes('.')) {
                const decimalParts = value.split('.');
                value =
                    decimalParts[0] +
                    '.' +
                    decimalParts[1].substring(0, 2);
            }

            $(this).val(value);
        });

        $('input[name="reference_number"]').on('input', function() {
            let value = $(this).val();

            value = value.replace(/[^A-Za-z0-9 ._\/#-]/g, '');

            $(this).val(value.substring(0, 100));
        });

        $('textarea[name="description"]').on('input', function() {
            $(this).val($(this).val().substring(0, 1000));
        });


        /*
         * jQuery Validate
         */

        $('#createBankTransactionForm').validate({

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
                $(element).addClass('border-red-500');
            },

            unhighlight: function(element) {
                $(element).removeClass('border-red-500');
            },

            rules: {

                bank_account_id: {
                    required: true,
                    digits: true,
                    min: 1
                },

                transaction_type: {
                    required: true
                },

                amount: {
                    required: true,
                    number: true,
                    min: 0.01
                },

                transaction_date: {
                    required: true,
                    date: true
                },

                reference_number: {
                    maxlength: 100
                },

                offset_account_id: {
                    required: true,
                    digits: true,
                    min: 1
                },

                description: {
                    maxlength: 1000
                }
            },

            messages: {

                bank_account_id: {
                    required: 'Please select a bank account.',
                    digits: 'Invalid bank account.',
                    min: 'Please select a bank account.'
                },

                transaction_type: {
                    required: 'Please select a transaction type.'
                },

                amount: {
                    required: 'Please enter the amount.',
                    number: 'Please enter a valid amount.',
                    min: 'Amount must be greater than zero.'
                },

                transaction_date: {
                    required: 'Please select the transaction date.',
                    date: 'Please enter a valid date.'
                },

                reference_number: {
                    maxlength: 'Reference number cannot exceed 100 characters.'
                },

                offset_account_id: {
                    required: 'Please select an offset account.',
                    digits: 'Invalid offset account.',
                    min: 'Please select an offset account.'
                },

                description: {
                    maxlength: 'Description cannot exceed 1000 characters.'
                }
            }
        });

    });
</script>

<?php require_once '../../../includes/footer.php'; ?>