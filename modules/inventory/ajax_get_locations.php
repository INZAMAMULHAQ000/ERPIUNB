<?php
header('Content-Type: application/json');
require_once '../../includes/header.php';
require_once __DIR__ . '/InventoryEngine.php';
require_once '../../helpers/security.php';
$db = getDB();

$action = $_GET['action'] ?? '';
$whId = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : 0;

if ($action === 'locations' && $whId) {
    $stmt = $db->prepare("SELECT id, location_code, location_name FROM locations WHERE warehouse_id = ? AND status='active' ORDER BY location_name");
    $stmt->execute([$whId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($action === 'stock') {
    $itemId = (int)($_GET['item_id'] ?? 0);
    $locId  = isset($_GET['location_id']) && $_GET['location_id'] !== '' ? (int)$_GET['location_id'] : null;
    $wh = isset($_GET['warehouse_id']) && $_GET['warehouse_id'] !== '' ? (int)$_GET['warehouse_id'] : null;
    if (!$itemId) { echo json_encode(['qty_on_hand'=>0,'avg_unit_cost'=>0]); exit; }
    $bal = InventoryEngine::getCurrentStock($db, $itemId, $wh, $locId);
    echo json_encode($bal);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
exit;

