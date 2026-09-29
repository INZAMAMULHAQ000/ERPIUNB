<?php
/**
 * F13 — Multi-Company & Consolidation Setup
 * Run: php setup_f13_group.php — idempotent.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/migrations/runner.php';

$db = getDB();
echo "=== F13 Group & Consolidation Setup ===\n\n";

echo "1. Running migration 018_multicompany_f13.sql...\n";
$n = applySql($db, __DIR__ . '/config/migrations/018_multicompany_f13.sql');
echo "   ✓ applied $n statements\n";

echo "2. Seeding permissions...\n";
$permissions = [
    ['view','company'],['create','company'],['edit','company'],
    ['view','intercompany'],['create','intercompany'],['match','intercompany'],['reconcile','intercompany'],
    ['create','consolidation'],['validate','consolidation'],['approve','consolidation'],
    ['post','consolidation'],['report','consolidation'],
];
$stmt = $db->prepare("INSERT IGNORE INTO permissions (name, module) VALUES (?, ?)");
foreach ($permissions as $p) $stmt->execute($p);
$db->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
           SELECT 1, id FROM permissions WHERE module IN ('company','intercompany','consolidation')");
echo "   ✓ ".count($permissions)." permissions, granted to Super Admin\n";

echo "3. Seeding sidebar menu (Group & Consolidation)...\n";
$menu = [
    [null, 'Group Dashboard',        'fas fa-sitemap',          'modules/finance/group/index.php',                        'company.view', 40],
    ['companies',       'Companies',              'fas fa-building',      'modules/finance/group/companies/index.php',       'company.view', 1],
    ['relationships',   'Relationships',          'fas fa-diagram-project','modules/finance/group/relationships/index.php',  'company.view', 2],
    ['ic_txn',          'IC Transactions',        'fas fa-right-left',    'modules/finance/group/intercompany/transactions.php','intercompany.view', 3],
    ['ic_match',        'IC Matching',            'fas fa-link',          'modules/finance/group/intercompany/matching.php', 'intercompany.match', 4],
    ['ic_rec',          'IC Reconciliation',      'fas fa-scale-balanced','modules/finance/group/intercompany/reconciliation.php','intercompany.reconcile', 5],
    ['runs',            'Consolidation Runs',     'fas fa-layer-group',   'modules/finance/group/consolidation/runs.php',    'consolidation.create', 6],
    ['reports',         'Consolidation Reports',  'fas fa-chart-column',  'modules/finance/group/reports/index.php',         'consolidation.report', 7],
];
foreach ($menu as $m) {
    $parentId = null;
    if ($m[0] !== null) {
        $s = $db->prepare("SELECT id FROM sidebar_menu WHERE label='Group Dashboard' AND section='Finance' LIMIT 1");
        $s->execute(); $parentId = $s->fetchColumn();
        if (!$parentId) continue;
    }
    $chk = $db->prepare("SELECT id FROM sidebar_menu WHERE label=? AND section='Finance'");
    $chk->execute([$m[1]]); $ex = $chk->fetchColumn();
    if ($ex) { $u=$db->prepare("UPDATE sidebar_menu SET icon=?,route=?,permission=?,sort_order=? WHERE id=?");
               $u->execute([$m[2],$m[3],$m[4],$m[5],$ex]); continue; }
    $i = $db->prepare("INSERT INTO sidebar_menu (parent_id, section, label, icon, route, permission, sort_order, is_active)
                       VALUES (?, 'Finance', ?, ?, ?, ?, ?, 1)");
    $i->execute([$parentId, $m[1], $m[2], $m[3], $m[4], $m[5]]);
}
echo "   ✓ menu ensured\n";

echo "4. Ensuring default company + demo group structure...\n";
$db->exec("INSERT INTO companies (id, company_code, company_name, legal_name, base_currency, status, is_default)
           SELECT 1,'DEFAULT','Default Company','Default Company (pre-consolidation books)','AED','active',1
           WHERE NOT EXISTS (SELECT 1 FROM companies WHERE id=1)");
if (!(int)$db->query("SELECT COUNT(*) FROM company_groups")->fetchColumn()) {
    $db->exec("INSERT INTO company_groups (group_code, group_name, parent_company_id, base_currency, status)
               VALUES ('GRP01','Main Group',1,'AED','active')");
    $gid = (int)$db->lastInsertId();
    $db->exec("INSERT INTO company_relationships (group_id,parent_company_id,child_company_id,ownership_percentage,effective_from,relationship_type,status)
               VALUES ($gid,1,1,100.000,CURDATE(),'parent','active')");
}
// IC account config for default company: reuse AR/AP-like accounts by nature fallback
if (!(int)$db->query("SELECT COUNT(*) FROM intercompany_accounts")->fetchColumn()) {
    $pick = function($nature,$like) use ($db) {
        $s=$db->prepare("SELECT coa.id FROM chart_of_accounts coa JOIN account_groups g ON g.id=coa.group_id
                         WHERE g.nature=? AND coa.status='active' AND coa.account_name LIKE ? LIMIT 1");
        $s->execute([$nature,"%$like%"]); $id=(int)$s->fetchColumn();
        if (!$id && !$like) return 0;
        if (!$id) { $s2=$db->prepare("SELECT coa.id FROM chart_of_accounts coa JOIN account_groups g ON g.id=coa.group_id WHERE g.nature=? AND coa.status='active' LIMIT 1"); $s2->execute([$nature]); $id=(int)$s2->fetchColumn(); }
        return $id;
    };
    $map = ['receivable'=>$pick('asset','receiv'),'payable'=>$pick('liability','payab'),
            'revenue'=>$pick('income',''),'expense'=>$pick('expense','')];
    foreach ($map as $type=>$acct) {
        if ($acct) $db->prepare("INSERT IGNORE INTO intercompany_accounts (company_id,ic_type,account_id) VALUES (1,?,?)")
                      ->execute([$type,$acct]);
    }
    echo "   ✓ default company IC accounts mapped\n";
}
echo "\n=== F13 setup complete ===\n";
