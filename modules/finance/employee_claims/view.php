<?php
$pageTitle = 'View Expense Claim';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('employee_claims.view')) { die('Access Denied'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('error', 'Invalid claim ID.'); redirect('index.php'); }

$stmt = $db->prepare("
    SELECT ec.*, e.first_name, e.last_name, e.employee_code, e.department_id,
        cat.name AS category_name, cat.default_expense_account_id,
        p.project_name, u.name AS created_by_name,
        je.voucher_no AS je_voucher_no,
        d.department_name
    FROM employee_expense_claims ec
    LEFT JOIN employees e ON ec.employee_id = e.id
    LEFT JOIN expense_categories cat ON ec.category_id = cat.id
    LEFT JOIN projects p ON ec.project_id = p.id
    LEFT JOIN users u ON ec.created_by = u.id
    LEFT JOIN journal_entries je ON ec.journal_entry_id = je.id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE ec.id = ?
");
$stmt->execute([$id]);
$claim = $stmt->fetch();
if (!$claim) { flash('error', 'Claim not found.'); redirect('index.php'); }

$attachments = $db->prepare("SELECT * FROM expense_attachments WHERE expense_type = 'employee_claim' AND expense_id = ? ORDER BY created_at DESC");
$attachments->execute([$id]);
$attachments = $attachments->fetchAll();

$history = $db->prepare("
    SELECT eah.*, u.name AS action_by_name
    FROM expense_approval_history eah
    LEFT JOIN users u ON eah.action_by = u.id
    WHERE eah.expense_type = 'employee_claim' AND eah.expense_id = ?
    ORDER BY eah.created_at ASC
");
$history->execute([$id]);
$history = $history->fetchAll();

// Check if there's a reimbursement
$reimbursement = $db->prepare("SELECT * FROM expense_reimbursements WHERE claim_id = ?");
$reimbursement->execute([$id]);
$reimbursement = $reimbursement->fetch();

$statusBadges = [
    'draft' => 'bg-gray-100 text-gray-800',
    'pending_manager' => 'bg-yellow-100 text-yellow-800',
    'pending_finance' => 'bg-orange-100 text-orange-800',
    'approved' => 'bg-blue-100 text-blue-800',
    'rejected' => 'bg-red-100 text-red-800',
    'reimbursed' => 'bg-green-100 text-green-800',
    'cancelled' => 'bg-gray-200 text-gray-600',
];
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Claim: <?= sanitize($claim['claim_number']) ?></h1>
        <p class="text-gray-600"><?= sanitize($claim['first_name'] . ' ' . $claim['last_name']) ?> · <?= sanitize($claim['category_name'] ?? '--') ?></p>
    </div>
    <div class="flex gap-2">
        <a href="index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-arrow-left mr-2"></i>Back</a>
        <?php if ($claim['status'] === 'draft' && hasPermission('employee_claims.edit')): ?>
        <a href="edit.php?id=<?= $id ?>" class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700 transition"><i class="fas fa-pencil mr-2"></i>Edit</a>
        <?php endif; ?>
        <?php if ($claim['status'] === 'draft' && hasPermission('employee_claims.create')): ?>
        <form method="POST" action="submit.php" style="display:inline" onsubmit="return confirm('Submit claim for approval?')">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="claim_id" value="<?= $id ?>">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-paper-plane mr-2"></i>Submit for Approval</button>
        </form>
        <?php endif; ?>
        <?php if ($claim['status'] === 'pending_manager' && hasPermission('employee_claims.approve')): ?>
        <form method="POST" action="approve_manager.php" style="display:inline" onsubmit="return confirm('Approve claim <?= sanitize(addslashes($claim['claim_number'])) ?> at manager level?')">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="claim_id" value="<?= $id ?>">
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition"><i class="fas fa-check mr-2"></i>Manager Approve</button>
        </form>
        <?php endif; ?>
        <?php if ($claim['status'] === 'pending_finance' && hasPermission('employee_claims.approve')): ?>
        <form method="POST" action="approve_finance.php" style="display:inline" onsubmit="return confirm('Approve claim <?= sanitize(addslashes($claim['claim_number'])) ?> at finance level? This will post to GL.')">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="claim_id" value="<?= $id ?>">
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition"><i class="fas fa-check mr-2"></i>Finance Approve & Post</button>
        </form>
        <?php endif; ?>
        <?php if (in_array($claim['status'], ['pending_manager', 'pending_finance']) && hasPermission('employee_claims.approve')): ?>
        <button onclick="showRejectModal()" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition"><i class="fas fa-times mr-2"></i>Reject</button>
        <?php endif; ?>
        <?php if (!in_array($claim['status'], ['reimbursed', 'cancelled']) && hasPermission('employee_claims.edit')): ?>
        <form method="POST" action="cancel.php" style="display:inline" onsubmit="return confirm('Cancel claim <?= sanitize(addslashes($claim['claim_number'])) ?>?<?= !empty($claim['journal_entry_id']) ? ' This will also reverse the posted journal entry.' : '' ?>')">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="claim_id" value="<?= $id ?>">
            <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition"><i class="fas fa-ban mr-2"></i>Cancel</button>
        </form>
        <?php endif; ?>
        <?php if ($claim['status'] === 'approved' && hasPermission('expense_reimbursements.create')): ?>
        <a href="../expense_reimbursements/create.php?claim_id=<?= $id ?>" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 transition"><i class="fas fa-hand-holding-usd mr-2"></i>Reimburse</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($claim['status'] === 'rejected' && $claim['rejection_reason']): ?>
<div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded mb-6">
    <p class="font-medium">Rejected</p>
    <p class="text-sm"><?= sanitize($claim['rejection_reason']) ?></p>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Left Column -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Claim Details</h3>
                <span class="px-3 py-1 text-sm rounded font-medium <?= $statusBadges[$claim['status']] ?? 'bg-gray-100' ?>">
                    <?= ucwords(str_replace('_', ' ', $claim['status'])) ?>
                </span>
            </div>
            <dl class="space-y-3">
                <div><dt class="text-sm text-gray-500">Employee</dt><dd class="text-sm font-medium"><?= sanitize($claim['first_name'] . ' ' . $claim['last_name']) ?> (<?= sanitize($claim['employee_code']) ?>)</dd></div>
                <div><dt class="text-sm text-gray-500">Department</dt><dd class="text-sm font-medium"><?= sanitize($claim['department_name'] ?? '--') ?></dd></div>
                <div><dt class="text-sm text-gray-500">Date</dt><dd class="text-sm font-medium"><?= date('M d, Y', strtotime($claim['expense_date'])) ?></dd></div>
                <div><dt class="text-sm text-gray-500">Category</dt><dd class="text-sm font-medium"><?= sanitize($claim['category_name'] ?? '--') ?></dd></div>
                <div><dt class="text-sm text-gray-500">Project</dt><dd class="text-sm font-medium"><?= sanitize($claim['project_name'] ?? '--') ?></dd></div>
                <?php if ($claim['je_voucher_no']): ?>
                <div><dt class="text-sm text-gray-500">Journal Voucher</dt><dd class="text-sm font-medium"><?= sanitize($claim['je_voucher_no']) ?></dd></div>
                <?php endif; ?>
                <div><dt class="text-sm text-gray-500">Created By</dt><dd class="text-sm font-medium"><?= sanitize($claim['created_by_name'] ?? '--') ?></dd></div>
            </dl>
            <?php if ($claim['description']): ?>
            <div class="mt-4"><h4 class="text-sm font-medium text-gray-700 mb-1">Description</h4><p class="text-sm text-gray-600 whitespace-pre-line"><?= sanitize($claim['description']) ?></p></div>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Amount Summary</h3>
            <dl class="space-y-3">
                <div class="flex justify-between"><span class="text-sm text-gray-500">Amount</span><span class="text-sm font-mono"><?= formatMoney((float)$claim['amount']) ?></span></div>
                <?php if ((float)$claim['tax_amount'] > 0): ?>
                <div class="flex justify-between"><span class="text-sm text-gray-500">Tax</span><span class="text-sm font-mono"><?= formatMoney((float)$claim['tax_amount']) ?></span></div>
                <?php endif; ?>
                <div class="flex justify-between border-t pt-2"><span class="text-sm font-bold text-gray-700">Total</span><span class="text-sm font-mono font-bold"><?= formatMoney((float)$claim['total_amount']) ?></span></div>
            </dl>
            <?php if ($reimbursement): ?>
            <div class="mt-4 pt-4 border-t">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Reimbursement</h4>
                <p class="text-sm"><?= sanitize($reimbursement['reimbursement_number']) ?></p>
                <p class="text-sm text-gray-600"><?= formatMoney((float)$reimbursement['amount']) ?> via <?= ucwords(str_replace('_', ' ', $reimbursement['payment_method'])) ?></p>
                <p class="text-xs text-gray-500"><?= date('M d, Y', strtotime($reimbursement['payment_date'])) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Attachments -->
        <?php if (!empty($attachments)): ?>
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-3 border-b"><h3 class="font-semibold text-gray-800">Receipts & Attachments</h3></div>
            <div class="p-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <?php foreach ($attachments as $att): ?>
                    <div class="border rounded p-3 text-center">
                        <?php
                        $icon = 'fa-file';
                        if (strpos($att['mime_type'], 'image') !== false) $icon = 'fa-file-image';
                        elseif (strpos($att['mime_type'], 'pdf') !== false) $icon = 'fa-file-pdf';
                        elseif (strpos($att['mime_type'], 'zip') !== false) $icon = 'fa-file-archive';
                        ?>
                        <i class="fas <?= $icon ?> text-3xl text-gray-400 mb-2"></i>
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
            <input type="hidden" name="claim_id" value="<?= $id ?>">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Reject Claim</h3>
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
