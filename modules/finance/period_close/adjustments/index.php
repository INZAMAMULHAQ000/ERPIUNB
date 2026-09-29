<?php
/**
 * F12 — Closing Adjustments (list) — STEP 9
 */
$pageTitle = 'Closing Adjustments';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../PeriodCloseService.php';

if (!hasPermission('period.view')) { die('Access Denied'); }

$db = getDB();
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid request.'); to('modules/finance/period_close/adjustments/index.php'); }
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $stmt = $db->prepare("SELECT * FROM closing_adjustments WHERE id = ?");
    $stmt->execute([$id]);
    $adj = $stmt->fetch();

    if (!$adj) { flash('error', 'Adjustment not found.'); to('modules/finance/period_close/adjustments/index.php'); }

    if ($action === 'submit' && in_array($adj['status'], ['draft','rejected']) && hasPermission('closing.adjust')) {
        $db->prepare("UPDATE closing_adjustments SET status='pending_approval' WHERE id=?")->execute([$id]);
        PeriodCloseService::log($db, 'adjustment_submitted', (int)$adj['financial_year_id'], $adj['period_id'], $id, $adj['title'], null, $userId);
        flash('success', 'Adjustment submitted for approval.');
    }
    elseif ($action === 'approve' && $adj['status']==='pending_approval' && hasPermission('closing.approve')) {
        $db->prepare("UPDATE closing_adjustments SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?")->execute([$userId, $id]);
        PeriodCloseService::log($db, 'adjustment_approved', (int)$adj['financial_year_id'], $adj['period_id'], $id, $adj['title'], null, $userId);
        flash('success', 'Adjustment approved. You can now post it.');
    }
    elseif ($action === 'reject' && $adj['status']==='pending_approval' && hasPermission('closing.approve')) {
        $db->prepare("UPDATE closing_adjustments SET status='rejected', rejection_reason=?, approved_by=? WHERE id=?")
           ->execute([trim($_POST['rejection_reason'] ?? '') ?: 'Rejected', $userId, $id]);
        PeriodCloseService::log($db, 'adjustment_rejected', (int)$adj['financial_year_id'], $adj['period_id'], $id, $adj['title'], $_POST['rejection_reason'] ?? '', $userId);
        flash('info', 'Adjustment rejected.');
    }
    elseif ($action === 'post' && $adj['status']==='approved' && hasPermission('closing.approve')) {
        // Post the underlying journal through THE Posting Engine.
        require_once __DIR__ . '/../../posting_engine/PostingEngine.php';
        $res = PostingEngine::post((int)$adj['journal_entry_id'], $userId);
        if (!empty($res['success'])) {
            $db->prepare("UPDATE closing_adjustments SET status='posted', posted_at=NOW() WHERE id=?")->execute([$id]);
            PeriodCloseService::log($db, 'adjustment_posted', (int)$adj['financial_year_id'], $adj['period_id'], $id, 'Voucher: '.$res['voucher_no'], null, $userId);
            flash('success', 'Adjustment posted: ' . $res['voucher_no']);
        } else {
            flash('error', 'Posting failed: ' . $res['message']);
        }
    }
    else {
        flash('error', 'Action not allowed for current status/permissions.');
    }
    to('modules/finance/period_close/adjustments/index.php');
}

$fys = $db->query("SELECT id, year_name FROM financial_years ORDER BY start_date DESC")->fetchAll();
$fyId = (int)($_GET['fy_id'] ?? 0);
if (!$fyId) { $a = getActiveFinancialYear($db); $fyId = $a ? (int)$a['id'] : 0; }

