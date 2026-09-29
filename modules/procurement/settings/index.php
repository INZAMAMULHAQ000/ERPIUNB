<?php
/** P1 — Procurement Settings + Approval Rules */
$pageTitle = 'Procurement Settings';
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('procurement.settings.manage')) { die('Access Denied'); }
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error','Invalid request.'); to('modules/procurement/settings/index.php'); }
    if (isset($_POST['save_settings'])) {
        ProcurementService::saveSettings($db, [
            'pr_prefix'=>strtoupper(trim($_POST['pr_prefix'] ?? 'PR')),
            'rfq_prefix'=>strtoupper(trim($_POST['rfq_prefix'] ?? 'RFQ')),
            'quote_prefix'=>strtoupper(trim($_POST['quote_prefix'] ?? 'QUO')),
            'po_prefix'=>strtoupper(trim($_POST['po_prefix'] ?? 'PO')),
            'grn_prefix'=>strtoupper(trim($_POST['grn_prefix'] ?? 'GRN')),
            'default_currency'=>strtoupper($_POST['default_currency'] ?? 'AED'),
        ], $_SESSION['user_id']);
        flash('success','Settings saved.');
    } elseif (isset($_POST['add_rule'])) {
        $db->prepare("INSERT INTO procurement_approval_rules (company_id,department_id,priority,max_amount,approver_role_id,rule_sequence,is_active)
                      VALUES (?,?,?,?,?,?,1)")
           ->execute([$_POST['company_id'] ?: null, $_POST['department_id'] ?: null,
                      in_array($_POST['priority'] ?? '', ['low','normal','high','urgent'], true) ? $_POST['priority'] : null,
                      ($_POST['max_amount'] ?? '') !== '' ? (float)$_POST['max_amount'] : null,
                      (int)$_POST['approver_role_id'], (int)($_POST['rule_sequence'] ?? 1)]);
        flash('success','Approval rule added.');
    } elseif (isset($_POST['toggle_rule'])) {
        $db->prepare("UPDATE procurement_approval_rules SET is_active = 1 - is_active WHERE id=?")->execute([(int)$_POST['id']]);
        flash('info','Rule toggled.');
    }
    to('modules/procurement/settings/index.php');
}

$settings = ProcurementService::getSettings($db);
$rules = $db->query("SELECT ar.*, r.name role_name, c.company_name, d.department_name
                     FROM procurement_approval_rules ar
                     JOIN roles r ON r.id=ar.approver_role_id
                     LEFT JOIN companies c ON c.id=ar.company_id
                     LEFT JOIN departments d ON d.id=ar.department_id
                     ORDER BY ar.rule_sequence, ar.id")->fetchAll();
$roles = $db->query("SELECT id, name FROM roles ORDER BY id")->fetchAll();
$companies = $db->query("SELECT id, company_name FROM companies")->fetchAll();
$departments = $db->query("SELECT id, department_name FROM departments")->fetchAll();
?>
<div class="mb-6 flex justify-between items-center">
    <div><h1 class="text-2xl font-bold text-gray-800">Procurement Settings</h1></div>
    <a href="<?= url('modules/procurement/dashboard/index.php') ?>" class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">Back</a>
</div>

<form method="post" class="bg-white rounded-lg shadow p-5 mb-6 grid grid-cols-2 md:grid-cols-6 gap-4 items-end">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><button name="save_settings" value="1" class="hidden"></button>
    <?php foreach ([['pr_prefix','PR Prefix'],['rfq_prefix','RFQ Prefix'],['quote_prefix','Quote Prefix'],['po_prefix','PO Prefix'],['grn_prefix','GRN Prefix']] as $f): ?>
    <div><label class="text-xs text-gray-500 block mb-1"><?= $f[1] ?></label>
        <input name="<?= $f[0] ?>" value="<?= sanitize($settings[$f[0]]) ?>" class="w-full border rounded px-2 py-2 text-sm"></div>
    <?php endforeach; ?>
    <div><label class="text-xs text-gray-500 block mb-1">Default Currency</label>
        <select name="default_currency" class="w-full border rounded px-2 py-2 text-sm">
        <?php foreach (['AED','USD','EUR','GBP','SAR'] as $cu): ?><option <?= $settings['default_currency']===$cu?'selected':'' ?>><?= $cu ?></option><?php endforeach; ?></select></div>
    <button name="save_settings" value="1" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm md:col-span-6 w-max">Save Settings</button>
</form>

<h3 class="font-bold mb-2">Approval Rules (configurable — evaluated top-down; every matching rule must approve)</h3>
<form method="post" class="bg-white rounded-lg shadow p-4 mb-5 grid grid-cols-2 md:grid-cols-7 gap-3 items-end">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><button name="add_rule" hidden></button>
    <select name="company_id" class="border rounded px-2 py-2 text-sm"><option value="">Any company</option>
        <?php foreach ($companies as $c): ?><option value="<?= $c['id'] ?>"><?= sanitize($c['company_name']) ?></option><?php endforeach; ?></select>
    <select name="department_id" class="border rounded px-2 py-2 text-sm"><option value="">Any dept</option>
        <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= sanitize($d['department_name']) ?></option><?php endforeach; ?></select>
    <select name="priority" class="border rounded px-2 py-2 text-sm"><option value="">Any priority</option><option>urgent</option><option>high</option><option>normal</option><option>low</option></select>
    <input type="number" step="0.01" name="max_amount" placeholder="Max total (blank=∞)" class="border rounded px-2 py-2 text-sm">
    <select name="approver_role_id" required class="border rounded px-2 py-2 text-sm"><?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>">Role: <?= sanitize($r['name']) ?></option><?php endforeach; ?></select>
    <input type="number" name="rule_sequence" value="1" min="1" class="border rounded px-2 py-2 text-sm" title="Sequence">
    <button name="add_rule" class="bg-gray-800 text-white px-4 py-2 rounded text-sm">Add Rule</button>
</form>

<div class="bg-white rounded-lg shadow overflow-x-auto mb-8">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs"><tr>
            <th class="px-4 py-3">Seq</th><th class="px-4 py-3 text-left">Company</th><th class="px-4 py-3 text-left">Dept</th>
            <th class="px-4 py-3 text-left">Priority</th><th class="px-4 py-3 text-right">Max Amount</th>
            <th class="px-4 py-3 text-left">Approver Role</th><th class="px-4 py-3">Active</th><th></th>
        </tr></thead>
        <tbody class="divide-y">
        <?php foreach ($rules as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 text-center"><?= (int)$r['rule_sequence'] ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($r['company_name'] ?? 'ANY') ?></td>
                <td class="px-4 py-2 text-xs"><?= sanitize($r['department_name'] ?? 'ANY') ?></td>
                <td class="px-4 py-2 text-xs uppercase"><?= sanitize($r['priority'] ?? 'ANY') ?></td>
                <td class="px-4 py-2 text-right font-mono"><?= $r['max_amount']!==null ? number_format((float)$r['max_amount'],2) : '∞' ?></td>
                <td class="px-4 py-2"><?= sanitize($r['role_name']) ?></td>
                <td class="px-4 py-2 text-center"><?= $r['is_active']?'✓':'—' ?></td>
                <td class="px-4 py-2 text-right">
                    <form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="toggle_rule" value="1"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button class="text-orange-600 hover:underline text-xs">Toggle</button></form></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once PROCUREMENT_APP_ROOT . '/includes/footer.php'; ?>

