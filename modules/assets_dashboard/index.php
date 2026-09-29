<?php
$pageTitle = 'Asset Dashboard';
$dashboardKey = 'assets';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('assets.view')) { die('Access Denied'); }

$db = getDB();

$totalAssets = $db->query("SELECT COUNT(*) as c FROM assets")->fetch()['c'];
$availableAssets = $db->query("SELECT COUNT(*) as c FROM assets WHERE status = 'available'")->fetch()['c'];
$assignedAssets = $db->query("SELECT COUNT(*) as c FROM assets WHERE status = 'assigned'")->fetch()['c'];
$maintenanceAssets = $db->query("SELECT COUNT(*) as c FROM assets WHERE status = 'maintenance'")->fetch()['c'];

$expiringWarranties = $db->query("SELECT * FROM assets WHERE warranty_expiry <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) AND warranty_expiry >= CURRENT_DATE ORDER BY warranty_expiry LIMIT 5")->fetchAll();
$expiredWarranties = $db->query("SELECT COUNT(*) as c FROM assets WHERE warranty_expiry < CURRENT_DATE AND warranty_expiry IS NOT NULL")->fetch()['c'];

$expiringLicenses = $db->query("SELECT * FROM software_licenses WHERE expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) AND expiry_date >= CURRENT_DATE AND status = 'active' ORDER BY expiry_date LIMIT 5")->fetchAll();
$expiredLicenses = $db->query("SELECT COUNT(*) as c FROM software_licenses WHERE expiry_date < CURRENT_DATE AND status = 'active'")->fetch()['c'];

$lowInventory = $db->query("SELECT * FROM inventory_items WHERE quantity <= min_quantity AND status = 'active' ORDER BY quantity LIMIT 5")->fetchAll();

