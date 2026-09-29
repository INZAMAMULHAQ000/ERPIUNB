<?php
$pageTitle = 'Tax Reports';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../helpers/tax_ui.php';

if (!hasPermission('tax.report')) { die('Access Denied'); }

$db = getDB();

// ---- Filters (parameterised; all SQL via prepared statements) ----
$where  = [];
$params = [];

$taxTypeId = (int)($_GET['tax_type_id'] ?? 0);
$dateFrom  = trim((string)($_GET['date_from'] ?? ''));
$dateTo    = trim((string)($_GET['date_to'] ?? ''));
$direction = trim((string)($_GET['direction'] ?? ''));
$status    = trim((string)($_GET['status'] ?? ''));

// Default range: Jan 1 of the current year .. today.
if ($dateFrom === '') $dateFrom = date('Y-01-01');
if ($dateTo   === '') $dateTo   = date('Y-m-d');

if ($taxTypeId > 0) {
    $where[]  = "x.tax_type_id = ?";
    $params[] = $taxTypeId;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[]  = "x.transaction_date >= ?";
    $params[] = $dateFrom;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[]  = "x.transaction_date <= ?";
    $params[] = $dateTo;
}
if ($direction === 'output' || $direction === 'input') {
    $where[]  = "x.tax_direction = ?";
    $params[] = $direction;
}
// The optional status filter applies to the Detail table / CSV export only.
// KPIs and the register both break down ACTIVE transactions regardless.

/**
 * Single-value aggregate against the tax register (with type join).
 */
function tax_report_value($select, array $w, array $p) {
    $sql = "SELECT {$select} FROM tax_transactions x JOIN tax_types t ON t.id = x.tax_type_id";
    if ($w) $sql .= " WHERE " . implode(' AND ', $w);
    $stmt = getDB()->prepare($sql);
    $stmt->execute($p);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    return $row[0] ?? 0;
}

/**
 * Last 200 matching rows (any row status, honouring the optional status filter).
 */
function tax_report_detail(array $w, array $p, $filtStatus) {
    $sql = "SELECT x.source_module, x.source_document_no, x.transaction_date, x.tax_direction,
                   x.tax_rate, x.taxable_amount, x.tax_amount, x.tax_status, x.status,
                   t.code AS type_code, t.name AS tax_type_name
            FROM tax_transactions x
            JOIN tax_types t ON t.id = x.tax_type_id";
    if ($filtStatus === 'active' || $filtStatus === 'reversed') {
        $w[] = "x.status = ?";
        $p[] = $filtStatus;
    }
    if ($w) $sql .= " WHERE " . implode(' AND ', $w);
    $sql .= " ORDER BY x.transaction_date DESC, x.id DESC LIMIT 200";
    $stmt = getDB()->prepare($sql);
    $stmt->execute($p);
    return $stmt->fetchAll();
}

// ---- CSV export (fires before any HTML is echoed) ----
if (isset($_GET['export']) && $_GET['export'] === '1'
    && (hasPermission('tax.export') || hasPermission('tax.report'))) {
    // Discard everything header.php buffered so the CSV body stays clean.
    while (ob_get_level()) { ob_end_clean(); }

    $rows = tax_report_detail($where, $params, $status);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=tax_report.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Document No', 'Date', 'Type', 'Direction', 'Rate', 'Taxable', 'Tax', 'Tax Status', 'Status']);
    $dirLabels = ['output' => 'Output', 'input' => 'Input', 'adjustment' => 'Adjustment'];
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['source_document_no'],
            $r['transaction_date'],
            $r['type_code'],
            $dirLabels[$r['tax_direction']] ?? ucwords((string)$r['tax_direction']),
            rtrim(rtrim(number_format((float)$r['tax_rate'], 4), '0'), '.'),
            number_format((float)$r['taxable_amount'], 2, '.', ''),
            number_format((float)$r['tax_amount'], 2, '.', ''),
            $r['tax_status'],
            $r['status'],
        ]);
    }
    fclose($out);
    exit;
}

