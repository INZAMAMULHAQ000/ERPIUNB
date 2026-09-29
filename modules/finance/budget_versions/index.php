<?php
$pageTitle = 'Budget Versions';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.view')) { die('Access Denied'); }

$db = getDB();

$budget_id = $_GET['budget_id'] ?? '';
$fy_id     = $_GET['financial_year_id'] ?? '';
$status    = $_GET['status'] ?? '';

$where = " WHERE 1=1 ";
$params = [];
if ($budget_id !== '') { $where .= " AND v.budget_id = ?"; $params[] = (int)$budget_id; }
if ($status !== '')    { $where .= " AND v.status = ?"; $params[] = $status; }
if ($fy_id !== '')     { $where .= " AND b.financial_year_id = ?"; $params[] = (int)$fy_id; }

$page  = getPageNumber();
$perPage = 15;
$countStmt = $db->prepare("SELECT COUNT(*) FROM budget_versions v JOIN budgets b ON b.id = v.budget_id $where");
$countStmt->execute($params);
$pagination = paginate($countStmt->fetchColumn(), $perPage, $page);

$base = "SELECT v.*, b.budget_code, b.budget_name, b.budget_type, fy.year_name
         FROM budget_versions v
         JOIN budgets b ON b.id = v.budget_id
         JOIN financial_years fy ON fy.id = b.financial_year_id $where";
$stmt = $db->prepare($base . " ORDER BY v.budget_id, v.version_no DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $pagination['offset']]));
$versions = $stmt->fetchAll();

$budgets = $db->query("SELECT id, budget_code, budget_name FROM budgets ORDER BY budget_code")->fetchAll();
$fys = $db->query("SELECT * FROM financial_years ORDER BY start_date DESC")->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Budget Versions</h1>
        <p class="text-gray-600">Version history — originals, revisions, forecasts &amp; finals</p>
    </div>
    <div class="flex gap-2">
        <a href="../budgets/index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Budgets</a>
        <?php if (hasPermission('budget.edit')): ?>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New Version</a>
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
        <div class="w-44">
            <label class="block text-sm font-medium text-gray-700 mb-1">Financial Year</label>
            <select name="financial_year_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Years</option>
                <?php foreach ($fys as $fy): ?>
                <option value="<?= (int)$fy['id'] ?>" <?= $fy_id == $fy['id'] ? 'selected' : '' ?>><?= sanitize($fy['year_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach (['draft','approved','active','locked'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-search"></i></button>
        <?php if ($budget_id || $status || $fy_id): ?>
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
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fiscal Year</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($versions as $v): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm">
                        <span class="font-mono text-gray-900"><?= sanitize($v['budget_code']) ?></span>
                        <div class="text-gray-600"><?= sanitize($v['budget_name']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900"><?= sanitize($v['version_label']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600 capitalize"><?= sanitize($v['version_type']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= sanitize($v['year_name']) ?></td>
                    <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded font-medium <?= version_status_badge($v['status']) ?>"><?= ucfirst($v['status']) ?></span></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney((float)$v['total_amount']) ?></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="view.php?id=<?= (int)$v['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3" title="View"><i class="fas fa-eye"></i></a>
                        <?php if ($v['status'] === 'draft' && hasPermission('budget.approve')): ?>
                        <form method="POST" action="activate.php" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="version_id" value="<?= (int)$v['id'] ?>">
                            <button type="submit" class="text-green-600 hover:text-green-900" title="Activate"><i class="fas fa-check-circle"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($versions)): ?>
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-history text-4xl mb-3 text-gray-300"></i>
                    <p>No versions found</p>
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4"><?= paginationLinks($pagination, '?') ?></div>
<?php require_once '../../../includes/footer.php'; ?>