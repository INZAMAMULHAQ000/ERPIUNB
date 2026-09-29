<?php
$pageTitle = 'View Vendor Payment';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('vendor_payments.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('error', 'Invalid payment ID.'); redirect('index.php'); }

$stmt = $db->prepare("
    SELECT vp.*, v.vendor_name, v.vendor_code, u.full_name AS created_by_name,
        ba.account_name AS bank_account_name, ca.cash_name AS cash_account_name,
        je.voucher_no AS je_voucher_no
    FROM vendor_payments vp
    JOIN vendors v ON vp.vendor_id = v.id
    LEFT JOIN users u ON vp.created_by = u.id
    LEFT JOIN bank_accounts ba ON vp.bank_account_id = ba.id
    LEFT JOIN cash_accounts ca ON vp.cash_account_id = ca.id
    LEFT JOIN journal_entries je ON vp.journal_entry_id = je.id
    WHERE vp.id = ?
");
$stmt->execute([$id]);
$payment = $stmt->fetch();
if (!$payment) { flash('error', 'Vendor payment not found.'); redirect('index.php'); }

$allocations = $db->prepare("
    SELECT pa.*, vb.bill_number, vb.total_amount, vb.balance_amount, vb.paid_amount
    FROM payment_allocations pa
    JOIN vendor_bills vb ON pa.bill_id = vb.id
    WHERE pa.payment_id = ? ORDER BY pa.created_at ASC
");
$allocations->execute([$id]);
$allocations = $allocations->fetchAll();

$methodLabels = ['cash' => 'Cash', 'bank_transfer' => 'Bank Transfer', 'cheque' => 'Cheque', 'credit_card' => 'Credit Card', 'online' => 'Online'];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Payment: <?= sanitize($payment['payment_number']) ?></h1>
        <p class="text-gray-600"><?= sanitize($payment['vendor_name']) ?> · <?= date('M d, Y', strtotime($payment['payment_date'])) ?></p>
    </div>
    <div class="flex gap-2">
        <a href="index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back</a>
        <?php if ((float)$payment['unallocated_amount'] > 0 && hasPermission('vendor_payments.create')): ?>
        <a href="allocate.php?id=<?= $id ?>" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition"><i class="fas fa-link mr-2"></i>Allocate Unallocated</a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Payment Info -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment Details</h3>
            <dl class="space-y-3">
                <div><dt class="text-sm text-gray-500">Vendor</dt><dd class="text-sm font-medium"><a href="../vendor_master/view.php?id=<?= $payment['vendor_id'] ?>" class="text-blue-600 hover:underline"><?= sanitize($payment['vendor_name']) ?></a></dd></div>
                <div><dt class="text-sm text-gray-500">Payment Date</dt><dd class="text-sm font-medium"><?= date('M d, Y', strtotime($payment['payment_date'])) ?></dd></div>
                <div><dt class="text-sm text-gray-500">Method</dt><dd class="text-sm font-medium"><span class="px-2 py-0.5 text-xs rounded bg-blue-100 text-blue-800"><?= $methodLabels[$payment['payment_method']] ?? $payment['payment_method'] ?></span></dd></div>
                <?php if ($payment['bank_account_name']): ?>
                <div><dt class="text-sm text-gray-500">Bank Account</dt><dd class="text-sm font-medium"><?= sanitize($payment['bank_account_name']) ?></dd></div>
                <?php endif; ?>
                <?php if ($payment['cash_account_name']): ?>
                <div><dt class="text-sm text-gray-500">Cash Account</dt><dd class="text-sm font-medium"><?= sanitize($payment['cash_account_name']) ?></dd></div>
                <?php endif; ?>
                <?php if ($payment['reference_number']): ?>
                <div><dt class="text-sm text-gray-500">Reference #</dt><dd class="text-sm font-mono font-medium"><?= sanitize($payment['reference_number']) ?></dd></div>
                <?php endif; ?>
                <?php if ($payment['je_voucher_no']): ?>
                <div><dt class="text-sm text-gray-500">Journal Voucher</dt><dd class="text-sm font-medium"><?= sanitize($payment['je_voucher_no']) ?></dd></div>
                <?php endif; ?>
                <div><dt class="text-sm text-gray-500">Created By</dt><dd class="text-sm font-medium"><?= sanitize($payment['created_by_name'] ?? '--') ?></dd></div>
            </dl>
            <?php if ($payment['remarks']): ?>
            <div class="mt-4"><h4 class="text-sm font-medium text-gray-700 mb-1">Remarks</h4><p class="text-sm text-gray-600 whitespace-pre-line"><?= sanitize($payment['remarks']) ?></p></div>
            <?php endif; ?>
        </div>

        <!-- Amount Summary -->
        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Amount Summary</h3>
            <dl class="space-y-3">
                <div class="flex justify-between"><span class="text-sm text-gray-500">Payment Amount</span><span class="text-sm font-mono font-bold"><?= formatMoney((float)$payment['amount']) ?></span></div>
                <div class="flex justify-between"><span class="text-sm text-gray-500">Allocated</span><span class="text-sm font-mono text-green-600"><?= formatMoney((float)$payment['amount'] - (float)$payment['unallocated_amount']) ?></span></div>
                <div class="flex justify-between border-t pt-2"><span class="text-sm font-bold <?= (float)$payment['unallocated_amount'] > 0 ? 'text-yellow-600' : 'text-green-600' ?>">Unallocated</span><span class="text-sm font-mono font-bold <?= (float)$payment['unallocated_amount'] > 0 ? 'text-yellow-600' : 'text-green-600' ?>"><?= formatMoney((float)$payment['unallocated_amount']) ?></span></div>
            </dl>
        </div>
    </div>

    <!-- Allocations -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800">Bill Allocations</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Bill #</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Bill Total</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Allocated</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Paid After</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Balance After</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($allocations)): ?>
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No allocations yet. <a href="allocate.php?id=<?= $id ?>" class="text-blue-600 hover:underline">Allocate now</a></td></tr>
                        <?php else: ?>
                        <?php foreach ($allocations as $a): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-sm font-mono"><a href="../vendor_bills/view.php?id=<?= $a['bill_id'] ?>" class="text-blue-600 hover:underline"><?= sanitize($a['bill_number']) ?></a></td>
                            <td class="px-4 py-2 text-sm text-right font-mono"><?= formatMoney((float)$a['total_amount']) ?></td>
                            <td class="px-4 py-2 text-sm text-right font-mono font-medium"><?= formatMoney((float)$a['amount']) ?></td>
                            <td class="px-4 py-2 text-sm text-right font-mono"><?= formatMoney((float)$a['paid_amount']) ?></td>
                            <td class="px-4 py-2 text-sm text-right font-mono <?= (float)$a['balance_amount'] > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= formatMoney((float)$a['balance_amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>
