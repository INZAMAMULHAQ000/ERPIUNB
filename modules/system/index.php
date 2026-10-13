<?php
$pageTitle = 'System';
$dashboardKey = 'system';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

$canUsers      = hasPermission('users.view');
$canRoles      = hasPermission('roles.view');
$canPerms      = hasPermission('permissions.view');
$canPeople     = hasPermission('people_management.view');
$canAudit      = hasPermission('auditlogs.view');

if (!$canUsers && !$canRoles && !$canPerms && !$canPeople) { die('Access Denied'); }

$db = getDB();

// --- Counts ---
$q = function ($sql) use ($db) { return $db->query($sql)->fetchColumn(); };

$totalUsers   = $q("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL");
$activeUsers  = $q("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND status = 'active'");
$newUsersMtd  = $q("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$totalRoles   = $q("SELECT COUNT(*) FROM roles WHERE deleted_at IS NULL");
$totalPerms   = $q("SELECT COUNT(*) FROM permissions WHERE deleted_at IS NULL");
$permModules  = $q("SELECT COUNT(DISTINCT module) FROM permissions WHERE deleted_at IS NULL");
$assignments  = $q("SELECT COUNT(*) FROM user_roles ur JOIN users u ON u.id = ur.user_id AND u.deleted_at IS NULL");
$unassigned   = $totalUsers - $q("SELECT COUNT(DISTINCT ur.user_id) FROM user_roles ur JOIN users u ON u.id = ur.user_id AND u.deleted_at IS NULL");
$totalPeople  = $q("SELECT COUNT(*) FROM people WHERE status != 'inactive'");
$activePeople = $q("SELECT COUNT(*) FROM people WHERE status = 'active'");
$peopleLinked = $q("SELECT COUNT(*) FROM people WHERE employee_id IS NOT NULL");
$loginsToday  = $q("SELECT COUNT(*) FROM audit_logs WHERE action = 'login' AND DATE(created_at) = CURDATE()");
$actsToday    = $q("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()");
$acts30       = $q("SELECT COUNT(*) FROM audit_logs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");

// --- Chart series ---
$usersByRole = $db->query("
    SELECT r.name AS label, COUNT(DISTINCT ur.user_id) AS cnt
    FROM roles r
    LEFT JOIN user_roles ur ON ur.role_id = r.id
    LEFT JOIN users u ON u.id = ur.user_id AND u.deleted_at IS NULL
    WHERE r.deleted_at IS NULL
    GROUP BY r.id, r.name
    ORDER BY cnt DESC
")->fetchAll();

$roleCoverage = $db->query("
    SELECT r.name AS label, COUNT(rp.permission_id) AS cnt
    FROM roles r
    LEFT JOIN role_permissions rp ON rp.role_id = r.id
    WHERE r.deleted_at IS NULL
    GROUP BY r.id, r.name
    ORDER BY cnt DESC
")->fetchAll();

$permsByModule = $db->query("
    SELECT module AS label, COUNT(*) AS cnt
    FROM permissions WHERE deleted_at IS NULL
    GROUP BY module
    ORDER BY cnt DESC
    LIMIT 12
")->fetchAll();

$activityTrend = $db->query("
    SELECT DATE(created_at) AS label, COUNT(*) AS cnt
    FROM audit_logs
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 89 DAY)
    GROUP BY DATE(created_at)
    ORDER BY label ASC
")->fetchAll();

$userGrowth = $db->query("
    SELECT DATE_FORMAT(created_at, '%b %Y') AS label, COUNT(*) AS cnt
    FROM users
    WHERE deleted_at IS NULL AND created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY MIN(created_at) ASC
")->fetchAll();

$activityByModule = $db->query("
    SELECT COALESCE(NULLIF(module, ''), 'other') AS label, COUNT(*) AS cnt
    FROM audit_logs
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    GROUP BY label
    ORDER BY cnt DESC
    LIMIT 8
")->fetchAll();

// --- Tables ---
$recentUsers = $db->query("
    SELECT u.id, u.name, u.email, u.status, u.created_at,
           COALESCE(GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', '), '—') AS roles
    FROM users u
    LEFT JOIN user_roles ur ON ur.user_id = u.id
    LEFT JOIN roles r ON r.id = ur.role_id AND r.deleted_at IS NULL
    WHERE u.deleted_at IS NULL
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT 8
")->fetchAll();

$recentActivity = $db->query("
    SELECT a.action, a.module, a.created_at, u.name AS user_name
    FROM audit_logs a
    LEFT JOIN users u ON u.id = a.user_id
    ORDER BY a.created_at DESC
    LIMIT 10
")->fetchAll();

$rolesList = $db->query("
    SELECT r.id, r.name, r.created_at,
           (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS perm_count,
           (SELECT COUNT(DISTINCT ur.user_id) FROM user_roles ur JOIN users u ON u.id = ur.user_id AND u.deleted_at IS NULL WHERE ur.role_id = r.id) AS user_count
    FROM roles r WHERE r.deleted_at IS NULL
    ORDER BY perm_count DESC
")->fetchAll();

$palette   = ['#6366f1', '#22c55e', '#f59e0b', '#ec4899', '#14b8a6', '#8b5cf6', '#ef4444', '#0ea5e9', '#64748b', '#a855f7', '#f97316', '#22d3ee'];
$statusColors = ['active' => 'bg-green-100 text-green-800', 'inactive' => 'bg-gray-100 text-gray-800', 'suspended' => 'bg-red-100 text-red-800'];

// Navigation cards — each gated by its own view permission.
$cards = [];
if ($canUsers) {
    $cards[] = ['href' => 'modules/users/index.php', 'title' => 'Users', 'desc' => 'Accounts, credentials and role assignment',
                'icon' => 'fa-users', 'bg' => 'bg-indigo-500', 'soft' => 'bg-indigo-50', 'text' => 'text-indigo-600',
                'stat' => $activeUsers, 'statLabel' => 'active accounts', 'extra' => $newUsersMtd . ' added this month'];
}
if ($canRoles) {
    $cards[] = ['href' => 'modules/roles/index.php', 'title' => 'Roles', 'desc' => 'Group permissions and assign to users',
                'icon' => 'fa-user-shield', 'bg' => 'bg-violet-500', 'soft' => 'bg-violet-50', 'text' => 'text-violet-600',
                'stat' => $totalRoles, 'statLabel' => 'roles defined', 'extra' => $assignments . ' user assignments'];
}
if ($canPerms) {
    $cards[] = ['href' => 'modules/permissions/index.php', 'title' => 'Permissions', 'desc' => 'Fine-grained module access control',
                'icon' => 'fa-key', 'bg' => 'bg-amber-500', 'soft' => 'bg-amber-50', 'text' => 'text-amber-600',
                'stat' => $totalPerms, 'statLabel' => 'permissions', 'extra' => 'across ' . $permModules . ' modules'];
}
if ($canPeople) {
    $cards[] = ['href' => 'modules/people_management/index.php', 'title' => 'People', 'desc' => 'Directory of contacts and staff records',
                'icon' => 'fa-address-book', 'bg' => 'bg-teal-500', 'soft' => 'bg-teal-50', 'text' => 'text-teal-600',
                'stat' => $totalPeople, 'statLabel' => 'people records', 'extra' => $peopleLinked . ' linked to employees'];
}
?>

<div id="dashboard-old-content">

<!-- Header -->
<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">System</h1>
        <p class="text-gray-500 text-sm mt-1">Users, roles, permissions and people directory</p>
    </div>
    <?php if ($canAudit): ?>
    <div class="flex items-center gap-4 text-xs text-slate-500">
        <span class="flex items-center gap-1.5"><i class="fas fa-sign-in-alt text-indigo-500"></i> <?= $loginsToday ?> logins today</span>
        <span class="flex items-center gap-1.5"><i class="fas fa-bolt text-amber-500"></i> <?= number_format($acts30) ?> events / 30 days</span>
    </div>
    <?php endif; ?>
</div>

<!-- ===== Module Navigation Cards ===== -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">
    <?php foreach ($cards as $c): ?>
    <a href="<?= url($c['href']) ?>" class="group bg-white rounded-xl shadow-sm border border-slate-100 p-5 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 block">
        <div class="flex items-start justify-between mb-4">
            <div class="w-11 h-11 rounded-xl <?= $c['bg'] ?> flex items-center justify-center shadow-sm group-hover:scale-110 transition-transform">
                <i class="fas <?= $c['icon'] ?> text-white"></i>
            </div>
            <i class="fas fa-arrow-right text-slate-300 group-hover:text-slate-500 group-hover:translate-x-1 transition-all text-sm mt-1"></i>
        </div>
        <h3 class="font-semibold text-slate-800 mb-0.5"><?= $c['title'] ?></h3>
        <p class="text-xs text-slate-400 leading-relaxed mb-4"><?= $c['desc'] ?></p>
        <div class="flex items-baseline gap-2">
            <span class="text-2xl font-bold <?= $c['text'] ?>"><?= number_format($c['stat']) ?></span>
            <span class="text-xs text-slate-400"><?= $c['statLabel'] ?></span>
        </div>
        <p class="text-[11px] text-slate-400 mt-1.5 pt-1.5 border-t border-slate-50"><?= $c['extra'] ?></p>
    </a>
    <?php endforeach; ?>
</div>

<!-- ===== KPI Strip ===== -->
<div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-4 mb-8">
    <?php
    $kpis = [];
    if ($canUsers) {
        $kpis[] = ['label' => 'Total Users',   'value' => $totalUsers,   'sub' => $activeUsers . ' active',   'icon' => 'fa-users',          'soft' => 'bg-indigo-50',  'text' => 'text-indigo-500'];
        $kpis[] = ['label' => 'New This Month','value' => $newUsersMtd,  'sub' => 'accounts created',         'icon' => 'fa-user-plus',      'soft' => 'bg-violet-50',  'text' => 'text-violet-500'];
    }
    if ($canRoles) {
        $kpis[] = ['label' => 'Roles',         'value' => $totalRoles,   'sub' => $assignments . ' assigned', 'icon' => 'fa-user-shield',    'soft' => 'bg-blue-50',    'text' => 'text-blue-500'];
    }
    if ($canPerms) {
        $kpis[] = ['label' => 'Permissions',   'value' => $totalPerms,   'sub' => $permModules . ' modules',  'icon' => 'fa-key',            'soft' => 'bg-amber-50',   'text' => 'text-amber-500'];
    }
    if ($canPeople) {
        $kpis[] = ['label' => 'People',        'value' => $totalPeople,  'sub' => $peopleLinked . ' linked',  'icon' => 'fa-address-book',   'soft' => 'bg-teal-50',    'text' => 'text-teal-500'];
    }
    if ($canUsers) {
        $kpis[] = ['label' => 'Unassigned',    'value' => max(0, $unassigned), 'sub' => 'no role yet',        'icon' => 'fa-user-slash',     'soft' => 'bg-red-50',     'text' => 'text-red-500'];
    }
    if ($canAudit) {
        $kpis[] = ['label' => 'Activity Today','value' => $actsToday,          'sub' => $loginsToday . ' logins', 'icon' => 'fa-chart-line',   'soft' => 'bg-green-50',   'text' => 'text-green-500'];
        $kpis[] = ['label' => 'Events / 30d',  'value' => number_format($acts30), 'sub' => 'audit trail',     'icon' => 'fa-history',        'soft' => 'bg-slate-100',  'text' => 'text-slate-500'];
    }
    ?>
    <?php foreach ($kpis as $k): ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide"><?= $k['label'] ?></span>
            <div class="w-8 h-8 rounded-lg <?= $k['soft'] ?> flex items-center justify-center">
                <i class="fas <?= $k['icon'] ?> <?= $k['text'] ?> text-xs"></i>
            </div>
        </div>
        <p class="text-xl font-bold text-slate-800"><?= is_string($k['value']) ? $k['value'] : number_format($k['value']) ?></p>
        <p class="text-[11px] text-slate-400 mt-0.5"><?= $k['sub'] ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- ===== Charts Row 1: Access control ===== -->
<?php if ($canUsers || $canRoles || $canPerms): ?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <?php if ($canUsers): ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-pie text-indigo-500 text-xs"></i> Users by Role
        </h3>
        <div id="usersByRoleChart" style="min-height: 260px"></div>
    </div>
    <?php endif; ?>
    <?php if ($canRoles): ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-shield-halved text-violet-500 text-xs"></i> Permissions Granted per Role
        </h3>
        <div id="roleCoverageChart" style="min-height: 260px"></div>
    </div>
    <?php endif; ?>
    <?php if ($canPerms): ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-key text-amber-500 text-xs"></i> Permissions by Module
        </h3>
        <div id="permsByModuleChart" style="min-height: 260px"></div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ===== Charts Row 2: Activity ===== -->
<?php if ($canAudit || $canUsers): ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <?php if ($canAudit): ?>
    <div class="<?= $canUsers ? '' : 'lg:col-span-2' ?> bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-line text-green-500 text-xs"></i> System Activity — Last 90 Days
        </h3>
        <div id="activityTrendChart" style="min-height: 280px"></div>
    </div>
    <?php endif; ?>
    <?php if ($canUsers): ?>
    <div class="<?= $canAudit ? '' : 'lg:col-span-2' ?> bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-user-plus text-blue-500 text-xs"></i> Accounts Created by Month
        </h3>
        <div id="userGrowthChart" style="min-height: 280px"></div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ===== Roles Overview + Activity by Module ===== -->
<?php if ($canRoles || $canAudit): ?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <?php if ($canRoles): ?>
    <div class="<?= $canAudit ? 'lg:col-span-2' : 'lg:col-span-3' ?> bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-user-shield text-indigo-500 text-xs"></i> Roles Overview
        </h3>
        <?php if (empty($rolesList)): ?>
        <p class="text-sm text-slate-400 text-center py-6">No roles defined</p>
        <?php else: ?>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100">
                    <th class="text-left text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2">Role</th>
                    <th class="text-right text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2">Permissions</th>
                    <th class="text-right text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2">Users</th>
                    <th class="text-left text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2 pl-4">Coverage</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($rolesList as $r): $pct = $totalPerms > 0 ? round(($r['perm_count'] / $totalPerms) * 100) : 0; ?>
                <tr class="hover:bg-slate-50">
                    <td class="py-3 font-medium text-slate-800">
                        <?= htmlspecialchars($r['name']) ?>
                        <div class="text-[11px] text-slate-400 font-normal"><?= date('M Y', strtotime($r['created_at'])) ?></div>
                    </td>
                    <td class="py-3 text-right text-slate-600 font-mono text-xs"><?= number_format($r['perm_count']) ?></td>
                    <td class="py-3 text-right text-slate-600 font-mono text-xs"><?= number_format($r['user_count']) ?></td>
                    <td class="py-3 pl-4">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-indigo-500 rounded-full" style="width: <?= min(100, $pct) ?>%"></div>
                            </div>
                            <span class="text-[11px] text-slate-400 w-9 text-right"><?= $pct ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($canAudit): ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-layer-group text-teal-500 text-xs"></i> Activity by Module
        </h3>
        <div id="activityByModuleChart" style="min-height: 260px"></div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ===== Recent Users + Activity Feed ===== -->
<?php if ($canUsers || $canAudit): ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <?php if ($canUsers): ?>
    <div class="<?= $canAudit ? '' : 'lg:col-span-2' ?> bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                <i class="fas fa-user-clock text-indigo-500 text-xs"></i> Recently Created Users
            </h3>
            <a href="<?= url('modules/users/index.php') ?>" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View all</a>
        </div>
        <?php if (empty($recentUsers)): ?>
        <p class="text-sm text-slate-400 text-center py-6">No users yet</p>
        <?php else: ?>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($recentUsers as $u): ?>
                <tr class="hover:bg-slate-50">
                    <td class="py-2.5">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center flex-shrink-0">
                                <span class="text-xs font-bold text-indigo-600"><?= strtoupper(substr($u['name'], 0, 1)) ?></span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-800 truncate"><?= htmlspecialchars($u['name']) ?></p>
                                <p class="text-xs text-slate-400 truncate"><?= htmlspecialchars($u['email']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="py-2.5 text-xs text-slate-500 whitespace-nowrap"><?= htmlspecialchars($u['roles']) ?></td>
                    <td class="py-2.5 text-right">
                        <span class="px-2 py-0.5 text-xs rounded-full <?= $statusColors[$u['status']] ?? 'bg-gray-100 text-gray-800' ?>"><?= ucfirst($u['status']) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($canAudit): ?>
    <div class="<?= $canUsers ? '' : 'lg:col-span-2' ?> bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-history text-slate-400 text-xs"></i> Latest System Activity
        </h3>
        <?php if (empty($recentActivity)): ?>
        <p class="text-sm text-slate-400 text-center py-6">No activity recorded</p>
        <?php else: ?>
        <div class="space-y-0">
            <?php foreach ($recentActivity as $a): ?>
            <div class="flex items-center justify-between py-2 border-b border-slate-50 last:border-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-7 h-7 rounded-lg bg-slate-50 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-circle text-[5px] text-slate-300"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm text-slate-700 truncate"><?= htmlspecialchars($a['action']) ?></p>
                        <p class="text-xs text-slate-400 truncate">
                            <?= htmlspecialchars($a['user_name'] ?? 'System') ?>
                            <?php if (!empty($a['module'])): ?> &middot; <span class="font-mono"><?= htmlspecialchars($a['module']) ?></span><?php endif; ?>
                        </p>
                    </div>
                </div>
                <span class="text-xs text-slate-400 whitespace-nowrap ml-3"><?= date('M d, H:i', strtotime($a['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ===== Quick Actions ===== -->
<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 mb-8">
    <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
        <i class="fas fa-bolt text-indigo-500 text-xs"></i> Quick Actions
    </h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <?php if (hasPermission('users.create')): ?>
        <a href="<?= url('modules/users/create.php') ?>" class="flex items-center gap-3 p-4 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
            <i class="fas fa-user-plus text-indigo-600 text-xl"></i>
            <div><div class="font-medium text-sm">New User</div><div class="text-xs text-gray-500">Create account</div></div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('roles.create')): ?>
        <a href="<?= url('modules/roles/create.php') ?>" class="flex items-center gap-3 p-4 bg-violet-50 rounded-lg hover:bg-violet-100 transition">
            <i class="fas fa-user-shield text-violet-600 text-xl"></i>
            <div><div class="font-medium text-sm">New Role</div><div class="text-xs text-gray-500">Group permissions</div></div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('permissions.create')): ?>
        <a href="<?= url('modules/permissions/create.php') ?>" class="flex items-center gap-3 p-4 bg-amber-50 rounded-lg hover:bg-amber-100 transition">
            <i class="fas fa-key text-amber-600 text-xl"></i>
            <div><div class="font-medium text-sm">New Permission</div><div class="text-xs text-gray-500">Add capability</div></div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('people_management.create')): ?>
        <a href="<?= url('modules/people_management/create.php') ?>" class="flex items-center gap-3 p-4 bg-teal-50 rounded-lg hover:bg-teal-100 transition">
            <i class="fas fa-address-book text-teal-600 text-xl"></i>
            <div><div class="font-medium text-sm">New Person</div><div class="text-xs text-gray-500">Directory entry</div></div>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Charts JS ===== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const render = window.renderCarbonChart;
    if (!render) return;

    const palette = <?= json_encode($palette) ?>;

    // Users by Role (doughnut)
    const urEl = document.getElementById('usersByRoleChart');
    if (urEl) {
        const labels = <?= json_encode(array_column($usersByRole, 'label')) ?>;
        const data   = <?= json_encode(array_map('intval', array_column($usersByRole, 'cnt'))) ?>;
        render(urEl, 'doughnut', {
            labels: labels,
            datasets: [{ data: data, backgroundColor: palette.slice(0, data.length), borderWidth: 0 }]
        }, { legend: { position: 'bottom' } });
    }

    // Permissions granted per role (bar)
    const rcEl = document.getElementById('roleCoverageChart');
    if (rcEl) {
        const labels = <?= json_encode(array_column($roleCoverage, 'label')) ?>;
        const data   = <?= json_encode(array_map('intval', array_column($roleCoverage, 'cnt'))) ?>;
        render(rcEl, 'bar', {
            labels: labels,
            datasets: [{ label: 'Permissions', data: data, backgroundColor: '#8b5cf6' }]
        }, { scales: { y: { beginAtZero: true } }, legend: { enabled: false } });
    }

    // Permissions by module (horizontal bar)
    const pmEl = document.getElementById('permsByModuleChart');
    if (pmEl) {
        const labels = <?= json_encode(array_column($permsByModule, 'label')) ?>;
        const data   = <?= json_encode(array_map('intval', array_column($permsByModule, 'cnt'))) ?>;
        render(pmEl, 'bar', {
            labels: labels,
            datasets: [{ label: 'Permissions', data: data, backgroundColor: '#f59e0b' }]
        }, { scales: { y: { beginAtZero: true } }, legend: { enabled: false } });
    }

    // Activity trend (line)
    const atEl = document.getElementById('activityTrendChart');
    if (atEl) {
        const labels = <?= json_encode(array_map(fn($r) => date('M j', strtotime($r['label'])), $activityTrend)) ?>;
        const data   = <?= json_encode(array_map('intval', array_column($activityTrend, 'cnt'))) ?>;
        render(atEl, 'line', {
            labels: labels,
            datasets: [{
                label: 'Events',
                data: data,
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34,197,94,0.10)',
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#22c55e',
                pointRadius: 3,
                borderWidth: 2
            }]
        }, { legend: { enabled: false }, curve: 'curveMonotoneX', points: { radius: 3 } });
    }

    // Account growth (bar)
    const ugEl = document.getElementById('userGrowthChart');
    if (ugEl) {
        const labels = <?= json_encode(array_column($userGrowth, 'label')) ?>;
        const data   = <?= json_encode(array_map('intval', array_column($userGrowth, 'cnt'))) ?>;
        render(ugEl, 'bar', {
            labels: labels,
            datasets: [{ label: 'New Accounts', data: data, backgroundColor: '#6366f1' }]
        }, { scales: { y: { beginAtZero: true } }, legend: { enabled: false } });
    }

    // Activity by module (doughnut)
    const amEl = document.getElementById('activityByModuleChart');
    if (amEl) {
        const labels = <?= json_encode(array_map(fn($r) => $r['label'], $activityByModule)) ?>;
        const data   = <?= json_encode(array_map('intval', array_column($activityByModule, 'cnt'))) ?>;
        render(amEl, 'donut', {
            labels: labels,
            datasets: [{ data: data, backgroundColor: palette.slice(0, data.length), borderWidth: 0 }]
        }, { legend: { position: 'bottom' } });
    }
});
</script>

</div><!-- /dashboard-old-content -->

<?php
require_once '../../includes/dashboard_template.php';
require_once '../../includes/footer.php'; ?>
