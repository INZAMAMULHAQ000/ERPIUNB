<?php
$pageTitle = 'View Credit Note';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('credit_notes.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Invalid credit note ID.');
    redirect('index.php');
}

// Load credit note with client and invoice info
$stmt = $db->prepare("
    SELECT cn.*, c.company_name, c.client_code, c.email, c.phone, c.address, c.gst_vat_number,
           ci.invoice_number, ci.invoice_date, ci.total_amount AS invoice_total, ci.status AS invoice_status,
           u.name AS creator_name
    FROM credit_notes cn
    JOIN clients c ON cn.client_id = c.id
    LEFT JOIN customer_invoices ci ON cn.invoice_id = ci.id
    LEFT JOIN users u ON cn.created_by = u.id
    WHERE cn.id = ?
");
$stmt->execute([$id]);
$creditNote = $stmt->fetch();

if (!$creditNote) {
    flash('error', 'Credit note not found.');
    redirect('index.php');
}

$creditNoteTypes = [
    'price_correction' => 'Price Correction',
    'return' => 'Return',
    'discount_adjustment' => 'Discount Adjustment',
    'cancellation' => 'Cancellation',
];

$typeBadges = [
    'price_correction' => 'bg-purple-100 text-purple-800',
    'return' => 'bg-orange-100 text-orange-800',
    'discount_adjustment' => 'bg-teal-100 text-teal-800',
    'cancellation' => 'bg-red-100 text-red-800',
];

$statusBadges = [
    'draft' => 'bg-gray-100 text-gray-800',
    'issued' => 'bg-blue-100 text-blue-800',
    'applied' => 'bg-green-100 text-green-800',
    'cancelled' => 'bg-red-100 text-red-800',
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
        <h1 class="text-2xl font-bold text-gray-800">Credit Note #<?= sanitize($creditNote['credit_note_number']) ?></h1>
        <p class="text-gray-600">View credit note details</p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-print mr-2"></i>Print
        </button>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Credit Notes
        </a>
    </div>
</div>

<!-- Credit Note Detail -->
<div class="bg-white rounded-lg shadow p-6 mb-6 print-area">
    <div class="flex justify-between items-start mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800 mb-2">CREDIT NOTE</h2>
            <p class="text-gray-500">#<?= sanitize($creditNote['credit_note_number']) ?></p>
            <span class="px-3 py-1 text-sm rounded font-medium mt-2 inline-block <?= $statusBadges[$creditNote['status']] ?? 'bg-gray-100 text-gray-800' ?>">
                <?= ucfirst($creditNote['status']) ?>
            </span>
        </div>
        <div class="text-right">
            <p class="font-semibold text-gray-800"><?= sanitize($companyName ?? 'Your Company') ?></p>
            <?php if ($_SESSION['company_tax_number'] ?? $creditNote['gst_vat_number']): ?>
            <p class="text-sm text-gray-500"><?= sanitize($_SESSION['company_tax_number'] ?? $creditNote['gst_vat_number']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-8 mb-6">
        <div>
            <h3 class="text-sm font-medium text-gray-500 uppercase mb-1">Credit To</h3>
            <p class="font-semibold text-gray-800"><?= sanitize($creditNote['company_name']) ?></p>
            <p class="text-sm text-gray-600"><?= nl2br(sanitize($creditNote['address'] ?? '')) ?></p>
            <p class="text-sm text-gray-600">Email: <?= sanitize($creditNote['email'] ?? '') ?></p>
            <p class="text-sm text-gray-600">Phone: <?= sanitize($creditNote['phone'] ?? '') ?></p>
            <p class="text-sm text-gray-600">Client Code: <?= sanitize($creditNote['client_code'] ?? '') ?></p>
            <?php if ($creditNote['gst_vat_number']): ?>
            <p class="text-sm text-gray-600">Tax Reg: <?= sanitize($creditNote['gst_vat_number']) ?></p>
            <?php endif; ?>
        </div>
        <div class="text-right">
            <div class="space-y-1">
                <p class="text-sm"><span class="text-gray-500">Credit Note Date:</span> <span class="font-medium"><?= date('M d, Y', strtotime($creditNote['credit_note_date'])) ?></span></p>
                <p class="text-sm"><span class="text-gray-500">Type:</span>
                    <span class="px-2 py-1 text-xs rounded font-medium <?= $typeBadges[$creditNote['credit_note_type']] ?? 'bg-gray-100 text-gray-800' ?>">
                        <?= $creditNoteTypes[$creditNote['credit_note_type']] ?? ucwords(str_replace('_', ' ', $creditNote['credit_note_type'])) ?>
                    </span>
                </p>
                <p class="text-sm"><span class="text-gray-500">Created By:</span> <span class="font-medium"><?= sanitize($creditNote['creator_name'] ?? 'N/A') ?></span></p>
                <p class="text-sm"><span class="text-gray-500">Created At:</span> <span class="font-medium"><?= date('M d, Y h:i A', strtotime($creditNote['created_at'])) ?></span></p>
            </div>
        </div>
    </div>

    <!-- Amount Summary -->
    <div class="border-t border-b py-4 mb-6">
        <?php if ($creditNote['credit_note_type'] === 'cancellation'): ?>
        <div class="flex justify-between items-center">
            <div>
                <p class="text-lg text-gray-600">Invoice Cancellation Amount</p>
            </div>
            <div class="text-right">
                <p class="text-3xl font-bold text-red-600"><?= formatMoney((float)$creditNote['total_amount']) ?></p>
            </div>
        </div>
        <?php else: ?>
        <div class="space-y-2">
            <div class="flex justify-between items-center">
                <div><p class="text-sm text-gray-600">Subtotal</p></div>
                <div class="text-right"><p class="text-sm font-mono text-gray-800"><?= formatMoney((float)$creditNote['subtotal']) ?></p></div>
            </div>
            <div class="flex justify-between items-center">
                <div><p class="text-sm text-gray-600">Tax Amount</p></div>
                <div class="text-right"><p class="text-sm font-mono text-gray-800"><?= formatMoney((float)$creditNote['tax_amount']) ?></p></div>
            </div>
            <div class="flex justify-between items-center border-t pt-2">
                <div><p class="text-lg font-semibold text-gray-800">Total</p></div>
                <div class="text-right"><p class="text-lg font-bold font-mono text-red-600"><?= formatMoney((float)$creditNote['total_amount']) ?></p></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($creditNote['reason']): ?>
    <div class="mb-4">
        <h4 class="text-sm font-medium text-gray-500 uppercase mb-1">Reason</h4>
        <p class="text-sm text-gray-700"><?= nl2br(sanitize($creditNote['reason'])) ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- Linked Invoice Information -->
<?php if ($creditNote['invoice_id']): ?>
<div class="bg-white rounded-lg shadow p-6 mb-6 print-area">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Linked Invoice</h3>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="text-sm text-gray-500">Invoice Number</p>
            <p class="font-medium text-gray-800">
                <a href="<?= url('modules/finance/customer_invoices/view.php?id=' . $creditNote['invoice_id']) ?>" class="text-blue-600 hover:text-blue-900">
                    <?= sanitize($creditNote['invoice_number']) ?>
                </a>
            </p>
        </div>
        <div>
            <p class="text-sm text-gray-500">Invoice Status</p>
            <span class="px-2 py-1 text-xs rounded font-medium <?= $invStatusBadges[$creditNote['invoice_status']] ?? 'bg-gray-100 text-gray-800' ?>">
                <?= ucwords(str_replace('_', ' ', $creditNote['invoice_status'] ?? 'N/A')) ?>
            </span>
        </div>
        <div>
            <p class="text-sm text-gray-500">Invoice Date</p>
            <p class="font-medium text-gray-800"><?= date('M d, Y', strtotime($creditNote['invoice_date'])) ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500">Invoice Total</p>
            <p class="font-medium text-gray-800"><?= formatMoney((float)$creditNote['invoice_total']) ?></p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Journal Entry Link -->
<?php if ($creditNote['journal_entry_id']): ?>
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-2">Journal Entry</h3>
    <p class="text-sm text-gray-600">
        This credit note has been posted to the general ledger.
        <a href="<?= url('modules/finance/journal_entries/view.php?id=' . $creditNote['journal_entry_id']) ?>" class="text-blue-600 hover:text-blue-900">
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
