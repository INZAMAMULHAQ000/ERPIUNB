<?php
/** P1 — PR List with filters */
$pageTitle = 'Purchase Requisitions';
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('procurement.view')) { die('Access Denied'); }
$db = getDB();

// company scope (STEP 33 / Test 7)
$accessible = CompanyService::accessibleCompanies($db, $_SESSION['user_id']);
$accIds = procurementAccessibleCompanyIds($db, $_SESSION['user_id']);
$scopeSql = procurementCompanyScopeSql($accIds);

$where = [$scopeSql]; $params = $accIds;
if (!empty($_GET['pr_number'])) { $where[]='pr.pr_number LIKE ?'; $params[]='%'.trim($_GET['pr_number']).'%'; }
if (!empty($_GET['status']))    { $where[]='pr.status=?'; $params[]=$_GET['status']; }
if (!empty($_GET['priority']))  { $where[]='pr.priority=?'; $params[]=$_GET['priority']; }
if (!empty($_GET['company_id'])){ $where[]='pr.company_id=?'; $params[]=(int)$_GET['company_id']; }
if (!empty($_GET['department_id'])){ $where[]='pr.department_id=?'; $params[]=(int)$_GET['department_id']; }
if (!empty($_GET['date_from'])){ $where[]='pr.request_date>=?'; $params[]=$_GET['date_from']; }
if (!empty($_GET['date_to'])){   $where[]='pr.request_date<=?'; $params[]=$_GET['date_to']; }

$sql = "SELECT pr.*, u.name requester_name, d.department_name, c.company_name
        FROM purchase_requisitions pr
        LEFT JOIN users u ON u.id=pr.requester_id
        LEFT JOIN departments d ON d.id=pr.department_id
        LEFT JOIN companies c ON c.id=pr.company_id
        WHERE ".implode(' AND ',$where)."
        ORDER BY pr.id DESC LIMIT 200";
$s=$db->prepare($sql); $s->execute($params);
$rows=$s->fetchAll();

$statusStyles=['draft'=>'bg-gray-100 text-gray-600','submitted'=>'bg-blue-100 text-blue-700','pending_approval'=>'bg-yellow-100 text-yellow-700',
               'approved'=>'bg-green-100 text-green-700','rejected'=>'bg-red-100 text-red-700','cancelled'=>'bg-purple-100 text-purple-700','closed'=>'bg-slate-200 text-slate-600'];
$prioStyles=['low'=>'text-gray-400','normal'=>'text-gray-600','high'=>'text-orange-600 font-semibold','urgent'=>'text-red-600 font-bold'];
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="text-2xl font-bold text-gray-800">Purchase Requisitions</h1></div>
    <?php if (hasPermission('procurement.pr.create')): ?>
    <a href="<?= url('modules/procurement/requisitions/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm"><i class="fas fa-plus mr-1"></i>New Requisition</a>
    <?php endif; ?>
</div>

<form method="get" class="bg-white rounded-lg shadow p-4 mb-5 grid grid-cols-2 md:grid-cols-7 gap-3 items-end">
    <input name="pr_number" value="<?= sanitize($_GET['pr_number'] ?? '') ?>" placeholder="PR Number" class="border rounded px-2 py-2 text-sm">
    <select name="status" class="border rounded px-2 py-2 text-sm"><option value="">All statuses</option>
        <?php foreach ($statusStyles as $k=>$v): ?><option value="<?= $k ?>" <?= ($_GET['status']?? '')===$k?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$k)) ?></option><?php endforeach; ?></select>
    <select name="company_id" class="border rounded px-2 py-2 text-sm"><option value="">All companies</option>
    	<?php foreach ($accessible as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (($_GET['company_id'] ?? '') == (string)$c['id']) ? 'selected' : '' ?>><?= sanitize($c['company_name']) ?></option><?php endforeach; ?></select>
    <input type="date" name="date_from" value="<?= sanitize($_GET['date_from'] ?? '') ?>" class="border rounded px-2 py-2 text-sm">
    <input type="date" name="date_to" value="<?= sanitize($_GET['date_to'] ?? '') ?>" class="border rounded px-2 py-2 text-sm">
    <select name="priority" class="border rounded px-2 py-2 text-sm"><option value="">All priorities</option>
        <?php foreach (['urgent','high','normal','low'] as $p): ?><option <?= ($_GET['priority']?? '')===$p?'selected':'' ?>><?= $p ?></option><?php endforeach; ?></select>
    <button class="bg-gray-800 text-white px-4 py-2 rounded text-sm hover:bg-gray-900">Filter</button>
</form>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-3 text-left">PR Number</th><th class="px-4 py-3 text-left">Date</th>
            <th class="px-4 py-3 text-left">Requester</th><th class="px-4 py-3 text-left">Dept</th>
            <th class="px-4 py-3 text-left">Company</th><th class="px-4 py-3 text-right">Total</th>
            <th class="px-4 py-3 text-center">Priority</th><th class="px-4 py-3 text-left">Required</th>
            <th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3"></th>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($rows as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-mono text-xs"><?= sanitize($r['pr_number']) ?></td>
                <td class="px-4 py-2"><?= date('d M Y', strtotime($r['request_date'])) ?></td>
                <td class="px-4 py-2"><?= sanitize($r['requester_name']) ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($r['department_name'] ?? '—') ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($r['company_name']) ?></td>
                <td class="px-4 py-2 text-right font-mono"><?= formatMoney($r['grand_total'], null, $r['currency']) ?></td>
                <td class="px-4 py-2 text-center <?= $prioStyles[$r['priority']] ?>"><?= strtoupper($r['priority']) ?></td>
                <td class="px-4 py-2 text-xs"><?= date('d M Y', strtotime($r['required_date'])) ?></td>
                <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs font-semibold <?= $statusStyles[$r['status']] ?>"><?= strtoupper(str_replace('_',' ',$r['status'])) ?></span></td>
                <td class="px-4 py-2 text-right"><a href="<?= url('modules/procurement/requisitions/view.php?id=' . (int)$r['id']) ?>" class="text-blue-600 hover:underline text-xs">Open →</a></td>
            </tr>
        <?php endforeach; if (empty($rows)): ?><tr><td colspan="10" class="px-4 py-10 text-center text-gray-400">No requisitions match.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once PROCUREMENT_APP_ROOT . '/includes/footer.php'; ?>
