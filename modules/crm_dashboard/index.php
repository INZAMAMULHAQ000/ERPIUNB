<?php
$pageTitle = 'CRM Dashboard';
$dashboardKey = 'crm';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('leads.view') && !hasPermission('clients.view') && !hasPermission('deals.view')) { die('Access Denied'); }

$db = getDB();

$totalLeads = $db->query("SELECT COUNT(*) FROM leads")->fetchColumn();
$newLeads = $db->query("SELECT COUNT(*) FROM leads WHERE status = 'new'")->fetchColumn();
$contactedLeads = $db->query("SELECT COUNT(*) FROM leads WHERE status = 'contacted'")->fetchColumn();
$qualifiedLeads = $db->query("SELECT COUNT(*) FROM leads WHERE status = 'qualified'")->fetchColumn();
$wonLeads = $db->query("SELECT COUNT(*) FROM leads WHERE status = 'won'")->fetchColumn();
$lostLeads = $db->query("SELECT COUNT(*) FROM leads WHERE status = 'lost'")->fetchColumn();
$totalClients = $db->query("SELECT COUNT(*) FROM clients WHERE status = 'active' AND deleted_at IS NULL")->fetchColumn();
$totalDeals = $db->query("SELECT COUNT(*) FROM deals")->fetchColumn();
$activeDeals = $db->query("SELECT COUNT(*) FROM deals WHERE stage NOT IN ('closed_won','closed_lost')")->fetchColumn();
$pipelineValue = $db->query("SELECT COALESCE(SUM(deal_value),0) FROM deals WHERE stage NOT IN ('closed_won','closed_lost')")->fetchColumn();
$wonValue = $db->query("SELECT COALESCE(SUM(deal_value),0) FROM deals WHERE stage = 'closed_won'")->fetchColumn();
$lostValue = $db->query("SELECT COALESCE(SUM(deal_value),0) FROM deals WHERE stage = 'closed_lost'")->fetchColumn();
$conversionRate = $totalLeads > 0 ? round(($wonLeads / $totalLeads) * 100, 1) : 0;
$leadsThisMonth = $db->query("SELECT COUNT(*) FROM leads WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())")->fetchColumn();
$dealsThisMonth = $db->query("SELECT COUNT(*) FROM deals WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())")->fetchColumn();

