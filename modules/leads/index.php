<?php
$pageTitle = 'Leads';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('leads.view')) {
    die('Access Denied');
}

$db = getDB();
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$assigned_to = $_GET['assigned_to'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (l.company_name LIKE ? OR l.contact_person LIKE ? OR l.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $where .= " AND l.status = ?";
    $params[] = $status;
}

if ($priority) {
    $where .= " AND l.priority = ?";
    $params[] = $priority;
}

if ($assigned_to) {
    $where .= " AND l.assigned_to = ?";
    $params[] = $assigned_to;
}

$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM leads l $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];

$pagination = paginate($total, $perPage, $page);
$queryParams = array_merge($params, [$perPage, $pagination['offset']]);

$stmt = $db->prepare("
    SELECT l.*, e.first_name, e.last_name
    FROM leads l
    LEFT JOIN employees e ON l.assigned_to = e.id
    $where ORDER BY l.created_at DESC LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$leads = $stmt->fetchAll();

$employees = $db->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();

$statusColors = ['new' => 'bg-blue-100 text-blue-800', 'contacted' => 'bg-yellow-100 text-yellow-800', 'qualified' => 'bg-purple-100 text-purple-800', 'proposal_sent' => 'bg-indigo-100 text-indigo-800', 'negotiation' => 'bg-orange-100 text-orange-800', 'won' => 'bg-green-100 text-green-800', 'lost' => 'bg-red-100 text-red-800'];
$priorityColors = ['low' => 'bg-gray-100 text-gray-800', 'medium' => 'bg-blue-100 text-blue-800', 'high' => 'bg-orange-100 text-orange-800', 'critical' => 'bg-red-100 text-red-800'];
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Leads</h1>
        <p class="text-gray-600">Manage sales leads</p>
    </div>
    <?php if (hasPermission('leads.create')): ?>
    <a href="<?= url('modules/leads/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>Add Lead
    </a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <?php
    $stats = [
        ['label' => 'Total Leads', 'value' => $total, 'color' => 'blue'],
        ['label' => 'New', 'value' => $db->query("SELECT COUNT(*) as c FROM leads WHERE status = 'new'")->fetch()['c'], 'color' => 'blue'],
        ['label' => 'Qualified', 'value' => $db->query("SELECT COUNT(*) as c FROM leads WHERE status = 'qualified'")->fetch()['c'], 'color' => 'purple'],
        ['label' => 'Won', 'value' => $db->query("SELECT COUNT(*) as c FROM leads WHERE status = 'won'")->fetch()['c'], 'color' => 'green']
    ];
    foreach ($stats as $stat): ?>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500"><?= $stat['label'] ?></div>
        <div class="text-2xl font-bold text-gray-800"><?= $stat['value'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Company, contact..." class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="new" <?= $status === 'new' ? 'selected' : '' ?>>New</option>
                <option value="contacted" <?= $status === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                <option value="qualified" <?= $status === 'qualified' ? 'selected' : '' ?>>Qualified</option>
                <option value="proposal_sent" <?= $status === 'proposal_sent' ? 'selected' : '' ?>>Proposal Sent</option>
                <option value="negotiation" <?= $status === 'negotiation' ? 'selected' : '' ?>>Negotiation</option>
                <option value="won" <?= $status === 'won' ? 'selected' : '' ?>>Won</option>
                <option value="lost" <?= $status === 'lost' ? 'selected' : '' ?>>Lost</option>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
            <select name="priority" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Low</option>
                <option value="medium" <?= $priority === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>High</option>
                <option value="critical" <?= $priority === 'critical' ? 'selected' : '' ?>>Critical</option>
            </select>
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-search"></i></button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Assigned</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Value</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($leads as $lead): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= sanitize($lead['company_name']) ?></div>
                    <div class="text-sm text-gray-500"><?= sanitize($lead['lead_code']) ?></div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-gray-900"><?= sanitize($lead['contact_person'] ?? '-') ?></div>
                    <div class="text-sm text-gray-500"><?= sanitize($lead['email'] ?? '') ?></div>
                </td>
                <td class="px-6 py-4 text-gray-600"><?= sanitize($lead['first_name'] ?? '-') ?></td>
                <td class="px-6 py-4 text-gray-600"><?= $lead['expected_value'] ? formatMoney($lead['expected_value'], 0) : '-' ?></td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs rounded <?= $statusColors[$lead['status']] ?? 'bg-gray-100' ?>"><?= ucfirst($lead['status']) ?></span>
                </td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs rounded <?= $priorityColors[$lead['priority']] ?? 'bg-gray-100' ?>"><?= ucfirst($lead['priority']) ?></span>
                </td>
                <td class="px-6 py-4 text-right">
                    <a href="<?= url('modules/leads/view.php?id=' . $lead['id'] . '') ?>" class="text-blue-600 hover:text-blue-900 mr-2"><i class="fas fa-eye"></i></a>
                    <?php if (hasPermission('leads.edit')): ?>
                    <a href="<?= url('modules/leads/edit.php?id=' . $lead['id'] . '') ?>" class="text-yellow-600 hover:text-yellow-900 mr-2"><i class="fas fa-edit"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($leads)): ?>
            <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No leads found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../includes/footer.php'; ?>

