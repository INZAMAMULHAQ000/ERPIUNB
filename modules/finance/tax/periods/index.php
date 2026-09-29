<?php
$pageTitle = 'Tax Periods';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.view')) { die('Access Denied'); }

$db = getDB();

// ---- Filters (GET) ----
$taxTypeId = (int)($_GET['tax_type_id'] ?? 0);
$status    = trim($_GET['status'] ?? '');
$validStatuses = ['open', 'submitted', 'closed'];

$where  = [];
$params = [];
if ($taxTypeId) { $where[] = 'p.tax_type_id = ?'; $params[] = $taxTypeId; }
if ($status !== '' && in_array($status, $validStatuses, true)) { $where[] = 'p.status = ?'; $params[] = $status; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT p.*, t.name AS type_name, t.code AS type_code,
           (SELECT COUNT(*) FROM tax_transactions x WHERE x.tax_period_id = p.id) AS txn_count,
           u.name AS closed_by_name
    FROM tax_periods p
    JOIN tax_types t ON t.id = p.tax_type_id
    LEFT JOIN users u ON u.id = p.closed_by
    $whereSql
    ORDER BY p.start_date DESC, p.id DESC
");
$stmt->execute($params);
$periods = $stmt->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Tax Periods</h1>
        <p class="text-gray-600">Open, submitted and closed filing periods — closed periods lock the tax register</p>
    </div>
    <?php if (hasPermission('tax.period.manage')): ?>
    <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>New Period
    </a>
    <?php endif; ?>
</div>

<?= taxui_tabs('periods') ?>

<!-- Filter bar -->
<form method="get" action="index.php" class="bg-white rounded-lg shadow p-4 mb-6">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div>
            <label for="tax_type_id" class="block text-xs font-medium text-gray-500 uppercase mb-1">Tax Type</label>
            <?= taxui_types_select($taxTypeId, 'tax_type_id', true) ?>
        </div>
        <div>
            <label for="status" class="block text-xs font-medium text-gray-500 uppercase mb-1">Status</label>
            <select name="status" id="status" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
                <option value="">All Statuses</option>
                <option value="open"      <?= $status === 'open' ? 'selected' : '' ?>>Open</option>
                <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                <option value="closed"    <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
            </select>
        </div>
    </div>
    <div class="mt-4 flex gap-3">
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm">
            <i class="fas fa-filter mr-1"></i>Filter
        </button>
        <a href="index.php" class="px-4 py-2 text-sm border rounded-lg text-gray-700 hover:bg-gray-50">Reset</a>
    </div>
</form>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Start</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">End</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Transactions</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Closed Info</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($periods)): ?>
            <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No tax periods match the current filters. <a href="create.php" class="text-blue-600 hover:underline">Create one</a>.</td></tr>
            <?php endif; ?>
            <?php foreach ($periods as $p): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= sanitize($p['period_name']) ?></div>
                    <span class="px-2 py-0.5 text-xs font-mono bg-slate-100 text-slate-700 rounded"><?= sanitize($p['type_code']) ?></span>
                </td>
                <td class="px-6 py-4 text-gray-700 whitespace-nowrap text-sm"><?= sanitize($p['start_date']) ?></td>
                <td class="px-6 py-4 text-gray-700 whitespace-nowrap text-sm"><?= sanitize($p['end_date']) ?></td>
                <td class="px-6 py-4 text-center text-gray-700"><?= number_format((int)$p['txn_count']) ?></td>
                <td class="px-6 py-4 text-center"><?= taxui_period_badge($p['status']) ?></td>
                <td class="px-6 py-4 text-sm text-gray-600 whitespace-nowrap"><?= $p['closed_by'] ? sanitize($p['closed_by_name']) . ' · ' . sanitize($p['closed_at']) : '—' ?></td>
                <td class="px-6 py-4 text-right whitespace-nowrap">
                    <?php if ($p['status'] === 'open' && hasPermission('tax.period.manage')): ?>
                    <form method="post" action="submit.php" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="period_id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="px-3 py-1 text-xs font-medium bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition">Submit</button>
                    </form>
                    <?php elseif ($p['status'] === 'submitted' && hasPermission('tax.period.close')): ?>
                    <form method="post" action="close.php" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="period_id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" onclick="return confirm('Close this period permanently? No further tax transactions will be allowed.')" class="px-3 py-1 text-xs font-medium bg-rose-600 text-white rounded-lg hover:bg-rose-700 transition">Close</button>
                    </form>
                    <?php else: ?>
                    <span class="text-gray-300">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>