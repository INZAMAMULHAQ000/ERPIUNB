<?php
$pageTitle = 'Budget Details';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$budget = budget_find($db, $id);
if (!$budget) { flash('error', 'Budget not found.', 'error'); redirect('index.php'); }

$s = $db->prepare("SELECT * FROM financial_years WHERE id = ?"); $s->execute([$budget['financial_year_id']]);
$fy = $s->fetch();
$version = budget_active_version($db, $id);

$lines = $version ? budget_lines_for_version($db, $version['id'], $fy, null) : [];
$totals = budget_lines_totals($lines);

$st = $db->prepare("SELECT * FROM budget_versions WHERE budget_id = ? ORDER BY version_no");
$st->execute([$id]);
$versions = $st->fetchAll();

$st = $db->prepare("SELECT r.*, acct.account_code, acct.account_name, d.department_name, p.project_name
                    FROM budget_revisions r
                    LEFT JOIN chart_of_accounts acct ON acct.id = (
                        SELECT chart_of_accounts_id FROM budget_lines WHERE id = r.line_id)
                    LEFT JOIN budget_lines blr ON blr.id = r.line_id
                    LEFT JOIN departments d ON d.id = blr.department_id
                    LEFT JOIN projects p ON p.id = blr.project_id
                    WHERE r.budget_id = ? ORDER BY r.created_at DESC");
$st->execute([$id]);
$revisions = $st->fetchAll();

$st = $db->prepare("SELECT bt.*, f.acct_code AS from_acct, t.acct_code AS to_acct
                    FROM budget_transfers bt
                    LEFT JOIN (SELECT bl.id, acct.account_code AS acct_code FROM budget_lines bl JOIN chart_of_accounts acct ON acct.id=bl.chart_of_accounts_id) f ON f.id = bt.from_line_id
                    LEFT JOIN (SELECT bl.id, acct.account_code AS acct_code FROM budget_lines bl JOIN chart_of_accounts acct ON acct.id=bl.chart_of_accounts_id) t ON t.id = bt.to_line_id
                    WHERE bt.budget_id = ? ORDER BY bt.created_at DESC");
$st->execute([$id]);
$transfers = $st->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= sanitize($budget['budget_name']) ?></h1>
        <p class="text-gray-600"><?= sanitize($budget['budget_code']) ?> · <?= sanitize($budget['year_name']) ?> · <?= ucwords(str_replace('_',' ',$budget['budget_type'])) ?> Budget</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back</a>
        <?php if (in_array($budget['status'], ['draft','rejected']) && hasPermission('budget.edit')): ?>
            <a href="edit.php?id=<?= $id ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-edit mr-2"></i>Edit</a>
            <a href="matrix.php?id=<?= $id ?>" class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700 transition"><i class="fas fa-th mr-2"></i>Matrix</a>
            <form method="POST" action="submit.php" style="display:inline" onsubmit="return confirm('Submit budget for approval?')">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="budget_id" value="<?= $id ?>">
                <button type="submit" class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700 transition"><i class="fas fa-paper-plane mr-2"></i>Submit</button>
            </form>
        <?php endif; ?>
        <?php if ($budget['status'] === 'pending_approval' && hasPermission('budget.approve')): ?>
            <form method="POST" action="approve.php" style="display:inline" onsubmit="return confirm('Approve this budget?')">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="budget_id" value="<?= $id ?>"><input type="hidden" name="action" value="approve">
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition"><i class="fas fa-check mr-2"></i>Approve</button>
            </form>
            <button type="button" onclick="showRejectModal()" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition"><i class="fas fa-times mr-2"></i>Reject</button>
        <?php endif; ?>
        <?php if ($budget['status'] === 'approved' && hasPermission('budget.approve')): ?>
            <form method="POST" action="activate.php" style="display:inline" onsubmit="return confirm('Activate this budget for monitoring?')">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="budget_id" value="<?= $id ?>">
                <button type="submit" class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700 transition"><i class="fas fa-play mr-2"></i>Activate</button>
            </form>
        <?php endif; ?>
        <?php if (in_array($budget['status'], ['active','approved']) ): ?>
            <a href="../budget_versions/create.php?budget_id=<?= $id ?>" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 transition"><i class="fas fa-copy mr-2"></i>New Version</a>
            <?php if (hasPermission('budget.transfer')): ?><a href="../budget_transfers/create.php?budget_id=<?= $id ?>" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 transition"><i class="fas fa-exchange-alt mr-2"></i>Transfer</a><?php endif; ?>
            <?php if (hasPermission('budget.revise')): ?><a href="../budget_revisions/create.php?budget_id=<?= $id ?>" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 transition"><i class="fas fa-pen-alt mr-2"></i>Revise</a><?php endif; ?>
            <?php if ($budget['status'] === 'active' && hasPermission('budget.approve')): ?>
            <form method="POST" action="close.php" style="display:inline" onsubmit="return confirm('Close this budget? The FY is complete.')">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="budget_id" value="<?= $id ?>">
                <button type="submit" class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800 transition"><i class="fas fa-unlock mr-2"></i>Close</button>
            </form>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (in_array($budget['status'], ['draft','rejected']) && hasPermission('budget.edit')): ?>
            <form method="POST" action="delete.php" style="display:inline" onsubmit="return confirm('Delete this draft budget permanently?')">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="budget_id" value="<?= $id ?>">
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition"><i class="fas fa-trash mr-2"></i>Delete</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Reject modal -->
<div id="rejectModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <form method="POST" action="approve.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="budget_id" value="<?= $id ?>">
            <input type="hidden" name="action" value="reject">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Reject Budget</h3>
                <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Rejection</label>
                <textarea name="rejection_reason" rows="3" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-red-500" required placeholder="Please provide a reason..."></textarea>
            </div>
            <div class="flex justify-end gap-3 px-6 pb-6">
                <button type="button" onclick="hideRejectModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">Cancel</button>
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition">Reject</button>
            </div>
        </form>
    </div>
</div>

<!-- Overview -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-5"><div class="flex items-center justify-between"><div><p class="text-xs text-gray-500 uppercase tracking-wide">Status</p><span class="inline-block mt-1 px-2 py-1 text-xs rounded font-medium <?= budget_status_badge($budget['status']) ?>"><?= ucwords(str_replace('_',' ',$budget['status'])) ?></span></div><div class="bg-gray-100 p-3 rounded-full"><i class="fas fa-bolt text-gray-600"></i></div></div></div>
    <div class="bg-white rounded-lg shadow p-5"><div class="flex items-center justify-between"><div><p class="text-xs text-gray-500 uppercase tracking-wide">Allocated</p><p class="text-xl font-bold text-gray-800 mt-1"><?= formatMoney($totals['allocated']) ?></p></div><div class="bg-blue-100 p-3 rounded-full"><i class="fas fa-coins text-blue-600"></i></div></div></div>
    <div class="bg-white rounded-lg shadow p-5"><div class="flex items-center justify-between"><div><p class="text-xs text-gray-500 uppercase tracking-wide">Actual Spent</p><p class="text-xl font-bold text-gray-800 mt-1"><?= formatMoney($totals['spent']) ?></p></div><div class="bg-red-100 p-3 rounded-full"><i class="fas fa-receipt text-red-600"></i></div></div></div>
    <div class="bg-white rounded-lg shadow p-5"><div class="flex items-center justify-between"><div><p class="text-xs text-gray-500 uppercase tracking-wide">Available</p><p class="text-xl font-bold text-gray-800 mt-1"><?= formatMoney($totals['available']) ?></p></div><div class="bg-emerald-100 p-3 rounded-full"><i class="fas fa-chart-pie text-emerald-600"></i></div></div></div>
</div>

<!-- Lines -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-4 py-3 border-b border-gray-200 flex justify-between items-center">
        <h2 class="text-lg font-semibold text-gray-800">Budget Lines — <?= sanitize($version['version_label'] ?? '') ?></h2>
        <span class="text-sm text-gray-500">Utilization <?= $totals['pct'] ?>%</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dimension</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Allocated</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Spent</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Available</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">%</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($lines as $l): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm"><?= sanitize($l['account_code']) ?> <span class="text-gray-500">— <?= sanitize($l['account_name']) ?></span></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= $l['department_name'] ? sanitize($l['department_name']) : '' ?><?= $l['project_name'] ? ' · ' . sanitize($l['project_name']) : '' ?><?= (!$l['department_name'] && !$l['project_name']) ? '—' : '' ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= budget_month_name($l['month']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney((float)$l['budget_amount']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney($l['spent']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney($l['available']) ?></td>
                    <td class="px-4 py-3 text-right"><span class="px-2 py-1 text-xs rounded font-medium <?= budget_variance_badge($l['pct']) ?>"><?= number_format($l['pct'],1) ?>%</span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($lines)): ?><tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No lines.</td></tr><?php endif; ?>
            </tbody>
            <tfoot class="bg-gray-100 font-semibold">
                <tr><td colspan="3" class="px-4 py-3 text-right">Totals</td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($totals['allocated']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($totals['spent']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($totals['available']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= number_format($totals['pct'],1) ?>%</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Versions -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-3 border-b border-gray-200"><h2 class="text-lg font-semibold text-gray-800">Versions</h2></div>
        <div class="p-4">
            <table class="w-full text-sm">
                <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Version</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th><th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th></tr></thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($versions as $v): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2"><a href="../budget_versions/view.php?id=<?= (int)$v['id'] ?>" class="text-blue-600 hover:text-blue-800"><?= sanitize($v['version_label']) ?> #<?= (int)$v['version_no'] ?></a></td>
                        <td class="px-3 py-2 text-gray-600 capitalize"><?= sanitize($v['version_type']) ?></td>
                        <td class="px-3 py-2"><span class="px-2 py-1 text-xs rounded font-medium <?= version_status_badge($v['status']) ?>"><?= ucfirst($v['status']) ?></span></td>
                        <td class="px-3 py-2 text-right font-mono"><?= formatMoney((float)$v['total_amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($versions)): ?><tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">No versions.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Revisions -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-3 border-b border-gray-200"><h2 class="text-lg font-semibold text-gray-800">Revisions</h2></div>
        <div class="p-4">
            <table class="w-full text-sm">
                <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Account</th><th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Change</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reason</th></tr></thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($revisions as $r): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2"><span class="px-2 py-1 text-xs rounded font-medium <?= $r['revision_type']==='increase'?'bg-green-100 text-green-800':($r['revision_type']==='decrease'?'bg-red-100 text-red-800':'bg-yellow-100 text-yellow-800') ?>"><?= ucfirst($r['revision_type']) ?></span></td>
                        <td class="px-3 py-2 text-gray-600"><?= sanitize($r['account_code']) ?></td>
                        <td class="px-3 py-2 text-right font-mono <?= $r['change_amount']>=0?'text-green-700':'text-red-700' ?>"><?= ($r['change_amount']>=0?'+':'') . number_format((float)$r['change_amount'],2) ?></td>
                        <td class="px-3 py-2 text-gray-600 max-w-[180px] truncate" title="<?= sanitize($r['reason']) ?>"><?= sanitize($r['reason']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($revisions)): ?><tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">No revisions.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Transfers -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-6 py-3 border-b border-gray-200"><h2 class="text-lg font-semibold text-gray-800">Transfers</h2></div>
    <div class="p-4 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Transfer #</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">From</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">To</th><th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th></tr></thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($transfers as $t): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2 font-mono text-gray-900"><?= sanitize($t['transfer_no']) ?></td>
                    <td class="px-3 py-2 text-gray-600"><?= sanitize($t['from_acct']) ?></td>
                    <td class="px-3 py-2 text-gray-600"><?= sanitize($t['to_acct']) ?></td>
                    <td class="px-3 py-2 text-right font-mono"><?= formatMoney((float)$t['amount']) ?></td>
                    <td class="px-3 py-2"><span class="px-2 py-1 text-xs rounded font-medium <?= transfer_status_badge($t['status']) ?>"><?= ucfirst($t['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($transfers)): ?><tr><td colspan="5" class="px-3 py-4 text-center text-gray-500">No transfers.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function showRejectModal() { document.getElementById('rejectModal').classList.remove('hidden'); document.getElementById('rejectModal').classList.add('flex'); }
function hideRejectModal() { document.getElementById('rejectModal').classList.add('hidden'); document.getElementById('rejectModal').classList.remove('flex'); }
</script>
<?php require_once '../../../includes/footer.php'; ?>