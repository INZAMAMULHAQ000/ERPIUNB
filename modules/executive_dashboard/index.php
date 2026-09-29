<?php
$pageTitle = 'Executive Dashboard';
$dashboardKey = 'executive';
require_once '../../includes/header.php';

if (!hasPermission('analytics.view')) {
    die('<div class="p-8 text-center text-red-600 font-semibold">Access Denied</div>');
}

$db = getDB();
logAudit($_SESSION['user_id'], 'viewed_executive_dashboard', 'analytics');

// ── KPI Data ──────────────────────────────────────────────────
$totalRevenue   = $db->query("SELECT COALESCE(SUM(paid_amount),0) FROM invoices WHERE YEAR(invoice_date)=YEAR(CURDATE())")->fetchColumn();
$lastMonthRev   = $db->query("SELECT COALESCE(SUM(paid_amount),0) FROM invoices WHERE DATE_FORMAT(invoice_date,'%Y-%m')=DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 1 MONTH),'%Y-%m')")->fetchColumn();
$thisMonthRev   = $db->query("SELECT COALESCE(SUM(paid_amount),0) FROM invoices WHERE DATE_FORMAT(invoice_date,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')")->fetchColumn();
$revGrowth      = $lastMonthRev > 0 ? round(($thisMonthRev - $lastMonthRev) / $lastMonthRev * 100, 1) : 0;

