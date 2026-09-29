<?php
/**
 * AJAX endpoint: Load open vendor bills for a given vendor.
 * Used by vendor_payments/create.php and allocate.php.
 */
require_once '../../../includes/header.php';

if (!hasPermission('vendor_payments.create') && !hasPermission('vendor_payments.view')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit;
}

$db = getDB();
$vendor_id = (int)($_GET['vendor_id'] ?? 0);

if ($vendor_id <= 0) {
    echo json_encode(['error' => 'Invalid vendor ID.']);
    exit;
}

$stmt = $db->prepare("
    SELECT id, bill_number, bill_date, due_date, total_amount, balance_amount
    FROM vendor_bills
    WHERE vendor_id = ? AND balance_amount > 0 AND status IN ('approved', 'partially_paid', 'overdue')
    ORDER BY due_date ASC
");
$stmt->execute([$vendor_id]);
$bills = $stmt->fetchAll();

// Format monetary values
foreach ($bills as &$b) {
    $b['total_amount'] = number_format((float)$b['total_amount'], 2, '.', '');
    $b['balance_amount'] = number_format((float)$b['balance_amount'], 2, '.', '');
    $b['bill_date'] = date('M d, Y', strtotime($b['bill_date']));
    $b['due_date'] = date('M d, Y', strtotime($b['due_date']));
}

header('Content-Type: application/json');
echo json_encode($bills);
