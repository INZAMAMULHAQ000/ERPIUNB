<?php
/**
 * F11 — Accounts Receivable Reports
 * Aging, Customer Statements, Overdue Invoices, DSO
 */

$pageTitle = 'AR Reports';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once '../FinancialReportService.php';

if (!hasPermission('report.ar.view')) { die('Access Denied'); }

$db = getDB();

// Get parameters
$reportType = $_GET['report_type'] ?? 'aging';
$clientId = (int)($_GET['client_id'] ?? 0);
$asOfDate = $_GET['as_of_date'] ?? date('Y-m-d');

// Get customers for selector
$customers = $db->query("SELECT id, client_code, company_name FROM clients WHERE status = 'active' AND deleted_at IS NULL ORDER BY company_name")->fetchAll();

// Generate report based on type
$report = null;
$customerStatement = null;

if ($reportType === 'aging') {
    $report = FinancialReportService::generateARAging([
        'as_of_date' => $asOfDate,
        'client_id' => $clientId,
    ]);
} elseif ($reportType === 'statement' && $clientId) {
    $customerStatement = FinancialReportService::generateCustomerStatement($clientId, [
        'date_to' => $asOfDate,
    ]);
    // Get customer info
    $clientStmt = $db->prepare("SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL");
    $clientStmt->execute([$clientId]);
    $customer = $clientStmt->fetch();
} elseif ($reportType === 'overdue') {
    $stmt = $db->prepare("
        SELECT ci.*, c.company_name, c.client_code, c.email,
               DATEDIFF(?, ci.due_date) as days_overdue
        FROM customer_invoices ci
        JOIN clients c ON c.id = ci.client_id
        WHERE ci.status IN ('sent','partially_paid','overdue') 
        AND ci.balance_amount > 0
        AND ci.due_date < ?
        ORDER BY days_overdue DESC
    ");
    $stmt->execute([$asOfDate, $asOfDate]);
    $report = ['success' => true, 'invoices' => $stmt->fetchAll()];
} elseif ($reportType === 'dso') {
    // Calculate DSO
    $startOfYear = date('Y-01-01');
    $totalReceivables = $db->query("SELECT COALESCE(SUM(balance_amount), 0) FROM customer_invoices WHERE status IN ('sent','partially_paid','overdue')")->fetchColumn();
    $totalRevenue = $db->prepare("
        SELECT COALESCE(SUM(total_amount), 0) 
        FROM customer_invoices 
        WHERE invoice_date BETWEEN ? AND ? AND status NOT IN ('cancelled','draft')
    ");
    $totalRevenue->execute([$startOfYear, $asOfDate]);
    $revenue = (float)$totalRevenue->fetchColumn();
    $dso = $revenue > 0 ? round(($totalReceivables / $revenue) * 365, 0) : 0;
    
    $report = [
        'success' => true,
        'dso' => $dso,
        'total_receivables' => $totalReceivables,
        'total_revenue' => $revenue,
        'as_of_date' => $asOfDate,
    ];
}
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Accounts Receivable Reports</h1>
        <p class="text-gray-600">As of <?= date('M d, Y', strtotime($asOfDate)) ?></p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"><i class="fas fa-print mr-2"></i>Print</button>
        <a href="../dashboard/index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-arrow-left mr-2"></i>Dashboard</a>
    </div>
</div>

<!-- Report Type Tabs -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="flex border-b">
        <a href="?report_type=aging&as_of_date=<?= $asOfDate ?>" class="px-6 py-3 font-medium <?= $reportType === 'aging' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            AR Aging
        </a>
        <a href="?report_type=statement&as_of_date=<?= $asOfDate ?>" class="px-6 py-3 font-medium <?= $reportType === 'statement' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Customer Statement
        </a>
        <a href="?report_type=overdue&as_of_date=<?= $asOfDate ?>" class="px-6 py-3 font-medium <?= $reportType === 'overdue' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            Overdue Invoices
        </a>
        <a href="?report_type=dso&as_of_date=<?= $asOfDate ?>" class="px-6 py-3 font-medium <?= $reportType === 'dso' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-800' ?>">
            DSO Analysis
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow mb-6 p-4">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <input type="hidden" name="report_type" value="<?= $reportType ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">As of Date</label>
            <input type="date" name="as_of_date" value="<?= sanitize($asOfDate) ?>" class="px-3 py-2 border rounded">
        </div>
        <?php if ($reportType === 'aging'): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
            <select name="client_id" class="px-3 py-2 border rounded">
                <option value="">All Customers</option>
                <?php foreach ($customers as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $clientId == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php elseif ($reportType === 'statement'): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Select Customer</label>
            <select name="client_id" class="px-3 py-2 border rounded" onchange="this.form.submit()">
                <option value="">-- Select Customer --</option>
                <?php foreach ($customers as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $clientId == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-search mr-2"></i>Generate</button>
    </form>
</div>

<?php if ($reportType === 'aging' && $report && $report['success']): ?>

<!-- AR Aging Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
    <div class="bg-green-50 rounded-lg shadow p-4 border-l-4 border-green-500">
        <p class="text-xs text-gray-500 uppercase">Current (0-30 Days)</p>
        <p class="text-xl font-bold text-green-700"><?= formatMoney($report['buckets']['current']) ?></p>
    </div>
    <div class="bg-yellow-50 rounded-lg shadow p-4 border-l-4 border-yellow-500">
        <p class="text-xs text-gray-500 uppercase">31-60 Days</p>
        <p class="text-xl font-bold text-yellow-700"><?= formatMoney($report['buckets']['31_60']) ?></p>
    </div>
    <div class="bg-orange-50 rounded-lg shadow p-4 border-l-4 border-orange-500">
        <p class="text-xs text-gray-500 uppercase">61-90 Days</p>
        <p class="text-xl font-bold text-orange-700"><?= formatMoney($report['buckets']['61_90']) ?></p>
    </div>
    <div class="bg-red-50 rounded-lg shadow p-4 border-l-4 border-red-500">
        <p class="text-xs text-gray-500 uppercase">90+ Days</p>
        <p class="text-xl font-bold text-red-700"><?= formatMoney($report['buckets']['90_plus']) ?></p>
    </div>
    <div class="bg-blue-50 rounded-lg shadow p-4 border-l-4 border-blue-500">
        <p class="text-xs text-gray-500 uppercase">Total Outstanding</p>
        <p class="text-xl font-bold text-blue-700"><?= formatMoney($report['total_outstanding']) ?></p>
    </div>
</div>

<!-- Customer Aging Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-800">Accounts Receivable Aging by Customer</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Current</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">31-60</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">61-90</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">90+</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['client_aging'])): ?>
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No outstanding receivables</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['client_aging'] as $cid => $aging): ?>
                <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location.href='?report_type=statement&client_id=<?= $cid ?>&as_of_date=<?= $asOfDate ?>'">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= sanitize($aging['name']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($aging['current']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($aging['31_60']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($aging['61_90']) ?></td>
                    <td class="px-4 py-3 text-right font-mono <?= $aging['90_plus'] > 0 ? 'text-red-600 font-semibold' : '' ?>"><?= formatMoney($aging['90_plus']) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-bold text-gray-800"><?= formatMoney($aging['total']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="bg-gray-100 font-bold">
                <tr>
                    <td class="px-4 py-3">TOTAL</td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($report['buckets']['current']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($report['buckets']['31_60']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($report['buckets']['61_90']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($report['buckets']['90_plus']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($report['total_outstanding']) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'statement' && $customerStatement && $customerStatement['success']): ?>

<!-- Customer Statement -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-6 py-4 border-b bg-gray-50">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="font-bold text-lg text-gray-800"><?= sanitize($customer['company_name']) ?></h3>
                <p class="text-sm text-gray-600">Code: <?= sanitize($customer['client_code']) ?></p>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500">Statement Date</p>
                <p class="font-semibold"><?= date('M d, Y', strtotime($asOfDate)) ?></p>
            </div>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Document #</th>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-right">Debit</th>
                    <th class="px-4 py-3 text-right">Credit</th>
                    <th class="px-4 py-3 text-right">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($customerStatement['transactions'])): ?>
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No transactions found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($customerStatement['transactions'] as $txn): ?>
                <tr>
                    <td class="px-4 py-2"><?= date('M d, Y', strtotime($txn['doc_date'])) ?></td>
                    <td class="px-4 py-2 font-mono"><?= sanitize($txn['doc_no']) ?></td>
                    <td class="px-4 py-2"><?= ucfirst(sanitize($txn['type'])) ?></td>
                    <td class="px-4 py-2 text-right font-mono"><?= $txn['debit'] > 0 ? formatMoney($txn['debit']) : '-' ?></td>
                    <td class="px-4 py-2 text-right font-mono"><?= $txn['credit'] > 0 ? formatMoney($txn['credit']) : '-' ?></td>
                    <td class="px-4 py-2 text-right font-mono font-semibold"><?= formatMoney($txn['balance']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="bg-gray-100 font-bold">
                <tr>
                    <td colspan="5" class="px-4 py-3 text-right">CLOSING BALANCE</td>
                    <td class="px-4 py-3 text-right font-mono text-lg"><?= formatMoney($customerStatement['closing_balance']) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'overdue' && $report && $report['success']): ?>

<!-- Overdue Invoices -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b bg-red-50">
        <h3 class="font-semibold text-red-800">Overdue Invoices</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Invoice #</th>
                    <th class="px-4 py-3 text-left">Customer</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="px-4 py-3 text-right">Balance</th>
                    <th class="px-4 py-3 text-left">Due Date</th>
                    <th class="px-4 py-3 text-right">Days Overdue</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($report['invoices'])): ?>
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No overdue invoices</td>
                </tr>
                <?php else: ?>
                <?php foreach ($report['invoices'] as $inv): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-blue-600"><?= sanitize($inv['invoice_number']) ?></td>
                    <td class="px-4 py-3"><?= sanitize($inv['company_name']) ?></td>
                    <td class="px-4 py-3 text-right font-mono"><?= formatMoney($inv['total_amount']) ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold"><?= formatMoney($inv['balance_amount']) ?></td>
                    <td class="px-4 py-3"><?= date('M d, Y', strtotime($inv['due_date'])) ?></td>
                    <td class="px-4 py-3 text-right">
                        <span class="px-2 py-1 rounded text-xs font-semibold <?= $inv['days_overdue'] > 90 ? 'bg-red-100 text-red-800' : ($inv['days_overdue'] > 60 ? 'bg-orange-100 text-orange-800' : 'bg-yellow-100 text-yellow-800') ?>">
                            <?= $inv['days_overdue'] ?> days
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'dso' && $report && $report['success']): ?>

<!-- DSO Analysis -->
<div class="bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-6">Days Sales Outstanding (DSO) Analysis</h3>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-blue-50 rounded-lg p-6 text-center border-l-4 border-blue-500">
            <p class="text-sm text-gray-500 uppercase">DSO</p>
            <p class="text-4xl font-bold text-blue-700"><?= $report['dso'] ?></p>
            <p class="text-sm text-gray-500">days</p>
        </div>
        <div class="bg-green-50 rounded-lg p-6 text-center border-l-4 border-green-500">
            <p class="text-sm text-gray-500 uppercase">Total Receivables</p>
            <p class="text-2xl font-bold text-gray-800"><?= formatMoney($report['total_receivables']) ?></p>
        </div>
        <div class="bg-purple-50 rounded-lg p-6 text-center border-l-4 border-purple-500">
            <p class="text-sm text-gray-500 uppercase">YTD Revenue</p>
            <p class="text-2xl font-bold text-gray-800"><?= formatMoney($report['total_revenue']) ?></p>
        </div>
    </div>
    
    <div class="bg-gray-50 rounded-lg p-4">
        <h4 class="font-semibold text-gray-800 mb-2">DSO Interpretation</h4>
        <ul class="text-sm text-gray-600 space-y-1">
            <li>• <strong>DSO &lt; 45 days:</strong> Excellent collection efficiency</li>
            <li>• <strong>DSO 45-60 days:</strong> Good collection performance</li>
            <li>• <strong>DSO 60-90 days:</strong> Needs improvement</li>
            <li>• <strong>DSO &gt; 90 days:</strong> Collection issues - action required</li>
        </ul>
    </div>
</div>

<?php endif; ?>

<style media="print">
    .erp-layout, nav, .sidebar, .navbar, form, button { display: none !important; }
    body { background: white; }
    @page { margin: 10mm; }
</style>

<?php require_once '../../../../includes/footer.php'; ?>