<?php
header('Content-Type: application/json');
require_once '../../includes/header.php';
require_once __DIR__ . '/InventoryEngine.php';
require_once '../../helpers/security.php';
$db = getDB();

$itemId = (int)($_GET['item_id'] ?? 0);
$whId   = isset($_GET['warehouse_id']) && $_GET['warehouse_id'] !== '' ? (int)$_GET['warehouse_id'] : null;
$locId  = isset($_GET['location_id']) && $_GET['location_id'] !== '' ? (int)$_GET['location_id'] : null;

if (!$itemId) {
    echo json_encode(['qty_on_hand' => 0, 'avg_unit_cost' => 0]);
    exit;
}

$bal = InventoryEngine::getCurrentStock($db, $itemId, $whId, $locId);
echo json_encode([
    'qty_on_hand'   => (float)($bal['qty_on_hand'] ?? 0),
    'qty_reserved'  => (float)($bal['qty_reserved'] ?? 0),
    'avg_unit_cost' => (float)($bal['avg_unit_cost'] ?? 0),
    'stock_value'   => (float)($bal['stock_value'] ?? 0),
]);
exit;

