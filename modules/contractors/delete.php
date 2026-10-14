<?php
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$pageTitle = 'Delete Contractor';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once __DIR__ . '/document_service.php';
require_once __DIR__ . '/contract_service.php';

if (!hasPermission('contractors.delete')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($isPost ? ($_POST['id'] ?? 0) : ($_GET['id'] ?? 0));
if ($id <= 0) {
    to('modules/contractors/index.php');
}

$stmt = $db->prepare("SELECT * FROM contractors WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) {
    flash('nf', 'Contractor not found.', 'error');
    to('modules/contractors/index.php');
}

$openCountStmt = $db->prepare("SELECT COUNT(*) FROM contractor_contracts WHERE contractor_id = ? AND deleted_at IS NULL AND status IN ('draft','active')");
$openCountStmt->execute([$id]);
$openContracts = (int) $openCountStmt->fetchColumn();

$totalContractsStmt = $db->prepare("SELECT COUNT(*) FROM contractor_contracts WHERE contractor_id = ? AND deleted_at IS NULL");
$totalContractsStmt->execute([$id]);
$totalContracts = (int) $totalContractsStmt->fetchColumn();

$docStmt = $db->prepare("SELECT COUNT(*) FROM contractor_documents WHERE contractor_id = ?");
$docStmt->execute([$id]);
$docCount = (int) $docStmt->fetchColumn();

$peopleStmt = $db->prepare("SELECT COUNT(*) FROM people WHERE contractor_id = ?");
$peopleStmt->execute([$id]);
$peopleLinks = (int) $peopleStmt->fetchColumn();

// Deleting a contractor who is still engaged would strand an open commitment.
$blocked = $openContracts > 0;

if ($isPost) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        die('Invalid request');
    }
    if ($blocked) {
        flash('blocked', 'Close or terminate the open contract(s) before deleting this contractor.', 'error');
        to('modules/contractors/view.php?id=' . $id);
    }

    $db->prepare("UPDATE contractors SET status = 'terminated', deleted_at = NOW(), updated_by = ?, termination_date = COALESCE(termination_date, CURDATE()), termination_reason = COALESCE(termination_reason, 'Removed from register') WHERE id = ?")
       ->execute([$_SESSION['user_id'], $id]);

    // A directory entry that only existed to hold this contractor goes with it.
    if ($peopleLinks > 0) {
        $db->prepare("UPDATE people SET contractor_id = NULL WHERE contractor_id = ?")->execute([$id]);
    }

    logAudit($_SESSION['user_id'], 'Deleted contractor: ' . trim($c['first_name'] . ' ' . $c['last_name']) . " (#$id)", 'contractors');
    flash('deleted', 'Contractor removed from the register. Contracts and documents were preserved for audit.', 'success');
    to('modules/contractors/index.php');
}

$name = trim($c['first_name'] . ' ' . $c['last_name']);
?>

<div class="max-w-xl mx-auto mt-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">
                <i class="fas fa-triangle-exclamation text-red-500"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-slate-800">Remove contractor from register?</h1>
                <p class="text-sm text-slate-500">
                    <span class="font-mono"><?= htmlspecialchars($c['contractor_code']) ?></span> &middot; <?= htmlspecialchars($name) ?>
                </p>
            </div>
        </div>

        <div class="px-6 py-5">
            <p class="text-sm text-slate-600 mb-4">
                This hides the contractor from the register and blocks new engagements. Contracts, scanned
                documents and their files are kept for audit:
            </p>
            <ul class="space-y-2 mb-5 text-sm">
                <li class="flex items-center justify-between">
                    <span class="text-slate-600">Open contracts (draft or active)</span>
                    <span class="font-semibold <?= $openContracts > 0 ? 'text-red-600' : 'text-slate-300' ?>"><?= $openContracts ?></span>
                </li>
                <li class="flex items-center justify-between">
                    <span class="text-slate-600">Contracts on record</span>
                    <span class="font-semibold <?= $totalContracts > 0 ? 'text-slate-800' : 'text-slate-300' ?>"><?= $totalContracts ?></span>
                </li>
                <li class="flex items-center justify-between">
                    <span class="text-slate-600">Stored documents</span>
                    <span class="font-semibold <?= $docCount > 0 ? 'text-slate-800' : 'text-slate-300' ?>"><?= $docCount ?></span>
                </li>
                <li class="flex items-center justify-between">
                    <span class="text-slate-600">People-directory links</span>
                    <span class="font-semibold <?= $peopleLinks > 0 ? 'text-slate-800' : 'text-slate-300' ?>"><?= $peopleLinks ?></span>
                </li>
            </ul>

            <?php if ($blocked): ?>
            <div class="rounded-lg bg-red-50 border border-red-100 p-4 text-sm text-red-700 mb-4">
                <p class="font-semibold mb-1"><i class="fas fa-ban mr-1.5"></i>Deletion blocked</p>
                <p><?= $openContracts ?> contract(s) are still open. Complete or terminate them on the
                    <a href="<?= url('modules/contractors/view.php?id=' . $id) ?>#contracts" class="underline">contractor profile</a>
                    first, so the commitment history stays coherent.</p>
            </div>
            <a href="<?= url('modules/contractors/view.php?id=' . $id) ?>"
               class="inline-block px-4 py-2 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Go back</a>
            <?php else: ?>
            <form method="post" class="flex gap-3">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
                    <i class="fas fa-trash mr-1.5"></i>Remove Contractor
                </button>
                <a href="<?= url('modules/contractors/view.php?id=' . $id) ?>"
                   class="px-4 py-2 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</a>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
