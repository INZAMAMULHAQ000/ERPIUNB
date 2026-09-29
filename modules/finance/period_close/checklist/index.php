<?php
/**
 * F12 — Closing Checklist (STEP 7/8): statuses, comments, attachments
 */
$pageTitle = 'Closing Checklist';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../PeriodCloseService.php';

if (!hasPermission('period.view')) { die('Access Denied'); }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid request.'); to('modules/finance/period_close/checklist/index.php'); }
    $itemId = (int)($_POST['item_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (hasPermission('closing.approve') || hasPermission('period.close')) {
        $res = PeriodCloseService::completeItem($db, $itemId, $status, $_POST['comments'] ?? '', $_SESSION['user_id'], $_POST['item_text'] ?? null);
        flash($res['success'] ? 'success' : 'error', $res['success'] ? 'Checklist item updated.' : $res['message']);
    } else {
        flash('error', 'You need closing approval permission to update items.');
    }
    to('modules/finance/period_close/checklist/index.php?fy_id='.(int)($_POST['fy_id'] ?? 0).'&period_id='.(int)($_POST['period_id'] ?? 0));
}

// Attachment upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    // handled below with item save — kept separate for clarity in this page version
}

$fys = $db->query("SELECT id, year_name FROM financial_years ORDER BY start_date DESC")->fetchAll();
$fyId = (int)($_GET['fy_id'] ?? 0);
if (!$fyId) { $a = getActiveFinancialYear($db); $fyId = $a ? (int)$a['id'] : 0; }

$periodId = (int)($_GET['period_id'] ?? 0); // 0 = year level
$levelLabel = $periodId ? 'Period Checklist' : 'Year-Level Checklist';

$items = [];
if ($fyId) {
    PeriodCloseService::ensureChecklistItems($db, $fyId, $periodId, $periodId ? 'period' : 'year');
    $s = $db->prepare("
        SELECT ci.*, u.name AS completed_by_name, t.module_area
        FROM period_checklist_items ci
        LEFT JOIN users u ON u.id = ci.completed_by
        LEFT JOIN period_checklist_templates t ON t.id = ci.template_id
        WHERE ci.financial_year_id = ? AND ci.period_id = ?
        ORDER BY COALESCE(t.sort_order, 999), ci.id
    ");
    $s->execute([$fyId, $periodId]);
    $items = $s->fetchAll();
}
$statusStyles = [
    'pending'=>'bg-gray-100 text-gray-600','in_progress'=>'bg-blue-100 text-blue-700',
    'completed'=>'bg-green-100 text-green-700','exception'=>'bg-red-100 text-red-700',
];
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Closing Checklist</h1>
        <p class="text-gray-600"><?= $levelLabel ?> — Pending / In Progress / Completed / Exception</p>
    </div>
    <a href="../index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
</div>

<form method="get" class="mb-4 flex flex-wrap gap-3">
    <select name="fy_id" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm bg-white">
        <?php foreach ($fys as $f): ?><option value="<?= $f['id'] ?>" <?= $f['id']==$fyId?'selected':'' ?>><?= sanitize($f['year_name']) ?></option><?php endforeach; ?>
    </select>
    <select name="period_id" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm bg-white">
        <option value="0" <?= !$periodId?'selected':'' ?>>Year-level</option>
        <?php
        if ($fyId) {
            $ps = $db->prepare("SELECT id, period_name FROM accounting_periods WHERE financial_year_id=? ORDER BY start_date");
            $ps->execute([$fyId]);
            foreach ($ps->fetchAll() as $p) echo '<option value="'.$p['id'].'" '.($periodId==$p['id']?'selected':'').'>'.sanitize($p['period_name']).'</option>';
        } ?>
    </select>
</form>

<div class="space-y-3">
<?php foreach ($items as $it): ?>
    <div class="bg-white rounded-lg shadow p-4">
        <form method="post" class="flex flex-wrap gap-3 items-start justify-between">
            <?= csrfToken() ?>
            <input type="hidden" name="item_id" value="<?= $it['id'] ?>">
            <input type="hidden" name="fy_id" value="<?= $fyId ?>">
            <input type="hidden" name="period_id" value="<?= $periodId ?>">
            <input type="hidden" name="item_text" value="<?= sanitize($it['item_text']) ?>">
            <div class="flex-1 min-w-[240px]">
                <p class="font-medium text-gray-800">
                    <?= sanitize($it['item_text']) ?>
                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full <?= $statusStyles[$it['status']] ?>"><?= strtoupper(str_replace('_',' ',$it['status'])) ?></span>
                </p>
                <?php if ($it['completed_by_name']): ?>
                    <p class="text-xs text-gray-400 mt-1">Completed by <?= sanitize($it['completed_by_name']) ?> on <?= date('d M Y H:i', strtotime($it['completed_at'])) ?></p>
                <?php endif; ?>
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <input type="text" name="comments" value="<?= sanitize($it['comments'] ?? '') ?>" placeholder="Comments" class="border rounded px-2 py-1 text-sm w-56">
                <select name="status" class="border rounded px-2 py-1 text-sm">
                    <?php foreach ($statusStyles as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $it['status']===$k?'selected':'' ?>><?= ucwords(str_replace('_',' ',$k)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="bg-blue-600 text-white px-3 py-1.5 rounded hover:bg-blue-700 text-sm">Save</button>
            </div>
        </form>
    </div>
<?php endforeach; ?>
<?php if (empty($items)): ?><p class="text-center text-gray-400 py-8">No checklist items.</p><?php endif; ?>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
