<?php
/** P1 — Pending Approvals queue */
$pageTitle = 'Pending Approvals';
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('procurement.view')) { die('Access Denied'); }
$db = getDB();
$companyIds = procurementAccessibleCompanyIds($db, $_SESSION['user_id']);
$companyScope = procurementCompanyScopeSql($companyIds);
$pendingStmt = $db->prepare("
    SELECT pr.*, u.name requester_name, c.company_name
    FROM purchase_requisitions pr
    LEFT JOIN users u ON u.id=pr.requester_id LEFT JOIN companies c ON c.id=pr.company_id
    WHERE {$companyScope}
      AND pr.status IN ('submitted','pending_approval')
    ORDER BY FIELD(pr.priority,'urgent','high','normal','low'), pr.submitted_at");
$pendingStmt->execute($companyIds);
$pending = $pendingStmt->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div><h1 class="text-2xl font-bold text-gray-800">Pending Approvals</h1>
    <p class="text-gray-600">Requisitions waiting for approval — urgent first</p></div>
    <a href="<?= url('modules/procurement/dashboard/index.php') ?>" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-3 text-left">PR Number</th><th class="px-4 py-3 text-left">Requester</th>
            <th class="px-4 py-3 text-left">Company</th><th class="px-4 py-3 text-right">Total</th>
            <th class="px-4 py-3 text-center">Priority</th><th class="px-4 py-3 text-left">Waiting Since</th><th></th>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($pending as $r): ?>
            <tr class="hover:bg-gray-50 <?= $r['priority']==='urgent'?'bg-red-50/50':'' ?>">
                <td class="px-4 py-2 font-mono text-xs"><?= sanitize($r['pr_number']) ?></td>
                <td class="px-4 py-2"><?= sanitize($r['requester_name']) ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($r['company_name']) ?></td>
                <td class="px-4 py-2 text-right font-mono"><?= formatMoney($r['grand_total'],$r['currency']) ?></td>
                <td class="px-4 py-2 text-center uppercase <?= $r['priority']==='urgent'?'text-red-600 font-bold':'text-gray-500' ?>"><?= $r['priority'] ?></td>
                <td class="px-4 py-2 text-xs"><?= $r['submitted_at'] ? date('d M Y H:i', strtotime($r['submitted_at'])) : '—' ?></td>
                <td class="px-4 py-2 text-right"><a href="<?= url('modules/procurement/requisitions/view.php?id=' . (int)$r['id']) ?>" class="text-blue-600 hover:underline text-xs">Review →</a></td>
            </tr>
        <?php endforeach; if (empty($pending)): ?><tr><td colspan="7" class="px-4 py-10 text-center text-green-600"><i class="fas fa-circle-check mr-1"></i>Nothing pending.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once PROCUREMENT_APP_ROOT . '/includes/footer.php'; ?>
