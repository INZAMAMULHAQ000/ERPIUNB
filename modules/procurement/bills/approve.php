<?php
$pageTitle = 'Approve Vendor Bill';
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('procurement.bills.approve')) { die('Access Denied'); }

$db = getDB();
$billId = (int)($_GET['id'] ?? 0);
if ($billId <= 0) { flash('error', 'Invalid bill ID.'); redirect('index.php'); }

$bill = BillService::getBill($db, $billId);
if (!$bill) { flash('error', 'Bill not found.'); redirect('index.php'); }
if (!in_array($bill['status'], ['pending_approval', 'matched'], true)) {
    flash('error', 'Only pending or matched bills can be approved.');
    redirect('view.php?id=' . $billId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid or expired form token. Please try again.');
        redirect('view.php?id=' . $billId);
    }

    $comments = trim((string)($_POST['comments'] ?? ''));
    $result = BillService::approve($db, $billId, $_SESSION['user_id'], $comments);

    if ($result['success']) {
        flash('success', 'Vendor bill approved successfully.');
    } else {
        flash('error', $result['message']);
    }
    redirect('view.php?id=' . $billId);
}
?>

<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white shadow rounded-lg p-6">
        <h1 class="text-xl font-bold text-gray-900 mb-4">Approve Vendor Bill</h1>
        <p class="text-sm text-gray-600 mb-4">You are approving bill <strong><?= sanitize($bill['bill_number']) ?></strong> for vendor <strong><?= sanitize($bill['vendor_name']) ?></strong> with total amount <strong><?= number_format((float)$bill['total_amount'], 2) ?></strong>.</p>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Comments (optional)</label>
                <textarea name="comments" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="Approval comments..."></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <a href="view.php?id=<?= $bill['id'] ?>" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg text-sm font-medium">Cancel</a>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg text-sm font-medium">Approve Bill</button>
            </div>
        </form>
    </div>
</div>