$assetsByCategory = $db->query("
    SELECT c.category_name, COUNT(a.id) as count 
    FROM assets a 
    JOIN asset_categories c ON a.category_id = c.id 
    GROUP BY c.id, c.category_name 
    ORDER BY count DESC
")->fetchAll();

$recentAssignments = $db->query("
    SELECT aa.*, a.asset_code, a.asset_name, e.first_name, e.last_name 
    FROM asset_assignments aa 
    JOIN assets a ON aa.asset_id = a.id 
    JOIN employees e ON aa.employee_id = e.id 
    ORDER BY aa.assigned_date DESC 
    LIMIT 5
")->fetchAll();

$totalAssetValue = $db->query("SELECT COALESCE(SUM(purchase_cost), 0) as total FROM assets WHERE status != 'retired'")->fetch()['total'];

// Warranty status buckets for the donut (valid / expiring soon / expired)
$warrantyValid = $db->query("SELECT COUNT(*) as c FROM assets WHERE warranty_expiry IS NULL OR warranty_expiry > DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)")->fetch()['c'];
$warrantyExpiring = count($expiringWarranties);
$warrantyExpired  = (int)$expiredWarranties;

// License status buckets for the donut (active / expiring soon / expired)
$activeLicenses = $db->query("SELECT COUNT(*) as c FROM software_licenses WHERE status='active' AND (expiry_date IS NULL OR expiry_date > DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY))")->fetch()['c'];
$licenseExpiring = count($expiringLicenses);
$licenseExpired  = (int)$expiredLicenses;
?>

<div id="dashboard-old-content">
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Asset Dashboard</h1>
    <p class="text-gray-600">Overview of IT assets and inventory</p>
</div>

<!-- Sub-Module Navigation -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <?php if (hasPermission('assets.view')): ?>
    <a href="<?= url('modules/assets/index.php') ?>" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-blue-500 flex items-center justify-center"><i class="fas fa-desktop text-white"></i></div>
        <div><div class="font-semibold text-sm">Assets</div><div class="text-xs text-gray-500">Manage assets</div></div>
    </a>
    <?php endif; ?>
    <?php if (hasPermission('assets.view')): ?>
    <a href="<?= url('modules/asset_categories/index.php') ?>" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-violet-500 flex items-center justify-center"><i class="fas fa-tags text-white"></i></div>
        <div><div class="font-semibold text-sm">Categories</div><div class="text-xs text-gray-500">Asset types</div></div>
    </a>
    <?php endif; ?>
    <?php if (hasPermission('asset_assignments.view')): ?>
    <a href="<?= url('modules/asset_assignments/index.php') ?>" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-green-500 flex items-center justify-center"><i class="fas fa-user-plus text-white"></i></div>
        <div><div class="font-semibold text-sm">Assignments</div><div class="text-xs text-gray-500">Track assignments</div></div>
    </a>
    <?php endif; ?>
    <?php if (hasPermission('maintenance.view')): ?>
    <a href="<?= url('modules/maintenance/index.php') ?>" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-amber-500 flex items-center justify-center"><i class="fas fa-tools text-white"></i></div>
        <div><div class="font-semibold text-sm">Maintenance</div><div class="text-xs text-gray-500">Service logs</div></div>
    </a>
    <?php endif; ?>
    <?php if (hasPermission('licenses.view')): ?>
    <a href="<?= url('modules/licenses/index.php') ?>" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-indigo-500 flex items-center justify-center"><i class="fas fa-key text-white"></i></div>
        <div><div class="font-semibold text-sm">Licenses</div><div class="text-xs text-gray-500">Software keys</div></div>
    </a>
    <?php endif; ?>
    <?php if (hasPermission('vendors.view')): ?>
    <a href="<?= url('modules/vendors/index.php') ?>" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-pink-500 flex items-center justify-center"><i class="fas fa-store text-white"></i></div>
        <div><div class="font-semibold text-sm">Vendors</div><div class="text-xs text-gray-500">Suppliers</div></div>
    </a>
    <?php endif; ?>
    <?php if (hasPermission('inventory.view')): ?>
    <a href="<?= url('modules/inventory/index.php') ?>" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-cyan-500 flex items-center justify-center"><i class="fas fa-boxes text-white"></i></div>
        <div><div class="font-semibold text-sm">Inventory</div><div class="text-xs text-gray-500">Stock items</div></div>
    </a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
        <div class="text-sm text-gray-500">Total Assets</div>
        <div class="text-2xl font-bold text-gray-800"><?= $totalAssets ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
        <div class="text-sm text-gray-500">Available</div>
        <div class="text-2xl font-bold text-green-600"><?= $availableAssets ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-yellow-500">
        <div class="text-sm text-gray-500">Assigned</div>
        <div class="text-2xl font-bold text-yellow-600"><?= $assignedAssets ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
        <div class="text-sm text-gray-500">Under Maintenance</div>
        <div class="text-2xl font-bold text-orange-600"><?= $maintenanceAssets ?></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Assets by Category</h2>
        <div id="assetsByCatChart"></div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Warranty Status</h2>
        <div id="warrantyChart"></div>
        <div class="mt-3 pt-3 border-t flex justify-between items-center text-sm">
            <span class="text-gray-600">Total Value</span>
            <span class="font-bold text-lg"><?= formatMoney($totalAssetValue) ?></span>
        </div>
        <?php if ($expiringWarranties): ?>
        <div class="mt-4 pt-4 border-t">
            <h3 class="text-sm font-medium text-gray-700 mb-2">Expiring Soon</h3>
            <?php foreach ($expiringWarranties as $w): ?>
            <div class="text-sm text-gray-600 flex justify-between">
                <span><?= sanitize($w['asset_code']) ?></span>
                <span class="text-orange-600"><?= date('M d', strtotime($w['warranty_expiry'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">License Status</h2>
        <div id="licenseChart"></div>
        <?php if ($expiringLicenses): ?>
        <div class="mt-4 pt-4 border-t">
            <h3 class="text-sm font-medium text-gray-700 mb-2">Expiring Soon</h3>
            <?php foreach ($expiringLicenses as $l): ?>
            <div class="text-sm text-gray-600 flex justify-between">
                <span><?= sanitize($l['software_name']) ?></span>
                <span class="text-orange-600"><?= date('M d', strtotime($l['expiry_date'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Recent Assignments</h2>
        <?php if ($recentAssignments): ?>
        <div class="space-y-2">
            <?php foreach ($recentAssignments as $a): ?>
            <div class="flex justify-between items-center py-2 border-b">
                <div>
                    <div class="font-medium"><?= sanitize($a['asset_code']) ?></div>
                    <div class="text-sm text-gray-500"><?= sanitize($a['first_name'] . ' ' . $a['last_name']) ?></div>
                </div>
                <div class="text-right">
                    <span class="px-2 py-1 text-xs rounded <?= $a['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>"><?= ucfirst($a['status']) ?></span>
                    <div class="text-xs text-gray-500"><?= date('M d', strtotime($a['assigned_date'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <a href="<?= url('modules/asset_assignments/index.php') ?>" class="block text-center text-blue-600 hover:text-blue-900 mt-4 text-sm">View All</a>
        <?php else: ?>
        <p class="text-gray-500 text-center py-4">No assignments</p>
        <?php endif; ?>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Low Inventory Alerts</h2>
        <?php if ($lowInventory): ?>
        <div class="space-y-2">
            <?php foreach ($lowInventory as $inv): ?>
            <div class="flex justify-between items-center py-2 border-b">
                <div>
                    <div class="font-medium"><?= sanitize($inv['item_name']) ?></div>
                    <div class="text-sm text-gray-500">Min: <?= $inv['min_quantity'] ?></div>
                </div>
                <div class="text-right">
                    <span class="font-bold text-red-600"><?= $inv['quantity'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <a href="<?= url('modules/inventory/index.php') ?>" class="block text-center text-blue-600 hover:text-blue-900 mt-4 text-sm">View All</a>
        <?php else: ?>
        <p class="text-gray-500 text-center py-4">All items in stock</p>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const render = window.renderCarbonChart;

    // Assets by Category → donut
    const catEl = document.getElementById('assetsByCatChart');
    if (catEl && render) {
        const labels = <?= json_encode(array_column($assetsByCategory, 'category_name')) ?>;
        const data   = <?= json_encode(array_column($assetsByCategory, 'count')) ?>;
        render(catEl, 'doughnut', {
            labels: labels.length ? labels : ['No data'],
            datasets: [{ data: data.length ? data : [1], backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4'] }]
        }, { legend: { position: 'bottom' } });
    }

    // Warranty Status → donut
    const warEl = document.getElementById('warrantyChart');
    if (warEl && render) {
        render(warEl, 'doughnut', {
            labels: ['Valid', 'Expiring Soon', 'Expired'],
            datasets: [{ data: [<?= (int)$warrantyValid ?>, <?= (int)$warrantyExpiring ?>, <?= (int)$warrantyExpired ?>], backgroundColor: ['#10b981', '#f59e0b', '#ef4444'] }]
        }, { legend: { position: 'bottom' } });
    }

    // License Status → donut
    const licEl = document.getElementById('licenseChart');
    if (licEl && render) {
        render(licEl, 'doughnut', {
            labels: ['Active', 'Expiring Soon', 'Expired'],
            datasets: [{ data: [<?= (int)$activeLicenses ?>, <?= (int)$licenseExpiring ?>, <?= (int)$licenseExpired ?>], backgroundColor: ['#10b981', '#f59e0b', '#ef4444'] }]
        }, { legend: { position: 'bottom' } });
    }
});
</script>

</div><!-- /dashboard-old-content -->
<?php
require_once '../../includes/dashboard_template.php';
require_once '../../includes/footer.php'; ?>

