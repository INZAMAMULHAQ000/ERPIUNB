<?php
$pageTitle = 'Procurement Reports';
require_once __DIR__ . '/../../bootstrap.php';

if (!hasPermission('procurement.bills.report')) { die('Access Denied'); }

$db = getDB();
$report = $_GET['report'] ?? 'outstanding';

$outstanding = [];
$unbilled = [];
$matchExceptions = [];
$billRegister = [];

if ($report === 'outstanding') {
    $outstanding = BillService::procurementOutstanding($db);
} elseif ($report === 'unbilled') {
    $unbilled = BillService::unbilledReceipts($db);
} elseif ($report === 'exceptions') {
    $matchExceptions = BillService::matchExceptions($db);
} elseif ($report === 'register') {
    $billRegister = BillService::listBills($db, [], 500, 0)['rows'];
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Procurement Reports</h1>

    <div class="bg-white shadow rounded-lg mb-6">
        <div class="p-4 border-b border-gray-200">
            <form method="GET" class="flex gap-4 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Report</label>
                    <select name="report" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="outstanding" <?= $report === 'outstanding' ? 'selected' : '' ?>>Outstanding Procurement</option>
                        <option value="unbilled" <?= $report === 'unbilled' ? 'selected' : '' ?>>Unbilled Receipts</option>
                        <option value="exceptions" <?= $report === 'exceptions' ? 'selected' : '' ?>>Match Exceptions</option>
                        <option value="register" <?= $report === 'register' ? 'selected' : '' ?>>Bill Register</option>
                    </select>
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Run Report</button>
            </form>
        </div>
    </div>

    <?php if ($report === 'outstanding'): ?>
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-3 border-b border-gray-200"><h2 class="text-lg font-semibold text-gray-900">Outstanding Procurement Value</h2></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bill #</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendor</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th><th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th><th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Outstanding</th></tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($outstanding as $r): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= sanitize($r['bill_number']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($r['vendor_name']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($r['due_date']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900 text-right"><?= number_format((float)$r['total_amount'], 2) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900 font-medium text-right"><?= number_format((float)$r['outstanding'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($outstanding)): ?><tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No outstanding bills found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ($report === 'unbilled'): ?>
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-3 border-b border-gray-200"><h2 class="text-lg font-semibold text-gray-900">Unbilled Receipts</h2></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">GR #</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">PO</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendor</th><th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Received</th><th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Billed</th><th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Unbilled</th></tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($unbilled as $r): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= sanitize($r['gr_number']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($r['po_number']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($r['vendor_name']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900 text-right"><?= number_format((float)$r['accepted_quantity_total'], 3) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900 text-right"><?= number_format((float)$r['billed_qty'], 3) ?></td>
                        <td class="px-4 py-3 text-sm text-red-600 font-medium text-right"><?= number_format((float)$r['unbilled_qty'], 3) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($unbilled)): ?><tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No unbilled receipts found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ($report === 'exceptions'): ?>
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-3 border-b border-gray-200"><h2 class="text-lg font-semibold text-gray-900">3-Way Match Exceptions</h2></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bill #</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendor</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">PO</th><th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Match</th><th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Variance</th><th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Exception Status</th></tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($matchExceptions as $r): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= sanitize($r['bill_number']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($r['vendor_name']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($r['po_number'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-center"><span class="px-2 py-1 text-xs rounded font-medium <?= $r['match_status'] === 'matched' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>"><?= ucfirst($r['match_status']) ?></span></td>
                        <td class="px-4 py-3 text-sm text-gray-900 text-right"><?= number_format((float)$r['variance_amount'], 2) ?> (<?= number_format((float)$r['variance_percent'], 2) ?>%)</td>
                        <td class="px-4 py-3 text-center"><span class="px-2 py-1 text-xs rounded font-medium <?= $r['exception_status'] === 'open' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>"><?= ucfirst($r['exception_status'] ?? 'none') ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($matchExceptions)): ?><tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No match exceptions found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ($report === 'register'): ?>
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-3 border-b border-gray-200"><h2 class="text-lg font-semibold text-gray-900">Vendor Bill Register</h2></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bill #</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendor</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">PO</th><th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th><th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th></tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($billRegister as $r): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= sanitize($r['bill_number']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($r['vendor_name']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($r['company_name']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($r['bill_date']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= sanitize($r['po_number'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900 text-right"><?= number_format((float)$r['total_amount'], 2) ?></td>
                        <td class="px-4 py-3 text-center"><span class="px-2 py-1 text-xs rounded font-medium <?= $statusBadges[$r['status']] ?? 'bg-gray-100 text-gray-800' ?>"><?= ucfirst($r['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($billRegister)): ?><tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No bills found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
