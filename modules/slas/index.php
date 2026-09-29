<?php
$pageTitle = 'SLA Management';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('slas.view')) { die('Access Denied'); }

$db = getDB();
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (sla_name LIKE ?)";
    $params[] = "%$search%";
}
if ($status) { $where .= " AND status = ?"; $params[] = $status; }

$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM slas $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("SELECT * FROM slas $where ORDER BY priority ASC, sla_name ASC LIMIT ? OFFSET ?");
$stmt->execute($queryParams);
$slas = $stmt->fetchAll();

$totalSLA = $db->query("SELECT COUNT(*) as c FROM slas")->fetch()['c'];
$activeSLA = $db->query("SELECT COUNT(*) as c FROM slas WHERE status = 'active'")->fetch()['c'];
$breachedTickets = $db->query("SELECT COUNT(*) as c FROM tickets WHERE sla_breached = 1 AND status NOT IN ('resolved', 'closed')")->fetch()['c'];
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">SLA Management</h1>
        <p class="text-gray-600">Service Level Agreement policies</p>
    </div>
    <?php if (hasPermission('slas.create')): ?>
    <a href="<?= url('modules/slas/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New SLA</a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Total SLA Policies</div>
        <div class="text-2xl font-bold text-gray-800"><?= $totalSLA ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Active Policies</div>
        <div class="text-2xl font-bold text-green-600"><?= $activeSLA ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">SLA Breached Tickets</div>
        <div class="text-2xl font-bold text-red-600"><?= $breachedTickets ?></div>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="SLA name..." class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-search"></i></button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SLA Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Response Time</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Resolution Time</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($slas as $sla): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= sanitize($sla['sla_name']) ?></div>
                    <?php if ($sla['description']): ?>
                    <div class="text-sm text-gray-500"><?= sanitize(substr($sla['description'], 0, 60)) ?><?= strlen($sla['description']) > 60 ? '...' : '' ?></div>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4">
                    <?php
                    $priorityColors = ['low' => 'bg-gray-100 text-gray-700', 'medium' => 'bg-blue-100 text-blue-800', 'high' => 'bg-orange-100 text-orange-800', 'critical' => 'bg-red-100 text-red-800'];
                    $pClass = $priorityColors[$sla['priority']] ?? 'bg-gray-100 text-gray-700';
                    ?>
                    <span class="px-2 py-1 text-xs rounded font-medium <?= $pClass ?>"><?= ucfirst($sla['priority']) ?></span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= $sla['response_time_hours'] ?> hours</td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= $sla['resolution_time_hours'] ?> hours</td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs rounded <?= $sla['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>"><?= ucfirst($sla['status']) ?></span>
                </td>
                <td class="px-6 py-4 text-right">
                    <?php if (hasPermission('slas.edit')): ?>
                    <a href="<?= url('modules/slas/edit.php?id=' . $sla['id']) ?>" class="text-blue-600 hover:text-blue-900 mr-2"><i class="fas fa-edit"></i></a>
                    <?php endif; ?>
                    <?php if (hasPermission('slas.delete')): ?>
                    <a href="<?= url('modules/slas/delete.php?id=' . $sla['id']) ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Delete this SLA policy?')"><i class="fas fa-trash"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($slas)): ?>
            <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No SLA policies found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../includes/footer.php'; ?>


