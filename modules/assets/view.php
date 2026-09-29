<?php
$pageTitle = 'View Asset';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('assets.view')) { die('Access Denied'); }

$db = getDB();
$id = $_GET['id'] ?? 0;

$asset = $db->prepare("SELECT a.*, c.category_name, v.vendor_name 
    FROM assets a 
    LEFT JOIN asset_categories c ON a.category_id = c.id 
    LEFT JOIN vendors v ON a.vendor_id = v.id 
    WHERE a.id = ?");
$asset->execute([$id]);
$asset = $asset->fetch();

if (!$asset) { die('Asset not found'); }

$assignments = $db->prepare("SELECT aa.*, e.first_name, e.last_name, e.employee_code as emp_code, u.name as assigned_by_name 
    FROM asset_assignments aa 
    LEFT JOIN employees e ON aa.employee_id = e.id 
    LEFT JOIN users u ON aa.assigned_by = u.id 
    WHERE aa.asset_id = ? 
    ORDER BY aa.assigned_date DESC");
$assignments->execute([$id]);
$assignments = $assignments->fetchAll();

$maintenance = $db->prepare("SELECT * FROM asset_maintenance WHERE asset_id = ? ORDER BY maintenance_date DESC LIMIT 5");
$maintenance->execute([$id]);
$maintenance = $maintenance->fetchAll();

$currentAssignment = $db->prepare("SELECT aa.*, e.first_name, e.last_name, e.employee_code as emp_code 
    FROM asset_assignments aa 
    LEFT JOIN employees e ON aa.employee_id = e.id 
    WHERE aa.asset_id = ? AND aa.status = 'active' 
    ORDER BY aa.assigned_date DESC LIMIT 1");
$currentAssignment->execute([$id]);
$currentAssignment = $currentAssignment->fetch();

$statusColors = ['available' => 'bg-green-100 text-green-800', 'assigned' => 'bg-blue-100 text-blue-800', 'maintenance' => 'bg-yellow-100 text-yellow-800', 'damaged' => 'bg-red-100 text-red-800', 'lost' => 'bg-gray-100 text-gray-800', 'retired' => 'bg-red-200 text-red-800'];
$conditionColors = ['new' => 'bg-blue-100 text-blue-800', 'good' => 'bg-green-100 text-green-800', 'fair' => 'bg-yellow-100 text-yellow-800', 'damaged' => 'bg-red-100 text-red-800'];
?>

<div class="mb-6 flex justify-between items-center">
    <a href="<?= url('modules/assets/index.php') ?>" class="text-blue-600 hover:text-blue-900"><i class="fas fa-arrow-left mr-1"></i>Back to Assets</a>
    <?php if (hasPermission('assets.edit')): ?>
    <a href="<?= url('modules/assets/edit.php?id=' . $id . '') ?>" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600"><i class="fas fa-edit mr-2"></i>Edit</a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Asset Details</h2>
        <div class="space-y-3">
            <div class="flex justify-between"><span class="text-gray-600">Asset Code:</span><span class="font-medium"><?= sanitize($asset['asset_code']) ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Asset Name:</span><span class="font-medium"><?= sanitize($asset['asset_name']) ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Category:</span><span><?= sanitize($asset['category_name']) ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Serial Number:</span><span><?= sanitize($asset['serial_number'] ?? '-') ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Brand/Model:</span><span><?= sanitize(($asset['brand'] ?? '-') . ' / ' . ($asset['model'] ?? '-')) ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Status:</span><span class="px-2 py-1 text-xs rounded <?= $statusColors[$asset['status']] ?? 'bg-gray-100' ?>"><?= ucfirst($asset['status']) ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Condition:</span><span class="px-2 py-1 text-xs rounded <?= $conditionColors[$asset['asset_condition']] ?? 'bg-gray-100' ?>"><?= ucfirst($asset['asset_condition']) ?></span></div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Purchase & Warranty</h2>
        <div class="space-y-3">
            <div class="flex justify-between"><span class="text-gray-600">Purchase Date:</span><span><?= $asset['purchase_date'] ? date('M d, Y', strtotime($asset['purchase_date'])) : '-' ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Purchase Cost:</span><span><?= $asset['purchase_cost'] ? formatMoney($asset['purchase_cost']) : '-' ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Vendor:</span><span><?= sanitize($asset['vendor_name'] ?? '-') ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Warranty Expiry:</span><span class="<?= $asset['warranty_expiry'] && strtotime($asset['warranty_expiry']) < time() ? 'text-red-600' : '' ?>"><?= $asset['warranty_expiry'] ? date('M d, Y', strtotime($asset['warranty_expiry'])) : '-' ?></span></div>
        </div>
        
        <?php if ($currentAssignment): ?>
        <div class="mt-6 pt-4 border-t">
            <h3 class="text-md font-semibold text-gray-800 mb-3">Current Assignment</h3>
            <div class="space-y-2">
                <div class="flex justify-between"><span class="text-gray-600">Employee:</span><span class="font-medium"><?= sanitize($currentAssignment['first_name'] . ' ' . $currentAssignment['last_name']) ?></span></div>
                <div class="flex justify-between"><span class="text-gray-600">Employee ID:</span><span><?= sanitize($currentAssignment['emp_code']) ?></span></div>
                <div class="flex justify-between"><span class="text-gray-600">Assigned Date:</span><span><?= date('M d, Y', strtotime($currentAssignment['assigned_date'])) ?></span></div>
                <?php if ($currentAssignment['expected_return_date']): ?>
                <div class="flex justify-between"><span class="text-gray-600">Expected Return:</span><span><?= date('M d, Y', strtotime($currentAssignment['expected_return_date'])) ?></span></div>
                <?php endif; ?>
            </div>
            <?php if (hasPermission('assets.assign')): ?>
            <a href="<?= url('modules/asset_returns/create.php?assignment_id=' . $currentAssignment['id'] . '') ?>" class="mt-3 inline-block bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 text-sm">Process Return</a>
            <?php endif; ?>
        </div>
        <?php elseif (hasPermission('assets.assign') && $asset['status'] == 'available'): ?>
        <div class="mt-6 pt-4 border-t">
            <a href="<?= url('modules/asset_assignments/create.php?asset_id=' . $id . '') ?>" class="inline-block bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Assign to Employee</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($asset['notes']): ?>
<div class="bg-white rounded-lg shadow mb-6 p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-2">Notes</h2>
    <p class="text-gray-600"><?= nl2br(sanitize($asset['notes'])) ?></p>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow mb-6 p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Assignment History</h2>
    <?php if ($assignments): ?>
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Employee</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Assigned Date</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Expected Return</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($assignments as $a): ?>
            <tr>
                <td class="px-4 py-3"><?= sanitize($a['first_name'] . ' ' . $a['last_name']) ?></td>
                <td class="px-4 py-3"><?= date('M d, Y', strtotime($a['assigned_date'])) ?></td>
                <td class="px-4 py-3"><?= $a['expected_return_date'] ? date('M d, Y', strtotime($a['expected_return_date'])) : '-' ?></td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs rounded <?= $a['status'] == 'active' ? 'bg-green-100 text-green-800' : ($a['status'] == 'overdue' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') ?>"><?= ucfirst($a['status']) ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="text-gray-500 text-center py-4">No assignment history</p>
    <?php endif; ?>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold text-gray-800">Maintenance History</h2>
        <?php if (hasPermission('maintenance.create')): ?>
        <a href="<?= url('modules/maintenance/create.php?asset_id=' . $id . '') ?>" class="bg-yellow-600 text-white px-3 py-1 rounded hover:bg-yellow-700 text-sm"><i class="fas fa-plus mr-1"></i>Add Maintenance</a>
        <?php endif; ?>
    </div>
    <?php if ($maintenance): ?>
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Date</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Type</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Vendor</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Cost</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($maintenance as $m): ?>
            <tr>
                <td class="px-4 py-3"><?= date('M d, Y', strtotime($m['maintenance_date'])) ?></td>
                <td class="px-4 py-3"><?= ucfirst($m['maintenance_type']) ?></td>
                <td class="px-4 py-3"><?= sanitize($m['vendor_name'] ?? '-') ?></td>
                <td class="px-4 py-3 text-right"><?= $m['cost'] ? formatMoney($m['cost']) : '-' ?></td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs rounded <?= $m['status'] == 'completed' ? 'bg-green-100 text-green-800' : ($m['status'] == 'in_progress' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') ?>"><?= ucfirst($m['status']) ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="text-gray-500 text-center py-4">No maintenance records</p>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>

