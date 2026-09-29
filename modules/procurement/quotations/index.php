<?php
/** P2 — Quotation list, view, comparison+evaluation+award (combined screens) */
$pageTitle='Vendor Quotations';
$screen = $_GET['screen'] ?? 'list';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/redirect.php';
require_once '../../finance/helpers/finance_helpers.php';
require_once __DIR__ . '/../RfqService.php';

if (!hasPermission('procurement.quotation.view')) die('Access Denied');
$db=getDB(); $UID=$_SESSION['user_id'];

/* ---------------- comparison + evaluation + award ---------------- */
if ($screen === 'comparison') :
    $rfqId=(int)($_GET['rfq_id'] ?? 0);
    $rfq=RfqService::getRFQ($db,$rfqId);
    if (!$rfq){ flash('error','RFQ not found.'); to('modules/procurement/rfq/index.php'); }

    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error','Invalid request.'); to("modules/procurement/quotations/comparison.php?rfq_id=$rfqId"); }
        $action=$_POST['action'] ?? '';
        if ($action==='save_criteria' && hasPermission('procurement.quotation.evaluate')) {
            $res=RfqService::saveCriteria($db,$rfqId,$_POST['criteria'] ?? [],$UID);
            flash($res['success']?'success':'error',$res['message'] ?? ('Saved '.$res['count'].' criteria.'));
        } elseif ($action==='save_eval' && hasPermission('procurement.quotation.evaluate')) {
            $qid=(int)($_POST['quotation_id'] ?? 0);
            $res=RfqService::saveEvaluations($db,$qid,$_POST['scores'] ?? [],$UID);
            flash($res['success']?'success':'error','Evaluation saved — weighted total '.$res['total_weighted']);
        } elseif ($action==='select' && hasPermission('procurement.quotation.select')) {
            if (!hasPermission('procurement.quotation.override') && !empty($_POST['is_override'])) die('Access Denied — override requires procurement.quotation.override');
            $level = ($_POST['award_level'] ?? 'rfq')==='item' ? 'item':'rfq';
            $res=RfqService::select($db,$rfqId,(int)($_POST['quotation_id'] ?? 0),trim($_POST['reason'] ?? ''),$UID,
                                    trim($_POST['override_reason'] ?? '') ?: null,$level,
                                    $level==='item'?(int)($_POST['rfq_item_id'] ?? 0):null);
            flash($res['success']?'success':'error',$res['message'] ?? 'Selection saved.');
        }
        to("modules/procurement/quotations/comparison.php?rfq_id=$rfqId");
    }

    $items=RfqService::getItems($db,$rfqId);
    $quotes=RfqService::getQuotations($db,$rfqId);
    $rankings=RfqService::rankings($db,$rfqId);
    $criteria=$db->query("SELECT * FROM rfq_evaluation_criteria WHERE rfq_id=$rfqId ORDER BY sort_order")->fetchAll();
    $critTotal=array_sum(array_map(fn($c)=>(float)$c['weight'],$criteria));

    // item price matrix
    $matrix=[]; // [quoteId][rfqItemId] => line_total
    foreach ($quotes as $q) foreach (RfqService::getQuoteItems($db,(int)$q['id']) as $qi)
        if (!$qi['is_alternative'] && $qi['rfq_item_id']) $matrix[(int)$q['id']][(int)$qi['rfq_item_id']]=$qi;

    $awards=$db->query("SELECT qs.*, v.vendor_name FROM quotation_selection qs JOIN vendors v ON v.id=qs.vendor_id WHERE qs.rfq_id=$rfqId ORDER BY qs.id")->fetchAll();
    ?>
    <div class="mb-6 flex justify-between items-center flex-wrap gap-3">
        <div><h1 class="text-2xl font-bold text-gray-800">Comparison &amp; Award</h1>
        <p class="font-mono text-sm text-gray-500"><?= sanitize($rfq['rfq_number']) ?> · <?= sanitize($rfq['company_name']) ?></p></div>
        <a href="../rfq/view.php?id=<?= $rfqId ?>" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back to RFQ</a>
    </div>

    <!-- Criteria -->
    <form method="post" class="bg-white rounded-lg shadow p-4 mb-6">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="save_criteria">
        <h3 class="font-semibold text-sm mb-2">Weighted Evaluation Criteria <span class="<?= abs($critTotal-100)<0.01?'text-green-600':'text-red-600' ?>">(total: <?= number_format($critTotal,2) ?>% — must equal 100%)</span></h3>
        <div id="critWrap" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
            <?php foreach ($criteria as $c): ?>
            <div class="flex gap-2"><input name="criteria[][criterion]" value="<?= sanitize($c['criterion']) ?>" class="border rounded px-2 py-1.5 text-sm flex-1">
                <input type="number" step="0.01" min="0" max="100" name="criteria[][weight]" value="<?= $c['weight'] ?>" class="border rounded px-2 py-1.5 text-sm w-24"></div>
            <?php endforeach; ?>
            <?php for ($i=count($criteria); $i<6; $i++): ?>
            <div class="flex gap-2"><input name="criteria[][criterion]" placeholder="Criterion e.g. Price" class="border rounded px-2 py-1.5 text-sm flex-1">
                <input type="number" step="0.01" min="0" max="100" name="criteria[][weight]" placeholder="%" class="border rounded px-2 py-1.5 text-sm w-24"></div>
            <?php endfor; ?>
        </div>
        <?php if (hasPermission('procurement.quotation.evaluate')): ?><button class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">Save Criteria</button><?php endif; ?>
    </form>

    <?php if (!count($quotes)): ?><p class="text-center text-gray-400 py-10">No quotations received yet.</p>
    <?php require_once '../../../includes/footer.php'; return; endif; ?>

    <!-- Price matrix -->
    <div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
        <div class="px-5 pt-4"><h3 class="font-semibold">Price Comparison (per RFQ item)</h3></div>
        <table class="w-full text-sm mt-2">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
                <th class="px-4 py-2 text-left">Item</th>
                <?php foreach ($rankings as $rq): ?><th class="px-4 py-2 text-right"><?= sanitize($rq['vendor_name']) ?></th><?php endforeach; ?>
            </tr></thead>
            <tbody class="divide-y">
            <?php foreach ($items as $it): $best=null;
                  foreach ($matrix as $qm) { if(isset($qm[(int)$it['id']])){ $lt=(float)$qm[(int)$it['id']]['line_total']; $best=$best===null?$lt:min($best,$lt);} } ?>
                <tr><td class="px-4 py-2"><?= sanitize($it['item_name']) ?> <span class="text-xs text-gray-400">×<?= number_format((float)$it['quantity'],2) ?></span></td>
                <?php foreach ($rankings as $rq): $cell=$matrix[(int)$rq['id']][(int)$it['id']] ?? null; ?>
                    <td class="px-4 py-2 text-right font-mono <?= $cell && $best!==null && abs((float)$cell['line_total']-$best)<0.005?'bg-green-100 font-bold':'' ?>">
                        <?= $cell?number_format((float)$cell['line_total'],2):'<span class="text-gray-300">not quoted</span>' ?></td>
                <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-gray-50 font-bold"><tr>
                <td class="px-4 py-2">GRAND TOTAL</td>
                <?php foreach ($rankings as $rq): ?><td class="px-4 py-2 text-right font-mono"><?= number_format((float)$rq['grand_total'],2) ?></td><?php endforeach; ?>
            </tr></tfoot>
        </table>
    </div>

    <!-- Ranking table -->
    <div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
        <div class="px-5 pt-4 flex justify-between items-center flex-wrap gap-2">
            <h3 class="font-semibold">Weighted Ranking</h3>
            <span class="text-xs text-gray-400">score 0–10 per criterion × weight</span>
        </div>
        <table class="w-full text-sm mt-2">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
                <th class="px-4 py-2">Rank</th><th class="px-4 py-2 text-left">Vendor</th>
                <th class="px-4 py-2 text-right">Total</th><th class="px-4 py-2 text-right">Weighted Score</th>
                <th class="px-4 py-2 text-right">Delivery (days)</th><th class="px-4 py-2 text-right">Warranty (mo)</th>
                <th class="px-4 py-2 text-left">Status</th><th class="px-4 py-2">Evaluate</th>
            </tr></thead>
            <tbody class="divide-y">
            <?php foreach ($rankings as $rq): ?>
                <tr class="<?= $rq['rank']===1?'bg-green-50/60':'' ?>">
                    <td class="px-4 py-2 text-center font-bold <?= $rq['rank']===1?'text-green-600':'' ?>">#<?= $rq['rank'] ?></td>
                    <td class="px-4 py-2"><?= sanitize($rq['vendor_name']) ?></td>
                    <td class="px-4 py-2 text-right font-mono"><?= number_format((float)$rq['grand_total'],2) ?></td>
                    <td class="px-4 py-2 text-right font-mono font-semibold"><?= number_format((float)$rq['wscore'],2) ?></td>
                    <td class="px-4 py-2 text-right"><?= $rq['min_delivery'] ?? '—' ?></td>
                    <td class="px-4 py-2 text-right"><?= $rq['max_warranty'] ?? '—' ?></td>
                    <td class="px-4 py-2 uppercase text-xs font-semibold"><?= str_replace('_',' ',$rq['status']) ?></td>
                    <td class="px-4 py-2 text-center">
                        <details><summary class="cursor-pointer text-blue-600 text-xs">Score…</summary>
                        <form method="post" class="mt-2 space-y-1 text-left">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="save_eval"><input type="hidden" name="quotation_id" value="<?= $rq['id'] ?>">
                            <?php foreach ($criteria as $c):
                                $ex=$db->prepare("SELECT score,comments FROM quotation_evaluations WHERE quotation_id=? AND criterion=?");
                                $ex->execute([(int)$rq['id'],$c['criterion']]); $prev=$ex->fetch(); ?>
                                <div class="flex gap-1 items-center">
                                    <span class="w-28 truncate text-xs" title="<?= sanitize($c['criterion']) ?> (<?= $c['weight'] ?>%)"><?= sanitize(mb_strimwidth($c['criterion'],0,14)) ?> <span class="text-gray-400"><?= $c['weight'] ?>%</span></span>
                                    <input type="number" step="0.1" min="0" max="10" name="scores[][score]" value="<?= $prev['score'] ?? '' ?>" required placeholder="0-10" class="border rounded px-1 py-0.5 w-16 text-xs">
                                    <input type="hidden" name="scores[][criterion]" value="<?= sanitize($c['criterion']) ?>">
                                    <input type="hidden" name="scores[][weight]" value="<?= $c['weight'] ?>">
                                    <input name="scores[][comments]" value="<?= sanitize($prev['comments'] ?? '') ?>" placeholder="comment" class="border rounded px-1 py-0.5 text-xs flex-1">
                                </div>
                            <?php endforeach; if(empty($criteria)): ?><p class="text-xs text-orange-500">Configure criteria above first.</p><?php endif; ?>
                            <?php if ($criteria && hasPermission('procurement.quotation.evaluate')): ?><button class="w-full bg-indigo-600 text-white rounded py-1 text-xs hover:bg-indigo-700">Save scores</button><?php endif; ?>
                        </form></details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Selection / override / split award -->
    <?php if (hasPermission('procurement.quotation.select') && in_array($rfq['status'],['fully_responded','partially_responded','evaluation','sent'],true)): ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <form method="post" class="bg-white border-2 border-green-300 rounded-lg shadow p-5 space-y-3">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="select"><input type="hidden" name="award_level" value="rfq">
            <h3 class="font-bold text-green-700"><i class="fas fa-trophy mr-1"></i>Award whole RFQ</h3>
            <select name="quotation_id" required class="w-full border rounded px-3 py-2 text-sm">
                <?php foreach ($rankings as $rq): ?><option value="<?= $rq['id'] ?>">#<?= $rq['rank'] ?> <?= sanitize($rq['vendor_name']) ?> — <?= formatMoney($rq['grand_total']) ?> (score <?= number_format((float)$rq['wscore'],2) ?>)</option><?php endforeach; ?></select>
            <textarea name="reason" required rows="2" placeholder="Selection reason (required)" class="w-full border rounded px-3 py-2 text-sm"></textarea>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" onchange="this.closest('form').querySelector('#ovr').classList.toggle('hidden',!this.checked)"> Manual override of ranking?</label>
            <div id="ovr" class="hidden"><textarea name="override_reason" rows="2" placeholder="Why a non-#1 vendor? (required when overriding)" class="w-full border rounded px-3 py-2 text-sm"></textarea></div>
            <button class="w-full bg-green-600 text-white py-2 rounded hover:bg-green-700 text-sm">Award &amp; close RFQ</button>
        </form>

        <form method="post" class="bg-white border-2 border-indigo-300 rounded-lg shadow p-5 space-y-3">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="select"><input type="hidden" name="award_level" value="item">
            <h3 class="font-bold text-indigo-700"><i class="fas fa-scissors mr-1"></i>Split award — per item</h3>
            <div><label class="text-xs block mb-1">RFQ Item</label>
                <select name="rfq_item_id" required class="w-full border rounded px-3 py-2 text-sm">
                <?php foreach ($items as $it): ?><option value="<?= $it['id'] ?>"><?= sanitize($it['item_name']) ?> ×<?= number_format((float)$it['quantity'],2) ?></option><?php endforeach; ?></select></div>
            <div><label class="text-xs block mb-1">Award to quotation</label>
                <select name="quotation_id" required class="w-full border rounded px-3 py-2 text-sm">
                <?php foreach ($rankings as $rq): ?><option value="<?= $rq['id'] ?>"><?= sanitize($rq['vendor_name']) ?> — <?= formatMoney($rq['grand_total']) ?></option><?php endforeach; ?></select></div>
            <textarea name="reason" required rows="2" placeholder="Reason (required)" class="w-full border rounded px-3 py-2 text-sm"></textarea>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" onchange="this.closest('form').querySelector('#ovr2').classList.toggle('hidden',!this.checked)"> Override ranking for this item?</label>
            <div id="ovr2" class="hidden"><textarea name="override_reason" rows="2" placeholder="Justification (required)" class="w-full border rounded px-3 py-2 text-sm"></textarea></div>
            <button class="w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700 text-sm">Record item award</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if (!empty($awards)): ?>
    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 mt-6 text-sm">
        <b>Awards recorded:</b>
        <ul class="list-disc ml-5 mt-1">
        <?php foreach ($awards as $a): ?>
            <li><?= $a['award_level']==='rfq'?'Whole RFQ':'Item #'.$a['rfq_item_id'] ?> → <b><?= sanitize($a['vendor_name']) ?></b>
                <?= $a['is_override']?'<span class="text-orange-600">(OVERRIDE: '.sanitize(mb_strimwidth($a['override_reason'] ?? '',0,60,'…')).')</span>':'' ?>
                — <?= date('d M Y H:i',strtotime($a['selected_at'])) ?></li>
        <?php endforeach; ?>
        </ul>
        <p class="mt-2 text-emerald-800">Ready to feed P3 — Purchase Order creation.</p>
    </div>
    <?php endif; ?>

