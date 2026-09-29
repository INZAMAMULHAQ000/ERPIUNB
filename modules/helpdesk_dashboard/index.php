<?php
$pageTitle = 'Helpdesk Dashboard';
$dashboardKey = 'helpdesk';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('helpdesk_dashboard.view')) { die('Access Denied'); }

$db = getDB();

$openTickets = $db->query("SELECT COUNT(*) as c FROM tickets WHERE status = 'open'")->fetch()['c'];
$inProgressTickets = $db->query("SELECT COUNT(*) as c FROM tickets WHERE status = 'in_progress'")->fetch()['c'];
$waitingTickets = $db->query("SELECT COUNT(*) as c FROM tickets WHERE status = 'waiting_client'")->fetch()['c'];
$resolvedTickets = $db->query("SELECT COUNT(*) as c FROM tickets WHERE status = 'resolved'")->fetch()['c'];
$closedTickets = $db->query("SELECT COUNT(*) as c FROM tickets WHERE status = 'closed'")->fetch()['c'];
$totalTickets = $db->query("SELECT COUNT(*) as c FROM tickets")->fetch()['c'];

$criticalTickets = $db->query("SELECT COUNT(*) as c FROM tickets WHERE priority = 'critical' AND status NOT IN ('resolved', 'closed')")->fetch()['c'];
$highTickets = $db->query("SELECT COUNT(*) as c FROM tickets WHERE priority = 'high' AND status NOT IN ('resolved', 'closed')")->fetch()['c'];
$mediumTickets = $db->query("SELECT COUNT(*) as c FROM tickets WHERE priority = 'medium' AND status NOT IN ('resolved', 'closed')")->fetch()['c'];
$lowTickets = $db->query("SELECT COUNT(*) as c FROM tickets WHERE priority = 'low' AND status NOT IN ('resolved', 'closed')")->fetch()['c'];

$slaBreached = $db->query("SELECT COUNT(*) as c FROM tickets WHERE sla_breached = 1 AND status NOT IN ('resolved', 'closed')")->fetch()['c'];
$resolvedToday = $db->query("SELECT COUNT(*) as c FROM tickets WHERE status = 'resolved' AND DATE(resolved_at) = CURDATE()")->fetch()['c'];
$createdToday = $db->query("SELECT COUNT(*) as c FROM tickets WHERE DATE(created_at) = CURDATE()")->fetch()['c'];

