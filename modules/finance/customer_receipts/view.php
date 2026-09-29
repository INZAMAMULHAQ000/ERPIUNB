<?php
$pageTitle = 'View Customer Receipt';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('customer_receipts.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Invalid receipt ID.');
    redirect('index.php');
}

// Load receipt with client info
$stmt = $db->prepare("
    SELECT cr.*, c.company_name, c.client_code, c.email, c.phone, c.address,
           ba.account_name AS bank_account_name, ba.bank_name,
           ca.cash_name AS cash_account_name,
           u.name AS creator_name
    FROM customer_receipts cr
    JOIN clients c ON cr.client_id = c.id
    LEFT JOIN bank_accounts ba ON cr.bank_account_id = ba.id
    LEFT JOIN cash_accounts ca ON cr.cash_account_id = ca.id
    LEFT JOIN users u ON cr.created_by = u.id
    WHERE cr.id = ?
");
$stmt->execute([$id]);
$receipt = $stmt->fetch();

if (!$receipt) {
    flash('error', 'Receipt not found.');
    redirect('index.php');
}

// Load allocations
$stmt = $db->prepare("
    SELECT ra.*, ci.invoice_number, ci.invoice_date, ci.total_amount, ci.paid_amount, ci.balance_amount, ci.status
    FROM receipt_allocations ra
    JOIN customer_invoices ci ON ra.invoice_id = ci.id
    WHERE ra.receipt_id = ?
    ORDER BY ra.created_at ASC
");
$stmt->execute([$id]);
$allocations = $stmt->fetchAll();

$paymentMethods = [
    'cash' => 'Cash',
    'bank_transfer' => 'Bank Transfer',
    'cheque' => 'Cheque',
    'credit_card' => 'Credit Card',
    'online' => 'Online',
];

$methodBadges = [
    'cash' => 'bg-green-100 text-green-800',
    'bank_transfer' => 'bg-blue-100 text-blue-800',
    'cheque' => 'bg-yellow-100 text-yellow-800',
    'credit_card' => 'bg-purple-100 text-purple-800',
    'online' => 'bg-indigo-100 text-indigo-800',
];

$invStatusBadges = [
    'draft' => 'bg-gray-100 text-gray-800',
    'approved' => 'bg-blue-100 text-blue-800',
    'sent' => 'bg-indigo-100 text-indigo-800',
    'partially_paid' => 'bg-yellow-100 text-yellow-800',
    'paid' => 'bg-green-100 text-green-800',
    'overdue' => 'bg-red-100 text-red-800',
    'cancelled' => 'bg-gray-200 text-gray-600',
];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Receipt #<?= sanitize($receipt['receipt_number']) ?></h1>
        <p class="text-gray-600">View receipt details</p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-print mr-2"></i>Print
        </button>
        <?php if (hasPermission('customer_receipts.create') && (float)$receipt['unallocated_amount'] > 0): ?>
        <a href="allocate.php?id=<?= $id ?>" class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700 transition">
            <i class="fas fa-hand-holding-usd mr-2"></i>Allocate (<?= number_format((float)$receipt['unallocated_amount'], 2) ?>)
        </a>
        <?php endif; ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Receipts
        </a>
    </div>
</div>

<!-- Receipt Detail -->
<div class="bg-white rounded-lg shadow p-6 mb-6 print-area">
    <div class="flex justify-between items-start mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800 mb-2">RECEIPT</h2>
            <p class="text-gray-500">#<?= sanitize($receipt['receipt_number']) ?></p>
        </div>
        <div class="text-right">
            <p class="font-semibold text-gray-800"><?= sanitize($companyName ?? 'Your Company') ?></p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-8 mb-6">
        <div>
            <h3 class="text-sm font-medium text-gray-500 uppercase mb-1">Received From</h3>
            <p class="font-semibold text-gray-800"><?= sanitize($receipt['company_name']) ?></p>
            <p class="text-sm text-gray-600"><?= nl2br(sanitize($receipt['address'] ?? '')) ?></p>
            <p class="text-sm text-gray-600">Email: <?= sanitize($receipt['email'] ?? '') ?></p>
            <p class="text-sm text-gray-600">Phone: <?= sanitize($receipt['phone'] ?? '') ?></p>
            <p class="text-sm text-gray-600">Client Code: <?= sanitize($receipt['client_code'] ?? '') ?></p>
        </div>
        <div class="text-right">
            <div class="space-y-1">
                <p class="text-sm"><span class="text-gray-500">Receipt Date:</span> <span class="font-medium"><?= date('M d, Y', strtotime($receipt['receipt_date'])) ?></span></p>
                <p class="text-sm"><span class="text-gray-500">Payment Method:</span>
                    <span class="px-2 py-1 text-xs rounded font-medium <?= $methodBadges[$receipt['payment_method']] ?? 'bg-gray-100 text-gray-800' ?>">
                        <?= $paymentMethods[$receipt['payment_method']] ?? ucwords(str_replace('_', ' ', $receipt['payment_method'])) ?>
                    </span>
                </p>
                <?php if ($receipt['reference_number']): ?>
                <p class="text-sm"><span class="text-gray-500">Reference:</span> <span class="font-medium"><?= sanitize($receipt['reference_number']) ?></span></p>
                <?php endif; ?>
                <?php if ($receipt['bank_account_name']): ?>
                <p class="text-sm"><span class="text-gray-500">Bank Account:</span> <span class="font-medium"><?= sanitize($receipt['bank_account_name']) ?> (<?= sanitize($receipt['bank_name'] ?? '') ?>)</span></p>
                <?php endif; ?>
                <?php if ($receipt['cash_account_name']): ?>
                <p class="text-sm"><span class="text-gray-500">Cash Account:</span> <span class="font-medium"><?= sanitize($receipt['cash_account_name']) ?></span></p>
                <?php endif; ?>
                <p class="text-sm"><span class="text-gray-500">Recorded By:</span> <span class="font-medium"><?= sanitize($receipt['creator_name'] ?? 'N/A') ?></span></p>
            </div>
        </div>
    </div>

    <!-- Amount Summary -->
    <div class="border-t border-b py-4 mb-6">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-lg text-gray-600">Total Amount Received</p>
            </div>
            <div class="text-right">
                <p class="text-3xl font-bold text-green-600"><?= formatMoney((float)$receipt['amount']) ?></p>
            </div>
        </div>
        <?php if ((float)$receipt['unallocated_amount'] > 0): ?>
        <div class="flex justify-between items-center mt-2">
            <div>
                <p class="text-sm text-gray-500">Unallocated Amount</p>
            </div>
            <div class="text-right">
                <p class="text-lg font-semibold text-yellow-600"><?= formatMoney((float)$receipt['unallocated_amount']) ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($receipt['remarks']): ?>
    <div class="mb-4">
        <h4 class="text-sm font-medium text-gray-500 uppercase mb-1">Remarks</h4>
        <p class="text-sm text-gray-700"><?= nl2br(sanitize($receipt['remarks'])) ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- Allocations Table -->
<?php if (!empty($allocations)): ?>
<div class="bg-white rounded-lg shadow p-6 mb-6 print-area">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Invoice Allocations</h3>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paid</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Allocated</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($allocations as $a): ?>
                <tr>
                    <td class="px-4 py-3 text-sm font-mono text-gray-900"><?= sanitize($a['invoice_number']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($a['invoice_date'])) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney((float)$a['total_amount']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-green-600"><?= formatMoney((float)$a['paid_amount']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono <?= (float)$a['balance_amount'] > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= formatMoney((float)$a['balance_amount']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-1 text-xs rounded font-medium <?= $invStatusBadges[$a['status']] ?? 'bg-gray-100 text-gray-800' ?>">
                            <?= ucwords(str_replace('_', ' ', $a['status'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-right font-mono font-semibold text-blue-600"><?= formatMoney((float)$a['amount']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-gray-50 font-semibold">
                <tr>
                    <td colspan="6" class="px-4 py-3 text-right text-sm text-gray-600">Total Allocated:</td>
                    <td class="px-4 py-3 text-right font-mono text-blue-600">
                        <?php
                        $totalAllocated = array_sum(array_column($allocations, 'amount'));
                        echo formatMoney((float)$totalAllocated);
                        ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Journal Entry Link -->
<?php if ($receipt['journal_entry_id']): ?>
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-2">Journal Entry</h3>
    <p class="text-sm text-gray-600">
        This receipt has been posted to the general ledger.
        <a href="<?= url('modules/finance/journal_entries/view.php?id=' . $receipt['journal_entry_id']) ?>" class="text-blue-600 hover:text-blue-900">
            View Journal Entry <i class="fas fa-external-link-alt ml-1"></i>
        </a>
    </p>
</div>
<?php endif; ?>

<style media="print">
    .erp-layout, .erp-main { margin: 0 !important; padding: 0 !important; }
    .print-area { box-shadow: none !important; border: none !important; }
    nav, .sidebar, .navbar, .erp-layout > nav, .erp-layout > div:first-child { display: none !important; }
    button, .bg-gray-600, .bg-gray-300, .flex.justify-between.items-center.mb-6 { display: none !important; }
    body { background: white; }
    @page { margin: 15mm; }
</style>

<?php require_once '../../../includes/footer.php'; ?>
