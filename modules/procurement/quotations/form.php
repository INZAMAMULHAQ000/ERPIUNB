<?php
/** P2 — Quotation create/edit (partial + alternatives + other charges; server-side calc) */
$pageTitle='Vendor Quotation';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/redirect.php';
require_once '../../finance/helpers/finance_helpers.php';
require_once __DIR__ . '/../RfqService.php';

if (!hasPermission('procurement.quotation.view')) die('Access Denied');
$db=getDB(); $UID=$_SESSION['user_id'];
$rfqId=(int)($_GET['rfq_id'] ?? 0);
$qid=(int)($_GET['id'] ?? 0);
if (!$rfqId && $qid) { $q=ProcurementService::getPR($db,0); } // placeholder
if ($qid) { $q=RfqService::getQuotation($db,$qid); $rfqId=(int)$q['rfq_id']; }
$rfq=$rfqId?RfqService::getRFQ($db,$rfqId):null;
if(!$rfq){ flash('error','RFQ not found.'); to('modules/procurement/rfq/index.php'); }
if ($qid && !hasPermission('procurement.quotation.edit')) die('Access Denied');
if (!$qid && !hasPermission('procurement.quotation.create')) die('Access Denied');

// vendor options: only vendors attached to this RFQ
$rvs=$db->prepare("SELECT rv.vendor_id, v.vendor_name FROM rfq_vendors rv JOIN vendors v ON v.id=rv.vendor_id WHERE rv.rfq_id=?");
$rvs->execute([$rfqId]); $rvList=$rvs->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error','Invalid request.'); to("modules/procurement/rfq/view.php?id=$rfqId"); }
    // NEVER trust posted totals — rebuild lines from raw inputs
    $lines=[]; $charges=[];
    foreach (($_POST['lines'] ?? []) as $l) {
        if (empty($l['rfq_item_id']) && trim((string)($l['item_name'] ?? ''))==='') continue;
        if ((float)($l['quantity'] ?? 0)<=0) continue;
        $lines[]=[
            'rfq_item_id'=>$l['rfq_item_id'] ?? null,
            'is_alternative'=>isset($l['is_alternative'])?1:0,
            'item_name'=>$l['item_name'] ?? '(alternative)',
            'description'=>$l['description'] ?? null,
            'quantity'=>$l['quantity'],'unit'=>$l['unit'] ?? 'pcs',
            'unit_price'=>$l['unit_price'] ?? 0,'discount_amount'=>0,'tax_percent'=>$l['tax_percent'] ?? 0,
            'delivery_days'=>$l['delivery_days'] ?? '','warranty_months'=>$l['warranty_months'] ?? '',
        ];
    }
    foreach (($_POST['charges'] ?? []) as $c) if (trim((string)($c['charge_name'] ?? ''))!=='') $charges[]=$c;
    $header=['vendor_id'=>$_POST['vendor_id'] ?? 0,'quotation_date'=>$_POST['quotation_date'] ?? date('Y-m-d'),
             'valid_until'=>$_POST['valid_until'] ?? null,'currency'=>$_POST['currency'] ?? 'AED',
             'payment_terms'=>$_POST['payment_terms'] ?? '','delivery_terms'=>$_POST['delivery_terms'] ?? '',
             'incoterms'=>$_POST['incoterms'] ?? '','warranty_terms'=>$_POST['warranty_terms'] ?? '',
             'vendor_reference'=>$_POST['vendor_reference'] ?? '','lead_time_days'=>$_POST['lead_time_days'] ?? '',
             'notes'=>$_POST['notes'] ?? ''];
    $res=RfqService::saveQuotation($db,$rfqId,$qid ?: null,$header,$lines,$charges,$UID);
    if (!empty($res['success'])) { flash('success','Quotation saved — totals computed server-side.'); to('modules/procurement/quotations/view.php?id='.(int)$res['id']); }
    foreach (($res['errors'] ?? [$res['message'] ?? 'Save failed.']) as $e) flash('error',$e);
}

$items=RfqService::getItems($db,$rfqId);
$existing=$qid?RfqService::getQuoteItems($db,$qid):[];
$charges=$qid?RfqService::getOtherCharges($db,$qid):[];
$q=$qid?RfqService::getQuotation($db,$qid):null;
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="text-2xl font-bold text-gray-800"><?= $qid?'Edit':'Record' ?> Vendor Quotation</h1>
    <p class="text-sm text-gray-500">RFQ <?= sanitize($rfq['rfq_number']) ?> — partial quotes &amp; alternative proposals supported</p></div>
    <a href="<?= $qid?'view.php?id='.$qid:'../rfq/view.php?id='.$rfqId ?>" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
</div>

