<?php
/** P1 — Procurement Reports hub + parameterized register/value reports */
$pageTitle = 'Procurement Reports';
$report = $_GET['r'] ?? '';
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('procurement.pr.report')) { die('Access Denied'); }
$db = getDB();
$companyIds = procurementAccessibleCompanyIds($db, $_SESSION['user_id']);
$companyScope = procurementCompanyScopeSql($companyIds);

$reports = [
    ['register',   'Purchase Requisition Register', 'All PRs with values & status'],
    ['pending',    'Pending Approval Report',       'Awaiting decision'],
    ['approved',   'Approved Requisition Report',   'Ready for RFQ phase'],
    ['rejected',   'Rejected Requisition Report',   'With reasons'],
    ['by_dept',    'PR Value by Department',        'Aggregated'],
    ['by_project', 'PR Value by Project',           'Aggregated'],
    ['by_cc',      'PR Value by Cost Center',       'Aggregated'],
];
if ($report === ''): ?>
<div class="mb-6 flex justify-between items-center">
    <div><h1 class="text-2xl font-bold text-gray-800">Procurement Reports</h1></div>
    <a href="<?= url('modules/procurement/dashboard/index.php') ?>" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
<?php foreach ($reports as $r): ?>
    <a href="<?= url('modules/procurement/reports/index.php?r=' . $r[0]) ?>" class="bg-white rounded-lg shadow p-5 hover:shadow-md block">
        <i class="fas fa-chart-column text-blue-500 mb-2"></i>
        <h3 class="font-semibold"><?= $r[1] ?></h3><p class="text-sm text-gray-500"><?= $r[2] ?></p>
    </a>
<?php endforeach; ?>
</div>
<?php
require_once PROCUREMENT_APP_ROOT . '/includes/footer.php'; return; endif;

// ---- report bodies ----
$where = ["pr.status<>'draft'", $companyScope];
$params = $companyIds;
$groupExpression = '';
$groupJoin = '';
$groupHeading = '';
$selectExtra = '';
switch ($report) {
    case 'pending':  $where[]="pr.status IN ('submitted','pending_approval')"; $title='Pending Approval Report'; break;
    case 'approved': $where[]="pr.status='approved'"; $title='Approved Requisition Report'; break;
    case 'rejected': $where[]="pr.status='rejected'"; $title='Rejected Requisition Report'; $selectExtra=', pr.rejection_reason'; break;
    case 'by_dept':
        $title='PR Value by Department';
        $groupHeading='Department';
        $groupExpression='COALESCE(d.department_name,"—")';
        $groupJoin='LEFT JOIN departments d ON d.id=pr.department_id';
        break;
    case 'by_project':
        $title='PR Value by Project';
        $groupHeading='Project';
        $groupExpression='COALESCE(p.project_name,"—")';
        $groupJoin='LEFT JOIN projects p ON p.id=pr.project_id';
        break;
    case 'by_cc':
        $title='PR Value by Cost Center';
        $groupHeading='Cost Center';
        $groupExpression='COALESCE(cc.cost_center_name,"—")';
        $groupJoin='LEFT JOIN cost_centers cc ON cc.id=pr.cost_center_id';
        break;
    case 'register':
    default: $title='Purchase Requisition Register';
}
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="text-2xl font-bold text-gray-800"><?= $title ?></h1></div>
    <div class="flex gap-2">
        <button onclick="window.print()" type="button" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm"><i class="fas fa-print mr-1"></i>Print</button>
        <a href="<?= url('modules/procurement/reports/index.php') ?>" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
<?php if ($groupExpression): ?>
    <?php
    $sql = "SELECT {$groupExpression} AS grp, pr.currency, COUNT(*) AS cnt,
                   SUM(pr.grand_total) AS total_value
            FROM purchase_requisitions pr
            {$groupJoin}
            WHERE ".implode(' AND ',$where)."
            GROUP BY grp, pr.currency
            ORDER BY total_value DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    ?>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-3 text-left"><?= sanitize($groupHeading) ?></th>
            <th class="px-4 py-3 text-left">Currency</th>
            <th class="px-4 py-3 text-right">PRs</th><th class="px-4 py-3 text-right">Total Value</th>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($rows as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2"><?= sanitize($r['grp']) ?></td>
                <td class="px-4 py-2"><?= sanitize($r['currency']) ?></td>
                <td class="px-4 py-2 text-right"><?= (int)$r['cnt'] ?></td>
                <td class="px-4 py-2 text-right font-mono"><?= formatMoney($r['total_value'], $r['currency']) ?></td>
            </tr>
        <?php endforeach; if (empty($rows)): ?><tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">No data.</td></tr><?php endif; ?>
        </tbody>
    </table>
<?php else: ?>
    <?php
    $sql = "SELECT pr.*, u.name requester_name, c.company_name, d.department_name {$selectExtra}
            FROM purchase_requisitions pr
            LEFT JOIN users u ON u.id=pr.requester_id LEFT JOIN companies c ON c.id=pr.company_id
            LEFT JOIN departments d ON d.id=pr.department_id
            WHERE ".implode(' AND ',$where)." ORDER BY pr.id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    ?>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-3 text-left">PR Number</th><th class="px-4 py-3 text-left">Date</th>
            <th class="px-4 py-3 text-left">Requester</th><th class="px-4 py-3 text-left">Company</th>
            <th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-left">Status</th>
            <?php if ($report==='rejected'): ?><th class="px-4 py-3 text-left">Reason</th><?php endif; ?>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($rows as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-mono text-xs"><?= sanitize($r['pr_number']) ?></td>
                <td class="px-4 py-2"><?= date('d M Y', strtotime($r['request_date'])) ?></td>
                <td class="px-4 py-2"><?= sanitize($r['requester_name']) ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($r['company_name']) ?></td>
                <td class="px-4 py-2 text-right font-mono"><?= number_format((float)$r['grand_total'],2) ?> <?= sanitize($r['currency']) ?></td>
                <td class="px-4 py-2 uppercase text-xs font-semibold"><?= str_replace('_',' ',$r['status']) ?></td>
                <?php if ($report==='rejected'): ?><td class="px-4 py-2 text-xs text-red-600"><?= sanitize(mb_strimwidth($r['rejection_reason'] ?? '',0,60,'…')) ?></td><?php endif; ?>
            </tr>
        <?php endforeach; if (empty($rows)): ?><tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No data.</td></tr><?php endif; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>

<?php require_once PROCUREMENT_APP_ROOT . '/includes/footer.php'; ?>
