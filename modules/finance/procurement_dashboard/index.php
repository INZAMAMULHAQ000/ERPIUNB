<?php
$pageTitle = 'Procurement Dashboard';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('procurement_dashboard.view')) { die('Access Denied'); }

$db = getDB();

// === KPI Cards ===

// Outstanding AP (bills with balance > 0)
$outstandingAP = $db->query("SELECT COALESCE(SUM(balance_amount),0) FROM vendor_bills WHERE status IN ('approved','partially_paid','overdue')")->fetchColumn();

// Overdue AP
$overdueAP = $db->query("SELECT COALESCE(SUM(balance_amount),0) FROM vendor_bills WHERE status='overdue' OR (status IN ('approved','partially_paid') AND due_date < CURDATE())")->fetchColumn();

// Bills this month
$billsThisMonth = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM vendor_bills WHERE MONTH(bill_date) = MONTH(CURDATE()) AND YEAR(bill_date) = YEAR(CURDATE()) AND status NOT IN ('cancelled')")->fetchColumn();
$billsThisMonthCount = $db->query("SELECT COUNT(*) FROM vendor_bills WHERE MONTH(bill_date) = MONTH(CURDATE()) AND YEAR(bill_date) = YEAR(CURDATE()) AND status NOT IN ('cancelled')")->fetchColumn();

// Payments this month
$paymentsThisMonth = $db->query("SELECT COALESCE(SUM(amount),0) FROM vendor_payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())")->fetchColumn();
$paymentsCount = $db->query("SELECT COUNT(*) FROM vendor_payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())")->fetchColumn();

