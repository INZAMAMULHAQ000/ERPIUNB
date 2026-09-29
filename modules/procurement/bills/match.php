<?php
$pageTitle = '3-Way Match';
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('procurement.bills.match')) { die('Access Denied'); }

$db = getDB();
$billId = (int)($_GET['id'] ?? 0);
if ($billId <= 0) { flash('error', 'Invalid bill ID.'); redirect('index.php'); }

$bill = BillService::getBill($db, $billId);
if (!$bill) { flash('error', 'Bill not found.'); redirect('index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid or expired form token.');
        redirect('view.php?id=' . $billId);
    }

    $result = BillService::run3WayMatch($db, $billId, $_SESSION['user_id']);
    if ($result['success']) {
        flash('success', '3-way match completed. Status: ' . ucfirst($result['match_status']) . ' (Variance: ' . number_format($result['variance_percent'], 2) . '%)');
    } else {
        flash('error', $result['message']);
    }
    redirect('view.php?id=' . $billId);
}

$matches = BillService::getMatches($db, $billId);
$exceptions = BillService::getExceptions($db, $billId);
$items = BillService::getItems($db, $billId);

$poId = (int)$bill['procurement_po_id'];
$grId = $bill['procurement_gr_id'] ? (int)$bill['procurement_gr_id'] : null;
$srId = $bill['procurement_sr_id'] ? (int)$bill['procurement_sr_id'] : null;

$poItems = [];
if ($poId) {
    $poItems = $db->prepare("SELECT * FROM purchase_order_items WHERE po_id=? ORDER BY po_item_number, id");
    $poItems->execute([$poId]);
    $poItems = $poItems->fetchAll();
}

$grItemMap = [];
if ($grId) {
    $grItems = $db->prepare("SELECT * FROM goods_receipt_items WHERE gr_id=? ORDER BY sort_order, id");
    $grItems->execute([$grId]);
    foreach ($grItems->fetchAll() as $gi) { $grItemMap[$gi['po_item_id']] = $gi; }
}

$srItemMap = [];
if ($srId) {
    $srItems = $db->prepare("SELECT * FROM service_receipt_items WHERE sr_id=? ORDER BY sort_order, id");
    $srItems->execute([$srId]);
    foreach ($srItems->fetchAll() as $si) { $srItemMap[$si['po_item_id']] = $si; }
}

