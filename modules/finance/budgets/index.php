<?php
$pageTitle = 'Budgets';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.view')) { die('Access Denied'); }

$db = getDB();

$search = $_GET['search'] ?? '';
$type   = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$fy_id  = $_GET['financial_year_id'] ?? '';

$where = " WHERE 1=1 ";
$params = [];
if ($search !== '') { $where .= " AND (b.budget_code LIKE ? OR b.budget_name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($type !== '')   { $where .= " AND b.budget_type = ?"; $params[] = $type; }
if ($status !== '') { $where .= " AND b.status = ?"; $params[] = $status; }
if ($fy_id !== '')  { $where .= " AND b.financial_year_id = ?"; $params[] = (int)$fy_id; }

$page  = getPageNumber();
$perPage = 15;
$countStmt = $db->prepare("SELECT COUNT(*) FROM budgets b $where");
$countStmt->execute($params);
$pagination = paginate($countStmt->fetchColumn(), $perPage, $page);

$base = "SELECT b.*, fy.year_name, v.version_label, v.total_amount
         FROM budgets b
         JOIN financial_years fy ON fy.id = b.financial_year_id
         LEFT JOIN budget_versions v ON v.id = b.current_version_id $where";
$stmt = $db->prepare($base . " ORDER BY b.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $pagination['offset']]));
$budgets = $stmt->fetchAll();

$fys = $db->query("SELECT * FROM financial_years ORDER BY start_date DESC")->fetchAll();
$activeFy = getActiveFinancialYear($db);

$budgetTypes = ['annual' => 'Annual', 'quarterly' => 'Quarterly', 'monthly' => 'Monthly', 'project' => 'Project', 'department' => 'Department'];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Budgets</h1>
        <p class="text-gray-600">Budget master, versions, transfers &amp; revisions</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
        <?php if (hasPermission('budget.create')): ?>
        <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New Budget</a>
        <?php endif; ?>
        <?php if (hasPermission('budget.report')): ?>
        <a href="../budget_reports/dashboard.php" class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700 transition"><i class="fas fa-chart-line mr-2"></i>Dashboard</a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="w-72">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Code or name..." class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
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
            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <select name="type" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Types</option>
                <?php foreach ($budgetTypes as $k => $v): ?>
                <option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach (['draft','pending_approval','approved','active','rejected','closed'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-search"></i></button>
        <?php if ($search || $type || $status || $fy_id): ?>
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
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Budget</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Financial Year</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Version</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($budgets as $b): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-mono text-gray-900"><?= sanitize($b['budget_code']) ?></td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= sanitize($b['budget_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= sanitize($b['year_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600 capitalize"><?= sanitize($b['budget_type']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($b['version_label'] ?? '—') ?></td>
                    <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded font-medium <?= budget_status_badge($b['status']) ?>"><?= ucwords(str_replace('_',' ',$b['status'])) ?></span></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney((float)($b['total_amount'] ?? 0)) ?></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="view.php?id=<?= (int)$b['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3" title="View"><i class="fas fa-eye"></i></a>
                        <?php if (hasPermission('budget.edit') && in_array($b['status'], ['draft','rejected'])): ?>
                        <a href="edit.php?id=<?= (int)$b['id'] ?>" class="text-amber-600 hover:text-amber-900 mr-3" title="Edit"><i class="fas fa-edit"></i></a>
                        <a href="matrix.php?id=<?= (int)$b['id'] ?>" class="text-emerald-600 hover:text-emerald-900 mr-3" title="Allocation Matrix"><i class="fas fa-th"></i></a>
                        <?php endif; ?>
                        <?php if (in_array($b['status'], ['draft','active']) && hasPermission('budget.transfer')): ?>
                        <a href="../budget_transfers/create.php?budget_id=<?= (int)$b['id'] ?>" class="text-purple-600 hover:text-purple-900 mr-3" title="Transfer"><i class="fas fa-exchange-alt"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($budgets)): ?>
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-coins text-4xl mb-3 text-gray-300"></i>
                    <p>No budgets found</p>
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4"><?= paginationLinks($pagination, '?') ?></div>
<?php require_once '../../../includes/footer.php'; ?>