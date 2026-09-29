<?php
session_start();
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../../../helpers/post.php';
require_once '../../DepreciationService.php';

if (!hasPermission('fixedasset.depreciate')) { die('Access Denied'); }

if (isset($_POST['depreciation_form'])) {
    $processed = $deprProcessor->processDepreciationProcessing($_POST['hidden'], $_POST['asset_ids']);
    echo json_encode[$processed];
}
?>
<div class="bg-white rounded-lg shadow break-words">
    <p class="text-lg font-semibold mb-4">This page implements depreciation processing functionality that can be extended to handle periodic depreciation posting.</p>
    <p class="text-sm text-gray-500">Deeper implementation of depreciation scheduling and processing is available in the progressive implementation path.</p>
</div>