// ---- KPIs (active transactions only) ----
$outputTax = (float)tax_report_value("COALESCE(SUM(x.tax_amount),0)",
    array_merge($where, ["x.tax_direction = 'output'", "x.status = 'active'"]), $params);
$inputTax  = (float)tax_report_value("COALESCE(SUM(x.tax_amount),0)",
    array_merge($where, ["x.tax_direction = 'input'", "x.status = 'active'"]), $params);
$netTax    = $outputTax - $inputTax;

$taxableBase = (float)tax_report_value("COALESCE(SUM(x.taxable_amount),0)",
    array_merge($where, ["x.status = 'active'"]), $params);
$txnCount    = (int)tax_report_value("COUNT(*)",
    array_merge($where, ["x.status = 'active'"]), $params);
$exemptCount = (int)tax_report_value("COUNT(*)",
    array_merge($where, ["x.status = 'active'", "x.tax_status IN ('zero_rated','exempt')"]), $params);

// ---- Tax Register Breakdown ----
$regSql  = "SELECT t.code AS type_code, x.tax_rate, x.tax_status,
                   COUNT(*) AS lines,
                   COALESCE(SUM(x.taxable_amount),0) AS taxable,
                   COALESCE(SUM(x.tax_amount),0)     AS tax
            FROM tax_transactions x
            JOIN tax_types t ON t.id = x.tax_type_id";
$regWhere = array_merge($where, ["x.status = 'active'"]);
$regSql  .= " WHERE " . implode(' AND ', $regWhere);
$regSql  .= " GROUP BY t.code, x.tax_rate, x.tax_status ORDER BY t.code, x.tax_rate DESC";
$regStmt   = $db->prepare($regSql);
$regStmt->execute($params);
$registers = $regStmt->fetchAll();

$totalTaxable = 0.0;
$totalTax     = 0.0;
foreach ($registers as $r) {
    $totalTaxable += (float)$r['taxable'];
    $totalTax     += (float)$r['tax'];
}

// ---- Detail ----
$detail = tax_report_detail($where, $params, $status);

// Export link preserves the current filters.
$exportQ = [
    'export'    => 1,
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'direction' => $direction,
    'status'    => $status,
];
if ($taxTypeId > 0) $exportQ['tax_type_id'] = $taxTypeId;
$exportUrl = 'index.php?' . http_build_query($exportQ);
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Tax Reports</h1>
        <p class="text-gray-600">Output tax, input tax and the tax register for the selected period</p>
    </div>
    <a href="<?= sanitize($exportUrl) ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm">
        <i class="fas fa-file-csv mr-2"></i>Export CSV
    </a>
</div>

<?= taxui_tabs('reports') ?>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="get" action="index.php" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tax Type</label>
            <?= taxui_types_select($taxTypeId, 'tax_type_id', true) ?>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
            <input type="date" name="date_from" value="<?= sanitize($dateFrom) ?>"
                   class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
            <input type="date" name="date_to" value="<?= sanitize($dateTo) ?>"
                   class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Direction</label>
            <select name="direction" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
                <option value="">All Directions</option>
                <option value="output" <?= $direction === 'output' ? 'selected' : '' ?>>Output Tax</option>
                <option value="input" <?= $direction === 'input' ? 'selected' : '' ?>>Input Tax</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">
                <option value="">All Statuses</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="reversed" <?= $status === 'reversed' ? 'selected' : '' ?>>Reversed</option>
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-filter mr-2"></i>Apply
            </button>
            <a href="index.php" class="px-4 py-2 text-sm border rounded-lg text-gray-700 hover:bg-gray-50" title="Reset filters">
                <i class="fas fa-undo"></i>
            </a>
        </div>
    </form>
</div>

