<?php
$pageTitle = 'Journal Entry Details';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../helpers/finance_helpers.php';

if (!hasPermission('finance.journal.view')) { die('Access Denied'); }

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
    flash('error', 'Invalid journal entry ID.', 'error');
    to('modules/finance/journal_entries/index.php');
}

// Load journal with voucher type and creator
$stmt = $db->prepare("
    SELECT je.*, vt.voucher_name, u.name AS creator_name
    FROM journal_entries je
    JOIN voucher_types vt ON je.voucher_type_id = vt.id
    JOIN users u ON je.created_by = u.id
    WHERE je.id = ?
");
$stmt->execute([$id]);
$journal = $stmt->fetch();

if (!$journal) {
    flash('error', 'Journal entry not found.', 'error');
    to('modules/finance/journal_entries/index.php');
}

// Load journal lines with account info
$linesStmt = $db->prepare("
    SELECT jed.*, coa.account_code, coa.account_name
    FROM journal_entry_details jed
    JOIN chart_of_accounts coa ON jed.account_id = coa.id
    WHERE jed.journal_entry_id = ?
    ORDER BY jed.id ASC
");
$linesStmt->execute([$id]);
$lines = $linesStmt->fetchAll();

// Calculate totals
$totalDebit = 0;
$totalCredit = 0;
foreach ($lines as $line) {
    $totalDebit += (float) $line['debit_amount'];
    $totalCredit += (float) $line['credit_amount'];
}

$isReadOnly = ($journal['status'] !== 'draft');
$isPosted = ($journal['status'] === 'posted');
$isReversed = ($journal['status'] === 'reversed');
?>
<!-- Page Header -->
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Journal Entry Details</h1>
        <p class="text-gray-600">Voucher No: <span class="font-mono font-semibold"><?= sanitize($journal['voucher_no']) ?></span></p>
    </div>
    <div class="flex gap-2">
        <a href="index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Journal Entries
        </a>
    </div>
</div>

<?php if ($isPosted): ?>
<!-- Posted Alert -->
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
    <i class="fas fa-check-circle mr-2"></i>
    <strong>Posted:</strong> This journal has been posted and cannot be edited.
    <?php if ($journal['posted_at']): ?>
    <span class="ml-2 text-sm">(<?= date('M d, Y h:i A', strtotime($journal['posted_at'])) ?>)</span>
    <?php endif; ?>
</div>
<?php elseif ($isReversed): ?>
<!-- Reversed Alert -->
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
    <i class="fas fa-undo mr-2"></i>
    <strong>Reversed:</strong> This journal entry has been reversed and cannot be modified.
</div>
<?php endif; ?>

<!-- Journal Header Card -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="p-6">
        <div class="flex justify-between items-start mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Journal Information</h2>
            <?= formatVoucherStatus($journal['status']) ?>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Voucher No</label>
                <p class="text-sm font-mono font-semibold text-gray-900"><?= sanitize($journal['voucher_no']) ?></p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Voucher Type</label>
                <p class="text-sm text-gray-900"><?= sanitize($journal['voucher_name']) ?></p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Voucher Date</label>
                <p class="text-sm text-gray-900"><?= date('M d, Y', strtotime($journal['voucher_date'])) ?></p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Reference No</label>
                <p class="text-sm text-gray-900"><?= sanitize($journal['reference_no'] ?? '-') ?></p>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Description</label>
                <p class="text-sm text-gray-900"><?= sanitize($journal['description'] ?? '-') ?></p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Created By</label>
                <p class="text-sm text-gray-900"><?= sanitize($journal['creator_name']) ?></p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Created At</label>
                <p class="text-sm text-gray-900"><?= date('M d, Y h:i A', strtotime($journal['created_at'])) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Journal Lines Table -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="p-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-800">Journal Lines</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php $i = 1; foreach ($lines as $line): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 text-sm text-gray-500"><?= $i++ ?></td>
                    <td class="px-6 py-3">
                        <div class="text-sm font-medium text-gray-900"><?= sanitize($line['account_name']) ?></div>
                        <div class="text-xs text-gray-500 font-mono"><?= sanitize($line['account_code']) ?></div>
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-600"><?= sanitize($line['description'] ?? '-') ?></td>
                    <td class="px-6 py-3 text-sm text-right font-mono text-gray-900">
                        <?= (float) $line['debit_amount'] > 0 ? formatNumber($line['debit_amount']) : '-' ?>
                    </td>
                    <td class="px-6 py-3 text-sm text-right font-mono text-gray-900">
                        <?= (float) $line['credit_amount'] > 0 ? formatNumber($line['credit_amount']) : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-gray-50 font-semibold">
                <tr>
                    <td colspan="3" class="px-6 py-3 text-sm text-gray-700 text-right">Totals</td>
                    <td class="px-6 py-3 text-sm text-right font-mono text-gray-900"><?= formatNumber($totalDebit) ?></td>
                    <td class="px-6 py-3 text-sm text-right font-mono text-gray-900"><?= formatNumber($totalCredit) ?></td>
                </tr>
                <?php if (abs($totalDebit - $totalCredit) < 0.001): ?>
                <tr>
                    <td colspan="5" class="px-6 py-2 text-xs text-green-600 text-right">
                        <i class="fas fa-check-circle mr-1"></i> Balanced
                    </td>
                </tr>
                <?php else: ?>
                <tr>
                    <td colspan="5" class="px-6 py-2 text-xs text-red-600 text-right">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Unbalanced (Difference: <?= formatNumber(abs($totalDebit - $totalCredit)) ?>)
                    </td>
                </tr>
                <?php endif; ?>
            </tfoot>
        </table>
    </div>
</div>

<!-- Action Buttons -->
<?php if ($journal['status'] === 'draft'): ?>
<div class="flex gap-3">
    <?php if (hasPermission('finance.journal.post')): ?>
    <form method="POST" action="<?= url('modules/finance/journal_entries/index.php?post=' . $id) ?>">
        <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition"
                onclick="return confirm('Post this journal entry? This action cannot be undone.')">
            <i class="fas fa-check-circle mr-2"></i>Post Journal
        </button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once '../../../includes/footer.php'; ?>

