<?php
/** P1 — Procurement setup (idempotent): perms, sidebar, default approval rule */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/migrations/runner.php';

$db = getDB();
echo "=== P1 Procurement Setup ===\n\n";

echo "1. Migration 019...\n";
applySql($db, __DIR__ . '/config/migrations/019_procurement_p1.sql');
echo "   done\n";

echo "2. Permissions...\n";
$permissions = [
    ['view','procurement'],['pr.create','procurement'],['pr.edit','procurement'],
    ['pr.submit','procurement'],['pr.approve','procurement'],['pr.reject','procurement'],
    ['pr.cancel','procurement'],['pr.report','procurement'],['settings.manage','procurement'],
];
$st = $db->prepare("INSERT IGNORE INTO permissions (name, module) VALUES (?,?)");
foreach ($permissions as $p) $st->execute($p);
$db->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
           SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
           WHERE r.name='Super Admin' AND p.module='procurement'");
$db->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
           SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
           WHERE r.name='Admin' AND p.module='procurement' AND p.name<>'settings.manage'");
echo "   ✓ ".count($permissions)." permissions granted to Super Admin (+Admin role)\n";

echo "3. Sidebar menu (Procurement section)...\n";
$menu = [
    [false, 'Procurement Dashboard',  'fas fa-cart-shopping', 'modules/procurement/dashboard/index.php',              'procurement.view',            1],
    [true,  'Requisitions',           'fas fa-file-lines',    'modules/procurement/requisitions/index.php',           'procurement.view',            2],
    [true,  'Pending Approvals',      'fas fa-stamp',         'modules/procurement/requisitions/approvals.php',       'procurement.view',            3],
    [true,  'PR Reports',             'fas fa-chart-column',  'modules/procurement/reports/index.php',                'procurement.pr.report',       4],
    [true,  'Procurement Settings',   'fas fa-gear',          'modules/procurement/settings/index.php',               'procurement.settings.manage', 5],
];
$dashboardRoute = 'modules/procurement/dashboard/index.php';
$dashboardId = null;
foreach ($menu as $m) {
    $parentId = $m[0] ? $dashboardId : null;
    if ($m[0] && !$parentId) {
        $s = $db->prepare("SELECT id FROM sidebar_menu WHERE route=? LIMIT 1");
        $s->execute([$dashboardRoute]);
        $parentId = (int)$s->fetchColumn();
        if (!$parentId) {
            throw new RuntimeException('Procurement dashboard menu entry was not created.');
        }
    }
    $chk = $db->prepare("SELECT id FROM sidebar_menu WHERE route=? LIMIT 1");
    $chk->execute([$m[3]]);
    $existingId = (int)$chk->fetchColumn();
    if ($existingId) {
        $update = $db->prepare("UPDATE sidebar_menu
                                SET parent_id=?, section='Procurement', section_order=6, label=?, icon=?,
                                    permission=?, sort_order=?, is_active=1, is_indented=?
                                WHERE id=?");
        $update->execute([$parentId, $m[1], $m[2], $m[4], $m[5], $m[0] ? 1 : 0, $existingId]);
        $menuId = $existingId;
    } else {
        $insert = $db->prepare("INSERT INTO sidebar_menu
            (parent_id, section, section_order, label, icon, route, permission, sort_order, is_active, is_indented)
            VALUES (?, 'Procurement', 6, ?, ?, ?, ?, ?, 1, ?)");
        $insert->execute([$parentId, $m[1], $m[2], $m[3], $m[4], $m[5], $m[0] ? 1 : 0]);
        $menuId = (int)$db->lastInsertId();
    }
    if ($m[3] === $dashboardRoute) {
        $dashboardId = $menuId;
    }
}
echo "   ✓ ensured\n";

echo "4. Default approval rules...\n";
if (!(int)$db->query("SELECT COUNT(*) FROM procurement_approval_rules")->fetchColumn()) {
    // Route: everything → Admin role; totals above 50,000 also require Super Admin
    $adminRole  = (int)$db->query("SELECT id FROM roles WHERE name='Admin' LIMIT 1")->fetchColumn();
    $superRole  = (int)$db->query("SELECT id FROM roles WHERE name='Super Admin' LIMIT 1")->fetchColumn();
    $db->prepare("INSERT INTO procurement_approval_rules (company_id,department_id,priority,max_amount,approver_role_id,rule_sequence,is_active)
                  VALUES (NULL,NULL,NULL,50000,?,1,1)")->execute([$adminRole ?: 1]);
    $db->prepare("INSERT INTO procurement_approval_rules (company_id,department_id,priority,max_amount,approver_role_id,rule_sequence,is_active)
                  VALUES (NULL,NULL,NULL,NULL,?,2,1)")->execute([$superRole ?: 1]);
    echo "   ✓ seeded: ≤50k needs Admin, any amount also needs Super Admin (configurable)\n";
}
$uploadDir = __DIR__.'/uploads/procurement';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0750, true);
$uploadProtection = "Require all denied\n";
if (!is_file($uploadDir.'/.htaccess') || file_get_contents($uploadDir.'/.htaccess') !== $uploadProtection) {
    file_put_contents($uploadDir.'/.htaccess', $uploadProtection);
}

echo "\n=== P1 setup complete ===\n";
