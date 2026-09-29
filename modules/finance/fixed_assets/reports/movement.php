<?php
$pageTitle = 'Asset Movement Report';
require_once '../../../../includes/header.php';

if (!hasPermission('fixedasset.report')) { die('Access Denied'); }

$db = getDB();

$search = $_GET['search'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$transfer_type = $_GET['transfer_type'] ?? '';

$where = "WHERE 1=1";
$params = [];

if ($search) { 
    $where .= " AND (a.asset_code LIKE ? OR a.asset_name LIKE ?)";
    $params[] = "%$search%"; 
    $params[] = "%$search%"; 
}
if ($from_date) { $where .= " AND t.transfer_date >= ?"; $params[] = $from_date; }
if ($to_date) { $where .= " AND t.transfer_date <= ?"; $params[] = $to_date; }
if ($transfer_type) { $where .= " AND t.transfer_type = ?"; $params[] = $transfer_type; }

$transfers = $db->prepare("
    SELECT 
        t.id as transfer_id,
        a.asset_code,
        a.asset_name,
        ac.category_name,
        t.transfer_date,
        'department' as transfer_type,
        fd.department_name as from_department,
        td.department_name as to_department,
        fp.project_name as from_project,
        tp.project_name as to_project,
        t.transfer_reason as notes,
        u.name as created_by
    FROM asset_transfer_history t
    LEFT JOIN fixed_asset_financials f ON t.asset_id = f.asset_id
    LEFT JOIN assets a ON f.asset_id = a.id
    LEFT JOIN fixed_asset_categories fac ON f.asset_category_id = fac.id
    LEFT JOIN asset_categories ac ON fac.asset_category_id = ac.id
    LEFT JOIN departments fd ON t.from_department_id = fd.id
    LEFT JOIN departments td ON t.to_department_id = td.id
    LEFT JOIN projects fp ON t.from_project_id = fp.id
    LEFT JOIN projects tp ON t.to_project_id = tp.id
    LEFT JOIN users u ON t.approved_by = u.id
    $where
    ORDER BY t.transfer_date DESC
");
$transfers->execute($params);
$transfers = $transfers->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Asset Movement Report</h1>
        <p class="text-gray-600">Transfers between departments, projects, and locations</p>
    </div>
    <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
        <i class="fas fa-print mr-2"></i>Print
    </button>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <input type="hidden" name="report" value="movement">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Asset code or name" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
            <input type="date" name="from_date" value="<?= sanitize($from_date) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
            <input type="date" name="to_date" value="<?= sanitize($to_date) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <select name="transfer_type" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Types</option>
                <option value="department" <?= $transfer_type === 'department' ? 'selected' : '' ?>>Department</option>
                <option value="project" <?= $transfer_type === 'project' ? 'selected' : '' ?>>Project</option>
                <option value="location" <?= $transfer_type === 'location' ? 'selected' : '' ?>>Location</option>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-search"></i></button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left">Asset Code</th>
                <th class="px-4 py-3 text-left">Asset Name</th>
                <th class="px-4 py-3 text-left">Category</th>
                <th class="px-4 py-3 text-center">Date</th>
                <th class="px-4 py-3 text-center">Type</th>
                <th class="px-4 py-3 text-left">From</th>
                <th class="px-4 py-3 text-left">To</th>
                <th class="px-4 py-3 text-left">Notes</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($transfers as $transfer): ?>
            <tr>
                <td class="px-4 py-3 font-medium"><?= sanitize($transfer['asset_code']) ?></td>
                <td class="px-4 py-3"><?= sanitize($transfer['asset_name']) ?></td>
                <td class="px-4 py-3"><?= sanitize($transfer['category_name']) ?></td>
                <td class="px-4 py-3 text-center"><?= date('M d, Y', strtotime($transfer['transfer_date'])) ?></td>
                <td class="px-4 py-3 text-center">
                    <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">
                        <?= str_replace('_', ' ', ucfirst($transfer['transfer_type'])) ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-gray-600">
                    <?php if ($transfer['transfer_type'] == 'department'): ?>
                        <?= sanitize($transfer['from_department'] ?? '-') ?> → <?= sanitize($transfer['to_department'] ?? '-') ?>
                    <?php elseif ($transfer['transfer_type'] == 'project'): ?>
                        <?= sanitize($transfer['from_project'] ?? '-') ?> → <?= sanitize($transfer['to_project'] ?? '-') ?>
                    <?php else: ?>
                        <?= sanitize($transfer['from_location'] ?? '-') ?> → <?= sanitize($transfer['to_location'] ?? '-') ?>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-gray-600 text-xs" title="<?= sanitize($transfer['notes']) ?>"><?= sanitize(substr($transfer['notes'], 0, 50)) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
