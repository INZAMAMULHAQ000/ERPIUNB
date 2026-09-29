<?php
$pageTitle = 'Tax Transactions';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.view')) { die('Access Denied'); }

$db = getDB();

// ---- Filters (GET) ----
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');
$taxTypeId  = (int)($_GET['tax_type_id'] ?? 0);
$direction  = trim($_GET['direction'] ?? '');
$txStatus   = trim($_GET['status'] ?? '');
$sourceMod  = trim($_GET['source_module'] ?? '');

$validDirections = ['output', 'input', 'adjustment'];
$validStatuses   = ['active', 'reversed'];
$validModules    = ['customer_invoice', 'vendor_bill', 'company_expense', 'credit_note', 'debit_note', 'tax_adjustment'];

$where  = [];
$params = [];

if ($dateFrom && strtotime($dateFrom) !== false) { $where[] = 'x.transaction_date >= ?'; $params[] = $dateFrom; }
if ($dateTo   && strtotime($dateTo)   !== false) { $where[] = 'x.transaction_date <= ?'; $params[] = $dateTo; }
if ($taxTypeId)                                  { $where[] = 'x.tax_type_id = ?';      $params[] = $taxTypeId; }
if ($direction !== '' && in_array($direction, $validDirections, true)) { $where[] = 'x.tax_direction = ?'; $params[] = $direction; }
if ($txStatus  !== '' && in_array($txStatus, $validStatuses, true))    { $where[] = 'x.status = ?';        $params[] = $txStatus; }
if ($sourceMod !== '' && in_array($sourceMod, $validModules, true))    { $where[] = 'x.source_module = ?'; $params[] = $sourceMod; }

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// ---- Rows (join tax type for code + name) ----
$stmt = $db->prepare("
    SELECT x.*, t.name AS type_name, t.code AS type_code
    FROM tax_transactions x
    JOIN tax_types t ON t.id = x.tax_type_id
    $whereSql
    ORDER BY x.transaction_date DESC, x.id DESC
    LIMIT 500
");
$stmt->execute($params);
$txns = $stmt->fetchAll();

// ---- Totals for the filtered set (exact aggregate) ----
$stmt = $db->prepare("
    SELECT COALESCE(SUM(x.taxable_amount), 0) AS taxable_total,
           COALESCE(SUM(x.tax_amount), 0)      AS tax_total,
           COUNT(*)                            AS txn_count
    FROM tax_transactions x
    JOIN tax_types t ON t.id = x.tax_type_id
    $whereSql
");
$stmt->execute($params);
$totals = $stmt->fetch();

$exportQ = http_build_query([
    'date_from'     => $dateFrom,
    'date_to'       => $dateTo,
    'tax_type_id'   => $taxTypeId ?: '',
    'direction'     => $direction,
    'status'        => $txStatus,
    'source_module' => $sourceMod,
]);
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Tax Transactions</h1>
        <p class="text-gray-600">Single register for output tax, input tax and adjustments</p>
    </div>
    <?php if (hasPermission('tax.export') || hasPermission('tax.view')): ?>
    <a href="export.php?<?= $exportQ ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-file-csv mr-2"></i>Export CSV
    </a>
    <?php endif; ?>
</div>

<?= taxui_tabs('transactions') ?>

<!-- Filter bar -->
<form method="get" action="index.php" class="bg-white rounded-lg shadow p-4 mb-6">
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <div>
            <label for="date_from" class="block text-xs font-medium text-gray-500 uppercase mb-1">Date From</label>
            <input type="date" id="date_from" name="date_from" value="<?= sanitize($dateFrom) ?>"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label for="date_to" class="block text-xs font-medium text-gray-500 uppercase mb-1">Date To</label>
            <input type="date" id="date_to" name="date_to" value="<?= sanitize($dateTo) ?>"
                   class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label for="tax_type_id" class="block text-xs font-medium text-gray-500 uppercase mb-1">Tax Type</label>
            <?= taxui_types_select($taxTypeId, 'tax_type_id', true) ?>
        </div>
        <div>
            <label for="direction" class="block text-xs font-medium text-gray-500 uppercase mb-1">Direction</label>
            <select name="direction" id="direction" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
                <option value="">All Directions</option>
                <option value="output"     <?= $direction === 'output' ? 'selected' : '' ?>>Output Tax</option>
                <option value="input"      <?= $direction === 'input' ? 'selected' : '' ?>>Input Tax</option>
                <option value="adjustment" <?= $direction === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
            </select>
        </div>
        <div>
            <label for="status" class="block text-xs font-medium text-gray-500 uppercase mb-1">Status</label>
            <select name="status" id="status" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
                <option value="">All Statuses</option>
                <option value="active"   <?= $txStatus === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="reversed" <?= $txStatus === 'reversed' ? 'selected' : '' ?>>Reversed</option>
            </select>
        </div>
        <div>
            <label for="source_module" class="block text-xs font-medium text-gray-500 uppercase mb-1">Source</label>
            <select name="source_module" id="source_module" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
                <option value="">All Sources</option>
                <?php foreach ($validModules as $m): ?>
                <option value="<?= $m ?>" <?= $sourceMod === $m ? 'selected' : '' ?>><?= taxui_source_label($m) ?></option>
                <?php endforeach; ?>
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

<!-- Totals strip -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Total Taxable</div>
        <div class="text-xl font-bold text-gray-800 mt-1"><?= taxui_amount($totals['taxable_total']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Total Tax</div>
        <div class="text-xl font-bold text-gray-800 mt-1"><?= taxui_amount($totals['tax_total']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Transactions (shown / matching)</div>
        <div class="text-xl font-bold text-gray-800 mt-1"><?= count($txns) ?> / <?= number_format((int)$totals['txn_count']) ?></div>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tax Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Direction</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Rate</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Taxable</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tax</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($txns)): ?>
            <tr><td colspan="8" class="px-6 py-8 text-center text-gray-500">No tax transactions match the current filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($txns as $x): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="text-xs text-gray-500"><?= taxui_source_label($x['source_module']) ?></div>
                    <?php if ($x['source_document_no'] !== null && $x['source_document_no'] !== ''): ?>
                    <a href="view.php?id=<?= $x['id'] ?>" class="text-sm font-mono text-blue-600 hover:underline"><?= sanitize($x['source_document_no']) ?></a>
                    <?php else: ?>
                    <span class="text-sm text-gray-700">#<?= (int)$x['source_record_id'] ?></span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-gray-700 whitespace-nowrap text-sm"><?= sanitize($x['transaction_date']) ?></td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs font-mono bg-slate-100 text-slate-700 rounded"><?= sanitize($x['type_code']) ?></span>
                </td>
                <td class="px-6 py-4"><?= taxui_direction_badge($x['tax_direction']) ?></td>
                <td class="px-6 py-4 text-right font-mono text-gray-900 whitespace-nowrap"><?= rtrim(rtrim(number_format((float)$x['tax_rate'], 4), '0'), '.') ?>%</td>
                <td class="px-6 py-4 text-right text-gray-900 whitespace-nowrap"><?= taxui_amount($x['taxable_amount']) ?></td>
                <td class="px-6 py-4 text-right text-gray-900 whitespace-nowrap"><?= taxui_amount($x['tax_amount']) ?></td>
                <td class="px-6 py-4 text-center"><?= taxui_txn_badge($x['status']) ?></td>
                <td class="px-6 py-4 text-right whitespace-nowrap">
                    <a href="view.php?id=<?= $x['id'] ?>" class="text-gray-500 hover:text-gray-800" title="View"><i class="fas fa-eye"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>