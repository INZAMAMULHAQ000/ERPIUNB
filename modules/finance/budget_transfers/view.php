<?php
$pageTitle = 'Transfer Detail';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/validation.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { flash('error', 'Transfer not found.', 'error'); redirect('index.php'); }

$t = $db->prepare("SELECT t.*, b.budget_code, b.budget_name, b.financial_year_id, b.status AS budget_status, v.version_label,
                          fy.year_name, fy.start_date, fy.end_date
                   FROM budget_transfers t
                   JOIN budgets b ON b.id = t.budget_id
                   JOIN financial_years fy ON fy.id = b.financial_year_id
                   JOIN budget_versions v ON v.id = t.budget_version_id
                   WHERE t.id = ?");
$t->execute([$id]);
$t = $t->fetch();
if (!$t) { flash('error', 'Transfer not found.', 'error'); redirect('index.php'); }

$fy = ['id' => $t['financial_year_id'], 'start_date' => $t['start_date'], 'end_date' => $t['end_date']];

// From line
$from = $db->prepare("SELECT bl.*, acct.account_code, acct.account_name, d.department_name, p.project_name
                      FROM budget_lines bl
                      JOIN chart_of_accounts acct ON acct.id = bl.chart_of_accounts_id
                      LEFT JOIN departments d ON d.id = bl.department_id
                      LEFT JOIN projects p ON p.id = bl.project_id
                      WHERE bl.id = ?");
$from->execute([$t['from_line_id']]);
$from = $from->fetch();

// To line
$to = $db->prepare("SELECT bl.*, acct.account_code, acct.account_name, d.department_name, p.project_name
                    FROM budget_lines bl
                    JOIN chart_of_accounts acct ON acct.id = bl.chart_of_accounts_id
                    LEFT JOIN departments d ON d.id = bl.department_id
                    LEFT JOIN projects p ON p.id = bl.project_id
                    WHERE bl.id = ?");
$to->execute([$t['to_line_id']]);
$to = $to->fetch();

$requestedBy = $db->prepare("SELECT name FROM users WHERE id = ?");
$requestedBy->execute([$t['requested_by']]);
$requestedBy = $requestedBy->fetch();
$reqName = $requestedBy ? $requestedBy['name'] : '—';
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= sanitize($t['transfer_no']) ?></h1>
        <p class="text-gray-600"><?= sanitize($t['budget_code']) ?> — <?= sanitize($t['budget_name']) ?></p>
    </div>
    <div class="flex gap-2">
        <a href="index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Transfers</a>
        <a href="../budgets/view.php?id=<?= (int)$t['budget_id'] ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-eye mr-2"></i>Budget</a>
    </div>
</div>

<div class="bg-white rounded-lg shadow p-4 mb-6 flex flex-wrap gap-6 items-center">
    <div>
        <div class="text-xs text-gray-500 uppercase">Transfer Type</div>
        <div class="text-sm font-medium text-gray-900 capitalize"><?= sanitize($t['transfer_type']) ?></div>
    </div>
    <div>
        <div class="text-xs text-gray-500 uppercase">Status</div>
        <span class="px-2 py-1 text-xs rounded font-medium <?= transfer_status_badge($t['status']) ?>"><?= ucfirst($t['status']) ?></span>
    </div>
    <div>
        <div class="text-xs text-gray-500 uppercase">Amount</div>
        <div class="text-xl font-bold font-mono text-gray-900"><?= formatMoney((float)$t['amount']) ?></div>
    </div>
    <div>
        <div class="text-xs text-gray-500 uppercase">Version</div>
        <div class="text-sm text-gray-700"><?= sanitize($t['version_label']) ?></div>
    </div>
    <div>
        <div class="text-xs text-gray-500 uppercase">Requested by</div>
        <div class="text-sm text-gray-700"><?= sanitize($reqName) ?></div>
    </div>
    <?php if ($t['status'] === 'pending' && $t['budget_status'] === 'active' && hasPermission('budget.approve')): ?>
    <div class="ml-auto flex gap-2">
        <button onclick="confirmApprove()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition"><i class="fas fa-check mr-2"></i>Approve</button>
        <button onclick="showReject()" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition"><i class="fas fa-times mr-2"></i>Reject</button>
    </div>
    <?php endif; ?>
    <?php if ($t['status'] === 'pending' && hasPermission('budget.transfer')): ?>
    <form method="POST" action="cancel.php" class="ml-auto" onsubmit="return confirm('Cancel this transfer?');">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="transfer_id" value="<?= (int)$t['id'] ?>">
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-ban mr-2"></i>Cancel</button>
    </form>
    <?php endif; ?>
</div>

<?php if (!empty($t['reason'])): ?>
<div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded mb-6">
    <span class="font-medium"><i class="fas fa-sticky-note mr-2"></i>Reason:</span>
    <?= sanitize($t['reason']) ?>
</div>
<?php endif; ?>

<?php if ($t['status'] === 'rejected' && !empty($t['rejection_reason'])): ?>
<div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded mb-6">
    <span class="font-medium"><i class="fas fa-ban mr-2"></i>Rejection Reason:</span>
    <?= sanitize($t['rejection_reason']) ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold text-gray-800 mb-3 flex items-center"><i class="fas fa-arrow-down text-red-500 mr-2"></i>From (Source)</h3>
        <dl class="space-y-2 text-sm">
            <dt class="text-gray-500">Account</dt>
            <dd class="font-mono text-gray-900"><?= sanitize($from['account_code'] ?? '—') ?> - <?= sanitize($from['account_name'] ?? '—') ?></dd>
            <dt class="text-gray-500">Department</dt>
            <dd class="text-gray-700"><?= sanitize($from['department_name'] ?? '—') ?></dd>
            <dt class="text-gray-500">Project</dt>
            <dd class="text-gray-700"><?= sanitize($from['project_name'] ?? '—') ?></dd>
            <dt class="text-gray-500">Month</dt>
            <dd class="text-gray-700"><?= sanitize(budget_month_name($from['month'] ?? 0)) ?></dd>
            <dt class="text-gray-500">Current Budget</dt>
            <dd class="font-mono text-gray-900"><?= formatMoney((float)($from['budget_amount'] ?? 0)) ?></dd>
        </dl>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold text-gray-800 mb-3 flex items-center"><i class="fas fa-arrow-up text-green-500 mr-2"></i>To (Destination)</h3>
        <dl class="space-y-2 text-sm">
            <dt class="text-gray-500">Account</dt>
            <dd class="font-mono text-gray-900"><?= sanitize($to['account_code'] ?? '—') ?> - <?= sanitize($to['account_name'] ?? '—') ?></dd>
            <dt class="text-gray-500">Department</dt>
            <dd class="text-gray-700"><?= sanitize($to['department_name'] ?? '—') ?></dd>
            <dt class="text-gray-500">Project</dt>
            <dd class="text-gray-700"><?= sanitize($to['project_name'] ?? '—') ?></dd>
            <dt class="text-gray-500">Month</dt>
            <dd class="text-gray-700"><?= sanitize(budget_month_name($to['month'] ?? 0)) ?></dd>
            <dt class="text-gray-500">Current Budget</dt>
            <dd class="font-mono text-gray-900"><?= formatMoney((float)($to['budget_amount'] ?? 0)) ?></dd>
        </dl>
    </div>
</div>

<?php if ($t['status'] === 'pending'): ?>
<div class="bg-white rounded-lg shadow p-5">
    <h3 class="font-semibold text-gray-800 mb-3">Actions</h3>
    <form method="POST" action="approve.php" id="approveForm" class="inline">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="transfer_id" value="<?= (int)$t['id'] ?>">
        <input type="hidden" name="action" value="approve">
    </form>
    <form method="POST" action="approve.php" id="rejectForm" class="inline">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="transfer_id" value="<?= (int)$t['id'] ?>">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="rejection_reason" id="rejectReason">
    </form>
</div>

<script>
function confirmApprove() {
    if (confirm('Approve this transfer? This will move budget from the source line to the destination line.')) {
        document.getElementById('approveForm').submit();
    }
}
function showReject() {
    const reason = prompt('Enter rejection reason (required):');
    if (reason !== null && reason.trim() !== '') {
        document.getElementById('rejectReason').value = reason.trim();
        document.getElementById('rejectForm').submit();
    } else if (reason !== null) {
        alert('Rejection reason is required.');
    }
}
</script>
<?php endif; ?>

<?php require_once '../../../includes/footer.php'; ?>