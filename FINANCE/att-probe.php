<?php
require __DIR__ . '/../config/database.php';
$pdo = getDB();
$r = $pdo->query("SELECT attendance_date, ROUND(SUM(status='present')/COUNT(*)*100,1) AS pct, COUNT(*) cnt FROM attendance WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY attendance_date ORDER BY attendance_date")->fetchAll();
echo "rows: " . count($r) . PHP_EOL;
echo json_encode($r) . PHP_EOL;
