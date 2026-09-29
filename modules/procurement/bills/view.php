<?php
$pageTitle = 'Vendor Bill Details';
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('procurement.bills.view')) { die('Access Denied'); }

$db = getDB();
$billId = (int)($_GET['id'] ?? 0);
if ($billId <= 0) { flash('error', 'Invalid bill ID.'); redirect('index.php'); }

$bill = BillService::getBill($db, $billId);
if (!$bill) { flash('error', 'Bill not found.'); redirect('index.php'); }

$items = BillService::getItems($db, $billId);
$matches = BillService::getMatches($db, $billId);
$exceptions = BillService::getExceptions($db, $billId);
$approvals = BillService::getApprovals($db, $billId);
$attachments = BillService::getAttachments($db, $billId);

$statusBadges = [
    'draft' => 'bg-yellow-100 text-yellow-800',
    'pending_approval' => 'bg-blue-100 text-blue-800',
    'matched' => 'bg-green-100 text-green-800',
    'exception' => 'bg-red-100 text-red-800',
    'approved' => 'bg-green-100 text-green-800',
    'posted' => 'bg-green-100 text-green-800',
    'rejected' => 'bg-red-100 text-red-800',
    'cancelled' => 'bg-gray-100 text-gray-800',
];
$matchBadges = [
    'pending' => 'bg-gray-100 text-gray-800',
    'matched' => 'bg-green-100 text-green-800',
    'exception' => 'bg-red-100 text-red-800',
    'overridden' => 'bg-yellow-100 text-yellow-800',
];
?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-start mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Vendor Bill <?= sanitize($bill['bill_number']) ?></h1>
            <p class="mt-1 text-sm text-gray-500">
                <span class="px-2 py-1 text-xs rounded font-medium <?= $statusBadges[$bill['status']] ?? 'bg-gray-100 text-gray-800' ?>"><?= ucfirst($bill['status']) ?></span>
                <?php if ($bill['match_status']): ?>
                <span class="ml-2 px-2 py-1 text-xs rounded font-medium <?= $matchBadges[$bill['match_status']] ?? 'bg-gray-100 text-gray-800' ?>">Match: <?= ucfirst($bill['match_status']) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2">
            <?php if (in_array($bill['status'], ['draft', 'rejected']) && hasPermission('procurement.bills.edit')): ?>
            <a href="edit.php?id=<?= $bill['id'] ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg text-sm font-medium">Edit</a>
            <?php endif; ?>
            <?php if (in_array($bill['status'], ['draft', 'rejected']) && hasPermission('procurement.bills.create')): ?>
            <form method="POST" action="submit.php" class="inline">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" value="<?= $bill['id'] ?>">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-sm font-medium">Submit</button>
            </form>
            <?php endif; ?>
            <?php if (($bill['status'] === 'pending_approval' || $bill['status'] === 'matched') && hasPermission('procurement.bills.approve')): ?>
            <form method="POST" action="approve.php" class="inline" onsubmit="return confirm('Approve this bill?');">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" value="<?= $bill['id'] ?>">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg text-sm font-medium">Approve</button>
            </form>
            <?php endif; ?>
            <?php if (($bill['status'] === 'approved' || $bill['status'] === 'matched') && hasPermission('procurement.bills.match')): ?>
            <a href="match.php?id=<?= $bill['id'] ?>" class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded-lg text-sm font-medium">3-Way Match</a>
            <?php endif; ?>
            <?php if (($bill['status'] === 'approved' || $bill['status'] === 'matched') && hasPermission('procurement.bills.match')): ?>
            <form method="POST" action="post.php" class="inline" onsubmit="return confirm('Post this bill to finance?');">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" value="<?= $bill['id'] ?>">
                <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white px-3 py-2 rounded-lg text-sm font-medium">Post to Finance</button>
            </form>
            <?php endif; ?>
            <?php if (in_array($bill['status'], ['draft', 'rejected']) && hasPermission('procurement.bills.cancel')): ?>
            <form method="POST" action="cancel.php" class="inline" onsubmit="return confirm('Cancel this bill? This action cannot be undone.');">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" value="<?= $bill['id'] ?>">
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg text-sm font-medium">Cancel</button>
            </form>
            <?php endif; ?>
            <a href="index.php" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-3 py-2 rounded-lg text-sm font-medium">Back</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Bill Header</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-500">Bill Number</dt><dd class="text-gray-900 font-medium"><?= sanitize($bill['bill_number']) ?></dd></div>
                    <div><dt class="text-gray-500">Vendor Invoice Number</dt><dd class="text-gray-900"><?= sanitize($bill['vendor_invoice_number'] ?: '-') ?></dd></div>
                    <div><dt class="text-gray-500">Vendor</dt><dd class="text-gray-900"><?= sanitize($bill['vendor_name']) ?> (<?= sanitize($bill['vendor_code'] ?? '') ?>)</dd></div>
                    <div><dt class="text-gray-500">Company</dt><dd class="text-gray-900"><?= sanitize($bill['company_name']) ?></dd></div>
                    <div><dt class="text-gray-500">PO Reference</dt><dd class="text-gray-900"><?= $bill['procurement_po_id'] ? '<a href="../purchase_orders/view.php?id=' . $bill['procurement_po_id'] . '" class="text-blue-600 hover:underline">' . sanitize($bill['po_number']) . '</a>' : '-' ?></dd></div>
                    <div><dt class="text-gray-500">Receipt Reference</dt><dd class="text-gray-900"><?= $bill['procurement_gr_id'] ? '<a href="../receipts/view/index.php?id=' . $bill['procurement_gr_id'] . '" class="text-blue-600 hover:underline">' . sanitize($bill['gr_number']) . '</a>' : ($bill['procurement_sr_id'] ? '<a href="../receipts/view/index.php?id=' . $bill['procurement_sr_id'] . '" class="text-blue-600 hover:underline">' . sanitize($bill['sr_number']) . '</a>' : '-') ?></dd></div>
                    <div><dt class="text-gray-500">Bill Date</dt><dd class="text-gray-900"><?= sanitize($bill['bill_date']) ?></dd></div>
                    <div><dt class="text-gray-500">Due Date</dt><dd class="text-gray-900"><?= sanitize($bill['due_date']) ?></dd></div>
                    <div><dt class="text-gray-500">Currency</dt><dd class="text-gray-900"><?= sanitize($bill['currency']) ?></dd></div>
                    <div><dt class="text-gray-500">Payment Terms</dt><dd class="text-gray-900"><?= sanitize($bill['payment_terms'] ?: '-') ?></dd></div>
                    <div class="sm:col-span-2"><dt class="text-gray-500">Notes</dt><dd class="text-gray-900"><?= nl2br(sanitize($bill['notes'] ?: '-')) ?></dd></div>
                </dl>
            </div>

            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Bill Items</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">PO Item</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Discount</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Tax</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Line Total</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Match</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($items as $it): ?>
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900"><?= sanitize($it['description']) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-700"><?= (int)$it['procurement_po_item_id'] ?: '-' ?></td>
                                <td class="px-4 py-3 text-sm text-gray-700 text-right"><?= number_format((float)$it['quantity'], 3) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-700 text-right"><?= number_format((float)$it['unit_price'], 4) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-700 text-right"><?= number_format((float)$it['discount_amount'], 2) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-700 text-right"><?= number_format((float)$it['tax_amount'], 2) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-900 font-medium text-right"><?= number_format((float)$it['line_total'], 2) ?></td>
                                <td class="px-4 py-3 text-center text-xs">
                                    <?php if ($it['match_quantity_status'] && $it['match_quantity_status'] !== 'pending'): ?>
                                    <span class="px-1 py-1 rounded <?= $it['match_quantity_status'] === 'matched' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>"><?= ucfirst($it['match_quantity_status']) ?></span>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="5" class="px-4 py-2 text-sm text-gray-600 text-right">Subtotal:</td>
                                <td class="px-4 py-2 text-sm text-gray-900 text-right"><?= number_format((float)$bill['subtotal'], 2) ?></td>
                            </tr>
                            <tr>
                                <td colspan="5" class="px-4 py-2 text-sm text-gray-600 text-right">Discount:</td>
                                <td class="px-4 py-2 text-sm text-gray-900 text-right"><?= number_format((float)$bill['discount_amount'], 2) ?></td>
                            </tr>
                            <tr>
                                <td colspan="5" class="px-4 py-2 text-sm text-gray-600 text-right">Tax:</td>
                                <td class="px-4 py-2 text-sm text-gray-900 text-right"><?= number_format((float)$bill['tax_amount'], 2) ?></td>
                            </tr>
                            <tr>
                                <td colspan="5" class="px-4 py-2 text-sm font-semibold text-gray-900 text-right">Grand Total:</td>
                                <td class="px-4 py-2 text-sm font-semibold text-gray-900 text-right"><?= number_format((float)$bill['total_amount'], 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <?php if (count($matches)): ?>
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">3-Way Match Results</h2>
                <?php foreach ($matches as $m): ?>
                <div class="border rounded-lg p-4 mb-3">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div><span class="text-gray-500">PO Amount:</span> <span class="font-medium"><?= number_format((float)$m['total_po_amount'], 2) ?></span></div>
                        <div><span class="text-gray-500">Receipt Amount:</span> <span class="font-medium"><?= number_format((float)$m['total_gr_amount'], 2) ?></span></div>
                        <div><span class="text-gray-500">Bill Amount:</span> <span class="font-medium"><?= number_format((float)$m['total_bill_amount'], 2) ?></span></div>
                        <div><span class="text-gray-500">Variance:</span> <span class="font-medium"><?= number_format((float)$m['variance_amount'], 2) ?> (<?= number_format((float)$m['variance_percent'], 2) ?>%)</span></div>
                    </div>
                    <?php if ($m['exception_details']): ?>
                    <div class="mt-3 text-sm text-red-700 bg-red-50 p-2 rounded"><?= sanitize($m['exception_details']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (count($exceptions)): ?>
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Exceptions</h2>
                <?php foreach ($exceptions as $ex): ?>
                <div class="border rounded-lg p-4 mb-3">
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="px-2 py-1 text-xs rounded font-medium <?= $ex['severity'] === 'critical' ? 'bg-red-100 text-red-800' : ($ex['severity'] === 'high' ? 'bg-orange-100 text-orange-800' : 'bg-yellow-100 text-yellow-800') ?>"><?= ucfirst($ex['severity']) ?></span>
                            <span class="ml-2 text-sm font-medium"><?= ucfirst(str_replace('_', ' ', $ex['exception_type'])) ?></span>
                            <p class="mt-1 text-sm text-gray-600"><?= sanitize($ex['reason']) ?></p>
                        </div>
                        <span class="px-2 py-1 text-xs rounded font-medium <?= $ex['status'] === 'open' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>"><?= ucfirst($ex['status']) ?></span>
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

            <?php if (count($approvals)): ?>
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Approval History</h2>
                <div class="space-y-3">
                    <?php foreach ($approvals as $a): ?>
                    <div class="flex items-start gap-3 text-sm">
                        <div class="flex-1">
                            <span class="font-medium"><?= sanitize($a['approver_name']) ?></span>
                            <span class="text-gray-500"> — <?= ucfirst($a['action']) ?></span>
                            <span class="text-gray-400 text-xs"><?= sanitize($a['created_at']) ?></span>
                            <?php if ($a['comments']): ?><p class="text-gray-600 mt-1"><?= sanitize($a['comments']) ?></p><?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="space-y-6">
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Summary</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd class="text-gray-900"><?= number_format((float)$bill['subtotal'], 2) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Discount</dt><dd class="text-gray-900"><?= number_format((float)$bill['discount_amount'], 2) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Tax</dt><dd class="text-gray-900"><?= number_format((float)$bill['tax_amount'], 2) ?></dd></div>
                    <div class="flex justify-between font-semibold text-base border-t pt-2"><dt class="text-gray-900">Total</dt><dd class="text-gray-900"><?= number_format((float)$bill['total_amount'], 2) ?></dd></div>
                    <?php if ($bill['journal_entry_id']): ?>
                    <div class="flex justify-between"><dt class="text-gray-500">Journal Entry</dt><dd class="text-gray-900"><?= (int)$bill['journal_entry_id'] ?></dd></div>
                    <?php endif; ?>
                </dl>
            </div>

            <?php if (count($attachments)): ?>
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Attachments</h2>
                <ul class="space-y-2 text-sm">
                    <?php foreach ($attachments as $att): ?>
                    <li class="flex items-center gap-2">
                        <i class="fas fa-paperclip text-gray-400"></i>
                        <a href="<?= sanitize($att['stored_path']) ?>" target="_blank" class="text-blue-600 hover:underline"><?= sanitize($att['original_name']) ?></a>
                        <span class="text-gray-400 text-xs">(<?= number_format($att['file_size'] / 1024, 1) ?> KB)</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
