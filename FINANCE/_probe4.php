<?php
$pdo = new PDO('mysql:host=localhost;dbname=erp_db;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "== settings keys (tax_%, finance_%) ==\n";
foreach ($pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'tax\\_%' OR setting_key LIKE 'finance\\_%' ORDER BY setting_key") as $r) {
    echo $r['setting_key'] . " = " . $r['setting_value'] . "\n";
}

echo "\n== settings table columns ==\n";
foreach ($pdo->query("SHOW COLUMNS FROM settings") as $c) { echo $c['Field'] . " : " . $c['Type'] . "\n"; }

echo "\n== financial years ==\n";
foreach ($pdo->query("SELECT * FROM financial_years") as $r) { echo json_encode($r) . "\n"; }

echo "\n== tax_rates (all) ==\n";
foreach ($pdo->query("SELECT id, tax_type_id, rate_name, rate_percentage, is_default, status, effective_from, effective_to FROM tax_rates ORDER BY id") as $r) { echo json_encode($r) . "\n"; }

echo "\n== tax_periods ==\n";
foreach ($pdo->query("SELECT id, tax_type_id, period_name, start_date, end_date, status FROM tax_periods ORDER BY id") as $r) { echo json_encode($r) . "\n"; }

echo "\n== tax_exemptions ==\n";
foreach ($pdo->query("SELECT id, exemption_code, name, reason, status FROM tax_exemptions") as $r) { echo json_encode($r) . "\n"; }

echo "\n== clients ==\n";
foreach ($pdo->query("SELECT id, client_code, company_name, status FROM clients ORDER BY id") as $r) { echo json_encode($r) . "\n"; }

echo "\n== vendors ==\n";
foreach ($pdo->query("SELECT id, vendor_code, vendor_name, status, default_expense_account_id, default_payable_account_id FROM vendors ORDER BY id") as $r) { echo json_encode($r) . "\n"; }

echo "\n== users ==\n";
foreach ($pdo->query("SELECT id, username, name FROM users ORDER BY id LIMIT 5") as $r) { echo json_encode($r) . "\n"; }