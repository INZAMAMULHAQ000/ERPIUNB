<?php
/**
 * Contract terms migration — closes two gaps found after the contractor build:
 *
 *  1. `employees` recorded only `joining_date`. In the UAE the employment
 *     itself is a fixed-term instrument (MOHRE contract), so an employee needs
 *     a contract number plus start and end dates and a slot for the signed
 *     contract scan — otherwise expiry reporting covers visas but not the
 *     employment contract. The expiry lives on the employee row, like every
 *     other document date in this schema, so no column is added to
 *     employee_documents.
 *  2. `contractor_documents` could technically link a row to a contract via
 *     `contract_id`, but nothing in the vocabulary described a contract
 *     document, so signed agreements, amendments, renewals and termination
 *     letters had no type to file under.
 *
 * Idempotent — safe to re-run.
 *   php config/migrations/2026_10_14_employee_contract_terms.php
 */

require_once __DIR__ . '/../database.php';

$db = getDB();
$DB = DB_NAME;
$added = 0;
$skipped = 0;

function hasColumn($db, $table, $col) {
    global $DB;
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?");
    $s->execute([$DB, $table, $col]);
    return (bool) $s->fetchColumn();
}

function addColumn($db, $table, $col, $definition, $after = null) {
    global $added, $skipped;
    if (hasColumn($db, $table, $col)) { $skipped++; return; }
    $pos = $after ? " AFTER `$after`" : '';
    $db->exec("ALTER TABLE `$table` ADD COLUMN `$col` $definition$pos");
    $added++;
    echo "  + $table.$col\n";
}

function hasIndex($db, $table, $name) {
    global $DB;
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?");
    $s->execute([$DB, $table, $name]);
    return (bool) $s->fetchColumn();
}

function addIndex($db, $table, $name, $cols) {
    if (hasIndex($db, $table, $name)) return;
    $db->exec("ALTER TABLE `$table` ADD INDEX `$name` ($cols)");
    echo "  i $table.$name\n";
}

echo "== Employee contract terms + contract document vocabulary ==\n";

echo "\n-- employees: the employment instrument --\n";
addColumn($db, 'employees', 'contract_number',     "VARCHAR(50) NULL", 'joining_date');
addColumn($db, 'employees', 'contract_start_date', "DATE NULL", 'contract_number');
addColumn($db, 'employees', 'contract_end_date',   "DATE NULL", 'contract_start_date');
addIndex($db, 'employees', 'idx_employee_contract_number', '`contract_number`');
addIndex($db, 'employees', 'idx_employee_contract_end',    '`contract_end_date`');

echo "\n-- contractor.document_type: contract paperwork vocabulary --\n";

// contractor_documents already carries contract_id with a supporting index, so
// only the classification values are new.
$valIns = $db->prepare("
    INSERT IGNORE INTO lookup_values (type_key, value_key, value_label, is_active, sort_order, created_at)
    VALUES (?, ?, ?, 1, ?, NOW())");

$contractDocTypes = [
    'contract_copy'      => ['Signed Contract',            80],
    'sow'                => ['Statement of Work (SOW)',     90],
    'amendment'          => ['Contract Amendment',         100],
    'renewal_letter'     => ['Renewal Letter',             110],
    'termination_letter' => ['Termination Letter',         120],
];
foreach ($contractDocTypes as $key => [$label, $order]) {
    $valIns->execute(['contractor.document_type', $key, $label, $order]);
    echo ($valIns->rowCount() ? '  + ' : '  = ') . "contractor.document_type.$key\n";
}

echo "\n== Done: $added columns added, $skipped already present ==\n";
