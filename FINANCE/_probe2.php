<?php
$pdo = new PDO('mysql:host=localhost;dbname=erp_db', 'root', '');
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
foreach (['tax_exemptions','tax_transactions','tax_periods'] as $t) {
  echo "== $t ==\n";
  foreach ($pdo->query("SHOW COLUMNS FROM $t") as $c) { echo $c['Field'] . " : " . $c['Type'] . "\n"; }
  echo "\n";
}
echo "== tax_transactions sample ==\n";
$rows = $pdo->query("SELECT * FROM tax_transactions ORDER BY id DESC LIMIT 3")->fetchAll();
echo json_encode($rows, JSON_PRETTY_PRINT) . "\n";