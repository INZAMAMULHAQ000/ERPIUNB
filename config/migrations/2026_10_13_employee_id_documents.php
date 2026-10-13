<?php
/**
 * Employee Master migration — widens `employees` with the UAE identification
 * set (nationality, passport, visa, Emirates ID + their expiry dates) and a
 * profile photo path.
 *
 * `employees` is widened in place: attendance, leave, payroll and the
 * reporting-manager self-FK all reference it, so the master is not forked.
 * The scanned copies (passport / visa / Emirates ID) live in the existing
 * `employee_documents` table, keyed by document_type, so no DDL is needed
 * there — only a composite index for the per-slot lookup.
 *
 * Idempotent — safe to re-run.
 *   php config/migrations/2026_10_13_employee_id_documents.php
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

echo "== Employee Master migration ==\n";

echo "\n-- employees: nationality & passport --\n";
addColumn($db, 'employees', 'nationality',         "VARCHAR(100) NULL", 'marital_status');
addColumn($db, 'employees', 'passport_number',     "VARCHAR(50) NULL", 'nationality');
addColumn($db, 'employees', 'passport_expiry',     "DATE NULL", 'passport_number');

echo "\n-- employees: visa --\n";
addColumn($db, 'employees', 'visa_type',           "VARCHAR(30) NULL", 'passport_expiry');
addColumn($db, 'employees', 'visa_number',         "VARCHAR(50) NULL", 'visa_type');
addColumn($db, 'employees', 'visa_expiry',         "DATE NULL", 'visa_number');

echo "\n-- employees: Emirates ID --\n";
addColumn($db, 'employees', 'emirates_id_number',  "VARCHAR(20) NULL", 'visa_expiry');
addColumn($db, 'employees', 'emirates_id_expiry',  "DATE NULL", 'emirates_id_number');

echo "\n-- employees: profile photo --\n";
addColumn($db, 'employees', 'photo_path',          "VARCHAR(500) NULL", 'user_id');

echo "\n-- indexes for master lookups --\n";
addIndex($db, 'employees', 'idx_passport_number',    '`passport_number`');
addIndex($db, 'employees', 'idx_emirates_id_number', '`emirates_id_number`');
addIndex($db, 'employee_documents', 'idx_employee_type', '`employee_id`, `document_type`');

echo "\n== Done: $added columns added, $skipped already present ==\n";
