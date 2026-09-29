<?php
$pageTitle = 'Dashboard';
$dashboardKey = 'main';
require_once 'includes/header.php';

$db = getDB();

// --- Stats ---
$userCount    = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$employeeCount = $db->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
$projectCount  = $db->query("SELECT COUNT(*) FROM projects WHERE status NOT IN ('completed','cancelled')")->fetchColumn();
$ticketCount   = $db->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open','in_progress')")->fetchColumn();
$invoiceCount  = $db->query("SELECT COUNT(*) FROM invoices WHERE status IN ('pending','overdue')")->fetchColumn();
$clientCount   = $db->query("SELECT COUNT(*) FROM clients")->fetchColumn();

// --- Module activity (last 7 days) ---
$moduleActivity = $db->query("
    SELECT module, COUNT(*) as cnt
    FROM audit_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY module
    ORDER BY cnt DESC
    LIMIT 6
")->fetchAll();

// --- Recent activity ---
$recentLogs = $db->query("
    SELECT al.*, u.name as user_name
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 8
")->fetchAll();

// --- Pending items ---
$pendingLeaves   = $db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();
$pendingExpenses = $db->query("SELECT COUNT(*) FROM expenses WHERE status = 'pending'")->fetchColumn();
$activeAssignments = $db->query("SELECT COUNT(*) FROM asset_assignments WHERE status = 'assigned'")->fetchColumn();
$todayAttendance = $db->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE()")->fetchColumn();

$maxModule = $moduleActivity ? max(array_column($moduleActivity, 'cnt')) : 1;

// --- Chart data: Ticket status ---
$ticketStatus = $db->query("SELECT status, COUNT(*) as cnt FROM tickets GROUP BY status ORDER BY cnt DESC")->fetchAll();

// --- Chart data: Project status ---
$projectStatus = $db->query("SELECT status, COUNT(*) as cnt FROM projects GROUP BY status ORDER BY cnt DESC")->fetchAll();

// --- Chart data: Monthly user registrations (last 6 months) ---
$monthlyUsers = $db->query("
    SELECT DATE_FORMAT(created_at, '%b') as label, COUNT(*) as cnt
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY MIN(created_at)
")->fetchAll();
$monthlyLabels = $monthlyUsers ? array_column($monthlyUsers, 'label') : [];
$monthlyCounts = $monthlyUsers ? array_column($monthlyUsers, 'cnt') : [];
?>

<div id="dashboard-old-content">
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Good <?= (int)date('H') < 12 ? 'Morning' : ((int)date('H') < 17 ? 'Afternoon' : 'Evening') ?>, <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>!</h1>
    <p class="text-gray-500 text-sm mt-1"><?= date('l, F j, Y') ?></p>
</div>

<!-- ===== Stats Cards ===== -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
    <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Users</span>
            <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center">
                <i class="fas fa-users text-indigo-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-slate-800"><?= $userCount ?></p>
        <div class="mt-2 h-1 w-full bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full bg-indigo-500 rounded-full" style="width: <?= min(100, $userCount * 10) ?>%"></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Employees</span>
            <div class="w-9 h-9 rounded-lg bg-emerald-50 flex items-center justify-center">
                <i class="fas fa-user-tie text-emerald-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-slate-800"><?= $employeeCount ?></p>
        <div class="mt-2 h-1 w-full bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full bg-emerald-500 rounded-full" style="width: <?= min(100, $employeeCount * 8) ?>%"></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Projects</span>
            <div class="w-9 h-9 rounded-lg bg-violet-50 flex items-center justify-center">
                <i class="fas fa-project-diagram text-violet-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-slate-800"><?= $projectCount ?></p>
        <div class="mt-2 h-1 w-full bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full bg-violet-500 rounded-full" style="width: <?= min(100, $projectCount * 15) ?>%"></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Tickets</span>
            <div class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center">
                <i class="fas fa-ticket-alt text-amber-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-slate-800"><?= $ticketCount ?></p>
        <div class="mt-2 h-1 w-full bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full bg-amber-500 rounded-full" style="width: <?= min(100, $ticketCount * 12) ?>%"></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Invoices</span>
            <div class="w-9 h-9 rounded-lg bg-rose-50 flex items-center justify-center">
                <i class="fas fa-file-invoice text-rose-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-slate-800"><?= $invoiceCount ?></p>
        <div class="mt-2 h-1 w-full bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full bg-rose-500 rounded-full" style="width: <?= min(100, $invoiceCount * 12) ?>%"></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Clients</span>
            <div class="w-9 h-9 rounded-lg bg-cyan-50 flex items-center justify-center">
                <i class="fas fa-building text-cyan-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-slate-800"><?= $clientCount ?></p>
        <div class="mt-2 h-1 w-full bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full bg-cyan-500 rounded-full" style="width: <?= min(100, $clientCount * 10) ?>%"></div>
        </div>
    </div>
</div>

<!-- ===== AI Insights Widget ===== -->
<?php
if (file_exists('modules/ai/bootstrap.php')) {
    require_once 'modules/ai/bootstrap.php';
    if (function_exists('hasPermission') && hasPermission('ai.insights.view')) {
        $aiUserId = $_SESSION['user_id'] ?? 0;
        if ($aiUserId && class_exists('AIInsightService')) {
            $aiCounts = AIInsightService::getUserInsightCounts($db, $aiUserId);
            $aiRecent = AIInsightService::getUserInsights($db, $aiUserId, ['limit' => 3]);
            $aiResolved = $db->prepare("SELECT COUNT(*) FROM ai_insight_records ir JOIN ai_insight_recipients rec ON ir.id = rec.insight_id WHERE rec.user_id = ? AND ir.status = 'resolved' AND ir.resolved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $aiResolved->execute([$aiUserId]);
            $aiResolvedCount = $aiResolved->fetchColumn();
?>
<div class="mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                <i class="fas fa-lightbulb text-yellow-500 text-xs"></i>
                AI Insights
            </h3>
            <a href="modules/ai/insights/index.php" class="text-xs text-blue-600 hover:underline">View All →</a>
        </div>
        <div class="grid grid-cols-4 gap-3 mb-4">
            <div class="text-center">
                <div class="text-xl font-bold text-red-600"><?= $aiCounts['critical'] ?></div>
                <div class="text-xs text-slate-500">Critical</div>
            </div>
            <div class="text-center">
                <div class="text-xl font-bold text-yellow-600"><?= $aiCounts['warning'] ?></div>
                <div class="text-xs text-slate-500">Warnings</div>
            </div>
            <div class="text-center">
                <div class="text-xl font-bold text-blue-600"><?= $aiCounts['notice'] + $aiCounts['info'] ?></div>
                <div class="text-xs text-slate-500">Info/Notice</div>
            </div>
            <div class="text-center">
                <div class="text-xl font-bold text-green-600"><?= $aiResolvedCount ?></div>
                <div class="text-xs text-slate-500">Resolved (7d)</div>
            </div>
        </div>
        <?php if (!empty($aiRecent)): ?>
        <div class="space-y-2">
            <?php foreach ($aiRecent as $insight): ?>
            <div class="flex items-center gap-2 text-sm p-2 rounded bg-slate-50">
                <span><?= match($insight['severity'] ?? 'info') { 'critical' => '🔴', 'warning' => '⚠️', 'notice' => 'ℹ️', default => '💡' } ?></span>
                <span class="text-slate-700 truncate flex-1"><?= htmlspecialchars($insight['title'] ?? '') ?></span>
                <span class="text-xs text-slate-400"><?= date('M j', strtotime($insight['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-sm text-slate-400 text-center py-2">No active insights</p>
        <?php endif; ?>
    </div>
</div>
<?php
        }
    }
}
?>

<!-- ===== Middle Row: Module Activity Bar Chart + Recent Activity ===== -->
<div class="grid grid-cols-1 lg:grid-cols-5 gap-6 mb-8">
    <!-- Module Activity Bar Chart -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-bar text-indigo-500 text-xs"></i>
            Module Activity <span class="text-xs text-slate-400 font-normal">(7 days)</span>
        </h3>
        <div class="space-y-3">
            <?php if ($moduleActivity): ?>
                <?php foreach ($moduleActivity as $m): ?>
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-slate-600 font-medium"><?= htmlspecialchars(ucfirst($m['module'])) ?></span>
                        <span class="text-slate-400"><?= $m['cnt'] ?></span>
                    </div>
                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all" style="width: <?= round(($m['cnt'] / $maxModule) * 100) ?>%; background: var(--primary)"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-xs text-slate-400 text-center py-4">No activity in the last 7 days</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activity Timeline -->
    <div class="lg:col-span-3 bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-clock text-indigo-500 text-xs"></i>
            Recent Activity
        </h3>
        <div class="space-y-0">
            <?php if ($recentLogs): ?>
                <?php foreach ($recentLogs as $log): ?>
                <div class="flex items-start gap-3 py-2.5 border-b border-slate-50 last:border-0">
                    <div class="w-1.5 h-1.5 rounded-full mt-2 flex-shrink-0
                        <?= $log['action'] === 'create' ? 'bg-emerald-400' : ($log['action'] === 'update' ? 'bg-amber-400' : ($log['action'] === 'delete' ? 'bg-rose-400' : 'bg-slate-300')) ?>">
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-slate-600 truncate">
                            <span class="font-medium text-slate-800"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></span>
                            <span class="text-slate-400"><?= htmlspecialchars($log['action']) ?></span>
                            <span class="text-slate-500"><?= htmlspecialchars($log['module']) ?></span>
                        </p>
                    </div>
                    <span class="text-[10px] text-slate-400 flex-shrink-0"><?= date('M d, H:i', strtotime($log['created_at'])) ?></span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-xs text-slate-400 text-center py-6">No recent activity</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===== Bottom Row: Quick Status Panels ===== -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                <i class="fas fa-calendar-alt text-amber-500 text-sm"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold">Pending Leaves</p>
                <p class="text-lg font-bold text-slate-800"><?= $pendingLeaves ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-rose-50 flex items-center justify-center">
                <i class="fas fa-receipt text-rose-500 text-sm"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold">Pending Expenses</p>
                <p class="text-lg font-bold text-slate-800"><?= $pendingExpenses ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center">
                <i class="fas fa-laptop text-indigo-500 text-sm"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold">Active Assets</p>
                <p class="text-lg font-bold text-slate-800"><?= $activeAssignments ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                <i class="fas fa-check-circle text-emerald-500 text-sm"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold">Today Attendance</p>
                <p class="text-lg font-bold text-slate-800"><?= $todayAttendance ?></p>
            </div>
        </div>
    </div>
</div>

<!-- ===== Charts Row ===== -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 flex flex-col">
        <h3 class="text-sm font-semibold text-slate-800 mb-3 flex items-center gap-2">
            <i class="fas fa-ticket-alt text-indigo-500 text-xs"></i>
            Tickets by Status
        </h3>
        <div class="relative flex-1" style="min-height:0">
            <div id="ticketChart"></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 flex flex-col">
        <h3 class="text-sm font-semibold text-slate-800 mb-3 flex items-center gap-2">
            <i class="fas fa-project-diagram text-indigo-500 text-xs"></i>
            Projects by Status
        </h3>
        <div class="relative flex-1" style="min-height:0">
            <div id="projectChart"></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 flex flex-col">
        <h3 class="text-sm font-semibold text-slate-800 mb-3 flex items-center gap-2">
            <i class="fas fa-users text-indigo-500 text-xs"></i>
            User Registrations
        </h3>
        <div class="relative flex-1" style="min-height:0">
            <div id="monthlyChart"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const render = window.renderCarbonChart;

    // Tickets by Status (doughnut)
    const ticketEl = document.getElementById('ticketChart');
    if (ticketEl && render) {
        render(ticketEl, 'doughnut', {
            labels: <?= json_encode(array_map('ucfirst', array_column($ticketStatus, 'status'))) ?>,
            datasets: [{
                data: <?= json_encode(array_map('intval', array_column($ticketStatus, 'cnt'))) ?>,
                backgroundColor: ['#f59e0b','#6366f1','#22c55e','#64748b','#ef4444'],
                borderWidth: 0
            }]
        }, { legend: { position: 'bottom' } });
    }

    // Projects by Status (doughnut)
    const projectEl = document.getElementById('projectChart');
    if (projectEl && render) {
        render(projectEl, 'doughnut', {
            labels: <?= json_encode(array_map('ucfirst', array_column($projectStatus, 'status'))) ?>,
            datasets: [{
                data: <?= json_encode(array_map('intval', array_column($projectStatus, 'cnt'))) ?>,
                backgroundColor: ['#8b5cf6','#6366f1','#22c55e','#f59e0b','#ef4444'],
                borderWidth: 0
            }]
        }, { legend: { position: 'bottom' } });
    }

    // User Registrations (line)
    const monthlyEl = document.getElementById('monthlyChart');
    if (monthlyEl && render) {
        render(monthlyEl, 'line', {
            labels: <?= json_encode($monthlyLabels) ?>,
            datasets: [{
                label: 'New Users',
                data: <?= json_encode(array_map('intval', $monthlyCounts)) ?>,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99,102,241,0.08)',
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#6366f1',
                pointRadius: 3,
                borderWidth: 2
            }]
        }, {
            legend: { enabled: false },
            curve: 'curve',
            points: { radius: 3 },
            getAreaOpacity: function () { return 0.15; }
        });
    }
});

</script>

</div><!-- /dashboard-old-content -->

<?php
require_once 'includes/dashboard_template.php';
require_once 'includes/footer.php'; ?>
