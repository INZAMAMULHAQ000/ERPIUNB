<?php
$pageTitle = 'Tax Adjustment Details';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT a.*, t.name AS type_name, t.code AS type_code, u.name AS created_name,
           ap.name AS approved_name, tp.period_name, je.voucher_no AS journal_voucher_no
    FROM tax_adjustments a
    JOIN tax_types t ON t.id = a.tax_type_id
    LEFT JOIN users u ON u.id = a.created_by
    LEFT JOIN users ap ON ap.id = a.approved_by
    LEFT JOIN tax_periods tp ON tp.id = a.tax_period_id
    LEFT JOIN journal_entries je ON je.id = a.journal_entry_id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$adj = $stmt->fetch();
if (!$adj) { flash('error', 'Tax adjustment not found.', 'error'); redirect('index.php'); }

$statusBadge = [
    'draft'    => ['bg-amber-100 text-amber-800', 'Draft'],
    'approved' => ['bg-green-100 text-green-800', 'Approved'],
    'rejected' => ['bg-rose-100 text-rose-800', 'Rejected'],
];
$sb = $statusBadge[$adj['status']] ?? ['bg-gray-100 text-gray-700', ucfirst((string)$adj['status'])];

// The schema does not store the rate, so derive it from the amounts for display.
$ratePct = (float)$adj['taxable_amount'] > 0 ? (float)$adj['tax_amount'] / (float)$adj['taxable_amount'] * 100 : 0.0;
$total = (float)$adj['taxable_amount'] + (float)$adj['tax_amount'];
?>

<div class="mb-6 flex justify-between items-start">
    <div>
        <a href="index.php" class="text-blue-600 hover:underline text-sm">&larr; Back to Tax Adjustments</a>
        <h1 class="text-2xl font-bold text-gray-800 mt-1">
            <span class="font-mono"><?= sanitize($adj['adjustment_no']) ?></span>
        </h1>
        <p class="text-gray-600">
            <?= taxui_direction_badge($adj['direction']) ?>
            <span class="ml-2 px-2 py-1 text-xs rounded font-medium <?= $sb[0] ?>"><?= $sb[1] ?></span>
        </p>
    </div>
</div>

<?= taxui_tabs('adjustments') ?>

