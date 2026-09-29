<?php
$pageTitle = 'Clients';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('clients.view')) { die('Access Denied'); }

$db = getDB();
$search = $_GET['search'] ?? '';
$industry = $_GET['industry'] ?? '';
$status = $_GET['status'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (c.company_name LIKE ? OR c.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($industry) { $where .= " AND c.industry = ?"; $params[] = $industry; }
if ($status) { $where .= " AND c.status = ?"; $params[] = $status; }

$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM clients c $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("SELECT c.*, e.first_name, e.last_name FROM clients c LEFT JOIN employees e ON c.assigned_manager = e.id $where ORDER BY c.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute($queryParams);
$clients = $stmt->fetchAll();

$industries = $db->query("SELECT DISTINCT industry FROM clients WHERE industry IS NOT NULL ORDER BY industry")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Clients</h1>
        <p class="text-gray-600">Manage clients</p>
    </div>
    <?php if (hasPermission('clients.create')): ?>
    <a href="<?= url('modules/clients/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>Add Client</a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <?php
    $stats = [
        ['label' => 'Total Clients', 'value' => $total],
        ['label' => 'Active', 'value' => $db->query("SELECT COUNT(*) as c FROM clients WHERE status = 'active'")->fetch()['c']],
        ['label' => 'Inactive', 'value' => $db->query("SELECT COUNT(*) as c FROM clients WHERE status = 'inactive'")->fetch()['c']]
    ];
    foreach ($stats as $s): ?>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500"><?= $s['label'] ?></div>
        <div class="text-2xl font-bold text-gray-800"><?= $s['value'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Company name..." class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
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
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Industry</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Manager</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($clients as $client): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= sanitize($client['company_name']) ?></div>
                    <div class="text-sm text-gray-500"><?= sanitize($client['client_code']) ?></div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-gray-900"><?= sanitize($client['contact_person'] ?? '-') ?></div>
                    <div class="text-sm text-gray-500"><?= sanitize($client['email'] ?? '') ?></div>
                </td>
                <td class="px-6 py-4 text-gray-600"><?= sanitize($client['industry'] ?? '-') ?></td>
                <td class="px-6 py-4 text-gray-600"><?= sanitize($client['first_name'] ?? '-') ?></td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs rounded <?= $client['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>"><?= ucfirst($client['status']) ?></span>
                </td>
                <td class="px-6 py-4 text-right">
                    <a href="<?= url('modules/clients/view.php?id=' . $client['id'] . '') ?>" class="text-blue-600 hover:text-blue-900 mr-2"><i class="fas fa-eye"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($clients)): ?>
            <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No clients found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginationLinks($pagination, '?') ?>
<?php require_once '../../includes/footer.php'; ?>

