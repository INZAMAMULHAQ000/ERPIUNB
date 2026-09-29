<?php
$pageTitle = 'Cancel Goods Receipt Note';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('goods_receipt.cancel')) { die('Access Denied'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { flash('error', 'Invalid request method.'); redirect('index.php'); }
if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid or expired form token.'); redirect('index.php'); }

$db = getDB();
$grn_id = (int)($_POST['grn_id'] ?? 0);
if ($grn_id <= 0) { flash('error', 'Invalid GRN ID.'); redirect('index.php'); }

$stmt = $db->prepare("SELECT * FROM goods_receipt_notes WHERE id = ?");
$stmt->execute([$grn_id]);
$grn = $stmt->fetch();
if (!$grn) { flash('error', 'GRN not found.'); redirect('index.php'); }
if ($grn['status'] !== 'completed') { flash('error', 'Only completed GRNs can be cancelled.'); redirect('view.php?id=' . $grn_id); }

try {
    $db->beginTransaction();

    // Roll back PO item received quantities
    $items = $db->prepare("SELECT * FROM finance_goods_receipt_items WHERE grn_id = ?");
    $items->execute([$grn_id]);
    $rollbackStmt = $db->prepare("UPDATE purchase_order_items SET received_quantity = GREATEST(0, received_quantity - ?) WHERE id = ?");

    while ($item = $items->fetch()) {
        $rollbackStmt->execute([$item['quantity_received'], $item['po_item_id']]);
    }

    // Update GRN status
    $stmt = $db->prepare("UPDATE goods_receipt_notes SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$grn_id]);

    // Recalculate PO status
    $checkStatus = $db->prepare("SELECT COUNT(*) FROM purchase_order_items WHERE po_id = ? AND quantity > received_quantity");
    $checkStatus->execute([$grn['po_id']]);
    $pendingCount = (int)$checkStatus->fetchColumn();

    if ($pendingCount === 0) {
        $db->prepare("UPDATE purchase_orders SET status = 'received', updated_at = NOW() WHERE id = ? AND status IN ('partially_received','received')")->execute([$grn['po_id']]);
    } else {
        $db->prepare("UPDATE purchase_orders SET status = 'approved', updated_at = NOW() WHERE id = ? AND status = 'partially_received'")->execute([$grn['po_id']]);
    }

    $db->commit();
    logAudit($_SESSION['user_id'], 'Cancelled GRN: ' . $grn['grn_number'], 'goods_receipt');
    flash('success', 'Goods receipt note cancelled. PO item quantities rolled back.', 'success');
} catch (Exception $e) {
    $db->rollBack();
    flash('error', 'Error: ' . $e->getMessage());
}

redirect('view.php?id=' . $grn_id);
