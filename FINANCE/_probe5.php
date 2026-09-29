<?php
$pdo = new PDO('mysql:host=localhost;dbname=erp_db;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
echo "== invoice_items ==\n";
foreach ($pdo->query("SHOW COLUMNS FROM invoice_items") as $c) { echo $c['Field'] . " : " . $c['Type'] . "\n"; }
echo "\n== vendor_bills columns ==\n";
foreach ($pdo->query("SHOW COLUMNS FROM vendor_bills") as $c) { echo $c['Field'] . " : " . $c['Type'] . "\n"; }
echo "\n== vendor_bill_items ==\n";
foreach ($pdo->query("SHOW COLUMNS FROM vendor_bill_items") as $c) { echo $c['Field'] . " : " . $c['Type'] . "\n"; }
echo "\n== credit_notes ==\n";
foreach ($pdo->query("SHOW COLUMNS FROM credit_notes") as $c) { echo $c['Field'] . " : " . $c['Type'] . "\n"; }
echo "\n== voucher_types active ==\n";
foreach ($pdo->query("SELECT id, voucher_name, status FROM voucher_types ORDER BY id") as $r) { echo json_encode($r) . "\n"; }
echo "\n== journal_entries ==\n";
foreach ($pdo->query("SHOW COLUMNS FROM journal_entries") as $c) { echo $c['Field'] . " : " . $c['Type'] . "\n"; }
echo "\n== general_ledger ==\n";
foreach ($pdo->query("SHOW COLUMNS FROM general_ledger") as $c) { echo $c['Field'] . " : " . $c['Type'] . "\n"; }