<?php
$pageTitle = 'Edit Payroll Payment Batch';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';

if (!hasPermission('payrollfinance.pay')) { die('Access Denied'); }

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Invalid payment batch ID.');
    to('index.php');
}

// Fetch the payment batch
$stmt = $db->prepare("SELECT ppb.*, pr.id AS run_id, pr.period_start, pr.period_end, pr.net_salary FROM payroll_payment_batches ppb JOIN payroll_runs pr ON ppb.payroll_run_id = pr.id WHERE ppb.id = ?");
$stmt->execute([$id]);
$batch = $stmt->fetch();
if (!$batch) {
    flash('error', 'Payment batch not found.');
    to('index.php');
}

// Fetch posted payroll runs with outstanding amounts (considering this payment batch's amount if editing)
$payrollRuns = [];
$payrollRunsStmt = $db->query("
    SELECT 
        pfp.payroll_run_id,
        pfp.payroll_period_start AS period_start,
        pfp.payroll_period_end AS period_end,
        pfp.total_net AS net_salary,
        COALESCE(SUM(pp.amount), 0) as amount_paid
    FROM payroll_finance_postings pfp
    LEFT JOIN payroll_payments pp ON pfp.payroll_run_id = pp.payroll_run_id
    WHERE pfp.status = 'Posted'
    GROUP BY pfp.payroll_run_id, pfp.payroll_period_start, pfp.payroll_period_end, pfp.total_net, pfp.status
    HAVING (pfp.total_net - COALESCE(SUM(pp.amount), 0)) > 0
    ORDER BY pfp.payroll_period_start DESC
");
while ($run = $payrollRunsStmt->fetch()) {
    $outstanding = $run['net_salary'] - $run['amount_paid'];
    $payrollRuns[] = [
        'id' => $run['payroll_run_id'],
        'period_start' => $run['period_start'],
        'period_end' => $run['period_end'],
        'net_salary' => $run['net_salary'],
        'amount_paid' => $run['amount_paid'],
        'outstanding_amount' => $outstanding
    ];
}

// Fetch active bank accounts
$bankAccounts = [];
$bankAccountsStmt = $db->query("SELECT id, account_name FROM bank_accounts WHERE status = 'active' ORDER BY account_name");
while ($account = $bankAccountsStmt->fetch()) {
    $bankAccounts[] = $account;
}

// If GET request, populate form with existing batch data (handled via old() with second parameter)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // No need to set _OLD; we'll use old() with default value in the form
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'payroll_run_id' => $_POST['payroll_run_id'] ?? '',
        'bank_account_id' => $_POST['bank_account_id'] ?? '',
        'payment_date' => $_POST['payment_date'] ?? '',
        'amount' => $_POST['amount'] ?? '',
        'reference_number' => $_POST['reference_number'] ?? '',
        'description' => $_POST['description'] ?? '',
    ];

    $errors = validate($data, [
        'payroll_run_id' => 'required',
        'bank_account_id' => 'required',
        'payment_date' => 'required',
        'amount' => 'required|numeric',
        'reference_number' => 'required',
        'description' => ''
    ]);

    // Validate that payroll run exists and has sufficient outstanding amount (excluding current batch)
    if (empty($errors['payroll_run_id']) && !empty($data['payroll_run_id'])) {
        $runStmt = $db->prepare("
            SELECT pfp.total_net AS net_salary, COALESCE(SUM(pp.amount), 0) as amount_paid
            FROM payroll_finance_postings pfp
            LEFT JOIN payroll_payments pp ON pfp.payroll_run_id = pp.payroll_run_id
            WHERE pfp.payroll_run_id = ? AND pfp.status = 'Posted'
            GROUP BY pfp.payroll_run_id
        ");
        $runStmt->execute([$data['payroll_run_id']]);
        $run = $runStmt->fetch();
        if ($run) {
            // Calculate outstanding amount excluding current batch
            $batchAmountStmt = $db->prepare("SELECT amount FROM payroll_payment_batches WHERE payroll_run_id = ? AND id != ?");
            $batchAmountStmt->execute([$data['payroll_run_id'], $id]);
            $batchAmount = $batchAmountStmt->fetchColumn() ?: 0;
            $outstanding = $run['net_salary'] - $run['amount_paid'] - $batchAmount;
            if ((float)$data['amount'] > $outstanding) {
                $errors['amount'] = 'Amount cannot exceed outstanding amount of ' . formatPayrollAmount($outstanding);
            }
        } else {
            $errors['payroll_run_id'] = 'Invalid payroll run';
        }
    }

    // Validate that bank account exists
    if (empty($errors['bank_account_id']) && !empty($data['bank_account_id'])) {
        $accountStmt = $db->prepare("SELECT id FROM bank_accounts WHERE id = ? AND status = 'active'");
        $accountStmt->execute([$data['bank_account_id']]);
        if (!$accountStmt->fetch()) {
            $errors['bank_account_id'] = 'Invalid bank account';
        }
    }

    if (empty($errors)) {
        $stmt = $db->prepare("
            UPDATE payroll_payment_batches 
            SET payroll_run_id = ?, bank_account_id = ?, payment_date = ?, amount = ?, reference_number = ?, description = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $data['payroll_run_id'],
            $data['bank_account_id'],
            $data['payment_date'],
            $data['amount'],
            $data['reference_number'],
            $data['description'],
            $_SESSION['user_id'],
            $id
        ]);

        logAudit($_SESSION['user_id'], 'Updated payroll payment batch: ' . $data['reference_number'], 'payroll');
        flash('success', 'Payment batch updated successfully');
        to('index.php');
    } else {
        saveFormState($errors);
        to('edit.php?id=' . $id);
    }
}
?>
<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Payment Batches</a>
</div>

