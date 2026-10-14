<?php
/**
 * Regenerates newdump/DATABASE_STRUCTURE.md — the full data dictionary of the
 * live database (every table, every column) used to track schema drift between
 * this workstation and production.
 *
 *   php newdump/generate_structure.php
 *
 * It reads information_schema only. Nothing is written to the database.
 */

require_once __DIR__ . '/../config/database.php';

$db = getDB();
$schema = DB_NAME;
$outPath = __DIR__ . '/DATABASE_STRUCTURE.md';

$tables = $db->query("
    SELECT table_name, table_comment, engine, table_collation
      FROM information_schema.tables
     WHERE table_schema = '$schema' AND table_type = 'BASE TABLE'
     ORDER BY table_name
")->fetchAll(PDO::FETCH_ASSOC);

$columns = $db->prepare("
    SELECT column_name, column_type, is_nullable, column_default, column_key, extra,
           character_set_name, numeric_precision, numeric_scale, column_comment
      FROM information_schema.columns
     WHERE table_schema = ? AND table_name = ?
     ORDER BY ordinal_position
");

// Foreign keys, so a column can show what it points at.
$fkRows = $db->query("
    SELECT k.table_name, k.column_name, r.referenced_table_name, r.referenced_column_name,
           c.delete_rule, c.update_rule, c.constraint_name
      FROM information_schema.key_column_usage k
      JOIN information_schema.referential_constraints c
        ON c.constraint_schema = k.constraint_schema
       AND c.constraint_name = k.constraint_name
      JOIN information_schema.key_column_usage r
        ON r.constraint_schema = k.constraint_schema
       AND r.constraint_name = k.constraint_name
       AND r.ordinal_position = k.ordinal_position
     WHERE k.table_schema = '$schema'
       AND k.referenced_table_name IS NOT NULL
       AND k.referenced_column_name IS NOT NULL
")->fetchAll(PDO::FETCH_ASSOC);

$fks = [];
foreach ($fkRows as $r) {
    $fks[$r['table_name']][strtolower($r['column_name'])] =
        $r['referenced_table_name'] . '.' . $r['referenced_column_name'] . ' (on delete ' . strtolower($r['delete_rule']) . ')';
}

// Primary keys that are composite are not marked by COLUMN_KEY alone.
$pkRows = $db->query("
    SELECT table_name, column_name FROM information_schema.statistics
     WHERE table_schema = '$schema' AND index_name = 'PRIMARY'
     ORDER BY table_name, seq_in_index
")->fetchAll(PDO::FETCH_ASSOC);
$pk = [];
foreach ($pkRows as $r) { $pk[$r['table_name']][] = $r['column_name']; }

$domains = [
    'Contractor master (new)' => ['contractors', 'contractor_'],
    'Employee & HR' => ['employees', 'employee_', 'attendance', 'leave', 'holiday', 'department', 'designation',
                        'payroll', 'timesheet', 'people', 'organisation', 'skill', 'recruitment', 'job_'],
    'Finance & General Ledger' => ['journal', 'general_ledger', 'chart_of_accounts', 'financial_year', 'period',
                                   'budget', 'tax', 'asset', 'depreciation', 'account', 'bank', 'cash_', 'invoice',
                                   'credit_note', 'debit_note', 'receipt', 'payment', 'expense', 'ledger', 'fiscal'],
    'Procurement & Vendors' => ['vendor', 'purchase', 'procurement', 'rfq', 'quotation', 'goods_receipt', 'requisition', 'supplier'],
    'Sales & CRM' => ['client', 'leads', 'deal', 'crm', 'opportunity'],
    'Projects & Tasks' => ['project', 'task', 'milestone'],
    'Inventory & Assets' => ['inventory', 'stock', 'warehouse', 'item'],
    'Helpdesk' => ['ticket', 'knowledge_base', 'sla'],
    'AI module' => ['ai_'],
    'Configuration & Lookups' => ['lookup', 'document_sequences', 'app_settings', 'currencies', 'currency_rates',
                                  'settings', 'template', 'widget', 'companies', 'company_'],
    'System & Security' => ['users', 'roles', 'permissions', 'user_roles', 'role_permissions', 'audit',
                            'user_companies', 'session', 'sidebar_menu', 'menu'],
];

function classify($table, array $domains)
{
    foreach ($domains as $name => $prefixes) {
        foreach ($prefixes as $p) {
            if ($table === $p || strpos($table, $p) === 0 || strpos($table, '_' . $p) !== false) {
                return $name;
            }
        }
    }
    return 'Other';
}

// Column counts in one query, reused by the header and the table index.
$colCounts = [];
foreach ($db->query("SELECT table_name, COUNT(*) AS n FROM information_schema.columns WHERE table_schema = '$schema' GROUP BY table_name") as $r) {
    $colCounts[$r['table_name']] = (int) $r['n'];
}
$totalColumns = array_sum($colCounts);

$rowsByTable = [];
$totalRows = 0;foreach ($tables as $t) {
    $tbl = $t['table_name'];
    try {
        $n = (int) $db->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
    } catch (PDOException $e) {
        $n = -1;
    }
    $rowsByTable[$tbl] = $n;
    if ($n > 0) { $totalRows += $n; }
}

$grouped = [];
foreach ($tables as $t) {
    $grouped[classify($t['table_name'], $domains)][] = $t;
}
$domainOrder = array_merge(array_keys($domains), ['Other']);

$md = [];
$md[] = '# ERP database structure — `' . $schema . '`';
$md[] = '';
$md[] = 'Full data dictionary generated **' . date('Y-m-d H:i:s') . '** from the live database by';
$md[] = '`php newdump/generate_structure.php`. Read straight from `information_schema` — this file is never';
$md[] = 'hand-maintained, so it always reflects the real schema.';
$md[] = '';
$md[] = '| | |';
$md[] = '|---|---|';
$md[] = '| Tables | **' . count($tables) . '** |';
$md[] = '| Columns | **' . number_format($totalColumns) . '** |';
$md[] = '| Total rows | **' . number_format($totalRows) . '** |';
$md[] = '| Engine | ' . implode(', ', array_unique(array_column($tables, 'engine'))) . ' |';
$md[] = '| Character set | ' . implode(', ', array_unique(array_map(fn($t) => explode('_', (string) $t['table_collation'])[0], $tables))) . ' |';
$md[] = '';
$md[] = 'Legend for the Key column below: `PK` primary, `UQ` unique, `IDX` indexed, `FK` foreign key (target shown in Notes).';
$md[] = '';

$md[] = '## Domains';
$md[] = '';
$md[] = '| Domain | Tables | Rows | |';
$md[] = '|---|---:|---:|---|';
foreach ($domainOrder as $d) {
    if (empty($grouped[$d])) { continue; }
    $list = $grouped[$d];
    $sum = array_sum(array_map(fn($t) => max(0, $rowsByTable[$t['table_name']]), $list));
    $anchor = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $d), '-'));
    $md[] = '| [' . $d . '](#' . $anchor . ') | ' . count($list) . ' | ' . number_format($sum) . ' |' . ($d === 'Contractor master (new)' ? ' **added by `2026_10_14_contractor_master.php`**' : '') . '|';
}
$md[] = '';
$md[] = '## Table index';
$md[] = '';
$md[] = '| Table | Cols | Rows | Domain |';
$md[] = '|---|---:|---:|---|';
foreach ($tables as $t) {
    $tbl = $t['table_name'];
    $cnt = $colCounts[$tbl] ?? 0;
    $rows = $rowsByTable[$tbl];
    $md[] = '| [`' . $tbl . '`](#' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $tbl)) . ') | ' . $cnt . ' | ' . ($rows < 0 ? '—' : number_format($rows)) . ' | ' . classify($tbl, $domains) . ' |';
}
$md[] = '';
$md[] = '---';

