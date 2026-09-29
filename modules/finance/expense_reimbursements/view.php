<?php
$pageTitle = 'View Reimbursement';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('expense_reimbursements.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('error', 'Invalid reimbursement ID.'); redirect('index.php'); }

$stmt = $db->prepare("
    SELECT er.*, e.first_name, e.last_name, e.employee_code,
        ec.claim_number, ec.description AS claim_description, ec.total_amount AS claim_amount,
        ec.status AS claim_status, je.voucher_no AS je_voucher_no,
        ba.account_name AS bank_account_name, ca.cash_name AS cash_account_name
    FROM expense_reimbursements er
    LEFT JOIN employees e ON er.employee_id = e.id
    LEFT JOIN employee_expense_claims ec ON er.claim_id = ec.id
    LEFT JOIN journal_entries je ON er.journal_entry_id = je.id
    LEFT JOIN bank_accounts ba ON er.bank_account_id = ba.id
    LEFT JOIN cash_accounts ca ON er.cash_account_id = ca.id
    WHERE er.id = ?
");
$stmt->execute([$id]);
$reimb = $stmt->fetch();
if (!$reimb) { flash('error', 'Reimbursement not found.'); redirect('index.php'); }

$statusBadges = [
    'draft' => 'bg-gray-100 text-gray-800',
    'paid' => 'bg-green-100 text-green-800',
    'cancelled' => 'bg-gray-200 text-gray-600',
];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Reimbursement: <?= sanitize($reimb['reimbursement_number']) ?></h1>
        <p class="text-gray-600"><?= sanitize($reimb['first_name'] . ' ' . $reimb['last_name']) ?></p>
    </div>
    <div class="flex gap-2">
        <a href="index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back</a>
        <a href="../employee_claims/view.php?id=<?= $reimb['claim_id'] ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-eye mr-2"></i>View Claim</a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Payment Details</h3>
                <span class="px-3 py-1 text-sm rounded font-medium <?= $statusBadges[$reimb['status']] ?? 'bg-gray-100' ?>">
                    <?= ucfirst($reimb['status']) ?>
                </span>
            </div>
            <dl class="space-y-3">
                <div><dt class="text-sm text-gray-500">Employee</dt><dd class="text-sm font-medium"><?= sanitize($reimb['first_name'] . ' ' . $reimb['last_name']) ?> (<?= sanitize($reimb['employee_code']) ?>)</dd></div>
                <div><dt class="text-sm text-gray-500">Claim</dt><dd class="text-sm font-medium"><a href="../employee_claims/view.php?id=<?= $reimb['claim_id'] ?>" class="text-blue-600 hover:underline"><?= sanitize($reimb['claim_number']) ?></a></dd></div>
                <div><dt class="text-sm text-gray-500">Payment Method</dt><dd class="text-sm font-medium"><?= ucwords(str_replace('_', ' ', $reimb['payment_method'])) ?></dd></div>
                <?php if ($reimb['bank_account_name']): ?>
                <div><dt class="text-sm text-gray-500">Bank Account</dt><dd class="text-sm font-medium"><?= sanitize($reimb['bank_account_name']) ?></dd></div>
                <?php endif; ?>
                <?php if ($reimb['cash_account_name']): ?>
                <div><dt class="text-sm text-gray-500">Cash Account</dt><dd class="text-sm font-medium"><?= sanitize($reimb['cash_account_name']) ?></dd></div>
                <?php endif; ?>
                <div><dt class="text-sm text-gray-500">Payment Date</dt><dd class="text-sm font-medium"><?= date('M d, Y', strtotime($reimb['payment_date'])) ?></dd></div>
                <?php if ($reimb['reference_number']): ?>
                <div><dt class="text-sm text-gray-500">Reference #</dt><dd class="text-sm font-medium"><?= sanitize($reimb['reference_number']) ?></dd></div>
                <?php endif; ?>
                <?php if ($reimb['je_voucher_no']): ?>
                <div><dt class="text-sm text-gray-500">Journal Voucher</dt><dd class="text-sm font-medium"><?= sanitize($reimb['je_voucher_no']) ?></dd></div>
                <?php endif; ?>
            </dl>
            <?php if ($reimb['remarks']): ?>
            <div class="mt-4"><h4 class="text-sm font-medium text-gray-700 mb-1">Remarks</h4><p class="text-sm text-gray-600 whitespace-pre-line"><?= sanitize($reimb['remarks']) ?></p></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Amount</h3>
            <dl>
                <div class="flex justify-between items-center p-4 bg-gray-50 rounded">
                    <span class="text-lg font-bold text-gray-700">Reimbursement Amount</span>
                    <span class="text-2xl font-bold font-mono text-green-600"><?= formatMoney((float)$reimb['amount']) ?></span>
                </div>
            </dl>
        </div>

        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Accounting Entry</h3>
            <div class="bg-gray-50 rounded p-4">
                <table class="w-full text-sm">
                    <thead><tr><th class="text-left font-medium text-gray-500 pb-2">Account</th><th class="text-right font-medium text-gray-500 pb-2">Debit</th><th class="text-right font-medium text-gray-500 pb-2">Credit</th></tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr><td class="py-2">Expense Payable</td><td class="text-right font-mono"><?= formatMoney((float)$reimb['amount']) ?></td><td class="text-right font-mono">--</td></tr>
                        <tr><td class="py-2"><?= $reimb['bank_account_name'] ? sanitize($reimb['bank_account_name']) : sanitize($reimb['cash_account_name'] ?? 'Cash/Bank') ?></td><td class="text-right font-mono">--</td><td class="text-right font-mono"><?= formatMoney((float)$reimb['amount']) ?></td></tr>
                    </tbody>
                </table>
            </div>
            <?php if ($reimb['je_voucher_no']): ?>
            <p class="text-xs text-gray-500 mt-3">Posted to General Ledger via voucher: <span class="font-mono"><?= sanitize($reimb['je_voucher_no']) ?></span></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>
