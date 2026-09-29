<?php
$pageTitle = 'Expense Dashboard';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('expense_dashboard.view')) { die('Access Denied'); }

$db = getDB();
$currentMonth = date('Y-m');
$currentYear = date('Y');

// KPI Cards
$todayTotal = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM company_expenses WHERE DATE(expense_date) = CURDATE() AND status NOT IN ('cancelled','draft')")->fetchColumn();
$monthExpenses = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM company_expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = '$currentMonth' AND status NOT IN ('cancelled','draft')")->fetchColumn();
$monthClaims = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM employee_expense_claims WHERE DATE_FORMAT(expense_date, '%Y-%m') = '$currentMonth' AND status NOT IN ('cancelled','draft')")->fetchColumn();
$pendingApproval = $db->query("SELECT COUNT(*) FROM company_expenses WHERE status = 'pending_approval'")->fetchColumn();
$pendingManager = $db->query("SELECT COUNT(*) FROM employee_expense_claims WHERE status = 'pending_manager'")->fetchColumn();
$pendingFinance = $db->query("SELECT COUNT(*) FROM employee_expense_claims WHERE status = 'pending_finance'")->fetchColumn();
$pendingReimbursements = $db->query("SELECT COUNT(*) FROM employee_expense_claims WHERE status = 'approved'")->fetchColumn();
$yearExpenses = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM company_expenses WHERE YEAR(expense_date) = $currentYear AND status NOT IN ('cancelled','draft')")->fetchColumn();
$yearClaims = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM employee_expense_claims WHERE YEAR(expense_date) = $currentYear AND status NOT IN ('cancelled','draft')")->fetchColumn();

