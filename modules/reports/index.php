<?php
$pageTitle = 'Reports Center';
require_once '../../includes/header.php';

if (!hasPermission('reports.view')) {
    die('<div class="p-8 text-center text-red-600 font-semibold">Access Denied</div>');
}

logAudit($_SESSION['user_id'], 'viewed_reports_center', 'reports');

$reportGroups = [
    'HR Reports' => [
        'icon' => 'fa-users',
        'color' => 'blue',
        'reports' => [
            ['key' => 'employee_summary',  'label' => 'Employee Summary',   'icon' => 'fa-user-tie'],
            ['key' => 'attendance_report', 'label' => 'Attendance Report',  'icon' => 'fa-clock'],
            ['key' => 'leave_summary',     'label' => 'Leave Summary',      'icon' => 'fa-calendar-minus'],
        ]
    ],
    'Project Reports' => [
        'icon' => 'fa-project-diagram',
        'color' => 'purple',
        'reports' => [
            ['key' => 'project_progress',  'label' => 'Project Progress',   'icon' => 'fa-tasks'],
            ['key' => 'task_completion',   'label' => 'Task Completion',    'icon' => 'fa-check-circle'],
            ['key' => 'timesheet_summary', 'label' => 'Timesheet Summary',  'icon' => 'fa-hourglass-half'],
        ]
    ],
    'CRM Reports' => [
        'icon' => 'fa-handshake',
        'color' => 'green',
        'reports' => [
            ['key' => 'leads_conversion',  'label' => 'Leads Conversion',   'icon' => 'fa-user-plus'],
            ['key' => 'sales_pipeline',    'label' => 'Sales Pipeline',     'icon' => 'fa-funnel-dollar'],
            ['key' => 'client_growth',     'label' => 'Client Growth',      'icon' => 'fa-chart-line'],
        ]
    ],
    'Finance Reports' => [
        'icon' => 'fa-dollar-sign',
        'color' => 'yellow',
        'reports' => [
            ['key' => 'revenue_summary',   'label' => 'Revenue Summary',    'icon' => 'fa-money-bill-wave'],
            ['key' => 'expense_summary',   'label' => 'Expense Summary',    'icon' => 'fa-receipt'],
            ['key' => 'profit_overview',   'label' => 'Profit Overview',    'icon' => 'fa-chart-bar'],
            ['key' => 'invoice_aging',     'label' => 'Invoice Aging',      'icon' => 'fa-file-invoice-dollar'],
        ]
    ],
    'Asset Reports' => [
        'icon' => 'fa-desktop',
        'color' => 'indigo',
        'reports' => [
            ['key' => 'asset_allocation',  'label' => 'Asset Allocation',   'icon' => 'fa-sitemap'],
            ['key' => 'maintenance_summary','label' => 'Maintenance Summary','icon' => 'fa-tools'],
            ['key' => 'license_expiry',    'label' => 'License Expiry',     'icon' => 'fa-key'],
        ]
    ],
    'Helpdesk Reports' => [
        'icon' => 'fa-headset',
        'color' => 'red',
        'reports' => [
            ['key' => 'ticket_resolution', 'label' => 'Ticket Resolution',  'icon' => 'fa-ticket-alt'],
            ['key' => 'sla_performance',   'label' => 'SLA Performance',    'icon' => 'fa-tachometer-alt'],
            ['key' => 'support_workload',  'label' => 'Support Workload',   'icon' => 'fa-users-cog'],
        ]
    ],
];

$colorMap = [
    'blue'   => ['bg' => 'bg-blue-50',   'icon' => 'text-blue-600',   'btn' => 'bg-blue-600 hover:bg-blue-700'],
    'purple' => ['bg' => 'bg-purple-50', 'icon' => 'text-purple-600', 'btn' => 'bg-purple-600 hover:bg-purple-700'],
    'green'  => ['bg' => 'bg-green-50',  'icon' => 'text-green-600',  'btn' => 'bg-green-600 hover:bg-green-700'],
    'yellow' => ['bg' => 'bg-yellow-50', 'icon' => 'text-yellow-600', 'btn' => 'bg-yellow-600 hover:bg-yellow-700'],
    'indigo' => ['bg' => 'bg-indigo-50', 'icon' => 'text-indigo-600', 'btn' => 'bg-indigo-600 hover:bg-indigo-700'],
    'red'    => ['bg' => 'bg-red-50',    'icon' => 'text-red-600',    'btn' => 'bg-red-600 hover:bg-red-700'],
];
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Reports Center</h1>
        <p class="text-gray-600">Generate and export business reports</p>
    </div>
    <div class="flex gap-2">
        <a href="saved.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition text-sm">
            <i class="fas fa-bookmark mr-1"></i>Saved Reports
        </a>
        <?php if (hasPermission('scheduled_reports.view')): ?>
        <a href="../scheduled_reports/index.php" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 transition text-sm">
            <i class="fas fa-calendar-alt mr-1"></i>Scheduled
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
<?php foreach ($reportGroups as $groupName => $group):
    $c = $colorMap[$group['color']];
?>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="<?= $c['bg'] ?> px-5 py-4 flex items-center gap-3 border-b">
        <i class="fas <?= $group['icon'] ?> <?= $c['icon'] ?> text-lg"></i>
        <h2 class="font-semibold text-gray-800"><?= $groupName ?></h2>
    </div>
    <div class="divide-y divide-gray-100">
        <?php foreach ($group['reports'] as $report): ?>
        <a href="view.php?type=<?= $report['key'] ?>" class="flex items-center px-5 py-3 hover:bg-gray-50 transition group">
            <i class="fas <?= $report['icon'] ?> text-gray-400 group-hover:<?= $c['icon'] ?> w-6 transition"></i>
            <span class="ml-3 text-gray-700 group-hover:text-gray-900 text-sm"><?= $report['label'] ?></span>
            <i class="fas fa-chevron-right ml-auto text-gray-300 group-hover:text-gray-500 text-xs"></i>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
