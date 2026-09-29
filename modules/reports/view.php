<?php
require_once '../../helpers/report_engine.php';

$type = $_GET['type'] ?? '';
$pageTitle = getReportTitle($type) . ' Report';
require_once '../../includes/header.php';

if (!hasPermission('reports.view')) {
    die('<div class="p-8 text-center text-red-600 font-semibold">Access Denied</div>');
}

if (!$type) {
    header('Location: index.php'); exit;
}

$db = getDB();

// Collect filters
$filters = [
    'date_from'     => $_GET['date_from'] ?? date('Y-m-01'),
    'date_to'       => $_GET['date_to']   ?? date('Y-m-d'),
    'department_id' => $_GET['department_id'] ?? '',
    'employee_id'   => $_GET['employee_id']   ?? '',
    'project_id'    => $_GET['project_id']    ?? '',
    'client_id'     => $_GET['client_id']     ?? '',
    'status'        => $_GET['status']        ?? '',
    'category_id'   => $_GET['category_id']   ?? '',
    'priority'      => $_GET['priority']      ?? '',
];

$data = getReportData($db, $type, $filters);
$columns = getReportColumns($type);
$title = getReportTitle($type);

logAudit($_SESSION['user_id'], 'generated_report_' . $type, 'reports');

// Filter options
$departments = $db->query("SELECT id, department_name FROM departments WHERE status='active' ORDER BY department_name")->fetchAll();
$employees   = $db->query("SELECT id, CONCAT(first_name,' ',last_name) as name FROM employees WHERE status='active' ORDER BY first_name")->fetchAll();
?>

<div class="mb-4 flex justify-between items-center flex-wrap gap-3">
    <div>
        <a href="index.php" class="text-blue-600 hover:underline text-sm"><i class="fas fa-arrow-left mr-1"></i>Reports Center</a>
        <h1 class="text-2xl font-bold text-gray-800 mt-1"><?= sanitize($title) ?></h1>
    </div>
    <?php if (hasPermission('reports.export')): ?>
    <div class="flex gap-2">
        <a href="export.php?type=<?= urlencode($type) ?>&format=csv&<?= http_build_query($filters) ?>"
           class="bg-green-600 text-white px-3 py-2 rounded hover:bg-green-700 text-sm transition">
            <i class="fas fa-file-csv mr-1"></i>CSV
        </a>
        <a href="export.php?type=<?= urlencode($type) ?>&format=pdf&<?= http_build_query($filters) ?>"
           class="bg-red-600 text-white px-3 py-2 rounded hover:bg-red-700 text-sm transition">
            <i class="fas fa-file-pdf mr-1"></i>PDF
        </a>
        <a href="print.php?type=<?= urlencode($type) ?>&<?= http_build_query($filters) ?>" target="_blank"
           class="bg-gray-600 text-white px-3 py-2 rounded hover:bg-gray-700 text-sm transition">
            <i class="fas fa-print mr-1"></i>Print
        </a>
        <button onclick="saveReport()" class="bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-700 text-sm transition">
            <i class="fas fa-bookmark mr-1"></i>Save
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow mb-5">
    <form method="GET" class="p-4 flex flex-wrap gap-3 items-end">
        <input type="hidden" name="type" value="<?= sanitize($type) ?>">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">From</label>
            <input type="date" name="date_from" value="<?= sanitize($filters['date_from']) ?>"
                class="px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">To</label>
            <input type="date" name="date_to" value="<?= sanitize($filters['date_to']) ?>"
                class="px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Department</label>
            <select name="department_id" class="px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
                <option value="">All Departments</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $filters['department_id'] == $d['id'] ? 'selected' : '' ?>>
                    <?= sanitize($d['department_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
            <select name="status" class="px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="active"    <?= $filters['status'] === 'active'    ? 'selected' : '' ?>>Active</option>
                <option value="inactive"  <?= $filters['status'] === 'inactive'  ? 'selected' : '' ?>>Inactive</option>
                <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="pending"   <?= $filters['status'] === 'pending'   ? 'selected' : '' ?>>Pending</option>
                <option value="open"      <?= $filters['status'] === 'open'      ? 'selected' : '' ?>>Open</option>
                <option value="closed"    <?= $filters['status'] === 'closed'    ? 'selected' : '' ?>>Closed</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm transition">
                <i class="fas fa-filter mr-1"></i>Apply
            </button>
            <a href="view.php?type=<?= urlencode($type) ?>" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300 text-sm transition">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<?php $summary = getReportSummary($type, $data); ?>
<?php if (!empty($summary)): ?>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
    <?php foreach ($summary as $card): ?>
    <div class="bg-white rounded-lg shadow p-4">
        <p class="text-xs text-gray-500 uppercase font-medium"><?= sanitize($card['label']) ?></p>
        <p class="text-2xl font-bold text-gray-800 mt-1"><?= sanitize($card['value']) ?></p>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Data Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-5 py-3 border-b flex justify-between items-center">
        <span class="font-medium text-gray-700"><?= count($data) ?> records found</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <?php foreach ($columns as $col): ?>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= sanitize($col['label']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($data)): ?>
                <tr><td colspan="<?= count($columns) ?>" class="px-4 py-8 text-center text-gray-500">No data found for selected filters</td></tr>
                <?php else: ?>
                <?php foreach ($data as $row): ?>
                <tr class="hover:bg-gray-50">
                    <?php foreach ($columns as $col): ?>
                    <td class="px-4 py-3 text-gray-700"><?= sanitize((string)($row[$col['key']] ?? '-')) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Save Report Modal -->
<div id="saveModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-6 w-96">
        <h3 class="text-lg font-semibold mb-4">Save Report</h3>
        <form method="POST" action="save.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="report_type" value="<?= sanitize($type) ?>">
            <input type="hidden" name="filters" value="<?= sanitize(json_encode($filters)) ?>">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Report Name</label>
                <input type="text" name="name" value="<?= sanitize($title) ?> - <?= date('Y-m-d') ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('saveModal').classList.add('hidden')"
                    class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 text-sm">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function saveReport() {
    document.getElementById('saveModal').classList.remove('hidden');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
