<?php
/** P1 — PR View + workflow actions (submit/approve/reject/cancel/attach) */
$pageTitle = 'Purchase Requisition';
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('procurement.view')) { die('Access Denied'); }
$db = getDB();
$UID = $_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);
$pr = ProcurementService::getPR($db,$id);
if (!$pr) { flash('error','Not found.'); to('modules/procurement/requisitions/index.php'); }

// company scope enforcement
CompanyService::requireCompanyAccess($db,$UID,(int)$pr['company_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error','Invalid request.'); to('modules/procurement/requisitions/view.php?id='.$id); }
    $action = $_POST['action'] ?? '';
    $res = ['success'=>false,'message'=>'Not permitted.'];
    if ($action==='submit'   && hasPermission('procurement.pr.submit'))  $res = ProcurementService::submit($db,$id,$UID);
    elseif ($action==='approve' && hasPermission('procurement.pr.approve')) $res = ProcurementService::approve($db,$id,$UID,trim($_POST['comments'] ?? ''));
    elseif ($action==='reject'  && hasPermission('procurement.pr.reject'))  $res = ProcurementService::reject($db,$id,$UID,trim($_POST['reason'] ?? ''));
    elseif ($action==='cancel') {
        if (!hasPermission('procurement.pr.cancel')) $res = ['success'=>false,'message'=>'No cancel permission.'];
        else $res = ProcurementService::cancel($db,$id,$UID,trim($_POST['reason'] ?? ''));
    } elseif ($action==='attach' && hasPermission('procurement.pr.edit')) {
        if (!empty($_FILES['attachment']['tmp_name']) && $_FILES['attachment']['error']===UPLOAD_ERR_OK) {
            $dir = __DIR__.'/../../../uploads/procurement/pr_'.$id;
            if (!is_dir($dir)) mkdir($dir,0777,true);
            $safe = time().'_'.preg_replace('/[^A-Za-z0-9._-]/','_',basename($_FILES['attachment']['name']));
            move_uploaded_file($_FILES['attachment']['tmp_name'], "$dir/$safe");
            $db->prepare("INSERT INTO purchase_requisition_attachments (pr_id,doc_label,original_name,stored_path,file_size,uploaded_by)
                          VALUES (?,?,?,?,?,?)")
               ->execute([$id, trim($_POST['doc_label'] ?? '') ?: 'Supporting Document', basename($_FILES['attachment']['name']),
                          "uploads/procurement/pr_$id/$safe", (int)$_FILES['attachment']['size'], $UID]);
            ProcurementService::log($db,'pr_attachment_added',$id,basename($_FILES['attachment']['name']),null,$UID);
            $res = ['success'=>true,'message'=>'Attachment added.'];
        }
    }
    flash($res['success']?'success':'error',$res['message'] ?? 'Done.');
    to('modules/procurement/requisitions/view.php?id='.$id);
}

$items     = ProcurementService::getItems($db,$id);
$approvals = ProcurementService::getApprovals($db,$id);
$audit     = ProcurementService::getAudit($db,$id);
$attachments = $db->query("SELECT * FROM purchase_requisition_attachments WHERE pr_id=$id ORDER BY id")->fetchAll();
$statusStyles=['draft'=>'bg-gray-100 text-gray-600','submitted'=>'bg-blue-100 text-blue-700','pending_approval'=>'bg-yellow-100 text-yellow-700',
               'approved'=>'bg-green-100 text-green-700','rejected'=>'bg-red-100 text-red-700','cancelled'=>'bg-purple-100 text-purple-700','closed'=>'bg-slate-200 text-slate-600'];
$canSubmit = hasPermission('procurement.pr.submit') && in_array($pr['status'],['draft','rejected'],true);
$canApprove= hasPermission('procurement.pr.approve') && in_array($pr['status'],['submitted','pending_approval'],true)
             && ((int)$pr['requester_id']!==$UID || CompanyService::isSuperAdmin($db,$UID));
$canReject = hasPermission('procurement.pr.reject') && in_array($pr['status'],['submitted','pending_approval'],true);
$canCancel = hasPermission('procurement.pr.cancel') && !in_array($pr['status'],['cancelled','closed'],true);
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800 font-mono"><?= sanitize($pr['pr_number']) ?></h1>
        <span class="px-2 py-0.5 rounded text-xs font-semibold <?= $statusStyles[$pr['status']] ?>"><?= strtoupper(str_replace('_',' ',$pr['status'])) ?></span>
        <?php if ($pr['priority']==='urgent'): ?><span class="ml-1 px-2 py-0.5 rounded text-xs bg-red-100 text-red-700 font-bold">URGENT</span><?php endif; ?>
    </div>
    <div class="flex gap-2 flex-wrap">
        <?php if ($canSubmit && hasPermission('procurement.pr.edit')): ?>
            <a href="<?= url('modules/procurement/requisitions/edit.php?id=' . $id) ?>" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm"><i class="fas fa-pen mr-1"></i><?= $pr['status']==='rejected'?'Revise':'Edit' ?></a>
        <?php endif; ?>
        <?php if ($canSubmit): ?>
        <form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="submit">
            <button class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700 text-sm"><i class="fas fa-paper-plane mr-1"></i><?= $pr['status']==='rejected'?'Resubmit':'Submit' ?></button></form>
        <?php endif; ?>
        <a href="<?= url('modules/procurement/requisitions/index.php') ?>" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
    </div>
