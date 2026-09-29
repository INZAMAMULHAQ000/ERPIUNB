<?php
/**
 * F11 — Tax Reports
 * Output Tax, Input Tax, Net Payable, Tax Period Summary
 * Integrates with F8 - Tax Management
 */

$pageTitle = 'Tax Reports';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once '../FinancialReportService.php';

if (!hasPermission('report.tax.view')) { die('Access Denied'); }

$db = getDB();

// Get parameters
$reportType = $_GET['report_type'] ?? 'summary';
$taxTypeId = (int)($_GET['tax_type_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');

// Get tax types
$taxTypes = $db->query("SELECT * FROM tax_types WHERE status = 'active' ORDER BY name")->fetchAll();

// Generate report based on type
$report = null;

if ($reportType === 'summary') {
    $report = FinancialReportService::generateTaxSummary([
        'tax_type_id' => $taxTypeId,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
    ]);
} elseif ($reportType === 'output') {
    $stmt = $db->prepare("
        SELECT tt.*,
               COALESCE(ci.invoice_number, tt.source_document_no) AS invoice_number,
               c.company_name as customer_name
        FROM tax_transactions tt
        LEFT JOIN customer_invoices ci ON ci.id = tt.source_record_id AND tt.source_module IN ('invoice','invoices','customer_invoices','ar')
        LEFT JOIN clients c ON c.id = ci.client_id
        WHERE tt.tax_direction = 'output' 
        AND tt.transaction_date BETWEEN ? AND ?
        AND tt.status = 'active'
        ORDER BY tt.transaction_date DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $report = ['success' => true, 'transactions' => $stmt->fetchAll(), 'type' => 'Output Tax'];
} elseif ($reportType === 'input') {
    $stmt = $db->prepare("
        SELECT tt.*,
               COALESCE(vb.bill_number, tt.source_document_no) AS bill_number,
               v.vendor_name
        FROM tax_transactions tt
        LEFT JOIN vendor_bills vb ON vb.id = tt.source_record_id AND tt.source_module IN ('bill','bills','vendor_bills','ap')
        LEFT JOIN vendors v ON v.id = vb.vendor_id
        WHERE tt.tax_direction = 'input' 
        AND tt.transaction_date BETWEEN ? AND ?
        AND tt.status = 'active'
        ORDER BY tt.transaction_date DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $report = ['success' => true, 'transactions' => $stmt->fetchAll(), 'type' => 'Input Tax'];
} elseif ($reportType === 'period') {
    // Get monthly tax summary
    $stmt = $db->prepare("
        SELECT DATE_FORMAT(tt.transaction_date, '%Y-%m') as tax_period,
               tt.tax_direction,
               SUM(tt.taxable_amount) as taxable,
               SUM(tt.tax_amount) as tax,
               COUNT(*) as count
        FROM tax_transactions tt
        WHERE tt.transaction_date BETWEEN ? AND ?
        AND tt.status = 'active'
        GROUP BY tax_period, tt.tax_direction
        ORDER BY tax_period DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $periods = $stmt->fetchAll();
    
    // Organize by period
    $periodSummary = [];
    foreach ($periods as $p) {
        $period = $p['tax_period'];
        if (!isset($periodSummary[$period])) {
            $periodSummary[$period] = ['output_taxable' => 0, 'output_tax' => 0, 'input_taxable' => 0, 'input_tax' => 0];
        }
        if ($p['tax_direction'] === 'output') {
            $periodSummary[$period]['output_taxable'] += (float)$p['taxable'];
            $periodSummary[$period]['output_tax'] += (float)$p['tax'];
        } else {
            $periodSummary[$period]['input_taxable'] += (float)$p['taxable'];
            $periodSummary[$period]['input_tax'] += (float)$p['tax'];
        }
    }
    $report = ['success' => true, 'periods' => $periodSummary];
} elseif ($reportType === 'adjustments') {
    $stmt = $db->prepare("
        SELECT ta.*, tt.id AS tax_type_id, tt.name AS tax_type_name
        FROM tax_adjustments ta
        JOIN tax_types tt ON tt.id = ta.tax_type_id
        WHERE ta.adjustment_date BETWEEN ? AND ?
        ORDER BY ta.adjustment_date DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $report = ['success' => true, 'adjustments' => $stmt->fetchAll()];
}
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Tax Reports</h1>
        <p class="text-gray-600">Period: <?= date('M d, Y', strtotime($dateFrom)) ?> to <?= date('M d, Y', strtotime($dateTo)) ?></p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"><i class="fas fa-print mr-2"></i>Print</button>
        <a href="../dashboard/index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-arrow-left mr-2"></i>Dashboard</a>
    </div>
</div>

<!-- Report Type Tabs -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="flex border-b overflow-x-auto">
        <a href="?report_type=summary&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="px-6 py-3 font-medium <?= $reportType === 'summary' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Tax Summary
        </a>
        <a href="?report_type=output&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="px-6 py-3 font-medium <?= $reportType === 'output' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Output Tax (Sales)
        </a>
        <a href="?report_type=input&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="px-6 py-3 font-medium <?= $reportType === 'input' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Input Tax (Purchases)
        </a>
        <a href="?report_type=period&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="px-6 py-3 font-medium <?= $reportType === 'period' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Period Summary
        </a>
        <a href="?report_type=adjustments&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="px-6 py-3 font-medium <?= $reportType === 'adjustments' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Adjustments
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow mb-6 p-4">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <input type="hidden" name="report_type" value="<?= $reportType ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input type="date" name="date_from" value="<?= sanitize($dateFrom) ?>" class="px-3 py-2 border rounded">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input type="date" name="date_to" value="<?= sanitize($dateTo) ?>" class="px-3 py-2 border rounded">
        </div>
        <?php if ($reportType === 'summary'): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tax Type</label>
            <select name="tax_type_id" class="px-3 py-2 border rounded">
                <option value="">All Tax Types</option>
                <?php foreach ($taxTypes as $tt): ?>
                <option value="<?= $tt['id'] ?>" <?= $taxTypeId == $tt['id'] ? 'selected' : '' ?>><?= sanitize($tt['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-search mr-2"></i>Generate</button>
    </form>
</div>

<?php if ($reportType === 'summary' && $report && $report['success']): ?>

<!-- Tax Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-blue-50 rounded-lg shadow p-4 border-l-4 border-blue-500">
        <p class="text-xs text-gray-500 uppercase">Output Tax (Sales)</p>
        <p class="text-xl font-bold text-blue-700"><?= formatMoney($report['output_tax']['tax']) ?></p>
        <p class="text-xs text-gray-400">Taxable: <?= formatMoney($report['output_tax']['taxable']) ?></p>
    </div>
    <div class="bg-green-50 rounded-lg shadow p-4 border-l-4 border-green-500">
        <p class="text-xs text-gray-500 uppercase">Input Tax (Purchases)</p>
        <p class="text-xl font-bold text-green-700"><?= formatMoney($report['input_tax']['tax']) ?></p>
        <p class="text-xs text-gray-400">Taxable: <?= formatMoney($report['input_tax']['taxable']) ?></p>
    </div>
    <div class="bg-purple-50 rounded-lg shadow p-4 border-l-4 border-purple-500">
        <p class="text-xs text-gray-500 uppercase">Net Tax Payable</p>
        <p class="text-xl font-bold <?= $report['net_payable'] >= 0 ? 'text-purple-700' : 'text-green-600' ?>">
            <?= formatMoney(abs($report['net_payable'])) ?>
        </p>
        <p class="text-xs text-gray-400"><?= $report['net_payable'] >= 0 ? 'Payable to Tax Authority' : 'Refundable from Tax Authority' ?></p>
    </div>
    <div class="bg-amber-50 rounded-lg shadow p-4 border-l-4 border-amber-500">
        <p class="text-xs text-gray-500 uppercase">Tax Effect</p>
        <p class="text-xl font-bold <?= $report['net_payable'] >= 0 ? 'text-red-600' : 'text-green-600' ?>">
            <?= $report['net_payable'] >= 0 ? '+' : '-' ?><?= formatMoney(abs($report['net_payable'])) ?>
        </p>
        <p class="text-xs text-gray-400"><?= $report['net_payable'] >= 0 ? 'Tax Outflow' : 'Tax Saving' ?></p>
    </div>
</div>

<!-- Tax Calculation Breakdown -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b bg-gradient-to-r from-blue-600 to-blue-500">
        <h3 class="font-bold text-white">Tax Calculation</h3>
    </div>
    <div class="p-6">
        <table class="w-full">
            <tbody>
                <tr class="border-b">
                    <td class="py-3 text-gray-700">Total Output Tax (Sales)</td>
                    <td class="py-3 text-right font-mono text-gray-800"><?= formatMoney($report['output_tax']['tax']) ?></td>
                </tr>
                <tr class="border-b">
                    <td class="py-3 text-gray-700">Less: Total Input Tax (Purchases)</td>
                    <td class="py-3 text-right font-mono text-gray-800">- <?= formatMoney($report['input_tax']['tax']) ?></td>
                </tr>
                <tr class="border-b bg-gray-50">
                    <td class="py-3 font-semibold text-gray-800">Net Tax Payable/(Refundable)</td>
                    <td class="py-3 text-right font-mono font-bold text-lg <?= $report['net_payable'] >= 0 ? 'text-red-600' : 'text-green-600' ?>">
                        <?= formatMoney(abs($report['net_payable'])) ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Taxable Basis Summary -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-3 border-b bg-blue-50">
            <h4 class="font-semibold text-blue-800">Output Tax Basis (Sales)</h4>
        </div>
        <div class="p-4">
            <div class="flex justify-between py-2">
                <span class="text-gray-600">Total Taxable Sales</span>
                <span class="font-mono text-gray-800"><?= formatMoney($report['output_tax']['taxable']) ?></span>
            </div>
            <div class="flex justify-between py-2">
                <span class="text-gray-600">Output Tax</span>
                <span class="font-mono text-gray-800"><?= formatMoney($report['output_tax']['tax']) ?></span>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-3 border-b bg-green-50">
            <h4 class="font-semibold text-green-800">Input Tax Basis (Purchases)</h4>
        </div>
        <div class="p-4">
            <div class="flex justify-between py-2">
                <span class="text-gray-600">Total Taxable Purchases</span>
                <span class="font-mono text-gray-800"><?= formatMoney($report['input_tax']['taxable']) ?></span>
            </div>
            <div class="flex justify-between py-2">
                <span class="text-gray-600">Input Tax (Credit)</span>
                <span class="font-mono text-gray-800"><?= formatMoney($report['input_tax']['tax']) ?></span>
            </div>
        </div>
    </div>
</div>

<?php elseif ($reportType === 'output' && $report && $report['success']): ?>

<!-- Output Tax Transactions -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b bg-blue-50">
        <h3 class="font-semibold text-blue-800">Output Tax Transactions (Sales)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Invoice #</th>
                    <th class="px-4 py-3 text-left">Customer</th>
                    <th class="px-4 py-3 text-right">Taxable Amount</th>
                    <th class="px-4 py-3 text-right">Tax Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['transactions'])): ?>
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">No output tax transactions found</td>
                </tr>
                <?php else: ?>
                <?php $totalTaxable = 0; $totalTax = 0; ?>
                <?php foreach ($report['transactions'] as $txn): ?>
                <?php $totalTaxable += (float)$txn['taxable_amount']; $totalTax += (float)$txn['tax_amount']; ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3"><?= date('M d, Y', strtotime($txn['transaction_date'])) ?></td>
                    <td class="px-4 py-3 font-mono text-blue-600"><?= sanitize($txn['invoice_number'] ?? '-') ?></td>
                    <td class="px-4 py-3"><?= sanitize($txn['customer_name'] ?? '-') ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($txn['taxable_amount']) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold"><?= formatMoney($txn['tax_amount']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tfoot class="bg-gray-100 font-bold">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-right">TOTALS</td>
                        <td class="px-4 py-3 text-right font-mono"><?= formatMoney($totalTaxable) ?></td>
                        <td class="px-4 py-3 text-right font-mono"><?= formatMoney($totalTax) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'input' && $report && $report['success']): ?>

<!-- Input Tax Transactions -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b bg-green-50">
        <h3 class="font-semibold text-green-800">Input Tax Transactions (Purchases)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Bill #</th>
                    <th class="px-4 py-3 text-left">Vendor</th>
                    <th class="px-4 py-3 text-right">Taxable Amount</th>
                    <th class="px-4 py-3 text-right">Tax Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['transactions'])): ?>
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">No input tax transactions found</td>
                </tr>
                <?php else: ?>
                <?php $totalTaxable = 0; $totalTax = 0; ?>
                <?php foreach ($report['transactions'] as $txn): ?>
                <?php $totalTaxable += (float)$txn['taxable_amount']; $totalTax += (float)$txn['tax_amount']; ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3"><?= date('M d, Y', strtotime($txn['transaction_date'])) ?></td>
                    <td class="px-4 py-3 font-mono text-blue-600"><?= sanitize($txn['bill_number'] ?? '-') ?></td>
                    <td class="px-4 py-3"><?= sanitize($txn['vendor_name'] ?? '-') ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($txn['taxable_amount']) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold"><?= formatMoney($txn['tax_amount']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tfoot class="bg-gray-100 font-bold">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-right">TOTALS</td>
                        <td class="px-4 py-3 text-right font-mono"><?= formatMoney($totalTaxable) ?></td>
                        <td class="px-4 py-3 text-right font-mono"><?= formatMoney($totalTax) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'period' && $report && $report['success']): ?>

<!-- Period Summary -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b bg-purple-50">
        <h3 class="font-semibold text-purple-800">Monthly Tax Period Summary</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Period</th>
                    <th class="px-4 py-3 text-right">Output Taxable</th>
                    <th class="px-4 py-3 text-right">Output Tax</th>
                    <th class="px-4 py-3 text-right">Input Taxable</th>
                    <th class="px-4 py-3 text-right">Input Tax</th>
                    <th class="px-4 py-3 text-right">Net Payable</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['periods'])): ?>
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No period data found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['periods'] as $period => $data): ?>
                <?php $netPayable = $data['output_tax'] - $data['input_tax']; ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><?= date('M Y', strtotime($period . '-01')) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($data['output_taxable']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($data['output_tax']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($data['input_taxable']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($data['input_tax']) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold <?= $netPayable >= 0 ? 'text-red-600' : 'text-green-600' ?>">
                        <?= formatMoney(abs($netPayable)) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'adjustments' && $report && $report['success']): ?>

<!-- Tax Adjustments -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b bg-amber-50">
        <h3 class="font-semibold text-amber-800">Tax Adjustments</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Tax Type</th>
                    <th class="px-4 py-3 text-left">Adjustment Type</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="px-4 py-3 text-left">Description</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['adjustments'])): ?>
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">No tax adjustments found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['adjustments'] as $adj): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3"><?= date('M d, Y', strtotime($adj['adjustment_date'])) ?></td>
                    <td class="px-4 py-3"><?= sanitize($adj['tax_type_name']) ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded text-xs font-semibold <?= $adj['adjustment_type'] === 'credit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= ucfirst(sanitize($adj['adjustment_type'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right font-mono font-semibold"><?= formatMoney($adj['amount']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= sanitize($adj['description'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<style media="print">
    .erp-layout, nav, .sidebar, .navbar, form, button { display: none !important; }
    body { background: white; }
    @page { margin: 10mm; }
</style>

<?php require_once '../../../../includes/footer.php'; ?>
