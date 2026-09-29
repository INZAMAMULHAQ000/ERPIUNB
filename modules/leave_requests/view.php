<?php
$pageTitle = 'Leave Request Details';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('leaves.view')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT lr.*, e.first_name, e.last_name, e.employee_code, e.department_id,
    lt.leave_name, lt.leave_code, lt.total_days,
    u.name as approver_name
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    LEFT JOIN users u ON lr.approved_by = u.id
    WHERE lr.id = ?
");
$stmt->execute([$id]);
$req = $stmt->fetch();

if (!$req) {
    flash('error', 'Leave request not found');
    to('http://localhost/ERP/modules/leave_requests/index.php');
}

$deptStmt = $db->prepare("SELECT department_name FROM departments WHERE id = ?");
$deptStmt->execute([$req['department_id']]);
$dept = $deptStmt->fetch();

$statusColors = ['pending' => 'bg-yellow-100 text-yellow-800', 'approved' => 'bg-green-100 text-green-800', 'rejected' => 'bg-red-100 text-red-800', 'cancelled' => 'bg-gray-100 text-gray-800'];
?>

<div class="mb-6">
    <a href="<?= url('modules/leave_requests/index.php') ?>" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Leave Requests
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-start mb-6">
        <h2 class="text-xl font-semibold">Leave Request Details</h2>
        <span class="px-3 py-1 text-sm rounded <?= $statusColors[$req['status']] ?? 'bg-gray-100' ?>">
            <?= ucfirst($req['status']) ?>
        </span>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <h3 class="text-lg font-medium mb-3">Employee Information</h3>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span class="text-gray-500">Name:</span>
                    <span class="font-medium"><?= sanitize($req['first_name'] . ' ' . $req['last_name']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Employee Code:</span>
                    <span><?= sanitize($req['employee_code']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Department:</span>
                    <span><?= sanitize($dept['department_name'] ?? '-') ?></span>
                </div>
            </div>
        </div>
        
        <div>
            <h3 class="text-lg font-medium mb-3">Leave Details</h3>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span class="text-gray-500">Leave Type:</span>
                    <span class="font-medium"><?= sanitize($req['leave_name']) ?> (<?= $req['leave_code'] ?>)</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">From Date:</span>
                    <span><?= date('M d, Y', strtotime($req['from_date'])) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">To Date:</span>
                    <span><?= date('M d, Y', strtotime($req['to_date'])) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Total Days:</span>
                    <span class="font-medium"><?= $req['total_days'] ?> days</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mt-6">
        <h3 class="text-lg font-medium mb-2">Reason</h3>
        <p class="text-gray-700 bg-gray-50 p-3 rounded"><?= sanitize($req['reason']) ?></p>
    </div>
    
    <?php if ($req['status'] !== 'pending'): ?>
    <div class="mt-6 pt-6 border-t">
        <h3 class="text-lg font-medium mb-3">Approval Information</h3>
        <div class="space-y-2">
            <div class="flex justify-between">
                <span class="text-gray-500">Processed By:</span>
                <span><?= sanitize($req['approver_name'] ?? '-') ?></span>
            </div>
            <?php if ($req['approval_remarks']): ?>
            <div>
                <span class="text-gray-500">Remarks:</span>
                <p class="mt-1 text-gray-700"><?= sanitize($req['approval_remarks']) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (hasPermission('leaves.approve') && $req['status'] === 'pending'): ?>
    <div class="mt-6 pt-6 border-t flex gap-4">
        <a href="<?= url('modules/leave_requests/approve.php?id=' . $id . '&action=approve') ?>" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700" onclick="return confirm('Approve this leave request?')">
            <i class="fas fa-check mr-2"></i>Approve
        </a>
        <a href="<?= url('modules/leave_requests/approve.php?id=' . $id . '&action=reject') ?>" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700" onclick="return confirm('Reject this leave request?')">
            <i class="fas fa-times mr-2"></i>Reject
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>

