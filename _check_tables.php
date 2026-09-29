<?php
require 'config/database.php';
$db = getDB();
$tables = array('goods_receipts','goods_receipt_items','goods_receipt_inspections','goods_receipt_rejections','goods_receipt_damages','goods_receipt_batches','goods_receipt_serials','service_receipts','service_receipt_items','receipt_attachments','po_receiving_summary');
foreach ($tables as $t) {
    $r = $db->query('SHOW TABLES LIKE ' . $db->quote($t))->fetchColumn();
    echo $t . ': ' . ($r ? 'EXISTS' : 'MISSING') . PHP_EOL;
}
