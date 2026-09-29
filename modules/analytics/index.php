<?php
$pageTitle = 'Analytics Dashboard';
$dashboardKey = 'analytics';
require_once '../../includes/header.php';

if (!hasPermission('analytics.view')) {
    die('<div class="p-8 text-center text-red-600 font-semibold">Access Denied</div>');
}

$db = getDB();
logAudit($_SESSION['user_id'], 'viewed_analytics', 'analytics');

$period = $_GET['period'] ?? '30';
$dateFrom = date('Y-m-d', strtotime("-{$period} days"));
$dateTo   = date('Y-m-d');

// ── HR Analytics ──────────────────────────────────────────────
$totalEmp   = $db->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn();
$newEmp     = $db->prepare("SELECT COUNT(*) FROM employees WHERE joining_date >= ?");
$newEmp->execute([$dateFrom]);
$newEmp = $newEmp->fetchColumn();
$avgAttPct  = $db->prepare("SELECT ROUND(AVG(sub.pct),1) FROM (SELECT ROUND(SUM(status='present')/COUNT(*)*100,1) AS pct FROM attendance WHERE attendance_date >= ? GROUP BY employee_id) sub");
$avgAttPct->execute([$dateFrom]);
$avgAttPct = $avgAttPct->fetchColumn() ?? 0;
$openLeaves = $db->query("SELECT COUNT(*) FROM leave_requests WHERE status='pending'")->fetchColumn();

// ── Project Analytics ─────────────────────────────────────────
$activeProjects = $db->query("SELECT COUNT(*) FROM projects WHERE status='active'")->fetchColumn();
$delayedTasks   = $db->query("SELECT COUNT(*) FROM tasks WHERE due_date < CURDATE() AND status NOT IN ('completed','cancelled')")->fetchColumn();
$completedTasks = $db->prepare("SELECT COUNT(*) FROM tasks WHERE status='completed' AND updated_at >= ?");
$completedTasks->execute([$dateFrom]);
$completedTasks = $completedTasks->fetchColumn();

// ── CRM Analytics ─────────────────────────────────────────────
$totalLeads     = $db->prepare("SELECT COUNT(*) FROM leads WHERE created_at >= ?");
$totalLeads->execute([$dateFrom]);
$totalLeads = $totalLeads->fetchColumn();
$convertedLeads = $db->prepare("SELECT COUNT(*) FROM leads WHERE status='converted' AND created_at >= ?");
$convertedLeads->execute([$dateFrom]);
$convertedLeads = $convertedLeads->fetchColumn();
$convRate       = $totalLeads > 0 ? round($convertedLeads / $totalLeads * 100, 1) : 0;
$totalClients   = $db->query("SELECT COUNT(*) FROM clients WHERE status='active'")->fetchColumn();

// ── Finance Analytics ─────────────────────────────────────────
$revenue  = $db->prepare("SELECT COALESCE(SUM(paid_amount),0) FROM invoices WHERE invoice_date >= ?");
$revenue->execute([$dateFrom]);
$revenue = $revenue->fetchColumn();
$expenses = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date >= ? AND status='approved'");
$expenses->execute([$dateFrom]);
$expenses = $expenses->fetchColumn();
$profit   = $revenue - $expenses;
$pendingInv = $db->query("SELECT COUNT(*) FROM invoices WHERE status IN ('sent','overdue')")->fetchColumn();

// ── Helpdesk Analytics ────────────────────────────────────────
$openTickets     = $db->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open','in_progress')")->fetchColumn();
$resolvedTickets = $db->prepare("SELECT COUNT(*) FROM tickets WHERE status='resolved' AND resolved_at >= ?");
$resolvedTickets->execute([$dateFrom]);
$resolvedTickets = $resolvedTickets->fetchColumn();
$totalTickets    = $db->prepare("SELECT COUNT(*) FROM tickets WHERE created_at >= ?");
$totalTickets->execute([$dateFrom]);
$totalTickets = $totalTickets->fetchColumn();
$resolutionRate  = $totalTickets > 0 ? round($resolvedTickets / $totalTickets * 100, 1) : 0;