foreach ($domainOrder as $d) {
    if (empty($grouped[$d])) { continue; }
    $md[] = '';
    $md[] = '## ' . $d;
    $md[] = '';
    foreach ($grouped[$d] as $t) {
        $tbl = $t['table_name'];
        $cols = $db->query("SELECT * FROM information_schema.columns WHERE table_schema='$schema' AND table_name=" . $db->quote($tbl) . " ORDER BY ordinal_position")->fetchAll(PDO::FETCH_ASSOC);
        $rows = $rowsByTable[$tbl];
        $md[] = '';
        $md[] = '### ' . $tbl;
        $md[] = '';
        $md[] = count($cols) . ' columns · ' . ($rows < 0 ? 'row count unavailable' : number_format($rows) . ' rows')
               . ($t['table_comment'] ? ' · _' . $t['table_comment'] . '_' : '');
        $md[] = '';
        $md[] = '| # | Column | Type | Null | Key | Default | Notes |';
        $md[] = '|---:|---|---|---|---|---|---|';
        foreach ($cols as $i => $c) {
            $name = $c['COLUMN_NAME'];
            $key = '';
            if (in_array($name, $pk[$tbl] ?? [], true)) { $key = 'PK'; }
            if ($c['COLUMN_KEY'] === 'UNI') { $key = trim($key . ' UQ'); }
            elseif ($c['COLUMN_KEY'] === 'MUL' && $key === '') { $key = 'IDX'; }
            $fkNote = $fks[$tbl][strtolower($name)] ?? '';
            if ($fkNote !== '') { $key = trim($key . ' FK'); }
            $notes = $fkNote;
            $extra = trim((string) $c['EXTRA']);
            if ($extra !== '' && $extra !== 'auto_increment') { $notes .= ($notes ? ' · ' : '') . $extra; }
            if ($extra === 'auto_increment') { $notes .= ($notes ? ' · ' : '') . 'auto increment'; }
            if (trim((string) $c['COLUMN_COMMENT']) !== '') { $notes .= ($notes ? ' · ' : '') . $c['COLUMN_COMMENT']; }
            if ($c['CHARACTER_SET_NAME'] && strpos((string) $c['COLLATION_NAME'], 'bin') !== false) {
                $notes .= ($notes ? ' · ' : '') . 'binary collation';
            }
            $default = $c['COLUMN_DEFAULT'];
            $defaultOut = $default === null ? '*NULL*' : (strpos(strtolower($default), 'current_timestamp') !== false ? $default : '`' . $default . '`');
            $md[] = '| ' . ($i + 1) . ' | `' . $name . '` | `' . $c['COLUMN_TYPE'] . '` | '
                  . ($c['IS_NULLABLE'] === 'YES' ? 'yes' : '**no**') . ' | ' . ($key ?: '—') . ' | '
                  . $defaultOut . ' | ' . ($notes ?: '—') . ' |';
        }
    }
}

file_put_contents($outPath, implode("\n", $md) . "\n");

echo "Wrote $outPath\n";
echo 'Tables: ' . count($tables) . ', total rows: ' . number_format($totalRows)
   . ', size: ' . number_format(filesize($outPath)) . " bytes\n";
foreach ($domainOrder as $d) {
    if (!empty($grouped[$d])) {
        echo '  ' . str_pad($d, 28) . count($grouped[$d]) . " tables\n";
    }
}
