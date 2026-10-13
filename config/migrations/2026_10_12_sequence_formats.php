<?php
/**
 * Document sequence formats + per-period counters.
 *
 * Existing numbers are shaped differently per module (JV-2026-2027-000001 vs
 * INV202610-0001 vs INV20260803-001). Rather than change what a document number
 * looks like, the shape moves into the database as a template, and the counter
 * becomes per (company, type, period) so a monthly restart stays correct while
 * allocation becomes atomic.
 *
 * Idempotent.
 *   php config/migrations/2026_10_12_sequence_formats.php
 */

require_once __DIR__ . '/../database.php';
$db = getDB();
$DB = DB_NAME;

function col($db, $table, $col, $def) {
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=? AND table_name=? AND column_name=?");
    $s->execute([DB_NAME, $table, $col]);
    if ($s->fetchColumn()) { echo "  = $table.$col exists\n"; return; }
    $db->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
    echo "  + $table.$col\n";
}

echo "== Sequence formats migration ==\n\n";

col($db, 'document_sequences', 'period_key', "VARCHAR(20) NOT NULL DEFAULT '' AFTER doc_type");
col($db, 'document_sequences', 'format', "VARCHAR(80) NULL AFTER date_format");

// Replace the two-part unique key with a three-part one so a period counter can
// coexist with the global row for the same type.
$hasOld = $db->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema='$DB' AND table_name='document_sequences' AND index_name='uq_docseq'")->fetchColumn();
$hasNew = $db->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema='$DB' AND table_name='document_sequences' AND index_name='uq_docseq_period'")->fetchColumn();

// Add the replacement before dropping the old one: the company_id foreign key
// needs a leftmost index on that column, so the old key cannot be dropped first.
if (!$hasNew) {
    $db->exec("ALTER TABLE `document_sequences` ADD UNIQUE KEY `uq_docseq_period` (`company_id`,`doc_type`,`period_key`)");
    echo "  + unique key (company_id, doc_type, period_key)\n";
    $hasNew = 1;
}
if ($hasOld && $hasNew) {
    $db->exec("ALTER TABLE `document_sequences` DROP INDEX `uq_docseq`");
    echo "  - dropped old unique key (company_id, doc_type)\n";
}

// Exact shape each module currently issues, so cutover changes nothing visible.
// Only document types actually routed through nextDocumentNumber() appear here:
// procurement (PR/PO/GRN/VB) and journal vouchers keep their own atomic
// sequences (procurement_sequences / voucher_types.voucher_prefix).
// columns: prefix, padding, date_format ('' = single counter), format
$specs = [
    'customer_invoice' => ['INV',   3, 'Ymd', '{prefix}{period}-{seq}'],   // INV20260812-007
    'credit_note'      => ['CN',    3, 'Ym',  '{prefix}{period}-{seq}'],   // CN202608-001
    'debit_note'       => ['DN',    3, 'Ymd', '{prefix}{period}-{seq}'],   // DN20260812-001
    'receipt'          => ['RCPT',  3, 'Ym',  '{prefix}{period}-{seq}'],   // RCPT202608-001
    'invoice_legacy'   => ['INV',   3, 'Y',   '{prefix}-{period}-{seq}'],  // INV-2026-008
    'customer'         => ['CL',    4, 'Y',   '{prefix}{period}{seq}'],    // CL20260002
    'vendor'           => ['VND',   4, '',    '{prefix}{seq}'],
];

// These types are handled elsewhere — remove any stale counter so the numbering
// screen does not offer a knob that nothing reads.
$db->prepare("DELETE FROM document_sequences WHERE doc_type IN ('journal_entry','purchase_order','purchase_request','goods_receipt','vendor_bill')")
   ->execute();
echo "  - removed counters for types with their own sequence engine\n";