<div class="bg-white rounded-lg shadow p-6 max-w-4xl">
    <h2 class="text-xl font-semibold mb-6">Edit Payment Batch</h2>

    <?php if ($errors): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Payroll Run <span class="text-red-600">*</span></labe>
                <select name="payroll_run_id" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Payroll Run</option>
                    <?php foreach ($payrollRuns as $run): ?>
                    <option value="<?= $run['id'] ?>" 
                            <?= old('payroll_run_id', $batch['payroll_run_id']) == $run['id'] ? 'selected' : '' ?>>
                        <?= sprintf('%s (%s to %s)', $run['id'], 
                            htmlspecialchars($run['period_start']), 
                            htmlspecialchars($run['period_end'])) ?>
                        (Outstanding: <?= formatPayrollAmount($run['outstanding_amount']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Bank Account <span class="text-red-600">*</span></labe>
                <select name="bank_account_id" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Bank Account</option>
                    <?php foreach ($bankAccounts as $account): ?>
                    <option value="<?= $account['id'] ?>" 
                            <?= old('bank_account_id', $batch['bank_account_id']) == $account['id'] ? 'selected' : '' ?>>
                        <?= sanitize($account['account_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date <span class="text-red-600">*</span></labe>
                <input type="date" name="payment_date" value="<?= old('payment_date', $batch['payment_date']) ?>" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Amount <span class="text-red-600">*</span></labe>
                <input type="number" name="amount" value="<?= old('amount', $batch['amount']) ?>" min="0.01" step="0.01" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-500 mt-1">Outstanding amount for selected payroll run will be validated on submit.</p>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Reference Number <span class="text-red-600">*</span></labe>
            <input type="text" name="reference_number" value="<?= sanitize(old('reference_number', $batch['reference_number'])) ?>" required placeholder="e.g. SALARY202608" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="3" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= sanitize(old('description', $batch['description'])) ?></textarea>
        </div>

        <div class="pt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">Update Payment Batch</button>
            <a href="index.php" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../../includes/footer.php'; ?>
