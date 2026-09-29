<?php
$pageTitle = 'Budget Revisions';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.view')) { die('Access Denied'); }

$db = getDB();

$budget_id = $_GET['budget_id'] ?? '';
$version_id = $_GET['version_id'] ?? '';

$where = " WHERE 1=1 ";
$params = [];
if ($budget_id !== '') { $where .= " AND r.budget_id = ?"; $params[] = (int)$budget_id; }
if ($version_id !== '') { $where .= " AND r.budget_version_id = ?"; $params[] = (int)$version_id; }

$page  = getPageNumber();
$perPage = 15;
$countStmt = $db->prepare("SELECT COUNT(*) FROM budget_revisions r $where");
$countStmt->execute($params);
$pagination = paginate($countStmt->fetchColumn(), $perPage, $page);

$base = "SELECT r.*, b.budget_code, b.budget_name, v.version_label, fy.year_name,
                acct.account_code, acct.account_name,
                d.department_name, p.project_name
         FROM budget_revisions r
         JOIN budgets b ON b.id = r.budget_id
         JOIN financial_years fy ON fy.id = b.financial_year_id
         JOIN budget_versions v ON v.id = r.budget_version_id
         JOIN budget_lines bl ON bl.id = r.line_id
         JOIN chart_of_accounts acct ON acct.id = bl.chart_of_accounts_id
         LEFT JOIN departments d ON d.id = bl.department_id
         LEFT JOIN projects p ON p.id = bl.project_id $where";
$stmt = $db->prepare($base . " ORDER BY r.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $pagination['offset']]));
$revisions = $stmt->fetchAll();

$budgets = $db->query("SELECT id, budget_code, budget_name FROM budgets ORDER BY budget_code")->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Budget Revisions</h1>
        <p class="text-gray-600">Individual line adjustments — increase, decrease, adjustment</p>
    </div>
    <div class="flex gap-2">
        <a href="../budgets/index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Budgets</a>
        <?php if (hasPermission('budget.revise')): ?>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New Revision</a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="w-64">
            <label class="block text-sm font-medium text-gray-700 mb-1">Budget</label>
            <select name="budget_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Budgets</option>
                <?php foreach ($budgets as $b): ?>
                <option value="<?= (int)$b['id'] ?>" <?= $budget_id == $b['id'] ? 'selected' : '' ?>><?= sanitize($b['budget_code']) ?> — <?= sanitize($b['budget_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-64">
            <label class="block text-sm font-medium text-gray-700 mb-1">Version</label>
            <select name="version_id" id="version_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Versions</option>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-search"></i></button>
        <?php if ($budget_id || $version_id): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<!-- List -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Budget</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Version</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Line</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Old</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">New</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Change</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($revisions as $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm">
                        <span class="font-mono text-gray-900"><?= sanitize($r['budget_code']) ?></span>
                        <div class="text-gray-600 text-xs"><?= sanitize($r['budget_name']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($r['version_label']) ?></td>
                    <td class="px-4 py-3 text-sm">
                        <span class="font-mono text-gray-900"><?= sanitize($r['account_code']) ?></span>
                        <div class="text-gray-600 text-xs"><?= sanitize($r['department_name'] ?? '—') ?> / <?= sanitize($r['project_name'] ?? '—') ?></div>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <span class="px-2 py-1 text-xs rounded font-medium
                        <?= $r['revision_type'] === 'increase' ? 'bg-green-100 text-green-800' :
                            ($r['revision_type'] === 'decrease' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') ?>">
                            <?= ucfirst($r['revision_type']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-700"><?= formatMoney((float)$r['old_amount']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney((float)$r['new_amount']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono
                        <?= (float)$r['change_amount'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= ((float)$r['change_amount'] >= 0 ? '+' : '') ?><?= formatMoney((float)$r['change_amount']) ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y H:i', strtotime($r['created_at'])) ?></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="view.php?id=<?= (int)$r['id'] ?>" class="text-blue-600 hover:text-blue-900" title="View"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($revisions)): ?>
                <tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-edit text-4xl mb-3 text-gray-300"></i>
                    <p>No revisions found</p>
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4"><?= paginationLinks($pagination, '?') ?></div>

<script>
// Populate versions when budget changes
document.querySelector('select[name="budget_id"]').addEventListener('change', function() {
    const bid = this.value;
    const vSel = document.getElementById('version_id');
    vSel.innerHTML = '<option value="">All Versions</option>';
    if (!bid) return;
    fetch('versions_ajax.php?budget_id=' + bid)
        .then(r => r.json())
        .then(data => {
            data.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.id;
                opt.textContent = v.version_label + ' (v' + v.version_no + ')';
                vSel.appendChild(opt);
            });
            <?php if ($version_id): ?>
            vSel.value = '<?= $version_id ?>';
            <?php endif; ?>
        });
});
// Initial load
<?php if ($budget_id): ?>
document.querySelector('select[name="budget_id"]').dispatchEvent(new Event('change'));
<?php endif; ?>
</script>
<?php require_once '../../../includes/footer.php'; ?>