$recentTickets = $db->query("
    SELECT t.ticket_number, t.subject, t.priority, t.status, t.created_at,
           e.first_name, e.last_name, tc.category_name
    FROM tickets t
    LEFT JOIN employees e ON t.assigned_to = e.id
    LEFT JOIN ticket_categories tc ON t.category_id = tc.id
    ORDER BY t.created_at DESC LIMIT 10
")->fetchAll();

$topAgents = $db->query("
    SELECT e.first_name, e.last_name, COUNT(t.id) as ticket_count,
           SUM(CASE WHEN t.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count
    FROM tickets t
    JOIN employees e ON t.assigned_to = e.id
    WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY t.assigned_to
    ORDER BY resolved_count DESC
    LIMIT 5
")->fetchAll();

$slaAlerts = $db->query("
    SELECT t.ticket_number, t.subject, t.priority, t.due_date, t.status,
           sla.sla_name, sla.resolution_time_hours,
           TIMESTAMPDIFF(HOUR, t.created_at, NOW()) as hours_elapsed
    FROM tickets t
    JOIN slas sla ON t.sla_id = sla.id
    WHERE t.sla_breached = 0 AND t.status NOT IN ('resolved', 'closed')
    AND TIMESTAMPDIFF(HOUR, t.created_at, NOW()) >= (sla.resolution_time_hours * 0.8)
    ORDER BY hours_elapsed DESC
    LIMIT 5
")->fetchAll();

$pendingResponses = $db->query("
    SELECT t.ticket_number, t.subject, t.status, t.created_at,
           cl.company_name as client_name
    FROM tickets t
    LEFT JOIN clients cl ON t.client_id = cl.id
    WHERE t.status = 'waiting_client'
    ORDER BY t.created_at ASC
    LIMIT 5
")->fetchAll();

$categoryStats = $db->query("
    SELECT tc.category_name, tc.category_code, COUNT(t.id) as ticket_count,
           SUM(CASE WHEN t.status = 'open' THEN 1 ELSE 0 END) as open_count
    FROM ticket_categories tc
    LEFT JOIN tickets t ON tc.id = t.category_id
    GROUP BY tc.id
    ORDER BY ticket_count DESC
")->fetchAll();

$priorityColors = ['low' => 'bg-gray-100 text-gray-700', 'medium' => 'bg-blue-100 text-blue-800', 'high' => 'bg-orange-100 text-orange-800', 'critical' => 'bg-red-100 text-red-800'];
$statusColors = ['open' => 'bg-blue-100 text-blue-800', 'in_progress' => 'bg-yellow-100 text-yellow-800', 'waiting_client' => 'bg-purple-100 text-purple-800', 'resolved' => 'bg-green-100 text-green-800', 'closed' => 'bg-gray-100 text-gray-800', 'reopened' => 'bg-red-100 text-red-800'];
?>

<div id="dashboard-old-content">
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Helpdesk Dashboard</h1>
    <p class="text-gray-600">Support ticket overview and analytics</p>
</div>

<!-- Sub-Module Navigation -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <?php if (hasPermission('tickets.view')): ?>
    <a href="<?= url('modules/tickets/index.php') ?>" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-blue-500 flex items-center justify-center"><i class="fas fa-ticket-alt text-white"></i></div>
        <div><div class="font-semibold text-sm">Tickets</div><div class="text-xs text-gray-500">All tickets</div></div>
    </a>
    <?php endif; ?>
    <?php if (hasPermission('ticket_categories.view')): ?>
    <a href="<?= url('modules/ticket_categories/index.php') ?>" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-violet-500 flex items-center justify-center"><i class="fas fa-tags text-white"></i></div>
        <div><div class="font-semibold text-sm">Categories</div><div class="text-xs text-gray-500">Ticket types</div></div>
    </a>
    <?php endif; ?>
    <?php if (hasPermission('slas.view')): ?>
    <a href="<?= url('modules/slas/index.php') ?>" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-amber-500 flex items-center justify-center"><i class="fas fa-clock text-white"></i></div>
        <div><div class="font-semibold text-sm">SLAs</div><div class="text-xs text-gray-500">Policies</div></div>
    </a>
    <?php endif; ?>
    <?php if (hasPermission('knowledgebase.view')): ?>
    <a href="<?= url('modules/knowledge_base/index.php') ?>" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-green-500 flex items-center justify-center"><i class="fas fa-book text-white"></i></div>
        <div><div class="font-semibold text-sm">Knowledge Base</div><div class="text-xs text-gray-500">Articles</div></div>
    </a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-gray-500">Open Tickets</div>
                <div class="text-3xl font-bold text-blue-600"><?= $openTickets ?></div>
            </div>
            <i class="fas fa-envelope-open text-3xl text-blue-200"></i>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-yellow-500">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-gray-500">In Progress</div>
                <div class="text-3xl font-bold text-yellow-600"><?= $inProgressTickets ?></div>
            </div>
            <i class="fas fa-spinner text-3xl text-yellow-200"></i>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-gray-500">Resolved Today</div>
                <div class="text-3xl font-bold text-green-600"><?= $resolvedToday ?></div>
            </div>
            <i class="fas fa-check-circle text-3xl text-green-200"></i>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-gray-500">SLA Breached</div>
                <div class="text-3xl font-bold text-red-600"><?= $slaBreached ?></div>
            </div>
            <i class="fas fa-exclamation-triangle text-3xl text-red-200"></i>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Created Today</div>
        <div class="text-2xl font-bold text-gray-800"><?= $createdToday ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Waiting Client</div>
        <div class="text-2xl font-bold text-purple-600"><?= $waitingTickets ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Total Resolved</div>
        <div class="text-2xl font-bold text-green-600"><?= $resolvedTickets ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Total Tickets</div>
        <div class="text-2xl font-bold text-gray-800"><?= $totalTickets ?></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4"><i class="fas fa-flag mr-2"></i>Tickets by Priority</h3>
        <div id="priorityChart"></div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4"><i class="fas fa-clock mr-2 text-red-500"></i>SLA Alerts</h3>
        <?php if (empty($slaAlerts)): ?>
        <p class="text-gray-500 text-center py-4">No SLA alerts</p>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($slaAlerts as $alert): ?>
            <div class="border-l-4 border-orange-400 bg-orange-50 p-3 rounded">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="font-mono text-xs text-blue-600"><?= sanitize($alert['ticket_number']) ?></span>
                        <p class="text-sm font-medium truncate"><?= sanitize($alert['subject']) ?></p>
                    </div>
                    <span class="text-xs text-orange-600 font-bold"><?= $alert['hours_elapsed'] ?>h / <?= $alert['resolution_time_hours'] ?>h</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4"><i class="fas fa-hourglass-half mr-2 text-purple-500"></i>Pending Client Response</h3>
        <?php if (empty($pendingResponses)): ?>
        <p class="text-gray-500 text-center py-4">No pending responses</p>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($pendingResponses as $pr): ?>
            <div class="border-l-4 border-purple-400 bg-purple-50 p-3 rounded">
                <span class="font-mono text-xs text-blue-600"><?= sanitize($pr['ticket_number']) ?></span>
                <p class="text-sm font-medium truncate"><?= sanitize($pr['subject']) ?></p>
                <p class="text-xs text-gray-500"><?= $pr['client_name'] ?? 'No client' ?> - <?= date('M d', strtotime($pr['created_at'])) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4"><i class="fas fa-list mr-2"></i>Recent Tickets</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ticket</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($recentTickets as $rt): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 font-mono text-xs text-blue-600"><?= sanitize($rt['ticket_number']) ?></td>
                        <td class="px-3 py-2 truncate max-w-xs"><?= sanitize($rt['subject']) ?></td>
                        <td class="px-3 py-2"><span class="px-1.5 py-0.5 text-xs rounded <?= $priorityColors[$rt['priority']] ?>"><?= ucfirst($rt['priority']) ?></span></td>
                        <td class="px-3 py-2"><span class="px-1.5 py-0.5 text-xs rounded <?= $statusColors[$rt['status']] ?>"><?= str_replace('_', ' ', ucfirst($rt['status'])) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4"><i class="fas fa-trophy mr-2 text-yellow-500"></i>Top Support Agents (30 days)</h3>
        <?php if (empty($topAgents)): ?>
        <p class="text-gray-500 text-center py-4">No agent data yet</p>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($topAgents as $idx => $agent): ?>
            <div class="flex items-center gap-3 p-3 <?= $idx === 0 ? 'bg-yellow-50 border border-yellow-200 rounded' : 'border-b' ?>">
                <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white text-sm font-bold">
                    <?= strtoupper(substr($agent['first_name'], 0, 1)) ?>
                </div>
                <div class="flex-1">
                    <div class="font-medium"><?= sanitize($agent['first_name'] . ' ' . $agent['last_name']) ?></div>
                    <div class="text-xs text-gray-500"><?= $agent['ticket_count'] ?> tickets assigned</div>
                </div>
                <div class="text-right">
                    <div class="text-green-600 font-bold"><?= $agent['resolved_count'] ?></div>
                    <div class="text-xs text-gray-500">resolved</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4"><i class="fas fa-tags mr-2"></i>Tickets by Category</h3>
        <div class="space-y-3">
            <?php foreach ($categoryStats as $cs): ?>
            <div class="flex items-center justify-between p-2 border-b last:border-0">
                <div>
                    <span class="font-medium"><?= sanitize($cs['category_name']) ?></span>
                    <span class="text-xs text-gray-400 ml-2"><?= sanitize($cs['category_code']) ?></span>
                </div>
                <div class="text-sm">
                    <span class="text-gray-600"><?= $cs['ticket_count'] ?> total</span>
                    <?php if ($cs['open_count'] > 0): ?>
                    <span class="text-blue-600 ml-2"><?= $cs['open_count'] ?> open</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4"><i class="fas fa-chart-pie mr-2"></i>Quick Actions</h3>
        <div class="grid grid-cols-2 gap-3">
            <?php if (hasPermission('tickets.create')): ?>
            <a href="<?= url('modules/tickets/create.php') ?>" class="flex items-center gap-3 p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                <i class="fas fa-plus-circle text-blue-600 text-xl"></i>
                <div>
                    <div class="font-medium text-sm">New Ticket</div>
                    <div class="text-xs text-gray-500">Create support ticket</div>
                </div>
            </a>
            <?php endif; ?>
            <?php if (hasPermission('tickets.view')): ?>
            <a href="<?= url('modules/tickets/index.php') ?>" class="flex items-center gap-3 p-4 bg-green-50 rounded-lg hover:bg-green-100 transition">
                <i class="fas fa-list text-green-600 text-xl"></i>
                <div>
                    <div class="font-medium text-sm">All Tickets</div>
                    <div class="text-xs text-gray-500">View ticket list</div>
                </div>
            </a>
            <?php endif; ?>
            <?php if (hasPermission('ticket_categories.view')): ?>
            <a href="<?= url('modules/ticket_categories/index.php') ?>" class="flex items-center gap-3 p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
                <i class="fas fa-tags text-purple-600 text-xl"></i>
                <div>
                    <div class="font-medium text-sm">Categories</div>
                    <div class="text-xs text-gray-500">Manage categories</div>
                </div>
            </a>
            <?php endif; ?>
            <?php if (hasPermission('slas.view')): ?>
            <a href="<?= url('modules/slas/index.php') ?>" class="flex items-center gap-3 p-4 bg-orange-50 rounded-lg hover:bg-orange-100 transition">
                <i class="fas fa-clock text-orange-600 text-xl"></i>
                <div>
                    <div class="font-medium text-sm">SLA Policies</div>
                    <div class="text-xs text-gray-500">Manage SLAs</div>
                </div>
            </a>
            <?php endif; ?>
            <?php if (hasPermission('knowledgebase.view')): ?>
            <a href="<?= url('modules/knowledge_base/index.php') ?>" class="flex items-center gap-3 p-4 bg-teal-50 rounded-lg hover:bg-teal-100 transition">
                <i class="fas fa-book text-teal-600 text-xl"></i>
                <div>
                    <div class="font-medium text-sm">Knowledge Base</div>
                    <div class="text-xs text-gray-500">Browse articles</div>
                </div>
            </a>
            <?php endif; ?>
            <?php if (hasPermission('knowledgebase.create')): ?>
            <a href="<?= url('modules/knowledge_base/create.php') ?>" class="flex items-center gap-3 p-4 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
                <i class="fas fa-file-alt text-indigo-600 text-xl"></i>
                <div>
                    <div class="font-medium text-sm">New Article</div>
                    <div class="text-xs text-gray-500">Write KB article</div>
                </div>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const render = window.renderCarbonChart;
    const el = document.getElementById('priorityChart');
    if (el && render) {
        const labels = <?= json_encode(['Critical', 'High', 'Medium', 'Low']) ?>;
        const data = <?= json_encode([(int)$criticalTickets, (int)$highTickets, (int)$mediumTickets, (int)$lowTickets]) ?>;
        render(el, 'doughnut', {
            labels,
            datasets: [{ data, backgroundColor: ['#ef4444', '#f97316', '#3b82f6', '#9ca3af'] }]
        }, { legend: { position: 'bottom' } });
    }
});
</script>

</div><!-- /dashboard-old-content -->
<?php
require_once '../../includes/dashboard_template.php';
require_once '../../includes/footer.php'; ?>

