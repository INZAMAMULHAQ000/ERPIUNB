<?php
require 'config/database.php';
$db = getDB();
$menu = $db->query("SELECT label, route, permission FROM sidebar_menu WHERE route LIKE 'modules/procurement/receipts%' ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
echo 'Receipts menu entries: ' . count($menu) . PHP_EOL;
foreach ($menu as $m) echo '  ' . $m['label'] . ' -> ' . $m['route'] . ' (perm: ' . $m['permission'] . ')' . PHP_EOL;
