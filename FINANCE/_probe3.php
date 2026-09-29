<?php
$pdo = new PDO('mysql:host=localhost;dbname=erp_db', 'root', '');
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

echo "== COA COLUMNS ==\n";
foreach ($pdo->query("SHOW COLUMNS FROM chart_of_accounts") as $c) { echo $c['Field'] . " : " . $c['Type'] . "\n"; }
echo "\n== COA (small) ==\n";
foreach ($pdo->query("SELECT id, account_code, account_name, group_id, type_id, status FROM chart_of_accounts ORDER BY id") as $r) {
    echo $r['id'] . " | " . $r['account_code'] . " | " . $r['account_name'] . " | grp=" . $r['group_id'] . " | type=" . $r['type_id'] . " | " . $r['status'] . "\n";
}
echo "\n== ACCOUNT GROUPS ==\n";
foreach ($pdo->query("SHOW COLUMNS FROM account_groups") as $c) { echo $c['Field'] . " : " . $c['Type'] . "\n"; }
echo "---\n";
foreach ($pdo->query("SELECT * FROM account_groups ORDER BY id") as $r) {
    echo json_encode($r) . "\n";
}
echo "\n== VOUCHER TYPES ==\n";
foreach ($pdo->query("SELECT id, voucher_name FROM voucher_types ORDER BY id") as $r) { echo $r['id'] . " " . $r['voucher_name'] . "\n"; }