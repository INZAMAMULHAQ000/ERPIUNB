<?php
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('procurement.bills.override')) { die('Access Denied'); }

$db = getDB();
$exceptionId = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($exceptionId <= 0 || !in_array($action, ['resolve', 'override', 'reject'], true)) {
    flash('error', 'Invalid request.');
    redirect('index.php');
}

$ex = $db->prepare("SELECT * FROM vendor_bill_exceptions WHERE id=?");
$ex->execute([$exceptionId]);
$exception = $ex->fetch();
if (!$exception) { flash('error', 'Exception not found.'); redirect('index.php'); }

$billId = (int)$exception['bill_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid or expired form token.');
        redirect('view.php?id=' . $billId);
    }

    $reason = trim((string)($_POST['reason'] ?? ''));
    $overrideReason = trim((string)($_POST['override_reason'] ?? ''));

    if ($action === 'override' && empty($overrideReason)) {
        flash('error', 'Override reason is required for override action.');
        redirect('exception.php?id=' . $exceptionId . '&action=' . $action);
    }

    $result = BillService::resolveException($db, $billId, $_SESSION['user_id'], $action, $reason, $overrideReason);

    if ($result['success']) {
        flash('success', 'Exception ' . $action . 'd successfully.');
    } else {
        flash('error', $result['message']);
    }
    redirect('view.php?id=' . $billId);
}

$bill = BillService::getBill($db, $billId);
?>

<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white shadow rounded-lg p-6">
        <h1 class="text-xl font-bold text-gray-900 mb-4"><?= ucfirst($action) ?> Match Exception</h1>
        <div class="bg-gray-50 rounded-lg p-4 mb-6 text-sm">
            <p><strong>Bill:</strong> <?= sanitize($bill['bill_number']) ?></p>
            <p><strong>Exception Type:</strong> <?= ucfirst(str_replace('_', ' ', $exception['exception_type'])) ?></p>
            <p><strong>Severity:</strong> <?= ucfirst($exception['severity']) ?></p>
            <p><strong>Reason:</strong> <?= sanitize($exception['reason']) ?></p>
            <p><strong>Difference:</strong> <?= number_format((float)$exception['difference'], 2) ?> (<?= number_format((float)$exception['difference_percent'], 2) ?>%)</p>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Reason / Comments <span class="text-red-600">*</span></labe>
                <textarea name="reason" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" required placeholder="Explain your decision..."></textarea>
            </div>
            <?php if ($action === 'override'): ?>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Override Reason <span class="text-red-600">*</span></labe>
                <textarea name="override_reason" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" required placeholder="Override justification (required for overrides)..."></textarea>
            </div>
            <?php endif; ?>
            <div class="flex justify-end gap-3">
                <a href="view.php?id=<?= $billId ?>" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg text-sm font-medium">Cancel</a>
                <button type="submit" class="bg-<?= $action === 'override' ? 'yellow' : ($action === 'reject' ? 'red' : 'green') ?>-600 hover:bg-<?= $action === 'override' ? 'yellow' : ($action === 'reject' ? 'red' : 'green') ?>-700 text-white px-6 py-2 rounded-lg text-sm font-medium"><?= ucfirst($action) ?></button>
            </div>
        </form>
    </div>
</div>
