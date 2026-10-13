<?php
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$pageTitle = 'Delete Customer';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('customers.delete')) { die('Access Denied'); }

$db = getDB();
$id = (int) ($isPost ? ($_POST['id'] ?? 0) : ($_GET['id'] ?? 0));
if ($id <= 0) { to('modules/customers/index.php'); }

$s = $db->prepare("SELECT id, client_code, company_name FROM clients WHERE id = ? AND deleted_at IS NULL");
$s->execute([$id]);
$c = $s->fetch();
if (!$c) { flash('nf', 'Customer not found.', 'error'); to('modules/customers/index.php'); }

// Ties that a soft delete would leave dangling in plain sight.
$ties = [
    'Invoices'        => (int) $db->query("SELECT COUNT(*) FROM customer_invoices WHERE client_id = $id")->fetchColumn(),
    'Receipts'        => (int) $db->query("SELECT COUNT(*) FROM customer_receipts WHERE client_id = $id")->fetchColumn(),
    'Credit notes'    => (int) $db->query("SELECT COUNT(*) FROM credit_notes WHERE client_id = $id")->fetchColumn(),
    'Deals'           => (int) $db->query("SELECT COUNT(*) FROM deals WHERE client_id = $id")->fetchColumn(),
    'Tickets'         => (int) $db->query("SELECT COUNT(*) FROM tickets WHERE client_id = $id")->fetchColumn(),
];
$outstanding = (float) $db->query("SELECT COALESCE(SUM(balance_amount),0) FROM customer_invoices WHERE client_id = $id AND status NOT IN ('cancelled','draft','void')")->fetchColumn();
$groupChildren = (int) $db->query("SELECT COUNT(*) FROM clients WHERE parent_client_id = $id AND deleted_at IS NULL")->fetchColumn();

$blocked = $outstanding > 0.009 || $groupChildren > 0;

if ($isPost) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { die('Invalid request'); }
    if ($blocked) {
        flash('blocked', 'This customer cannot be deleted while it has an outstanding balance or group members.', 'error');
        to('modules/customers/view.php?id=' . $id);
    }
    $db->prepare("UPDATE clients SET deleted_at = NOW(), status = 'archived', updated_by = ? WHERE id = ?")
       ->execute([$_SESSION['user_id'], $id]);
    logAudit($_SESSION['user_id'], 'Deleted customer: ' . $c['company_name'] . " (#{$id})", 'customers');
    flash('deleted', 'Customer deleted. Historical transactions were preserved.', 'success');
    to('modules/customers/index.php');
}
?>

<div class="max-w-xl mx-auto mt-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">
                <i class="fas fa-triangle-exclamation text-red-500"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-slate-800">Delete customer?</h1>
                <p class="text-sm text-slate-500"><span class="font-mono"><?= htmlspecialchars($c['client_code']) ?></span> · <?= htmlspecialchars($c['company_name']) ?></p>
            </div>
        </div>

        <div class="px-6 py-5">
            <p class="text-sm text-slate-600 mb-4">
                This removes the customer from the master list and blocks it from new transactions.
                Related records are kept for audit purposes:
            </p>
            <ul class="space-y-2 mb-5">
                <?php foreach ($ties as $label => $n): ?>
                <li class="flex items-center justify-between text-sm">
                    <span class="text-slate-600"><?= $label ?></span>
                    <span class="font-semibold <?= $n > 0 ? 'text-slate-800' : 'text-slate-300' ?>"><?= $n ?></span>
                </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($blocked): ?>
            <div class="rounded-lg bg-red-50 border border-red-100 p-4 text-sm text-red-700 mb-4">
                <p class="font-semibold mb-1"><i class="fas fa-ban mr-1.5"></i>Deletion blocked</p>
                <ul class="list-disc list-inside space-y-0.5 text-red-600">
                    <?php if ($outstanding > 0.009): ?><li>Outstanding balance of <?= number_format($outstanding, 2) ?> must be settled first.</li><?php endif; ?>
                    <?php if ($groupChildren): ?><li><?= $groupChildren ?> group member account<?= $groupChildren == 1 ? '' : 's' ?> still point to this parent.</li><?php endif; ?>
                </ul>
            </div>
            <div class="flex gap-3">
                <a href="<?= url('modules/customers/view.php?id=' . $id) ?>" class="px-4 py-2 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Go back</a>
            </div>
            <?php else: ?>
            <form method="post" class="flex gap-3">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
                    <i class="fas fa-trash mr-1.5"></i>Delete Customer
                </button>
                <a href="<?= url('modules/customers/view.php?id=' . $id) ?>" class="px-4 py-2 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</a>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
