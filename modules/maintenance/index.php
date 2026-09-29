<?php
$pageTitle = 'Asset Maintenance';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('maintenance.view')) { die('Access Denied'); }

$db = getDB();
$asset_id = $_GET['asset_id'] ?? '';
$maintenance_type = $_GET['maintenance_type'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($asset_id) { $where .= " AND m.asset_id = ?"; $params[] = $asset_id; }
if ($maintenance_type) { $where .= " AND m.maintenance_type = ?"; $params[] = $maintenance_type; }

$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM asset_maintenance m $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("SELECT m.*, a.asset_code, a.asset_name 
    FROM asset_maintenance m 
    JOIN assets a ON m.asset_id = a.id 
    $where 
    ORDER BY m.maintenance_date DESC 
    LIMIT ? OFFSET ?");
$stmt->execute($queryParams);
$maintenance = $stmt->fetchAll();

$assets = $db->query("SELECT id, asset_code, asset_name FROM assets ORDER BY asset_code")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Asset Maintenance</h1>
        <p class="text-gray-600">Track maintenance and repairs</p>
    </div>
    <?php if (hasPermission('maintenance.create')): ?>
    <a href="<?= url('modules/maintenance/create.php') ?>" class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700 transition"><i class="fas fa-plus mr-2"></i>Add Maintenance</a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <?php
    $totalCost = $db->query("SELECT COALESCE(SUM(cost), 0) as total FROM asset_maintenance WHERE status = 'completed'")->fetch()['total'];
    $thisMonthCost = $db->query("SELECT COALESCE(SUM(cost), 0) as total FROM asset_maintenance WHERE status = 'completed' AND MONTH(maintenance_date) = MONTH(CURRENT_DATE) AND YEAR(maintenance_date) = YEAR(CURRENT_DATE)")->fetch()['total'];
    $scheduledCount = $db->query("SELECT COUNT(*) as c FROM asset_maintenance WHERE status = 'scheduled'")->fetch()['c'];
    $inProgressCount = $db->query("SELECT COUNT(*) as c FROM asset_maintenance WHERE status = 'in_progress'")->fetch()['c'];
    ?>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Total Cost</div>
        <div class="text-2xl font-bold text-gray-800"><?= formatMoney($totalCost) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">This Month</div>
        <div class="text-2xl font-bold text-blue-600"><?= formatMoney($thisMonthCost) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Scheduled</div>
        <div class="text-2xl font-bold text-yellow-600"><?= $scheduledCount ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">In Progress</div>
        <div class="text-2xl font-bold text-orange-600"><?= $inProgressCount ?></div>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="w-48">
            <label class="block text-sm font-medium text-gray-700 mb-1">Asset</label>
            <select name="asset_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <?php foreach ($assets as $a): ?><option value="<?= $a['id'] ?>" <?= $asset_id == $a['id'] ? 'selected' : '' ?>><?= sanitize($a['asset_code']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <select name="maintenance_type" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="repair" <?= $maintenance_type === 'repair' ? 'selected' : '' ?>>Repair</option>
                <option value="upgrade" <?= $maintenance_type === 'upgrade' ? 'selected' : '' ?>>Upgrade</option>
                <option value="cleaning" <?= $maintenance_type === 'cleaning' ? 'selected' : '' ?>>Cleaning</option>
                <option value="replacement" <?= $maintenance_type === 'replacement' ? 'selected' : '' ?>>Replacement</option>
                <option value="inspection" <?= $maintenance_type === 'inspection' ? 'selected' : '' ?>>Inspection</option>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-search"></i></button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendor</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cost</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($maintenance as $m): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= sanitize($m['asset_code']) ?></div>
                    <div class="text-sm text-gray-500"><?= sanitize($m['asset_name']) ?></div>
                </td>
                <td class="px-6 py-4 text-gray-600"><?= date('M d, Y', strtotime($m['maintenance_date'])) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= ucfirst($m['maintenance_type']) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= sanitize($m['vendor_name'] ?? '-') ?></td>
                <td class="px-6 py-4 text-right font-medium"><?= $m['cost'] ? formatMoney($m['cost']) : '-' ?></td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs rounded <?= $m['status'] == 'completed' ? 'bg-green-100 text-green-800' : ($m['status'] == 'in_progress' ? 'bg-yellow-100 text-yellow-800' : ($m['status'] == 'scheduled' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800')) ?>"><?= ucfirst($m['status']) ?></span>
                </td>
                <td class="px-6 py-4 text-right">
                    <a href="<?= url('modules/assets/view.php?id=' . $m['asset_id'] . '') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-eye"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($maintenance)): ?>
            <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No maintenance records found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../includes/footer.php'; ?>

