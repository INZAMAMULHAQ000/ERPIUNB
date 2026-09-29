<?php
$pageTitle = 'Budget Monitoring';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/pagination.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.view')) { die('Access Denied'); }

$db = getDB();

$budget_id  = $_GET['budget_id'] ?? '';
$version_id = $_GET['version_id'] ?? '';
$dept_id    = $_GET['department_id'] ?? '';
$proj_id    = $_GET['project_id'] ?? '';
$acct_id    = $_GET['account_id'] ?? '';
$month      = $_GET['month'] ?? '';
$fy_id      = $_GET['financial_year_id'] ?? '';

$where = " WHERE 1=1 ";
$params = [];
if ($budget_id)  { $where .= " AND bl.budget_id = ?"; $params[] = (int)$budget_id; }
if ($version_id) { $where .= " AND bl.budget_version_id = ?"; $params[] = (int)$version_id; }
if ($fy_id)      { $where .= " AND b.financial_year_id = ?"; $params[] = (int)$fy_id; }
if ($month !== '')    { $where .= " AND bl.month = ?"; $params[] = (int)$month; }
if ($dept_id !== '')  { $where .= " AND bl.department_id = ?"; $params[] = (int)$dept_id; }
if ($proj_id !== '')  { $where .= " AND bl.project_id = ?"; $params[] = (int)$proj_id; }
if ($acct_id !== '')  { $where .= " AND bl.chart_of_accounts_id = ?"; $params[] = (int)$acct_id; }

$page  = getPageNumber();
$perPage = 50;
$countStmt = $db->prepare("SELECT COUNT(*) FROM budget_lines bl JOIN budgets b ON b.id = bl.budget_id $where");
$countStmt->execute($params);
$pagination = paginate($countStmt->fetchColumn(), $perPage, $page);

$base = "SELECT bl.*, b.budget_code, b.budget_name, b.budget_type, b.financial_year_id, fy.start_date, fy.end_date,
                v.version_label,
                acct.account_code, acct.account_name,
                d.department_name, d.id AS dept_id,
                p.project_name, p.id AS proj_id
         FROM budget_lines bl
         JOIN budgets b ON b.id = bl.budget_id
         JOIN financial_years fy ON fy.id = b.financial_year_id
         JOIN budget_versions v ON v.id = bl.budget_version_id
         JOIN chart_of_accounts acct ON acct.id = bl.chart_of_accounts_id
         LEFT JOIN departments d ON d.id = bl.department_id
         LEFT JOIN projects p ON p.id = bl.project_id $where";
