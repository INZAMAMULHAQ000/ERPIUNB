<?php
$pageTitle = 'Asset Impairment Report';
require_once '../../../../includes/header.php';

if (!hasPermission('fixedasset.report')) { die('Access Denied'); }

$db = getDB();

$search = $_GET['search'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$approval_status = $_GET['approval_status'] ?? '';

$where = "WHERE 1=1";
$params = [];

if ($search) { 
    $where .= " AND (a.asset_code LIKE ? OR a.asset_name LIKE ?)";
    $params[] = "%$search%"; 
    $params[] = "%$search%"; 
}
if ($from_date) { $where .= " AND i.impairment_date >= ?"; $params[] = $from_date; }
if ($to_date) { $where .= " AND i.impairment_date <= ?"; $params[] = $to_date; }
if ($approval_status) { $where .= " AND i.approval_status = ?"; $params[] = $approval_status; }

$impairments = $db->prepare("
    SELECT 
        i.id as impairment_id,
        a.asset_code,
        a.asset_name,
        ac.category_name,
        i.impairment_date,
        i.carrying_amount,
        i.recoverable_amount,
        i.impairment_loss,
        i.reason,
        i.approval_status,
        u.name as created_by
    FROM asset_impairment i
    LEFT JOIN fixed_asset_financials f ON i.asset_id = f.asset_id
    LEFT JOIN assets a ON f.asset_id = a.id
    LEFT JOIN fixed_asset_categories fac ON f.asset_category_id = fac.id
    LEFT JOIN asset_categories ac ON fac.asset_category_id = ac.id
    LEFT JOIN users u ON i.approved_by = u.id
    $where
    ORDER BY i.impairment_date DESC
");
$impairments->execute($params);
$impairments = $impairments->fetchAll();

$totalLoss = array_sum(array_column($impairments, 'impairment_loss'));
$totalApproved = array_sum(array_filter(array_column($impairments, 'impairment_loss'), function($val) { return $val > 0; }));
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Asset Impairment Report</h1>
        <p class="text-gray-600">Record of all asset impairments</p>
    </div>
    <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
        <i class="fas fa-print mr-2"></i>Print
    </button>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <input type="hidden" name="report" value="impairment">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Asset code or name" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
            <input type="date" name="from_date" value="<?= sanitize($from_date) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
            <input type="date" name="to_date" value="<?= sanitize($to_date) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-search"></i></button>
    </form>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
        <div class="text-sm text-red-600">Total Impairment Loss</div>
        <div class="text-2xl font-bold text-red-800"><?= formatAmount($totalLoss) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-gray-500">
        <div class="text-sm text-gray-600">Total Impairments</div>
        <div class="text-2xl font-bold text-gray-800"><?= count($impairments) ?></div>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left">Asset Code</th>
                <th class="px-4 py-3 text-left">Asset Name</th>
                <th class="px-4 py-3 text-left">Category</th>
                <th class="px-4 py-3 text-center">Date</th>
                <th class="px-4 py-3 text-right">Carrying Amount</th>
                <th class="px-4 py-3 text-right">Recoverable Amount</th>
                <th class="px-4 py-3 text-right text-red-600">Impairment Loss</th>
                <th class="px-4 py-3 text-center">Status</th>
                <th class="px-4 py-3 text-left">Reason</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($impairments as $imp): ?>
            <tr>
                <td class="px-4 py-3 font-medium"><?= sanitize($imp['asset_code']) ?></td>
                <td class="px-4 py-3"><?= sanitize($imp['asset_name']) ?></td>
                <td class="px-4 py-3"><?= sanitize($imp['category_name']) ?></td>
                <td class="px-4 py-3 text-center"><?= date('M d, Y', strtotime($imp['impairment_date'])) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($imp['carrying_amount']) ?></td>
                <td class="px-4 py-3 text-right"><?= formatAmount($imp['recoverable_amount']) ?></td>
                <td class="px-4 py-3 text-right font-bold text-red-600">-<?= formatAmount($imp['impairment_loss']) ?></td>
                <td class="px-4 py-3 text-center">
                    <span class="px-2 py-1 text-xs rounded
                        <?= $imp['approval_status'] == 'approved' ? 'bg-green-100 text-green-800' :
                           ($imp['approval_status'] == 'rejected' ? 'bg-red-100 text-red-800' :
                           'bg-yellow-100 text-yellow-800') ?>">
                        <?= ucfirst($imp['approval_status']) ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-gray-600 text-xs"><?= sanitize($imp['reason']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
