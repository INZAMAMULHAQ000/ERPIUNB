<?php
$pageTitle = 'Petty Cash Top-Up';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/finance_helpers.php';
require_once '../helpers/banking_helpers.php';

if (!hasPermission('finance.cash.transaction')) {
    die('Access Denied');
}

$db = getDB();
$errors = [];
$activeFY = getActiveFinancialYear($db);

$funds = $db->query("SELECT pcf.*, ca.cash_name, ca.chart_account_id, e.name AS custodian_name FROM petty_cash_funds pcf JOIN cash_accounts ca ON pcf.cash_account_id = ca.id LEFT JOIN employees e ON pcf.custodian_employee_id = e.id WHERE pcf.status = 'open' ORDER BY pcf.fund_name")->fetchAll();
$coaAccounts = $db->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE status = 'active' ORDER BY account_code")->fetchAll();

$selectedFundId = (int)($_GET['fund_id'] ?? ($_POST['petty_cash_fund_id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    }
    if (!$activeFY) {
        $errors[] = 'No active financial year.';
    }

    $petty_cash_fund_id = (int)($_POST['petty_cash_fund_id'] ?? 0);
    $amount = (float)str_replace(',', '', $_POST['amount'] ?? 0);
    $topup_date = trim($_POST['topup_date'] ?? '');
    $offset_account_id = (int)($_POST['offset_account_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if ($petty_cash_fund_id <= 0) {
        $errors[] = 'Select a petty cash fund.';
    }
    if ($amount <= 0) {
        $errors[] = 'Amount must be greater than zero.';
    }
    if (empty($topup_date)) {
        $errors[] = 'Top-up date is required.';
    }
    if ($offset_account_id <= 0) {
        $errors[] = 'Select the source account.';
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT pcf.*, ca.chart_account_id FROM petty_cash_funds pcf JOIN cash_accounts ca ON pcf.cash_account_id = ca.id WHERE pcf.id = ? AND pcf.status = 'open'");
            $stmt->execute([$petty_cash_fund_id]);
            $fund = $stmt->fetch();
            if (!$fund) throw new Exception('Petty cash fund not found or closed.');

            $cashChartId = (int)$fund['chart_account_id'];

            // Journal Voucher
            $vtStmt = $db->prepare("SELECT id FROM voucher_types WHERE voucher_prefix = 'JV' AND status = 'active' LIMIT 1");
            $vtStmt->execute();
            $vt = $vtStmt->fetch();
            if (!$vt) throw new Exception("Journal Voucher 'JV' not found.");
            $voucherTypeId = (int)$vt['id'];
            $voucherNo = generateVoucherNo($voucherTypeId, $db);

            $stmt = $db->prepare("INSERT INTO journal_entries (voucher_no, voucher_type_id, voucher_date, reference_no, description, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, 'draft', ?, NOW())");
            $stmt->execute([$voucherNo, $voucherTypeId, $topup_date, '', $description ?: 'Petty cash top-up: ' . $fund['fund_name'], $_SESSION['user_id']]);
            $journalId = (int)$db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description) VALUES (?, ?, ?, ?, ?)");
            // DR: Petty cash
            $stmt->execute([$journalId, $cashChartId, $amount, 0, 'Petty cash top-up: ' . $fund['fund_name']]);
            // CR: Source account
            $stmt->execute([$journalId, $offset_account_id, 0, $amount, 'Petty cash top-up from source']);

            require_once __DIR__ . '/../posting_engine/PostingEngine.php';
            $result = PostingEngine::post($journalId, $_SESSION['user_id']);
            if (!$result['success']) {
                throw new Exception($result['message']);
            }

            // Update fund balance
            $stmt = $db->prepare("UPDATE petty_cash_funds SET current_balance = current_balance + ? WHERE id = ?");
            $stmt->execute([$amount, $petty_cash_fund_id]);

            // Record transaction
            $stmt = $db->prepare("INSERT INTO petty_cash_transactions (petty_cash_fund_id, transaction_type, amount, description, voucher_number, journal_entry_id, created_by) VALUES (?, 'topup', ?, ?, ?, ?, ?)");
            $stmt->execute([$petty_cash_fund_id, $amount, $description ?: 'Petty cash topped up', $voucherNo, $journalId, $_SESSION['user_id']]);

            $db->commit();
            logAudit($_SESSION['user_id'], 'Petty cash top-up: AED ' . $amount . ' (' . $fund['fund_name'] . ')', 'finance');
            flash('success', 'Petty cash topped up by AED ' . formatNumber($amount) . '. New balance: AED ' . formatNumber((float)$fund['current_balance'] + $amount) . '. Voucher: ' . $voucherNo);
            to('modules/finance/petty_cash/index.php');
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Top-up failed: ' . $e->getMessage();
        }
    }
}
?>
<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Petty Cash</a>
</div>
<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">Top-Up Petty Cash Fund</h2>
    <?php if ($errors): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <form method="POST" class="space-y-4">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Petty Cash Fund <span class="text-red-600">*</span></labe>
                    <select name="petty_cash_fund_id" required class="w-full px-3 py-2 border rounded">
                        <option value="">Select Fund</option>
                        <?php foreach ($funds as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= ($selectedFundId === (int)$f['id'] || old('petty_cash_fund_id') == $f['id']) ? 'selected' : '' ?>>
                                <?= sanitize($f['fund_name']) ?> — Balance: AED <?= formatNumber($f['current_balance']) ?> / Imprest: AED <?= formatNumber($f['imprest_amount']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Amount (AED) <span class="text-red-600">*</span></labe>
                    <input type="number" name="amount" step="0.01" min="0.01" value="<?= old('amount') ?>" required class="w-full px-3 py-2 border rounded">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Top-Up Date <span class="text-red-600">*</span></labe>
                    <input type="date" name="topup_date" value="<?= old('topup_date', date('Y-m-d')) ?>" required class="w-full px-3 py-2 border rounded">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Source Account <span class="text-red-600">*</span></labe>
                    <select name="offset_account_id" required class="w-full px-3 py-2 border rounded">
                        <option value="">Select Source</option>
                        <?php foreach ($coaAccounts as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= old('offset_account_id') == $a['id'] ? 'selected' : '' ?>><?= sanitize($a['account_code'] . ' - ' . $a['account_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">The account funding the top-up (e.g. Bank, Main Cash)</p>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="2" class="w-full px-3 py-2 border rounded"><?= old('description') ?></textarea>
            </div>
        </div>
        <div class="pt-4 border-t">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700"><i class="fas fa-plus-circle mr-2"></i>Top-Up Fund</button>
            <a href="index.php" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>
<?php require_once '../../../includes/footer.php'; ?>