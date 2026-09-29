<?php
$pageTitle = 'Activity Logs';
require_once '../../includes/header.php';

if (!hasPermission('auditlogs.view')) {
    die('<div class="p-8 text-center text-red-600 font-semibold">Access Denied</div>');
}

$db = getDB();
logAudit($_SESSION['user_id'], 'viewed_activity_logs', 'auditlogs');

// Filters
$search    = trim($_GET['search']    ?? '');
$module    = trim($_GET['module']    ?? '');
$user_id   = trim($_GET['user_id']   ?? '');
$date_from = trim($_GET['date_from'] ?? date('Y-m-01'));
$date_to   = trim($_GET['date_to']   ?? date('Y-m-d'));
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 25;

$where  = "WHERE al.created_at BETWEEN ? AND ?";
$params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];

if ($search) {
    $where .= " AND (al.action LIKE ? OR al.module LIKE ? OR u.name LIKE ? OR al.ip_address LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($module)  { $where .= " AND al.module=?";  $params[] = $module; }
if ($user_id) { $where .= " AND al.user_id=?"; $params[] = $user_id; }

$countStmt = $db->prepare("SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON al.user_id=u.id $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$totalPages = max(1, ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT al.*, u.name AS user_name
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id=u.id
    $where ORDER BY al.created_at DESC LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$logs = $stmt->fetchAll();

// Filter options
$modules = $db->query("SELECT DISTINCT module FROM audit_logs ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
$users   = $db->query("SELECT id, name FROM users ORDER BY name")->fetchAll();

// Stats
$todayCount  = $db->query("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$weekCount   = $db->query("SELECT COUNT(*) FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$uniqueUsers = $db->query("SELECT COUNT(DISTINCT user_id) FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Activity Logs</h1>
        <p class="text-gray-600">System-wide audit trail</p>
    </div>
    <?php if (hasPermission('reports.export')): ?>
    <a href="export.php?<?= http_build_query(array_filter(['search'=>$search,'module'=>$module,'user_id'=>$user_id,'date_from'=>$date_from,'date_to'=>$date_to])) ?>"
       class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 text-sm transition">
        <i class="fas fa-file-csv mr-1"></i>Export CSV
    </a>
    <?php endif; ?>
</div>

<!-- Stats -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 flex items-center gap-3">
        <div class="bg-blue-500 text-white rounded-full w-10 h-10 flex items-center justify-center">
            <i class="fas fa-calendar-day text-sm"></i>
        </div>
        <div><p class="text-xl font-bold text-gray-800"><?= number_format($todayCount) ?></p><p class="text-xs text-gray-500">Today's Events</p></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 flex items-center gap-3">
        <div class="bg-purple-500 text-white rounded-full w-10 h-10 flex items-center justify-center">
            <i class="fas fa-calendar-week text-sm"></i>
        </div>
        <div><p class="text-xl font-bold text-gray-800"><?= number_format($weekCount) ?></p><p class="text-xs text-gray-500">This Week</p></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 flex items-center gap-3">
        <div class="bg-green-500 text-white rounded-full w-10 h-10 flex items-center justify-center">
            <i class="fas fa-users text-sm"></i>
        </div>
        <div><p class="text-xl font-bold text-gray-800"><?= $uniqueUsers ?></p><p class="text-xs text-gray-500">Active Users (7d)</p></div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow mb-5">
    <form method="GET" class="p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>"
                placeholder="Action, module, user, IP..."
                class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Module</label>
            <select name="module" class="px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
                <option value="">All Modules</option>
                <?php foreach ($modules as $m): ?>
                <option value="<?= sanitize($m) ?>" <?= $module === $m ? 'selected' : '' ?>><?= sanitize(ucfirst($m)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">User</label>
            <select name="user_id" class="px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
                <option value="">All Users</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $user_id == $u['id'] ? 'selected' : '' ?>><?= sanitize($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">From</label>
            <input type="date" name="date_from" value="<?= sanitize($date_from) ?>"
                class="px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">To</label>
            <input type="date" name="date_to" value="<?= sanitize($date_to) ?>"
                class="px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm transition">
                <i class="fas fa-search mr-1"></i>Filter
            </button>
            <a href="index.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300 text-sm transition">Reset</a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-5 py-3 border-b flex justify-between items-center text-sm text-gray-600">
        <span><?= number_format($total) ?> records found</span>
        <span>Page <?= $page ?> of <?= $totalPages ?></span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Module</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Timestamp</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($logs)): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No activity logs found</td></tr>
                <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-blue-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                <?= strtoupper(substr($log['user_name'] ?? 'S', 0, 1)) ?>
                            </div>
                            <span class="text-gray-700"><?= sanitize($log['user_name'] ?? 'System') ?></span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-gray-700 font-mono text-xs"><?= sanitize($log['action']) ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs"><?= sanitize($log['module']) ?></span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 font-mono text-xs"><?= sanitize($log['ip_address'] ?? '-') ?></td>
                    <td class="px-4 py-3 text-gray-500 text-xs"><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="flex justify-between items-center mt-4">
    <div class="text-sm text-gray-600">
        Showing <?= number_format(($page-1)*$perPage+1) ?> to <?= number_format(min($page*$perPage,$total)) ?> of <?= number_format($total) ?>
    </div>
    <div class="flex gap-1">
        <?php
        $qs = http_build_query(array_filter(['search'=>$search,'module'=>$module,'user_id'=>$user_id,'date_from'=>$date_from,'date_to'=>$date_to]));
        $start = max(1, $page-2); $end = min($totalPages, $page+2);
        if ($page > 1): ?>
        <a href="?<?= $qs ?>&page=<?= $page-1 ?>" class="px-3 py-1 border rounded bg-white text-gray-700 hover:bg-gray-100 text-sm">‹</a>
        <?php endif;
        for ($i=$start; $i<=$end; $i++): ?>
        <a href="?<?= $qs ?>&page=<?= $i ?>" class="px-3 py-1 border rounded text-sm <?= $i===$page ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' ?>"><?= $i ?></a>
        <?php endfor;
        if ($page < $totalPages): ?>
        <a href="?<?= $qs ?>&page=<?= $page+1 ?>" class="px-3 py-1 border rounded bg-white text-gray-700 hover:bg-gray-100 text-sm">›</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
