<?php
$pageTitle = 'View Customer Invoice';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('customer_invoices.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Invalid invoice ID.');
    redirect('index.php');
}

// Load invoice with client info
$stmt = $db->prepare("
    SELECT ci.*, c.company_name, c.client_code, c.email, c.phone, c.address, c.gst_vat_number,
           p.project_name, p.project_code,
           u.name AS creator_name
    FROM customer_invoices ci
    JOIN clients c ON ci.client_id = c.id
    LEFT JOIN projects p ON ci.project_id = p.id
    LEFT JOIN users u ON ci.created_by = u.id
    WHERE ci.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    flash('error', 'Invoice not found.');
    redirect('index.php');
}

// Load items
$stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Load status history
$stmt = $db->prepare("
    SELECT ish.*, u.name AS changed_by_name
    FROM invoice_status_history ish
    LEFT JOIN users u ON ish.changed_by = u.id
    WHERE ish.invoice_id = ?
    ORDER BY ish.created_at ASC
");
$stmt->execute([$id]);
$statusHistory = $stmt->fetchAll();

// Load payment history (from receipt allocations)
$stmt = $db->prepare("
    SELECT ra.amount AS allocated_amount, ra.created_at AS allocated_at,
           cr.receipt_number, cr.receipt_date, cr.payment_method, cr.reference_number
    FROM receipt_allocations ra
    JOIN customer_receipts cr ON ra.receipt_id = cr.id
    WHERE ra.invoice_id = ?
    ORDER BY ra.created_at ASC
");
$stmt->execute([$id]);
$payments = $stmt->fetchAll();

$statusBadges = [
    'draft' => 'bg-gray-100 text-gray-800',
    'approved' => 'bg-blue-100 text-blue-800',
    'sent' => 'bg-indigo-100 text-indigo-800',
    'partially_paid' => 'bg-yellow-100 text-yellow-800',
    'paid' => 'bg-green-100 text-green-800',
    'overdue' => 'bg-red-100 text-red-800',
    'cancelled' => 'bg-gray-200 text-gray-600',
];

$canCancel = !in_array($invoice['status'], ['paid', 'cancelled']);
$canEdit = $invoice['status'] === 'draft';
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Invoice #<?= sanitize($invoice['invoice_number']) ?></h1>
        <p class="text-gray-600">View invoice details</p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <a href="print.php?id=<?= $id ?>" target="_blank" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-print mr-2"></i>Print
        </a>
        <?php if ($canEdit && hasPermission('customer_invoices.create')): ?>
        <a href="duplicate.php?id=<?= $id ?>" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 transition">
            <i class="fas fa-copy mr-2"></i>Duplicate
        </a>
        <?php endif; ?>
        <?php if ($canEdit && hasPermission('customer_invoices.edit')): ?>
        <a href="edit.php?id=<?= $id ?>" class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700 transition">
            <i class="fas fa-edit mr-2"></i>Edit
        </a>
        <?php endif; ?>
        <?php if ($invoice['status'] === 'draft' && hasPermission('customer_invoices.approve')): ?>
        <form method="POST" action="approve.php" style="display:inline" onsubmit="return confirm('Approve this invoice? This will post to the general ledger.')">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="invoice_id" value="<?= $id ?>">
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
                <i class="fas fa-check mr-2"></i>Approve
            </button>
        </form>
        <?php endif; ?>
        <?php if ($invoice['status'] === 'approved' && hasPermission('customer_invoices.edit')): ?>
        <form method="POST" action="send.php" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="invoice_id" value="<?= $id ?>">
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 transition">
                <i class="fas fa-paper-plane mr-2"></i>Mark as Sent
            </button>
        </form>
        <?php endif; ?>
        <?php if ($canCancel && hasPermission('customer_invoices.cancel')): ?>
        <button onclick="showCancelModal()" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition">
            <i class="fas fa-ban mr-2"></i>Cancel
        </button>
        <?php endif; ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back
        </a>
    </div>
</div>

<!-- Invoice Detail -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="flex justify-between items-start mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800 mb-2">INVOICE</h2>
            <p class="text-gray-500">#<?= sanitize($invoice['invoice_number']) ?></p>
            <span class="px-3 py-1 text-sm rounded font-medium mt-2 inline-block <?= $statusBadges[$invoice['status']] ?? 'bg-gray-100 text-gray-800' ?>">
                <?= ucwords(str_replace('_', ' ', $invoice['status'])) ?>
            </span>
        </div>
        <div class="text-right">
            <p class="font-semibold text-gray-800"><?= sanitize($companyName ?? 'Your Company') ?></p>
            <p class="text-sm text-gray-500"><?= sanitize($companyTaxNumber ?? '') ?></p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-8 mb-6">
        <div>
            <h3 class="text-sm font-medium text-gray-500 uppercase mb-1">Bill To</h3>
            <p class="font-semibold text-gray-800"><?= sanitize($invoice['company_name']) ?></p>
            <p class="text-sm text-gray-600"><?= nl2br(sanitize($invoice['address'] ?? '')) ?></p>
            <p class="text-sm text-gray-600">Email: <?= sanitize($invoice['email'] ?? '') ?></p>
            <p class="text-sm text-gray-600">Phone: <?= sanitize($invoice['phone'] ?? '') ?></p>
            <?php if ($invoice['gst_vat_number']): ?>
            <p class="text-sm text-gray-600">Tax Reg: <?= sanitize($invoice['gst_vat_number']) ?></p>
            <?php endif; ?>
        </div>
        <div class="text-right">
            <div class="space-y-1">
                <p class="text-sm"><span class="text-gray-500">Invoice Date:</span> <span class="font-medium"><?= date('M d, Y', strtotime($invoice['invoice_date'])) ?></span></p>
                <p class="text-sm"><span class="text-gray-500">Due Date:</span> <span class="font-medium"><?= date('M d, Y', strtotime($invoice['due_date'])) ?></span></p>
                <p class="text-sm"><span class="text-gray-500">Currency:</span> <span class="font-medium"><?= sanitize($invoice['currency']) ?></span></p>
                <p class="text-sm"><span class="text-gray-500">Payment Terms:</span> <span class="font-medium"><?= sanitize($invoice['payment_terms'] ?? 'N/A') ?></span></p>
                <?php if ($invoice['project_name']): ?>
                <p class="text-sm"><span class="text-gray-500">Project:</span> <span class="font-medium"><?= sanitize($invoice['project_name']) ?> (<?= sanitize($invoice['project_code']) ?>)</span></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <table class="w-full mb-6">
        <thead class="bg-gray-50 border-y">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Qty</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Unit</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Disc %</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Tax %</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php $idx = 1; foreach ($items as $item): ?>
            <tr>
                <td class="px-4 py-3 text-sm text-gray-600"><?= $idx++ ?></td>
                <td class="px-4 py-3 text-sm text-gray-800"><?= sanitize($item['description']) ?></td>
                <td class="px-4 py-3 text-sm text-center text-gray-600"><?= (float)$item['quantity'] ?></td>
                <td class="px-4 py-3 text-sm text-center text-gray-600"><?= sanitize($item['unit'] ?? '-') ?></td>
                <td class="px-4 py-3 text-sm text-right font-mono text-gray-800"><?= formatMoney((float)$item['unit_price']) ?></td>
                <td class="px-4 py-3 text-sm text-center text-gray-600"><?= (float)$item['discount_percent'] > 0 ? (float)$item['discount_percent'] . '%' : '-' ?></td>
                <td class="px-4 py-3 text-sm text-center text-gray-600"><?= (float)$item['tax_percent'] > 0 ? (float)$item['tax_percent'] . '%' : '-' ?></td>
                <td class="px-4 py-3 text-sm text-right font-mono font-semibold text-gray-800"><?= formatMoney((float)$item['line_total']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-gray-50">
            <tr>
                <td colspan="7" class="px-4 py-2 text-right text-sm text-gray-600">Subtotal:</td>
                <td class="px-4 py-2 text-right font-mono text-sm text-gray-800"><?= formatMoney((float)$invoice['subtotal']) ?></td>
            </tr>
            <?php if ((float)$invoice['discount_amount'] > 0): ?>
            <tr>
                <td colspan="7" class="px-4 py-2 text-right text-sm text-gray-600">Discount:</td>
                <td class="px-4 py-2 text-right font-mono text-sm text-gray-600">-<?= formatMoney((float)$invoice['discount_amount']) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td colspan="7" class="px-4 py-2 text-right text-sm text-gray-600">Tax:</td>
                <td class="px-4 py-2 text-right font-mono text-sm text-gray-600"><?= formatMoney((float)$invoice['tax_amount']) ?></td>
            </tr>
            <tr class="text-lg">
                <td colspan="7" class="px-4 py-2 text-right font-bold text-gray-800">Total:</td>
                <td class="px-4 py-2 text-right font-mono font-bold text-gray-800"><?= formatMoney((float)$invoice['total_amount']) ?></td>
            </tr>
            <?php if ((float)$invoice['paid_amount'] > 0): ?>
            <tr>
                <td colspan="7" class="px-4 py-2 text-right text-sm text-green-600">Paid:</td>
                <td class="px-4 py-2 text-right font-mono text-sm text-green-600"><?= formatMoney((float)$invoice['paid_amount']) ?></td>
            </tr>
            <tr>
                <td colspan="7" class="px-4 py-2 text-right font-bold text-sm <?= (float)$invoice['balance_amount'] > 0 ? 'text-red-600' : 'text-gray-800' ?>">Balance Due:</td>
                <td class="px-4 py-2 text-right font-mono font-bold text-sm <?= (float)$invoice['balance_amount'] > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= formatMoney((float)$invoice['balance_amount']) ?></td>
            </tr>
            <?php endif; ?>
        </tfoot>
    </table>

    <?php if ($invoice['notes']): ?>
    <div class="border-t pt-4">
        <h4 class="text-sm font-medium text-gray-500 uppercase mb-1">Notes</h4>
        <p class="text-sm text-gray-700"><?= nl2br(sanitize($invoice['notes'])) ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- Status History -->
<?php if (!empty($statusHistory)): ?>
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Status History</h3>
    <div class="space-y-3">
        <?php foreach ($statusHistory as $sh): ?>
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0 mt-0.5">
                <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center">
                    <i class="fas fa-history text-gray-500 text-xs"></i>
                </div>
            </div>
            <div>
                <p class="text-sm text-gray-800">
                    <?php if ($sh['from_status']): ?>
                    Status changed from <span class="font-medium"><?= ucwords(str_replace('_', ' ', $sh['from_status'])) ?></span> to <span class="font-medium"><?= ucwords(str_replace('_', ' ', $sh['to_status'])) ?></span>
                    <?php else: ?>
                    Status set to <span class="font-medium"><?= ucwords(str_replace('_', ' ', $sh['to_status'])) ?></span>
                    <?php endif; ?>
                </p>
                <p class="text-xs text-gray-500"><?= date('M d, Y h:i A', strtotime($sh['created_at'])) ?> by <?= sanitize($sh['changed_by_name'] ?? 'System') ?></p>
                <?php if ($sh['change_note']): ?>
                <p class="text-xs text-gray-600 mt-1"><?= sanitize($sh['change_note']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Payment History -->
<?php if (!empty($payments)): ?>
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment History</h3>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Receipt #</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td class="px-4 py-3 text-sm font-mono text-gray-800"><?= sanitize($p['receipt_number']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= date('M d, Y', strtotime($p['receipt_date'])) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= ucwords(str_replace('_', ' ', $p['payment_method'])) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($p['reference_number'] ?? '-') ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-green-600"><?= formatMoney((float)$p['allocated_amount']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Cancel Modal -->
<div id="cancelModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Cancel Invoice</h3>
        <p class="text-sm text-gray-600 mb-4">Are you sure you want to cancel invoice <strong>#<?= sanitize($invoice['invoice_number']) ?></strong>? This action cannot be undone.</p>
        <form method="POST" action="cancel.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="invoice_id" value="<?= $id ?>">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Cancellation <span class="text-red-500">*</span></label>
                <textarea name="remarks" rows="3" required class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="Enter cancellation reason"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="hideCancelModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">Keep Invoice</button>
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition">Confirm Cancellation</button>
            </div>
        </form>
    </div>
</div>

<script>
function showCancelModal() {
    document.getElementById('cancelModal').classList.remove('hidden');
    document.getElementById('cancelModal').classList.add('flex');
}
function hideCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
    document.getElementById('cancelModal').classList.remove('flex');
}
</script>

<?php require_once '../../../includes/footer.php'; ?>