$leadsByStatus = $db->query("SELECT status, COUNT(*) as cnt FROM leads GROUP BY status ORDER BY FIELD(status,'new','contacted','qualified','proposal_sent','negotiation','won','lost')")->fetchAll();
$dealsByStage = $db->query("SELECT stage, COUNT(*) as cnt, COALESCE(SUM(deal_value),0) as total_value FROM deals GROUP BY stage ORDER BY FIELD(stage,'inquiry','qualified','proposal','negotiation','closed_won','closed_lost')")->fetchAll();
$leadsBySource = $db->query("SELECT COALESCE(source,'Unknown') as source, COUNT(*) as cnt FROM leads GROUP BY source ORDER BY cnt DESC LIMIT 8")->fetchAll();
$dealsByMonth = $db->query("
    SELECT DATE_FORMAT(created_at, '%b') as label, COUNT(*) as cnt, COALESCE(SUM(deal_value),0) as total_value
    FROM deals
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY MIN(created_at)
")->fetchAll();

$recentLeads = $db->query("
    SELECT l.*, e.first_name, e.last_name
    FROM leads l
    LEFT JOIN employees e ON l.assigned_to = e.id
    ORDER BY l.created_at DESC LIMIT 8
")->fetchAll();

$topDeals = $db->query("
    SELECT d.deal_title, d.deal_value, d.stage, d.expected_closing_date,
           c.company_name, CONCAT(e.first_name, ' ', e.last_name) as assignee
    FROM deals d
    LEFT JOIN clients c ON d.client_id = c.id
    LEFT JOIN employees e ON d.assigned_to = e.id
    WHERE d.stage NOT IN ('closed_won','closed_lost')
    ORDER BY d.deal_value DESC LIMIT 6
")->fetchAll();

$upcomingFollowups = $db->query("
    SELECT lf.*, l.company_name, l.contact_person
    FROM lead_followups lf
    JOIN leads l ON lf.lead_id = l.id
    WHERE lf.followup_date >= CURDATE() AND lf.status = 'pending'
    ORDER BY lf.followup_date ASC LIMIT 6
")->fetchAll();

$recentDeals = $db->query("
    SELECT d.*, c.company_name, CONCAT(e.first_name, ' ', e.last_name) as assignee
    FROM deals d
    LEFT JOIN clients c ON d.client_id = c.id
    LEFT JOIN employees e ON d.assigned_to = e.id
    ORDER BY d.created_at DESC LIMIT 8
")->fetchAll();

$stageColors = [
    'inquiry' => '#94a3b8',
    'qualified' => '#6366f1',
    'proposal' => '#f59e0b',
    'negotiation' => '#f97316',
    'closed_won' => '#22c55e',
    'closed_lost' => '#ef4444'
];
$stageLabels = [
    'inquiry' => 'Inquiry',
    'qualified' => 'Qualified',
    'proposal' => 'Proposal',
    'negotiation' => 'Negotiation',
    'closed_won' => 'Won',
    'closed_lost' => 'Lost'
];
$statusColors = [
    'new' => 'bg-blue-100 text-blue-800',
    'contacted' => 'bg-yellow-100 text-yellow-800',
    'qualified' => 'bg-purple-100 text-purple-800',
    'proposal_sent' => 'bg-orange-100 text-orange-800',
    'negotiation' => 'bg-amber-100 text-amber-800',
    'won' => 'bg-green-100 text-green-800',
    'lost' => 'bg-red-100 text-red-800'
];
$dealStageColors = [
    'inquiry' => 'bg-gray-100 text-gray-800',
    'qualified' => 'bg-indigo-100 text-indigo-800',
    'proposal' => 'bg-yellow-100 text-yellow-800',
    'negotiation' => 'bg-orange-100 text-orange-800',
    'closed_won' => 'bg-green-100 text-green-800',
    'closed_lost' => 'bg-red-100 text-red-800'
];
?>

<div id="dashboard-old-content">

<!-- Header -->
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">CRM Dashboard</h1>
    <p class="text-gray-500 text-sm mt-1">Customer Relationship Management Overview</p>
</div>

<!-- ===== KPI Stats Cards ===== -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
    <a href="<?= url('modules/leads/index.php') ?>" class="bg-white rounded-xl shadow-sm p-4 border border-slate-100 hover:shadow-md transition block">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Total Leads</span>
            <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center">
                <i class="fas fa-user-plus text-blue-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-slate-800"><?= $totalLeads ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= $leadsThisMonth ?> new this month</p>
    </a>
    <a href="<?= url('modules/customers/index.php') ?>" class="bg-white rounded-xl shadow-sm p-4 border border-slate-100 hover:shadow-md transition block">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Active Customers</span>
            <div class="w-9 h-9 rounded-lg bg-emerald-50 flex items-center justify-center">
                <i class="fas fa-building text-emerald-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-slate-800"><?= $totalClients ?></p>
    </a>
    <a href="<?= url('modules/deals/index.php') ?>" class="bg-white rounded-xl shadow-sm p-4 border border-slate-100 hover:shadow-md transition block">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Active Deals</span>
            <div class="w-9 h-9 rounded-lg bg-violet-50 flex items-center justify-center">
                <i class="fas fa-handshake text-violet-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-slate-800"><?= $activeDeals ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= $dealsThisMonth ?> created this month</p>
    </a>
    <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Pipeline Value</span>
            <div class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center">
                <i class="fas fa-coins text-amber-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-slate-800"><?= formatMoney($pipelineValue) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Won Value</span>
            <div class="w-9 h-9 rounded-lg bg-green-50 flex items-center justify-center">
                <i class="fas fa-trophy text-green-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-green-600"><?= formatMoney($wonValue) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Conversion</span>
            <div class="w-9 h-9 rounded-lg bg-cyan-50 flex items-center justify-center">
                <i class="fas fa-chart-line text-cyan-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-slate-800"><?= $conversionRate ?>%</p>
        <p class="text-xs text-slate-400 mt-1"><?= $wonLeads ?> won / <?= $totalLeads ?> total</p>
    </div>
</div>

<!-- ===== Sub-Module Navigation Cards ===== -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <?php if (hasPermission('leads.view')): ?>
    <a href="<?= url('modules/leads/index.php') ?>" class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 hover:shadow-md transition group">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-blue-500 flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-user-plus text-white"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800">Leads</h3>
                <p class="text-xs text-slate-400">Manage prospects</p>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-2xl font-bold text-slate-800"><?= $totalLeads ?></span>
            <span class="text-xs text-slate-400">total leads</span>
        </div>
    </a>
    <?php endif; ?>
    <?php if (hasPermission('customers.view')): ?>
    <a href="<?= url('modules/customers/index.php') ?>" class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 hover:shadow-md transition group">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-emerald-500 flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-building text-white"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800">Customers</h3>
                <p class="text-xs text-slate-400">Customer master</p>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-2xl font-bold text-slate-800"><?= $totalClients ?></span>
            <span class="text-xs text-slate-400">active clients</span>
        </div>
    </a>
    <?php endif; ?>
    <?php if (hasPermission('deals.view')): ?>
    <a href="<?= url('modules/deals/index.php') ?>" class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 hover:shadow-md transition group">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-violet-500 flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-handshake text-white"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800">Deals</h3>
                <p class="text-xs text-slate-400">Sales pipeline</p>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-2xl font-bold text-slate-800"><?= $totalDeals ?></span>
            <span class="text-xs text-slate-400">total deals</span>
        </div>
    </a>
    <?php endif; ?>
    <?php if (hasPermission('followups.view')): ?>
    <a href="<?= url('modules/followups/index.php') ?>" class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 hover:shadow-md transition group">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-amber-500 flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-calendar-check text-white"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800">Follow-ups</h3>
                <p class="text-xs text-slate-400">Track activities</p>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-2xl font-bold text-slate-800"><?= count($upcomingFollowups) ?></span>
            <span class="text-xs text-slate-400">upcoming</span>
        </div>
    </a>
    <?php endif; ?>
</div>

<!-- ===== Charts Row 1: Leads by Status + Deals by Stage ===== -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-bar text-blue-500 text-xs"></i>
            Leads by Status
        </h3>
        <div id="leadsByStatusChart" style="min-height: 260px"></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-pie text-violet-500 text-xs"></i>
            Deals by Stage
        </h3>
        <div id="dealsByStageChart" style="min-height: 260px"></div>
    </div>
</div>

<!-- ===== Charts Row 2: Lead Sources + Monthly Deal Trend ===== -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-bar text-emerald-500 text-xs"></i>
            Leads by Source
        </h3>
        <div id="leadsBySourceChart" style="min-height: 260px"></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-line text-amber-500 text-xs"></i>
            Deal Trend (6 Months)
        </h3>
        <div id="dealTrendChart" style="min-height: 260px"></div>
    </div>
</div>

<!-- ===== Pipeline Value Summary + Top Deals ===== -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-funnel-dollar text-violet-500 text-xs"></i>
            Pipeline Summary
        </h3>
        <div class="space-y-3">
            <?php foreach ($dealsByStage as $ds): ?>
            <div>
                <div class="flex justify-between text-xs mb-1">
                    <span class="text-slate-600 font-medium"><?= $stageLabels[$ds['stage']] ?? $ds['stage'] ?></span>
                    <span class="text-slate-400"><?= formatMoney($ds['total_value']) ?></span>
                </div>
                <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full" style="width: <?= $pipelineValue > 0 ? round(($ds['total_value'] / $pipelineValue) * 100) : 0 ?>%; background: <?= $stageColors[$ds['stage']] ?? '#94a3b8' ?>"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-gem text-amber-500 text-xs"></i>
            Top Active Deals
        </h3>
        <?php if (empty($topDeals)): ?>
        <p class="text-sm text-slate-400 text-center py-6">No active deals</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="text-left text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2">Deal</th>
                        <th class="text-left text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2">Client</th>
                        <th class="text-left text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2">Value</th>
                        <th class="text-left text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2">Stage</th>
                        <th class="text-left text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2">Closing</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($topDeals as $td): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="py-2.5 font-medium text-slate-800"><?= htmlspecialchars($td['deal_title']) ?></td>
                        <td class="py-2.5 text-slate-600"><?= htmlspecialchars($td['company_name'] ?? 'N/A') ?></td>
                        <td class="py-2.5 font-semibold text-slate-800"><?= formatMoney($td['deal_value']) ?></td>
                        <td class="py-2.5"><span class="px-2 py-0.5 text-xs rounded-full <?= $dealStageColors[$td['stage']] ?? '' ?>"><?= $stageLabels[$td['stage']] ?? $td['stage'] ?></span></td>
                        <td class="py-2.5 text-slate-500 text-xs"><?= $td['expected_closing_date'] ? date('M d, Y', strtotime($td['expected_closing_date'])) : 'N/A' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Recent Leads + Upcoming Follow-ups ===== -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-user-plus text-blue-500 text-xs"></i>
            Recent Leads
        </h3>
        <?php if (empty($recentLeads)): ?>
        <p class="text-sm text-slate-400 text-center py-6">No leads yet</p>
        <?php else: ?>
        <div class="space-y-0">
            <?php foreach ($recentLeads as $rl): ?>
            <div class="flex items-center justify-between py-2.5 border-b border-slate-50 last:border-0">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-800 truncate"><?= htmlspecialchars($rl['company_name']) ?></p>
                    <p class="text-xs text-slate-400"><?= htmlspecialchars($rl['contact_person'] ?? 'No contact') ?> &middot; <?= $rl['source'] ? htmlspecialchars($rl['source']) : 'Unknown source' ?></p>
                </div>
                <div class="flex items-center gap-2 ml-3">
                    <span class="px-2 py-0.5 text-xs rounded-full <?= $statusColors[$rl['status']] ?? '' ?>"><?= str_replace('_', ' ', ucfirst($rl['status'])) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-calendar-check text-amber-500 text-xs"></i>
            Upcoming Follow-ups
        </h3>
        <?php if (empty($upcomingFollowups)): ?>
        <p class="text-sm text-slate-400 text-center py-6">No upcoming follow-ups</p>
        <?php else: ?>
        <div class="space-y-0">
            <?php foreach ($upcomingFollowups as $fu): ?>
            <div class="flex items-center gap-3 py-2.5 border-b border-slate-50 last:border-0">
                <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-<?= $fu['followup_type'] === 'call' ? 'phone' : ($fu['followup_type'] === 'email' ? 'envelope' : ($fu['followup_type'] === 'meeting' ? 'users' : 'comments')) ?> text-amber-500 text-xs"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-800 truncate"><?= htmlspecialchars($fu['company_name']) ?></p>
                    <p class="text-xs text-slate-400"><?= ucfirst($fu['followup_type']) ?> &middot; <?= htmlspecialchars($fu['contact_person'] ?? '') ?></p>
                </div>
                <span class="text-xs text-slate-500 flex-shrink-0"><?= date('M d', strtotime($fu['followup_date'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Quick Actions ===== -->
<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 mb-8">
    <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
        <i class="fas fa-bolt text-indigo-500 text-xs"></i>
        Quick Actions
    </h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <?php if (hasPermission('leads.create')): ?>
        <a href="<?= url('modules/leads/create.php') ?>" class="flex items-center gap-3 p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
            <i class="fas fa-plus-circle text-blue-600 text-xl"></i>
            <div>
                <div class="font-medium text-sm">New Lead</div>
                <div class="text-xs text-gray-500">Add a prospect</div>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('customers.create')): ?>
        <a href="<?= url('modules/customers/form.php') ?>" class="flex items-center gap-3 p-4 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition">
            <i class="fas fa-building text-emerald-600 text-xl"></i>
            <div>
                <div class="font-medium text-sm">New Customer</div>
                <div class="text-xs text-gray-500">Add to customer master</div>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('deals.create')): ?>
        <a href="<?= url('modules/deals/create.php') ?>" class="flex items-center gap-3 p-4 bg-violet-50 rounded-lg hover:bg-violet-100 transition">
            <i class="fas fa-handshake text-violet-600 text-xl"></i>
            <div>
                <div class="font-medium text-sm">New Deal</div>
                <div class="text-xs text-gray-500">Create deal</div>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('followups.create')): ?>
        <a href="<?= url('modules/followups/create.php') ?>" class="flex items-center gap-3 p-4 bg-amber-50 rounded-lg hover:bg-amber-100 transition">
            <i class="fas fa-calendar-plus text-amber-600 text-xl"></i>
            <div>
                <div class="font-medium text-sm">New Follow-up</div>
                <div class="text-xs text-gray-500">Schedule activity</div>
            </div>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Charts JS ===== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const render = window.renderCarbonChart;

    // Leads by Status (bar chart)
    const leadsEl = document.getElementById('leadsByStatusChart');
    if (leadsEl && render) {
        const labels = <?= json_encode(array_map(function($s) { return str_replace('_', ' ', ucfirst($s['status'])); }, $leadsByStatus)) ?>;
        const data = <?= json_encode(array_map('intval', array_column($leadsByStatus, 'cnt'))) ?>;
        const colors = ['#3b82f6','#eab308','#a855f7','#f97316','#f59e0b','#22c55e','#ef4444'];
        render(leadsEl, 'bar', {
            labels: labels,
            datasets: [{ label: 'Leads', data: data, backgroundColor: colors.slice(0, data.length) }]
        }, { scales: { y: { beginAtZero: true } }, legend: { enabled: false } });
    }

    // Deals by Stage (doughnut)
    const dealsEl = document.getElementById('dealsByStageChart');
    if (dealsEl && render) {
        const stageLabels = <?= json_encode(array_map(function($d) use ($stageLabels) { return $stageLabels[$d['stage']] ?? $d['stage']; }, $dealsByStage)) ?>;
        const stageData = <?= json_encode(array_map('intval', array_column($dealsByStage, 'cnt'))) ?>;
        const stageColorsArr = <?= json_encode(array_map(function($d) use ($stageColors) { return $stageColors[$d['stage']] ?? '#94a3b8'; }, $dealsByStage)) ?>;
        render(dealsEl, 'doughnut', {
            labels: stageLabels,
            datasets: [{ data: stageData, backgroundColor: stageColorsArr, borderWidth: 0 }]
        }, { legend: { position: 'bottom' } });
    }

    // Leads by Source (horizontal bar)
    const sourceEl = document.getElementById('leadsBySourceChart');
    if (sourceEl && render) {
        const srcLabels = <?= json_encode(array_column($leadsBySource, 'source')) ?>;
        const srcData = <?= json_encode(array_map('intval', array_column($leadsBySource, 'cnt'))) ?>;
        render(sourceEl, 'bar', {
            labels: srcLabels,
            datasets: [{ label: 'Leads', data: srcData, backgroundColor: '#22c55e' }]
        }, { scales: { x: { beginAtZero: true } }, legend: { enabled: false }, bar: { horizontal: true } });
    }

    // Deal Trend (line chart)
    const trendEl = document.getElementById('dealTrendChart');
    if (trendEl && render) {
        const trendLabels = <?= json_encode(array_column($dealsByMonth, 'label')) ?>;
        const trendData = <?= json_encode(array_map('intval', array_column($dealsByMonth, 'cnt'))) ?>;
        render(trendEl, 'line', {
            labels: trendLabels,
            datasets: [{
                label: 'Deals Created',
                data: trendData,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99,102,241,0.08)',
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#6366f1',
                pointRadius: 4,
                borderWidth: 2
            }]
        }, { legend: { enabled: false }, curve: 'curve', points: { radius: 4 } });
    }
});
</script>

</div><!-- /dashboard-old-content -->

<?php
require_once '../../includes/dashboard_template.php';
require_once '../../includes/footer.php'; ?>