$billItemMap = [];
foreach ($items as $bi) { $billItemMap[$bi['procurement_po_item_id'] ?? 0] = $bi; }
?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">3-Way Match: <?= sanitize($bill['bill_number']) ?></h1>
        <div class="flex gap-2">
            <form method="POST" class="inline">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Run 3-Way Match</button>
            </form>
            <a href="view.php?id=<?= $bill['id'] ?>" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg text-sm font-medium">Back</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-sm font-medium text-gray-500 uppercase mb-2">Purchase Order</h3>
            <p class="text-lg font-semibold text-gray-900"><?= sanitize($bill['po_number'] ?? '-') ?></p>
            <p class="text-sm text-gray-600">Total: <?= number_format((float)($bill['subtotal'] ?? 0), 2) ?></p>
        </div>
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-sm font-medium text-gray-500 uppercase mb-2">Goods/Services Receipt</h3>
            <p class="text-lg font-semibold text-gray-900"><?= sanitize($bill['gr_number'] ?? $bill['sr_number'] ?? '-') ?></p>
            <p class="text-sm text-gray-600">Status: <?= sanitize($bill['status']) ?></p>
        </div>
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-sm font-medium text-gray-500 uppercase mb-2">Vendor Bill</h3>
            <p class="text-lg font-semibold text-gray-900"><?= sanitize($bill['bill_number']) ?></p>
            <p class="text-sm text-gray-600">Total: <?= number_format((float)$bill['total_amount'], 2) ?></p>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Line-by-Line Comparison</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">PO Qty</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">PO Price</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Received Qty</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Received Price</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Bill Qty</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Bill Price</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Qty Match</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Price Match</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($poItems as $poItem): 
                        $grItem = $grItemMap[$poItem['id']] ?? $srItemMap[$poItem['id']] ?? null;
                        $billItem = $billItemMap[$poItem['id']] ?? null;
                        $receivedQty = $grItem ? (float)$grItem['accepted_quantity'] : ($srItemMap[$poItem['id']] ? (float)$srItemMap[$poItem['id']]['accepted_quantity'] : 0);
                        $receivedPrice = $grItem ? (float)$grItem['unit_price'] : ($srItemMap[$poItem['id']] ? (float)$srItemMap[$poItem['id']]['unit_price'] : (float)$poItem['unit_price']);
                        $billQty = $billItem ? (float)$billItem['quantity'] : 0;
                        $billPrice = $billItem ? (float)$billItem['unit_price'] : 0;
                        $qtyDiff = $billQty - $receivedQty;
                        $priceDiffPct = $receivedPrice > 0 ? abs(($billPrice - $receivedPrice) / $receivedPrice * 100) : 0;
                        $toleranceQty = (float)($bill['tolerance_quantity'] ?? 5);
                        $tolerancePrice = (float)($bill['tolerance_price'] ?? 5);
                        $qtyOk = abs($qtyDiff) <= $toleranceQty;
                        $priceOk = $priceDiffPct <= $tolerancePrice;
                    ?>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900"><?= sanitize($poItem['item_name']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 text-right"><?= number_format((float)$poItem['ordered_quantity'], 3) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 text-right"><?= number_format((float)$poItem['unit_price'], 4) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 text-right"><?= number_format($receivedQty, 3) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 text-right"><?= number_format($receivedPrice, 4) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 text-right"><?= number_format($billQty, 3) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 text-right"><?= number_format($billPrice, 4) ?></td>
                        <td class="px-4 py-3 text-center"><?= $qtyOk ? '<span class="text-green-600">&#10003;</span>' : '<span class="text-red-600">&#10007; ' . number_format($qtyDiff, 3) . '</span>' ?></td>
                        <td class="px-4 py-3 text-center"><?= $priceOk ? '<span class="text-green-600">&#10003;</span>' : '<span class="text-red-600">&#10007; ' . number_format($priceDiffPct, 2) . '%</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($poItems)): ?>
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">No items found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (count($exceptions)): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-6">
        <h2 class="text-lg font-semibold text-red-900 mb-4">Match Exceptions</h2>
        <?php foreach ($exceptions as $ex): ?>
        <div class="border border-red-200 rounded-lg p-4 mb-3 bg-white">
            <div class="flex justify-between items-start">
                <div>
                    <span class="px-2 py-1 text-xs rounded font-medium <?= $ex['severity'] === 'critical' ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800' ?>"><?= ucfirst($ex['severity']) ?></span>
                    <span class="ml-2 text-sm font-medium"><?= ucfirst(str_replace('_', ' ', $ex['exception_type'])) ?></span>
                    <p class="mt-1 text-sm text-gray-600"><?= sanitize($ex['reason']) ?></p>
                    <p class="mt-1 text-sm text-gray-500">Difference: <?= number_format((float)$ex['difference'], 2) ?> (<?= number_format((float)$ex['difference_percent'], 2) ?>%)</p>
                </div>
            </div>
            <?php if ($ex['status'] === 'open' && hasPermission('procurement.bills.override')): ?>
            <div class="mt-3 flex gap-2">
                <a href="exception.php?id=<?= $ex['id'] ?>&action=resolve" class="text-green-600 hover:text-green-900 text-sm">Resolve</a>
                <a href="exception.php?id=<?= $ex['id'] ?>&action=override" class="text-yellow-600 hover:text-yellow-900 text-sm">Override</a>
                <a href="exception.php?id=<?= $ex['id'] ?>&action=reject" class="text-red-600 hover:text-red-900 text-sm">Reject</a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
