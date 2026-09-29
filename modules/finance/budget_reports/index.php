<?php
$pageTitle = 'Budget Reports';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../helpers/budget_helpers.php';
require_once '../helpers/finance_helpers.php';

if (!hasPermission('budget.report')) { die('Access Denied'); }

$db = getDB();

$report_type = $_GET['report_type'] ?? 'department';
$budget_id   = $_GET['budget_id'] ?? '';
$version_id  = $_GET['version_id'] ?? '';
$fy_id       = $_GET['financial_year_id'] ?? '';
$dept_id     = $_GET['department_id'] ?? '';
$proj_id     = $_GET['project_id'] ?? '';
$acct_id     = $_GET['account_id'] ?? '';
$month       = $_GET['month'] ?? '';

$budgets = $db->query("SELECT b.*, fy.year_name FROM budgets b JOIN financial_years fy ON fy.id = b.financial_year_id WHERE b.status NOT IN ('closed') ORDER BY b.budget_code")->fetchAll();
$fys     = $db->query("SELECT * FROM financial_years ORDER BY start_date DESC")->fetchAll();
$departments = $db->query("SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name")->fetchAll();
$projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();
$accounts = $db->query("SELECT coa.id, coa.account_code, coa.account_name
                        FROM chart_of_accounts coa
                        JOIN account_groups ag ON ag.id = coa.group_id
                        WHERE coa.status = 'active' AND ag.nature = 'expense'
                        ORDER BY coa.account_code")->fetchAll();

// Build line query with filters
$w = " WHERE 1=1 ";
$p = [];
if ($budget_id)  { $w .= " AND bl.budget_id = ?"; $p[] = (int)$budget_id; }
if ($version_id) { $w .= " AND bl.budget_version_id = ?"; $p[] = (int)$version_id; }
if ($fy_id)      { $w .= " AND b.financial_year_id = ?"; $p[] = (int)$fy_id; }
if ($dept_id)    { $w .= " AND bl.department_id = ?"; $p[] = (int)$dept_id; }
if ($proj_id)    { $w .= " AND bl.project_id = ?"; $p[] = (int)$proj_id; }
if ($acct_id)    { $w .= " AND bl.chart_of_accounts_id = ?"; $p[] = (int)$acct_id; }
if ($month !== '') { $w .= " AND bl.month = ?"; $p[] = (int)$month; }

$lineSql = "SELECT bl.*, b.budget_code, b.budget_name, b.financial_year_id, fy.start_date, fy.end_date,
                   v.version_label, acct.account_code, acct.account_name,
                   d.department_name, p.project_name
            FROM budget_lines bl
            JOIN budgets b ON b.id = bl.budget_id
            JOIN financial_years fy ON fy.id = b.financial_year_id
            JOIN budget_versions v ON v.id = bl.budget_version_id
            JOIN chart_of_accounts acct ON acct.id = bl.chart_of_accounts_id
            LEFT JOIN departments d ON d.id = bl.department_id
            LEFT JOIN projects p ON p.id = bl.project_id
            $w";
$stmt = $db->prepare($lineSql);
$stmt->execute($p);
$lines = $stmt->fetchAll();

// Enrich with actuals + FY cache
$fyCache = [];
foreach ($lines as &$l) {
    $k = $l['financial_year_id'];
    if (!isset($fyCache[$k])) {
        $fyCache[$k] = ['id' => $l['financial_year_id'], 'start_date' => $l['start_date'], 'end_date' => $l['end_date']];
    }
    $fy = $fyCache[$k];
    list($from, $to) = budget_month_range($fy, $l['month']);
    $l['spent'] = budget_line_actual($db, $l, $fy, $from, $to);
    $l['available'] = (float)$l['budget_amount'] - $l['spent'];
    $l['pct'] = (float)$l['budget_amount'] > 0 ? round($l['spent'] / (float)$l['budget_amount'] * 100, 2) : 0.0;
}
unset($l);

// Aggregate by report type
$reportTitle = '';
$reportHeaders = [];
$reportRows = [];
$grandTotals = ['allocated' => 0.0, 'spent' => 0.0, 'variance' => 0.0];

function varianceBadge($pct) {
    return budget_variance_badge($pct);
}

switch ($report_type) {
    case 'department':
        $reportTitle = 'Department Budget Report';
        $reportHeaders = ['Department', 'Allocated', 'Spent', 'Available', 'Variance', '% Used'];
        $groups = [];
        foreach ($lines as $l) {
            $k = $l['department_id'] ?? 0;
            if (!isset($groups[$k])) $groups[$k] = ['name' => $l['department_name'] ?? 'Unassigned', 'allocated' => 0.0, 'spent' => 0.0];
            $groups[$k]['allocated'] += (float)$l['budget_amount'];
            $groups[$k]['spent']     += $l['spent'];
        }
        uasort($groups, fn($a, $b) => $b['spent'] - $a['spent']);
        foreach ($groups as $g) {
            $var = $g['allocated'] - $g['spent'];
            $pct = $g['allocated'] > 0 ? round($g['spent'] / $g['allocated'] * 100, 2) : 0.0;
            $reportRows[] = [
                'name' => $g['name'],
                'allocated' => $g['allocated'], 'spent' => $g['spent'],
                'available' => max($g['allocated'] - $g['spent'], 0), 'variance' => $var, 'pct' => $pct,
            ];
            $grandTotals['allocated'] += $g['allocated']; $grandTotals['spent'] += $g['spent']; $grandTotals['variance'] += $var;
        }
        break;

    case 'project':
        $reportTitle = 'Project Budget Report';
        $reportHeaders = ['Project', 'Allocated', 'Spent', 'Available', 'Variance', '% Used'];
        $groups = [];
        foreach ($lines as $l) {
            $k = $l['project_id'] ?? 0;
            if (!isset($groups[$k])) $groups[$k] = ['name' => $l['project_name'] ?? 'Unassigned', 'allocated' => 0.0, 'spent' => 0.0];
            $groups[$k]['allocated'] += (float)$l['budget_amount'];
            $groups[$k]['spent']     += $l['spent'];
        }
        uasort($groups, fn($a, $b) => $b['spent'] - $a['spent']);
        foreach ($groups as $g) {
            $var = $g['allocated'] - $g['spent'];
            $pct = $g['allocated'] > 0 ? round($g['spent'] / $g['allocated'] * 100, 2) : 0.0;
            $reportRows[] = [
                'name' => $g['name'],
                'allocated' => $g['allocated'], 'spent' => $g['spent'],
                'available' => max($g['allocated'] - $g['spent'], 0), 'variance' => $var, 'pct' => $pct,
            ];
            $grandTotals['allocated'] += $g['allocated']; $grandTotals['spent'] += $g['spent']; $grandTotals['variance'] += $var;
        }
        break;

    case 'account':
        $reportTitle = 'Account Budget Report';
        $reportHeaders = ['Account', 'Allocated', 'Spent', 'Available', 'Variance', '% Used'];
        $groups = [];
        foreach ($lines as $l) {
            $k = $l['chart_of_accounts_id'];
            if (!isset($groups[$k])) $groups[$k] = ['name' => $l['account_code'] . ' - ' . $l['account_name'], 'allocated' => 0.0, 'spent' => 0.0];
            $groups[$k]['allocated'] += (float)$l['budget_amount'];
            $groups[$k]['spent']     += $l['spent'];
        }
        uasort($groups, fn($a, $b) => $b['spent'] - $a['spent']);
        foreach ($groups as $g) {
            $var = $g['allocated'] - $g['spent'];
            $pct = $g['allocated'] > 0 ? round($g['spent'] / $g['allocated'] * 100, 2) : 0.0;
            $reportRows[] = [
                'name' => $g['name'],
                'allocated' => $g['allocated'], 'spent' => $g['spent'],
                'available' => max($g['allocated'] - $g['spent'], 0), 'variance' => $var, 'pct' => $pct,
            ];
            $grandTotals['allocated'] += $g['allocated']; $grandTotals['spent'] += $g['spent']; $grandTotals['variance'] += $var;
        }
        break;

    case 'monthly':
        $reportTitle = 'Monthly Budget Report';
        $reportHeaders = ['Month', 'Allocated', 'Spent', 'Available', 'Variance', '% Used'];
        $monthly = array_fill(1, 12, ['allocated' => 0.0, 'spent' => 0.0]);
        foreach ($lines as $l) {
            $m = (int)$l['month'];
            if ($m < 1 || $m > 12) $m = 1;
            $monthly[$m]['allocated'] += (float)$l['budget_amount'];
            $monthly[$m]['spent']     += $l['spent'];
        }
        for ($m = 1; $m <= 12; $m++) {
            $g = $monthly[$m];
            $var = $g['allocated'] - $g['spent'];
            $pct = $g['allocated'] > 0 ? round($g['spent'] / $g['allocated'] * 100, 2) : 0.0;
            $reportRows[] = [
                'name' => budget_month_name($m),
                'allocated' => $g['allocated'], 'spent' => $g['spent'],
                'available' => max($g['allocated'] - $g['spent'], 0), 'variance' => $var, 'pct' => $pct,
            ];
            $grandTotals['allocated'] += $g['allocated']; $grandTotals['spent'] += $g['spent']; $grandTotals['variance'] += $var;
        }
        break;

    case 'annual':
        $reportTitle = 'Annual Budget Report';
        $reportHeaders = ['Budget', 'Version', 'Allocated', 'Spent', 'Available', '% Used'];
        $groups = [];
        foreach ($lines as $l) {
            $k = $l['budget_id'] . '|' . $l['budget_version_id'];
            if (!isset($groups[$k])) $groups[$k] = ['name' => $l['budget_code'] . ' — ' . $l['budget_name'], 'version' => $l['version_label'], 'allocated' => 0.0, 'spent' => 0.0];
            $groups[$k]['allocated'] += (float)$l['budget_amount'];
            $groups[$k]['spent']     += $l['spent'];
        }
        uasort($groups, fn($a, $b) => $b['spent'] - $a['spent']);
        foreach ($groups as $g) {
            $pct = $g['allocated'] > 0 ? round($g['spent'] / $g['allocated'] * 100, 2) : 0.0;
            $reportRows[] = [
                'name' => $g['name'],
                'allocated' => $g['allocated'], 'spent' => $g['spent'],
                'available' => max($g['allocated'] - $g['spent'], 0), 'pct' => $pct,
                'extra' => $g['version'],
            ];
            $grandTotals['allocated'] += $g['allocated']; $grandTotals['spent'] += $g['spent']; $grandTotals['variance'] += $g['allocated'] - $g['spent'];
        }
        break;

    case 'budget_vs_actual':
        $reportTitle = 'Budget vs Actual';
        $reportHeaders = ['Account', 'Department', 'Project', 'Allocated', 'Spent', 'Variance', '% Used'];
        foreach ($lines as $l) {
            $var = (float)$l['budget_amount'] - $l['spent'];
            $reportRows[] = [
                'name' => $l['account_code'] . ' - ' . $l['account_name'],
                'allocated' => (float)$l['budget_amount'], 'spent' => $l['spent'],
                'available' => $l['available'], 'variance' => $var, 'pct' => $l['pct'],
                'extra' => ($l['department_name'] ?? '—') . ' / ' . ($l['project_name'] ?? '—'),
            ];
            $grandTotals['allocated'] += (float)$l['budget_amount']; $grandTotals['spent'] += $l['spent']; $grandTotals['variance'] += $var;
        }
        break;

    case 'variance':
        $reportTitle = 'Variance Report';
        $reportHeaders = ['Account', 'Department', 'Project', 'Month', 'Allocated', 'Spent', 'Variance', '% Used'];
        foreach ($lines as $l) {
            $var = (float)$l['budget_amount'] - $l['spent'];
            $reportRows[] = [
                'name' => $l['account_code'] . ' - ' . $l['account_name'],
                'allocated' => (float)$l['budget_amount'], 'spent' => $l['spent'],
                'available' => $l['available'], 'variance' => $var, 'pct' => $l['pct'],
                'extra' => ($l['department_name'] ?? '—') . ' / ' . ($l['project_name'] ?? '—'),
                'month' => budget_month_name($l['month']),
            ];
            $grandTotals['allocated'] += (float)$l['budget_amount']; $grandTotals['spent'] += $l['spent']; $grandTotals['variance'] += $var;
        }
        break;

    default:
        $report_type = 'department';
}
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Budget Reports</h1>
        <p class="text-gray-600">Department, project, account, monthly &amp; variance reporting</p>
    </div>
    <div class="flex gap-2">
        <a href="dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 transition"><i class="fas fa-chart-pie mr-2"></i>Dashboard</a>
        <a href="../budgets/index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Budgets</a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
            <select name="report_type" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="department" <?= $report_type === 'department' ? 'selected' : '' ?>>Department Budget</option>
                <option value="project" <?= $report_type === 'project' ? 'selected' : '' ?>>Project Budget</option>
                <option value="account" <?= $report_type === 'account' ? 'selected' : '' ?>>Account Budget</option>
                <option value="monthly" <?= $report_type === 'monthly' ? 'selected' : '' ?>>Monthly Budget</option>
                <option value="annual" <?= $report_type === 'annual' ? 'selected' : '' ?>>Annual Budget</option>
                <option value="budget_vs_actual" <?= $report_type === 'budget_vs_actual' ? 'selected' : '' ?>>Budget vs Actual</option>
                <option value="variance" <?= $report_type === 'variance' ? 'selected' : '' ?>>Variance Report</option>
            </select>
        </div>
        <div class="w-50">
            <label class="block text-sm font-medium text-gray-700 mb-1">Budget</label>
            <select name="budget_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Budgets</option>
                <?php foreach ($budgets as $b): ?>
                <option value="<?= (int)$b['id'] ?>" <?= $budget_id == $b['id'] ? 'selected' : '' ?>><?= sanitize($b['budget_code']) ?> — <?= sanitize($b['budget_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-50">
            <label class="block text-sm font-medium text-gray-700 mb-1">Version</label>
            <select name="version_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">Active / Latest</option>
                <?php if ($budget_id):
                    $vers = $db->prepare("SELECT id, version_label FROM budget_versions WHERE budget_id = ?");
                    $vers->execute([(int)$budget_id]);
                    foreach ($vers->fetchAll() as $v): ?>
                    <option value="<?= (int)$v['id'] ?>" <?= $version_id == $v['id'] ? 'selected' : '' ?>><?= sanitize($v['version_label']) ?></option>
                <?php endforeach; endif; ?>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Financial Year</label>
            <select name="financial_year_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach ($fys as $fy): ?>
                <option value="<?= (int)$fy['id'] ?>" <?= $fy_id == $fy['id'] ? 'selected' : '' ?>><?= sanitize($fy['year_name']) ?></option>
                <?php endforeach; ?>
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
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Account</label>
            <select name="account_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach ($accounts as $a): ?>
                <option value="<?= (int)$a['id'] ?>" <?= $acct_id == $a['id'] ? 'selected' : '' ?>><?= sanitize($a['account_code']) ?> - <?= sanitize($a['account_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
            <select name="month" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>><?= budget_month_name($m) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-search mr-2"></i>Generate</button>
        <?php if ($budget_id || $version_id || $fy_id || $dept_id || $proj_id || $acct_id || $month !== ''): ?>
        <a href="index.php?report_type=<?= urlencode($report_type) ?>" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<!-- Report Results -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-4 py-3 border-b flex justify-between items-center">
        <h3 class="font-semibold text-gray-800"><?= sanitize($reportTitle) ?></h3>
        <span class="text-sm text-gray-500"><?= count($reportRows) ?> rows</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <?php foreach ($reportHeaders as $h): ?>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= sanitize($h) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($reportRows as $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-900"><?= sanitize($r['name']) ?></td>
                    <?php if ($report_type === 'annual'): ?>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($r['extra']) ?></td>
                    <?php elseif ($report_type === 'budget_vs_actual'): ?>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($r['extra']) ?></td>
                    <?php elseif ($report_type === 'variance'): ?>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($r['extra']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($r['month']) ?></td>
                    <?php endif; ?>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney($r['allocated']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-700"><?= formatMoney($r['spent']) ?></td>
                    <?php if ($report_type !== 'annual'): ?>
                    <td class="px-4 py-3 text-sm text-right font-mono text-emerald-600"><?= formatMoney($r['available']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono <?= $r['variance'] >= 0 ? 'text-emerald-600' : 'text-red-600' ?>"><?= $r['variance'] >= 0 ? '+' : '' ?><?= formatMoney($r['variance']) ?></td>
                    <?php endif; ?>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block px-2 py-1 text-xs rounded font-medium <?= varianceBadge($r['pct']) ?>" title="<?= budget_variance_label($r['pct']) ?>"><?= $r['pct'] ?>%</span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($reportRows)): ?>
                <tr><td colspan="<?= count($reportHeaders) ?>" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-file-alt text-4xl mb-3 text-gray-300"></i>
                    <p>No data found for the selected criteria</p>
                </td></tr>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($reportRows)): ?>
            <tfoot class="bg-gray-50 font-medium">
                <tr>
                    <td colspan="2" class="px-4 py-3 text-right text-sm text-gray-700">Totals</td>
                    <td class="px-4 py-3 text-right font-mono text-gray-900"><?= formatMoney($grandTotals['allocated']) ?></td>
                    <td class="px-4 py-3 text-right font-mono text-gray-900"><?= formatMoney($grandTotals['spent']) ?></td>
                    <td class="px-4 py-3 text-right font-mono text-emerald-700"><?= formatMoney(max($grandTotals['allocated'] - $grandTotals['spent'], 0)) ?></td>
                    <td class="px-4 py-3 text-right font-mono <?= $grandTotals['variance'] >= 0 ? 'text-emerald-700' : 'text-red-700' ?>"><?= $grandTotals['variance'] >= 0 ? '+' : '' ?><?= formatMoney($grandTotals['variance']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block px-2 py-1 text-xs rounded font-medium <?= varianceBadge($grandTotals['allocated'] > 0 ? round($grandTotals['spent'] / $grandTotals['allocated'] * 100, 2) : 0) ?>"><?= $grandTotals['allocated'] > 0 ? round($grandTotals['spent'] / $grandTotals['allocated'] * 100, 2) : 0 ?>%</span>
                    </td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>