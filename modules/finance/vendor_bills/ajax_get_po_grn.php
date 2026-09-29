<?php
/**
 * AJAX endpoint: Returns eligible POs and GRNs for a given vendor.
 * Used by vendor_bills/create.php and edit.php to populate PO/GRN dropdowns.
 */
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/middleware/auth.php';
requireAuth();

header('Content-Type: application/json');

$vendor_id = (int)($_GET['vendor_id'] ?? 0);
if ($vendor_id <= 0) { echo json_encode(['error' => 'Invalid vendor ID']); exit; }

$db = getDB();

// Eligible POs: approved/sent/partially_received/received (not draft, closed, cancelled)
$pos = $db->prepare("
    SELECT po.id, po.po_number, po.total_amount, po.status,
        (SELECT COALESCE(SUM(vb.total_amount), 0) FROM vendor_bills vb WHERE vb.po_id = po.id AND vb.status NOT IN ('cancelled')) AS total_billed
    FROM purchase_orders po
    WHERE po.vendor_id = ? AND po.status NOT IN ('draft', 'cancelled', 'closed')
    ORDER BY po.po_number DESC
");
$pos->execute([$vendor_id]);
$posData = $pos->fetchAll();

// Eligible GRNs: completed GRNs for this vendor
$grns = $db->prepare("
    SELECT grn.id, grn.grn_number, grn.receipt_date,
        po.po_number
    FROM goods_receipt_notes grn
    JOIN purchase_orders po ON grn.po_id = po.id
    WHERE grn.vendor_id = ? AND grn.status = 'completed'
    ORDER BY grn.grn_number DESC
");
$grns->execute([$vendor_id]);
$grnsData = $grns->fetchAll();

echo json_encode([
    'pos' => $posData,
    'grns' => $grnsData,
]);
