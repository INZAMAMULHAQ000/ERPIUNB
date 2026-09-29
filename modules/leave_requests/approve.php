<?php
$pageTitle = 'Process Leave Request';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('leaves.approve')) {
    die('Access Denied');
}

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if (!in_array($action, ['approve', 'reject'])) {
    flash('error', 'Invalid action');
    to('modules/leave_requests/index.php');
}

// Fetch full leave request details
$stmt = $db->prepare("
    SELECT lr.*, e.first_name, e.last_name, e.employee_code, lt.leave_name, lt.leave_code
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    WHERE lr.id = ? AND lr.status = 'pending'
");
$stmt->execute([$id]);
$req = $stmt->fetch();

if (!$req) {
    flash('error', 'Leave request not found or already processed');
    to('modules/leave_requests/index.php');
}

// Handle form submission with remarks
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $remarks = $_POST['remarks'] ?? '';
    $decision = $_POST['decision'] ?? $action;
    $newStatus = $decision === 'approve' ? 'approved' : 'rejected';

    try {
        $db->beginTransaction();

        // Update leave request status
        $updateStmt = $db->prepare("UPDATE leave_requests SET status = ?, approved_by = ?, approval_remarks = ? WHERE id = ?");
        $updateStmt->execute([$newStatus, $_SESSION['user_id'], $remarks, $id]);

        // If approved and NOT LOP, update leave balance
        if ($newStatus === 'approved' && !$req['is_lop']) {
            $year = date('Y', strtotime($req['from_date']));
            $days = (float)$req['total_days'];

            // Check if balance record exists
            $balStmt = $db->prepare("SELECT id, used_days FROM leave_balances WHERE employee_id = ? AND leave_type_id = ? AND year = ?");
            $balStmt->execute([$req['employee_id'], $req['leave_type_id'], $year]);
            $balance = $balStmt->fetch();

            if ($balance) {
                // Update existing balance
                $updBal = $db->prepare("UPDATE leave_balances SET used_days = used_days + ? WHERE id = ?");
                $updBal->execute([$days, $balance['id']]);
            } else {
                // Auto-create balance record with default allocation from leave_types
                $ltStmt = $db->prepare("SELECT total_days FROM leave_types WHERE id = ?");
                $ltStmt->execute([$req['leave_type_id']]);
                $defaultDays = $ltStmt->fetch()['total_days'];

                $insBal = $db->prepare("INSERT INTO leave_balances (employee_id, leave_type_id, year, total_days, used_days) VALUES (?, ?, ?, ?, ?)");
                $insBal->execute([$req['employee_id'], $req['leave_type_id'], $year, $defaultDays, $days]);
            }
        }

        $db->commit();

        logAudit($_SESSION['user_id'], $decision . '_leave', 'leaves');
        flash('success', 'Leave request ' . $newStatus . ' successfully');
        to('modules/leave_requests/index.php');

    } catch (Exception $e) {
        $db->rollBack();
        flash('error', 'Failed to process: ' . $e->getMessage(), 'error');
        to('modules/leave_requests/view.php?id=' . $id);
    }
}
?>
<div class="mb-6">
    <a href="<?= url('modules/leave_requests/view.php?id=' . $id) ?>" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Request
    </a>
</div>

<div class="max-w-2xl mx-auto bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-2">
        <?= $action === 'approve' ? 'Approve' : 'Reject' ?> Leave Request
    </h2>
    <p class="text-gray-600 mb-6">Review and process the leave request below.</p>

    <!-- Request Summary -->
    <div class="bg-gray-50 rounded p-4 mb-6 space-y-2">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <span class="text-xs text-gray-500 uppercase tracking-wide">Employee</span>
                <div class="font-medium"><?= sanitize($req['first_name'] . ' ' . $req['last_name']) ?></div>
                <div class="text-sm text-gray-500"><?= sanitize($req['employee_code']) ?></div>
            </div>
            <div>
                <span class="text-xs text-gray-500 uppercase tracking-wide">Leave Type</span>
                <div class="font-medium"><?= sanitize($req['leave_name']) ?> (<?= $req['leave_code'] ?>)</div>
            </div>
            <div>
                <span class="text-xs text-gray-500 uppercase tracking-wide">From</span>
                <div class="font-medium"><?= date('M d, Y', strtotime($req['from_date'])) ?></div>
            </div>
            <div>
                <span class="text-xs text-gray-500 uppercase tracking-wide">To</span>
                <div class="font-medium"><?= date('M d, Y', strtotime($req['to_date'])) ?></div>
            </div>
            <div>
                <span class="text-xs text-gray-500 uppercase tracking-wide">Days</span>
                <div class="font-medium"><?= $req['total_days'] ?></div>
            </div>
            <div>
                <span class="text-xs text-gray-500 uppercase tracking-wide">Status</span>
                <?php if ($req['is_lop']): ?>
                <span class="px-2 py-0.5 text-xs rounded bg-red-100 text-red-800 font-medium">Includes LOP</span>
                <?php else: ?>
                <span class="px-2 py-0.5 text-xs rounded bg-yellow-100 text-yellow-800">Pending</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="pt-2 border-t">
            <span class="text-xs text-gray-500 uppercase tracking-wide">Reason</span>
            <p class="text-gray-700 mt-1"><?= sanitize($req['reason']) ?></p>
        </div>
        <?php if ($req['is_lop']): ?>
        <div class="pt-2 border-t">
            <div class="bg-red-50 text-red-700 text-sm p-2 rounded">
                <i class="fas fa-exclamation-circle mr-1"></i> This leave has been flagged as Loss of Pay (LOP) due to insufficient balance. No balance will be deducted.
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Approval Form -->
    <form method="POST">
        <input type="hidden" name="decision" value="<?= $action ?>">

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Approval Remarks</label>
            <textarea name="remarks" rows="3" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                placeholder="Optional remarks..."><?= old('remarks') ?></textarea>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="<?= $action === 'approve' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700' ?> text-white px-6 py-2 rounded transition">
                <i class="fas fa-<?= $action === 'approve' ? 'check' : 'times' ?> mr-2"></i>
                <?= ucfirst($action) ?> Leave
            </button>
            <a href="<?= url('modules/leave_requests/view.php?id=' . $id) ?>" class="text-gray-600 px-4 py-2 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
