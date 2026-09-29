<?php
/**
 * AJAX endpoint: check leave balance and LOP status for a given employee + leave type.
 * Returns JSON for the leave application form.
 */
require_once '../../config/database.php';
require_once '../../helpers/security.php';

header('Content-Type: application/json');

if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$employeeId = (int)($_GET['employee_id'] ?? 0);
$leaveTypeId = (int)($_GET['leave_type_id'] ?? 0);
$requestedDays = (float)($_GET['days'] ?? 1);

if (!$employeeId || !$leaveTypeId || $requestedDays <= 0) {
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

try {
    $db = getDB();
    $year = date('Y');

    $stmt = $db->prepare("
        SELECT COALESCE(lb.total_days, lt.total_days) as allocated,
               COALESCE(lb.used_days, 0) as used,
               lt.leave_name, lt.leave_code, lt.total_days as default_days
        FROM leave_types lt
        LEFT JOIN leave_balances lb ON lb.leave_type_id = lt.id
            AND lb.employee_id = ? AND lb.year = ?
        WHERE lt.id = ?
    ");
    $stmt->execute([$employeeId, $year, $leaveTypeId]);
    $balance = $stmt->fetch();

    if (!$balance) {
        echo json_encode(['error' => 'Leave type not found']);
        exit;
    }

    $remaining = $balance['allocated'] - $balance['used'];

    // Count pending leaves that would consume balance if approved
    $pendingStmt = $db->prepare("
        SELECT COALESCE(SUM(total_days), 0) as pending_days
        FROM leave_requests
        WHERE employee_id = ? AND leave_type_id = ? AND status = 'pending'
        AND YEAR(from_date) = ?
    ");
    $pendingStmt->execute([$employeeId, $leaveTypeId, $year]);
    $pendingDays = $pendingStmt->fetch()['pending_days'];

    $effectiveRemaining = $remaining - $pendingDays;

    $data = [
        'allocated' => (float)$balance['allocated'],
        'used' => (float)$balance['used'],
        'remaining' => $effectiveRemaining,
        'pending_days' => (float)$pendingDays,
        'leave_name' => $balance['leave_name'],
        'leave_code' => $balance['leave_code'],
        'requested_days' => $requestedDays,
    ];

    if ($requestedDays <= $effectiveRemaining) {
        $data['status'] = 'ok';
    } elseif ($effectiveRemaining > 0) {
        $data['status'] = 'lop_partial';
        $data['lop_days'] = $requestedDays - $effectiveRemaining;
        $data['covered_days'] = $effectiveRemaining;
    } else {
        $data['status'] = 'lop_full';
        $data['lop_days'] = $requestedDays;
        $data['covered_days'] = 0;
    }

    echo json_encode($data);

} catch (Exception $e) {
    echo json_encode(['error' => 'Server error']);
}
