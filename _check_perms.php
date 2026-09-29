<?php
require 'config/database.php';
$db = getDB();
$perms = $db->query("SELECT name, module FROM permissions WHERE module='procurement' AND name LIKE 'receipt.%' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
echo 'Receipt permissions: ' . count($perms) . PHP_EOL;
foreach ($perms as $p) echo '  - ' . $p['name'] . PHP_EOL;
