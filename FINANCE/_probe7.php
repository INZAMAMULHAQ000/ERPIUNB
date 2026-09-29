<?php
$pdo = new PDO('mysql:host=localhost;dbname=erp_db;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
echo "== tables matching vendor/supplier/client ==\n";
foreach ($pdo->query("SHOW TABLES LIKE '%vendor%'") as $r) { echo "  " . json_encode($r) . "\n"; }
foreach ($pdo->query("SHOW TABLES LIKE '%supplier%'") as $r) { echo "  " . json_encode($r) . "\n"; }
foreach ($pdo->query("SHOW TABLES LIKE '%client%'") as $r) { echo "  " . json_encode($r) . "\n"; }
foreach ($pdo->query("SHOW TABLES LIKE '%account_types%'") as $r) { echo "  " . json_encode($r) . "\n"; }

echo "\n== invoice_items for invoices 1..8 ==\n";
foreach ($pdo->query("SELECT id, invoice_id, item_name, description, quantity, unit_price, discount_percent, tax_percent, tax_type_id, tax_rate_id, tax_group_id, tax_status, taxable_amount, tax_exemption_id, tax_amount, line_total, sort_order, tax_percentage, total FROM invoice_items WHERE invoice_id <= 8 ORDER BY invoice_id") as $r) { echo "  " . json_encode($r) . "\n"; }

echo "\n== customers vs clients table sample ==\n";
foreach ($pdo->query("SELECT id, client_code, company_name FROM clients ORDER BY id LIMIT 8") as $r) { echo "  " . json_encode($r) . "\n"; }

echo "\n== bills count + last ==\n";
echo "  vendor_bills count: " . $pdo->query("SELECT COUNT(*) FROM vendor_bills")->fetchColumn() . "\n";