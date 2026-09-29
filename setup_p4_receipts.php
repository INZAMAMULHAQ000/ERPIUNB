<?php
/** P4 — Goods Receipt / Service Receipt setup (idempotent) */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/migrations/runner.php';
$db = getDB();
echo "=== P4 Receipt Setup ===" . PHP_EOL;

echo "1. Migration 022...\n";
applySql($db, __DIR__ . '/config/migrations/022_receipts_p4.sql', true);
echo "   done\n";

echo "2. Permissions...\n";
$perms = [
    ['receipt.view','procurement'],['receipt.create','procurement'],['receipt.edit','procurement'],
    ['receipt.submit','procurement'],['receipt.inspect','procurement'],['receipt.accept','procurement'],
    ['receipt.reject','procurement'],['receipt.cancel','procurement'],['receipt.report','procurement'],
];
$st = $db->prepare("INSERT IGNORE INTO permissions (name,module) VALUES (?,?)");
foreach ($perms as $p) $st->execute($p);
$db->exec("INSERT IGNORE INTO role_permissions (role_id,permission_id) SELECT 1,id FROM permissions WHERE module='procurement'");
$db->exec("INSERT IGNORE INTO role_permissions (role_id,permission_id) SELECT 2,id FROM permissions WHERE module='procurement'");
echo "   ✓ ".count($perms)." permissions ensured\n";

echo "3. Sidebar menu...\n";
$parent = (int)$db->query("SELECT id FROM sidebar_menu WHERE label='Procurement Dashboard' LIMIT 1")->fetchColumn();
$menu = [
    ['Receipt Dashboard', 'modules/procurement/receipts/dashboard/index.php', 'procurement.receipt.view', 'fa-clipboard-check'],
    ['Receipt List',      'modules/procurement/receipts/list/index.php',     'procurement.receipt.view', 'fa-list'],
    ['Create GR',         'modules/procurement/receipts/create/index.php',   'procurement.receipt.create','fa-plus-circle'],
    ['Receipt Reports',   'modules/procurement/receipts/reports/index.php',  'procurement.receipt.report','fa-chart-bar'],
];
if ($parent) foreach ($menu as $m) {
    $c = $db->prepare("SELECT id FROM sidebar_menu WHERE label=?");
    $c->execute([$m[0]]);
    $ex = $c->fetchColumn();
    if ($ex) {
        $db->prepare("UPDATE sidebar_menu SET route=?,permission=? WHERE id=?")->execute([$m[1], $m[2], $ex]);
        continue;
    }
    $db->prepare("INSERT INTO sidebar_menu (parent_id,section,label,icon,route,permission,sort_order,is_active)
                  VALUES (?, 'Procurement', ?, ?, ?, ?, 9, 1)")
       ->execute([$parent, $m[0], $m[3], $m[1], $m[2]]);
}
echo "   ✓ menu entries ensured\n";

$uploadDir = __DIR__.'/uploads/procurement/receipts';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0750, true);
file_put_contents($uploadDir.'/.htaccess', "Require all denied\n");

echo "\n=== P4 setup complete ===\n";
