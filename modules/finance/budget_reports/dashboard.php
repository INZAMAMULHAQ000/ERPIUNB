<?php
$pageTitle = 'Budget Dashboard';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../helpers/budget_helpers.php';
require_once '../helpers/finance_helpers.php';

if (!hasPermission('budget.view')) { die('Access Denied'); }

$db = getDB();
$fy = getActiveFinancialYear($db);
$fyId = $fy['id'] ?? 0;

// Active budgets and their current (active/latest) version lines
$budgets = $db->query("SELECT * FROM budgets WHERE status NOT IN ('closed','rejected') ORDER BY budget_code")->fetchAll();

$totals = ['allocated' => 0.0, 'spent' => 0.0, 'committed' => 0.0, 'available' => 0.0, 'variance' => 0.0];
$deptMap = [];
$projMap = [];
$monthly = array_fill(1, 12, ['spent' => 0.0, 'budget' => 0.0]);

$budgetSummaries = [];
foreach ($budgets as $b) {
    $version = budget_active_version($db, $b['id']);
    if (!$version) continue;

    $bFy = [
        'id'         => $b['financial_year_id'],
        'start_date' => null,
        'end_date'   => null,
    ];
    $fyRow = $db->prepare("SELECT start_date, end_date FROM financial_years WHERE id = ?");
    $fyRow->execute([$b['financial_year_id']]);
    if ($f = $fyRow->fetch()) { $bFy['start_date'] = $f['start_date']; $bFy['end_date'] = $f['end_date']; }

    $lines = budget_lines_for_version($db, $version['id'], $bFy);
    $bTotals = budget_lines_totals($lines);
    $budgetSummaries[] = [
        'id'          => (int)$b['id'],
        'budget_code' => $b['budget_code'],
        'budget_name' => $b['budget_name'],
        'version'     => $version['version_label'],
        'allocated'   => $bTotals['allocated'],
        'spent'       => $bTotals['spent'],
        'available'   => $bTotals['available'],
        'pct'         => $bTotals['pct'],
    ];

    $totals['allocated']  += $bTotals['allocated'];
    $totals['spent']      += $bTotals['spent'];
    $totals['committed']  += $bTotals['committed'];
    $totals['available']  += $bTotals['available'];
    $totals['variance']   += $bTotals['variance'];

    foreach ($lines as $l) {
        $m = (int)$l['month'];
        if ($m >= 1 && $m <= 12) {
            $monthly[$m]['spent']  += $l['spent'];
            $monthly[$m]['budget'] += (float)$l['budget_amount'];
        }
        if (!empty($l['department_id'])) {
            $k = $l['department_id'];
            if (!isset($deptMap[$k])) $deptMap[$k] = ['name' => $l['department_name'] ?? '—', 'allocated' => 0.0, 'spent' => 0.0];
            $deptMap[$k]['allocated'] += (float)$l['budget_amount'];
            $deptMap[$k]['spent']     += $l['spent'];
        }
        if (!empty($l['project_id'])) {
            $k = $l['project_id'];
            if (!isset($projMap[$k])) $projMap[$k] = ['name' => $l['project_name'] ?? '—', 'allocated' => 0.0, 'spent' => 0.0];
            $projMap[$k]['allocated'] += (float)$l['budget_amount'];
            $projMap[$k]['spent']     += $l['spent'];
        }
    }
}

$totals['pct'] = $totals['allocated'] > 0 ? round($totals['spent'] / $totals['allocated'] * 100, 2) : 0.0;

// Departments / Projects over budget (>=100% used)
$deptsOver = array_values(array_filter($deptMap, fn($d) => $d['allocated'] > 0 && $d['spent'] >= $d['allocated']));
$projsOver = array_values(array_filter($projMap, fn($p) => $p['allocated'] > 0 && $p['spent'] >= $p['allocated']));
usort($deptsOver, fn($a, $b) => $b['spent'] - $a['spent']);
usort($projsOver, fn($a, $b) => $b['spent'] - $a['spent']);