<?php require_once '../../../includes/footer.php'; return; endif;

/* ---------------- default: quotations list ---------------- */
$where=["1=1"];$params=[];
if(!empty($_GET['rfq_id'])){$where[]='q.rfq_id=?';$params[]=(int)$_GET['rfq_id'];}
if(!empty($_GET['status'])){$where[]='q.status=?';$params[]=$_GET['status'];}
$rows=$db->query("SELECT q.*, v.vendor_name, r.rfq_number FROM vendor_quotations q
                  JOIN vendors v ON v.id=q.vendor_id JOIN request_for_quotations r ON r.id=q.rfq_id
                  WHERE ".implode(' AND ',$where)." ORDER BY q.id DESC LIMIT 200")->fetchAll();
$statusStyles=['draft'=>'bg-gray-100 text-gray-600','received'=>'bg-cyan-100 text-cyan-700','under_evaluation'=>'bg-indigo-100 text-indigo-700',
 'shortlisted'=>'bg-blue-100 text-blue-700','rejected'=>'bg-red-100 text-red-700','selected'=>'bg-green-100 text-green-700',
 'expired'=>'bg-orange-100 text-orange-700','cancelled'=>'bg-purple-100 text-purple-700'];
?>
<div class="mb-6 flex justify-between items-center">
    <div><h1 class="text-2xl font-bold text-gray-800">Vendor Quotations</h1></div>
    <a href="../rfq/index.php" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">RFQs</a>
</div>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-3 text-left">Number</th><th class="px-4 py-3 text-left">RFQ</th>
            <th class="px-4 py-3 text-left">Vendor</th><th class="px-4 py-3 text-right">Grand Total</th>
            <th class="px-4 py-3 text-left">Valid Until</th><th class="px-4 py-3 text-left">Status</th><th></th>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($rows as $r): $expired=$r['valid_until'] && $r['valid_until']<date('Y-m-d'); ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-mono text-xs"><?= sanitize($r['quotation_number']) ?></td>
                <td class="px-4 py-2 font-mono text-xs"><?= sanitize($r['rfq_number']) ?></td>
                <td class="px-4 py-2"><?= sanitize($r['vendor_name']) ?></td>
                <td class="px-4 py-2 text-right font-mono font-semibold"><?= formatMoney((float)$r['grand_total']) ?></td>
                <td class="px-4 py-2 text-xs <?= $expired?'text-orange-600 font-semibold':'text-gray-500' ?>">
                    <?= $r['valid_until']?date('d M Y',strtotime($r['valid_until'])):'—' ?> <?= $expired?'EXPIRED':'' ?></td>
                <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs font-semibold <?= $statusStyles[$r['status']] ?>"><?= strtoupper(str_replace('_',' ',$r['status'])) ?></span></td>
                <td class="px-4 py-2 text-right whitespace-nowrap">
                    <a href="view.php?id=<?= $r['id'] ?>" class="text-blue-600 hover:underline text-xs">View</a> ·
                    <?php if (hasPermission('procurement.quotation.edit')): ?><a href="form.php?id=<?= $r['id'] ?>" class="text-blue-600 hover:underline text-xs">Edit</a><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; if(empty($rows)): ?><tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No quotations yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../../includes/footer.php'; ?>
