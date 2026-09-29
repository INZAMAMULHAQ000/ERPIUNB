<?php
$pageTitle = 'Vendor Profile';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('vendor_master.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('error', 'Invalid vendor ID.'); redirect('index.php'); }

// Load vendor
$stmt = $db->prepare("SELECT * FROM vendors WHERE id = ?");
$stmt->execute([$id]);
$vendor = $stmt->fetch();
if (!$vendor) { flash('error', 'Vendor not found.'); redirect('index.php'); }

// AP Summary
$stmt = $db->query("SELECT
    (SELECT COALESCE(SUM(total_amount),0) FROM vendor_bills WHERE vendor_id = $id AND status IN ('approved','partially_paid','paid','overdue')) as total_billed,
    (SELECT COALESCE(SUM(total_amount),0) FROM vendor_bills WHERE vendor_id = $id AND status = 'draft') as draft_bills_total,
    (SELECT COALESCE(SUM(balance_amount),0) FROM vendor_bills WHERE vendor_id = $id AND status IN ('approved','partially_paid','overdue')) as outstanding,
    (SELECT COALESCE(SUM(balance_amount),0) FROM vendor_bills WHERE vendor_id = $id AND status IN ('approved','partially_paid','overdue') AND due_date < CURDATE()) as overdue,
    (SELECT COALESCE(SUM(amount),0) FROM vendor_payments WHERE vendor_id = $id) as total_paid,
    (SELECT COUNT(*) FROM purchase_orders WHERE vendor_id = $id) as po_count,
    (SELECT COUNT(*) FROM purchase_orders WHERE vendor_id = $id AND status IN ('draft','approved','sent')) as pending_pos,
    (SELECT COUNT(*) FROM vendor_bills WHERE vendor_id = $id) as bill_count,
    (SELECT COUNT(*) FROM vendor_bills WHERE vendor_id = $id AND status IN ('approved','partially_paid','overdue')) as open_bills,
    (SELECT COUNT(*) FROM vendor_bills WHERE vendor_id = $id AND status IN ('approved','partially_paid','overdue') AND due_date < CURDATE()) as overdue_bills
");
$summary = $stmt->fetch();

// Recent bills
$bills = $db->prepare("SELECT * FROM vendor_bills WHERE vendor_id = ? ORDER BY created_at DESC LIMIT 10");
$bills->execute([$id]);
$bills = $bills->fetchAll();

// Recent payments
$payments = $db->prepare("SELECT * FROM vendor_payments WHERE vendor_id = ? ORDER BY created_at DESC LIMIT 10");
$payments->execute([$id]);
$payments = $payments->fetchAll();

// Recent POs
$pos = $db->prepare("SELECT * FROM purchase_orders WHERE vendor_id = ? ORDER BY created_at DESC LIMIT 10");
$pos->execute([$id]);
$pos = $pos->fetchAll();

$statusBadges = [
    'draft' => 'bg-gray-100 text-gray-800',
    'approved' => 'bg-blue-100 text-blue-800',
    'sent' => 'bg-indigo-100 text-indigo-800',
    'partially_paid' => 'bg-yellow-100 text-yellow-800',
    'paid' => 'bg-green-100 text-green-800',
    'overdue' => 'bg-red-100 text-red-800',
    'cancelled' => 'bg-gray-200 text-gray-600',
    'partially_received' => 'bg-yellow-100 text-yellow-800',
    'received' => 'bg-green-100 text-green-800',
    'closed' => 'bg-blue-100 text-blue-800',
];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= sanitize($vendor['vendor_name']) ?></h1>
        <p class="text-gray-600">Vendor Profile · Code: <?= sanitize($vendor['vendor_code'] ?? '--') ?></p>
    </div>
    <div class="flex gap-2">
        <a href="index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back
        </a>
        <?php if (hasPermission('vendor_master.edit')): ?>
        <a href="edit.php?id=<?= $id ?>" class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700 transition">
            <i class="fas fa-pencil mr-2"></i>Edit
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <p class="text-sm text-gray-500">Total Billed</p>
        <p class="text-xl font-bold text-gray-800"><?= formatMoney((float)$summary['total_billed']) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <p class="text-sm text-gray-500">Total Paid</p>
        <p class="text-xl font-bold text-green-600"><?= formatMoney((float)$summary['total_paid']) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <p class="text-sm text-gray-500">Outstanding</p>
        <p class="text-xl font-bold text-red-600"><?= formatMoney((float)$summary['outstanding']) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <p class="text-sm text-gray-500">Overdue</p>
        <p class="text-xl font-bold text-red-700"><?= formatMoney((float)$summary['overdue']) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <p class="text-sm text-gray-500">Purchase Orders</p>
        <p class="text-xl font-bold text-gray-800"><?= (int)$summary['po_count'] ?></p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Vendor Details -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Vendor Details</h3>
            <dl class="space-y-3">
                <div>
                    <dt class="text-sm text-gray-500">Vendor Code</dt>
                    <dd class="text-sm font-medium text-gray-900"><?= sanitize($vendor['vendor_code'] ?? '--') ?></dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">Contact Person</dt>
                    <dd class="text-sm font-medium text-gray-900"><?= sanitize($vendor['contact_person'] ?? '--') ?></dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">Email</dt>
                    <dd class="text-sm font-medium text-gray-900"><?= sanitize($vendor['email'] ?? '--') ?></dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">Phone</dt>
                    <dd class="text-sm font-medium text-gray-900"><?= sanitize($vendor['phone'] ?? '--') ?></dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">VAT/GST Number</dt>
                    <dd class="text-sm font-medium text-gray-900"><?= sanitize($vendor['vat_number'] ?? '--') ?></dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">Payment Terms</dt>
                    <dd class="text-sm font-medium text-gray-900"><?= sanitize($vendor['payment_terms'] ?? '--') ?></dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">Currency</dt>
                    <dd class="text-sm font-medium text-gray-900"><?= sanitize($vendor['currency'] ?? 'AED') ?></dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">Status</dt>
                    <dd>
                        <span class="px-2 py-1 text-xs rounded font-medium <?= $vendor['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                            <?= ucfirst($vendor['status']) ?>
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">Website</dt>
                    <dd class="text-sm font-medium text-gray-900"><?= $vendor['website'] ? '<a href="' . sanitize($vendor['website']) . '" target="_blank" class="text-blue-600 hover:underline">' . sanitize($vendor['website']) . '</a>' : '--' ?></dd>
                </div>
            </dl>
        </div>

        <!-- Address & Notes -->
        <?php if ($vendor['address'] || $vendor['notes']): ?>
        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <?php if ($vendor['address']): ?>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">Address</h3>
            <p class="text-sm text-gray-600 whitespace-pre-line"><?= sanitize($vendor['address']) ?></p>
            <?php endif; ?>
            <?php if ($vendor['notes']): ?>
            <h3 class="text-lg font-semibold text-gray-800 mb-2 mt-4">Notes</h3>
            <p class="text-sm text-gray-600 whitespace-pre-line"><?= sanitize($vendor['notes']) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Activity -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Recent Bills -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-3 border-b flex justify-between items-center">
                <h3 class="font-semibold text-gray-800">Recent Bills</h3>
                <a href="../vendor_bills/index.php?vendor_id=<?= $id ?>" class="text-sm text-blue-600 hover:underline">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Bill #</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Due</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($bills)): ?>
                        <tr><td colspan="6" class="px-4 py-4 text-center text-gray-500">No bills yet</td></tr>
                        <?php else: foreach ($bills as $b): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-sm font-mono"><a href="../vendor_bills/view.php?id=<?= $b['id'] ?>" class="text-blue-600 hover:underline"><?= sanitize($b['bill_number']) ?></a></td>
                            <td class="px-4 py-2 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($b['bill_date'])) ?></td>
                            <td class="px-4 py-2 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($b['due_date'])) ?></td>
                            <td class="px-4 py-2 text-center whitespace-nowrap">
                                <span class="px-2 py-0.5 text-xs rounded font-medium <?= $statusBadges[$b['status']] ?? 'bg-gray-100' ?>">
                                    <?= ucwords(str_replace('_', ' ', $b['status'])) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 text-sm text-right font-mono"><?= formatMoney((float)$b['total_amount']) ?></td>
                            <td class="px-4 py-2 text-sm text-right font-mono <?= (float)$b['balance_amount'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                <?= formatMoney((float)$b['balance_amount']) ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-3 border-b flex justify-between items-center">
                <h3 class="font-semibold text-gray-800">Recent Payments</h3>
                <a href="../vendor_payments/index.php?vendor_id=<?= $id ?>" class="text-sm text-blue-600 hover:underline">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Payment #</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Reference</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($payments)): ?>
                        <tr><td colspan="5" class="px-4 py-4 text-center text-gray-500">No payments yet</td></tr>
                        <?php else: foreach ($payments as $p): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-sm font-mono"><a href="../vendor_payments/view.php?id=<?= $p['id'] ?>" class="text-blue-600 hover:underline"><?= sanitize($p['payment_number']) ?></a></td>
                            <td class="px-4 py-2 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($p['payment_date'])) ?></td>
                            <td class="px-4 py-2 text-sm text-gray-600"><?= ucwords(str_replace('_', ' ', $p['payment_method'])) ?></td>
                            <td class="px-4 py-2 text-sm text-right font-mono text-gray-900"><?= formatMoney((float)$p['amount']) ?></td>
                            <td class="px-4 py-2 text-sm text-right text-gray-600"><?= sanitize($p['reference_number'] ?? '--') ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Purchase Orders -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-3 border-b flex justify-between items-center">
                <h3 class="font-semibold text-gray-800">Recent Purchase Orders</h3>
                <a href="../purchase_orders/index.php?vendor_id=<?= $id ?>" class="text-sm text-blue-600 hover:underline">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">PO #</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Delivery</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($pos)): ?>
                        <tr><td colspan="5" class="px-4 py-4 text-center text-gray-500">No purchase orders yet</td></tr>
                        <?php else: foreach ($pos as $po): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-sm font-mono"><a href="../purchase_orders/view.php?id=<?= $po['id'] ?>" class="text-blue-600 hover:underline"><?= sanitize($po['po_number']) ?></a></td>
                            <td class="px-4 py-2 text-sm text-gray-600 whitespace-nowrap"><?= date('M d, Y', strtotime($po['order_date'])) ?></td>
                            <td class="px-4 py-2 text-sm text-gray-600 whitespace-nowrap"><?= $po['expected_delivery_date'] ? date('M d, Y', strtotime($po['expected_delivery_date'])) : '--' ?></td>
                            <td class="px-4 py-2 text-center whitespace-nowrap">
                                <span class="px-2 py-0.5 text-xs rounded font-medium <?= $statusBadges[$po['status']] ?? 'bg-gray-100' ?>">
                                    <?= ucwords(str_replace('_', ' ', $po['status'])) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 text-sm text-right font-mono"><?= formatMoney((float)$po['total_amount']) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>
