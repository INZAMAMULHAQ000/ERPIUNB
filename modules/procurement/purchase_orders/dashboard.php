<?php
/** P3 — Purchase Order Dashboard */
$pageTitle = 'PO Dashboard';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';
require_once '../../finance/helpers/finance_helpers.php';
require_once __DIR__ . '/../ProcurementService.php';
require_once __DIR__ . '/../PoService.php';

if (!hasPermission('procurement.po.view')) die('Access Denied');
$db = getDB();
$stats = PoService::dashboardStats($db);

$cards = [
    ['Draft POs',           (int)($stats['draft'] ?? 0),       'gray',  'fa-file'],
    ['Pending Approval',    (int)($stats['pending'] ?? 0),     'yellow','fa-hourglass-half'],
    ['Approved POs',        (int)($stats['approved'] ?? 0),    'green', 'fa-check-circle'],
    ['Sent POs',            (int)($stats['sent'] ?? 0),        'blue',  'fa-paper-plane'],
    ['Acknowledged',        (int)($stats['ack'] ?? 0),         'indigo','fa-handshake'],
    ['Cancelled',           (int)($stats['cancelled'] ?? 0),   'red',   'fa-ban'],
];
?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Purchase Orders</h1>
        <p class="text-gray-600">Award → PO → Approval → Send → Acknowledge</p>
    </div>
    <?php if (hasPermission('procurement.po.create')): ?>
    <a href="create.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
        <i class="fas fa-plus mr-1"></i>New PO from Award
    </a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
    <?php foreach ($cards as $c): ?>
    <div class="bg-white rounded-lg shadow p-3 text-center border-t-4 border-<?= $c[2] ?>-500">
        <i class="fas <?= $c[3] ?> text-gray-300 mb-1"></i>
        <p class="text-[11px] text-gray-500 uppercase leading-tight"><?= $c[0] ?></p>
        <p class="font-bold text-xl"><?= $c[1] ?></p>
    </div>
    <?php endforeach; ?>
    <div class="bg-white rounded-lg shadow p-3 col-span-2 border-t-4 border-emerald-500">
        <p class="text-[11px] text-gray-500 uppercase">Open PO Value / This Month</p>
        <p class="font-bold"><?= formatMoney((float)($stats['open_value'] ?? 0)) ?></p>
        <p class="text-sm text-blue-600">This Month: <?= formatMoney((float)($stats['month_value'] ?? 0)) ?></p>
    </div>
</div>

<div class="bg-white rounded-lg shadow p-5 max-w-2xl text-sm text-gray-600">
    <b>Workflow:</b> Award → Create PO → Submit → Approve → Send to Vendor → Acknowledge<br>
    POs carry full traceability: PR → RFQ → Quotation → Award → PO.<br>
    Use the buttons above or <a href="list.php" class="text-blue-600 hover:underline">view all POs</a>.
</div>

<?php require_once '../../../includes/footer.php'; ?>
