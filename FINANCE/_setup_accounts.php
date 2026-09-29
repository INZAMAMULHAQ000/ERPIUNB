<?php
$pdo = new PDO('mysql:host=localhost;dbname=erp_db;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "== Creating missing AR/AP/Revenue/SalesReturn accounts ==\n";

/* 1. Accounts Receivable (type_id=21 Receivable, group_id=14 AR) */
$pdo->prepare("
    INSERT INTO chart_of_accounts (account_code, account_name, group_id, type_id, opening_balance, opening_balance_type, allow_manual_entry, status)
    VALUES ('1200-AR', 'Accounts Receivable', 14, 21, 0.00, 'debit', 1, 'active')
")->execute();
$arId = $pdo->lastInsertId();
echo "AR account created id=$arId\n";

/* 2. Accounts Payable (type_id=20 Payable, group_id=21 AP) */
$pdo->prepare("
    INSERT INTO chart_of_accounts (account_code, account_name, group_id, type_id, opening_balance, opening_balance_type, allow_manual_entry, status)
    VALUES ('2000-AP', 'Accounts Payable', 21, 20, 0.00, 'credit', 1, 'active')
")->execute();
$apId = $pdo->lastInsertId();
echo "AP account created id=$apId\n";

/* 3. Sales Revenue already exists as id=11 (type=18) - verify */
$rev = $pdo->query("SELECT id, account_code FROM chart_of_accounts WHERE id=11")->fetch();
echo "Revenue account id={$rev['id']} code={$rev['account_code']}\n";

/* 4. Expense default - use 600-MKT (id=25) as the default expense account */
$exp = $pdo->query("SELECT id, account_code FROM chart_of_accounts WHERE id=25")->fetch();
echo "Default expense account id={$exp['id']} code={$exp['account_code']}\n";

/* 5. Sales Return (type_id=18 Revenue, group_id=40 Sales Revenue) */
$pdo->prepare("
    INSERT INTO chart_of_accounts (account_code, account_name, group_id, type_id, opening_balance, opening_balance_type, allow_manual_entry, status)
    VALUES ('4000-SR', 'Sales Returns & Allowances', 40, 18, 0.00, 'credit', 1, 'active')
")->execute();
$srId = $pdo->lastInsertId();
echo "Sales Return account created id=$srId\n";

/* 6. Wire finance settings */
$settings = [
    'finance_ar_account_id' => $arId,
    'finance_ap_account_id' => $apId,
    'finance_revenue_account_id' => $rev['id'],
    'finance_expense_account_id' => $exp['id'],
    'finance_sales_return_account_id' => $srId,
];

foreach ($settings as $k => $v) {
    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$v, $k]);
    echo "Settings $k = $v\n";
}

echo "\n== Verification ==\n";
foreach ($pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('finance_ar_account_id','finance_ap_account_id','finance_revenue_account_id','finance_expense_account_id','finance_sales_return_account_id','finance_expense_payable_account_id') ORDER BY setting_key") as $r) {
    echo $r['setting_key'] . " = " . $r['setting_value'] . "\n";
}

echo "\nDone.\n";