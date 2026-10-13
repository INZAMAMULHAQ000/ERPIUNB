<?php
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$pageTitle = 'Delete Company';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('company.delete')) { die('Access Denied'); }

$db = getDB();
$id = (int) ($isPost ? ($_POST['id'] ?? 0) : ($_GET['id'] ?? 0));
if ($id <= 0) { to('modules/companies/index.php'); }

$s = $db->prepare("SELECT id, company_code, company_name, is_default FROM companies WHERE id = ? AND deleted_at IS NULL");
$s->execute([$id]);
$c = $s->fetch();
if (!$c) { flash('nf', 'Company not found.', 'error'); to('modules/companies/index.php'); }

// Anything that would be orphaned or silently mis-stated by removing this entity.
$ties = [
    'Journal entries'   => (int) $db->query("SELECT COUNT(*) FROM journal_entries WHERE company_id = $id")->fetchColumn(),
    'General ledger lines' => (int) $db->query("SELECT COUNT(*) FROM general_ledger WHERE company_id = $id")->fetchColumn(),
    'Purchase orders'   => (int) $db->query("SELECT COUNT(*) FROM purchase_orders WHERE company_id = $id")->fetchColumn(),
    'Vendor bills'      => (int) $db->query("SELECT COUNT(*) FROM vendor_bills WHERE company_id = $id")->fetchColumn(),
    'Inventory movements' => (int) $db->query("SELECT COUNT(*) FROM inventory_stock_ledger WHERE company_id = $id")->fetchColumn(),
    'Financial years'   => (int) $db->query("SELECT COUNT(*) FROM financial_years WHERE company_id = $id")->fetchColumn(),
    'Users with access' => (int) $db->query("SELECT COUNT(*) FROM user_companies WHERE company_id = $id")->fetchColumn(),
];
$subsidiaries = (int) $db->query("SELECT COUNT(*) FROM companies WHERE parent_company_id = $id AND deleted_at IS NULL")->fetchColumn();

// Posted ledger data is the hard line: removing the entity would break the audit trail.
$blocked = $ties['General ledger lines'] > 0 || $ties['Journal entries'] > 0 || $subsidiaries > 0 || (int) $c['is_default'] === 1;

$reasons = [];
if ($ties['General ledger lines'] > 0 || $ties['Journal entries'] > 0) {
    $reasons[] = 'It has posted journals and general-ledger history. Archive it instead so the audit trail stays intact.';
}
if ($subsidiaries > 0) { $reasons[] = "$subsidiaries subsidiary account(s) still point to it as parent."; }
if ((int) $c['is_default'] === 1) { $reasons[] = 'It is the default company — promote another company first.'; }

if ($isPost) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { die('Invalid request'); }
    if ($blocked) {
        flash('blocked', 'This company cannot be deleted.', 'error');
        to('modules/companies/view.php?id=' . $id);
    }
    $db->beginTransaction();
    try {
        $db->prepare("UPDATE companies SET deleted_at = NOW(), status = 'archived', is_default = 0, updated_by = ? WHERE id = ?")
           ->execute([$_SESSION['user_id'], $id]);
        // Leave user_companies rows in place: access is meaningless once the company is gone,
        // and dropping them would destroy information needed to restore.
        logAudit($_SESSION['user_id'], 'Deleted company: ' . $c['company_name'] . " (#$id)", 'company');
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        flash('db', 'Could not delete company: ' . $e->getMessage(), 'error');
        to('modules/companies/view.php?id=' . $id);
    }
    flash('deleted', 'Company deleted. Historical transactions were preserved.', 'success');
    to('modules/companies/index.php');
}
?>

<div class="max-w-xl mx-auto mt-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">
                <i class="fas fa-triangle-exclamation text-red-500"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-slate-800">Delete company?</h1>
                <p class="text-sm text-slate-500"><span class="font-mono"><?= htmlspecialchars($c['company_code']) ?></span> · <?= htmlspecialchars($c['company_name']) ?></p>
            </div>
        </div>

        <div class="px-6 py-5">
            <p class="text-sm text-slate-600 mb-4">Related records are counted below and are never removed:</p>
            <ul class="space-y-2 mb-5">
                <?php foreach ($ties as $label => $n): ?>
                <li class="flex items-center justify-between text-sm">
                    <span class="text-slate-600"><?= $label ?></span>
                    <span class="font-semibold <?= $n > 0 ? 'text-slate-800' : 'text-slate-300' ?>"><?= number_format($n) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($blocked): ?>
            <div class="rounded-lg bg-red-50 border border-red-100 p-4 text-sm text-red-700 mb-4">
                <p class="font-semibold mb-1"><i class="fas fa-ban mr-1.5"></i>Deletion blocked</p>
                <ul class="list-disc list-inside space-y-0.5 text-red-600">
                    <?php foreach ($reasons as $r): ?><li><?= htmlspecialchars($r) ?></li><?php endforeach; ?>
                </ul>
            </div>
            <a href="<?= url('modules/companies/view.php?id=' . $id) ?>" class="inline-block px-4 py-2 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Go back</a>
            <?php else: ?>
            <form method="post" class="flex gap-3">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition"><i class="fas fa-trash mr-1.5"></i>Delete Company</button>
                <a href="<?= url('modules/companies/view.php?id=' . $id) ?>" class="px-4 py-2 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</a>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
