<?php
/**
 * AJAX endpoint: Returns PO items for a given PO ID.
 * Used by goods_receipt/create.php to load items for receiving.
 */
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/middleware/auth.php';
requireAuth();

header('Content-Type: application/json');

$po_id = (int)($_GET['po_id'] ?? 0);
if ($po_id <= 0) { echo json_encode(['error' => 'Invalid PO ID']); exit; }

$db = getDB();

// Verify PO exists and is receivable
$stmt = $db->prepare("SELECT status FROM purchase_orders WHERE id = ?");
$stmt->execute([$po_id]);
$po = $stmt->fetch();
if (!$po) { echo json_encode(['error' => 'PO not found']); exit; }
if (!in_array($po['status'], ['approved','sent','partially_received'])) {
    // Still allow loading items even if not in receiving status (for informational purposes)
}

$stmt = $db->prepare("SELECT * FROM purchase_order_items WHERE po_id = ? ORDER BY sort_order");
$stmt->execute([$po_id]);
$items = $stmt->fetchAll();

echo json_encode($items);
