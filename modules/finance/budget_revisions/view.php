<?php
$pageTitle = 'Revision Detail';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { flash('error', 'Revision not found.', 'error'); redirect('index.php'); }

$r = $db->prepare("SELECT r.*, b.budget_code, b.budget_name, b.financial_year_id, v.version_label,
                          acct.account_code, acct.account_name,
                          d.department_name, p.project_name
                   FROM budget_revisions r
                   JOIN budgets b ON b.id = r.budget_id
                   JOIN budget_versions v ON v.id = r.budget_version_id
                   JOIN budget_lines bl ON bl.id = r.line_id
                   JOIN chart_of_accounts acct ON acct.id = bl.chart_of_accounts_id
                   LEFT JOIN departments d ON d.id = bl.department_id
                   LEFT JOIN projects p ON p.id = bl.project_id
                   WHERE r.id = ?");
$r->execute([$id]);
$r = $r->fetch();
if (!$r) { flash('error', 'Revision not found.', 'error'); redirect('index.php'); }

$createdBy = $db->prepare("SELECT name FROM users WHERE id = ?");
$createdBy->execute([$r['created_by']]);
$createdBy = $createdBy->fetch();
$creatorName = $createdBy ? $createdBy['name'] : '—';
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Revision Detail</h1>
        <p class="text-gray-600"><?= sanitize($r['budget_code']) ?> — <?= sanitize($r['budget_name']) ?></p>
    </div>
    <div class="flex gap-2">
        <a href="index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Revisions</a>
        <a href="../budgets/view.php?id=<?= (int)$r['budget_id'] ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-eye mr-2"></i>Budget</a>
    </div>
</div>

<div class="bg-white rounded-lg shadow p-4 mb-6 flex flex-wrap gap-6 items-center">
    <div>
        <div class="text-xs text-gray-500 uppercase">Revision Type</div>
        <span class="px-2 py-1 text-xs rounded font-medium
            <?= $r['revision_type'] === 'increase' ? 'bg-green-100 text-green-800' :
                ($r['revision_type'] === 'decrease' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') ?>">
            <?= ucfirst($r['revision_type']) ?>
        </span>
    </div>
    <div>
        <div class="text-xs text-gray-500 uppercase">Version</div>
        <div class="text-sm text-gray-700"><?= sanitize($r['version_label']) ?></div>
    </div>
    <div>
        <div class="text-xs text-gray-500 uppercase">Old Amount</div>
        <div class="text-lg font-bold font-mono text-gray-900"><?= formatMoney((float)$r['old_amount']) ?></div>
    </div>
    <div>
        <div class="text-xs text-gray-500 uppercase">New Amount</div>
        <div class="text-lg font-bold font-mono text-gray-900"><?= formatMoney((float)$r['new_amount']) ?></div>
    </div>
    <div>
        <div class="text-xs text-gray-500 uppercase">Change</div>
        <div class="text-lg font-bold font-mono
            <?= (float)$r['change_amount'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
            <?= ((float)$r['change_amount'] >= 0 ? '+' : '') ?><?= formatMoney((float)$r['change_amount']) ?>
        </div>
    </div>
    <div>
        <div class="text-xs text-gray-500 uppercase">Created by</div>
        <div class="text-sm text-gray-700"><?= sanitize($creatorName) ?></div>
    </div>
    <div>
        <div class="text-xs text-gray-500 uppercase">Date</div>
        <div class="text-sm text-gray-700"><?= date('M d, Y H:i', strtotime($r['created_at'])) ?></div>
    </div>
</div>

<?php if (!empty($r['reason'])): ?>
<div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded mb-6">
    <span class="font-medium"><i class="fas fa-sticky-note mr-2"></i>Reason:</span>
    <?= sanitize($r['reason']) ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow p-5 mb-6">
    <h3 class="font-semibold text-gray-800 mb-3">Line Details</h3>
    <dl class="grid grid-cols-2 gap-3 text-sm">
        <dt class="text-gray-500">Account</dt>
        <dd class="font-mono text-gray-900"><?= sanitize($r['account_code']) ?> - <?= sanitize($r['account_name']) ?></dd>
        <dt class="text-gray-500">Department</dt>
        <dd class="text-gray-700"><?= sanitize($r['department_name'] ?? '—') ?></dd>
        <dt class="text-gray-500">Project</dt>
        <dd class="text-gray-700"><?= sanitize($r['project_name'] ?? '—') ?></dd>
        <dt class="text-gray-500">Budget Line ID</dt>
        <dd class="font-mono text-gray-700">#<?= (int)$r['line_id'] ?></dd>
    </dl>
</div>

<?php require_once '../../../includes/footer.php'; ?>