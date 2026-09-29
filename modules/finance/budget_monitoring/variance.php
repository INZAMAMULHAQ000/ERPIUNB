<?php
$pageTitle = 'Variance Analysis';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../helpers/budget_helpers.php';

if (!hasPermission('budget.view')) { die('Access Denied'); }

$db = getDB();

$budget_id  = (int)($_GET['budget_id'] ?? 0);
$version_id = (int)($_GET['version_id'] ?? 0);
$fy_id      = (int)($_GET['financial_year_id'] ?? 0);

$budgets = $db->query("SELECT b.*, fy.year_name FROM budgets b JOIN financial_years fy ON fy.id = b.financial_year_id WHERE b.status NOT IN ('closed') ORDER BY b.budget_code")->fetchAll();
$fys     = $db->query("SELECT * FROM financial_years ORDER BY start_date DESC")->fetchAll();

if (!$fy_id && !$budget_id && !empty($budgets)) { $fy_id = $budgets[0]['financial_year_id']; }
if (!$fy_id && !empty($fys)) { $fy_id = $fys[0]['id']; }

$w = " WHERE b.status NOT IN ('closed') ";
$p = [];
if ($budget_id)  { $w .= " AND bl.budget_id = ?"; $p[] = $budget_id; }
if ($version_id) { $w .= " AND bl.budget_version_id = ?"; $p[] = $version_id; }
if ($fy_id)      { $w .= " AND b.financial_year_id = ?"; $p[] = $fy_id; }

$fyCache = [];
$monthlyLabels = []; $monthlyBudget = []; $monthlyActual = []; $monthlyVariance = [];
for ($m = 1; $m <= 12; $m++) {
    $monthlyLabels[] = budget_month_name($m);
    $monthlyBudget[]   = 0;
    $monthlyActual[]   = 0;
    $monthlyVariance[] = 0;
}

