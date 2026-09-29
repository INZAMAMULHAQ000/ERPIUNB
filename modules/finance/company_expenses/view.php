<?php
$pageTitle = 'View Company Expense';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('company_expenses.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('error', 'Invalid expense ID.'); redirect('index.php'); }

$stmt = $db->prepare("
    SELECT ce.*, ec.name AS category_name, ec.default_expense_account_id,
        d.department_name, p.project_name, v.vendor_name,
        u.name AS created_by_name, je.voucher_no AS je_voucher_no,
        pj.voucher_no AS payment_voucher_no,
        ba.account_name AS bank_account_name, ca.cash_name AS cash_account_name
    FROM company_expenses ce
    LEFT JOIN expense_categories ec ON ce.category_id = ec.id
    LEFT JOIN departments d ON ce.department_id = d.id
    LEFT JOIN projects p ON ce.project_id = p.id
    LEFT JOIN vendors v ON ce.vendor_id = v.id
    LEFT JOIN users u ON ce.created_by = u.id
    LEFT JOIN journal_entries je ON ce.journal_entry_id = je.id
    LEFT JOIN journal_entries pj ON ce.payment_journal_id = pj.id
    LEFT JOIN bank_accounts ba ON ce.bank_account_id = ba.id
    LEFT JOIN cash_accounts ca ON ce.cash_account_id = ca.id
    WHERE ce.id = ?
");
$stmt->execute([$id]);
$expense = $stmt->fetch();
if (!$expense) { flash('error', 'Expense not found.'); redirect('index.php'); }

// Load line items
$items = $db->prepare("SELECT * FROM expense_items WHERE expense_type = 'company_expense' AND expense_id = ? ORDER BY sort_order");
$items->execute([$id]);
$items = $items->fetchAll();

// Load attachments
$attachments = $db->prepare("SELECT * FROM expense_attachments WHERE expense_type = 'company_expense' AND expense_id = ? ORDER BY created_at DESC");
$attachments->execute([$id]);
$attachments = $attachments->fetchAll();

// Load approval history
$history = $db->prepare("
    SELECT eah.*, u.name AS action_by_name
    FROM expense_approval_history eah
    LEFT JOIN users u ON eah.action_by = u.id
    WHERE eah.expense_type = 'company_expense' AND eah.expense_id = ?
    ORDER BY eah.created_at ASC
");
$history->execute([$id]);
$history = $history->fetchAll();

$statusBadges = [
    'draft' => 'bg-gray-100 text-gray-800',
    'pending_approval' => 'bg-yellow-100 text-yellow-800',
    'approved' => 'bg-blue-100 text-blue-800',
    'rejected' => 'bg-red-100 text-red-800',
    'paid' => 'bg-green-100 text-green-800',
    'cancelled' => 'bg-gray-200 text-gray-600',
];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Expense: <?= sanitize($expense['expense_number']) ?></h1>
        <p class="text-gray-600"><?= sanitize($expense['category_name'] ?? '--') ?> · <?= date('M d, Y', strtotime($expense['expense_date'])) ?></p>
    </div>
    <div class="flex gap-2">
        <a href="index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back</a>
        <?php if ($expense['status'] === 'draft' && hasPermission('company_expenses.edit')): ?>
        <a href="edit.php?id=<?= $id ?>" class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700 transition"><i class="fas fa-pencil mr-2"></i>Edit</a>
        <?php endif; ?>
        <?php if ($expense['status'] === 'draft' && hasPermission('company_expenses.create')): ?>
        <form method="POST" action="submit.php" style="display:inline" onsubmit="return confirm('Submit for approval?')">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="expense_id" value="<?= $id ?>">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-paper-plane mr-2"></i>Submit for Approval</button>
        </form>
        <?php endif; ?>
        <?php if ($expense['status'] === 'pending_approval' && hasPermission('company_expenses.approve')): ?>
        <form method="POST" action="approve.php" style="display:inline" onsubmit="return confirm('Approve expense <?= sanitize(addslashes($expense['expense_number'])) ?>? This will post to the general ledger.')">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="expense_id" value="<?= $id ?>">
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition"><i class="fas fa-check mr-2"></i>Approve & Post</button>
        </form>
        <?php endif; ?>
        <?php if ($expense['status'] === 'pending_approval' && hasPermission('company_expenses.approve')): ?>
        <button onclick="showRejectModal()" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition"><i class="fas fa-times mr-2"></i>Reject</button>
        <?php endif; ?>
        <?php if ($expense['status'] === 'approved' && hasPermission('company_expenses.pay')): ?>
        <form method="POST" action="mark_paid.php" style="display:inline" onsubmit="return confirm('Mark expense <?= sanitize(addslashes($expense['expense_number'])) ?> as paid? This will post the payment (DR Expense Payable, CR <?= $expense['payment_method'] === 'cash' ? 'Cash' : 'Bank' ?>) to the general ledger.')">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="expense_id" value="<?= $id ?>">
            <button type="submit" class="bg-green-700 text-white px-4 py-2 rounded hover:bg-green-800 transition"><i class="fas fa-money-bill-wave mr-2"></i>Mark as Paid</button>
        </form>
        <?php endif; ?>
        <?php if (!in_array($expense['status'], ['paid','cancelled']) && hasPermission('company_expenses.cancel')): ?>
        <form method="POST" action="cancel.php" style="display:inline" onsubmit="return confirm('Cancel expense <?= sanitize(addslashes($expense['expense_number'])) ?>?<?= !empty($expense['journal_entry_id']) ? ' This will also reverse the posted journal entry.' : '' ?>')">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="expense_id" value="<?= $id ?>">
            <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-ban mr-2"></i>Cancel</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($expense['status'] === 'rejected' && $expense['rejection_reason']): ?>
<div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded mb-6">
    <p class="font-medium">Rejected</p>
    <p class="text-sm"><?= sanitize($expense['rejection_reason']) ?></p>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Left Column -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Expense Details</h3>
                <span class="px-3 py-1 text-sm rounded font-medium <?= $statusBadges[$expense['status']] ?? 'bg-gray-100' ?>">
                    <?= ucwords(str_replace('_', ' ', $expense['status'])) ?>
                </span>
            </div>
            <dl class="space-y-3">
                <div><dt class="text-sm text-gray-500">Category</dt><dd class="text-sm font-medium"><?= sanitize($expense['category_name'] ?? '--') ?></dd></div>
                <div><dt class="text-sm text-gray-500">Date</dt><dd class="text-sm font-medium"><?= date('M d, Y', strtotime($expense['expense_date'])) ?></dd></div>
                <div><dt class="text-sm text-gray-500">Department</dt><dd class="text-sm font-medium"><?= sanitize($expense['department_name'] ?? '--') ?></dd></div>
                <div><dt class="text-sm text-gray-500">Project</dt><dd class="text-sm font-medium"><?= sanitize($expense['project_name'] ?? '--') ?></dd></div>
                <div><dt class="text-sm text-gray-500">Vendor</dt><dd class="text-sm font-medium"><?= sanitize($expense['vendor_name'] ?? '--') ?></dd></div>
                <div><dt class="text-sm text-gray-500">Payment Method</dt><dd class="text-sm font-medium"><?= $expense['payment_method'] ? ucwords(str_replace('_', ' ', $expense['payment_method'])) : '--' ?></dd></div>
                <?php if ($expense['bank_account_name']): ?>
                <div><dt class="text-sm text-gray-500">Bank Account</dt><dd class="text-sm font-medium"><?= sanitize($expense['bank_account_name']) ?></dd></div>
                <?php endif; ?>
                <?php if ($expense['cash_account_name']): ?>
                <div><dt class="text-sm text-gray-500">Cash Account</dt><dd class="text-sm font-medium"><?= sanitize($expense['cash_account_name']) ?></dd></div>
                <?php endif; ?>
                <?php if ($expense['je_voucher_no']): ?>
                <div><dt class="text-sm text-gray-500">Approval Voucher</dt><dd class="text-sm font-medium"><?= sanitize($expense['je_voucher_no']) ?></dd></div>
                <?php endif; ?>
                <?php if ($expense['status'] === 'paid' && $expense['payment_voucher_no']): ?>
                <div><dt class="text-sm text-gray-500">Payment Voucher</dt><dd class="text-sm font-medium"><?= sanitize($expense['payment_voucher_no']) ?></dd></div>
                <?php endif; ?>
                <div><dt class="text-sm text-gray-500">Created By</dt><dd class="text-sm font-medium"><?= sanitize($expense['created_by_name'] ?? '--') ?></dd></div>
            </dl>
            <?php if ($expense['description']): ?>
            <div class="mt-4"><h4 class="text-sm font-medium text-gray-700 mb-1">Description</h4><p class="text-sm text-gray-600 whitespace-pre-line"><?= sanitize($expense['description']) ?></p></div>
            <?php endif; ?>
        </div>

        <!-- Amount Summary -->
        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Amount Summary</h3>
            <dl class="space-y-3">
                <div class="flex justify-between"><span class="text-sm text-gray-500">Amount</span><span class="text-sm font-mono"><?= formatMoney((float)$expense['amount']) ?></span></div>
                <?php if ((float)$expense['tax_amount'] > 0): ?>
                <div class="flex justify-between"><span class="text-sm text-gray-500">Tax</span><span class="text-sm font-mono"><?= formatMoney((float)$expense['tax_amount']) ?></span></div>
                <?php endif; ?>
                <div class="flex justify-between border-t pt-2"><span class="text-sm font-bold text-gray-700">Total</span><span class="text-sm font-mono font-bold"><?= formatMoney((float)$expense['total_amount']) ?></span></div>
            </dl>
        </div>
    </div>

    <!-- Right Column -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Line Items -->
        <?php if (!empty($items)): ?>
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800">Line Items</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Tax</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($items as $i => $item): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 text-sm text-gray-500"><?= $i + 1 ?></td>
                            <td class="px-3 py-2 text-sm text-gray-900"><?= sanitize($item['description'] ?? '--') ?></td>
                            <td class="px-3 py-2 text-sm text-right font-mono"><?= (float)$item['quantity'] ?></td>
                            <td class="px-3 py-2 text-sm text-right font-mono"><?= formatMoney((float)$item['amount']) ?></td>
                            <td class="px-3 py-2 text-sm text-right font-mono"><?= (float)$item['tax_amount'] > 0 ? formatMoney((float)$item['tax_amount']) : '--' ?></td>
                            <td class="px-3 py-2 text-sm text-right font-mono font-medium"><?= formatMoney((float)$item['line_total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Attachments -->
        <?php if (!empty($attachments)): ?>
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800">Attachments</h3></div>
            <div class="p-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <?php foreach ($attachments as $att): ?>
                    <div class="border rounded p-3 text-center">
                        <i class="fas fa-file text-3xl text-gray-400 mb-2"></i>
                        <p class="text-xs text-gray-600 truncate"><?= sanitize($att['original_name']) ?></p>
                        <a href="<?= url(sanitize($att['file_path'])) ?>" target="_blank" class="text-blue-600 text-xs hover:underline">View</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Approval History -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800">Approval History</h3></div>
            <div class="p-4">
                <?php if (empty($history)): ?><p class="text-sm text-gray-500">No history recorded.</p>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($history as $h): ?>
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-2 h-2 mt-2 rounded-full <?= $h['action'] === 'approved' ? 'bg-green-500' : ($h['action'] === 'rejected' ? 'bg-red-500' : 'bg-blue-500') ?>"></div>
                        <div>
                            <p class="text-sm">
                                <span class="font-medium"><?= ucwords(str_replace('_', ' ', $h['approval_level'])) ?></span>
                                <span class="<?= $h['action'] === 'approved' ? 'text-green-600' : ($h['action'] === 'rejected' ? 'text-red-600' : 'text-blue-600') ?>">
                                    — <?= ucfirst($h['action']) ?>
                                </span>
                            </p>
                            <p class="text-xs text-gray-500"><?= sanitize($h['action_by_name'] ?? '--') ?> · <?= date('M d, Y H:i', strtotime($h['created_at'])) ?></p>
                            <?php if ($h['comments']): ?><p class="text-xs text-gray-600 mt-1"><?= sanitize($h['comments']) ?></p><?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <form method="POST" action="reject.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="expense_id" value="<?= $id ?>">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Reject Expense</h3>
                <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Rejection</label>
                <textarea name="rejection_reason" rows="3" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-red-500" required placeholder="Please provide a reason..."></textarea>
            </div>
            <div class="flex justify-end gap-3 px-6 pb-6">
                <button type="button" onclick="hideRejectModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">Cancel</button>
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition">Reject</button>
            </div>
        </form>
    </div>
</div>

<script>
function showRejectModal() { document.getElementById('rejectModal').classList.remove('hidden'); document.getElementById('rejectModal').classList.add('flex'); }
function hideRejectModal() { document.getElementById('rejectModal').classList.add('hidden'); document.getElementById('rejectModal').classList.remove('flex'); }
</script>

<?php require_once '../../../includes/footer.php'; ?>
