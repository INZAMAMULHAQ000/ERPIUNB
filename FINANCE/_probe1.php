<?php
$pdo = new PDO('mysql:host=localhost;dbname=erp_db', 'root', '');
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

echo "== SETTINGS (finance/tax) ==\n";
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN (
    'finance_ar_account_id','finance_revenue_account_id','finance_ap_account_id',
    'finance_expense_account_id','finance_sales_return_account_id','finance_expense_payable_account_id',
    'tax_input_tax_account_id','tax_output_tax_account_id','tax_adjustment_account_id') ORDER BY setting_key");
foreach ($stmt as $r) { echo $r['setting_key'] . " = " . $r['setting_value'] . "\n"; }

echo "\n== ACCOUNT TYPES ==\n";
foreach ($pdo->query('SELECT id, type_name FROM account_types ORDER BY id') as $r) { echo $r['id'] . " " . $r['type_name'] . "\n"; }

echo "\n== COA RECEIVABLE/PAYABLE/VENDOR/CUSTOMER/REVENUE ==\n";
foreach ($pdo->query("SELECT id, account_code, account_name, account_type_id, status FROM chart_of_accounts WHERE account_type_id IN (SELECT id FROM account_types WHERE type_name IN ('Receivable','Payable','Vendor','Customer','Revenue','Income')) ORDER BY id") as $r) {
    echo $r['id'] . " | " . $r['account_code'] . " | " . $r['account_name'] . " | type=" . $r['account_type_id'] . " | " . $r['status'] . "\n";
}