<!-- KPI row 1 -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Total Output Tax</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?= taxui_amount($outputTax) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Total Input Tax</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?= taxui_amount($inputTax) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 <?= $netTax <= 0 ? 'bg-green-50' : 'bg-rose-50' ?>">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Net Tax Payable</div>
        <div class="text-2xl font-bold <?= $netTax <= 0 ? 'text-green-800' : 'text-rose-800' ?> mt-1"><?= taxui_amount($netTax) ?></div>
    </div>
</div>

<!-- KPI row 2 -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Taxable Base</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?= taxui_amount($taxableBase) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Transaction Count</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?= number_format($txnCount) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Exempt / Zero-Rated Lines</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?= number_format($exemptCount) ?></div>
    </div>
</div>

<!-- Tax Register Breakdown -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-800">Tax Register Breakdown</h2>
        <p class="text-xs text-gray-500 mt-0.5">Active transactions grouped by tax type, rate and status for <?= sanitize($dateFrom) ?> to <?= sanitize($dateTo) ?></p>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tax Type</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Rate</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tax Status</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Lines</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Taxable</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tax</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($registers)): ?>
            <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No matching active transactions for the selected period.</td></tr>
            <?php endif; ?>
            <?php foreach ($registers as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs font-mono bg-slate-100 text-slate-700 rounded"><?= sanitize($r['type_code']) ?></span>
                </td>
                <td class="px-6 py-4 text-right font-mono text-gray-900"><?= rtrim(rtrim(number_format((float)$r['tax_rate'], 4), '0'), '.') ?>%</td>
                <td class="px-6 py-4"><?= taxui_status_badge($r['tax_status']) ?></td>
                <td class="px-6 py-4 text-center text-gray-700"><?= (int)$r['lines'] ?></td>
                <td class="px-6 py-4 text-right text-gray-900"><?= taxui_amount($r['taxable']) ?></td>
                <td class="px-6 py-4 text-right text-gray-900"><?= taxui_amount($r['tax']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <?php if (!empty($registers)): ?>
        <tfoot class="bg-gray-50">
            <tr>
                <td colspan="4" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</td>
                <td class="px-6 py-3 text-right font-semibold text-gray-900"><?= taxui_amount($totalTaxable) ?></td>
                <td class="px-6 py-3 text-right font-semibold text-gray-900"><?= taxui_amount($totalTax) ?></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
</div>

<!-- Detail -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
        <div>
            <h2 class="font-semibold text-gray-800">Detail</h2>
            <p class="text-xs text-gray-500 mt-0.5">Last <?= count($detail) ?> matching transactions (any status)</p>
        </div>
        <a href="<?= sanitize($exportUrl) ?>" class="text-sm text-blue-600 hover:underline"><i class="fas fa-file-csv mr-1"></i>Export CSV</a>
    </div>
    <div class="max-h-96 overflow-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Document No</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Direction</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Rate</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Taxable</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tax</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($detail)): ?>
                <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No transactions match the selected filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($detail as $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3">
                        <div class="font-mono text-sm text-gray-900"><?= sanitize($r['source_document_no'] ?: '—') ?></div>
                        <div class="text-xs text-gray-500"><?= taxui_source_label($r['source_module']) ?></div>
                    </td>
                    <td class="px-6 py-3 text-gray-700 whitespace-nowrap"><?= sanitize($r['transaction_date']) ?></td>
                    <td class="px-6 py-3 text-center"><?= taxui_direction_badge($r['tax_direction']) ?></td>
                    <td class="px-6 py-3 text-right font-mono text-gray-900"><?= rtrim(rtrim(number_format((float)$r['tax_rate'], 4), '0'), '.') ?>%</td>
                    <td class="px-6 py-3 text-right text-gray-900"><?= taxui_amount($r['taxable_amount']) ?></td>
                    <td class="px-6 py-3 text-right text-gray-900"><?= taxui_amount($r['tax_amount']) ?></td>
                    <td class="px-6 py-3 text-center"><?= taxui_txn_badge($r['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../../../includes/footer.php'; ?>