$totalEmployees = $db->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn();
$newHires       = $db->query("SELECT COUNT(*) FROM employees WHERE joining_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();

$activeProjects = $db->query("SELECT COUNT(*) FROM projects WHERE status='active'")->fetchColumn();
$overdueProjects= $db->query("SELECT COUNT(*) FROM projects WHERE end_date < CURDATE() AND status='active'")->fetchColumn();

$pendingInvoices= $db->query("SELECT COUNT(*) FROM invoices WHERE status IN ('sent','overdue')")->fetchColumn();
$overdueInvoices= $db->query("SELECT COUNT(*) FROM invoices WHERE status='overdue'")->fetchColumn();
$pendingAmount  = $db->query("SELECT COALESCE(SUM(total_amount-paid_amount),0) FROM invoices WHERE status IN ('sent','overdue')")->fetchColumn();

$openTickets    = $db->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open','in_progress')")->fetchColumn();
$criticalTickets= $db->query("SELECT COUNT(*) FROM tickets WHERE priority='critical' AND status NOT IN ('resolved','closed')")->fetchColumn();

$totalExpenses  = $db->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE YEAR(expense_date)=YEAR(CURDATE()) AND status='approved'")->fetchColumn();
$netProfit      = $totalRevenue - $totalExpenses;
$profitMargin   = $totalRevenue > 0 ? round($netProfit / $totalRevenue * 100, 1) : 0;

// ── Charts ────────────────────────────────────────────────────
$monthlyRevenue = $db->query("
    SELECT DATE_FORMAT(invoice_date,'%b %Y') AS label, SUM(paid_amount) AS value
    FROM invoices WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(invoice_date,'%Y-%m') ORDER BY invoice_date ASC
")->fetchAll();

$projectStatus = $db->query("SELECT status, COUNT(*) AS cnt FROM projects GROUP BY status")->fetchAll();

$topClients = $db->query("
    SELECT c.company_name AS name, SUM(i.paid_amount) AS revenue
    FROM clients c JOIN invoices i ON i.client_id=c.id
    WHERE YEAR(i.invoice_date)=YEAR(CURDATE())
    GROUP BY c.id ORDER BY revenue DESC LIMIT 5
")->fetchAll();

$recentActivity = $db->query("
    SELECT al.action, al.module, u.name AS user_name, al.created_at
    FROM audit_logs al LEFT JOIN users u ON al.user_id=u.id
    ORDER BY al.created_at DESC LIMIT 8
")->fetchAll();

$pendingLeaves = $db->query("
    SELECT CONCAT(e.first_name,' ',e.last_name) AS name, lt.leave_name AS leave_type,
           lr.from_date AS start_date, lr.to_date AS end_date
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id=e.id
    LEFT JOIN leave_types lt ON lr.leave_type_id=lt.id
    WHERE lr.status='pending' ORDER BY lr.created_at DESC LIMIT 5
")->fetchAll();
?>

<div id="dashboard-old-content">
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Executive Dashboard</h1>
        <p class="text-gray-600">Company performance overview — <?= date('F Y') ?></p>
    </div>
    <div class="flex gap-2">
        <a href="../analytics/index.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm transition">
            <i class="fas fa-chart-bar mr-1"></i>Full Analytics
        </a>
        <a href="../reports/index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-sm transition">
            <i class="fas fa-file-alt mr-1"></i>Reports
        </a>
    </div>
</div>

<!-- Sub-Module Navigation -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <a href="<?= url('modules/analytics/index.php') ?>" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-blue-500 flex items-center justify-center"><i class="fas fa-chart-bar text-white"></i></div>
        <div><div class="font-semibold text-sm">Analytics</div><div class="text-xs text-gray-500">Deep insights</div></div>
    </a>
    <a href="<?= url('modules/reports/index.php') ?>" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-green-500 flex items-center justify-center"><i class="fas fa-file-alt text-white"></i></div>
        <div><div class="font-semibold text-sm">Reports</div><div class="text-xs text-gray-500">Generate reports</div></div>
    </a>
    <a href="<?= url('modules/scheduled_reports/index.php') ?>" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-amber-500 flex items-center justify-center"><i class="fas fa-calendar-alt text-white"></i></div>
        <div><div class="font-semibold text-sm">Scheduled</div><div class="text-xs text-gray-500">Auto reports</div></div>
    </a>
    <a href="<?= url('modules/activity_logs/index.php') ?>" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-violet-500 flex items-center justify-center"><i class="fas fa-history text-white"></i></div>
        <div><div class="font-semibold text-sm">Activity Logs</div><div class="text-xs text-gray-500">Audit trail</div></div>
    </a>
</div>

<!-- Top KPI Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <!-- Revenue -->
    <div class="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-lg shadow p-5">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-green-100 text-xs uppercase font-medium">YTD Revenue</p>
                <p class="text-2xl font-bold mt-1"><?= formatMoney($totalRevenue, 0) ?></p>
                <p class="text-green-100 text-xs mt-1">
                    <?= $revGrowth >= 0 ? '▲' : '▼' ?> <?= abs($revGrowth) ?>% vs last month
                </p>
            </div>
            <i class="fas fa-dollar-sign text-green-200 text-2xl"></i>
        </div>
    </div>
    <!-- Employees -->
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-lg shadow p-5">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-blue-100 text-xs uppercase font-medium">Employees</p>
                <p class="text-2xl font-bold mt-1"><?= $totalEmployees ?></p>
                <p class="text-blue-100 text-xs mt-1">+<?= $newHires ?> new this month</p>
            </div>
            <i class="fas fa-users text-blue-200 text-2xl"></i>
        </div>
    </div>
    <!-- Net Profit -->
    <div class="bg-gradient-to-br from-<?= $netProfit >= 0 ? 'indigo' : 'red' ?>-500 to-<?= $netProfit >= 0 ? 'indigo' : 'red' ?>-600 text-white rounded-lg shadow p-5">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-indigo-100 text-xs uppercase font-medium">Net Profit</p>
                <p class="text-2xl font-bold mt-1"><?= formatMoney($netProfit, 0) ?></p>
                <p class="text-indigo-100 text-xs mt-1"><?= $profitMargin ?>% margin</p>
            </div>
            <i class="fas fa-chart-line text-indigo-200 text-2xl"></i>
        </div>
    </div>
    <!-- Open Tickets -->
    <div class="bg-gradient-to-br from-<?= $criticalTickets > 0 ? 'red' : 'yellow' ?>-500 to-<?= $criticalTickets > 0 ? 'red' : 'yellow' ?>-600 text-white rounded-lg shadow p-5">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-yellow-100 text-xs uppercase font-medium">Open Tickets</p>
                <p class="text-2xl font-bold mt-1"><?= $openTickets ?></p>
                <p class="text-yellow-100 text-xs mt-1"><?= $criticalTickets ?> critical</p>
            </div>
            <i class="fas fa-ticket-alt text-yellow-200 text-2xl"></i>
        </div>
    </div>
</div>

<!-- Secondary KPIs -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <p class="text-xs text-gray-500 uppercase font-medium">Active Projects</p>
        <p class="text-2xl font-bold text-gray-800 mt-1"><?= $activeProjects ?></p>
        <?php if ($overdueProjects > 0): ?>
        <p class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i><?= $overdueProjects ?> overdue</p>
        <?php endif; ?>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <p class="text-xs text-gray-500 uppercase font-medium">Pending Invoices</p>
        <p class="text-2xl font-bold text-gray-800 mt-1"><?= $pendingInvoices ?></p>
        <p class="text-xs text-gray-500 mt-1"><?= formatMoney($pendingAmount, 0) ?> outstanding</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <p class="text-xs text-gray-500 uppercase font-medium">YTD Expenses</p>
        <p class="text-2xl font-bold text-gray-800 mt-1"><?= formatMoney($totalExpenses, 0) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <p class="text-xs text-gray-500 uppercase font-medium">Overdue Invoices</p>
        <p class="text-2xl font-bold <?= $overdueInvoices > 0 ? 'text-red-600' : 'text-gray-800' ?> mt-1"><?= $overdueInvoices ?></p>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <!-- Revenue Trend -->
    <div class="md:col-span-2 bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold text-gray-700 mb-4">Monthly Revenue (12 months)</h3>
        <div id="revTrendChart"></div>
    </div>
    <!-- Project Status -->
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold text-gray-700 mb-4">Project Status</h3>
        <div id="projStatusChart"></div>
    </div>
</div>

<!-- Bottom Row -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- Top Clients -->
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold text-gray-700 mb-4">Top Clients (YTD)</h3>
        <?php if (empty($topClients)): ?>
        <p class="text-gray-500 text-sm">No data available</p>
        <?php else: ?>
        <?php $maxRev = max(array_column($topClients, 'revenue')); ?>
        <div class="space-y-3">
            <?php foreach ($topClients as $client): ?>
            <div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-700 truncate"><?= sanitize($client['name']) ?></span>
                    <span class="font-medium text-gray-800 ml-2"><?= formatMoney($client['revenue'], 0) ?></span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2">
                    <div class="bg-blue-500 h-2 rounded-full" style="width:<?= $maxRev > 0 ? round($client['revenue']/$maxRev*100) : 0 ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Pending Leave Requests -->
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold text-gray-700 mb-4">Pending Leave Requests</h3>
        <?php if (empty($pendingLeaves)): ?>
        <p class="text-gray-500 text-sm">No pending requests</p>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($pendingLeaves as $lr): ?>
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-yellow-400 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                    <?= strtoupper(substr($lr['name'], 0, 1)) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate"><?= sanitize($lr['name']) ?></p>
                    <p class="text-xs text-gray-500"><?= sanitize($lr['leave_type'] ?? 'Leave') ?> · <?= date('M d', strtotime($lr['start_date'])) ?></p>
                </div>
                <a href="../leave_requests/index.php" class="text-xs text-blue-600 hover:underline">Review</a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold text-gray-700 mb-4">Recent Activity</h3>
        <div class="space-y-3">
            <?php foreach ($recentActivity as $act): ?>
            <div class="flex items-start gap-2">
                <div class="w-2 h-2 rounded-full bg-blue-400 mt-1.5 flex-shrink-0"></div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-gray-700 truncate">
                        <span class="font-medium"><?= sanitize($act['user_name'] ?? 'System') ?></span>
                        — <?= sanitize(str_replace('_', ' ', $act['action'])) ?>
                    </p>
                    <p class="text-xs text-gray-400"><?= date('M d, H:i', strtotime($act['created_at'])) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <a href="../activity_logs/index.php" class="text-xs text-blue-600 hover:underline mt-3 block">View all activity →</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const render = window.renderCarbonChart;

    // Revenue Trend (line, 12 months)
    const revEl = document.getElementById('revTrendChart');
    if (revEl && render) {
        render(revEl, 'line', {
            labels: <?= json_encode(array_column($monthlyRevenue, 'label')) ?>,
            datasets: [{
                label: 'Revenue',
                data: <?= json_encode(array_map('floatval', array_column($monthlyRevenue, 'value'))) ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16,185,129,0.1)',
                fill: true, tension: 0.4, pointRadius: 4
            }]
        }, {
            legend: { enabled: false },
            curve: 'curve',
            points: { radius: 4 },
            getAreaOpacity: function () { return 0.2; }
        });
    }

    // Project Status (doughnut)
    const projEl = document.getElementById('projStatusChart');
    if (projEl && render) {
        render(projEl, 'doughnut', {
            labels: <?= json_encode(array_column($projectStatus, 'status')) ?>,
            datasets: [{
                data: <?= json_encode(array_map('intval', array_column($projectStatus, 'cnt'))) ?>,
                backgroundColor: ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6']
            }]
        }, { legend: { position: 'bottom' } });
    }
});
</script>

</div><!-- /dashboard-old-content -->
<?php
require_once '../../includes/dashboard_template.php';
require_once '../../includes/footer.php'; ?>
