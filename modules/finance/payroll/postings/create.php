<?php
/**
 * Create Payroll Finance Posting
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process form data and create posting
    // This would validate input, create posting record, then redirect
    header('Location: index.php?success=1');
    exit;
}

$db = getDB();
$approvedRunsStmt = $db->prepare("SELECT id, period_name, net_salary FROM payroll_runs WHERE status = 'Approved' ORDER BY id DESC");
$approvedRunsStmt->execute();
$approvedRuns = $approvedRunsStmt->fetchAll();
?>
<div class="max-w-2xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Post Payroll to Finance</h1>
    <form method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div>
            <label class="block mb-1 font-medium">Select Payroll Run</label>
            <select name="payroll_run_id" required class="w-full px-3 py-2 border rounded">
                <option value="">-- Select Approved Payroll Run --</option>
                <?php foreach ($approvedRuns as $run): ?>
                    <option value="<?= $run['id'] ?>">
                        Run #<?= $run['id'] ?> - <?= htmlspecialchars($run['period_name']) ?> (AED <?= number_format($run['net_salary'], 2) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Post to Finance</button>
        <a href="index.php" class="text-blue-600 hover:underline ml-4">Cancel</a>
    </form>
</div>
<?php require_once '../../../includes/footer.php'; ?>
