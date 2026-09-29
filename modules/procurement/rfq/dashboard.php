<?php
/** P2 — RFQ Dashboard */
$pageTitle='RFQ Dashboard';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../../helpers/redirect.php';
require_once '../../finance/helpers/finance_helpers.php';
require_once __DIR__ . '/../RfqService.php';
if (!hasPermission('procurement.rfq.view')) die('Access Denied');
$db=getDB();
RfqService::applyDeadlineFlags($db);

$st=$db->query("SELECT
  SUM(status IN ('open','sent')) open_rfq,
  (SELECT COUNT(*) FROM rfq_vendors rv JOIN request_for_quotations r ON r.id=rv.rfq_id WHERE rv.status IN ('sent','selected') AND r.status NOT IN ('cancelled','closed')) pending_resp,
  (SELECT COUNT(*) FROM vendor_quotations q WHERE q.status IN ('received','under_evaluation')) quotes,
  SUM(status='evaluation') eval,
  SUM(status='fully_responded') await_select,
  SUM(response_due<CURDATE() AND status IN ('sent','partially_responded','open')) expired,
  COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','closed') THEN estimated_pr_value END),0) est_value
 FROM request_for_quotations")->fetch();
$savings=0;
foreach ($db->query("SELECT id FROM request_for_quotations WHERE status='awarded'") as $r) {
    $s=RfqService::savingsSummary($db,(int)$r['id']);
    $savings += max(0,(float)($s['savings_vs_estimate'] ?? 0));
}
$cards=[
 ['Open RFQs',(int)$st['open_rfq'],'blue','fa-envelope-open-text'],
 ['Pending Vendor Responses',(int)$st['pending_resp'],'yellow','fa-hourglass-half'],
 ['Quotations Received',(int)$st['quotes'],'cyan','fa-inbox'],
 ['Under Evaluation',(int)$st['eval'],'indigo','fa-scale-balanced'],
 ['Awaiting Selection',(int)$st['await_select'],'purple','fa-gavel'],
 ['Expired / Overdue',(int)$st['expired'],'red','fa-triangle-exclamation'],
];
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="text-2xl font-bold text-gray-800">RFQ &amp; Vendor Quotations</h1>
    <p class="text-gray-600">Approved PR → RFQ → vendors → quotations → comparison → award</p></div>
    <?php if (hasPermission('procurement.rfq.create')): ?>
    <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm"><i class="fas fa-plus mr-1"></i>New RFQ from Approved PR</a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3 mb-6">
    <?php foreach ($cards as $c): ?>
    <div class="bg-white rounded-lg shadow p-3 text-center border-t-4 border-<?= $c[2] ?>-500">
        <i class="fas <?= $c[3] ?> text-gray-300 mb-1"></i>
        <p class="text-[11px] text-gray-500 uppercase leading-tight"><?= $c[0] ?></p>
        <p class="font-bold text-xl"><?= (int)$c[1] ?></p>
    </div>
    <?php endforeach; ?>
    <div class="bg-white rounded-lg shadow p-3 col-span-2 border-t-4 border-emerald-500">
        <p class="text-[11px] text-gray-500 uppercase">Est. Procurement Value / Potential Savings</p>
        <p class="font-bold"><?= formatMoney((float)$st['est_value']) ?></p>
        <p class="text-sm text-green-600 font-semibold">Savings: <?= formatMoney($savings) ?></p>
    </div>
</div>

<div class="bg-white rounded-lg shadow p-5 max-w-2xl text-sm text-gray-600">
    <b>Workflow:</b> Approved PR → Create RFQ → add vendors → Send → receive quotations → evaluate (weighted) → compare → award (full or split).<br>
    Use the buttons above. The awarded data feeds P3 — Purchase Orders.
</div>

<?php require_once '../../../includes/footer.php'; ?>
