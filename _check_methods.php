<?php
require 'modules/procurement/ReceiptService.php';
$methods = get_class_methods('ReceiptService');
$required = array('createGR','createSR','finalizeGR','finalizeSR','cancelGR','cancelSR','updateInspection','acceptGR','rejectGR','acceptSR','rejectSR','getGR','getSR','getGRItems','getSRItems','listReceipts','dashboardStats','updatePOReceivingStatus','nextGRNumber','nextSRNumber','validateGRHeader','validateGRLines');
foreach ($required as $m) {
    echo $m . ': ' . (in_array($m, $methods) ? 'EXISTS' : 'MISSING') . PHP_EOL;
}