$rows = [];
if ($fyId) {
    $s = $db->prepare("
        SELECT ca.*, p.period_name, u.name AS created_by_name, a.name AS approved_by_name,
               je.voucher_no, je.status je_status
        FROM closing_adjustments ca
        LEFT JOIN accounting_periods p ON p.id = ca.period_id
        LEFT JOIN users u ON u.id = ca.created_by
        LEFT JOIN users a ON a.id = ca.approved_by
        LEFT JOIN journal_entries je ON je.id = ca.journal_entry_id
        WHERE ca.financial_year_id = ?
        ORDER BY ca.created_at DESC
    ");
    $s->execute([$fyId]);
    $rows = $s->fetchAll();
}
$statusStyles = [
    'draft'=>'bg-gray-100 text-gray-600','pending_approval'=>'bg-yellow-100 text-yellow-700',
    'approved'=>'bg-blue-100 text-blue-700','posted'=>'bg-green-100 text-green-700',
    'rejected'=>'bg-red-100 text-red-700','reversed'=>'bg-purple-100 text-purple-700',
];
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Closing Adjustments</h1>
        <p class="text-gray-600">Authorized period-end journals — always posted via the Posting Engine</p>
    </div>
    <div class="flex gap-2">
        <?php if (hasPermission('closing.adjust')): ?>
        <a href="create.php?fy_id=<?= $fyId ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm"><i class="fas fa-plus mr-1"></i>New Adjustment</a>
        <?php endif; ?>
        <a href="../index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
    </div>
</div>

<form method="get" class="mb-4">
    <select name="fy_id" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm bg-white">
        <?php foreach ($fys as $f): ?><option value="<?= $f['id'] ?>" <?= $f['id']==$fyId?'selected':'' ?>><?= sanitize($f['year_name']) ?></option><?php endforeach; ?>
    </select>
</form>

<div class="space-y-3">
<?php foreach ($rows as $r): ?>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex justify-between items-start flex-wrap gap-3">
            <div class="flex-1 min-w-[260px]">
                <p class="font-semibold text-gray-800">
                    <?= sanitize($r['title']) ?>
                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full <?= $statusStyles[$r['status']] ?>"><?= strtoupper(str_replace('_',' ',$r['status'])) ?></span>
                    <span class="ml-1 text-xs bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full"><?= strtoupper($r['adjustment_type']) ?></span>
                </p>
                <p class="text-sm text-gray-500 mt-1"><?= sanitize(mb_strimwidth($r['reason'], 0, 140, '…')) ?></p>
                <p class="text-xs text-gray-400 mt-1">
                    <?= sanitize($r['created_by_name']) ?> · <?= date('d M Y', strtotime($r['created_at'])) ?>
                    <?= $r['period_name'] ? ' · ' . sanitize($r['period_name']) : '' ?>
                    <?= $r['voucher_no'] ? ' · Voucher <span class="font-mono">' . sanitize($r['voucher_no']) . '</span> (' . sanitize($r['je_status']) . ')' : '' ?>
                    <?= $r['amount'] ? ' · Amount ' . formatMoney($r['amount']) : '' ?>
                </p>
                <?php if ($r['status']==='rejected'): ?><p class="text-xs text-red-500 mt-1">Reason: <?= sanitize($r['rejection_reason']) ?></p><?php endif; ?>
            </div>
            <div class="flex gap-2 flex-wrap">
                <?php if (in_array($r['status'], ['draft','rejected']) && hasPermission('closing.adjust')): ?>
                <form method="post"><?= csrfToken() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="submit">
                    <button class="text-blue-600 hover:underline text-xs font-medium">Submit for Approval</button></form>
                <?php endif; ?>
                <?php if ($r['status']==='pending_approval' && hasPermission('closing.approve') && $r['created_by'] != $userId): ?>
                <form method="post"><?= csrfToken() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="approve">
                    <button class="text-green-600 hover:underline text-xs font-medium">Approve</button></form>
                <button data-modal-open="reject<?= $r['id'] ?>" class="text-red-600 hover:underline text-xs font-medium">Reject</button>
                <?php endif; ?>
                <?php if ($r['status']==='approved' && hasPermission('closing.approve')): ?>
                <form method="post" onsubmit="return confirm('Post this adjustment journal through the Posting Engine?');">
                    <?= csrfToken() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="post">
                    <button class="text-emerald-600 hover:underline text-xs font-medium">Post Journal</button></form>
                <?php endif; ?>
                <?php if ($r['journal_entry_id']): ?>
                <a href="../../journal_entries/edit.php?id=<?= $r['journal_entry_id'] ?>" target="_blank" class="text-gray-400 hover:text-blue-600 text-xs ml-1"><i class="fas fa-up-right-from-square"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($r['status']==='pending_approval'): ?>
    <div id="reject<?= $r['id'] ?>" class="hidden fixed inset-0 z-50 bg-black bg-opacity-40 flex items-start justify-center pt-24">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="px-4 py-3 border-b font-semibold">Reject Adjustment</div>
            <form method="post" class="p-4 space-y-3">
                <?= csrfToken() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="reject">
                <textarea name="rejection_reason" required rows="3" placeholder="Rejection reason" class="w-full border rounded px-3 py-2 text-sm"></textarea>
                <div class="flex gap-2 justify-end">
                    <button type="button" data-modal-close class="px-3 py-2 border rounded text-sm">Cancel</button>
                    <button class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 text-sm">Reject</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
<?php endforeach; ?>
<?php if (empty($rows)): ?>
<div class="bg-white rounded-lg shadow p-8 text-center text-gray-400">No closing adjustments for this year.</div>
<?php endif; ?>
</div>

<script>
document.querySelectorAll('[data-modal-open]').forEach(function(el){
    el.addEventListener('click', function(e){ e.preventDefault(); document.getElementById(el.getAttribute('data-modal-open')).classList.remove('hidden'); });
});
document.querySelectorAll('[data-modal-close]').forEach(function(el){
    el.addEventListener('click', function(){ el.closest('.fixed').classList.add('hidden'); });
});
</script>

<?php require_once '../../../../includes/footer.php'; ?>
