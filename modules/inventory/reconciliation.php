<?php
$pageTitle = 'Inventory Reconciliation';
require_once '../../includes/header.php';
require_once __DIR__ . '/InventoryEngine.php';

if (!hasPermission('inventory.reports')) { die('Access Denied'); }
$db = getDB();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrf($csrfToken)) {
        $error = 'Invalid CSRF token.';
    } else {
        try {
            if ($action === 'create') {
                $result = InventoryEngine::createReconciliation($db, [
                    'company_id'         => (int)$_POST['company_id'],
                    'warehouse_id'       => $_POST['warehouse_id'] ?: null,
                    'reconciliation_date'=> $_POST['reconciliation_date'] ?: date('Y-m-d'),
                    'notes'              => $_POST['notes'] ?? null,
                    'created_by'         => $_SESSION['user_id'],
                ]);
                header('Location: reconciliation.php?id=' . $result['reconciliation_id']);
                exit;
            } elseif ($action === 'add_items') {
                $reconciliationId = (int)$_POST['reconciliation_id'];
                $count = InventoryEngine::addReconciliationItems($db, $reconciliationId, [
                    'item_id' => $_POST['item_id'] ?: null,
                ]);
                $message = "Added $count items to reconciliation.";
            } elseif ($action === 'record_count') {
                $itemId = (int)$_POST['item_id'];
                $result = InventoryEngine::recordPhysicalCount($db, $itemId, (float)$_POST['physical_qty'], $_POST['notes'] ?? '');
                if ($result['success']) {
                    $message = 'Physical count recorded. Discrepancy: ' . number_format($result['discrepancy_qty'], 3);
                } else {
                    $error = $result['message'];
                }
            } elseif ($action === 'complete') {
                $reconciliationId = (int)$_POST['reconciliation_id'];
                $result = InventoryEngine::completeReconciliation($db, $reconciliationId, $_SESSION['user_id'], $_POST['reason'] ?? '');
                if ($result['success']) {
                    $message = 'Reconciliation completed. Adjustments created: ' . count($result['adjustments_created']);
                } else {
                    $error = $result['message'];
                }
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

$reconciliationId = $_GET['id'] ?? '';
$reconciliation = null;
if ($reconciliationId) {
    $reconciliation = InventoryEngine::getReconciliation($db, (int)$reconciliationId);
}

$companies = $db->query("SELECT id, company_name FROM companies WHERE status='active' ORDER BY company_name")->fetchAll();
$warehouses = $db->query("SELECT id, warehouse_code, warehouse_name FROM warehouses WHERE status='active' ORDER BY warehouse_name")->fetchAll();
$reconciliations = InventoryEngine::listReconciliations($db, [], 20, 0);
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Inventory Reconciliation</h1>
    <p class="text-gray-600">Inventory Controls — Phase I3</p>
</div>

<?php if ($message): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?= sanitize($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= sanitize($error) ?></div>
<?php endif; ?>

<?php if ($reconciliation): ?>
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Reconciliation #<?= $reconciliation['id'] ?></h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
        <div><span class="text-sm text-gray-500">Status:</span> <?= InventoryEngine::getStatusBadge($reconciliation['status']) ?></div>
        <div><span class="text-sm text-gray-500">Date:</span> <?= $reconciliation['reconciliation_date'] ?></div>
        <div><span class="text-sm text-gray-500">Total Items:</span> <?= $reconciliation['total_items'] ?></div>
        <div><span class="text-sm text-gray-500">Discrepancies:</span> <?= $reconciliation['discrepancy_items'] ?></div>
        <div><span class="text-sm text-gray-500">Ledger Value:</span> <?= number_format($reconciliation['total_ledger_value'], 2) ?></div>
        <div><span class="text-sm text-gray-500">Difference:</span> <?= number_format($reconciliation['difference_value'], 2) ?></div>
    </div>
    <?php if ($reconciliation['status'] === 'draft'): ?>
    <form method="post" class="mb-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="add_items">
        <input type="hidden" name="reconciliation_id" value="<?= $reconciliation['id'] ?>">
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Populate from Stock Balances</button>
    </form>
    <?php endif; ?>
    <?php if ($reconciliation['status'] !== 'completed' && !empty($reconciliation['items'])): ?>
    <form method="post" class="mb-4" onsubmit="return confirm('Complete reconciliation and create adjustments for discrepancies?')">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="complete">
        <input type="hidden" name="reconciliation_id" value="<?= $reconciliation['id'] ?>">
        <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
        <input type="text" name="reason" class="border rounded px-3 py-2 mb-2 w-full">
        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Complete Reconciliation</button>
    </form>
    <?php endif; ?>
</div>

<?php if (!empty($reconciliation['items'])): ?>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Warehouse</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ledger Qty</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Physical Qty</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Reserved</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Available</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ledger Value</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Discrepancy</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($reconciliation['items'] as $item): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-900"><?= sanitize($item['item_code']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($item['warehouse_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-right text-gray-900"><?= number_format($item['ledger_qty'], 3) ?></td>
                    <td class="px-4 py-3 text-sm text-right text-gray-900"><?= number_format($item['physical_qty'] ?? 0, 3) ?></td>
                    <td class="px-4 py-3 text-sm text-right text-gray-700"><?= number_format($item['reserved_qty'], 3) ?></td>
                    <td class="px-4 py-3 text-sm text-right text-gray-700"><?= number_format($item['available_qty'], 3) ?></td>
                    <td class="px-4 py-3 text-sm text-right text-gray-900"><?= number_format($item['ledger_value'], 2) ?></td>
                    <td class="px-4 py-3 text-sm text-right <?= $item['discrepancy_qty'] != 0 ? 'text-red-600 font-medium' : 'text-gray-900' ?>">
                        <?= number_format($item['discrepancy_qty'], 3) ?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?php if ($reconciliation['status'] !== 'completed'): ?>
                        <form method="post" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="record_count">
                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                            <input type="number" step="0.001" name="physical_qty" value="<?= number_format($item['physical_qty'] ?? 0, 3) ?>" class="border rounded px-2 py-1 w-24 text-sm">
                            <button type="submit" class="text-blue-600 hover:text-blue-800 ml-2">Save</button>
                        </form>
                        <?php else: ?>
                        <span class="text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">New Reconciliation</h2>
    <form method="post" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="create">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Company</label>
                <select name="company_id" required class="w-full border rounded px-3 py-2">
                    <option value="">Select</option>
                    <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= sanitize($c['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse</label>
                <select name="warehouse_id" class="w-full border rounded px-3 py-2">
                    <option value="">All</option>
                    <?php foreach ($warehouses as $w): ?>
                    <option value="<?= $w['id'] ?>"><?= sanitize($w['warehouse_code'] . ' - ' . $w['warehouse_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                <input type="date" name="reconciliation_date" value="<?= date('Y-m-d') ?>" required class="w-full border rounded px-3 py-2">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea name="notes" rows="2" class="w-full border rounded px-3 py-2"></textarea>
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Create Reconciliation</button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-4 py-3 border-b">
        <h2 class="text-lg font-semibold text-gray-800">Recent Reconciliations</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Warehouse</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Items</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Discrepancies</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($reconciliations['rows'] as $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-900"><?= $r['id'] ?></td>
                    <td class="px-4 py-3 text-sm text-gray-700"><?= $r['reconciliation_date'] ?></td>
                    <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($r['warehouse_name'] ?? 'All') ?></td>
                    <td class="px-4 py-3 text-sm"><?= InventoryEngine::getStatusBadge($r['status']) ?></td>
                    <td class="px-4 py-3 text-sm text-right text-gray-900"><?= $r['total_items'] ?></td>
                    <td class="px-4 py-3 text-sm text-right <?= $r['discrepancy_items'] > 0 ? 'text-red-600 font-medium' : 'text-gray-900' ?>"><?= $r['discrepancy_items'] ?></td>
                    <td class="px-4 py-3 text-sm">
                        <a href="?id=<?= $r['id'] ?>" class="text-blue-600 hover:text-blue-800">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($reconciliations['rows'])) { echo '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No reconciliations found</td></tr>'; } ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
