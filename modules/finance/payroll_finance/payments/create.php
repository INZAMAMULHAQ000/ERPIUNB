<?php
/**
 * Create Payment Batch
 * Phase F10 - Payroll & Finance Integration
 */
$pageTitle = 'Create Payment Batch';
require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

if (!hasPermission('payroll_finance.create')) {
    redirect_to('no-access.php');
}

$db = getDB();

// Get payroll runs that have been posted to finance but not yet fully paid
$postedRuns = $db->prepare("
    SELECT DISTINCT pfp.payroll_run_id, pfp.total_net, pfp.posting_date,
           COALESCE(SUM(ppb.total_amount), 0) as already_paid
    FROM payroll_finance_postings pfp
    LEFT JOIN payroll_payment_batches ppb ON pfp.payroll_run_id = ppb.payroll_run_id
        AND ppb.status NOT IN ('Failed', 'Cancelled')
    WHERE pfp.status = 'Posted'
    GROUP BY pfp.payroll_run_id
    HAVING (pfp.total_net - COALESCE(SUM(ppb.total_amount), 0)) > 0
    ORDER BY pfp.posting_date DESC
");
$postedRuns->execute();
$runs = $postedRuns->fetchAll();

// Get bank accounts
$banks = $db->prepare("SELECT id, account_name FROM bank_accounts WHERE status = 'active' ORDER BY account_name");
$banks->execute();
$bankAccounts = $banks->fetchAll();

// Get available payment methods
$paymentMethods = ['bank_transfer' => 'Bank Transfer', 'check' => 'Check', 'cash' => 'Cash'];
?>
<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="index.php" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-2"></i>Back to Payment Batches</a>
    </div>

    <div class="bg-white rounded-lg shadow p-8">
        <h1 class="text-2xl font-bold mb-6">Create Salary Payment Batch</h1>

        <?php if (empty($runs)): ?>
            <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded">
                <i class="fas fa-info-circle mr-2"></i>
                No payroll runs available for payment. All posted payroll runs have been fully paid, or no payroll has been posted yet.
                <a href="../postings/create.php" class="underline ml-2">Post a payroll first</a>.
            </div>
        <?php else: ?>
            <form method="POST" action="process.php" class="space-y-6">

                <!-- Payroll Run Selection -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payroll Run <span class="text-red-600">*</span></labe>
                        <select name="payroll_run_id" id="payrollRunSelect" class="w-full px-3 py-2 border rounded" required onchange="updateRunTotals()">
                            <option value="">-- Select Payroll Run --</option>
                            <?php foreach ($runs as $r): ?>
                                <option value="<?= $r['payroll_run_id'] ?>"
                                    data-total="<?= $r['total_net'] ?>"
                                    data-paid="<?= $r['already_paid'] ?>">
                                    Payroll Run #<?= (int)$r['payroll_run_id'] ?>
                                    - AED <?= number_format($r['total_net'], 2) ?>
                                    (Paid: AED <?= number_format($r['already_paid'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bank Account <span class="text-red-600">*</span></labe>
                        <select name="bank_account_id" class="w-full px-3 py-2 border rounded" required>
                            <option value="">-- Select Bank Account --</option>
                            <?php foreach ($bankAccounts as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= sanitize($b['account_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Date <span class="text-red-600">*</span></labe>
                        <input type="date" name="payment_date" class="w-full px-3 py-2 border rounded" required value="<?= date('Y-m-d') ?>">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method <span class="text-red-600">*</span></labe>
                        <select name="payment_method" class="w-full px-3 py-2 border rounded" required>
                            <?php foreach ($paymentMethods as $val => $label): ?>
                                <option value="<?= $val ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reference Number</label>
                        <input type="text" name="reference_number" class="w-full px-3 py-2 border rounded" placeholder="e.g. CHQ-001, TRANSFER-123">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Amount (AED) <span class="text-red-600">*</span></labe>
                        <input type="number" name="total_amount" id="paymentAmount" step="0.01" min="0.01"
                               class="w-full px-3 py-2 border rounded" required placeholder="0.00"
                               oninput="validateAmount()">
                        <p id="amountHint" class="text-xs text-gray-500 mt-1"></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Batch Name (Optional)</label>
                        <input type="text" name="batch_name" class="w-full px-3 py-2 border rounded" placeholder="e.g. Batch 1 - October 2024">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description / Notes</label>
                    <textarea name="description" rows="3" class="w-full px-3 py-2 border rounded"
                              placeholder="Optional notes for this payment batch..."></textarea>
                </div>

                <!-- Summary Card -->
                <div id="summaryCard" class="hidden bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-700 mb-3">Payment Summary</h3>
                    <div class="grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500">Payroll Total</p>
                            <p class="font-bold text-lg" id="sumPayrollTotal">AED 0.00</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Already Paid</p>
                            <p class="font-bold text-lg text-orange-600" id="sumAlreadyPaid">AED 0.00</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Remaining Balance</p>
                            <p class="font-bold text-lg text-green-600" id="sumRemaining">AED 0.00</p>
                        </div>
                    </div>
                </div>

                <!-- Warning: partial payment -->
                <div id="partialWarning" class="hidden bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Partial Payment:</strong> The payment amount is less than the remaining balance. A second batch will be needed for the remainder.
                </div>

                <!-- Error: overpayment -->
                <div id="overpayError" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                    <i class="fas fa-times-circle mr-2"></i>
                    <strong>Overpayment not allowed:</strong> The payment amount exceeds the remaining balance for this payroll run.
                </div>

                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <a href="index.php" class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">Cancel</a>
                    <button type="submit" id="submitBtn" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50" disabled>
                        <i class="fas fa-paper-plane mr-2"></i>Create Payment Batch
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
function updateRunTotals() {
    const select = document.getElementById('payrollRunSelect');
    const option = select.options[select.selectedIndex];
    const summaryCard = document.getElementById('summaryCard');
    const amountInput = document.getElementById('paymentAmount');

    if (!option.value) {
        summaryCard.classList.add('hidden');
        amountInput.value = '';
        document.getElementById('submitBtn').disabled = true;
        return;
    }

    const total = parseFloat(option.dataset.total) || 0;
    const paid = parseFloat(option.dataset.paid) || 0;
    const remaining = total - paid;

    document.getElementById('sumPayrollTotal').textContent = 'AED ' + formatMoneyRaw(total);
    document.getElementById('sumAlreadyPaid').textContent = 'AED ' + formatMoneyRaw(paid);
    document.getElementById('sumRemaining').textContent = 'AED ' + formatMoneyRaw(remaining);

    summaryCard.classList.remove('hidden');

    // Pre-fill with remaining balance
    amountInput.value = remaining.toFixed(2);
    amountInput.max = remaining;

    validateAmount();
}

function validateAmount() {
    const select = document.getElementById('payrollRunSelect');
    const option = select.options[select.selectedIndex];
    const amountInput = document.getElementById('paymentAmount');
    const submitBtn = document.getElementById('submitBtn');
    const partialWarning = document.getElementById('partialWarning');
    const overpayError = document.getElementById('overpayError');

    if (!option.value || !amountInput.value) {
        submitBtn.disabled = true;
        partialWarning.classList.add('hidden');
        overpayError.classList.add('hidden');
        return;
    }

    const total = parseFloat(option.dataset.total) || 0;
    const paid = parseFloat(option.dataset.paid) || 0;
    const remaining = total - paid;
    const amount = parseFloat(amountInput.value) || 0;

    if (amount <= 0) {
        submitBtn.disabled = true;
        partialWarning.classList.add('hidden');
        overpayError.classList.add('hidden');
        return;
    }

    if (amount > remaining + 0.01) {
        submitBtn.disabled = true;
        overpayError.classList.remove('hidden');
        partialWarning.classList.add('hidden');
    } else if (amount < remaining - 0.01) {
        submitBtn.disabled = false;
        partialWarning.classList.remove('hidden');
        overpayError.classList.add('hidden');
    } else {
        submitBtn.disabled = false;
        partialWarning.classList.add('hidden');
        overpayError.classList.add('hidden');
    }

    document.getElementById('amountHint').textContent =
        'Remaining: AED ' + formatMoneyRaw(remaining - amount);
}

function formatMoneyRaw(val) {
    return Number(val).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
</script>