$stmt = $db->prepare("SELECT bl.*, b.financial_year_id, fy.start_date, fy.end_date
                      FROM budget_lines bl
                      JOIN budgets b ON b.id = bl.budget_id
                      JOIN financial_years fy ON fy.id = b.financial_year_id
                      $w");
$stmt->execute($p);
$lines = $stmt->fetchAll();

foreach ($lines as $l) {
    $fyCacheKey = $l['financial_year_id'];
    if (!isset($fyCache[$fyCacheKey])) {
        $fyCache[$fyCacheKey] = [
            'id'         => $l['financial_year_id'],
            'start_date' => $l['start_date'],
            'end_date'   => $l['end_date'],
        ];
    }
    $fy = $fyCache[$fyCacheKey];
    $m = (int)$l['month'];
    if ($m < 1 || $m > 12) $m = 1;
    $idx = $m - 1;
    list($from, $to) = budget_month_range($fy, $l['month']);
    $spent = budget_line_actual($db, $l, $fy, $from, $to);
    $amt = (float)$l['budget_amount'];
    $monthlyBudget[$idx]   += $amt;
    $monthlyActual[$idx]   += $spent;
    $monthlyVariance[$idx] += $amt - $spent;
}

$detailRows = [];
$acctSql = "
    SELECT bl.chart_of_accounts_id, acct.account_code, acct.account_name, SUM(bl.budget_amount) AS budget_total
    FROM budget_lines bl
    JOIN budgets b ON b.id = bl.budget_id
    JOIN chart_of_accounts acct ON acct.id = bl.chart_of_accounts_id
    $w
    GROUP BY bl.chart_of_accounts_id, acct.account_code, acct.account_name
    ORDER BY acct.account_code
";
$acctStmt = $db->prepare($acctSql);
$acctStmt->execute($p);
$acctGroups = $acctStmt->fetchAll();

foreach ($acctGroups as $ag) {
    $spent = 0;
    foreach ($fyCache as $fy) {
        list($from, $to) = budget_month_range($fy, null);
        $spent += budget_dimension_actual($db, $fy, $from, $to, null, null, $ag['chart_of_accounts_id']);
    }
    $budgetAmt = (float)$ag['budget_total'];
    $var = $budgetAmt - $spent;
    $pct = $budgetAmt > 0 ? round($spent / $budgetAmt * 100, 2) : 0;
    $detailRows[] = [
        'account_code' => $ag['account_code'],
        'account_name' => $ag['account_name'],
        'budget'       => $budgetAmt,
        'actual'       => $spent,
        'variance'     => $var,
        'pct'          => $pct,
    ];
}

$deptRows = $projRows = [];
if ($budget_id) {
    $deptSql = "
        SELECT bl.department_id, d.department_name, SUM(bl.budget_amount) AS budget_total
        FROM budget_lines bl
        JOIN budgets b ON b.id = bl.budget_id
        LEFT JOIN departments d ON d.id = bl.department_id
        $w AND bl.department_id IS NOT NULL
        GROUP BY bl.department_id, d.department_name
        ORDER BY budget_total DESC
    ";
    $dStmt = $db->prepare($deptSql);
    $dStmt->execute($p);
    foreach ($dStmt->fetchAll() as $dr) {
        $spent = 0;
        foreach ($fyCache as $fy) {
            list($from, $to) = budget_month_range($fy, null);
            $spent += budget_dimension_actual($db, $fy, $from, $to, $dr['department_id'], null);
        }
        $deptRows[] = array_merge($dr, ['actual' => $spent, 'variance' => $dr['budget_total'] - $spent, 'pct' => $dr['budget_total'] > 0 ? round($spent / $dr['budget_total'] * 100, 2) : 0]);
    }

    $projSql = "
        SELECT bl.project_id, p.project_name, SUM(bl.budget_amount) AS budget_total
        FROM budget_lines bl
        JOIN budgets b ON b.id = bl.budget_id
        LEFT JOIN projects p ON p.id = bl.project_id
        $w AND bl.project_id IS NOT NULL
        GROUP BY bl.project_id, p.project_name
        ORDER BY budget_total DESC
    ";
    $prStmt = $db->prepare($projSql);
    $prStmt->execute($p);
    foreach ($prStmt->fetchAll() as $pr) {
        $spent = 0;
        foreach ($fyCache as $fy) {
            list($from, $to) = budget_month_range($fy, null);
            $spent += budget_dimension_actual($db, $fy, $from, $to, null, $pr['project_id']);
        }
        $projRows[] = array_merge($pr, ['actual' => $spent, 'variance' => $pr['budget_total'] - $spent, 'pct' => $pr['budget_total'] > 0 ? round($spent / $pr['budget_total'] * 100, 2) : 0]);
    }
}

$jsLabels  = json_encode($monthlyLabels);
$jsBudget  = json_encode(array_map(fn($v) => round($v, 2), $monthlyBudget));
$jsActual  = json_encode(array_map(fn($v) => round($v, 2), $monthlyActual));
$jsVariance= json_encode(array_map(fn($v) => round($v, 2), $monthlyVariance));
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Variance Analysis</h1>
        <p class="text-gray-600">Budget vs Actual — monthly trend & account breakdown</p>
    </div>
    <a href="index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Monitoring</a>
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
            <select name="version_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">Active Version</option>
                <?php if ($budget_id):
                    $vers = $db->prepare("SELECT id, version_label FROM budget_versions WHERE budget_id = ?");
                    $vers->execute([$budget_id]);
                    foreach ($vers->fetchAll() as $v): ?>
                    <option value="<?= (int)$v['id'] ?>" <?= $version_id == $v['id'] ? 'selected' : '' ?>><?= sanitize($v['version_label']) ?></option>
                <?php endforeach; endif; ?>
            </select>
        </div>
        <div class="w-44">
            <label class="block text-sm font-medium text-gray-700 mb-1">Fiscal Year</label>
            <select name="financial_year_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach ($fys as $fy): ?>
                <option value="<?= (int)$fy['id'] ?>" <?= $fy_id == $fy['id'] ? 'selected' : '' ?>><?= sanitize($fy['year_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-search"></i></button>
    </form>
</div>

<!-- Monthly Trend Chart -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="font-semibold text-gray-800 mb-4">Monthly Budget vs Actual Trend</h3>
    <div id="trendChart"></div>
</div>

<!-- Account Variance Table -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-4 py-3 border-b bg-gray-50">
        <h3 class="font-semibold text-gray-800">Variance by Account</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Budget</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actual</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Variance</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">% Used</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($detailRows as $d): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm">
                        <span class="font-mono text-gray-900"><?= sanitize($d['account_code']) ?></span>
                        <div class="text-gray-600 text-xs"><?= sanitize($d['account_name']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney($d['budget']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-700"><?= formatMoney($d['actual']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono
                        <?= $d['variance'] >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
                        <?= $d['variance'] >= 0 ? '+' : '' ?><?= formatMoney($d['variance']) ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block px-2 py-1 text-xs rounded font-medium
                            <?= $d['pct'] >= 100 ? 'bg-red-100 text-red-800' : ($d['pct'] >= 80 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') ?>">
                            <?= $d['pct'] ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($detailRows)): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No data</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Department Variance -->
<?php if (!empty($deptRows)): ?>
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-4 py-3 border-b bg-gray-50">
        <h3 class="font-semibold text-gray-800">Variance by Department</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Budget</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actual</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Variance</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">% Used</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($deptRows as $d): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($d['department_name'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney($d['budget_total']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-700"><?= formatMoney($d['actual']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono
                        <?= $d['variance'] >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
                        <?= $d['variance'] >= 0 ? '+' : '' ?><?= formatMoney($d['variance']) ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block px-2 py-1 text-xs rounded font-medium
                            <?= $d['pct'] >= 100 ? 'bg-red-100 text-red-800' : ($d['pct'] >= 80 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') ?>">
                            <?= $d['pct'] ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Project Variance -->
<?php if (!empty($projRows)): ?>
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-4 py-3 border-b bg-gray-50">
        <h3 class="font-semibold text-gray-800">Variance by Project</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Budget</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actual</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Variance</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">% Used</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($projRows as $p): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($p['project_name'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney($p['budget_total']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-700"><?= formatMoney($p['actual']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono
                        <?= $p['variance'] >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
                        <?= $p['variance'] >= 0 ? '+' : '' ?><?= formatMoney($p['variance']) ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block px-2 py-1 text-xs rounded font-medium
                            <?= $p['pct'] >= 100 ? 'bg-red-100 text-red-800' : ($p['pct'] >= 80 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') ?>">
                            <?= $p['pct'] ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const render = window.renderCarbonChart;
    const el = document.getElementById('trendChart');
    if (el && render) {
        render(el, 'combo', {
            labels: <?= $jsLabels ?>,
            datasets: [
                {
                    label: 'Budget',
                    data: <?= $jsBudget ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                },
                {
                    label: 'Actual (GL)',
                    data: <?= $jsActual ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.7)',
                    borderColor: 'rgb(239, 68, 68)',
                    borderWidth: 1
                },
                {
                    label: 'Variance',
                    data: <?= $jsVariance ?>,
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.2)',
                    borderWidth: 2,
                    pointRadius: 4
                }
            ]
        }, {
            chartTypes: ['bar', 'bar', 'line'],
            legend: { position: 'top' },
            points: { radius: 4 },
            axes: {
                left: {
                    title: 'Amount (USD)',
                    ticks: { formatter: val => '$' + Number(val).toLocaleString() }
                }
            }
        });
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>