<?php
/**
 * F12 — Accounting Period & Year-End Closing — Setup Script
 * Run once: php setup_f12_period_close.php  (CLI) or via browser.
 * Safe to re-run: idempotent inserts only.
 */

require_once __DIR__ . '/config/database.php';

$db = getDB();

echo "=== F12 Period Close Setup ===\n\n";

// 1. Run migration
echo "1. Running migration 017_period_close_f12.sql...\n";
$sql = file_get_contents(__DIR__ . '/config/migrations/017_period_close_f12.sql');
$statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql)));
foreach ($statements as $q) {
    if ($q === '') continue;
    // skip pure comment blocks
    $first = strtolower(preg_replace('/^[\s\-\/]+/', '', $q));
    if (strpos($first, 'alter') !== 0 && strpos($first, 'create') !== 0 && strpos($first, 'insert') !== 0) continue;
    try { $db->exec($q); } catch (Exception $e) {
        // ALTERs may fail on re-run (duplicate column) — tolerate
        echo "   (skipped: " . substr($e->getMessage(), 0, 60) . ")\n";
    }
}
echo "   ✓ Migration applied\n";

// 2. Permissions (module.name convention)
echo "2. Seeding F12 permissions...\n";
$permissions = [
    ['view',    'period'],
    ['create',  'period'],
    ['close',   'period'],
    ['reopen',  'period'],
    ['close',   'year'],
    ['reopen',  'year'],
    ['adjust',  'closing'],
    ['approve', 'closing'],
    ['report',  'closing'],
];
$stmt = $db->prepare("INSERT IGNORE INTO permissions (name, module) VALUES (?, ?)");
foreach ($permissions as $p) $stmt->execute($p);
echo "   ✓ " . count($permissions) . " permissions ensured\n";

// Grant all to Super Admin (role id=1), same as previous phases
$db->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
    SELECT 1, id FROM permissions WHERE module IN ('period','year','closing')");
echo "   ✓ Granted to Super Admin role\n";

// 3. Sidebar menu — parent + children under Finance section
echo "3. Seeding sidebar menu...\n";
$menu = [
    // parent
    [null, 'Period & Year-End Close', 'fas fa-calendar-check', 'modules/finance/period_close/index.php', 'period.view', 30],
    ['financial_years', 'Financial Years',      'fas fa-calendar-alt',   'modules/finance/period_close/financial_years/index.php', 'period.view', 1],
    ['periods',         'Accounting Periods',   'fas fa-table-list',     'modules/finance/period_close/periods/index.php',         'period.view', 2],
    ['closing',         'Year-End Close',       'fas fa-flag-checkered', 'modules/finance/period_close/closing/index.php',          'year.close', 3],
    ['checklist',       'Closing Checklist',    'fas fa-list-check',     'modules/finance/period_close/checklist/index.php',       'period.view', 4],
    ['reconciliation',  'Reconciliation Status','fas fa-scale-balanced', 'modules/finance/period_close/reconciliation/index.php',  'period.view', 5],
    ['adjustments',     'Closing Adjustments',  'fas fa-pen-to-square',  'modules/finance/period_close/adjustments/index.php',     'closing.adjust', 6],
    ['opening_balances','Opening Balances (F12)','fas fa-forward',       'modules/finance/period_close/opening_balances/index.php','period.view', 7],
    ['history',         'Closing History',      'fas fa-clock-rotate-left','modules/finance/period_close/history/index.php',       'period.view', 8],
    ['reports',         'Closing Reports',      'fas fa-file-invoice',   'modules/finance/period_close/reports/index.php',         'closing.report', 9],
];

function f12_menu_item($db, $parentLabel, $label, $icon, $route, $permission, $sortOrder)
{
    $parentId = null;
    if ($parentLabel !== null) {
        $s = $db->prepare("SELECT id FROM sidebar_menu WHERE label = ? AND section = 'Finance' LIMIT 1");
        $s->execute([$parentLabel]);
        $parentId = $s->fetchColumn();
        if (!$parentId) return; // parent missing — skip child silently
    }
    $chk = $db->prepare("SELECT id FROM sidebar_menu WHERE label = ? AND section = 'Finance'");
    $chk->execute([$label]);
    $existingId = $chk->fetchColumn();

    if ($existingId) {
        $u = $db->prepare("UPDATE sidebar_menu SET icon=?, route=?, permission=?, sort_order=? WHERE id=?");
        $u->execute([$icon, $route, $permission, $sortOrder, $existingId]);
        return;
    }
    $i = $db->prepare("
        INSERT INTO sidebar_menu (parent_id, section, label, icon, route, permission, sort_order, is_active)
        VALUES (?, 'Finance', ?, ?, ?, ?, ?, 1)
    ");
    $i->execute([$parentId, $label, $icon, $route, $permission, $sortOrder]);
}

foreach ($menu as $m) f12_menu_item($db, $m[0], $m[1], $m[2], $m[3], $m[4], $m[5]);
echo "   ✓ Menu items ensured\n";

// 4. Checklist templates come from the migration; ensure instance rows for active FY exist lazily in UI.

// 5. Ensure periods exist for every FY
echo "4. Ensuring monthly periods for all financial years...\n";
require_once __DIR__ . '/modules/finance/period_close/PeriodCloseService.php';
$fys = $db->query("SELECT id FROM financial_years")->fetchAll(PDO::FETCH_COLUMN);
foreach ($fys as $fyId) {
    $r = PeriodCloseService::ensurePeriodsExist($db, (int)$fyId);
    if ($r['created']) echo "   + FY #$fyId: {$r['created']} periods generated\n";
}
echo "   ✓ Periods verified\n";

echo "\n=== F12 setup complete ===\n";