<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-0">
        <div class="px-6 py-4 border-b border-r border-gray-100">
            <dt class="text-xs text-gray-500 uppercase tracking-wide">Adjustment Date</dt>
            <dd class="text-gray-900 mt-1"><?= sanitize($adj['adjustment_date']) ?></dd>
        </div>
        <div class="px-6 py-4 border-b border-r border-gray-100">
            <dt class="text-xs text-gray-500 uppercase tracking-wide">Tax Type</dt>
            <dd class="text-gray-900 mt-1">
                <span class="px-2 py-0.5 text-xs font-mono bg-slate-100 text-slate-700 rounded"><?= sanitize($adj['type_code']) ?></span>
                <span class="ml-1"><?= sanitize($adj['type_name']) ?></span>
            </dd>
        </div>
        <div class="px-6 py-4 border-b border-r border-gray-100">
            <dt class="text-xs text-gray-500 uppercase tracking-wide">Tax Period</dt>
            <dd class="text-gray-900 mt-1"><?= sanitize($adj['period_name'] ?? '—') ?></dd>
        </div>
        <div class="px-6 py-4 border-b border-gray-100">
            <dt class="text-xs text-gray-500 uppercase tracking-wide">Reference</dt>
            <dd class="text-gray-900 mt-1"><?= sanitize($adj['reference'] ?? '—') ?></dd>
        </div>
        <div class="px-6 py-4 border-r border-gray-100">
            <dt class="text-xs text-gray-500 uppercase tracking-wide">Created By</dt>
            <dd class="text-gray-900 mt-1"><?= sanitize($adj['created_name']) ?></dd>
        </div>
        <div class="px-6 py-4 border-r border-gray-100">
            <dt class="text-xs text-gray-500 uppercase tracking-wide">Created At</dt>
            <dd class="text-gray-900 mt-1"><?= sanitize($adj['created_at']) ?></dd>
        </div>
        <?php if ($adj['status'] === 'approved'): ?>
        <div class="px-6 py-4 border-r border-gray-100">
            <dt class="text-xs text-gray-500 uppercase tracking-wide">Approved By</dt>
            <dd class="text-gray-900 mt-1"><?= sanitize($adj['approved_name']) ?></dd>
        </div>
        <div class="px-6 py-4">
            <dt class="text-xs text-gray-500 uppercase tracking-wide">Approved At</dt>
            <dd class="text-gray-900 mt-1"><?= sanitize($adj['approved_at']) ?></dd>
        </div>
        <?php else: ?>
        <div class="px-6 py-4 border-r border-gray-100">
            <dt class="text-xs text-gray-500 uppercase tracking-wide">Approved By</dt>
            <dd class="text-gray-400 mt-1">—</dd>
        </div>
        <div class="px-6 py-4">
            <dt class="text-xs text-gray-500 uppercase tracking-wide">Approved At</dt>
            <dd class="text-gray-400 mt-1">—</dd>
        </div>
        <?php endif; ?>
    </dl>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Taxable</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?= taxui_amount($adj['taxable_amount']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Rate</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?= rtrim(rtrim(number_format($ratePct, 4), '0'), '.') ?>%</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Tax</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?= taxui_amount($adj['tax_amount']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">TOTAL</div>
        <div class="text-2xl font-bold text-blue-700 mt-1"><?= taxui_amount($total) ?></div>
    </div>
</div>

<div class="mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="font-semibold text-gray-800 mb-2">Reason</h2>
        <p class="text-gray-700"><?= sanitize($adj['reason']) ?></p>
    </div>
</div>

<?php if ($adj['approval_notes']): ?>
<div class="mb-6">
    <div class="bg-amber-50 border border-amber-200 rounded-lg shadow p-6">
        <h2 class="font-semibold text-amber-900 mb-2">Approval Notes</h2>
        <p class="text-amber-800"><?= sanitize($adj['approval_notes']) ?></p>
    </div>
</div>
<?php endif; ?>

<?php if ($adj['status'] === 'approved' && $adj['journal_entry_id']): ?>
<div class="mb-6">
    <div class="bg-green-50 border border-green-200 rounded-lg shadow p-6">
        <h2 class="font-semibold text-green-900 mb-2"><i class="fas fa-check-circle mr-1"></i>Posted to General Ledger</h2>
        <p class="text-green-800">Journal voucher <span class="font-mono font-medium"><?= sanitize($adj['journal_voucher_no']) ?></span></p>
        <p class="text-xs text-green-700 mt-1">The adjustment was posted to the configured tax GL accounts and registered in the tax register.</p>
    </div>
</div>
<?php endif; ?>

<?php if ($adj['status'] === 'draft' && hasPermission('tax.adjust')): ?>
<div class="bg-white rounded-lg shadow p-6">
    <div class="flex flex-wrap items-start gap-6">
        <form method="post" action="approve.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <button type="submit" class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700"
                    onclick="return confirm('Approve and post this adjustment to the general ledger?')">
                <i class="fas fa-check-circle mr-2"></i>Approve &amp; Post
            </button>
        </form>
        <form method="post" action="reject.php" class="flex-1 min-w-[280px]">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <label class="block text-sm font-medium text-gray-700 mb-1">Rejection notes</label>
            <textarea name="approval_notes" rows="2" placeholder="Optional — why is this adjustment being rejected?" class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500"></textarea>
            <button type="submit" class="mt-2 px-4 py-2 text-sm bg-rose-600 text-white rounded-lg hover:bg-rose-700"
                    onclick="return confirm('Reject this adjustment?')">
                <i class="fas fa-times-circle mr-2"></i>Reject
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once '../../../../includes/footer.php'; ?>