// Top expense categories this month
$topCategories = $db->query("
    SELECT ec.name, COALESCE(SUM(ce.total_amount), 0) AS total
    FROM expense_categories ec
    LEFT JOIN company_expenses ce ON ce.category_id = ec.id AND DATE_FORMAT(ce.expense_date, '%Y-%m') = '$currentMonth' AND ce.status NOT IN ('cancelled')
    GROUP BY ec.id, ec.name
    HAVING total > 0
    ORDER BY total DESC
    LIMIT 10
")->fetchAll();

// Department-wise expense
$deptExpenses = $db->query("
    SELECT d.department_name, COALESCE(SUM(ce.total_amount), 0) AS total
    FROM departments d
    LEFT JOIN company_expenses ce ON ce.department_id = d.id AND DATE_FORMAT(ce.expense_date, '%Y-%m') = '$currentMonth' AND ce.status NOT IN ('cancelled')
    GROUP BY d.id, d.department_name
    HAVING total > 0
    ORDER BY total DESC
    LIMIT 10
")->fetchAll();

// Monthly trend (last 6 months)
$monthlyTrend = $db->query("
    SELECT DATE_FORMAT(expense_date, '%Y-%m') AS month,
           COALESCE(SUM(total_amount), 0) AS total
    FROM company_expenses
    WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
      AND status NOT IN ('cancelled','draft')
    GROUP BY DATE_FORMAT(expense_date, '%Y-%m')
    ORDER BY month ASC
")->fetchAll();

// Continuous 6-month window (fill months without expenses with 0)
$expMonthMap = [];
foreach ($monthlyTrend as $mt) { $expMonthMap[$mt['month']] = (float)$mt['total']; }
$expTrendLabels = [];
$expTrendTotals = [];
for ($i = 5; $i >= 0; $i--) {
    $k = date('Y-m', strtotime("-$i months"));
    $expTrendLabels[] = date('M', strtotime($k . '-01'));
    $expTrendTotals[] = $expMonthMap[$k] ?? 0;
}

// Recent expenses
$recentExpenses = $db->query("
    SELECT ce.*, ec.name AS category_name
    FROM company_expenses ce
    LEFT JOIN expense_categories ec ON ce.category_id = ec.id
    ORDER BY ce.created_at DESC LIMIT 5
")->fetchAll();

// Recent claims
$recentClaims = $db->query("
    SELECT ec.*, e.first_name, e.last_name, cat.name AS category_name
    FROM employee_expense_claims ec
    LEFT JOIN employees e ON ec.employee_id = e.id
    LEFT JOIN expense_categories cat ON ec.category_id = cat.id
    ORDER BY ec.created_at DESC LIMIT 5
")->fetchAll();
?>
<div class="mb-6">
    <div class="finance-hero">
        <div class="finance-hero-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706, #b45309);">
            <i class="fas fa-receipt"></i>
        </div>
        <div class="finance-hero-text">
            <h1>Expense Dashboard</h1>
            <p>Real-time overview of company expenses and employee claims</p>
        </div>
    </div>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Today's Expenses</p>
                <p class="text-xl font-bold text-gray-800 mt-1"><?= formatMoney((float)$todayTotal) ?></p>
            </div>
            <div class="bg-blue-100 p-3 rounded-full"><i class="fas fa-calendar-day text-blue-600"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">This Month</p>
                <p class="text-xl font-bold text-gray-800 mt-1"><?= formatMoney((float)$monthExpenses + (float)$monthClaims) ?></p>
            </div>
            <div class="bg-indigo-100 p-3 rounded-full"><i class="fas fa-calendar-alt text-indigo-600"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Pending Approvals</p>
                <p class="text-xl font-bold text-yellow-600 mt-1"><?= (int)$pendingApproval + (int)$pendingManager + (int)$pendingFinance ?></p>
            </div>
            <div class="bg-yellow-100 p-3 rounded-full"><i class="fas fa-clock text-yellow-600"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Awaiting Reimbursement</p>
                <p class="text-xl font-bold text-purple-600 mt-1"><?= (int)$pendingReimbursements ?></p>
            </div>
            <div class="bg-purple-100 p-3 rounded-full"><i class="fas fa-hand-holding-usd text-purple-600"></i></div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Monthly Breakdown -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-sm font-semibold text-gray-800 mb-4">Month Breakdown</h3>
        <div id="monthBreakdownChart"></div>
        <div class="mt-3 pt-3 border-t">
            <div class="flex justify-between text-sm font-bold"><span class="text-gray-800">Year to Date</span><span class="font-mono"><?= formatMoney((float)$yearExpenses + (float)$yearClaims) ?></span></div>
        </div>
    </div>

    <!-- Top Categories -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-sm font-semibold text-gray-800 mb-4">Top Expense Categories (This Month)</h3>
        <div id="topCatChart"></div>
    </div>

    <!-- Department Expenses -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-sm font-semibold text-gray-800 mb-4">Department Expenses (This Month)</h3>
        <div id="deptExpChart"></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Recent Company Expenses -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b flex justify-between items-center">
            <h3 class="font-semibold text-gray-800">Recent Company Expenses</h3>
            <a href="../company_expenses/index.php" class="text-blue-600 text-sm hover:underline">View All</a>
        </div>
        <div class="divide-y divide-gray-200">
            <?php if (empty($recentExpenses)): ?>
            <div class="p-6 text-center text-gray-500"><p>No expenses recorded yet</p></div>
            <?php else: ?>
            <?php foreach ($recentExpenses as $re): ?>
            <div class="px-4 py-3 flex justify-between items-center hover:bg-gray-50">
                <div>
                    <p class="text-sm font-medium"><a href="../company_expenses/view.php?id=<?= $re['id'] ?>" class="text-blue-600 hover:underline"><?= sanitize($re['expense_number']) ?></a></p>
                    <p class="text-xs text-gray-500"><?= sanitize($re['category_name'] ?? '--') ?> · <?= date('M d', strtotime($re['expense_date'])) ?></p>
                </div>
                <span class="text-sm font-mono font-medium"><?= formatMoney((float)$re['total_amount']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Claims -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b flex justify-between items-center">
            <h3 class="font-semibold text-gray-800">Recent Claims</h3>
            <a href="../employee_claims/index.php" class="text-blue-600 text-sm hover:underline">View All</a>
        </div>
        <div class="divide-y divide-gray-200">
            <?php if (empty($recentClaims)): ?>
            <div class="p-6 text-center text-gray-500"><p>No claims submitted yet</p></div>
            <?php else: ?>
            <?php foreach ($recentClaims as $rc): ?>
            <div class="px-4 py-3 flex justify-between items-center hover:bg-gray-50">
                <div>
                    <p class="text-sm font-medium"><a href="../employee_claims/view.php?id=<?= $rc['id'] ?>" class="text-blue-600 hover:underline"><?= sanitize($rc['claim_number']) ?></a></p>
                    <p class="text-xs text-gray-500"><?= sanitize($rc['first_name'] . ' ' . $rc['last_name']) ?> · <?= sanitize($rc['category_name'] ?? '--') ?></p>
                </div>
                <span class="text-sm font-mono font-medium"><?= formatMoney((float)$rc['total_amount']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Monthly Trend -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-sm font-semibold text-gray-800 mb-4">Expense Trend (Last 6 Months)</h3>
    <div id="expenseTrendChart"></div>
</div>

<style>
.finance-hero { display: flex; align-items: center; gap: 1rem; padding: 0 0 0.25rem; }
.finance-hero-icon { width: 3.25rem; height: 3.25rem; min-width: 3.25rem; border-radius: 0.875rem; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.375rem; position: relative; }
.finance-hero-icon::after { content: ''; position: absolute; inset: 0; border-radius: inherit; background: linear-gradient(135deg, rgba(255,255,255,0.20) 0%, transparent 60%); pointer-events: none; }
.finance-hero-text h1 { font-size: 1.5rem; font-weight: 700; color: var(--text-primary, #1e293b); line-height: 1.2; }
.finance-hero-text p { font-size: 0.875rem; color: var(--text-muted, #94a3b8); margin-top: 0.125rem; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const render = window.renderCarbonChart;
    const money = v => Number(v).toLocaleString();

    // Month Breakdown → donut (Company vs Claims)
    const mbEl = document.getElementById('monthBreakdownChart');
    if (mbEl && render) {
        render(mbEl, 'doughnut', {
            labels: ['Company Expenses', 'Employee Claims'],
            datasets: [{ data: [<?= (float)$monthExpenses ?>, <?= (float)$monthClaims ?>], backgroundColor: ['#2563eb', '#10b981'] }]
        }, { legend: { position: 'bottom' } });
    }

    // Top Categories → donut (USD tooltips)
    const tcEl = document.getElementById('topCatChart');
    if (tcEl && render) {
        const labels = <?= json_encode(array_column($topCategories, 'name')) ?>;
        const data   = <?= json_encode(array_column($topCategories, 'total')) ?>;
        render(tcEl, 'doughnut', {
            labels: labels.length ? labels : ['No data'],
            datasets: [{ data: data.length ? data : [1], backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4', '#ec4899', '#14b8a6', '#f97316', '#6366f1'] }]
        }, { legend: { position: 'bottom' } });
    }

    // Department Expenses → horizontal bar
    const deptEl = document.getElementById('deptExpChart');
    if (deptEl && render) {
        const labels = <?= json_encode(array_column($deptExpenses, 'department_name')) ?>;
        const data   = <?= json_encode(array_column($deptExpenses, 'total')) ?>;
        render(deptEl, 'horizontal', {
            labels: labels.length ? labels : ['No data'],
            datasets: [{ label: 'Spent', data: data.length ? data : [0], backgroundColor: 'rgba(99,102,241,0.75)' }]
        }, { legend: { position: 'bottom' } });
    }

    // Expense Trend → line (continuous 6-month window)
    const trendEl = document.getElementById('expenseTrendChart');
    if (trendEl && render) {
        render(trendEl, 'line', {
            labels: <?= json_encode($expTrendLabels) ?>,
            datasets: [{ label: 'Spent', data: <?= json_encode($expTrendTotals) ?>, backgroundColor: 'rgba(59,130,246,0.15)', borderColor: '#3b82f6' }]
        }, {
            axes: { left: { ticks: { formatter: v => money(v) } } }
        });
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>
