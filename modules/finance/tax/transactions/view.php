<?php
$pageTitle = 'Tax Transaction Details';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT x.*, r.rate_name, r.rate_percentage, g.group_name, e.exemption_code, e.name AS exemption_name,
           p.period_name, t.name AS type_name, t.code AS type_code, u.name AS created_by_name
    FROM tax_transactions x
    LEFT JOIN tax_rates r     ON r.id = x.tax_rate_id
    LEFT JOIN tax_groups g    ON g.id = x.tax_group_id
    LEFT JOIN tax_exemptions e ON e.id = x.exemption_id
    LEFT JOIN tax_periods p   ON p.id = x.tax_period_id
    LEFT JOIN tax_types t     ON t.id = x.tax_type_id
    LEFT JOIN users u         ON u.id = x.created_by
    WHERE x.id = ?
");
$stmt->execute([$id]);
$t = $stmt->fetch();
if (!$t) { flash('error', 'Tax transaction not found.', 'error'); redirect('index.php'); }

$total = (float)$t['taxable_amount'] + (float)$t['tax_amount'];
$ratePct = rtrim(rtrim(number_format((float)$t['tax_rate'], 4), '0'), '.') . '%';
?>

<div class="mb-6">
    <a href="index.php" class="text-blue-600 hover:underline text-sm">&larr; Back to Tax Transactions</a>
    <div class="flex flex-wrap items-center gap-3 mt-3">
        <h1 class="text-2xl font-bold text-gray-800 font-mono"><?= sanitize($t['source_document_no'] ?? ('#' . (int)$t['source_record_id'])) ?></h1>
        <span class="text-gray-500 text-sm"><i class="fas fa-calendar-alt mr-1"></i><?= sanitize($t['transaction_date']) ?></span>
        <?= taxui_direction_badge($t['tax_direction']) ?>
        <?= taxui_txn_badge($t['status']) ?>
        <?php if ($t['type_code']): ?>
        <span class="px-2 py-1 text-xs font-mono bg-slate-100 text-slate-700 rounded"><?= sanitize($t['type_code']) ?></span>
        <?php endif; ?>
    </div>
</div>

<?= taxui_tabs('transactions') ?>

<?php if ($t['status'] === 'reversed' && $t['reversal_of']): ?>
<div class="mb-6 p-4 rounded bg-amber-50 text-amber-800 border border-amber-200">
    <i class="fas fa-undo mr-2"></i>This transaction was reversed from
    <a href="view.php?id=<?= (int)$t['reversal_of'] ?>" class="underline font-medium">transaction #<?= (int)$t['reversal_of'] ?></a>.
</div>
<?php endif; ?>

<?php if ($t['reference'] !== null && $t['reference'] !== ''): ?>
<div class="mb-6 bg-white rounded-lg shadow p-4">
    <p class="text-gray-700 text-sm"><span class="text-xs uppercase tracking-wide text-gray-500 pr-2">Reference:</span><?= sanitize($t['reference']) ?></p>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Taxable Amount</div>
        <div class="text-xl font-bold text-gray-800 mt-1"><?= taxui_amount($t['taxable_amount']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Tax Amount</div>
        <div class="text-xl font-bold text-gray-800 mt-1"><?= taxui_amount($t['tax_amount']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Total</div>
        <div class="text-xl font-bold text-blue-600 mt-1"><?= taxui_amount($total) ?></div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Tax Type</div>
        <div class="text-gray-900"><?= sanitize($t['type_name'] ?? '—') ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Tax Rate</div>
        <div class="text-gray-900"><?php if ($t['rate_name']): ?><?= sanitize($t['rate_name']) ?> (<?= $ratePct ?>)<?php else: ?>—<?php endif; ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Tax Group</div>
        <div class="text-gray-900"><?= sanitize($t['group_name'] ?? '—') ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Tax Status</div>
        <div class="mt-1"><?= taxui_status_badge($t['tax_status']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Exemption</div>
        <div class="text-gray-900"><?php if ($t['exemption_code']): ?><span class="font-mono text-sm"><?= sanitize($t['exemption_code']) ?></span> — <?= sanitize($t['exemption_name']) ?><?php else: ?>—<?php endif; ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Tax Period</div>
        <div class="text-gray-900"><?= sanitize($t['period_name'] ?? '—') ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Counterparty</div>
        <div class="text-gray-900"><?php if ($t['counterparty_type'] !== 'none'): ?><?= ucwords(str_replace('_', ' ', $t['counterparty_type'])) ?> #<?= (int)$t['counterparty_id'] ?><?php else: ?>—<?php endif; ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Created By</div>
        <div class="text-gray-900"><?= sanitize($t['created_by_name'] ?? '—') ?></div>
    </div>
</div>

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Source</div>
    <div class="text-gray-900">
        <?= taxui_source_label($t['source_module']) ?> &middot; #<?= (int)$t['source_record_id'] ?>
        <span class="text-gray-500 text-xs ml-2"><i class="fas fa-clock mr-1"></i>Recorded <?= sanitize($t['created_at']) ?></span>
    </div>
</div>

<?php require_once '../../../../includes/footer.php'; ?>