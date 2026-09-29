<?php
/** P2 — RFQ & Quotations setup (idempotent) */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/migrations/runner.php';
$db = getDB();
echo "=== P2 RFQ Setup ===\n";
applySql($db, __DIR__ . '/config/migrations/020_rfq_p2.sql');

$perms = [
    ['rfq.view','procurement'],['rfq.create','procurement'],['rfq.edit','procurement'],
    ['rfq.send','procurement'],['rfq.cancel','procurement'],
    ['quotation.view','procurement'],['quotation.create','procurement'],['quotation.edit','procurement'],
    ['quotation.evaluate','procurement'],['quotation.select','procurement'],['quotation.override','procurement'],
    ['rfq.report','procurement'],
];
$st=$db->prepare("INSERT IGNORE INTO permissions (name,module) VALUES (?,?)");
foreach ($perms as $p) $st->execute($p);
// Super Admin: all; Admin role: all except override
$db->exec("INSERT IGNORE INTO role_permissions (role_id,permission_id) SELECT 1,id FROM permissions WHERE module='procurement'");
$db->exec("INSERT IGNORE INTO role_permissions (role_id,permission_id)
           SELECT 2,p.id FROM permissions p WHERE p.module='procurement'
             AND NOT (p.name='quotation.override')");
echo "permissions ensured\n";

// menu children under Procurement parent
$parent=(int)$db->query("SELECT id FROM sidebar_menu WHERE label='Procurement Dashboard' LIMIT 1")->fetchColumn();
$menu = [
    ['RFQ Dashboard',   'modules/procurement/rfq/dashboard.php',            'procurement.rfq.view',       'fa-file-signature'],
    ['Quotation Comparison', 'modules/procurement/quotations/comparison.php','procurement.quotation.view','fa-scale-unbalanced'],
];
if ($parent) foreach ($menu as $m) {
    $c=$db->prepare("SELECT id FROM sidebar_menu WHERE label=?"); $c->execute([$m[0]]); $ex=$c->fetchColumn();
    if ($ex) { $db->prepare("UPDATE sidebar_menu SET route=?,permission=? WHERE id=?")->execute([$m[1],$m[2],$ex]); continue; }
    $i=$db->prepare("INSERT INTO sidebar_menu (parent_id,section,label,icon,route,permission,sort_order,is_active)
                     VALUES (?, 'Procurement', ?, ?, ?, ?, 6, 1)");
    $i->execute([$parent,$m[0],$m[3],$m[1],$m[2]]);
}
echo "=== done ===\n";