// ── Chart Data ────────────────────────────────────────────────
// Monthly revenue (last 6 months)
$revenueChart = $db->query("
    SELECT DATE_FORMAT(invoice_date,'%b') AS month, SUM(paid_amount) AS revenue
    FROM invoices WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(invoice_date,'%Y-%m') ORDER BY invoice_date ASC
")->fetchAll();

// Monthly expenses (last 6 months)
$expenseChart = $db->query("
    SELECT DATE_FORMAT(expense_date,'%b') AS month, SUM(amount) AS expenses
    FROM expenses WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND status='approved'
    GROUP BY DATE_FORMAT(expense_date,'%Y-%m') ORDER BY expense_date ASC
")->fetchAll();

// Ticket status breakdown
$ticketStatus = $db->query("
    SELECT status, COUNT(*) AS cnt FROM tickets GROUP BY status
")->fetchAll();

// Lead source breakdown
$leadSources = $db->query("
    SELECT source, COUNT(*) AS cnt FROM leads GROUP BY source ORDER BY cnt DESC LIMIT 6
")->fetchAll();

// Department headcount
$deptHeadcount = $db->query("
    SELECT d.department_name, COUNT(e.id) AS cnt
    FROM departments d LEFT JOIN employees e ON e.department_id=d.id AND e.status='active'
    GROUP BY d.id ORDER BY cnt DESC LIMIT 8
")->fetchAll();

// Attendance trend (last 7 days)
$attTrend = $db->query("
    SELECT attendance_date, ROUND(SUM(status='present')/COUNT(*)*100,1) AS pct
    FROM attendance WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY attendance_date ORDER BY attendance_date ASC
")->fetchAll();

// Attendance heatmap: dept × day for the last 30 days
$attHeat = $db->query("
    SELECT d.department_name AS dept, a.attendance_date AS day,
           ROUND(SUM(a.status='present')/COUNT(*)*100,1) AS pct
    FROM attendance a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY d.id, d.department_name, a.attendance_date
")->fetchAll();

$attHeatDays = [];
$attHeatByDept = [];
for ($i = 29; $i >= 0; $i--) {
    $attHeatDays[] = date('Y-m-d', strtotime("-$i days"));
}
foreach ($attHeat as $ah) {
    if (!$ah['dept']) {
        continue;
    }
    if (!isset($attHeatByDept[$ah['dept']])) {
        $attHeatByDept[$ah['dept']] = [];
    }
    $attHeatByDept[$ah['dept']][$ah['day']] = (float)$ah['pct'];
}
?>

<div id="dashboard-old-content">
    <div class="mb-6 flex justify-between items-center flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Analytics Dashboard</h1>
            <p class="text-gray-600">Business intelligence overview</p>
        </div>

        <form method="GET" class="flex items-center gap-2" style="flex: 0 0 auto;">
            <label class="text-sm text-gray-600 whitespace-nowrap">
                Period:
            </label>

            <select name="period"
                onchange="this.form.submit()"
                style="width: 180px; min-width: 180px; max-width: 180px;"
                class="px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
                <option value="7" <?= $period == '7' ? 'selected' : '' ?>>Last 7 days</option>
                <option value="30" <?= $period == '30' ? 'selected' : '' ?>>Last 30 days</option>
                <option value="90" <?= $period == '90' ? 'selected' : '' ?>>Last 90 days</option>
                <option value="365" <?= $period == '365' ? 'selected' : '' ?>>Last Year</option>
            </select>
        </form>
    </div>

    <!-- HR Analytics -->
    <div class="mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">HR Analytics</div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <?php
        $hrCards = [
            ['label' => 'Active Employees',  'value' => $totalEmp,    'icon' => 'fa-users',          'color' => 'blue'],
            ['label' => 'New Hires',         'value' => $newEmp,      'icon' => 'fa-user-plus',       'color' => 'green'],
            ['label' => 'Avg Attendance %',  'value' => $avgAttPct . '%', 'icon' => 'fa-clock',          'color' => 'yellow'],
            ['label' => 'Pending Leaves',    'value' => $openLeaves,  'icon' => 'fa-calendar-minus',  'color' => 'red'],
        ];
        foreach ($hrCards as $card):
            $colors = ['blue' => 'bg-blue-500', 'green' => 'bg-green-500', 'yellow' => 'bg-yellow-500', 'red' => 'bg-red-500'];
        ?>
            <div class="bg-white rounded-lg shadow p-5 flex items-center gap-4">
                <div class="<?= $colors[$card['color']] ?> text-white rounded-full w-12 h-12 flex items-center justify-center flex-shrink-0">
                    <i class="fas <?= $card['icon'] ?>"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?= $card['value'] ?></p>
                    <p class="text-xs text-gray-500"><?= $card['label'] ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Project Analytics -->
    <div class="mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">Project Analytics</div>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
        <?php
        $projCards = [
            ['label' => 'Active Projects',   'value' => $activeProjects, 'icon' => 'fa-project-diagram', 'color' => 'purple'],
            ['label' => 'Delayed Tasks',     'value' => $delayedTasks,   'icon' => 'fa-exclamation-circle', 'color' => 'red'],
            ['label' => 'Tasks Completed',   'value' => $completedTasks, 'icon' => 'fa-check-circle',   'color' => 'green'],
        ];
        foreach ($projCards as $card):
            $colors = ['purple' => 'bg-purple-500', 'red' => 'bg-red-500', 'green' => 'bg-green-500'];
        ?>
            <div class="bg-white rounded-lg shadow p-5 flex items-center gap-4">
                <div class="<?= $colors[$card['color']] ?> text-white rounded-full w-12 h-12 flex items-center justify-center flex-shrink-0">
                    <i class="fas <?= $card['icon'] ?>"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?= $card['value'] ?></p>
                    <p class="text-xs text-gray-500"><?= $card['label'] ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Finance & CRM Analytics -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- Finance -->
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <i class="fas fa-dollar-sign text-green-500"></i> Finance Overview
            </h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Revenue</span>
                    <span class="font-bold text-green-600"><?= formatMoney($revenue) ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Expenses</span>
                    <span class="font-bold text-red-600"><?= formatMoney($expenses) ?></span>
                </div>
                <div class="border-t pt-2 flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700">Net Profit</span>
                    <span class="font-bold text-lg <?= $profit >= 0 ? 'text-green-700' : 'text-red-700' ?>">
                        <?= formatMoney($profit) ?>
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Pending Invoices</span>
                    <span class="font-bold text-yellow-600"><?= $pendingInv ?></span>
                </div>
            </div>
        </div>
        <!-- CRM -->
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <i class="fas fa-handshake text-blue-500"></i> CRM Overview
            </h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">New Leads</span>
                    <span class="font-bold text-gray-800"><?= $totalLeads ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Converted</span>
                    <span class="font-bold text-green-600"><?= $convertedLeads ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Conversion Rate</span>
                    <span class="font-bold text-blue-600"><?= $convRate ?>%</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Active Clients</span>
                    <span class="font-bold text-gray-800"><?= $totalClients ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- Revenue vs Expenses Chart -->
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-700 mb-4">Revenue vs Expenses (6 months)</h3>
            <div id="revenueChart"></div>
        </div>
        <!-- Department Headcount -->
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-700 mb-4">Department Headcount</h3>
            <div id="deptChart"></div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Ticket Status Pie -->
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-700 mb-4">Ticket Status</h3>
            <div id="ticketChart"></div>
        </div>
        <!-- Lead Sources -->
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-700 mb-4">Lead Sources</h3>
            <div id="leadChart"></div>
        </div>
        <!-- Attendance Trend -->
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-700 mb-4">Attendance Trend (7 days)</h3>
            <div id="attChart"></div>
        </div>
    </div>

    <!-- Attendance Heatmap -->
    <div class="bg-white rounded-lg shadow p-5 mb-6">
        <h3 class="font-semibold text-gray-700 mb-4">Attendance by Department (30 days)</h3>
        <div id="attHeatChart"></div>
    </div>

    <!-- Helpdesk Analytics -->
    <div class="mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">Helpdesk Analytics</div>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
        <?php
        $hdCards = [
            ['label' => 'Open Tickets',      'value' => $openTickets,    'icon' => 'fa-ticket-alt',    'color' => 'red'],
            ['label' => 'Resolved (period)', 'value' => $resolvedTickets, 'icon' => 'fa-check-double',  'color' => 'green'],
            ['label' => 'Resolution Rate',   'value' => $resolutionRate . '%', 'icon' => 'fa-tachometer-alt', 'color' => 'blue'],
        ];
        foreach ($hdCards as $card):
            $colors = ['red' => 'bg-red-500', 'green' => 'bg-green-500', 'blue' => 'bg-blue-500'];
        ?>
            <div class="bg-white rounded-lg shadow p-5 flex items-center gap-4">
                <div class="<?= $colors[$card['color']] ?> text-white rounded-full w-12 h-12 flex items-center justify-center flex-shrink-0">
                    <i class="fas <?= $card['icon'] ?>"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?= $card['value'] ?></p>
                    <p class="text-xs text-gray-500"><?= $card['label'] ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const render = window.renderCarbonChart;

            // Revenue vs Expenses → grouped bar (2 series)
            const revEl = document.getElementById('revenueChart');
            if (revEl && render) {
                const revLabels = <?= json_encode(array_column($revenueChart, 'month')) ?>;
                render(revEl, 'bar', {
                    labels: revLabels.length ? revLabels : ['No data'],
                    datasets: [{
                            label: 'Revenue',
                            data: <?= json_encode(array_column($revenueChart, 'revenue')) ?>,
                            backgroundColor: 'rgba(34,197,94,0.7)'
                        },
                        {
                            label: 'Expenses',
                            data: <?= json_encode(array_column($expenseChart, 'expenses')) ?>,
                            backgroundColor: 'rgba(239,68,68,0.7)'
                        }
                    ]
                }, {
                    legend: {
                        position: 'bottom'
                    }
                });
            }

            // Department Headcount → horizontal bar
            const deptEl = document.getElementById('deptChart');
            if (deptEl && render) {
                const labels = <?= json_encode(array_column($deptHeadcount, 'department_name')) ?>;
                render(deptEl, 'horizontal', {
                    labels: labels.length ? labels : ['No data'],
                    datasets: [{
                        label: 'Employees',
                        data: <?= json_encode(array_column($deptHeadcount, 'cnt')) ?>,
                        backgroundColor: 'rgba(99,102,241,0.7)'
                    }]
                }, {
                    legend: {
                        enabled: false
                    }
                });
            }

            // Ticket Status → doughnut
            const ticketEl = document.getElementById('ticketChart');
            if (ticketEl && render) {
                const labels = <?= json_encode(array_column($ticketStatus, 'status')) ?>;
                const data = <?= json_encode(array_column($ticketStatus, 'cnt')) ?>;
                render(ticketEl, 'doughnut', {
                    labels: labels.length ? labels : ['No data'],
                    datasets: [{
                        data: data.length ? data : [1],
                        backgroundColor: ['#3b82f6', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6']
                    }]
                }, {
                    legend: {
                        position: 'bottom'
                    }
                });
            }

            // Lead Sources → pie
            const leadEl = document.getElementById('leadChart');
            if (leadEl && render) {
                const labels = <?= json_encode(array_column($leadSources, 'source')) ?>;
                const data = <?= json_encode(array_column($leadSources, 'cnt')) ?>;
                render(leadEl, 'pie', {
                    labels: labels.length ? labels : ['No data'],
                    datasets: [{
                        data: data.length ? data : [1],
                        backgroundColor: ['#6366f1', '#f59e0b', '#10b981', '#ef4444', '#3b82f6', '#ec4899']
                    }]
                }, {
                    legend: {
                        position: 'bottom'
                    }
                });
            }

            // Attendance Trend → line (percent, 0-100 axis)
            const attEl = document.getElementById('attChart');
            if (attEl && render) {
                const labels = <?= json_encode(array_column($attTrend, 'attendance_date')) ?>;
                render(attEl, 'line', {
                    labels: labels.length ? labels : ['No data'],
                    datasets: [{
                        label: 'Attendance %',
                        data: <?= json_encode(array_column($attTrend, 'pct')) ?>,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59,130,246,0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                }, {
                    legend: {
                        enabled: false
                    },
                    curve: 'curve',
                    axes: {
                        left: {
                            domain: [0, 100]
                        }
                    },
                    getAreaOpacity: function() {
                        return 0.2;
                    }
                });
            }

            // Attendance Heatmap → dept × day (30-day window)
            const heatEl = document.getElementById('attHeatChart');
            if (heatEl && render) {
                const days = <?= json_encode($attHeatDays) ?>;
                const byDept = <?= json_encode($attHeatByDept) ?>;
                const depts = Object.keys(byDept);
                render(heatEl, 'heatmap', {
                    labels: days,
                    datasets: depts.map(d => ({
                        label: d,
                        data: days.map(day => ({
                            value: byDept[d][day] !== undefined ? byDept[d][day] : 0
                        }))
                    }))
                }, {
                    legend: {
                        position: 'bottom'
                    }
                });
            }
        });
    </script>

</div><!-- /dashboard-old-content -->
<?php
require_once '../../includes/dashboard_template.php';
require_once '../../includes/footer.php'; ?>