// Top spending categories from company expenses within the active FY
$topCategories = [];
if ($fy) {
    $stmt = $db->prepare("SELECT ce.category_id, COALESCE(ec.name, 'Uncategorized') AS name, COALESCE(SUM(ce.total_amount), 0) AS total
                          FROM company_expenses ce
                          LEFT JOIN expense_categories ec ON ec.id = ce.category_id
                          WHERE ce.status NOT IN ('cancelled','draft')
                            AND ce.expense_date BETWEEN ? AND ?
                          GROUP BY ce.category_id, ec.name
                          ORDER BY total DESC
                          LIMIT 6");
    $stmt->execute([$fy['start_date'], $fy['end_date']]);
    $topCategories = $stmt->fetchAll();
}

// Chart data
$monthLabels = []; $monthBudget = []; $monthSpent = [];
for ($m = 1; $m <= 12; $m++) {
    $monthLabels[] = budget_month_name($m);
    $monthBudget[] = round($monthly[$m]['budget'], 2);
    $monthSpent[]  = round($monthly[$m]['spent'], 2);
}

$jsLabels = json_encode($monthLabels);
$jsBudget = json_encode($monthBudget);
$jsSpent  = json_encode($monthSpent);
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Budget Dashboard</h1>
        <p class="text-gray-600"><?= $fy ? 'Fiscal Year: ' . sanitize($fy['year_name']) : 'No active financial year' ?></p>
    </div>
    <div class="flex gap-2">
        <a href="../budgets/index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Budgets</a>
        <a href="index.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-file-alt mr-2"></i>Reports</a>
    </div>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div class="text-xs text-gray-500 uppercase">Total Budget</div>
            <i class="fas fa-coins text-blue-500"></i>
        </div>
        <div class="text-2xl font-bold font-mono text-gray-900 mt-2"><?= formatMoney($totals['allocated']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div class="text-xs text-gray-500 uppercase">Actual Spend</div>
            <i class="fas fa-chart-line text-red-500"></i>
        </div>
        <div class="text-2xl font-bold font-mono text-red-600 mt-2"><?= formatMoney($totals['spent']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div class="text-xs text-gray-500 uppercase">Remaining Budget</div>
            <i class="fas fa-piggy-bank text-emerald-500"></i>
        </div>
        <div class="text-2xl font-bold font-mono text-emerald-600 mt-2"><?= formatMoney($totals['available']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div class="text-xs text-gray-500 uppercase">Budget Utilization</div>
            <i class="fas fa-percent text-amber-500"></i>
        </div>
        <div class="text-2xl font-bold font-mono <?= $totals['pct'] >= 100 ? 'text-red-600' : ($totals['pct'] >= 80 ? 'text-amber-600' : 'text-emerald-600') ?> mt-2"><?= $totals['pct'] ?>%</div>
    </div>
</div>

<!-- Alerts: Departments / Projects over budget -->
<?php if ($deptsOver || $projsOver): ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <?php if ($deptsOver): ?>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-4 py-3 border-b bg-red-50 flex items-center gap-2">
            <i class="fas fa-exclamation-triangle text-red-500"></i>
            <h3 class="font-semibold text-red-800">Departments Over Budget</h3>
        </div>
        <div class="divide-y divide-gray-200">
            <?php foreach ($deptsOver as $d): ?>
            <div class="px-4 py-3 flex justify-between items-center">
                <span class="text-sm text-gray-800"><?= sanitize($d['name']) ?></span>
                <span class="text-sm font-mono text-red-600"><?= formatMoney($d['spent']) ?> / <?= formatMoney($d['allocated']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($projsOver): ?>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-4 py-3 border-b bg-red-50 flex items-center gap-2">
            <i class="fas fa-exclamation-triangle text-red-500"></i>
            <h3 class="font-semibold text-red-800">Projects Over Budget</h3>
        </div>
        <div class="divide-y divide-gray-200">
            <?php foreach ($projsOver as $p): ?>
            <div class="px-4 py-3 flex justify-between items-center">
                <span class="text-sm text-gray-800"><?= sanitize($p['name']) ?></span>
                <span class="text-sm font-mono text-red-600"><?= formatMoney($p['spent']) ?> / <?= formatMoney($p['allocated']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Monthly Spend Trend -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="font-semibold text-gray-800 mb-4">Monthly Budget vs Spend Trend</h3>
    <div id="trendChart"></div>
</div>

<!-- Top Spending Categories -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="px-4 py-3 border-b bg-gray-50">
        <h3 class="font-semibold text-gray-800">Top Spending Categories (<?= $fy ? sanitize($fy['year_name']) : '' ?>)</h3>
    </div>
    <?php if ($topCategories): ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Spent</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($topCategories as $tc): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-800"><?= sanitize($tc['name']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney((float)$tc['total']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="p-8 text-center text-gray-500">
        <i class="fas fa-chart-pie text-4xl mb-3 text-gray-300"></i>
        <p>No company expenses recorded for this period</p>
    </div>
    <?php endif; ?>
</div>

<!-- Budget Summary -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-4 py-3 border-b bg-gray-50 flex justify-between items-center">
        <h3 class="font-semibold text-gray-800">Budget Summary</h3>
        <a href="../budgets/index.php" class="text-blue-600 hover:text-blue-900 text-sm"><i class="fas fa-arrow-right mr-1"></i>Manage Budgets</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Budget</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Version</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Allocated</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Spent</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Available</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">% Used</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($budgetSummaries as $bs): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm">
                        <span class="font-mono text-gray-900"><?= sanitize($bs['budget_code']) ?></span>
                        <div class="text-gray-600 text-xs"><?= sanitize($bs['budget_name']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= sanitize($bs['version']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-900"><?= formatMoney($bs['allocated']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-700"><?= formatMoney($bs['spent']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-emerald-600"><?= formatMoney($bs['available']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block px-2 py-1 text-xs rounded font-medium <?= budget_variance_badge($bs['pct']) ?>"><?= $bs['pct'] ?>%</span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($budgetSummaries)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-coins text-4xl mb-3 text-gray-300"></i>
                    <p>No budgets created yet</p>
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const render = window.renderCarbonChart;
    const el = document.getElementById('trendChart');
    if (el && render) {
        render(el, 'bar', {
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
                    label: 'Spent',
                    data: <?= $jsSpent ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.7)',
                    borderColor: 'rgb(239, 68, 68)',
                    borderWidth: 1
                }
            ]
        }, {
            legend: { position: 'top' },
            axes: {
                left: { ticks: { formatter: val => '$' + Number(val).toLocaleString() } }
            }
        });
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>