</div>

<?php if ($pr['status']==='rejected' && $pr['rejection_reason']): ?>
<div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 mb-5 text-sm"><b>Rejected:</b> <?= sanitize($pr['rejection_reason']) ?> — revise and resubmit.</div>
<?php endif; ?>
<?php if ($pr['status']==='cancelled' && $pr['cancel_reason']): ?>
<div class="bg-purple-50 border border-purple-200 text-purple-800 rounded-lg p-3 mb-5 text-sm"><b>Cancelled</b> by user #<?= (int)$pr['cancelled_by'] ?> on <?= date('d M Y H:i', strtotime($pr['cancelled_at'])) ?>: <?= sanitize($pr['cancel_reason']) ?></div>
<?php endif; ?>

<!-- Approval actions -->
<?php if ($canApprove || $canReject): ?>
<div class="bg-white border-2 border-yellow-300 rounded-lg p-4 mb-6 flex flex-wrap gap-3 items-end">
    <form method="post" class="flex gap-2 items-end"><input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="approve">
        <div><label class="text-xs block mb-1">Approval comments</label><input name="comments" placeholder="optional" class="border rounded px-3 py-2 text-sm w-64"></div>
        <button class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 text-sm"><i class="fas fa-check mr-1"></i>Approve</button></form>
    <?php if ($canReject): ?>
    <form method="post" class="flex gap-2 items-end" onsubmit="this.reason.value=prompt('Rejection reason (required):')||'';return this.reason.value.trim()!=='';">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="reject"><input type="hidden" name="reason" value="">
        <button class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 text-sm"><i class="fas fa-xmark mr-1"></i>Reject</button></form>
    <?php endif; ?>
    <?php if ($canCancel): ?>
    <form method="post" class="flex gap-2 items-end" onsubmit="this.reason.value=prompt('Cancellation reason (required):')||'';return this.reason.value.trim()!=='';">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="reason" value="">
        <button class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 text-sm"><i class="fas fa-ban mr-1"></i>Cancel PR</button></form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Header info -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-lg shadow p-5 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div><p class="text-xs text-gray-400">Requester</p><b><?= sanitize($pr['requester_name']) ?></b></div>
            <div><p class="text-xs text-gray-400">Department</p><b><?= sanitize($pr['department_name'] ?? '—') ?></b></div>
            <div><p class="text-xs text-gray-400">Company</p><b><?= sanitize($pr['company_name']) ?></b></div>
            <div><p class="text-xs text-gray-400">Priority</p><b class="uppercase"><?= sanitize($pr['priority']) ?></b></div>
            <div><p class="text-xs text-gray-400">Project</p><b><?= sanitize($pr['project_name'] ?? '—') ?></b></div>
            <div><p class="text-xs text-gray-400">Cost Center</p><b><?= sanitize($pr['cost_center_name'] ?? '—') ?></b></div>
            <div><p class="text-xs text-gray-400">Branch</p><b><?= sanitize($pr['branch'] ?? '—') ?></b></div>
            <div><p class="text-xs text-gray-400">Required Date</p><b><?= date('d M Y', strtotime($pr['required_date'])) ?></b></div>
            <?php if ($pr['purpose']): ?><div class="md:col-span-4"><p class="text-xs text-gray-400">Purpose</p><?= sanitize($pr['purpose']) ?></div><?php endif; ?>
            <?php if ($pr['description']): ?><div class="md:col-span-4"><p class="text-xs text-gray-400">Description</p><?= sanitize($pr['description']) ?></div><?php endif; ?>
        </div>

        <!-- Items -->
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <div class="px-5 pt-4"><h3 class="font-semibold">Items</h3></div>
            <table class="w-full text-sm mt-2">
                <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
                    <th class="px-4 py-2 text-left">Type / Item</th><th class="px-4 py-2 text-right">Qty</th>
                    <th class="px-4 py-2 text-center">Unit</th><th class="px-4 py-2 text-right">Unit Price</th>
                    <th class="px-4 py-2 text-right">Tax %</th><th class="px-4 py-2 text-right">Line Total</th>
                    <th class="px-4 py-2 text-left">Vendor</th>
                </tr></thead>
                <tbody class="divide-y">
                <?php foreach ($items as $i): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2"><span class="text-xs bg-indigo-50 text-indigo-600 px-1.5 rounded mr-1 uppercase"><?= sanitize($i['item_type']) ?></span>
                            <b><?= sanitize($i['item_name']) ?></b><?php if ($i['description']): ?><br><span class="text-xs text-gray-400"><?= sanitize($i['description']) ?></span><?php endif; ?></td>
                        <td class="px-4 py-2 text-right font-mono"><?= number_format((float)$i['quantity'],2) ?></td>
                        <td class="px-4 py-2 text-center text-xs"><?= sanitize($i['unit']) ?></td>
                        <td class="px-4 py-2 text-right font-mono"><?= number_format((float)$i['est_unit_price'],2) ?></td>
                        <td class="px-4 py-2 text-right font-mono text-xs"><?= rtrim(rtrim(number_format((float)$i['tax_percent'],3),'0'),'.') ?>%</td>
                        <td class="px-4 py-2 text-right font-mono font-semibold"><?= formatMoney($i['line_total'],$pr['currency']) ?></td>
                        <td class="px-4 py-2 text-xs"><?= sanitize($i['vendor_name'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50 font-semibold text-sm">
                    <tr><td colspan="5" class="px-4 py-1.5 text-right">Subtotal</td><td class="px-4 py-1.5 text-right font-mono"><?= formatMoney($pr['subtotal'],$pr['currency']) ?></td><td></td></tr>
                    <tr><td colspan="5" class="px-4 py-1.5 text-right">Tax</td><td class="px-4 py-1.5 text-right font-mono"><?= formatMoney($pr['tax_total'],$pr['currency']) ?></td><td></td></tr>
                    <?php if ((float)$pr['discount_total']>0): ?><tr><td colspan="5" class="px-4 py-1.5 text-right">Discount</td><td class="px-4 py-1.5 text-right font-mono">−<?= formatMoney($pr['discount_total'],$pr['currency']) ?></td><td></td></tr><?php endif; ?>
                    <tr class="border-t-2"><td colspan="5" class="px-4 py-2 text-right">GRAND TOTAL (server-computed)</td>
                        <td class="px-4 py-2 text-right font-mono text-base"><?= formatMoney($pr['grand_total'],$pr['currency']) ?></td><td></td></tr>
                </tfoot>
            </table>
        </div>

        <!-- Attachments -->
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold mb-3">Attachments</h3>
            <?php if (hasPermission('procurement.pr.edit') && !in_array($pr['status'],['cancelled','closed'],true)): ?>
            <form method="post" enctype="multipart/form-data" class="flex gap-2 items-end mb-4">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="attach">
                <input name="doc_label" placeholder="Label e.g. Specification" class="border rounded px-2 py-1.5 text-sm w-48">
                <input type="file" name="attachment" required class="text-sm">
                <button class="bg-blue-600 text-white px-3 py-1.5 rounded text-sm">Upload</button>
            </form>
            <?php endif; ?>
            <ul class="space-y-1 text-sm">
            <?php foreach ($attachments as $a): ?>
                <li><a href="<?= sanitize(url($a['stored_path'])) ?>" target="_blank" class="text-blue-600 hover:underline"><i class="fas fa-paperclip mr-1"></i><?= sanitize($a['original_name']) ?></a>
                    <span class="text-xs text-gray-400 ml-2"><?= sanitize($a['doc_label']) ?> · <?= round($a['file_size']/1024) ?> KB</span></li>
            <?php endforeach; if (empty($attachments)): ?><li class="text-gray-400">None.</li><?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- History -->
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold mb-3">Approval History</h3>
            <ol class="space-y-3 text-sm border-l-2 pl-4">
            <?php foreach ($approvals as $a): ?>
                <li class="relative">
                    <span class="absolute -left-[21px] top-1 w-3 h-3 rounded-full <?= $a['action']==='approved'?'bg-green-500':($a['action']==='rejected'?'bg-red-500':($a['action']==='cancelled'?'bg-purple-500':'bg-blue-500')) ?>"></span>
                    <b class="capitalize"><?= sanitize($a['action']) ?></b> — <?= sanitize($a['approver_name']) ?>
                    <div class="text-xs text-gray-400"><?= date('d M Y H:i:s', strtotime($a['created_at'])) ?>
                        · <?= sanitize($a['previous_status']) ?> → <?= sanitize($a['new_status']) ?></div>
                    <?php if ($a['comments']): ?><div class="text-xs italic text-gray-500">“<?= sanitize($a['comments']) ?>”</div><?php endif; ?>
                </li>
            <?php endforeach; if (empty($approvals)): ?><li class="text-gray-400 list-none">No history yet.</li><?php endif; ?>
            </ol>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold mb-3">Audit History</h3>
            <ul class="space-y-1.5 text-xs text-gray-600">
            <?php foreach ($audit as $a): ?>
                <li>[<?= date('d M H:i:s', strtotime($a['created_at'])) ?>] <b><?= sanitize(str_replace('_',' ',$a['action'])) ?></b>
                    — <?= sanitize($a['user_name'] ?? 'system') ?><?= $a['ip_address'] ? ' ('.sanitize($a['ip_address']).')' : '' ?>
                    <?= $a['reason'] ? '<i>'.sanitize($a['reason']).'</i>' : '' ?></li>
            <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<?php require_once PROCUREMENT_APP_ROOT . '/includes/footer.php'; ?>
