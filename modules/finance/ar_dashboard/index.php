<?php
$pageTitle = 'Accounts Receivable Dashboard';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('ar_dashboard.view')) { die('Access Denied'); }

$db = getDB();

// --- Summary Cards ---

// Total Receivables (all outstanding invoice balances)
$totalReceivables = $db->query("SELECT COALESCE(SUM(balance_amount),0) FROM customer_invoices WHERE status IN ('approved','sent','partially_paid','overdue')")->fetchColumn();

// Overdue Amount (invoices past due date with balance)
$overdueAmount = $db->query("
    SELECT COALESCE(SUM(balance_amount),0)
    FROM customer_invoices
    WHERE status IN ('sent','partially_paid','overdue') AND balance_amount > 0 AND due_date < CURDATE()
")->fetchColumn();

// Invoices This Month
$invoicesThisMonth = $db->query("
    SELECT COUNT(*) FROM customer_invoices
    WHERE MONTH(invoice_date) = MONTH(CURDATE()) AND YEAR(invoice_date) = YEAR(CURDATE())
")->fetchColumn();

// Payments Received This Month
$paymentsThisMonth = $db->query("
    SELECT COALESCE(SUM(amount),0) FROM customer_receipts
    WHERE MONTH(receipt_date) = MONTH(CURDATE()) AND YEAR(receipt_date) = YEAR(CURDATE())
")->fetchColumn();

// Average Collection Days (simplified: count of days between invoice date and receipt date)
$avgCollectionDays = $db->query("
    SELECT COALESCE(AVG(DATEDIFF(cr.receipt_date, ci.invoice_date)), 0)
    FROM receipt_allocations ra
    JOIN customer_receipts cr ON ra.receipt_id = cr.id
    JOIN customer_invoices ci ON ra.invoice_id = ci.id
    WHERE cr.receipt_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
")->fetchColumn();

// --- Top Outstanding Customers ---
$topCustomers = $db->query("
    SELECT c.id, c.company_name, c.client_code, COALESCE(SUM(ci.balance_amount),0) as outstanding
    FROM clients c
    JOIN customer_invoices ci ON c.id = ci.client_id
    WHERE ci.status IN ('sent','partially_paid','overdue') AND ci.balance_amount > 0
    GROUP BY c.id, c.company_name, c.client_code
    ORDER BY outstanding DESC
    LIMIT 10
")->fetchAll();

// --- Aging Summary ---
$asOfTimestamp = time();
$agingBuckets = [0, 0, 0, 0, 0];
$agingStmt = $db->query("
    SELECT due_date, balance_amount FROM customer_invoices
    WHERE status IN ('sent','partially_paid','overdue') AND balance_amount > 0
");
$agingCounts = [0, 0, 0, 0];

foreach ($agingStmt as $row) {
    $daysOverdue = floor(($asOfTimestamp - strtotime($row['due_date'])) / 86400);
    $balance = (float)$row['balance_amount'];

    if ($daysOverdue <= 0) {
        $agingBuckets[0] += $balance;
        $agingCounts[0]++;
    } elseif ($daysOverdue <= 30) {
        $agingBuckets[0] += $balance;
        $agingCounts[0]++;
    } elseif ($daysOverdue <= 60) {
        $agingBuckets[1] += $balance;
        $agingCounts[1]++;
    } elseif ($daysOverdue <= 90) {
        $agingBuckets[2] += $balance;
        $agingCounts[2]++;
    } else {
        $agingBuckets[3] += $balance;
        $agingCounts[3]++;
    }
}

// --- Monthly trend (last 6 months) ---
$monthlyTrend = $db->query("
    SELECT DATE_FORMAT(invoice_date, '%Y-%m') as month, COUNT(*) as count, COALESCE(SUM(total_amount),0) as total
    FROM customer_invoices
    WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
    ORDER BY month ASC
")->fetchAll();

$months = [];
$monthTotals = [];
$monthCounts = [];
foreach ($monthlyTrend as $m) {
    $months[] = $m['month'];
    $monthTotals[] = (float)$m['total'];
    $monthCounts[] = (int)$m['count'];
}

// Continuous 6-month window (fill months without invoices with 0)
$monthlyMap = [];
foreach ($monthlyTrend as $m) { $monthlyMap[$m['month']] = (float)$m['total']; }
$trendLabels = [];
$trendTotals = [];
for ($i = 5; $i >= 0; $i--) {
    $k = date('Y-m', strtotime("-$i months"));
    $trendLabels[] = date('M', strtotime($k . '-01'));
    $trendTotals[] = $monthlyMap[$k] ?? 0;
}

// Recent receipts
$recentReceipts = $db->query("
    SELECT cr.receipt_number, cr.receipt_date, cr.amount, cr.payment_method,
           c.company_name
    FROM customer_receipts cr
    JOIN clients c ON cr.client_id = c.id
    ORDER BY cr.created_at DESC
    LIMIT 5
")->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Accounts Receivable Dashboard</h1>
        <p class="text-gray-600">Real-time overview of receivables and collections</p>
    </div>
    <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
        <i class="fas fa-arrow-left mr-2"></i>Back to Finance
    </a>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase">Total Receivables</p>
                <p class="text-xl font-bold text-gray-800"><?= formatMoney((float)$totalReceivables) ?></p>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
                <i class="fas fa-file-invoice text-blue-600 text-lg"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase">Overdue</p>
                <p class="text-xl font-bold text-red-600"><?= formatMoney((float)$overdueAmount) ?></p>
            </div>
            <div class="bg-red-100 p-3 rounded-full">
                <i class="fas fa-exclamation-triangle text-red-600 text-lg"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase">Invoices This Month</p>
                <p class="text-xl font-bold text-gray-800"><?= (int)$invoicesThisMonth ?></p>
            </div>
            <div class="bg-indigo-100 p-3 rounded-full">
                <i class="fas fa-calendar-check text-indigo-600 text-lg"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase">Payments Received</p>
                <p class="text-xl font-bold text-green-600"><?= formatMoney((float)$paymentsThisMonth) ?></p>
            </div>
            <div class="bg-green-100 p-3 rounded-full">
                <i class="fas fa-money-bill-wave text-green-600 text-lg"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase">Avg Collection Days</p>
                <p class="text-xl font-bold text-gray-800"><?= round((float)$avgCollectionDays) ?> days</p>
            </div>
            <div class="bg-purple-100 p-3 rounded-full">
                <i class="fas fa-calendar-alt text-purple-600 text-lg"></i>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Aging Summary Chart -->
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">Aging Summary</h3>
        <div id="agingChart"></div>
        <div class="mt-3 pt-3 border-t text-sm font-semibold flex justify-between">
            <span class="text-gray-600">Total Outstanding</span>
            <span class="text-gray-800"><?= formatMoney((float)$totalReceivables) ?></span>
        </div>
    </div>

    <!-- Top Outstanding Customers -->
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">Top Outstanding Customers</h3>
        <?php if (!empty($topCustomers)): ?>
        <div class="space-y-3">
            <?php foreach ($topCustomers as $i => $cust): ?>
            <?php $barPct = $totalReceivables > 0 ? round((float)$cust['outstanding'] / (float)$totalReceivables * 100) : 0; ?>
            <div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-700 truncate mr-2"><?= sanitize($cust['company_name']) ?></span>
                    <span class="font-mono font-medium text-gray-800 whitespace-nowrap"><?= formatMoney((float)$cust['outstanding']) ?></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-1.5">
                    <div class="bg-blue-500 h-1.5 rounded-full" style="width: <?= $barPct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-sm text-gray-500 text-center py-4">No outstanding customers</p>
        <?php endif; ?>
    </div>

    <!-- Invoice Trend -->
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">Monthly Invoice Trend</h3>
        <div id="invoiceTrendChart"></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Recent Receipts -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-semibold text-gray-800">Recent Receipts</h3>
            <a href="<?= url('modules/finance/customer_receipts/index.php') ?>" class="text-xs text-blue-600 hover:text-blue-900">View All</a>
        </div>
        <?php if (!empty($recentReceipts)): ?>
        <div class="divide-y divide-gray-200">
            <?php foreach ($recentReceipts as $r): ?>
            <div class="flex justify-between py-2">
                <div>
                    <p class="text-sm font-mono text-gray-800"><?= sanitize($r['receipt_number']) ?></p>
                    <p class="text-xs text-gray-500"><?= sanitize($r['company_name']) ?> - <?= date('M d', strtotime($r['receipt_date'])) ?></p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-mono text-green-600"><?= formatMoney((float)$r['amount']) ?></p>
                    <p class="text-xs text-gray-500"><?= ucwords(str_replace('_', ' ', $r['payment_method'])) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-sm text-gray-500 text-center py-4">No receipts recorded yet</p>
        <?php endif; ?>
    </div>

    <!-- Quick Links -->
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">Quick Actions</h3>
        <div class="grid grid-cols-2 gap-3">
            <?php if (hasPermission('customer_invoices.create')): ?>
            <a href="<?= url('modules/finance/customer_invoices/create.php') ?>" class="bg-blue-50 hover:bg-blue-100 rounded-lg p-4 text-center transition">
                <i class="fas fa-plus-circle text-blue-600 text-2xl mb-2"></i>
                <p class="text-sm font-medium text-blue-800">New Invoice</p>
            </a>
            <?php endif; ?>
            <?php if (hasPermission('customer_receipts.create')): ?>
            <a href="<?= url('modules/finance/customer_receipts/create.php') ?>" class="bg-green-50 hover:bg-green-100 rounded-lg p-4 text-center transition">
                <i class="fas fa-money-bill-wave text-green-600 text-2xl mb-2"></i>
                <p class="text-sm font-medium text-green-800">Record Receipt</p>
            </a>
            <?php endif; ?>
            <?php if (hasPermission('credit_notes.create')): ?>
            <a href="<?= url('modules/finance/credit_notes/create.php') ?>" class="bg-purple-50 hover:bg-purple-100 rounded-lg p-4 text-center transition">
                <i class="fas fa-file-invoice text-purple-600 text-2xl mb-2"></i>
                <p class="text-sm font-medium text-purple-800">Credit Note</p>
            </a>
            <?php endif; ?>
            <?php if (hasPermission('customer_statements.view')): ?>
            <a href="<?= url('modules/finance/customer_statements/index.php') ?>" class="bg-orange-50 hover:bg-orange-100 rounded-lg p-4 text-center transition">
                <i class="fas fa-file-alt text-orange-600 text-2xl mb-2"></i>
                <p class="text-sm font-medium text-orange-800">Statements</p>
            </a>
            <?php endif; ?>
            <?php if (hasPermission('aging_reports.view')): ?>
            <a href="<?= url('modules/finance/aging_reports/index.php') ?>" class="bg-red-50 hover:bg-red-100 rounded-lg p-4 text-center transition">
                <i class="fas fa-clock text-red-600 text-2xl mb-2"></i>
                <p class="text-sm font-medium text-red-800">Aging Report</p>
            </a>
            <?php endif; ?>
            <?php if (hasPermission('customer_invoices.view')): ?>
            <a href="<?= url('modules/finance/customer_invoices/index.php') ?>" class="bg-indigo-50 hover:bg-indigo-100 rounded-lg p-4 text-center transition">
                <i class="fas fa-list text-indigo-600 text-2xl mb-2"></i>
                <p class="text-sm font-medium text-indigo-800">All Invoices</p>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const render = window.renderCarbonChart;

    // Aging Summary → donut (4 buckets, USD tooltips)
    const agingEl = document.getElementById('agingChart');
    if (agingEl && render) {
        render(agingEl, 'doughnut', {
            labels: ['0-30 Days', '31-60 Days', '61-90 Days', '90+ Days'],
            datasets: [{ data: [<?= (float)$agingBuckets[0] ?>, <?= (float)$agingBuckets[1] ?>, <?= (float)$agingBuckets[2] ?>, <?= (float)$agingBuckets[3] ?>], backgroundColor: ['#10b981', '#f59e0b', '#f97316', '#ef4444'] }]
        }, { legend: { position: 'bottom' } });
    }

    // Monthly Invoice Trend → line (continuous 6-month window)
    const trendEl = document.getElementById('invoiceTrendChart');
    if (trendEl && render) {
        render(trendEl, 'line', {
            labels: <?= json_encode($trendLabels) ?>,
            datasets: [{ label: 'Invoiced', data: <?= json_encode($trendTotals) ?>, backgroundColor: 'rgba(59,130,246,0.15)', borderColor: '#3b82f6' }]
        }, {
            axes: { left: { ticks: { formatter: v => Number(v).toLocaleString() } } }
        });
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>
