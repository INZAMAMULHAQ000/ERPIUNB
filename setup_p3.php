<?php
/** P3 — Purchase Order setup (idempotent) */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/migrations/runner.php';
$db = getDB();
echo "=== P3 PO Setup ===" . PHP_EOL;
applySql($db, __DIR__ . '/config/migrations/021_po_p3.sql', true);

$perms = [
    ['po.view','procurement'],['po.create','procurement'],['po.edit','procurement'],
    ['po.submit','procurement'],['po.approve','procurement'],['po.reject','procurement'],
    ['po.send','procurement'],['po.acknowledge','procurement'],['po.revise','procurement'],
    ['po.cancel','procurement'],['po.close','procurement'],['po.report','procurement'],
];
$st = $db->prepare("INSERT IGNORE INTO permissions (name,module) VALUES (?,?)");
foreach ($perms as $p) $st->execute($p);

// Super Admin: all; Admin role: all
$db->exec("INSERT IGNORE INTO role_permissions (role_id,permission_id) SELECT 1,id FROM permissions WHERE module='procurement'");
$db->exec("INSERT IGNORE INTO role_permissions (role_id,permission_id) SELECT 2,id FROM permissions WHERE module='procurement'");
echo "permissions ensured" . PHP_EOL;

// Menu entries under Procurement parent
$parent = (int)$db->query("SELECT id FROM sidebar_menu WHERE label='Procurement Dashboard' LIMIT 1")->fetchColumn();
$menu = [
    ['PO Dashboard',      'modules/procurement/purchase_orders/dashboard.php', 'procurement.po.view',      'fa-file-invoice'],
    ['PO List',           'modules/procurement/purchase_orders/list.php',     'procurement.po.view',      'fa-list'],
    ['Create PO',         'modules/procurement/purchase_orders/create.php',   'procurement.po.create',    'fa-plus-circle'],
    ['PO Reports',        'modules/procurement/purchase_orders/reports.php',  'procurement.po.report',    'fa-chart-bar'],
];
if ($parent) foreach ($menu as $m) {
    $c = $db->prepare("SELECT id FROM sidebar_menu WHERE label=?");
    $c->execute([$m[0]]);
    $ex = $c->fetchColumn();
    if ($ex) { $db->prepare("UPDATE sidebar_menu SET route=?,permission=? WHERE id=?")->execute([$m[1], $m[2], $ex]); continue; }
    $i = $db->prepare("INSERT INTO sidebar_menu (parent_id,section,label,icon,route,permission,sort_order,is_active)
                       VALUES (?, 'Procurement', ?, ?, ?, ?, 7, 1)");
    $i->execute([$parent, $m[0], $m[3], $m[1], $m[2]]);
}
echo "=== done ===" . PHP_EOL;