$stmt = $db->prepare($base . " ORDER BY b.budget_code, acct.account_code, bl.month LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $pagination['offset']]));
$rawLines = $stmt->fetchAll();

// Enrich with actuals per line
$fyCache = [];
$enriched = [];
foreach ($rawLines as $l) {
    $fyCacheKey = $l['financial_year_id'];
    if (!isset($fyCache[$fyCacheKey])) {
        $fyCache[$fyCacheKey] = [
            'id'         => $l['financial_year_id'],
            'start_date' => $l['start_date'],
            'end_date'   => $l['end_date'],
        ];
    }
    $fy = $fyCache[$fyCacheKey];
    list($from, $to) = budget_month_range($fy, $l['month']);
    $spent = budget_line_actual($db, $l, $fy, $from, $to);
    $commStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM budget_transfers WHERE from_line_id = ? AND status = 'pending'");
    $commStmt->execute([$l['id']]);
    $committed = (float)$commStmt->fetchColumn();
    $l['spent']      = $spent;
    $l['committed']  = $committed;
    $l['available']  = (float)$l['budget_amount'] - $spent - $committed;
    $l['pct']        = (float)$l['budget_amount'] > 0 ? round($spent / (float)$l['budget_amount'] * 100, 2) : 0.0;
    $l['variance']   = (float)$l['budget_amount'] - $spent;
    $enriched[] = $l;
}

// Totals
$totals = ['allocated' => 0.0, 'spent' => 0.0, 'committed' => 0.0, 'available' => 0.0, 'pct' => 0.0, 'variance' => 0.0, 'count' => count($enriched)];
foreach ($enriched as $l) {
    $totals['allocated'] += (float)$l['budget_amount'];
    $totals['spent']     += $l['spent'];
    $totals['committed'] += $l['committed'];
    $totals['available'] += $l['available'];
    $totals['variance']  += $l['variance'];
}
$totals['pct'] = $totals['allocated'] > 0 ? round($totals['spent'] / $totals['allocated'] * 100, 2) : 0.0;

$budgets = $db->query("SELECT b.*, fy.year_name FROM budgets b JOIN financial_years fy ON fy.id = b.financial_year_id WHERE b.status NOT IN ('closed') ORDER BY b.budget_code")->fetchAll();
$fys = $db->query("SELECT * FROM financial_years ORDER BY start_date DESC")->fetchAll();
$departments = $db->query("SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name")->fetchAll();
$projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();
$accounts = $db->query("SELECT coa.id, coa.account_code, coa.account_name
                        FROM chart_of_accounts coa
                        JOIN account_groups ag ON ag.id = coa.group_id
                        WHERE coa.status = 'active' AND ag.nature = 'expense'
                        ORDER BY coa.account_code")->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Budget Monitoring</h1>
        <p class="text-gray-600">Allocated vs Spent vs Available — real-time GL actuals</p>
    </div>
    <div class="flex gap-2">
        <a href="../budgets/index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back</a>
        <a href="variance.php" class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700 transition"><i class="fas fa-chart-bar mr-2"></i>Variance Analysis</a>
        <?php if (hasPermission('budget.report') && $budget_id): ?>
        <form method="POST" action="snapshot.php" class="inline" onsubmit="return confirm('Generate a monitoring snapshot for the selected budget?');">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="budget_id" value="<?= (int)$budget_id ?>">
            <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 transition"><i class="fas fa-camera mr-2"></i>Snapshot</button>
        </form>
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
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
            <select name="month" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Months</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>><?= budget_month_name($m) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
            <select name="department_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= (int)$d['id'] ?>" <?= $dept_id == $d['id'] ? 'selected' : '' ?>><?= sanitize($d['department_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
            <select name="project_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach ($projects as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= $proj_id == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['project_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-56">
            <label class="block text-sm font-medium text-gray-700 mb-1">Account</label>
            <select name="account_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach ($accounts as $a): ?>
                <option value="<?= (int)$a['id'] ?>" <?= $acct_id == $a['id'] ? 'selected' : '' ?>><?= sanitize($a['account_code']) ?> - <?= sanitize($a['account_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-search"></i></button>
        <?php if ($budget_id || $month || $dept_id || $proj_id || $acct_id || $fy_id): ?>
        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-5">
        <div class="text-xs text-gray-500 uppercase">Allocated</div>
        <div class="text-xl font-bold font-mono text-gray-900"><?= formatMoney($totals['allocated']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="text-xs text-gray-500 uppercase">Spent (GL)</div>
        <div class="text-xl font-bold font-mono text-gray-900"><?= formatMoney($totals['spent']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="text-xs text-gray-500 uppercase">Available</div>
        <div class="text-xl font-bold font-mono text-emerald-600"><?= formatMoney($totals['available']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="text-xs text-gray-500 uppercase">Utilization</div>
        <div class="text-xl font-bold font-mono
            <?= $totals['pct'] >= 100 ? 'text-red-600' : ($totals['pct'] >= 80 ? 'text-amber-600' : 'text-emerald-600') ?>">
            <?= $totals['pct'] ?>%
        </div>
    </div>
</div>

<!-- Grid -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Budget</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Version</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dept / Project</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Month</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Allocated</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Spent</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Available</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">% Used</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($enriched as $l): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm">
                        <span class="font-mono text-gray-900"><?= sanitize($l['budget_code']) ?></span>
                        <div class="text-gray-600 text-xs"><?= sanitize($l['budget_name']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($l['version_label']) ?></td>
                    <td class="px-4 py-3 text-sm">
                        <span class="font-mono text-gray-900"><?= sanitize($l['account_code']) ?></span>
                        <div class="text-gray-600 text-xs"><?= sanitize($l['account_name']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?= sanitize($l['department_name'] ?? '—') ?>
                        <?php if ($l['project_name']): ?>
                        / <?= sanitize($l['project_name']) ?>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-center text-gray-600"><?= sanitize(budget_month_name($l['month'])) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney((float)$l['budget_amount']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-700"><?= formatMoney($l['spent']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-emerald-600"><?= formatMoney($l['available']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block px-2 py-1 text-xs rounded font-medium
                            <?= budget_variance_badge($l['pct']) ?>"
                            title="<?= budget_variance_label($l['pct']) ?>">
                            <?= $l['pct'] ?>%
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block w-3 h-3 rounded-full
                            <?= $l['pct'] >= 100 ? 'bg-red-500' : ($l['pct'] >= 80 ? 'bg-yellow-500' : 'bg-green-500') ?>"
                            title="<?= budget_variance_label($l['pct']) ?>"></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($enriched)): ?>
                <tr><td colspan="10" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-chart-line text-4xl mb-3 text-gray-300"></i>
                    <p>No budget lines match the filters</p>
                </td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot class="bg-gray-50 font-medium">
                <tr>
                    <td colspan="5" class="px-4 py-3 text-right text-sm text-gray-700">Totals</td>
                    <td class="px-4 py-3 text-right font-mono text-gray-900"><?= formatMoney($totals['allocated']) ?></td>
                    <td class="px-4 py-3 text-right font-mono text-gray-900"><?= formatMoney($totals['spent']) ?></td>
                    <td class="px-4 py-3 text-right font-mono text-emerald-700"><?= formatMoney($totals['available']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block px-2 py-1 text-xs rounded font-medium <?= budget_variance_badge($totals['pct']) ?>">
                            <?= $totals['pct'] ?>%
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block w-3 h-3 rounded-full
                            <?= $totals['pct'] >= 100 ? 'bg-red-500' : ($totals['pct'] >= 80 ? 'bg-yellow-500' : 'bg-green-500') ?>"></span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="mt-4"><?= paginationLinks($pagination, '?') ?></div>
<?php require_once '../../../includes/footer.php'; ?>