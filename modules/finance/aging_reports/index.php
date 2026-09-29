<?php
$pageTitle = 'Aging Report';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../helpers/finance_helpers.php';

if (!hasPermission('aging_reports.view')) { die('Access Denied'); }

$db = getDB();
$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');
$client_id = (int)($_GET['client_id'] ?? 0);

// Build query for aging
$where = "WHERE ci.status IN ('sent','partially_paid','overdue') AND ci.balance_amount > 0";
$params = [];
if ($client_id > 0) {
    $where .= " AND ci.client_id = ?";
    $params[] = $client_id;
}

// Fetch all overdue/outstanding invoices with aging
$query = "
    SELECT ci.id, ci.invoice_number, ci.invoice_date, ci.due_date, ci.total_amount,
           ci.paid_amount, ci.balance_amount, ci.status,
           c.id AS client_id, c.company_name, c.client_code, c.phone, c.email
    FROM customer_invoices ci
    JOIN clients c ON ci.client_id = c.id
    $where
    ORDER BY c.company_name ASC, ci.due_date ASC
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Calculate aging buckets
$agingBuckets = [
    'current' => ['label' => '0-30 Days', 'total' => 0, 'count' => 0, 'invoices' => []],
    '31_60' => ['label' => '31-60 Days', 'total' => 0, 'count' => 0, 'invoices' => []],
    '61_90' => ['label' => '61-90 Days', 'total' => 0, 'count' => 0, 'invoices' => []],
    '90_plus' => ['label' => '90+ Days', 'total' => 0, 'count' => 0, 'invoices' => []],
];

$asOfTimestamp = strtotime($as_of_date);
$total_outstanding = 0;

foreach ($invoices as $inv) {
    $dueTimestamp = strtotime($inv['due_date']);
    $daysOverdue = floor(($asOfTimestamp - $dueTimestamp) / 86400);

    $bucketKey = 'current';
    if ($daysOverdue > 90) {
        $bucketKey = '90_plus';
    } elseif ($daysOverdue > 60) {
        $bucketKey = '61_90';
    } elseif ($daysOverdue > 30) {
        $bucketKey = '31_60';
    } elseif ($daysOverdue > 0) {
        $bucketKey = 'current';
    }

    // Normalize: 0-30 bucket for invoices not yet due or up to 30 days overdue
    if ($daysOverdue <= 0) {
        $bucketKey = 'current';
        $daysOverdue = 0;
    } elseif ($daysOverdue <= 30) {
        $bucketKey = 'current';
    }

    $agingBuckets[$bucketKey]['total'] += (float)$inv['balance_amount'];
    $agingBuckets[$bucketKey]['count']++;
    $agingBuckets[$bucketKey]['invoices'][] = $inv + ['days_overdue' => $daysOverdue];
    $total_outstanding += (float)$inv['balance_amount'];
}

// Per-client aging summary
$clientAging = [];
foreach ($invoices as $inv) {
    $cid = $inv['client_id'];
    if (!isset($clientAging[$cid])) {
        $clientAging[$cid] = [
            'company_name' => $inv['company_name'],
            'client_code' => $inv['client_code'],
            'phone' => $inv['phone'],
            'email' => $inv['email'],
            'current' => 0,
            '31_60' => 0,
            '61_90' => 0,
            '90_plus' => 0,
            'total' => 0,
        ];
    }
    $dueTimestamp = strtotime($inv['due_date']);
    $daysOverdue = floor(($asOfTimestamp - $dueTimestamp) / 86400);

    if ($daysOverdue <= 0) {
        $clientAging[$cid]['current'] += (float)$inv['balance_amount'];
    } elseif ($daysOverdue <= 30) {
        $clientAging[$cid]['current'] += (float)$inv['balance_amount'];
    } elseif ($daysOverdue <= 60) {
        $clientAging[$cid]['31_60'] += (float)$inv['balance_amount'];
    } elseif ($daysOverdue <= 90) {
        $clientAging[$cid]['61_90'] += (float)$inv['balance_amount'];
    } else {
        $clientAging[$cid]['90_plus'] += (float)$inv['balance_amount'];
    }
    $clientAging[$cid]['total'] += (float)$inv['balance_amount'];
}

// Clients for filter
$clients = $db->query("SELECT id, client_code, company_name FROM clients WHERE status = 'active' ORDER BY company_name")->fetchAll();

$total_overdue = $agingBuckets['31_60']['total'] + $agingBuckets['61_90']['total'] + $agingBuckets['90_plus']['total'];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Aging Report</h1>
        <p class="text-gray-600">Accounts receivable aging analysis</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/finance/index.php') ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Finance
        </a>
        <button onclick="window.print()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
            <i class="fas fa-print mr-2"></i>Print
        </button>
    </div>
</div>

