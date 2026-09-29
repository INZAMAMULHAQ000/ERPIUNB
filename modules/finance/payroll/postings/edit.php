<?php
/**
 * Edit Payroll Finance Posting
 *
 * @package XXX
 * @submodule Finance.Payroll.Postings
 */
require_once __DIR__ . '/../../../../helpers/rbac.php';
require_once __DIR__ . '/../../../../helpers/redirect.php';
require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../../../helpers/finance_helpers.php';

// Check permission
if (!hasPermission('payrollfinance.post')) {
    redirect_to('no-access.php');
}

// Get posting ID from URL
$postingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($postingId <= 0) {
    redirect_to('index.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validate and update posting
    flash('success', 'Posting updated.');
    redirect_to('index.php');
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM payroll_finance_postings WHERE id = ?");
$stmt->execute([$postingId]);
$posting = $stmt->fetch();
?>
<div class="max-w-2xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Edit Posting #<?= $postingId ?></h1>
    <p class="text-gray-600">Posting ID: <?= $postingId ?></p>
    <p class="text-gray-600">Status: <?= htmlspecialchars($posting['status'] ?? 'N/A') ?></p>
    <a href="index.php" class="text-blue-600 hover:underline mt-4 inline-block">Back to List</a>
</div>
<?php require_once '../../../includes/footer.php'; ?>
