<?php
$pageTitle = 'Vendor Bills';
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('procurement.bills.view')) { die('Access Denied'); }

$db = getDB();
$filters = [
    'status' => $_GET['status'] ?? '',
    'vendor_id' => $_GET['vendor_id'] ?? '',
    'company_id' => $_GET['company_id'] ?? '',
    'match_status' => $_GET['match_status'] ?? '',
    'search' => trim((string)($_GET['search'] ?? '')),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$result = BillService::listBills($db, $filters, $perPage, $offset);
$bills = $result['rows'];
$total = $result['total'];
$totalPages = max(1, (int)ceil($total / $perPage));

$vendors = $db->query("SELECT id, vendor_name FROM vendors WHERE status='active' ORDER BY vendor_name")->fetchAll();
$companies = $db->query("SELECT id, company_name FROM companies WHERE status='active' ORDER BY company_name")->fetchAll();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Vendor Bills</h1>
        <?php if (hasPermission('procurement.bills.create')): ?>
        <a href="create.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
            <i class="fas fa-plus mr-2"></i>Create Vendor Bill
        </a>
        <?php endif; ?>
    </div>

    <div class="bg-white shadow rounded-lg mb-6">
        <div class="p-4 border-b border-gray-200">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" name="search" value="<?= sanitize($filters['search']) ?>" placeholder="Bill number..."
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="">All</option>
                        <?php foreach (BillService::STATUSES as $s): ?>
                        <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Match Status</label>
                    <select name="match_status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="">All</option>
                        <?php foreach (BillService::MATCH_STATUSES as $s): ?>
                        <option value="<?= $s ?>" <?= $filters['match_status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Vendor</label>
                    <select name="vendor_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="">All</option>
                        <?php foreach ($vendors as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= (int)$filters['vendor_id'] === (int)$v['id'] ? 'selected' : '' ?>><?= sanitize($v['vendor_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Company</label>
                    <select name="company_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="">All</option>
                        <?php foreach ($companies as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (int)$filters['company_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= sanitize($c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-5 flex justify-end gap-2">
                    <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">Filter</button>
                    <a href="index.php" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg text-sm font-medium">Clear</a>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bill #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendor</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">PO</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Match</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!count($bills)): ?>
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">No vendor bills found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($bills as $b): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= sanitize($b['bill_number']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($b['vendor_name']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($b['company_name']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($b['po_number'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($b['bill_date']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900 font-medium"><?= number_format((float)$b['total_amount'], 2) ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?php
                            $badges = [
                                'draft' => 'bg-yellow-100 text-yellow-800',
                                'pending_approval' => 'bg-blue-100 text-blue-800',
                                'matched' => 'bg-green-100 text-green-800',
                                'exception' => 'bg-red-100 text-red-800',
                                'approved' => 'bg-green-100 text-green-800',
                                'posted' => 'bg-green-100 text-green-800',
                                'rejected' => 'bg-red-100 text-red-800',
                                'cancelled' => 'bg-gray-100 text-gray-800',
                            ];
                            $badge = $badges[$b['status']] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <span class="px-2 py-1 text-xs rounded font-medium <?= $badge ?>"><?= ucfirst($b['status']) ?></span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <?php if ($b['match_status']): ?>
                            <span class="px-2 py-1 text-xs rounded font-medium <?= $b['match_status'] === 'matched' ? 'bg-green-100 text-green-800' : ($b['match_status'] === 'exception' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                <?= ucfirst($b['match_status']) ?>
                            </span>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <a href="view.php?id=<?= $b['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-2">View</a>
                            <?php if ($b['status'] === 'pending_approval' || $b['status'] === 'matched'): ?>
                            <a href="approve.php?id=<?= $b['id'] ?>" class="text-green-600 hover:text-green-900 mr-2">Approve</a>
                            <?php endif; ?>
                            <?php if ($b['status'] === 'approved' || $b['status'] === 'matched'): ?>
                            <a href="post.php?id=<?= $b['id'] ?>" class="text-purple-600 hover:text-purple-900 mr-2">Post</a>
                            <?php endif; ?>
                            <?php if (in_array($b['status'], ['draft', 'rejected'])): ?>
                            <a href="edit.php?id=<?= $b['id'] ?>" class="text-indigo-600 hover:text-indigo-900 mr-2">Edit</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="px-4 py-3 border-t border-gray-200 flex items-center justify-between">
            <div class="text-sm text-gray-700">Showing <?= $offset + 1 ?> to <?= min($offset + $perPage, $total) ?> of <?= $total ?> bills</div>
            <div class="flex gap-1">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?><?= http_build_query(array_filter(['status' => $filters['status'], 'vendor_id' => $filters['vendor_id'], 'company_id' => $filters['company_id'], 'match_status' => $filters['match_status'], 'search' => $filters['search']])) ?>"
                   class="px-3 py-1 text-sm rounded <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
