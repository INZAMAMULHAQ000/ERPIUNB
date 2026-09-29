<?php
$pdo = new PDO('mysql:host=localhost;dbname=erp_db;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
echo "== invoice_items columns ==\n";
foreach ($pdo->query("SHOW COLUMNS FROM invoice_items") as $c) { echo "  {$c['Field']} : {$c['Type']}\n"; }

echo "\n== vendor_bills columns ==\n";
foreach ($pdo->query("SHOW COLUMNS FROM vendor_bills") as $c) { echo "  {$c['Field']} : {$c['Type']}\n"; }

echo "\n== vendor_bill_items columns ==\n";
foreach ($pdo->query("SHOW COLUMNS FROM vendor_bill_items") as $c) { echo "  {$c['Field']} : {$c['Type']}\n"; }

echo "\n== all vendor_bills (last 10) ==\n";
foreach ($pdo->query("SELECT id, vendor_id, bill_date, total_amount, tax_amount, status, notes FROM vendor_bills ORDER BY id DESC LIMIT 10") as $r) { echo "  " . json_encode($r) . "\n"; }

echo "\n== all tax_transactions (last 15) ==\n";
foreach ($pdo->query("SELECT id, source_module, source_record_id, tax_direction, tax_status, taxable_amount, tax_amount, tax_rate_id, tax_rate, exemption_id, status, created_at FROM tax_transactions ORDER BY id DESC LIMIT 15") as $r) { echo "  " . json_encode($r) . "\n"; }

echo "\n== customers list (for client_id) ==\n";
foreach ($pdo->query("SELECT id, customer_name, company_name FROM customers ORDER BY id LIMIT 8") as $r) { echo "  " . json_encode($r) . "\n"; }

echo "\n== vendors list ==\n";
foreach ($pdo->query("SELECT id, vendor_name, company_name FROM vendors ORDER BY id LIMIT 8") as $r) { echo "  " . json_encode($r) . "\n"; }

echo "\n== tax_transactions leftover check ==\n";
foreach ($pdo->query("SELECT source_module, source_record_id, tax_direction, COUNT(*) c, SUM(tax_amount) s FROM tax_transactions WHERE status='active' GROUP BY source_module, source_record_id, tax_direction ORDER BY source_module, source_record_id") as $r) { echo "  " . json_encode($r) . "\n"; }