<!-- Filter Form -->
<div class="bg-white rounded-lg shadow mb-6 p-4">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div class="w-44">
            <label class="block text-sm font-medium text-gray-700 mb-1">Client</label>
            <select name="client_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">All Clients</option>
                <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $client_id == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-medium text-gray-700 mb-1">As of Date</label>
            <input type="date" name="as_of_date" value="<?= sanitize($as_of_date) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
            <i class="fas fa-search mr-2"></i>Generate Report
        </button>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-400">
        <p class="text-xs text-gray-500 uppercase">0-30 Days</p>
        <p class="text-lg font-bold text-gray-800"><?= formatMoney($agingBuckets['current']['total']) ?></p>
        <p class="text-xs text-gray-400"><?= $agingBuckets['current']['count'] ?> invoices</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-yellow-400">
        <p class="text-xs text-gray-500 uppercase">31-60 Days</p>
        <p class="text-lg font-bold text-gray-800"><?= formatMoney($agingBuckets['31_60']['total']) ?></p>
        <p class="text-xs text-gray-400"><?= $agingBuckets['31_60']['count'] ?> invoices</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-400">
        <p class="text-xs text-gray-500 uppercase">61-90 Days</p>
        <p class="text-lg font-bold text-gray-800"><?= formatMoney($agingBuckets['61_90']['total']) ?></p>
        <p class="text-xs text-gray-400"><?= $agingBuckets['61_90']['count'] ?> invoices</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
        <p class="text-xs text-gray-500 uppercase">90+ Days</p>
        <p class="text-lg font-bold text-red-600"><?= formatMoney($agingBuckets['90_plus']['total']) ?></p>
        <p class="text-xs text-gray-400"><?= $agingBuckets['90_plus']['count'] ?> invoices</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-gray-800">
        <p class="text-xs text-gray-500 uppercase">Total Outstanding</p>
        <p class="text-lg font-bold text-gray-800"><?= formatMoney($total_outstanding) ?></p>
        <p class="text-xs text-gray-400">Overdue: <?= formatMoney($total_overdue) ?></p>
    </div>
</div>

<!-- Client Aging Summary -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="px-4 py-3 border-b">
        <h3 class="text-lg font-semibold text-gray-800">Aging Summary by Client</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">0-30 Days</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">31-60 Days</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">61-90 Days</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">90+ Days</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($clientAging as $ca): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-800"><?= sanitize($ca['company_name']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono text-gray-800"><?= formatMoney($ca['current']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono <?= $ca['31_60'] > 0 ? 'text-yellow-600' : 'text-gray-800' ?>"><?= formatMoney($ca['31_60']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono <?= $ca['61_90'] > 0 ? 'text-orange-600' : 'text-gray-800' ?>"><?= formatMoney($ca['61_90']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono <?= $ca['90_plus'] > 0 ? 'text-red-600' : 'text-gray-800' ?>"><?= formatMoney($ca['90_plus']) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-mono font-bold text-gray-800"><?= formatMoney($ca['total']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($clientAging)): ?>
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                        <i class="fas fa-clock text-4xl mb-3 text-gray-300"></i>
                        <p>No outstanding invoices found</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Aging Buckets Detail -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <?php foreach ($agingBuckets as $key => $bucket): ?>
    <?php if (!empty($bucket['invoices'])): ?>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-4 py-3 border-b flex justify-between items-center">
            <h3 class="text-sm font-semibold text-gray-800"><?= $bucket['label'] ?></h3>
            <span class="text-sm font-mono font-bold <?= $key === '90_plus' ? 'text-red-600' : 'text-gray-800' ?>"><?= formatMoney($bucket['total']) ?></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Invoice</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Due</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Days</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm">
                    <?php foreach ($bucket['invoices'] as $inv): ?>
                    <tr>
                        <td class="px-3 py-2 font-mono text-gray-800"><?= sanitize($inv['invoice_number']) ?></td>
                        <td class="px-3 py-2 text-gray-600"><?= sanitize($inv['company_name']) ?></td>
                        <td class="px-3 py-2 text-right text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($inv['due_date'])) ?></td>
                        <td class="px-3 py-2 text-right <?= $inv['days_overdue'] > 90 ? 'text-red-600 font-bold' : 'text-gray-600' ?>"><?= $inv['days_overdue'] ?></td>
                        <td class="px-3 py-2 text-right font-mono text-gray-800"><?= formatMoney((float)$inv['balance_amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
</div>

<style media="print">
    .erp-layout, .erp-main { margin: 0 !important; padding: 0 !important; }
    nav, .sidebar, .navbar, .erp-layout > nav, .erp-layout > div:first-child { display: none !important; }
    button, .bg-blue-600, .flex.justify-between.items-center.mb-6 { display: none !important; }
    .bg-white { box-shadow: none !important; border: 1px solid #e5e7eb !important; }
    body { background: white; }
    @page { margin: 15mm; }
</style>

<?php require_once '../../../includes/footer.php'; ?>