$companyIds = $db->query("SELECT id FROM companies WHERE deleted_at IS NULL ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
$ins = $db->prepare("
    INSERT INTO document_sequences (company_id, doc_type, period_key, prefix, padding, date_format, format, next_number)
    VALUES (?,?,?,?,?,?,?,1)
    ON DUPLICATE KEY UPDATE
        prefix = VALUES(prefix), padding = VALUES(padding),
        date_format = VALUES(date_format), format = VALUES(format)
");
$applied = 0;
foreach ($companyIds as $cid) {
    foreach ($specs as $type => [$prefix, $padding, $df, $fmt]) {
        // Keep a prefix the operator has already customised on the company master.
        $p = $prefix;
        if ($type === 'customer_invoice' || $type === 'invoice_legacy') {
            $p = $db->query("SELECT COALESCE(NULLIF(invoice_prefix,''),'INV') FROM companies WHERE id = " . (int) $cid)->fetchColumn() ?: $prefix;
        }
        $ins->execute([$cid, $type, '', $p, $padding, $df, $fmt]);
        $applied++;
    }
}
echo "  ~ applied $applied sequence templates (" . count($specs) . " types x " . count($companyIds) . " companies)\n";

echo "\n== Done ==\n";

// Collision floors. For a period-bearing type the counter that matters is the
// row for the CURRENT period, so seed that from the highest suffix already used
// within the same prefix+period — never reuse a number that is on a document.
$floors = [
    'customer_invoice' => ['customer_invoices', 'invoice_number'],
    'receipt'          => ['customer_receipts', 'receipt_number'],
    'credit_note'      => ['credit_notes',      'credit_note_number'],
    'debit_note'       => ['debit_notes',       'debit_note_number'],
    'invoice_legacy'   => ['invoices',          'invoice_number'],
    'customer'         => ['clients',           'client_code'],
];
$companyIds = array_map('intval', $db->query("SELECT id FROM companies WHERE deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN));

foreach ($floors as $type => [$table, $col]) {
    if (!$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='$DB' AND table_name='$table' AND column_name='$col'")->fetchColumn()) {
        echo "  - floor skipped for $type ($table.$col absent)\n"; continue;
    }
    [$pfx, $pad, $df] = [$specs[$type][0], $specs[$type][1], $specs[$type][2]];
    foreach ($companyIds as $cid) {
        $prefix = $pfx;
        if ($type === 'customer_invoice') {
            $prefix = $db->query("SELECT COALESCE(NULLIF(invoice_prefix,''),'INV') FROM companies WHERE id = $cid")->fetchColumn() ?: $pfx;
        }
        $period = $df ? date($df) : '';

        // Build the literal portion that precedes {seq} from the format itself, so
        // dashed shapes like INV-2026-008 are matched (prefix+period alone misses them).
        $literal = str_replace(
            ['{prefix}', '{period}', '{year}', '{fy}'],
            [$prefix, $period, date('Y'), date('Y')],
            (string) strstr($specs[$type][3] . '{seq}', '{seq}', true)
        );
        $like = $literal . '%';

        $s = $db->prepare("SELECT COALESCE(MAX(CAST(RIGHT(`$col`, ?) AS UNSIGNED)), 0) FROM `$table` WHERE `$col` LIKE ?");
        $s->execute([$pad, $like]);
        $maxSuffix = (int) $s->fetchColumn();
        $floor = $maxSuffix + 1;

        // Ensure the period row exists, then raise its counter.
        $db->prepare("
            INSERT INTO document_sequences (company_id, doc_type, period_key, prefix, padding, date_format, format, next_number)
            VALUES (?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE next_number = GREATEST(next_number, VALUES(next_number)),
                                    prefix = VALUES(prefix), padding = VALUES(padding),
                                    date_format = VALUES(date_format), format = VALUES(format)
        ")->execute([$cid, $type, $period, $prefix, $pad, $df, $specs[$type][3], $floor]);

        echo "  ~ $type co=$cid period='$period' floor $floor (last used $maxSuffix)\n";
    }
}

foreach ($db->query("SELECT doc_type, format, date_format FROM document_sequences GROUP BY doc_type, format, date_format ORDER BY doc_type") as $r) {
    echo "  {$r["doc_type"]}: {$r["format"]}" . ($r['date_format'] ? "  (period={$r["date_format"]})" : '') . "\n";
}
