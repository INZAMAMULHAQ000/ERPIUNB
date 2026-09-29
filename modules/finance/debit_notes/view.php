<?php
$pageTitle = 'View Debit Note';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('debit_notes.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('error', 'Invalid debit note ID.'); redirect('index.php'); }

$stmt = $db->prepare("
    SELECT dn.*, v.vendor_name, v.vendor_code, vb.bill_number,
        u.full_name AS created_by_name, je.voucher_no AS je_voucher_no
    FROM debit_notes dn
    JOIN vendors v ON dn.vendor_id = v.id
    LEFT JOIN vendor_bills vb ON dn.bill_id = vb.id
    LEFT JOIN users u ON dn.created_by = u.id
    LEFT JOIN journal_entries je ON dn.journal_entry_id = je.id
    WHERE dn.id = ?
");
$stmt->execute([$id]);
$note = $stmt->fetch();
if (!$note) { flash('error', 'Debit note not found.'); redirect('index.php'); }

$dnTypes = ['price_correction' => 'Price Correction', 'return' => 'Return', 'discount_adjustment' => 'Discount Adjustment', 'cancellation' => 'Cancellation'];
$statusBadges = [
    'draft' => 'bg-gray-100 text-gray-800',
    'issued' => 'bg-blue-100 text-blue-800',
    'applied' => 'bg-green-100 text-green-800',
    'cancelled' => 'bg-gray-200 text-gray-600',
];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Debit Note: <?= sanitize($note['debit_note_number']) ?></h1>
        <p class="text-gray-600"><?= sanitize($note['vendor_name']) ?> · <?= $dnTypes[$note['debit_note_type']] ?? $note['debit_note_type'] ?> · <?= date('M d, Y', strtotime($note['debit_note_date'])) ?></p>
    </div>
    <div class="flex gap-2">
        <a href="index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back</a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Debit Note Details</h3>
                <span class="px-3 py-1 text-sm rounded font-medium <?= $statusBadges[$note['status']] ?? 'bg-gray-100' ?>">
                    <?= ucfirst($note['status']) ?>
                </span>
            </div>
            <dl class="space-y-3">
                <div><dt class="text-sm text-gray-500">Vendor</dt><dd class="text-sm font-medium"><a href="../vendor_master/view.php?id=<?= $note['vendor_id'] ?>" class="text-blue-600 hover:underline"><?= sanitize($note['vendor_name']) ?></a></dd></div>
                <div><dt class="text-sm text-gray-500">Date</dt><dd class="text-sm font-medium"><?= date('M d, Y', strtotime($note['debit_note_date'])) ?></dd></div>
                <div><dt class="text-sm text-gray-500">Type</dt><dd class="text-sm font-medium"><?= $dnTypes[$note['debit_note_type']] ?? $note['debit_note_type'] ?></dd></div>
                <?php if ($note['bill_number']): ?>
                <div><dt class="text-sm text-gray-500">Related Bill</dt><dd class="text-sm font-medium"><a href="../vendor_bills/view.php?id=<?= $note['bill_id'] ?>" class="text-blue-600 hover:underline"><?= sanitize($note['bill_number']) ?></a></dd></div>
                <?php endif; ?>
                <?php if ($note['je_voucher_no']): ?>
                <div><dt class="text-sm text-gray-500">Journal Voucher</dt><dd class="text-sm font-medium"><?= sanitize($note['je_voucher_no']) ?></dd></div>
                <?php endif; ?>
                <div><dt class="text-sm text-gray-500">Created By</dt><dd class="text-sm font-medium"><?= sanitize($note['created_by_name'] ?? '--') ?></dd></div>
                <div class="flex justify-between border-t pt-2 mt-2">
                    <span class="text-sm font-bold text-gray-700">Amount</span>
                    <span class="text-sm font-mono font-bold"><?= formatMoney((float)$note['total_amount']) ?></span>
                </div>
            </dl>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Reason</h3>
            <p class="text-sm text-gray-600 whitespace-pre-line"><?= sanitize($note['reason'] ?? 'No reason provided.') ?></p>

            <?php if ($note['je_voucher_no']): ?>
            <div class="mt-6 p-4 bg-blue-50 rounded">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-check-circle mr-2"></i>
                    This debit note has been <strong>issued</strong> and posted to the general ledger.
                    Voucher: <strong><?= sanitize($note['je_voucher_no']) ?></strong>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>
