<?php
$pageTitle = 'Approve Purchase Order';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

if (!hasPermission('purchase_orders.approve')) { die('Access Denied'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { flash('error', 'Invalid request method.'); redirect('index.php'); }
if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('error', 'Invalid or expired form token.'); redirect('index.php'); }

$db = getDB();
$po_id = (int)($_POST['po_id'] ?? 0);
if ($po_id <= 0) { flash('error', 'Invalid PO ID.'); redirect('index.php'); }

$stmt = $db->prepare("SELECT * FROM purchase_orders WHERE id = ?");
$stmt->execute([$po_id]);
$po = $stmt->fetch();
if (!$po) { flash('error', 'Purchase order not found.'); redirect('index.php'); }
if ($po['status'] !== 'draft') { flash('error', 'Only draft POs can be approved.'); redirect('view.php?id=' . $po_id); }

try {
    $db->beginTransaction();

    $stmt = $db->prepare("UPDATE purchase_orders SET status = 'approved', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$po_id]);

    $stmt = $db->prepare("INSERT INTO po_status_history (po_id, from_status, to_status, changed_by, change_note) VALUES (?, 'draft', 'approved', ?, ?)");
    $stmt->execute([$po_id, $_SESSION['user_id'], 'Purchase order approved']);

    $db->commit();
    logAudit($_SESSION['user_id'], 'Approved PO: ' . $po['po_number'], 'purchase_orders');
    flash('success', 'Purchase order ' . $po['po_number'] . ' approved successfully.', 'success');
} catch (Exception $e) {
    $db->rollBack();
    flash('error', 'Error: ' . $e->getMessage());
}

redirect('view.php?id=' . $po_id);