<form method="post" class="space-y-5">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <div class="bg-white rounded-lg shadow p-5 grid grid-cols-1 md:grid-cols-4 gap-4">
        <?php if (!$qid): ?>
        <div><label class="text-xs text-gray-500 block mb-1">Vendor <span class="text-red-600">*</span></labe>
            <select name="vendor_id" required class="w-full border rounded px-3 py-2 text-sm">
                <option value="">— select —</option>
                <?php foreach ($rvList as $v): ?><option value="<?= $v['vendor_id'] ?>"><?= sanitize($v['vendor_name']) ?></option><?php endforeach; ?></select></div>
        <?php else: ?>
        <div><label class="text-xs text-gray-500 block mb-1">Vendor</label>
            <input disabled value="<?= sanitize($q['vendor_name']) ?>" class="w-full border rounded px-3 py-2 text-sm bg-gray-50">
            <input type="hidden" name="vendor_id" value="<?= (int)$q['vendor_id'] ?>"></div>
        <?php endif; ?>
        <div><label class="text-xs text-gray-500 block mb-1">Quotation date</label><input type="date" name="quotation_date" value="<?= sanitize($q['quotation_date'] ?? date('Y-m-d')) ?>" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div><label class="text-xs text-gray-500 block mb-1">Valid until</label><input type="date" name="valid_until" value="<?= sanitize($q['valid_until'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div><label class="text-xs text-gray-500 block mb-1">Currency</label><input name="currency" value="<?= sanitize($q['currency'] ?? $rfq['currency']) ?>" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div><label class="text-xs text-gray-500 block mb-1">Payment terms</label><input name="payment_terms" value="<?= sanitize($q['payment_terms'] ?? 'Net 30') ?>" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div><label class="text-xs text-gray-500 block mb-1">Delivery terms</label><input name="delivery_terms" value="<?= sanitize($q['delivery_terms'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div><label class="text-xs text-gray-500 block mb-1">Lead time (days)</label><input type="number" min="0" name="lead_time_days" value="<?= sanitize($q['lead_time_days'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div><label class="text-xs text-gray-500 block mb-1">Vendor ref #</label><input name="vendor_reference" value="<?= sanitize($q['vendor_reference'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div class="md:col-span-2"><label class="text-xs text-gray-500 block mb-1">Warranty terms</label><input name="warranty_terms" value="<?= sanitize($q['warranty_terms'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div class="md:col-span-2"><label class="text-xs text-gray-500 block mb-1">Incoterms</label><input name="incoterms" placeholder="e.g. DDP Dubai" value="<?= sanitize($q['incoterms'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm"></div>
        <div class="md:col-span-4"><label class="text-xs text-gray-500 block mb-1">Notes</label><textarea name="notes" rows="2" class="w-full border rounded px-3 py-2 text-sm"><?= sanitize($q['notes'] ?? '') ?></textarea></div>
    </div>

    <!-- Lines: pre-filled per RFQ item; uncheck = not quoted (PARTIAL). Alt rows allowed -->
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold mb-2 text-sm">Quoted lines — untick items the vendor did NOT quote (partial quotation)</h3>
        <table class="w-full text-sm" id="linesTable">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                <th>Quote?</th><th class="text-left px-2 py-2">RFQ Item</th><th class="text-right px-2">Qty</th>
                <th class="text-right px-2">Unit Price</th><th class="text-right px-2">Tax %</th>
                <th class="text-right px-2">Deliv. days</th><th class="text-right px-2">Warr. mo</th><th class="text-right px-2 lineTot">Line Total</th>
            </tr></thead>
            <tbody></tbody>
        </table>
        <button type="button" onclick="addAlt()" class="mt-2 text-blue-600 hover:underline text-sm"><i class="fas fa-plus mr-1"></i>Add alternative / extra line</button>

        <div class="flex justify-end mt-4 space-x-8 text-sm">
            <div id="chargeBox">
                <b class="block mb-1">Other charges (configurable)</b>
                <div id="chargesWrap"></div>
                <button type="button" onclick="addCharge()" class="text-blue-600 hover:underline text-xs mt-1"><i class="fas fa-plus mr-1"></i>Add charge</button>
            </div>
            <div class="text-right space-y-1">
                <div>Subtotal: <b class="font-mono" id="tSub">0.00</b></div>
                <div>Tax: <b class="font-mono" id="tTax">0.00</b></div>
                <div>Other charges: <b class="font-mono" id="tChg">0.00</b></div>
                <div class="border-t pt-1 text-base">GRAND TOTAL (server): <b class="font-mono" id="tGrand">0.00</b></div>
            </div>
        </div>
    </div>

    <button class="bg-blue-600 text-white px-6 py-2.5 rounded hover:bg-blue-700 text-sm"><?= $qid?'Update':'Save' ?> Quotation (totals recalculated server-side)</button>
</form>

<script>
const rfqItems = <?= json_encode(array_map(fn($i)=>['id'=>$i['id'],'n'=>$i['item_name'],'q'=>$i['quantity'],'u'=>$i['unit'],'tax'=>$i['tax_percent']],$items)) ?>;
const existing = <?= json_encode(array_values(array_filter($existing, fn($e)=>!(int)$e['is_alternative']))) ?>;
const altExisting = <?= json_encode(array_values(array_filter($existing, fn($e)=>(int)$e['is_alternative']))) ?>;
const chgExisting = <?= json_encode($charges) ?>;

function addLine(data, isAlt) {
    data=data||{};
    const tb=document.querySelector('#linesTable tbody');
    const tr=document.createElement('tr'); tr.className='border-b'+(isAlt?' bg-orange-50':'');
    let itemCell;
    if (data.rfq_item_id || !isAlt) {
        const rid = data.rfq_item_id || '';
        itemCell = `<input type="checkbox" class="lnOn" checked onchange="toggleRow(this)">
                    <span class="ml-1 text-xs">${rid?(rfqItems.find(r=>r.id==rid)||{n:'?'}).n:''}</span>
                    <input type="hidden" name="lines[][rfq_item_id]" value="${rid}">
                    <input name="lines[][item_name]" value="${(data.item_name||'').replace(/"/g,'&quot;')}" class="hidden">`;
    } else {
        itemCell = `<input type="checkbox" checked disabled> <span class="text-xs text-orange-600 mr-1">ALT</span>
                    <input name="lines[][item_name]" required placeholder="Alternative item" value="${(data.item_name||'').replace(/"/g,'&quot;')}" class="border rounded px-1 py-0.5 text-xs w-40">
                    <input type="hidden" name="lines[][rfq_item_id]" value="">`;
    }
    tr.innerHTML = `<td class="px-2 py-1">${itemCell}
            ${isAlt?'<input type="hidden" name="lines[][is_alternative]" value="1">':''}</td>
        <td class="px-2 py-1"><input type="number" step="0.001" min="0" name="lines[][quantity]" value="${data.quantity||''}" class="w-24 border rounded px-1 py-1 text-right lnQ"></td>
        <td class="px-2 py-1"><input type="number" step="0.01" min="0" name="lines[][unit_price]" value="${data.unit_price||data.est_unit_price||''}" class="w-28 border rounded px-1 py-1 text-right lnP"></td>
        <td class="px-2 py-1"><input type="number" step="0.001" min="0" name="lines[][tax_percent]" value="${data.tax_percent||0}" class="w-20 border rounded px-1 py-1 text-right lnT"></td>
        <td class="px-2 py-1"><input type="number" min="0" name="lines[][delivery_days]" value="${data.delivery_days||''}" class="w-20 border rounded px-1 py-1 text-right"></td>
        <td class="px-2 py-1"><input type="number" min="0" name="lines[][warranty_months]" value="${data.warranty_months||''}" class="w-20 border rounded px-1 py-1 text-right"></td>
        <td class="px-2 py-1 text-right font-mono lnTot">0.00</td>`;
    tb.appendChild(tr); calc();
}
function toggleRow(cb){ cb.closest('tr').style.opacity = cb.checked ? '1' : '0.35'; calc(); }
function addAlt(){ addLine({}, true); }
function addCharge(name,amt){
    const w=document.getElementById('chargesWrap'); const d=document.createElement('div');
    d.className='flex gap-1 mb-1'; d.innerHTML =
      `<input name="charges[][charge_name]" placeholder="Freight / Insurance / ..." value="${name||''}" class="border rounded px-1 py-0.5 text-xs w-36">
       <input type="number" step="0.01" name="charges[][amount]" value="${amt||''}" class="border rounded px-1 py-0.5 text-xs w-24 text-right chgA">
       <button type="button" onclick="this.parentElement.remove();calc()" class="text-red-500">&times;</button>`;
    w.appendChild(d);
}
document.addEventListener('input', e=>{ if(e.target.matches('.lnQ,.lnP,.lnT,.chgA')) calc(); });
function calc(){
    let sub=0,tax=0;
    document.querySelectorAll('#linesTable tbody tr').forEach(tr=>{
        if (tr.querySelector('.lnOn') && !tr.querySelector('.lnOn').checked) return;
        const q=parseFloat(tr.querySelector('.lnQ')?.value)||0;
        const p=parseFloat(tr.querySelector('.lnP')?.value)||0;
        const t=parseFloat(tr.querySelector('.lnT')?.value)||0;
        const s=q*p, tt=s*(1+t/100); sub+=s; tax+=tt-s;
        const tot=tr.querySelector('.lnTot'); if(tot) tot.textContent=tt.toFixed(2);
    });
    let ch=0; document.querySelectorAll('.chgA').forEach(i=>ch+=parseFloat(i.value)||0);
    document.getElementById('tSub').textContent=sub.toFixed(2);
    document.getElementById('tTax').textContent=tax.toFixed(2);
    document.getElementById('tChg').textContent=ch.toFixed(2);
    document.getElementById('tGrand').textContent=(sub+tax+ch).toFixed(2);
}
// prefill
if (existing.length) existing.forEach(e=>addLine({...e}));
else rfqItems.forEach(ri=>addLine({rfq_item_id:ri.id, quantity:ri.q, tax_percent:ri.tax}));
altExisting.forEach(e=>addLine(e,true));
chgExisting.forEach(c=>addCharge(c.charge_name,c.amount));
if(!chgExisting.length) addCharge('Freight','');
calc();
</script>

<?php require_once '../../../includes/footer.php'; ?>