// PO summary
$poCount = $db->query("SELECT COUNT(*) FROM purchase_orders WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();
$pendingPO = $db->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('draft','approved','sent')")->fetchColumn();

// === Top Vendors by billing ===
$topVendors = $db->query("
    SELECT v.vendor_name, v.vendor_code,
        COUNT(vb.id) AS bill_count,
        COALESCE(SUM(vb.total_amount),0) AS total_billed,
        COALESCE(SUM(vb.balance_amount),0) AS outstanding
    FROM vendors v
    LEFT JOIN vendor_bills vb ON v.id = vb.vendor_id AND vb.status NOT IN ('cancelled')
    GROUP BY v.id
    ORDER BY total_billed DESC
    LIMIT 5
")->fetchAll();

// === Due/Overdue Bills ===
$dueSoon = $db->query("
    SELECT vb.id, vb.bill_number, vb.due_date, vb.total_amount, vb.balance_amount,
        v.vendor_name, DATEDIFF(vb.due_date, CURDATE()) AS days_left
    FROM vendor_bills vb
    JOIN vendors v ON vb.vendor_id = v.id
    WHERE vb.balance_amount > 0 AND vb.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND vb.status IN ('approved','partially_paid')
    ORDER BY vb.due_date ASC
    LIMIT 5
")->fetchAll();

$overdueBills = $db->query("
    SELECT vb.id, vb.bill_number, vb.due_date, vb.total_amount, vb.balance_amount,
        v.vendor_name, DATEDIFF(CURDATE(), vb.due_date) AS days_overdue
    FROM vendor_bills vb
    JOIN vendors v ON vb.vendor_id = v.id
    WHERE vb.balance_amount > 0 AND vb.due_date < CURDATE()
        AND vb.status IN ('approved','partially_paid','overdue')
    ORDER BY vb.due_date ASC
    LIMIT 5
")->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Procurement Dashboard</h1>
        <p class="text-gray-600">Accounts Payable overview as of <?= date('M d, Y') ?></p>
    </div>
    <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back to Finance</a>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Outstanding AP</p>
                <p class="text-2xl font-bold text-red-600"><?= formatMoney((float)$outstandingAP) ?></p>
            </div>
            <div class="bg-red-100 p-3 rounded-full"><i class="fas fa-credit-card text-red-600 text-xl"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Overdue AP</p>
                <p class="text-2xl font-bold text-red-700"><?= formatMoney((float)$overdueAP) ?></p>
            </div>
            <div class="bg-red-200 p-3 rounded-full"><i class="fas fa-exclamation-triangle text-red-700 text-xl"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Bills This Month</p>
                <p class="text-2xl font-bold text-blue-600"><?= formatMoney((float)$billsThisMonth) ?></p>
                <p class="text-xs text-gray-400"><?= (int)$billsThisMonthCount ?> bills</p>
            </div>
            <div class="bg-blue-100 p-3 rounded-full"><i class="fas fa-file-invoice text-blue-600 text-xl"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Payments This Month</p>
                <p class="text-2xl font-bold text-green-600"><?= formatMoney((float)$paymentsThisMonth) ?></p>
                <p class="text-xs text-gray-400"><?= (int)$paymentsCount ?> payments</p>
            </div>
            <div class="bg-green-100 p-3 rounded-full"><i class="fas fa-money-bill text-green-600 text-xl"></i></div>
        </div>
    </div>
</div>

<!-- PO Summary Row -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">POs This Month</p>
                <p class="text-2xl font-bold text-purple-600"><?= (int)$poCount ?></p>
            </div>
            <div class="bg-purple-100 p-3 rounded-full"><i class="fas fa-file-purchase text-purple-600 text-xl"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Pending POs</p>
                <p class="text-2xl font-bold text-yellow-600"><?= (int)$pendingPO ?></p>
            </div>
            <div class="bg-yellow-100 p-3 rounded-full"><i class="fas fa-clock text-yellow-600 text-xl"></i></div>
        </div>
    </div>
</div>

<!-- Lower Section: Two columns -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Due Soon -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b flex justify-between items-center">
            <h3 class="font-semibold text-gray-800"><i class="fas fa-clock text-yellow-500 mr-2"></i>Due Within 7 Days</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr><th class="px-3 py-2 text-xs font-medium text-gray-500 uppercase">Vendor</th><th class="px-3 py-2 text-xs font-medium text-gray-500 uppercase">Bill #</th><th class="px-3 py-2 text-xs font-medium text-gray-500 uppercase">Due</th><th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Balance</th><th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($dueSoon)): ?>
                    <tr><td colspan="5" class="px-4 py-4 text-center text-sm text-gray-500">No bills due this week.</td></tr>
                    <?php else: ?>
                    <?php foreach ($dueSoon as $b): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 text-sm"><?= sanitize($b['vendor_name']) ?></td>
                        <td class="px-3 py-2 text-sm font-mono"><?= sanitize($b['bill_number']) ?></td>
                        <td class="px-3 py-2 text-sm whitespace-nowrap"><?= date('M d', strtotime($b['due_date'])) ?> (<?= $b['days_left'] ?>d)</td>
                        <td class="px-3 py-2 text-sm text-right font-mono text-red-600"><?= formatMoney((float)$b['balance_amount']) ?></td>
                        <td class="px-3 py-2 text-right"><a href="../vendor_bills/view.php?id=<?= $b['id'] ?>" class="text-blue-600 hover:underline text-xs">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Overdue Bills -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b flex justify-between items-center">
            <h3 class="font-semibold text-gray-800"><i class="fas fa-exclamation-circle text-red-500 mr-2"></i>Overdue Bills</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr><th class="px-3 py-2 text-xs font-medium text-gray-500 uppercase">Vendor</th><th class="px-3 py-2 text-xs font-medium text-gray-500 uppercase">Bill #</th><th class="px-3 py-2 text-xs font-medium text-gray-500 uppercase">Due</th><th class="px-3 py-2 text-xs font-medium text-gray-500 uppercase">Overdue</th><th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Balance</th><th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($overdueBills)): ?>
                    <tr><td colspan="6" class="px-4 py-4 text-center text-sm text-gray-500">No overdue bills.</td></tr>
                    <?php else: ?>
                    <?php foreach ($overdueBills as $b): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 text-sm"><?= sanitize($b['vendor_name']) ?></td>
                        <td class="px-3 py-2 text-sm font-mono"><?= sanitize($b['bill_number']) ?></td>
                        <td class="px-3 py-2 text-sm whitespace-nowrap"><?= date('M d, Y', strtotime($b['due_date'])) ?></td>
                        <td class="px-3 py-2 text-sm text-red-600 font-medium"><?= $b['days_overdue'] ?>d</td>
                        <td class="px-3 py-2 text-sm text-right font-mono text-red-600"><?= formatMoney((float)$b['balance_amount']) ?></td>
                        <td class="px-3 py-2 text-right"><a href="../vendor_bills/view.php?id=<?= $b['id'] ?>" class="text-blue-600 hover:underline text-xs">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Top Vendors -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800"><i class="fas fa-trophy text-yellow-500 mr-2"></i>Top Vendors by Billings</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr><th class="px-4 py-2 text-xs font-medium text-gray-500 uppercase">Vendor</th><th class="px-4 py-2 text-xs font-medium text-gray-500 uppercase">Code</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Bills</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total Billed</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Outstanding</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($topVendors as $v): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-sm font-medium"><?= sanitize($v['vendor_name']) ?></td>
                    <td class="px-4 py-2 text-sm font-mono text-gray-500"><?= sanitize($v['vendor_code']) ?></td>
                    <td class="px-4 py-2 text-sm text-right"><?= (int)$v['bill_count'] ?></td>
                    <td class="px-4 py-2 text-sm text-right font-mono"><?= formatMoney((float)$v['total_billed']) ?></td>
                    <td class="px-4 py-2 text-sm text-right font-mono text-red-600"><?= formatMoney((float)$v['outstanding']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>
