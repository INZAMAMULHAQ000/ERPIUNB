<?php
$pageTitle = 'Record Customer Receipt';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/finance_helpers.php';
if (!hasPermission('customer_receipts.create')) {
    die('Access Denied');
}
$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}
// Receipt number from the company document sequence (RCPT + YYYYMM + 3).
$receipt_number = ($_SERVER['REQUEST_METHOD'] === 'POST')
    ? nextDocumentNumber($db, 'receipt')
    : peekDocumentNumber($db, 'receipt');
// Load clients for dropdown
$clients = $db->query("
    SELECT id, client_code, company_name
    FROM clients
    WHERE status = 'active' AND deleted_at IS NULL
    ORDER BY company_name
")->fetchAll();
$bankAccounts = $db->query("
    SELECT id, account_name, bank_name, account_number
    FROM bank_accounts
    WHERE status = 'active'
    ORDER BY account_name
")->fetchAll();
$cashAccounts = $db->query("
    SELECT id, cash_name, location
    FROM cash_accounts
    WHERE status = 'active'
    ORDER BY cash_name
")->fetchAll();
// If client_id is provided via GET, load their open invoices
$selected_client_id = (int)($_GET['client_id'] ?? old('client_id', 0));
$openInvoices = [];
if ($selected_client_id > 0) {
    $invStmt = $db->prepare("
        SELECT
            id,
            invoice_number,
            invoice_date,
            due_date,
            total_amount,
            paid_amount,
            balance_amount,
            status
        FROM customer_invoices
        WHERE client_id = ?
        AND status IN ('approved','sent','partially_paid','overdue')
        ORDER BY due_date ASC
    ");
    $invStmt->execute([$selected_client_id]);
    $openInvoices = $invStmt->fetchAll();
}
// --- POST Handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid or expired form token. Please try again.';
    }
    $client_id = (int)($_POST['client_id'] ?? 0);
    $receipt_date = trim($_POST['receipt_date'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $bank_account_id = !empty($_POST['bank_account_id'])
        ? (int)$_POST['bank_account_id']
        : null;
    $cash_account_id = !empty($_POST['cash_account_id'])
        ? (int)$_POST['cash_account_id']
        : null;
    $reference_number = trim($_POST['reference_number'] ?? '');
    $amount = (float)str_replace(
        ',',
        '',
        $_POST['amount'] ?? 0
    );
    $remarks = trim($_POST['remarks'] ?? '');
    $allocations = $_POST['allocations'] ?? [];
    // Validate
    if ($client_id <= 0) {
        $errors[] = 'Please select a client.';
    }
    if (empty($receipt_date)) {
        $errors[] = 'Receipt date is required.';
    }
    if (empty($payment_method)) {
        $errors[] = 'Payment method is required.';
    }
    if ($payment_method === 'cash' && $cash_account_id <= 0) {
        $errors[] = 'Please select a cash account for cash payments.';
    }
    if ($payment_method !== 'cash' && $bank_account_id <= 0) {
        $errors[] = 'Please select a bank account for this payment method.';
    }
    if ($amount <= 0) {
        $errors[] = 'Amount must be greater than zero.';
    }
    // Validate allocations
    $total_allocated = 0;
    if (!empty($allocations) && is_array($allocations)) {
        foreach ($allocations as $inv_id => $alloc) {
            $alloc_amount = (float)str_replace(
                ',',
                '',
                $alloc['amount'] ?? 0
            );
            if ($alloc_amount > 0) {
                $total_allocated += $alloc_amount;
            }
        }
    }
    if ($total_allocated > $amount) {
        $errors[] =
            'Total allocated amount (' .
            formatNumber($total_allocated) .
            ') exceeds receipt amount (' .
            formatNumber($amount) .
            ').';
    }
    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/finance/customer_receipts/create.php');
    }
    try {
        $db->beginTransaction();
        // 1. Insert receipt
        $stmt = $db->prepare("
            INSERT INTO customer_receipts
                (
                    receipt_number,
                    client_id,
                    receipt_date,
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
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $unallocated = $amount - $total_allocated;
        $stmt->execute([
            $receipt_number,
            $client_id,
            $receipt_date,
            $payment_method,
            $bank_account_id,
            $cash_account_id,
            $reference_number ?: null,
            $amount,
            $unallocated,
            $remarks ?: null,
            $_SESSION['user_id'],
        ]);
        $receipt_id = (int)$db->lastInsertId();
        // 2. Insert allocations
        if (!empty($allocations) && is_array($allocations)) {
            foreach ($allocations as $inv_id => $alloc) {
                $alloc_amount = (float)str_replace(
                    ',',
                    '',
                    $alloc['amount'] ?? 0
                );
                if ($alloc_amount <= 0) {
                    continue;
                }
                $stmt = $db->prepare("
                    INSERT INTO receipt_allocations
                        (receipt_id, invoice_id, amount)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $receipt_id,
                    (int)$inv_id,
                    $alloc_amount
                ]);
                // Update invoice paid_amount, balance_amount, status
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
                $invStmt->execute([(int)$inv_id]);
                $inv = $invStmt->fetch();
                if ($inv) {
                    $new_paid =
                        (float)$inv['paid_amount'] +
                        $alloc_amount;
                    $new_balance =
                        (float)$inv['balance_amount'] -
                        $alloc_amount;
                    if ($new_balance <= 0) {
                        $new_status = 'paid';
                        $new_balance = 0;
                    } else {
                        $new_status = 'partially_paid';
                    }
                    $updateStmt = $db->prepare("
                        UPDATE customer_invoices
                        SET
                            paid_amount = ?,
                            balance_amount = ?,
                            status = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([
                        $new_paid,
                        $new_balance,
                        $new_status,
                        (int)$inv_id
                    ]);
                    // Record status history
                    if ($new_status !== $inv['status']) {
                        $histStmt = $db->prepare("
                            INSERT INTO invoice_status_history
                                (
                                    invoice_id,
                                    from_status,
                                    to_status,
                                    changed_by,
                                    change_note
                                )
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $histStmt->execute([
                            (int)$inv_id,
                            $inv['status'],
                            $new_status,
                            $_SESSION['user_id'],
                            'Auto-updated via receipt ' . $receipt_number
                        ]);
                    }
                }
            }
        }
        // 3. Create journal entry
        require_once '../posting_engine/PostingEngine.php';
        // Get chart account ID based on payment method
        $chart_account_id = null;
        if ($payment_method === 'cash' && $cash_account_id) {
            $stmt = $db->prepare("
                SELECT chart_account_id
                FROM cash_accounts
                WHERE id = ?
            ");
            $stmt->execute([$cash_account_id]);
            $chart_account_id = (int)$stmt->fetchColumn();
        } elseif ($bank_account_id) {
            $stmt = $db->prepare("
                SELECT chart_account_id
                FROM bank_accounts
                WHERE id = ?
            ");
            $stmt->execute([$bank_account_id]);
            $chart_account_id = (int)$stmt->fetchColumn();
        }
        // Get AR account from settings
        $arSetting = $db->query("
            SELECT setting_value
            FROM settings
            WHERE setting_key = 'finance_ar_account_id'
        ")->fetch();
        $ar_account_id = $arSetting
            ? (int)$arSetting['setting_value']
            : 0;
        if ($chart_account_id && $ar_account_id) {
            // Get Receipt voucher type
            $vtStmt = $db->prepare("
                SELECT id
                FROM voucher_types
                WHERE voucher_name = 'Receipt'
                AND status = 'active'
                LIMIT 1
            ");
            $vtStmt->execute();
            $voucher_type_id = (int)$vtStmt->fetchColumn();
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
                    VALUES (?, ?, ?, ?, ?, 'draft', ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $voucher_no,
                    $voucher_type_id,
                    $receipt_date,
                    $receipt_number,
                    'Customer receipt: ' .
                        $receipt_number .
                        ' - Payment received',
                    $_SESSION['user_id'],
                ]);
                $journal_entry_id =
                    (int)$db->lastInsertId();
                // DR: Cash/Bank account
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
                    $chart_account_id,
                    $amount,
                    0,
                    'Payment received - ' . $receipt_number
                ]);
                // CR: AR account
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
                    $ar_account_id,
                    0,
                    $amount,
                    'Accounts Receivable - ' . $receipt_number
                ]);
                // Update receipt with journal entry id
                $stmt = $db->prepare("
                    UPDATE customer_receipts
                    SET journal_entry_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $journal_entry_id,
                    $receipt_id
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
            } else {
                // Voucher type not found - log warning but don't fail
                error_log(
                    'Receipt voucher type not found - journal not created for receipt ' .
                        $receipt_number
                );
            }
        } else {
            // Missing account configuration - log warning
            error_log(
                'Missing chart account or AR account - journal not created for receipt ' .
                    $receipt_number
            );
        }
        $db->commit();
        logAudit(
            $_SESSION['user_id'],
            'Recorded customer receipt: ' .
                $receipt_number .
                ' (Amount: ' .
                $amount .
                ')',
            'customer_receipts'
        );
        flash(
            'success',
            'Receipt recorded successfully. Receipt #' .
                $receipt_number,
            'success'
        );
        redirect('index.php');
    } catch (Exception $e) {
        $db->rollBack();
        flash(
            'error',
            'Error recording receipt: ' .
                $e->getMessage()
        );
        to('modules/finance/customer_receipts/create.php');
    }
}
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            Record Customer Receipt
        </h1>
        <p class="text-gray-600">
            Record a payment received from a customer
        </p>
    </div>
    <a href="index.php"
        class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
        <i class="fas fa-arrow-left mr-2"></i>
        Back to Receipts
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
        id="receiptForm"
        class="space-y-4"
        novalidate>
        <input type="hidden"
            name="csrf_token"
            value="<?= csrfToken() ?>">
        <!-- Header Row -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Receipt Number
                </label>
                <input type="text"
                    value="<?= sanitize($receipt_number) ?>"
                    readonly
                    class="w-full px-3 py-2 border rounded bg-gray-100 text-gray-600">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Receipt Date
                    <span class="text-red-500">*</span>
                </label>
                <input type="date"
                    name="receipt_date"
                    value="<?= old('receipt_date', date('Y-m-d')) ?>"
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
                    <option value="">Select Client</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>"
                            <?= $selected_client_id == $c['id'] ? 'selected' : '' ?>>
                            <?= sanitize($c['company_name']) ?>
                            (<?= sanitize($c['client_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Reference Number
                </label>
                <input type="text"
                    name="reference_number"
                    value="<?= old('reference_number') ?>"
                    maxlength="100"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    placeholder="Cheque/Ref #">
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Payment Method
                    <span class="text-red-500">*</span>
                </label>
                <select name="payment_method"
                    id="paymentMethod"
                    required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                    onchange="toggleAccountFields()">
                    <option value="">Select Method</option>
                    <option value="cash"
                        <?= old('payment_method') === 'cash' ? 'selected' : '' ?>>
                        Cash
                    </option>
                    <option value="bank_transfer"
                        <?= old('payment_method') === 'bank_transfer' ? 'selected' : '' ?>>
                        Bank Transfer
                    </option>
                    <option value="cheque"
                        <?= old('payment_method') === 'cheque' ? 'selected' : '' ?>>
                        Cheque
                    </option>
                    <option value="credit_card"
                        <?= old('payment_method') === 'credit_card' ? 'selected' : '' ?>>
                        Credit Card
                    </option>
                    <option value="online"
                        <?= old('payment_method') === 'online' ? 'selected' : '' ?>>
                        Online
                    </option>
                </select>
            </div>
            <div id="bankAccountGroup"
                style="display:none">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Bank Account
                    <span class="text-red-500">*</span>
                </label>
                <select name="bank_account_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">
                        Select Bank Account
                    </option>
                    <?php foreach ($bankAccounts as $ba): ?>
                        <option value="<?= $ba['id'] ?>"
                            <?= old('bank_account_id') == $ba['id'] ? 'selected' : '' ?>>
                            <?= sanitize($ba['account_name']) ?>
                            -
                            <?= sanitize($ba['bank_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="cashAccountGroup"
                style="display:none">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Cash Account
                    <span class="text-red-500">*</span>
                </label>
                <select name="cash_account_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">
                        Select Cash Account
                    </option>
                    <?php foreach ($cashAccounts as $ca): ?>
                        <option value="<?= $ca['id'] ?>"
                            <?= old('cash_account_id') == $ca['id'] ? 'selected' : '' ?>>
                            <?= sanitize($ca['cash_name']) ?>
                            (<?= sanitize($ca['location'] ?? 'N/A') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Receipt Amount
                    <span class="text-red-500">*</span>
                </label>
                <input type="number"
                    name="amount"
                    id="receiptAmount"
                    step="0.01"
                    min="0.01"
                    value="<?= old('amount', '0') ?>"
                    required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 text-right"
                    oninput="validateAllocations()">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Remarks
            </label>
            <textarea name="remarks"
                rows="2"
                maxlength="1000"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                placeholder="Optional remarks"><?= old('remarks') ?></textarea>
        </div>
        <?php if (!empty($openInvoices)): ?>
            <!-- Invoice Allocations -->
            <div class="mt-6 pt-4 border-t">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">
                    Allocate Payment to Invoices
                </h3>
                <p class="text-sm text-gray-500 mb-3">
                    Allocate the receipt amount to one or more open invoices.
                    Total allocated must not exceed the receipt amount.
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full"
                        id="allocationsTable">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    Invoice #
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    Date
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                    Total
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                    Balance
                                </th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                    Status
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                    Allocate
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php $total_balance = 0; ?>
                            <?php foreach ($openInvoices as $inv):
                                $total_balance +=
                                    (float)$inv['balance_amount'];
                            ?>
                                <tr>
                                    <td class="px-4 py-3 text-sm font-mono text-gray-900">
                                        <?= sanitize($inv['invoice_number']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                        <?= date(
                                            'M d, Y',
                                            strtotime($inv['invoice_date'])
                                        ) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900">
                                        <?= formatMoney(
                                            (float)$inv['total_amount']
                                        ) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right font-mono
                                <?= (float)$inv['balance_amount'] > 0
                                    ? 'text-red-600'
                                    : 'text-green-600' ?>">
                                        <?= formatMoney(
                                            (float)$inv['balance_amount']
                                        ) ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <?php
                                        $invBadges = [
                                            'approved' =>
                                            'bg-blue-100 text-blue-800',
                                            'sent' =>
                                            'bg-indigo-100 text-indigo-800',
                                            'partially_paid' =>
                                            'bg-yellow-100 text-yellow-800',
                                            'overdue' =>
                                            'bg-red-100 text-red-800',
                                        ];
                                        ?>
                                        <span class="px-2 py-1 text-xs rounded font-medium
                                    <?= $invBadges[$inv['status']]
                                        ?? 'bg-gray-100 text-gray-800' ?>">
                                            <?= ucwords(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $inv['status']
                                                )
                                            ) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <input type="number"
                                            name="allocations[<?= $inv['id'] ?>][amount]"
                                            step="0.01"
                                            min="0"
                                            max="<?= (float)$inv['balance_amount'] ?>"
                                            value="0"
                                            class="w-32 px-2 py-2 border rounded text-right allocation-input"
                                            data-balance="<?= (float)$inv['balance_amount'] ?>"
                                            oninput="validateAllocations()">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold">
                            <tr>
                                <td colspan="4"
                                    class="px-4 py-3 text-sm text-gray-600">
                                    Total Outstanding Balance:
                                    <span class="font-bold text-gray-800">
                                        <?= formatMoney($total_balance) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-sm">
                                    Total Allocated:
                                </td>
                                <td class="px-4 py-3 text-right font-mono"
                                    id="totalAllocated">
                                    0.00
                                </td>
                            </tr>
                            <tr id="allocationWarning"
                                class="hidden">
                                <td colspan="6"
                                    class="px-4 py-2 text-sm text-red-600 text-right">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Allocated amount exceeds receipt amount
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        <?php elseif ($selected_client_id > 0): ?>
            <div class="mt-6 pt-4 border-t">
                <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded">
                    <i class="fas fa-info-circle mr-2"></i>
                    This client has no open invoices requiring payment.
                </div>
            </div>
        <?php endif; ?>
        <!-- Submit -->
        <div class="pt-4 border-t flex items-center justify-between">
            <div>
                <span id="unallocatedDisplay"
                    class="text-sm text-gray-600">
                    Unallocated:
                    <span class="font-mono font-bold">
                        <?= old('amount', '0.00') ?>
                    </span>
                </span>
            </div>
            <div>
                <button type="submit"
                    class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition"
                    id="submitBtn">
                    <i class="fas fa-save mr-2"></i>
                    Record Receipt
                </button>
                <a href="index.php"
                    class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">
                    Cancel
                </a>
            </div>
        </div>
    </form>
</div>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- jQuery Validation -->
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>
<script>
    function toggleAccountFields() {
        const method =
            document.getElementById('paymentMethod').value;
        const bankGroup =
            document.getElementById('bankAccountGroup');
        const cashGroup =
            document.getElementById('cashAccountGroup');
        if (method === 'cash') {
            bankGroup.style.display = 'none';
            cashGroup.style.display = 'block';
        } else if (method) {
            cashGroup.style.display = 'none';
            bankGroup.style.display = 'block';
        } else {
            bankGroup.style.display = 'none';
            cashGroup.style.display = 'none';
        }
    }

    function validateAllocations() {
        const receiptAmount =
            parseFloat(
                document.getElementById('receiptAmount').value
            ) || 0;
        const inputs =
            document.querySelectorAll('.allocation-input');
        let totalAllocated = 0;
        inputs.forEach(function(input) {
            const val =
                parseFloat(input.value) || 0;
            const balance =
                parseFloat(
                    input.getAttribute('data-balance')
                ) || 0;
            // Clamp to balance
            if (val > balance) {
                input.value =
                    balance.toFixed(2);
                totalAllocated += balance;
            } else {
                totalAllocated += val;
            }
        });
        document.getElementById('totalAllocated').textContent =
            totalAllocated.toFixed(2);
        const unallocated =
            receiptAmount - totalAllocated;
        document.getElementById('unallocatedDisplay').innerHTML =
            'Unallocated: <span class="font-mono font-bold ' +
            (unallocated < 0 ?
                'text-red-600' :
                'text-gray-800') +
            '">' +
            unallocated.toFixed(2) +
            '</span>';
        const warning =
            document.getElementById('allocationWarning');
        const submitBtn =
            document.getElementById('submitBtn');
        if (totalAllocated > receiptAmount) {
            warning.classList.remove('hidden');
            submitBtn.disabled = true;
            submitBtn.classList.add(
                'opacity-50',
                'cursor-not-allowed'
            );
        } else {
            warning.classList.add('hidden');
            submitBtn.disabled = false;
            submitBtn.classList.remove(
                'opacity-50',
                'cursor-not-allowed'
            );
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
        | Reference Number
        |--------------------------------------------------------------------------
        */
        $('input[name="reference_number"]').on('input', function() {
            let value = $(this).val();
            value = value.replace(
                /[^A-Za-z0-9 .,&()_/@#+'-]/g,
                ''
            );
            value = value.replace(
                /\s{2,}/g,
                ' '
            );
            $(this).val(
                value.substring(0, 100)
            );
        });
        /*
        |--------------------------------------------------------------------------
        | Receipt Amount
        |--------------------------------------------------------------------------
        */
        $('input[name="amount"]').on('input', function() {
            let value = $(this).val();
            value = value.replace(
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
            validateAllocations();
        });
        /*
        |--------------------------------------------------------------------------
        | Allocation Amount
        |--------------------------------------------------------------------------
        */
        $(document).on(
            'input',
            '.allocation-input',
            function() {
                let value = $(this).val();
                value = value.replace(
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
                validateAllocations();
            }
        );
        /*
        |--------------------------------------------------------------------------
        | Remarks
        |--------------------------------------------------------------------------
        */
        $('textarea[name="remarks"]').on('input', function() {
            let value = $(this).val();
            value = value.replace(
                /\s{2,}/g,
                ' '
            );
            $(this).val(
                value.substring(0, 1000)
            );
        });
        /*
        |--------------------------------------------------------------------------
        | Custom Payment Method Validation
        |--------------------------------------------------------------------------
        */
        $.validator.addMethod(
            'paymentAccount',
            function(value, element) {
                const method =
                    $('select[name="payment_method"]').val();
                if (method === 'cash') {
                    const cashAccount =
                        $('select[name="cash_account_id"]').val();
                    return cashAccount !== '';
                }
                if (method !== '') {
                    const bankAccount =
                        $('select[name="bank_account_id"]').val();
                    return bankAccount !== '';
                }
                return true;
            },
            'Please select the required account.'
        );
        /*
        |--------------------------------------------------------------------------
        | Allocation Validation
        |--------------------------------------------------------------------------
        */
        $.validator.addMethod(
            'validAllocation',
            function(value, element) {
                if (value === '') {
                    return true;
                }
                const amount =
                    parseFloat(value) || 0;
                const balance =
                    parseFloat(
                        $(element).data('balance')
                    ) || 0;
                return amount >= 0 &&
                    amount <= balance;
            },
            'Allocation cannot exceed the invoice balance.'
        );
        /*
        |--------------------------------------------------------------------------
        | Total Allocation Validation
        |--------------------------------------------------------------------------
        */
        $.validator.addMethod(
            'allocationTotalValid',
            function(value, element) {
                const receiptAmount =
                    parseFloat(
                        $('input[name="amount"]').val()
                    ) || 0;
                let totalAllocated = 0;
                $('.allocation-input').each(function() {
                    const amount =
                        parseFloat($(this).val()) || 0;
                    totalAllocated += amount;
                });
                return totalAllocated <= receiptAmount;
            },
            'Total allocated amount cannot exceed receipt amount.'
        );
        /*
        |--------------------------------------------------------------------------
        | Form Validation
        |--------------------------------------------------------------------------
        */
        $('#receiptForm').validate({
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
                receipt_date: {
                    required: true,
                    dateISO: true
                },
                payment_method: {
                    required: true
                },
                bank_account_id: {
                    digits: true,
                    min: 1,
                    paymentAccount: true
                },
                cash_account_id: {
                    digits: true,
                    min: 1,
                    paymentAccount: true
                },
                reference_number: {
                    maxlength: 100
                },
                amount: {
                    required: true,
                    number: true,
                    min: 0.01,
                    allocationTotalValid: true
                },
                remarks: {
                    maxlength: 1000
                }
            },
            messages: {
                client_id: {
                    required: 'Please select a client.',
                    digits: 'Invalid client.',
                    min: 'Please select a valid client.'
                },
                receipt_date: {
                    required: 'Please select the receipt date.',
                    dateISO: 'Please enter a valid receipt date.'
                },
                payment_method: {
                    required: 'Please select a payment method.'
                },
                bank_account_id: {
                    digits: 'Invalid bank account.',
                    min: 'Please select a valid bank account.',
                    paymentAccount: 'Please select a bank account for this payment method.'
                },
                cash_account_id: {
                    digits: 'Invalid cash account.',
                    min: 'Please select a valid cash account.',
                    paymentAccount: 'Please select a cash account for cash payments.'
                },
                reference_number: {
                    maxlength: 'Reference number cannot exceed 100 characters.'
                },
                amount: {
                    required: 'Please enter the receipt amount.',
                    number: 'Please enter a valid amount.',
                    min: 'Receipt amount must be greater than zero.',
                    allocationTotalValid: 'Total allocated amount cannot exceed receipt amount.'
                },
                remarks: {
                    maxlength: 'Remarks cannot exceed 1000 characters.'
                }
            },
            submitHandler: function(form) {
                let valid = true;
                /*
                |--------------------------------------------------------------------------
                | Validate Allocation Inputs
                |--------------------------------------------------------------------------
                */
                $('.allocation-input').each(function() {
                    const value =
                        parseFloat($(this).val()) || 0;
                    const balance =
                        parseFloat(
                            $(this).data('balance')
                        ) || 0;
                    if (value < 0 || value > balance) {
                        valid = false;
                        $(this).addClass(
                            'border-red-500'
                        );
                    } else {
                        $(this).removeClass(
                            'border-red-500'
                        );
                    }
                });
                /*
                |--------------------------------------------------------------------------
                | Validate Total Allocation
                |--------------------------------------------------------------------------
                */
                const receiptAmount =
                    parseFloat(
                        $('#receiptAmount').val()
                    ) || 0;
                let totalAllocated = 0;
                $('.allocation-input').each(function() {
                    totalAllocated +=
                        parseFloat($(this).val()) || 0;
                });
                if (totalAllocated > receiptAmount) {
                    valid = false;
                    $('#allocationWarning')
                        .removeClass('hidden');
                } else {
                    $('#allocationWarning')
                        .addClass('hidden');
                }
                if (!valid) {
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
        | Revalidate Account Fields when Payment Method Changes
        |--------------------------------------------------------------------------
        */
        $('#paymentMethod').on('change', function() {
            $('#bankAccountGroup select').valid();
            $('#cashAccountGroup select').valid();
        });
        /*
        |--------------------------------------------------------------------------
        | Account Selection Validation
        |--------------------------------------------------------------------------
        */
        $('#bankAccountGroup select, #cashAccountGroup select')
            .on('change', function() {
                $(this).valid();
            });
        /*
        |--------------------------------------------------------------------------
        | Initialize
        |--------------------------------------------------------------------------
        */
        toggleAccountFields();
        validateAllocations();
    });
</script>
<?php require_once '../../../includes/footer.php'; ?>