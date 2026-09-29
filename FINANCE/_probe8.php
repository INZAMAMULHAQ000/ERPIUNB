<?php
$pdo = new PDO('mysql:host=localhost;dbname=erp_db;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
echo "== vendors columns ==\n";
foreach ($pdo->query("SHOW COLUMNS FROM vendors") as $c) { echo "  {$c['Field']} : {$c['Type']}\n"; }
echo "\n== vendors rows ==\n";
foreach ($pdo->query("SELECT * FROM vendors ORDER BY id LIMIT 5") as $r) { echo "  " . json_encode($r) . "\n"; }
echo "\n== account_types ==\n";
foreach ($pdo->query("SELECT * FROM account_types ORDER BY id") as $r) { echo "  " . json_encode($r) . "\n"; }