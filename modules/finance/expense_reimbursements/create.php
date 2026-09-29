<?php
$pageTitle = 'Process Reimbursement';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/finance_helpers.php';

if (!hasPermission('expense_reimbursements.create')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

// Load approved claims
$claims = $db->query("
    SELECT ec.*, e.first_name, e.last_name, e.employee_code
    FROM employee_expense_claims ec
    JOIN employees e ON ec.employee_id = e.id
    WHERE ec.status = 'approved'
    ORDER BY ec.updated_at ASC
")->fetchAll();

$bankAccounts = $db->query("SELECT id, account_name, account_number FROM bank_accounts WHERE status = 'active' ORDER BY account_name")->fetchAll();
$cashAccounts = $db->query("SELECT id, cash_name FROM cash_accounts WHERE status = 'active' ORDER BY cash_name")->fetchAll();

// Pre-select claim if passed via GET
$preselectedClaimId = (int)($_GET['claim_id'] ?? 0);

// Generate reimbursement number
$prefix = 'REIMB' . date('Ymd');
$stmt = $db->prepare("SELECT COUNT(*) FROM expense_reimbursements WHERE reimbursement_number LIKE ?");
$stmt->execute([$prefix . '%']);
$reimbursement_number = $prefix . '-' . str_pad((int)$stmt->fetchColumn() + 1, 3, '0', STR_PAD_LEFT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid or expired form token.';
    }

    $claim_id = (int)($_POST['claim_id'] ?? 0);
    $payment_method = trim($_POST['payment_method'] ?? '');
    $bank_account_id = !empty($_POST['bank_account_id']) ? (int)$_POST['bank_account_id'] : null;
    $cash_account_id = !empty($_POST['cash_account_id']) ? (int)$_POST['cash_account_id'] : null;
    $payment_date = trim($_POST['payment_date'] ?? '');
    $reference_number = trim($_POST['reference_number'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $amount = (float)str_replace(',', '', $_POST['amount'] ?? 0);

    if ($claim_id <= 0) {
        $errors[] = 'Please select a claim.';
    }

    if (empty($payment_method)) {
        $errors[] = 'Please select a payment method.';
    }

    if (in_array($payment_method, ['bank_transfer', 'cheque']) && $bank_account_id <= 0) {
        $errors[] = 'Please select a bank account for bank/cheque payment.';
    }

    if ($payment_method === 'cash' && $cash_account_id <= 0) {
        $errors[] = 'Please select a cash account.';
    }

    if (empty($payment_date)) {
        $errors[] = 'Payment date is required.';
    }

    if ($amount <= 0) {
        $errors[] = 'Amount must be greater than zero.';
    }

    // Load claim
    $claimStmt = $db->prepare("
        SELECT ec.*, e.first_name, e.last_name, e.employee_code
        FROM employee_expense_claims ec
        JOIN employees e ON ec.employee_id = e.id
        WHERE ec.id = ? AND ec.status = 'approved'
    ");
    $claimStmt->execute([$claim_id]);

    $claim = $claimStmt->fetch();

    if (!$claim) {
        $errors[] = 'Selected claim not found or not approved.';
    } elseif ($amount > (float)$claim['total_amount']) {
        $errors[] = 'Reimbursement amount cannot exceed the approved claim total (' . formatMoney((float)$claim['total_amount']) . ').';
    }

    // Check if already reimbursed
    $checkReimb = $db->prepare("
        SELECT COUNT(*) 
        FROM expense_reimbursements 
        WHERE claim_id = ? 
        AND status = 'paid'
    ");

    $checkReimb->execute([$claim_id]);

    if ((int)$checkReimb->fetchColumn() > 0) {
        $errors[] = 'This claim has already been reimbursed.';
    }

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/finance/expense_reimbursements/create.php');
    }

    // Retry on concurrent reimbursement-number collision
    for ($attempt = 1; $attempt <= 5; $attempt++) {

        $db->beginTransaction();

        try {

            // Determine which account to CREDIT
            $cashAccountId = null;
            $bankAccountId = null;
            $creditAccountId = 0;

            if ($payment_method === 'bank_transfer' || $payment_method === 'cheque') {

                $bankAccountId = $bank_account_id;

                $stmt = $db->prepare("
                    SELECT chart_account_id 
                    FROM bank_accounts 
                    WHERE id = ?
                ");

                $stmt->execute([$bank_account_id]);

                $creditAccountId = (int)$stmt->fetchColumn();
            } elseif ($payment_method === 'cash') {

                $cashAccountId = $cash_account_id;

                $stmt = $db->prepare("
                    SELECT chart_account_id 
                    FROM cash_accounts 
                    WHERE id = ?
                ");

                $stmt->execute([$cash_account_id]);

                $creditAccountId = (int)$stmt->fetchColumn();
            }

            if ($creditAccountId <= 0) {
                throw new Exception(
                    'Selected payment account has no GL mapping. Configure chart account link first.'
                );
            }

            // Get expense payable account
            $settings = $db->query("
                SELECT setting_key, setting_value 
                FROM settings 
                WHERE setting_key = 'finance_expense_payable_account_id'
            ")->fetchAll();

            $expPayableAccountId = 0;

            foreach ($settings as $s) {
                if ($s['setting_key'] === 'finance_expense_payable_account_id') {
                    $expPayableAccountId = (int)$s['setting_value'];
                }
            }

            if ($expPayableAccountId <= 0) {
                throw new Exception(
                    'Expense Payable account not configured. Go to Finance Settings.'
                );
            }

            // Get Expense Payment voucher type
            $vt = $db->prepare("
                SELECT id, voucher_prefix 
                FROM voucher_types 
                WHERE voucher_name = 'Expense Payment' 
                LIMIT 1
            ");

            $vt->execute();

            $vtRow = $vt->fetch();

            if (!$vtRow) {

                $db->prepare("
                    INSERT INTO voucher_types 
                    (voucher_name, voucher_prefix) 
                    VALUES ('Expense Payment', 'EP')
                ")->execute();

                $voucherTypeId = (int)$db->lastInsertId();
            } else {

                $voucherTypeId = (int)$vtRow['id'];
            }

            $voucherNo = generateVoucherNo($voucherTypeId, $db);

            if (!$voucherNo) {
                throw new Exception('Failed to generate voucher number.');
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
                VALUES (?, ?, ?, ?, 'draft', ?, NOW(), NOW())
            ");

            $stmt->execute([
                $voucherNo,
                $voucherTypeId,
                $payment_date,
                'Expense Reimbursement - Claim #' .
                    $claim['claim_number'] . ' - ' .
                    sanitize($claim['first_name'] . ' ' . $claim['last_name']),
                $_SESSION['user_id']
            ]);

            $journalId = (int)$db->lastInsertId();

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

            // DR: Expense Payable
            $detailStmt->execute([
                $journalId,
                $expPayableAccountId,
                $amount,
                0,
                'Reimbursement Claim #' . $claim['claim_number']
            ]);

            // CR: Bank / Cash
            $detailStmt->execute([
                $journalId,
                $creditAccountId,
                0,
                $amount,
                'Reimbursement Claim #' . $claim['claim_number']
            ]);

            // Post through PostingEngine
            require_once __DIR__ . '/../posting_engine/PostingEngine.php';

            $result = PostingEngine::post(
                $journalId,
                $_SESSION['user_id']
            );

            if (!$result['success']) {
                throw new Exception($result['message']);
            }

            // Create reimbursement record
            $stmt = $db->prepare("
                INSERT INTO expense_reimbursements 
                (
                    reimbursement_number,
                    claim_id,
                    employee_id,
                    payment_method,
                    bank_account_id,
                    cash_account_id,
                    amount,
                    reference_number,
                    payment_date,
                    remarks,
                    status,
                    journal_entry_id,
                    created_by,
                    created_at,
                    updated_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $reimbursement_number,
                $claim_id,
                $claim['employee_id'],
                $payment_method,
                $bankAccountId,
                $cashAccountId,
                $amount,
                $reference_number ?: null,
                $payment_date,
                $remarks ?: null,
                $journalId,
                $_SESSION['user_id']
            ]);

            $reimb_id = (int)$db->lastInsertId();

            // Update claim status to reimbursed
            $stmt = $db->prepare("
                UPDATE employee_expense_claims 
                SET status = 'reimbursed', updated_at = NOW() 
                WHERE id = ? 
                AND status = 'approved'
            ");

            $stmt->execute([$claim_id]);

            if ($stmt->rowCount() === 0) {
                throw new Exception(
                    'This claim has already been reimbursed.'
                );
            }

            // Record history
            $stmt = $db->prepare("
                INSERT INTO expense_approval_history 
                (
                    expense_type,
                    expense_id,
                    approval_level,
                    action,
                    comments,
                    action_by
                )
                VALUES 
                (
                    'employee_claim',
                    ?,
                    'reimbursement',
                    'approved',
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $claim_id,
                'Reimbursed via ' . $reimbursement_number .
                    '. Voucher: ' . $voucherNo,
                $_SESSION['user_id']
            ]);

            $db->commit();

            logAudit(
                $_SESSION['user_id'],
                'Processed reimbursement: ' .
                    $reimbursement_number .
                    ' for claim: ' .
                    $claim['claim_number'],
                'expense_reimbursements'
            );

            flash(
                'success',
                'Reimbursement ' .
                    $reimbursement_number .
                    ' processed successfully. Voucher #' .
                    $voucherNo,
                'success'
            );

            redirect('view.php?id=' . $reimb_id);
        } catch (Exception $e) {

            $db->rollBack();

            $isDupNumber =
                $e instanceof PDOException &&
                (int)$e->getCode() === 23000 &&
                stripos($e->getMessage(), 'reimbursement_number') !== false;

            if ($isDupNumber && $attempt < 5) {

                $stmt = $db->prepare("
                    SELECT COUNT(*) 
                    FROM expense_reimbursements 
                    WHERE reimbursement_number LIKE ?
                ");

                $stmt->execute([$prefix . '%']);

                $reimbursement_number =
                    $prefix . '-' .
                    str_pad(
                        (int)$stmt->fetchColumn() + 1,
                        3,
                        '0',
                        STR_PAD_LEFT
                    );

                continue;
            }

            flash('error', 'Error: ' . $e->getMessage());

            to(
                'modules/finance/expense_reimbursements/create.php'
            );
        }
    }
}
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            Process Reimbursement
        </h1>

        <p class="text-gray-600">
            Pay an approved expense claim to an employee
        </p>
    </div>

    <a href="index.php"
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

<form method="POST" id="reimbursementForm">

    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            Reimbursement Details
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Reimbursement #
                </label>

                <input type="text"
                    value="<?= sanitize($reimbursement_number) ?>"
                    class="w-full px-3 py-2 border rounded bg-gray-50 text-gray-500"
                    readonly>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Claim <span class="text-red-500">*</span>
                </label>

                <select name="claim_id"
                    id="claimSelect"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    required>

                    <option value="">
                        -- Select Approved Claim --
                    </option>

                    <?php foreach ($claims as $c): ?>

                        <option value="<?= $c['id'] ?>"
                            <?= old('claim_id', $preselectedClaimId) == $c['id'] ? 'selected' : '' ?>
                            data-amount="<?= $c['total_amount'] ?>"
                            data-employee="<?= sanitize($c['first_name'] . ' ' . $c['last_name']) ?>">

                            <?= sanitize(
                                $c['claim_number'] .
                                    ' - ' .
                                    $c['first_name'] . ' ' .
                                    $c['last_name'] .
                                    ' (' .
                                    formatMoney((float)$c['total_amount']) .
                                    ')'
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Payment Method <span class="text-red-500">*</span>
                </label>

                <select name="payment_method"
                    id="paymentMethod"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    required>

                    <option value="">
                        -- Select --
                    </option>

                    <option value="bank_transfer"
                        <?= old('payment_method') === 'bank_transfer' ? 'selected' : '' ?>>
                        Bank Transfer
                    </option>

                    <option value="cash"
                        <?= old('payment_method') === 'cash' ? 'selected' : '' ?>>
                        Cash
                    </option>

                    <option value="cheque"
                        <?= old('payment_method') === 'cheque' ? 'selected' : '' ?>>
                        Cheque
                    </option>

                </select>
            </div>

            <div id="bankField"
                class="<?= in_array(old('payment_method'), ['bank_transfer', 'cheque']) ? '' : 'hidden' ?>">

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Bank Account
                </label>

                <select name="bank_account_id"
                    id="bankAccount"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Select --
                    </option>

                    <?php foreach ($bankAccounts as $b): ?>

                        <option value="<?= $b['id'] ?>"
                            <?= old('bank_account_id') == $b['id'] ? 'selected' : '' ?>>

                            <?= sanitize(
                                $b['account_name'] .
                                    ' (' .
                                    $b['account_number'] .
                                    ')'
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>
            </div>

            <div id="cashField"
                class="<?= old('payment_method') === 'cash' ? '' : 'hidden' ?>">

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Cash Account
                </label>

                <select name="cash_account_id"
                    id="cashAccount"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">

                    <option value="">
                        -- Select --
                    </option>

                    <?php foreach ($cashAccounts as $c): ?>

                        <option value="<?= $c['id'] ?>"
                            <?= old('cash_account_id') == $c['id'] ? 'selected' : '' ?>>

                            <?= sanitize($c['cash_name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Payment Date <span class="text-red-500">*</span>
                </label>

                <input type="date"
                    name="payment_date"
                    value="<?= sanitize(old('payment_date', date('Y-m-d'))) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Amount <span class="text-red-500">*</span>
                </label>

                <input type="number"
                    name="amount"
                    id="amountField"
                    step="0.01"
                    min="0.01"
                    value="<?= sanitize(old('amount', '0')) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Reference #
                </label>

                <input type="text"
                    name="reference_number"
                    value="<?= sanitize(old('reference_number')) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    placeholder="Cheque / transaction ref">
            </div>

        </div>

        <div class="mt-4">

            <label class="block text-sm font-medium text-gray-700 mb-1">
                Remarks
            </label>

            <textarea name="remarks"
                rows="2"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= sanitize(old('remarks')) ?></textarea>

        </div>

    </div>

    <div class="flex justify-end gap-4 mb-8">

        <a href="index.php"
            class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400 transition">
            Cancel
        </a>

        <button type="submit"
            class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">

            <i class="fas fa-hand-holding-usd mr-2"></i>
            Process Reimbursement

        </button>

    </div>

</form>

<style>
    #reimbursementForm p.error {
        display: block !important;
        width: 100%;
        margin-top: 4px !important;
        margin-bottom: 0 !important;
        clear: both;
        color: #dc2626;
        font-size: 0.875rem;
    }

    #reimbursementForm input.error,
    #reimbursementForm select.error,
    #reimbursementForm textarea.error {
        border-color: #ef4444 !important;
    }
</style>

<script>
    document.getElementById('paymentMethod').addEventListener('change', function() {

        document.getElementById('bankField').classList.toggle(
            'hidden',
            !['bank_transfer', 'cheque'].includes(this.value)
        );

        document.getElementById('cashField').classList.toggle(
            'hidden',
            this.value !== 'cash'
        );
    });


    document.getElementById('claimSelect').addEventListener('change', function() {

        const opt = this.options[this.selectedIndex];

        if (opt && opt.dataset.amount) {
            document.getElementById('amountField').value = opt.dataset.amount;
        }
    });
</script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function() {

        $("#reimbursementForm").validate({

            errorElement: "p",

            errorClass: "error text-red-500 text-sm mt-1 block",

            errorPlacement: function(error, element) {

                /*
                 * Select dropdown validation message
                 * will appear BELOW the dropdown.
                 */
                if (element.is("select")) {

                    element.parent().append(error);

                } else {

                    element.after(error);

                }
            },

            highlight: function(element) {

                $(element)
                    .addClass("border-red-500")
                    .removeClass("border-gray-300");
            },

            unhighlight: function(element) {

                $(element)
                    .removeClass("border-red-500")
                    .addClass("border-gray-300");
            },

            rules: {

                claim_id: {
                    required: true
                },

                payment_method: {
                    required: true
                },

                bank_account_id: {
                    required: {
                        depends: function() {
                            return $("#paymentMethod").val() === "bank_transfer" ||
                                $("#paymentMethod").val() === "cheque";
                        }
                    }
                },

                cash_account_id: {
                    required: {
                        depends: function() {
                            return $("#paymentMethod").val() === "cash";
                        }
                    }
                },

                payment_date: {
                    required: true,
                    date: true
                },

                amount: {
                    required: true,
                    number: true,
                    min: 0.01
                },

                reference_number: {
                    maxlength: 100
                },

                remarks: {
                    maxlength: 500
                }
            },

            messages: {

                claim_id: {
                    required: "Please select a claim."
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

                payment_date: {
                    required: "Payment date is required.",
                    date: "Please enter a valid payment date."
                },

                amount: {
                    required: "Amount is required.",
                    number: "Please enter a valid amount.",
                    min: "Amount must be greater than zero."
                },

                reference_number: {
                    maxlength: "Reference number cannot exceed 100 characters."
                },

                remarks: {
                    maxlength: "Remarks cannot exceed 500 characters."
                }
            },

            /*
             * Revalidate dynamic account fields
             * whenever payment method changes.
             */
            onkeyup: function(element) {

                if (element.name === "amount" ||
                    element.name === "reference_number" ||
                    element.name === "remarks") {

                    this.element(element);
                }
            },

            submitHandler: function(form) {

                form.submit();
            }
        });


        /*
         * Payment Method changed:
         * validate only the currently required account.
         */
        $("#paymentMethod").on("change", function() {

            $("#bankAccount").valid();
            $("#cashAccount").valid();

        });

    });
</script>

<?php require_once '../../../includes/footer.php'; ?>